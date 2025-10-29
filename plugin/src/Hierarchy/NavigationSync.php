<?php
/**
 * Navigation Sync
 *
 * Coordinates menu building after hierarchy updates.
 *
 * @package NotionWP
 * @subpackage Hierarchy
 */

declare(strict_types=1);

namespace NotionWP\Hierarchy;

/**
 * Class NavigationSync
 *
 * Listens for hierarchy updates and triggers menu building.
 */
class NavigationSync {
	/**
	 * MenuBuilder instance
	 *
	 * @var MenuBuilder
	 */
	private MenuBuilder $menu_builder;

	/**
	 * HierarchyDetector instance
	 *
	 * @var HierarchyDetector
	 */
	private HierarchyDetector $hierarchy_detector;

	/**
	 * Whether menu sync is enabled
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Target menu name for sync
	 *
	 * @var string
	 */
	private string $menu_name;

	/**
	 * Constructor
	 *
	 * @param MenuBuilder       $menu_builder       MenuBuilder instance.
	 * @param HierarchyDetector $hierarchy_detector HierarchyDetector instance.
	 */
	public function __construct( MenuBuilder $menu_builder, HierarchyDetector $hierarchy_detector ) {
		$this->menu_builder       = $menu_builder;
		$this->hierarchy_detector = $hierarchy_detector;

		// Get settings from WordPress options.
		$this->enabled   = (bool) get_option( 'notion_sync_menu_enabled', true );
		$this->menu_name = get_option( 'notion_sync_menu_name', 'Notion Navigation' );
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! $this->enabled ) {
			return;
		}

		// Hook into hierarchy updates.
		add_action( 'notion_hierarchy_updated', array( $this, 'trigger_menu_sync' ), 10, 4 );
	}

	/**
	 * Trigger menu sync after hierarchy update
	 *
	 * This method is called after a page's parent relationship has been updated.
	 * It builds the complete hierarchy and updates the menu.
	 *
	 * @param int    $post_id          Child post ID.
	 * @param int    $parent_post_id   Parent post ID.
	 * @param string $notion_page_id   Child Notion page ID.
	 * @param string $parent_notion_id Parent Notion page ID.
	 * @return void
	 */
	public function trigger_menu_sync( int $post_id, int $parent_post_id, string $notion_page_id, string $parent_notion_id ): void {
		// Find the root page by traversing up the hierarchy.
		$root_page_id = $this->find_root_page( $parent_notion_id );

		// Build hierarchy map from root.
		$hierarchy_map = $this->hierarchy_detector->build_hierarchy_map( $root_page_id );

		if ( empty( $hierarchy_map ) ) {
			return;
		}

		// Update menu.
		$menu_id = $this->menu_builder->create_or_update_menu( $this->menu_name, $hierarchy_map );

		if ( $menu_id > 0 ) {
			/**
			 * Fires after menu has been synced from Notion hierarchy.
			 *
			 * @since 0.2.0-dev
			 *
			 * @param int    $menu_id        Menu ID that was updated.
			 * @param string $menu_name      Menu name.
			 * @param array  $hierarchy_map  Hierarchy map used for sync.
			 */
			do_action( 'notion_menu_synced', $menu_id, $this->menu_name, $hierarchy_map );
		}
	}

	/**
	 * Find the root page of a hierarchy
	 *
	 * Traverses up the parent chain to find the topmost page.
	 *
	 * @param string $page_id Notion page ID.
	 * @param int    $max_iterations Maximum iterations to prevent infinite loops.
	 * @return string Root page ID.
	 */
	private function find_root_page( string $page_id, int $max_iterations = 10 ): string {
		$current_id = $page_id;
		$iterations = 0;

		while ( $iterations < $max_iterations ) {
			// Normalize ID.
			$normalized_id = str_replace( '-', '', $current_id );

			// Get post for current page.
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

			if ( empty( $posts ) ) {
				break;
			}

			$post_id = $posts[0];

			// Check if this page has a parent.
			$parent_notion_id = get_post_meta( $post_id, '_notion_parent_page_id', true );

			if ( empty( $parent_notion_id ) ) {
				// This is the root.
				return $current_id;
			}

			// Move up to parent.
			$current_id = $parent_notion_id;
			++$iterations;
		}

		// Return the last known ID if we hit max iterations.
		return $current_id;
	}
}
