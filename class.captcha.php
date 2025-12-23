<?php

/*
	Jax Captcha Class v1.o1 - Copyright (c) 2005, Andreas John aka Jack (tR)
	This program and it's moduls are Open Source in terms of General Public License (GPL) v2.0

	class.captcha.php 		(captcha class module)

	Last modification: 2005-09-05

	2007-04-05 Moved cleanup to generate image. Moritz 'Morty' Strübe (morty@gmx.net)
	2007-07-07 Added file_exists check.
*/

require_once plugin_dir_path( __FILE__ ) . 'add_logger.php';


class captcha {
	public $session_key = null;
	public $temp_dir = null;
	public $width = 160;
	public $height = 60;
	public $jpg_quality = 15;


	/**
	 * Constructor - Initializes Captcha class!
	 *
	 * @param string $session_key
	 * @param string $temp_dir
	 *
	 * @return captcha
	 */
	public function __construct( $session_key, $temp_dir ) {
		$this->session_key = $session_key;
		$this->temp_dir    = $temp_dir;
	}

	/**
	 * Returns name of the new generated captcha image file
	 *
	 * @param unknown_type $num_chars
	 *
	 * @return unknown
	 */
    /**
     * Returns name of the new generated captcha image file
     *
     * @param int $num_chars Number of characters in captcha
     * @return string|false Hash of captcha or false on failure
     */
    public function get_pic( $num_chars = 8 ) {
	$logger = add_pn_logger();

        // Define characters for captcha - exclude similar looking characters
        // Removed: 0 (zero), O (letter o), I (letter i), 1 (one), l (lowercase L)
        // to improve readability and reduce user frustration
        $alphabet = array(
                'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K',
                'L', 'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'U', 'V',
                'W', 'X', 'Y', 'Z',
                '2', '3', '4', '5', '6', '7', '8', '9'
        );

        $max = count( $alphabet );

        // Generate random string
        $captcha_str = '';
        for ( $i = 1; $i <= $num_chars; $i++ ) {
            // Choose randomly a character from alphabet and append it to string
            $chosen = rand( 0, $max - 1 ); // Use 0-based index
            $captcha_str .= $alphabet[ $chosen ];
        }

        // Generate a picture file that displays the random string
        $captcha_hash = md5( strtolower( $captcha_str ) );
        $image_path = $this->temp_dir . '/cap_' . $captcha_hash . '.jpg';

	//$logger && $logger->info( 'class.captcha::get_pic: generate image', ['file' => $image_path] );

        if ( $this->_generate_image( $image_path, $captcha_str ) ) {
            $hash_file = $this->temp_dir . '/cap_' . $this->session_key . '.txt';
	    //$logger && $logger->info( 'class.captcha::get_pic: opening captcha file', ['f' => $hash_file] );
            $fh = fopen( $hash_file, 'w' );

            if ( ! $fh ) {
		$logger && $logger->error( 'class.captcha::get_pic: cannot open captcha file', ['f' => $hash_file] );
                return false;
            }

            chmod( $hash_file, 0644 ); // More secure than 0777
            fputs( $fh, $captcha_hash );
            fclose( $fh );

            return $captcha_hash;
        }

        return false;
    }

