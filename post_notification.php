<?php
/**
 * Plugin Name: Post Notification
 * Plugin URI: https://github.com/bome/post-notification
 * Description: Sends an email to all subscribers.
 * Version: 1.3.1
 * Author: bome (based on other Authors, see README.md)
 * License: GPL v2
 * Requires at least: 5.0
 * Requires PHP: 8.3
 *
 * GitHub URI: bome/post-notification
 * Primary Branch: master
 */


#------------------------------------------------------
# INFO
#------------------------------------------------------
# This is part of the Post Notification Plugin for
# Wordpress. Please see the readme.txt for details.
#------------------------------------------------------

# Load Jetpack Autoloader (manages package versions across plugins)
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload_packages.php';

/* Optional in development:
 * Prefer dev versions (9999999-dev, dev-branch) while testing.
 */
if ( ! defined( 'JETPACK_AUTOLOAD_DEV' ) ) {
    define( 'JETPACK_AUTOLOAD_DEV', true );
}

require_once plugin_dir_path( __FILE__ ) . 'add_logger.php';

function get_pn_logger() {
    static $logger = null;

    if ($logger === null) {
        $logger = add_pn_logger();
    }

    return $logger;
}

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
    if ( defined( 'WP_CONTENT_DIR' ) ) {
        $content_dir = WP_CONTENT_DIR;
    } else {
        // ABSPATH may not be defined in non-WordPress contexts (e.g., during static analysis)
        $root_guess = defined( 'ABSPATH' ) ? ABSPATH : dirname( __FILE__, 3 ) . '/';
        $content_dir = trailingslashit( $root_guess ) . 'wp-content';
    }
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

// -----------------------------
// Custom Post Type: pn_mailing
// -----------------------------

add_action( 'init', function () {
    $labels = array(
        'name'               => _x( 'Mailings', 'post type general name', 'post_notification' ),
        'singular_name'      => _x( 'Mailing', 'post type singular name', 'post_notification' ),
        'menu_name'          => _x( 'Mailings', 'admin menu', 'post_notification' ),
        'name_admin_bar'     => _x( 'Mailing', 'add new on admin bar', 'post_notification' ),
        'add_new'            => _x( 'Add New', 'mailing', 'post_notification' ),
        'add_new_item'       => __( 'Add New Mailing', 'post_notification' ),
        'new_item'           => __( 'New Mailing', 'post_notification' ),
        'edit_item'          => __( 'Edit Mailing', 'post_notification' ),
        'view_item'          => __( 'View Mailing', 'post_notification' ),
        'all_items'          => __( 'All Mailings', 'post_notification' ),
        'search_items'       => __( 'Search Mailings', 'post_notification' ),
        'not_found'          => __( 'No mailings found.', 'post_notification' ),
        'not_found_in_trash' => __( 'No mailings found in Trash.', 'post_notification' ),
    );

    register_post_type( 'pn_mailing', array(
        'labels'             => $labels,
        'public'             => false,
        'exclude_from_search'=> true,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => false,
        'supports'           => array( 'title', 'editor' ),
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
        'menu_position'      => 26,
        'menu_icon'          => 'dashicons-email-alt',
    ) );
} );

// Metabox: Verteilerlisten + Senden-Button
add_action( 'add_meta_boxes', function() {
    add_meta_box( 'pn_mailing_lists', __( 'Verteilerlisten', 'post_notification' ), 'pn_mailing_render_lists_metabox', 'pn_mailing', 'side', 'high' );
    add_meta_box( 'pn_mailing_send', __( 'Versand', 'post_notification' ), 'pn_mailing_render_send_metabox', 'pn_mailing', 'side', 'high' );
} );

function pn_mailing_render_lists_metabox( WP_Post $post ) {
    wp_nonce_field( 'pn_mailing_meta', 'pn_mailing_meta_nonce' );
    $selected = (array) get_post_meta( $post->ID, '_pn_mailing_lists', true );
    // Fetch lists via API
    if ( ! function_exists( 'pn_list_get_lists' ) ) {
        echo '<p>' . esc_html__( 'List API not available.', 'post_notification' ) . '</p>';
        return;
    }
    $lists = pn_list_get_lists();
    if ( empty( $lists ) ) {
        echo '<p>' . esc_html__( 'No lists defined yet.', 'post_notification' ) . '</p>';
        return;
    }
    echo '<div class="pn-mailing-lists">';
    foreach ( $lists as $list ) {
        $slug = isset( $list['slug'] ) ? $list['slug'] : ( $list->slug ?? '' );
        $name = isset( $list['name'] ) ? $list['name'] : ( $list->name ?? $slug );
        if ( ! $slug ) { continue; }
        $checked = in_array( $slug, $selected, true ) ? 'checked' : '';
        echo '<label style="display:block;margin:2px 0;">';
        echo '<input type="checkbox" name="pn_mailing_lists[]" value="' . esc_attr( $slug ) . '" ' . $checked . ' /> ' . esc_html( $name );
        echo '</label>';
    }
    echo '</div>';
}

