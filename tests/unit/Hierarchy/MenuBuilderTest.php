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
		// Mock wp_get_nav_menu_object to return null by default (menu doesn't exist)
		Functions\expect( 'wp_get_nav_menu_object' )
			->andReturnNull()
			->byDefault();

		// Mock wp_create_nav_menu to return menu ID
		Functions\expect( 'wp_create_nav_menu' )
			->andReturn( 123 )
			->byDefault();

		// Mock wp_get_nav_menu_items to return empty array
		Functions\expect( 'wp_get_nav_menu_items' )
			->andReturn( array() )
			->byDefault();

		// Mock wp_delete_post
		Functions\expect( 'wp_delete_post' )
			->andReturn( true )
			->byDefault();

		// Mock wp_update_nav_menu_item
		Functions\expect( 'wp_update_nav_menu_item' )
			->andReturn( 1 )
			->byDefault();

		// Mock get_post_type
		Functions\expect( 'get_post_type' )
			->andReturn( 'page' )
			->byDefault();

		// Mock is_wp_error
		Functions\expect( 'is_wp_error' )
			->andReturn( false )
			->byDefault();
	}

	/**
	 * Test create_or_update_menu creates new menu when it doesn't exist
	 */
	public function test_create_or_update_menu_creates_new_menu(): void {
		Functions\expect( 'wp_get_nav_menu_object' )
			->once()
			->with( 'Test Menu' )
			->andReturnNull();

		Functions\expect( 'wp_create_nav_menu' )
			->once()
			->with( 'Test Menu' )
			->andReturn( 123 );

		Functions\expect( 'wp_get_nav_menu_items' )
			->once()
			->with( 123 )
			->andReturn( array() );

		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->andReturn( false );

		$menu_id = $this->builder->create_or_update_menu( 'Test Menu', array() );

		$this->assertEquals( 123, $menu_id );
	}

	/**
	 * Test create_or_update_menu returns 0 when menu creation fails
	 */
	public function test_create_or_update_menu_returns_zero_on_creation_failure(): void {
		Functions\expect( 'wp_get_nav_menu_object' )
			->once()
			->andReturnNull();

		// Simulate WP_Error return
		$wp_error = new \WP_Error( 'menu_error', 'Failed to create menu' );
		Functions\expect( 'wp_create_nav_menu' )
			->once()
			->andReturn( $wp_error );

		Functions\expect( 'is_wp_error' )
			->once()
			->with( $wp_error )
			->andReturn( true );

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

		Functions\expect( 'wp_get_nav_menu_object' )
			->once()
			->with( 'Test Menu' )
			->andReturn( $menu_object );

		Functions\expect( 'wp_get_nav_menu_items' )
			->once()
			->with( 456 )
			->andReturn( array() );

		// Should not call wp_create_nav_menu
		Functions\expect( 'wp_create_nav_menu' )->never();

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

		Functions\expect( 'wp_get_nav_menu_object' )
			->once()
			->andReturn( $menu_object );

		// Mock menu items
		$item1 = (object) array( 'ID' => 100 );
		$item2 = (object) array( 'ID' => 101 );
		$item3 = (object) array( 'ID' => 102 );

		Functions\expect( 'wp_get_nav_menu_items' )
			->once()
			->with( 123 )
			->andReturn( array( $item1, $item2, $item3 ) );

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

		// Mock is_manual for item 102
		$this->menu_item_meta->shouldReceive( 'is_manual' )
			->with( 102 )
			->andReturn( true );

		// Only item 101 should be deleted (Notion-synced without override)
		Functions\expect( 'wp_delete_post' )
			->once()
			->with( 101, true )
			->andReturn( true );

		$menu_id = $this->builder->create_or_update_menu( 'Test Menu', array() );

		$this->assertEquals( 123, $menu_id );
	}

	/**
	 * Test create_or_update_menu preserves items with override flag
	 */
	public function test_create_or_update_menu_preserves_overridden_items(): void {
		$menu_object = (object) array( 'term_id' => 123 );

		Functions\expect( 'wp_get_nav_menu_object' )
			->once()
			->andReturn( $menu_object );

		$item = (object) array( 'ID' => 100 );

		Functions\expect( 'wp_get_nav_menu_items' )
			->once()
			->andReturn( array( $item ) );

		$this->menu_item_meta->shouldReceive( 'is_notion_synced' )
			->with( 100 )
			->andReturn( true );

		$this->menu_item_meta->shouldReceive( 'has_override' )
			->with( 100 )
			->andReturn( true );

		// Should NOT delete item with override
		Functions\expect( 'wp_delete_post' )->never();

		$menu_id = $this->builder->create_or_update_menu( 'Test Menu', array() );

		$this->assertEquals( 123, $menu_id );
	}

	/**
	 * Test create_or_update_menu adds pages from hierarchy
	 */
	public function test_create_or_update_menu_adds_hierarchy_pages(): void {
		$menu_object = (object) array( 'term_id' => 123 );

		Functions\expect( 'wp_get_nav_menu_object' )
			->once()
			->andReturn( $menu_object );

		Functions\expect( 'wp_get_nav_menu_items' )
			->once()
			->andReturn( array() );

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

		// Mock wp_update_nav_menu_item for adding the page
		Functions\expect( 'wp_update_nav_menu_item' )
			->once()
			->with( 123, 0, \Mockery::on( function ( $args ) {
				return $args['menu-item-object-id'] === 100
					&& $args['menu-item-parent-id'] === 0
					&& $args['menu-item-type'] === 'post_type';
			} ) )
			->andReturn( 200 ); // Menu item ID

		Functions\expect( 'get_post_type' )
			->once()
			->with( 100 )
			->andReturn( 'page' );

		$this->menu_item_meta->shouldReceive( 'mark_as_notion_synced' )
			->once()
			->with( 200, 'page-123' );

		$menu_id = $this->builder->create_or_update_menu( 'Test Menu', $hierarchy );

		$this->assertEquals( 123, $menu_id );
	}

	/**
	 * Test create_or_update_menu builds nested hierarchy
	 */
	public function test_create_or_update_menu_builds_nested_hierarchy(): void {
		$menu_object = (object) array( 'term_id' => 123 );

		Functions\expect( 'wp_get_nav_menu_object' )
			->once()
			->andReturn( $menu_object );

		Functions\expect( 'wp_get_nav_menu_items' )
			->once()
			->andReturn( array() );

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

		// Mock adding parent
		Functions\expect( 'wp_update_nav_menu_item' )
			->once()
			->with( 123, 0, \Mockery::on( function ( $args ) {
				return $args['menu-item-object-id'] === 100
					&& $args['menu-item-parent-id'] === 0;
			} ) )
			->andReturn( 200 ); // Parent menu item ID

		// Mock adding child with parent reference
		Functions\expect( 'wp_update_nav_menu_item' )
			->once()
			->with( 123, 0, \Mockery::on( function ( $args ) {
				return $args['menu-item-object-id'] === 101
					&& $args['menu-item-parent-id'] === 200; // References parent menu item
			} ) )
			->andReturn( 201 ); // Child menu item ID

		Functions\expect( 'get_post_type' )
			->times( 2 )
			->andReturn( 'page' );

		$this->menu_item_meta->shouldReceive( 'mark_as_notion_synced' )
			->twice();

		$menu_id = $this->builder->create_or_update_menu( 'Test Menu', $hierarchy );

		$this->assertEquals( 123, $menu_id );
	}

	/**
	 * Test create_or_update_menu sorts items by menu_order
	 */
	public function test_create_or_update_menu_respects_menu_order(): void {
		$menu_object = (object) array( 'term_id' => 123 );

		Functions\expect( 'wp_get_nav_menu_object' )
			->once()
			->andReturn( $menu_object );

		Functions\expect( 'wp_get_nav_menu_items' )
			->once()
			->andReturn( array() );

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

		Functions\expect( 'wp_update_nav_menu_item' )
			->times( 3 )
			->andReturnUsing( function ( $menu_id, $item_id, $args ) use ( &$call_order ) {
				$call_order[] = $args['menu-item-object-id'];
				return 200 + $args['menu-item-object-id'];
			} );

		Functions\expect( 'get_post_type' )
			->times( 3 )
			->andReturn( 'page' );

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
		Functions\expect( 'wp_get_nav_menu_items' )
			->once()
			->with( 123 )
			->andReturn( null ); // WordPress returns null for empty menu

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

		Functions\expect( 'wp_get_nav_menu_items' )
			->once()
			->with( 123 )
			->andReturn( array( $item1, $item2, $item3 ) );

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

		Functions\expect( 'wp_get_nav_menu_items' )
			->once()
			->andReturn( array( $item ) );

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
