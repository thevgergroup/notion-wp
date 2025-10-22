<?php
/**
 * Link to Page Block Converter
 *
 * Converts Notion link_to_page blocks (dedicated page links) to WordPress content.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;
use NotionSync\Blocks\LinkRewriter;
use NotionSync\Router\LinkRegistry;

/**
 * Converts Notion link_to_page blocks to linked references
 *
 * Link to page blocks are dedicated blocks that reference another Notion page.
 * This converter creates a link to the referenced page, using the WordPress
 * permalink if the page has been synced, or a Notion URL otherwise.
 *
 * @since 1.0.0
 */
class LinkToPageConverter implements BlockConverterInterface {
	/**
	 * Check if this converter supports the given Notion block
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if block type is 'link_to_page'.
	 */
	public function supports( array $notion_block ): bool {
		return isset( $notion_block['type'] ) && 'link_to_page' === $notion_block['type'];
	}

	/**
	 * Convert Notion link_to_page block to Gutenberg content
	 *
	 * Creates a paragraph with a link to the referenced page. The link uses the
	 * /notion/{slug} format which automatically redirects to WordPress if synced
	 * or Notion if not synced.
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string Gutenberg block HTML.
	 */
	public function convert( array $notion_block ): string {
		// Extract page ID from link_to_page structure.
		$page_id   = null;
		$link_type = 'page';

		if ( isset( $notion_block['link_to_page']['page_id'] ) ) {
			$page_id = $notion_block['link_to_page']['page_id'];
		} elseif ( isset( $notion_block['link_to_page']['database_id'] ) ) {
			// Can also link to databases.
			$page_id   = $notion_block['link_to_page']['database_id'];
			$link_type = 'database';
		}

		if ( empty( $page_id ) ) {
			// Cannot create link without page ID.
			return sprintf(
				"<!-- wp:paragraph -->\n<p><strong>🔗 Linked Page</strong></p>\n<!-- /wp:paragraph -->\n\n"
			);
		}

		// Normalize page ID (remove dashes).
		$normalized_id = str_replace( '-', '', $page_id );

		// Register link in registry (creates entry if doesn't exist).
		// This allows /notion/{slug} URLs to work immediately.
		// Title will be updated when the page is synced.
		$registry = new LinkRegistry();
		$registry->register(
			array(
				'notion_id'    => $normalized_id,
				'notion_title' => $normalized_id, // Use ID as title temporarily.
				'notion_type'  => $link_type,
			)
		);

		// Get slug for this Notion ID.
		$slug = $registry->get_slug_for_notion_id( $normalized_id );

		// Build /notion/{slug} URL.
		$url = home_url( '/notion/' . $slug );

		// Create a paragraph with a link icon and the link.
		// Include data-notion-id attribute for backward compatibility.
		// Note: We use "→" as a visual indicator this is a link block.
		return sprintf(
			"<!-- wp:paragraph -->\n<p>→ <a href=\"%s\" data-notion-id=\"%s\">View linked page</a></p>\n<!-- /wp:paragraph -->\n\n",
			esc_url( $url ),
			esc_attr( $normalized_id )
		);
	}
}
