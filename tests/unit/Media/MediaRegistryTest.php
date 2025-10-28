<?php
/**
 * Tests for Media Registry
 *
 * Tests the media deduplication registry that prevents duplicate downloads
 * of the same Notion media on re-sync.
 *
 * @package NotionWP
 * @since 1.0.0
 */

namespace NotionWP\Tests\Unit\Media;

use NotionSync\Media\MediaRegistry;
use NotionWP\Tests\Unit\BaseTestCase;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Test MediaRegistry functionality
 */
class MediaRegistryTest extends BaseTestCase {

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp(); // BaseTestCase sets up Brain\Monkey and WordPress mocks

		// Mock global wpdb (provided by BaseTestCase)
		$this->setup_wpdb_mock();
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

		// Mock wp_cache_delete for cache invalidation
		Functions\expect( 'wp_cache_delete' )
			->once()
			->andReturn( true );

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

		// Mock wp_cache_get to return false (cache miss)
		Functions\expect( 'wp_cache_get' )
			->once()
			->andReturn( false );

		// Mock wp_cache_set to store result in cache
		Functions\expect( 'wp_cache_set' )
			->once()
			->andReturn( true );

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

		// Mock wp_cache_get to return false (cache miss)
		Functions\expect( 'wp_cache_get' )
			->once()
			->andReturn( false );

		// Mock wp_cache_set to store null result in cache
		Functions\expect( 'wp_cache_set' )
			->once()
			->andReturn( true );

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
}
