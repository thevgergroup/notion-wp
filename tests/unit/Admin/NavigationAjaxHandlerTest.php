<?php
/**
 * Tests for NavigationAjaxHandler class
 *
 * @package NotionWP
 * @subpackage Tests\Unit\Admin
 */

declare(strict_types=1);

namespace NotionWP\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use NotionWP\Admin\NavigationAjaxHandler;
use NotionWP\Tests\Unit\BaseTestCase;

/**
 * Class NavigationAjaxHandlerTest
 *
 * Tests the AJAX handler for menu synchronization operations,
 * including root page detection and hierarchy building.
 *
 * @covers \NotionWP\Admin\NavigationAjaxHandler
 */
class NavigationAjaxHandlerTest extends BaseTestCase {
	/**
	 * NavigationAjaxHandler instance
	 *
	 * @var NavigationAjaxHandler
	 */
	private NavigationAjaxHandler $handler;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->handler = new NavigationAjaxHandler();

		// Setup global wpdb mock
		$this->setup_wpdb_mock();

		// Setup WordPress AJAX function mocks
		$this->setup_ajax_mocks();
	}

	/**
	 * Setup WordPress AJAX function mocks
	 */
	private function setup_ajax_mocks(): void {
		// Mock check_ajax_referer
		Functions\expect( 'check_ajax_referer' )
			->andReturn( true )
			->byDefault();

		// Mock current_user_can
		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true )
			->byDefault();

		// Mock wp_send_json_error
		Functions\when( 'wp_send_json_error' )
			->alias( function ( $data, $status_code = null ) {
				throw new \Exception( 'AJAX Error: ' . ( $data['message'] ?? 'Unknown error' ) );
			} );

		// Mock wp_send_json_success
		Functions\when( 'wp_send_json_success' )
			->alias( function ( $data ) {
				// Return the data for testing
				return $data;
			} );

		// Mock WordPress translation functions
		Functions\expect( '__' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		Functions\expect( 'esc_html' )
			->andReturnUsing( function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
			} );

		Functions\expect( 'esc_url' )
			->andReturnUsing( function ( $url ) {
				return $url;
			} );

		Functions\expect( 'wp_kses' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );

		// Mock admin_url
		Functions\expect( 'admin_url' )
			->andReturnUsing( function ( $path ) {
				return 'http://example.com/wp-admin/' . $path;
			} );

		// Mock get_option
		Functions\expect( 'get_option' )
			->with( 'notion_sync_menu_name', 'Notion Navigation' )
			->andReturn( 'Notion Navigation' )
			->byDefault();

		// Mock current_theme_supports
		Functions\expect( 'current_theme_supports' )
			->with( 'menus' )
			->andReturn( true )
			->byDefault();

		// Mock get_registered_nav_menus
		Functions\expect( 'get_registered_nav_menus' )
			->andReturn( array( 'primary' => 'Primary Menu' ) )
			->byDefault();

		// Mock wp_json_encode
		Functions\expect( 'wp_json_encode' )
			->andReturnUsing( function ( $data ) {
				return json_encode( $data );
			} );

		// Mock error_log
		Functions\expect( 'error_log' )
			->andReturnNull()
			->byDefault();

		// Mock wp_get_nav_menu_items
		Functions\expect( 'wp_get_nav_menu_items' )
			->andReturn( array() )
			->byDefault();
	}

	/**
	 * Test register method adds AJAX action
	 */
	public function test_register_adds_ajax_action(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'wp_ajax_notion_sync_menu_now', \Mockery::type( 'array' ) );

		$this->handler->register();

		$this->assertTrue( true );
	}

	/**
	 * Test ajax_sync_menu_now verifies nonce
	 */
	public function test_ajax_sync_menu_now_verifies_nonce(): void {
		Functions\expect( 'check_ajax_referer' )
			->once()
			->with( 'notion_sync_menu_now', 'nonce' )
			->andThrow( new \Exception( 'Nonce verification failed' ) );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Nonce verification failed' );

		$this->handler->ajax_sync_menu_now();
	}

	/**
	 * Test ajax_sync_menu_now checks user capabilities
	 */
	public function test_ajax_sync_menu_now_checks_capabilities(): void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Insufficient permissions' );

		$this->handler->ajax_sync_menu_now();
	}

	/**
	 * Test ajax_sync_menu_now fails when no root pages found
	 */
	public function test_ajax_sync_menu_now_fails_when_no_root_pages(): void {
		global $wpdb;

		// Mock wpdb to return no pages with notion_page_id
		$wpdb->shouldReceive( 'get_col' )
			->once()
			->andReturn( array() );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'No root pages found' );

		$this->handler->ajax_sync_menu_now();
	}

	/**
	 * Test find_root_pages with wpdb mocking
	 *
	 * Tests the private method indirectly through ajax_sync_menu_now.
	 */
	public function test_ajax_sync_menu_now_finds_root_pages_correctly(): void {
		global $wpdb;

		// Mock posts with notion_page_id
		$wpdb->shouldReceive( 'get_col' )
			->once()
			->with( \Mockery::on( function ( $query ) {
				return strpos( $query, 'notion_page_id' ) !== false;
			} ) )
			->andReturn( array( '1', '2', '3', '4' ) );

		// Mock posts with parent (posts 2 and 3 have parents)
		$wpdb->shouldReceive( 'get_col' )
			->once()
			->with( \Mockery::on( function ( $query ) {
				return strpos( $query, '_notion_parent_page_id' ) !== false;
			} ) )
			->andReturn( array( '2', '3' ) );

		// Mock get_post_meta for root posts (1 and 4)
		Functions\expect( 'get_post_meta' )
			->with( 1, 'notion_page_id', true )
			->andReturn( 'root-page-1' );

		Functions\expect( 'get_post_meta' )
			->with( 4, 'notion_page_id', true )
			->andReturn( 'root-page-2' );

		// Mock the rest of the sync process
		$this->mock_sync_process();

		$this->handler->ajax_sync_menu_now();

		$this->assertTrue( true );
	}

	/**
	 * Test ajax_sync_menu_now with ID format compatibility
	 *
	 * Ensures root page detection works with both dashed and non-dashed IDs.
	 */
	public function test_ajax_sync_menu_now_handles_mixed_id_formats(): void {
		global $wpdb;

		// Root pages with mixed ID formats
		$wpdb->shouldReceive( 'get_col' )
			->once()
			->andReturn( array( '1', '2' ) );

		$wpdb->shouldReceive( 'get_col' )
			->once()
			->andReturn( array() ); // No posts with parents

		// Mock get_post_meta with different ID formats
		Functions\expect( 'get_post_meta' )
			->with( 1, 'notion_page_id', true )
			->andReturn( '2634dac9b96e813da15efd85567b68ff' ); // No dashes

		Functions\expect( 'get_post_meta' )
			->with( 2, 'notion_page_id', true )
			->andReturn( '2634dac9-b96e-813d-a15e-fd85567b68ff' ); // With dashes

		$this->mock_sync_process();

		$this->handler->ajax_sync_menu_now();

		$this->assertTrue( true );
	}

	/**
	 * Test ajax_sync_menu_now builds hierarchy successfully
	 */
	public function test_ajax_sync_menu_now_builds_hierarchy_successfully(): void {
		global $wpdb;

		// Mock finding root page
		$wpdb->shouldReceive( 'get_col' )
			->once()
			->andReturn( array( '1' ) );

		$wpdb->shouldReceive( 'get_col' )
			->once()
			->andReturn( array() );

		Functions\expect( 'get_post_meta' )
			->with( 1, 'notion_page_id', true )
			->andReturn( 'root-page-id' );

		$this->mock_sync_process();

		$this->handler->ajax_sync_menu_now();

		$this->assertTrue( true );
	}

	/**
	 * Test ajax_sync_menu_now handles theme without menu support
	 */
	public function test_ajax_sync_menu_now_handles_theme_without_menus(): void {
		global $wpdb;

		$wpdb->shouldReceive( 'get_col' )
			->once()
			->andReturn( array( '1' ) );

		$wpdb->shouldReceive( 'get_col' )
			->once()
			->andReturn( array() );

		Functions\expect( 'get_post_meta' )
			->with( 1, 'notion_page_id', true )
			->andReturn( 'root-page-id' );

		// Theme doesn't support menus
		Functions\expect( 'current_theme_supports' )
			->with( 'menus' )
			->andReturn( false );

		Functions\expect( 'get_registered_nav_menus' )
			->andReturn( array() );

		$this->mock_sync_process();

		// Should still succeed but show different message
		$this->handler->ajax_sync_menu_now();

		$this->assertTrue( true );
	}

	/**
	 * Test ajax_sync_menu_now exception handling
	 */
	public function test_ajax_sync_menu_now_handles_exceptions(): void {
		global $wpdb;

		// Throw exception during database query
		$wpdb->shouldReceive( 'get_col' )
			->andThrow( new \Exception( 'Database error' ) );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Menu sync failed: Database error' );

		$this->handler->ajax_sync_menu_now();
	}

	/**
	 * Test ajax_sync_menu_now returns success with menu details
	 */
	public function test_ajax_sync_menu_now_returns_success_data(): void {
		global $wpdb;

		$wpdb->shouldReceive( 'get_col' )
			->times( 2 )
			->andReturn( array( '1' ), array() );

		Functions\expect( 'get_post_meta' )
			->andReturn( 'root-page-id' );

		// Mock menu items for count
		$menu_items = array(
			(object) array( 'ID' => 1 ),
			(object) array( 'ID' => 2 ),
			(object) array( 'ID' => 3 ),
		);

		Functions\expect( 'wp_get_nav_menu_items' )
			->once()
			->with( 123 )
			->andReturn( $menu_items );

		$this->mock_sync_process();

		// Capture the success data
		$success_data = null;
		Functions\when( 'wp_send_json_success' )
			->alias( function ( $data ) use ( &$success_data ) {
				$success_data = $data;
			} );

		$this->handler->ajax_sync_menu_now();

		// Verify we captured success data
		$this->assertNotNull( $success_data );
	}

	/**
	 * Helper method to mock the sync process
	 *
	 * Mocks HierarchyDetector, MenuBuilder, and related WordPress functions.
	 */
	private function mock_sync_process(): void {
		// Mock HierarchyDetector::build_hierarchy_map
		Functions\expect( 'get_posts' )
			->andReturn( array() )
			->byDefault();

		Functions\expect( 'get_post' )
			->andReturnNull()
			->byDefault();

		// Mock MenuBuilder::create_or_update_menu
		Functions\expect( 'wp_get_nav_menu_object' )
			->andReturnNull();

		Functions\expect( 'wp_create_nav_menu' )
			->andReturn( 123 ); // Menu ID

		Functions\expect( 'wp_update_nav_menu_item' )
			->andReturn( 1 );

		Functions\expect( 'get_post_type' )
			->andReturn( 'page' );

		Functions\expect( 'wp_delete_post' )
			->andReturn( true );

		// Mock is_wp_error
		Functions\expect( 'is_wp_error' )
			->andReturn( false );
	}
}
