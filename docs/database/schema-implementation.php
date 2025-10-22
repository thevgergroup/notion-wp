<?php
/**
 * Database Schema Implementation for Notion Database Rows
 *
 * This file contains the WordPress-compatible database schema setup
 * including dbDelta table creation and virtual column management.
 *
 * @package NotionWP
 * @version 1.0.0
 */

namespace NotionWP\Database;

class Schema {

    /**
     * Create database tables using dbDelta
     *
     * Called during plugin activation and version upgrades
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'notion_database_rows';
        $config_table = $wpdb->prefix . 'notion_database_property_config';
        $history_table = $wpdb->prefix . 'notion_sync_history';

        // Main rows table
        $sql_rows = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            database_post_id BIGINT(20) UNSIGNED NOT NULL,
            notion_page_id VARCHAR(50) NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            created_time DATETIME DEFAULT NULL,
            last_edited_time DATETIME DEFAULT NULL,
            properties_json JSON NOT NULL,
            searchable_content TEXT DEFAULT NULL,
            sync_status VARCHAR(20) DEFAULT 'synced',
            sync_error_message TEXT DEFAULT NULL,
            last_synced_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY notion_page_id (notion_page_id),
            KEY database_post_id (database_post_id),
            KEY idx_title (title),
            KEY idx_created_time (created_time),
            KEY idx_last_edited_time (last_edited_time),
            KEY idx_sync_status (sync_status),
            KEY idx_last_synced (last_synced_at),
            FULLTEXT KEY idx_searchable (title, searchable_content)
        ) $charset_collate;";

        // Property configuration table
        $sql_config = "CREATE TABLE $config_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            database_post_id BIGINT(20) UNSIGNED NOT NULL,
            property_id VARCHAR(100) NOT NULL,
            property_name VARCHAR(100) NOT NULL,
            property_type VARCHAR(50) NOT NULL,
            json_path VARCHAR(255) NOT NULL,
            enable_virtual_column TINYINT(1) DEFAULT 0,
            virtual_column_name VARCHAR(64) DEFAULT NULL,
            virtual_column_type VARCHAR(50) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_property (database_post_id, property_id),
            KEY idx_database (database_post_id),
            KEY idx_virtual_enabled (enable_virtual_column)
        ) $charset_collate;";

        // Sync history table
        $sql_history = "CREATE TABLE $history_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            database_post_id BIGINT(20) UNSIGNED NOT NULL,
            sync_type VARCHAR(20) NOT NULL,
            rows_added INT DEFAULT 0,
            rows_updated INT DEFAULT 0,
            rows_deleted INT DEFAULT 0,
            rows_failed INT DEFAULT 0,
            sync_status VARCHAR(20) DEFAULT 'in_progress',
            error_message TEXT DEFAULT NULL,
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_database (database_post_id),
            KEY idx_status (sync_status),
            KEY idx_started (started_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_rows);
        dbDelta($sql_config);
        dbDelta($sql_history);

        // After dbDelta, check for and create virtual columns
        self::sync_virtual_columns();
    }

    /**
     * Check database capabilities for JSON and virtual columns
     *
     * @return array Capability flags
     */
    public static function check_capabilities() {
        global $wpdb;

        $capabilities = [
            'json_support' => false,
            'virtual_columns' => false,
            'fulltext_support' => false,
            'mysql_version' => $wpdb->db_version(),
        ];

        // Check JSON support (MySQL 5.7.8+, MariaDB 10.2.7+)
        $result = $wpdb->get_var("SELECT JSON_TYPE('{}')");
        $capabilities['json_support'] = ($result === 'OBJECT');

        // Check virtual column support (MySQL 5.7.6+, MariaDB 10.2.1+)
        // We test by attempting to create a temporary table
        $test_result = $wpdb->query("
            CREATE TEMPORARY TABLE IF NOT EXISTS test_virtual_columns (
                id INT,
                json_data JSON,
                virtual_col VARCHAR(50) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(json_data, '$.test'))) VIRTUAL
            )
        ");
        $capabilities['virtual_columns'] = ($test_result !== false);
        $wpdb->query("DROP TEMPORARY TABLE IF EXISTS test_virtual_columns");

        // Check FULLTEXT support (InnoDB with MySQL 5.6+)
        $capabilities['fulltext_support'] = version_compare($wpdb->db_version(), '5.6.0', '>=');

