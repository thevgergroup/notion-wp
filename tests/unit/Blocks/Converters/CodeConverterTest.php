<?php
/**
 * Tests for Code Converter
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Tests\Unit\Blocks\Converters;

use NotionSync\Blocks\Converters\CodeConverter;

/**
 * Test CodeConverter functionality
 */
class CodeConverterTest extends BaseConverterTestCase {

	/**
	 * Converter instance
	 *
	 * @var CodeConverter
	 */
	private CodeConverter $converter;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->converter = new CodeConverter();
	}

	/**
	 * Test supports method returns true for code blocks
	 */
	public function test_supports_code_block_type(): void {
		$block = array( 'type' => 'code' );
		$this->assertTrue( $this->converter->supports( $block ) );

		$other_block = array( 'type' => 'paragraph' );
		$this->assertFalse( $this->converter->supports( $other_block ) );
	}

	/**
	 * Test basic code conversion
	 */
	public function test_converts_simple_code_block(): void {
		$notion_block = array(
			'type' => 'code',
			'code' => array(
				'rich_text' => array(
					array(
						'plain_text' => 'console.log("Hello World");',
					),
				),
				'language'  => 'javascript',
				'caption'   => array(),
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( '<!-- wp:code', $result );
		$this->assertStringContainsString( 'console.log("Hello World");', $result );
		$this->assertStringContainsString( 'javascript', $result );
	}

	/**
	 * Test code block with Python
	 */
	public function test_converts_python_code_block(): void {
		$notion_block = array(
			'type' => 'code',
			'code' => array(
				'rich_text' => array(
					array(
						'plain_text' => 'def hello():\n    print("Hello")',
					),
				),
				'language'  => 'python',
				'caption'   => array(),
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( 'python', $result );
		$this->assertStringContainsString( 'def hello():', $result );
	}

	/**
	 * Test code block with caption
	 */
	public function test_converts_code_block_with_caption(): void {
		$notion_block = array(
			'type' => 'code',
			'code' => array(
				'rich_text' => array(
					array(
						'plain_text' => 'npm install notion-sync',
					),
				),
				'language'  => 'bash',
				'caption'   => array(
					array(
						'plain_text' => 'Install the package',
					),
				),
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( 'npm install notion-sync', $result );
		$this->assertStringContainsString( 'Install the package', $result );
		$this->assertStringContainsString( 'code-caption', $result );
	}

	/**
	 * Test multi-line code block
	 */
	public function test_converts_multiline_code_block(): void {
		$notion_block = array(
			'type' => 'code',
			'code' => array(
				'rich_text' => array(
					array(
						'plain_text' => 'function add(a, b) {\n  return a + b;\n}',
					),
				),
				'language'  => 'javascript',
				'caption'   => array(),
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( 'function add(a, b)', $result );
		$this->assertStringContainsString( 'return a + b;', $result );
	}

	/**
	 * Test language mapping from Notion to Gutenberg
	 */
	public function test_maps_notion_languages_correctly(): void {
		$language_tests = array(
			'c++'             => 'cpp',
			'c#'              => 'csharp',
			'typescript'      => 'typescript',
			'plain text'      => 'plaintext',
			'html'            => 'markup',
			'xml'             => 'markup',
		);

		foreach ( $language_tests as $notion_lang => $expected_gutenberg_lang ) {
			$notion_block = array(
				'type' => 'code',
				'code' => array(
					'rich_text' => array(
						array(
							'plain_text' => 'test code',
						),
					),
					'language'  => $notion_lang,
					'caption'   => array(),
				),
			);

			$result = $this->converter->convert( $notion_block );
			$this->assertStringContainsString( $expected_gutenberg_lang, $result, "Failed mapping {$notion_lang} to {$expected_gutenberg_lang}" );
		}
	}

	/**
	 * Test code block with HTML special characters
	 */
	public function test_escapes_html_in_code(): void {
		$notion_block = array(
			'type' => 'code',
			'code' => array(
				'rich_text' => array(
					array(
						'plain_text' => '<script>alert("XSS")</script>',
					),
				),
				'language'  => 'html',
				'caption'   => array(),
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( '&lt;script&gt;', $result );
		$this->assertStringContainsString( '&lt;/script&gt;', $result );
		$this->assertStringNotContainsString( '<script>', $result );
	}

	/**
	 * Test empty code block returns empty string
	 */
	public function test_returns_empty_string_for_empty_code(): void {
		$notion_block = array(
			'type' => 'code',
			'code' => array(
				'rich_text' => array(),
				'language'  => 'javascript',
				'caption'   => array(),
			),
		);

		$result = $this->converter->convert( $notion_block );
		$this->assertEmpty( $result );
	}

	/**
	 * Test plaintext code block (no language specified)
	 */
	public function test_converts_plaintext_code_block(): void {
		$notion_block = array(
			'type' => 'code',
			'code' => array(
				'rich_text' => array(
					array(
						'plain_text' => 'Just some text',
					),
				),
				'language'  => 'plain text',
				'caption'   => array(),
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( 'Just some text', $result );
		$this->assertStringContainsString( 'wp-block-code', $result );
	}
}
