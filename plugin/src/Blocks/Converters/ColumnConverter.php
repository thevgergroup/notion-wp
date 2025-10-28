<?php
/**
 * Column Block Converter
 *
 * Converts Notion column_list and column blocks to WordPress column layouts.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;

/**
 * Converts Notion column blocks to WordPress column layouts
 *
 * Notion uses column_list as parent and column as children.
 * We convert these to WordPress columns block or custom HTML with flexbox.
 *
 * @since 1.0.0
 */
class ColumnConverter implements BlockConverterInterface {

	/**
	 * Check if this converter supports the given block type
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if this converter handles this block type.
	 */
	public function supports( array $notion_block ): bool {
		$type = $notion_block['type'] ?? '';
		return 'column_list' === $type || 'column' === $type;
	}

	/**
	 * Convert Notion column block to WordPress HTML
	 *
	 * Note: column_list is the parent container, column is the individual column.
	 * The BlockConverter will handle nested children.
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string HTML for column block.
	 */
	public function convert( array $notion_block ): string {
		$type = $notion_block['type'] ?? '';

		if ( 'column_list' === $type ) {
			return $this->convert_column_list( $notion_block );
		}

		if ( 'column' === $type ) {
			return $this->convert_column( $notion_block );
		}

		return '';
	}

	/**
	 * Convert column_list (parent container)
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string HTML for column list container.
	 */
	private function convert_column_list( array $notion_block ): string {
		// Column list opening - children will be inserted by BlockConverter.
		return "<!-- wp:html -->\n<div class=\"notion-columns\">\n";
	}

	/**
	 * Convert individual column
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string HTML for individual column.
	 */
	private function convert_column( array $notion_block ): string {
		// Individual column - content will be inserted by BlockConverter.
		return "\t<div class=\"notion-column\">\n";
	}
}
