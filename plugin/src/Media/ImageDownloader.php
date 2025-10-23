<?php
/**
 * Image Downloader
 *
 * Downloads images from Notion's S3 URLs with retry logic and validation.
 *
 * @package NotionSync\Media
 * @since 0.3.0
 */

namespace NotionSync\Media;

/**
 * Class ImageDownloader
 *
 * Handles downloading images from Notion's time-limited S3 URLs.
 * Includes retry logic, validation, and security checks.
 *
 * @since 0.3.0
 */
class ImageDownloader {

	/**
	 * Maximum number of download retry attempts.
	 *
	 * @var int
	 */
	private const MAX_RETRIES = 3;

	/**
	 * Download timeout in seconds.
	 *
	 * @var int
	 */
	private const TIMEOUT_SECONDS = 30;

	/**
	 * Maximum file size in bytes (10MB).
	 *
	 * @var int
	 */
	private const MAX_FILE_SIZE = 10485760;

	/**
	 * Allowed MIME types for images.
	 *
	 * @var array
	 */
	private const ALLOWED_MIME_TYPES = [
		'image/jpeg',
		'image/jpg',
		'image/png',
		'image/gif',
		'image/webp',
		'image/svg+xml',
		'image/bmp',
	];

	/**
	 * MIME types that require conversion.
	 *
	 * @var array
	 */
	private const CONVERTIBLE_MIME_TYPES = [
		'image/tiff',
		'image/tif',
	];

	/**
	 * Temporary directory for downloads.
	 *
	 * @var string
	 */
	private string $temp_dir;

	/**
	 * Constructor.
	 *
	 * @param string|null $temp_dir Optional custom temporary directory.
	 */
	public function __construct( ?string $temp_dir = null ) {
		$this->temp_dir = $temp_dir ?? sys_get_temp_dir();
	}

	/**
	 * Download an image from a URL.
	 *
	 * @param string $url     Image URL (typically Notion S3 URL).
	 * @param array  $options {
	 *     Optional download options.
	 *
	 *     @type string $filename Custom filename (optional).
	 *     @type bool   $validate Whether to validate MIME type (default: true).
	 *     @type bool   $force    Force download even for external URLs (default: false).
	 * }
	 * @return array {
	 *     Download result.
	 *
	 *     @type string $file_path   Path to downloaded file.
	 *     @type string $filename    Original or generated filename.
	 *     @type string $mime_type   Detected MIME type.
	 *     @type int    $file_size   File size in bytes.
	 *     @type string $source_url  Original URL.
	 * }
	 * @throws \Exception If download fails after all retries.
	 */
	public function download( string $url, array $options = [] ): array {
		$validate = $options['validate'] ?? true;
		$force    = $options['force'] ?? false;

		// Validate URL.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new \InvalidArgumentException( 'Invalid URL provided: ' . $url );
		}

		// Check if URL should be downloaded (unless forced).
		if ( ! $force && ! $this->should_download( $url ) ) {
			throw new \Exception( 'URL should not be downloaded (external URL): ' . $url );
		}

		// Attempt download with retries.
		$last_exception = null;
		for ( $attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++ ) {
			try {
				return $this->attempt_download( $url, $options );
			} catch ( \Exception $e ) {
				$last_exception = $e;

				// Log retry attempt.
				error_log(
					sprintf(
						'ImageDownloader: Attempt %d/%d failed for %s: %s',
						$attempt,
						self::MAX_RETRIES,
						$url,
						$e->getMessage()
					)
				);

				// Wait before retry (exponential backoff).
				if ( $attempt < self::MAX_RETRIES ) {
					$wait_seconds = pow( 2, $attempt - 1 ); // 1s, 2s, 4s.
					sleep( $wait_seconds );
				}
			}
		}

