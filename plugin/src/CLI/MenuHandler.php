<?php
/**
 * Menu Handler for WP-CLI
 *
 * Handles menu-related CLI commands for debugging and diagnostics.
 *
 * @package NotionSync
 * @since 0.2.0-dev
 */

namespace NotionSync\CLI;

use NotionWP\Hierarchy\HierarchyDetector;
use NotionWP\Hierarchy\MenuBuilder;
use NotionWP\Navigation\MenuItemMeta;

/**
 * Menu Handler Class
 *
 * Provides CLI commands for diagnosing menu sync issues.
 */
class MenuHandler {

	/**
	 * Debug menu hierarchy and sync status
	 *
	 * Displays detailed information about the current page hierarchy
	 * and menu sync state for diagnostics.
	 *
	 * @param bool $verbose Show detailed output.
	 * @return void
	 */
	public static function debug_menu( bool $verbose = false ): void {
		\WP_CLI::log( \WP_CLI::colorize( '%G' . str_repeat( '=', 70 ) . '%n' ) );
		\WP_CLI::log( \WP_CLI::colorize( '%GMENU SYNC DIAGNOSTICS%n' ) );
		\WP_CLI::log( \WP_CLI::colorize( '%G' . str_repeat( '=', 70 ) . '%n' ) );
		\WP_CLI::log( '' );

		// Check if any pages are synced.
		self::check_synced_pages( $verbose );

		// Find root pages.
		$root_pages = self::find_root_pages();
		\WP_CLI::log( '' );
		\WP_CLI::log( \WP_CLI::colorize( '%BRoot Pages Found:%n ' . count( $root_pages ) ) );

		if ( empty( $root_pages ) ) {
			\WP_CLI::warning( 'No root pages found. Pages may not have parent relationships set.' );
			\WP_CLI::log( '' );
			return;
		}

		// Display root pages.
		foreach ( $root_pages as $root_page_id ) {
			$post_id = self::find_post_by_notion_id( $root_page_id );
			if ( $post_id ) {
				$post = get_post( $post_id );
				\WP_CLI::log( sprintf( '  - %s (WP ID: %d, Notion ID: %s)', $post->post_title, $post_id, $root_page_id ) );
			}
		}

		\WP_CLI::log( '' );

		// Build hierarchy maps for each root.
		$hierarchy_detector = new HierarchyDetector();
		$combined_map       = array();

		foreach ( $root_pages as $root_page_id ) {
			\WP_CLI::log( \WP_CLI::colorize( '%YBuilding hierarchy for root: ' . $root_page_id . '%n' ) );

			$hierarchy_map = $hierarchy_detector->build_hierarchy_map( $root_page_id );

			if ( empty( $hierarchy_map ) ) {
				\WP_CLI::warning( 'Failed to build hierarchy map for this root.' );
				continue;
			}

			\WP_CLI::log( sprintf( 'Found %d pages in this hierarchy:', count( $hierarchy_map ) ) );

			// Display hierarchy tree.
			self::display_hierarchy_tree( $hierarchy_map, $root_page_id, 0, $verbose );

			$combined_map = array_merge( $combined_map, $hierarchy_map );

			\WP_CLI::log( '' );
		}

		// Summary.
		\WP_CLI::log( \WP_CLI::colorize( '%G' . str_repeat( '-', 70 ) . '%n' ) );
		\WP_CLI::log( \WP_CLI::colorize( '%BSummary:%n' ) );
		\WP_CLI::log( sprintf( 'Total root pages: %d', count( $root_pages ) ) );
		\WP_CLI::log( sprintf( 'Total pages in hierarchy: %d', count( $combined_map ) ) );

		// Check current menu.
		$menu_name = get_option( 'notion_sync_menu_name', 'Notion Navigation' );
		$menu      = wp_get_nav_menu_object( $menu_name );

		if ( $menu ) {
			$menu_items = wp_get_nav_menu_items( $menu->term_id );
			\WP_CLI::log( sprintf( 'Current menu "%s" has %d items', $menu_name, is_array( $menu_items ) ? count( $menu_items ) : 0 ) );
		} else {
			\WP_CLI::log( sprintf( 'Menu "%s" does not exist yet', $menu_name ) );
		}

		\WP_CLI::log( \WP_CLI::colorize( '%G' . str_repeat( '=', 70 ) . '%n' ) );
	}

