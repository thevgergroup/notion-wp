<?php
/**
 * Sync Logger
 *
 * Persistent logging system for sync operations.
 *
 * @package NotionSync\Utils
 * @since 0.3.0
 */

namespace NotionSync\Utils;

use NotionSync\Database\SyncLogSchema;

/**
 * Class SyncLogger
 *
 * Logs sync issues to database for persistent tracking and admin review.
 *
 * @since 0.3.0
 */
class SyncLogger {

	/**
	 * Severity levels.
	 */
	public const SEVERITY_INFO    = 'info';
	public const SEVERITY_WARNING = 'warning';
	public const SEVERITY_ERROR   = 'error';

	/**
	 * Common log categories.
	 */
	public const CATEGORY_IMAGE       = 'image';
	public const CATEGORY_BLOCK       = 'block';
	public const CATEGORY_API         = 'api';
	public const CATEGORY_CONVERSION  = 'conversion';
	public const CATEGORY_PERFORMANCE = 'performance';

	/**
	 * Log a sync issue.
	 *
	 * @param string   $notion_page_id Notion page ID.
	 * @param string   $severity       Severity level (info|warning|error).
	 * @param string   $category       Category (image|block|api|conversion|performance).
	 * @param string   $message        Human-readable message.
	 * @param array    $context        Additional context data (optional).
	 * @param int|null $wp_post_id     WordPress post ID (optional).
	 * @return int|false Insert ID on success, false on failure.
	 */
	public static function log(
		string $notion_page_id,
		string $severity,
		string $category,
		string $message,
		array $context = [],
		?int $wp_post_id = null
	) {
		global $wpdb;

		// Ensure table exists.
		if ( ! SyncLogSchema::table_exists() ) {
			SyncLogSchema::create_table();
		}

		$table_name = SyncLogSchema::get_table_name();

		// Validate severity.
		$valid_severities = [ self::SEVERITY_INFO, self::SEVERITY_WARNING, self::SEVERITY_ERROR ];
		if ( ! in_array( $severity, $valid_severities, true ) ) {
			$severity = self::SEVERITY_INFO;
		}

		// Prepare context JSON.
		$context_json = ! empty( $context ) ? wp_json_encode( $context ) : null;

		// Insert log entry.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table_name,
			[
				'notion_page_id' => $notion_page_id,
				'wp_post_id'     => $wp_post_id,
				'severity'       => $severity,
				'category'       => $category,
				'message'        => $message,
				'context'        => $context_json,
				'created_at'     => current_time( 'mysql' ),
			],
			[
				'%s', // notion_page_id.
				'%d', // wp_post_id.
				'%s', // severity.
				'%s', // category.
				'%s', // message.
				'%s', // context.
				'%s', // created_at.
			]
		);

		if ( false === $result ) {
			error_log(
				sprintf(
					'[NotionSync] Failed to insert sync log: %s',
					$wpdb->last_error
				)
			);
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get unresolved logs.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type string|null $notion_page_id Filter by Notion page ID.
	 *     @type int|null    $wp_post_id     Filter by WordPress post ID.
	 *     @type string|null $severity       Filter by severity.
	 *     @type string|null $category       Filter by category.
	 *     @type int         $limit          Number of results (default: 100).
	 *     @type int         $offset         Offset for pagination (default: 0).
	 * }
	 * @return array Array of log entries.
	 */
	public static function get_unresolved_logs( array $args = [] ): array {
		global $wpdb;

		$table_name = SyncLogSchema::get_table_name();

		// Build WHERE clause.
		$where   = [ 'resolved = 0' ];
		$prepare = [];

		if ( ! empty( $args['notion_page_id'] ) ) {
			$where[]   = 'notion_page_id = %s';
			$prepare[] = $args['notion_page_id'];
		}

		if ( ! empty( $args['wp_post_id'] ) ) {
			$where[]   = 'wp_post_id = %d';
			$prepare[] = $args['wp_post_id'];
		}

		if ( ! empty( $args['severity'] ) ) {
			$where[]   = 'severity = %s';
			$prepare[] = $args['severity'];
		}

		if ( ! empty( $args['category'] ) ) {
			$where[]   = 'category = %s';
			$prepare[] = $args['category'];
		}

		$where_clause = implode( ' AND ', $where );

		// Build query.
		$limit  = absint( $args['limit'] ?? 100 );
		$offset = absint( $args['offset'] ?? 0 );

		// Build query with placeholders for LIMIT and OFFSET.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";

		// Add limit and offset to prepare array.
		$prepare[] = $limit;
		$prepare[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( $query, $prepare ), ARRAY_A );

		// Decode context JSON.
		foreach ( $results as &$result ) {
			if ( ! empty( $result['context'] ) ) {
				$result['context'] = json_decode( $result['context'], true );
			}
		}

		return $results;
	}

