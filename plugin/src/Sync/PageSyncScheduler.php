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

			// Register failure handler to track Action Scheduler task failures.
			add_action( 'action_scheduler_failed_action', [ __CLASS__, 'handle_failed_action' ], 10, 2 );
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
		$fn_exists = function_exists( 'as_schedule_single_action' ) ? 'true' : 'false';
		$class_exists = class_exists( 'ActionScheduler' ) ? 'true' : 'false';

		if ( ! function_exists( 'as_schedule_single_action' ) ) {
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
				'vger-sync-for-notion'
			);
		}


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
			$start_time = microtime( true );
			$result     = $instance->sync_manager->sync_page( $page_id );
			$duration   = microtime( true ) - $start_time;

			if ( $result['success'] ) {
				++$batch['successful'];
				$batch['page_statuses'][ $page_id ] = 'completed';
				$batch['results'][ $page_id ]       = [
					'success'  => true,
					'post_id'  => $result['post_id'],
					'duration' => $duration,
				];

			} else {
				++$batch['failed'];
				$batch['page_statuses'][ $page_id ] = 'failed';
				$batch['results'][ $page_id ]       = [
					'success'  => false,
					'error'    => $result['error'] ?? 'Unknown error',
					'duration' => $duration,
				];

			}
		} catch ( \Exception $e ) {
			$duration = isset( $start_time ) ? microtime( true ) - $start_time : 0;

			++$batch['failed'];
			$batch['page_statuses'][ $page_id ] = 'failed';
			$batch['results'][ $page_id ]       = [
				'success'  => false,
				'error'    => $e->getMessage(),
				'duration' => $duration,
			];

		}

		// Increment processed counter.
		++$batch['processed'];

		// Clear current_page_id after processing.
		$batch['current_page_id'] = null;

		// Check if this is the last page.
		if ( $batch['processed'] >= $batch['total'] ) {
			$batch['status']       = 'completed';
			$batch['completed_at'] = current_time( 'mysql' );

			// Calculate total duration.
			$total_duration = 0;
			foreach ( $batch['results'] as $page_result ) {
				$total_duration += $page_result['duration'] ?? 0;
			}


			// Log individual results for debugging.
			foreach ( $batch['results'] as $result_page_id => $result ) {
				if ( ! $result['success'] ) {
				}
			}
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

	/**
	 * Handle failed Action Scheduler action.
	 *
	 * Called when an Action Scheduler task fails with an exception or fatal error.
	 * Logs the failure and updates batch metadata if applicable.
	 *
	 * @param int        $action_id Action ID that failed.
	 * @param \Exception $exception Exception that caused the failure.
	 * @return void
	 */
	public static function handle_failed_action( int $action_id, \Exception $exception ): void {
		// Get the action to extract args.
		if ( ! function_exists( 'as_get_scheduled_action' ) ) {
			return;
		}

		$action = as_get_scheduled_action( $action_id );
		if ( ! $action || $action->get_hook() !== self::ACTION_HOOK ) {
			return; // Not our action.
		}

		$args = $action->get_args();
		if ( empty( $args[0] ) || empty( $args[1] ) ) {
			return; // Invalid args.
		}

		list( $batch_id, $page_id ) = $args;

		// Log to SyncLogger for persistent tracking.
		\NotionSync\Utils\SyncLogger::log(
			$page_id,
			\NotionSync\Utils\SyncLogger::SEVERITY_ERROR,
			\NotionSync\Utils\SyncLogger::CATEGORY_API,
			sprintf( 'Action Scheduler task failed: %s', $exception->getMessage() ),
			array(
				'batch_id'   => $batch_id,
				'action_id'  => $action_id,
				'trace'      => $exception->getTraceAsString(),
			),
			null
		);

		// Update batch metadata.
		$batch = get_option( "notion_sync_page_batch_{$batch_id}", null );
		if ( $batch ) {
			++$batch['failed'];
			$batch['page_statuses'][ $page_id ] = 'failed';
			$batch['results'][ $page_id ]       = [
				'success' => false,
				'error'   => sprintf( 'Action Scheduler task failed: %s', $exception->getMessage() ),
			];

			// Increment processed counter.
			++$batch['processed'];

			// Check if batch is complete.
			if ( $batch['processed'] >= $batch['total'] ) {
				$batch['status']       = 'completed';
				$batch['completed_at'] = current_time( 'mysql' );
			}

			update_option( "notion_sync_page_batch_{$batch_id}", $batch, false );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging.
		error_log(
			sprintf(
				'[PageSync] ✗ Action Scheduler Failure: batch %s, page %s - %s',
				$batch_id,
				substr( $page_id, 0, 8 ),
				$exception->getMessage()
			)
		);
	}
}
