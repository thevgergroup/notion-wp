<?php
/**
 * Block Converter Registry
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks;

use NotionSync\Utils\PerformanceLogger;

/**
 * Registry for managing block converters
 *
 * Orchestrates the conversion of Notion blocks to Gutenberg blocks by
 * maintaining a registry of converters and routing blocks to the appropriate
 * converter based on block type.
 *
 * @since 1.0.0
 */
class BlockConverter {
	/**
	 * Registered block converters
	 *
	 * @var BlockConverterInterface[]
	 */
	private array $converters = array();

	/**
	 * Constructor
	 *
	 * Initializes the converter registry and registers default converters.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->register_default_converters();
	}

	/**
	 * Register default block converters
	 *
	 * Registers the built-in converters for basic Notion block types.
	 * Third-party plugins can add additional converters using the
	 * 'notion_sync_block_converters' filter.
	 *
	 * @since 1.0.0
	 */
	private function register_default_converters(): void {
		$default_converters = array(
			new Converters\ParagraphConverter(),
			new Converters\HeadingConverter(),
			new Converters\BulletedListConverter(),
			new Converters\NumberedListConverter(),
			new Converters\QuoteConverter(),
			new Converters\DividerConverter(),
			new Converters\TableConverter(),
			new Converters\ChildPageConverter(),
			new Converters\ChildDatabaseConverter(),
			new Converters\LinkToPageConverter(),
			new Converters\ImageConverter(),
			new Converters\FileConverter(),
		);

		/**
		 * Filter the default block converters
		 *
		 * Allows third-party plugins to register custom block converters.
		 *
		 * @since 1.0.0
		 *
		 * @param BlockConverterInterface[] $default_converters Array of converter instances.
		 */
		$converters = apply_filters( 'notion_sync_block_converters', $default_converters );

		foreach ( $converters as $converter ) {
			if ( $converter instanceof BlockConverterInterface ) {
				$this->converters[] = $converter;
			}
		}
	}

	/**
	 * Register a converter for a block type
	 *
	 * Adds a converter to the registry. Converters are checked in the order
	 * they are registered, so later converters can override earlier ones if
	 * their supports() method returns true for the same block type.
	 *
	 * @since 1.0.0
	 *
	 * @param string                  $type      Block type identifier (for reference only).
	 * @param BlockConverterInterface $converter The converter instance.
	 */
	public function register_converter( string $type, BlockConverterInterface $converter ): void {
		$this->converters[] = $converter;
	}

	/**
	 * Convert array of Notion blocks to Gutenberg content
	 *
	 * Processes an array of Notion blocks and converts each to Gutenberg format.
	 * Blocks without a matching converter are logged and skipped.
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_blocks Array of Notion block objects from the API.
	 * @return string Gutenberg block HTML ready for post_content.
	 */
	public function convert_blocks( array $notion_blocks ): string {
		$gutenberg_content = '';
		$block_type_counts = array();

		foreach ( $notion_blocks as $notion_block ) {
			$block_type = $notion_block['type'] ?? 'unknown';
			$converter  = $this->find_converter( $notion_block );

			if ( $converter ) {
				// Track block type performance.
				$perf_label = "convert_block_{$block_type}";
				PerformanceLogger::start( $perf_label );

				$gutenberg_content .= $converter->convert( $notion_block );

				PerformanceLogger::stop( $perf_label );

				// Count block types.
				if ( ! isset( $block_type_counts[ $block_type ] ) ) {
					$block_type_counts[ $block_type ] = 0;
				}
				++$block_type_counts[ $block_type ];
			} else {
				// Log unsupported block type.
				$this->log_unsupported_block( $notion_block );

				// Add HTML comment as placeholder.
				$gutenberg_content .= $this->create_unsupported_block_placeholder( $notion_block );
			}
		}

		// Log block type summary.
		error_log(
			sprintf(
				'[PERF] Block type distribution: %s',
				wp_json_encode( $block_type_counts )
			)
		);

		return $gutenberg_content;
	}

	/**
	 * Find a converter that supports the given block
	 *
	 * Iterates through registered converters and returns the first one
	 * that supports the given block type.
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The Notion block data.
	 * @return BlockConverterInterface|null The matching converter or null if none found.
	 */
	private function find_converter( array $notion_block ): ?BlockConverterInterface {
		foreach ( $this->converters as $converter ) {
			if ( $converter->supports( $notion_block ) ) {
				return $converter;
			}
		}

		return null;
	}

	/**
	 * Log unsupported block type
	 *
	 * Creates a log entry for blocks that don't have a registered converter.
	 * This helps identify gaps in block type coverage.
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The unsupported block data.
	 */
	private function log_unsupported_block( array $notion_block ): void {
		$block_type = $notion_block['type'] ?? 'unknown';
		$block_id   = $notion_block['id'] ?? 'no-id';

		error_log(
			sprintf(
				'[NotionSync] Unsupported block type: %s (ID: %s)',
				$block_type,
				$block_id
			)
		);
	}

	/**
	 * Create placeholder for unsupported blocks
	 *
	 * Generates an HTML comment placeholder for blocks that cannot be converted.
	 * This preserves the block's existence in the content while making it visible
	 * that manual conversion may be needed.
	 *
	 * @since 1.0.0
	 *
	 * @param array $notion_block The unsupported block data.
	 * @return string HTML comment placeholder.
	 */
	private function create_unsupported_block_placeholder( array $notion_block ): string {
		$block_type = $notion_block['type'] ?? 'unknown';
		$block_id   = $notion_block['id'] ?? 'no-id';

		return sprintf(
			"<!-- Unsupported Notion block: %s (ID: %s) -->\n\n",
			esc_html( $block_type ),
			esc_html( $block_id )
		);
	}

	/**
	 * Get all registered converters
	 *
	 * Returns the array of all registered converter instances.
	 * Useful for testing and debugging.
	 *
	 * @since 1.0.0
	 *
	 * @return BlockConverterInterface[] Array of converter instances.
	 */
	public function get_converters(): array {
		return $this->converters;
	}
}
