<?php
/**
 * Page Sync Scheduler
 *
 * Handles background processing of bulk page sync operations using Action Scheduler.
 * Prevents timeouts and provides progress tracking for multi-page syncs.
 *
 * @package NotionSync\Sync
 * @since 0.1.5
 */

namespace NotionSync\Sync;

/**
 * Class PageSyncScheduler
 *
 * Schedules and processes page sync operations in the background to prevent
 * PHP timeouts during bulk sync operations.
 *
 * @since 0.1.5
 */
class PageSyncScheduler {

	/**
	 * Action hook name for processing individual page sync.
	 *
	 * @var string
	 */
	private const ACTION_HOOK = 'notion_sync_process_page_batch';

	/**
	 * Sync manager instance.
	 *
	 * @var SyncManager
	 */
	private SyncManager $sync_manager;

	/**
	 * Constructor.
	 *
	 * @param SyncManager|null $sync_manager Optional custom sync manager.
	 */
	public function __construct( ?SyncManager $sync_manager = null ) {
		$this->sync_manager = $sync_manager ?? new SyncManager();
	}

	/**
	 * Register Action Scheduler hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			add_action( self::ACTION_HOOK, [ __CLASS__, 'process_page_sync' ], 10, 2 );
		}
	}

	/**
	 * Schedule bulk page sync operation.
	 *
	 * @param array $page_ids Array of Notion page IDs to sync.
	 * @return array {
	 *     Scheduling result.
	 *
	 *     @type string $status   'scheduled'.
	 *     @type string $batch_id Unique batch identifier.
	 *     @type int    $total    Total pages to sync.
	 * }
	 */
	public function schedule_bulk_sync( array $page_ids ): array {
		if ( empty( $page_ids ) ) {
			return [
				'status' => 'error',
				'error'  => 'No page IDs provided',
			];
		}

		// Check if Action Scheduler is available.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( 'PageSyncScheduler: Checking Action Scheduler availability' );
		error_log( 'PageSyncScheduler: function_exists(as_schedule_single_action) = ' . ( function_exists( 'as_schedule_single_action' ) ? 'true' : 'false' ) );
		error_log( 'PageSyncScheduler: class_exists(ActionScheduler) = ' . ( class_exists( 'ActionScheduler' ) ? 'true' : 'false' ) );

		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( 'PageSyncScheduler: Action Scheduler functions not available' );
			return [
				'status' => 'error',
				'error'  => 'Action Scheduler not available. Please ensure Action Scheduler is properly loaded.',
			];
		}

		// Generate unique batch ID.
		$batch_id = uniqid( 'page_sync_', true );
		$total    = count( $page_ids );

		// Initialize per-page status tracking.
		$page_statuses = array();
		foreach ( $page_ids as $page_id ) {
			$page_statuses[ $page_id ] = 'queued';
		}

		// Store batch metadata in options table.
		update_option(
			"notion_sync_page_batch_{$batch_id}",
			[
				'page_ids'       => $page_ids,
				'total'          => $total,
				'processed'      => 0,
				'successful'     => 0,
				'failed'         => 0,
				'status'         => 'queued',
				'started_at'     => current_time( 'mysql' ),
				'page_statuses'  => $page_statuses,
				'current_page_id' => null,
				'results'        => [],
			],
			false // Don't autoload.
		);

		// Schedule individual sync jobs for each page.
		// Stagger by 1 second to avoid overwhelming the server.
		foreach ( $page_ids as $index => $page_id ) {
			as_schedule_single_action(
				time() + $index,
				self::ACTION_HOOK,
				[ $batch_id, $page_id ],
				'notion-sync'
			);
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( "PageSyncScheduler: Scheduled {$total} pages for batch {$batch_id}" );

		return [
			'status'   => 'scheduled',
			'batch_id' => $batch_id,
			'total'    => $total,
		];
	}

