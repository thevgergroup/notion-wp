<?php
/**
 * Tests for Paragraph Converter
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionWP\Tests\Unit\Blocks\Converters;

use NotionSync\Blocks\Converters\ParagraphConverter;

/**
 * Test ParagraphConverter functionality
 */
class ParagraphConverterTest extends BaseConverterTestCase {
	/**
	 * Converter instance
	 *
	 * @var ParagraphConverter
	 */
	private ParagraphConverter $converter;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->converter = new ParagraphConverter();
	}

	/**
	 * Test that converter supports paragraph blocks
	 */
	public function test_supports_paragraph_blocks(): void {
		$block = array( 'type' => 'paragraph' );
		$this->assertTrue( $this->converter->supports( $block ) );
	}

	/**
	 * Test that converter does not support non-paragraph blocks
	 */
	public function test_does_not_support_other_blocks(): void {
		$block = array( 'type' => 'heading_1' );
		$this->assertFalse( $this->converter->supports( $block ) );
	}

	/**
	 * Test conversion of simple paragraph
	 */
	public function test_convert_simple_paragraph(): void {
		$block = $this->load_fixture( 'paragraph-simple.json' );
		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<!-- wp:paragraph -->', $result );
		$this->assertStringContainsString( '<p>This is a simple paragraph with no formatting.</p>', $result );
		$this->assertStringContainsString( '<!-- /wp:paragraph -->', $result );
	}

	/**
	 * Test conversion of paragraph with bold, italic, and code formatting
	 */
	public function test_convert_formatted_paragraph(): void {
		$block = $this->load_fixture( 'paragraph-formatted.json' );
		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<strong>bold</strong>', $result );
		$this->assertStringContainsString( '<em>italic</em>', $result );
		$this->assertStringContainsString( '<code>code</code>', $result );
	}

	/**
	 * Test conversion of paragraph with link
	 */
	public function test_convert_paragraph_with_link(): void {
		$block = $this->load_fixture( 'paragraph-with-link.json' );
		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<a href="https://example.com">this link</a>', $result );
	}

	/**
	 * Test conversion of empty paragraph
	 */
	public function test_convert_empty_paragraph(): void {
		$block = array(
			'type' => 'paragraph',
			'paragraph' => array(
				'rich_text' => array(),
			),
		);

		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<p>&nbsp;</p>', $result );
	}

	/**
	 * Test HTML escaping of content
	 */
	public function test_escapes_html_content(): void {
		$block = array(
			'type' => 'paragraph',
			'paragraph' => array(
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
	 * Test URL escaping in links
	 */
	public function test_escapes_urls_in_links(): void {
		$block = array(
			'type' => 'paragraph',
			'paragraph' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'click here',
							'link' => array(
								'url' => 'javascript:alert("xss")',
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

		// WordPress esc_url() should strip dangerous protocols.
		$this->assertStringNotContainsString( 'javascript:', $result );
	}

	/**
	 * Test multiple annotations applied correctly
	 */
	public function test_multiple_annotations(): void {
		$block = array(
			'type' => 'paragraph',
			'paragraph' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'bold and italic',
						),
						'annotations' => array(
							'bold' => true,
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

		// Bold should wrap italic.
		$this->assertStringContainsString( '<strong><em>bold and italic</em></strong>', $result );
	}

	/**
	 * Test strikethrough and underline annotations
	 */
	public function test_strikethrough_and_underline(): void {
		$block = array(
			'type' => 'paragraph',
			'paragraph' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array(
							'content' => 'strikethrough',
						),
						'annotations' => array(
							'bold' => false,
							'italic' => false,
							'strikethrough' => true,
							'underline' => false,
							'code' => false,
						),
					),
					array(
						'type' => 'text',
						'text' => array(
							'content' => ' and underline',
						),
						'annotations' => array(
							'bold' => false,
							'italic' => false,
							'strikethrough' => false,
							'underline' => true,
							'code' => false,
						),
					),
				),
			),
		);

		$result = $this->converter->convert( $block );

		$this->assertStringContainsString( '<s>strikethrough</s>', $result );
		$this->assertStringContainsString( '<u> and underline</u>', $result );
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
