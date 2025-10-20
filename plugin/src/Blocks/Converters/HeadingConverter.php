<?php
/**
 * Heading Block Converter
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;

/**
 * Converts Notion heading blocks to Gutenberg headings
 *
 * Supports heading levels 1-3 (heading_1, heading_2, heading_3).
 * Handles rich text formatting within headings.
 *
 * @since 1.0.0
 */
class HeadingConverter implements BlockConverterInterface {
	/**
	 * Check if this converter supports the given Notion block
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if block type is a heading.
	 */
	public function supports( array $notion_block ): bool {
		$type = $notion_block['type'] ?? '';
		return in_array( $type, array( 'heading_1', 'heading_2', 'heading_3' ), true );
	}

	/**
	 * Convert Notion heading to Gutenberg heading
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string Gutenberg heading block HTML.
	 */
	public function convert( array $notion_block ): string {
		$type  = $notion_block['type'] ?? '';
		$level = $this->get_heading_level( $type );

		// Get rich text from the appropriate property.
		$rich_text = $notion_block[ $type ]['rich_text'] ?? array();

		$html_content = $this->convert_rich_text( $rich_text );

		// Handle empty headings.
		if ( empty( $html_content ) ) {
			$html_content = '&nbsp;';
		}

		return sprintf(
			"<!-- wp:heading {\"level\":%d} -->\n<h%d>%s</h%d>\n<!-- /wp:heading -->\n\n",
			$level,
			$level,
			$html_content,
			$level
		);
	}

	/**
	 * Get numeric heading level from Notion type
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Notion block type (heading_1, heading_2, heading_3).
	 * @return int Heading level (1-3).
	 */
	private function get_heading_level( string $type ): int {
		switch ( $type ) {
			case 'heading_1':
				return 1;
			case 'heading_2':
				return 2;
			case 'heading_3':
				return 3;
			default:
				return 2; // Default to h2 if unknown.
		}
	}

	/**
	 * Convert Notion rich text array to HTML
	 *
	 * Processes rich text with formatting annotations.
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
