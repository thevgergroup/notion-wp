<?php
/**
 * Menu Item Meta
 *
 * Handles metadata for menu items including Notion sync flags,
 * override settings, and page ID tracking.
 *
 * @package NotionWP
 * @subpackage Navigation
 */

declare(strict_types=1);

namespace NotionWP\Navigation;

/**
 * Class MenuItemMeta
 *
 * Manages custom metadata for menu items to track:
 * - Whether an item is synced from Notion
 * - User override preferences
 * - Notion page ID associations
 */
class MenuItemMeta {
	/**
	 * Meta key for Notion synced flag
	 */
	private const META_NOTION_SYNCED = '_notion_synced';

	/**
	 * Meta key for Notion page ID
	 */
	private const META_NOTION_PAGE_ID = '_notion_page_id';

	/**
	 * Meta key for override flag
	 */
	private const META_NOTION_OVERRIDE = '_notion_override';

	/**
	 * Meta key for manual item flag
	 */
	private const META_MANUAL_ITEM = '_manual_item';

	/**
	 * Mark a menu item as synced from Notion
	 *
	 * @param int    $item_id        Menu item ID.
	 * @param string $notion_page_id Notion page ID.
	 * @return void
	 */
	public function mark_as_notion_synced( int $item_id, string $notion_page_id ): void {
		// TODO: Implement Notion sync marking
	}

	/**
	 * Check if a menu item is synced from Notion
	 *
	 * @param int $item_id Menu item ID.
	 * @return bool True if synced from Notion, false otherwise.
	 */
	public function is_notion_synced( int $item_id ): bool {
		// TODO: Implement Notion sync check
		return false;
	}

	/**
	 * Set override flag for a menu item
	 *
	 * When true, prevents Notion from updating this item during sync.
	 *
	 * @param int  $item_id  Menu item ID.
	 * @param bool $override Whether to enable override.
	 * @return void
	 */
	public function set_override( int $item_id, bool $override ): void {
		// TODO: Implement override setting
	}

	/**
	 * Check if a menu item has override enabled
	 *
	 * @param int $item_id Menu item ID.
	 * @return bool True if override is enabled, false otherwise.
	 */
	public function has_override( int $item_id ): bool {
		// TODO: Implement override check
		return false;
	}

	/**
	 * Get the Notion page ID for a menu item
	 *
	 * @param int $item_id Menu item ID.
	 * @return string|null Notion page ID, or null if not set.
	 */
	public function get_notion_page_id( int $item_id ): ?string {
		// TODO: Implement Notion page ID retrieval
		return null;
	}

	/**
	 * Mark a menu item as manually added
	 *
	 * @param int $item_id Menu item ID.
	 * @return void
	 */
	public function mark_as_manual( int $item_id ): void {
		// TODO: Implement manual item marking
	}

	/**
	 * Check if a menu item was manually added
	 *
	 * @param int $item_id Menu item ID.
	 * @return bool True if manually added, false otherwise.
	 */
	public function is_manual( int $item_id ): bool {
		// TODO: Implement manual item check
		return false;
	}
}
