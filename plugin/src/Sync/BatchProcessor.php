<?php
/**
 * Batch Processor
 *
 * Handles background processing of large database syncs using Action Scheduler.
 * Queues entries in batches to avoid timeouts and tracks progress.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Sync;

use NotionSync\Database\RowRepository;
use NotionSync\Database\DatabasePostType;
use NotionSync\Router\LinkRegistry;

/**
 * Class BatchProcessor
 *
 * Processes large database syncs in batches using Action Scheduler.
 *
 * @since 1.0.0
 */
class BatchProcessor {

	/**
	 * Number of entries to process per batch.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 20;

	/**
	 * Action Scheduler action name.
	 *
	 * @var string
	 */
	private const ACTION_NAME = 'notion_sync_process_batch';

	/**
	 * Database fetcher instance.
	 *
	 * @var DatabaseFetcher
	 */
	private $fetcher;

	/**
	 * Row repository instance.
	 *
	 * @var RowRepository
	 */
	private $repository;

	/**
	 * Link registry instance.
	 *
	 * @var LinkRegistry
	 */
	private $link_registry;

	/**
	 * Constructor.
	 *
	 * @param DatabaseFetcher   $fetcher       Database fetcher.
	 * @param RowRepository     $repository    Row repository.
	 * @param LinkRegistry|null $link_registry Link registry instance.
	 */
	public function __construct( DatabaseFetcher $fetcher, RowRepository $repository, ?LinkRegistry $link_registry = null ) {
		$this->fetcher       = $fetcher;
		$this->repository    = $repository;
		$this->link_registry = $link_registry ?? new LinkRegistry();
	}

