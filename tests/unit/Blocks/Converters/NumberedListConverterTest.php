<?php
/**
 * Tests for Numbered List Converter
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Tests\Unit\Blocks\Converters;

use NotionSync\Blocks\Converters\NumberedListConverter;

/**
 * Test NumberedListConverter functionality
 */
class NumberedListConverterTest extends BaseConverterTestCase {
	/**
	 * Converter instance
	 *
	 * @var NumberedListConverter
	 */
	private NumberedListConverter $converter;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->converter = new NumberedListConverter();
	}

	/**
	 * Test that converter supports numbered list blocks
	 */
	public function test_supports_numbered_list_blocks(): void {
		$block = array( 'type' => 'numbered_list_item' );
		$this->assertTrue( $this->converter->supports( $block ) );
	}

	/**
	 * Test that converter does not support other blocks
	 */
	public function test_does_not_support_other_blocks(): void {
		$this->assertFalse( $this->converter->supports( array( 'type' => 'paragraph' ) ) );
		$this->assertFalse( $this->converter->supports( array( 'type' => 'bulleted_list_item' ) ) );
	}

	/**
	 * Test conversion of simple numbered list item
	 */
	public function test_convert_simple_list_item(): void {
		$block = $this->load_fixture( 'numbered-list.json' );
		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<!-- wp:list {"ordered":true} -->', $result );
		$this->assertStringContainsString( '<ol><li>First numbered item</li></ol>', $result );
		$this->assertStringContainsString( '<!-- /wp:list -->', $result );
	}

	/**
	 * Test list item with formatting
	 */
	public function test_list_item_with_formatting(): void {
		$block = array(
			'type' => 'numbered_list_item',
			'numbered_list_item' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'Important step',
						),
						'annotations' => array(
							'bold' => true,
							'italic' => false,
							'strikethrough' => false,
							'underline' => false,
							'code' => false,
						),
					),
				),
			),
		);

		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<strong>Important step</strong>', $result );
	}

	/**
	 * Test empty list item
	 */
	public function test_convert_empty_list_item(): void {
		$block = array(
			'type' => 'numbered_list_item',
			'numbered_list_item' => array(
				'rich_text' => array(),
			),
		);

		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<li>&nbsp;</li>', $result );
	}

	/**
	 * Test list item with link
	 */
	public function test_list_item_with_link(): void {
		$block = array(
			'type' => 'numbered_list_item',
			'numbered_list_item' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'Visit website',
							'link' => array(
								'url' => 'https://example.com',
							),
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
		);

		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<a href="https://example.com">Visit website</a>', $result );
	}

	/**
	 * Test nested list items
	 */
	public function test_nested_list_items(): void {
		$block = array(
			'type' => 'numbered_list_item',
			'has_children' => true,
			'numbered_list_item' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'Step 1',
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
			'children' => array(
				array(
					'type' => 'numbered_list_item',
					'numbered_list_item' => array(
						'rich_text' => array(
							array(
								'type' => 'text',
								'text' => array(
									'content' => 'Sub-step 1.1',
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
			),
		);

		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( 'Step 1', $result );
		$this->assertStringContainsString( 'Sub-step 1.1', $result );
		$this->assertStringContainsString( '<ol><li>Sub-step 1.1</li></ol>', $result );
	}

	/**
	 * Test HTML escaping
	 */
	public function test_escapes_html_in_list_items(): void {
		$block = array(
			'type' => 'numbered_list_item',
			'numbered_list_item' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => '<script>alert("xss")</script>',
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
		);

		$result = $this->converter->convert( $block );

		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

	/**
	 * Test code annotation in list item
	 */
	public function test_code_in_list_item(): void {
		$block = array(
			'type' => 'numbered_list_item',
			'numbered_list_item' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'Run ',
						),
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
							'content' => 'npm install',
						),
						'annotations' => array(
							'bold' => false,
							'italic' => false,
							'strikethrough' => false,
							'underline' => false,
							'code' => true,
						),
					),
				),
			),
		);

		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<code>npm install</code>', $result );
	}

	/**
	 * Load test fixture from JSON file
	 *
	 * @param string $filename Fixture filename.
	 * @return array Parsed JSON data.
	 */
	private function load_fixture( string $filename ): array {
		$path = __DIR__ . '/../../../fixtures/notion-blocks/' . $filename;
		$json = file_get_contents( $path );
		return json_decode( $json, true );
	}
}
