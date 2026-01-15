<?php

#------------------------------------------------------
# INFO
#------------------------------------------------------
# This is part of the Post Notification Plugin for
# Wordpress. Please see the readme.txt for details.
#------------------------------------------------------

require_once plugin_dir_path( __FILE__ ) . 'add_logger.php';

function pn_get_logger() {
    global $pn_logger;
    if ( !isset( $pn_logger ) || $pn_logger === null ) {
        $pn_logger = function_exists( 'add_pn_logger' ) ? add_pn_logger( 'pn' ) : null;
    }
    return $pn_logger;
}

function pn_set_option( $option, $value ) {
    $logger = pn_get_logger();
    if ( $logger ) {
        // check if option has changed
        $old_value = get_option( $option );
        $new_value = $value;
        // if new_value is int, convert to string for comparison
        if ( is_numeric( $new_value ) ) {
            $new_value = (string) $new_value;
        }
        if ( $old_value !== $new_value ) {
            $logger->info( 'Updating option', [ 'option' => $option, 'old_value' => $old_value, 'new_value' => $new_value ] );
        } else {
            //$logger->info( 'Option unchanged', [ 'option' => $option, 'value' => $new_value ] );
        }
    }
    
    update_option( $option, $value );
}

function post_notification_is_file( $path, $file ) {
    if ( ! is_file( $path . '/' . $file ) ) {
        // Nur Warnung ausgeben, wenn es ein Sprach-Ordner ist
        if ( preg_match( '/[a-z]{2}_[A-Z]{2}$/', basename( $path ) ) ) {
            echo '<div class="error">' . __( 'File missing in profile folder.', 'post_notification' ) . '<br />';
            echo __( 'Folder', 'post_notification' ) . ': <b>' . esc_html( $path ) . '</b><br />';
            echo __( 'File', 'post_notification' ) . ': <b>' . esc_html( $file ) . '</b></div>';
        }

        return false;
    }

    return true;
}

function post_notification_check_string( $path, $string, $silent = false ) {
    static $invalid_once = array();

    // Load strings in a backward-compatible way
    global $post_notification_strings;
    // Ensure a clean slate for this include
    unset( $post_notification_strings );
    $loaded = include $path . '/strings.php';

    // Accept multiple legacy conventions
    if ( ! isset( $post_notification_strings ) || ! is_array( $post_notification_strings ) ) {
        if ( is_array( $loaded ) ) {
            $post_notification_strings = $loaded;
        } elseif ( isset( $strings ) && is_array( $strings ) ) { // very old packs
            $post_notification_strings = $strings;
        } elseif ( isset( $pn_strings ) && is_array( $pn_strings ) ) { // alternative var name used by forks
            $post_notification_strings = $pn_strings;
        }
    }

    if ( ! isset( $post_notification_strings ) || ! is_array( $post_notification_strings ) ) {
        if ( ! $silent && empty( $invalid_once[ $path ] ) ) {
            echo '<div class="error">' . __( 'Invalid strings file: $post_notification_strings is missing or not an array.', 'post_notification' ) . '<br />';
            echo __( 'File', 'post_notification' ) . ': <b>' . $path . '/strings.php</b></div>';
            $invalid_once[ $path ] = true;
        }
        return false;
    }

    if ( ! array_key_exists( $string, $post_notification_strings ) ) {
        if ( ! $silent ) {
            echo '<div class="error">' . __( 'Missing string in string file.', 'post_notification' ) . '<br />';
            echo __( 'File', 'post_notification' ) . ': <b>' . $path . '/strings.php </b><br />';
            echo __( 'String', 'post_notification' ) . ': <b>' . $string . '</b></div>';
        }
        return false;
    }

    return true;
}

function post_notification_is_profile( $path, $silent = true ) {
    if ( ! ( post_notification_is_file( $path, 'confirm.tmpl' ) && post_notification_is_file( $path, 'reg_success.tmpl' ) && post_notification_is_file( $path, 'select.tmpl' ) && post_notification_is_file( $path, 'subscribe.tmpl' ) && post_notification_is_file( $path, 'unsubscribe.tmpl' ) && post_notification_is_file( $path, 'strings.php' ) ) ) {
        return false;
    }

    if ( ! ( post_notification_check_string( $path, 'error', $silent ) && post_notification_check_string( $path, 'already_subscribed', $silent ) && post_notification_check_string( $path, 'activation_faild', $silent ) && post_notification_check_string( $path, 'address_not_in_database', $silent ) && post_notification_check_string( $path, 'sign_up_again', $silent ) && post_notification_check_string( $path, 'deaktivated', $silent ) && post_notification_check_string( $path, 'no_longer_activated', $silent ) && post_notification_check_string( $path, 'check_email', $silent ) && post_notification_check_string( $path, 'wrong_captcha', $silent ) && post_notification_check_string( $path, 'all', $silent ) && post_notification_check_string( $path, 'saved', $silent ) ) ) {
        return false;
    }

    return true;
}

function post_notification_select( $var, $comp ) {
    if ( get_option( 'post_notification_' . $var ) == $comp ) {
        return ' selected="selected" ';
    }

    return '';
}

function post_notification_select_yesno( $var ) {
    echo '<select name="' . $var . '" >';
    echo '<option value="no" ' . post_notification_select( $var, 'no' ) . ' >' . __( 'No', 'post_notification' ) . '</option>';
    echo '<option value="yes" ' . post_notification_select( $var, 'yes' ) . ' >' . __( 'Yes', 'post_notification' ) . '</option>';
    echo '</select>';
}

