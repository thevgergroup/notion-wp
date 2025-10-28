<?php
/**
 * Tests for Heading Converter
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionWP\Tests\Unit\Blocks\Converters;

use NotionSync\Blocks\Converters\HeadingConverter;

/**
 * Test HeadingConverter functionality
 */
class HeadingConverterTest extends BaseConverterTestCase {
	/**
	 * Converter instance
	 *
	 * @var HeadingConverter
	 */
	private HeadingConverter $converter;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->converter = new HeadingConverter();
	}

	/**
	 * Test that converter supports heading blocks
	 */
	public function test_supports_heading_blocks(): void {
		$this->assertTrue( $this->converter->supports( array( 'type' => 'heading_1' ) ) );
		$this->assertTrue( $this->converter->supports( array( 'type' => 'heading_2' ) ) );
		$this->assertTrue( $this->converter->supports( array( 'type' => 'heading_3' ) ) );
	}

	/**
	 * Test that converter does not support non-heading blocks
	 */
	public function test_does_not_support_other_blocks(): void {
		$this->assertFalse( $this->converter->supports( array( 'type' => 'paragraph' ) ) );
		$this->assertFalse( $this->converter->supports( array( 'type' => 'bulleted_list_item' ) ) );
	}

	/**
	 * Test conversion of heading level 1
	 */
	public function test_convert_heading_1(): void {
		$block = $this->load_fixture( 'heading-1.json' );
		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<!-- wp:heading {"level":1} -->', $result );
		$this->assertStringContainsString( '<h1>Main Title</h1>', $result );
		$this->assertStringContainsString( '<!-- /wp:heading -->', $result );
	}

	/**
	 * Test conversion of heading level 2
	 */
	public function test_convert_heading_2(): void {
		$block = $this->load_fixture( 'heading-2.json' );
		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<!-- wp:heading {"level":2} -->', $result );
		$this->assertStringContainsString( '<h2>Section Heading</h2>', $result );
		$this->assertStringContainsString( '<!-- /wp:heading -->', $result );
	}

	/**
	 * Test conversion of heading level 3
	 */
	public function test_convert_heading_3(): void {
		$block = array(
			'type' => 'heading_3',
			'heading_3' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'Subsection',
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

		$this->assertStringContainsString( '<!-- wp:heading {"level":3} -->', $result );
		$this->assertStringContainsString( '<h3>Subsection</h3>', $result );
	}

	/**
	 * Test heading with formatting
	 */
	public function test_heading_with_formatting(): void {
		$block = array(
			'type' => 'heading_2',
			'heading_2' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'Important ',
						),
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
						'text' => array(
							'content' => 'Section',
						),
						'annotations' => array(
							'bold' => false,
							'italic' => true,
							'strikethrough' => false,
							'underline' => false,
							'code' => false,
						),
					),
				),
			),
		);

		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<strong>Important </strong>', $result );
		$this->assertStringContainsString( '<em>Section</em>', $result );
	}

	/**
	 * Test empty heading
	 */
	public function test_convert_empty_heading(): void {
		$block = array(
			'type' => 'heading_2',
			'heading_2' => array(
				'rich_text' => array(),
			),
		);

		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<h2>&nbsp;</h2>', $result );
	}

	/**
	 * Test heading with link
	 */
	public function test_heading_with_link(): void {
		$block = array(
			'type' => 'heading_2',
			'heading_2' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'Visit Site',
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

		$this->assertStringContainsString( '<a href="https://example.com">Visit Site</a>', $result );
	}

	/**
	 * Test HTML escaping in headings
	 */
	public function test_escapes_html_in_headings(): void {
		$block = array(
			'type' => 'heading_1',
			'heading_1' => array(
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
		$json = file_get_contents( $path );
		return json_decode( $json, true );
	}
}
