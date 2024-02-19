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
	echo "<h2>" . post_notification_feheader() . "</h2>\n";
}

function post_notification_body_shortcode_function() {
	echo post_notification_febody() . "\n";
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
 * @param future Also list posts published in the future
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

/// Install a theme
function post_notification_installtheme() {
	if ( get_option( 'post_notification_filter_include' ) === 'no' ) {
		$src  = POST_NOTIFICATION_PATH . 'post_notification_template.php';
		$dest = ABSPATH . 'wp-content/themes/' . get_option( 'template' ) . '/post_notification_template.php';
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
	$cats_str .= '<script type="text/javascript"><!--' . "\n  post_notification_cats_init();\n //--></script>";

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
				$ip = sprintf( '%u', ip2long( $_SERVER['REMOTE_ADDR'] ) );
				if ( $ip < 0 || $ip === false ) {
					$ip = 0;
				} //This has changed with php 5
				$wpdb->query(
					"INSERT INTO $t_emails (email_addr,date_subscribed, act_code, subscribe_ip) " .
					"VALUES ('" . $addr . "','" . post_notification_date2mysql() . "', '$code', $ip  )"
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
