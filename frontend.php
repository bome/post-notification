<?php

#------------------------------------------------------
# INFO
#------------------------------------------------------
# This is part of the Post Notification Plugin for
# Wordpress. Please see the readme.txt for details.
#------------------------------------------------------

/**
 * Frontend wrapper function
 * Outputs header and body in div
 *
 * @param string $class CSS class for wrapper div
 */
function post_notification_fe( $class = 'entry' ) {
	$content = post_notification_page_content();

	echo '<h2>' . esc_html( $content['header'] ) . "</h2>\n";
	echo '<div class="' . esc_attr( $class ) . '">' . $content['body'] . "</div>\n";
}

/**
 * Main function to create page content
 *
 * @return array Array with 'header' and 'body' keys
 */
function post_notification_page_content() {
	global $post_notification_page_content_glob, $wpdb;

	// Return cached content if available
	if ( $post_notification_page_content_glob ) {
		return $post_notification_page_content_glob;
	}

	// Initialize content array
	$content = array(
		'header' => '',
		'body'   => ''
	);

 // Load strings for the current profile with backward compatibility
	$strings_file = post_notification_get_profile_dir() . '/strings.php';
	if ( ! file_exists( $strings_file ) ) {
		$content['header'] = 'Error';
		$content['body'] = '<p class="error">Configuration error: strings file not found.</p>';
		return $content;
	}
	global $post_notification_strings;
	unset( $post_notification_strings );
	$loaded = include $strings_file;
	if ( ! isset( $post_notification_strings ) || ! is_array( $post_notification_strings ) ) {
		if ( is_array( $loaded ) ) {
			$post_notification_strings = $loaded;
		} elseif ( isset( $strings ) && is_array( $strings ) ) {
			$post_notification_strings = $strings;
		} elseif ( isset( $pn_strings ) && is_array( $pn_strings ) ) {
			$post_notification_strings = $pn_strings;
		}
	}

	// Validate strings are loaded
	if ( ! isset( $post_notification_strings ) || ! is_array( $post_notification_strings ) ) {
		$content['header'] = 'Error';
		$content['body'] = '<p class="error">Configuration error: strings not properly loaded.</p>';
		return $content;
	}

	// Database tables
	$t_emails = $wpdb->prefix . 'post_notification_emails';
	$t_cats = $wpdb->prefix . 'post_notification_cats';

	// Get and sanitize input parameters
	$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';
	$addr = isset( $_REQUEST['addr'] ) ? sanitize_email( $_REQUEST['addr'] ) : '';
	$code = isset( $_REQUEST['code'] ) ? sanitize_text_field( $_REQUEST['code'] ) : '';
	$pn_cats = isset( $_POST['pn_cats'] ) && is_array( $_POST['pn_cats'] ) ? array_map( 'absint', $_POST['pn_cats'] ) : array();

	// Check for one-click unsubscribe (RFC 8058)
	$one_click_unsubscribe = ( isset( $_POST['List-Unsubscribe'] ) && $_POST['List-Unsubscribe'] === 'One-Click' );

	// Apply email blacklist filter
	$excluded_emails = apply_filters( 'post_notification_excluded_email_addresses', '' );
	if ( ! empty( $addr ) && ! empty( $excluded_emails ) && strpos( $excluded_emails, $addr ) !== false ) {
		$content['header'] = esc_html__( 'Error', 'post_notification' );
		$content['body'] = '<p class="error">' . esc_html__( 'This email address cannot be used.', 'post_notification' ) . '</p>';
		return $content;
	}

	// Get blog settings
	$from_email = get_option( 'post_notification_from_email' );
	if ( empty( $from_email ) ) {
		$from_email = get_option( 'admin_email' );
	}
	$blogname = get_option( 'blogname' );

	$msg = '';

	// ******************************************************** //
	//                   WITH AUTHENTICATION CODE
	// ******************************************************** //
	if ( ! empty( $code ) && ! empty( $addr ) && is_email( $addr ) ) {
		// Verify code matches email - SECURE with prepared statement
		$email_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$t_emails} WHERE email_addr = %s AND act_code = %s",
			$addr,
			$code
		) );

		if ( ! $email_id ) {
			$content['header'] = $post_notification_strings['error'];
			$content['body'] = '<p class="error">' . $post_notification_strings['activation_faild'] . '</p>';
			$post_notification_page_content_glob = $content;
			return $content;
		}

		// Handle one-click unsubscribe
		if ( $one_click_unsubscribe ) {
			$wpdb->delete( $t_cats, array( 'id' => $email_id ), array( '%d' ) );
			$wpdb->delete( $t_emails, array( 'id' => $email_id ), array( '%d' ) );

			// Silent response for one-click
			http_response_code( 200 );
			exit;
		}

		// Check if user needs activation
		$gets_mail = $wpdb->get_var( $wpdb->prepare(
			"SELECT gets_mail FROM {$t_emails} WHERE id = %d",
			$email_id
		) );

		if ( $gets_mail != 1 ) {
			// Activate user
			$now = post_notification_date2mysql();
			$wpdb->update(
				$t_emails,
				array( 'gets_mail' => 1, 'date_subscribed' => $now ),
				array( 'id' => $email_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);

			// Add default categories
			$selected_cats = explode( ',', get_option( 'post_notification_selected_cats' ) );
			foreach ( $selected_cats as $cat_id ) {
				$cat_id = absint( $cat_id );
				if ( $cat_id > 0 ) {
					$wpdb->insert(
						$t_cats,
						array( 'id' => $email_id, 'cat_id' => $cat_id ),
						array( '%d', '%d' )
					);
				}
			}

			if ( isset( $post_notification_strings['welcome'] ) ) {
				$msg = '<h3>' . str_replace( '@@blogname', esc_html( $blogname ), $post_notification_strings['welcome'] ) . '</h3>';
			} else {
				$msg = '<h3>' . $post_notification_strings['saved'] . '</h3>';
			}
		}

		// Handle category selection
		if ( $action === 'subscribe' ) {
			$wpdb->update(
				$t_emails,
				array( 'gets_mail' => 1 ),
				array( 'id' => $email_id ),
				array( '%d' ),
				array( '%d' )
			);

			if ( get_option( 'post_notification_show_cats' ) === 'yes' ) {
				// Delete existing categories
				$wpdb->delete( $t_cats, array( 'id' => $email_id ), array( '%d' ) );

				// Add selected categories
				foreach ( $pn_cats as $cat_id ) {
					if ( $cat_id > 0 ) {
						$wpdb->insert(
							$t_cats,
							array( 'id' => $email_id, 'cat_id' => $cat_id ),
							array( '%d', '%d' )
						);
					}
				}
			}

			$msg .= '<h3>' . $post_notification_strings['saved'] . '</h3>';
		}

		// Handle unsubscribe
		if ( $action === 'unsubscribe' ) {
			$wpdb->delete( $t_cats, array( 'id' => $email_id ), array( '%d' ) );
			$wpdb->delete( $t_emails, array( 'id' => $email_id ), array( '%d' ) );

			$content['header'] = $post_notification_strings['deaktivated'];
			$content['body'] = str_replace(
				array( '@@addr', '@@blogname' ),
				array( esc_html( $addr ), esc_html( $blogname ) ),
				$post_notification_strings['no_longer_activated']
			);
			$post_notification_page_content_glob = $content;
			return $content;
		}

		// Show selection form
		$content['header'] = get_option( 'post_notification_page_name' );

		if ( get_option( 'post_notification_show_cats' ) === 'yes' ) {
			$subscribed_cats = $wpdb->get_col( $wpdb->prepare(
				"SELECT cat_id FROM {$t_cats} WHERE id = %d",
				$email_id
			) );
			$cats_str = post_notification_get_catselect( $post_notification_strings['all'], $subscribed_cats );
		} else {
			$cats_str = '';
		}

		$vars = '<input type="hidden" name="code" value="' . esc_attr( $code ) . '" />';
		$vars .= '<input type="hidden" name="addr" value="' . esc_attr( $addr ) . '" />';

		if ( $action === 'subscribe' && get_option( 'post_notification_saved_tmpl' ) === 'yes' ) {
			$msg = post_notification_ldfile( 'saved.tmpl' );
		} else {
			$msg .= post_notification_ldfile( 'select.tmpl' );
		}

		$msg = str_replace( '@@action', esc_url( post_notification_get_link() ), $msg );
		$msg = str_replace( '@@addr', esc_html( $addr ), $msg );
		$msg = str_replace( '@@cats', $cats_str, $msg );
		$msg = str_replace( '@@vars', $vars, $msg );

	} else {
		// ******************************************************** //
		//                   WITHOUT AUTHENTICATION
		// ******************************************************** //

		// Check if user already has WP account
		if ( ( $action === 'subscribe' || $action === '' ) && ! empty( $addr ) && is_email( $addr ) && email_exists( $addr ) ) {
			$params = array(
				'send' => true,
				'header' => __( 'User account already exists', 'post_notification' ),
				'msg' => '<p>' . __( 'Please log into your account.', 'post_notification' ) . '</p>',
			);
			$params = apply_filters( 'pn_can_send_confirmation_email_to_existing_wp_users', $params, $addr );

			if ( $params['send'] === false ) {
				$content['header'] = $params['header'];
				$content['body'] = $params['msg'];
				$post_notification_page_content_glob = $content;
				return $content;
			}
		}

		// Process subscription
		if ( ! empty( $addr ) && is_email( $addr ) && post_notification_check_spam() ) {

			if ( $action === 'subscribe' || $action === '' ) {
				// Send confirmation email
				$conf_url = post_notification_get_mailurl( $addr );
				$mailmsg = post_notification_ldfile( 'confirm.tmpl' );
				$mailmsg = str_replace( '@@addr', $addr, $mailmsg );
				$mailmsg = str_replace( '@@conf_url', $conf_url, $mailmsg );

    pn_send_mail( $addr, "$blogname - " . get_option( 'post_notification_page_name' ), $mailmsg, post_notification_header() );

				$content['header'] = $post_notification_strings['registration_successful'];
				$content['body'] = post_notification_ldfile( 'reg_success.tmpl' );
				$post_notification_page_content_glob = $content;
				return $content;
			}

			if ( $action === 'unsubscribe' ) {
				// Check if email exists
				$exists = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$t_emails} WHERE email_addr = %s",
					$addr
				) );

				if ( $exists ) {
					$conf_url = post_notification_get_mailurl( $addr ) . '&action=unsubscribe';
					$mailmsg = post_notification_ldfile( 'unsubscribe.tmpl' );
					$mailmsg = str_replace( array( '@@addr', '@@conf_url' ), array( $addr, $conf_url ), $mailmsg );
					pn_send_mail( $addr, "$blogname - " . $post_notification_strings['deaktivated'], $mailmsg, post_notification_header() );
				}

				$content['header'] = $post_notification_strings['deaktivated'];
				$content['body'] = str_replace(
					array( '@@addr', '@@blogname' ),
					array( esc_html( $addr ), esc_html( $blogname ) ),
					$post_notification_strings['unsubscribe_mail']
				);
				$post_notification_page_content_glob = $content;
				return $content;
			}
		}

		// Show errors if any
		if ( ! empty( $addr ) && ! empty( $action ) ) {
			if ( ! is_email( $addr ) ) {
				$msg .= '<p class="error">' . $post_notification_strings['check_email'] . '</p>';
			}
			if ( post_notification_is_wp_armour_active() ) {
				if ( wpa_check_is_spam( $_POST ) ) {
					$msg .= '<p class="error">' . esc_html__( 'Spam detected. Please try again.', 'post_notification' ) . '</p>';
				}
			} else {
				if ( ! post_notification_check_captcha() ) {
					$msg .= '<p class="error">' . $post_notification_strings['wrong_captcha'] . '</p>';
				}
				if ( ! post_notification_check_honeypot() ) {
					$msg .= '<p class="error">' . esc_html__( 'Spam detected.', 'post_notification' ) . '</p>';
				}
			}
		}

		// Get email from logged-in user if available
		if ( empty( $addr ) ) {
			$addr = post_notification_get_addr();
		}

		$vars = ! empty( $addr ) ? '<input type="hidden" name="addr" value="' . esc_attr( $addr ) . '" />' : '';

		$content['header'] = get_option( 'post_notification_page_name' );
		$msg .= post_notification_ldfile( 'subscribe.tmpl' );

		$msg = str_replace( '@@action', esc_url( post_notification_get_link( $addr ) ), $msg );
		$msg = str_replace( '@@addr', esc_attr( $addr ), $msg );
		$msg = str_replace( '@@cats', '', $msg );
		$msg = str_replace( '@@vars', $vars, $msg );
	}

	$msg = post_notification_add_spam_protection( $msg );
	$content['body'] = $msg;
	$post_notification_page_content_glob = $content;

	return $content;
}

