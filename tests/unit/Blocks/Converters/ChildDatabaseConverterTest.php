<?php
/**
 * Tests for ChildDatabaseConverter
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Tests\Unit\Blocks\Converters;

use NotionSync\Blocks\Converters\ChildDatabaseConverter;
use NotionSync\Database\DatabasePostType;
use NotionSync\Router\LinkRegistry;
use NotionWP\Tests\Unit\Blocks\Converters\BaseConverterTestCase;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Test ChildDatabaseConverter
 */
class ChildDatabaseConverterTest extends BaseConverterTestCase {

	/**
	 * Converter instance
	 *
	 * @var ChildDatabaseConverter
	 */
	private ChildDatabaseConverter $converter;

	/**
	 * Set up test case
	 */
	public function setUp(): void {
		parent::setUp();
		$this->converter = new ChildDatabaseConverter();
	}

	/**
	 * Test converter supports child_database blocks
	 */
	public function test_supports_child_database_blocks(): void {
		$block = array(
			'type' => 'child_database',
			'id'   => '12345678-1234-1234-1234-123456789abc',
		);

		$this->assertTrue( $this->converter->supports( $block ) );
	}

	/**
	 * Test converter does not support other block types
	 */
	public function test_does_not_support_other_blocks(): void {
		$block = array(
			'type' => 'paragraph',
		);

		$this->assertFalse( $this->converter->supports( $block ) );
	}

	/**
	 * Test convert creates database-view block when database is synced
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_convert_creates_database_view_block_for_synced_database(): void {
		// Mock $wpdb global (needed because @preserveGlobalState disabled)
		global $wpdb;
		$wpdb         = Mockery::mock( '\wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->postmeta = 'wp_postmeta';
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			function ( $query, ...$args ) {
				return vsprintf( str_replace( '%s', "'%s'", str_replace( '%d', '%d', $query ) ), $args );
			}
		);
		$wpdb->shouldReceive( 'get_row' )->andReturn( null );
		// Mock get_var to return a post ID for the database lookup
		$wpdb->shouldReceive( 'get_var' )->andReturn( 6 );

		// Mock WordPress functions needed for Notion API client
		Functions\when( 'get_option' )->justReturn( 'encrypted_token_value' );

		// Mock the Encryption class
		$mock_encryption = Mockery::mock( 'overload:NotionSync\Security\Encryption' );
		$mock_encryption->shouldReceive( 'decrypt' )->andReturn( 'decrypted_token_value' );

		// Mock the NotionClient
		$mock_client = Mockery::mock( 'overload:NotionSync\API\NotionClient' );
		$mock_client->shouldReceive( 'load_page_chunk' )->andReturn(
			array(
				'recordMap' => array(
					'block' => array(
						'12345678-1234-1234-1234-12345678abc' => array(
							'value' => array(
								'view_ids' => array( 'view-id-1' ),
							),
						),
					),
					'collection_view' => array(
						'view-id-1' => array(
							'value' => array(
								'format' => array(
									'collection_pointer' => array(
										'id' => 'collection-id-1',
									),
								),
							),
						),
					),
				),
			)
		);

		$notion_block = array(
			'type'           => 'child_database',
			'id'             => '12345678-1234-1234-1234-12345678abc',
			'child_database' => array(
				'title' => 'Test Database',
			),
		);

		// Mock WordPress functions (esc_attr and esc_html are already mocked in BaseTestCase)
		// Mock error_log to prevent output during tests.
		Functions\when( 'error_log' )->justReturn( null );

		$result = $this->converter->convert( $notion_block );

		// Should create database-view block with correct attributes.
		$this->assertStringContainsString( 'wp:notion-wp/database-view', $result );
		$this->assertStringContainsString( '"databaseId":6', $result );
		$this->assertStringContainsString( '"viewType":"table"', $result );

		// Should NOT contain notion-link block.
		$this->assertStringNotContainsString( 'notion-sync/notion-link', $result );
	}

	/**
	 * Test convert creates notion-link block when database is not synced
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_convert_creates_notion_link_block_for_unsynced_database(): void {
		// Mock $wpdb global (needed because @preserveGlobalState disabled)
		global $wpdb;
		$wpdb         = Mockery::mock( '\wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->postmeta = 'wp_postmeta';
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			function ( $query, ...$args ) {
				return vsprintf( str_replace( '%s', "'%s'", str_replace( '%d', '%d', $query ) ), $args );
			}
		);
		$wpdb->shouldReceive( 'get_row' )->andReturn( null );
		$wpdb->shouldReceive( 'get_var' )->andReturn( null );
		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );
		$wpdb->shouldReceive( 'update' )->andReturn( 1 );
		$wpdb->insertid = 1;

		// Mock WordPress functions needed for operation
		Functions\when( 'get_option' )->justReturn( null ); // No API token, so find_parent_database returns null
		Functions\when( 'current_time' )->justReturn( '2024-01-01 00:00:00' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'error_log' )->justReturn( null );

		$notion_block = array(
			'type'           => 'child_database',
			'id'             => '12345678-1234-1234-1234-12345678abc',
			'child_database' => array(
				'title' => 'Test Database',
			),
		);

		$result = $this->converter->convert( $notion_block );

		// Should create notion-link block.
		$this->assertStringContainsString( 'wp:notion-sync/notion-link', $result );
		$this->assertStringContainsString( '"notionId":"1234567812341234123412345678abc"', $result );
		$this->assertStringContainsString( '"showIcon":true', $result );
		$this->assertStringContainsString( '"openInNewTab":true', $result );

		// Should NOT contain database-view block.
		$this->assertStringNotContainsString( 'notion-wp/database-view', $result );
	}

	/**
	 * Test convert handles missing database ID
	 */
	public function test_convert_handles_missing_database_id(): void {
		$notion_block = array(
			'type'           => 'child_database',
			'child_database' => array(
				'title' => 'Test Database',
			),
		);

		// esc_html is already mocked in BaseTestCase

		$result = $this->converter->convert( $notion_block );

		// Should create a paragraph with database title.
		$this->assertStringContainsString( 'wp:paragraph', $result );
		$this->assertStringContainsString( 'Test Database', $result );
		$this->assertStringContainsString( '📊', $result );
	}

