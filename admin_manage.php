<?php

#------------------------------------------------------
# INFO
#------------------------------------------------------
# This is part of the Post Notification Plugin for
# Wordpress. Please see the readme.txt for details.
#------------------------------------------------------

function ldif2addresses( $input ) {
    // Simple built-in LDIF parser to extract mail: entries; no external class needed
    if ( ! is_readable( $input ) ) {
        return '';
    }

    $content = @file_get_contents( $input );
    if ( $content === false ) {
        return '';
    }

    // Collect all values of lines like "mail: someone@example.com"
    $retval = '';
    if ( preg_match_all( '/^mail:\s*(.+)$/mi', $content, $matches ) ) {
        foreach ( $matches[1] as $raw ) {
            $email = trim( $raw );
            if ( is_email( $email ) ) {
                $retval .= sanitize_email( $email ) . ',';
            }
        }
    }

    return $retval;
}

function post_notification_admin_sub() {
    // Security checks
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'post_notification' ) );
    }

    echo '<h3>' . __( 'Manage addresses', 'post_notification' ) . '</h3>';

    if ( empty( $_POST['manage'] ) ) {
        ?>
        <p> <?php _e( 'The Emails may be seprated by newline, space, comma, semi colon, tabs, [, ], &lt; or &gt;.', 'post_notification' ); ?>
            <br/>
            <b><?php _e( 'Watch out! There is only simple checking whether the email address is valid.', 'post_notification' ); ?> </b>
        </p>

         <form name="import" action="admin.php?page=post_notification/admin.php&amp;action=manage" method="post">
            <?php wp_nonce_field( 'post_notification_manage_addresses', 'post_notification_manage_nonce' ); ?>
            <b><?php _e( 'Emails', 'post_notification' ); ?>:</b>
            <br/>
            <textarea name="imp_emails" cols="60" rows="10" class="commentBox"><?php
                if ( isset( $_POST['ldif_import'] ) && $_POST['ldif_import'] ) {
                    // Verify nonce for LDIF import
                    if ( isset( $_POST['post_notification_ldif_nonce'] ) &&
                         wp_verify_nonce( $_POST['post_notification_ldif_nonce'], 'post_notification_ldif_import' ) ) {

                        // Validate file upload
                        if ( isset( $_FILES['ldif_file'] ) &&
                             $_FILES['ldif_file']['error'] === UPLOAD_ERR_OK ) {

                            // Check file extension
                            $file_name = $_FILES['ldif_file']['name'];
                            $file_ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

                            if ( $file_ext === 'ldif' ) {
                                echo esc_textarea( ldif2addresses( $_FILES['ldif_file']['tmp_name'] ) );
                            } else {
                                echo esc_html__( 'Invalid file type. Only .ldif files are allowed.', 'post_notification' );
                            }
                        }
                    }
                }
                ?></textarea>
            <br/><br/>

            <?php _e( 'What should be done?', 'post_notification' ); ?><br/>
            <input type="radio" name="logic" value="add"
                   checked="checked"><?php _e( 'Add selected categories', 'post_notification' ); ?></input><br/>
            <input type="radio" name="logic"
                   value="rem"><?php _e( 'Remove selected categories', 'post_notification' ); ?></input><br/>
            <input type="radio" name="logic"
                   value="repl"><?php _e( 'Replace with selected categories', 'post_notification' ); ?></input><br/>
            <input type="radio" name="logic"
                   value="del"><?php _e( 'Unsubscribe/Delete the listed emails', 'post_notification' ); ?></input><br/>
            <?php
            $selected_cats = explode( ',', get_option( 'post_notification_selected_cats' ) );
            echo post_notification_get_catselect( '', $selected_cats ); ?>
            <input type="submit" name="manage" value="<?php _e( 'Manage', 'post_notification' ); ?>"
                   class="commentButton"/>
            <input type="reset" name="Reset" value="<?php _e( 'Reset', 'post_notification' ); ?>"
                   class="commentButton"/><br/><br/><br/>
        </form>
        <?php
    } else {
        // Verify nonce for manage action
        if ( ! isset( $_POST['post_notification_manage_nonce'] ) ||
             ! wp_verify_nonce( $_POST['post_notification_manage_nonce'], 'post_notification_manage_addresses' ) ) {
            wp_die( __( 'Security check failed.', 'post_notification' ) );
        }

        global $wpdb;
        $t_emails = $wpdb->prefix . 'post_notification_emails';
        $t_cats   = $wpdb->prefix . 'post_notification_cats';

        // Validate and sanitize logic parameter
        $allowed_logic = array( 'add', 'rem', 'repl', 'del' );
        $logic = isset( $_POST['logic'] ) ? sanitize_text_field( $_POST['logic'] ) : 'add';

        if ( ! in_array( $logic, $allowed_logic, true ) ) {
            $logic = 'add';
        }

        // Get and validate categories
        $pn_cats = isset( $_POST['pn_cats'] ) && is_array( $_POST['pn_cats'] ) ? $_POST['pn_cats'] : array();
        $sanitized_cats = array();
        foreach ( $pn_cats as $cat ) {
            if ( is_numeric( $cat ) ) {
                $sanitized_cats[] = absint( $cat );
            }
        }

        // Get and validate email addresses
        $import_emails = isset( $_POST['imp_emails'] ) ? $_POST['imp_emails'] : '';
        $import_array = preg_split( '/[\s\n\[\]<>\t,;]+/', $import_emails, - 1, PREG_SPLIT_NO_EMPTY );

        foreach ( $import_array as $addr ) {
            // Sanitize email
            $addr = sanitize_email( $addr );

            // Skip empty addresses
            if ( empty( $addr ) ) {
                continue;
            }

            // Validate email
            if ( ! is_email( $addr ) ) {
                echo '<div class="error">' . esc_html__( 'Email is not valid:', 'post_notification' ) . ' ' . esc_html( $addr ) . '</div>';
                continue;
            }

            // Set Variables
            $gets_mail = 1;
            $now       = post_notification_date2mysql();

            // Check database for existing email - SECURE with prepared statement
            $mid = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM $t_emails WHERE email_addr = %s",
                    $addr
            ) );

            // Handle delete logic
            if ( $logic === 'del' ) {
                if ( $mid ) {
                    $wpdb->delete( $t_emails, array( 'id' => $mid ), array( '%d' ) );
                    $wpdb->delete( $t_cats, array( 'id' => $mid ), array( '%d' ) );
                    echo '<div>' . esc_html__( 'Removed email:', 'post_notification' ) . ' ' . esc_html( $addr ) . '</div>';
                } else {
                    echo '<div class="error">' . esc_html__( 'Email is not in DB:', 'post_notification' ) . ' ' . esc_html( $addr ) . '</div>';
                }
                continue;
            }

            // Create entry if doesn't exist
            if ( ! $mid ) {
                $wpdb->insert(
                        $t_emails,
                        array(
                                'email_addr'      => $addr,
                                'gets_mail'       => $gets_mail,
                                'last_modified'   => $now,
                                'date_subscribed' => $now
                        ),
                        array( '%s', '%d', '%s', '%s' )
                );
                echo '<div>' . esc_html__( 'Added Email:', 'post_notification' ) . ' ' . esc_html( $addr ) . '</div>';

                $mid = $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM $t_emails WHERE email_addr = %s",
                        $addr
                ) );
            }

            // Verify we have a valid ID
            if ( ! $mid ) {
                echo '<div class="error">' . esc_html__( 'Something went wrong with the Email:', 'post_notification' ) . ' ' . esc_html( $addr ) . '</div>';
                continue;
            }

            // Handle replace logic - delete existing categories
            if ( $logic === 'repl' ) {
                $wpdb->delete( $t_cats, array( 'id' => $mid ), array( '%d' ) );
            }

            // Process categories
            foreach ( $sanitized_cats as $cat ) {
                if ( $logic === 'rem' ) {
                    // Remove category
                    $wpdb->delete(
                            $t_cats,
                            array( 'id' => $mid, 'cat_id' => $cat ),
                            array( '%d', '%d' )
                    );
                } else {
                    // Add category if not exists
                    $exists = $wpdb->get_var( $wpdb->prepare(
                            "SELECT id FROM $t_cats WHERE id = %d AND cat_id = %d",
                            $mid,
                            $cat
                    ) );

                    if ( ! $exists ) {
                        $wpdb->insert(
                                $t_cats,
                                array( 'id' => $mid, 'cat_id' => $cat ),
                                array( '%d', '%d' )
                        );
                    }
                }
            }

            echo '<div>' . esc_html__( 'Updated Email:', 'post_notification' ) . ' ' . esc_html( $addr ) . '</div>';
        } // end foreach
    }
}