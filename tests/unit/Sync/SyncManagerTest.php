<?php
/**
 * Tests for Sync Manager
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Tests\Unit\Sync;

use NotionSync\Sync\SyncManager;
use NotionSync\Sync\ContentFetcher;
use NotionSync\Blocks\BlockConverter;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Test SyncManager functionality
 *
 * Tests the core sync orchestration logic including duplicate detection,
 * error handling, and WordPress post creation/updates.
 */
class SyncManagerTest extends TestCase {

	/**
	 * Mock content fetcher
	 *
	 * @var ContentFetcher|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_fetcher;

	/**
	 * Mock block converter
	 *
	 * @var BlockConverter|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_converter;

	/**
	 * SyncManager instance
	 *
	 * @var SyncManager
	 */
	private $sync_manager;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Create mocks for dependencies.
		$this->mock_fetcher   = $this->createMock( ContentFetcher::class );
		$this->mock_converter = $this->createMock( BlockConverter::class );

		// Create SyncManager with mocked dependencies.
		$this->sync_manager = new SyncManager( $this->mock_fetcher, $this->mock_converter );

		// Mock WordPress functions.
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
	 * Test that sync_page creates a new post successfully
	 *
	 * First sync of a Notion page should create a new WordPress post
	 * with correct content and meta data.
	 */
	public function test_sync_page_creates_new_post(): void {
		$notion_page_id = 'abc123def456';
		$expected_post_id = 42;

		// Mock page properties from Notion.
		$page_properties = array(
			'id'               => $notion_page_id,
			'title'            => 'Test Page Title',
			'last_edited_time' => '2025-10-20T10:00:00.000Z',
		);

		// Mock blocks from Notion.
		$notion_blocks = array(
			array( 'type' => 'paragraph', 'content' => 'Test content' ),
		);

		// Mock converted Gutenberg HTML.
		$gutenberg_html = "<!-- wp:paragraph -->\n<p>Test content</p>\n<!-- /wp:paragraph -->\n";

		// Configure fetcher mocks.
		$this->mock_fetcher
			->expects( $this->once() )
			->method( 'fetch_page_properties' )
			->with( $notion_page_id )
			->willReturn( $page_properties );

		$this->mock_fetcher
			->expects( $this->once() )
			->method( 'fetch_page_blocks' )
			->with( $notion_page_id )
			->willReturn( $notion_blocks );

		// Configure converter mock.
		$this->mock_converter
			->expects( $this->once() )
			->method( 'convert_blocks' )
			->with( $notion_blocks )
			->willReturn( $gutenberg_html );

		// Mock no existing post found (first sync).
		Functions\expect( 'get_posts' )
			->once()
			->andReturn( array() );

		// Mock wp_kses_post to sanitize content.
		Functions\expect( 'wp_kses_post' )
			->once()
			->with( $gutenberg_html )
			->andReturn( $gutenberg_html );

		// Mock sanitize_text_field for title.
		Functions\expect( 'sanitize_text_field' )
			->once()
			->with( 'Test Page Title' )
			->andReturn( 'Test Page Title' );

		// Mock current_time for timestamp.
		Functions\expect( 'current_time' )
			->times( 1 )
			->with( 'mysql' )
			->andReturn( '2025-10-20 10:00:00' );

		// Mock wp_insert_post to create new post.
		Functions\expect( 'wp_insert_post' )
			->once()
			->andReturn( $expected_post_id );

		// Mock update_post_meta calls.
		Functions\expect( 'update_post_meta' )
			->times( 3 )
			->andReturn( true );

		// Execute sync.
		$result = $this->sync_manager->sync_page( $notion_page_id );

		// Assert success.
		$this->assertTrue( $result['success'] );
		$this->assertEquals( $expected_post_id, $result['post_id'] );
		$this->assertNull( $result['error'] );
	}

