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
	 * Creates a paragraph with a link to the database in Notion.
	 * Database syncing is a Phase 2 feature, so we link back to Notion for now.
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

		// Use LinkRewriter to get the /notion/{slug} URL.
		// This automatically registers the link in LinkRegistry.
		$link_data = LinkRewriter::rewrite_url( '/' . $normalized_id );

		// Update registry with the actual title (LinkRewriter uses ID as temporary title).
		$registry = new LinkRegistry();
		$entry    = $registry->find_by_notion_id( $normalized_id );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( sprintf( '[ChildDatabaseConverter] Database: %s, ID: %s, Entry exists: %s, Entry title: %s', $title, $normalized_id, $entry ? 'yes' : 'no', $entry ? $entry->notion_title : 'N/A' ) );

		if ( $entry && $entry->notion_title === $normalized_id ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( sprintf( '[ChildDatabaseConverter] Updating title from "%s" to "%s"', $entry->notion_title, $title ) );
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
}
