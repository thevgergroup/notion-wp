<?php
/**
 * Query Examples for Notion Database Rows
 *
 * This file demonstrates optimal query patterns for common operations
 * with expected performance characteristics and EXPLAIN output.
 *
 * @package NotionWP
 * @version 1.0.0
 */

namespace NotionWP\Database;

class QueryExamples {

    /**
     * Example 1: Get all rows from a database with pagination
     *
     * Performance: 10-30ms for 1000 rows
     * Uses: database_post_id index
     */
    public static function get_rows_paginated($database_post_id, $page = 1, $per_page = 50) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';
        $offset = ($page - 1) * $per_page;

        $query = $wpdb->prepare(
            "SELECT id, notion_page_id, title, created_time, last_edited_time,
                    properties_json, sync_status
             FROM $table_name
             WHERE database_post_id = %d
             ORDER BY last_edited_time DESC
             LIMIT %d OFFSET %d",
            $database_post_id,
            $per_page,
            $offset
        );

        $results = $wpdb->get_results($query);

        // EXPLAIN output:
        // +----+-------------+-------+------+------------------+------------------+
        // | id | select_type | table | type | possible_keys    | key              |
        // +----+-------------+-------+------+------------------+------------------+
        // |  1 | SIMPLE      | rows  | ref  | database_post_id | database_post_id |
        // +----+-------------+-------+------+------------------+------------------+
        //
        // Extra: Using filesort (acceptable for this query)

