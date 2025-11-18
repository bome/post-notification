<?php
#------------------------------------------------------
/*
 INFO
------------------------------------------------------
 This is part of the Post Notification Plugin for
 Wordpress. Please see the readme.txt for details.
------------------------------------------------------

*/
// 2020 September: use shortcodes:
add_shortcode( 'post_notification_header', 'post_notification_header_shortcode_function' );
add_shortcode( 'post_notification_body', 'post_notification_body_shortcode_function' );

function post_notification_header_shortcode_function() {
	return "<h2>" . post_notification_feheader() . "</h2>\n";
}

function post_notification_body_shortcode_function() {
	return post_notification_febody() . "\n";
}

function post_notification_get_profile_dir( $profile = '' ) {
	if ( $profile == '' ) {
		$profile = get_option( 'post_notification_profile' );
	}

	$dir = POST_NOTIFICATION_PATH . $profile;
	if ( file_exists( $dir ) ) {
		return $dir;
	}

	$dir = POST_NOTIFICATION_DATA . $profile;
	if ( file_exists( $dir ) ) {
		return $dir;
	}

	return false;
}

function post_notification_mysql2gmdate( $mysqlstring ) {
	if ( empty( $mysqlstring ) ) {
		return false;
	}

	return gmmktime(
		(int) substr( $mysqlstring, 11, 2 ),
		(int) substr( $mysqlstring, 14, 2 ),
		(int) substr( $mysqlstring, 17, 2 ),
		(int) substr( $mysqlstring, 5, 2 ),
		(int) substr( $mysqlstring, 8, 2 ),
		(int) substr( $mysqlstring, 0, 4 )
	);
}

function post_notification_date2mysql( $unixtimestamp = 0 ) {
	if ( empty( $unixtimestamp ) ) {
		return gmdate( 'Y-m-d H:i:s' );
	}

	return gmdate( 'Y-m-d H:i:s', $unixtimestamp );
}


/**
 * This function returns the SQL-statement to select all the posts which are to be sent.
 *
 * @paramfuture Also list posts published in the future
 */
function post_notification_sql_posts_to_send( $future = false ) {
	global $wpdb;
	if ( ! $future ) {
		$add_where = "AND GREATEST(p.post_date_gmt, el.date_saved) < '" . post_notification_date2mysql( ( time() - get_option( 'post_notification_nervous' ) ) ) . "' ";
	} else {
		$add_where = '';
	}
	$t_posts = $wpdb->prefix . 'post_notification_posts';
	if ( get_option( 'db_version' ) < 4772 ) {
		return " FROM $wpdb->posts p, $t_posts el " .
		       "WHERE( " .
		       "el.notification_sent >= 0 AND " .
		       "p.id = el.post_id AND " .
		       "p.post_status IN('publish', 'static' , 'private') " .
		       "$add_where)";
	}

	return " FROM $wpdb->posts p, $t_posts el " .
	       "WHERE( " .
	       "el.notification_sent >= 0 AND " .
	       "p.id = el.post_id AND " .
	       "p.post_status IN('publish', 'private', 'future') " .
	       "$add_where)";
}

/// returns a link to the Post Notification Page.
function post_notification_get_link() {
	$page = get_option( 'post_notification_url' );
	if ( is_numeric( $page ) ) {
		return get_permalink( $page );
	}

	return $page;
}


///Load file from profile and do standard Replacement
function post_notification_ldfile( $file ) {
	$msg = file_get_contents( post_notification_get_profile_dir() . '/' . $file );

	if ( function_exists( 'iconv' ) && function_exists( 'mb_detect_encoding' ) ) {
		$temp = iconv(
			mb_detect_encoding( $msg, "UTF-8, UTF-7, ISO-8859-1, ASCII, EUC-JP,SJIS, eucJP-win, SJIS-win, JIS, ISO-2022-JP" ),
			get_option( 'blog_charset' ),
			$msg
		); //"auto" doesn't work on quite a few platforms so we have to list encodings.
		if ( $temp != "" ) {
			$msg = $temp;
		}
	}
	$blogname = get_option( 'blogname' );
	$msg      = str_replace( '@@blogname', $blogname, $msg );
	$msg      = str_replace( '@@site', $blogname, $msg );

	return $msg;
}


/// Encode umlauts for mail headers
function post_notification_encode( $in_str, $charset ) {
	//get line break
	//See RFC 2047

	if ( get_option( 'post_notification_hdr_nl' ) === 'rn' ) {
		$hdr_nl = "\r\n";
	} else {
		$hdr_nl = "\n";
	}


	if ( ! function_exists( 'mb_detect_encoding' ) ) {
		return $in_str;
	} //Can't do anything without mb-functions.

	$end       = '?=';
	$start     = '=?' . $charset . '?B?';
	$enc_len   = strlen( $start ) + 2; //2 = end
	$lastbreak = 0;
	$code      = '';
	$out_str   = '';

	for ( $i = 0, $iMax = strlen( $in_str ); $i < $iMax; $i ++ ) {
		if ( function_exists( 'mb_check_encoding' ) ) {
			$isascii = mb_check_encoding( $in_str[ $i ], 'ASCII' );
		} else {
			$isascii = ( mb_detect_encoding( $in_str[ $i ], 'UTF-8, ISO-8859-1, ASCII' ) === 'ASCII' );
		}

		//some adjustments
		if ( $code !== '' ) {
			if ( $in_str[ $i ] === ' ' || $in_str[ $i ] === "\t" ) {
				$isascii = false;
			}
		}

		//linebreaking
		$this_line_len = strlen( $out_str ) + strlen( $code ) + $enc_len - $lastbreak; //$enc_len is needed in case a non-ascii is added
		if ( $this_line_len > 65 && ( $in_str[ $i ] === ' ' || $in_str[ $i ] === "\t" ) ) {
			if ( $code != '' ) { //Get rid of $code
				$out_str .= $start . base64_encode( $code ) . $end;
			}
			//Linebrak and space -> rfc 822.
			//In case we have $code this is no problem, as a new $code will start in the next line. -> Fail safe, little overhead
			$out_str .= $hdr_nl . $in_str[ $i ];

			$code      = '';
			$lastbreak += $this_line_len;
		}


		if ( ! $isascii ) {
			$code .= $in_str[ $i ];
		} else {
			if ( $code ) {
				$out_str .= $start . base64_encode( $code ) . $end;
				$code    = '';
			}
			$out_str .= $in_str[ $i ];
		}
	}
	//We have some chars in the code-buffer we have to get rid of....
	if ( $code ) {
		$out_str .= $start . base64_encode( $code ) . $end;
	}

	return $out_str;
}

