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
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->repository = new RowRepository();
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
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'page'    => array(
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

		// Calculate offset.
		$offset = ( $page - 1 ) * $per_page;

		// Get rows from repository.
		$rows = $this->repository->get_rows( $post_id, $per_page, $offset );

		// Get total count for pagination.
		$total_count = $this->repository->count_rows( $post_id );

		return new \WP_REST_Response(
			array(
				'rows'       => $rows,
				'pagination' => array(
					'total'       => $total_count,
					'per_page'    => $per_page,
					'current_page' => $page,
					'total_pages' => ceil( $total_count / $per_page ),
				),
			),
			200
		);
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

		// Get a sample row to extract schema.
		$sample_rows = $this->repository->get_rows( $post_id, 1, 0 );

		if ( empty( $sample_rows ) ) {
			return new \WP_REST_Response(
				array(
					'columns' => array(),
					'message' => 'No rows found',
				),
				200
			);
		}

		// Extract column definitions from first row's properties.
		// Note: RowRepository already decodes JSON, so properties is an array.
		$properties = $sample_rows[0]['properties'];
		$columns    = $this->build_column_definitions( $properties );

		return new \WP_REST_Response(
			array(
				'columns'             => $columns,
				'notion_database_id'  => get_post_meta( $post_id, 'notion_database_id', true ),
				'title'               => $post->post_title,
				'row_count'           => get_post_meta( $post_id, 'row_count', true ),
				'last_synced'         => get_post_meta( $post_id, 'last_synced', true ),
			),
			200
		);
	}

	/**
	 * Build Tabulator column definitions from Notion properties.
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
			'field'      => 'notion_id',
			'title'      => 'ID',
			'width'      => 100,
			'frozen'     => true,
			'formatter'  => 'html',
			'headerFilter' => 'input',
		);

		// Add title column (always second).
		$columns[] = array(
			'field'      => 'title',
			'title'      => 'Title',
			'width'      => 250,
			'frozen'     => true,
			'headerFilter' => 'input',
		);

		// Map Notion property types to Tabulator columns.
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

		// Add metadata columns.
		$columns[] = array(
			'field'      => 'created_time',
			'title'      => 'Created',
			'width'      => 160,
			'sorter'     => 'datetime',
			'headerFilter' => 'input',
		);

		$columns[] = array(
			'field'      => 'last_edited_time',
			'title'      => 'Last Edited',
			'width'      => 160,
			'sorter'     => 'datetime',
			'headerFilter' => 'input',
		);

		return $columns;
	}

	/**
	 * Map Notion property to Tabulator column definition.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value Property value from Notion.
	 * @return array|null Column definition or null if unsupported.
	 */
	private function map_property_to_column( string $name, $value ): ?array {
		// Base column definition.
		$column = array(
			'field'        => 'properties.' . $name,
			'title'        => $name,
			'headerFilter' => 'input',
		);

		// Detect property type and configure accordingly.
		if ( is_array( $value ) ) {
			// Array properties (multi-select, people, etc.).
			$column['formatter'] = 'html';
			$column['width']     = 200;
		} elseif ( is_numeric( $value ) ) {
			// Number property.
			$column['sorter'] = 'number';
			$column['width']  = 120;
		} elseif ( is_bool( $value ) ) {
			// Checkbox property.
			$column['formatter'] = 'tickCross';
			$column['width']     = 100;
			$column['hozAlign']  = 'center';
		} elseif ( is_string( $value ) ) {
			// Text, select, etc.
			$column['width'] = 180;

			// Check if it's a date string.
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}/', $value ) ) {
				$column['sorter'] = 'datetime';
			}
		}

		return $column;
	}
}
