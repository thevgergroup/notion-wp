<?php
/**
 * Numbered List Block Converter
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;

/**
 * Converts Notion numbered_list_item blocks to Gutenberg lists
 *
 * Handles rich text formatting within list items and supports nested lists.
 * Note: In Notion's API, list items are returned as individual blocks.
 * Adjacent list items should be grouped into a single <ol> element.
 *
 * @since 1.0.0
 */
class NumberedListConverter implements BlockConverterInterface {
	/**
	 * Check if this converter supports the given Notion block
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if block type is 'numbered_list_item'.
	 */
	public function supports( array $notion_block ): bool {
		return isset( $notion_block['type'] ) && 'numbered_list_item' === $notion_block['type'];
	}

	/**
	 * Convert Notion numbered list item to Gutenberg list
	 *
	 * Note: This converts a single list item. The BlockConverter should group
	 * consecutive list items together, but for Phase 1 MVP, we output individual
	 * list blocks which WordPress will render correctly.
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string Gutenberg list block HTML.
	 */
	public function convert( array $notion_block ): string {
		$rich_text = $notion_block['numbered_list_item']['rich_text'] ?? array();
		$html_content = $this->convert_rich_text( $rich_text );

		// Handle empty list items.
		if ( empty( $html_content ) ) {
			$html_content = '&nbsp;';
		}

		// Check if this item has children (nested list).
		$has_children = ! empty( $notion_block['has_children'] );
		$children_html = '';

		if ( $has_children && isset( $notion_block['children'] ) ) {
			$children_html = $this->convert_children( $notion_block['children'] );
		}

		return sprintf(
			"<!-- wp:list {\"ordered\":true} -->\n<ol><li>%s%s</li></ol>\n<!-- /wp:list -->\n\n",
			$html_content,
			$children_html
		);
	}

	/**
	 * Convert nested children blocks
	 *
	 * Recursively converts child blocks (supports nested lists).
	 *
	 * @since 1.0.0
	 *
	 * @param array $children Array of child block objects.
	 * @return string HTML for nested content.
	 */
	private function convert_children( array $children ): string {
		$html = '';

		foreach ( $children as $child_block ) {
			// Check if child is also a numbered list item.
			if ( $this->supports( $child_block ) ) {
				$child_rich_text = $child_block['numbered_list_item']['rich_text'] ?? array();
				$child_content = $this->convert_rich_text( $child_rich_text );

				if ( empty( $child_content ) ) {
					$child_content = '&nbsp;';
				}

				$html .= '<ol><li>' . $child_content . '</li></ol>';
			}
		}

		return $html;
	}

	/**
	 * Convert Notion rich text array to HTML
	 *
	 * @since 1.0.0
	 *
	 * @param array $rich_text_array Array of Notion rich text objects.
	 * @return string Formatted HTML string.
	 */
	private function convert_rich_text( array $rich_text_array ): string {
		$html = '';

		foreach ( $rich_text_array as $text_item ) {
			$html .= $this->convert_text_item( $text_item );
		}

		return $html;
	}

	/**
	 * Convert a single rich text item to HTML
	 *
	 * @since 1.0.0
	 *
	 * @param array $text_item A single Notion rich text object.
	 * @return string Formatted HTML for this text segment.
	 */
	private function convert_text_item( array $text_item ): string {
		$content = $this->extract_content( $text_item );
		$formatted = $this->apply_annotations( $content, $text_item['annotations'] ?? array() );
		$formatted = $this->apply_link( $formatted, $text_item );

		return $formatted;
	}

	/**
	 * Extract text content from a rich text item
	 *
	 * @since 1.0.0
	 *
	 * @param array $text_item The rich text item.
	 * @return string The text content.
	 */
	private function extract_content( array $text_item ): string {
		$type = $text_item['type'] ?? 'text';

		switch ( $type ) {
			case 'text':
				return $text_item['text']['content'] ?? '';
			case 'mention':
				return $text_item['plain_text'] ?? '';
			case 'equation':
				return $text_item['equation']['expression'] ?? '';
			default:
				return $text_item['plain_text'] ?? '';
		}
	}

	/**
	 * Apply formatting annotations to text
	 *
	 * @since 1.0.0
	 *
	 * @param string $content     The text content to format.
	 * @param array  $annotations Notion annotations object.
	 * @return string HTML with formatting tags applied.
	 */
	private function apply_annotations( string $content, array $annotations ): string {
		$formatted = esc_html( $content );

		if ( ! empty( $annotations['code'] ) ) {
			$formatted = '<code>' . $formatted . '</code>';
		}

		if ( ! empty( $annotations['strikethrough'] ) ) {
			$formatted = '<s>' . $formatted . '</s>';
		}

		if ( ! empty( $annotations['underline'] ) ) {
			$formatted = '<u>' . $formatted . '</u>';
		}

		if ( ! empty( $annotations['italic'] ) ) {
			$formatted = '<em>' . $formatted . '</em>';
		}

		if ( ! empty( $annotations['bold'] ) ) {
			$formatted = '<strong>' . $formatted . '</strong>';
		}

		return $formatted;
	}

	/**
	 * Apply link to formatted text
	 *
	 * @since 1.0.0
	 *
	 * @param string $formatted  The formatted HTML content.
	 * @param array  $text_item  The rich text item.
	 * @return string HTML with link applied if present.
	 */
	private function apply_link( string $formatted, array $text_item ): string {
		$link_url = null;

		if ( isset( $text_item['text']['link']['url'] ) ) {
			$link_url = $text_item['text']['link']['url'];
		} elseif ( isset( $text_item['href'] ) ) {
			$link_url = $text_item['href'];
		}

		if ( $link_url ) {
			$escaped_url = esc_url( $link_url );
			$formatted   = sprintf( '<a href="%s">%s</a>', $escaped_url, $formatted );
		}

		return $formatted;
	}
}