// Generate the Mail header
// Ak: add unsubscribe options before sending (sendmail.php)
function post_notification_header( $html = false ) {
	$from_name = str_replace( '@@blogname', get_option( 'blogname' ), get_option( 'post_notification_from_name' ) );

	$from_name = post_notification_encode( $from_name, get_option( 'blog_charset' ) );

	$from_email = get_option( 'post_notification_from_email' );
	if (empty($from_email)) {
		//take the blog's admin email
		$from_email = get_option( 'admin_email' );
	}

	$header = array();

	//$header['MIME-Version'] = "MIME-Version: 1.0";
	$header['From']        = "From: " . $from_name . " <" . $from_email . ">";
	$header['Reply-To']    = "Reply-To: " . $from_email;
	$header['Return-Path'] = "Return-Path: " . $from_email;

	if ( $html ) {
		$header['Content-type'] = "Content-type: text/html; charset=" . get_option( 'blog_charset' );
	} else {
		$header['Content-type'] = "Content-type: text/plain; charset=" . get_option( 'blog_charset' );
	}

 return $header;
}

/**
 * Unified mail sending helper for Post Notification.
 * Routes based on setting post_notification_mailer_method: 'wp', 'pn_smtp', 'wc'.
 * Returns bool for success like wp_mail.
 */
function pn_send_mail( $to, $subject, $message, $headers = array(), $attachments = array() ) {
	$method = get_option( 'post_notification_mailer_method', 'wp' );

	// Normalize headers to array of Name: Value or key=>value
	if ( is_string( $headers ) ) {
		$headers = preg_split( "/\r?\n/", $headers );
	}
	$headers = is_array( $headers ) ? $headers : array();

	// Helper to parse headers into assoc array
	$assoc = array();
	foreach ( $headers as $k => $v ) {
		if ( is_string( $k ) ) {
			$assoc[ $k ] = $v;
		} elseif ( is_string( $v ) && strpos( $v, ':' ) !== false ) {
			list( $name, $val ) = array( trim( substr( $v, 0, strpos( $v, ':' ) ) ), trim( substr( $v, strpos( $v, ':' ) + 1 ) ) );
			$assoc[ $name ] = $val;
		}
	}

	// WooCommerce mailer
	if ( $method === 'wc' ) {
		if ( ( function_exists( 'is_woocommerce_activated' ) && is_woocommerce_activated() ) || class_exists( 'WooCommerce' ) ) {
			if ( function_exists( 'post_notification_WC_send_with_custom_from' ) ) {
				return (bool) post_notification_WC_send_with_custom_from( $to, $subject, $message, $headers, (array) $attachments );
			}
		}
		// Fallback to wp_mail if WC not available
		$method = 'wp';
	}

	if ( $method === 'pn_smtp' ) {
		// Use PHPMailer directly
		if ( class_exists( '\\PHPMailer\\PHPMailer\\PHPMailer' ) ) {
			$mail = new \PHPMailer\PHPMailer\PHPMailer( true );
		} elseif ( class_exists( 'PHPMailer' ) ) {
			$mail = new PHPMailer( true );
		} else {
			// As last resort, fall back to wp_mail
			return (bool) wp_mail( $to, $subject, $message, $headers, $attachments );
		}

		try {
			$mail->isSMTP();
			$mail->Host = (string) get_option( 'post_notification_smtp_host', '' );
			$mail->Port = (int) get_option( 'post_notification_smtp_port', 587 );
			$secure = (string) get_option( 'post_notification_smtp_secure', 'tls' );
			if ( $secure === 'none' ) {
				$mail->SMTPSecure = '';
			} else {
				$mail->SMTPSecure = $secure; // 'ssl' or 'tls'
			}
			$mail->SMTPAuth = ( get_option( 'post_notification_smtp_auth', 'yes' ) === 'yes' );
			$mail->Timeout = (int) get_option( 'post_notification_smtp_timeout', 30 );
			$mail->CharSet = get_option( 'blog_charset', 'UTF-8' );

			$user = (string) get_option( 'post_notification_smtp_user', '' );
			$pass = (string) get_option( 'post_notification_smtp_pass', '' );
			if ( $mail->SMTPAuth ) {
				$mail->Username = $user;
				$mail->Password = $pass;
			}

			// From and Reply-To from headers (built by post_notification_header())
			$from_email = get_option( 'post_notification_from_email' );
			if ( empty( $from_email ) ) { $from_email = get_option( 'admin_email' ); }
			$from_name = str_replace( '@@blogname', get_option( 'blogname' ), get_option( 'post_notification_from_name' ) );

			if ( isset( $assoc['From'] ) && preg_match( '/<([^>]+)>/', $assoc['From'], $m ) ) {
				$from_email = trim( $m[1] );
				$from_name = trim( preg_replace( '/\s*<[^>]+>.*/', '', $assoc['From'] ) );
			}
			$mail->setFrom( $from_email, $from_name );
			if ( isset( $assoc['Reply-To'] ) && preg_match( '/<([^>]+)>/', $assoc['Reply-To'], $m2 ) ) {
				$mail->addReplyTo( trim( $m2[1] ) );
			}

			// Content type
			$is_html = false;
			if ( isset( $assoc['Content-type'] ) && stripos( $assoc['Content-type'], 'text/html' ) !== false ) {
				$is_html = true;
			}
			$mail->isHTML( $is_html );
			$mail->Subject = $subject;
			$mail->Body = $message;
			if ( ! $is_html ) {
				$mail->AltBody = $message;
			}

			// To may be array
			$tos = is_array( $to ) ? $to : array( $to );
			foreach ( $tos as $t ) {
				if ( is_email( $t ) ) $mail->addAddress( $t );
			}

			// Custom headers (e.g., List-Unsubscribe)
			foreach ( $assoc as $name => $val ) {
				if ( in_array( strtolower( $name ), array( 'from', 'reply-to', 'content-type' ), true ) ) continue;
				$mail->addCustomHeader( $name, $val );
			}

			// Attachments
			foreach ( (array) $attachments as $att ) {
				$mail->addAttachment( $att );
			}

			return $mail->send();
		} catch ( \Exception $e ) {
			// Fallback
			return (bool) wp_mail( $to, $subject, $message, $headers, $attachments );
		}
	}

	// Default: WordPress
	return (bool) wp_mail( $to, $subject, $message, $headers, $attachments );
}

