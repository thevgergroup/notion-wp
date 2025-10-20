<?php
/**
 * Block Converter Usage Examples
 *
 * This file demonstrates how to use the Block Conversion System
 * in various scenarios. These examples are intended for Stream 3
 * (SyncManager) integration.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Examples;

use NotionSync\Blocks\BlockConverter;
use NotionSync\Blocks\BlockConverterInterface;

/**
 * Example 1: Basic Usage
 *
 * Convert Notion blocks from API response to WordPress content.
 */
function example_basic_conversion(): void {
	// Initialize converter.
	$converter = new BlockConverter();

	// Sample Notion API response (blocks from a page).
	$notion_blocks = array(
		array(
			'type' => 'heading_1',
			'heading_1' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array( 'content' => 'Welcome to My Blog' ),
						'annotations' => array(
							'bold' => false,
							'italic' => false,
							'strikethrough' => false,
							'underline' => false,
							'code' => false,
						),
					),
				),
			),
		),
		array(
			'type' => 'paragraph',
			'paragraph' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array( 'content' => 'This is my first post with ' ),
						'annotations' => array(
							'bold' => false,
							'italic' => false,
							'strikethrough' => false,
							'underline' => false,
							'code' => false,
						),
					),
					array(
						'type' => 'text',
						'text' => array( 'content' => 'bold text' ),
						'annotations' => array(
							'bold' => true,
							'italic' => false,
							'strikethrough' => false,
							'underline' => false,
							'code' => false,
						),
					),
					array(
						'type' => 'text',
						'text' => array( 'content' => ' and ' ),
						'annotations' => array(
							'bold' => false,
							'italic' => false,
							'strikethrough' => false,
							'underline' => false,
							'code' => false,
						),
					),
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'a link',
							'link' => array( 'url' => 'https://example.com' ),
						),
						'annotations' => array(
							'bold' => false,
							'italic' => false,
							'strikethrough' => false,
							'underline' => false,
							'code' => false,
						),
					),
				),
			),
		),
	);

	// Convert to Gutenberg HTML.
	$gutenberg_html = $converter->convert_blocks( $notion_blocks );

	// Use in WordPress post.
	$post_id = wp_insert_post(
		array(
			'post_title'   => 'My First Post',
			'post_content' => $gutenberg_html,
			'post_status'  => 'publish',
			'post_type'    => 'post',
		)
	);

	echo "Created post ID: $post_id\n";
}

/**
 * Example 2: SyncManager Integration
 *
 * How SyncManager should use BlockConverter.
 */
class ExampleSyncManager {
	/**
	 * Block converter instance
	 *
	 * @var BlockConverter
	 */
	private BlockConverter $block_converter;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->block_converter = new BlockConverter();
	}

	/**
	 * Sync a Notion page to WordPress
	 *
	 * @param string $page_id Notion page ID.
	 * @return int WordPress post ID.
	 */
	public function sync_page( string $page_id ): int {
		// 1. Fetch page properties from Notion API.
		$page_properties = $this->fetch_page_properties( $page_id );

		// 2. Fetch page blocks from Notion API.
		$notion_blocks = $this->fetch_page_blocks( $page_id );

		// 3. Convert blocks to Gutenberg HTML.
		$post_content = $this->block_converter->convert_blocks( $notion_blocks );

		// 4. Create or update WordPress post.
		$post_id = wp_insert_post(
			array(
				'post_title'   => $page_properties['title'],
				'post_content' => $post_content,
				'post_status'  => 'publish',
				'meta_input'   => array(
					'notion_page_id' => $page_id,
					'last_synced'    => current_time( 'mysql' ),
				),
			)
		);

		return $post_id;
	}

	/**
	 * Mock method to fetch page properties
	 *
	 * @param string $page_id Page ID.
	 * @return array Page properties.
	 */
	private function fetch_page_properties( string $page_id ): array {
		// Implement with NotionClient.
		return array( 'title' => 'Example Title' );
	}

	/**
	 * Mock method to fetch page blocks
	 *
	 * @param string $page_id Page ID.
	 * @return array Notion blocks.
	 */
	private function fetch_page_blocks( string $page_id ): array {
		// Implement with NotionClient.
		return array();
	}
}

/**
 * Example 3: Custom Block Converter
 *
 * Register a custom converter for unsupported block types.
 */
class CustomCalloutConverter implements BlockConverterInterface {
	/**
	 * Check if this converter supports the block
	 *
	 * @param array $notion_block Notion block data.
	 * @return bool True if supported.
	 */
	public function supports( array $notion_block ): bool {
		return isset( $notion_block['type'] ) && 'callout' === $notion_block['type'];
	}

	/**
	 * Convert callout block to Gutenberg
	 *
	 * @param array $notion_block Notion block data.
	 * @return string Gutenberg HTML.
	 */
	public function convert( array $notion_block ): string {
		$icon      = $notion_block['callout']['icon']['emoji'] ?? '💡';
		$rich_text = $notion_block['callout']['rich_text'] ?? array();

		// Convert rich text (reuse logic from ParagraphConverter).
		$content = $this->convert_rich_text( $rich_text );

		// Output as Gutenberg paragraph with custom class.
		return sprintf(
			"<!-- wp:paragraph {\"className\":\"notion-callout\"} -->\n<p class=\"notion-callout\"><span class=\"callout-icon\">%s</span> %s</p>\n<!-- /wp:paragraph -->\n\n",
			esc_html( $icon ),
			$content
		);
	}

