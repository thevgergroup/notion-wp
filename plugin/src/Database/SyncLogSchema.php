<?php
/**
 * Sync Log Database Schema
 *
 * Manages the database table for persistent sync logging.
 *
 * @package NotionSync\Database
 * @since 0.3.0
 */

namespace NotionSync\Database;

/**
 * Class SyncLogSchema
 *
 * Creates and manages the sync log database table.
 *
 * @since 0.3.0
 */
class SyncLogSchema {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	private const TABLE_NAME = 'notion_sync_logs';

	/**
	 * Get the full table name with WordPress prefix.
	 *
	 * @return string Full table name.
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create the sync log table.
	 *
	 * Called on plugin activation.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			notion_page_id varchar(255) NOT NULL,
			wp_post_id bigint(20) unsigned DEFAULT NULL,
			severity enum('info','warning','error') NOT NULL DEFAULT 'info',
			category varchar(50) NOT NULL,
			message text NOT NULL,
			context longtext DEFAULT NULL,
			resolved tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			resolved_at datetime DEFAULT NULL,
			resolved_by bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY notion_page_id (notion_page_id),
			KEY wp_post_id (wp_post_id),
			KEY severity (severity),
			KEY category (category),
			KEY resolved (resolved),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Log table creation.
		error_log(
			sprintf(
				'[NotionSync] Sync log table created or updated: %s',
				$table_name
			)
		);
	}

	/**
	 * Drop the sync log table.
	 *
	 * Called on plugin uninstall (if user chooses to remove data).
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

		error_log(
			sprintf(
				'[NotionSync] Sync log table dropped: %s',
				$table_name
			)
		);
	}

	/**
	 * Check if the table exists.
	 *
	 * @return bool True if table exists.
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $result === $table_name;
	}
}
