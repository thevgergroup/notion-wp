<?php
/**
 * Paragraph Block Converter
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;
use NotionSync\Blocks\LinkRewriter;

/**
 * Converts Notion paragraph blocks to Gutenberg paragraphs
 *
 * Handles rich text formatting including bold, italic, code, strikethrough,
 * underline, and links. Properly escapes all content for security.
 *
 * @since 1.0.0
 */
class ParagraphConverter implements BlockConverterInterface {
	/**
	 * Check if this converter supports the given Notion block
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if block type is 'paragraph'.
	 */
	public function supports( array $notion_block ): bool {
		return isset( $notion_block['type'] ) && 'paragraph' === $notion_block['type'];
	}

	/**
	 * Convert Notion paragraph to Gutenberg paragraph
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string Gutenberg paragraph block HTML.
	 */
	public function convert( array $notion_block ): string {
		$rich_text    = $notion_block['paragraph']['rich_text'] ?? array();
		$html_content = $this->convert_rich_text( $rich_text );

		// Handle empty paragraphs.
		if ( empty( $html_content ) ) {
			$html_content = '&nbsp;';
		}

		return sprintf(
			"<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->\n\n",
			$html_content
		);
	}

	/**
	 * Convert Notion rich text array to HTML
	 *
	 * Processes an array of rich text objects, applying formatting annotations
	 * and converting to HTML with proper escaping.
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
	 * Applies formatting annotations in the correct order and handles links.
	 *
	 * @since 1.0.0
	 *
	 * @param array $text_item A single Notion rich text object.
	 * @return string Formatted HTML for this text segment.
	 */
	private function convert_text_item( array $text_item ): string {
		// Extract content based on text type.
		$content = $this->extract_content( $text_item );

		// Apply annotations (bold, italic, etc.).
		$formatted = $this->apply_annotations( $content, $text_item['annotations'] ?? array() );

		// Apply link if present.
		$formatted = $this->apply_link( $formatted, $text_item );

		return $formatted;
	}

	/**
	 * Extract text content from a rich text item
	 *
	 * Handles different text types (text, mention, equation).
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
				// For mentions, use plain_text fallback.
				return $text_item['plain_text'] ?? '';

			case 'equation':
				// For equations, use the expression.
				return $text_item['equation']['expression'] ?? '';

			default:
				return $text_item['plain_text'] ?? '';
		}
	}

	/**
	 * Apply formatting annotations to text
	 *
	 * Wraps text in HTML tags based on Notion annotations.
	 * Annotations are applied in a specific order to ensure proper nesting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content     The text content to format.
	 * @param array  $annotations Notion annotations object.
	 * @return string HTML with formatting tags applied.
	 */
	private function apply_annotations( string $content, array $annotations ): string {
		// Escape the content first.
		$formatted = esc_html( $content );

		// Apply code first (innermost).
		if ( ! empty( $annotations['code'] ) ) {
			$formatted = '<code>' . $formatted . '</code>';
		}

		// Apply strikethrough.
		if ( ! empty( $annotations['strikethrough'] ) ) {
			$formatted = '<s>' . $formatted . '</s>';
		}

		// Apply underline.
		if ( ! empty( $annotations['underline'] ) ) {
			$formatted = '<u>' . $formatted . '</u>';
		}

		// Apply italic.
		if ( ! empty( $annotations['italic'] ) ) {
			$formatted = '<em>' . $formatted . '</em>';
		}

		// Apply bold (outermost).
		if ( ! empty( $annotations['bold'] ) ) {
			$formatted = '<strong>' . $formatted . '</strong>';
		}

		return $formatted;
	}

	/**
	 * Apply link to formatted text
	 *
	 * Wraps text in an anchor tag if a link is present.
	 * Automatically rewrites Notion internal links to WordPress permalinks
	 * if the target page has been synced.
	 *
	 * @since 1.0.0
	 *
	 * @param string $formatted  The formatted HTML content.
	 * @param array  $text_item  The rich text item.
	 * @return string HTML with link applied if present.
	 */
	private function apply_link( string $formatted, array $text_item ): string {
		$link_url = null;

		// Check for link in text object.
		if ( isset( $text_item['text']['link']['url'] ) ) {
			$link_url = $text_item['text']['link']['url'];
		} elseif ( isset( $text_item['href'] ) ) {
			// Alternative location for links.
			$link_url = $text_item['href'];
		}

		if ( $link_url ) {
			// Rewrite Notion internal links to WordPress permalinks.
			$link_data   = LinkRewriter::rewrite_url( $link_url );
			$escaped_url = esc_url( $link_data['url'] );

			// Add data-notion-id attribute if this is a Notion link.
			// This allows the link to be updated when permalink structure changes.
			if ( $link_data['notion_page_id'] ) {
				$formatted = sprintf(
					'<a href="%s" data-notion-id="%s">%s</a>',
					$escaped_url,
					esc_attr( $link_data['notion_page_id'] ),
					$formatted
				);
			} else {
				$formatted = sprintf( '<a href="%s">%s</a>', $escaped_url, $formatted );
			}
		}

		return $formatted;
	}
}