/// Install a theme
function post_notification_installtheme() {
    if ( get_option( 'post_notification_filter_include' ) === 'no' ) {
        $src  = POST_NOTIFICATION_PATH . 'post_notification_template.php';
        // Prefer API to build theme path, avoid direct ABSPATH dependency
        $theme_root = function_exists( 'get_theme_root' ) ? trailingslashit( get_theme_root() ) : ( defined( 'WP_CONTENT_DIR' ) ? trailingslashit( WP_CONTENT_DIR ) . 'themes/' : '' );
        $dest = $theme_root . get_option( 'template' ) . '/post_notification_template.php';
        if ( ! @file_exists( $dest ) ) {
            if ( ! @copy( $src, $dest ) ) {
                return $dest;
            }
        }
	}

	return '';
}

/// Calculate when the next mail need to be sent.
function post_notification_set_next_send() {
	global $wpdb;
	$t_emails = $wpdb->prefix . 'post_notification_emails';
	//This is not the ideal place, but quite ok.
	update_option( 'post_notification_subscribers', $wpdb->get_var( "SELECT COUNT(*) FROM $t_emails WHERE gets_mail = 1" ) );


	$d_next = post_notification_mysql2gmdate( $wpdb->get_var( "SELECT MIN(GREATEST(post_date_gmt, date_saved)) " . post_notification_sql_posts_to_send( true ) ) );
	if ( $d_next ) { //We do have somthing to send
		$nervous  = $d_next + get_option( 'post_notification_nervous' ); //There is no other way :-(
		$nextsend = get_option( 'post_notification_lastsend' ) + get_option( 'post_notification_pause' );
		$d_next   = max( $nextsend, $nervous );
		update_option( 'post_notification_nextsend', $d_next );
	} else {
		//There are no post with unsent mail.
		update_option( 'post_notification_nextsend', - 1 );
	}
}


/**
 * Create a link to the subscription page.
 *
 * @param addr The adress, which is to be used
 * @param code The Code, if available. If not it will be retrieved from the db.
 *
 * @return string|the
 */

function post_notification_get_mailurl( $addr, $code = '' ) {

	$code = post_notification_get_code( $addr, $code );

	//Adjust the URL
	$confurl = post_notification_get_link();
	if ( strpos( $confurl, '/?' ) || strpos( $confurl, 'index.php?' ) ) {
		$confurl .= '&';
	} else {
		$confurl .= '?';
	}
	$confurl .= "code=" . $code . "&addr=" . urlencode( $addr ) . "&";

	return $confurl;
}

class Walker_post_notification extends Walker {
	public $tree_type = 'category';
	public $db_fields = array( 'parent' => 'category_parent', 'id' => 'cat_ID' ); //TODO: decouple this
	public $id_list = array( 0 );
	public $last_id = 0;

	//$$ak: fixed for php7
	//old: function start_lvl(&$output, $depth, $args)
	public function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent          = str_repeat( "\t", $depth );
		$output          .= "$indent<ul class='children'>\n";
		$this->id_list[] = $this->last_id;

		return $output;
	}

	//$$ak: fixed for php7
	//old: function end_lvl(&$output, $depth, $args)
	public function end_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "$indent</ul>\n";
		array_pop( $this->id_list );

		return $output;
	}


	//$$ak: fixed for php7
	//old: function start_el(&$output, $category, $depth, $args)
	// Variable changed to $object from $category in function below
	public function start_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {
		$output .= str_repeat( "\t", $depth * 3 );
		$output .= "<li>";

		$output .= "\t" . '<input class="pn_checkbox" type="checkbox" name="pn_cats[]" value="' . $object->cat_ID .
		           '" id="cat.' . implode( '.', $this->id_list ) . '.' . $object->cat_ID . '" ';
		if ( in_array( $object->cat_ID, $args['pn_ids'] ) ) {
			$output .= ' checked="checked"';
		}
		$output .= ' onclick = "post_notification_cats_init()" />';

		$output .= apply_filters( 'list_cats', $object->cat_name, $object );


		$output        .= "</li>\n";
		$this->last_id = $object->cat_ID;

		return $output;
	}
}

/**
 * Return everything needed for selecting the cats.
 *
 * @param all_str The string used for all categories
 * @param subcats An number-array of cats which should be selected.
 *
 */

function post_notification_get_catselect( $all_str = '', $subcats = array() ) {
	if ( ! is_array( $subcats ) ) {
		$subcats = array();
	}

	$walkparam = array( 'pn_ids' => $subcats );

	if ( $all_str == '' ) {
		$all_str = __( 'All', 'post_notification' );
	}
	if ( get_option( 'post_notification_empty_cats' ) === 'yes' ) {
		$cats = get_categories( array( 'hide_empty' => false ) );
	} else {
		$cats = get_categories();
	}
	$walker = new Walker_post_notification;

	$cats_str = '<ul class="children postnotification_cats"><li><input class="pn_checkbox" type="checkbox" name="pn_cats[]" value="0" id="cat.0" onclick="post_notification_cats_init()" ';
	if ( in_array( 0, $subcats ) ) {
		$cats_str .= ' checked="checked"';
	}
	$cats_str .= '>' . $all_str . '</li>';
	$cats_str .= '<ul class="children">' . $walker->walk( $cats, 0, $walkparam ) . '</ul>';
	$cats_str .= '</ul>';
	$cats_str .= "<script type=\"text/javascript\"><!--\n  post_notification_cats_init();\n //--></script>";

	return $cats_str;
}

function post_notification_get_addr() {
	$commenter = wp_get_current_commenter();
	$addr      = $commenter['comment_author_email'];

	if ( $addr == '' ) { //still havn't got email
		$user = wp_get_current_user();
		$addr = $user->user_email;
	}

	return $addr;
}

function post_notification_date_i18n_tz( $dateformatstring, $unixtimestamp ) {

	// In case the Time Zone plugin is installed, date() is working correctly.
	// Let it do its work.
	//
	if ( class_exists( 'TimeZone' ) ) {
		return date_i18n( $dateformatstring, $unixtimestamp );
	}
	// Else, we cannot rely on date() and must revert to gmdate(). We assume
	// that no daylight saving takes part. Else, install the plugin from
	// http://kimmo.suominen.com/sw/timezone/ using (or not) the patches from
	// http://www.philippusdo.de/technische-informationen/ .
	//
	global $wp_locale;
	$i = $unixtimestamp + get_option( 'gmt_offset' ) * 3600;

	if ( ( ! empty( $wp_locale->month ) ) && ( ! empty( $wp_locale->weekday ) ) ) {
		$datemonth            = $wp_locale->get_month( gmdate( 'm', $i ) );
		$datemonth_abbrev     = $wp_locale->get_month_abbrev( $datemonth );
		$dateweekday          = $wp_locale->get_weekday( gmdate( 'w', $i ) );
		$dateweekday_abbrev   = $wp_locale->get_weekday_abbrev( $dateweekday );
		$datemeridiem         = $wp_locale->get_meridiem( gmdate( 'a', $i ) );
		$datemeridiem_capital = $wp_locale->get_meridiem( gmdate( 'A', $i ) );

		$dateformatstring = ' ' . $dateformatstring;
		$dateformatstring = preg_replace( "/([^\\\])D/", "\\1" . backslashit( $dateweekday_abbrev ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])F/", "\\1" . backslashit( $datemonth ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])l/", "\\1" . backslashit( $dateweekday ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])M/", "\\1" . backslashit( $datemonth_abbrev ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])a/", "\\1" . backslashit( $datemeridiem ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])A/", "\\1" . backslashit( $datemeridiem_capital ), $dateformatstring );
		$dateformatstring = substr( $dateformatstring, 1 );
	}

	return @gmdate( $dateformatstring, $i );
}

