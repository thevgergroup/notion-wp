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

		// Create Notion URL.
		$url = 'https://notion.so/' . $normalized_id;

		// Create a paragraph with database icon and link to Notion.
		$template  = "<!-- wp:paragraph -->\n";
		$template .= '<p>📊 <strong><a href="%s" target="_blank" rel="noopener noreferrer">';
		$template .= 'View Database: %s</a></strong> <em>(opens in Notion)</em></p>';
		$template .= "\n<!-- /wp:paragraph -->\n\n";

		return sprintf(
			$template,
			esc_url( $url ),
			esc_html( $title )
		);
	}
}
