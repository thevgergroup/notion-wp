<?php
/**
 * Tests for MenuManager class
 *
 * @package NotionWP
 * @subpackage Tests\Unit\Navigation
 */

declare(strict_types=1);

namespace NotionWP\Tests\Unit\Navigation;

use NotionWP\Navigation\MenuManager;
use PHPUnit\Framework\TestCase;

/**
 * Class MenuManagerTest
 *
 * @covers \NotionWP\Navigation\MenuManager
 */
class MenuManagerTest extends TestCase {
	/**
	 * MenuManager instance
	 *
	 * @var MenuManager
	 */
	private MenuManager $manager;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->manager = new MenuManager();
	}

	/**
	 * Test list_menus returns empty array initially
	 */
	public function test_list_menus_returns_empty_initially(): void {
		$menus = $this->manager->list_menus();
		$this->assertIsArray( $menus );
		$this->assertEmpty( $menus );
	}

	/**
	 * Test get_menu_items returns empty array initially
	 */
	public function test_get_menu_items_returns_empty_initially(): void {
		$items = $this->manager->get_menu_items( 1 );
		$this->assertIsArray( $items );
		$this->assertEmpty( $items );
	}

	/**
	 * Test add_manual_item returns 0 initially
	 */
	public function test_add_manual_item_returns_zero_initially(): void {
		$item_id = $this->manager->add_manual_item( 1, array() );
		$this->assertEquals( 0, $item_id );
	}

	/**
	 * Test update_item returns false initially
	 */
	public function test_update_item_returns_false_initially(): void {
		$result = $this->manager->update_item( 1, array() );
		$this->assertFalse( $result );
	}

	/**
	 * Test delete_item returns false initially
	 */
	public function test_delete_item_returns_false_initially(): void {
		$result = $this->manager->delete_item( 1 );
		$this->assertFalse( $result );
	}

	/**
	 * Test reorder_items returns false initially
	 */
	public function test_reorder_items_returns_false_initially(): void {
		$result = $this->manager->reorder_items( 1, array() );
		$this->assertFalse( $result );
	}
}
