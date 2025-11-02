<?php
/**
 * Block Patterns for Notion Sync
 *
 * Provides pre-configured block patterns for displaying Notion content,
 * particularly navigation menus and hierarchies.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks;

/**
 * Class Patterns
 *
 * Registers and manages block patterns for Notion content.
 *
 * @since 1.0.0
 */
class Patterns {

	/**
	 * Register WordPress hooks.
	 *
	 * Called from the main plugin init hook, so we register immediately
	 * instead of deferring to another init action.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		$this->register_pattern_category();
		$this->register_patterns();
	}

	/**
	 * Register custom pattern category for Notion patterns.
	 *
	 * Creates a dedicated category in the block pattern inserter
	 * for all Notion Sync related patterns.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_pattern_category(): void {
		if ( ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}

		register_block_pattern_category(
			'notion-sync',
			array(
				'label'       => __( 'Notion Sync', 'notion-wp' ),
				'description' => __( 'Patterns for displaying Notion content and navigation.', 'notion-wp' ),
			)
		);
	}

	/**
	 * Register all block patterns.
	 *
	 * Registers pre-configured patterns for Notion content display.
	 * Currently includes navigation hierarchy pattern.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_patterns(): void {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		// Register navigation hierarchy pattern.
		$this->register_navigation_pattern();
	}

	/**
	 * Register Notion Navigation Hierarchy pattern.
	 *
	 * Creates a sidebar-friendly pattern that displays the Notion menu
	 * as a hierarchical navigation. Compatible with Twenty Twenty-Four
	 * and Twenty Twenty-Five themes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_navigation_pattern(): void {
		// Get the Notion menu name from settings.
		$menu_name = get_option( 'notion_sync_menu_name', 'Notion Navigation' );
		$menu      = wp_get_nav_menu_object( $menu_name );

		// Only register pattern if the Notion menu exists.
		if ( ! $menu ) {
			return;
		}

		// Build navigation block markup.
		$pattern_content = $this->get_navigation_pattern_content( $menu->term_id );

		register_block_pattern(
			'notion-sync/navigation-hierarchy',
			array(
				'title'       => __( 'Notion Navigation Hierarchy', 'notion-wp' ),
				'description' => __( 'Display your Notion pages as a hierarchical navigation menu. Perfect for sidebars and documentation sites.', 'notion-wp' ),
				'content'     => $pattern_content,
				'categories'  => array( 'notion-sync', 'featured' ),
				'keywords'    => array( 'notion', 'navigation', 'menu', 'sidebar', 'hierarchy' ),
				'viewportWidth' => 400,
				'blockTypes'  => array( 'core/navigation' ),
			)
		);
	}

	/**
	 * Get navigation pattern content markup.
	 *
	 * Generates the block markup for the navigation hierarchy pattern.
	 * Uses core/navigation block with nested menu items.
	 *
	 * @since 1.0.0
	 *
	 * @param int $menu_id WordPress menu ID.
	 * @return string Block markup for the pattern.
	 */
	private function get_navigation_pattern_content( int $menu_id ): string {
		// Get menu name for the ref attribute.
		$menu      = wp_get_nav_menu_object( $menu_id );
		$menu_slug = $menu ? $menu->slug : '';

		// Build the pattern with Group > Heading + Navigation.
		$pattern = '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:heading {"level":3,"fontSize":"medium"} -->
	<h3 class="wp-block-heading has-medium-font-size">' . esc_html__( 'Notion Pages', 'notion-wp' ) . '</h3>
	<!-- /wp:heading -->

	<!-- wp:navigation {"ref":' . absint( $menu_id ) . ',"overlayMenu":"never","layout":{"type":"flex","orientation":"vertical"},"style":{"spacing":{"blockGap":"0.5rem"}}} /-->
</div>
<!-- /wp:group -->';

		return $pattern;
	}

	/**
	 * Get list-based navigation pattern content (fallback).
	 *
	 * Alternative pattern using core/list instead of core/navigation.
	 * Useful for themes that don't fully support Navigation block.
	 *
	 * @since 1.0.0
	 *
	 * @param int $menu_id WordPress menu ID.
	 * @return string Block markup for the list-based pattern.
	 */
	private function get_list_pattern_content( int $menu_id ): string {
		$menu_items = wp_get_nav_menu_items( $menu_id );

		if ( empty( $menu_items ) ) {
			return '<!-- wp:paragraph -->
<p>' . esc_html__( 'No Notion pages found. Please sync pages from Notion first.', 'notion-wp' ) . '</p>
<!-- /wp:paragraph -->';
		}

		// Build hierarchical list markup.
		$list_html = $this->build_hierarchical_list( $menu_items );

		$pattern = '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:heading {"level":3,"fontSize":"medium"} -->
	<h3 class="wp-block-heading has-medium-font-size">' . esc_html__( 'Notion Pages', 'notion-wp' ) . '</h3>
	<!-- /wp:heading -->

	' . $list_html . '
</div>
<!-- /wp:group -->';

		return $pattern;
	}

	/**
	 * Build hierarchical list markup from menu items.
	 *
	 * Creates nested HTML list markup from flat menu items array.
	 * Respects parent-child relationships.
	 *
	 * @since 1.0.0
	 *
	 * @param array $menu_items Array of menu item objects.
	 * @param int   $parent_id  Parent menu item ID (0 for root).
	 * @return string HTML markup for the list.
	 */
	private function build_hierarchical_list( array $menu_items, int $parent_id = 0 ): string {
		$html = '';

		// Get items at this level.
		$items_at_level = array_filter(
			$menu_items,
			function ( $item ) use ( $parent_id ) {
				return (int) $item->menu_item_parent === $parent_id;
			}
		);

		if ( empty( $items_at_level ) ) {
			return $html;
		}

		$html .= '<!-- wp:list --><ul>';

		foreach ( $items_at_level as $item ) {
			$html .= '<!-- wp:list-item -->';
			$html .= '<li><a href="' . esc_url( $item->url ) . '">' . esc_html( $item->title ) . '</a>';

			// Recursively build children.
			$children = $this->build_hierarchical_list( $menu_items, (int) $item->ID );
			if ( ! empty( $children ) ) {
				$html .= $children;
			}

			$html .= '</li>';
			$html .= '<!-- /wp:list-item -->';
		}

		$html .= '</ul><!-- /wp:list -->';

		return $html;
	}
}
