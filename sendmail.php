<?php

#------------------------------------------------------
# INFO
#------------------------------------------------------
# This is part of the Post Notification Plugin for
# Wordpress. Please see the readme.txt for details.
#------------------------------------------------------

function post_notification_arrayreplace( $input, $array ) {
	foreach ( $array as $s => $r ) {
		$input = str_replace( $s, $r, $input );
	}

	return $input;
}

/**
 * Build the notification email payload for a post.
 *
 * - Rendert den Beitrag mit pn_render_content_for_email() (inkl. Block-/Shortcode-Support).
 * - Optional schneidet „more“ bzw. „excerpt“ zu.
 * - Sanitiert HTML für E-Mail und macht Links/Bilder absolut.
 * - Baut Betreff, Body, Header und Metadaten.
 *
 * @param int         $id        Post ID.
 * @param string      $template  Template-Dateiname (z. B. 'default.html'); leer = Option verwenden.
 * @return array|false { 'id','subject','body','header','title' } oder false bei Fehler.
 */
function post_notification_create_email( $id, $template = '' ) {
	$post = get_post( $id );
	if ( ! $post ) {
		return false;
	}

	$blogname   = get_bloginfo( 'name' );
	$post_url   = get_permalink( $post );
	$post_title = wp_strip_all_tags( get_the_title( $post ) );
	$author     = get_userdata( $post->post_author );
	$post_author = $author ? $author->display_name : '';

	// Template & Mailtyp
	if ( $template === '' ) {
		$template = (string) get_option( 'post_notification_template' );
	}
	$html_email = ( substr( $template, -5 ) === '.html' );

	// 1) Beitrag in „kuratiertem“ E-Mail-HTML rendern (inkl. Block-/Shortcode-Pipeline & Exclude-UI)
	$content_html = pn_render_content_for_email( $id, 'exclude' );

	// 2) „more“ / „excerpt“ anwenden (nach dem Rendern, damit Blöcke/Shortcodes zuerst laufen)
	$show_mode = (string) get_option( 'post_notification_show_content' );
	if ( $show_mode === 'more' ) {
		// Anker anhand des <!--more--> Markers schneiden
		$parts        = explode( '<!--more', $content_html, 2 );
		$content_html = $parts[0];
		if ( ! empty( $parts[1] ) ) {
			$read_more = esc_html( (string) get_option( 'post_notification_read_more' ) );
			$content_html .= '<p><a href="@@permalink">' . $read_more . '</a></p>';
		}
	} elseif ( $show_mode === 'excerpt' ) {
		// Wenn WP-Auszug existiert, den nehmen – sonst HTML-schonend kürzen
		if ( ! empty( $post->post_excerpt ) ) {
			$content_html = wpautop( wp_kses_post( $post->post_excerpt ) );
		} else {
			$content_html = pn_trim_html_words( $content_html, 55, '…' );
		}
		$read_more = esc_html( (string) get_option( 'post_notification_read_more' ) );
		$content_html .= '<p><a href="@@permalink">' . $read_more . '</a></p>';
	}

	// 3) Sanitize: auf E-Mail-Subset einschränken & href/src absolut auf https
	$content_html = pn_email_sanitize_html( $content_html, home_url( '/', 'https' ) );

	// 4) Plain-Text Ableitung (nur wenn Textmail)
	$content_for_body = $html_email ? $content_html : post_notification_html_to_text( $content_html );

	// 5) Template laden & Variablen ersetzen
	$body = (string) post_notification_ldfile( $template );
	$body = str_replace(
		['@@content','@@title','@@permalink','@@author','@@time','@@date'],
		[
			$content_for_body,
			$post_title,
			$post_url,
			$post_author,
			mysql2date( get_option( 'time_format' ), $post->post_date ),
			mysql2date( get_option( 'date_format' ), $post->post_date ),
		],
		$body
	);

	// 6) User-replacements (falls vorhanden)
	if ( function_exists( 'post_notification_uf_perPost' ) ) {
		$body = post_notification_arrayreplace( $body, post_notification_uf_perPost( $id ) );
	}

	// 7) Header bauen
	$header = post_notification_header( $html_email );

	// 8) Betreff bauen
	$subject = (string) get_option( 'post_notification_subject' );
	$subject = str_replace(
		['@@blogname','@@title'],
		[ $blogname, ( $post_title !== '' ? $post_title : __( 'New post', 'post_notification' ) ) ],
		$subject
	);
	$subject = post_notification_encode( $subject, get_option( 'blog_charset' ) );

	return [
		'id'      => $id,
		'subject' => $subject,
		'body'    => $body,
		'header'  => $header,
		'title'   => $post_title,
	];
}

