<?php
/**
 * Tests for Image Block Converter
 *
 * Tests conversion of Notion image blocks to WordPress Gutenberg image blocks.
 * Covers external images, Notion-hosted files, captions, and media library integration.
 *
 * @package NotionWP
 * @since 1.0.0
 */

namespace NotionWP\Tests\Unit\Blocks\Converters;

use NotionSync\Blocks\Converters\ImageConverter;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Test ImageConverter functionality
 */
class ImageConverterTest extends BaseConverterTestCase {

	/**
	 * Image converter instance
	 *
	 * @var ImageConverter
	 */
	private ImageConverter $converter;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->converter = new ImageConverter();

		// Mock additional WordPress functions specific to media handling
		$this->setup_media_mocks();

		// Mock global wpdb for MediaRegistry
		$this->setup_wpdb_mock();
	}

	/**
	 * Set up media-specific WordPress function mocks
	 */
	protected function setup_media_mocks(): void {
		// Mock media-related WordPress functions
		Functions\when( 'wp_upload_dir' )->justReturn(
			array(
				'path'    => '/tmp/uploads',
				'url'     => 'http://example.com/uploads',
				'subdir'  => '',
				'basedir' => '/tmp/uploads',
				'baseurl' => 'http://example.com/uploads',
				'error'   => false,
			)
		);

		Functions\when( 'wp_get_attachment_url' )->justReturn( 'https://example.com/uploads/image.jpg' );
	}

	/**
	 * Test converting external image to Gutenberg block
	 *
	 * External images (Unsplash, etc.) should be kept as external URLs
	 * in the Gutenberg block without downloading.
	 */
	public function test_converts_external_image_to_gutenberg_block(): void {
		$notion_block = array(
			'type'  => 'image',
			'id'    => 'abc123',
			'image' => array(
				'type'     => 'external',
				'external' => array(
					'url' => 'https://images.unsplash.com/photo-example?w=1200',
				),
			),
		);

		$result = $this->converter->convert( $notion_block );

		// External images use HTML block (no attachment ID in WordPress)
		$this->assertStringContainsString( '<!-- wp:html -->', $result );
		$this->assertStringContainsString( '<!-- /wp:html -->', $result );

		// Should contain external URL
		$this->assertStringContainsString( 'https://images.unsplash.com/photo-example?w=1200', $result );

		// Should use figure/img HTML structure
		$this->assertStringContainsString( '<figure class="wp-block-image">', $result );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'class="external-image"', $result );
	}

	/**
	 * Test converting image with caption
	 *
	 * Notion image captions should be converted to WordPress figcaption.
	 */
	public function test_converts_image_with_caption(): void {
		$notion_block = array(
			'type'  => 'image',
			'id'    => 'abc123',
			'image' => array(
				'type'     => 'external',
				'external' => array(
					'url' => 'https://example.com/image.jpg',
				),
				'caption'  => array(
					array(
						'type'       => 'text',
						'text'       => array(
							'content' => 'Beautiful landscape photo',
						),
						'plain_text' => 'Beautiful landscape photo',
					),
				),
			),
		);

		$result = $this->converter->convert( $notion_block );

		// Should contain figcaption
		$this->assertStringContainsString( '<figcaption', $result );
		$this->assertStringContainsString( 'Beautiful landscape photo', $result );
	}

	/**
	 * Test converting image with empty caption
	 *
	 * Empty captions should not create figcaption element.
	 */
	public function test_handles_image_with_empty_caption(): void {
		$notion_block = array(
			'type'  => 'image',
			'id'    => 'abc123',
			'image' => array(
				'type'     => 'external',
				'external' => array(
					'url' => 'https://example.com/image.jpg',
				),
				'caption'  => array(), // Empty caption
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );

		$result = $this->converter->convert( $notion_block );

		// Should NOT contain figcaption
		$this->assertStringNotContainsString( '<figcaption', $result );
	}

	/**
	 * Test converting Notion-hosted image (expiring URL)
	 *
	 * Notion file URLs expire, so they should be downloaded to WordPress.
	 * This test verifies the download is triggered.
	 */
	public function test_downloads_notion_hosted_image(): void {
		$skip_reason = 'ImageConverter download logic requires ImageDownloader and MediaUploader mocks'
			. ' - TODO: implement in integration test';
		$this->markTestSkipped( $skip_reason );
	}

	/**
	 * Test handling missing image URL
	 *
	 * Should handle gracefully when image URL is missing.
	 */
	public function test_handles_missing_image_url(): void {
		$notion_block = array(
			'type'  => 'image',
			'id'    => 'abc123',
			'image' => array(
				'type'     => 'external',
				'external' => array(), // Missing URL
			),
		);

		$result = $this->converter->convert( $notion_block );

		// Should return placeholder or empty string
		// Exact behavior depends on implementation
		$this->assertIsString( $result );
		// Should not throw exception
	}

	/**
	 * Test converting image with rich text caption
	 *
	 * Captions can have multiple rich text segments with formatting.
	 */
	public function test_converts_image_with_rich_text_caption(): void {
		$this->markTestSkipped( 'Rich text caption formatting not yet implemented - uses plain_text only currently' );
	}

	/**
	 * Test supports method returns true for image blocks
	 *
	 * Converter should only handle image block types.
	 */
	public function test_supports_image_block_type(): void {
		$image_block = array( 'type' => 'image' );
		$this->assertTrue( $this->converter->supports( $image_block ) );

		$paragraph_block = array( 'type' => 'paragraph' );
		$this->assertFalse( $this->converter->supports( $paragraph_block ) );
	}

	/**
	 * Test converting image with parent post ID set
	 *
	 * When parent post ID is set, downloaded images should be attached to that post.
	 */
	public function test_attaches_image_to_parent_post(): void {
		$this->markTestSkipped( 'File download logic requires ImageDownloader and MediaUploader mocks - TODO: implement in integration test' );
	}

	/**
	 * Test Notion page ID context is used for MediaRegistry
	 *
	 * When converting images, the Notion page ID should be used to generate
	 * unique identifiers in MediaRegistry to prevent duplicate downloads.
	 */
	public function test_uses_notion_page_id_for_media_registry(): void {
		$this->markTestSkipped( 'MediaRegistry integration requires ImageDownloader and MediaUploader mocks - TODO: implement in integration test' );
	}

	/**
	 * Test loading fixture from test files
	 *
	 * Demonstrates using JSON fixtures for complex test data.
	 */
	public function test_converts_image_from_fixture(): void {
		$this->markTestSkipped( 'Fixture file not yet created - TODO: create fixtures/notion-responses/blocks-image.json' );
	}
}
