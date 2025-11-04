<?php
/**
 * Child Database Block Converter
 *
 * Converts Notion child_database blocks (embedded database views) to WordPress content.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;
use NotionSync\Blocks\LinkRewriter;
use NotionSync\Router\LinkRegistry;

/**
 * Converts Notion child_database blocks to linked references
 *
 * Child databases in Notion are database views embedded within a page.
 * For Phase 1 MVP, we create a simple link back to Notion since syncing
 * entire databases is a complex Phase 2 feature.
 *
 * @since 1.0.0
 */
class ChildDatabaseConverter implements BlockConverterInterface {
	/**
	 * Check if this converter supports the given Notion block
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if block type is 'child_database'.
	 */
	public function supports( array $notion_block ): bool {
		return isset( $notion_block['type'] ) && 'child_database' === $notion_block['type'];
	}

	/**
	 * Convert Notion child_database block to Gutenberg content
	 *
	 * Attempts to create a database-view block by:
	 * 1. Querying the Notion loadCachedPageChunkV2 API to get the parent collection ID
	 * 2. Looking up the WordPress database post by collection_id
	 * 3. Creating a notion-wp/database-view block if found
	 * 4. Falling back to notion-link block if lookup fails
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string Gutenberg block HTML.
	 */
	public function convert( array $notion_block ): string {
		$database_id = $notion_block['id'] ?? '';
		$title       = $notion_block['child_database']['title'] ?? 'Untitled Database';

		if ( empty( $database_id ) ) {
			// Cannot create link without database ID.
			return sprintf(
				"<!-- wp:paragraph -->\n<p><strong>📊 Database: %s</strong></p>\n<!-- /wp:paragraph -->\n\n",
				esc_html( $title )
			);
		}

		// Normalize database ID (remove dashes).
		$normalized_id = str_replace( '-', '', $database_id );


		// Try to find parent database via loadCachedPageChunkV2 API.
		$wp_database_id = $this->find_parent_database( $database_id );

		if ( $wp_database_id ) {

			// Create database-view block.
			return sprintf(
				"<!-- wp:notion-wp/database-view {\"databaseId\":%d,\"viewType\":\"table\"} /-->\n\n",
				$wp_database_id
			);
		}

		// Fallback: Parent database not found, create notion-link block.

		// Use LinkRewriter to get the /notion/{slug} URL.
		// This automatically registers the link in LinkRegistry.
		$link_data = LinkRewriter::rewrite_url( '/' . $normalized_id );

		// Update registry with the actual title (LinkRewriter uses ID as temporary title).
		$registry = new LinkRegistry();
		$entry    = $registry->find_by_notion_id( $normalized_id );

		if ( $entry && $entry->notion_title === $normalized_id ) {
			// Update with actual title and type.
			$registry->register(
				array(
					'notion_id'    => $normalized_id,
					'notion_title' => $title,
					'notion_type'  => 'database',
				)
			);
		}

		// Output Notion Link block for database.
		// The block will fetch current title/slug/URL at render time.
		// Open in new tab for databases since they may not be synced to WordPress.
		return sprintf(
			"<!-- wp:notion-sync/notion-link {\"notionId\":\"%s\",\"showIcon\":true,\"openInNewTab\":true} /-->\n\n",
			esc_attr( $normalized_id )
		);
	}

