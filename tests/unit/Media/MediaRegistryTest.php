<?php
/**
 * Tests for Media Registry
 *
 * Tests the media deduplication registry that prevents duplicate downloads
 * of the same Notion media on re-sync.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Tests\Unit\Media;

use NotionSync\Media\MediaRegistry;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * Test MediaRegistry functionality
 */
class MediaRegistryTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress database functions
		$this->setup_wordpress_mocks();

		// Mock global wpdb
		$this->setup_wpdb_mock();
	}

	/**
	 * Tear down test environment
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test registering a new media entry
	 *
	 * Should store the mapping between Notion media ID and WordPress attachment ID.
	 */
	public function test_register_stores_media_mapping(): void {
		global $wpdb;

		$notion_media_id = 'notion-image-123';
		$attachment_id   = 42;
		$source_url      = 'https://example.com/image.jpg';

		// Note: get_post is already mocked in BaseTestCase setup_wordpress_mocks()
		// to return a valid post object

		// Mock wpdb->insert to verify data is inserted
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				\Mockery::type( 'string' ), // table name
				\Mockery::on(
					function ( $data ) use ( $notion_media_id, $attachment_id, $source_url ) {
						$this->assertEquals( $notion_media_id, $data['notion_identifier'] );
						$this->assertEquals( $attachment_id, $data['attachment_id'] );
						$this->assertEquals( $source_url, $data['notion_file_url'] );
						$this->assertArrayHasKey( 'registered_at', $data );
						return true;
					}
				),
				\Mockery::type( 'array' ) // format array
			)
			->andReturn( 1 );

		// Execute registration
		$result = MediaRegistry::register( $notion_media_id, $attachment_id, $source_url );

		// Assert successful registration
		$this->assertTrue( $result );
	}

	/**
	 * Test finding existing media in registry
	 *
	 * Should return attachment ID when media already registered.
	 */
	public function test_find_returns_existing_attachment_id(): void {
		global $wpdb;

		$notion_media_id = 'notion-image-123';
		$attachment_id   = 42;

		// Mock wpdb->get_var to return the attachment ID
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( (string) $attachment_id );

		// Execute find
		$result = MediaRegistry::find( $notion_media_id );

		// Assert attachment ID returned
		$this->assertEquals( $attachment_id, $result );
	}

	/**
	 * Test finding non-existent media returns null
	 *
	 * Should return null when media not in registry.
	 */
	public function test_find_returns_null_when_not_found(): void {
		global $wpdb;

		$notion_media_id = 'nonexistent-media-id';

		// Mock wpdb->get_var to return null (not found)
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturnNull();

		// Execute find
		$result = MediaRegistry::find( $notion_media_id );

		// Assert null returned
		$this->assertNull( $result );
	}

	// NOTE: The following methods were removed as premature optimization:
	// - test_register_updates_existing_entry() - YAGNI: Update not needed, UNIQUE constraint prevents duplicates
	// - test_clear_removes_all_entries() - YAGNI: Clearing registry not needed in MVP
	// - test_get_all_returns_complete_registry() - YAGNI: Getting all entries not needed
	// - test_remove_deletes_specific_entry() - YAGNI: Removing entries not needed
	// - test_find_validates_attachment_exists() - YAGNI: Validation can be added if needed
	//
	// If these features are needed in the future, implement them THEN write tests.
	// Don't write tests for code that doesn't exist!

	/**
	 * Set up WordPress function mocks
	 *
	 * Creates default mocks for WordPress functions used by MediaRegistry.
	 */
	private function setup_wordpress_mocks(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );
		Functions\when( 'get_post' )->justReturn( (object) array( 'ID' => 42 ) );
		Functions\when( 'current_time' )->justReturn( '2025-10-25 10:00:00' );
	}

	/**
	 * Set up wpdb mock
	 *
	 * Creates a mock wpdb object and sets it as the global $wpdb.
	 */
	private function setup_wpdb_mock(): void {
		global $wpdb;

		// Create a mock wpdb object
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		// Mock get_var to return null by default (not found)
		$wpdb->shouldReceive( 'get_var' )
			->andReturnNull()
			->byDefault();

		// Mock prepare to just return the query
		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( $query ) {
					// Simple prepare mock - just return the query with placeholders replaced
					$args = func_get_args();
					array_shift( $args ); // Remove query
					foreach ( $args as $arg ) {
						$query = preg_replace( '/%[sdi]|%i/', "'" . $arg . "'", $query, 1 );
					}
					return $query;
				}
			);

		// Mock insert
		$wpdb->shouldReceive( 'insert' )
			->andReturn( 1 )
			->byDefault();

		// Mock delete
		$wpdb->shouldReceive( 'delete' )
			->andReturn( 1 )
			->byDefault();

		// Set insert_id
		$wpdb->insert_id = 1;
	}
}