// ============================================
// SPAM PROTECTION FUNCTIONS
// ============================================

/**
 * Check if WP Armour plugin is active and available
 *
 * @return bool True if WP Armour is active
 */
function post_notification_is_wp_armour_active() {
	return function_exists( 'wpa_check_is_spam' );
}

/**
 * Check for spam using WP Armour or fallback methods
 *
 * @return bool True if NOT spam, false if spam detected
 */
function post_notification_check_spam() {
	// Priority 1: Use WP Armour if available
	if ( post_notification_is_wp_armour_active() ) {
		if ( wpa_check_is_spam( $_POST ) ) {
			// Log spam attempt
			do_action( 'wpa_handle_spammers', 'post_notification_subscription', $_POST );
			error_log( 'Post Notification: Spam detected by WP Armour' );
			return false;
		}
		return true;
	}

	// Fallback 1: Check honeypot
	if ( ! post_notification_check_honeypot() ) {
		error_log( 'Post Notification: Spam detected by honeypot' );
		return false;
	}

	// Fallback 2: Check captcha
	if ( ! post_notification_check_captcha() ) {
		error_log( 'Post Notification: Invalid captcha' );
		return false;
	}

	return true;
}

/**
 * Check honeypot field (spam protection)
 * Only used if WP Armour is not available
 *
 * @return bool True if passed, false if spam detected
 */
