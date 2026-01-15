<?php

#------------------------------------------------------
# INFO
#------------------------------------------------------
# This is part of the Post Notification Plugin for
# Wordpress. Please see the readme.txt for details.
#------------------------------------------------------

function post_notification_admin_sub() {
    // Security check - only admins can send test emails
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'post_notification' ) );
    }

    global $wpdb;
    require_once( POST_NOTIFICATION_PATH . 'sendmail.php' );

    echo '<h3>' . esc_html__( 'Test', 'post_notification' ) . '</h3>';

    // Get form values with defaults
    $pid = isset( $_POST['pid'] ) ? absint( $_POST['pid'] ) : '';
    $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : get_option( 'post_notification_from_email' );
    $template = isset( $_POST['template'] ) ? sanitize_file_name( $_POST['template'] ) : get_option( 'post_notification_template' );
    $nosend = isset( $_POST['nosend'] ) && $_POST['nosend'] === 'true';

    ?>
    <form id="test" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=post_notification/admin.php&action=test' ) ); ?>">
        <?php wp_nonce_field( 'post_notification_test_email', 'post_notification_test_nonce' ); ?>

        <table class="post_notification_admin_table">
            <tr class="alternate">
                <th class="pn_th_caption"><?php esc_html_e( 'Post id:', 'post_notification' ); ?></th>
                <td>
                    <input name="pid" type="number" min="1" size="35" value="<?php echo esc_attr( $pid ); ?>" required />
                </td>
            </tr>
            <tr class="alternate">
                <td></td>
                <td>
                    <?php esc_html_e( 'This must be the ID of the post you want to send. You can find the ID under Manage->Posts.', 'post_notification' ); ?>
                </td>
            </tr>

            <tr class="alternate">
                <th class="pn_th_caption"><?php esc_html_e( 'Email:', 'post_notification' ); ?></th>
                <td>
                    <input name="email" type="email" size="35" value="<?php echo esc_attr( $email ); ?>" required />
                </td>
            </tr>

            <tr class="alternate">
                <th class="pn_th_caption"><?php esc_html_e( 'Template:', 'post_notification' ); ?></th>
                <td>
                    <select name="template" required>
                        <?php echo post_notification_get_template_options( $template ); ?>
                    </select>
                </td>
            </tr>

            <tr class="alternate">
                <th class="pn_th_caption"><?php esc_html_e( 'Do not send mail:', 'post_notification' ); ?></th>
                <td>
                    <input type="checkbox" name="nosend" value="true" <?php checked( $nosend ); ?> />
                    <span class="description"><?php esc_html_e( 'Check this to only preview the email without sending it.', 'post_notification' ); ?></span>
                </td>
            </tr>

            <tr class="alternate">
                <td>&nbsp;</td>
                <td>
                    <input type="submit" name="updateSettings" class="button button-primary" value="<?php esc_attr_e( 'Send test mail', 'post_notification' ); ?>" />
                </td>
            </tr>
        </table>
    </form>
    <?php

    // Process email sending
    if ( isset( $_POST['updateSettings'] ) ) {
        // Verify nonce
        if ( ! isset( $_POST['post_notification_test_nonce'] ) ||
             ! wp_verify_nonce( $_POST['post_notification_test_nonce'], 'post_notification_test_email' ) ) {
            wp_die( __( 'Security check failed.', 'post_notification' ) );
        }

        // Validate required fields
        if ( empty( $_POST['email'] ) || empty( $_POST['pid'] ) || empty( $_POST['template'] ) ) {
            echo '<div class="error"><p>' . esc_html__( 'Please fill in all required fields.', 'post_notification' ) . '</p></div>';
            return;
        }

        post_notification_process_test_email();
    }
}

/**
 * Get template options for select dropdown
 *
 * @param string $selected Currently selected template
 * @return string HTML options
 */
function post_notification_get_template_options( $selected = '' ) {
    $profile_dir = post_notification_get_profile_dir();

    if ( ! is_dir( $profile_dir ) || ! is_readable( $profile_dir ) ) {
        return '<option value="">' . esc_html__( 'No templates found', 'post_notification' ) . '</option>';
    }

    $options = '';
    $dir_handle = @opendir( $profile_dir );

    if ( ! $dir_handle ) {
        return '<option value="">' . esc_html__( 'Cannot read template directory', 'post_notification' ) . '</option>';
    }

    $templates = array();
    while ( false !== ( $file = readdir( $dir_handle ) ) ) {
        // Only show .html and .txt files
        $ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
        if ( in_array( $ext, array( 'html', 'txt' ), true ) ) {
            $safe_file = sanitize_file_name( $file );
            if ( $safe_file === $file ) { // Only if sanitization didn't change it
                $templates[] = $file;
            }
        }
    }
    closedir( $dir_handle );

    // Sort templates
    sort( $templates );

    foreach ( $templates as $file ) {
        $options .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $file ),
                selected( $selected, $file, false ),
                esc_html( $file )
        );
    }

    return $options;
}

