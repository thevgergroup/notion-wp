<?php
/**
 * Tests for HierarchyDetector class
 *
 * @package NotionWP
 * @subpackage Tests\Unit\Hierarchy
 */

declare(strict_types=1);

namespace NotionWP\Tests\Unit\Hierarchy;

use Brain\Monkey\Functions;
use NotionWP\Hierarchy\HierarchyDetector;
use NotionWP\Tests\Unit\BaseTestCase;

/**
 * Class HierarchyDetectorTest
 *
 * Tests the HierarchyDetector class, with special focus on ID format compatibility
 * (with and without dashes) to prevent regressions of the menu sync bug.
 *
 * @covers \NotionWP\Hierarchy\HierarchyDetector
 */
class HierarchyDetectorTest extends BaseTestCase {
	/**
	 * HierarchyDetector instance
	 *
	 * @var HierarchyDetector
	 */
	private HierarchyDetector $detector;

	/**
	 * Sample Notion page IDs for testing
	 *
	 * @var array
	 */
	private array $test_page_ids = array(
		'root_no_dashes'     => '2634dac9b96e813da15efd85567b68ff',
		'root_with_dashes'   => '2634dac9-b96e-813d-a15e-fd85567b68ff',
		'child1_no_dashes'   => 'abc123def456789012345678901234ab',
		'child1_with_dashes' => 'abc123de-f456-7890-1234-5678901234ab',
		'child2_no_dashes'   => '11112222333344445555666677778888',
		'child2_with_dashes' => '11112222-3333-4444-5555-666677778888',
	);

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->detector = new HierarchyDetector();

