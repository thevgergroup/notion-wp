<?php
/**
 * Media Sync Scheduler
 *
 * Handles background processing of media downloads using Action Scheduler.
 * Prevents timeouts for pages with 10+ images.
 *
 * @package NotionSync\Media
 * @since 0.3.0
 */

namespace NotionSync\Media;

/**
 * Class MediaSyncScheduler
 *
 * Schedules and processes media downloads in the background to prevent
 * PHP timeouts during large sync operations.
 *
 * @since 0.3.0
 */
class MediaSyncScheduler {

	/**
	 * Action hook name for processing media.
	 *
	 * @var string
	 */
	private const ACTION_HOOK = 'notion_sync_process_media_batch';

	/**
	 * Threshold for background processing (number of images).
	 *
	 * @var int
	 */
	private const BACKGROUND_THRESHOLD = 10;

	/**
	 * Image downloader instance.
	 *
	 * @var ImageDownloader
	 */
	private ImageDownloader $downloader;

	/**
	 * Media uploader instance.
	 *
	 * @var MediaUploader
	 */
	private MediaUploader $uploader;

	/**
	 * Constructor.
	 *
	 * @param ImageDownloader|null $downloader Optional custom downloader.
	 * @param MediaUploader|null   $uploader   Optional custom uploader.
	 */
	public function __construct( ?ImageDownloader $downloader = null, ?MediaUploader $uploader = null ) {
		$this->downloader = $downloader ?? new ImageDownloader();
		$this->uploader   = $uploader ?? new MediaUploader();
	}

	/**
	 * Register Action Scheduler hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			add_action( self::ACTION_HOOK, [ __CLASS__, 'process_media_batch' ], 10, 3 );
		}
	}

	/**
	 * Schedule media processing for a batch of images.
	 *
	 * @param int   $post_id     WordPress post ID.
	 * @param array $media_items Array of media items to process.
	 * @param array $options     {
	 *     Optional processing options.
	 *
	 *     @type bool $force_background Force background processing even for small batches.
	 * }
	 * @return array {
	 *     Processing result.
	 *
	 *     @type string $status       'sync' or 'scheduled'.
	 *     @type int    $total        Total media items.
	 *     @type array  $results      Processed attachment IDs (if sync).
	 *     @type int    $scheduled_id Action Scheduler job ID (if scheduled).
	 * }
	 */
	public function schedule_or_process(
		int $post_id,
		array $media_items,
		array $options = []
	): array {
		$total = count( $media_items );

		// Determine if we should process synchronously or in background.
		$force_background = $options['force_background'] ?? false;
		$use_background   = $force_background || $total >= self::BACKGROUND_THRESHOLD;

		if ( ! $use_background ) {
			// Process synchronously (small batch).
			return [
				'status'  => 'sync',
				'total'   => $total,
				'results' => $this->process_media_items( $post_id, $media_items ),
			];
		}

		// Schedule background processing (large batch).
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			// Action Scheduler not available - fall back to sync.
			error_log( 'MediaSyncScheduler: Action Scheduler not available, processing synchronously' );
			return [
				'status'  => 'sync',
				'total'   => $total,
				'results' => $this->process_media_items( $post_id, $media_items ),
			];
		}

		// Store media items in post meta for background processing.
		$batch_id = uniqid( 'media_batch_', true );
		update_post_meta( $post_id, "_notion_media_batch_{$batch_id}", $media_items );
		update_post_meta( $post_id, '_notion_media_batch_status', 'processing' );
		update_post_meta( $post_id, '_notion_media_batch_total', $total );
		update_post_meta( $post_id, '_notion_media_batch_processed', 0 );

		// Schedule Action Scheduler job.
		$action_id = as_schedule_single_action(
			time(),
			self::ACTION_HOOK,
			[ $post_id, $batch_id, $total ],
			'notion-sync'
		);

