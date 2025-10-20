<?php
/**
 * Child Page Block Converter
 *
 * Converts Notion child_page blocks (embedded sub-pages) to WordPress content.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;
use NotionSync\Blocks\LinkRewriter;

/**
 * Converts Notion child_page blocks to linked references
 *
 * Child pages in Notion are sub-pages embedded within a parent page.
 * This converter creates a link to the child page, using the WordPress
 * permalink if the page has been synced, or a Notion URL otherwise.
 *
 * @since 1.0.0
 */
class ChildPageConverter implements BlockConverterInterface {
	/**
	 * Check if this converter supports the given Notion block
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if block type is 'child_page'.
	 */
	public function supports( array $notion_block ): bool {
		return isset( $notion_block['type'] ) && 'child_page' === $notion_block['type'];
	}

	/**
	 * Convert Notion child_page block to Gutenberg content
	 *
	 * Creates a paragraph with a link to the child page. The link will point to
	 * the WordPress post if the child page has been synced, or to Notion if not.
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string Gutenberg block HTML.
	 */
	public function convert( array $notion_block ): string {
		$page_id = $notion_block['id'] ?? '';
		$title   = $notion_block['child_page']['title'] ?? 'Untitled Page';

		if ( empty( $page_id ) ) {
			// Cannot create link without page ID.
			return sprintf(
				"<!-- wp:paragraph -->\n<p><strong>%s</strong></p>\n<!-- /wp:paragraph -->\n\n",
				esc_html( $title )
			);
		}

		// Normalize page ID (remove dashes).
		$normalized_id = str_replace( '-', '', $page_id );

		// Get the appropriate URL (WordPress permalink or Notion URL).
		$url = LinkRewriter::get_wordpress_permalink( $normalized_id );
		if ( ! $url ) {
			// Page not synced yet, use Notion URL.
			$url = 'https://notion.so/' . $normalized_id;
		}

		// Create a paragraph with an icon and link to the child page.
		// Include data-notion-id attribute so links can be updated when permalink structure changes.
		return sprintf(
			"<!-- wp:paragraph -->\n<p>📄 <strong><a href=\"%s\" data-notion-id=\"%s\">%s</a></strong></p>\n<!-- /wp:paragraph -->\n\n",
			esc_url( $url ),
			esc_attr( $normalized_id ),
			esc_html( $title )
		);
	}
}
