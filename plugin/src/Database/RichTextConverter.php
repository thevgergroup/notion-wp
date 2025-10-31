<?php
/**
 * Rich Text Converter
 *
 * Converts Notion rich_text arrays to formatted HTML with support for
 * annotations (bold, italic, strikethrough, underline, code, colors) and links.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Database;

/**
 * Class RichTextConverter
 *
 * Handles conversion of Notion's rich text format to HTML.
 *
 * @since 1.0.0
 */
class RichTextConverter {

	/**
	 * Convert Notion rich_text array to HTML.
	 *
	 * Processes text content with annotations (bold, italic, etc.) and links.
	 * Returns escaped HTML ready for display.
	 *
	 * @since 1.0.0
	 *
	 * @param array $rich_text_array Array of rich_text objects from Notion.
	 * @return string Formatted HTML string.
	 */
	public function to_html( array $rich_text_array ): string {
		if ( empty( $rich_text_array ) ) {
			return '';
		}

		$html_parts = array();

		foreach ( $rich_text_array as $text_segment ) {
			$html_parts[] = $this->convert_segment( $text_segment );
		}

		return implode( '', $html_parts );
	}

	/**
	 * Convert single rich text segment to HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param array $segment Single rich_text segment.
	 * @return string HTML for this segment.
	 */
	private function convert_segment( array $segment ): string {
		// Extract text content.
		$content = $segment['plain_text'] ?? $segment['text']['content'] ?? '';

		if ( empty( $content ) ) {
			return '';
		}

		// Escape HTML entities first.
		$content = esc_html( $content );

		// Apply annotations if present.
		if ( ! empty( $segment['annotations'] ) ) {
			$content = $this->apply_annotations( $content, $segment['annotations'] );
		}

		// Wrap in link if href is present.
		if ( ! empty( $segment['href'] ) || ! empty( $segment['text']['link']['url'] ) ) {
			$url     = $segment['href'] ?? $segment['text']['link']['url'] ?? '';
			$content = $this->wrap_link( $content, $url );
		}

		return $content;
	}

	/**
	 * Apply Notion annotations to text content.
	 *
	 * Supports: bold, italic, strikethrough, underline, code, color.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content     Escaped text content.
	 * @param array  $annotations Notion annotations object.
	 * @return string Content with HTML tags applied.
	 */
	private function apply_annotations( string $content, array $annotations ): string {
		// Apply bold.
		if ( ! empty( $annotations['bold'] ) ) {
			$content = '<strong>' . $content . '</strong>';
		}

		// Apply italic.
		if ( ! empty( $annotations['italic'] ) ) {
			$content = '<em>' . $content . '</em>';
		}

		// Apply strikethrough.
		if ( ! empty( $annotations['strikethrough'] ) ) {
			$content = '<s>' . $content . '</s>';
		}

		// Apply underline.
		if ( ! empty( $annotations['underline'] ) ) {
			$content = '<u>' . $content . '</u>';
		}

		// Apply code.
		if ( ! empty( $annotations['code'] ) ) {
			$content = '<code>' . $content . '</code>';
		}

		// Apply color (CSS class).
		if ( ! empty( $annotations['color'] ) && 'default' !== $annotations['color'] ) {
			$color_class = $this->get_color_class( $annotations['color'] );
			$content     = '<span class="' . esc_attr( $color_class ) . '">' . $content . '</span>';
		}

		return $content;
	}

	/**
	 * Wrap content in anchor tag.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Formatted content.
	 * @param string $url     Link URL.
	 * @return string Content wrapped in anchor tag.
	 */
	private function wrap_link( string $content, string $url ): string {
		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $url ),
			$content
		);
	}

	/**
	 * Get CSS class name for Notion color.
	 *
	 * Maps Notion color names to CSS class names.
	 *
	 * @since 1.0.0
	 *
	 * @param string $color Notion color name (e.g., 'red', 'blue_background').
	 * @return string CSS class name.
	 */
	private function get_color_class( string $color ): string {
		// Notion colors: gray, brown, orange, yellow, green, blue, purple, pink, red.
		// Background variants: {color}_background.
		$sanitized = sanitize_html_class( $color );
		return 'notion-color-' . $sanitized;
	}

	/**
	 * Convert rich text to plain text (strip formatting).
	 *
	 * Useful for extracting plain text content for indexing or display.
	 *
	 * @since 1.0.0
	 *
	 * @param array $rich_text_array Array of rich_text objects from Notion.
	 * @return string Plain text content.
	 */
	public function to_plain_text( array $rich_text_array ): string {
		if ( empty( $rich_text_array ) ) {
			return '';
		}

		$text_parts = array();

		foreach ( $rich_text_array as $segment ) {
			$text_parts[] = $segment['plain_text'] ?? $segment['text']['content'] ?? '';
		}

		return implode( '', $text_parts );
	}
}
