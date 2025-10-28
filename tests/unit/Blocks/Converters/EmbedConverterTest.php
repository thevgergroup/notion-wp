<?php
/**
 * Tests for Embed Block Converter
 *
 * Tests conversion of Notion embed blocks to WordPress oEmbed blocks.
 * Covers video embeds, bookmarks, PDFs, and generic embeds.
 *
 * @package NotionWP
 * @since 1.0.0
 */

namespace NotionWP\Tests\Unit\Blocks\Converters;

use NotionSync\Blocks\Converters\EmbedConverter;
use Brain\Monkey\Functions;

/**
 * Test EmbedConverter functionality
 */
class EmbedConverterTest extends BaseConverterTestCase {

	/**
	 * Embed converter instance
	 *
	 * @var EmbedConverter
	 */
	private EmbedConverter $converter;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->converter = new EmbedConverter();
	}

	/**
	 * Test supports method returns true for embed block types
	 */
	public function test_supports_embed_block_types(): void {
		$this->assertTrue( $this->converter->supports( array( 'type' => 'embed' ) ) );
		$this->assertTrue( $this->converter->supports( array( 'type' => 'video' ) ) );
		$this->assertTrue( $this->converter->supports( array( 'type' => 'bookmark' ) ) );
		$this->assertTrue( $this->converter->supports( array( 'type' => 'pdf' ) ) );
		$this->assertTrue( $this->converter->supports( array( 'type' => 'link_preview' ) ) );

		$this->assertFalse( $this->converter->supports( array( 'type' => 'paragraph' ) ) );
		$this->assertFalse( $this->converter->supports( array( 'type' => 'image' ) ) );
	}

	/**
	 * Test converting YouTube embed
	 */
	public function test_converts_youtube_embed(): void {
		$notion_block = array(
			'type'  => 'embed',
			'embed' => array(
				'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_attr' )->andReturnUsing( fn( $attr ) => $attr );

		$result = $this->converter->convert( $notion_block );

		// Should contain embed block wrapper
		$this->assertStringContainsString( '<!-- wp:embed', $result );
		$this->assertStringContainsString( '<!-- /wp:embed -->', $result );

		// Should identify YouTube as provider
		$this->assertStringContainsString( '"providerNameSlug":"youtube"', $result );
		$this->assertStringContainsString( 'is-provider-youtube', $result );

		// Should contain the URL
		$this->assertStringContainsString( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', $result );
	}

	/**
	 * Test converting YouTube short URL (youtu.be)
	 */
	public function test_converts_youtube_short_url(): void {
		$notion_block = array(
			'type'  => 'video',
			'video' => array(
				'url' => 'https://youtu.be/dQw4w9WgXcQ',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_attr' )->andReturnUsing( fn( $attr ) => $attr );

		$result = $this->converter->convert( $notion_block );

		// Should still detect as YouTube
		$this->assertStringContainsString( '"providerNameSlug":"youtube"', $result );
	}

	/**
	 * Test converting Vimeo embed
	 */
	public function test_converts_vimeo_embed(): void {
		$notion_block = array(
			'type'  => 'embed',
			'embed' => array(
				'url' => 'https://vimeo.com/123456789',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_attr' )->andReturnUsing( fn( $attr ) => $attr );

		$result = $this->converter->convert( $notion_block );

		// Should identify Vimeo as provider
		$this->assertStringContainsString( '"providerNameSlug":"vimeo"', $result );
		$this->assertStringContainsString( 'is-provider-vimeo', $result );
	}

	/**
	 * Test converting Twitter/X embed
	 */
	public function test_converts_twitter_embed(): void {
		$notion_block = array(
			'type'  => 'embed',
			'embed' => array(
				'url' => 'https://twitter.com/user/status/123456789',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_attr' )->andReturnUsing( fn( $attr ) => $attr );

		$result = $this->converter->convert( $notion_block );

		// Should identify Twitter as provider
		$this->assertStringContainsString( '"providerNameSlug":"twitter"', $result );
		$this->assertStringContainsString( 'is-provider-twitter', $result );
	}

	/**
	 * Test converting X.com (new Twitter domain) embed
	 */
	public function test_converts_x_com_embed(): void {
		$notion_block = array(
			'type'  => 'embed',
			'embed' => array(
				'url' => 'https://x.com/user/status/123456789',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_attr' )->andReturnUsing( fn( $attr ) => $attr );

		$result = $this->converter->convert( $notion_block );

		// Should still identify as Twitter provider
		$this->assertStringContainsString( '"providerNameSlug":"twitter"', $result );
	}

	/**
	 * Test converting Instagram embed
	 */
	public function test_converts_instagram_embed(): void {
		$notion_block = array(
			'type'  => 'embed',
			'embed' => array(
				'url' => 'https://www.instagram.com/p/ABC123/',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_attr' )->andReturnUsing( fn( $attr ) => $attr );

		$result = $this->converter->convert( $notion_block );

		// Should identify Instagram as provider
		$this->assertStringContainsString( '"providerNameSlug":"instagram"', $result );
		$this->assertStringContainsString( 'is-provider-instagram', $result );
	}

	/**
	 * Test converting Spotify embed
	 */
	public function test_converts_spotify_embed(): void {
		$notion_block = array(
			'type'  => 'embed',
			'embed' => array(
				'url' => 'https://open.spotify.com/track/abc123',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_attr' )->andReturnUsing( fn( $attr ) => $attr );

		$result = $this->converter->convert( $notion_block );

		// Should identify Spotify as provider
		$this->assertStringContainsString( '"providerNameSlug":"spotify"', $result );
	}

	/**
	 * Test converting SoundCloud embed
	 */
	public function test_converts_soundcloud_embed(): void {
		$notion_block = array(
			'type'  => 'embed',
			'embed' => array(
				'url' => 'https://soundcloud.com/artist/track',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_attr' )->andReturnUsing( fn( $attr ) => $attr );

		$result = $this->converter->convert( $notion_block );

		// Should identify SoundCloud as provider
		$this->assertStringContainsString( '"providerNameSlug":"soundcloud"', $result );
	}

	/**
	 * Test converting bookmark block
	 */
	public function test_converts_bookmark_to_link_preview(): void {
		$notion_block = array(
			'type'     => 'bookmark',
			'bookmark' => array(
				'url' => 'https://example.com/article/my-great-post',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_html' )->andReturnUsing( fn( $text ) => $text );

		$result = $this->converter->convert( $notion_block );

		// Should use HTML block for bookmark
		$this->assertStringContainsString( '<!-- wp:html -->', $result );

		// Should contain bookmark structure
		$this->assertStringContainsString( 'notion-bookmark', $result );
		$this->assertStringContainsString( 'notion-bookmark-link', $result );
		$this->assertStringContainsString( 'notion-bookmark-title', $result );
		$this->assertStringContainsString( 'notion-bookmark-url', $result );

		// Should contain the URL
		$this->assertStringContainsString( 'https://example.com/article/my-great-post', $result );

		// Should extract title from URL path
		$this->assertStringContainsString( 'My Great Post', $result );
	}

	/**
	 * Test converting PDF block
	 */
	public function test_converts_pdf_to_link_preview(): void {
		$notion_block = array(
			'type' => 'pdf',
			'pdf'  => array(
				'url' => 'https://example.com/documents/report.pdf',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_html' )->andReturnUsing( fn( $text ) => $text );

		$result = $this->converter->convert( $notion_block );

		// Should use link preview format
		$this->assertStringContainsString( 'notion-bookmark', $result );
		$this->assertStringContainsString( 'https://example.com/documents/report.pdf', $result );

		// Should extract title without extension
		$this->assertStringContainsString( 'Report', $result );
	}

	/**
	 * Test converting generic embed with unknown provider
	 */
	public function test_converts_generic_embed(): void {
		$notion_block = array(
			'type'  => 'embed',
			'embed' => array(
				'url' => 'https://unknown-service.com/embed/123',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );

		$result = $this->converter->convert( $notion_block );

		// Should use HTML block with iframe
		$this->assertStringContainsString( '<!-- wp:html -->', $result );
		$this->assertStringContainsString( '<iframe', $result );
		$this->assertStringContainsString( 'notion-embed', $result );

		// Should contain the URL in iframe src
		$this->assertStringContainsString( 'src="https://unknown-service.com/embed/123"', $result );
	}

	/**
	 * Test empty URL returns empty string
	 */
	public function test_empty_url_returns_empty_string(): void {
		$notion_block = array(
			'type'  => 'embed',
			'embed' => array(
				'url' => '',
			),
		);

		$result = $this->converter->convert( $notion_block );

		// Should return empty string
		$this->assertSame( '', $result );
	}

	/**
	 * Test missing URL returns empty string
	 */
	public function test_missing_url_returns_empty_string(): void {
		$notion_block = array(
			'type'  => 'embed',
			'embed' => array(),
		);

		$result = $this->converter->convert( $notion_block );

		// Should return empty string
		$this->assertSame( '', $result );
	}

	/**
	 * Test URL title extraction from path
	 */
	public function test_extracts_title_from_url_path(): void {
		$notion_block = array(
			'type'     => 'bookmark',
			'bookmark' => array(
				'url' => 'https://example.com/blog/hello-world-post',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_html' )->andReturnUsing( fn( $text ) => $text );

		$result = $this->converter->convert( $notion_block );

		// Should convert dashes to spaces and capitalize
		$this->assertStringContainsString( 'Hello World Post', $result );
	}

	/**
	 * Test URL title extraction with underscores
	 */
	public function test_extracts_title_with_underscores(): void {
		$notion_block = array(
			'type'     => 'bookmark',
			'bookmark' => array(
				'url' => 'https://example.com/my_awesome_page',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_html' )->andReturnUsing( fn( $text ) => $text );

		$result = $this->converter->convert( $notion_block );

		// Should convert underscores to spaces
		$this->assertStringContainsString( 'My Awesome Page', $result );
	}

	/**
	 * Test URL title extraction removes file extension
	 */
	public function test_removes_file_extension_from_title(): void {
		$notion_block = array(
			'type'     => 'bookmark',
			'bookmark' => array(
				'url' => 'https://example.com/document.pdf',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_html' )->andReturnUsing( fn( $text ) => $text );

		$result = $this->converter->convert( $notion_block );

		// Should remove .pdf extension
		$this->assertStringContainsString( 'Document', $result );
		$this->assertStringNotContainsString( 'Document.pdf', $result );
	}

	/**
	 * Test URL with only domain uses hostname as title
	 */
	public function test_uses_hostname_when_no_path(): void {
		$notion_block = array(
			'type'     => 'bookmark',
			'bookmark' => array(
				'url' => 'https://example.com',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_html' )->andReturnUsing( fn( $text ) => $text );

		$result = $this->converter->convert( $notion_block );

		// Should use hostname as title
		$this->assertStringContainsString( 'example.com', $result );
	}

	/**
	 * Test URL with root path uses hostname as title
	 */
	public function test_uses_hostname_when_root_path(): void {
		$notion_block = array(
			'type'     => 'bookmark',
			'bookmark' => array(
				'url' => 'https://example.com/',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_html' )->andReturnUsing( fn( $text ) => $text );

		$result = $this->converter->convert( $notion_block );

		// Should use hostname as title when path is just "/"
		$this->assertStringContainsString( 'example.com', $result );
	}

	/**
	 * Test video block with external URL
	 */
	public function test_converts_video_block_with_external_url(): void {
		$notion_block = array(
			'type'  => 'video',
			'video' => array(
				'url' => 'https://www.youtube.com/watch?v=abc123',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_attr' )->andReturnUsing( fn( $attr ) => $attr );

		$result = $this->converter->convert( $notion_block );

		// Should properly extract nested URL and convert to oEmbed
		$this->assertStringContainsString( '"providerNameSlug":"youtube"', $result );
		$this->assertStringContainsString( 'https://www.youtube.com/watch?v=abc123', $result );
	}

	/**
	 * Test provider detection handles www prefix
	 */
	public function test_provider_detection_handles_www_prefix(): void {
		$notion_block = array(
			'type'  => 'embed',
			'embed' => array(
				'url' => 'https://www.youtube.com/watch?v=test',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_attr' )->andReturnUsing( fn( $attr ) => $attr );

		$result = $this->converter->convert( $notion_block );

		// Should detect YouTube even with www prefix
		$this->assertStringContainsString( '"providerNameSlug":"youtube"', $result );
	}

	/**
	 * Test bookmark opens in new tab
	 */
	public function test_bookmark_opens_in_new_tab(): void {
		$notion_block = array(
			'type'     => 'bookmark',
			'bookmark' => array(
				'url' => 'https://example.com',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );
		Functions\expect( 'esc_html' )->andReturnUsing( fn( $text ) => $text );

		$result = $this->converter->convert( $notion_block );

		// Should have target="_blank" and rel="noopener noreferrer"
		$this->assertStringContainsString( 'target="_blank"', $result );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $result );
	}

	/**
	 * Test generic embed iframe has proper attributes
	 */
	public function test_generic_embed_iframe_attributes(): void {
		$notion_block = array(
			'type'  => 'embed',
			'embed' => array(
				'url' => 'https://unknown.com/widget',
			),
		);

		Functions\expect( 'esc_url' )->andReturnUsing( fn( $url ) => $url );

		$result = $this->converter->convert( $notion_block );

		// Should have proper iframe attributes
		$this->assertStringContainsString( 'width="100%"', $result );
		$this->assertStringContainsString( 'height="500"', $result );
		$this->assertStringContainsString( 'frameborder="0"', $result );
		$this->assertStringContainsString( 'allowfullscreen', $result );
	}
}
