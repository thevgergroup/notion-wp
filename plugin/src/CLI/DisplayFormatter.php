<?php
/**
 * WP-CLI Display Formatter
 *
 * Provides formatting utilities for WP-CLI output.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\CLI;

use NotionSync\Sync\ContentFetcher;

/**
 * Class DisplayFormatter
 *
 * Handles formatting and display of information in WP-CLI commands.
 *
 * @since 1.0.0
 */
class DisplayFormatter {

	/**
	 * Resolve parent page title for display.
	 *
	 * For pages with a parent page, fetches and displays the parent's title.
	 * Uses caching to avoid redundant API calls.
	 *
	 * @param array          $page         Page data from Notion.
	 * @param ContentFetcher $fetcher      Content fetcher instance for API calls.
	 * @param array          $parent_cache Reference to parent title cache array.
	 * @return string Parent display string (title, "workspace", or "N/A").
	 */
	public static function resolve_parent_title( array $page, ContentFetcher $fetcher, array &$parent_cache ): string {
		$parent_type = $page['parent_type'] ?? 'unknown';

		// Handle non-page parents.
		if ( 'workspace' === $parent_type ) {
			return 'workspace';
		}

		if ( 'database_id' === $parent_type ) {
			return 'database';
		}

		// For page_id parents, fetch the parent page title.
		if ( 'page_id' === $parent_type ) {
			// Parent info should be in the raw page data that ContentFetcher has.
			// We need to get the parent page ID and fetch its title.
			// The parent structure is: {"type": "page_id", "page_id": "uuid"}.

			// Since we only have the formatted data here, we need to make an API call.
			// First check if we have this in our cache.
			$page_id = $page['id'] ?? '';

			// We need to get the parent page ID from the raw Notion data.
			// The easiest way is to fetch the page details which includes parent info.
			try {
				$page_details = $fetcher->fetch_page_properties( $page_id );

				if ( isset( $page_details['parent']['page_id'] ) ) {
					$parent_id = $page_details['parent']['page_id'];

					// Check cache first.
					if ( isset( $parent_cache[ $parent_id ] ) ) {
						return $parent_cache[ $parent_id ];
					}

					// Fetch parent page title.
					$parent_details = $fetcher->fetch_page_properties( $parent_id );
					$parent_title   = $parent_details['title'] ?? 'Untitled';

					// Cache it.
					$parent_cache[ $parent_id ] = $parent_title;

					return $parent_title;
				}
			} catch ( \Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( 'Failed to resolve parent title: ' . $e->getMessage() );
				return 'page_id';
			}
		}

		return $parent_type;
	}
}
