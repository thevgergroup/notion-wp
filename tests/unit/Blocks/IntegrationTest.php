<?php
/**
 * Integration tests for Block Conversion System
 *
 * Tests the complete conversion pipeline with real Notion data.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Tests\Unit\Blocks;

use NotionSync\Blocks\BlockConverter;
use PHPUnit\Framework\TestCase;

/**
 * Test complete block conversion pipeline
 */
class IntegrationTest extends TestCase {
	/**
	 * Converter instance
	 *
	 * @var BlockConverter
	 */
	private BlockConverter $converter;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->converter = new BlockConverter();
	}

	/**
	 * Test complete document conversion
	 *
	 * Converts a full document with mixed content types.
	 */
	public function test_convert_complete_document(): void {
		$blocks = array(
			$this->load_fixture( 'heading-1.json' ),
			$this->load_fixture( 'paragraph-simple.json' ),
			$this->load_fixture( 'heading-2.json' ),
			$this->load_fixture( 'paragraph-formatted.json' ),
			$this->load_fixture( 'bulleted-list.json' ),
			$this->load_fixture( 'numbered-list.json' ),
			$this->load_fixture( 'paragraph-with-link.json' ),
		);

		$result = $this->converter->convert_blocks( $blocks );

		// Verify all block types are present.
		$this->assertStringContainsString( '<!-- wp:heading {"level":1} -->', $result );
		$this->assertStringContainsString( '<h1>Main Title</h1>', $result );

		$this->assertStringContainsString( '<!-- wp:paragraph -->', $result );
		$this->assertStringContainsString( 'simple paragraph with no formatting', $result );

		$this->assertStringContainsString( '<!-- wp:heading {"level":2} -->', $result );
		$this->assertStringContainsString( '<h2>Section Heading</h2>', $result );

		$this->assertStringContainsString( '<strong>bold</strong>', $result );
		$this->assertStringContainsString( '<em>italic</em>', $result );
		$this->assertStringContainsString( '<code>code</code>', $result );

		$this->assertStringContainsString( '<!-- wp:list -->', $result );
		$this->assertStringContainsString( '<ul><li>First bullet point</li></ul>', $result );

		$this->assertStringContainsString( '<!-- wp:list {"ordered":true} -->', $result );
		$this->assertStringContainsString( '<ol><li>First numbered item</li></ol>', $result );

		$this->assertStringContainsString( '<a href="https://example.com">this link</a>', $result );
	}

	/**
	 * Test document structure preservation
	 *
	 * Ensures block order is maintained.
	 */
	public function test_block_order_preserved(): void {
		$blocks = array(
			$this->load_fixture( 'heading-1.json' ),
			$this->load_fixture( 'paragraph-simple.json' ),
			$this->load_fixture( 'heading-2.json' ),
		);

		$result = $this->converter->convert_blocks( $blocks );

		// Check that heading 1 appears before paragraph.
		$h1_pos = strpos( $result, '<h1>Main Title</h1>' );
		$p_pos  = strpos( $result, 'simple paragraph' );
		$h2_pos = strpos( $result, '<h2>Section Heading</h2>' );

		$this->assertLessThan( $p_pos, $h1_pos, 'H1 should appear before paragraph' );
		$this->assertLessThan( $h2_pos, $p_pos, 'Paragraph should appear before H2' );
	}

	/**
	 * Test round-trip fidelity
	 *
	 * Verifies that converting the same block multiple times produces
	 * identical output.
	 */
	public function test_round_trip_consistency(): void {
		$block = $this->load_fixture( 'paragraph-formatted.json' );

		$result1 = $this->converter->convert_blocks( array( $block ) );
		$result2 = $this->converter->convert_blocks( array( $block ) );

		$this->assertEquals( $result1, $result2, 'Multiple conversions should be identical' );
	}

	/**
	 * Test performance with large document
	 *
	 * Ensures converter can handle many blocks efficiently.
	 */
	public function test_large_document_performance(): void {
		// Create 100 blocks.
		$blocks = array();
		for ( $i = 0; $i < 100; $i++ ) {
			$blocks[] = array(
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

		$start_time = microtime( true );
		$result = $this->converter->convert_blocks( $blocks );
		$end_time = microtime( true );

		$execution_time = $end_time - $start_time;

		// Should complete in under 1 second.
		$this->assertLessThan( 1.0, $execution_time, 'Should convert 100 blocks in under 1 second' );

		// Verify all blocks were converted.
		$this->assertStringContainsString( 'Paragraph 0', $result );
		$this->assertStringContainsString( 'Paragraph 99', $result );
	}

	/**
	 * Test complex nested content
	 */
	public function test_complex_nested_content(): void {
		$block = array(
			'type' => 'paragraph',
			'paragraph' => array(
				'rich_text' => array(
					array(
						'type' => 'text',
						'text' => array( 'content' => 'This is ' ),
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
						'text' => array( 'content' => 'bold and italic' ),
						'annotations' => array(
							'bold' => true,
							'italic' => true,
							'strikethrough' => false,
							'underline' => false,
							'code' => false,
						),
					),
					array(
						'type' => 'text',
						'text' => array(
							'content' => ' with a link',
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
						'text' => array( 'content' => 'code' ),
						'annotations' => array(
							'bold' => false,
							'italic' => false,
							'strikethrough' => false,
							'underline' => false,
							'code' => true,
						),
					),
					array(
						'type' => 'text',
						'text' => array( 'content' => '.' ),
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

		$result = $this->converter->convert_blocks( array( $block ) );

		// Verify all formatting is present.
		$this->assertStringContainsString( '<strong><em>bold and italic</em></strong>', $result );
		$this->assertStringContainsString( '<a href="https://example.com"> with a link</a>', $result );
		$this->assertStringContainsString( '<code>code</code>', $result );
	}

	/**
	 * Load test fixture from JSON file
	 *
	 * @param string $filename Fixture filename.
	 * @return array Parsed JSON data.
	 */
	private function load_fixture( string $filename ): array {
		$path = __DIR__ . '/../../fixtures/notion-blocks/' . $filename;
		$json = file_get_contents( $path );
		return json_decode( $json, true );
	}
}