/**
 * Kürzt bereits gerendertes HTML auf N Wörter, balanciert Tags und erhält Basis-Markup.
 *
 * @param string $html
 * @param int    $words
 * @param string $more
 * @return string
 */
function pn_trim_html_words( string $html, int $words = 55, string $more = '…' ) : string {
	// Text extrahieren, Wortanzahl begrenzen
	$text  = wp_strip_all_tags( $html );
	$parts = preg_split( '/\s+/', trim( $text ) );
	if ( is_array( $parts ) && count( $parts ) > $words ) {
		$parts = array_slice( $parts, 0, $words );
		$text  = implode( ' ', $parts ) . $more;
	}
	// Minimal wieder mit <p> verpacken
	return wpautop( wp_kses_post( $text ) );
}

/**
 * Render post content for email with a controlled subset of `the_content` filters.
 * - Honors your "include/exclude" list
 * - Temporarily removes disallowed filters, runs the_content, then restores them
 * - Disables email-hostile filters by default (e.g., autoembed)
 *
 * @param int    $post_id
 * @param string $mode 'include' or 'exclude' (how your settings are interpreted)
 * @return string Rendered HTML safe-ish for email
 */
function pn_render_content_for_email( int $post_id, string $mode = 'exclude' ): string {
	global $wp_filter, $post;

	// 1) Load post & prepare global $post for filters/shortcodes that rely on loop context.
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	setup_postdata( $post );

	// 2) Start with raw content (don’t strip shortcodes here — we want allowed filters to run).
	$content = get_post_field( 'post_content', $post_id );

	// Optional: handle your “more” / “excerpt” options BEFORE running filters, if you want to
	// keep the email short. Otherwise, do it AFTER render with a HTML truncator.

	// 3) Figure out which callbacks to detach.
	//    - Pull user selection from your settings helper (array of callback IDs).
	//    - Always force-remove a few email-hostile filters (iframes, heavy builders).
	$user_list = pn_get_saved_the_content_excludes(); // or pn_get_includes() if you flipped semantics
	$force_blocklist = array(
		// Explicitly block WordPress oEmbed callbacks which can pull remote HTML/JS
		'WP_Embed::autoembed',               // turns plain URLs into embeds (iframes)
		'WP_Embed::run_shortcode',           // [embed] shortcodes → iframes
		// Keep legacy short name for setups that stringify differently
		'autoembed',
		// Misc callbacks that add non-email-friendly chrome
		'prepend_attachment',                // irrelevant for email
		'builder_wrapper',                   // typical page-builder chrome
		'advanced_hook_content_markup',      // theme/builder wrappers
		'_render_related_images',            // complex grids
	);

	// Normalize to a set we can check quickly.
	$user_set   = is_array( $user_list ) ? array_flip( $user_list ) : array();
	$force_set  = array_flip( $force_blocklist );

	// 4) Collect active the_content filters and decide which to remove.
	$removed = array(); // keep what we remove so we can restore it later

	if ( isset( $wp_filter['the_content'] ) && $wp_filter['the_content'] instanceof WP_Hook ) {
		// Snapshot current callbacks grouped by priority.
		$callbacks = $wp_filter['the_content']->callbacks;

		foreach ( $callbacks as $priority => $group ) {
			foreach ( $group as $unique_id => $entry ) {
				// Derive a readable name for matching against your stored IDs.
				$callback_id = pn_callback_to_id( $entry['function'] ); // same helper you already use

				$should_remove = false;

				if ( isset( $force_set[ $callback_id ] ) ) {
					$should_remove = true;
				} elseif ( 'exclude' === $mode && isset( $user_set[ $callback_id ] ) ) {
					$should_remove = true;
				} elseif ( 'include' === $mode && ! isset( $user_set[ $callback_id ] ) ) {
					$should_remove = true;
				}

				if ( $should_remove ) {
					// Temporarily remove it and record for restoration.
					remove_filter( 'the_content', $entry['function'], (int) $priority );
					$removed[] = array(
						'fn'       => $entry['function'],
						'priority' => (int) $priority,
					);
				}
			}
		}
	}

	// 5) Make sure the bare minimum stays ON for emails (block rendering + basic formatting).
	//    These are core-safe and useful in email HTML.
	//    If they’re already attached, add_filter will be a no-op due to unique IDs.
	add_filter( 'the_content', 'do_blocks', 9 );
	add_filter( 'the_content', 'wptexturize', 10 );
	add_filter( 'the_content', 'wpautop', 10 );
	add_filter( 'the_content', 'shortcode_unautop', 10 );
	add_filter( 'the_content', 'wp_replace_insecure_home_url', 10 );

	// 6) Run the normal pipeline now that we’ve curated it.
	$rendered = apply_filters( 'the_content', $content );

	// 7) Restore previously removed callbacks to leave WP in a clean state.
	foreach ( $removed as $r ) {
		add_filter( 'the_content', $r['fn'], $r['priority'] );
	}

	// 8) Email-oriented sanitation/absolutization you already have.
	$rendered = pn_email_sanitize_html( $rendered, home_url( '/', 'https' ) );

	// 9) Cleanup loop globals.
	wp_reset_postdata();

	return $rendered;
}