		// All retries failed.
		throw new \Exception(
			sprintf(
				'Failed to download image after %d attempts: %s',
				self::MAX_RETRIES,
				$last_exception->getMessage()
			),
			0,
			$last_exception
		);
	}

	/**
	 * Attempt a single download.
	 *
	 * @param string $url     Image URL.
	 * @param array  $options Download options.
	 * @return array Download result.
	 * @throws \Exception If download fails.
	 */
	private function attempt_download( string $url, array $options ): array {
		// Generate temporary filename.
		$temp_filename = $options['filename'] ?? $this->generate_filename( $url );
		$temp_path     = $this->temp_dir . '/' . $temp_filename;

		// Download using WordPress HTTP API.
		$response = wp_remote_get(
			$url,
			[
				'timeout'  => self::TIMEOUT_SECONDS,
				'stream'   => true,
				'filename' => $temp_path,
			]
		);

		// Check for HTTP errors.
		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'HTTP request failed: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			throw new \Exception( 'HTTP request returned status code: ' . $response_code );
		}

		// Verify file was created.
		if ( ! file_exists( $temp_path ) ) {
			throw new \Exception( 'Downloaded file not found at: ' . $temp_path );
		}

		// Check file size.
		$file_size = filesize( $temp_path );
		if ( $file_size === false ) {
			unlink( $temp_path );
			throw new \Exception( 'Could not determine file size' );
		}

		if ( $file_size === 0 ) {
			unlink( $temp_path );
			throw new \Exception( 'Downloaded file is empty' );
		}

		if ( $file_size > self::MAX_FILE_SIZE ) {
			unlink( $temp_path );
			throw new \Exception(
				sprintf(
					'File size (%d bytes) exceeds maximum allowed size (%d bytes)',
					$file_size,
					self::MAX_FILE_SIZE
				)
			);
		}

		// Detect MIME type.
		$mime_type = $this->detect_mime_type( $temp_path );

		// Convert TIFF images to JPEG if needed.
		if ( in_array( $mime_type, self::CONVERTIBLE_MIME_TYPES, true ) ) {
			error_log( sprintf( 'ImageDownloader: Converting TIFF image to JPEG: %s', $url ) );

			try {
				$converted_result = $this->convert_tiff_to_jpeg( $temp_path );
				// Delete original TIFF file.
				unlink( $temp_path );
				// Update variables to converted file.
				$temp_path = $converted_result['file_path'];
				$mime_type = $converted_result['mime_type'];
				$file_size = $converted_result['file_size'];
			} catch ( \Exception $e ) {
				unlink( $temp_path );
				throw new \Exception( 'Failed to convert TIFF image to JPEG: ' . $e->getMessage() );
			}
		}

		// Validate MIME type if requested.
		if ( $options['validate'] ?? true ) {
			if ( ! in_array( $mime_type, self::ALLOWED_MIME_TYPES, true ) ) {
				unlink( $temp_path );
				throw new \Exception( 'Invalid MIME type: ' . $mime_type );
			}
		}

		return [
			'file_path'  => $temp_path,
			'filename'   => basename( $temp_path ),
			'mime_type'  => $mime_type,
			'file_size'  => $file_size,
			'source_url' => $url,
		];
	}

	/**
	 * Determine if a URL should be downloaded.
	 *
	 * Notion S3 URLs must be downloaded (they expire).
	 * External URLs (Unsplash, Giphy) should be linked, not downloaded.
	 *
	 * @param string $url Image URL.
	 * @return bool True if should download.
	 */
	public function should_download( string $url ): bool {
		// Check if it's a Notion S3 URL (must download - expires in 1 hour).
		// Notion uses multiple S3 bucket patterns:
		// - s3.us-west-2.amazonaws.com/secure.notion-static.com (legacy)
		// - prod-files-secure.s3.us-west-2.amazonaws.com (current)
		if ( strpos( $url, 's3.us-west-2.amazonaws.com/secure.notion-static.com' ) !== false ||
		     strpos( $url, 's3-us-west-2.amazonaws.com/secure.notion-static.com' ) !== false ||
		     strpos( $url, 'prod-files-secure.s3.us-west-2.amazonaws.com' ) !== false ) {
			return true;
		}

		// Check if it's Unsplash (link, don't download - legal/CDN).
		if ( strpos( $url, 'images.unsplash.com' ) !== false ) {
			return false;
		}

		// Check if it's Giphy (link, don't download - TOS violation).
		if ( strpos( $url, 'giphy.com' ) !== false || strpos( $url, 'media.giphy.com' ) !== false ) {
			return false;
		}

		// Check user preference for other external URLs.
		$strategy = get_option( 'notion_sync_external_media_strategy', 'link' );

		return $strategy === 'download';
	}

	/**
	 * Generate a unique filename for the downloaded file.
	 *
	 * @param string $url Source URL.
	 * @return string Generated filename.
	 */
	private function generate_filename( string $url ): string {
		// Try to extract filename from URL.
		$path     = wp_parse_url( $url, PHP_URL_PATH );
		$basename = $path ? basename( $path ) : '';

		// If we have a filename with extension, use it.
		if ( $basename && strpos( $basename, '.' ) !== false ) {
			// Add unique prefix to avoid collisions.
			return uniqid( 'notion_', true ) . '_' . sanitize_file_name( $basename );
		}

		// Generate filename with timestamp.
		return uniqid( 'notion_', true ) . '_' . time() . '.tmp';
	}

	/**
	 * Detect MIME type of a file.
	 *
	 * @param string $file_path Path to file.
	 * @return string MIME type.
	 */
	private function detect_mime_type( string $file_path ): string {
		// Try WordPress function first.
		$mime_type = wp_check_filetype( $file_path );
		if ( ! empty( $mime_type['type'] ) ) {
			return $mime_type['type'];
		}

		// Fallback to finfo.
		if ( function_exists( 'finfo_open' ) ) {
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_file( $finfo, $file_path );
			finfo_close( $finfo );

			if ( $mime_type ) {
				return $mime_type;
			}
		}

		// Last resort: mime_content_type.
		if ( function_exists( 'mime_content_type' ) ) {
			$mime_type = mime_content_type( $file_path );
			if ( $mime_type ) {
				return $mime_type;
			}
		}

		// Unknown.
		return 'application/octet-stream';
	}

	/**
	 * Get allowed MIME types.
	 *
	 * @return array Allowed MIME types.
	 */
	public static function get_allowed_mime_types(): array {
		return self::ALLOWED_MIME_TYPES;
	}

	/**
	 * Check if a MIME type is allowed.
	 *
	 * @param string $mime_type MIME type to check.
	 * @return bool True if allowed.
	 */
	public static function is_allowed_mime_type( string $mime_type ): bool {
		return in_array( $mime_type, self::ALLOWED_MIME_TYPES, true );
	}

	/**
	 * Convert TIFF image to JPEG format.
	 *
	 * WordPress doesn't support TIFF images by default, so we convert them
	 * to JPEG to ensure compatibility.
	 *
	 * @param string $tiff_path Path to TIFF file.
	 * @return array {
	 *     Conversion result.
	 *
	 *     @type string $file_path Path to converted JPEG file.
	 *     @type string $mime_type MIME type (image/jpeg).
	 *     @type int    $file_size File size in bytes.
	 * }
	 * @throws \Exception If conversion fails.
	 */
	private function convert_tiff_to_jpeg( string $tiff_path ): array {
		// Check if ImageMagick is available.
		if ( ! extension_loaded( 'imagick' ) && ! class_exists( 'Imagick' ) ) {
			// Try GD as fallback (though GD doesn't support TIFF well).
			if ( ! extension_loaded( 'gd' ) ) {
				throw new \Exception( 'Neither ImageMagick nor GD extension available for TIFF conversion' );
			}

			throw new \Exception( 'GD extension does not support TIFF images. Please install ImageMagick extension.' );
		}

		try {
			// Create Imagick object.
			$imagick = new \Imagick( $tiff_path );

			// Set format to JPEG.
			$imagick->setImageFormat( 'jpeg' );

			// Set quality to 90% (good balance between quality and file size).
			$imagick->setImageCompressionQuality( 90 );

			// Generate output filename.
			$jpeg_path = preg_replace( '/\.(tiff?|tif)$/i', '.jpg', $tiff_path );
			if ( $jpeg_path === $tiff_path ) {
				// If no extension found, append .jpg.
				$jpeg_path .= '.jpg';
			}

			// Write JPEG file.
			$imagick->writeImage( $jpeg_path );

			// Clean up.
			$imagick->clear();
			$imagick->destroy();

			// Verify JPEG was created.
			if ( ! file_exists( $jpeg_path ) ) {
				throw new \Exception( 'Converted JPEG file not found' );
			}

			$file_size = filesize( $jpeg_path );
			if ( $file_size === false || $file_size === 0 ) {
				unlink( $jpeg_path );
				throw new \Exception( 'Converted JPEG file is empty or unreadable' );
			}

			error_log(
				sprintf(
					'ImageDownloader: Successfully converted TIFF to JPEG (%d bytes)',
					$file_size
				)
			);

			return [
				'file_path'  => $jpeg_path,
				'mime_type'  => 'image/jpeg',
				'file_size'  => $file_size,
			];

		} catch ( \ImagickException $e ) {
			throw new \Exception( 'ImageMagick conversion failed: ' . $e->getMessage() );
		}
	}
}
