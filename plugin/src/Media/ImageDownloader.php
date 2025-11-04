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

use NotionSync\Utils\SyncLogger;

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
	private const ALLOWED_MIME_TYPES = array(
		'image/jpeg',
		'image/jpg',
		'image/png',
		'image/gif',
		'image/webp',
		'image/svg+xml',
		'image/bmp',
	);

	/**
	 * Unsupported MIME types that should be linked instead of downloaded.
	 *
	 * @var array
	 */
	private const UNSUPPORTED_MIME_TYPES = array(
		'image/tiff',
		'image/tif',
	);

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
	 * Check content-type of URL without downloading (HEAD request).
	 *
	 * Saves bandwidth by checking if file is supported before downloading.
	 *
	 * @since 0.4.0
	 *
	 * @param string $url URL to check.
	 * @return array {
	 *     Content type information.
	 *
	 *     @type string|null $content_type  Content-Type header value.
	 *     @type bool        $is_supported  True if type is in ALLOWED_MIME_TYPES.
	 *     @type bool        $is_unsupported True if type is in UNSUPPORTED_MIME_TYPES.
	 * }
	 */
	public function check_content_type( string $url ): array {
		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'content_type'   => null,
				'is_supported'   => false,
				'is_unsupported' => false,
			);
		}

		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		// Remove charset if present (e.g., "image/jpeg; charset=utf-8").
		if ( $content_type && strpos( $content_type, ';' ) !== false ) {
			list( $content_type ) = explode( ';', $content_type, 2 );
			$content_type = trim( $content_type );
		}

		return array(
			'content_type'   => $content_type,
			'is_supported'   => $content_type ? in_array( $content_type, self::ALLOWED_MIME_TYPES, true ) : false,
			'is_unsupported' => $content_type ? in_array( $content_type, self::UNSUPPORTED_MIME_TYPES, true ) : false,
		);
	}

	/**
	 * Download an image from a URL.
	 *
	 * @param string $url     Image URL (typically Notion S3 URL).
	 * @param array  $options {
	 *     Optional download options.
	 *
	 *     @type string      $filename        Custom filename (optional).
	 *     @type bool        $validate        Whether to validate MIME type (default: true).
	 *     @type bool        $force           Force download even for external URLs (default: false).
	 *     @type string|null $notion_page_id  Notion page ID for logging (optional).
	 *     @type int|null    $wp_post_id      WordPress post ID for logging (optional).
	 * }
	 * @return array {
	 *     Download result.
	 *
	 *     @type string      $file_path     Path to downloaded file (or null if unsupported).
	 *     @type string      $filename      Original or generated filename.
	 *     @type string      $mime_type     Detected MIME type.
	 *     @type int         $file_size     File size in bytes (or 0 if unsupported).
	 *     @type string      $source_url    Original URL.
	 *     @type bool        $unsupported   True if file type is unsupported.
	 *     @type string|null $linked_url    URL to use for linking (for unsupported types).
	 * }
	 * @throws \Exception If download fails after all retries or invalid URL provided.
	 */
	public function download( string $url, array $options = array() ): array {
		$validate = $options['validate'] ?? true;
		$force    = $options['force'] ?? false;

		// Validate URL security (SSRF protection).
		$this->validate_url_security( $url );

		// Check if URL should be downloaded (unless forced).
		if ( ! $force && ! $this->should_download( $url ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error message for debugging.
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

				// Wait before retry (exponential backoff).
				if ( $attempt < self::MAX_RETRIES ) {
					$wait_seconds = pow( 2, $attempt - 1 ); // 1s, 2s, 4s.
					sleep( $wait_seconds );
				}
			}
		}

		// All retries failed.
		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		throw new \Exception(
			sprintf(
				'Failed to download image after %d attempts: %s',
				self::MAX_RETRIES,
				$last_exception->getMessage()
			),
			0,
			$last_exception
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Validate URL for security vulnerabilities (SSRF protection).
	 *
	 * Prevents Server-Side Request Forgery attacks by blocking:
	 * - Internal/private IP ranges
	 * - Non-HTTP/HTTPS protocols
	 * - URLs that fail WordPress security validation
	 *
	 * @since 0.4.0
	 *
	 * @param string $url URL to validate.
	 * @return void
	 * @throws \InvalidArgumentException If URL is invalid or potentially dangerous.
	 */
	private function validate_url_security( string $url ): void {
		// 1. Basic format validation.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error message for debugging.
			throw new \InvalidArgumentException( 'Invalid URL format: ' . $url );
		}

		// 2. Only allow HTTP/HTTPS.
		$parsed = wp_parse_url( $url );
		if ( ! isset( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
			throw new \InvalidArgumentException( 'Only HTTP/HTTPS protocols allowed' );
		}

		// 3. Block internal/private IP ranges.
		if ( isset( $parsed['host'] ) ) {
			$ip = gethostbyname( $parsed['host'] );

			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
				throw new \InvalidArgumentException( 'Access to internal/private IPs not allowed' );
			}
		}

		// 4. WordPress validation.
		if ( ! wp_http_validate_url( $url ) ) {
			throw new \InvalidArgumentException( 'URL failed WordPress security validation' );
		}
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
			array(
				'timeout'  => self::TIMEOUT_SECONDS,
				'stream'   => true,
				'filename' => $temp_path,
			)
		);

		// Check for HTTP errors.
		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error message for debugging.
			throw new \Exception( 'HTTP request failed: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error message for debugging.
			throw new \Exception( 'HTTP request returned status code: ' . $response_code );
		}

		// Verify file was created.
		if ( ! file_exists( $temp_path ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error message for debugging.
			throw new \Exception( 'Downloaded file not found at: ' . $temp_path );
		}

		// Check file size.
		$file_size = filesize( $temp_path );
		if ( false === $file_size ) {
			wp_delete_file( $temp_path );
			throw new \Exception( 'Could not determine file size' );
		}

		if ( 0 === $file_size ) {
			wp_delete_file( $temp_path );
			throw new \Exception( 'Downloaded file is empty' );
		}

		if ( $file_size > self::MAX_FILE_SIZE ) {
			wp_delete_file( $temp_path );
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception(
				sprintf(
					'File size (%d bytes) exceeds maximum allowed size (%d bytes)',
					$file_size,
					self::MAX_FILE_SIZE
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		// Detect MIME type.
		$mime_type = $this->detect_mime_type( $temp_path );

		// Check if MIME type needs conversion (e.g., TIFF → PNG).
		if ( in_array( $mime_type, self::UNSUPPORTED_MIME_TYPES, true ) ) {
			// Try to convert to PNG using Imagick.
			$converted_path = $this->convert_to_png( $temp_path, $mime_type );

			if ( $converted_path ) {
				// Conversion successful - use converted file.

				// Clean up original file.
				wp_delete_file( $temp_path );

				// Update temp_path and mime_type to use converted file.
				$temp_path = $converted_path;
				$mime_type = 'image/png';
			} else {
				// Conversion failed - clean up and return unsupported.
				wp_delete_file( $temp_path );

				// Log the issue.
				$notion_page_id = $options['notion_page_id'] ?? null;
				$wp_post_id     = $options['wp_post_id'] ?? null;

				if ( $notion_page_id ) {
					SyncLogger::log(
						$notion_page_id,
						SyncLogger::SEVERITY_WARNING,
						SyncLogger::CATEGORY_IMAGE,
						sprintf( 'Unsupported image format (%s) could not be converted. Image will be linked to original URL.', $mime_type ),
						array(
							'url'       => $url,
							'mime_type' => $mime_type,
							'filename'  => basename( $url ),
						),
						$wp_post_id
					);
				}


				// Return special result indicating unsupported type.
				return array(
					'file_path'   => null,
					'filename'    => basename( wp_parse_url( $url, PHP_URL_PATH ) ?? 'image' ),
					'mime_type'   => $mime_type,
					'file_size'   => 0,
					'source_url'  => $url,
					'unsupported' => true,
					'linked_url'  => $url,
				);
			}
		}

		// Validate MIME type if requested.
		if ( $options['validate'] ?? true ) {
			if ( ! in_array( $mime_type, self::ALLOWED_MIME_TYPES, true ) ) {
				wp_delete_file( $temp_path );
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error message for debugging.
				throw new \Exception( 'Invalid MIME type: ' . $mime_type );
			}
		}

		return array(
			'file_path'   => $temp_path,
			'filename'    => basename( $temp_path ),
			'mime_type'   => $mime_type,
			'file_size'   => $file_size,
			'source_url'  => $url,
			'unsupported' => false,
			'linked_url'  => null,
		);
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
		// - s3.us-west-2.amazonaws.com/secure.notion-static.com (legacy).
		// - prod-files-secure.s3.us-west-2.amazonaws.com (current).
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

		return 'download' === $strategy;
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
	 * Convert unsupported image format to PNG using Imagick.
	 *
	 * Converts formats like TIFF to PNG so they can be uploaded to WordPress.
	 *
	 * @since 0.4.0
	 *
	 * @param string $file_path Path to file to convert.
	 * @param string $mime_type MIME type of source file.
	 * @return string|null Path to converted PNG file, or null on failure.
	 */
	private function convert_to_png( string $file_path, string $mime_type ): ?string {
		// Check if Imagick extension is available.
		if ( ! extension_loaded( 'imagick' ) ) {
			return null;
		}

		try {
			// Create Imagick object.
			$imagick = new \Imagick( $file_path );

			// Set format to PNG.
			$imagick->setImageFormat( 'png' );

			// Set compression quality (0-100, higher = better quality, larger file).
			$imagick->setImageCompressionQuality( 90 );

			// Generate new filename.
			$png_path = preg_replace( '/\.[^.]+$/', '.png', $file_path );

			// Write PNG file.
			$imagick->writeImage( $png_path );

			// Clean up.
			$imagick->clear();
			$imagick->destroy();


			return $png_path;

		} catch ( \Exception $e ) {
			return null;
		}
	}
}
