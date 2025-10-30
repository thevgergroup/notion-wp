<?php
/**
 * Navigation AJAX Handler
 *
 * Handles AJAX requests for menu synchronization operations.
 *
 * @package NotionWP
 * @subpackage Admin
 * @since 0.2.0-dev
 */

declare(strict_types=1);

namespace NotionWP\Admin;

use NotionWP\Hierarchy\HierarchyDetector;
use NotionWP\Hierarchy\MenuBuilder;
use NotionWP\Navigation\MenuItemMeta;

/**
 * Class NavigationAjaxHandler
 *
 * Manages AJAX endpoints for navigation menu synchronization.
 * Follows patterns established by SyncAjaxHandler and DatabaseAjaxHandler.
 *
 * @since 0.2.0-dev
 */
class NavigationAjaxHandler {

	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.2.0-dev
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_notion_sync_menu_now', array( $this, 'ajax_sync_menu_now' ) );
	}

	/**
	 * AJAX handler for manual menu sync.
	 *
	 * Finds all root pages (pages with no parent), builds hierarchy maps,
	 * and triggers menu sync using MenuBuilder.
	 *
	 * Security:
	 * - Verifies nonce: 'notion_sync_menu_now'
	 * - Checks user capability: 'manage_options'
	 *
	 * Response format:
	 * Success: {
	 *   message: "Menu 'Notion Navigation' updated with 5 items",
	 *   menu_id: 123,
	 *   item_count: 5
	 * }
	 *
	 * Error: {
	 *   message: "Error message"
	 * }
	 *
	 * @since 0.2.0-dev
	 * @return void
	 */
	public function ajax_sync_menu_now(): void {
		// Verify nonce.
		check_ajax_referer( 'notion_sync_menu_now', 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions to sync menu.', 'notion-wp' ),
				),
				403
			);
		}

		try {
			// Get menu name from settings.
			$menu_name = get_option( 'notion_sync_menu_name', 'Notion Navigation' );

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[NavigationAjax] Starting menu sync. Menu name: ' . $menu_name );

			// Initialize dependencies.
			$hierarchy_detector = new HierarchyDetector();
			$menu_item_meta     = new MenuItemMeta();
			$menu_builder       = new MenuBuilder( $menu_item_meta, $hierarchy_detector );

			// Find all root pages (pages with no parent).
			$root_pages = $this->find_root_pages();

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[NavigationAjax] Found ' . count( $root_pages ) . ' root pages: ' . wp_json_encode( $root_pages ) );

			if ( empty( $root_pages ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( '[NavigationAjax] No root pages found - returning error' );
				wp_send_json_error(
					array(
						'message' => __(
							'No root pages found. Please sync some pages from Notion first.',
							'notion-wp'
						),
					),
					404
				);
			}

			// Build combined hierarchy map from all root pages.
			$combined_hierarchy_map = array();
			foreach ( $root_pages as $root_page_id ) {
				$hierarchy_map = $hierarchy_detector->build_hierarchy_map( $root_page_id );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( '[NavigationAjax] Hierarchy map for root ' . $root_page_id . ': ' . wp_json_encode( $hierarchy_map ) );
				if ( ! empty( $hierarchy_map ) ) {
					$combined_hierarchy_map = array_merge( $combined_hierarchy_map, $hierarchy_map );
				}
			}

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[NavigationAjax] Combined hierarchy map (' . count( $combined_hierarchy_map ) . ' items): ' . wp_json_encode( array_keys( $combined_hierarchy_map ) ) );

			if ( empty( $combined_hierarchy_map ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Failed to build hierarchy map. No pages found.', 'notion-wp' ),
					),
					500
				);
			}

			// Create or update the menu.
			$menu_id = $menu_builder->create_or_update_menu( $menu_name, $combined_hierarchy_map );

			if ( 0 === $menu_id ) {
				wp_send_json_error(
					array(
						'message' => __( 'Failed to create or update menu.', 'notion-wp' ),
					),
					500
				);
			}

			// Count menu items.
			$menu_items = wp_get_nav_menu_items( $menu_id );
			$item_count = is_array( $menu_items ) ? count( $menu_items ) : 0;

			// Update last sync time for meta box display.
			update_option( 'notion_menu_last_sync_time', time() );

			// Build success message with next steps.
			$menus_url      = admin_url( 'nav-menus.php?action=edit&menu=' . $menu_id );
			$success_parts  = array();
			$success_parts[] = sprintf(
				/* translators: 1: menu name, 2: number of items */
				__( 'Menu "%1$s" updated with %2$d items.', 'notion-wp' ),
				esc_html( $menu_name ),
				$item_count
			);

			// Check if theme supports menus.
			$theme_supports_menus = current_theme_supports( 'menus' );
			$menu_locations       = get_registered_nav_menus();

			if ( $theme_supports_menus && ! empty( $menu_locations ) ) {
				// Theme supports menus - show normal assignment instructions.
				$success_parts[] = wp_kses(
					sprintf(
						/* translators: %s: URL to menu editor */
						__( '<a href="%s" target="_blank">View &amp; assign menu</a> in Appearance &rarr; Menus.', 'notion-wp' ),
						esc_url( $menus_url )
					),
					array(
						'a' => array(
							'href'   => array(),
							'target' => array(),
						),
					)
				);
			} else {
				// Theme doesn't support menus - show alternative guidance.
				$success_parts[] = wp_kses(
					sprintf(
						/* translators: %s: URL to menu editor */
						__( '<a href="%s" target="_blank">View menu</a>. Note: Your theme does not support menu locations, so you cannot assign this menu without additional theme configuration.', 'notion-wp' ),
						esc_url( $menus_url )
					),
					array(
						'a' => array(
							'href'   => array(),
							'target' => array(),
						),
					)
				);
			}

			if ( $item_count > 0 ) {
				$success_parts[] = __( 'Menu will auto-update as you sync more pages from Notion.', 'notion-wp' );
			}

			// Send success response.
			wp_send_json_success(
				array(
					'message'    => implode( ' ', $success_parts ),
					'menu_id'    => $menu_id,
					'item_count' => $item_count,
					'menu_url'   => $menus_url,
				)
			);

		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging.
			error_log( '[NavigationAjax] Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging.
			error_log( '[NavigationAjax] Stack trace: ' . $e->getTraceAsString() );

			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Menu sync failed: %s', 'notion-wp' ),
						$e->getMessage()
					),
				),
				500
			);
		}
	}

	/**
	 * Find all root pages (pages with no parent)
	 *
	 * Queries WordPress for posts that have the notion_page_id meta
	 * but do NOT have the _notion_parent_page_id meta.
	 *
	 * @since 0.2.0-dev
	 * @return array<string> Array of Notion page IDs that are root pages.
	 */
	private function find_root_pages(): array {
		global $wpdb;

		// Find all posts with notion_page_id meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$posts_with_notion_id = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id
				FROM {$wpdb->postmeta}
				WHERE meta_key = %s",
				'notion_page_id'
			)
		);

		if ( empty( $posts_with_notion_id ) ) {
			return array();
		}

		// Find posts that have a parent.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$posts_with_parent = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id
				FROM {$wpdb->postmeta}
				WHERE meta_key = %s
				AND meta_value != ''",
				'_notion_parent_page_id'
			)
		);

		// Root pages are those with notion_page_id but without _notion_parent_page_id.
		$root_post_ids = array_diff( $posts_with_notion_id, $posts_with_parent );

		// Get the Notion page IDs for each root post.
		$root_page_ids = array();
		foreach ( $root_post_ids as $post_id ) {
			$notion_page_id = get_post_meta( (int) $post_id, 'notion_page_id', true );
			if ( ! empty( $notion_page_id ) ) {
				$root_page_ids[] = $notion_page_id;
			}
		}

		return $root_page_ids;
	}
}
