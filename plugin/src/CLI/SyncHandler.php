<?php
/**
 * WP-CLI Sync Handler
 *
 * Handles sync operations for pages and databases via WP-CLI.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\CLI;

use NotionSync\API\NotionClient;
use NotionSync\Sync\SyncManager;
use NotionSync\Sync\DatabaseFetcher;
use NotionSync\Sync\BatchProcessor;
use NotionSync\Database\RowRepository;

/**
 * Class SyncHandler
 *
 * Handles sync operations for Notion pages and databases.
 *
 * @since 1.0.0
 */
class SyncHandler {

	/**
	 * Sync a single page.
	 *
	 * @param string $notion_id Notion page ID.
	 * @param bool   $force     Whether to force re-sync.
	 */
	public static function sync_page( string $notion_id, bool $force ): void {
		$sync_manager = new SyncManager();

		// Check if already synced.
		if ( ! $force ) {
			$status = $sync_manager->get_sync_status( $notion_id );
			if ( $status['is_synced'] ) {
				\WP_CLI::log(
					sprintf(
						'Page already synced to post ID %d (last synced: %s)',
						$status['post_id'],
						CommandHelpers::format_timestamp( $status['last_synced'] )
					)
				);
				\WP_CLI::log( 'Use --force to re-sync.' );
				return;
			}
		}

		\WP_CLI::log( "Syncing page {$notion_id}..." );

		$result = $sync_manager->sync_page( $notion_id );

		if ( $result['success'] ) {
			\WP_CLI::success(
				sprintf(
					'Page synced successfully! WordPress post ID: %d',
					$result['post_id']
				)
			);
			\WP_CLI::log( 'View post: ' . get_permalink( $result['post_id'] ) );
		} else {
			\WP_CLI::error( 'Sync failed: ' . $result['error'] );
		}
	}

	/**
	 * Sync a database.
	 *
	 * @param string       $database_id Notion database ID.
	 * @param NotionClient $client      Notion client instance.
	 * @param int          $batch_size  Entries per batch.
	 */
	public static function sync_database( string $database_id, NotionClient $client, int $batch_size ): void {
		\WP_CLI::log( "Syncing database {$database_id}..." );

		$fetcher    = new DatabaseFetcher( $client );
		$repository = new RowRepository();
		$processor  = new BatchProcessor( $fetcher, $repository );

		try {
			$batch_id = $processor->queue_database_sync( $database_id );

			\WP_CLI::success( sprintf( 'Database sync queued! Batch ID: %s', $batch_id ) );
			\WP_CLI::log( 'Use "wp notion batch-status ' . $batch_id . '" to check progress.' );
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Background processing will handle the sync via Action Scheduler.' );

		} catch ( \Exception $e ) {
			\WP_CLI::error( 'Failed to queue database sync: ' . $e->getMessage() );
		}
	}
}
