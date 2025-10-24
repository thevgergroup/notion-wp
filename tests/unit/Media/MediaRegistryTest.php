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

use NotionWP\Media\MediaRegistry;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Test MediaRegistry functionality
 */
class MediaRegistryTest extends TestCase {

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress database functions
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
	 * Test registering a new media entry
	 *
	 * Should store the mapping between Notion media ID and WordPress attachment ID.
	 */
	public function test_register_stores_media_mapping(): void {
		$notion_media_id = 'notion-image-123';
		$attachment_id   = 42;
		$source_url      = 'https://example.com/image.jpg';

		// Mock update_option to store registry
		Functions\expect( 'update_option' )
			->once()
			->with(
				'notion_wp_media_registry',
				\Mockery::on(
					function ( $registry ) use ( $notion_media_id, $attachment_id, $source_url ) {
						// Verify registry structure
						$this->assertIsArray( $registry );
						$this->assertArrayHasKey( $notion_media_id, $registry );

						$entry = $registry[ $notion_media_id ];
						$this->assertEquals( $attachment_id, $entry['attachment_id'] );
						$this->assertEquals( $source_url, $entry['source_url'] );
						$this->assertArrayHasKey( 'registered_at', $entry );

						return true;
					}
				)
			)
			->andReturn( true );

		// Mock get_option to return empty registry initially
		Functions\expect( 'get_option' )
			->once()
			->with( 'notion_wp_media_registry', array() )
			->andReturn( array() );

		// Execute registration
		MediaRegistry::register( $notion_media_id, $attachment_id, $source_url );
	}

	/**
	 * Test finding existing media in registry
	 *
	 * Should return attachment ID when media already registered.
	 */
	public function test_find_returns_existing_attachment_id(): void {
		$notion_media_id = 'notion-image-123';
		$attachment_id   = 42;

		// Mock get_option to return registry with existing entry
		Functions\expect( 'get_option' )
			->once()
			->with( 'notion_wp_media_registry', array() )
			->andReturn(
				array(
					$notion_media_id => array(
						'attachment_id' => $attachment_id,
						'source_url'    => 'https://example.com/image.jpg',
						'registered_at' => '2025-10-20 10:00:00',
					),
				)
			);

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
		$notion_media_id = 'nonexistent-media-id';

		// Mock get_option to return empty registry
		Functions\expect( 'get_option' )
			->once()
			->with( 'notion_wp_media_registry', array() )
			->andReturn( array() );

		// Execute find
		$result = MediaRegistry::find( $notion_media_id );

		// Assert null returned
		$this->assertNull( $result );
	}

	/**
	 * Test updating existing registry entry
	 *
	 * Should update attachment ID for existing Notion media ID.
	 */
	public function test_register_updates_existing_entry(): void {
		$notion_media_id     = 'notion-image-123';
		$old_attachment_id   = 42;
		$new_attachment_id   = 99;
		$source_url          = 'https://example.com/image.jpg';

		// Mock get_option to return registry with old entry
		Functions\expect( 'get_option' )
			->once()
			->andReturn(
				array(
					$notion_media_id => array(
						'attachment_id' => $old_attachment_id,
						'source_url'    => $source_url,
						'registered_at' => '2025-10-20 10:00:00',
					),
				)
			);

		// Mock update_option to verify update
		Functions\expect( 'update_option' )
			->once()
			->with(
				'notion_wp_media_registry',
				\Mockery::on(
					function ( $registry ) use ( $notion_media_id, $new_attachment_id ) {
						// Verify attachment ID was updated
						$this->assertEquals( $new_attachment_id, $registry[ $notion_media_id ]['attachment_id'] );
						return true;
					}
				)
			)
			->andReturn( true );

		// Execute registration with new attachment ID
		MediaRegistry::register( $notion_media_id, $new_attachment_id, $source_url );
	}

	/**
	 * Test clearing registry
	 *
	 * Should remove all entries from registry.
	 */
	public function test_clear_removes_all_entries(): void {
		// Mock delete_option
		Functions\expect( 'delete_option' )
			->once()
			->with( 'notion_wp_media_registry' )
			->andReturn( true );

		// Execute clear
		MediaRegistry::clear();
	}

	/**
	 * Test getting all registry entries
	 *
	 * Should return complete registry array.
	 */
	public function test_get_all_returns_complete_registry(): void {
		$registry = array(
			'media-1' => array(
				'attachment_id' => 42,
				'source_url'    => 'https://example.com/image1.jpg',
				'registered_at' => '2025-10-20 10:00:00',
			),
			'media-2' => array(
				'attachment_id' => 43,
				'source_url'    => 'https://example.com/image2.jpg',
				'registered_at' => '2025-10-20 10:05:00',
			),
		);

		// Mock get_option
		Functions\expect( 'get_option' )
			->once()
			->with( 'notion_wp_media_registry', array() )
			->andReturn( $registry );

		// Execute get all
		$result = MediaRegistry::get_all();

		// Assert complete registry returned
		$this->assertEquals( $registry, $result );
		$this->assertCount( 2, $result );
	}

	/**
	 * Test removing specific entry
	 *
	 * Should remove single entry from registry.
	 */
	public function test_remove_deletes_specific_entry(): void {
		$notion_media_id = 'media-to-remove';
		$other_media_id  = 'media-to-keep';

		// Mock get_option to return registry with two entries
		Functions\expect( 'get_option' )
			->once()
			->andReturn(
				array(
					$notion_media_id => array(
						'attachment_id' => 42,
						'source_url'    => 'https://example.com/remove.jpg',
						'registered_at' => '2025-10-20 10:00:00',
					),
					$other_media_id  => array(
						'attachment_id' => 43,
						'source_url'    => 'https://example.com/keep.jpg',
						'registered_at' => '2025-10-20 10:00:00',
					),
				)
			);

		// Mock update_option to verify removal
		Functions\expect( 'update_option' )
			->once()
			->with(
				'notion_wp_media_registry',
				\Mockery::on(
					function ( $registry ) use ( $notion_media_id, $other_media_id ) {
						// Verify removed entry is gone
						$this->assertArrayNotHasKey( $notion_media_id, $registry );
						// Verify other entry remains
						$this->assertArrayHasKey( $other_media_id, $registry );
						return true;
					}
				)
			)
			->andReturn( true );

		// Execute remove
		MediaRegistry::remove( $notion_media_id );
	}

	/**
	 * Test validating attachment still exists
	 *
	 * Should verify WordPress attachment still exists before returning it.
	 */
	public function test_find_validates_attachment_exists(): void {
		$notion_media_id = 'notion-image-123';
		$attachment_id   = 42;

		// Mock get_option to return registry
		Functions\expect( 'get_option' )
			->once()
			->andReturn(
				array(
					$notion_media_id => array(
						'attachment_id' => $attachment_id,
						'source_url'    => 'https://example.com/image.jpg',
						'registered_at' => '2025-10-20 10:00:00',
					),
				)
			);

		// Mock get_post to verify attachment exists
		Functions\expect( 'get_post' )
			->once()
			->with( $attachment_id )
			->andReturn( null ); // Attachment deleted

		// Execute find - should return null because attachment deleted
		$result = MediaRegistry::find( $notion_media_id );

		$this->assertNull( $result );
	}

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
	}
}
