<?php

#------------------------------------------------------
# INFO
#------------------------------------------------------
# This is part of the Post Notification Plugin for
# Wordpress. Please see the readme.txt for details.
#------------------------------------------------------

function post_notification_admin_sub() {
    // Security check - only admins can export emails
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'post_notification' ) );
    }

    global $wpdb;
    $t_emails = $wpdb->prefix . 'post_notification_emails';

    echo '<h3>' . esc_html__( 'Export Emails', 'post_notification' ) . '</h3>';

    // Get all active email addresses
    $emails = $wpdb->get_results( $wpdb->prepare( "SELECT email_addr FROM {$t_emails} WHERE gets_mail = %d", 1 ) );

    if ( ! $emails ) {
        echo '<p>' . esc_html__( 'No entries found.', 'post_notification' ) . '</p>';

        return;
    }

    echo '<p>' . sprintf( esc_html__( 'Found %d active email addresses.', 'post_notification' ), count( $emails ) ) . '</p>';

    // Export buttons - HIER kommt der Link hin
    echo '<div style="margin-top: 20px;">';
    echo '<button type="button" class="button" onclick="postNotificationCopyEmails()">' . esc_html__( 'Copy to Clipboard', 'post_notification' ) . '</button> ';

    // CSV-Download Link mit Nonce
    echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=post_notification/admin.php&action=export_csv' ), 'post_notification_export_csv' ) ) . '" class="button">' . esc_html__( 'Download as CSV', 'post_notification' ) . '</a>';

    // Display emails with proper escaping
    echo '<div class="post-notification-email-list">';
    foreach ( $emails as $email ) {
        echo esc_html( $email->email_addr ) . '<br />';
    }
    echo '</div>';

    echo '</div>';

    // JavaScript for copy functionality
    ?>
    <script>
        function postNotificationCopyEmails() {
            var emailList = document.querySelector('.post-notification-email-list');
            var emails = emailList.innerText.replace(/<br\s*\/?>/gi, '\n');

            if (navigator.clipboard) {
                navigator.clipboard.writeText(emails).then(function () {
                    alert('<?php echo esc_js( __( 'Emails copied to clipboard!', 'post_notification' ) ); ?>');
                });
            }
        }
    </script>
    <?php
}