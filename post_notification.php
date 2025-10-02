<?php

/*
Plugin Name: Post Notification
Plugin URI: https://github.com/bome/post-notification
Description: Sends an email to all subscribers. See Readme2.txt or instructions for details.
Author: bome, (based on Moritz Strübe)
Version: 1.2.50
License: GPL v2
Author URI: http://bome.com
Min WP Version: 5

*/


#------------------------------------------------------
# INFO
#------------------------------------------------------
# This is part of the Post Notification Plugin for
# Wordpress. Please see the readme.txt for details.
#------------------------------------------------------


/**
 * This file has all the stuff that is really needed to initialize the plugin.
 */
if ( ! defined( 'POST_NOTIFICATION_BASENAME' ) ) {
    define( 'POST_NOTIFICATION_BASENAME', plugin_basename( __FILE__ ) ); // e.g. post-notification/post-notification.php
}

if ( ! defined( 'POST_NOTIFICATION_PLUGIN_DIR' ) ) {
    // Folder name only, e.g. 'post-notification'
    define( 'POST_NOTIFICATION_PLUGIN_DIR', dirname( POST_NOTIFICATION_BASENAME ) );
}

if ( ! defined( 'POST_NOTIFICATION_PATH' ) ) {
    // Absolute filesystem path with trailing slash
    define( 'POST_NOTIFICATION_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
}

if ( ! defined( 'POST_NOTIFICATION_PATH_URL' ) ) {
    // Absolute URL with trailing slash
    define( 'POST_NOTIFICATION_PATH_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
}

if ( ! defined( 'POST_NOTIFICATION_PATH_REL' ) ) {
    // Relative path from plugins directory (used by some older APIs)
    // Example: 'post-notification'
    define( 'POST_NOTIFICATION_PATH_REL', POST_NOTIFICATION_PLUGIN_DIR );
}

if ( ! defined( 'POST_NOTIFICATION_CONTENT_DIR' ) ) {
    // Content dir (filesystem path), fallback safe
    $content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : trailingslashit( ABSPATH ) . 'wp-content';
    define( 'POST_NOTIFICATION_CONTENT_DIR', trailingslashit( $content_dir ) );
}

if ( ! defined( 'POST_NOTIFICATION_DATA' ) ) {
    // Writable data dir for the plugin inside wp-content
    define( 'POST_NOTIFICATION_DATA', POST_NOTIFICATION_CONTENT_DIR . 'post_notification/' );
}

//TODO: remove these in future versions
if ( ! defined( 'post_notification_path' ) ) {
    define( 'post_notification_path', POST_NOTIFICATION_PATH );
}

//Include all the helper functions
require_once( POST_NOTIFICATION_PATH . "functions.php" );

/**
 * This function returns the header of Post Notification as a string
 */
function post_notification_feheader() {
	require_once( POST_NOTIFICATION_PATH . 'frontend.php' );
	$content = post_notification_page_content();

	return $content['header'];
}

/**
 * This function returns the body of Post Notification as a string
 */
function post_notification_febody() {
	require_once( POST_NOTIFICATION_PATH . 'frontend.php' );
	$content = post_notification_page_content();

	return $content['body'];
}

// add js for categories
add_action( 'wp_enqueue_scripts', 'post_notification_enqueue_cats_js' );
add_action( 'admin_enqueue_scripts', 'post_notification_enqueue_cats_js' );

// add css
function postnotification_css() {
	wp_enqueue_style( 'postnotification_css', POST_NOTIFICATION_PATH_URL . '/css/postnotification.css' );
}

add_action( 'wp_enqueue_scripts', 'postnotification_css' );

// add css to admin backend
function post_notification_admin_css() {
	wp_enqueue_style( 'postnotification_admin_css', POST_NOTIFICATION_PATH_URL . '/css/postnotification_admin.css' );
}

add_action( 'admin_enqueue_scripts', 'post_notification_admin_css' );

/// Add the Admin panel
function post_notification_admin_adder() {
	$name = add_options_page( 'Post Notification', 'Post Notification', 'manage_options', 'post_notification/admin.php', 'post_notification_admin' );

	//This is for future use.
	//add_action('load-' . $name, 'post_notification_admin_load');
}


/// Show the admin panel
function post_notification_admin() {
	require_once( POST_NOTIFICATION_PATH . "admin.php" );
	post_notification_admin_page();
}

/// For Future use
function post_notification_admin_load() {
	require_once( POST_NOTIFICATION_PATH . "admin.php" );
	post_notification_admin_page_load();
}


/// Add the subscribe-page to the meta-information
function post_notification_meta() {
	if ( get_option( 'post_notification_page_meta' ) == 'yes' ) {
		$link = post_notification_get_link();
		if ( $link ) {
			echo '<li><a href="' . $link . '">' . get_option( 'post_notification_page_name' ) . '</a></li>';
		}
	}
}


function post_notification_get_subscribers() {
	return get_option( 'post_notification_subscribers' );
}


/// Add the option to whether to send a notification
function post_notification_form()
{
global $post_ID, $post, $wpdb;
$t_posts = $wpdb->prefix . 'post_notification_posts';

load_plugin_textdomain( 'post_notification', POST_NOTIFICATION_PATH_REL );

$textyes = __( 'Yes', 'post_notification' );
$textdef = __( 'Default', 'post_notification' );
$default = false;

$sendY = '';
$sendN = '';

if ( 0 != $post_ID ) { //We've got an ID.
	$status = $wpdb->get_var( "SELECT notification_sent FROM $t_posts WHERE post_ID = '$post_ID'" );

	if ( isset( $status ) ) { //It's in the DB
		if ( $status >= 0 ) { //It will be sent in the future
			$default = true;
			$textdef = __( 'Send Mails in queue.', 'post_notification' );
		} else { //It has been sent or is not being sent.
			$sendN = 'selected="selected"';
			if ( $status != - 2 ) { //If it's -2 nothing has been sent.
				$textyes = __( 'Resend', 'post_notification' );
			}
		}
	} else { //This one has been written bevore PN was installed.
		$sendN = 'selected="selected"';
	}
} else {
	$default = true;
}

if ( ! function_exists( 'add_meta_box' ) ) {
?>
<div id="advancedstuff" class="dbx-group">
    <div class="dbx-b-ox-wrapper">
        <fieldset id="emailnotification" class="dbx-box">
            <div class="dbx-h-andle-wrapper"><h3 class="dbx-handle">Post Notification</h3></div>
            <div class="dbx-c-ontent-wrapper">
                <div class="dbx-content">
					<?php
					}
					_e( 'Send notification when publishing?', 'post_notification' ); ?>
                    <select id="post_notification_notify" name="post_notification_notify">
						<?php if ( $default ) { ?>
                            <option value="def" selected="selected"><?php echo $textdef; ?></option>
						<?php } ?>
                        <option value="yes" <?php echo $sendY; ?>><?php echo $textyes; ?></option>
                        <option value="no" <?php echo $sendN; ?>><?php _e( 'No', 'post_notification' ) ?></option>
                    </select><?php

					if ( ! function_exists( 'add_meta_box' ) ) { ?>

                </div>
            </div>
        </fieldset>
    </div>
	<?php }
	}


	/// Add a Post to the notificationlist
	function post_notification_add( $post_ID ) {
		global $wpdb;
		$post = get_post( $post_ID );

		$t_posts = $wpdb->prefix . 'post_notification_posts';

		if ( empty ( $_POST['post_notification_notify'] ) ) {
			$notify = 'def';
		} else {
			$notify = $_POST['post_notification_notify'];
		}

		//Todo, userlevels

		$status = $wpdb->get_var( "SELECT notification_sent FROM $t_posts WHERE post_ID = '$post_ID'" );

		if ( $notify == 'def' && ! isset( $status ) ) { //default is not to change
			if ( get_option( 'db_version' ) < 4772 ) {
				if ( $post->post_status == 'post' ) {
					$notify = get_option( 'post_notification_send_default' );
				}
				if ( $post->post_status == 'private' ) {
					$notify = get_option( 'post_notification_send_default' );
				}
				if ( $post->post_status == 'static' ) {
					$notify = get_option( 'post_notification_send_page' );
				}
			} else {
				if ( $post->post_type == 'post' ) {
					$notify = get_option( 'post_notification_send_default' );
				}
				if ( $post->post_type == 'post'
				     && $post->post_status == 'private' ) {
					$notify = get_option( 'post_notification_send_default' );
				}
				if ( $post->post_type == 'page' ) {
					$notify = get_option( 'post_notification_send_page' );
				}
			}
		}


		if ( $notify == 'yes' ) {
			if ( isset( $status ) ) {
				$wpdb->query( "UPDATE $t_posts  SET notification_sent = 0 WHERE post_id = " . $post_ID );
			} else {
				$wpdb->query( "INSERT INTO $t_posts  (post_ID, notification_sent) VALUES ('$post_ID',  0)" );
			}
		} elseif ( $notify == 'no' ) {
			if ( $status != - 1 ) { //Mails are sent - no reason to change this
				if ( isset( $status ) ) {
					$wpdb->query( "UPDATE $t_posts  SET notification_sent = -2 WHERE post_id = " . $post_ID );
				} else {
					$wpdb->query( "INSERT INTO $t_posts  (post_ID, notification_sent) VALUES ('$post_ID',  -2)" );
				}
			}
		}
		// We should have an entry now, so lets write the time.
		$wpdb->query( "UPDATE $t_posts  SET date_saved = '" . post_notification_date2mysql() . "' WHERE post_id = " . $post_ID );
		post_notification_set_next_send();
	}


	/// Check whether a Mail is to be sent.
	function post_notification_send_check( $force = false ) {
		if ( get_option( 'post_notification_nextsend' ) == - 1 ) {
			return;
		}
		if ( get_option( 'post_notification_nextsend' ) > time() ) {
			return;
		} //There's nothing to send.
		if ( ( get_option( 'post_notification_debug' ) != 'yes' ) || $force ) { //Don't send in debugmode.
			require_once( POST_NOTIFICATION_PATH . 'sendmail.php' );
			post_notification_send();
		}
	}


	/// A wrapper function for the installation
	function post_notification_install_wrap() {
		require_once( POST_NOTIFICATION_PATH . 'install.php' );
		post_notification_install();
	}

	/// A wrapper function for the deinstallation
	function post_notification_uninstall_wrap() {
		require_once( POST_NOTIFICATION_PATH . 'install.php' );
		post_notification_uninstall();
	}


	//This function was provided by syfr12
	// http://pn.strübe.de/forum.php?req=thread&id=386
	function post_notification_register( $user_id ) {
		global $wpdb;

		if ( $user_id == 0 ) {
			$user_id = get_userdatabylogin( $_POST['user_login'] );
		}

		$auto_subscribe = get_option( 'post_notification_auto_subscribe' );
		if ( $auto_subscribe == "no" ) {
			return;
		}

		if ( 0 == $user_id ) {
			$user_id = (int) func_get_arg( 0 );
		}
		if ( 0 == $user_id ) {
			return;
		}

		$t_emails = $wpdb->prefix . 'post_notification_emails';
		$t_cats   = $wpdb->prefix . 'post_notification_cats';


		$user      = get_userdata( $user_id );
		$addr      = $user->user_email;
		$gets_mail = 1;
		$now       = post_notification_date2mysql();

		$mid = $wpdb->get_var( "SELECT id FROM $t_emails WHERE email_addr = '$addr'" );
		if ( ! $mid ) {
			$wpdb->query(
				"INSERT " . $t_emails .
				" (email_addr, gets_mail, last_modified, date_subscribed) " .
				" VALUES ('$addr', '$gets_mail', '$now', '$now')"
			);
			$mid = $wpdb->get_var( "SELECT id FROM $t_emails WHERE email_addr = '$addr'" );
		}

		$selected_cats = explode( ',', get_option( 'post_notification_selected_cats' ) );

		foreach ( $selected_cats as $cat ) {
			if ( is_numeric( $cat ) ) { //Security
				if ( ! $wpdb->get_var( "SELECT id FROM $t_cats WHERE id = $mid AND cat_id = $cat" ) ) {
					$wpdb->query( "INSERT INTO $t_cats (id, cat_id) VALUES($mid, $cat)" );
				}
			}
		}
	}


	//********************************************//
	// Actions
	//********************************************//


	function post_notification_gui_init() {
		if ( function_exists( 'add_meta_box' ) ) {
			//This starts with WP 2.5
			//$$fb 2020-07-21 the callback must be in quotes, too.
			add_meta_box( 'post_notification', 'Post Notification', 'post_notification_form', 'post', 'normal' );
			add_meta_box( 'post_notification', 'Post Notification', 'post_notification_form', 'page', 'normal' );
		} else {
			// Notify box in advanced mode
			add_action( 'edit_form_advanced', 'post_notification_form', 5 );
			// Notify box in page mode
			add_action( 'edit_page_form', 'post_notification_form', 5 );
		}

		// Notify box in simple mode
		add_action( 'simple_edit_form', 'post_notification_form', 5 );

		//Todo this shouldn't be here, but this is the most simple solution
		add_action( 'user_register', 'post_notification_register' );
	}

	add_action( 'admin_menu', 'post_notification_gui_init' );

	// Admin menu
	add_action( 'admin_menu', 'post_notification_admin_adder' );


	// Save for notification
	add_action( 'save_post', 'post_notification_add', 5 );

	// Send the notification
	if ( get_option( 'post_notification_sendcheck' ) == 'head' ) {
		add_action( 'wp_head', 'post_notification_send_check' );
	} elseif ( get_option( 'post_notification_sendcheck' ) == 'footer' ) {
		add_action( 'wp_footer', 'post_notification_send_check' );
	} else {
		add_action( 'shutdown', 'post_notification_send_check' );
	}


	// Trigger installation
	add_action( 'activate_post_notification/post_notification.php', 'post_notification_install_wrap' );

	// Trigger uninstallation
	add_action( 'deactivate_post_notification/post_notification.php', 'post_notification_uninstall_wrap' );

	// Add Metainformation
	add_action( 'wp_meta', 'post_notification_meta', 0 );

	// Copy template to theme
	add_action( 'switch_theme', 'post_notification_installtheme' );


	// Replacement of Post-Strings.
	// depraced, use shortcodes
	//if (get_option('post_notification_filter_include') != 'no') {
	//    require_once(POST_NOTIFICATION_PATH . 'frontend.php');
	//    add_filter('the_content', 'post_notification_filter_content');
	//    add_filter('the_title', 'post_notification_filter_content');
	//    add_filter('single_post_title', 'post_notification_filter_content');
	//}

	//Widget by  Philipp - at least the first version :-)

	function post_notification_widget( $args ) { // $args enthält Strings die vor/nach dem Widget und vor/nach dem Titel ausgegeben werden sollen
		// Ausgabe
		$options = get_option( 'post_notification_widget' );
		echo $args['before_widget'];
		echo $args['before_title'] . $options['title'] . $args['after_title'];
		echo '<form id="newsletter" method="post" action="' . post_notification_get_link() . '" >';
		echo '<p>' . $options['subtext'] . '<br/> <input type="text" style="direction:ltr; text-align: left" name="addr" size="' . $options['size'] . '" maxlength="50" value="' . post_notification_get_addr() . '"/> <br/>';
		echo '<input type="submit" name="submit" value="' . $options['submit'] . '" /></p>';
		echo '</form>';

		echo $args['after_widget'];
	}

	function post_notification_widget_control() {
		$options = $newoptions = get_option( 'post_notification_widget' );

		//Prefill with defaults
		load_plugin_textdomain( 'post_notification', POST_NOTIFICATION_PATH_REL );
		if ( empty( $newoptions['title'] ) ) {
			$newoptions['title'] = get_option( 'post_notification_page_name' );
		}
		if ( empty( $newoptions['subtext'] ) ) {
			$newoptions['subtext'] = __( 'Email:', 'post_notification' );
		}
		if ( empty( $newoptions['size'] ) ) {
			$newoptions['size'] = 15;
		}
		if ( empty( $newoptions['submit'] ) ) {
			$newoptions['submit'] = __( 'Submit', 'post_notification' );
		}


		//Write new options
		if ( isset( $_POST["post_notification_widget-sent"] ) ) {
    $newoptions['title']   = strip_tags( stripslashes( $_POST["post_notification_widget-title"] ) );
    $newoptions['subtext'] = strip_tags( stripslashes( $_POST["post_notification_widget-subtext"] ) );
    $newoptions['size']    = strip_tags( stripslashes( $_POST["post_notification_widget-size"] ) );
    $newoptions['submit']  = strip_tags( stripslashes( $_POST["post_notification_widget-submit"] ) );
}

		//Write to db if they changed
		if ( $options != $newoptions ) {
			$options = $newoptions;
			update_option( 'post_notification_widget', $options );
		}

		$title   = htmlspecialchars( $options['title'], ENT_QUOTES );
		$subtext = htmlspecialchars( $options['subtext'], ENT_QUOTES );
		$size    = htmlspecialchars( $options['size'], ENT_QUOTES );
		$submit  = htmlspecialchars( $options['submit'], ENT_QUOTES ); ?>
        <p><label for="post_notification_widget-title"><?php _e( 'Title:', 'post_notification' ); ?>
                <input style="width: 250px;" name="post_notification_widget-title" type="text"
                       value="<?php echo $title; ?>"/></label></p>

        <p><label for="post_notification_widget-title"><?php _e( 'Subtext:', 'post_notification' ); ?>
                <input style="width: 250px;" name="post_notification_widget-subtext" type="text"
                       value="<?php echo $subtext; ?>"/></label></p>


        <p><label for="post_notification_widget-size"><?php _e( 'Size:', 'post_notification' ); ?>
                <input name="post_notification_widget-size" type="text" value="<?php echo $size; ?>"/></label></p>

        <p><label for="post_notification_widget-submit"><?php _e( 'Submit:', 'post_notification' ); ?>
                <input name="post_notification_widget-submit" type="text" value="<?php echo $submit; ?>"/></label></p>

        <input type="hidden" id="post_notification_widget-submit" name="post_notification_widget-sent" value="1"/>
		<?php
	}


	// Register widget
	function post_notification_widget_registrieren() {
		if ( function_exists( 'wp_register_sidebar_widget' ) ) {
			wp_register_sidebar_widget( 'widget', 'Post Notification', 'post_notification_widget' );
			wp_register_widget_control( 'widget', 'Post Notification', 'post_notification_widget_control' );
		}
	}

	add_action( 'plugins_loaded', 'post_notification_widget_registrieren' );



