<?php
/**
 * Database REST API Controller
 *
 * Provides REST endpoints for fetching database rows and metadata.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\API;

use NotionSync\Database\RowRepository;
use NotionSync\Database\PropertyFormatter;

/**
 * Class DatabaseRestController
 *
 * Handles REST API requests for database data.
 *
 * @since 1.0.0
 */
class DatabaseRestController {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'notion-sync/v1';

	/**
	 * Row repository instance.
	 *
	 * @var RowRepository
	 */
	private $repository;

	/**
	 * Property formatter instance.
	 *
	 * @var PropertyFormatter
	 */
	private $formatter;

	/**
	 * Cache TTL constants (in seconds).
	 */
	private const CACHE_TTL_SCHEMA = 3600;     // 60 minutes.
	private const CACHE_TTL_ROWS = 1800;       // 30 minutes.
	private const CACHE_TTL_ADMIN = 300;       // 5 minutes for admin requests.

	/**
	 * Maximum cache size (1MB) to avoid storing huge responses.
	 */
	private const MAX_CACHE_SIZE = 1048576;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->repository = new RowRepository();
		$this->formatter  = new PropertyFormatter();
		$this->register_cache_invalidation_hooks();
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes(): void {
		// Get database rows with optional filtering/sorting.
		register_rest_route(
			self::NAMESPACE,
			'/databases/(?P<post_id>\d+)/rows',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_rows' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'args'                => array(
					'post_id'  => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'default'           => 1,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 50,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Get database schema/metadata.
		register_rest_route(
			self::NAMESPACE,
			'/databases/(?P<post_id>\d+)/schema',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_schema' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Check read permission.
	 *
	 * Allow public access to published databases.
	 * This enables the frontend database viewer to work for all visitors.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool True if user has permission.
	 */
	public function check_read_permission( $request ): bool {
		$post_id = $request->get_param( 'post_id' );

		// Verify post exists and is a database.
		$post = get_post( $post_id );
		if ( ! $post || 'notion_database' !== $post->post_type ) {
			return false;
		}

		// Allow access if post is published (public).
		if ( 'publish' === $post->post_status ) {
			return true;
		}

		// For non-published posts, require admin permission.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get database rows.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_rows( $request ) {
		$post_id  = $request->get_param( 'post_id' );
		$page     = $request->get_param( 'page' );
		$per_page = min( $request->get_param( 'per_page' ), 100 ); // Cap at 100.

		// Verify post exists and is a database.
		$post = get_post( $post_id );
		if ( ! $post || 'notion_database' !== $post->post_type ) {
			return new \WP_REST_Response(
				array( 'message' => 'Database not found' ),
				404
			);
		}

		// Generate cache key with pagination parameters.
		$cache_params = array(
			'page'     => $page,
			'per_page' => $per_page,
		);
		$cache_key = $this->get_cache_key( 'rows', $post_id, $cache_params );
		$ttl       = $this->get_cache_ttl( 'rows' );

		// Try to get from cache or execute query.
		list( $data, $cache_hit ) = $this->get_cached_or_execute(
			$cache_key,
			$ttl,
			function () use ( $post_id, $page, $per_page ) {
				// Calculate offset.
				$offset = ( $page - 1 ) * $per_page;

				// Get rows from repository.
				$rows = $this->repository->get_rows( $post_id, $per_page, $offset );

				// Get total count for pagination.
				$total_count = $this->repository->count_rows( $post_id );

				return array(
					'rows'       => $rows,
					'pagination' => array(
						'total'        => $total_count,
						'per_page'     => $per_page,
						'current_page' => $page,
						'total_pages'  => ceil( $total_count / $per_page ),
					),
				);
			}
		);

		// Create response with cache headers.
		$response = new \WP_REST_Response( $data, 200 );
		return $this->add_cache_headers( $response, $cache_hit, $ttl );
	}

	/**
	 * Get database schema/metadata.
	 *
	 * Returns Notion database properties to build Tabulator column definitions.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_schema( $request ) {
		$post_id = $request->get_param( 'post_id' );

		// Verify post exists.
		$post = get_post( $post_id );
		if ( ! $post || 'notion_database' !== $post->post_type ) {
			return new \WP_REST_Response(
				array( 'message' => 'Database not found' ),
				404
			);
		}

		// Generate cache key.
		$cache_key = $this->get_cache_key( 'schema', $post_id );
		$ttl       = $this->get_cache_ttl( 'schema' );

		// Try to get from cache or execute query.
		list( $data, $cache_hit ) = $this->get_cached_or_execute(
			$cache_key,
			$ttl,
			function () use ( $post_id, $post ) {
				// Get a sample row to extract schema.
				$sample_rows = $this->repository->get_rows( $post_id, 1, 0 );

				if ( empty( $sample_rows ) ) {
					return array(
						'columns' => array(),
						'message' => 'No rows found',
					);
				}

				// Extract column definitions from first row's properties.
				// Note: RowRepository already decodes JSON, so properties is an array.
				$properties = $sample_rows[0]['properties'];
				$columns    = $this->build_column_definitions( $properties );

				return array(
					'columns'            => $columns,
					'notion_database_id' => get_post_meta( $post_id, 'notion_database_id', true ),
					'title'              => $post->post_title,
					'row_count'          => get_post_meta( $post_id, 'row_count', true ),
					'last_synced'        => get_post_meta( $post_id, 'last_synced', true ),
				);
			}
		);

		// Create response with cache headers.
		$response = new \WP_REST_Response( $data, 200 );
		return $this->add_cache_headers( $response, $cache_hit, $ttl );
	}

	/**
	 * Build Tabulator column definitions from Notion properties.
	 *
	 * Uses PropertyFormatter to generate proper column configurations
	 * based on Notion property types.
	 *
	 * @since 1.0.0
	 *
	 * @param array $properties Notion database properties.
	 * @return array Tabulator column definitions.
	 */
	private function build_column_definitions( array $properties ): array {
		$columns = array();

		// Add ID column (always first).
		$columns[] = array(
			'field'        => 'notion_id',
			'title'        => 'ID',
			'width'        => 100,
			'frozen'       => true,
			'formatter'    => 'html',
			'headerFilter' => 'input',
		);

		// Add title column (always second).
		$columns[] = array(
			'field'        => 'title',
			'title'        => 'Title',
			'width'        => 250,
			'frozen'       => true,
			'headerFilter' => 'input',
			'formatter'    => 'html',
		);

		// Map Notion property types to Tabulator columns using PropertyFormatter.
		foreach ( $properties as $prop_name => $prop_value ) {
			// Skip title (already added) and internal fields.
			if ( 'title' === strtolower( $prop_name ) ) {
				continue;
			}

			$column = $this->map_property_to_column( $prop_name, $prop_value );
			if ( $column ) {
				$columns[] = $column;
			}
		}

		// Add metadata columns using PropertyFormatter.
		$columns[] = $this->formatter->get_column_config(
			'created_time',
			'created_time',
			'Created'
		);

		$columns[] = $this->formatter->get_column_config(
			'last_edited_time',
			'last_edited_time',
			'Last Edited'
		);

		return $columns;
	}

	/**
	 * Map Notion property to Tabulator column definition.
	 *
	 * Infers property type from value structure and uses PropertyFormatter
	 * to generate appropriate column configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value Property value from Notion.
	 * @return array|null Column definition or null if unsupported.
	 */
	private function map_property_to_column( string $name, $value ): ?array {
		// Infer property type from value structure.
		$property_type = $this->infer_property_type( $value );

		if ( ! $property_type ) {
			// Fallback to basic column.
			return array(
				'field'        => 'properties.' . $name,
				'title'        => $name,
				'width'        => 180,
				'headerFilter' => 'input',
			);
		}

		// Use PropertyFormatter to get column config.
		return $this->formatter->get_column_config(
			$property_type,
			'properties.' . $name,
			$name
		);
	}

	/**
	 * Infer Notion property type from value structure.
	 *
	 * Examines the value structure to determine the Notion property type.
	 * This is necessary because row data doesn't include type metadata.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Property value.
	 * @return string|null Property type or null if undetectable.
	 */
	private function infer_property_type( $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		// Boolean = checkbox.
		if ( is_bool( $value ) ) {
			return 'checkbox';
		}

		// Numeric = number.
		if ( is_numeric( $value ) && ! is_string( $value ) ) {
			return 'number';
		}

		// String types.
		if ( is_string( $value ) ) {
			// Check for URL.
			if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
				return 'url';
			}

			// Check for email.
			if ( is_email( $value ) ) {
				return 'email';
			}

			// Check for date/datetime.
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}/', $value ) ) {
				return 'date';
			}

			// Default to text.
			return 'text';
		}

		// Array types - examine structure.
		if ( is_array( $value ) ) {
			// Empty array.
			if ( empty( $value ) ) {
				return 'text';
			}

			// Rich text array (has plain_text or text.content).
			if ( isset( $value[0]['plain_text'] ) || isset( $value[0]['text'] ) ) {
				return 'rich_text';
			}

			// Select/Multi-select (has name and color).
			if ( isset( $value['name'] ) && isset( $value['color'] ) ) {
				return 'select';
			}

			// Multi-select array.
			if ( isset( $value[0]['name'] ) && isset( $value[0]['color'] ) ) {
				return 'multi_select';
			}

			// People array (has person objects).
			if ( isset( $value[0]['object'] ) && 'user' === $value[0]['object'] ) {
				return 'people';
			}

			// Files array (has file objects).
			if ( isset( $value[0]['url'] ) || isset( $value[0]['file'] ) ) {
				return 'files';
			}

			// Date object (has start).
			if ( isset( $value['start'] ) ) {
				return 'date';
			}

			// Relation array (has page IDs).
			if ( isset( $value[0]['id'] ) && empty( $value[0]['name'] ) ) {
				return 'relation';
			}

			// Rollup object (has type and value).
			if ( isset( $value['type'] ) && in_array( $value['type'], array( 'number', 'date', 'array' ), true ) ) {
				return 'rollup';
			}

			// Default array type.
			return 'text';
		}

		return null;
	}