function post_notification_get_code( $addr, $code = '' ) {
	global $wpdb;
	if ( strlen( $code ) != 32 ) {
		$t_emails = $wpdb->prefix . 'post_notification_emails';
		$query    = $wpdb->get_results( "SELECT id, act_code FROM $t_emails WHERE email_addr = '" . $addr . "'" );
		$query    = $query[0];

  //Get Activation Code
  if ( ( $query->id == '' ) || ( strlen( $query->act_code ) != 32 ) ) { //Reuse the code
      mt_srand( (double) microtime() * 1000000 );
      $code = md5( mt_rand( 100000, 99999999 ) . time() );
      if ( $query->id == '' ) {
          // Store subscribe_ip as string (supports IPv4/IPv6); validate input
          $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
          $ip = filter_var( $remote_ip, FILTER_VALIDATE_IP ) ? $remote_ip : '';
          $wpdb->query(
              $wpdb->prepare(
                  "INSERT INTO $t_emails (email_addr,date_subscribed, act_code, subscribe_ip) VALUES (%s, %s, %s, %s)",
                  $addr,
                  post_notification_date2mysql(),
                  $code,
                  $ip
              )
          );
      } else {
          $wpdb->query(
              "UPDATE $t_emails SET act_code = '$code' WHERE email_addr = '" . $addr . "'"
          );
      }
  } else {
      $code = $query->act_code;
  }
	}

	return $code;

}

function post_notification_get_list_name() {
	$page_name = get_option( 'post_notification_page_name' );

	//get the full server domain
	$urlparts = parse_url( site_url() );
	$domain   = $urlparts ['host'];

	if ( ! isset( $page_name ) || strlen( $page_name ) < 3 ) {
		return "default." . $domain;
	}

// Strip HTML Tags
	$clear = strip_tags( $page_name );
// Clean up things like &amp;
	$clear = html_entity_decode( $clear );
// Strip out any url-encoded stuff
	$clear = urldecode( $clear );
// Replace non-AlNum characters with space
	$clear = preg_replace( '/[^A-Za-z0-9]/', '', $clear );
// Replace Multiple spaces with single space
	$clear = preg_replace( '/ +/', ' ', $clear );
// Trim the string of leading/trailing space
	$clear = trim( $clear );
	// lower and cut after 10 characters
	$listname = substr( strtolower( $clear ), 0, 10 );

	return $listname . "." . $domain;
}

function post_notification_enqueue_cats_js() {
	wp_enqueue_script( 'custom-js', POST_NOTIFICATION_PATH_URL . 'pncats.js', array( 'jquery' ), false, false );
}

/**
 * Check if WooCommerce is activated
 */
if ( ! function_exists( 'is_woocommerce_activated' ) ) {
	function is_woocommerce_activated() {
		if ( class_exists( 'woocommerce' ) ) {
			return true;
		}

		return false;
	}
}

// generates link with ID of user account
function pn_get_wp_user_link( $email ) {
	$user = get_user_by( 'email', $email );
	if ( ! empty( $user ) ) {
		return '<a href="/wp-admin/user-edit.php?user_id=' . $user->ID . '">' . $user->nickname . '</a>';
	}

	return "";
}

/**
 * Convert HTML to plain text for email
 *
 * Uses WordPress native functions for safe HTML to text conversion
 *
 * @param string $html HTML content
 * @param int $width Line width for wrapping (0 = no wrapping)
 * @return string Plain text
 */
function post_notification_html_to_text( $html, $width = 70 ) {
	// Remove scripts and styles completely
	$html = preg_replace( '#<script[^>]*?>.*?</script>#si', '', $html );
	$html = preg_replace( '#<style[^>]*?>.*?</style>#si', '', $html );

	// Add line breaks before block elements
	$html = preg_replace( '#<(p|div|h[1-6]|blockquote|pre)[^>]*>#i', "\n", $html );
	$html = preg_replace( '#</(p|div|h[1-6]|blockquote|pre)>#i', "\n", $html );

	// Convert line breaks
	$html = preg_replace( '#<br\s*/?\s*>#i', "\n", $html );

	// Convert horizontal rules
	$html = preg_replace( '#<hr\s*/?\s*>#i', "\n" . str_repeat( '-', 50 ) . "\n", $html );

	// Convert lists
	$html = preg_replace( '#<li[^>]*>#i', "\n  * ", $html );
	$html = preg_replace( '#</li>#i', '', $html );
	$html = preg_replace( '#</?[ou]l[^>]*>#i', "\n", $html );

	// Convert bold/strong to *text*
	$html = preg_replace( '#<(strong|b)[^>]*>(.*?)</\1>#is', '*$2*', $html );

	// Convert italic/em to _text_
	$html = preg_replace( '#<(em|i)[^>]*>(.*?)</\1>#is', '_$2_', $html );

	// Convert links to text with URL
	$html = preg_replace_callback(
		'#<a[^>]+href=["\'](.*?)["\'][^>]*>(.*?)</a>#is',
		function( $matches ) {
			$url = $matches[1];
			$text = strip_tags( $matches[2] );

			// Don't duplicate if text is already the URL
			if ( trim( $text ) === trim( $url ) ) {
				return $url;
			}

			// Skip if text is empty
			if ( empty( trim( $text ) ) ) {
				return $url;
			}

			return $text . ' (' . $url . ')';
		},
		$html
	);

	// Strip all remaining HTML tags (WordPress native function)
	$text = wp_strip_all_tags( $html );

	// Convert HTML entities to characters
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	// Normalize whitespace
	$text = preg_replace( '/[ \t]+/', ' ', $text ); // Multiple spaces to one
	$text = preg_replace( '/\n[ \t]+/', "\n", $text ); // Remove spaces at line start
	$text = preg_replace( '/[ \t]+\n/', "\n", $text ); // Remove spaces at line end
	$text = preg_replace( '/\n{3,}/', "\n\n", $text ); // Max 2 line breaks

	// Trim each line
	$lines = explode( "\n", $text );
	$lines = array_map( 'trim', $lines );
	$text = implode( "\n", $lines );

	// Word wrap if width is specified
	if ( $width > 0 ) {
		$lines = explode( "\n", $text );
		$wrapped = array();

		foreach ( $lines as $line ) {
			// Only wrap if line is longer than width
			if ( mb_strlen( $line, 'UTF-8' ) > $width ) {
				// Don't break words
				$wrapped_line = wordwrap( $line, $width, "\n", false );
				$wrapped[] = $wrapped_line;
			} else {
				$wrapped[] = $line;
			}
		}

		$text = implode( "\n", $wrapped );
	}

	return trim( $text );
}