	/**
	 * Sync menu now (same logic as AJAX handler)
	 *
	 * @return void
	 */
	public static function sync_menu_now(): void {
		\WP_CLI::log( 'Starting menu sync...' );

		$menu_name          = get_option( 'notion_sync_menu_name', 'Notion Navigation' );
		$hierarchy_detector = new HierarchyDetector();
		$menu_item_meta     = new MenuItemMeta();
		$menu_builder       = new MenuBuilder( $menu_item_meta, $hierarchy_detector );

		// Find root pages.
		$root_pages = self::find_root_pages();

		if ( empty( $root_pages ) ) {
			\WP_CLI::error( 'No root pages found. Please sync some pages from Notion first.' );
		}

		\WP_CLI::log( sprintf( 'Found %d root pages', count( $root_pages ) ) );

		// Build combined hierarchy map.
		$combined_hierarchy_map = array();
		foreach ( $root_pages as $root_page_id ) {
			$hierarchy_map = $hierarchy_detector->build_hierarchy_map( $root_page_id );
			if ( ! empty( $hierarchy_map ) ) {
				$combined_hierarchy_map = array_merge( $combined_hierarchy_map, $hierarchy_map );
			}
		}

		if ( empty( $combined_hierarchy_map ) ) {
			\WP_CLI::error( 'Failed to build hierarchy map. No pages found.' );
		}

		\WP_CLI::log( sprintf( 'Built hierarchy map with %d total pages', count( $combined_hierarchy_map ) ) );

		// Create or update menu.
		$menu_id = $menu_builder->create_or_update_menu( $menu_name, $combined_hierarchy_map );

		if ( 0 === $menu_id ) {
			\WP_CLI::error( 'Failed to create or update menu.' );
		}

		// Count menu items.
		$menu_items = wp_get_nav_menu_items( $menu_id );
		$item_count = is_array( $menu_items ) ? count( $menu_items ) : 0;

		\WP_CLI::success( sprintf( 'Menu "%s" updated with %d items (Menu ID: %d)', $menu_name, $item_count, $menu_id ) );
	}

	/**
	 * Display hierarchy tree recursively
	 *
	 * @param array  $hierarchy_map Full hierarchy map.
	 * @param string $page_id       Current page ID to display.
	 * @param int    $depth         Current depth level.
	 * @param bool   $verbose       Show detailed info.
	 * @return void
	 */
	private static function display_hierarchy_tree( array $hierarchy_map, string $page_id, int $depth = 0, bool $verbose = false ): void {
		if ( ! isset( $hierarchy_map[ $page_id ] ) ) {
			return;
		}

		$page_data = $hierarchy_map[ $page_id ];
		$indent    = str_repeat( '  ', $depth );
		$prefix    = $depth > 0 ? '└─ ' : '';

		$post = get_post( $page_data['post_id'] );
		if ( ! $post ) {
			return;
		}

		\WP_CLI::log(
			sprintf(
				'%s%s%s (WP ID: %d, Order: %d)',
				$indent,
				$prefix,
				$post->post_title,
				$page_data['post_id'],
				$page_data['order']
			)
		);

		if ( $verbose ) {
			\WP_CLI::log( sprintf( '%s   Notion ID: %s', $indent, $page_id ) );
			\WP_CLI::log( sprintf( '%s   Parent: %s', $indent, $page_data['parent_page_id'] ?? 'none' ) );
			\WP_CLI::log( sprintf( '%s   Children: %d', $indent, count( $page_data['children'] ) ) );
		}

		// Recursively display children.
		if ( ! empty( $page_data['children'] ) ) {
			foreach ( $page_data['children'] as $child_id ) {
				self::display_hierarchy_tree( $hierarchy_map, $child_id, $depth + 1, $verbose );
			}
		}
	}

	/**
	 * Check synced pages
	 *
	 * @param bool $verbose Show detailed output.
	 * @return void
	 */
	private static function check_synced_pages( bool $verbose ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total_synced = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'notion_page_id'
			)
		);

		\WP_CLI::log( \WP_CLI::colorize( '%BTotal Synced Pages:%n ' . $total_synced ) );

		if ( $verbose && $total_synced > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$synced_posts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title, pm.meta_value as notion_id, pm2.meta_value as parent_id
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
					LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = %s
					ORDER BY p.ID",
					'notion_page_id',
					'_notion_parent_page_id'
				)
			);

			\WP_CLI::log( '' );
			\WP_CLI::log( 'Synced Pages:' );
			foreach ( $synced_posts as $post ) {
				$parent_info = $post->parent_id ? ' (Parent: ' . $post->parent_id . ')' : ' (Root)';
				\WP_CLI::log( sprintf( '  - %s (ID: %d, Notion: %s)%s', $post->post_title, $post->ID, $post->notion_id, $parent_info ) );
			}
		}
	}

	/**
	 * Find all root pages (pages with no parent)
	 *
	 * @return array<string> Array of Notion page IDs.
	 */
	private static function find_root_pages(): array {
		global $wpdb;

		// Find all posts with notion_page_id meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$posts_with_notion_id = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
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
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
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

	/**
	 * Find WordPress post by Notion page ID
	 *
	 * @param string $notion_page_id Notion page ID.
	 * @return int|null Post ID if found, null otherwise.
	 */
	private static function find_post_by_notion_id( string $notion_page_id ): ?int {
		$normalized_id = str_replace( '-', '', $notion_page_id );

		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'notion_page_id',
						'value'   => $normalized_id,
						'compare' => '=',
					),
				),
			)
		);

		return ! empty( $posts ) ? $posts[0] : null;
	}
}
