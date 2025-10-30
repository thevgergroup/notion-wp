<?php
/**
 * Tests for MenuBuilder class
 *
 * @package NotionWP
 * @subpackage Tests\Unit\Hierarchy
 */

declare(strict_types=1);

namespace NotionWP\Tests\Unit\Hierarchy;

use Brain\Monkey\Functions;
use Mockery;
use NotionWP\Hierarchy\HierarchyDetector;
use NotionWP\Hierarchy\MenuBuilder;
use NotionWP\Navigation\MenuItemMeta;
use NotionWP\Tests\Unit\BaseTestCase;

/**
 * Class MenuBuilderTest
 *
 * Tests the MenuBuilder class for creating and updating WordPress navigation menus
 * from Notion page hierarchy while preserving manual modifications.
 *
 * @covers \NotionWP\Hierarchy\MenuBuilder
 */
class MenuBuilderTest extends BaseTestCase {
	/**
	 * MenuBuilder instance
	 *
	 * @var MenuBuilder
	 */
	private MenuBuilder $builder;

	/**
	 * Mock MenuItemMeta instance
	 *
	 * @var MenuItemMeta|Mockery\MockInterface
	 */
	private $menu_item_meta;

	/**
	 * Mock HierarchyDetector instance
	 *
	 * @var HierarchyDetector|Mockery\MockInterface
	 */
	private $hierarchy_detector;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create mock dependencies
		$this->menu_item_meta      = Mockery::mock( MenuItemMeta::class );
		$this->hierarchy_detector  = Mockery::mock( HierarchyDetector::class );

		$this->builder = new MenuBuilder( $this->menu_item_meta, $this->hierarchy_detector );