/**
 * Helpers to render and manage the "the_content" exclude list in Post Notification settings.
 * Compatible with modern WP_Hook structure.
 */

/**
 * Build a stable callback ID for a filter callback, similar to WP internals.
 * Falls back to human-readable labels where possible.
 */
function pn_build_callback_id( string $tag, $callback, int $priority ): string {
	// WordPress helper available? Use it to match how WP indexes callbacks.
	if ( function_exists( '_wp_filter_build_unique_id' ) ) {
		$id = _wp_filter_build_unique_id( $tag, $callback, $priority );
		if ( $id !== false && $id !== null ) {
			return (string) $id;
		}
	}

	// Manual fallback: convert the callable to a readable string.
	if ( is_string( $callback ) ) {
		return $callback;
	}
	if ( is_array( $callback ) && count( $callback ) === 2 ) {
		[ $obj_or_class, $method ] = $callback;
		if ( is_object( $obj_or_class ) ) {
			$obj_or_class = get_class( $obj_or_class );
		}

		return $obj_or_class . '::' . $method;
	}
	if ( $callback instanceof Closure ) {
		// Closures are unstable; add a hash to avoid collisions.
		return 'closure_' . md5( spl_object_hash( $callback ) . '|' . $tag . '|' . $priority );
	}

	// Last resort: serialize/hash anything else
	return 'cb_' . md5( maybe_serialize( $callback ) . '|' . $tag . '|' . $priority );
}

/**
 * Collect all callbacks attached to "the_content", grouped and sorted by priority.
 * Returns a flat list of [id, label, priority].
 */
function pn_collect_the_content_callbacks(): array {
	global $wp_filter;

	$result = [];
	$tag    = 'the_content';

	if ( empty( $wp_filter[ $tag ] ) ) {
		return $result;
	}

	// Since WP 4.7 this is a WP_Hook object
	$hook = $wp_filter[ $tag ];

	// Get the callbacks array by priority
	$callbacks_by_priority = is_object( $hook ) && isset( $hook->callbacks ) ? $hook->callbacks : (array) $hook; // very old WP fallbacks

	ksort( $callbacks_by_priority, SORT_NUMERIC );

	foreach ( $callbacks_by_priority as $priority => $callbacks ) {
		foreach ( (array) $callbacks as $maybe_id => $entry ) {
			// $entry should contain ['function' => callable]
			if ( empty( $entry['function'] ) ) {
				continue;
			}
			$callable = $entry['function'];
			$id       = pn_build_callback_id( $tag, $callable, (int) $priority );

			// Human label for UI
			$label    = $id;
			$result[] = [
				'id'       => $id,
				'label'    => $label,
				'priority' => (int) $priority,
			];
		}
	}

	return $result;
}

/**
 * Load saved exclude list from options (handles serialized legacy value).
 */
function pn_get_saved_the_content_excludes(): array {
	$saved = get_option( 'post_notification_the_content_exclude' );

	if ( is_string( $saved ) && strlen( $saved ) ) {
		// Backward compatibility: legacy serialized value
		$maybe = @unserialize( $saved );
		if ( $maybe !== false || $saved === 'b:0;' ) {
			$saved = $maybe;
		}
	}

	return is_array( $saved ) ? $saved : [];
}

/**
 * Render the checklist UI for excludes. Call from your settings page.
 */
function pn_render_the_content_exclude_checklist(): void {
	$items    = pn_collect_the_content_callbacks();
	$selected = pn_get_saved_the_content_excludes();

	if ( empty( $items ) ) {
		echo '<p><em>No filters found on <code>the_content</code>.</em></p>';
		return;
	}

	echo '<div class="pn-filter-list">';

	foreach ( $items as $item ) {
		$callback_id = $item['id'];
		$is_checked  = in_array( $callback_id, $selected, true );
		$priority    = (int) $item['priority'];

		// nutzt deine neue Helper-Funktion mit Name + Kurzbeschreibung:
		pn_render_filter_checkbox_line( $callback_id, $is_checked, $priority );
	}

	echo '</div>';
}

/**
 * Try to normalize a callback ID to a recognizable name.
 * - Strips leading 32-hex chars (anonymous closure IDs in some setups)
 * - If still empty, returns original.
 */
function pn_normalize_callback_name( string $id ): string {
	// Strip a leading 32-char hex blob if present (e.g. "0000000...check_weaverii")
	if ( preg_match( '/^([0-9a-f]{32})(.+)$/i', $id, $m ) ) {
		$id = ltrim( $m[2] );
	}

	// Some entries may end with only hex; keep them unchanged to display raw.
	return $id !== '' ? $id : $id;
}



/**
 * Example: render one line with name + description.
 * Use this in your settings table where you output each checkbox.
 */
function pn_render_filter_checkbox_line( string $callback_id, bool $checked, int $priority ): void {
	$name = pn_normalize_callback_name( $callback_id );
	$data = pn_get_filter_description(  $callback_id ) ?? null;

	$desc  = $data['desc'] ?? 'Unknown or plugin-specific filter.';
	$rec   = $data['recommendation'] ?? '⚠️ Not classified – check manually.';

	printf(
		'<div class="pn-filter-item" data-status="%7$s">
		<label>
			<input type="checkbox" name="the_content[]" value="%1$s" %2$s />
			<span class="pn-filter-name"><code>%3$s</code></span>
			<span class="pn-filter-priority">(prio %4$d)</span>
		</label>
		<div class="pn-filter-meta">
			<p class="pn-filter-desc">%5$s</p>
			<p class="pn-filter-recommendation">%6$s</p>
		</div>
		<hr class="pn-filter-separator" />
	</div>',
		esc_attr($callback_id),
		checked($checked, true, false),
		esc_html($name),
		(int)$priority,
		esc_html($desc),
		esc_html($rec),
		esc_attr(pn_get_recommendation_status($rec))
	);
}