/**
 * Minimal helper to stringify a callback into your stable ID format.
 * Must match what you store in the DB (same logic as your checklist).
 */
function pn_callback_to_id( $callback ): string {
	if ( is_string( $callback ) ) {
		return $callback;
	}
	if ( is_array( $callback ) && is_object( $callback[0] ) ) {
		return get_class( $callback[0] ) . '::' . $callback[1];
	}
	if ( is_array( $callback ) ) {
		return $callback[0] . '::' . $callback[1];
	}
	if ( $callback instanceof Closure ) {
		return 'closure';
	}
	return 'unknown';
}


function pn_email_sanitize_html( string $html, string $base = '' ): string {
	$logger = function_exists('add_pn_logger') ? add_pn_logger('pn') : null;

	// strip hard-disallowed containers early
	$before = $html;
	$html = preg_replace( '#<(script|style|iframe|object|embed|base|link)\b[^>]*>.*?</\1>#is', '', $html );
	// also remove stray self-closing base/link tags
	$html = preg_replace( '#<\s*(base|link)\b[^>]*\/?>#is', '', $html );

	if ( $logger && $before !== $html ) {
		$logger->info('pn_email_sanitize_html: stripped disallowed tags from content');
	}

	// absolutize href/src on a and img only
	$dom = new DOMDocument( '1.0', 'UTF-8' );
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<div id="r">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	$home = rtrim( $base ?: home_url( '/', 'https' ), '/' );

	foreach ( [ 'a' => 'href', 'img' => 'src' ] as $tag => $attr ) {
		$nodes = $dom->getElementsByTagName( $tag );
		// NodeList is live; collect first
		$els = [];
		foreach ( $nodes as $n ) { $els[] = $n; }
		foreach ( $els as $el ) {
			if ( ! $el->hasAttribute( $attr ) ) {
				continue;
			}
			$u = $el->getAttribute( $attr );
			if ( $u === '' || $u[0] === '#' ) {
				continue;
			}
			// skip dangerous schemes early; wp_kses will also enforce, but avoid leaking into previews
			if ( preg_match( '#^(javascript|data):#i', $u ) ) {
				$el->removeAttribute( $attr );
				continue;
			}
			if ( strpos( $u, '//' ) === 0 ) {
				$el->setAttribute( $attr, 'https:' . $u );
				continue;
			}
			if ( ! preg_match( '#^[a-z][a-z0-9+\-.]*://#i', $u ) ) {
				$rel = $u;
				// normalize dot-segments when base is site root
				while ( strpos( $rel, '../' ) === 0 ) { $rel = substr( $rel, 3 ); }
				if ( strpos( $rel, './' ) === 0 ) { $rel = substr( $rel, 2 ); }
				$el->setAttribute( $attr, ( $rel[0] === '/' ? $home . $rel : $home . '/' . ltrim( $rel, '/' ) ) );
			}
		}
	}
	$root = $dom->getElementById( 'r' );
	$out  = '';
	if ( $root ) {
		foreach ( $root->childNodes as $c ) {
			$out .= $dom->saveHTML( $c );
		}
	}
	libxml_clear_errors();

	// allow only email-friendly tags + a minimal style attr
	$allowed = [
		'a'      => [ 'href' => true, 'title' => true, 'target' => true, 'rel' => true, 'style' => true ],
		'p'      => [ 'style' => true ],
		'br'     => [],
		'strong' => [],
		'em'     => [],
		'b'      => [],
		'i'      => [],
		'u'      => [],
		'span'   => [ 'style' => true ],
		'ul'     => [ 'style' => true ],
		'ol'     => [ 'style' => true ],
		'li'     => [ 'style' => true ],
		'img'    => [
			'src'    => true,
			'alt'    => true,
			'width'  => true,
			'height' => true,
			'style'  => true,
			'border' => true,
		],
		'h1'     => [ 'style' => true ],
		'h2'     => [ 'style' => true ],
		'h3'     => [ 'style' => true ],
		'hr'     => [ 'style' => true ],
		'table'  => [
			'width'       => true,
			'cellpadding' => true,
			'cellspacing' => true,
			'border'      => true,
			'align'       => true,
			'role'        => true,
			'style'       => true,
		],
		'tbody'  => [],
		'tr'     => [],
		'td'     => [ 'width' => true, 'align' => true, 'valign' => true, 'style' => true ],
	];
	$sanitized = wp_kses( $out, $allowed );

	if ( $logger && $sanitized !== $out ) {
		$logger->info('pn_email_sanitize_html: removed disallowed attributes/tags via wp_kses');
	}

	// remove empty <p>
	$sanitized = preg_replace( '#<p>\s*(?:&nbsp;|\xC2\xA0|\s)*</p>#i', '', $sanitized );

	return trim( $sanitized );
}


