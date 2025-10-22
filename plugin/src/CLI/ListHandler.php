<?php
/**
 * WP-CLI List Handler
 *
 * Handles listing Notion pages and databases.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\CLI;

use NotionSync\API\NotionClient;
use NotionSync\Sync\ContentFetcher;
use NotionSync\Sync\DatabaseFetcher;

/**
 * Class ListHandler
 *
 * Handles listing operations for Notion resources.
 *
 * @since 1.0.0
 */
class ListHandler {

	/**
	 * List Notion pages and databases.
	 *
	 * @param NotionClient $client Notion client instance.
	 * @param string|null  $type   Filter by type: 'page' or 'database' or null for both.
	 * @param int          $limit  Maximum number of items to display.
	 * @param string       $format Output format (table, csv, json, yaml).
	 */
	public static function list_resources( NotionClient $client, ?string $type, int $limit, string $format ): void {
		$items = array();

		// Fetch pages if requested (or no type specified).
		if ( ! $type || 'page' === $type ) {
			\WP_CLI::log( 'Fetching pages from Notion...' );
			$fetcher = new ContentFetcher( $client );
			$pages   = $fetcher->fetch_pages_list( $limit );

			// Cache for parent page titles to avoid redundant API calls.
			$parent_cache = array();

			foreach ( $pages as $page ) {
				$parent_display = DisplayFormatter::resolve_parent_title( $page, $fetcher, $parent_cache );

				$items[] = array(
					'Type'        => 'Page',
					'ID'          => $page['id'] ?? '',
					'Title'       => $page['title'] ?? 'Untitled',
					'Last Edited' => CommandHelpers::format_timestamp( $page['last_edited_time'] ?? '' ),
					'Parent'      => $parent_display,
				);
			}
		}

		// Fetch databases if requested (or no type specified).
		if ( ! $type || 'database' === $type ) {
			\WP_CLI::log( 'Fetching databases from Notion...' );
			$db_fetcher = new DatabaseFetcher( $client );
			$databases  = $db_fetcher->get_databases();

			// Limit databases if type is specifically 'database'.
			if ( 'database' === $type ) {
				$databases = array_slice( $databases, 0, $limit );
			}

			foreach ( $databases as $database ) {
				$items[] = array(
					'Type'        => 'Database',
					'ID'          => $database['id'] ?? '',
					'Title'       => $database['title'] ?? 'Untitled',
					'Last Edited' => CommandHelpers::format_timestamp( $database['last_edited_time'] ?? '' ),
					'Parent'      => 'N/A',
				);
			}
		}

		if ( empty( $items ) ) {
			\WP_CLI::warning( 'No items found. Make sure your integration has access to Notion pages/databases.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $items, array( 'Type', 'ID', 'Title', 'Last Edited', 'Parent' ) );
	}
}
