<?php
/**
 * Tests for MenuBuilder class
 *
 * @package NotionWP
 * @subpackage Tests\Unit\Hierarchy
 */

declare(strict_types=1);

namespace NotionWP\Tests\Unit\Hierarchy;

use NotionWP\Hierarchy\MenuBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Class MenuBuilderTest
 *
 * @covers \NotionWP\Hierarchy\MenuBuilder
 */
class MenuBuilderTest extends TestCase {
	/**
	 * MenuBuilder instance
	 *
	 * @var MenuBuilder
	 */
	private MenuBuilder $builder;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->builder = new MenuBuilder();
	}

	/**
	 * Test create_or_update_menu returns 0 initially
	 */
	public function test_create_or_update_menu_returns_zero_initially(): void {
		$menu_id = $this->builder->create_or_update_menu( 'Test Menu', array() );
		$this->assertEquals( 0, $menu_id );
	}

	/**
	 * Test preserve_manual_items returns empty array initially
	 */
	public function test_preserve_manual_items_returns_empty_initially(): void {
		$items = $this->builder->preserve_manual_items( 1 );
		$this->assertIsArray( $items );
		$this->assertEmpty( $items );
	}
}
