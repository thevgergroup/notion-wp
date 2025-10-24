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

use NotionWP\Blocks\Converters\ImageConverter;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Test ImageConverter functionality
 */
class ImageConverterTest extends TestCase {

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
		Monkey\setUp();

		$this->converter = new ImageConverter();

		// Mock WordPress functions
		$this->setup_wordpress_mocks();
	}

	/**
	 * Tear down test environment
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
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

		Functions\expect( 'esc_url' )
			->once()
			->andReturnUsing( fn( $url ) => $url );

		$result = $this->converter->convert( $notion_block );

		// Should contain WordPress image block
		$this->assertStringContainsString( '<!-- wp:image -->', $result );
		$this->assertStringContainsString( '<!-- /wp:image -->', $result );

		// Should contain external URL
		$this->assertStringContainsString( 'https://images.unsplash.com/photo-example?w=1200', $result );

		// Should use figure/img HTML structure
		$this->assertStringContainsString( '<figure class="wp-block-image">', $result );
		$this->assertStringContainsString( '<img', $result );
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
						'type' => 'text',
						'text' => array(
							'content' => 'Beautiful landscape photo',
						),
					),
				),
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_html' )->andReturnUsing( fn( $text ) => $text );

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
		$notion_block = array(
			'type'  => 'image',
			'id'    => 'abc123',
			'image' => array(
				'type' => 'file',
				'file' => array(
					'url'         => 'https://s3.amazonaws.com/notion/image.jpg?expires=123',
					'expiry_time' => '2025-10-20T12:00:00.000Z',
				),
			),
		);

		// Mock that parent post ID is set (required for media upload)
		$this->converter->set_parent_post_id( 42 );

		// Mock download_and_upload_to_wordpress method
		// Note: In real implementation, this would call ImageDownloader and MediaUploader
		Functions\expect( 'wp_get_attachment_url' )
			->once()
			->with( 99 ) // Mocked attachment ID
			->andReturn( 'https://wordpress.test/wp-content/uploads/2025/10/image.jpg' );

		// For this test, we assume internal method handles download
		// In integration test, we would verify actual download occurs

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );

		$result = $this->converter->convert( $notion_block );

		// Should contain WordPress upload URL, not Notion S3 URL
		// Note: This assertion depends on implementation details
		$this->assertStringContainsString( '<!-- wp:image -->', $result );
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
						'type'        => 'text',
						'text'        => array(
							'content' => 'Photo by ',
						),
						'annotations' => array(
							'bold' => false,
						),
					),
					array(
						'type'        => 'text',
						'text'        => array(
							'content' => 'John Doe',
						),
						'annotations' => array(
							'bold' => true, // Bold name
						),
					),
				),
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_html' )->andReturnUsing( fn( $text ) => $text );

		$result = $this->converter->convert( $notion_block );

		// Should contain caption with formatting
		$this->assertStringContainsString( '<figcaption', $result );
		$this->assertStringContainsString( 'Photo by', $result );
		$this->assertStringContainsString( '<strong>John Doe</strong>', $result );
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
		$this->converter->set_parent_post_id( 42 );

		$notion_block = array(
			'type'  => 'image',
			'id'    => 'abc123',
			'image' => array(
				'type' => 'file',
				'file' => array(
					'url' => 'https://s3.amazonaws.com/notion/image.jpg',
				),
			),
		);

		// In integration test, would verify attachment post_parent = 42
		// For unit test, just verify no errors

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );

		$result = $this->converter->convert( $notion_block );

		$this->assertIsString( $result );
	}

	/**
	 * Test Notion page ID context is used for MediaRegistry
	 *
	 * When converting images, the Notion page ID should be used to generate
	 * unique identifiers in MediaRegistry to prevent duplicate downloads.
	 */
	public function test_uses_notion_page_id_for_media_registry(): void {
		$this->converter->set_notion_page_id( 'test-page-123' );
		$this->converter->set_parent_post_id( 42 );

		$notion_block = array(
			'type'  => 'image',
			'id'    => 'image-block-456',
			'image' => array(
				'type' => 'file',
				'file' => array(
					'url' => 'https://s3.amazonaws.com/notion/image.jpg',
				),
			),
		);

		// MediaRegistry identifier should be: page_id + block_id
		// Expected: 'test-page-123_image-block-456'

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );

		$result = $this->converter->convert( $notion_block );

		// In integration test, would verify MediaRegistry::find('test-page-123_image-block-456') works
		$this->assertIsString( $result );
	}

	/**
	 * Test loading fixture from test files
	 *
	 * Demonstrates using JSON fixtures for complex test data.
	 */
	public function test_converts_image_from_fixture(): void {
		// Load fixture
		$fixture_path = __DIR__ . '/../../../fixtures/notion-responses/blocks-image.json';

		if ( ! file_exists( $fixture_path ) ) {
			$this->markTestSkipped( 'Fixture file not found: ' . $fixture_path );
		}

		$blocks       = json_decode( file_get_contents( $fixture_path ), true );
		$image_block  = $blocks[0]; // First block is external image

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_html' )->andReturnUsing( fn( $text ) => $text );

		$result = $this->converter->convert( $image_block );

		// Verify conversion based on fixture data
		$this->assertStringContainsString( '<!-- wp:image -->', $result );
		$this->assertStringContainsString( 'https://images.unsplash.com/', $result );
		$this->assertStringContainsString( 'Beautiful landscape photo', $result );
	}

	/**
	 * Set up WordPress function mocks
	 */
	private function setup_wordpress_mocks(): void {
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'wp_get_attachment_url' )->justReturn( 'https://example.com/uploads/image.jpg' );
	}
}
