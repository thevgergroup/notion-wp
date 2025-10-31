<?php
/**
 * Media Registry
 *
 * Tracks mappings between Notion media and WordPress attachments.
 * Extends the LinkRegistry pattern from Phase 2 to media files.
 *
 * @package NotionSync\Media
 * @since 0.3.0
 */

namespace NotionSync\Media;

/**
 * Class MediaRegistry
 *
 * Registry for tracking Notion media → WordPress attachments.
 * Prevents duplicate uploads and enables cross-reference resolution.
 *
 * Pattern: notion_identifier (block_id|file_url) → attachment_id → media_url
 *
 * @since 0.3.0
 */
class MediaRegistry {

	/**
	 * Database table name (without prefix).
	 *
	 * @var string
	 */
	private const TABLE_NAME = 'notion_media_registry';

	/**
	 * Get the full table name with WordPress prefix.
	 *
	 * @return string Table name.
	 */
	private static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create the registry table.
	 *
	 * Called during plugin activation.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			notion_identifier varchar(255) NOT NULL,
			attachment_id bigint(20) UNSIGNED DEFAULT NULL,
			notion_file_url text,
			status varchar(20) NOT NULL DEFAULT 'uploaded',
			registered_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			error_count int DEFAULT 0,
			last_error text,
			PRIMARY KEY  (id),
			UNIQUE KEY notion_identifier (notion_identifier),
			KEY attachment_id (attachment_id),
			KEY registered_at (registered_at),
			KEY updated_at (updated_at),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Register a Notion media file in the registry.
	 *
	 * @param string   $notion_identifier Notion block ID or file URL hash.
	 * @param int|null $attachment_id     WordPress attachment ID (null for unsupported/external).
	 * @param string   $notion_file_url   Optional original Notion file URL.
	 * @param string   $status            Status: 'uploaded', 'unsupported', 'external'.
	 * @return bool True on success.
	 */
	public static function register(
		string $notion_identifier,
		?int $attachment_id = null,
		string $notion_file_url = '',
		string $status = 'uploaded'
	): bool {
		global $wpdb;

		// Verify attachment exists if attachment_id provided.
		if ( null !== $attachment_id && ! get_post( $attachment_id ) ) {
			error_log( "MediaRegistry: Attachment ID {$attachment_id} does not exist" );
			return false;
		}

		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'notion_identifier' => $notion_identifier,
				'attachment_id'     => $attachment_id,
				'notion_file_url'   => $notion_file_url,
				'status'            => $status,
				'registered_at'     => current_time( 'mysql' ),
			],
			[ '%s', '%d', '%s', '%s', '%s' ]
		);

		if ( false === $result ) {
			error_log(
				sprintf(
					'MediaRegistry: Failed to register %s → %s: %s',
					$notion_identifier,
					$attachment_id ? $attachment_id : 'null',
					$wpdb->last_error
				)
			);
			return false;
		}

		// Invalidate cache for this identifier.
		$cache_key = 'media_registry_' . md5( $notion_identifier );
		wp_cache_delete( $cache_key, 'notion_sync' );

		return true;
	}

	/**
	 * Find WordPress attachment ID by Notion identifier.
	 *
	 * @param string $notion_identifier Notion block ID or file URL hash.
	 * @return int|null Attachment ID or null if not found.
	 */
	public static function find( string $notion_identifier ): ?int {
		// Check object cache first to reduce database queries.
		$cache_key = 'media_registry_' . md5( $notion_identifier );
		$cached    = wp_cache_get( $cache_key, 'notion_sync' );

		if ( false !== $cached ) {
			// Return cached value (could be null, so check for false specifically).
			return $cached;
		}

		global $wpdb;

		$table_name = self::get_table_name();

		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT attachment_id FROM {$table_name} WHERE notion_identifier = %s LIMIT 1",
				$notion_identifier
			)
		);

		$result = $attachment_id ? (int) $attachment_id : null;

		// Cache the result (including null) for 1 hour.
		wp_cache_set( $cache_key, $result, 'notion_sync', HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Check if a Notion identifier exists in the registry.
	 *
	 * @param string $notion_identifier Notion block ID or file URL hash.
	 * @return bool True if exists.
	 */
	public static function exists( string $notion_identifier ): bool {
		global $wpdb;

		$table_name = self::get_table_name();

		$count = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT COUNT(*) FROM {$table_name} WHERE notion_identifier = %s LIMIT 1",
				$notion_identifier
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Get status of a Notion identifier.
	 *
	 * @param string $notion_identifier Notion block ID or file URL hash.
	 * @return string|null Status ('uploaded', 'unsupported', 'external') or null if not found.
	 */
	public static function get_status( string $notion_identifier ): ?string {
		global $wpdb;

		$table_name = self::get_table_name();

		$status = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT status FROM {$table_name} WHERE notion_identifier = %s LIMIT 1",
				$notion_identifier
			)
		);

		return $status ? $status : null;
	}

	/**
	 * Get WordPress media URL from Notion identifier.
	 *
	 * @param string $notion_identifier Notion block ID or file URL hash.
	 * @return string|null Media URL or null if not found.
	 */
	public static function get_media_url( string $notion_identifier ): ?string {
		$attachment_id = self::find( $notion_identifier );

		if ( ! $attachment_id ) {
			return null;
		}

		$url = wp_get_attachment_url( $attachment_id );
		return $url ? $url : null;
	}

	/**
	 * Get original Notion file URL from identifier.
	 *
	 * @param string $notion_identifier Notion block ID or file URL hash.
	 * @return string|null Notion file URL or null if not found.
	 */
	public static function get_notion_url( string $notion_identifier ): ?string {
		global $wpdb;

		$table_name = self::get_table_name();

		$notion_url = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT notion_file_url FROM {$table_name} WHERE notion_identifier = %s LIMIT 1",
				$notion_identifier
			)
		);

		return $notion_url ? $notion_url : null;
	}

	/**
	 * Update registry entry.
	 *
	 * Used when media is replaced in Notion.
	 *
	 * @param string   $notion_identifier Notion block ID or file URL hash.
	 * @param int|null $attachment_id     New WordPress attachment ID.
	 * @param string   $notion_file_url   New Notion file URL.
	 * @param string   $status            Status: 'uploaded', 'unsupported', 'external'.
	 * @return bool True on success.
	 */
	public static function update(
		string $notion_identifier,
		?int $attachment_id = null,
		string $notion_file_url = '',
		string $status = 'uploaded'
	): bool {
		global $wpdb;

		// Verify attachment exists if provided.
		if ( null !== $attachment_id && ! get_post( $attachment_id ) ) {
			error_log( "MediaRegistry: Attachment ID {$attachment_id} does not exist" );
			return false;
		}

		$result = $wpdb->update(
			self::get_table_name(),
			[
				'attachment_id'   => $attachment_id,
				'notion_file_url' => $notion_file_url,
				'status'          => $status,
				'updated_at'      => current_time( 'mysql' ),
			],
			[ 'notion_identifier' => $notion_identifier ],
			[ '%d', '%s', '%s', '%s' ],
			[ '%s' ]
		);

		if ( false === $result ) {
			error_log(
				sprintf(
					'MediaRegistry: Failed to update %s: %s',
					$notion_identifier,
					$wpdb->last_error
				)
			);
			return false;
		}

		// Invalidate cache for this identifier.
		$cache_key = 'media_registry_' . md5( $notion_identifier );
		wp_cache_delete( $cache_key, 'notion_sync' );

		return true;
	}

	/**
	 * Delete registry entry.
	 *
	 * @param string $notion_identifier Notion block ID or file URL hash.
	 * @return bool True on success.
	 */
	public static function delete( string $notion_identifier ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			self::get_table_name(),
			[ 'notion_identifier' => $notion_identifier ],
			[ '%s' ]
		);

		if ( false !== $result ) {
			// Invalidate cache for this identifier.
			$cache_key = 'media_registry_' . md5( $notion_identifier );
			wp_cache_delete( $cache_key, 'notion_sync' );
		}

		return false !== $result;
	}

	/**
	 * Find all media for a specific attachment.
	 *
	 * @param int $attachment_id WordPress attachment ID.
	 * @return array Array of Notion identifiers.
	 */
	public static function find_by_attachment( int $attachment_id ): array {
		global $wpdb;

		$table_name = self::get_table_name();

		$results = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT notion_identifier FROM {$table_name} WHERE attachment_id = %d",
				$attachment_id
			)
		);

		return $results ? $results : [];
	}

	/**
	 * Check if media needs to be re-uploaded.
	 *
	 * Compares stored Notion URL with current URL to detect replacements.
	 *
	 * @param string $notion_identifier Notion block ID.
	 * @param string $current_url       Current Notion file URL.
	 * @return bool True if needs re-upload.
	 */
	public static function needs_reupload( string $notion_identifier, string $current_url ): bool {
		$stored_url = self::get_notion_url( $notion_identifier );

		// If no stored URL, can't determine - assume doesn't need reupload.
		if ( ! $stored_url ) {
			return false;
		}

		// Strip query parameters for comparison (Notion adds expiry timestamps).
		$stored_base = strtok( $stored_url, '?' );
		$current_base = strtok( $current_url, '?' );

		return $stored_base !== $current_base;
	}

	/**
	 * Get registry statistics.
	 *
	 * @return array {
	 *     Registry statistics.
	 *
	 *     @type int $total_entries      Total registry entries.
	 *     @type int $total_attachments  Unique attachment count.
	 *     @type int $orphaned           Entries with deleted attachments.
	 * }
	 */
	public static function get_stats(): array {
		global $wpdb;

		$table_name = self::get_table_name();

		// Total entries.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

		// Unique attachments.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$unique = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT attachment_id) FROM {$table_name}" );

		// Orphaned entries (attachment deleted).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$orphaned = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$table_name} r
			LEFT JOIN {$wpdb->posts} p ON r.attachment_id = p.ID
			WHERE p.ID IS NULL"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return [
			'total_entries'     => $total,
			'total_attachments' => $unique,
			'orphaned'          => $orphaned,
		];
	}

	/**
	 * Clean up orphaned registry entries.
	 *
	 * Removes entries where the WordPress attachment no longer exists.
	 *
	 * @return int Number of entries cleaned.
	 */
	public static function cleanup_orphaned(): int {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$result = $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
			"DELETE r FROM {$table_name} r
			LEFT JOIN {$wpdb->posts} p ON r.attachment_id = p.ID
			WHERE p.ID IS NULL"
		);

		return false !== $result ? (int) $result : 0;
	}

	/**
	 * Clear all registry entries.
	 *
	 * WARNING: This will remove all media tracking data.
	 *
	 * @return bool True on success.
	 */
	public static function clear_all(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table name is safe.
		$result = $wpdb->query( 'TRUNCATE TABLE ' . self::get_table_name() );
		return false !== $result;
	}

	/**
	 * Generate identifier from Notion file URL.
	 *
	 * Creates a consistent hash for file URLs.
	 *
	 * @param string $url Notion file URL.
	 * @return string Hash identifier.
	 */
	public static function hash_url( string $url ): string {
		// Strip query parameters (they include expiry timestamps).
		$base_url = strtok( $url, '?' );
		return 'url_' . md5( $base_url );
	}
}
