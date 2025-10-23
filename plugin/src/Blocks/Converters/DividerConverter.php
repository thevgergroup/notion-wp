<?php
/**
 * Divider Block Converter
 *
 * Converts Notion divider blocks to WordPress separator blocks.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;

/**
 * Converts Notion divider blocks to WordPress separator blocks
 *
 * @since 1.0.0
 */
class DividerConverter implements BlockConverterInterface {

	/**
	 * Check if this converter supports the given block type
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if this converter handles this block type.
	 */
	public function supports( array $notion_block ): bool {
		return isset( $notion_block['type'] ) && 'divider' === $notion_block['type'];
	}

	/**
	 * Convert Notion divider block to WordPress separator block
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string Gutenberg block HTML.
	 */
	public function convert( array $notion_block ): string {
		// Return Gutenberg separator block.
		return "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->\n\n";
	}
}
