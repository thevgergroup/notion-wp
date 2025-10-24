<?php
/**
 * Table Block Converter
 *
 * Converts Notion table blocks to WordPress table blocks.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;

/**
 * Converts Notion table blocks to WordPress table blocks
 *
 * @since 1.0.0
 */
class TableConverter implements BlockConverterInterface {

	/**
	 * Check if this converter supports the given block type
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if this converter handles this block type.
	 */
	public function supports( array $notion_block ): bool {
		return isset( $notion_block['type'] ) && 'table' === $notion_block['type'];
	}

	/**
	 * Convert Notion table block to WordPress table block
	 *
	 * Note: Notion table blocks contain table_row children that need to be
	 * fetched separately. This converter handles the table structure.
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string Gutenberg block HTML.
	 */
	public function convert( array $notion_block ): string {
		$table_data = $notion_block['table'] ?? array();
		$has_header = $table_data['has_column_header'] ?? false;
		$has_row_header = $table_data['has_row_header'] ?? false;

		// Get children (table rows) if available.
		$children = $notion_block['children'] ?? array();

		if ( empty( $children ) ) {
			// Table has no rows - return placeholder.
			return "<!-- wp:paragraph -->\n<p><em>[Table with no content]</em></p>\n<!-- /wp:paragraph -->\n\n";
		}

		// Build table HTML.
		$table_html = '<figure class="wp-block-table"><table>';

		// Process rows.
		$first_row = true;
		foreach ( $children as $row_block ) {
			if ( 'table_row' !== ( $row_block['type'] ?? '' ) ) {
				continue;
			}

			$cells = $row_block['table_row']['cells'] ?? array();

			if ( empty( $cells ) ) {
				continue;
			}

			// Determine if this row should use <th> tags.
			$use_header = ( $first_row && $has_header );
			$tag_open   = $use_header ? '<thead><tr>' : '<tr>';
			$tag_close  = $use_header ? '</tr></thead>' : '</tr>';
			$cell_tag   = $use_header || $has_row_header ? 'th' : 'td';

			$table_html .= $tag_open;

			foreach ( $cells as $index => $cell ) {
				// Use <th> for first cell if has_row_header is true.
				$current_cell_tag = ( $has_row_header && 0 === $index ) ? 'th' : $cell_tag;

				$cell_content = $this->extract_rich_text( $cell );
				$table_html  .= sprintf(
					'<%s>%s</%s>',
					$current_cell_tag,
					wp_kses_post( $cell_content ),
					$current_cell_tag
				);
			}

			$table_html .= $tag_close;

			if ( $first_row && $has_header ) {
				$table_html .= '<tbody>';
			}

			$first_row = false;
		}

		// Close tbody if we opened it.
		if ( $has_header && count( $children ) > 1 ) {
			$table_html .= '</tbody>';
		}

		$table_html .= '</table></figure>';

		// Return Gutenberg table block.
		return sprintf(
			"<!-- wp:table -->\n%s\n<!-- /wp:table -->\n\n",
			$table_html
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