	/**
	 * Get logs for a specific page.
	 *
	 * @param string $notion_page_id Notion page ID.
	 * @param bool   $unresolved_only Only get unresolved logs (default: true).
	 * @return array Array of log entries.
	 */
	public static function get_logs_for_page( string $notion_page_id, bool $unresolved_only = true ): array {
		global $wpdb;

		$table_name = SyncLogSchema::get_table_name();

		$where = [ 'notion_page_id = %s' ];
		if ( $unresolved_only ) {
			$where[] = 'resolved = 0';
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared, variable contains prepared SQL.
			$wpdb->prepare( $query, $notion_page_id ),
			ARRAY_A
		);

		// Decode context JSON.
		foreach ( $results as &$result ) {
			if ( ! empty( $result['context'] ) ) {
				$result['context'] = json_decode( $result['context'], true );
			}
		}

		return $results;
	}

	/**
	 * Mark a log entry as resolved.
	 *
	 * @param int $log_id Log entry ID.
	 * @return bool True on success, false on failure.
	 */
	public static function resolve_log( int $log_id ): bool {
		global $wpdb;

		$table_name = SyncLogSchema::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table_name,
			[
				'resolved'    => 1,
				'resolved_at' => current_time( 'mysql' ),
				'resolved_by' => get_current_user_id(),
			],
			[ 'id' => $log_id ],
			[ '%d', '%s', '%d' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Mark all logs for a page as resolved.
	 *
	 * @param string $notion_page_id Notion page ID.
	 * @return int|false Number of rows updated, false on failure.
	 */
	public static function resolve_logs_for_page( string $notion_page_id ) {
		global $wpdb;

		$table_name = SyncLogSchema::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"UPDATE {$table_name} SET resolved = 1, resolved_at = %s, resolved_by = %d WHERE notion_page_id = %s AND resolved = 0",
				current_time( 'mysql' ),
				get_current_user_id(),
				$notion_page_id
			)
		);

		return $result;
	}

	/**
	 * Get unresolved log count.
	 *
	 * @param array $args {
	 *     Filter arguments.
	 *
	 *     @type string|null $severity Filter by severity.
	 *     @type string|null $category Filter by category.
	 * }
	 * @return int Number of unresolved logs.
	 */
	public static function get_unresolved_count( array $args = [] ): int {
		global $wpdb;

		$table_name = SyncLogSchema::get_table_name();

		$where   = [ 'resolved = 0' ];
		$prepare = [];

		if ( ! empty( $args['severity'] ) ) {
			$where[]   = 'severity = %s';
			$prepare[] = $args['severity'];
		}

		if ( ! empty( $args['category'] ) ) {
			$where[]   = 'category = %s';
			$prepare[] = $args['category'];
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";

		if ( ! empty( $prepare ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( $query, $prepare ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Delete old resolved logs.
	 *
	 * @param int $days_old Delete logs resolved more than N days ago (default: 30).
	 * @return int|false Number of rows deleted, false on failure.
	 */
	public static function cleanup_old_logs( int $days_old = 30 ) {
		global $wpdb;

		$table_name = SyncLogSchema::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"DELETE FROM {$table_name} WHERE resolved = 1 AND resolved_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days_old
			)
		);

		return $result;
	}
}
