<?php

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

function add_pn_logger( $loggerName = 'pn' ) {
	static $loggers = array();
	if ( ! isset( $loggers[ $loggerName ] ) ) {
		$dateFormat = "d.m. - H:i:s";
		$output     = "[%datetime%] | %level_name%: %message% %context% %extra%\n";
		$formatter  = new LineFormatter( $output, $dateFormat );

		$upload_dir = wp_upload_dir();
		$log_dir = trailingslashit( $upload_dir['basedir'] ) . 'PostNotification/logs/';

		$file = $log_dir . pn_generate_filename( $loggerName );

		if ( ! file_exists( $file ) ) {
			touch( $file );
		}

		$streamHandler = new StreamHandler( $file, Level::Debug );
		$streamHandler->setFormatter( $formatter );

		$logger = new Logger( $loggerName );
		$logger->pushHandler( $streamHandler );

		$admin_emails = pn_getAdminMails();
		$servername   = pn_get_server_name();

		if ( ! empty( $admin_emails ) ) {
			$errorMailer = new NativeMailerHandler( $admin_emails, $servername . ': PostNotification - Error', $servername, Level::Error, true, 70 );
			$errorMailer->setFormatter( $formatter );
			$logger->pushHandler( $errorMailer );

			$infoMailer = new NativeMailerHandler( $admin_emails, $servername . ': PostNotification - Info', $servername, Level::Info, true, 70 );
			$infoMailer->setFormatter( $formatter );
			$logger->pushHandler( $infoMailer );
		}

		$loggers[ $loggerName ] = $logger;
	}

	return $loggers[ $loggerName ];
}

/**
 * Generate filename with extension .log if it does not exist.
 */
function pn_generate_filename( $loggerName ) {
	$filename = $loggerName;
	if ( ! str_contains( $loggerName, '.' ) || 'log' !== substr( strrchr( $loggerName, "." ), 1 ) ) {
		$filename .= '.log';
	}

	return $filename;
}

function pn_getAdminMails() {
	$administrators = get_users( array(
		'role'    => 'administrator',
		'orderby' => 'user_nicename',
	) );
	$emails         = array();
	foreach ( $administrators as $admin ) {
		$emails[] = $admin->user_email;
	}

	return $emails;
}


/**
 * Get the server name from environment.
 * This function encapsulates the global variable $_SERVER for testing purposes.
 */
function pn_get_server_name() {
	if ( function_exists( 'get_site_url' ) ) {
		// If within a WordPress environment
		return parse_url( get_site_url(), PHP_URL_HOST );
	} else {
		// If not within a WordPress environment
		if ( isset( $_SERVER['SERVER_NAME'] ) ) {
			$server_name = $_SERVER['SERVER_NAME'];
			if ( filter_var( 'http://' . $server_name, FILTER_VALIDATE_URL ) ) {
				return $server_name;
			} else {
				// Handle invalid server name here
				return 'undefined';
			}
		} else {
			// Handle unset server name here
			return 'undefined';
		}
	}
}

if ( ! function_exists( 'str_contains' ) ) {
	function str_contains( string $haystack, string $needle ): bool {
		return '' === $needle || false !== strpos( $haystack, $needle );
	}
}