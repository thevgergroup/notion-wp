<?php
/**
 * Database Schema Manager
 *
 * Creates and manages custom database tables for storing Notion database
 * rows as JSON documents.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Database;

/**
 * Class Schema
 *
 * Handles creation, verification, and deletion of custom database tables.
 *
 * @since 1.0.0
 */
class Schema {

	/**
	 * Custom table name for database rows (without prefix).
	 *
	 * @var string
	 */
	private const TABLE_NAME = 'notion_database_rows';

	/**
	 * Custom table name for link registry (without prefix).
	 *
	 * @var string
	 */
	private const LINKS_TABLE_NAME = 'notion_links';

	/**
	 * Create custom database tables.
	 *
	 * Called on plugin activation. Uses dbDelta for safe table creation/updates.
	 *
	 * @since 1.0.0
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$links_table     = $wpdb->prefix . self::LINKS_TABLE_NAME;

		// SQL for creating the rows table.
		// Uses LONGTEXT for JSON storage (MySQL 5.5+ compatible).
		// Extracts key fields (title, status, dates) for indexing and queries.
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			database_post_id BIGINT UNSIGNED NOT NULL,
			notion_page_id VARCHAR(36) NOT NULL UNIQUE,

			properties LONGTEXT NOT NULL,

			title VARCHAR(500),
			status VARCHAR(50),
			created_time DATETIME,
			last_edited_time DATETIME,
			synced_at DATETIME NOT NULL,

			KEY database_post_id (database_post_id),
			KEY status (status),
			KEY created_time (created_time),
			KEY last_edited_time (last_edited_time),
			FULLTEXT title_fulltext (title)
		) {$charset_collate};";

		// SQL for creating the link registry table.
		// Stores mapping between Notion IDs and WordPress resources.
		// Supports both ID formats (with/without dashes) for compatibility.
		$links_sql = "CREATE TABLE {$links_table} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			notion_id VARCHAR(32) NOT NULL COMMENT 'Notion ID without dashes',
			notion_id_uuid VARCHAR(36) NOT NULL COMMENT 'Notion ID with dashes (UUID format)',
			notion_type ENUM('page', 'database') NOT NULL,
			notion_title TEXT NOT NULL,
			notion_url VARCHAR(500) COMMENT 'Original Notion URL',
			wp_post_id BIGINT UNSIGNED NULL COMMENT 'WordPress post ID if synced',
			wp_post_type VARCHAR(20) NULL COMMENT 'post, page, notion_database, etc',
			slug VARCHAR(200) NOT NULL,
			access_count INT UNSIGNED DEFAULT 0,
			last_accessed_at DATETIME NULL,
			sync_status ENUM('not_synced', 'synced', 'deleted') DEFAULT 'not_synced',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			UNIQUE KEY slug (slug),
			KEY notion_id (notion_id),
			KEY notion_id_uuid (notion_id_uuid),
			KEY wp_post_id (wp_post_id),
			KEY sync_status (sync_status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		dbDelta( $links_sql );
	}

	/**
	 * Check if custom tables exist.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if tables exist and are accessible.
	 */
	public static function tables_exist(): bool {
		global $wpdb;

		$table_name  = $wpdb->prefix . self::TABLE_NAME;
		$links_table = $wpdb->prefix . self::LINKS_TABLE_NAME;

		$rows_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		$links_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$links_table
			)
		);

		return $rows_exists === $table_name && $links_exists === $links_table;
	}

	/**
	 * Drop custom database tables.
	 *
	 * Called on plugin uninstall if user opts to delete data.
	 * **WARNING**: This permanently deletes all synced data.
	 *
	 * @since 1.0.0
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$table_name  = $wpdb->prefix . self::TABLE_NAME;
		$links_table = $wpdb->prefix . self::LINKS_TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Intentional table drop on uninstall, table name is safe.
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Intentional table drop on uninstall, table name is safe.
		$wpdb->query( "DROP TABLE IF EXISTS {$links_table}" );
	}

	/**
	 * Get full table name with WordPress prefix.
	 *
	 * @since 1.0.0
	 *
	 * @return string Full table name for database rows.
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Get full links table name with WordPress prefix.
	 *
	 * @since 1.0.0
	 *
	 * @return string Full table name for link registry.
	 */
	public static function get_links_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::LINKS_TABLE_NAME;
	}
}
