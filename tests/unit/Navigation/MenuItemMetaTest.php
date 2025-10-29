<?php
/**
 * Tests for MenuItemMeta class
 *
 * @package NotionWP
 * @subpackage Tests\Unit\Navigation
 */

declare(strict_types=1);

namespace NotionWP\Tests\Unit\Navigation;

use NotionWP\Navigation\MenuItemMeta;
use PHPUnit\Framework\TestCase;

/**
 * Class MenuItemMetaTest
 *
 * @covers \NotionWP\Navigation\MenuItemMeta
 */
class MenuItemMetaTest extends TestCase {
	/**
	 * MenuItemMeta instance
	 *
	 * @var MenuItemMeta
	 */
	private MenuItemMeta $meta;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->meta = new MenuItemMeta();
	}

	/**
	 * Test is_notion_synced returns false initially
	 */
	public function test_is_notion_synced_returns_false_initially(): void {
		$result = $this->meta->is_notion_synced( 1 );
		$this->assertFalse( $result );
	}

	/**
	 * Test has_override returns false initially
	 */
	public function test_has_override_returns_false_initially(): void {
		$result = $this->meta->has_override( 1 );
		$this->assertFalse( $result );
	}

	/**
	 * Test get_notion_page_id returns null initially
	 */
	public function test_get_notion_page_id_returns_null_initially(): void {
		$result = $this->meta->get_notion_page_id( 1 );
		$this->assertNull( $result );
	}

	/**
	 * Test is_manual returns false initially
	 */
	public function test_is_manual_returns_false_initially(): void {
		$result = $this->meta->is_manual( 1 );
		$this->assertFalse( $result );
	}
}