	/**
	 * Process individual page sync (Action Scheduler callback).
	 *
	 * @param string $batch_id Batch identifier.
	 * @param string $page_id  Notion page ID.
	 * @return void
	 */
	public static function process_page_sync( string $batch_id, string $page_id ): void {
		$instance = new self();

		// Get batch metadata.
		$batch = get_option( "notion_sync_page_batch_{$batch_id}", [] );

		if ( empty( $batch ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( "PageSyncScheduler: Batch {$batch_id} not found" );
			return;
		}

		// Update status to processing if this is the first page.
		if ( 'queued' === $batch['status'] ) {
			$batch['status'] = 'processing';
		}

		// Initialize page_statuses array if not present (backward compatibility).
		if ( ! isset( $batch['page_statuses'] ) ) {
			$batch['page_statuses'] = array();
		}

		// Mark this page as currently processing.
		$batch['current_page_id']           = $page_id;
		$batch['page_statuses'][ $page_id ] = 'processing';
		update_option( "notion_sync_page_batch_{$batch_id}", $batch, false );

		try {
			// Sync the page.
			$result = $instance->sync_manager->sync_page( $page_id );

			if ( $result['success'] ) {
				++$batch['successful'];
				$batch['page_statuses'][ $page_id ] = 'completed';
				$batch['results'][ $page_id ]       = [
					'success' => true,
					'post_id' => $result['post_id'],
				];

				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( "PageSyncScheduler: Successfully synced page {$page_id} (post {$result['post_id']})" );
			} else {
				++$batch['failed'];
				$batch['page_statuses'][ $page_id ] = 'failed';
				$batch['results'][ $page_id ]       = [
					'success' => false,
					'error'   => $result['error'] ?? 'Unknown error',
				];

				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( "PageSyncScheduler: Failed to sync page {$page_id}: " . ( $result['error'] ?? 'Unknown error' ) );
			}
		} catch ( \Exception $e ) {
			++$batch['failed'];
			$batch['page_statuses'][ $page_id ] = 'failed';
			$batch['results'][ $page_id ]       = [
				'success' => false,
				'error'   => $e->getMessage(),
			];

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( "PageSyncScheduler: Exception syncing page {$page_id}: " . $e->getMessage() );
		}

		// Increment processed counter.
		++$batch['processed'];

		// Clear current_page_id after processing.
		$batch['current_page_id'] = null;

		// Check if this is the last page.
		if ( $batch['processed'] >= $batch['total'] ) {
			$batch['status']       = 'completed';
			$batch['completed_at'] = current_time( 'mysql' );

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log(
				sprintf(
					'PageSyncScheduler: Batch %s completed - %d successful, %d failed',
					$batch_id,
					$batch['successful'],
					$batch['failed']
				)
			);
		}

		// Save updated batch metadata.
		update_option( "notion_sync_page_batch_{$batch_id}", $batch, false );
	}

	/**
	 * Get batch progress.
	 *
	 * @param string $batch_id Batch identifier.
	 * @return array|null {
	 *     Batch status or null if not found.
	 *
	 *     @type int    $total      Total pages.
	 *     @type int    $processed  Number of processed pages.
	 *     @type int    $successful Number of successful syncs.
	 *     @type int    $failed     Number of failed syncs.
	 *     @type string $status     'queued', 'processing', or 'completed'.
	 *     @type array  $results    Per-page results.
	 *     @type string $started_at Start timestamp.
	 *     @type string $completed_at Completion timestamp (if completed).
	 * }
	 */
	public function get_batch_progress( string $batch_id ): ?array {
		$batch = get_option( "notion_sync_page_batch_{$batch_id}", null );

		if ( null === $batch ) {
			return null;
		}

		// Calculate percentage.
		$batch['percentage'] = $batch['total'] > 0
			? round( ( $batch['processed'] / $batch['total'] ) * 100 )
			: 0;

		return $batch;
	}

	/**
	 * Get the most recent active batch ID.
	 *
	 * Returns the batch_id of the most recently started batch that is still
	 * queued or processing. Used to restore sync state across page loads.
	 *
	 * @return string|null Active batch ID or null if none found.
	 */
	public function get_active_batch_id(): ?string {
		global $wpdb;

		// Query for most recent batch options with 'queued' or 'processing' status.
		$results = $wpdb->get_results(
			"SELECT option_name, option_value
			FROM {$wpdb->options}
			WHERE option_name LIKE 'notion_sync_page_batch_%'
			ORDER BY option_id DESC
			LIMIT 10",
			ARRAY_A
		);

		foreach ( $results as $row ) {
			$batch = maybe_unserialize( $row['option_value'] );
			if ( is_array( $batch ) && in_array( $batch['status'] ?? '', array( 'queued', 'processing' ), true ) ) {
				// Extract batch_id from option_name.
				$batch_id = str_replace( 'notion_sync_page_batch_', '', $row['option_name'] );
				return $batch_id;
			}
		}

		return null;
	}

	/**
	 * Cancel batch operation.
	 *
	 * Unschedules all pending actions for this batch.
	 *
	 * @param string $batch_id Batch identifier.
	 * @return bool Success status.
	 */
	public function cancel_batch( string $batch_id ): bool {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return false;
		}

		// Get batch metadata.
		$batch = get_option( "notion_sync_page_batch_{$batch_id}", null );

		if ( null === $batch ) {
			return false;
		}

		// Cancel all pending actions for this batch.
		// Note: Can't use batch_id in args filter because we schedule with [batch_id, page_id].
		// Instead, get all page IDs and unschedule individually.
		foreach ( $batch['page_ids'] as $page_id ) {
			as_unschedule_all_actions(
				self::ACTION_HOOK,
				[ $batch_id, $page_id ]
			);
		}

		// Update batch status.
		$batch['status']       = 'cancelled';
		$batch['completed_at'] = current_time( 'mysql' );
		update_option( "notion_sync_page_batch_{$batch_id}", $batch, false );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( "PageSyncScheduler: Cancelled batch {$batch_id}" );

		return true;
	}

	/**
	 * Clean up batch metadata.
	 *
	 * Removes batch data from options table.
	 * Should be called after results are no longer needed.
	 *
	 * @param string $batch_id Batch identifier.
	 * @return bool Success status.
	 */
	public function cleanup_batch( string $batch_id ): bool {
		return delete_option( "notion_sync_page_batch_{$batch_id}" );
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
