<?php
/**
 * Callout Block Converter
 *
 * Converts Notion callout blocks to WordPress styled callout blocks.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;

/**
 * Converts Notion callout blocks to WordPress callout blocks
 *
 * Callouts are informational blocks with icons and colored backgrounds.
 * They are converted to custom HTML with appropriate CSS classes.
 *
 * @since 1.0.0
 */
class CalloutConverter implements BlockConverterInterface {

	/**
	 * Check if this converter supports the given block type
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if this converter handles this block type.
	 */
	public function supports( array $notion_block ): bool {
		return isset( $notion_block['type'] ) && 'callout' === $notion_block['type'];
	}

	/**
	 * Convert Notion callout block to WordPress callout block
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string HTML for callout block.
	 */
	public function convert( array $notion_block ): string {
		$block_data = $notion_block['callout'] ?? array();
		$rich_text  = $block_data['rich_text'] ?? array();
		$icon       = $block_data['icon'] ?? array();
		$color      = $block_data['color'] ?? 'default';

		// Extract text content and convert rich text formatting.
		$text = $this->extract_rich_text( $rich_text );

		if ( empty( $text ) ) {
			return '';
		}

		// Extract icon (emoji or external URL).
		$icon_html = $this->extract_icon( $icon );

		// Map Notion color to CSS class.
		$color_class = $this->map_color_to_class( $color );

		// Return custom HTML block with callout styling.
		return sprintf(
			"<!-- wp:html -->\n<div class=\"notion-callout %s\">\n\t%s<div class=\"notion-callout-text\">%s</div>\n</div>\n<!-- /wp:html -->\n\n",
			esc_attr( $color_class ),
			$icon_html,
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

			// Additional XSS protection: sanitize the final content with allowed HTML tags.
			$allowed_html = array(
				'strong' => array(),
				'em'     => array(),
				'code'   => array(),
				'u'      => array(),
				's'      => array(),
				'a'      => array(
					'href' => array(),
				),
			);
			$html        .= wp_kses( $content, $allowed_html );
		}

		return $html;
	}

	/**
	 * Extract icon from Notion icon object
	 *
	 * @param array $icon Notion icon data.
	 * @return string HTML for icon.
	 */
	private function extract_icon( array $icon ): string {
		if ( empty( $icon ) ) {
			return '';
		}

		$type = $icon['type'] ?? '';

		if ( 'emoji' === $type && isset( $icon['emoji'] ) ) {
			return sprintf(
				'<span class="notion-callout-icon">%s</span>',
				esc_html( $icon['emoji'] )
			);
		}

		if ( 'external' === $type && isset( $icon['external']['url'] ) ) {
			return sprintf(
				'<img class="notion-callout-icon" src="%s" alt="" />',
				esc_url( $icon['external']['url'] )
			);
		}

		if ( 'file' === $type && isset( $icon['file']['url'] ) ) {
			return sprintf(
				'<img class="notion-callout-icon" src="%s" alt="" />',
				esc_url( $icon['file']['url'] )
			);
		}

		return '';
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
			'default' => 'notion-callout-default',
			'gray'    => 'notion-callout-gray',
			'brown'   => 'notion-callout-brown',
			'orange'  => 'notion-callout-orange',
			'yellow'  => 'notion-callout-yellow',
			'green'   => 'notion-callout-green',
			'blue'    => 'notion-callout-blue',
			'purple'  => 'notion-callout-purple',
			'pink'    => 'notion-callout-pink',
			'red'     => 'notion-callout-red',
		);

		return $color_map[ $color ] ?? 'notion-callout-default';
	}
}
