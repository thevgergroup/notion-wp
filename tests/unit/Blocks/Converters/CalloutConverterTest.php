<?php
/**
 * Tests for Callout Converter
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Tests\Unit\Blocks\Converters;

use NotionSync\Blocks\Converters\CalloutConverter;

/**
 * Test CalloutConverter functionality
 */
class CalloutConverterTest extends BaseConverterTestCase {

	/**
	 * Converter instance
	 *
	 * @var CalloutConverter
	 */
	private CalloutConverter $converter;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->converter = new CalloutConverter();
	}

	/**
	 * Test supports method returns true for callout blocks
	 */
	public function test_supports_callout_block_type(): void {
		$block = array( 'type' => 'callout' );
		$this->assertTrue( $this->converter->supports( $block ) );

		$other_block = array( 'type' => 'paragraph' );
		$this->assertFalse( $this->converter->supports( $other_block ) );
	}

	/**
	 * Test basic callout conversion with emoji icon
	 */
	public function test_converts_simple_callout_with_emoji(): void {
		$notion_block = array(
			'type'    => 'callout',
			'callout' => array(
				'rich_text' => array(
					array(
						'plain_text'  => 'This is a callout message',
						'annotations' => array(
							'bold'   => false,
							'italic' => false,
							'code'   => false,
						),
					),
				),
				'icon'      => array(
					'type'  => 'emoji',
					'emoji' => '💡',
				),
				'color'     => 'gray_background',
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( '<!-- wp:html -->', $result );
		$this->assertStringContainsString( 'notion-callout', $result );
		$this->assertStringContainsString( 'notion-callout-gray', $result );
		$this->assertStringContainsString( '💡', $result );
		$this->assertStringContainsString( 'This is a callout message', $result );
	}

	/**
	 * Test callout with formatted rich text
	 */
	public function test_converts_callout_with_formatted_text(): void {
		$notion_block = array(
			'type'    => 'callout',
			'callout' => array(
				'rich_text' => array(
					array(
						'plain_text'  => 'Bold text',
						'annotations' => array(
							'bold'   => true,
							'italic' => false,
							'code'   => false,
						),
					),
					array(
						'plain_text'  => ' and ',
						'annotations' => array(
							'bold'   => false,
							'italic' => false,
							'code'   => false,
						),
					),
					array(
						'plain_text'  => 'italic text',
						'annotations' => array(
							'bold'   => false,
							'italic' => true,
							'code'   => false,
						),
					),
				),
				'icon'      => array(
					'type'  => 'emoji',
					'emoji' => '📌',
				),
				'color'     => 'blue_background',
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( '<strong>Bold text</strong>', $result );
		$this->assertStringContainsString( '<em>italic text</em>', $result );
		$this->assertStringContainsString( 'notion-callout-blue', $result );
	}

	/**
	 * Test callout with different color backgrounds
	 */
	public function test_converts_callout_with_various_colors(): void {
		$colors = array(
			'default'         => 'notion-callout-default',
			'gray_background' => 'notion-callout-gray',
			'orange_background' => 'notion-callout-orange',
			'yellow_background' => 'notion-callout-yellow',
			'green_background' => 'notion-callout-green',
			'blue_background' => 'notion-callout-blue',
			'purple_background' => 'notion-callout-purple',
			'pink_background' => 'notion-callout-pink',
			'red_background' => 'notion-callout-red',
		);

		foreach ( $colors as $notion_color => $expected_class ) {
			$notion_block = array(
				'type'    => 'callout',
				'callout' => array(
					'rich_text' => array(
						array(
							'plain_text'  => 'Test message',
							'annotations' => array(),
						),
					),
					'icon'      => array(
						'type'  => 'emoji',
						'emoji' => '⚠️',
					),
					'color'     => $notion_color,
				),
			);

			$result = $this->converter->convert( $notion_block );
			$this->assertStringContainsString( $expected_class, $result, "Failed for color: {$notion_color}" );
		}
	}

	/**
	 * Test callout with external icon URL
	 */
	public function test_converts_callout_with_external_icon(): void {
		$notion_block = array(
			'type'    => 'callout',
			'callout' => array(
				'rich_text' => array(
					array(
						'plain_text'  => 'Message with external icon',
						'annotations' => array(),
					),
				),
				'icon'      => array(
					'type'     => 'external',
					'external' => array(
						'url' => 'https://example.com/icon.png',
					),
				),
				'color'     => 'default',
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'https://example.com/icon.png', $result );
		$this->assertStringContainsString( 'notion-callout-icon', $result );
	}

	/**
	 * Test callout without icon
	 */
	public function test_converts_callout_without_icon(): void {
		$notion_block = array(
			'type'    => 'callout',
			'callout' => array(
				'rich_text' => array(
					array(
						'plain_text'  => 'Message without icon',
						'annotations' => array(),
					),
				),
				'icon'      => array(),
				'color'     => 'default',
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( 'Message without icon', $result );
		$this->assertStringNotContainsString( 'notion-callout-icon', $result );
	}

	/**
	 * Test empty callout returns empty string
	 */
	public function test_returns_empty_string_for_empty_callout(): void {
		$notion_block = array(
			'type'    => 'callout',
			'callout' => array(
				'rich_text' => array(),
				'icon'      => array(),
				'color'     => 'default',
			),
		);

		$result = $this->converter->convert( $notion_block );
		$this->assertEmpty( $result );
	}

	/**
	 * Test callout with code annotation
	 */
	public function test_converts_callout_with_code_annotation(): void {
		$notion_block = array(
			'type'    => 'callout',
			'callout' => array(
				'rich_text' => array(
					array(
						'plain_text'  => 'npm install',
						'annotations' => array(
							'code' => true,
						),
					),
				),
				'icon'      => array(
					'type'  => 'emoji',
					'emoji' => '💻',
				),
				'color'     => 'gray_background',
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( '<code>npm install</code>', $result );
	}

	/**
	 * Test callout with link
	 */
	public function test_converts_callout_with_link(): void {
		$notion_block = array(
			'type'    => 'callout',
			'callout' => array(
				'rich_text' => array(
					array(
						'plain_text'  => 'Visit our docs',
						'annotations' => array(),
						'href'        => 'https://example.com/docs',
					),
				),
				'icon'      => array(
					'type'  => 'emoji',
					'emoji' => '📚',
				),
				'color'     => 'blue_background',
			),
		);

		$result = $this->converter->convert( $notion_block );

		$this->assertStringContainsString( '<a href="https://example.com/docs">Visit our docs</a>', $result );
	}
}
