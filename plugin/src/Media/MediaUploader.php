<?php
/**
 * Media Uploader
 *
 * Uploads files to WordPress Media Library with metadata.
 *
 * @package NotionSync\Media
 * @since 0.3.0
 */

namespace NotionSync\Media;

/**
 * Class MediaUploader
 *
 * Handles uploading downloaded files to the WordPress Media Library.
 * Manages attachment metadata (alt text, caption, description).
 *
 * @since 0.3.0
 */
class MediaUploader {

	/**
	 * Upload a file to the WordPress Media Library.
	 *
	 * @param string   $file_path       Path to the file to upload.
	 * @param array    $metadata        {
	 *     Optional attachment metadata.
	 *
	 *     @type string $title       Attachment title.
	 *     @type string $caption     Attachment caption.
	 *     @type string $description Attachment description.
	 *     @type string $alt_text    Image alt text.
	 * }
	 * @param int|null $parent_post_id  Optional parent post ID.
	 * @return int Attachment ID.
	 * @throws \Exception If upload fails.
	 */
	public function upload( string $file_path, array $metadata = [], ?int $parent_post_id = null ): int {
		// Validate file exists.
		if ( ! file_exists( $file_path ) ) {
			throw new \Exception( 'File not found: ' . $file_path );
		}

		// Get filename.
		$filename = basename( $file_path );

		// Detect MIME type.
		$mime_type = $this->get_mime_type( $file_path );

		// Prepare upload.
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			throw new \Exception( 'Upload directory error: ' . $upload_dir['error'] );
		}

		// Generate unique filename to avoid collisions.
		$unique_filename = wp_unique_filename( $upload_dir['path'], $filename );
		$upload_path     = $upload_dir['path'] . '/' . $unique_filename;

		// Copy file to uploads directory.
		if ( ! copy( $file_path, $upload_path ) ) {
			throw new \Exception( 'Failed to copy file to uploads directory' );
		}

		// Clean up temporary file.
		unlink( $file_path );

		// Prepare attachment data.
		$attachment_data = [
			'post_mime_type' => $mime_type,
			'post_title'     => $metadata['title'] ?? sanitize_title( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => $metadata['description'] ?? '',
			'post_excerpt'   => $metadata['caption'] ?? '',
			'post_status'    => 'inherit',
		];

		// Set parent post if provided.
		if ( $parent_post_id ) {
			$attachment_data['post_parent'] = $parent_post_id;
		}

		// Insert attachment.
		$attachment_id = wp_insert_attachment( $attachment_data, $upload_path, $parent_post_id );

		if ( is_wp_error( $attachment_id ) ) {
			unlink( $upload_path );
			throw new \Exception( 'Failed to insert attachment: ' . $attachment_id->get_error_message() );
		}

		if ( ! $attachment_id ) {
			unlink( $upload_path );
			throw new \Exception( 'Failed to insert attachment: Unknown error' );
		}

		// Generate attachment metadata and thumbnails.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $upload_path );
		wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

		// Set alt text for images.
		if ( ! empty( $metadata['alt_text'] ) && strpos( $mime_type, 'image/' ) === 0 ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $metadata['alt_text'] ) );
		}

		return $attachment_id;
	}

	/**
	 * Upload multiple files.
	 *
	 * @param array    $files          Array of file paths.
	 * @param array    $metadata_array Array of metadata arrays (same order as files).
	 * @param int|null $parent_post_id Optional parent post ID.
	 * @return array Array of attachment IDs.
	 */
	public function upload_multiple( array $files, array $metadata_array = [], ?int $parent_post_id = null ): array {
		$attachment_ids = [];

		foreach ( $files as $index => $file_path ) {
			try {
				$metadata         = $metadata_array[ $index ] ?? [];
				$attachment_ids[] = $this->upload( $file_path, $metadata, $parent_post_id );
			} catch ( \Exception $e ) {
				error_log(
					sprintf(
						'MediaUploader: Failed to upload file %s: %s',
						$file_path,
						$e->getMessage()
					)
				);
			}
		}

		return $attachment_ids;
	}

	/**
	 * Update attachment metadata.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $metadata      {
	 *     Metadata to update.
	 *
	 *     @type string $title       Attachment title.
	 *     @type string $caption     Attachment caption.
	 *     @type string $description Attachment description.
	 *     @type string $alt_text    Image alt text.
	 * }
	 * @return bool True on success.
	 */
	public function update_metadata( int $attachment_id, array $metadata ): bool {
		$update_data = [];

		if ( isset( $metadata['title'] ) ) {
			$update_data['ID']         = $attachment_id;
			$update_data['post_title'] = $metadata['title'];
		}

		if ( isset( $metadata['caption'] ) ) {
			$update_data['ID']           = $attachment_id;
			$update_data['post_excerpt'] = $metadata['caption'];
		}

		if ( isset( $metadata['description'] ) ) {
			$update_data['ID']           = $attachment_id;
			$update_data['post_content'] = $metadata['description'];
		}

		// Update post if we have data.
		if ( ! empty( $update_data ) ) {
			$result = wp_update_post( $update_data );
			if ( is_wp_error( $result ) || ! $result ) {
				return false;
			}
		}

		// Update alt text for images.
		if ( isset( $metadata['alt_text'] ) ) {
			$mime_type = get_post_mime_type( $attachment_id );
			if ( $mime_type && strpos( $mime_type, 'image/' ) === 0 ) {
				update_post_meta(
					$attachment_id,
					'_wp_attachment_image_alt',
					sanitize_text_field( $metadata['alt_text'] )
				);
			}
		}

		return true;
	}

	/**
	 * Get attachment URL.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|null Attachment URL or null if not found.
	 */
	public function get_attachment_url( int $attachment_id ): ?string {
		$url = wp_get_attachment_url( $attachment_id );
		return $url ?: null;
	}

	/**
	 * Get attachment metadata.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null Attachment metadata or null if not found.
	 */
	public function get_attachment_metadata( int $attachment_id ): ?array {
		$post = get_post( $attachment_id );
		if ( ! $post ) {
			return null;
		}

		$alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		return [
			'id'          => $attachment_id,
			'url'         => wp_get_attachment_url( $attachment_id ),
			'title'       => $post->post_title,
			'caption'     => $post->post_excerpt,
			'description' => $post->post_content,
			'alt_text'    => $alt_text ?: '',
			'mime_type'   => $post->post_mime_type,
			'file_size'   => filesize( get_attached_file( $attachment_id ) ),
		];
	}

	/**
	 * Delete an attachment.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $force_delete  Whether to bypass trash and force deletion.
	 * @return bool True on success.
	 */
	public function delete_attachment( int $attachment_id, bool $force_delete = false ): bool {
		$result = wp_delete_attachment( $attachment_id, $force_delete );
		return $result !== false && $result !== null;
	}

	/**
	 * Get MIME type of a file.
	 *
	 * @param string $file_path Path to file.
	 * @return string MIME type.
	 */
	private function get_mime_type( string $file_path ): string {
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

		return 'application/octet-stream';
	}
}
