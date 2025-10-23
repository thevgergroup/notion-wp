<?php
/**
 * Quote Block Converter
 *
 * Converts Notion quote blocks to WordPress quote blocks.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;

/**
 * Converts Notion quote blocks to WordPress quote blocks
 *
 * @since 1.0.0
 */
class QuoteConverter implements BlockConverterInterface {

	/**
	 * Check if this converter supports the given block type
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if this converter handles this block type.
	 */
	public function supports( array $notion_block ): bool {
		return isset( $notion_block['type'] ) && 'quote' === $notion_block['type'];
	}

	/**
	 * Convert Notion quote block to WordPress quote block
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string Gutenberg block HTML.
	 */
	public function convert( array $notion_block ): string {
		$block_data = $notion_block['quote'] ?? array();
		$rich_text  = $block_data['rich_text'] ?? array();

		// Extract text content and convert rich text formatting.
		$text = $this->extract_rich_text( $rich_text );

		if ( empty( $text ) ) {
			return '';
		}

		// Return Gutenberg quote block.
		return sprintf(
			"<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>%s</p></blockquote>\n<!-- /wp:quote -->\n\n",
			wp_kses_post( $text )
		);
	}

	/**
	 * Extract text from Notion rich text array
	 *
	 * Converts Notion rich text format to HTML with formatting.
	 *
	 * @param array $rich_text Array of rich text objects.
	 * @return string HTML formatted text.
	 */
	private function extract_rich_text( array $rich_text ): string {
		$html = '';

		foreach ( $rich_text as $text_obj ) {
			$content     = $text_obj['plain_text'] ?? '';
			$annotations = $text_obj['annotations'] ?? array();

			// Apply formatting.
			if ( ! empty( $annotations['bold'] ) ) {
				$content = '<strong>' . $content . '</strong>';
			}
			if ( ! empty( $annotations['italic'] ) ) {
				$content = '<em>' . $content . '</em>';
			}
			if ( ! empty( $annotations['strikethrough'] ) ) {
				$content = '<s>' . $content . '</s>';
			}
			if ( ! empty( $annotations['code'] ) ) {
				$content = '<code>' . $content . '</code>';
			}

			// Handle links.
			if ( isset( $text_obj['href'] ) ) {
				$content = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $text_obj['href'] ),
					$content
				);
			}

			$html .= $content;
		}

		return $html;
	}
}
