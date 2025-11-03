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
					'name'          => __( 'Notion Databases', 'notion-sync' ),
					'singular_name' => __( 'Notion Database', 'notion-sync' ),
					'add_new'       => __( 'Add New', 'notion-sync' ),
					'add_new_item'  => __( 'Add New Database', 'notion-sync' ),
					'edit_item'     => __( 'Edit Database', 'notion-sync' ),
					'view_item'     => __( 'View Database', 'notion-sync' ),
					'all_items'     => __( 'All Databases', 'notion-sync' ),
					'search_items'  => __( 'Search Databases', 'notion-sync' ),
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
	 * IMPORTANT: For Notion databases, the database ID and collection ID are the same.
	 * When syncing:
	 * - Pass the database ID (from URL or API) as $notion_database_id
	 * - The $database_info['id'] from get_database_schema() will match this ID
	 * - Both notion_database_id and notion_collection_id will store the same value
	 *
	 * For child_database blocks:
	 * - The block has an 'id' (block ID) and 'collection_id' (database ID)
	 * - You must pass the collection_id to sync, not the block id
	 * - ChildDatabaseConverter handles this extraction automatically
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_database_id Notion database ID (normalized, no dashes).
	 * @param array  $database_info      Database metadata from Notion API (from get_database_schema()).
	 * @return int WordPress post ID.
	 * @throws \RuntimeException If post creation fails or ID mismatch detected.
	 */
	public function find_or_create( string $notion_database_id, array $database_info ): int {
		// Validate that the database_info contains the expected ID.
		// The 'id' field from get_database_schema() should match the database_id parameter.
		if ( isset( $database_info['id'] ) ) {
			$api_id         = str_replace( '-', '', $database_info['id'] );
			$normalized_id  = str_replace( '-', '', $notion_database_id );

			if ( $api_id !== $normalized_id ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log(
					sprintf(
						'[DatabasePostType] ID mismatch detected! Param: %s, API response: %s. ' .
						'This may indicate you are trying to sync a child_database block ID ' .
						'instead of the collection_id.',
						$notion_database_id,
						$database_info['id']
					)
				);
				// Use the API-returned ID as the source of truth.
				$notion_database_id = $api_id;
			}
		}

		// Search for existing post by database ID.
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

		// If existing post found, update its metadata and return.
		if ( ! empty( $posts ) ) {
			$post_id = $posts[0];

			// Update collection_id if provided in database_info.
			if ( isset( $database_info['collection_id'] ) ) {
				$collection_id = str_replace( '-', '', $database_info['collection_id'] );
				update_post_meta( $post_id, 'notion_collection_id', $collection_id );
			}

			return $post_id;
		}

		// Prepare meta_input for new post.
		// Use collection_id from database_info if available, otherwise fall back to database_id.
		$collection_id = isset( $database_info['collection_id'] )
			? str_replace( '-', '', $database_info['collection_id'] )
			: $notion_database_id;

		$meta_input = array(
			'notion_database_id'   => $notion_database_id,
			'notion_collection_id' => $collection_id,
			'notion_last_edited'   => $database_info['last_edited_time'] ?? '',
			'row_count'            => 0,
			'last_synced'          => current_time( 'mysql' ),
		);

		// Create new post.
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $database_info['title'] ?? 'Untitled Database',
				'post_status' => 'publish',
				'meta_input'  => $meta_input,
			)
		);

		// @phpstan-ignore-next-line -- wp_insert_post can return WP_Error|int, PHPStan doesn't recognize WP_Error return type.
		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException(
				sprintf(
					'Failed to create database post: %s',
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not browser output.
					$post_id->get_error_message()
				)
			);
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log(
			sprintf(
				'[DatabasePostType] Created database post %d for Notion database %s',
				$post_id,
				$notion_database_id
			)
		);

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
	 * Find database post by Notion collection ID.
	 *
	 * This is useful for looking up databases when processing child_database blocks,
	 * which reference the parent database via collection_id.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_collection_id Notion collection ID (database ID from the API).
	 * @return int|null WordPress post ID if found, null otherwise.
	 */
	public function find_by_collection_id( string $notion_collection_id ): ?int {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'     => 'notion_collection_id',
						'value'   => $notion_collection_id,
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
