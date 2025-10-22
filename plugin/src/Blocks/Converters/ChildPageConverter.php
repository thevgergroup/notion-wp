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
use NotionSync\Router\LinkRegistry;

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

		// Use LinkRewriter to get the /notion/{slug} URL.
		// This automatically registers the link in LinkRegistry.
		$link_data = LinkRewriter::rewrite_url( '/' . $normalized_id );

		// Update registry with the actual title (LinkRewriter uses ID as temporary title).
		$registry = new LinkRegistry();
		$entry    = $registry->find_by_notion_id( $normalized_id );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( sprintf( '[ChildPageConverter] Page: %s, ID: %s, Entry exists: %s, Entry title: %s', $title, $normalized_id, $entry ? 'yes' : 'no', $entry ? $entry->notion_title : 'N/A' ) );

		if ( $entry && $entry->notion_title === $normalized_id ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( sprintf( '[ChildPageConverter] Updating title from "%s" to "%s"', $entry->notion_title, $title ) );
			// Update with actual title if we have it.
			$registry->register(
				array(
					'notion_id'    => $normalized_id,
					'notion_title' => $title,
					'notion_type'  => 'page',
				)
			);
		}

		// Output Notion Link block instead of static HTML.
		// The block will fetch current title/slug/URL at render time.
		return sprintf(
			"<!-- wp:notion-sync/notion-link {\"notionId\":\"%s\",\"showIcon\":true} /-->\n\n",
			esc_attr( $normalized_id )
		);
	}
}
