<?php
/**
 * Menu Builder
 *
 * Builds and updates WordPress navigation menus from Notion page hierarchy.
 * Preserves manually-added items and respects override flags.
 *
 * @package NotionWP
 * @subpackage Hierarchy
 */

declare(strict_types=1);

namespace NotionWP\Hierarchy;

/**
 * Class MenuBuilder
 *
 * Responsible for creating and updating WordPress navigation menus
 * based on the Notion page hierarchy while preserving manual modifications.
 */
class MenuBuilder {
	/**
	 * Create or update a WordPress navigation menu
	 *
	 * If the menu doesn't exist, it will be created.
	 * If it exists, it will be updated with the new hierarchy
	 * while preserving manually-added items.
	 *
	 * @param string $menu_name    Name of the menu to create/update.
	 * @param array  $hierarchy_map Hierarchy map from HierarchyDetector.
	 * @return int Menu ID, or 0 on failure.
	 */
	public function create_or_update_menu( string $menu_name, array $hierarchy_map ): int {
		// TODO: Implement menu creation/update
		return 0;
	}

	/**
	 * Add a page to a menu
	 *
	 * Creates a menu item for the given post and adds it to the menu.
	 * Handles nested items via parent_menu_item parameter.
	 *
	 * @param int $menu_id           Menu ID to add the item to.
	 * @param int $post_id           WordPress post ID to add.
	 * @param int $parent_menu_item  Parent menu item ID (0 for top-level).
	 * @return int Menu item ID, or 0 on failure.
	 */
	private function add_page_to_menu( int $menu_id, int $post_id, int $parent_menu_item = 0 ): int {
		// TODO: Implement menu item addition
		return 0;
	}

	/**
	 * Get manually-added items from a menu
	 *
	 * Returns an array of menu item IDs that were manually added by users
	 * (not synced from Notion) so they can be preserved during sync.
	 *
	 * @param int $menu_id Menu ID to check.
	 * @return array<int> Array of menu item IDs that are manual.
	 */
	public function preserve_manual_items( int $menu_id ): array {
		// TODO: Implement manual item detection
		return array();
	}
}