function pn_get_recommendation_status( string $rec ): string {
	if ( str_contains( $rec, '✅' ) ) {
		return 'success';
	}
	if ( str_contains( $rec, '⚠️' ) ) {
		return 'warning';
	}
	if ( str_contains( $rec, '❌' ) ) {
		return 'error';
	}
	return 'neutral';
}


/**
 * Load filter descriptions from external file
 */
function pn_get_filter_descriptions_array(): array {
	static $descriptions = null;

	if ( $descriptions === null ) {
		$file = __DIR__ . '/content-filter-descriptions.php';
		if ( file_exists( $file ) ) {
			$descriptions = include $file;
		} else {
			$descriptions = [];
		}
	}

	return $descriptions;
}

/**
 * Get description and recommendation for a known filter callback.
 */
function pn_get_filter_description( string $raw_id ): array {
	$name         = pn_normalize_callback_name( $raw_id );
	$descriptions = pn_get_filter_descriptions_array();

	// Exact match
	if ( isset( $descriptions[ $name ] ) ) {
		$data = $descriptions[ $name ];

		return [
			'desc'           => $data['desc'] ?? 'No description available.',
			'recommendation' => $data['recommendation'] ?? '⚠️ Not classified – check manually.',
		];
	}

	// Heuristics: try to detect known substrings
	foreach ( $descriptions as $key => $desc ) {
		if ( $key !== '' && stripos( $name, $key ) !== false ) {
			return [
				'desc'           => $desc['desc'] ?? 'No description available.',
				'recommendation' => $desc['recommendation'] ?? '⚠️ Not classified – check manually.',
			];
		}
	}

	// Generic fallback
	return [
		'desc'           => 'Unknown/3rd-party filter. Likely intended for web rendering; may not be email-friendly.',
		'recommendation' => '⚠️ Not classified – check manually.',
	];
}

// ==============================================
// Mailing list API (internal, WP‑style function names)
// Storage: custom tables created in install.php
// - wp_post_notification_lists (id, slug, name)
// - wp_post_notification_list_users (list_id, user_id)
// - wp_post_notification_emails (id, email_addr, gets_mail, last_modified, date_subscribed, act_code, subscribe_ip)
// Users are identified by WP user IDs (integers)
// ==============================================

/**
 * Format an IP value for display in admin/UI.
 * Supports legacy integer (IPv4), textual IPv4/IPv6, and binary (inet_ntop).
 * Returns an empty string for empty/invalid inputs.
 *
 * @param mixed $value
 * @return string
 */
function pn_format_ip_for_display( $value ): string {
    // Empty
    if ($value === null || $value === '' || $value === false) {
        return '';
    }

    // Numeric legacy IPv4 (stored as int)
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        $intVal = (int) $value;
        if ($intVal >= 0) {
            $ip = @long2ip($intVal);
            if ($ip !== false && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
        }
        // fallthrough to generic sanitize below
    }

    // Binary packed IP (4 or 16 bytes)
    if (is_string($value) && strlen($value) > 0 && preg_match('/^[\x00-\xff]+$/', $value)) {
        $len = strlen($value);
        if ($len === 4 || $len === 16) {
            if (function_exists('inet_ntop')) {
                $ip = @inet_ntop($value);
                if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
    }

    // Textual IPv4/IPv6
    if (is_string($value) && filter_var($value, FILTER_VALIDATE_IP)) {
        return $value;
    }

    // Fallback: sanitized string
    return is_string($value) ? sanitize_text_field($value) : '';
}

/**
 * Ensure required PN tables exist (delta‑sicher). Runs on load; cheap no‑op if tables already exist.
 */
function pn_maybe_create_core_tables() {
    global $wpdb;
    if ( ! isset( $wpdb ) || ! $wpdb ) return;

    // We use dbDelta via maybe_create_table helper
    if ( ! function_exists( 'maybe_create_table' ) ) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $charset_collate = $wpdb->get_charset_collate();

    // post_notification_emails
    $t_emails = $wpdb->prefix . 'post_notification_emails';
    $sql_emails = "CREATE TABLE {$t_emails} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        email_addr varchar(255) NOT NULL,
        gets_mail tinyint(1) NOT NULL DEFAULT 1,
        last_modified datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        date_subscribed datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        act_code varchar(64) NOT NULL DEFAULT '',
        subscribe_ip varchar(64) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        UNIQUE KEY email_addr (email_addr)
    ) {$charset_collate};";
    maybe_create_table( $t_emails, $sql_emails );

    // post_notification_lists (already used, ensure exists)
    $t_lists = $wpdb->prefix . 'post_notification_lists';
    $sql_lists = "CREATE TABLE {$t_lists} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        slug varchar(191) NOT NULL,
        name varchar(255) NOT NULL,
        created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (id),
        UNIQUE KEY slug (slug)
    ) {$charset_collate};";
    maybe_create_table( $t_lists, $sql_lists );

    // post_notification_list_users (already used, ensure exists)
    $t_rel = $wpdb->prefix . 'post_notification_list_users';
    $sql_rel = "CREATE TABLE {$t_rel} (
        list_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        added_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (list_id, user_id),
        KEY user_id (user_id)
    ) {$charset_collate};";
    maybe_create_table( $t_rel, $sql_rel );
}

// Run once per load; cheap when tables exist
add_action( 'plugins_loaded', 'pn_maybe_create_core_tables', 5 );

/**
 * Get list ID by slug.
 */
function pn_list_get_id_by_slug( string $slug ) {
    global $wpdb;
    $slug = sanitize_key( $slug );
    if ( $slug === '' ) return 0;
    $t_lists = $wpdb->prefix . 'post_notification_lists';
    $id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t_lists WHERE slug=%s", $slug ) );
    return $id ?: 0;
}