	/**
	 * Convert rich text to HTML
	 *
	 * @param array $rich_text_array Rich text array.
	 * @return string HTML content.
	 */
	private function convert_rich_text( array $rich_text_array ): string {
		$html = '';

		foreach ( $rich_text_array as $item ) {
			$content = $item['text']['content'] ?? '';
			$html   .= esc_html( $content );
		}

		return $html;
	}
}

/**
 * Register custom converter via WordPress filter
 */
function register_custom_converters(): void {
	add_filter(
		'notion_sync_block_converters',
		function ( array $converters ) {
			// Add custom callout converter.
			$converters[] = new CustomCalloutConverter();
			return $converters;
		}
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_custom_converters' );

/**
 * Example 4: Direct Registration
 *
 * Register converter directly without filter.
 */
function example_direct_registration(): void {
	$converter = new BlockConverter();

	// Register custom converter.
	$converter->register_converter( 'callout', new CustomCalloutConverter() );

	// Now callout blocks will be converted.
	$notion_blocks = array(
		array(
			'type'    => 'callout',
			'callout' => array(
				'icon'      => array( 'emoji' => '💡' ),
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array( 'content' => 'Important note!' ),
					),
				),
			),
		),
	);

	$result = $converter->convert_blocks( $notion_blocks );
	echo $result;
}

/**
 * Example 5: Handling Errors Gracefully
 *
 * Demonstrate error handling and logging.
 */
function example_error_handling(): void {
	$converter = new BlockConverter();

	// Mix of valid and invalid blocks.
	$notion_blocks = array(
		array(
			'type' => 'paragraph',
			'paragraph' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array( 'content' => 'Valid paragraph' ),
						'annotations' => array(
							'bold' => false,
							'italic' => false,
							'strikethrough' => false,
							'underline' => false,
							'code' => false,
						),
					),
				),
			),
		),
		array(
			'type' => 'unsupported_block_type',
			'id'   => '12345678-1234-1234-1234-123456789012',
		),
		array(
			'type' => 'heading_1',
			'heading_1' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array( 'content' => 'Valid heading' ),
						'annotations' => array(
							'bold' => false,
							'italic' => false,
							'strikethrough' => false,
							'underline' => false,
							'code' => false,
						),
					),
				),
			),
		),
	);

	// Convert - unsupported blocks will be logged and replaced with placeholders.
	$result = $converter->convert_blocks( $notion_blocks );

	// Check error_log for:
	// [NotionSync] Unsupported block type: unsupported_block_type (ID: 12345678-1234-1234-1234-123456789012).

	// Result will contain:
	// - Valid paragraph.
	// - HTML comment placeholder for unsupported block.
	// - Valid heading.

	echo $result;
}

/**
 * Example 6: Performance Testing
 *
 * Test conversion performance with large documents.
 */
function example_performance_test(): void {
	$converter = new BlockConverter();

	// Generate 1000 blocks.
	$notion_blocks = array();
	for ( $i = 0; $i < 1000; $i++ ) {
		$notion_blocks[] = array(
			'type' => 'paragraph',
			'paragraph' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array( 'content' => "Paragraph $i" ),
						'annotations' => array(
							'bold' => false,
							'italic' => false,
							'strikethrough' => false,
							'underline' => false,
							'code' => false,
						),
					),
				),
			),
		);
	}

	// Measure conversion time.
	$start_time = microtime( true );
	$result     = $converter->convert_blocks( $notion_blocks );
	$end_time   = microtime( true );

	$execution_time = ( $end_time - $start_time ) * 1000; // Convert to milliseconds.

	echo "Converted 1000 blocks in {$execution_time}ms\n";
	echo "Average time per block: " . ( $execution_time / 1000 ) . "ms\n";
}

/**
 * Example 7: Security Validation
 *
 * Demonstrate XSS prevention and HTML escaping.
 */
function example_security_validation(): void {
	$converter = new BlockConverter();

	// Attempt XSS injection.
	$malicious_blocks = array(
		array(
			'type' => 'paragraph',
			'paragraph' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array( 'content' => '<script>alert("XSS")</script>' ),
						'annotations' => array(
							'bold' => false,
							'italic' => false,
							'strikethrough' => false,
							'underline' => false,
							'code' => false,
						),
					),
				),
			),
		),
		array(
			'type' => 'paragraph',
			'paragraph' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'Click here',
							'link' => array( 'url' => 'javascript:alert("XSS")' ),
						),
						'annotations' => array(
							'bold' => false,
							'italic' => false,
							'strikethrough' => false,
							'underline' => false,
							'code' => false,
						),
					),
				),
			),
		),
	);

	$result = $converter->convert_blocks( $malicious_blocks );

	// Verify output is safe:
	// 1. Script tags are escaped: &lt;script&gt;
	// 2. JavaScript URLs are stripped.

	echo "Safe output:\n";
	echo $result;

	// Assertions for testing.
	assert( ! str_contains( $result, '<script>' ) );
	assert( str_contains( $result, '&lt;script&gt;' ) );
	assert( ! str_contains( $result, 'javascript:' ) );
}
