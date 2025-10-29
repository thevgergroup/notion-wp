<?php
/**
 * Tests for MenuOverrideHandler class
 *
 * @package NotionWP
 * @subpackage Tests\Unit\Navigation
 */

declare(strict_types=1);

namespace NotionWP\Tests\Unit\Navigation;

use NotionWP\Navigation\MenuItemMeta;
use NotionWP\Navigation\MenuOverrideHandler;
use PHPUnit\Framework\TestCase;

/**
 * Class MenuOverrideHandlerTest
 *
 * @covers \NotionWP\Navigation\MenuOverrideHandler
 */
class MenuOverrideHandlerTest extends TestCase {
	/**
	 * MenuOverrideHandler instance
	 *
	 * @var MenuOverrideHandler
	 */
	private MenuOverrideHandler $handler;

	/**
	 * MenuItemMeta mock
	 *
	 * @var MenuItemMeta|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $meta_mock;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->meta_mock = $this->createMock( MenuItemMeta::class );
		$this->handler   = new MenuOverrideHandler( $this->meta_mock );
	}

	/**
	 * Test should_update_item returns true initially
	 */
	public function test_should_update_item_returns_true_initially(): void {
		$result = $this->handler->should_update_item( 1 );
		$this->assertTrue( $result );
	}

	/**
	 * Test merge_notion_and_manual_items returns empty array initially
	 */
	public function test_merge_notion_and_manual_items_returns_empty_initially(): void {
		$result = $this->handler->merge_notion_and_manual_items( array(), 1 );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}
}