	/**
	 * Test that sync_page updates an existing post
	 *
	 * Second sync of a Notion page should update the existing WordPress post
	 * rather than creating a duplicate.
	 */
	public function test_sync_page_updates_existing_post(): void {
		$notion_page_id = 'abc123def456';
		$existing_post_id = 42;

		// Mock page properties.
		$page_properties = array(
			'id'               => $notion_page_id,
			'title'            => 'Updated Title',
			'last_edited_time' => '2025-10-20T11:00:00.000Z',
		);

		// Mock blocks.
		$notion_blocks = array(
			array( 'type' => 'paragraph', 'content' => 'Updated content' ),
		);

		// Mock converted HTML.
		$gutenberg_html = "<!-- wp:paragraph -->\n<p>Updated content</p>\n<!-- /wp:paragraph -->\n";

		// Configure fetcher mocks.
		$this->mock_fetcher
			->expects( $this->once() )
			->method( 'fetch_page_properties' )
			->willReturn( $page_properties );

		$this->mock_fetcher
			->expects( $this->once() )
			->method( 'fetch_page_blocks' )
			->willReturn( $notion_blocks );

		// Configure converter mock.
		$this->mock_converter
			->expects( $this->once() )
			->method( 'convert_blocks' )
			->willReturn( $gutenberg_html );

		// Mock existing post found (duplicate detection).
		Functions\expect( 'get_posts' )
			->once()
			->andReturn( array( $existing_post_id ) );

		// Mock sanitization.
		Functions\expect( 'wp_kses_post' )
			->once()
			->andReturn( $gutenberg_html );

		Functions\expect( 'sanitize_text_field' )
			->once()
			->andReturn( 'Updated Title' );

		Functions\expect( 'current_time' )
			->once()
			->andReturn( '2025-10-20 11:00:00' );

		// Mock wp_update_post for existing post.
		Functions\expect( 'wp_update_post' )
			->once()
			->andReturn( $existing_post_id );

		// Mock update_post_meta calls.
		Functions\expect( 'update_post_meta' )
			->times( 3 )
			->andReturn( true );

		// Execute sync.
		$result = $this->sync_manager->sync_page( $notion_page_id );

		// Assert success with existing post ID.
		$this->assertTrue( $result['success'] );
		$this->assertEquals( $existing_post_id, $result['post_id'] );
		$this->assertNull( $result['error'] );
	}

	/**
	 * Test get_sync_status returns correct status for synced page
	 *
	 * When a page has been synced, get_sync_status should return
	 * is_synced=true with post ID and timestamp.
	 */
	public function test_get_sync_status_returns_correct_status(): void {
		$notion_page_id = 'abc123def456';
		$post_id = 42;
		$last_synced = '2025-10-20 10:00:00';

		// Mock finding existing post.
		Functions\expect( 'get_posts' )
			->once()
			->andReturn( array( $post_id ) );

		// Mock get_post_meta for last synced timestamp.
		Functions\expect( 'get_post_meta' )
			->once()
			->with( $post_id, 'notion_last_synced', true )
			->andReturn( $last_synced );

		// Execute get_sync_status.
		$status = $this->sync_manager->get_sync_status( $notion_page_id );

		// Assert status.
		$this->assertTrue( $status['is_synced'] );
		$this->assertEquals( $post_id, $status['post_id'] );
		$this->assertEquals( $last_synced, $status['last_synced'] );
	}

	/**
	 * Test sync_page handles fetch error gracefully
	 *
	 * When ContentFetcher fails to fetch page properties,
	 * should return error array without throwing exception.
	 */
	public function test_sync_page_handles_fetch_error(): void {
		$notion_page_id = 'abc123def456';

		// Mock fetcher returning empty array (error case).
		$this->mock_fetcher
			->expects( $this->once() )
			->method( 'fetch_page_properties' )
			->willReturn( array() );

		// Execute sync.
		$result = $this->sync_manager->sync_page( $notion_page_id );

		// Assert error result.
		$this->assertFalse( $result['success'] );
		$this->assertNull( $result['post_id'] );
		$this->assertNotNull( $result['error'] );
		$this->assertStringContainsString( 'Failed to fetch page properties', $result['error'] );
	}

	/**
	 * Test sync_page handles conversion error gracefully
	 *
	 * When BlockConverter throws exception during conversion,
	 * should return error array without propagating exception.
	 */
	public function test_sync_page_handles_conversion_error(): void {
		$notion_page_id = 'abc123def456';

		// Mock page properties.
		$page_properties = array(
			'id'               => $notion_page_id,
			'title'            => 'Test Page',
			'last_edited_time' => '2025-10-20T10:00:00.000Z',
		);

		// Mock blocks.
		$notion_blocks = array(
			array( 'type' => 'unsupported_block' ),
		);

		// Configure fetcher mocks.
		$this->mock_fetcher
			->expects( $this->once() )
			->method( 'fetch_page_properties' )
			->willReturn( $page_properties );

		$this->mock_fetcher
			->expects( $this->once() )
			->method( 'fetch_page_blocks' )
			->willReturn( $notion_blocks );

		// Mock converter throwing exception.
		$this->mock_converter
			->expects( $this->once() )
			->method( 'convert_blocks' )
			->willThrowException( new \Exception( 'Invalid block type' ) );

		// Mock no existing post.
		Functions\expect( 'get_posts' )
			->once()
			->andReturn( array() );

		// Execute sync.
		$result = $this->sync_manager->sync_page( $notion_page_id );

		// Assert error result.
		$this->assertFalse( $result['success'] );
		$this->assertNull( $result['post_id'] );
		$this->assertNotNull( $result['error'] );
		$this->assertStringContainsString( 'Block conversion failed', $result['error'] );
	}

