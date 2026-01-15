<?php

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

// @return the PN log dir with trailing slash
function pn_get_log_dir() {
		// Determine log directory from settings
		$mode = get_option( 'post_notification_log_dir_mode', 'default' );
		$custom = trim( (string) get_option( 'post_notification_log_dir_custom', '' ) );

		$default_dir = trailingslashit( POST_NOTIFICATION_PATH ) . 'log/';
		$log_dir = $default_dir;

		if ( $mode === 'custom' && $custom !== '' ) {
			// very simple absolute-path check; if invalid, fall back to default
			$is_absolute = ( DIRECTORY_SEPARATOR === substr( $custom, 0, 1 ) ) || preg_match( '/^[A-Za-z]:\\\\/', $custom );
			if ( $is_absolute ) {
				$log_dir = trailingslashit( $custom );
			}
		}

		//generate log directory if it does not exist
		if ( ! file_exists( $log_dir ) ) {
			@mkdir( $log_dir, 0775, true );
		}

		return $log_dir;
}


function add_pn_logger( $loggerName = 'post-notification', $filename = 'post-notification.log' ) {
	static $loggers = array();
	if ( ! isset( $loggers[ $loggerName ] ) ) {
		$dateFormat = "Y-m-d H:i:s";
		// include channel so multiple logical loggers share one file with prefixes
		$output     = "[%datetime%] | %level_name% [%channel%]: %message% %context% %extra%\n";
		$formatter  = new LineFormatter( $output, $dateFormat );

		// Single shared logfile name as requested
		$file = pn_get_log_dir() . $filename;

		if ( ! file_exists( $file ) ) {
			@touch( $file );
		}

		$level = ( get_option( 'post_notification_debug' ) === 'yes') ? Level::Debug : Level::Info;
		$streamHandler = new StreamHandler( $file, $level );
		$streamHandler->setFormatter( $formatter );

		$logger = new Logger( $loggerName );
		$logger->pushHandler( $streamHandler );

		$admin_emails = pn_getAdminMails();
		$servername   = pn_get_server_name();

		if ( ! empty( $admin_emails ) ) {
			$errorMailer = new NativeMailerHandler( $admin_emails, $servername . ': PostNotification - Error', $servername, Level::Error, true, 70 );
			$errorMailer->setFormatter( $formatter );
			$logger->pushHandler( $errorMailer );
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