/** Get list display name by slug (or empty string if not found). */
function pn_list_get_name_by_slug( string $slug ): string {
    global $wpdb;
    $slug = sanitize_key( $slug );
    if ( $slug === '' ) return '';
    $t_lists = $wpdb->prefix . 'post_notification_lists';
    $name = (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $t_lists WHERE slug=%s", $slug ) );
    return is_string( $name ) ? $name : '';
}

/**
 * Create a list if it does not exist.
 * @return int List ID (existing or newly created), 0 on failure.
 */
function pn_list_create( string $list_slug, array $args = array() ): int {
    global $wpdb;
    $slug = sanitize_key( $list_slug );
    if ( $slug === '' ) return 0;
    $name = isset( $args['name'] ) && $args['name'] !== '' ? sanitize_text_field( $args['name'] ) : $slug;

    $t_lists = $wpdb->prefix . 'post_notification_lists';
    $existing = pn_list_get_id_by_slug( $slug );
    if ( $existing ) {
        // Optionally update name
        $wpdb->update( $t_lists, array( 'name' => $name ), array( 'id' => $existing ) );
        return (int) $existing;
    }
    $ok = $wpdb->insert( $t_lists, array(
        'slug'       => $slug,
        'name'       => $name,
        'created_at' => current_time( 'mysql' ),
    ), array( '%s','%s','%s' ) );
    if ( $ok ) {
        $id = (int) $wpdb->insert_id;
        do_action( 'post_notification_list_created', $id, $slug, $name );
        return $id;
    }
    return 0;
}

/** Check if a list exists by slug. */
function pn_list_exists( string $list_slug ): bool {
    return pn_list_get_id_by_slug( $list_slug ) > 0;
}

/**
 * Rename a list's display name by slug.
 *
 * @param string $list_slug
 * @param array  $args ['name' => string]
 * @return bool True on success
 */
function pn_list_rename( string $list_slug, array $args = array() ): bool {
    global $wpdb;
    $slug = sanitize_key( $list_slug );
    if ( $slug === '' ) return false;
    $new_name = isset( $args['name'] ) ? (string) $args['name'] : '';
    $new_name = $new_name !== '' ? sanitize_text_field( $new_name ) : '';
    if ( $new_name === '' ) return false;

    $t_lists = $wpdb->prefix . 'post_notification_lists';
    $id = pn_list_get_id_by_slug( $slug );
    if ( ! $id ) return false;
    $old_name = pn_list_get_name_by_slug( $slug );
    $updated = $wpdb->update( $t_lists, array( 'name' => $new_name ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
    if ( $updated === false ) return false;
    /** Action: fired after a list was renamed. */
    do_action( 'post_notification_list_renamed', (int) $id, $slug, $old_name, $new_name );
    return true;
}

/**
 * Ensure a list exists and (optionally) its display name matches.
 * Applies a policy filter: `pn/list/rename_mode` → 'log' | 'enforce' | 'ignore'. Default 'log'.
 *
 * @param string $list_slug
 * @param array  $args ['name' => string]
 * @return int|bool List ID on success, false on failure
 */
function pn_list_ensure( string $list_slug, array $args = array() ) {
    $id = pn_list_create( $list_slug, $args );
    if ( ! $id ) return false;

    // Optionally align display name according to policy
    $desired_name = isset( $args['name'] ) && $args['name'] !== '' ? (string) $args['name'] : '';
    if ( $desired_name === '' ) return $id;

    $current_name = pn_list_get_name_by_slug( $list_slug );
    if ( $current_name !== '' && $current_name !== $desired_name ) {
        $mode = apply_filters( 'pn/list/rename_mode', 'log', $list_slug, $current_name, $desired_name );
        if ( $mode === 'enforce' ) {
            pn_list_rename( $list_slug, array( 'name' => $desired_name ) );
        } elseif ( $mode === 'log' ) {
            // Best-effort logging without introducing a hard dependency on any logger
            if ( function_exists( 'error_log' ) ) {
                @error_log( sprintf('[PostNotification] List name differs (slug=%s, current="%s", desired="%s")', $list_slug, $current_name, $desired_name) );
            }
        }
        // 'ignore' → do nothing
    }

    return $id;
}

/** Add a user to a list (idempotent). */
function pn_list_add_user( string $list_slug, $user ): bool {
    global $wpdb;
    $list_id = pn_list_get_id_by_slug( $list_slug );
    if ( ! $list_id ) { $list_id = pn_list_create( $list_slug ); }
    if ( ! $list_id ) return false;
    $user_id = is_object( $user ) ? (int) $user->ID : (int) $user;
    if ( $user_id <= 0 ) return false;
    $t_rel = $wpdb->prefix . 'post_notification_list_users';
    // Upsert-like behavior
    $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t_rel WHERE list_id=%d AND user_id=%d", $list_id, $user_id ) );
    if ( $exists ) return true;
    $ok = $wpdb->insert( $t_rel, array(
        'list_id'  => $list_id,
        'user_id'  => $user_id,
        'added_at' => current_time( 'mysql' ),
    ), array( '%d','%d','%s' ) );
    if ( $ok ) do_action( 'post_notification_list_user_added', $list_id, $user_id );
    return (bool) $ok;
}

/** Remove a user from a list. */
function pn_list_remove_user( string $list_slug, $user ): bool {
    global $wpdb;
    $list_id = pn_list_get_id_by_slug( $list_slug );
    if ( ! $list_id ) return false;
    $user_id = is_object( $user ) ? (int) $user->ID : (int) $user;
    if ( $user_id <= 0 ) return false;
    $t_rel = $wpdb->prefix . 'post_notification_list_users';
    $ok = $wpdb->delete( $t_rel, array( 'list_id' => $list_id, 'user_id' => $user_id ), array( '%d','%d' ) );
    if ( $ok ) do_action( 'post_notification_list_user_removed', $list_id, $user_id );
    return (bool) $ok;
}

/** Check if user is in list. */
function pn_list_user_is_subscribed( string $list_slug, $user ): bool {
    global $wpdb;
    $list_id = pn_list_get_id_by_slug( $list_slug );
    if ( ! $list_id ) return false;
    $user_id = is_object( $user ) ? (int) $user->ID : (int) $user;
    if ( $user_id <= 0 ) return false;
    $t_rel = $wpdb->prefix . 'post_notification_list_users';
    $cnt = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t_rel WHERE list_id=%d AND user_id=%d", $list_id, $user_id ) );
    return $cnt > 0;
}

/** Get user IDs of a list. */
function pn_list_get_users( string $list_slug ): array {
    global $wpdb;
    $list_id = pn_list_get_id_by_slug( $list_slug );
    if ( ! $list_id ) return array();
    $t_rel = $wpdb->prefix . 'post_notification_list_users';
    $ids = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM $t_rel WHERE list_id=%d ORDER BY user_id ASC", $list_id ) );
    return array_map( 'intval', (array) $ids );
}

/** Get all lists (as assoc arrays with id, slug, name). */
function pn_list_get_lists(): array {
    global $wpdb;
    $t_lists = $wpdb->prefix . 'post_notification_lists';
    $rows = $wpdb->get_results( "SELECT id, slug, name FROM $t_lists ORDER BY name ASC", 'ARRAY_A' );
    return is_array( $rows ) ? $rows : array();
}

// ==============================================
// Categories helpers (emails ↔ cats)
// ==============================================

/** Get email row ID by address (0 if missing). */
function pn_email_get_id( string $email ): int {
    global $wpdb;
    $email = sanitize_email( $email );
    if ( $email === '' ) return 0;
    $t_emails = $wpdb->prefix . 'post_notification_emails';
    $id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_emails} WHERE email_addr=%s", $email ) );
    return $id ?: 0;
}

/** Check if an email (by ID) has any category entries. */
function pn_email_has_any_cat( int $email_id ): bool {
    global $wpdb;
    if ( $email_id <= 0 ) return false;
    $t_cats = $wpdb->prefix . 'post_notification_cats';
    $cnt = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_cats} WHERE id=%d", $email_id ) );
    return $cnt > 0;
}

/** Subscribe an email (by ID) to a category (default 0 = All). */
function pn_email_subscribe_cat( int $email_id, int $cat_id = 0 ): bool {
    global $wpdb;
    if ( $email_id <= 0 ) return false;
    $t_cats = $wpdb->prefix . 'post_notification_cats';
    // avoid duplicates
    $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_cats} WHERE id=%d AND cat_id=%d", $email_id, $cat_id ) );
    if ( $exists ) return true;
    $ok = $wpdb->insert( $t_cats, array( 'id' => $email_id, 'cat_id' => $cat_id ), array( '%d','%d' ) );
    return (bool) $ok;
}