		return [
			'status'       => 'scheduled',
			'total'        => $total,
			'batch_id'     => $batch_id,
			'scheduled_id' => $action_id,
		];
	}

	/**
	 * Process media batch (Action Scheduler callback).
	 *
	 * @param int    $post_id  WordPress post ID.
	 * @param string $batch_id Batch identifier.
	 * @param int    $total    Total media items.
	 * @return void
	 */
	public static function process_media_batch( int $post_id, string $batch_id, int $total ): void {
		$instance = new self();

		// Retrieve media items from post meta.
		$media_items = get_post_meta( $post_id, "_notion_media_batch_{$batch_id}", true );

		if ( empty( $media_items ) || ! is_array( $media_items ) ) {
			error_log( "MediaSyncScheduler: No media items found for batch {$batch_id}" );
			update_post_meta( $post_id, '_notion_media_batch_status', 'failed' );
			return;
		}

		try {
			// Process all media items.
			$results = $instance->process_media_items( $post_id, $media_items );

			// Update status.
			update_post_meta( $post_id, '_notion_media_batch_status', 'completed' );
			update_post_meta( $post_id, '_notion_media_batch_processed', count( $results ) );
			update_post_meta( $post_id, '_notion_media_batch_results', $results );

			// Clean up batch data.
			delete_post_meta( $post_id, "_notion_media_batch_{$batch_id}" );

			$processed_count = count( $results );
			error_log( "MediaSyncScheduler: Completed batch {$batch_id} - {$processed_count}/{$total} items processed" );

		} catch ( \Exception $e ) {
			error_log( "MediaSyncScheduler: Batch {$batch_id} failed - " . $e->getMessage() );
			update_post_meta( $post_id, '_notion_media_batch_status', 'failed' );
			update_post_meta( $post_id, '_notion_media_batch_error', $e->getMessage() );
		}
	}

	/**
	 * Process an array of media items.
	 *
	 * @param int   $post_id     WordPress post ID.
	 * @param array $media_items Media items to process.
	 * @return array Processed attachment IDs indexed by block ID.
	 */
	private function process_media_items( int $post_id, array $media_items ): array {
		$results = [];

		foreach ( $media_items as $item ) {
			try {
				$block_id  = $item['block_id'] ?? '';
				$url       = $item['url'] ?? '';
				$metadata  = $item['metadata'] ?? [];

				if ( empty( $block_id ) || empty( $url ) ) {
					continue;
				}

				// Check if already processed.
				if ( MediaRegistry::exists( $block_id ) ) {
					$results[ $block_id ] = MediaRegistry::find( $block_id );
					continue;
				}

				// Download image.
				$downloaded = $this->downloader->download( $url );

				// Upload to Media Library.
				$attachment_id = $this->uploader->upload(
					$downloaded['file_path'],
					$metadata,
					$post_id
				);

				// Register in MediaRegistry.
				MediaRegistry::register( $block_id, $attachment_id, $url );

				$results[ $block_id ] = $attachment_id;

			} catch ( \Exception $e ) {
				error_log(
					sprintf(
						'MediaSyncScheduler: Failed to process media item %s: %s',
						$item['block_id'] ?? 'unknown',
						$e->getMessage()
					)
				);
			}
		}

		return $results;
	}

	/**
	 * Get batch processing status.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array|null {
	 *     Batch status or null if no batch.
	 *
	 *     @type string $status    'processing', 'completed', or 'failed'.
	 *     @type int    $total     Total items.
	 *     @type int    $processed Number of processed items.
	 *     @type array  $results   Attachment IDs (if completed).
	 *     @type string $error     Error message (if failed).
	 * }
	 */
	public function get_batch_status( int $post_id ): ?array {
		$status = get_post_meta( $post_id, '_notion_media_batch_status', true );

		if ( empty( $status ) ) {
			return null;
		}

		$result = [
			'status'    => $status,
			'total'     => (int) get_post_meta( $post_id, '_notion_media_batch_total', true ),
			'processed' => (int) get_post_meta( $post_id, '_notion_media_batch_processed', true ),
		];

		if ( 'completed' === $status ) {
			$result['results'] = get_post_meta( $post_id, '_notion_media_batch_results', true ) ?
				get_post_meta( $post_id, '_notion_media_batch_results', true ) :
				[];
		}

		if ( 'failed' === $status ) {
			$result['error'] = get_post_meta( $post_id, '_notion_media_batch_error', true );
		}

		return $result;
	}

	/**
	 * Clean up batch metadata for a post.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return void
	 */
	public function cleanup_batch_metadata( int $post_id ): void {
		delete_post_meta( $post_id, '_notion_media_batch_status' );
		delete_post_meta( $post_id, '_notion_media_batch_total' );
		delete_post_meta( $post_id, '_notion_media_batch_processed' );
		delete_post_meta( $post_id, '_notion_media_batch_results' );
		delete_post_meta( $post_id, '_notion_media_batch_error' );

		// Clean up any orphaned batch data.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta}
				WHERE post_id = %d
				AND meta_key LIKE %s",
				$post_id,
				'_notion_media_batch_%'
			)
		);
	}

	/**
	 * Get background processing threshold.
	 *
	 * @return int Number of images that triggers background processing.
	 */
	public static function get_background_threshold(): int {
		/**
		 * Filter the background processing threshold.
		 *
		 * @param int $threshold Number of images that triggers background processing.
		 */
		return apply_filters( 'notion_sync_media_background_threshold', self::BACKGROUND_THRESHOLD );
	}

	/**
	 * Check if Action Scheduler is available.
	 *
	 * @return bool True if Action Scheduler is available.
	 */
	public static function is_action_scheduler_available(): bool {
		return function_exists( 'as_schedule_single_action' );
	}
}