function pn_mailing_render_send_metabox( WP_Post $post ) {
    $sent = (bool) get_post_meta( $post->ID, '_pn_mailing_sent', true );
    if ( $sent ) {
        echo '<p><strong>' . esc_html__( 'Dieses Mailing wurde bereits versendet.', 'post_notification' ) . '</strong></p>';
        echo '<p>' . esc_html__( 'Bearbeiten ist gesperrt. Nutzen Sie „Duplizieren“, um eine neue Version zu erstellen.', 'post_notification' ) . '</p>';
    } else {
        $url = wp_nonce_url( add_query_arg( array( 'pn_mailing_send' => $post->ID ) ), 'pn_mailing_send_' . $post->ID );
        echo '<a href="' . esc_url( $url ) . '" class="button button-primary">' . esc_html__( 'Jetzt senden', 'post_notification' ) . '</a>';
    }
}

// Save lists
add_action( 'save_post_pn_mailing', function( $post_id, $post, $update ) {
    if ( ! isset( $_POST['pn_mailing_meta_nonce'] ) || ! wp_verify_nonce( $_POST['pn_mailing_meta_nonce'], 'pn_mailing_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
    if ( (bool) get_post_meta( $post_id, '_pn_mailing_sent', true ) ) { return; }
    $lists = isset( $_POST['pn_mailing_lists'] ) && is_array( $_POST['pn_mailing_lists'] ) ? array_map( 'sanitize_key', (array) $_POST['pn_mailing_lists'] ) : array();
    update_post_meta( $post_id, '_pn_mailing_lists', array_values( array_unique( $lists ) ) );
}, 10, 3 );

// Handle send action via admin
add_action( 'admin_init', function() {
    if ( empty( $_GET['pn_mailing_send'] ) ) { return; }
    $post_id = absint( $_GET['pn_mailing_send'] );
    if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
    check_admin_referer( 'pn_mailing_send_' . $post_id );
    pn_send_mailing( $post_id );
    wp_redirect( get_edit_post_link( $post_id, 'raw' ) );
    exit;
} );

// Lock sent mailings: prevent further edits
add_filter( 'map_meta_cap', function( $caps, $cap, $user_id, $args ) {
    if ( in_array( $cap, array( 'edit_post', 'delete_post' ), true ) ) {
        $post_id = isset( $args[0] ) ? (int) $args[0] : 0;
        if ( $post_id && get_post_type( $post_id ) === 'pn_mailing' && get_post_meta( $post_id, '_pn_mailing_sent', true ) ) {
            return array( 'do_not_allow' );
        }
    }
    return $caps;
}, 10, 4 );

// Row action: Duplicate
add_filter( 'post_row_actions', function( $actions, $post ) {
    if ( $post->post_type !== 'pn_mailing' ) { return $actions; }
    $url = wp_nonce_url( add_query_arg( array( 'action' => 'pn_mailing_duplicate', 'post' => $post->ID ), admin_url( 'edit.php?post_type=pn_mailing' ) ), 'pn_mailing_duplicate_' . $post->ID );
    $actions['pn_mailing_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplizieren', 'post_notification' ) . '</a>';
    return $actions;
}, 10, 2 );

add_action( 'admin_init', function() {
    if ( isset( $_GET['action'], $_GET['post'] ) && $_GET['action'] === 'pn_mailing_duplicate' ) {
        $post_id = absint( $_GET['post'] );
        check_admin_referer( 'pn_mailing_duplicate_' . $post_id );
        if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
        $post = get_post( $post_id );
        $new  = array(
            'post_type'   => 'pn_mailing',
            'post_title'  => $post->post_title . ' (Copy)',
            'post_content'=> $post->post_content,
            'post_status' => 'draft',
        );
        $new_id = wp_insert_post( $new );
        if ( $new_id && ! is_wp_error( $new_id ) ) {
            delete_post_meta( $new_id, '_pn_mailing_sent' );
            $lists = (array) get_post_meta( $post_id, '_pn_mailing_lists', true );
            if ( $lists ) { update_post_meta( $new_id, '_pn_mailing_lists', $lists ); }
            wp_redirect( get_edit_post_link( $new_id, 'raw' ) );
            exit;
        }
    }
} );

/**
 * Versandroutine für ein pn_mailing.
 * - Holt ausgewählte Listen
 * - Ermittelt zugehörige User IDs
 * - Rendert personalisierte E-Mail und sendet
 * - Markiert Mailing als gesendet und sperrt weitere Edits
 */
function pn_send_mailing( int $post_id ): void {
    $logger = get_pn_logger();
    $post   = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'pn_mailing' ) { return; }
    if ( get_post_meta( $post_id, '_pn_mailing_sent', true ) ) { return; }

    $lists = (array) get_post_meta( $post_id, '_pn_mailing_lists', true );
    $user_ids = array();
    if ( function_exists( 'pn_list_get_users' ) ) {
        foreach ( $lists as $slug ) {
            $ids = pn_list_get_users( $slug );
            if ( is_array( $ids ) ) { $user_ids = array_merge( $user_ids, $ids ); }
        }
    }
    $user_ids = array_values( array_unique( array_map( 'intval', $user_ids ) ) );

    // Render base body once from the post content (non-personalized portion)
    require_once POST_NOTIFICATION_PATH . 'sendmail.php';
    $base_html = pn_render_content_for_email( $post_id, 'exclude' );

    $subject = (string) get_option( 'post_notification_subject', '@@blogname: @@title' );
    $subject = str_replace(
        array( '@@blogname', '@@title' ),
        array( get_bloginfo( 'name' ), wp_strip_all_tags( get_the_title( $post_id ) ) ),
        $subject
    );

    $header = post_notification_header( true ); // send HTML by default for mailings

    $sent_count = 0;
    foreach ( $user_ids as $uid ) {
        $user = get_userdata( $uid );
        if ( ! $user || empty( $user->user_email ) ) { continue; }
        $html = pn_personalize_html( $base_html, $user, $post_id );
        $body = $html; // no text alt for now
        $ok   = pn_send_mail( $user->user_email, $subject, $body, $header, array() );
        if ( $ok ) { $sent_count++; }
    }

    update_post_meta( $post_id, '_pn_mailing_sent', 1 );
    $logger && $logger->info( 'Mailing sent', array( 'tag' => 'Mailing', 'post_id' => $post_id, 'lists' => $lists, 'recipients' => count( $user_ids ), 'sent' => $sent_count ) );
}

/**
 * Personalisierung: ersetzt Standard-Tokens und erlaubt Erweiterung über Hooks.
 */
function pn_personalize_html( string $html, WP_User $user, int $post_id ): string {
    $replacements = array(
        '[pn:user_email]'        => $user->user_email,
        '[pn:user_display_name]' => $user->display_name,
        '[pn:site_name]'         => get_bloginfo( 'name' ),
        '[pn:site_url]'          => home_url( '/' ),
    );
    $replacements = apply_filters( 'post_notification_personalize_tokens', $replacements, $user, $post_id );
    $out = strtr( $html, $replacements );
    /** Allow final HTML mangling by other plugins */
    $out = apply_filters( 'post_notification_render_shortcodes', $out, $user, $post_id );
    return $out;
}

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

// add CSS to frontend
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
            if ( $post->post_type == 'post' && $post->post_status == 'private' ) {
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
    } else if ( $notify == 'no' ) {
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
        $wpdb->query( "INSERT " . $t_emails . " (email_addr, gets_mail, last_modified, date_subscribed) " . " VALUES ('$addr', '$gets_mail', '$now', '$now')" );
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
} else if ( get_option( 'post_notification_sendcheck' ) == 'footer' ) {
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

// Load textdomain ONCE (outside the form function)
add_action( 'plugins_loaded', function () {
    load_plugin_textdomain(
            'post_notification',
            false,
            dirname( POST_NOTIFICATION_BASENAME ) . '/languages'
    );
} );

function post_notification_form() {
    global $post_ID, $wpdb;

    $t_posts = $wpdb->prefix . 'post_notification_posts';

    $textyes = __( 'Yes', 'post_notification' );
    $textdef = __( 'Default', 'post_notification' );
    $default = false;

    $sendY = '';
    $sendN = '';

    // Ensure int
    $pid = isset( $post_ID ) ? (int) $post_ID : 0;

    if ( $pid !== 0 ) {
        // Safe query
        $status = $wpdb->get_var(
                $wpdb->prepare(
                        "SELECT notification_sent FROM {$t_posts} WHERE post_ID = %d",
                        $pid
                )
        );

        if ( isset( $status ) ) {
            if ( (int) $status >= 0 ) {
                $default = true;
                $textdef = __( 'Send Mails in queue.', 'post_notification' );
            } else {
                // Sent or not sending
                $sendN = 'selected="selected"';
                if ( (int) $status !== -2 ) {
                    $textyes = __( 'Resend', 'post_notification' );
                }
            }
        } else {
            // Post existed before plugin
            $sendN = 'selected="selected"';
        }
    } else {
        $default = true;
    }

    // If user explicitly chose "yes" before, reflect it (optional: read your meta/option)
    // Example: if you store a post meta 'post_notification_notify' == 'yes'
    // $prev = get_post_meta( $pid, 'post_notification_notify', true );
    // if ( $prev === 'yes' ) { $sendY = 'selected="selected"'; $default = false; $sendN = ''; }

    // Render field (simple, without ancient dbx wrapper)
    ?>
    <p>
        <label for="post_notification_notify"><?php _e( 'Send notification when publishing?', 'post_notification' ); ?></label><br>
        <select id="post_notification_notify" name="post_notification_notify">
            <?php if ( $default ) : ?>
                <option value="def" selected="selected"><?php echo esc_html( $textdef ); ?></option>
            <?php endif; ?>
            <option value="yes" <?php echo $sendY; ?>><?php echo esc_html( $textyes ); ?></option>
            <option value="no"  <?php echo $sendN; ?>><?php _e( 'No', 'post_notification' ); ?></option>
        </select>
    </p>
    <?php
}