	/**
	 * Find parent database by querying Notion's loadCachedPageChunkV2 API
	 *
	 * Queries the internal page chunk API to extract the collection_id,
	 * then looks up the WordPress database post by that collection_id.
	 *
	 * @since 1.0.0
	 *
	 * @param string $child_database_id The child database block ID.
	 * @return int|null WordPress post ID of the parent database, or null if not found.
	 */
	private function find_parent_database( string $child_database_id ): ?int {
		try {
			// Get Notion API client.
			$client = $this->get_notion_client();
			if ( ! $client ) {
				return null;
			}

			// Format ID with dashes for API call.
			$formatted_id = $this->format_id_with_dashes( $child_database_id );


			// Query the loadCachedPageChunkV2 API.
			$chunk_data = $client->load_page_chunk( $formatted_id );

			if ( isset( $chunk_data['error'] ) ) {
				return null;
			}

			// Extract collection_id from the response.
			$collection_id = $this->extract_collection_id( $chunk_data, $formatted_id );

			if ( ! $collection_id ) {
				return null;
			}


			// Look up WordPress database post by collection_id.
			return $this->lookup_database_by_collection_id( $collection_id );

		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Get NotionClient instance
	 *
	 * Retrieves and decrypts the Notion API token from WordPress options
	 * and creates a NotionClient instance.
	 *
	 * @since 1.0.0
	 *
	 * @return \NotionSync\API\NotionClient|null NotionClient instance or null on failure.
	 */
	private function get_notion_client(): ?\NotionSync\API\NotionClient {
		$encrypted_token = get_option( 'notion_wp_token' );

		if ( empty( $encrypted_token ) ) {
			return null;
		}

		try {
			$token = \NotionSync\Security\Encryption::decrypt( $encrypted_token );

			if ( empty( $token ) ) {
				return null;
			}

			return new \NotionSync\API\NotionClient( $token );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Format ID with dashes for UUID format
	 *
	 * Converts a 32-character hex ID to standard UUID format with dashes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id ID with or without dashes.
	 * @return string ID formatted with dashes (8-4-4-4-12).
	 */
	private function format_id_with_dashes( string $id ): string {
		$normalized = str_replace( '-', '', $id );

		if ( 32 !== strlen( $normalized ) ) {
			return $id; // Return as-is if not standard length.
		}

		return substr( $normalized, 0, 8 ) . '-' .
			substr( $normalized, 8, 4 ) . '-' .
			substr( $normalized, 12, 4 ) . '-' .
			substr( $normalized, 16, 4 ) . '-' .
			substr( $normalized, 20 );
	}

	/**
	 * Extract collection_id from page chunk response
	 *
	 * Navigates the page chunk response structure to extract the collection_id
	 * from the collection_view format.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $chunk_data Page chunk response data.
	 * @param string $formatted_id Formatted block ID.
	 * @return string|null Collection ID or null if not found.
	 */
	private function extract_collection_id( array $chunk_data, string $formatted_id ): ?string {
		// Validate response structure.
		if ( ! isset( $chunk_data['recordMap'] ) ) {
			return null;
		}

		$record_map = $chunk_data['recordMap'];

		// Get the block data.
		if ( ! isset( $record_map['block'][ $formatted_id ]['value'] ) ) {
			return null;
		}

		$block_data = $record_map['block'][ $formatted_id ]['value'];

		// Get view IDs.
		if ( ! isset( $block_data['view_ids'] ) || empty( $block_data['view_ids'] ) ) {
			return null;
		}

		$view_id = $block_data['view_ids'][0];

		// Get collection view data.
		if ( ! isset( $record_map['collection_view'][ $view_id ]['value'] ) ) {
			return null;
		}

		$collection_view = $record_map['collection_view'][ $view_id ]['value'];

		// Get collection pointer.
		if ( ! isset( $collection_view['format']['collection_pointer']['id'] ) ) {
			return null;
		}

		return $collection_view['format']['collection_pointer']['id'];
	}

	/**
	 * Look up WordPress database post by collection_id
	 *
	 * Queries the postmeta table for a database post with matching notion_collection_id.
	 *
	 * @since 1.0.0
	 *
	 * @param string $collection_id Notion collection ID.
	 * @return int|null WordPress post ID or null if not found.
	 */
	private function lookup_database_by_collection_id( string $collection_id ): ?int {
		global $wpdb;

		// Normalize collection_id by removing dashes for lookup.
		$normalized_id = str_replace( '-', '', $collection_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Necessary for meta lookup.
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = 'notion_collection_id'
				AND meta_value = %s
				LIMIT 1",
				$normalized_id
			)
		);

		if ( $post_id ) {
			return (int) $post_id;
		}

		return null;
	}
}
