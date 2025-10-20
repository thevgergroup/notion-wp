<?php
/**
 * Block Converter Interface
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks;

/**
 * Interface for block converters
 *
 * Defines the contract that all Notion block converters must implement.
 * Each converter is responsible for transforming a specific Notion block type
 * into Gutenberg-compatible HTML.
 *
 * @since 1.0.0
 */
interface BlockConverterInterface {
	/**
	 * Check if this converter supports the given Notion block
	 *
	 * Implementations should check the block's 'type' field to determine
	 * if this converter can handle the block.
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data from the API.
	 * @return bool True if this converter supports the block type.
	 */
	public function supports( array $notion_block ): bool;

	/**
	 * Convert Notion block to Gutenberg block HTML
	 *
	 * Transforms a Notion block into Gutenberg block markup. The returned HTML
	 * should include Gutenberg block comments and be ready for insertion into
	 * WordPress post_content.
	 *
	 * Example output:
	 * <!-- wp:paragraph -->
	 * <p>Hello world</p>
	 * <!-- /wp:paragraph -->
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data from the API.
	 * @return string Gutenberg block HTML with block comments.
	 */
	public function convert( array $notion_block ): string;
}
