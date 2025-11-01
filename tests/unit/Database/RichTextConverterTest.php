<?php
/**
 * Tests for RichTextConverter
 *
 * @package NotionSync
 */

namespace NotionSync\Tests\Unit\Database;

use NotionSync\Database\RichTextConverter;
use NotionWP\Tests\Unit\BaseTestCase;

/**
 * Class RichTextConverterTest
 *
 * @covers \NotionSync\Database\RichTextConverter
 */
class RichTextConverterTest extends BaseTestCase {

	/**
	 * Converter instance.
	 *
	 * @var RichTextConverter
	 */
	private $converter;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->converter = new RichTextConverter();

		// Add stub for sanitize_html_class used in color formatting
		\Brain\Monkey\Functions\stubs(
			array(
				'sanitize_html_class' => function ( $class ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $class ) );
				},
			)
		);
	}

	/**
	 * Test converting empty rich text array.
	 */
	public function test_to_html_empty_array(): void {
		$result = $this->converter->to_html( array() );
		$this->assertSame( '', $result );
	}

	/**
	 * Test converting simple plain text.
	 */
	public function test_to_html_plain_text(): void {
		$rich_text = array(
			array(
				'plain_text'  => 'Hello World',
				'annotations' => array(
					'bold'          => false,
					'italic'        => false,
					'strikethrough' => false,
					'underline'     => false,
					'code'          => false,
					'color'         => 'default',
				),
			),
		);


		$result = $this->converter->to_html( $rich_text );
		$this->assertSame( 'Hello World', $result );
	}

	/**
	 * Test bold annotation.
	 */
	public function test_to_html_bold_text(): void {
		$rich_text = array(
			array(
				'plain_text'  => 'Bold Text',
				'annotations' => array(
					'bold'          => true,
					'italic'        => false,
					'strikethrough' => false,
					'underline'     => false,
					'code'          => false,
					'color'         => 'default',
				),
			),
		);


		$result = $this->converter->to_html( $rich_text );
		$this->assertSame( '<strong>Bold Text</strong>', $result );
	}

	/**
	 * Test italic annotation.
	 */
	public function test_to_html_italic_text(): void {
		$rich_text = array(
			array(
				'plain_text'  => 'Italic Text',
				'annotations' => array(
					'bold'          => false,
					'italic'        => true,
					'strikethrough' => false,
					'underline'     => false,
					'code'          => false,
					'color'         => 'default',
				),
			),
		);


		$result = $this->converter->to_html( $rich_text );
		$this->assertSame( '<em>Italic Text</em>', $result );
	}

	/**
	 * Test strikethrough annotation.
	 */
	public function test_to_html_strikethrough_text(): void {
		$rich_text = array(
			array(
				'plain_text'  => 'Strikethrough Text',
				'annotations' => array(
					'bold'          => false,
					'italic'        => false,
					'strikethrough' => true,
					'underline'     => false,
					'code'          => false,
					'color'         => 'default',
				),
			),
		);


		$result = $this->converter->to_html( $rich_text );
		$this->assertSame( '<s>Strikethrough Text</s>', $result );
	}

	/**
	 * Test underline annotation.
	 */
	public function test_to_html_underline_text(): void {
		$rich_text = array(
			array(
				'plain_text'  => 'Underline Text',
				'annotations' => array(
					'bold'          => false,
					'italic'        => false,
					'strikethrough' => false,
					'underline'     => true,
					'code'          => false,
					'color'         => 'default',
				),
			),
		);


		$result = $this->converter->to_html( $rich_text );
		$this->assertSame( '<u>Underline Text</u>', $result );
	}

	/**
	 * Test code annotation.
	 */
	public function test_to_html_code_text(): void {
		$rich_text = array(
			array(
				'plain_text'  => 'Code Text',
				'annotations' => array(
					'bold'          => false,
					'italic'        => false,
					'strikethrough' => false,
					'underline'     => false,
					'code'          => true,
					'color'         => 'default',
				),
			),
		);


		$result = $this->converter->to_html( $rich_text );
		$this->assertSame( '<code>Code Text</code>', $result );
	}

	/**
	 * Test color annotation.
	 */
	public function test_to_html_colored_text(): void {
		$rich_text = array(
			array(
				'plain_text'  => 'Red Text',
				'annotations' => array(
					'bold'          => false,
					'italic'        => false,
					'strikethrough' => false,
					'underline'     => false,
					'code'          => false,
					'color'         => 'red',
				),
			),
		);




		$result = $this->converter->to_html( $rich_text );
		$this->assertSame( '<span class="notion-color-red">Red Text</span>', $result );
	}

	/**
	 * Test combined annotations (bold + italic).
	 */
	public function test_to_html_combined_annotations(): void {
		$rich_text = array(
			array(
				'plain_text'  => 'Bold and Italic',
				'annotations' => array(
					'bold'          => true,
					'italic'        => true,
					'strikethrough' => false,
					'underline'     => false,
					'code'          => false,
					'color'         => 'default',
				),
			),
		);


		$result = $this->converter->to_html( $rich_text );
		$this->assertSame( '<em><strong>Bold and Italic</strong></em>', $result );
	}

	/**
	 * Test link with text.
	 */
	public function test_to_html_link(): void {
		$rich_text = array(
			array(
				'plain_text'  => 'Click here',
				'href'        => 'https://example.com',
				'annotations' => array(
					'bold'          => false,
					'italic'        => false,
					'strikethrough' => false,
					'underline'     => false,
					'code'          => false,
					'color'         => 'default',
				),
			),
		);



		$result = $this->converter->to_html( $rich_text );
		$expected = '<a href="https://example.com" target="_blank" rel="noopener noreferrer">Click here</a>';
		$this->assertSame( $expected, $result );
	}

	/**
	 * Test multiple segments.
	 */
	public function test_to_html_multiple_segments(): void {
		$rich_text = array(
			array(
				'plain_text'  => 'Normal ',
				'annotations' => array(
					'bold'          => false,
					'italic'        => false,
					'strikethrough' => false,
					'underline'     => false,
					'code'          => false,
					'color'         => 'default',
				),
			),
			array(
				'plain_text'  => 'bold',
				'annotations' => array(
					'bold'          => true,
					'italic'        => false,
					'strikethrough' => false,
					'underline'     => false,
					'code'          => false,
					'color'         => 'default',
				),
			),
			array(
				'plain_text'  => ' text',
				'annotations' => array(
					'bold'          => false,
					'italic'        => false,
					'strikethrough' => false,
					'underline'     => false,
					'code'          => false,
					'color'         => 'default',
				),
			),
		);

		$result = $this->converter->to_html( $rich_text );
		$this->assertSame( 'Normal <strong>bold</strong> text', $result );
	}

	/**
	 * Test to_plain_text method.
	 */
	public function test_to_plain_text(): void {
		$rich_text = array(
			array(
				'plain_text'  => 'Bold ',
				'annotations' => array(
					'bold' => true,
				),
			),
			array(
				'plain_text'  => 'and ',
				'annotations' => array(),
			),
			array(
				'plain_text'  => 'italic',
				'annotations' => array(
					'italic' => true,
				),
			),
		);

		$result = $this->converter->to_plain_text( $rich_text );
		$this->assertSame( 'Bold and italic', $result );
	}

	/**
	 * Test to_plain_text with empty array.
	 */
	public function test_to_plain_text_empty(): void {
		$result = $this->converter->to_plain_text( array() );
		$this->assertSame( '', $result );
	}

	/**
	 * Test HTML escaping for malicious content.
	 */
	public function test_to_html_escapes_malicious_content(): void {
		$rich_text = array(
			array(
				'plain_text'  => '<script>alert("XSS")</script>',
				'annotations' => array(
					'bold'          => false,
					'italic'        => false,
					'strikethrough' => false,
					'underline'     => false,
					'code'          => false,
					'color'         => 'default',
				),
			),
		);


		$result = $this->converter->to_html( $rich_text );
		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

	/**
	 * Test alternative text structure (text.content instead of plain_text).
	 */
	public function test_to_html_alternative_structure(): void {
		$rich_text = array(
			array(
				'text'        => array(
					'content' => 'Alternative structure',
				),
				'annotations' => array(
					'bold'          => false,
					'italic'        => false,
					'strikethrough' => false,
					'underline'     => false,
					'code'          => false,
					'color'         => 'default',
				),
			),
		);


		$result = $this->converter->to_html( $rich_text );
		$this->assertSame( 'Alternative structure', $result );
	}

	/**
	 * Test link from alternative structure (text.link.url).
	 */
	public function test_to_html_link_alternative_structure(): void {
		$rich_text = array(
			array(
				'plain_text'  => 'Link text',
				'text'        => array(
					'content' => 'Link text',
					'link'    => array(
						'url' => 'https://example.com',
					),
				),
				'annotations' => array(
					'bold'          => false,
					'italic'        => false,
					'strikethrough' => false,
					'underline'     => false,
					'code'          => false,
					'color'         => 'default',
				),
			),
		);



		$result = $this->converter->to_html( $rich_text );
		$expected = '<a href="https://example.com" target="_blank" rel="noopener noreferrer">Link text</a>';
		$this->assertSame( $expected, $result );
	}
}