		// Mock WordPress functions specific to hierarchy detection
		$this->setup_hierarchy_mocks();
	}

	/**
	 * Setup WordPress function mocks for hierarchy tests
	 */
	private function setup_hierarchy_mocks(): void {
		// Mock get_posts to return empty array by default
		Functions\expect( 'get_posts' )
			->andReturn( array() )
			->byDefault();

		// Mock get_post to return null by default
		Functions\expect( 'get_post' )
			->andReturnNull()
			->byDefault();

		// Mock is_wp_error to return false
		Functions\expect( 'is_wp_error' )
			->andReturn( false )
			->byDefault();

		// Mock do_action for hierarchy_updated hook
		Functions\expect( 'do_action' )
			->with( 'notion_hierarchy_updated', \Mockery::any(), \Mockery::any(), \Mockery::any(), \Mockery::any() )
			->andReturnNull()
			->byDefault();
	}

	/**
	 * Test constructor sets max depth correctly
	 */
	public function test_constructor_sets_max_depth(): void {
		$detector = new HierarchyDetector( 3 );
		$this->assertEquals( 3, $detector->get_max_depth() );
	}

	/**
	 * Test constructor enforces minimum depth of 1
	 */
	public function test_constructor_enforces_minimum_depth(): void {
		$detector = new HierarchyDetector( 0 );
		$this->assertEquals( 1, $detector->get_max_depth() );
	}

	/**
	 * Test constructor enforces maximum depth of 10
	 */
	public function test_constructor_enforces_maximum_depth(): void {
		$detector = new HierarchyDetector( 15 );
		$this->assertEquals( 10, $detector->get_max_depth() );
	}

	/**
	 * Test get_child_pages returns empty array when no children exist
	 */
	public function test_get_child_pages_returns_empty_for_no_children(): void {
		Functions\expect( 'get_posts' )
			->once()
			->with( \Mockery::on( function ( $args ) {
				return isset( $args['meta_query'] )
					&& $args['meta_query']['relation'] === 'OR'
					&& $args['posts_per_page'] === -1;
			} ) )
			->andReturn( array() );

		$children = $this->detector->get_child_pages( 'page-123' );
		$this->assertIsArray( $children );
		$this->assertEmpty( $children );
	}

	/**
	 * CRITICAL TEST: Test get_child_pages finds children with parent ID stored WITHOUT dashes
	 *
	 * This is the primary bug scenario: parent ID is stored as normalized (no dashes),
	 * and we need to ensure the query searches for both formats.
	 */
	public function test_get_child_pages_finds_children_with_parent_stored_without_dashes(): void {
		$parent_id = $this->test_page_ids['root_with_dashes'];
		$child_id  = $this->test_page_ids['child1_no_dashes'];

		// Mock get_posts to return child when searching for parent
		Functions\expect( 'get_posts' )
			->once()
			->with( \Mockery::on( function ( $args ) use ( $parent_id ) {
				// Verify the meta query searches for BOTH formats
				$meta_query = $args['meta_query'] ?? array();
				$this->assertEquals( 'OR', $meta_query['relation'] );

				// Should search for normalized (no dashes)
				$normalized = str_replace( '-', '', $parent_id );
				$this->assertEquals( '_notion_parent_page_id', $meta_query[0]['key'] );
				$this->assertEquals( $normalized, $meta_query[0]['value'] );

				// Should also search for with dashes
				$this->assertEquals( '_notion_parent_page_id', $meta_query[1]['key'] );
				$this->assertEquals( $parent_id, $meta_query[1]['value'] );

				return true;
			} ) )
			->andReturn( array( 100 ) ); // Return child post ID

		// Mock get_post_meta to return child's Notion ID
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 100, 'notion_page_id', true )
			->andReturn( $child_id );

		$children = $this->detector->get_child_pages( $parent_id );

		$this->assertIsArray( $children );
		$this->assertCount( 1, $children );
		$this->assertEquals( $child_id, $children[0] );
	}

	/**
	 * CRITICAL TEST: Test get_child_pages finds children with parent ID stored WITH dashes
	 *
	 * This tests the inverse scenario: parent ID stored with dashes format.
	 */
	public function test_get_child_pages_finds_children_with_parent_stored_with_dashes(): void {
		$parent_id = $this->test_page_ids['root_no_dashes'];
		$child_id  = $this->test_page_ids['child1_with_dashes'];

		// Mock get_posts to return child when searching for parent
		Functions\expect( 'get_posts' )
			->once()
			->with( \Mockery::on( function ( $args ) use ( $parent_id ) {
				// Verify the meta query searches for BOTH formats
				$meta_query = $args['meta_query'] ?? array();

				// First query: normalized (parent_id is already normalized)
				$this->assertEquals( $parent_id, $meta_query[0]['value'] );

				// Second query: with dashes (converted from normalized)
				$with_dashes = substr( $parent_id, 0, 8 ) . '-' .
							   substr( $parent_id, 8, 4 ) . '-' .
							   substr( $parent_id, 12, 4 ) . '-' .
							   substr( $parent_id, 16, 4 ) . '-' .
							   substr( $parent_id, 20 );
				$this->assertEquals( $with_dashes, $meta_query[1]['value'] );

				return true;
			} ) )
			->andReturn( array( 101 ) ); // Return child post ID

		// Mock get_post_meta to return child's Notion ID
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 101, 'notion_page_id', true )
			->andReturn( $child_id );

		$children = $this->detector->get_child_pages( $parent_id );

		$this->assertIsArray( $children );
		$this->assertCount( 1, $children );
		$this->assertEquals( $child_id, $children[0] );
	}

	/**
	 * CRITICAL TEST: Test get_child_pages with mixed ID formats
	 *
	 * Tests scenario where multiple children have parent IDs stored in different formats.
	 */
	public function test_get_child_pages_finds_all_children_with_mixed_formats(): void {
		$parent_id = $this->test_page_ids['root_with_dashes'];

		// Mock get_posts to return multiple children
		Functions\expect( 'get_posts' )
			->once()
			->andReturn( array( 100, 101, 102 ) );

		// Mock get_post_meta for each child
		Functions\expect( 'get_post_meta' )
			->with( 100, 'notion_page_id', true )
			->andReturn( $this->test_page_ids['child1_no_dashes'] );

		Functions\expect( 'get_post_meta' )
			->with( 101, 'notion_page_id', true )
			->andReturn( $this->test_page_ids['child1_with_dashes'] );

		Functions\expect( 'get_post_meta' )
			->with( 102, 'notion_page_id', true )
			->andReturn( $this->test_page_ids['child2_no_dashes'] );

		$children = $this->detector->get_child_pages( $parent_id );

		$this->assertIsArray( $children );
		$this->assertCount( 3, $children );
		$this->assertContains( $this->test_page_ids['child1_no_dashes'], $children );
		$this->assertContains( $this->test_page_ids['child1_with_dashes'], $children );
		$this->assertContains( $this->test_page_ids['child2_no_dashes'], $children );
	}

	/**
	 * Test get_child_pages skips posts without notion_page_id meta
	 */
	public function test_get_child_pages_skips_posts_without_notion_id(): void {
		// Mock get_posts to return posts
		Functions\expect( 'get_posts' )
			->once()
			->andReturn( array( 100, 101 ) );

		// Mock get_post_meta: first returns ID, second returns empty
		Functions\expect( 'get_post_meta' )
			->with( 100, 'notion_page_id', true )
			->andReturn( $this->test_page_ids['child1_no_dashes'] );

		Functions\expect( 'get_post_meta' )
			->with( 101, 'notion_page_id', true )
			->andReturn( '' ); // Empty Notion ID

		$children = $this->detector->get_child_pages( 'parent-id' );

		$this->assertIsArray( $children );
		$this->assertCount( 1, $children ); // Only the first one
		$this->assertEquals( $this->test_page_ids['child1_no_dashes'], $children[0] );
	}

	/**
	 * Test process_page_hierarchy does nothing when no parent info exists
	 */
	public function test_process_page_hierarchy_skips_when_no_parent(): void {
		$page_properties = array(
			'title' => array( 'plain_text' => 'Test Page' ),
		);

		// Should not call update_post_meta or wp_update_post
		Functions\expect( 'update_post_meta' )->never();
		Functions\expect( 'wp_update_post' )->never();

		$this->detector->process_page_hierarchy( 1, 'page-id', $page_properties );

		// No exceptions = success
		$this->assertTrue( true );
	}

	/**
	 * Test process_page_hierarchy skips non-page parents
	 */
	public function test_process_page_hierarchy_skips_non_page_parents(): void {
		$page_properties = array(
			'parent' => array(
				'type'        => 'database_id',
				'database_id' => 'database-123',
			),
		);

		// Should not call update_post_meta or wp_update_post
		Functions\expect( 'update_post_meta' )->never();
		Functions\expect( 'wp_update_post' )->never();

		$this->detector->process_page_hierarchy( 1, 'page-id', $page_properties );

		$this->assertTrue( true );
	}

	/**
	 * Test process_page_hierarchy stores parent ID meta
	 */
	public function test_process_page_hierarchy_stores_parent_id(): void {
		$parent_id = $this->test_page_ids['root_with_dashes'];
		$page_properties = array(
			'parent' => array(
				'type'    => 'page_id',
				'page_id' => $parent_id,
			),
		);

		// Mock update_post_meta to verify parent ID is stored
		Functions\expect( 'update_post_meta' )
			->once()
			->with( 123, '_notion_parent_page_id', $parent_id )
			->andReturn( true );

		// Parent post not found, so wp_update_post shouldn't be called
		Functions\expect( 'get_posts' )
			->once()
			->andReturn( array() );

		Functions\expect( 'wp_update_post' )->never();

		$this->detector->process_page_hierarchy( 123, 'page-id', $page_properties );

		$this->assertTrue( true );
	}

	/**
	 * Test process_page_hierarchy updates WordPress post parent when found
	 */
	public function test_process_page_hierarchy_updates_post_parent(): void {
		$parent_notion_id = $this->test_page_ids['root_with_dashes'];
		$page_properties = array(
			'parent' => array(
				'type'    => 'page_id',
				'page_id' => $parent_notion_id,
			),
		);

		// Mock update_post_meta
		Functions\expect( 'update_post_meta' )
			->once()
			->with( 123, '_notion_parent_page_id', $parent_notion_id );

		// Mock finding parent post
		Functions\expect( 'get_posts' )
			->once()
			->with( \Mockery::on( function ( $args ) {
				return $args['meta_query'][0]['key'] === 'notion_page_id';
			} ) )
			->andReturn( array( 456 ) ); // Parent post ID

		// Mock wp_update_post to verify parent is set
		Functions\expect( 'wp_update_post' )
			->once()
			->with( array(
				'ID'          => 123,
				'post_parent' => 456,
			) )
			->andReturn( 123 );

		// Mock do_action for hierarchy_updated
		Functions\expect( 'do_action' )
			->once()
			->with( 'notion_hierarchy_updated', 123, 456, 'page-id', $parent_notion_id );

		$this->detector->process_page_hierarchy( 123, 'page-id', $page_properties );

		$this->assertTrue( true );
	}

	/**
	 * Test build_hierarchy_map returns empty array when root page not found
	 */
	public function test_build_hierarchy_map_returns_empty_when_root_not_found(): void {
		Functions\expect( 'get_posts' )
			->once()
			->andReturn( array() ); // No post found

		$hierarchy = $this->detector->build_hierarchy_map( 'nonexistent-page' );

		$this->assertIsArray( $hierarchy );
		$this->assertEmpty( $hierarchy );
	}

	/**
	 * Test build_hierarchy_map builds single root page
	 */
	public function test_build_hierarchy_map_single_root_page(): void {
		$root_id = $this->test_page_ids['root_no_dashes'];

		// Mock finding root post
		Functions\expect( 'get_posts' )
			->once()
			->andReturn( array( 100 ) );

		// Mock get_post to return root post object
		Functions\expect( 'get_post' )
			->once()
			->with( 100 )
			->andReturn( (object) array(
				'ID'         => 100,
				'post_title' => 'Root Page',
				'menu_order' => 0,
			) );

		// Mock get_posts for children search (none found)
		Functions\expect( 'get_posts' )
			->andReturn( array() );

		$hierarchy = $this->detector->build_hierarchy_map( $root_id );

		$this->assertIsArray( $hierarchy );
		$this->assertCount( 1, $hierarchy );
		$this->assertArrayHasKey( $root_id, $hierarchy );
		$this->assertEquals( 100, $hierarchy[ $root_id ]['post_id'] );
		$this->assertEquals( 'Root Page', $hierarchy[ $root_id ]['title'] );
		$this->assertNull( $hierarchy[ $root_id ]['parent_page_id'] );
		$this->assertEmpty( $hierarchy[ $root_id ]['children'] );
	}

	/**
	 * Test build_hierarchy_map respects max depth
	 */
	public function test_build_hierarchy_map_respects_max_depth(): void {
		$detector = new HierarchyDetector( 1 ); // Max depth of 1

		$root_id = $this->test_page_ids['root_no_dashes'];

		// Mock finding root post
		Functions\expect( 'get_posts' )
			->once()
			->andReturn( array( 100 ) );

		Functions\expect( 'get_post' )
			->once()
			->andReturn( (object) array(
				'ID'         => 100,
				'post_title' => 'Root Page',
				'menu_order' => 0,
			) );

		// Even if children exist, should not process them due to max depth
		Functions\expect( 'get_posts' )
			->andReturn( array() ); // No children search at max depth

		$hierarchy = $detector->build_hierarchy_map( $root_id );

		$this->assertCount( 1, $hierarchy );
		$this->assertEmpty( $hierarchy[ $root_id ]['children'] );
	}

	/**
	 * Test build_hierarchy_map with multi-level hierarchy
	 */
	public function test_build_hierarchy_map_multi_level(): void {
		$root_id   = $this->test_page_ids['root_no_dashes'];
		$child1_id = $this->test_page_ids['child1_no_dashes'];
		$child2_id = $this->test_page_ids['child2_no_dashes'];

		// Mock finding root post (first call)
		Functions\expect( 'get_posts' )
			->with( \Mockery::on( function ( $args ) use ( $root_id ) {
				$normalized = str_replace( '-', '', $root_id );
				return isset( $args['meta_query'][0]['value'] ) &&
					   $args['meta_query'][0]['value'] === $normalized;
			} ) )
			->andReturn( array( 100 ) );

		Functions\expect( 'get_post' )
			->with( 100 )
			->andReturn( (object) array(
				'ID'         => 100,
				'post_title' => 'Root Page',
				'menu_order' => 0,
			) );

		// Mock get_child_pages for root (returns child1)
		Functions\expect( 'get_posts' )
			->with( \Mockery::on( function ( $args ) {
				return isset( $args['meta_query'] ) &&
					   $args['meta_query'][0]['key'] === '_notion_parent_page_id';
			} ) )
			->andReturn( array( 101 ) );

		Functions\expect( 'get_post_meta' )
			->with( 101, 'notion_page_id', true )
			->andReturn( $child1_id );

		// Mock finding child1 post
		Functions\expect( 'get_posts' )
			->with( \Mockery::on( function ( $args ) use ( $child1_id ) {
				return isset( $args['meta_query'][0]['value'] ) &&
					   $args['meta_query'][0]['value'] === $child1_id;
			} ) )
			->andReturn( array( 101 ) );

		Functions\expect( 'get_post' )
			->with( 101 )
			->andReturn( (object) array(
				'ID'         => 101,
				'post_title' => 'Child 1 Page',
				'menu_order' => 0,
			) );

		$hierarchy = $this->detector->build_hierarchy_map( $root_id );

		$this->assertIsArray( $hierarchy );
		$this->assertArrayHasKey( $root_id, $hierarchy );
		$this->assertArrayHasKey( 'children', $hierarchy[ $root_id ] );
	}
}
