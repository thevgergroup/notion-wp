<?php
/**
 * Image Download Handler
 *
 * Processes background image downloads queued via Action Scheduler.
 * Downloads images from Notion S3 and uploads to WordPress Media Library.
 *
 * Benefits of background processing:
 * - No PHP timeouts (pages with 100+ images sync in seconds)
 * - Deduplication via MediaRegistry
 * - Self-healing with dynamic blocks (no post content updates needed)
 *
 * @package NotionSync\Media
 * @since 0.4.0
 */

namespace NotionSync\Media;

/**
 * Class ImageDownloadHandler
 *
 * Handles background processing of individual image downloads.
 *
 * @since 0.4.0
 */
class ImageDownloadHandler {

	/**
	 * Action hook name for processing individual images.
	 *
	 * @var string
	 */
	private const ACTION_HOOK = 'notion_sync_download_image';

	/**
	 * Register Action Scheduler hooks.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			add_action( self::ACTION_HOOK, array( __CLASS__, 'process_download' ), 10, 5 );
		}
	}

	/**
	 * Process image download (Action Scheduler callback).
	 *
	 * Downloads image from Notion S3, uploads to WordPress Media Library,
	 * and registers in MediaRegistry for deduplication.
	 *
	 * No post content updates needed - dynamic block will automatically
	 * show the image on next page load.
	 *
	 * @since 0.4.0
	 *
	 * @param string $block_id       Notion block ID.
	 * @param string $notion_url     Notion S3 URL.
	 * @param string $notion_page_id Notion page ID (for logging).
	 * @param int    $wp_post_id     WordPress post ID (for attachment parent).
	 * @param string $caption        Image caption.
	 * @return void
	 */
	public static function process_download( string $block_id, string $notion_url, string $notion_page_id = '', int $wp_post_id = 0, string $caption = '' ): void {

		// Validate required arguments.
		if ( empty( $block_id ) || empty( $notion_url ) ) {
			error_log(
				sprintf(
					'[ImageDownloadHandler] Invalid arguments: block_id=%s, notion_url=%s',
					$block_id,
					$notion_url ? 'provided' : 'missing'
				)
			);
			return;
		}

		try {
			// Check if already processed (deduplication).
			if ( MediaRegistry::exists( $block_id ) ) {
				$existing_id = MediaRegistry::find( $block_id );
				error_log(
					sprintf(
						'[ImageDownloadHandler] Image already exists: block %s → attachment %d',
						substr( $block_id, 0, 8 ),
						$existing_id
					)
				);
				return;
			}

			// Download from Notion S3.
			$downloader = new ImageDownloader();
			$downloaded = $downloader->download(
				$notion_url,
				array(
					'notion_page_id' => $notion_page_id,
					'wp_post_id'     => $wp_post_id,
				)
			);

			// Check if image type is unsupported (e.g., TIFF).
			if ( ! empty( $downloaded['unsupported'] ) ) {
				error_log(
					sprintf(
						'[ImageDownloadHandler] Unsupported image type for block %s, skipping upload',
						substr( $block_id, 0, 8 )
					)
				);
				// Don't register unsupported types - dynamic block will show Notion URL.
				return;
			}

			// Upload to WordPress Media Library.
			$uploader      = new MediaUploader();
			$attachment_id = $uploader->upload(
				$downloaded['file_path'],
				array(
					'alt_text' => $caption ? $caption : 'Image from Notion',
					'caption'  => $caption,
				),
				$wp_post_id
			);

			// Register in MediaRegistry (enables deduplication and dynamic block rendering).
			MediaRegistry::register( $block_id, $attachment_id, $notion_url );

			error_log(
				sprintf(
					'[ImageDownloadHandler] Successfully processed: block %s → attachment %d (post %d)',
					substr( $block_id, 0, 8 ),
					$attachment_id,
					$wp_post_id
				)
			);

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'[ImageDownloadHandler] Failed to process block %s: %s',
					substr( $block_id, 0, 8 ),
					$e->getMessage()
				)
			);
			// Let Action Scheduler handle retries automatically.
			throw $e;
		}
	}

	/**
	 * Check if Action Scheduler is available.
	 *
	 * @since 0.4.0
	 *
	 * @return bool True if Action Scheduler is available.
	 */
	public static function is_action_scheduler_available(): bool {
		return function_exists( 'as_schedule_single_action' );
	}
}