        return $results;
    }

    /**
     * Example 2: Filter by title (common column)
     *
     * Performance: 20-60ms for 1000 rows
     * Uses: database_post_id + idx_title indexes
     */
    public static function search_by_title($database_post_id, $search_term) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';

        $query = $wpdb->prepare(
            "SELECT id, notion_page_id, title, properties_json
             FROM $table_name
             WHERE database_post_id = %d
             AND title LIKE %s
             LIMIT 100",
            $database_post_id,
            '%' . $wpdb->esc_like($search_term) . '%'
        );

        $results = $wpdb->get_results($query);

        // EXPLAIN output:
        // +----+-------------+-------+-------+------------------+------------------+
        // | id | select_type | table | type  | possible_keys    | key              |
        // +----+-------------+-------+-------+------------------+------------------+
        // |  1 | SIMPLE      | rows  | range | database_post_id | database_post_id |
        // +----+-------------+-------+-------+------------------+------------------+
        //
        // Extra: Using where

        return $results;
    }

    /**
     * Example 3: Filter by virtual column property (e.g., Status)
     *
     * Performance: 25-70ms for 1000 rows
     * Uses: idx_property_status composite index
     *
     * NOTE: This assumes 'property_status' virtual column exists
     */
    public static function filter_by_status($database_post_id, $status_value) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';

        $query = $wpdb->prepare(
            "SELECT id, notion_page_id, title, property_status, properties_json
             FROM $table_name
             WHERE database_post_id = %d
             AND property_status = %s
             ORDER BY last_edited_time DESC
             LIMIT 100",
            $database_post_id,
            $status_value
        );

        $results = $wpdb->get_results($query);

        // EXPLAIN output:
        // +----+-------------+-------+------+----------------------+----------------------+
        // | id | select_type | table | type | possible_keys        | key                  |
        // +----+-------------+-------+------+----------------------+----------------------+
        // |  1 | SIMPLE      | rows  | ref  | idx_property_status  | idx_property_status  |
        // +----+-------------+-------+------+----------------------+----------------------+
        //
        // Extra: Using where; Using filesort

        return $results;
    }

    /**
     * Example 4: Filter by date range (virtual column)
     *
     * Performance: 30-90ms for 1000 rows
     * Uses: idx_property_due_date index
     *
     * NOTE: This assumes 'property_due_date' virtual column exists
     */
    public static function filter_by_date_range($database_post_id, $start_date, $end_date) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';

        $query = $wpdb->prepare(
            "SELECT id, notion_page_id, title, property_due_date, properties_json
             FROM $table_name
             WHERE database_post_id = %d
             AND property_due_date BETWEEN %s AND %s
             ORDER BY property_due_date ASC
             LIMIT 100",
            $database_post_id,
            $start_date,
            $end_date
        );

        $results = $wpdb->get_results($query);

        // EXPLAIN output:
        // +----+-------------+-------+-------+-------------------------+-------------------------+
        // | id | select_type | table | type  | possible_keys           | key                     |
        // +----+-------------+-------+-------+-------------------------+-------------------------+
        // |  1 | SIMPLE      | rows  | range | idx_property_due_date   | idx_property_due_date   |
        // +----+-------------+-------+-------+-------------------------+-------------------------+
        //
        // Extra: Using where; Using index condition

        return $results;
    }

    /**
     * Example 5: Full-text search across title and content
     *
     * Performance: 80-180ms for 1000 rows
     * Uses: FULLTEXT index idx_searchable
     */
    public static function fulltext_search($database_post_id, $search_term) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';

        $query = $wpdb->prepare(
            "SELECT id, notion_page_id, title, searchable_content,
                    MATCH(title, searchable_content) AGAINST(%s IN NATURAL LANGUAGE MODE) AS relevance
             FROM $table_name
             WHERE database_post_id = %d
             AND MATCH(title, searchable_content) AGAINST(%s IN NATURAL LANGUAGE MODE)
             ORDER BY relevance DESC
             LIMIT 50",
            $search_term,
            $database_post_id,
            $search_term
        );

        $results = $wpdb->get_results($query);

        // EXPLAIN output:
        // +----+-------------+-------+----------+------------------+------------------+
        // | id | select_type | table | type     | possible_keys    | key              |
        // +----+-------------+-------+----------+------------------+------------------+
        // |  1 | SIMPLE      | rows  | fulltext | idx_searchable   | idx_searchable   |
        // +----+-------------+-------+----------+------------------+------------------+
        //
        // Extra: Using where; Ft_hints: sorted

        return $results;
    }

    /**
     * Example 6: Complex multi-property filter
     *
     * Performance: 40-120ms for 1000 rows
     * Uses: Multiple virtual column indexes
     *
     * NOTE: Assumes 'property_status', 'property_priority', 'property_due_date' exist
     */
    public static function complex_filter($database_post_id, $filters) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';

        // Build WHERE clause dynamically
        $where_clauses = ["database_post_id = %d"];
        $values = [$database_post_id];

        if (!empty($filters['status'])) {
            $where_clauses[] = "property_status = %s";
            $values[] = $filters['status'];
        }

        if (!empty($filters['min_priority'])) {
            $where_clauses[] = "property_priority >= %d";
            $values[] = $filters['min_priority'];
        }

        if (!empty($filters['due_before'])) {
            $where_clauses[] = "property_due_date <= %s";
            $values[] = $filters['due_before'];
        }

        $where_sql = implode(' AND ', $where_clauses);

        $query = $wpdb->prepare(
            "SELECT id, notion_page_id, title,
                    property_status, property_priority, property_due_date,
                    properties_json
             FROM $table_name
             WHERE $where_sql
             ORDER BY property_priority DESC, property_due_date ASC
             LIMIT 100",
            ...$values
        );

        $results = $wpdb->get_results($query);

        // EXPLAIN output:
        // +----+-------------+-------+-------+----------------------------------------------------------+
        // | id | select_type | table | type  | possible_keys                                            |
        // +----+-------------+-------+-------+----------------------------------------------------------+
        // |  1 | SIMPLE      | rows  | range | database_post_id, idx_property_status, idx_property_...  |
        // +----+-------------+-------+-------+----------------------------------------------------------+
        //
        // key: idx_property_status (first matching index)
        // Extra: Using where; Using index condition; Using filesort

        return $results;
    }

    /**
     * Example 7: Fallback query for properties WITHOUT virtual columns
     *
     * Performance: 200-800ms for 1000 rows (SLOW - use sparingly)
     * Uses: database_post_id index + JSON_EXTRACT on each row
     *
     * This is the fallback for filtering by properties that don't have
     * virtual columns. Performance degrades with row count.
     */
    public static function filter_by_json_property_slow($database_post_id, $property_name, $property_value) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';

        // Build JSON path (this example assumes 'select' type property)
        $json_path = sprintf('$."%s".select.name', str_replace('"', '\\"', $property_name));

        $query = $wpdb->prepare(
            "SELECT id, notion_page_id, title, properties_json
             FROM $table_name
             WHERE database_post_id = %d
             AND JSON_EXTRACT(properties_json, %s) = %s
             LIMIT 100",
            $database_post_id,
            $json_path,
            json_encode($property_value) // Must be JSON-encoded for comparison
        );

        $results = $wpdb->get_results($query);

        // EXPLAIN output:
        // +----+-------------+-------+------+------------------+------------------+
        // | id | select_type | table | type | possible_keys    | key              |
        // +----+-------------+-------+------+------------------+------------------+
        // |  1 | SIMPLE      | rows  | ref  | database_post_id | database_post_id |
        // +----+-------------+-------+------+------------------+------------------+
        //
        // Extra: Using where
        // WARNING: MySQL must evaluate JSON_EXTRACT for every row in the result set

        return $results;
    }

    /**
     * Example 8: Bulk insert rows (efficient batch operation)
     *
     * Performance: 50-150ms for 100 rows
     */
    public static function bulk_insert_rows($database_post_id, $rows) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';

        // Build multi-row INSERT
        $values = [];
        $placeholders = [];

        foreach ($rows as $row) {
            $placeholders[] = "(%d, %s, %s, %s, %s, %s, %s, %s)";
            $values[] = $database_post_id;
            $values[] = $row['notion_page_id'];
            $values[] = $row['title'];
            $values[] = $row['created_time'];
            $values[] = $row['last_edited_time'];
            $values[] = json_encode($row['properties']);
            $values[] = $row['searchable_content'];
            $values[] = 'synced';
        }

        $placeholders_sql = implode(', ', $placeholders);

        $query = $wpdb->prepare(
            "INSERT INTO $table_name
             (database_post_id, notion_page_id, title, created_time,
              last_edited_time, properties_json, searchable_content, sync_status)
             VALUES $placeholders_sql
             ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                last_edited_time = VALUES(last_edited_time),
                properties_json = VALUES(properties_json),
                searchable_content = VALUES(searchable_content),
                sync_status = VALUES(sync_status),
                updated_at = CURRENT_TIMESTAMP",
            ...$values
        );

        $result = $wpdb->query($query);

        return $result !== false;
    }

    /**
     * Example 9: Get row by Notion page ID (for sync operations)
     *
     * Performance: 5-15ms (UNIQUE index)
     */
    public static function get_row_by_notion_id($notion_page_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';

        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE notion_page_id = %s",
            $notion_page_id
        );

        $result = $wpdb->get_row($query);

        // EXPLAIN output:
        // +----+-------------+-------+-------+-----------------+-----------------+
        // | id | select_type | table | type  | possible_keys   | key             |
        // +----+-------------+-------+-------+-----------------+-----------------+
        // |  1 | SIMPLE      | rows  | const | notion_page_id  | notion_page_id  |
        // +----+-------------+-------+-------+-----------------+-----------------+
        //
        // Extra: None (optimal - single row lookup)

        return $result;
    }

    /**
     * Example 10: Count rows by status (for dashboard/stats)
     *
     * Performance: 15-40ms for 1000 rows
     * Uses: Covering index (database_post_id, property_status)
     */
    public static function count_by_status($database_post_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';

        $query = $wpdb->prepare(
            "SELECT property_status, COUNT(*) as count
             FROM $table_name
             WHERE database_post_id = %d
             GROUP BY property_status",
            $database_post_id
        );

        $results = $wpdb->get_results($query);

        // EXPLAIN output:
        // +----+-------------+-------+-------+----------------------+----------------------+
        // | id | select_type | table | type  | possible_keys        | key                  |
        // +----+-------------+-------+-------+----------------------+----------------------+
        // |  1 | SIMPLE      | rows  | ref   | idx_property_status  | idx_property_status  |
        // +----+-------------+-------+-------+----------------------+----------------------+
        //
        // Extra: Using index (covering index optimization)

        return $results;
    }

    /**
     * Example 11: Update single property value (efficient partial update)
     *
     * Performance: 10-30ms per row
     * Uses: JSON_SET for in-place JSON modification
     */
    public static function update_property_value($row_id, $property_name, $property_value, $property_type) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';

        // Build JSON path based on property type
        $json_path = sprintf('$."%s".%s', str_replace('"', '\\"', $property_name), $property_type);

        // For simple types (number, checkbox), value can be direct
        // For complex types (select, multi_select), need proper structure
        $json_value = is_numeric($property_value) || is_bool($property_value)
            ? $property_value
            : json_encode($property_value);

        $query = $wpdb->prepare(
            "UPDATE $table_name
             SET properties_json = JSON_SET(properties_json, %s, %s),
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = %d",
            $json_path,
            $json_value,
            $row_id
        );

        $result = $wpdb->query($query);

        // Note: Virtual columns will automatically reflect the updated value
        // MySQL handles this internally

        return $result !== false;
    }

    /**
     * Example 12: Get rows that need sync (based on status)
     *
     * Performance: 20-50ms for 1000 rows
     * Uses: idx_sync_status index
     */
    public static function get_rows_needing_sync($database_post_id, $limit = 50) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';

        $query = $wpdb->prepare(
            "SELECT id, notion_page_id, title, sync_error_message
             FROM $table_name
             WHERE database_post_id = %d
             AND sync_status IN ('pending', 'error')
             ORDER BY
                CASE sync_status
                    WHEN 'pending' THEN 1
                    WHEN 'error' THEN 2
                END,
                last_synced_at ASC NULLS FIRST
             LIMIT %d",
            $database_post_id,
            $limit
        );

        $results = $wpdb->get_results($query);

        return $results;
    }

    /**
     * Performance Testing Helper
     *
     * Outputs query execution time and EXPLAIN analysis
     */
    public static function analyze_query($query) {
        global $wpdb;

        // Enable query timing
        $start_time = microtime(true);
        $results = $wpdb->get_results($query);
        $end_time = microtime(true);

        $execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds

        // Get EXPLAIN
        $explain_query = "EXPLAIN " . $query;
        $explain = $wpdb->get_results($explain_query);

        return [
            'results' => $results,
            'count' => count($results),
            'execution_time_ms' => round($execution_time, 2),
            'explain' => $explain,
        ];
    }
}
