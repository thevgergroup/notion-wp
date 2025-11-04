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
use NotionSync\Database\DatabasePostType;

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
	 * Creates a paragraph with a link to the referenced page. Uses WordPress
	 * permalink if the target page has been synced, otherwise links to Notion.
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

		// Special handling for database links - check if synced and create database-view block.
		if ( 'database' === $link_type ) {
			$database_post_type = new DatabasePostType();
			$db_post_id         = $database_post_type->find_by_notion_id( $normalized_id );

			if ( $db_post_id ) {
				// Database is synced - create interactive database-view block.

				return sprintf(
					'<!-- wp:notion-wp/database-view ' .
					'{"databaseId":%d,"viewType":"table","showFilters":true,"showExport":true} /-->' . "\n\n",
					$db_post_id
				);
			}

			// Database not synced - continue with link fallback below.
		}

		// Register link in registry (creates entry if doesn't exist).
		// Title will be updated when the target page is synced.
		$registry = new LinkRegistry();
		$registry->register(
			array(
				'notion_id'    => $normalized_id,
				'notion_title' => $normalized_id, // Use ID as title temporarily.
				'notion_type'  => $link_type,
			)
		);

		// Fetch the entry to get current title and sync status.
		$entry = $registry->find_by_notion_id( $normalized_id );

		// Determine URL and link text.
		if ( $entry ) {
			// Use appropriate URL based on sync status.
			if ( 'synced' === $entry->sync_status && ! empty( $entry->wp_post_id ) ) {
				// Synced - use WordPress permalink.
				$url = get_permalink( $entry->wp_post_id );
				if ( ! $url ) {
					// Permalink not available - fall back to Notion.
					$url = 'https://notion.so/' . $normalized_id;
				}
			} else {
				// Not synced - link to Notion.
				$url = 'https://notion.so/' . $normalized_id;
			}

			// Use current title from registry.
			// If title is still a Notion ID (not yet synced), show friendlier text.
			$link_text = $entry->notion_title;
			if ( $link_text === $normalized_id ) {
				$link_text = 'View linked page';
			}

			// Add icon prefix.
			$icon      = 'database' === $entry->notion_type ? '📊' : '📄';
			$link_text = $icon . ' ' . $link_text;
		} else {
			// Entry not found (shouldn't happen after register).
			$url       = 'https://notion.so/' . $normalized_id;
			$link_text = '📄 View linked page';
		}

		// Create a paragraph with the link.
		// Include data-notion-id attribute for future updates.
		return sprintf(
			"<!-- wp:paragraph -->\n<p><a href=\"%s\" data-notion-id=\"%s\" class=\"notion-link\">%s</a></p>\n<!-- /wp:paragraph -->\n\n",
			esc_url( $url ),
			esc_attr( $normalized_id ),
			esc_html( $link_text )
		);
	}
}
