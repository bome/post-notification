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

function post_notification_create_email( $id, $template = '' ) {
	$blogname = get_option( 'blogname' );

	if ( get_option( 'post_notification_hdr_nl' ) == 'rn' ) {
		$hdr_nl = "\r\n";
	} else {
		$hdr_nl = "\n";
	}

	if ( $template == '' ) {
		$template = get_option( 'post_notification_template' );
	}
	if ( substr( $template, - 5 ) == '.html' ) {
		$html_email = true;
	} else {
		$html_email = false;
	}

	//Get the post
	$post = get_post( $id );

	if (empty($post)) {
		return false;
	}

	$post_url = get_permalink( $post->ID );

	$post_author = get_userdata( $post->post_author );
	$post_author = $post_author->display_name;
	$post_title  = $post->post_title;

	// 1) get raw content (no filters)
	$post_content = get_post_field( 'post_content', $id );

	// 2) handle "more" option without deprecated split()
	if ( get_option( 'post_notification_show_content' ) === 'more' ) {
		$parts = explode( '<!--more', $post_content, 2 );
		$post_content = $parts[0];
		if ( ! empty( $parts[1] ) ) {
			$post_content .= '<a href="@@permalink">' . esc_html( get_option( 'post_notification_read_more' ) ) . '</a>';
		}
	}

	// 3) excerpt fallback
	if ( get_option( 'post_notification_show_content' ) === 'excerpt' ) {
		if ( ! empty( $post->post_excerpt ) ) {
			$post_content = $post->post_excerpt;
		} else {
			// simple 55-words excerpt, tags stripped
			$text = wp_strip_all_tags( $post_content );
			$words = preg_split( '/\s+/', trim( $text ) );
			if ( count( $words ) > 55 ) {
				$words = array_slice( $words, 0, 55 );
				$text  = implode( ' ', $words ) . '…';
			}
			$post_content = wp_kses_post( $text );
		}
		$post_content .= '<br><a href="@@permalink">' . esc_html( get_option( 'post_notification_read_more' ) ) . '</a>';
	}

	// 4) Remove shortcodes (so no builder/JS runs)
	$post_content = strip_shortcodes( $post_content );

	// 5) Basic formatting: balance tags and add paragraphs
	$post_content = wpautop( balanceTags( $post_content, true ) );


	// 6) Sanitize to an email-friendly subset and absolutize href/src to https

	$post_content = pn_email_sanitize_html( $post_content, home_url('/', 'https') );

	// Do some date stuff
	$post_date = mysql2date( get_option( 'date_format' ), $post->post_date );
	$post_time = mysql2date( get_option( 'time_format' ), $post->post_date );

	if ( ! $html_email ) {
		if ( get_option( 'post_notification_debug' ) == 'yes' ) {
			echo 'Date1: ' . htmlspecialchars( $post_date ) . '<br />';
		}

		if ( function_exists( 'iconv' ) && ( strpos( phpversion(), '4' ) == 0 ) ) { //html_entity_decode does not support UTF-8 in php < 5
			$post_time = ( ( $temp = iconv( get_option( 'blog_charset' ), 'ISO8859-1', $post_time ) ) != "" ) ? $temp : $post_time;
			$post_date = ( ( $temp = iconv( get_option( 'blog_charset' ), 'ISO8859-1', $post_date ) ) != "" ) ? $temp : $post_date;
		}
		if ( get_option( 'post_notification_debug' ) == 'yes' ) {
			echo 'Date2: ' . htmlspecialchars( $post_date ) . '<br />';
		}


		$post_time = @html_entity_decode( $post_time, ENT_QUOTES, get_option( 'blog_charset' ) );
		$post_date = @html_entity_decode( $post_date, ENT_QUOTES, get_option( 'blog_charset' ) );
		if ( get_option( 'post_notification_debug' ) == 'yes' ) {
			echo 'Date3: ' . htmlspecialchars( $post_date ) . '<br />';
		}

		if ( function_exists( 'iconv' ) && ( strpos( phpversion(), '4' ) == 0 ) ) { //html_entity_decode does not support UTF-8 in php < 5
			$post_time = ( ( $temp = iconv( 'ISO8859-1', get_option( 'blog_charset' ), $post_time ) ) != "" ) ? $temp : $post_time;
			$post_date = ( ( $temp = iconv( 'ISO8859-1', get_option( 'blog_charset' ), $post_date ) ) != "" ) ? $temp : $post_date;
		}
		if ( get_option( 'post_notification_debug' ) == 'yes' ) {
			echo 'Date4: ' . htmlspecialchars( $post_date ) . '<br />';
		}
	}

	$post_title = strip_tags( $post_title );


	//Convert from HTML to text.
	if ( ! $html_email && !empty( $post_content ) ) {
		require_once( POST_NOTIFICATION_PATH . 'class.html2text.php' );
		//$$ak: fix syntax
		//$h2t =& new html2text($post_content);
		$h2t          = new html2text( $post_content );
		$post_content = $h2t->get_text();
	}

	// Load template
	$body = post_notification_ldfile( $template );

	if ( get_option( 'post_notification_debug' ) == 'yes' ) {
		echo "Email variables: <br /><table>";
		echo '<tr><td>Emailtype</td><td>' . ( ( $html_email ) ? 'HTML' : 'TEXT' ) . '</td>';
		echo '<tr><td>@@title</td><td>' . $post_title . '</td></tr>';
		echo '<tr><td>@@permalink</td><td>' . $post_url . '</td></tr>';
		echo '<tr><td>@@author</td><td>' . $post_author . '</td></tr>';
		echo '<tr><td>@@time</td><td>' . $post_time . '</td></tr>';
		echo '<tr><td>@@date</td><td>' . $post_date . '</td></tr>';
		echo "</table>";
	}

	// Replace variables
	$body = str_replace( '@@content', $post_content, $body ); //Insert the posting first. -> for Replacements
	$body = str_replace( '@@title', $post_title, $body );
	$body = str_replace( '@@permalink', $post_url, $body );
	$body = str_replace( '@@author', $post_author, $body );
	$body = str_replace( '@@time', $post_time, $body );
	$body = str_replace( '@@date', $post_date, $body );

	// User replacements
	if ( function_exists( 'post_notificataion_uf_perPost' ) ) {
		$body = post_notification_arrayreplace( $body, post_notificataion_uf_perPost( $id ) );
	}

	// EMAIL HEADER
	$header = post_notification_header( $html_email );


	// SUBJECT
	$subject = get_option( 'post_notification_subject' );
	$subject = str_replace( '@@blogname', $blogname, $subject );
	if ( $post_title != '' ) {
		$subject = str_replace( '@@title', $post_title, $subject );
	} else {
		$subject = str_replace( '@@title', __( 'New post', 'post_notification' ), $subject );
	}
	$subject = post_notification_encode( $subject, get_option( 'blog_charset' ) );


	$rv            = array();
	$rv['id']      = $id;
	$rv['subject'] = $subject;
	$rv['body']    = $body;
	$rv['header']  = $header;
	$rv['title']   = $post_title;

	return $rv;
}


