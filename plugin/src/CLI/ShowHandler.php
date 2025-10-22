<?php
/**
 * WP-CLI Show Handler
 *
 * Handles showing details for Notion pages and databases.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\CLI;

use NotionSync\API\NotionClient;
use NotionSync\Sync\ContentFetcher;
use NotionSync\Sync\DatabaseFetcher;
use NotionSync\Sync\SyncManager;

/**
 * Class ShowHandler
 *
 * Handles show operations for Notion resources.
 *
 * @since 1.0.0
 */
class ShowHandler {

	/**
	 * Show details for a Notion page.
	 *
	 * @param NotionClient $client      Notion client instance.
	 * @param string       $notion_id   Notion page ID.
	 * @param bool         $show_blocks Whether to show block structure.
	 * @param bool         $show_raw    Whether to output raw JSON.
	 */
	public static function show_page( NotionClient $client, string $notion_id, bool $show_blocks, bool $show_raw ): void {
		$fetcher = new ContentFetcher( $client );

		\WP_CLI::log( "Fetching page {$notion_id}..." );

		// Fetch page properties.
		$properties = $fetcher->fetch_page_properties( $notion_id );

		if ( empty( $properties ) ) {
			\WP_CLI::error( 'Page not found or integration lacks access.' );
		}

		// Display page properties.
		\WP_CLI::log( \WP_CLI::colorize( '%GPage Properties:%n' ) );
		\WP_CLI::log( '  ID:            ' . ( $properties['id'] ?? 'N/A' ) );
		\WP_CLI::log( '  Title:         ' . ( $properties['title'] ?? 'Untitled' ) );
		\WP_CLI::log( '  Created:       ' . CommandHelpers::format_timestamp( $properties['created_time'] ?? '' ) );
		\WP_CLI::log( '  Last Edited:   ' . CommandHelpers::format_timestamp( $properties['last_edited_time'] ?? '' ) );
		\WP_CLI::log( '  URL:           ' . ( $properties['url'] ?? 'N/A' ) );

		// Check sync status.
		$sync_manager = new SyncManager();
		$sync_status  = $sync_manager->get_sync_status( $notion_id );

		\WP_CLI::log( '' );
		\WP_CLI::log( \WP_CLI::colorize( '%GSync Status:%n' ) );
		if ( $sync_status['is_synced'] ) {
			\WP_CLI::log( '  Synced:        ' . \WP_CLI::colorize( '%GYes%n' ) );
			\WP_CLI::log( '  WP Post ID:    ' . $sync_status['post_id'] );
			\WP_CLI::log( '  Last Synced:   ' . CommandHelpers::format_timestamp( $sync_status['last_synced'] ) );
			\WP_CLI::log( '  WP URL:        ' . get_permalink( $sync_status['post_id'] ) );
		} else {
			\WP_CLI::log( '  Synced:        ' . \WP_CLI::colorize( '%RNo%n' ) );
		}

		// Show blocks if requested.
		if ( $show_blocks || $show_raw ) {
			\WP_CLI::log( '' );
			$blocks = $fetcher->fetch_page_blocks( $notion_id );

			if ( empty( $blocks ) ) {
				\WP_CLI::log( '  (No blocks found)' );
			} elseif ( $show_raw ) {
				// Output raw JSON for debugging.
				\WP_CLI::log( \WP_CLI::colorize( '%GRaw Block JSON:%n' ) );
				\WP_CLI::log( json_encode( $blocks, JSON_PRETTY_PRINT ) );
			} else {
				\WP_CLI::log( \WP_CLI::colorize( '%GPage Blocks:%n' ) );
				\WP_CLI::log( '  Total blocks: ' . count( $blocks ) );
				foreach ( $blocks as $index => $block ) {
					$type = $block['type'] ?? 'unknown';
					\WP_CLI::log( sprintf( '  [%d] %s', $index + 1, $type ) );
				}
			}
		}
	}

	/**
	 * Show details for a Notion database.
	 *
	 * @param NotionClient $client      Notion client instance.
	 * @param string       $database_id Notion database ID.
	 * @param int          $limit       Number of sample rows to display.
	 * @param string       $format      Output format.
	 */
	public static function show_database( NotionClient $client, string $database_id, int $limit, string $format ): void {
		$fetcher = new DatabaseFetcher( $client );

		\WP_CLI::log( "Fetching database {$database_id}..." );

		// Get database schema.
		$schema = $fetcher->get_database_schema( $database_id );

		\WP_CLI::log( \WP_CLI::colorize( '%GDatabase Information:%n' ) );
		\WP_CLI::log( '  ID:            ' . ( $schema['id'] ?? 'N/A' ) );
		\WP_CLI::log( '  Title:         ' . ( $schema['title'] ?? 'Untitled' ) );
		\WP_CLI::log( '  Last Edited:   ' . CommandHelpers::format_timestamp( $schema['last_edited_time'] ?? '' ) );
		\WP_CLI::log( '' );

		// Display properties/columns.
		\WP_CLI::log( \WP_CLI::colorize( '%GProperties (Columns):%n' ) );
		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $name => $prop ) {
				$type = $prop['type'] ?? 'unknown';
				\WP_CLI::log( sprintf( '  - %s (%s)', $name, $type ) );
			}
		}

		// Fetch sample rows.
		\WP_CLI::log( '' );
		\WP_CLI::log( \WP_CLI::colorize( "%GSample Rows (showing first {$limit}):%n" ) );

		$entries = $fetcher->query_database( $database_id );

		if ( empty( $entries ) ) {
			\WP_CLI::warning( 'No entries found in database.' );
			return;
		}

		// Limit entries for display.
		$sample_entries = array_slice( $entries, 0, $limit );

		// Normalize and prepare for display.
		$rows = array();
		foreach ( $sample_entries as $entry ) {
			$normalized = $fetcher->normalize_entry( $entry );
			$rows[]     = array(
				'ID'         => substr( $normalized['id'], 0, 8 ) . '...',
				'Created'    => CommandHelpers::format_timestamp( $normalized['created_time'] ),
				'Properties' => count( $normalized['properties'] ),
			);
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'ID', 'Created', 'Properties' ) );

		\WP_CLI::log( sprintf( "\nTotal entries in database: %d", count( $entries ) ) );
	}
}
