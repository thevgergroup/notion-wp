<?php
/**
 * Tests for HierarchyDetector class
 *
 * @package NotionWP
 * @subpackage Tests\Unit\Hierarchy
 */

declare(strict_types=1);

namespace NotionWP\Tests\Unit\Hierarchy;

use NotionWP\Hierarchy\HierarchyDetector;
use PHPUnit\Framework\TestCase;

/**
 * Class HierarchyDetectorTest
 *
 * @covers \NotionWP\Hierarchy\HierarchyDetector
 */
class HierarchyDetectorTest extends TestCase {
	/**
	 * HierarchyDetector instance
	 *
	 * @var HierarchyDetector
	 */
	private HierarchyDetector $detector;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->detector = new HierarchyDetector();
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
	 * Test get_child_pages returns empty array for page with no children
	 */
	public function test_get_child_pages_returns_empty_for_no_children(): void {
		$children = $this->detector->get_child_pages( 'page-123' );
		$this->assertIsArray( $children );
		$this->assertEmpty( $children );
	}

	/**
	 * Test build_hierarchy_map returns empty array initially
	 */
	public function test_build_hierarchy_map_returns_empty_initially(): void {
		$hierarchy = $this->detector->build_hierarchy_map( 'page-123' );
		$this->assertIsArray( $hierarchy );
		$this->assertEmpty( $hierarchy );
	}
}