/**
 * Process and send/preview test email
 */
function post_notification_process_test_email() {
    global $wpdb;

    echo '<div class="post-notification-test-results">';
    echo '<h3>' . esc_html__( 'Test Email Results', 'post_notification' ) . '</h3>';

    // Sanitize and validate inputs
    $post_id = absint( $_POST['pid'] );
    $email = sanitize_email( $_POST['email'] );
    $template = sanitize_file_name( $_POST['template'] );
    $nosend = isset( $_POST['nosend'] ) && $_POST['nosend'] === 'true';

    // Validate email
    if ( ! is_email( $email ) ) {
        echo '<div class="error"><p>' . esc_html__( 'Invalid email address.', 'post_notification' ) . '</p></div>';
        echo '</div>';
        return;
    }

    // Validate post exists
    $post = get_post( $post_id );
    if ( ! $post ) {
        echo '<div class="error"><p>' . esc_html__( 'Post not found.', 'post_notification' ) . '</p></div>';
        echo '</div>';
        return;
    }

    // Validate template file exists
    $template_path = post_notification_get_profile_dir() . '/' . $template;
    if ( ! file_exists( $template_path ) || ! is_readable( $template_path ) ) {
        echo '<div class="error"><p>' . esc_html__( 'Template file not found.', 'post_notification' ) . '</p></div>';
        echo '</div>';
        return;
    }

    $t_emails = $wpdb->prefix . 'post_notification_emails';

    // Check if email exists in database - SECURE with prepared statement
    $email_data = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.email_addr, e.id, e.act_code FROM {$t_emails} e WHERE e.email_addr = %s",
            $email
    ) );

    if ( ! $email_data ) {
        echo '<div class="error"><p>' .
             esc_html__( 'Error:', 'post_notification' ) . ' ' .
             esc_html__( 'Email has to be in the database.', 'post_notification' ) .
             '</p></div>';
        echo '</div>';
        return;
    }

    // Load user functions if available
    if ( file_exists( POST_NOTIFICATION_PATH . 'userfunctions.php' ) ) {
        include_once( POST_NOTIFICATION_PATH . 'userfunctions.php' );
    }

    $GLOBALS['wp_query']->init_query_flags();

    // Create email
    $maildata = post_notification_create_email( $post_id, $template );

    // Check if email was created successfully
    if ( $maildata === false ) {
        echo '<div class="error"><p>' . esc_html__( 'Failed to create email.', 'post_notification' ) . '</p></div>';
        echo '</div>';
        return;
    }

    // Send or preview email
    $send = ! $nosend;
    $maildata = post_notification_sendmail( $maildata, $email_data->email_addr, '', $send, true /* is_test */ );

    // Display results
    if ( $send ) {
        if ( $maildata['sent'] == false ) {
            echo '<div class="error"><p><strong>' . esc_html__( 'The mail has not been sent!', 'post_notification' ) . '</strong></p></div>';

            // Only show debug info if WP_DEBUG is enabled
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                global $phpmailer;
                echo '<details><summary>' . esc_html__( 'Debug Information', 'post_notification' ) . '</summary>';
                echo '<pre>' . esc_html( print_r( $phpmailer, true ) ) . '</pre>';
                echo '</details>';
            }
        } else {
            echo '<div class="updated"><p><strong>' . esc_html__( 'Test email sent successfully!', 'post_notification' ) . '</strong></p></div>';
        }
    } else {
        echo '<div class="updated"><p><strong>' . esc_html__( 'Email preview (not sent)', 'post_notification' ) . '</strong></p></div>';
    }

    // Display email details
    echo '<div class="post-notification-email-preview" style="background: #f5f5f5; padding: 15px; margin: 15px 0; border: 1px solid #ddd;">';

    echo '<h4>' . esc_html__( 'Email Details:', 'post_notification' ) . '</h4>';

    echo '<p><strong>' . esc_html__( 'To:', 'post_notification' ) . '</strong> ' . esc_html( $email ) . '</p>';

    echo '<p><strong>' . esc_html__( 'Subject:', 'post_notification' ) . '</strong><br>';
    echo '<code>' . esc_html( $maildata['subject'] ) . '</code></p>';

    if ( ! empty( $maildata['header'] ) ) {
        echo '<details style="margin-top: 15px;"><summary><strong>' . esc_html__( 'Headers', 'post_notification' ) . '</strong></summary>';
        echo '<pre style="background: white; padding: 10px; overflow: auto;">' . esc_html( print_r( $maildata['header'], true ) ) . '</pre>';
        echo '</details>';
    }

    echo '<details style="margin-top: 15px;" open><summary><strong>' . esc_html__( 'Body', 'post_notification' ) . '</strong></summary>';
    echo '<pre style="background: white; padding: 10px; overflow: auto; max-height: 500px;">' . esc_html( $maildata['body'] ) . '</pre>';
    echo '</details>';

    echo '</div>'; // .post-notification-email-preview
    echo '</div>'; // .post-notification-test-results
}