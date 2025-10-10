<?php

#------------------------------------------------------
# INFO
#------------------------------------------------------
# This is part of the Post Notification Plugin for
# Wordpress. Please see the readme.txt for details.
#------------------------------------------------------

function post_notification_admin_sub() {
	// Security check - only admins can edit templates
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'post_notification' ) );
	}

	echo '<h3>' . esc_html__( 'Edit templates', 'post_notification' ) . '</h3>';

	// Validate that the base path exists and is readable
	if ( ! is_dir( POST_NOTIFICATION_PATH ) || ! is_readable( POST_NOTIFICATION_PATH ) ) {
		echo '<div class="error"><p>' . esc_html__( 'Template directory not found or not readable.', 'post_notification' ) . '</p></div>';
		return;
	}

	// Get list of profile directories
	$profiles = post_notification_get_template_profiles();

	if ( empty( $profiles ) ) {
		echo '<div class="notice notice-info"><p>' . esc_html__( 'No template profiles found.', 'post_notification' ) . '</p></div>';
		return;
	}

	// Display profiles and their templates
	foreach ( $profiles as $profile ) {
		echo '<div class="post-notification-profile">';
		echo '<p><strong>' . esc_html( $profile['name'] ) . '</strong></p>';
		echo '<ul>';

		foreach ( $profile['files'] as $file ) {
			$edit_url = add_query_arg(
				array(
					'page' => 'post_notification_edit_template',
					'profile' => urlencode( $profile['name'] ),
					'file' => urlencode( $file['name'] )
				),
				admin_url( 'admin.php' )
			);

			// Add nonce for security
			$edit_url = wp_nonce_url( $edit_url, 'post_notification_edit_template_' . $profile['name'] . '_' . $file['name'] );

			echo '<li>';
			echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $file['name'] ) . '</a>';
			echo ' <span class="description">(' . esc_html( size_format( $file['size'] ) ) . ')</span>';
			echo '</li>';
		}

		echo '</ul>';
		echo '</div>';
	}
}

/**
 * Get list of template profiles and their files
 *
 * @return array List of profiles with their template files
 */
function post_notification_get_template_profiles() {
	$profiles = array();

	// Allowed file extensions for templates
	$allowed_extensions = array( 'html', 'txt', 'tmpl', 'php' );

	$dir_handle = @opendir( POST_NOTIFICATION_PATH );

	if ( ! $dir_handle ) {
		return $profiles;
	}

	while ( false !== ( $dir = readdir( $dir_handle ) ) ) {
		// Skip hidden directories and special directories
		if ( $dir[0] === '.' || $dir[0] === '_' ) {
			continue;
		}

		$dir_path = POST_NOTIFICATION_PATH . '/' . $dir;

		// Verify it's a directory and readable
		if ( ! is_dir( $dir_path ) || ! is_readable( $dir_path ) ) {
			continue;
		}

		// Sanitize directory name to prevent path traversal
		$safe_dir = sanitize_file_name( $dir );
		if ( $safe_dir !== $dir ) {
			continue; // Skip if sanitization changed the name (potential security risk)
		}

		$profile = array(
			'name' => $safe_dir,
			'path' => $dir_path,
			'files' => array()
		);

		// Get files in this profile directory
		$file_handle = @opendir( $dir_path );

		if ( ! $file_handle ) {
			continue;
		}

		while ( false !== ( $file = readdir( $file_handle ) ) ) {
			// Skip hidden files
			if ( $file[0] === '.' ) {
				continue;
			}

			$file_path = $dir_path . '/' . $file;

			// Only include regular files that are readable
			if ( ! is_file( $file_path ) || ! is_readable( $file_path ) ) {
				continue;
			}

			// Check file extension
			$file_ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			if ( ! in_array( $file_ext, $allowed_extensions, true ) ) {
				continue;
			}

			// Sanitize filename
			$safe_file = sanitize_file_name( $file );
			if ( $safe_file !== $file ) {
				continue; // Skip if sanitization changed the name
			}

			$profile['files'][] = array(
				'name' => $safe_file,
				'path' => $file_path,
				'size' => filesize( $file_path ),
				'extension' => $file_ext
			);
		}

		closedir( $file_handle );

		// Only add profile if it has template files
		if ( ! empty( $profile['files'] ) ) {
			// Sort files alphabetically
			usort( $profile['files'], function( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			} );

			$profiles[] = $profile;
		}
	}

	closedir( $dir_handle );

	// Sort profiles alphabetically
	usort( $profiles, function( $a, $b ) {
		return strcmp( $a['name'], $b['name'] );
	} );

	return $profiles;
}