	/**
	 * Test duplicate detection via post meta query
	 *
	 * Verifies that get_posts is called with correct meta_query
	 * to find existing posts by Notion page ID.
	 */
	public function test_duplicate_detection_via_post_meta(): void {
		$notion_page_id = 'abc-123-def-456'; // With dashes.
		$normalized_id = 'abc123def456';     // Normalized (without dashes).

		// Mock get_posts with meta_query verification.
		Functions\expect( 'get_posts' )
			->once()
			->with( \Mockery::on( function ( $args ) use ( $normalized_id ) {
				// Verify meta_query structure.
				if ( ! isset( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
					return false;
				}

				$meta_query = $args['meta_query'][0];

				// Verify meta key.
				if ( $meta_query['key'] !== 'notion_page_id' ) {
					return false;
				}

				// Verify meta value (should be normalized).
				if ( $meta_query['value'] !== $normalized_id ) {
					return false;
				}

				// Verify compare operator.
				if ( $meta_query['compare'] !== '=' ) {
					return false;
				}

				return true;
			} ) )
			->andReturn( array() );

		// Execute get_sync_status to trigger meta query.
		$this->sync_manager->get_sync_status( $notion_page_id );
	}

	/**
	 * Test invalid page ID validation
	 *
	 * Should return error for invalid page ID formats.
	 */
	public function test_sync_page_validates_page_id(): void {
		// Test empty page ID.
		$result = $this->sync_manager->sync_page( '' );
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'cannot be empty', $result['error'] );

		// Test page ID with invalid characters.
		$result = $this->sync_manager->sync_page( 'abc@123!def' );
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'invalid characters', $result['error'] );

		// Test page ID exceeding max length.
		$result = $this->sync_manager->sync_page( str_repeat( 'a', 100 ) );
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'maximum length', $result['error'] );
	}

	/**
	 * Test WordPress post creation failure handling
	 *
	 * Should return error when wp_insert_post returns WP_Error.
	 */
	public function test_sync_page_handles_post_creation_failure(): void {
		$notion_page_id = 'abc123def456';

		// Mock page properties.
		$page_properties = array(
			'id'               => $notion_page_id,
			'title'            => 'Test Page',
			'last_edited_time' => '2025-10-20T10:00:00.000Z',
		);

		// Mock blocks and conversion.
		$notion_blocks = array(
			array( 'type' => 'paragraph', 'content' => 'Test' ),
		);

		$gutenberg_html = "<!-- wp:paragraph -->\n<p>Test</p>\n<!-- /wp:paragraph -->\n";

		// Configure mocks.
		$this->mock_fetcher
			->method( 'fetch_page_properties' )
			->willReturn( $page_properties );

		$this->mock_fetcher
			->method( 'fetch_page_blocks' )
			->willReturn( $notion_blocks );

		$this->mock_converter
			->method( 'convert_blocks' )
			->willReturn( $gutenberg_html );

		Functions\expect( 'get_posts' )
			->once()
			->andReturn( array() );

		Functions\expect( 'wp_kses_post' )
			->once()
			->andReturn( $gutenberg_html );

		Functions\expect( 'sanitize_text_field' )
			->once()
			->andReturn( 'Test Page' );

		Functions\expect( 'current_time' )
			->once()
			->andReturn( '2025-10-20 10:00:00' );

		// Mock wp_insert_post returning WP_Error.
		$wp_error = $this->createMock( \WP_Error::class );
		$wp_error->method( 'get_error_message' )
			->willReturn( 'Database connection error' );

		Functions\expect( 'wp_insert_post' )
			->once()
			->andReturn( $wp_error );

		// Mock is_wp_error.
		Functions\expect( 'is_wp_error' )
			->once()
			->with( $wp_error )
			->andReturn( true );

		// Execute sync.
		$result = $this->sync_manager->sync_page( $notion_page_id );

		// Assert error result.
		$this->assertFalse( $result['success'] );
		$this->assertNull( $result['post_id'] );
		$this->assertStringContainsString( 'WordPress post creation failed', $result['error'] );
		$this->assertStringContainsString( 'Database connection error', $result['error'] );
	}

	/**
	 * Test get_sync_status for unsynced page
	 *
	 * Should return is_synced=false with null values.
	 */
	public function test_get_sync_status_for_unsynced_page(): void {
		$notion_page_id = 'abc123def456';

		// Mock no existing post found.
		Functions\expect( 'get_posts' )
			->once()
			->andReturn( array() );

		// Execute get_sync_status.
		$status = $this->sync_manager->get_sync_status( $notion_page_id );

		// Assert unsynced status.
		$this->assertFalse( $status['is_synced'] );
		$this->assertNull( $status['post_id'] );
		$this->assertNull( $status['last_synced'] );
	}

	/**
	 * Set up WordPress function mocks
	 *
	 * Creates default mocks for WordPress functions used by SyncManager.
	 */
	private function setup_wordpress_mocks(): void {
		// Mock WordPress functions with default behaviors.
		Functions\when( 'get_option' )->justReturn( 'encrypted_token_value' );
		Functions\when( 'get_posts' )->justReturn( array() );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'wp_insert_post' )->justReturn( 1 );
		Functions\when( 'wp_update_post' )->justReturn( 1 );
		Functions\when( 'current_time' )->justReturn( '2025-10-20 10:00:00' );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'esc_html' )->returnArg();
	}
}