function post_notification_admin_sub() {
    echo '<h3>' . __( 'Settings', 'post_notification' ) . '</h3>';

    if ( ! empty( $_POST['updateSettings'] ) ) {
        // Security checks
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'post_notification' ) );
        }

        // Nonce verification for CSRF protection
        if ( ! isset( $_POST['post_notification_nonce'] ) || ! wp_verify_nonce( $_POST['post_notification_nonce'], 'post_notification_update_settings' ) ) {
            wp_die( __( 'Security check failed.', 'post_notification' ) );
        }

        // Settings configuration with type, default value, and validation rules
        $settings_config = array(
                // Feature-Schalter: Newsletter (pn_mailing) aktivieren
                'enable_mailing'             => array( 'type' => 'text', 'default' => 'no', 'allowed_values' => array('yes','no') ),
                'read_more'                  => array( 'type' => 'text', 'default' => '' ),
                'show_content'               => array( 'type' => 'text', 'default' => 'no' ),
                'send_default'               => array( 'type' => 'text', 'default' => 'no' ),
                'send_private'               => array( 'type' => 'text', 'default' => 'no' ),
                'send_page'                  => array( 'type' => 'text', 'default' => 'no' ),
                'subject'                    => array( 'type' => 'text', 'default' => '' ),
                'from_name'                  => array( 'type' => 'text', 'default' => '' ),
                'from_email'                 => array( 'type' => 'email', 'default' => '' ),
                'page_name'                  => array( 'type' => 'text', 'default' => '' ),
                'pn_url'                     => array( 'type' => 'text', 'default' => '', 'option_name' => 'url' ),
                'page_meta'                  => array( 'type' => 'text', 'default' => 'no' ),
                'filter_include'             => array( 'type' => 'text', 'default' => 'no' ),
                'debug'                      => array( 'type' => 'text', 'default' => 'yes' ),
                'lock'                       => array( 'type' => 'text', 'default' => 'file' ),
                'empty_cats'                 => array( 'type' => 'text', 'default' => 'no' ),
                'show_cats'                  => array( 'type' => 'text', 'default' => 'no' ),
                'sendcheck'                  => array( 'type' => 'text', 'default' => 'shutdown' ),
                'saved_tmpl'                 => array( 'type' => 'text', 'default' => 'no' ),
                'auto_subscribe'             => array( 'type' => 'text', 'default' => 'no' ),
                'honeypot'                   => array( 'type' => 'text', 'default' => 'no' ),
                'unsubscribe_email'          => array( 'type' => 'email', 'default' => '' ),
                'unsubscribe_link_in_header' => array( 'type' => 'text', 'default' => 'no' ),
                'mailer_method'              => array( 'type' => 'text', 'default' => 'wp', 'allowed_values' => array('wp','pn_smtp','wc') ),
                'smtp_host'                  => array( 'type' => 'text', 'default' => '' ),
                'smtp_port'                  => array( 'type' => 'int', 'default' => 587, 'min' => 1 ),
                'smtp_secure'                => array( 'type' => 'text', 'default' => 'tls', 'allowed_values' => array('none','ssl','tls') ),
                'smtp_auth'                  => array( 'type' => 'text', 'default' => 'yes', 'allowed_values' => array('yes','no') ),
                'smtp_user'                  => array( 'type' => 'text', 'default' => '' ),
                'smtp_timeout'               => array( 'type' => 'int', 'default' => 30, 'min' => 1 ),
                // Logging directory settings
                'log_dir_mode'               => array( 'type' => 'text', 'default' => 'default', 'allowed_values' => array( 'default', 'custom' ) ),
                'log_dir_custom'             => array( 'type' => 'text', 'default' => '' ),
                'the_content'                => array(
                        'type'        => 'array',
                        'default'     => array(),
                        'option_name' => 'the_content_exclude',
                ),
                'captcha'                    => array( 'type' => 'int', 'default' => 0, 'min' => 0 ),
                'pause'                      => array( 'type' => 'int', 'default' => 0, 'min' => 0 ),
                'nervous'                    => array( 'type' => 'int', 'default' => 0, 'min' => 0 ),
                'maxmails'                   => array(
                        'type'        => 'int',
                        'default'     => 10,
                        'min'         => 1,
                        'option_name' => 'maxsend',
                ),
                'hdr_nl'                     => array(
                        'type'           => 'text',
                        'default'        => 'n',
                        'allowed_values' => array( 'n', 'rn' ),
                ),
        );

        // Process all standard settings
        foreach ( $settings_config as $post_key => $config ) {
            $option_name = isset( $config['option_name'] ) ? $config['option_name'] : $post_key;
            $option_name = 'post_notification_' . $option_name;

            // Get value from POST or use default
            $value = isset( $_POST[ $post_key ] ) ? $_POST[ $post_key ] : $config['default'];

            // Sanitize based on type
            switch ( $config['type'] ) {
                case 'email':
                    $sanitized_value = sanitize_email( $value );
                    break;

                case 'array':
                    if ( is_array( $value ) ) {
                        // log array values
                        //$logger = pn_get_logger();
                        //$value = array_map( 'sanitize_text_field', $value );
                        //if ( $logger ) {
                        //    $logger->info( 'Processing array option: ' . $option_name . " with " . count( $value ) . " items:", $value );
                        //}

                        $sanitized_value = serialize( $value );
                    } else {
                        $sanitized_value = serialize( array() );
                    }
                    break;

                case 'int':
                    $sanitized_value = intval( $value );
                    // Validate minimum value if specified
                    if ( isset( $config['min'] ) && $sanitized_value < $config['min'] ) {
                        echo '<div class="error">' . sprintf( __( '%s must be %d or greater.', 'post_notification' ), ucfirst( str_replace( '_', ' ', $post_key ) ), $config['min'] ) . '</div>';
                        $sanitized_value = $config['default'];
                    }
                    break;

                case 'url':
                    $sanitized_value = esc_url_raw( $value );
                    break;

                case 'text':
                default:
                    $sanitized_value = sanitize_text_field( $value );
                    // Validate against allowed values if specified
                    if ( isset( $config['allowed_values'] ) && ! in_array( $sanitized_value, $config['allowed_values'], true ) ) {
                        $sanitized_value = $config['default'];
                    }
                    break;
            }

            pn_set_option( $option_name, $sanitized_value );
        }

        // Handle SMTP password: only update if a non-placeholder, non-empty value submitted
        if ( isset( $_POST['smtp_pass'] ) ) {
            $raw_pass = (string) $_POST['smtp_pass'];
            // Detect placeholder bullets (10 dots) and empty
            $is_placeholder = ( trim( $raw_pass ) === str_repeat('•', 10) );
            if ( ! $is_placeholder && $raw_pass !== '' ) {
                pn_set_option( 'post_notification_smtp_pass', sanitize_text_field( $raw_pass ) );
            }
        }

        // Handle profile and template - requires special file validation
        if ( isset( $_POST['en_profile'] ) && isset( $_POST['en_template'] ) ) {
            $en_profile  = sanitize_text_field( $_POST['en_profile'] );
            $en_template = sanitize_file_name( $_POST['en_template'] );

            if ( is_file( POST_NOTIFICATION_PATH . $en_profile . '/' . $en_template ) || is_file( POST_NOTIFICATION_DATA . $en_profile . '/' . $en_template ) ) {
                pn_set_option( 'post_notification_profile', $en_profile );
                pn_set_option( 'post_notification_template', $en_template );
            } else {
                // Don't save any profile/template information to avoid inconsistent state
                echo '<div class="error">' . __( 'Could not find the template in this profile. Please select a template and save again.', 'post_notification' ) . '</div>';
                $profile = $en_profile;
            }
        }

        // Handle default categories - requires special array processing
        if ( isset( $_POST['pn_cats'] ) && is_array( $_POST['pn_cats'] ) ) {
            $categories   = $_POST['pn_cats'];
            $categoryList = '';
            foreach ( $categories as $category ) {
                if ( is_numeric( $category ) ) {
                    $categoryList .= ',' . absint( $category );
                }
            }
            pn_set_option( 'post_notification_selected_cats', substr( $categoryList, 1 ) );
        } else {
            pn_set_option( 'post_notification_selected_cats', '' );
        }

        // Handle page creation if requested
        if ( isset( $_POST['add_page'] ) && $_POST['add_page'] === "add" ) {

            // Database change in 2.1
            if ( get_option( 'db_version' ) < 4772 ) {
                $post_status = "static";
            } else {
                $post_type   = "page";
                $post_status = "publish";
            }

            // Collect the data for new page
            if ( get_option( 'post_notification_filter_include' ) === 'no' ) {
                $post_title   = isset( $_POST['page_name'] ) ? sanitize_text_field( $_POST['page_name'] ) : __( 'Post Notification', 'post_notification' );
                $post_content = __( 'If you can read this, something went wrong. :-(', 'post_notification' );
            } else {
                $post_title   = '@@post_notification_header';
                $post_content = '@@post_notification_body';
            }
            $post_data = compact( 'post_content', 'post_title', 'post_status', 'post_type' );
            $post_data = add_magic_quotes( $post_data );

            // Insert the page
            $post_ID = wp_insert_post( $post_data );

            // Save the page ID to the URL option
            pn_set_option( 'post_notification_url', $post_ID );
        }

        // Safer handling for uninstall option
        $uninstall_choice = isset( $_POST['uninstall_action'] ) ? sanitize_text_field( $_POST['uninstall_action'] ) : 'keep';
        $set_uninstall    = 'no';
        if ( $uninstall_choice === 'delete' ) {
            $ack = ! empty( $_POST['uninstall_ack'] );
            $confirm = isset( $_POST['uninstall_confirm'] ) ? trim( wp_unslash( $_POST['uninstall_confirm'] ) ) : '';
            if ( $ack && $confirm === 'DELETE' ) {
                $set_uninstall = 'yes';
            } else {
                echo '<div class="error"><p>' . __( 'Uninstall not armed. Please confirm the checkbox and type DELETE to proceed.', 'post_notification' ) . '</p></div>';
            }
        }
        pn_set_option( 'post_notification_uninstall', $set_uninstall );

        // Add page template meta if we are using the template
        if ( get_option( 'post_notification_filter_include' ) === 'no' ) {
            add_post_meta( $post_ID, '_wp_page_template', 'post_notification_template.php', true );
        }

        echo '<h4>' . __( '✅ Settings updated.', 'post_notification' ) . '</h4>';
    }


    // Try to install the theme in case we need it. There will be no warning. Warnings are only on the info page.
    post_notification_installtheme();

    //Find Profiles
    if ( ! isset( $profile ) ) { //If the profile is already set, dont change.
        $profile = get_option( 'post_notification_profile' );
    }

    $profile_list = array();

    if ( file_exists( POST_NOTIFICATION_DATA ) ) {
        $dir_handle = opendir( POST_NOTIFICATION_DATA );
        while ( false !== ( $file = readdir( $dir_handle ) ) ) {
            if ( @is_dir( POST_NOTIFICATION_DATA . $file ) && $file[0] !== '.' && $file[0] !== '_' && $file !== "css" && $file !== "scss" ) {
                if ( post_notification_is_profile( POST_NOTIFICATION_DATA . $file ) ) {
                    $profile_list[] = $file;
                }
            }
        }
        closedir( $dir_handle );
    } else {
        echo '<div class = "error">' . __( 'Please save own Profiles in: ', 'post_notification' ) . ' ' . POST_NOTIFICATION_DATA . '<br/>';
        echo __( 'Otherwise they may be deleted using autoupdate. ', 'post_notification' ) . '</div>';
    }


    $dir_handle = opendir( POST_NOTIFICATION_PATH );
    while ( false !== ( $file = readdir( $dir_handle ) ) ) {
        if ( is_dir( POST_NOTIFICATION_PATH . $file ) && $file[0] !== '.' && $file[0] !== '_' && $file !== "css" && $file !== "scss" ) {
            if ( post_notification_is_profile( POST_NOTIFICATION_PATH . $file ) ) {
                if ( ! in_array( $file, $profile_list ) ) {
                    $profile_list[] = $file;
                }
            }
        }
    }
    closedir( $dir_handle );

    $en_profiles = "";
    foreach ( $profile_list as $profile_list_el ) {
        $en_profiles .= '<option value="' . esc_attr( $profile_list_el ) . '" ';
        if ( $profile_list_el == $profile ) {
            $en_profiles .= ' selected="selected"';
        }
        $en_profiles .= '>' . esc_html( $profile_list_el ) . '</option>';
    }

    // Find templates
    $template     = get_option( 'post_notification_template' );
    $dir_handle   = opendir( post_notification_get_profile_dir( $profile ) );
    $en_templates = "";
    while ( false !== ( $file = readdir( $dir_handle ) ) ) {
        if ( substr( $file, - 5 ) === '.html' || substr( $file, - 4 ) === '.txt' ) {
            $en_templates .= '<option value="' . esc_attr( $file ) . '" ';
            if ( $file == $template ) {
                $en_templates .= ' selected="selected"';
            }
            $en_templates .= '>' . esc_html( $file ) . '</option>';
        }
    }
    closedir( $dir_handle ); ?>

    <form id="update" method="post" action="admin.php?page=post_notification/admin.php&amp;action=settings">
        <?php wp_nonce_field( 'post_notification_update_settings', 'post_notification_nonce' ); ?>
        <h3><?php _e( 'Newsletter / Mailings', 'post_notification' ); ?></h3>
        <table class="post_notification_admin_table">
            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Newsletter-Funktion aktivieren:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <select name="enable_mailing">
                        <option value="no" <?php echo post_notification_get_selected( 'enable_mailing', 'no' ); ?>><?php _e( 'No', 'post_notification' ); ?></option>
                        <option value="yes" <?php echo post_notification_get_selected( 'enable_mailing', 'yes' ); ?>><?php _e( 'Yes', 'post_notification' ); ?></option>
                    </select>
                </td>
            </tr>
        </table>

        <h3> <?php _e( 'Sending', 'post_notification' ); ?></h3>
        <table class="post_notification_admin_table">

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Send normal posts by default:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <select name="send_default">
                        <option value="no" <?php echo post_notification_get_selected( 'send_default', 'no' ); ?>><?php _e( 'No', 'post_notification' ); ?></option>
                        <option value="yes" <?php echo post_notification_get_selected( 'send_default', 'yes' ); ?>><?php _e( 'Yes', 'post_notification' ); ?></option>
                    </select>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Send private posts by default:', 'post_notification' ); ?></th>
                <td class="pn_td"><select name="send_private">
                        <option value="no" <?php echo post_notification_get_selected( 'send_private', 'no' ); ?>><?php _e( 'No', 'post_notification' ); ?></option>
                        <option value="yes" <?php echo post_notification_get_selected( 'send_private', 'yes' ); ?>><?php _e( 'Yes', 'post_notification' ); ?></option>
                    </select></td>
            </tr>


            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Send pages by default:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <select name="send_page">
                        <option value="no" <?php echo post_notification_get_selected( 'send_page', 'no' ); ?>><?php _e( 'No', 'post_notification' ); ?></option>
                        <option value="yes" <?php echo post_notification_get_selected( 'send_page', 'yes' ); ?>><?php _e( 'Yes', 'post_notification' ); ?></option>
                    </select>
                    <br/>
                    <?php echo '<b>' . __( 'Note: You can always override the settings above when writing a post. There is a Post Notification box somewhere near the upload box when writing or editing a post.', 'post_notification' ) . '</b>'; ?>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Number of mails to be sent in a burst:', 'post_notification' ); ?></th>
                <td class="pn_td"><input name="maxmails" type="text" id="maxmail" size="35"
                                         value="<?php echo esc_attr( get_option( 'post_notification_maxsend' ) ); ?>"/>
                </td>
            </tr>

            <tr class="pn_row">
                <?php
                	global $wpdb;
                	$t_emails = $wpdb->prefix . 'post_notification_emails';

                    $maxsend = get_option( 'post_notification_maxsend' );
                    $pause = get_option( 'post_notification_pause' );
                    $emails_per_hour = ( $maxsend > 0 && $pause >= 0 ) ? floor( 3600 * $maxsend / $pause ) : 0;
                    $nummails = $wpdb->get_var( "SELECT COUNT(*) FROM $t_emails WHERE gets_mail = 1" );
                    $hours = ( $emails_per_hour > 0 ) ? ceil( $nummails * 10 / $emails_per_hour ) / 10.0 : 0;
                ?>
                <th class="pn_th_caption"><?php _e( 'Pause between transmission:', 'post_notification' ); ?></th>
                <td class="pn_td"><input name="pause" type="text" id="pause" size="35"
                                         value="<?php echo esc_attr( get_option( 'post_notification_pause' ) ); ?>"/> <?php 
                                         _e( 'seconds.', 'post_notification' ); 
                                         if ( $emails_per_hour > 0 ) {
                                             echo '<br/>' . sprintf( __( 'Approximately %d emails/hour', 'post_notification' ), $emails_per_hour );
                                         }
                                        if ( $hours > 0 ) {
                                            if ( $emails_per_hour > 0 ) {
                                                echo ', ';
                                            }
                                            echo sprintf( __( 'sending to all %d subscribers takes approx. %0.1f hours.', 'post_notification' ), $nummails, $hours );
                                        }
                                         ?>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Nervous finger wait:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <input name="nervous" type="text" id="nervous" size="35"
                           value="<?php echo esc_attr( get_option( 'post_notification_nervous' ) ); ?>"/> <?php _e( 'seconds.', 'post_notification' ); ?>
                    <br/>
                    <?php _e( 'This option sets the time to wait before sending an Email. So if you have an nervous finger you can unpublish your post quickly and no mails are sent.', 'post_notification' ); ?>
                </td>
            </tr>

        </table>


        <h3> <?php _e( 'Look', 'post_notification' ); ?></h3>
        <table class="post_notification_admin_table">

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Copy complete post in to the mail:', 'post_notification' ) ?></th>
                <td class="pn_td">
                    <select name="show_content">
                        <option value="no" <?php echo post_notification_get_selected( 'show_content', 'no' ); ?>><?php _e( 'No', 'post_notification' ); ?></option>
                        <option value="yes" <?php echo post_notification_get_selected( 'show_content', 'yes' ); ?>><?php _e( 'Yes', 'post_notification' ); ?></option>
                        <option value="more" <?php echo post_notification_get_selected( 'show_content', 'more' ); ?>><?php _e( 'Up to the more-tag.', 'post_notification' ); ?></option>
                        <option value="excerpt" <?php echo post_notification_get_selected( 'show_content', 'excerpt' ); ?>><?php _e( 'The excerpt', 'post_notification' ); ?></option>
                    </select>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Read-more-text:', 'post_notification' ); ?></th>
                <td class="pn_td"><input name="read_more" type="text" size="35"
                                         value="<?php echo esc_attr( get_option( 'post_notification_read_more' ) ); ?>"/>
                </td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td"><?php _e( 'This text is put behind the content in case the mail is truncated. E.g. because of the more-tag.', 'post_notification' ); ?>
                </td>
            </tr>


            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Profile:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <select name="en_profile">
                        <?php echo $en_profiles; ?>
                    </select>
                </td>
            </tr>


            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Template:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <select name="en_template" id="pn_template_select">
                        <?php echo $en_templates; ?>
                    </select>
                </td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td">
                    <?php
                    $current_profile_dir = post_notification_get_profile_dir( $profile );
                    echo '<p>' . esc_html__( 'Templates are files inside the selected profile folder. Files ending with .txt are sent as plain text; files ending with .html are sent as HTML.', 'post_notification' ) . '</p>';
                    echo '<p><strong>' . esc_html__( 'Current profile folder:', 'post_notification' ) . '</strong> <code>' . esc_html( $current_profile_dir ) . '</code></p>';
                    ?>
                    <div id="pn_wc_template_note" style="display:none; margin:8px 0; padding:8px; border-left:4px solid #2271b1; background:#f0f6fc;">
                        <strong><?php echo esc_html__( 'WooCommerce mailer notice', 'post_notification' ); ?>:</strong>
                        <span class="pn_wc_note_text"></span>
                    </div>
                    <p class="description">
                        <?php _e( 'To create a new template, copy an existing .txt or .html file in that folder, rename it, edit it to your needs, then select it here and click save.', 'post_notification' ); ?>
                    </p>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: 1: data profiles path */
                            __( 'Tip: Put your custom profiles into %s so they survive plugin updates.', 'post_notification' ),
                            '<code>' . esc_html( POST_NOTIFICATION_DATA ) . '</code>'
                        );
                        ?>
                    </p>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Email sending method:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <?php $mailer_method = get_option( 'post_notification_mailer_method', 'wp' ); ?>
                    <label><input type="radio" name="mailer_method" value="wp" <?php echo checked( $mailer_method, 'wp', false ); ?> /> <?php _e( 'Default WordPress mailer (wp_mail)', 'post_notification' ); ?></label><br/>
                    <label><input type="radio" name="mailer_method" value="pn_smtp" <?php echo checked( $mailer_method, 'pn_smtp', false ); ?> /> <?php _e( 'Post Notification SMTP (PHPMailer direct)', 'post_notification' ); ?></label><br/>
                    <?php if ( function_exists('is_woocommerce_activated') ? is_woocommerce_activated() : class_exists('WooCommerce') ) : ?>
                        <label><input type="radio" name="mailer_method" value="wc" <?php echo checked( $mailer_method, 'wc', false ); ?> /> <?php _e( 'WooCommerce mailer (uses WC email template)', 'post_notification' ); ?></label><br/>
                    <?php endif; ?>
                    <p class="description">
                        <?php _e( 'Choose how emails are sent: 1) WordPress default (uses your host’s mail or SMTP plugin), 2) Post Notification’s own SMTP using PHPMailer (configure below), 3) WooCommerce mailer if WooCommerce is installed.', 'post_notification' ); ?>
                    </p>
                </td>
            </tr>

            <tr class="pn_row pn_smtp_settings">
                <th class="pn_th_caption"><?php _e( 'SMTP settings (for Post Notification SMTP):', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <?php
                    $smtp_host = get_option('post_notification_smtp_host', '');
                    $smtp_port = (int) get_option('post_notification_smtp_port', 587);
                    $smtp_secure = get_option('post_notification_smtp_secure', 'tls');
                    $smtp_auth = get_option('post_notification_smtp_auth', 'yes');
                    $smtp_user = get_option('post_notification_smtp_user', '');
                    $smtp_pass = get_option('post_notification_smtp_pass', '');
                    $smtp_timeout = (int) get_option('post_notification_smtp_timeout', 30);
                    ?>
                    <p>
                        <label><?php _e('Host', 'post_notification'); ?>: <input type="text" name="smtp_host" value="<?php echo esc_attr($smtp_host); ?>" size="40" /></label>
                        &nbsp;&nbsp;
                        <label><?php _e('Port', 'post_notification'); ?>: <input type="number" name="smtp_port" value="<?php echo esc_attr($smtp_port); ?>" min="1" max="65535" /></label>
                        &nbsp;&nbsp;
                        <label><?php _e('Encryption', 'post_notification'); ?>:
                            <select name="smtp_secure">
                                <option value="none" <?php echo selected($smtp_secure, 'none', false); ?>><?php _e('None', 'post_notification'); ?></option>
                                <option value="ssl" <?php echo selected($smtp_secure, 'ssl', false); ?>>SSL</option>
                                <option value="tls" <?php echo selected($smtp_secure, 'tls', false); ?>>TLS</option>
                            </select>
                        </label>
                    </p>
                    <p>
                        <label><?php _e('Authentication', 'post_notification'); ?>:
                            <select name="smtp_auth">
                                <option value="no" <?php echo selected($smtp_auth, 'no', false); ?>><?php _e('No', 'post_notification'); ?></option>
                                <option value="yes" <?php echo selected($smtp_auth, 'yes', false); ?>><?php _e('Yes', 'post_notification'); ?></option>
                            </select>
                        </label>
                        &nbsp;&nbsp;
                        <label><?php _e('Username', 'post_notification'); ?>: <input type="text" name="smtp_user" value="<?php echo esc_attr($smtp_user); ?>" size="30" autocomplete="off" /></label>
                        &nbsp;&nbsp;
                        <label><?php _e('Password', 'post_notification'); ?>: <input type="password" name="smtp_pass" value="<?php echo $smtp_pass ? esc_attr( str_repeat('•', 10) ) : ''; ?>" size="30" autocomplete="new-password" placeholder="<?php esc_attr_e('Leave blank to keep current', 'post_notification'); ?>" /></label>
                    </p>
                    <p>
                        <label><?php _e('Timeout (seconds)', 'post_notification'); ?>: <input type="number" name="smtp_timeout" value="<?php echo esc_attr($smtp_timeout); ?>" min="1" max="300" /></label>
                    </p>
                    <p class="description"><?php _e('These settings are used only when "Post Notification SMTP" is selected above. Make sure your server firewall allows outbound SMTP connections to the configured host and port.', 'post_notification'); ?></p>
                </td>
            </tr>

            <script>
                (function(){
                    var form = document.getElementById('update');
                    if (!form) return;
                    var noteBox = document.getElementById('pn_wc_template_note');
                    var noteText = noteBox ? noteBox.querySelector('.pn_wc_note_text') : null;
                    var tmpl = document.getElementById('pn_template_select');
                    function isHtmlTemplate(){
                        if (!tmpl) return false;
                        var val = (tmpl.value||'').toLowerCase();
                        return val.slice(-5) === '.html';
                    }
                    function currentMethod(){
                        var m = form.querySelector('input[name="mailer_method"]:checked');
                        return m ? m.value : 'wp';
                    }
                    function updateNotes(){
                        if (!noteBox || !noteText) return;
                        var method = currentMethod();
                        if (method === 'wc'){
                            noteBox.style.display = '';
                            if (isHtmlTemplate()){
                                noteText.textContent = '<?php echo esc_js( __( 'WooCommerce mailer wraps your selected PN HTML template inside the WooCommerce email layout. Your template content is still used.', 'post_notification' ) ); ?>';
                            } else {
                                noteText.textContent = '<?php echo esc_js( __( 'WooCommerce mailer wraps emails in an HTML layout. Your selected .txt template will be converted into HTML inside the WooCommerce template. For best results, consider choosing a .html template.', 'post_notification' ) ); ?>';
                            }
                        } else if (method === 'pn_smtp' || method === 'wp') {
                            noteBox.style.display = 'none';
                        }
                    }
                    function toggleSmtp(){
                        var method = currentMethod();
                        var rows = form.querySelectorAll('.pn_smtp_settings');
                        for (var i=0;i<rows.length;i++){
                            rows[i].style.display = (method === 'pn_smtp') ? '' : 'none';
                        }
                        updateNotes();
                    }
                    form.addEventListener('change', function(e){
                        if (e.target){
                            if (e.target.name === 'mailer_method') toggleSmtp();
                            if (e.target.id === 'pn_template_select') updateNotes();
                        }
                    });
                    toggleSmtp();
                })();
            </script>

            <tr class="pn_row">
                <td></td>
                <td class="pn_td">
                    <?php _e( 'Templates with the extension .txt are sent as text-mails. Templates with the extension .html are sent as HTML-mails', 'post_notification' ); ?>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Subject:', 'post_notification' ); ?></th>
                <td class="pn_td"><input name="subject" type="text" size="35"
                                         value="<?php echo esc_attr( get_option( 'post_notification_subject' ) ); ?>"/>
                </td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td"><?php _e( 'Use @@blogname as placeholder for the blog name.', 'post_notification' ); ?>
                    <?php _e( 'Use @@title as placeholder for the title of the post.', 'post_notification' ); ?>
                </td>
            </tr>


            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Sender-Name:', 'post_notification' ); ?></th>
                <td class="pn_td"><input name="from_name" type="text" size="35"
                                         value="<?php echo esc_attr( get_option( 'post_notification_from_name' ) ); ?>"/>
                </td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td"><?php _e( 'Use @@blogname as placeholder for the blog name.', 'post_notification' ); ?>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Sender-Email:', 'post_notification' ); ?></th>
                <td class="pn_td"><input name="from_email" type="text" size="35"
                                         value="<?php echo esc_attr( get_option( 'post_notification_from_email' ) ); ?>"/>
                </td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td"><?php _e( 'Using admin email if empty.', 'post_notification' ); ?>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Show "saved.tmpl" when saving frontend settings.', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <select name="saved_tmpl">
                        <option value="no" <?php echo post_notification_get_selected( 'saved_tmpl', 'no' ); ?>><?php _e( 'No', 'post_notification' ); ?></option>
                        <option value="yes" <?php echo post_notification_get_selected( 'saved_tmpl', 'yes' ); ?>><?php _e( 'Yes', 'post_notification' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td">
                    <?php _e( 'You can add an additional saved.tmpl file to your profile. If you select this option this file will be shown to the subscriber when he saves his settings.', 'post_notification' ); ?>
                </td>
            </tr>

        </table>


        <h3> <?php _e( 'Frontend', 'post_notification' ); ?></h3>
        <table class="post_notification_admin_table">

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Name of the Post Notification page:', 'post_notification' ); ?></th>
                <td class="pn_td"><input name="page_name" type="text" id="page_name" size="60"
                                         value="<?php echo esc_attr( get_option( 'post_notification_page_name' ) ); ?>"/>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Add Post Notification page:', 'post_notification' ); ?></th>
                <td class="pn_td"><input name="add_page" type="checkbox" id="add_page" value="add"/></td>
            </tr>


            <tr class="pn_row">
                <td></td>
                <td class="pn_td">
                    <?php _e( 'Adds a Post Notification page to your pages.', 'post_notification' ) . ' ';
                    _e( 'The file "post_notification_template.php" has been copied into the active theme. You may want to edit this file to fit your needs.  ', 'post_notification' ); ?>
                    <br/>
                    <?php _e( 'This checkbox is cleared after execution.', 'post_notification' ); ?><br/>
                    <?php _e( 'Also see the Instructions for this.', 'post_notification' ); ?>
                </td>
            </tr>


            <tr class="pn_row">
                <?php
                $pn_page_url_caption_class = 'pn_th_caption';
                $pn_page_url = get_option( 'post_notification_url' ); 
                $pn_page_url_link = '';
                if (empty( $pn_page_url )) {
                    $pn_page_url_caption_class = 'pn_th_caption_warning';
                    $pn_page_url = '';
                } elseif ( is_numeric( $pn_page_url ) ) {
                    $pn_page_url_link = get_permalink( (int) $pn_page_url );
                }
                ?>
                <th class="<?php echo esc_attr( $pn_page_url_caption_class ); ?>"><?php _e( 'Post Notification page:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <input name="pn_url" type="text" id="pn_url" size="60"
                                         value="<?php echo esc_attr( $pn_page_url ); ?>"/>
                    <?php
                    if ( ! empty( $pn_page_url_link ) ) {
                        echo '                    <a href="' . esc_url( $pn_page_url_link ) . '" target="_blank">' . esc_html( $pn_page_url_link ) . "</a>\n";
                    }
                    echo "<br/>\n";
                    _e( 'This must be the URL or the ID of the page on which you subscribe.', 'post_notification' ) . ' ';
                    echo "<br/>\n";
                    _e( 'If you pick "Add Post Notification page" this will be completed automatically.', 'post_notification' );
                    echo "<br/>\n";
                    _e( 'For more information, check the instructions.', 'post_notification' );
                    ?>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Allow selection of categories:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <?php post_notification_select_yesno( 'show_cats' ); ?>
                </td>
            </tr>


            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Show empty categories:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <?php post_notification_select_yesno( 'empty_cats' ); ?>
                </td>
            </tr>


            <?php
            $selected_cats_list = get_option( 'post_notification_selected_cats' );
            $selected_cats      = explode( ',', $selected_cats_list ); ?>
            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Default categories:', 'post_notification' ); ?></th>
                <td class="pn_td"><?php echo post_notification_get_catselect( '', $selected_cats ); ?></td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td"><?php _e( 'The categories which will be automatically selected when a user subscribes, and which is also default for the Manage Addresses dialog. Choosing a category includes all subcategories.', 'post_notification' ); ?>
                </td>
            </tr>


            <tr class="pn_row">
                <th class="pn_th_caption">
                    <a href="<?php _e( 'http://en.wikipedia.org/wiki/Captcha', 'post_notification' ); ?>"><?php _e( 'Captcha-chars:', 'post_notification' ); ?></a>
                </th>
                <td class="pn_td"><input name="captcha" type="text" size="60"
                                         value="<?php echo esc_attr( get_option( 'post_notification_captcha' ) ); ?>"/>
                </td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td">
                    <?php _e( 'Number of Captcha-chars. 0 is no Captcha', 'post_notification' ); ?><br/>
                    <b><?php _e( 'Warning:', 'post_notification' ); ?></b>
                    <?php _e( 'Your template must support Captchas.', 'post_notification' ); ?>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php echo "Use Honeypot (hidden field in subscribtion form?"; ?></th>
                <td class="pn_td">
                    <select name="honeypot">
                        <option value="no" <?php echo post_notification_get_selected( 'honeypot', 'no' ); ?>><?php echo "No"; ?></option>
                        <option value="yes" <?php echo post_notification_get_selected( 'honeypot', 'yes' ); ?>><?php echo "Yes"; ?></option>
                    </select>
                </td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td">
                    <?php _e( 'Using WP Armour if available', 'post_notification' ); ?>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Post Notification link in the meta-section:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <select name="page_meta">
                        <option value="no" <?php echo post_notification_get_selected( 'page_meta', 'no' ); ?>><?php _e( 'No', 'post_notification' ); ?></option>
                        <option value="yes" <?php echo post_notification_get_selected( 'page_meta', 'yes' ); ?>><?php _e( 'Yes', 'post_notification' ); ?></option>
                    </select>
                </td>
            </tr>


            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Replacement in Posts:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <select name="filter_include">
                        <option value="no" <?php echo post_notification_get_selected( 'filter_include', 'no' ); ?>><?php _e( 'No', 'post_notification' ); ?></option>
                        <option value="yes" <?php echo post_notification_get_selected( 'filter_include', 'yes' ); ?>><?php _e( 'Yes', 'post_notification' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td">
                    <?php _e( 'The Stings @@post_notification_header and @@post_notification_body will be replaced in your post.', 'post_notification' ); ?>
                    <br/>
                    <?php _e( 'Also see the Instructions for this.', 'post_notification' ); ?>
                </td>
            </tr>

        </table>


        <h3> <?php _e( 'Technical', 'post_notification' ); ?></h3>
        <table class="post_notification_admin_table">

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Type of header line break:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <select name="hdr_nl">
                        <option value="rn" <?php echo post_notification_get_selected( 'hdr_nl', 'rn' ); ?>>\r\n</option>
                        <option value="n" <?php echo post_notification_get_selected( 'hdr_nl', 'n' ); ?>>\n</option>
                    </select>
                </td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td">
                    <?php
                    _e(
                            'According to RFC 5322, email headers must use \r\n (CRLF) line breaks. ' .
                            'Some legacy servers had issues with this, requiring \n as a workaround. ' .
                            'Modern mail systems (PHPMailer, wp_mail) fully support the standard \r\n. ' .
                            'Use \r\n (default) unless you experience header issues in emails.',
                            'post_notification'
                    );
                    ?>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Locking:', 'post_notification' ) ?></th>
                <td class="pn_td">
                    <select name="lock">
                        <option value="file" <?php echo post_notification_get_selected( 'lock', 'file' ); ?>><?php _e( 'File', 'post_notification' ); ?></option>
                        <option value="db" <?php echo post_notification_get_selected( 'lock', 'db' ); ?>><?php _e( 'Database', 'post_notification' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td">
                    <?php _e( 'Try using database locking if you are geting duplicate messages.', 'post_notification' );
                    echo ' ' . '<a href="http://php.net/manual/function.flock.php">' . __( 'More information.', 'post_notification' ) . '</a>'; ?>
                </td>
            </tr>


            <tr class="pn_row">

                <th class="pn_th_caption">
                    <?php _e( 'Filters to exclude from filtering "the_content":', 'post_notification' ); ?>
                </th>
                <td class="pn_td">
                    <?php pn_render_the_content_exclude_checklist(); ?>
                </td>
            </tr>

            <tr class="pn_row">
                <td></td>
                <td class="pn_td">
                    <?php
                    _e( 'Some plugins use filters to modify the content of a post. You might not want some of them modifying your mails. Finding the right filters might need some playing around.', 'post_notification' ); ?>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'When to send:', 'post_notification' ) ?></th>
                <td class="pn_td">
                    <select name="sendcheck">
                        <option value="head" <?php echo post_notification_get_selected( 'sendcheck', 'head' ); ?>><?php _e( 'Header', 'post_notification' ); ?></option>
                        <option value="footer" <?php echo post_notification_get_selected( 'sendcheck', 'footer' ); ?>><?php _e( 'Footer', 'post_notification' ); ?></option>
                        <option value="shutdown" <?php echo post_notification_get_selected( 'sendcheck', 'shutdown' ); ?>><?php _e( 'Shutdown', 'post_notification' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td">
                    <?php _e( 'By default PN sends mails after the page has been rendered and sent to the user (shutdown).' . ' Some hosters kill all scripts after the connection has been closed. ' . 'You can try sending mails before the page is rendered (header) or before creating the footer of the ' . 'page (footer).', 'post_notification' ); ?>
                </td>
            </tr>
            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Add user to PN when registering to WP:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <select name="auto_subscribe">
                        <option value="no" <?php echo post_notification_get_selected( 'auto_subscribe', 'no' ); ?>><?php _e( 'No', 'post_notification' ); ?></option>
                        <option value="yes" <?php echo post_notification_get_selected( 'auto_subscribe', 'yes' ); ?>><?php _e( 'Yes', 'post_notification' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Insert unsubscribe headers:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <select name="unsubscribe_link_in_header">
                        <option value="no" <?php echo post_notification_get_selected( 'unsubscribe_link_in_header', 'no' ); ?>><?php _e( 'No', 'post_notification' ); ?></option>
                        <option value="yes" <?php echo post_notification_get_selected( 'unsubscribe_link_in_header', 'yes' ); ?>><?php _e( 'Yes', 'post_notification' ); ?></option>
                    </select>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Email used for unsubscribe header:', 'post_notification' ); ?></th>
                <td class="pn_td"><input name="unsubscribe_email" type="text" id="pn_url" size="60"
                                         value="<?php echo esc_attr( get_option( 'post_notification_unsubscribe_email' ) ); ?>"/>
                </td>
            </tr>
        </table>


        <h3> <?php _e( 'Miscellaneous', 'post_notification' ); ?></h3>
        <table class="post_notification_admin_table">

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Log directory', 'post_notification' ); ?>:</th>
                <td class="pn_td">
                    <?php
                    $log_mode   = get_option( 'post_notification_log_dir_mode', 'default' );
                    $log_custom = get_option( 'post_notification_log_dir_custom', '' );
                    $default_dir = trailingslashit( POST_NOTIFICATION_PATH ) . 'log/';
                    $effective_dir = $default_dir;
                    if ( $log_mode === 'custom' && ! empty( $log_custom ) ) {
                        $effective_dir = trailingslashit( $log_custom );
                    }
                    ?>
                    <label>
                        <input type="radio" name="log_dir_mode" value="default" <?php echo checked( $log_mode, 'default', false ); ?> />
                        <?php _e( 'Default (plugin folder /log)', 'post_notification' ); ?>
                        <code><?php echo esc_html( $default_dir ); ?></code>
                    </label>
                    <br/>
                    <label>
                        <input type="radio" name="log_dir_mode" value="custom" <?php echo checked( $log_mode, 'custom', false ); ?> />
                        <?php _e( 'Custom path (outside webroot recommended)', 'post_notification' ); ?>
                    </label>
                    <br/>
                    <input type="text" name="log_dir_custom" id="log_dir_custom" size="60" value="<?php echo esc_attr( $log_custom ); ?>" placeholder="/var/log/post-notification/" />
                    <p class="description">
                        <?php _e( 'Note: PHP process must have write permissions to this directory. The plugin will attempt to create the folder if it does not exist.', 'post_notification' ); ?>
                    </p>
                    <p>
                        <?php _e( 'Effective log directory', 'post_notification' ); ?>:
                        <code><?php echo esc_html( $effective_dir ); ?></code><br/>
                        <?php _e( 'All logs are written to a single file named', 'post_notification' ); ?> <code>post-notification.log</code>.
                    </p>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Debug:', 'post_notification' ) ?></th>
                <td class="pn_td">
                    <select name="debug">
                        <option value="no" <?php echo post_notification_get_selected( 'debug', 'no' ); ?>><?php _e( 'No', 'post_notification' ); ?></option>
                        <option value="yes" <?php echo post_notification_get_selected( 'debug', 'yes' ); ?>><?php _e( 'Yes', 'post_notification' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Uninstall:', 'post_notification' ) ?></th>
                <td class="pn_td">
                    <fieldset id="uninstall_section" style="border:1px solid #d63638; padding:10px;">
                        <legend style="color:#d63638;"><strong><?php _e( 'Danger zone', 'post_notification' ); ?></strong></legend>
                        <label>
                            <input type="radio" name="uninstall_action" value="keep" <?php echo checked( get_option( 'post_notification_uninstall', 'no' ), 'no', false ); ?> />
                            <?php _e( 'Keep data on deactivation (recommended)', 'post_notification' ); ?>
                        </label>
                        <br/>
                        <label>
                            <input type="radio" name="uninstall_action" value="delete" <?php echo checked( get_option( 'post_notification_uninstall', 'no' ), 'yes', false ); ?> />
                            <?php _e( 'Delete ALL plugin data on deactivation', 'post_notification' ); ?>
                        </label>
                        <div id="uninstall_confirm_wrap" style="margin-top:8px; display:none;">
                            <p class="description" style="color:#a00; font-weight:bold;">
                                <?php _e( 'This will permanently delete all Post Notification database tables and options when you deactivate the plugin. This cannot be undone.', 'post_notification' ); ?>
                            </p>
                            <label>
                                <input type="checkbox" name="uninstall_ack" value="1" />
                                <?php _e( 'I understand that all data will be permanently deleted.', 'post_notification' ); ?>
                            </label>
                            <br/>
                            <label>
                                <?php _e( 'Type DELETE to confirm:', 'post_notification' ); ?>
                                <input type="text" name="uninstall_confirm" value="" size="10" autocomplete="off" />
                            </label>
                        </div>
                    </fieldset>
                    <script>
                        (function(){
                            var form = document.getElementById('update');
                            if (!form) return;
                            var keep = form.querySelector('input[name="uninstall_action"][value="keep"]');
                            var del  = form.querySelector('input[name="uninstall_action"][value="delete"]');
                            var wrap = document.getElementById('uninstall_confirm_wrap');
                            function update(){
                                if (del && del.checked){
                                    wrap.style.display = 'block';
                                } else {
                                    wrap.style.display = 'none';
                                }
                            }
                            if (keep) keep.addEventListener('change', update);
                            if (del) del.addEventListener('change', update);
                            update();
                            form.addEventListener('submit', function(e){
                                if (del && del.checked){
                                    var ack = form.querySelector('input[name="uninstall_ack"]');
                                    var conf = form.querySelector('input[name="uninstall_confirm"]');
                                    if (!ack || !ack.checked || !conf || conf.value !== 'DELETE'){
                                        e.preventDefault();
                                        alert('<?php echo esc_js( __( 'To enable uninstall on deactivation, please tick the acknowledgment and type DELETE exactly.', 'post_notification' ) ); ?>');
                                    }
                                }
                            });
                        })();
                    </script>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption">&nbsp;</th>
                <td class="pn_td"><input type="submit" name="updateSettings"
                                         value="<?php _e( 'save', 'post_notification' ); ?>"/></td>
            </tr>
        </table>
    </form>
    <?php
}

/**
 * Echoes a select with yes/no and the current value selected
 *
 * @param string $option_name The name of the option without "post_notification_"
 */
function post_notification_get_selected( $option_name, $value ) {
    $current_value = get_option( 'post_notification_' . $option_name, '' );

    return selected( $current_value, $value, false );
}