	/**
	 * Register cache invalidation hooks.
	 *
	 * Automatically clears cache when database posts are updated or synced.
	 *
	 * @since 1.0.0
	 */
	private function register_cache_invalidation_hooks(): void {
		// Invalidate cache when database post is saved.
		add_action( 'save_post_notion_database', array( $this, 'handle_post_save' ), 10, 1 );

		// Invalidate cache when database is deleted.
		add_action( 'before_delete_post', array( $this, 'handle_post_delete' ), 10, 1 );

		// Register AJAX handler for manual cache flush.
		add_action( 'wp_ajax_notion_flush_database_cache', array( $this, 'handle_manual_cache_flush' ) );
	}

	/**
	 * Handle post save event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 */
	public function handle_post_save( int $post_id ): void {
		$this->invalidate_database_cache( $post_id );
	}

	/**
	 * Handle post delete event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 */
	public function handle_post_delete( int $post_id ): void {
		$post = get_post( $post_id );
		if ( $post && 'notion_database' === $post->post_type ) {
			$this->invalidate_database_cache( $post_id );
		}
	}

	/**
	 * Handle manual cache flush AJAX request.
	 *
	 * @since 1.0.0
	 */
	public function handle_manual_cache_flush(): void {
		// Verify nonce.
		check_ajax_referer( 'notion_sync_ajax', 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Insufficient permissions.', 'notion-wp' ) ),
				403
			);
		}

