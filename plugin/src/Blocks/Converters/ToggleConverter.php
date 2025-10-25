<?php
/**
 * Toggle Block Converter
 *
 * Converts Notion toggle blocks to WordPress details/summary HTML.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;

/**
 * Converts Notion toggle blocks to WordPress HTML with details/summary
 *
 * Notion toggles are collapsible sections with a title and optional nested content.
 * They are converted to HTML5 <details>/<summary> elements.
 *
 * @since 1.0.0
 */
class ToggleConverter implements BlockConverterInterface {

	/**
	 * Check if this converter supports the given block type
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if this converter handles this block type.
	 */
	public function supports( array $notion_block ): bool {
		return isset( $notion_block['type'] ) && 'toggle' === $notion_block['type'];
	}

	/**
	 * Convert Notion toggle block to WordPress HTML
	 *
	 * Note: Nested children will be converted by the main BlockConverter
	 * when it processes has_children blocks.
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string HTML for toggle block.
	 */
	public function convert( array $notion_block ): string {
		$block_data = $notion_block['toggle'] ?? array();
		$rich_text  = $block_data['rich_text'] ?? array();
		$color      = $block_data['color'] ?? 'default';

		// Extract toggle title.
		$title = $this->extract_rich_text( $rich_text );

		if ( empty( $title ) ) {
			return '';
		}

		// Map Notion color to CSS class.
		$color_class = $this->map_color_to_class( $color );

		// Note: This creates the opening of a details element.
		// The BlockConverter will add nested children content,
		// and we'll close the details element after children.
		// For now, we create a self-contained toggle.
		return sprintf(
			"<!-- wp:html -->\n<details class=\"notion-toggle %s\">\n\t<summary>%s</summary>\n\t<div class=\"notion-toggle-content\">\n\t\t<!-- Children will be inserted here by BlockConverter -->\n\t</div>\n</details>\n<!-- /wp:html -->\n\n",
			esc_attr( $color_class ),
			wp_kses_post( $title )
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

			// Escape content first.
			$content = esc_html( $content );

			// Apply formatting.
			if ( ! empty( $annotations['code'] ) ) {
				$content = '<code>' . $content . '</code>';
			}
			if ( ! empty( $annotations['bold'] ) ) {
				$content = '<strong>' . $content . '</strong>';
			}
			if ( ! empty( $annotations['italic'] ) ) {
				$content = '<em>' . $content . '</em>';
			}
			if ( ! empty( $annotations['strikethrough'] ) ) {
				$content = '<s>' . $content . '</s>';
			}
			if ( ! empty( $annotations['underline'] ) ) {
				$content = '<u>' . $content . '</u>';
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

	/**
	 * Map Notion color to CSS class
	 *
	 * @param string $color Notion color value.
	 * @return string CSS class name.
	 */
	private function map_color_to_class( string $color ): string {
		// Remove '_background' suffix if present.
		$color = str_replace( '_background', '', $color );

		// Map Notion colors to CSS classes.
		$color_map = array(
			'default' => 'notion-toggle-default',
			'gray'    => 'notion-toggle-gray',
			'brown'   => 'notion-toggle-brown',
			'orange'  => 'notion-toggle-orange',
			'yellow'  => 'notion-toggle-yellow',
			'green'   => 'notion-toggle-green',
			'blue'    => 'notion-toggle-blue',
			'purple'  => 'notion-toggle-purple',
			'pink'    => 'notion-toggle-pink',
			'red'     => 'notion-toggle-red',
		);

		return $color_map[ $color ] ?? 'notion-toggle-default';
	}
}