function post_notification_check_honeypot() {
	if ( get_option( 'post_notification_honeypot' ) !== 'yes' ) {
		return true;
	}

	$honeypot = isset( $_POST['email'] ) ? sanitize_text_field( $_POST['email'] ) : '';

	// Honeypot should be empty (hidden field)
	return empty( $honeypot );
}

/**
 * Check captcha verification
 * Only used if WP Armour is not available
 *
 * @return bool True if passed or disabled
 */
function post_notification_check_captcha() {
	$captcha_length = absint( get_option( 'post_notification_captcha' ) );

	if ( $captcha_length === 0 ) {
		return true; // Captcha disabled
	}

	$captchacode = isset( $_POST['captchacode'] ) ? sanitize_text_field( $_POST['captchacode'] ) : '';
	$captcha = isset( $_POST['captcha'] ) ? sanitize_text_field( $_POST['captcha'] ) : '';

	if ( empty( $captchacode ) || empty( $captcha ) ) {
		return false;
	}

	require_once( POST_NOTIFICATION_PATH . 'class.captcha.php' );
	$my_captcha = new captcha( $captchacode, POST_NOTIFICATION_TEMP_PATH );

	return $my_captcha->verify( $captcha );
}

/** remove captcha placeholder if not needed */
function post_notification_remove_captcha_placeholder( $html ) {
	return preg_replace( '/<!--capt-->(.*?)<!--cha-->/is', '', $html );
}