function post_notification_sendmail( $maildata, $addr, $code = '', $send = true ) {
	$maildata['body'] = str_replace( '@@addr', $addr, $maildata['body'] );

	$conf_url = post_notification_get_mailurl( $addr, $code );


	$maildata['body'] = str_replace( '@@unsub', $conf_url, $maildata['body'] );
	$maildata['body'] = str_replace( '@@conf_url', $conf_url, $maildata['body'] );
	//User replacements
	if ( function_exists( 'post_notificataion_uf_perEmail' ) ) {
		$maildata['body'] = post_notification_arrayreplace( $maildata['body'], post_notificataion_uf_perEmail( $maildata['id'], $addr ) );
	}

	if ( \get_option( 'post_notification_unsubscribe_link_in_header' ) == 'yes' ) {
		$maildata['header'] = post_notification_add_additional_headers( $addr, $maildata, $code );
	}
	// Send mail to debug address
	$addr = "postnotification@bome.com";
	if ( $send ) {
		// Use woocommerce mailer if installed and activated in PN Settings
		// Route via configured mailer method (WP, PN SMTP, or WC)
		$maildata['sent'] = pn_send_mail( $addr, $maildata['subject'], $maildata['body'], $maildata['header'], array() );
	} else {
		$maildata['sent'] = false;
	}

	return $maildata;
}