        return $capabilities;
    }

    /**
     * Sync virtual columns based on property configuration
     *
     * This checks the property_config table and ensures virtual columns
     * exist for properties marked as enable_virtual_column=1
     */
    public static function sync_virtual_columns() {
        global $wpdb;

        $capabilities = self::check_capabilities();
        if (!$capabilities['virtual_columns']) {
            error_log('NotionWP: Virtual columns not supported on this MySQL version');
            return false;
        }

        $table_name = $wpdb->prefix . 'notion_database_rows';
        $config_table = $wpdb->prefix . 'notion_database_property_config';

        // Get all properties that should have virtual columns
        $properties = $wpdb->get_results(
            "SELECT * FROM $config_table WHERE enable_virtual_column = 1"
        );

        foreach ($properties as $property) {
            self::create_virtual_column(
                $property->virtual_column_name,
                $property->json_path,
                $property->virtual_column_type,
                $property->database_post_id
            );
        }

        return true;
    }

    /**
     * Create a virtual generated column for a Notion property
     *
     * @param string $column_name Name for the virtual column (e.g., 'property_status')
     * @param string $json_path   JSON path to extract (e.g., '$.Status.select.name')
     * @param string $column_type MySQL column type (e.g., 'VARCHAR(50)', 'DATETIME', 'INT')
     * @param int    $database_id Optional: create database-specific index
     * @return bool Success status
     */
    public static function create_virtual_column($column_name, $json_path, $column_type, $database_id = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';

        // Check if column already exists
        $column_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = %s
                AND TABLE_NAME = %s
                AND COLUMN_NAME = %s",
                DB_NAME,
                $table_name,
                $column_name
            )
        );

        if ($column_exists) {
            error_log("NotionWP: Virtual column '$column_name' already exists");
            return true;
        }

        // Sanitize column name (alphanumeric and underscore only)
        $safe_column_name = preg_replace('/[^a-z0-9_]/i', '', $column_name);
        if ($safe_column_name !== $column_name) {
            error_log("NotionWP: Invalid column name '$column_name'");
            return false;
        }

        // Create the virtual column
        $sql = sprintf(
            "ALTER TABLE %s ADD COLUMN %s %s GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(properties_json, %s))) VIRTUAL",
            $table_name,
            $safe_column_name,
            $column_type,
            $wpdb->prepare('%s', $json_path)
        );

        $result = $wpdb->query($sql);

        if ($result === false) {
            error_log("NotionWP: Failed to create virtual column '$column_name': " . $wpdb->last_error);
            return false;
        }

        // Create index on the virtual column
        $index_name = 'idx_' . $safe_column_name;

        if ($database_id) {
            // Create composite index with database_post_id for faster filtering
            $index_sql = sprintf(
                "ALTER TABLE %s ADD INDEX %s (database_post_id, %s)",
                $table_name,
                $index_name,
                $safe_column_name
            );
        } else {
            // Create single-column index
            $index_sql = sprintf(
                "ALTER TABLE %s ADD INDEX %s (%s)",
                $table_name,
                $index_name,
                $safe_column_name
            );
        }

        $index_result = $wpdb->query($index_sql);

        if ($index_result === false) {
            error_log("NotionWP: Failed to create index '$index_name': " . $wpdb->last_error);
            // Don't return false - column was created successfully
        }

        return true;
    }

    /**
     * Drop a virtual column
     *
     * @param string $column_name Name of the virtual column to drop
     * @return bool Success status
     */
    public static function drop_virtual_column($column_name) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';
        $safe_column_name = preg_replace('/[^a-z0-9_]/i', '', $column_name);

        // Drop the index first
        $index_name = 'idx_' . $safe_column_name;
        $wpdb->query(sprintf("ALTER TABLE %s DROP INDEX %s", $table_name, $index_name));

        // Drop the column
        $sql = sprintf("ALTER TABLE %s DROP COLUMN %s", $table_name, $safe_column_name);
        $result = $wpdb->query($sql);

        return ($result !== false);
    }

    /**
     * Register a Notion property for virtual column indexing
     *
     * This should be called when a user wants to enable fast filtering
     * on a specific Notion property
     *
     * @param int    $database_post_id WordPress post ID of the Notion database
     * @param string $property_id      Notion property ID
     * @param string $property_name    Human-readable property name
     * @param string $property_type    Notion property type (select, date, number, etc.)
     * @param bool   $enable_index     Whether to create virtual column immediately
     * @return int|false Insert ID or false on failure
     */
    public static function register_property_for_indexing(
        $database_post_id,
        $property_id,
        $property_name,
        $property_type,
        $enable_index = false
    ) {
        global $wpdb;

        $config_table = $wpdb->prefix . 'notion_database_property_config';

        // Generate JSON path based on property type
        $json_path = self::generate_json_path($property_name, $property_type);

        // Generate column name (sanitized)
        $column_name = 'property_' . sanitize_key($property_name);

        // Get appropriate MySQL column type
        $column_type = self::get_mysql_type_for_notion_property($property_type);

        $data = [
            'database_post_id' => $database_post_id,
            'property_id' => $property_id,
            'property_name' => $property_name,
            'property_type' => $property_type,
            'json_path' => $json_path,
            'enable_virtual_column' => $enable_index ? 1 : 0,
            'virtual_column_name' => $column_name,
            'virtual_column_type' => $column_type,
        ];

        $result = $wpdb->insert($config_table, $data);

        if ($result === false) {
            error_log('NotionWP: Failed to register property for indexing: ' . $wpdb->last_error);
            return false;
        }

        // If enable_index is true, create the virtual column now
        if ($enable_index) {
            self::create_virtual_column($column_name, $json_path, $column_type, $database_post_id);
        }

        return $wpdb->insert_id;
    }

    /**
     * Generate JSON path for extracting property value
     *
     * @param string $property_name Notion property name
     * @param string $property_type Notion property type
     * @return string JSON path expression
     */
    private static function generate_json_path($property_name, $property_type) {
        $escaped_name = str_replace('"', '\\"', $property_name);
        $base_path = '$."' . $escaped_name . '"';

        // Map Notion property types to their value extraction paths
        $type_paths = [
            'title' => '.title[0].plain_text',
            'rich_text' => '.rich_text[0].plain_text',
            'number' => '.number',
            'select' => '.select.name',
            'multi_select' => '.multi_select[0].name', // First value
            'date' => '.date.start',
            'people' => '.people[0].name',
            'files' => '.files[0].name',
            'checkbox' => '.checkbox',
            'url' => '.url',
            'email' => '.email',
            'phone_number' => '.phone_number',
            'formula' => '.formula.string', // Adjust based on formula type
            'relation' => '.relation[0].id',
            'rollup' => '.rollup.number', // Adjust based on rollup type
            'created_time' => '.created_time',
            'created_by' => '.created_by.name',
            'last_edited_time' => '.last_edited_time',
            'last_edited_by' => '.last_edited_by.name',
            'status' => '.status.name',
        ];

        $suffix = $type_paths[$property_type] ?? '.value';
        return $base_path . $suffix;
    }

    /**
     * Get appropriate MySQL column type for Notion property type
     *
     * @param string $property_type Notion property type
     * @return string MySQL column type
     */
    private static function get_mysql_type_for_notion_property($property_type) {
        $type_map = [
            'title' => 'VARCHAR(255)',
            'rich_text' => 'VARCHAR(255)',
            'number' => 'DECIMAL(20,6)',
            'select' => 'VARCHAR(100)',
            'multi_select' => 'VARCHAR(100)',
            'date' => 'DATETIME',
            'people' => 'VARCHAR(100)',
            'files' => 'VARCHAR(255)',
            'checkbox' => 'TINYINT(1)',
            'url' => 'VARCHAR(500)',
            'email' => 'VARCHAR(255)',
            'phone_number' => 'VARCHAR(50)',
            'formula' => 'VARCHAR(255)',
            'relation' => 'VARCHAR(50)',
            'rollup' => 'DECIMAL(20,6)',
            'created_time' => 'DATETIME',
            'created_by' => 'VARCHAR(100)',
            'last_edited_time' => 'DATETIME',
            'last_edited_by' => 'VARCHAR(100)',
            'status' => 'VARCHAR(100)',
        ];

        return $type_map[$property_type] ?? 'VARCHAR(255)';
    }

    /**
     * Get current schema version
     *
     * @return string Version number
     */
    public static function get_schema_version() {
        return get_option('notionwp_schema_version', '0.0.0');
    }

    /**
     * Update schema version
     *
     * @param string $version Version number
     */
    public static function update_schema_version($version) {
        update_option('notionwp_schema_version', $version);
    }

    /**
     * Check if schema needs upgrade
     *
     * @param string $required_version Required schema version
     * @return bool True if upgrade needed
     */
    public static function needs_upgrade($required_version) {
        $current_version = self::get_schema_version();
        return version_compare($current_version, $required_version, '<');
    }
}
