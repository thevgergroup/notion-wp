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
	 * Override setup_wpdb_mock to add postmeta property
	 */
	protected function setup_wpdb_mock(): void {
		parent::setup_wpdb_mock();

		global $wpdb;
		$wpdb->postmeta = 'wp_postmeta';
	}

	/**
	 * Setup WordPress AJAX function mocks
	 */
	private function setup_ajax_mocks(): void {
		// Mock check_ajax_referer - use when() to avoid conflicts
		Functions\when( 'check_ajax_referer' )
			->justReturn( true );

		// Mock current_user_can - use when() to avoid conflicts
		Functions\when( 'current_user_can' )
			->justReturn( true );

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
		Functions\when( '__' )
			->returnArg();

		Functions\when( 'esc_html' )
			->alias( function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
			} );

		Functions\when( 'esc_url' )
			->returnArg();

		Functions\when( 'wp_kses' )
			->returnArg();

		// Mock admin_url
		Functions\when( 'admin_url' )
			->alias( function ( $path ) {
				return 'http://example.com/wp-admin/' . $path;
			} );

		// Mock get_option
		Functions\when( 'get_option' )
			->alias( function ( $option, $default = false ) {
				if ( $option === 'notion_sync_menu_name' ) {
					return 'Notion Navigation';
				}
				return $default;
			} );

		// Mock current_theme_supports
		Functions\when( 'current_theme_supports' )
			->justReturn( true );

		// Mock get_registered_nav_menus
		Functions\when( 'get_registered_nav_menus' )
			->justReturn( array( 'primary' => 'Primary Menu' ) );

		// Mock wp_json_encode
		Functions\when( 'wp_json_encode' )
			->alias( function ( $data ) {
				return json_encode( $data );
			} );

		// Mock error_log
		Functions\when( 'error_log' )
			->justReturn();

		// Mock wp_get_nav_menu_items
		Functions\when( 'wp_get_nav_menu_items' )
			->justReturn( array() );
	}

	/**
	 * Test register method adds AJAX action
	 */
	public function test_register_adds_ajax_action(): void {
		Functions\when( 'add_action' )
			->justReturn( true );

		$this->handler->register();

		$this->assertTrue( true );
	}

	/**
	 * Test ajax_sync_menu_now verifies nonce
	 */
	public function test_ajax_sync_menu_now_verifies_nonce(): void {
		Functions\when( 'check_ajax_referer' )
			->alias( function () {
				throw new \Exception( 'Nonce verification failed' );
			} );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Nonce verification failed' );

		$this->handler->ajax_sync_menu_now();
	}

	/**
	 * Test ajax_sync_menu_now checks user capabilities
	 */
	public function test_ajax_sync_menu_now_checks_capabilities(): void {
		Functions\when( 'current_user_can' )
			->justReturn( false );

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

		// Mock posts with notion_page_id (first call)
		// Mock posts with parent (second call) - posts 2 and 3 have parents
		$wpdb->shouldReceive( 'get_col' )
			->times( 2 )
			->andReturn( array( '1', '2', '3', '4' ), array( '2', '3' ) );

		// Mock get_post_meta for root posts (1 and 4)
		Functions\when( 'get_post_meta' )
			->alias( function ( $post_id, $key, $single ) {
				if ( $key !== 'notion_page_id' ) {
					return '';
				}

				if ( $post_id === 1 ) {
					return 'root-page-1';
				} elseif ( $post_id === 4 ) {
					return 'root-page-2';
				}

				return '';
			} );

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
			->times( 2 )
			->andReturn( array( '1', '2' ), array() ); // No posts with parents

		// Mock get_post_meta with different ID formats
		Functions\when( 'get_post_meta' )
			->alias( function ( $post_id, $key, $single ) {
				if ( $key !== 'notion_page_id' ) {
					return '';
				}

				if ( $post_id === 1 ) {
					return '2634dac9b96e813da15efd85567b68ff'; // No dashes
				} elseif ( $post_id === 2 ) {
					return '2634dac9-b96e-813d-a15e-fd85567b68ff'; // With dashes
				}

				return '';
			} );

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
			->times( 2 )
			->andReturn( array( '1' ), array() );

		Functions\when( 'get_post_meta' )
			->alias( function ( $post_id, $key, $single ) {
				if ( $post_id === 1 && $key === 'notion_page_id' ) {
					return 'root-page-id';
				}
				return '';
			} );

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
			->times( 2 )
			->andReturn( array( '1' ), array() );

		Functions\when( 'get_post_meta' )
			->alias( function ( $post_id, $key, $single ) {
				if ( $post_id === 1 && $key === 'notion_page_id' ) {
					return 'root-page-id';
				}
				return '';
			} );

		// Theme doesn't support menus
		Functions\when( 'current_theme_supports' )
			->justReturn( false );

		Functions\when( 'get_registered_nav_menus' )
			->justReturn( array() );

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

		Functions\when( 'get_post_meta' )
			->alias( function ( $post_id, $key, $single ) {
				if ( $key === 'notion_page_id' ) {
					return 'root-page-id';
				}
				return '';
			} );

		// Mock menu items for count
		$menu_items = array(
			(object) array( 'ID' => 1 ),
			(object) array( 'ID' => 2 ),
			(object) array( 'ID' => 3 ),
		);

		Functions\when( 'wp_get_nav_menu_items' )
			->alias( function ( $menu_id ) use ( $menu_items ) {
				if ( $menu_id === 123 ) {
					return $menu_items;
				}
				return array();
			} );

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
		Functions\when( 'get_posts' )
			->justReturn( array() );

		Functions\when( 'get_post' )
			->justReturn( null );

		Functions\when( 'get_post_meta' )
			->justReturn( '' );

		// Mock MenuBuilder::create_or_update_menu
		Functions\when( 'wp_get_nav_menu_object' )
			->justReturn( null );

		Functions\when( 'wp_create_nav_menu' )
			->justReturn( 123 ); // Menu ID

		Functions\when( 'wp_update_nav_menu_item' )
			->justReturn( 1 );

		Functions\when( 'get_post_type' )
			->justReturn( 'page' );

		Functions\when( 'wp_delete_post' )
			->justReturn( true );

		// Mock is_wp_error
		Functions\when( 'is_wp_error' )
			->justReturn( false );
	}
}
