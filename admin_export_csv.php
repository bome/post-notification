<?php

#------------------------------------------------------
# INFO
#------------------------------------------------------
# This is part of the Post Notification Plugin for
# Wordpress. Please see the readme.txt for details.
#------------------------------------------------------

function post_notification_admin_sub() {
	// Security check - only admins can export
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'post_notification' ) );
	}

	// Verify nonce
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'post_notification_export_csv' ) ) {
		wp_die( __( 'Security check failed.', 'post_notification' ) );
	}

	global $wpdb;
	$t_emails = $wpdb->prefix . 'post_notification_emails';

	// Get emails with additional info
	$emails = $wpdb->get_results( $wpdb->prepare(
		"SELECT email_addr, date_subscribed, last_modified FROM {$t_emails} WHERE gets_mail = %d ORDER BY email_addr ASC",
		1
	) );

	if ( ! $emails ) {
		wp_die( __( 'No entries found to export.', 'post_notification' ) );
	}

	// Set headers for CSV download
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=post-notification-emails-' . date( 'Y-m-d-His' ) . '.csv' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	// Open output stream
	$output = fopen( 'php://output', 'w' );

	// Add BOM for Excel UTF-8 support
	fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );

	// Add CSV header row
	fputcsv( $output, array(
		'Email Address',
		'Date Subscribed',
		'Last Modified'
	) );

	// Add data rows
	foreach ( $emails as $email ) {
		fputcsv( $output, array(
			$email->email_addr,
			$email->date_subscribed,
			$email->last_modified
		) );
	}

	fclose( $output );
	exit; // Important: Stop all further output
}