	/**
	 * Test convert handles missing title
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_convert_handles_missing_title(): void {
		// Mock $wpdb global (needed because @preserveGlobalState disabled)
		global $wpdb;
		$wpdb         = Mockery::mock( '\wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->postmeta = 'wp_postmeta';
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			function ( $query, ...$args ) {
				return vsprintf( str_replace( '%s', "'%s'", str_replace( '%d', '%d', $query ) ), $args );
			}
		);
		$wpdb->shouldReceive( 'get_row' )->andReturn( null );
		$wpdb->shouldReceive( 'get_var' )->andReturn( null );
		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );
		$wpdb->shouldReceive( 'update' )->andReturn( 1 );
		$wpdb->insertid = 1;

		// Mock WordPress functions needed for operation
		Functions\when( 'get_option' )->justReturn( null ); // No API token, so find_parent_database returns null
		Functions\when( 'current_time' )->justReturn( '2024-01-01 00:00:00' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'error_log' )->justReturn( null );

		$notion_block = array(
			'type'           => 'child_database',
			'id'             => '12345678-1234-1234-1234-12345678abc',
			'child_database' => array(),
		);

		$result = $this->converter->convert( $notion_block );

		// Should still create a notion-link block with default title.
		$this->assertStringContainsString( 'wp:notion-sync/notion-link', $result );
	}

	/**
	 * Test convert normalizes database ID by removing dashes
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_convert_normalizes_database_id(): void {
		// Mock $wpdb global (needed because @preserveGlobalState disabled)
		global $wpdb;
		$wpdb         = Mockery::mock( '\wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->postmeta = 'wp_postmeta';
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			function ( $query, ...$args ) {
				return vsprintf( str_replace( '%s', "'%s'", str_replace( '%d', '%d', $query ) ), $args );
			}
		);
		$wpdb->shouldReceive( 'get_row' )->andReturn( null );
		$wpdb->shouldReceive( 'get_var' )->andReturn( null );
		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );
		$wpdb->shouldReceive( 'update' )->andReturn( 1 );
		$wpdb->insertid = 1;

		// Mock WordPress functions needed for operation
		Functions\when( 'get_option' )->justReturn( null ); // No API token, so find_parent_database returns null
		Functions\when( 'current_time' )->justReturn( '2024-01-01 00:00:00' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'error_log' )->justReturn( null );

		$notion_block = array(
			'type'           => 'child_database',
			'id'             => '12345678-1234-1234-1234-12345678abc', // With dashes.
			'child_database' => array(
				'title' => 'Test Database',
			),
		);

		$result = $this->converter->convert( $notion_block );

		// Verify the output contains the normalized ID (without dashes).
		$this->assertStringContainsString( '1234567812341234123412345678abc', $result );
		// And does NOT contain the ID with dashes.
		$this->assertStringNotContainsString( '12345678-1234-1234-1234-12345678abc', $result );
	}
}
