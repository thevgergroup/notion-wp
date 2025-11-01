<?php
/**
 * Hierarchy Detector
 *
 * Detects and builds hierarchical relationships between Notion pages
 * using parent information from the Notion API.
 *
 * @package NotionWP
 * @subpackage Hierarchy
 */

declare(strict_types=1);

namespace NotionWP\Hierarchy;

/**
 * Class HierarchyDetector
 *
 * Responsible for detecting parent-child relationships in Notion pages
 * and building a hierarchy map that can be used to set WordPress post parents.
 */
class HierarchyDetector {
	/**
	 * Maximum depth for hierarchy to prevent infinite recursion
	 *
	 * @var int
	 */
	private int $max_depth;

	/**
	 * Meta key for storing Notion parent page ID
	 */
	private const META_PARENT_PAGE_ID = '_notion_parent_page_id';

	/**
	 * Meta key for storing Notion page ID (used for lookups)
	 */
	private const META_NOTION_PAGE_ID = 'notion_page_id';

	/**
	 * Constructor
	 *
	 * @param int $max_depth Maximum depth for hierarchy traversal (default: 5).
	 */
	public function __construct( int $max_depth = 5 ) {
		$this->max_depth = max( 1, min( 10, $max_depth ) );
	}

	/**
	 * Initialize hierarchy detection hooks
	 *
	 * Hooks into the sync workflow to process hierarchy after each page sync.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'notion_sync_page_synced', array( $this, 'process_page_hierarchy' ), 10, 3 );
	}

	/**
	 * Process page hierarchy after sync
	 *
	 * Extracts parent information from page properties and updates
	 * WordPress post parent relationships.
	 *
	 * @param int    $post_id         WordPress post ID.
	 * @param string $notion_page_id  Notion page ID (with dashes).
	 * @param array  $page_properties Page properties from Notion API.
	 * @return void
	 */
	public function process_page_hierarchy( int $post_id, string $notion_page_id, array $page_properties ): void {
		// Extract parent information from page properties.
		$parent = $page_properties['parent'] ?? array();

		if ( empty( $parent['type'] ) ) {
			// No parent information available.
			return;
		}

		// Only process page parents (not database, workspace, or block parents).
		if ( 'page_id' !== $parent['type'] ) {
			return;
		}

		$parent_notion_id = $parent['page_id'] ?? '';
		if ( empty( $parent_notion_id ) ) {
			return;
		}

		// Store the parent page ID in meta for reference.
		update_post_meta( $post_id, self::META_PARENT_PAGE_ID, $parent_notion_id );

		// Find the WordPress post for the parent page.
		$parent_post_id = $this->find_post_by_notion_id( $parent_notion_id );

		if ( $parent_post_id ) {
			// Update WordPress post parent relationship.
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_parent' => $parent_post_id,
				)
			);