		// Get and validate post ID.
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid post ID.', 'notion-wp' ) ),
				400
			);
		}

		// Invalidate cache.
		$this->invalidate_database_cache( $post_id );

		wp_send_json_success(
			array( 'message' => __( 'Cache cleared successfully.', 'notion-wp' ) )
		);
	}

	/**
	 * Generate cache key for database requests.
	 *
	 * Creates a unique cache key based on type, post ID, and request parameters.
	 * Includes last modified timestamp for automatic invalidation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type    Cache type ('rows' or 'schema').
	 * @param int    $post_id Database post ID.
	 * @param array  $params  Optional parameters (page, per_page, filters).
	 * @return string Cache key.
	 */
	private function get_cache_key( string $type, int $post_id, array $params = array() ): string {
		// Include last modified time to auto-invalidate on updates.
		$last_modified = get_post_modified_time( 'U', true, $post_id );

		// For rows cache, include pagination parameters.
		if ( 'rows' === $type && ! empty( $params ) ) {
			$hash = md5( wp_json_encode( $params ) );
			return sprintf( 'notion_db_rows_%d_%s_%d', $post_id, $hash, $last_modified );
		}

		// For schema cache, simpler key.
		return sprintf( 'notion_db_%s_%d_%d', $type, $post_id, $last_modified );
	}

	/**
	 * Get cache TTL based on type and context.
	 *
	 * Returns appropriate cache expiration time:
	 * - Schema: 60 minutes (rarely changes)
	 * - Rows: 30 minutes (changes more frequently)
	 * - Admin requests: 5 minutes (need fresher data)
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Cache type ('rows' or 'schema').
	 * @return int TTL in seconds.
	 */
	private function get_cache_ttl( string $type ): int {
		// Use shorter TTL for admin users.
		if ( current_user_can( 'manage_options' ) ) {
			return self::CACHE_TTL_ADMIN;
		}

		// Return type-specific TTL for public requests.
		return 'schema' === $type ? self::CACHE_TTL_SCHEMA : self::CACHE_TTL_ROWS;
	}

	/**
	 * Invalidate all cached data for a database.
	 *
	 * Removes all transients related to the specified database post.
	 * Uses pattern-based deletion to clear all pagination variants.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Database post ID.
	 */
	private function invalidate_database_cache( int $post_id ): void {
		global $wpdb;

		// Delete all transients matching the pattern for this database.
		// Pattern: _transient_notion_db_%_{$post_id}_%
		$pattern = $wpdb->esc_like( '_transient_notion_db_' ) . '%' . $wpdb->esc_like( "_{$post_id}_" ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		// Log cache invalidation in debug mode.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[NotionWP Cache] Invalidated cache for database post ID: %d', $post_id ) );
		}
	}

	/**
	 * Get data from cache or execute callback.
	 *
	 * Attempts to retrieve data from cache. If not found or expired,
	 * executes the callback, caches the result, and returns it.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $cache_key Cache key.
	 * @param int      $ttl       Time to live in seconds.
	 * @param callable $callback  Function to execute on cache miss.
	 * @return array Array with [data, cache_hit_bool].
	 */
	private function get_cached_or_execute( string $cache_key, int $ttl, callable $callback ): array {
		// Try to get from cache.
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			// Cache hit - log if debugging.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( '[NotionWP Cache] HIT: %s', $cache_key ) );
			}
			return array( $cached, true );
		}

		// Cache miss - execute callback.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[NotionWP Cache] MISS: %s', $cache_key ) );
		}

		$data = $callback();

		// Only cache if data is reasonable size.
		$data_size = strlen( wp_json_encode( $data ) );
		if ( $data_size <= self::MAX_CACHE_SIZE ) {
			set_transient( $cache_key, $data, $ttl );
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[NotionWP Cache] Data too large to cache (%d bytes): %s', $data_size, $cache_key ) );
		}

		return array( $data, false );
	}

	/**
	 * Add cache status headers to REST response.
	 *
	 * Adds X-NotionWP-Cache and X-NotionWP-Cache-Expires headers
	 * for debugging and monitoring cache performance.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Response $response   Response object.
	 * @param bool              $cache_hit  Whether response came from cache.
	 * @param int               $ttl        Cache TTL in seconds.
	 * @return \WP_REST_Response Modified response with cache headers.
	 */
	private function add_cache_headers( \WP_REST_Response $response, bool $cache_hit, int $ttl ): \WP_REST_Response {
		$response->header( 'X-NotionWP-Cache', $cache_hit ? 'HIT' : 'MISS' );
		$response->header( 'X-NotionWP-Cache-Expires', time() + $ttl );
		return $response;
	}
}
