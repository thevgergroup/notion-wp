<?php
/**
 * Tests for Block Converter Registry
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Tests\Unit\Blocks;

use NotionSync\Blocks\BlockConverter;
use NotionSync\Blocks\BlockConverterInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test BlockConverter registry functionality
 */
class BlockConverterTest extends TestCase {
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
	 * Test that default converters are registered
	 */
	public function test_default_converters_registered(): void {
		$converters = $this->converter->get_converters();

		$this->assertCount( 4, $converters );
		$this->assertContainsOnlyInstancesOf( BlockConverterInterface::class, $converters );
	}

	/**
	 * Test registering a custom converter
	 */
	public function test_register_custom_converter(): void {
		$custom_converter = $this->createMock( BlockConverterInterface::class );
		$this->converter->register_converter( 'custom', $custom_converter );

		$converters = $this->converter->get_converters();
		$this->assertCount( 5, $converters );
	}

	/**
	 * Test converting multiple blocks
	 */
	public function test_convert_multiple_blocks(): void {
		$blocks = array(
			array(
				'type' => 'paragraph',
				'paragraph' => array(
					'rich_text' => array(
						array(
							'type' => 'text',
							'text' => array( 'content' => 'First paragraph' ),
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
				'type' => 'heading_2',
				'heading_2' => array(
					'rich_text' => array(
						array(
							'type' => 'text',
							'text' => array( 'content' => 'My Heading' ),
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

		$result = $this->converter->convert_blocks( $blocks );

		$this->assertStringContainsString( '<!-- wp:paragraph -->', $result );
		$this->assertStringContainsString( 'First paragraph', $result );
		$this->assertStringContainsString( '<!-- wp:heading {"level":2} -->', $result );
		$this->assertStringContainsString( 'My Heading', $result );
	}

	/**
	 * Test converting empty blocks array
	 */
	public function test_convert_empty_blocks_array(): void {
		$result = $this->converter->convert_blocks( array() );
		$this->assertEmpty( $result );
	}

	/**
	 * Test handling unsupported block type
	 */
	public function test_unsupported_block_type(): void {
		$blocks = array(
			array(
				'type' => 'unsupported_type',
				'id' => '12345678-1234-1234-1234-123456789012',
			),
		);

		$result = $this->converter->convert_blocks( $blocks );

		// Should contain HTML comment placeholder.
		$this->assertStringContainsString( '<!-- Unsupported Notion block:', $result );
		$this->assertStringContainsString( 'unsupported_type', $result );
	}

	/**
	 * Test mixed supported and unsupported blocks
	 */
	public function test_mixed_block_types(): void {
		$blocks = array(
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
				'type' => 'unsupported',
				'id' => 'test-id',
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

		$result = $this->converter->convert_blocks( $blocks );

		// Should contain valid blocks.
		$this->assertStringContainsString( 'Valid paragraph', $result );
		$this->assertStringContainsString( 'Valid heading', $result );

		// Should contain placeholder for unsupported.
		$this->assertStringContainsString( '<!-- Unsupported Notion block:', $result );
		$this->assertStringContainsString( 'unsupported', $result );
	}

	/**
	 * Test converting list blocks
	 */
	public function test_convert_list_blocks(): void {
		$blocks = array(
			array(
				'type' => 'bulleted_list_item',
				'bulleted_list_item' => array(
					'rich_text' => array(
						array(
							'type' => 'text',
							'text' => array( 'content' => 'Bullet item' ),
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
				'type' => 'numbered_list_item',
				'numbered_list_item' => array(
					'rich_text' => array(
						array(
							'type' => 'text',
							'text' => array( 'content' => 'Numbered item' ),
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

		$result = $this->converter->convert_blocks( $blocks );

		$this->assertStringContainsString( '<ul><li>Bullet item</li></ul>', $result );
		$this->assertStringContainsString( '<ol><li>Numbered item</li></ol>', $result );
	}

	/**
	 * Test block with missing type field
	 */
	public function test_block_with_missing_type(): void {
		$blocks = array(
			array(
				'id' => '12345678-1234-1234-1234-123456789012',
				// Missing 'type' field.
			),
		);

		$result = $this->converter->convert_blocks( $blocks );

		// Should handle gracefully with placeholder.
		$this->assertStringContainsString( '<!-- Unsupported Notion block:', $result );
	}
}