		// Setup WordPress menu function mocks
		$this->setup_menu_mocks();
	}

	/**
	 * Setup WordPress menu function mocks
	 */
	private function setup_menu_mocks(): void {
		// Use lenient mocking for WordPress functions that may be called multiple times
		Functions\when( 'wp_get_nav_menu_object' )->justReturn( null );
		Functions\when( 'wp_create_nav_menu' )->justReturn( 123 );
		Functions\when( 'wp_get_nav_menu_items' )->justReturn( array() );
		Functions\when( 'wp_delete_post' )->justReturn( true );
		Functions\when( 'wp_update_nav_menu_item' )->justReturn( 1 );
		Functions\when( 'get_post_type' )->justReturn( 'page' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'term_exists' )->justReturn( null );
		Functions\when( 'delete_post_meta' )->justReturn( true );
	}

	/**
	 * Test create_or_update_menu creates new menu when it doesn't exist
	 */
	public function test_create_or_update_menu_creates_new_menu(): void {
		Functions\when( 'wp_get_nav_menu_object' )
			->justReturn( null );

		Functions\when( 'wp_create_nav_menu' )
			->justReturn( 123 );

		Functions\when( 'wp_get_nav_menu_items' )
			->justReturn( array() );

		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->andReturn( false );

		$menu_id = $this->builder->create_or_update_menu( 'Test Menu', array() );

		$this->assertEquals( 123, $menu_id );
	}

	/**
	 * Test create_or_update_menu returns 0 when menu creation fails
	 */
	public function test_create_or_update_menu_returns_zero_on_creation_failure(): void {
		Functions\when( 'wp_get_nav_menu_object' )
			->justReturn( null );

		// Simulate WP_Error return
		$wp_error = new \WP_Error( 'menu_error', 'Failed to create menu' );
		Functions\when( 'wp_create_nav_menu' )
			->justReturn( $wp_error );

		Functions\when( 'is_wp_error' )
			->alias( function ( $thing ) use ( $wp_error ) {
				return $thing === $wp_error;
			} );

		$menu_id = $this->builder->create_or_update_menu( 'Test Menu', array() );

		$this->assertEquals( 0, $menu_id );
	}

	/**
	 * Test create_or_update_menu uses existing menu when it exists
	 */
	public function test_create_or_update_menu_uses_existing_menu(): void {
		$menu_object = (object) array(
			'term_id' => 456,
			'name'    => 'Test Menu',
		);

		Functions\when( 'wp_get_nav_menu_object' )
			->justReturn( $menu_object );

		Functions\when( 'wp_get_nav_menu_items' )
			->justReturn( array() );

		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->andReturn( false );

		$menu_id = $this->builder->create_or_update_menu( 'Test Menu', array() );

		$this->assertEquals( 456, $menu_id );
	}

	/**
	 * Test create_or_update_menu deletes Notion-synced items without override
	 */
	public function test_create_or_update_menu_deletes_notion_synced_items(): void {
		$menu_object = (object) array( 'term_id' => 123 );

		Functions\when( 'wp_get_nav_menu_object' )
			->justReturn( $menu_object );

		// Mock menu items
		$item1 = (object) array( 'ID' => 100 );
		$item2 = (object) array( 'ID' => 101 );
		$item3 = (object) array( 'ID' => 102 );

		Functions\when( 'wp_get_nav_menu_items' )
			->justReturn( array( $item1, $item2, $item3 ) );

		// Mock is_notion_synced: items 100 and 101 are synced, 102 is manual
		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->with( 100 )
			->andReturn( true );

		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->with( 101 )
			->andReturn( true );

		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->with( 102 )
			->andReturn( false );

		// Mock has_override: item 100 has override, 101 doesn't
		$this->menu_item_meta->shouldReceive( 'has_override' )
			->with( 100 )
			->andReturn( true );

		$this->menu_item_meta->shouldReceive( 'has_override' )
			->with( 101 )
			->andReturn( false );

		// Mock is_manual - called for all items in preserve_manual_items
		$this->menu_item_meta->shouldReceive( 'is_manual' )
			->with( 100 )
			->andReturn( false );

		$this->menu_item_meta->shouldReceive( 'is_manual' )
			->with( 101 )
			->andReturn( false );

		$this->menu_item_meta->shouldReceive( 'is_manual' )
			->with( 102 )
			->andReturn( true );

		$deleted_items = array();
		Functions\when( 'wp_delete_post' )
			->alias( function ( $item_id, $force_delete ) use ( &$deleted_items ) {
				$deleted_items[] = $item_id;
				return true;
			} );

		$menu_id = $this->builder->create_or_update_menu( 'Test Menu', array() );

		$this->assertEquals( 123, $menu_id );
		// Only item 101 should be deleted (Notion-synced without override)
		$this->assertEquals( array( 101 ), $deleted_items );
	}

	/**
	 * Test create_or_update_menu preserves items with override flag
	 */
	public function test_create_or_update_menu_preserves_overridden_items(): void {
		$menu_object = (object) array( 'term_id' => 123 );

		Functions\when( 'wp_get_nav_menu_object' )
			->justReturn( $menu_object );

		$item = (object) array( 'ID' => 100 );

		Functions\when( 'wp_get_nav_menu_items' )
			->justReturn( array( $item ) );

		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->with( 100 )
			->andReturn( true );

		$this->menu_item_meta->shouldReceive( 'has_override' )
			->with( 100 )
			->andReturn( true );

		$this->menu_item_meta->shouldReceive( 'is_manual' )
			->with( 100 )
			->andReturn( false );

		$deleted_items = array();
		Functions\when( 'wp_delete_post' )
			->alias( function ( $item_id, $force_delete ) use ( &$deleted_items ) {
				$deleted_items[] = $item_id;
				return true;
			} );

		$menu_id = $this->builder->create_or_update_menu( 'Test Menu', array() );

		$this->assertEquals( 123, $menu_id );
		// Should NOT delete item with override
		$this->assertEmpty( $deleted_items );
	}

	/**
	 * Test create_or_update_menu adds pages from hierarchy
	 */
	public function test_create_or_update_menu_adds_hierarchy_pages(): void {
		$menu_object = (object) array( 'term_id' => 123 );

		Functions\when( 'wp_get_nav_menu_object' )
			->justReturn( $menu_object );

		Functions\when( 'wp_get_nav_menu_items' )
			->justReturn( array() );

		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->andReturn( false );

		// Hierarchy with one root page
		$hierarchy = array(
			'page-123' => array(
				'post_id'         => 100,
				'parent_page_id'  => null,
				'parent_post_id'  => null,
				'title'           => 'Root Page',
				'order'           => 0,
				'children'        => array(),
			),
		);

		$menu_items_added = array();
		Functions\when( 'wp_update_nav_menu_item' )
			->alias( function ( $menu_id, $item_id, $args ) use ( &$menu_items_added ) {
				$menu_items_added[] = $args;
				return 200;
			} );

		Functions\when( 'get_post_type' )
			->justReturn( 'page' );

		$this->menu_item_meta->shouldReceive( 'mark_as_notion_synced' )
			->once()
			->with( 200, 'page-123' );

		$menu_id = $this->builder->create_or_update_menu( 'Test Menu', $hierarchy );

		$this->assertEquals( 123, $menu_id );
		$this->assertCount( 1, $menu_items_added );
		$this->assertEquals( 100, $menu_items_added[0]['menu-item-object-id'] );
		$this->assertEquals( 0, $menu_items_added[0]['menu-item-parent-id'] );
		$this->assertEquals( 'post_type', $menu_items_added[0]['menu-item-type'] );
	}

	/**
	 * Test create_or_update_menu builds nested hierarchy
	 */
	public function test_create_or_update_menu_builds_nested_hierarchy(): void {
		$menu_object = (object) array( 'term_id' => 123 );

		Functions\when( 'wp_get_nav_menu_object' )
			->justReturn( $menu_object );

		Functions\when( 'wp_get_nav_menu_items' )
			->justReturn( array() );

		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->andReturn( false );

		// Hierarchy with parent and child
		$hierarchy = array(
			'parent-id' => array(
				'post_id'         => 100,
				'parent_page_id'  => null,
				'parent_post_id'  => null,
				'title'           => 'Parent Page',
				'order'           => 0,
				'children'        => array( 'child-id' ),
			),
			'child-id' => array(
				'post_id'         => 101,
				'parent_page_id'  => 'parent-id',
				'parent_post_id'  => 100,
				'title'           => 'Child Page',
				'order'           => 0,
				'children'        => array(),
			),
		);

		$menu_items_added = array();
		$item_id_counter = 200;
		Functions\when( 'wp_update_nav_menu_item' )
			->alias( function ( $menu_id, $item_id, $args ) use ( &$menu_items_added, &$item_id_counter ) {
				$menu_items_added[] = $args;
				return $item_id_counter++;
			} );

		Functions\when( 'get_post_type' )
			->justReturn( 'page' );

		$this->menu_item_meta->shouldReceive( 'mark_as_notion_synced' )
			->twice();

		$menu_id = $this->builder->create_or_update_menu( 'Test Menu', $hierarchy );

		$this->assertEquals( 123, $menu_id );
		$this->assertCount( 2, $menu_items_added );
		// Parent item
		$this->assertEquals( 100, $menu_items_added[0]['menu-item-object-id'] );
		$this->assertEquals( 0, $menu_items_added[0]['menu-item-parent-id'] );
		// Child item references parent menu item (200)
		$this->assertEquals( 101, $menu_items_added[1]['menu-item-object-id'] );
		$this->assertEquals( 200, $menu_items_added[1]['menu-item-parent-id'] );
	}

	/**
	 * Test create_or_update_menu sorts items by menu_order
	 */
	public function test_create_or_update_menu_respects_menu_order(): void {
		$menu_object = (object) array( 'term_id' => 123 );

		Functions\when( 'wp_get_nav_menu_object' )
			->justReturn( $menu_object );

		Functions\when( 'wp_get_nav_menu_items' )
			->justReturn( array() );

		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->andReturn( false );

		// Hierarchy with items in reverse order
		$hierarchy = array(
			'page-3' => array(
				'post_id'         => 103,
				'parent_page_id'  => null,
				'parent_post_id'  => null,
				'title'           => 'Third Page',
				'order'           => 2,
				'children'        => array(),
			),
			'page-1' => array(
				'post_id'         => 101,
				'parent_page_id'  => null,
				'parent_post_id'  => null,
				'title'           => 'First Page',
				'order'           => 0,
				'children'        => array(),
			),
			'page-2' => array(
				'post_id'         => 102,
				'parent_page_id'  => null,
				'parent_post_id'  => null,
				'title'           => 'Second Page',
				'order'           => 1,
				'children'        => array(),
			),
		);

		$call_order = array();

		Functions\when( 'wp_update_nav_menu_item' )
			->alias( function ( $menu_id, $item_id, $args ) use ( &$call_order ) {
				$call_order[] = $args['menu-item-object-id'];
				return 200 + $args['menu-item-object-id'];
			} );

		Functions\when( 'get_post_type' )
			->justReturn( 'page' );

		$this->menu_item_meta->shouldReceive( 'mark_as_notion_synced' )
			->times( 3 );

		$menu_id = $this->builder->create_or_update_menu( 'Test Menu', $hierarchy );

		// Verify items were added in order: 101, 102, 103
		$this->assertEquals( array( 101, 102, 103 ), $call_order );
	}

	/**
	 * Test preserve_manual_items returns empty array when menu is empty
	 */
	public function test_preserve_manual_items_returns_empty_for_empty_menu(): void {
		Functions\when( 'wp_get_nav_menu_items' )
			->justReturn( null ); // WordPress returns null for empty menu

		$items = $this->builder->preserve_manual_items( 123 );

		$this->assertIsArray( $items );
		$this->assertEmpty( $items );
	}

	/**
	 * Test preserve_manual_items identifies manual items
	 */
	public function test_preserve_manual_items_identifies_manual_items(): void {
		$item1 = (object) array( 'ID' => 100 );
		$item2 = (object) array( 'ID' => 101 );
		$item3 = (object) array( 'ID' => 102 );

		Functions\when( 'wp_get_nav_menu_items' )
			->justReturn( array( $item1, $item2, $item3 ) );

		// Mock is_notion_synced
		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->with( 100 )
			->andReturn( true ); // Notion-synced

		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->with( 101 )
			->andReturn( false ); // Not synced

		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->with( 102 )
			->andReturn( false ); // Not synced

		// Mock is_manual
		$this->menu_item_meta->shouldReceive( 'is_manual' )
			->with( 100 )
			->andReturn( false );

		$this->menu_item_meta->shouldReceive( 'is_manual' )
			->with( 101 )
			->andReturn( true ); // Explicitly marked as manual

		$this->menu_item_meta->shouldReceive( 'is_manual' )
			->with( 102 )
			->andReturn( false ); // Legacy item (neither synced nor manual)

		$manual_items = $this->builder->preserve_manual_items( 123 );

		$this->assertIsArray( $manual_items );
		$this->assertCount( 2, $manual_items ); // Items 101 and 102
		$this->assertContains( 101, $manual_items );
		$this->assertContains( 102, $manual_items );
	}

	/**
	 * Test preserve_manual_items excludes Notion-synced items
	 */
	public function test_preserve_manual_items_excludes_notion_synced(): void {
		$item = (object) array( 'ID' => 100 );

		Functions\when( 'wp_get_nav_menu_items' )
			->justReturn( array( $item ) );

		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->with( 100 )
			->andReturn( true );

		$this->menu_item_meta->shouldReceive( 'is_manual' )
			->with( 100 )
			->andReturn( false );

		$manual_items = $this->builder->preserve_manual_items( 123 );

		$this->assertIsArray( $manual_items );
		$this->assertEmpty( $manual_items );
	}
}