/** Batch ensure category assignment for a list of email addresses. Returns counters. */
function pn_emails_ensure_cat( array $emails, int $cat_id = 0 ): array {
    $result = [ 'added' => 0, 'skipped' => 0, 'errors' => 0 ];
    foreach ( $emails as $email ) {
        $email = is_string($email) ? trim( strtolower( $email ) ) : '';
        if ( $email === '' || ! is_email( $email ) ) { $result['skipped']++; continue; }
        try {
            $eid = pn_email_get_id( $email );
            if ( $eid <= 0 ) { $result['skipped']++; continue; }
            if ( pn_email_has_any_cat( $eid ) ) { $result['skipped']++; continue; }
            if ( pn_email_subscribe_cat( $eid, $cat_id ) ) { $result['added']++; }
            else { $result['errors']++; }
        } catch ( \Throwable $e ) {
            if ( function_exists('error_log') ) { @error_log('[PostNotification] pn_emails_ensure_cat error: '.$e->getMessage()); }
            $result['errors']++;
        }
    }
    return $result;
}

/** Ensure a WordPress category exists by slug; create if missing. Returns term_id (0 on failure). */
function pn_cat_get_or_create( string $slug, string $name, array $args = array() ): int {
    $slug = sanitize_title( $slug );
    $name = $name !== '' ? sanitize_text_field( $name ) : $slug;
    if ( $slug === '' ) return 0;

    $term = get_term_by( 'slug', $slug, 'category' );
    if ( $term && ! is_wp_error( $term ) ) {
        return (int) $term->term_id;
    }
    $insert = wp_insert_term( $name, 'category', array_merge( array( 'slug' => $slug ), $args ) );
    if ( is_wp_error( $insert ) ) {
        // If slug exists under a different name, try to fetch again to be safe
        $term = get_term_by( 'slug', $slug, 'category' );
        return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
    }
    return (int) ( $insert['term_id'] ?? 0 );
}

/** Remove a category assignment for an email_id. */
function pn_email_unsubscribe_cat( int $email_id, int $cat_id ): bool {
    global $wpdb;
    if ( $email_id <= 0 || $cat_id <= 0 ) return false;
    $t_cats = $wpdb->prefix . 'post_notification_cats';
    $ok = $wpdb->delete( $t_cats, array( 'id' => $email_id, 'cat_id' => $cat_id ), array( '%d','%d' ) );
    return (bool) $ok;
}

/**
 * Sync a segment: ensure each email is in primary $cat_id and removed from all $peer_cat_ids.
 * Returns counters: added, removed, skipped, errors.
 */
function pn_emails_sync_segment( array $emails, int $primary_cat_id, array $peer_cat_ids = array() ): array {
    global $wpdb;
    $res = [ 'added' => 0, 'removed' => 0, 'skipped' => 0, 'errors' => 0 ];
    if ( $primary_cat_id <= 0 ) return $res;
    $peer_cat_ids = array_values( array_unique( array_map( 'intval', (array) $peer_cat_ids ) ) );
    $t_cats = $wpdb->prefix . 'post_notification_cats';

    foreach ( $emails as $email ) {
        $email = is_string( $email ) ? trim( strtolower( $email ) ) : '';
        if ( $email === '' || ! is_email( $email ) ) { $res['skipped']++; continue; }
        try {
            $eid = pn_email_get_id( $email );
            if ( $eid <= 0 ) { $res['skipped']++; continue; }
            // Add to primary (idempotent)
            if ( pn_email_subscribe_cat( $eid, $primary_cat_id ) ) { $res['added']++; }
            // Remove from peer cats
            foreach ( $peer_cat_ids as $pc ) {
                if ( $pc > 0 ) {
                    $ok = $wpdb->delete( $t_cats, array( 'id' => $eid, 'cat_id' => $pc ), array( '%d','%d' ) );
                    if ( $ok ) { $res['removed'] += (int) $ok; }
                }
            }
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) { @error_log('[PostNotification] pn_emails_sync_segment error: '.$e->getMessage()); }
            $res['errors']++;
        }
    }
    return $res;
}

// WP-CLI: backfill categories for emails without any category
if ( defined('WP_CLI') && WP_CLI ) {
    \WP_CLI::add_command( 'pn cats backfill', function( $args, $assoc_args ) {
        $cat = isset($assoc_args['cat']) ? intval($assoc_args['cat']) : 0;
        global $wpdb;
        $t_emails = $wpdb->prefix . 'post_notification_emails';
        $t_cats   = $wpdb->prefix . 'post_notification_cats';
        $ids = $wpdb->get_col( "SELECT e.id FROM {$t_emails} e LEFT JOIN {$t_cats} c ON c.id=e.id WHERE c.id IS NULL" );
        $added=0;$errors=0;$skipped=0;
        foreach ( (array)$ids as $eid ) {
            $eid = (int)$eid;
            if ( $eid <= 0 ) { $skipped++; continue; }
            $ok = pn_email_subscribe_cat( $eid, $cat );
            if ( $ok ) $added++; else $errors++;
        }
        \WP_CLI::success( sprintf('Backfill completed. Added=%d, errors=%d, skipped=%d', $added, $errors, $skipped) );
    } );
}