	/**
	 * Queue a database for batch sync.
	 *
	 * @since 1.0.0
	 *
	 * @param string $database_id Notion database ID.
	 * @return string Batch ID for tracking.
	 * @throws \RuntimeException If database has no entries or Action Scheduler unavailable.
	 */
	public function queue_database_sync( string $database_id ): string {
		// Verify Action Scheduler is available.
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			throw new \RuntimeException(
				'Action Scheduler is not available. Please run composer update.'
			);
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( 'BatchProcessor: Starting queue_database_sync for database: ' . $database_id );

		// Fetch all entries from database.
		$entries = $this->fetcher->query_database( $database_id );

		if ( empty( $entries ) ) {
			throw new \RuntimeException( 'No entries found in database' );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( 'BatchProcessor: Fetched ' . count( $entries ) . ' entries from database' );

		// Find or create database post.
		$database_info = $this->fetcher->get_database_schema( $database_id );
		$database_cpt  = new DatabasePostType();
		$post_id       = $database_cpt->find_or_create( $database_id, $database_info );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( 'BatchProcessor: Database post ID: ' . $post_id );

		// Register/update database link in registry.
		// This enables /notion/{slug} URLs to redirect to the database viewer.
		$this->link_registry->register(
			array(
				'notion_id'    => str_replace( '-', '', $database_id ),
				'notion_title' => $database_info['title'] ?? 'Untitled Database',
				'notion_type'  => 'database',
				'wp_post_id'   => $post_id,
				'wp_post_type' => 'notion_database',
			)
		);

		// Generate unique batch ID.
		$batch_id = 'batch_' . substr( md5( $database_id . time() ), 0, 10 );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( 'BatchProcessor: Generated batch ID: ' . $batch_id );

		// Split entries into batches.
		$batches = array_chunk( $entries, self::BATCH_SIZE );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( 'BatchProcessor: Split into ' . count( $batches ) . ' batches of size ' . self::BATCH_SIZE );

		// Save batch metadata first.
		update_option(
			"notion_sync_batch_{$batch_id}",
			array(
				'database_id'   => $database_id,
				'post_id'       => $post_id,
				'total_entries' => count( $entries ),
				'total_batches' => count( $batches ),
				'completed'     => 0,
				'failed'        => 0,
				'status'        => 'queued',
				'started_at'    => current_time( 'mysql' ),
			)
		);

		// Schedule each batch with Action Scheduler.
		// Store batch data separately to avoid 8000 char limit for Action Scheduler args.
		foreach ( $batches as $index => $batch ) {
			$batch_key = "notion_sync_batch_{$batch_id}_data_" . ( $index + 1 );

			// Store batch entries in wp_options.
			update_option( $batch_key, $batch, false );

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log(
				sprintf(
					'BatchProcessor: Stored %d entries in %s',
					count( $batch ),
					$batch_key
				)
			);

			$scheduled_time = time() + ( $index * 3 );

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log(
				sprintf(
					'BatchProcessor: Scheduling batch %d/%d for time %s (in %d seconds)',
					$index + 1,
					count( $batches ),
					gmdate( 'Y-m-d H:i:s', $scheduled_time ),
					$index * 3
				)
			);

			// Only pass minimal data to Action Scheduler (not the full entries array).
			$action_id = as_schedule_single_action(
				$scheduled_time,
				self::ACTION_NAME,
				array(
					'batch_id'      => $batch_id,
					'post_id'       => $post_id,
					'batch_number'  => $index + 1,
					'total_batches' => count( $batches ),
				)
			);

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( 'BatchProcessor: Scheduled action returned ID: ' . var_export( $action_id, true ) );
		}

		return $batch_id;
	}

	/**
	 * Process a batch of entries.
	 *
	 * Called by Action Scheduler for each batch.
	 *
	 * @since 1.0.0
	 *
	 * @param string $batch_id      Batch identifier.
	 * @param int    $post_id       Database post ID.
	 * @param int    $batch_number  Current batch number.
	 * @param int    $total_batches Total number of batches.
	 */
	public function process_batch(
		string $batch_id,
		int $post_id,
		int $batch_number,
		int $total_batches
	): void {
		// Retrieve batch entries from storage.
		$batch_key = "notion_sync_batch_{$batch_id}_data_{$batch_number}";
		$entries   = get_option( $batch_key, array() );

		if ( empty( $entries ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log(
				sprintf(
					'BatchProcessor: No entries found for %s',
					$batch_key
				)
			);
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log(
			sprintf(
				'BatchProcessor: Retrieved %d entries from %s',
				count( $entries ),
				$batch_key
			)
		);

		// Update status to processing.
		$this->update_batch_status( $batch_id, 'processing' );

		$completed = 0;
		$failed    = 0;

		// Process each entry in the batch.
		foreach ( $entries as $entry ) {
			try {
				// Normalize entry structure.
				$normalized = $this->fetcher->normalize_entry( $entry );

				// Extract indexed fields.
				$extracted = array(
					'title'            => $this->extract_title( $normalized['properties'] ),
					'status'           => $normalized['properties']['Status'] ?? null,
					'created_time'     => $normalized['created_time'],
					'last_edited_time' => $normalized['last_edited_time'],
				);

				// Store in database.
				$this->repository->upsert(
					$post_id,
					$normalized['id'],
					$normalized['properties'],
					$extracted
				);

				++$completed;

			} catch ( \Exception $e ) {
				++$failed;
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for batch processing.
				error_log(
					sprintf(
						'Batch %s: Failed to sync entry %s: %s',
						$batch_id,
						$entry['id'] ?? 'unknown',
						$e->getMessage()
					)
				);
			}
		}

		// Update batch progress.
		$this->increment_batch_progress( $batch_id, $completed, $failed );

		// Clean up batch data after processing.
		delete_option( $batch_key );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log(
			sprintf(
				'BatchProcessor: Cleaned up batch data for %s',
				$batch_key
			)
		);

		// If this is the last batch, mark as complete.
		if ( $batch_number === $total_batches ) {
			$this->complete_batch( $batch_id, $post_id );
		}
	}

	/**
	 * Get batch progress.
	 *
	 * @since 1.0.0
	 *
	 * @param string $batch_id Batch identifier.
	 * @return array Batch metadata with progress information.
	 */
	public function get_batch_progress( string $batch_id ): array {
		$progress = get_option( "notion_sync_batch_{$batch_id}", array() );

		// Calculate percentage if we have data.
		if ( ! empty( $progress['total_entries'] ) ) {
			$progress['percentage'] = round(
				( $progress['completed'] / $progress['total_entries'] ) * 100
			);
		}

		return $progress;
	}

	/**
	 * Cancel batch operation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $batch_id Batch identifier.
	 * @return bool Success status.
	 */
	public function cancel_batch( string $batch_id ): bool {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return false;
		}

		// Cancel all pending actions for this batch.
		as_unschedule_all_actions(
			self::ACTION_NAME,
			array( 'batch_id' => $batch_id )
		);

		// Update status to cancelled.
		$batch_meta = $this->get_batch_progress( $batch_id );
		if ( $batch_meta ) {
			$batch_meta['status']       = 'cancelled';
			$batch_meta['completed_at'] = current_time( 'mysql' );
			update_option( "notion_sync_batch_{$batch_id}", $batch_meta );
			return true;
		}

		return false;
	}

	/**
	 * Extract title from properties array.
	 *
	 * Looks for common title field names.
	 *
	 * @param array $properties Normalized properties array.
	 * @return string Title or "Untitled".
	 */
	private function extract_title( array $properties ): string {
		// Try common title field names.
		$title_candidates = array( 'Title', 'Name', 'title', 'name' );

		foreach ( $title_candidates as $field ) {
			if ( ! empty( $properties[ $field ] ) ) {
				return substr( $properties[ $field ], 0, 500 );
			}
		}

		return 'Untitled';
	}

	/**
	 * Update batch status.
	 *
	 * @param string $batch_id Batch ID.
	 * @param string $status   New status.
	 */
	private function update_batch_status( string $batch_id, string $status ): void {
		$batch_meta           = $this->get_batch_progress( $batch_id );
		$batch_meta['status'] = $status;
		update_option( "notion_sync_batch_{$batch_id}", $batch_meta );
	}

	/**
	 * Increment batch progress counters.
	 *
	 * @param string $batch_id  Batch ID.
	 * @param int    $completed Number completed in this batch.
	 * @param int    $failed    Number failed in this batch.
	 */
	private function increment_batch_progress( string $batch_id, int $completed, int $failed ): void {
		$batch_meta                = $this->get_batch_progress( $batch_id );
		$batch_meta['completed']  += $completed;
		$batch_meta['failed']     += $failed;
		update_option( "notion_sync_batch_{$batch_id}", $batch_meta );
	}

	/**
	 * Complete batch operation.
	 *
	 * @param string $batch_id Batch ID.
	 * @param int    $post_id  Database post ID.
	 */
	private function complete_batch( string $batch_id, int $post_id ): void {
		$batch_meta                   = $this->get_batch_progress( $batch_id );
		$batch_meta['status']         = 'completed';
		$batch_meta['completed_at']   = current_time( 'mysql' );
		update_option( "notion_sync_batch_{$batch_id}", $batch_meta );

		// Update database post with final row count.
		$row_count = $this->repository->count_rows( $post_id );
		update_post_meta( $post_id, 'row_count', $row_count );
		update_post_meta( $post_id, 'last_synced', current_time( 'mysql' ) );
	}
}
