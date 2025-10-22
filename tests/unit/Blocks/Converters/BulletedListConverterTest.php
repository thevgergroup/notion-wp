<?php
/**
 * Tests for Bulleted List Converter
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Tests\Unit\Blocks\Converters;

use NotionSync\Blocks\Converters\BulletedListConverter;
use PHPUnit\Framework\TestCase;

/**
 * Test BulletedListConverter functionality
 */
class BulletedListConverterTest extends TestCase {
	/**
	 * Converter instance
	 *
	 * @var BulletedListConverter
	 */
	private BulletedListConverter $converter;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->converter = new BulletedListConverter();
	}

	/**
	 * Test that converter supports bulleted list blocks
	 */
	public function test_supports_bulleted_list_blocks(): void {
		$block = array( 'type' => 'bulleted_list_item' );
		$this->assertTrue( $this->converter->supports( $block ) );
	}

	/**
	 * Test that converter does not support other blocks
	 */
	public function test_does_not_support_other_blocks(): void {
		$this->assertFalse( $this->converter->supports( array( 'type' => 'paragraph' ) ) );
		$this->assertFalse( $this->converter->supports( array( 'type' => 'numbered_list_item' ) ) );
	}

	/**
	 * Test conversion of simple bulleted list item
	 */
	public function test_convert_simple_list_item(): void {
		$block = $this->load_fixture( 'bulleted-list.json' );
		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<!-- wp:list -->', $result );
		$this->assertStringContainsString( '<ul><li>First bullet point</li></ul>', $result );
		$this->assertStringContainsString( '<!-- /wp:list -->', $result );
	}

	/**
	 * Test list item with formatting
	 */
	public function test_list_item_with_formatting(): void {
		$block = array(
			'type' => 'bulleted_list_item',
			'bulleted_list_item' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'Bold item',
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

		$this->assertStringContainsString( '<strong>Bold item</strong>', $result );
	}

	/**
	 * Test empty list item
	 */
	public function test_convert_empty_list_item(): void {
		$block = array(
			'type' => 'bulleted_list_item',
			'bulleted_list_item' => array(
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
			'type' => 'bulleted_list_item',
			'bulleted_list_item' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'Check this',
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

		$this->assertStringContainsString( '<a href="https://example.com">Check this</a>', $result );
	}

	/**
	 * Test nested list items
	 */
	public function test_nested_list_items(): void {
		$block = array(
			'type' => 'bulleted_list_item',
			'has_children' => true,
			'bulleted_list_item' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'Parent item',
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
					'type' => 'bulleted_list_item',
					'bulleted_list_item' => array(
						'rich_text' => array(
							array(
								'type' => 'text',
								'text' => array(
									'content' => 'Child item',
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

		$this->assertStringContainsString( 'Parent item', $result );
		$this->assertStringContainsString( 'Child item', $result );
		$this->assertStringContainsString( '<ul><li>Child item</li></ul>', $result );
	}

	/**
	 * Test HTML escaping
	 */
	public function test_escapes_html_in_list_items(): void {
		$block = array(
			'type' => 'bulleted_list_item',
			'bulleted_list_item' => array(
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
	 * Load test fixture from JSON file
	 *
	 * @param string $filename Fixture filename.
	 * @return array Parsed JSON data.
	 */
	private function load_fixture( string $filename ): array {
		$path = __DIR__ . '/../../../fixtures/notion-blocks/' . $filename;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Loading local test fixture.
		$json = file_get_contents( $path );
		return json_decode( $json, true );
	}
}