    /**
     * Generates Image file for captcha
     *
     * @param string $location File path for captcha image
     * @param string $char_seq Character sequence to display
     * @return bool Success status
     */
    public function _generate_image( $location, $char_seq ) {
	$logger = add_pn_logger();

        // Validate temp directory
        if ( ! is_dir( $this->temp_dir ) || ! is_writable( $this->temp_dir ) ) {
	    // create temp dir
	    mkdir( $this->temp_dir );
	}
        if ( ! is_dir( $this->temp_dir ) || ! is_writable( $this->temp_dir ) ) {
            $logger && $logger->error( 'Captcha: temp directory not writable ', [ 'tempdir' => $this->temp_dir ] );
            return false;
        }

        $num_chars = strlen( $char_seq );

        $img = imagecreatetruecolor( $this->width, $this->height );

        if ( ! $img ) {
            $logger && $logger->error( 'Captcha: Failed to create image');
            return false;
        }

        imagealphablending( $img, 1 );
        imagecolortransparent( $img );

        // Generate background of randomly built ellipses
        for ( $i = 1; $i <= 200; $i++ ) {
            $r = rand( 0, 100 );
            $g = rand( 0, 100 );
            $b = rand( 0, 100 );
            $color = imagecolorallocate( $img, $r, $g, $b );
            imagefilledellipse(
                    $img,
                    rand( 0, $this->width ),
                    rand( 0, $this->height ),
                    rand( 0, (int)( $this->width / 16 ) ),
                    rand( 0, (int)( $this->height / 4 ) ),
                    $color
            );
        }

        $start_x = (int)( $this->width / $num_chars );
        $max_font_size = $start_x;
        $start_x = (int)( 0.5 * $start_x );
        $max_x_ofs = (int)( $max_font_size * 0.9 );

        // Verify font file exists
        $fontpath = dirname( __FILE__ ) . '/default.ttf';
        if ( ! file_exists( $fontpath ) ) {
            $logger && $logger->error( 'Captcha: Font file not found', [ 'file' => $fontpath ] );
            imagedestroy( $img );
            return false;
        }

        // Set each letter with random angle, size and color
        for ( $i = 0; $i < $num_chars; $i++ ) { // Changed to < instead of <=
            $r = rand( 127, 255 );
            $g = rand( 127, 255 );
            $b = rand( 127, 255 );
            $y_pos = (int)( ( $this->height / 2 ) + rand( 5, 20 ) );

            $fontsize = rand( 18, $max_font_size );
            $color = imagecolorallocate( $img, $r, $g, $b );
            $angle = rand( -25, 25 ); // Simplified angle generation

            imagettftext(
                    $img,
                    $fontsize,
                    $angle,
                    $start_x + $i * $max_x_ofs,
                    $y_pos,
                    $color,
                    $fontpath,
                    substr( $char_seq, $i, 1 )
            );
        }

        // Create image file
        $result = imagejpeg( $img, $location, $this->jpg_quality );

        if ( $result ) {
            chmod( $location, 0644 ); // More secure permissions
        }

        imagedestroy( $img );

        // Clean up old captcha files (older than 6 minutes)
        $this->_cleanup_old_files();

        return $result;
    }

    /**
     * Clean up old captcha files
     */
    private function _cleanup_old_files() {
        if ( ! is_dir( $this->temp_dir ) || ! is_readable( $this->temp_dir ) ) {
            return;
        }

        $tmp_dir = dir( $this->temp_dir );

        if ( ! $tmp_dir ) {
            return;
        }

        $current_time = time();
        $max_age = 360; // 6 minutes

        while ( false !== ( $entry = $tmp_dir->read() ) ) {
            // Only process captcha files
            if ( strpos( $entry, 'cap_' ) !== 0 ) {
                continue;
            }

            $file_path = $this->temp_dir . '/' . $entry;

            // Verify it's a file and within our temp directory
            if ( ! is_file( $file_path ) ) {
                continue;
            }

            // Delete if older than max_age
            if ( $current_time - filemtime( $file_path ) > $max_age ) {
                @unlink( $file_path );
            }
        }

        $tmp_dir->close();
    }

    /**
     * Check hash of password against hash of searched characters
     *
     * @param string $char_seq User input to verify
     * @return bool True if captcha is correct
     */
    public function verify( $char_seq ) {
        // Sanitize input
        $char_seq = preg_replace( '/[^A-Z0-9]/i', '', $char_seq );

        if ( empty( $char_seq ) ) {
            return false;
        }

        $file = $this->temp_dir . '/cap_' . $this->session_key . '.txt';

        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            return false;
        }

        $hash = file_get_contents( $file );

        // Delete file after verification attempt (one-time use)
        @unlink( $file );

        return ( md5( strtolower( $char_seq ) ) === $hash );
    }
}