			/**
			 * Fires after a page hierarchy relationship has been updated.
			 *
			 * @since 0.2.0-dev
			 *
			 * @param int    $post_id         Child post ID.
			 * @param int    $parent_post_id  Parent post ID.
			 * @param string $notion_page_id  Child Notion page ID.
			 * @param string $parent_notion_id Parent Notion page ID.
			 */
			do_action(
				'notion_wp_hierarchy_updated',
				$post_id,
				$parent_post_id,
				$notion_page_id,
				$parent_notion_id
			);
		}
	}

	/**
	 * Find WordPress post by Notion page ID
	 *
	 * @param string $notion_page_id Notion page ID (with or without dashes).
	 * @return int|null Post ID if found, null otherwise.
	 */
	private function find_post_by_notion_id( string $notion_page_id ): ?int {
		// Normalize ID by removing dashes (matches SyncManager storage format).
		$normalized_id = str_replace( '-', '', $notion_page_id );

		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::META_NOTION_PAGE_ID,
						'value'   => $normalized_id,
						'compare' => '=',
					),
				),
			)
		);

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Get child pages for a given Notion page ID
	 *
	 * Queries WordPress for posts that have this page as their parent.
	 * Searches for both normalized (no dashes) and original (with dashes) formats
	 * to handle legacy data and API variations.
	 *
	 * @param string $page_id Notion page ID.
	 * @return array<string> Array of child page IDs.
	 */
	public function get_child_pages( string $page_id ): array {
		$normalized_id = str_replace( '-', '', $page_id );

		// Notion IDs can be stored with or without dashes depending on source.
		// We need to check both formats: normalized (no dashes) and with dashes.
		$with_dashes = strlen( $page_id ) === 32 ?
			substr( $page_id, 0, 8 ) . '-' .
			substr( $page_id, 8, 4 ) . '-' .
			substr( $page_id, 12, 4 ) . '-' .
			substr( $page_id, 16, 4 ) . '-' .
			substr( $page_id, 20 ) :
			$page_id;

		$children = get_posts(
			array(
				'post_type'      => 'any',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => self::META_PARENT_PAGE_ID,
						'value'   => $normalized_id,
						'compare' => '=',
					),
					array(
						'key'     => self::META_PARENT_PAGE_ID,
						'value'   => $with_dashes,
						'compare' => '=',
					),
				),
			)
		);

		// Get Notion page IDs for each child.
		$child_page_ids = array();
		foreach ( $children as $child_post_id ) {
			$child_notion_id = get_post_meta( $child_post_id, self::META_NOTION_PAGE_ID, true );
			if ( $child_notion_id ) {
				$child_page_ids[] = $child_notion_id;
			}
		}

		return $child_page_ids;
	}

	/**
	 * Build a hierarchy map starting from a root page
	 *
	 * Recursively traverses the page hierarchy and builds a map
	 * containing page relationships, WordPress post IDs, titles, and order.
	 *
	 * @param string $root_page_id Notion page ID to start from.
	 * @param int    $max_depth    Maximum depth to traverse (default: 5).
	 * @return array<string, array{
	 *     post_id: int,
	 *     parent_page_id: string|null,
	 *     parent_post_id: int|null,
	 *     title: string,
	 *     order: int,
	 *     children?: array
	 * }> Hierarchy map indexed by Notion page ID.
	 */
	public function build_hierarchy_map( string $root_page_id, int $max_depth = 5 ): array {
		$map = array();
		$this->build_hierarchy_map_recursive( $root_page_id, null, 0, $max_depth, $map );
		return $map;
	}

	/**
	 * Recursive helper for building hierarchy map
	 *
	 * @param string      $page_id        Current page ID.
	 * @param string|null $parent_page_id Parent page ID.
	 * @param int         $depth          Current depth.
	 * @param int         $max_depth      Maximum depth.
	 * @param array       &$map           Map array (passed by reference).
	 * @return void
	 */
	private function build_hierarchy_map_recursive( string $page_id, ?string $parent_page_id, int $depth, int $max_depth, array &$map ): void {
		if ( $depth >= $max_depth ) {
			return;
		}

		$post_id = $this->find_post_by_notion_id( $page_id );
		if ( ! $post_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$parent_post_id = null;
		if ( $parent_page_id ) {
			$parent_post_id = $this->find_post_by_notion_id( $parent_page_id );
		}

		$map[ $page_id ] = array(
			'post_id'         => $post_id,
			'parent_page_id'  => $parent_page_id,
			'parent_post_id'  => $parent_post_id,
			'title'           => $post->post_title,
			'order'           => $post->menu_order,
			'children'        => array(),
		);

		// Recursively process children.
		$children = $this->get_child_pages( $page_id );
		foreach ( $children as $child_page_id ) {
			$this->build_hierarchy_map_recursive( $child_page_id, $page_id, $depth + 1, $max_depth, $map );
			$map[ $page_id ]['children'][] = $child_page_id;
		}
	}

	/**
	 * Get the maximum depth setting
	 *
	 * @return int Maximum depth.
	 */
	public function get_max_depth(): int {
		return $this->max_depth;
	}
}