function pn_email_sanitize_html( string $html, string $base = '' ): string {
	// remove scripts/styles
	$html = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', $html );

	// absolutize href/src
	$dom = new DOMDocument( '1.0', 'UTF-8' );
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<div id="r">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	$home = rtrim( $base ?: home_url( '/', 'https' ), '/' );

	foreach ( [ 'a' => 'href', 'img' => 'src' ] as $tag => $attr ) {
		foreach ( $dom->getElementsByTagName( $tag ) as $el ) {
			if ( ! $el->hasAttribute( $attr ) ) {
				continue;
			}
			$u = $el->getAttribute( $attr );
			if ( $u === '' || $u[0] === '#' ) {
				continue;
			}
			if ( strpos( $u, '//' ) === 0 ) {
				$el->setAttribute( $attr, 'https:' . $u );
				continue;
			}
			if ( ! preg_match( '#^[a-z][a-z0-9+\-.]*://#i', $u ) ) {
				$el->setAttribute( $attr, ( $u[0] === '/' ? $home . $u : $home . '/' . ltrim( $u, '/' ) ) );
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

	// allow only email-friendly tags
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
		'img'    => [ 'src'    => true,
		              'alt'    => true,
		              'width'  => true,
		              'height' => true,
		              'style'  => true,
		              'border' => true
		],
		'h1'     => [ 'style' => true ],
		'h2'     => [ 'style' => true ],
		'h3'     => [ 'style' => true ],
		'hr'     => [ 'style' => true ],
		'table'  => [ 'width'       => true,
		              'cellpadding' => true,
		              'cellspacing' => true,
		              'border'      => true,
		              'align'       => true,
		              'role'        => true,
		              'style'       => true
		],
		'tbody'  => [],
		'tr'     => [],
		'td'     => [ 'width' => true, 'align' => true, 'valign' => true, 'style' => true ],
	];
	$out     = wp_kses( $out, $allowed );
	// remove empty <p>
	$out = preg_replace( '#<p>\s*(?:&nbsp;|\xC2\xA0|\s)*</p>#i', '', $out );

	return trim( $out );
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

	if ( get_option( 'post_notification_unsubscribe_link_in_header' ) == 'yes' ) {
		$maildata['header'] = post_notification_add_additional_headers( $addr, $maildata, $code );
	}

	if ( $send ) {
		// Use woocommerce mailer if installed and activated in PN Settings
		if ( ( is_woocommerce_activated() ) and ( get_option( 'post_notification_use_wc_mailer' ) === 'yes' ) ) {
			global $woocommerce;

			// wrap in WC email
			$mailer  = WC()->mailer();
			$message = $mailer->wrap_message( $maildata['title'], $maildata['body'] );
			// send off
			$maildata['sent'] = $mailer->send( $addr, $maildata['subject'], $message, $maildata['header'], array() );
		} else {
			//wordpress
			$maildata['sent'] = wp_mail( $addr, $maildata['subject'], $maildata['body'], $maildata['header'] );
		}
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
		$emails = $wpdb->get_results(
			" SELECT e.email_addr, e.id, e.act_code" .
			" FROM $t_emails e, $t_cats c " .
			" WHERE c.cat_id IN ($cat_ids) AND c.id = e.id AND e.gets_mail = 1 AND e.id >= " . $post->notification_sent . //Only send to people who havn't got mail before.
			" GROUP BY e.id " .
			" ORDER BY e.id ASC"
		); //We need this. Otherwise we can't be shure whether we already have sent mail.

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
		$wpdb->query(
			" UPDATE $t_posts " .
			" SET " .
			" notification_sent = " . $mailssent .
			" WHERE post_id = " . $post->id
		);


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

	$includeURL = true;

	$custom_header = $maildata['header'];
	$code          = post_notification_get_code( $addr, $code );

	$custom_header['List-Unsubscribe-Post'] = "List-Unsubscribe-Post: List-Unsubscribe=One-Click";
	$custom_header['Precedence']            = "Precedence: bulk";

	$list_unsubscribe    = "";
	$list_unsubscribeurl = "";

	$unsubscribe_email = get_option( 'post_notification_unsubscribe_email' );
	if ( is_email( $unsubscribe_email ) ) {
		$list_unsubscribe .= "<mailto:" . $unsubscribe_email . "?subject=Unsubscribe[" . $addr . "]" . $code . ">";
	} else {
		$unsubscribe_email = "";
	}

	if ( $includeURL ) {
		$list_unsubscribeurl = post_notification_get_unsubscribeurl( $addr, $code );
	}

	if ( ! empty( $list_unsubscribeurl ) ) {
		if ( ! empty( $list_unsubscribe ) ) {
			$list_unsubscribe .= ",";
		}
		$list_unsubscribe  .= "<" . $list_unsubscribeurl . ">";
		$xlist_unsubscribe = "visit " . $list_unsubscribeurl;
	} else {
		if ( ! empty( $unsubscribe_email ) ) {
			$xlist_unsubscribe = "send an email to " . $unsubscribe_email;
		}
	}

	if ( ! empty( $list_unsubscribe ) ) {
		$custom_header['List-Unsubscribe'] = "List-Unsubscribe: " . $list_unsubscribe;
	}
	if ( ! empty( $xlist_unsubscribe ) ) {
		$custom_header['X-Unsubscribe'] = "X-Unsubscribe: " . $xlist_unsubscribe;
	}

	//$$fb 2020-01-06: including List-ID header will cause AppleMail on iOS to not show the Unsubscribe button!
	//$post_notification_list_name = post_notification_get_list_name();
	//$custom_header['List-ID'] = "List-ID: <".$post_notification_list_name.">";

	return $custom_header;
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
