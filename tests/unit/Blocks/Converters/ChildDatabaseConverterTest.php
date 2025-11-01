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
		// Mock the DatabasePostType to return a post ID.
		$mock_db_post_type = Mockery::mock( 'overload:' . DatabasePostType::class );
		$mock_db_post_type->shouldReceive( 'find_by_notion_id' )
			->with( '1234567812341234123412345678abc' )
			->andReturn( 6 );

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
		$this->assertStringContainsString( '"showFilters":true', $result );
		$this->assertStringContainsString( '"showExport":true', $result );

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
		// Mock the DatabasePostType to return null (not found).
		$mock_db_post_type = Mockery::mock( 'overload:' . DatabasePostType::class );
		$mock_db_post_type->shouldReceive( 'find_by_notion_id' )
			->with( '1234567812341234123412345678abc' )
			->andReturn( null );

		// Mock LinkRegistry.
		$mock_registry = Mockery::mock( 'overload:' . LinkRegistry::class );
		$mock_registry->shouldReceive( 'find_by_notion_id' )
			->with( '1234567812341234123412345678abc' )
			->andReturn( null );
		$mock_registry->shouldReceive( 'register' )
			->with(
				Mockery::on(
					function ( $args ) {
						return '1234567812341234123412345678abc' === $args['notion_id']
							&& 'Test Database' === $args['notion_title']
							&& 'database' === $args['notion_type'];
					}
				)
			)
			->andReturn( true );

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
		// Mock the DatabasePostType to return null.
		$mock_db_post_type = Mockery::mock( 'overload:' . DatabasePostType::class );
		$mock_db_post_type->shouldReceive( 'find_by_notion_id' )
			->andReturn( null );

		// Mock LinkRegistry.
		$mock_registry = Mockery::mock( 'overload:' . LinkRegistry::class );
		$mock_registry->shouldReceive( 'find_by_notion_id' )->andReturn( null );
		$mock_registry->shouldReceive( 'register' )->andReturn( true );

		$notion_block = array(
			'type'           => 'child_database',
			'id'             => '12345678-1234-1234-1234-12345678abc',
			'child_database' => array(),
		);

		// Mock WordPress functions (esc_attr and esc_html are already mocked in BaseTestCase)
		// Mock error_log.
		Functions\when( 'error_log' )->justReturn( null );

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
		// Mock the DatabasePostType.
		$mock_db_post_type = Mockery::mock( 'overload:' . DatabasePostType::class );

		// The important assertion: should be called with normalized ID (no dashes).
		$mock_db_post_type->shouldReceive( 'find_by_notion_id' )
			->once()
			->with( '1234567812341234123412345678abc' )
			->andReturn( null );

		// Mock LinkRegistry.
		$mock_registry = Mockery::mock( 'overload:' . LinkRegistry::class );
		$mock_registry->shouldReceive( 'find_by_notion_id' )->andReturn( null );
		$mock_registry->shouldReceive( 'register' )->andReturn( true );

		$notion_block = array(
			'type'           => 'child_database',
			'id'             => '12345678-1234-1234-1234-12345678abc', // With dashes.
			'child_database' => array(
				'title' => 'Test Database',
			),
		);

		// Mock WordPress functions (esc_attr and esc_html are already mocked in BaseTestCase)
		Functions\when( 'error_log' )->justReturn( null );

		$this->converter->convert( $notion_block );

		// Mockery will verify that find_by_notion_id was called with normalized ID.
	}
}
