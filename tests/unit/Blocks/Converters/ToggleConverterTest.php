<?php
/**
 * Tests for Toggle Converter
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Tests\Unit\Blocks\Converters;

use NotionSync\Blocks\Converters\ToggleConverter;

/**
 * Test ToggleConverter functionality
 */
class ToggleConverterTest extends BaseConverterTestCase {

	/**
	 * Converter instance
	 *
	 * @var ToggleConverter
	 */
	private ToggleConverter $converter;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->converter = new ToggleConverter();
	}

	/**
	 * Test supports method returns true for toggle blocks
	 */
	public function test_supports_toggle_block_type(): void {
		$block = array( 'type' => 'toggle' );
		$this->assertTrue( $this->converter->supports( $block ) );

		$other_block = array( 'type' => 'paragraph' );
		$this->assertFalse( $this->converter->supports( $other_block ) );
	}

	/**
	 * Test basic toggle conversion
	 */
	public function test_converts_simple_toggle(): void {
		$notion_block = array(
			'type'   => 'toggle',
			'toggle' => array(
				'rich_text' => array(
					array(
						'plain_text'  => 'Click to expand',
						'annotations' => array(
							'bold'   => false,
							'italic' => false,
						),
					),
				),
				'color'     => 'default',
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( '<details', $result );
		$this->assertStringContainsString( '<summary>', $result );
		$this->assertStringContainsString( 'Click to expand', $result );
		$this->assertStringContainsString( 'notion-toggle', $result );
	}

	/**
	 * Test toggle with formatted text
	 */
	public function test_converts_toggle_with_formatted_text(): void {
		$notion_block = array(
			'type'   => 'toggle',
			'toggle' => array(
				'rich_text' => array(
					array(
						'plain_text'  => 'Important',
						'annotations' => array(
							'bold' => true,
						),
					),
					array(
						'plain_text'  => ' information',
						'annotations' => array(
							'bold' => false,
						),
					),
				),
				'color'     => 'blue',
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( '<strong>Important</strong>', $result );
		$this->assertStringContainsString( 'information', $result );
		$this->assertStringContainsString( 'notion-toggle-blue', $result );
	}

	/**
	 * Test toggle with different colors
	 */
	public function test_converts_toggle_with_various_colors(): void {
		$colors = array(
			'default'           => 'notion-toggle-default',
			'gray'              => 'notion-toggle-gray',
			'orange_background' => 'notion-toggle-orange',
			'yellow_background' => 'notion-toggle-yellow',
			'green_background'  => 'notion-toggle-green',
			'blue_background'   => 'notion-toggle-blue',
		);

		foreach ( $colors as $notion_color => $expected_class ) {
			$notion_block = array(
				'type'   => 'toggle',
				'toggle' => array(
					'rich_text' => array(
						array(
							'plain_text' => 'Toggle title',
						),
					),
					'color'     => $notion_color,
				),
			);

			$result = $this->converter->convert( $notion_block );
			$this->assertStringContainsString( $expected_class, $result, "Failed for color: {$notion_color}" );
		}
	}

	/**
	 * Test toggle with code annotation
	 */
	public function test_converts_toggle_with_code_annotation(): void {
		$notion_block = array(
			'type'   => 'toggle',
			'toggle' => array(
				'rich_text' => array(
					array(
						'plain_text'  => 'Technical details',
						'annotations' => array(
							'code' => true,
						),
					),
				),
				'color'     => 'gray',
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( '<code>Technical details</code>', $result );
	}

	/**
	 * Test toggle with link
	 */
	public function test_converts_toggle_with_link(): void {
		$notion_block = array(
			'type'   => 'toggle',
			'toggle' => array(
				'rich_text' => array(
					array(
						'plain_text'  => 'Read more',
						'annotations' => array(),
						'href'        => 'https://example.com',
					),
				),
				'color'     => 'default',
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( '<a href="https://example.com">Read more</a>', $result );
	}

	/**
	 * Test empty toggle returns empty string
	 */
	public function test_returns_empty_string_for_empty_toggle(): void {
		$notion_block = array(
			'type'   => 'toggle',
			'toggle' => array(
				'rich_text' => array(),
				'color'     => 'default',
			),
		);

		$result = $this->converter->convert( $notion_block );
		$this->assertEmpty( $result );
	}

	/**
	 * Test toggle contains content div for children
	 */
	public function test_toggle_includes_content_div_for_children(): void {
		$notion_block = array(
			'type'   => 'toggle',
			'toggle' => array(
				'rich_text' => array(
					array(
						'plain_text' => 'Expandable section',
					),
				),
				'color'     => 'default',
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( 'notion-toggle-content', $result );
		$this->assertStringContainsString( '</details>', $result );
	}
}
