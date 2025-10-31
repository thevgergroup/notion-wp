<?php
/**
 * Fallback Block Converter
 *
 * Handles unsupported Notion block types with graceful degradation.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;

/**
 * Fallback converter for unsupported Notion block types
 *
 * This converter acts as a catch-all for block types that don't have
 * a dedicated converter. It outputs an HTML comment with the block type
 * and attempts to extract any plain text content.
 *
 * IMPORTANT: This converter must be registered LAST in the converter list
 * so it only handles blocks that no other converter supports.
 *
 * @since 1.0.0
 */
class FallbackConverter implements BlockConverterInterface {

	/**
	 * Check if this converter supports the given block type
	 *
	 * This converter supports ALL block types as a fallback.
	 * It should be registered last so other converters get priority.
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool Always returns true (supports all blocks).
	 */
	public function supports( array $notion_block ): bool {
		return isset( $notion_block['type'] );
	}

	/**
	 * Convert unsupported Notion block to a warning comment
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string HTML comment with block type information.
	 */
	public function convert( array $notion_block ): string {
		$type = $notion_block['type'] ?? 'unknown';

		// Try to extract any plain text content from the block.
		$text_content = $this->extract_text_content( $notion_block, $type );

		// Log the unsupported block type for debugging.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging for unsupported blocks.
		error_log(
			sprintf(
				'[NotionSync] Unsupported block type: %s (ID: %s)',
				$type,
				$notion_block['id'] ?? 'unknown'
			)
		);

		// If we found text content, output it as a paragraph with a warning comment.
		if ( ! empty( $text_content ) ) {
			$comment_start = sprintf( '<!-- Unsupported Notion block type: %s -->', esc_html( $type ) );
			$comment_end   = sprintf( '<!-- End unsupported block: %s -->', esc_html( $type ) );
			$paragraph     = sprintf(
				"<!-- wp:paragraph -->\n<p class=\"notion-unsupported-block\">%s</p>\n<!-- /wp:paragraph -->",
				wp_kses_post( $text_content )
			);

			return sprintf( "%s\n%s\n%s\n\n", $comment_start, $paragraph, $comment_end );
		}

		// If no text content, just output a warning comment.
		return sprintf(
			"<!-- Unsupported Notion block type: %s (Block ID: %s) -->\n<!-- This block type is not yet supported by the Notion Sync plugin -->\n\n",
			esc_html( $type ),
			esc_html( $notion_block['id'] ?? 'unknown' )
		);
	}

	/**
	 * Extract any available text content from the block
	 *
	 * Attempts to find text content in common Notion block structures.
	 *
	 * @param array  $notion_block The Notion block data.
	 * @param string $type         Block type.
	 * @return string Extracted text content or empty string.
	 */
	private function extract_text_content( array $notion_block, string $type ): string {
		// Try to get the block-type-specific data.
		$block_data = $notion_block[ $type ] ?? array();

		// Check for rich_text array (most common).
		if ( isset( $block_data['rich_text'] ) && is_array( $block_data['rich_text'] ) ) {
			return $this->extract_rich_text( $block_data['rich_text'] );
		}

		// Check for title array (used in some block types).
		if ( isset( $block_data['title'] ) && is_array( $block_data['title'] ) ) {
			return $this->extract_rich_text( $block_data['title'] );
		}

		// Check for caption array.
		if ( isset( $block_data['caption'] ) && is_array( $block_data['caption'] ) ) {
			return $this->extract_rich_text( $block_data['caption'] );
		}

		// Check for text property (simple string).
		if ( isset( $block_data['text'] ) && is_string( $block_data['text'] ) ) {
			return $block_data['text'];
		}

		// Check for url property (for link-based blocks).
		if ( isset( $block_data['url'] ) && is_string( $block_data['url'] ) ) {
			return sprintf( 'Link: %s', $block_data['url'] );
		}

		return '';
	}

	/**
	 * Extract text from rich text array
	 *
	 * @param array $rich_text Array of rich text objects.
	 * @return string Plain text content.
	 */
	private function extract_rich_text( array $rich_text ): string {
		$text = '';

		foreach ( $rich_text as $text_obj ) {
			$text .= $text_obj['plain_text'] ?? '';
		}

		return $text;
	}
}
