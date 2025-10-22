<?php
/**
 * Database Custom Post Type
 *
 * Each Notion database becomes a WordPress CPT post that stores metadata
 * (title, ID, last synced time, row count). The actual database rows are
 * stored separately in the custom table as JSON.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Database;

/**
 * Class DatabasePostType
 *
 * Registers and manages the notion_database custom post type.
 *
 * @since 1.0.0
 */
class DatabasePostType {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'notion_database';

	/**
	 * Register custom post type.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => __( 'Notion Databases', 'notion-wp' ),
					'singular_name' => __( 'Notion Database', 'notion-wp' ),
					'add_new'       => __( 'Add New', 'notion-wp' ),
					'add_new_item'  => __( 'Add New Database', 'notion-wp' ),
					'edit_item'     => __( 'Edit Database', 'notion-wp' ),
					'view_item'     => __( 'View Database', 'notion-wp' ),
					'all_items'     => __( 'All Databases', 'notion-wp' ),
					'search_items'  => __( 'Search Databases', 'notion-wp' ),
				),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => false, // Accessed via Notion Sync settings page.
				'show_in_rest'       => false,
				'capability_type'    => 'post',
				'hierarchical'       => false,
				'supports'           => array( 'title' ),
				'has_archive'        => true,
				'rewrite'            => array(
					'slug'       => 'database',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Find or create database post.
	 *
	 * Searches for existing post by Notion database ID. If not found, creates new post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_database_id Notion database ID.
	 * @param array  $database_info      Database metadata from Notion API.
	 * @return int WordPress post ID.
	 * @throws \RuntimeException If post creation fails.
	 */
	public function find_or_create( string $notion_database_id, array $database_info ): int {
		// Search for existing post.
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'     => 'notion_database_id',
						'value'   => $notion_database_id,
						'compare' => '=',
					),
				),
				'fields'         => 'ids',
			)
		);

		// Return existing post if found.
		if ( ! empty( $posts ) ) {
			return $posts[0];
		}

		// Create new post.
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $database_info['title'] ?? 'Untitled Database',
				'post_status' => 'publish',
				'meta_input'  => array(
					'notion_database_id' => $notion_database_id,
					'notion_last_edited' => $database_info['last_edited_time'] ?? '',
					'row_count'          => 0,
					'last_synced'        => current_time( 'mysql' ),
				),
			)
		);

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException(
				sprintf(
					'Failed to create database post: %s',
					$post_id->get_error_message()
				)
			);
		}

		return $post_id;
	}

	/**
	 * Find database post by Notion ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_database_id Notion database ID.
	 * @return int|null WordPress post ID if found, null otherwise.
	 */
	public function find_by_notion_id( string $notion_database_id ): ?int {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'     => 'notion_database_id',
						'value'   => $notion_database_id,
						'compare' => '=',
					),
				),
				'fields'         => 'ids',
			)
		);

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Update database metadata.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id        WordPress post ID.
	 * @param array $database_info  Database metadata from Notion API.
	 */
	public function update_metadata( int $post_id, array $database_info ): void {
		if ( isset( $database_info['title'] ) ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $database_info['title'],
				)
			);
		}

		if ( isset( $database_info['last_edited_time'] ) ) {
			update_post_meta( $post_id, 'notion_last_edited', $database_info['last_edited_time'] );
		}

		update_post_meta( $post_id, 'last_synced', current_time( 'mysql' ) );
	}

	/**
	 * Update row count.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id   WordPress post ID.
	 * @param int $row_count Number of rows.
	 */
	public function update_row_count( int $post_id, int $row_count ): void {
		update_post_meta( $post_id, 'row_count', $row_count );
	}
}
