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

use NotionWP\Navigation\MenuItemMeta;

/**
 * Class MenuBuilder
 *
 * Responsible for creating and updating WordPress navigation menus
 * based on the Notion page hierarchy while preserving manual modifications.
 */
class MenuBuilder {
	/**
	 * MenuItemMeta instance for tracking sync status
	 *
	 * @var MenuItemMeta
	 */
	private MenuItemMeta $menu_item_meta;

	/**
	 * HierarchyDetector instance for building hierarchy maps
	 *
	 * @var HierarchyDetector
	 */
	private HierarchyDetector $hierarchy_detector;

	/**
	 * Constructor
	 *
	 * @param MenuItemMeta      $menu_item_meta      MenuItemMeta instance.
	 * @param HierarchyDetector $hierarchy_detector  HierarchyDetector instance.
	 */
	public function __construct( MenuItemMeta $menu_item_meta, HierarchyDetector $hierarchy_detector ) {
		$this->menu_item_meta      = $menu_item_meta;
		$this->hierarchy_detector  = $hierarchy_detector;
	}
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
		// Get or create the menu.
		$menu = wp_get_nav_menu_object( $menu_name );

		if ( ! $menu ) {
			$menu_id = wp_create_nav_menu( $menu_name );
			if ( is_wp_error( $menu_id ) ) {
				return 0;
			}
		} else {
			$menu_id = $menu->term_id;
		}

		// Get existing Notion-synced items to determine what needs updating.
		$existing_notion_items = $this->get_notion_synced_items( $menu_id );

		// Get manual items that should be preserved.
		$manual_items = $this->preserve_manual_items( $menu_id );

		// Remove Notion-synced items that will be re-added (unless overridden).
		foreach ( $existing_notion_items as $item_id ) {
			if ( ! $this->menu_item_meta->has_override( $item_id ) ) {
				wp_delete_post( $item_id, true );
			}
		}

		// Add pages from hierarchy map.
		$this->add_hierarchy_to_menu( $menu_id, $hierarchy_map );

		return $menu_id;
	}

	/**
	 * Add hierarchy to menu recursively
	 *
	 * @param int   $menu_id        Menu ID.
	 * @param array $hierarchy_map  Hierarchy map.
	 * @param int   $parent_item_id Parent menu item ID (0 for root).
	 * @return void
	 */
	private function add_hierarchy_to_menu( int $menu_id, array $hierarchy_map, int $parent_item_id = 0 ): void {
		// Find root items (items with no parent).
		$root_items = array();
		foreach ( $hierarchy_map as $page_id => $page_data ) {
			if ( empty( $page_data['parent_page_id'] ) ) {
				$root_items[ $page_id ] = $page_data;
			}
		}

		// Sort by menu order.
		uasort(
			$root_items,
			function ( $a, $b ) {
				return $a['order'] <=> $b['order'];
			}
		);

		// Add root items and their children recursively.
		foreach ( $root_items as $page_id => $page_data ) {
			$menu_item_id = $this->add_page_to_menu(
				$menu_id,
				$page_data['post_id'],
				$parent_item_id,
				$page_id
			);

			// Recursively add children.
			if ( ! empty( $page_data['children'] ) ) {
				foreach ( $page_data['children'] as $child_page_id ) {
					if ( isset( $hierarchy_map[ $child_page_id ] ) ) {
						$child_data = $hierarchy_map[ $child_page_id ];
						$child_menu_item_id = $this->add_page_to_menu(
							$menu_id,
							$child_data['post_id'],
							$menu_item_id,
							$child_page_id
						);

						// Continue recursion for grandchildren.
						if ( ! empty( $child_data['children'] ) ) {
							$this->add_child_items_recursively(
								$menu_id,
								$child_data['children'],
								$hierarchy_map,
								$child_menu_item_id
							);
						}
					}
				}
			}
		}
	}

	/**
	 * Recursively add child items to menu
	 *
	 * @param int   $menu_id          Menu ID.
	 * @param array $children         Array of child page IDs.
	 * @param array $hierarchy_map    Full hierarchy map.
	 * @param int   $parent_item_id   Parent menu item ID.
	 * @return void
	 */
	private function add_child_items_recursively( int $menu_id, array $children, array $hierarchy_map, int $parent_item_id ): void {
		foreach ( $children as $child_page_id ) {
			if ( isset( $hierarchy_map[ $child_page_id ] ) ) {
				$child_data = $hierarchy_map[ $child_page_id ];
				$child_menu_item_id = $this->add_page_to_menu(
					$menu_id,
					$child_data['post_id'],
					$parent_item_id,
					$child_page_id
				);

				// Continue recursion.
				if ( ! empty( $child_data['children'] ) ) {
					$this->add_child_items_recursively(
						$menu_id,
						$child_data['children'],
						$hierarchy_map,
						$child_menu_item_id
					);
				}
			}
		}
	}

	/**
	 * Add a page to a menu
	 *
	 * Creates a menu item for the given post and adds it to the menu.
	 * Handles nested items via parent_menu_item parameter.
	 *
	 * @param int    $menu_id          Menu ID to add the item to.
	 * @param int    $post_id          WordPress post ID to add.
	 * @param int    $parent_menu_item Parent menu item ID (0 for top-level).
	 * @param string $notion_page_id   Notion page ID for tracking.
	 * @return int Menu item ID, or 0 on failure.
	 */
	private function add_page_to_menu( int $menu_id, int $post_id, int $parent_menu_item = 0, string $notion_page_id = '' ): int {
		$menu_item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-object-id'   => $post_id,
				'menu-item-object'      => get_post_type( $post_id ),
				'menu-item-parent-id'   => $parent_menu_item,
				'menu-item-type'        => 'post_type',
				'menu-item-status'      => 'publish',
			)
		);

		if ( is_wp_error( $menu_item_id ) || ! $menu_item_id ) {
			return 0;
		}

		// Mark as Notion-synced if page ID provided.
		if ( ! empty( $notion_page_id ) ) {
			$this->menu_item_meta->mark_as_notion_synced( $menu_item_id, $notion_page_id );
		}

		return $menu_item_id;
	}

	/**
	 * Get Notion-synced menu items
	 *
	 * @param int $menu_id Menu ID.
	 * @return array<int> Array of menu item IDs that are Notion-synced.
	 */
	private function get_notion_synced_items( int $menu_id ): array {
		$menu_items = wp_get_nav_menu_items( $menu_id );
		if ( ! $menu_items ) {
			return array();
		}

		$notion_items = array();
		foreach ( $menu_items as $item ) {
			if ( $this->menu_item_meta->is_notion_synced( $item->ID ) ) {
				$notion_items[] = $item->ID;
			}
		}

		return $notion_items;
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
		$menu_items = wp_get_nav_menu_items( $menu_id );
		if ( ! $menu_items ) {
			return array();
		}

		$manual_items = array();
		foreach ( $menu_items as $item ) {
			if ( $this->menu_item_meta->is_manual( $item->ID ) ||
				( ! $this->menu_item_meta->is_notion_synced( $item->ID ) && ! $this->menu_item_meta->is_manual( $item->ID ) ) ) {
				// Item is either explicitly marked as manual, or it's neither Notion-synced nor manual (legacy item).
				$manual_items[] = $item->ID;
			}
		}

		return $manual_items;
	}
}
