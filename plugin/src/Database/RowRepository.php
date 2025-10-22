<?php
/**
 * Row Repository
 *
 * Handles CRUD operations for database rows stored as JSON in custom table.
 * Provides methods for inserting, updating, querying, and deleting rows.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Database;

/**
 * Class RowRepository
 *
 * Repository pattern for database row operations with JSON encoding/decoding.
 *
 * @since 1.0.0
 */
class RowRepository {

	/**
	 * Custom table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->table_name = Schema::get_table_name();
	}

	/**
	 * Insert or update a database row.
	 *
	 * Performs upsert operation - inserts new row or updates existing one
	 * based on notion_page_id (unique constraint).
	 *
	 * @since 1.0.0
	 *
	 * @param int    $database_post_id WordPress post ID for database.
	 * @param string $notion_page_id   Notion page ID (unique).
	 * @param array  $properties       Row data (will be JSON encoded).
	 * @param array  $extracted        Extracted fields for indexing (title, status, dates).
	 * @return bool Success status.
	 */
	public function upsert(
		int $database_post_id,
		string $notion_page_id,
		array $properties,
		array $extracted = array()
	): bool {
		global $wpdb;

		// Check if row already exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT id FROM {$this->table_name} WHERE notion_page_id = %s",
				$notion_page_id
			)
		);

		// Prepare data array.
		$data = array(
			'database_post_id' => $database_post_id,
			'notion_page_id'   => $notion_page_id,
			'properties'       => wp_json_encode( $properties ),
			'title'            => $extracted['title'] ?? null,
			'status'           => $extracted['status'] ?? null,
			'created_time'     => $extracted['created_time'] ?? null,
			'last_edited_time' => $extracted['last_edited_time'] ?? null,
			'synced_at'        => current_time( 'mysql' ),
		);

		// Format array for wpdb.
		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $exists ) {
			// Update existing row.
			return false !== $wpdb->update(
				$this->table_name,
				$data,
				array( 'notion_page_id' => $notion_page_id ),
				$format,
				array( '%s' )
			);
		}

		// Insert new row.
		return false !== $wpdb->insert(
			$this->table_name,
			$data,
			$format
		);
	}

	/**
	 * Get all rows for a database.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $database_post_id WordPress post ID for database.
	 * @param int   $limit            Results limit. Default 100.
	 * @param int   $offset           Results offset. Default 0.
	 * @param array $filters          Optional filters (search, status).
	 * @return array Array of row objects with decoded JSON properties.
	 */
	public function get_rows(
		int $database_post_id,
		int $limit = 100,
		int $offset = 0,
		array $filters = array()
	): array {
		global $wpdb;

		// Start building WHERE clause.
		$where = $wpdb->prepare( 'WHERE database_post_id = %d', $database_post_id );

		// Add status filter.
		if ( ! empty( $filters['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $filters['status'] );
		}

		// Add search filter (uses FULLTEXT index).
		if ( ! empty( $filters['search'] ) ) {
			$where .= $wpdb->prepare(
				' AND MATCH(title) AGAINST(%s IN BOOLEAN MODE)',
				$filters['search']
			);
		}

		// Build query with LIMIT and OFFSET.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				 {$where}
				 ORDER BY last_edited_time DESC
				 LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $results ) ) {
			return array();
		}

		// Decode JSON properties for each row.
		foreach ( $results as &$row ) {
			$row['properties'] = json_decode( $row['properties'], true );
		}

		return $results;
	}

	/**
	 * Get single row by Notion page ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_page_id Notion page ID.
	 * @return array|null Row data with decoded JSON, null if not found.
	 */
	public function get_row_by_notion_id( string $notion_page_id ): ?array {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT * FROM {$this->table_name} WHERE notion_page_id = %s",
				$notion_page_id
			),
			ARRAY_A
		);

		if ( ! $result ) {
			return null;
		}

		// Decode JSON properties.
		$result['properties'] = json_decode( $result['properties'], true );

		return $result;
	}

	/**
	 * Get row count for a database.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $database_post_id WordPress post ID for database.
	 * @param array $filters          Optional filters (status, search).
	 * @return int Row count.
	 */
	public function count_rows( int $database_post_id, array $filters = array() ): int {
		global $wpdb;

		// Start building WHERE clause.
		$where = $wpdb->prepare( 'WHERE database_post_id = %d', $database_post_id );

		// Add filters if provided.
		if ( ! empty( $filters['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $filters['status'] );
		}

		if ( ! empty( $filters['search'] ) ) {
			$where .= $wpdb->prepare(
				' AND MATCH(title) AGAINST(%s IN BOOLEAN MODE)',
				$filters['search']
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name and $where are safe.
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table_name} {$where}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return (int) $count;
	}

	/**
	 * Delete all rows for a database.
	 *
	 * @since 1.0.0
	 *
	 * @param int $database_post_id WordPress post ID for database.
	 * @return bool Success status.
	 */
	public function delete_rows( int $database_post_id ): bool {
		global $wpdb;

		return false !== $wpdb->delete(
			$this->table_name,
			array( 'database_post_id' => $database_post_id ),
			array( '%d' )
		);
	}

	/**
	 * Delete single row by Notion page ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_page_id Notion page ID.
	 * @return bool Success status.
	 */
	public function delete_row( string $notion_page_id ): bool {
		global $wpdb;

		return false !== $wpdb->delete(
			$this->table_name,
			array( 'notion_page_id' => $notion_page_id ),
			array( '%s' )
		);
	}

	/**
	 * Get distinct status values for a database.
	 *
	 * Useful for building status filter dropdowns.
	 *
	 * @since 1.0.0
	 *
	 * @param int $database_post_id WordPress post ID for database.
	 * @return array Array of distinct status values.
	 */
	public function get_distinct_statuses( int $database_post_id ): array {
		global $wpdb;

		$statuses = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT DISTINCT status FROM {$this->table_name}
				 WHERE database_post_id = %d AND status IS NOT NULL
				 ORDER BY status ASC",
				$database_post_id
			)
		);

		return is_array( $statuses ) ? $statuses : array();
	}

	/**
	 * Get rows modified after a specific timestamp.
	 *
	 * Useful for incremental syncs.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $database_post_id WordPress post ID for database.
	 * @param string $timestamp        MySQL datetime string.
	 * @return array Array of row objects modified after timestamp.
	 */
	public function get_rows_modified_after( int $database_post_id, string $timestamp ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT * FROM {$this->table_name}
				 WHERE database_post_id = %d AND last_edited_time > %s
				 ORDER BY last_edited_time DESC",
				$database_post_id,
				$timestamp
			),
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		// Decode JSON properties.
		foreach ( $results as &$row ) {
			$row['properties'] = json_decode( $row['properties'], true );
		}

		return $results;
	}
}