/// Send mails.
function post_notification_send() {
	global $wpdb, $timestart;

	if ( get_option( 'post_notification_lock' ) == 'db' ) {
		if ( ! $wpdb->get_var( "SELECT GET_LOCK('" . $wpdb->prefix . 'post_notification_lock' . "', 0)" ) ) {
			return;
		}
	} else {
		$mutex = @fopen( POST_NOTIFICATION_PATH . '_temp/post_notification.lock', 'w' );


		if ( ! $mutex || ! flock( $mutex, LOCK_EX | LOCK_NB, $eWouldBlock ) || $eWouldBlock ) {
			// There is already someone mailing.
			@fclose( $mutex );

			return;
		}
	}

	//Make sure plugins don't think we're a page or something....

	$GLOBALS['wp_query']->init_query_flags();

	ignore_user_abort( true ); //Let's get this done....

	//some general stuff
	$t_emails = $wpdb->prefix . 'post_notification_emails';
	$t_posts  = $wpdb->prefix . 'post_notification_posts';
	$t_cats   = $wpdb->prefix . 'post_notification_cats';

	$posts = $wpdb->get_results( "SELECT id, notification_sent " . post_notification_sql_posts_to_send() );


	if ( ! $posts ) { //This shouldn't happen, but never mind.
		post_notification_set_next_send();

		return; //Nothing to do.
	}

	//Include user functions, if they exist.
	//We don't want an error if the file does not exist.
	if ( file_exists( POST_NOTIFICATION_PATH . 'userfunctions.php' ) ) {
		include_once( POST_NOTIFICATION_PATH . 'userfunctions.php' );
	}


	// Mail out mails
	$maxsend   = get_option( 'post_notification_maxsend' );
	$mailssent = - 1;
	$endtime   = ini_get( 'max_execution_time' );
	if ( $endtime != 0 ) {
		$endtime += floor( $timestart ) - 5; //Make shure we will have a least 5 sek left.
	}
	$time_remain = 1;
	foreach ( $posts as $post ) {
		if ( get_option( 'post_notification_debug' ) == 'yes' ) {
			echo '<hr />Sending post: ' . $post->id . '<br />';
		}

		//Find the categories
		if ( get_option( 'db_version' ) < 6124 ) {
			$cats = $wpdb->get_results( "SELECT category_id FROM " . $wpdb->post2cat . " WHERE post_id = " . $post->id );
		} else {
			$cats = $wpdb->get_results( "SELECT term_id
										FROM " . $wpdb->term_relationships . "
										JOIN " . $wpdb->term_taxonomy . " USING (term_taxonomy_id)
										WHERE taxonomy = 'category' AND object_id = " . $post->id );
		}
		$cat_ids = array();

		foreach ( $cats as $cat ) {
			if ( get_option( 'db_version' ) < 6124 ) {
				$last_cat = $cat->category_id;
			} else {
				$last_cat = $cat->term_id;
			}
			while ( $last_cat != 0 ) {
				$cat_ids[] = (string) $last_cat;
				if ( get_option( 'db_version' ) < 6124 ) {
					$last_cat = $wpdb->get_var( "SELECT category_parent FROM " . $wpdb->categories . " WHERE cat_ID = $last_cat" );
				} else {
					$last_cat = $wpdb->get_var( "SELECT parent FROM " . $wpdb->term_taxonomy . "
												WHERE term_id = $last_cat AND taxonomy = 'category'" );
				}
			}
		}
		$cat_ids[] = (string) $last_cat;
		$cat_ids   = implode( ", ", $cat_ids ); //convert to string
		if ( get_option( 'post_notification_debug' ) == 'yes' ) {
			echo 'The cat-Ids are: ' . $cat_ids . '<br />';
		}

		// Get the Emailadds
		$emails = $wpdb->get_results( " SELECT e.email_addr, e.id, e.act_code" . " FROM $t_emails e, $t_cats c " . " WHERE c.cat_id IN ($cat_ids) AND c.id = e.id AND e.gets_mail = 1 AND e.id >= " . $post->notification_sent . //Only send to people who havn't got mail before.
		                              " GROUP BY e.id " . " ORDER BY e.id ASC" ); //We need this. Otherwise we can't be shure whether we already have sent mail.

		if ( get_option( 'post_notification_debug' ) == 'yes' ) {
			echo count( $emails ) . ' Emails were found.<br />';
		}
		if ( $emails ) { //Check wheater ther are any mails to send anything.
			//Get Data
			$maildata = post_notification_create_email( $post->id );
			foreach ( $emails as $email ) {
				if ( get_option( 'post_notification_debug' ) == 'yes' ) {
					echo 'Sending mail to: ' . $email->email_addr . '<br />';
				}

				if ( $endtime != 0 ) { //if this is 0 we have as much time as we want.
					$time_remain = $endtime - time();
				}
				if ( $maxsend < 1 || $time_remain < 0 ) { //Are we allowed to send any more mails?
					$mailssent = $email->id; //Save where we stoped
					break;
				}
				//$$fb 2020-01-06: why not using $email->act_code for the $code parameter?
				post_notification_sendmail( $maildata, $email->email_addr, '' );

				$maxsend --;
			}
		}
		// Update notification_sent to -1 (We're done)
		$wpdb->query( " UPDATE $t_posts " . " SET " . " notification_sent = " . $mailssent . " WHERE post_id = " . $post->id );


		if ( $maxsend < 1 ) {
			break;
		} //We dont need to go on, if there's nothing to send.
	}
	update_option( 'post_notification_lastsend', time() );
	post_notification_set_next_send();

	if ( get_option( 'post_notification_lock' ) == 'db' ) {
		$wpdb->query( "SELECT RELEASE_LOCK('" . $wpdb->prefix . 'post_notification_lock' . "')" );
	} else {
		flock( $mutex, LOCK_UN );
		fclose( $mutex );
	}
}

function post_notification_add_additional_headers( $addr, $maildata, $code = '' ) {

	// $$fb 2020-01-06 testing state:
	// AppleMail on iPhone shows Unsubscribe button
	// AppleMail on MacOS does not show Unsubscribe button, but does so for e.g. emails from Google with same types of headers.
	// all other tested email clients never show an unsubscribe button

	$headers_in = isset( $maildata['header'] ) ? (array) $maildata['header'] : array();
	$headers    = array();

	foreach ( $headers_in as $v ) {
		if ( is_string( $v ) ) {
			$headers[] = trim( $v );
		}
	}

	$code              = post_notification_get_code( $addr, $code );
	$unsubscribe_email = get_option( 'post_notification_unsubscribe_email' );
	$unsubscribe_url   = post_notification_get_unsubscribeurl( $addr, $code );

	$list_unsubscribe_parts = array();

	if ( is_email( $unsubscribe_email ) ) {
		$subject                  = sprintf( 'Unsubscribe[%s]%s', $addr, $code );
		$list_unsubscribe_parts[] = '<mailto:' . $unsubscribe_email . '?subject=' . rawurlencode( $subject ) . '>';
	}
	if ( ! empty( $unsubscribe_url ) ) {
		$list_unsubscribe_parts[] = '<' . $unsubscribe_url . '>';
	}

	if ( ! empty( $list_unsubscribe_parts ) ) {
		$headers[] = 'List-Unsubscribe: ' . implode( ',', $list_unsubscribe_parts );
	}
	if ( ! empty( $unsubscribe_url ) ) {
		$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		$headers[] = 'X-Unsubscribe: visit ' . $unsubscribe_url;
	} else if ( is_email( $unsubscribe_email ) ) {
		$headers[] = 'X-Unsubscribe: send an email to ' . $unsubscribe_email;
	}

	$headers[] = 'Precedence: bulk';

	//$$fb 2020-01-06: including List-ID header will cause AppleMail on iOS to not show the Unsubscribe button!
	//$post_notification_list_name = post_notification_get_list_name();
	//$custom_header['List-ID'] = "List-ID: <".$post_notification_list_name.">";

	return $headers;
}

function post_notification_get_unsubscribeurl( $addr, $code ) {
	$confurl = post_notification_get_link();
	if ( strpos( $confurl, '/?' ) || strpos( $confurl, 'index.php?' ) ) {
		$confurl .= '&';
	} else {
		$confurl .= '?';
	}
	$confurl .= "code=" . $code . "&addr=" . $addr;

	return $confurl;
}

function post_notification_WC_send_with_custom_from( string $to, string $subject, string $html, array $headers = [], array $attachments = [] ) {
	$from_addr = get_option( 'post_notification_from_email' );
	if (empty($from_addr)) {
		//take the blog's admin email
		$from_addr = get_option( 'admin_email' );
	}
	$from_name = get_option( 'post_notification_from_name' );
	if ($from_name === '') {
		$from_name = get_bloginfo('name');
	} elseif (strpos($from_name, '@@blogname') !== false) {
		$from_name = get_bloginfo('name');
	}

	// 1) Clean headers: remove any existing "From:" lines to avoid duplicates
	$headers = array_values(array_filter(array_map('strval', $headers), function($h){
		return stripos($h, 'from:') !== 0; // drop user-provided From
	}));

	// 2) Set WC/WP From via filters (high priority to win over WC defaults)
	$f1 = add_filter('woocommerce_email_from_address', function($addr) use ($from_addr){ return is_email($from_addr) ? $from_addr : $addr; }, 100);
	$f2 = add_filter('woocommerce_email_from_name',    function($name) use ($from_name){ return $from_name ?: $name; }, 100);
	$f3 = add_filter('wp_mail_from',                   function($addr) use ($from_addr){ return is_email($from_addr) ? $from_addr : $addr; }, 100);
	$f4 = add_filter('wp_mail_from_name',              function($name) use ($from_name){ return $from_name ?: $name; }, 100);

	// 3) Set envelope sender (= Return-Path) via phpmailer_init
	$f5 = add_action('phpmailer_init', function( $phpmailer ) use ( $from_addr ) {
		if ( is_email( $from_addr ) ) {
			$phpmailer->Sender = $from_addr;   // controls Return-Path
		}
	}, 100);

	// 4) Send with WC mailer (wrap_message keeps the template)
	$mailer  = WC()->mailer();
	$message = $mailer->wrap_message( $subject, $html );
	$sent    = $mailer->send( $to, $subject, $message, $headers, $attachments );

	// 5) Cleanup (important so other mails aren’t affected)
	remove_filter('woocommerce_email_from_address', $f1, 100);
	remove_filter('woocommerce_email_from_name',    $f2, 100);
	remove_filter('wp_mail_from',                   $f3, 100);
	remove_filter('wp_mail_from_name',              $f4, 100);
	remove_action('phpmailer_init',                 $f5, 100);

	return $sent;
}