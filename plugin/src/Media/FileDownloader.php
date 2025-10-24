<?php
/**
 * File Downloader
 *
 * Downloads files (PDFs, documents, etc.) from Notion's S3 URLs.
 *
 * @package NotionSync\Media
 * @since 0.3.0
 */

namespace NotionSync\Media;

/**
 * Class FileDownloader
 *
 * Handles downloading non-image files like PDFs, Word documents, etc.
 * Includes retry logic, validation, and security checks.
 *
 * @since 0.3.0
 */
class FileDownloader {

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
	private const TIMEOUT_SECONDS = 60;

	/**
	 * Maximum file size in bytes (50MB).
	 *
	 * @var int
	 */
	private const MAX_FILE_SIZE = 52428800;

	/**
	 * Allowed MIME types for files.
	 *
	 * @var array
	 */
	private const ALLOWED_MIME_TYPES = [
		// PDF
		'application/pdf',

		// Microsoft Office
		'application/msword',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/vnd.ms-excel',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'application/vnd.ms-powerpoint',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation',

		// Text files
		'text/plain',
		'text/csv',
		'text/markdown',

		// Archives
		'application/zip',
		'application/x-zip-compressed',
		'application/x-rar-compressed',
		'application/x-7z-compressed',

		// Other common formats
		'application/json',
		'application/xml',
		'text/xml',
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
	 * Download a file from a URL.
	 *
	 * @param string $url     File URL (typically Notion S3 URL).
	 * @param array  $options {
	 *     Optional download options.
	 *
	 *     @type string $filename Custom filename (optional).
	 *     @type bool   $validate Whether to validate MIME type (default: true).
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

		// Validate URL.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new \InvalidArgumentException( 'Invalid URL provided: ' . $url );
		}

		// Notion S3 files must be downloaded (they expire).
		if ( ! $this->is_notion_url( $url ) ) {
			throw new \Exception( 'Only Notion S3 URLs are supported for file download' );
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
						'FileDownloader: Attempt %d/%d failed for %s: %s',
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
				'Failed to download file after %d attempts: %s',
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
	 * @param string $url     File URL.
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

		// Validate MIME type if requested.
		if ( $options['validate'] ?? true ) {
			if ( ! in_array( $mime_type, self::ALLOWED_MIME_TYPES, true ) ) {
				unlink( $temp_path );
				throw new \Exception( 'Invalid MIME type for file: ' . $mime_type );
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
	 * Check if URL is a Notion S3 URL.
	 *
	 * @param string $url URL to check.
	 * @return bool True if Notion URL.
	 */
	private function is_notion_url( string $url ): bool {
		return strpos( $url, 's3.us-west-2.amazonaws.com/secure.notion-static.com' ) !== false ||
				strpos( $url, 's3-us-west-2.amazonaws.com/secure.notion-static.com' ) !== false;
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
			return uniqid( 'notion_file_', true ) . '_' . sanitize_file_name( $basename );
		}

		// Generate filename with timestamp.
		return uniqid( 'notion_file_', true ) . '_' . time() . '.tmp';
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
}
