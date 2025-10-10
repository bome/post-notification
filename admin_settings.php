<?php

#------------------------------------------------------
# INFO
#------------------------------------------------------
# This is part of the Post Notification Plugin for
# Wordpress. Please see the readme.txt for details.
#------------------------------------------------------


function post_notification_is_file( $path, $file ) {
    if ( ! is_file( $path . '/' . $file ) ) {
        echo '<div class="error">' . __( 'File missing in profile folder.', 'post_notification' ) . '<br />';
        echo __( 'Folder', 'post_notification' ) . ': <b>' . $path . '</b><br />';
        echo __( 'File', 'post_notification' ) . ': <b>' . $file . '</b></div>';

        return false;
    }

    return true;
}

function post_notification_check_string( $path, $string ) {
    require( $path . '/strings.php' );
    if ( ! array_key_exists( $string, $post_notification_strings ) ) {
        echo '<div class="error">' . __( 'Missing string in string file.', 'post_notification' ) . '<br />';
        echo __( 'File', 'post_notification' ) . ': <b>' . $path . '/strings.php </b><br />';
        echo __( 'String', 'post_notification' ) . ': <b>' . $string . '</b></div>';

        return false;
    }

    return true;
}

function post_notification_is_profile( $path ) {
    if ( ! ( post_notification_is_file( $path, 'confirm.tmpl' ) && post_notification_is_file( $path, 'reg_success.tmpl' ) && post_notification_is_file( $path, 'select.tmpl' ) && post_notification_is_file( $path, 'subscribe.tmpl' ) && post_notification_is_file( $path, 'unsubscribe.tmpl' ) && post_notification_is_file( $path, 'strings.php' ) ) ) {
        return false;
    }

    if ( ! ( post_notification_check_string( $path, 'error' ) && post_notification_check_string( $path, 'already_subscribed' ) && post_notification_check_string( $path, 'activation_faild' ) && post_notification_check_string( $path, 'address_not_in_database' ) && post_notification_check_string( $path, 'sign_up_again' ) && post_notification_check_string( $path, 'deaktivated' ) && post_notification_check_string( $path, 'no_longer_activated' ) && post_notification_check_string( $path, 'check_email' ) && post_notification_check_string( $path, 'wrong_captcha' ) && post_notification_check_string( $path, 'all' ) && post_notification_check_string( $path, 'saved' ) ) ) {
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
                'uninstall'                  => array( 'type' => 'text', 'default' => 'no' ),
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
                'use_wc_mailer'              => array( 'type' => 'text', 'default' => 'no' ),
                'the_content'                => array( 'type'        => 'array',
                                                       'default'     => array(),
                                                       'option_name' => 'the_content_exclude',
                ),
                'captcha'                    => array( 'type' => 'int', 'default' => 0, 'min' => 0 ),
                'pause'                      => array( 'type' => 'int', 'default' => 0, 'min' => 0 ),
                'nervous'                    => array( 'type' => 'int', 'default' => 0, 'min' => 0 ),
                'maxmails'                   => array( 'type'        => 'int',
                                                       'default'     => 10,
                                                       'min'         => 1,
                                                       'option_name' => 'maxsend',
                ),
                'hdr_nl'                     => array( 'type'           => 'text',
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
                        $sanitized_value = serialize( array_map( 'sanitize_text_field', $value ) );
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

            update_option( $option_name, $sanitized_value );
        }

        // Handle profile and template - requires special file validation
        if ( isset( $_POST['en_profile'] ) && isset( $_POST['en_template'] ) ) {
            $en_profile  = sanitize_text_field( $_POST['en_profile'] );
            $en_template = sanitize_file_name( $_POST['en_template'] );

            if ( is_file( POST_NOTIFICATION_PATH . $en_profile . '/' . $en_template ) || is_file( POST_NOTIFICATION_DATA . $en_profile . '/' . $en_template ) ) {
                update_option( 'post_notification_profile', $en_profile );
                update_option( 'post_notification_template', $en_template );
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
            update_option( 'post_notification_selected_cats', substr( $categoryList, 1 ) );
        } else {
            update_option( 'post_notification_selected_cats', '' );
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

            // Add page template meta if we are using the template
            if ( get_option( 'post_notification_filter_include' ) === 'no' ) {
                add_post_meta( $post_ID, '_wp_page_template', 'post_notification_template.php', true );
            }

            // Save the page ID to the URL option
            update_option( 'post_notification_url', $post_ID );
        }

        echo '<H4>' . __( 'Data was updated.', 'post_notification' ) . '</H4>';
    }


    // Try to install the theme in case we need it. There be no warning. Warnings are only on the info-page.
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
        <h4> <?php _e( 'When to send', 'post_notification' ); ?></h4>
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
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Note:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <?php echo '<b>' . __( 'You can always override the settings above when writing a post. There is a Post Notification box somewhere near the upload box when writing or editing a post.', 'post_notification' ) . '</b>'; ?>
                </td>
            </tr>

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Nervous finger wait:', 'post_notification' ); ?></th>
                <td class="pn_td">
                    <input name="nervous" type="text" id="nervous" size="35"
                           value="<?php echo esc_attr( get_option( 'post_notification_nervous' ) ); ?>"/> <?php _e( 'seconds.', 'post_notification' ); ?>
                </td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td">
                    <?php _e( 'This option sets the time to wait before sending an Email. So if you have an nervous finger you can unpublish your post quickly and no mails are sent.', 'post_notification' ); ?>
                </td>
            </tr>

        </table>
        <h4> <?php _e( 'Look', 'post_notification' ); ?></h4>
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
                    <select name="en_template">
                        <?php echo $en_templates; ?>
                    </select>
                </td>
            </tr>

            <?php if ( is_woocommerce_activated() ) { ?>
                <tr class="pn_row">
                    <th class="pn_th_caption"><?php _e( 'Use woocommerce mailer (with WC template):', 'post_notification' ); ?></th>
                    <td class="pn_td">
                        <select name="use_wc_mailer">
                            <option value="no" <?php echo post_notification_get_selected( 'use_wc_mailer', 'no' ); ?>><?php _e( 'No', 'post_notification' ); ?></option>
                            <option value="yes" <?php echo post_notification_get_selected( 'use_wc_mailer', 'yes' ); ?>><?php _e( 'Yes', 'post_notification' ); ?></option>
                        </select>
                    </td>
                </tr>
            <?php } ?>

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


        <h4> <?php _e( 'Technical', 'post_notification' ); ?></h4>
        <table class="post_notification_admin_table">

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Number of mails to be sent in a burst:', 'post_notification' ); ?></th>
                <td class="pn_td"><input name="maxmails" type="text" id="maxmail" size="35"
                                         value="<?php echo esc_attr( get_option( 'post_notification_maxsend' ) ); ?>"/></td>
            </tr>


            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Pause between transmission:', 'post_notification' ); ?></th>
                <td class="pn_td"><input name="pause" type="text" id="pause" size="35"
                                         value="<?php echo esc_attr(  get_option( 'post_notification_pause' ) ); ?>"/> <?php _e( 'seconds.', 'post_notification' ); ?>
                </td>
            </tr>

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
                    <?php _e( 'According to the PHP-specification \r\n must be used. Never the less quite a few servers have trouble if they get a \r\n instead of \n. You\'ll see part of the header in your mail if you have the wrong selection.', 'post_notification' ) ?>
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

                <th class="pn_th_caption"><?php _e( 'Filters to exclude from filtering "the_content":', 'post_notification' ) ?></th>

                <td class="pn_td">
                    <?php
                    global $wp_filter;
                    $rem_filters = get_option( 'post_notification_the_content_exclude' );
                    if ( is_string( $rem_filters ) && strlen( $rem_filters ) ) {
                        $rem_filters = unserialize( $rem_filters );
                    }
                    if ( ! is_array( $rem_filters ) ) {
                        $rem_filters = array();
                    }

                    foreach ( $wp_filter['the_content'] as $filter_level => $filters_in_level ) {
                        foreach ( $filters_in_level as $filter ) {
                            if ( function_exists( '_wp_filter_build_unique_id' ) ) {
                                // If a function is passed the unique_id will return the function name.
                                // Therefore there should be no problem with backward compatibilty
                                // priority may/must be false as all functions should get an Id when being registered
                                // As prio = false, $tag is not needed at all!
                                $fn_name = _wp_filter_build_unique_id( 'the_content', $filter['function'], $filter_level );
                            } else {
                                $fn_name = $filter['function'];
                            }
                            if ( ! ( $fn_name === false ) ) {
                                echo '<input type="checkbox"  name="the_content[]" value="' . esc_attr( $fn_name ) . '" ';
                                if ( in_array( $fn_name, $rem_filters ) ) {
                                    echo ' checked="checked" ';
                                }

                                echo '>' . esc_html( $fn_name ) . '</input><br />';
                            }
                        }
                    } ?>
                </td>
            </tr>

            <tr class="pn_row">
                <td></td>
                <td class="pn_td">
                    <?php
                    _e( 'Some plugins use filters to modify the content of a post. You might not want some of them modifying your mails. Finding the right filters might need some playing around.', 'post_notification' ); ?>
                </td>
            </tr>


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
                                         value="<?php echo esc_attr(  get_option( 'post_notification_unsubscribe_email' ) ); ?>"/>
                </td>
            </tr>
        </table>


        <h4> <?php _e( 'Frontend', 'post_notification' ); ?></h4>
        <table class="post_notification_admin_table">

            <tr class="pn_row">
                <th class="pn_th_caption"><?php _e( 'Name of the Post Notification page:', 'post_notification' ); ?></th>
                <td class="pn_td"><input name="page_name" type="text" id="page_name" size="60"
                                         value="<?php echo esc_attr(  get_option( 'post_notification_page_name' ) ); ?>"/></td>
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
                                         value="<?php echo esc_attr( get_option( 'post_notification_captcha' ) ); ?>"/></td>
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
                <th class="pn_th_caption"><?php _e( 'Link to the Post Notification page:', 'post_notification' ); ?></th>
                <td class="pn_td"><input name="pn_url" type="text" id="pn_url" size="60"
                                         value="<?php echo esc_attr( get_option( 'post_notification_url' ) ); ?>"/></td>
            </tr>
            <tr class="pn_row">
                <td></td>
                <td class="pn_td">
                    <?php _e( 'This must be the URL or the ID of the page on which you subscribe.', 'post_notification' ) . ' ';
                    _e( 'If you pick "Add Post Notification page" this will be compleated automaticly.', 'post_notification' ) . ' ';
                    _e( 'Also see the Instructions for this.', 'post_notification' ); ?>
                </td>
            </tr>


            <tr class="pn_row">
                <td></td>
            </tr>


        </table>
        <h4> <?php _e( 'Miscellaneous', 'post_notification' ); ?></h4>
        <table class="post_notification_admin_table">


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
                    <select name="uninstall">
                        <option value="no" <?php echo post_notification_get_selected( 'uninstall', 'no' ); ?>><?php _e( 'No', 'post_notification' ); ?></option>
                        <option value="yes" <?php echo post_notification_get_selected( 'uninstall', 'yes' ); ?>><?php _e( 'Yes', 'post_notification' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr class="pn_row">
                <th class="pn_th_caption_warning" colspan="2">
                    <?php _e( 'WARNING: If this option is set, all database entries are deleted upon deactivation. Of course all data is lost.', 'post_notification' ); ?></th>

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
