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
	 * Custom table name (without prefix).
	 *
	 * @var string
	 */
	private const TABLE_NAME = 'notion_database_rows';

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

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
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

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $result === $table_name;
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

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional table drop on uninstall.
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	/**
	 * Get full table name with WordPress prefix.
	 *
	 * @since 1.0.0
	 *
	 * @return string Full table name.
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}
}