/**
 * Add spam protection to form (WP Armour or Honeypot/Captcha)
 *
 * @param string $html Form HTML
 * @return string Modified HTML with spam protection
 */
function post_notification_add_spam_protection( $html ) {
	// Priority: Use WP Armour if available
	if ( post_notification_is_wp_armour_active() ) {
		$html = post_notification_remove_captcha_placeholder( $html );
		return post_notification_add_wp_armour( $html );
	}

	// Fallback: Use built-in honeypot
	if ( get_option( 'post_notification_honeypot' ) === 'yes' ) {
		$html = post_notification_add_honeypot( $html );
	}

	// Fallback: Use captcha
	$html = post_notification_add_captcha( $html );

	return $html;
}

/**
 * Add WP Armour protection to form
 *
 * @param string $html Form HTML
 * @return string Modified HTML with WP Armour field
 */
function post_notification_add_wp_armour( $html ) {
	$wp_armour_field = '<input type="hidden" id="wpa_initiator" class="wpa_initiator" name="wpa_initiator" value="" />';

	return str_replace( '</form>', $wp_armour_field . '</form>', $html );
}

/**
 * Add honeypot field to form
 * Only used if WP Armour is not available
 *
 * @param string $html Form HTML
 * @return string Modified HTML with honeypot
 */
function post_notification_add_honeypot( $html ) {
	$honeypot = '<style>#pn_verifyemail{opacity:0;position:absolute;top:-9999px;left:-9999px;height:0;width:0;}</style>';
	$honeypot .= '<p id="pn_verifyemail" aria-hidden="true" tabindex="-1">';
	$honeypot .= '<label for="email">' . esc_html__( 'Leave this field empty', 'post_notification' ) . '</label>';
	$honeypot .= '<input autocomplete="off" type="text" name="email" id="email" value="" tabindex="-1" />';
	$honeypot .= '</p>';

	return str_replace( '</form>', $honeypot . '</form>', $html );
}

/**
 * Add captcha to form
 * Only used if WP Armour is not available
 *
 * @param string $html Form HTML
 * @return string Modified HTML with captcha
 */
function post_notification_add_captcha( $html ) {
	$captcha_length = absint( get_option( 'post_notification_captcha' ) );
	if ( $captcha_length === 0 ) {
		return post_notification_remove_captcha_placeholder( $html );
	}

	require_once( POST_NOTIFICATION_PATH . 'class.captcha.php' );
	$captcha_code = md5( wp_rand( 0, 40000 ) );
	$my_captcha = new captcha( $captcha_code, POST_NOTIFICATION_TEMP_PATH );
	$captcha_hash = $my_captcha->get_pic( $captcha_length );

	if ( $captcha_hash ) {
		$captchaimg = POST_NOTIFICATION_TEMP_PATH_URL . '/cap_' . $captcha_hash . '.jpg';
		$html = str_replace( '@@captchaimg', esc_url( $captchaimg ), $html );
		$html = str_replace( '@@captchacode', esc_attr( $captcha_code ), $html );
	} else {
		$html = post_notification_remove_captcha_placeholder( $html );
	}

	return $html;
}
