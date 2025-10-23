<?php
/**
 * Link Registry Repository
 *
 * Manages the wp_notion_links table - central mapping of Notion resources
 * to WordPress posts/pages. Provides intelligent URL routing for /notion/{slug}.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Router;

/**
 * Class LinkRegistry
 *
 * Repository pattern for managing link registry entries. Handles:
 * - Registering Notion pages/databases in the registry
 * - Looking up links by Notion ID or slug
 * - Marking links as synced when WordPress post is created
 * - Access tracking for analytics
 * - Unique slug generation
 *
 * @since 1.0.0
 */
class LinkRegistry {

	/**
	 * Database table name (with prefix).
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'notion_links';
	}

	/**
	 * Register or update a link entry.
	 *
	 * Called during sync operations to register newly discovered links.
	 * If the entry exists, it will be updated. Otherwise, a new entry is created.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Link registration arguments.
	 *
	 *     @type string $notion_id    Notion ID without dashes (required).
	 *     @type string $notion_title Human-readable title (required).
	 *     @type string $notion_type  'page' or 'database' (required).
	 *     @type int    $wp_post_id   WordPress post ID (optional).
	 *     @type string $wp_post_type WordPress post type (optional).
	 *     @type string $slug         Custom slug (optional, auto-generated from title).
	 * }
	 * @return int|false Link entry ID on success, false on failure.
	 */
	public function register( array $args ) {
		global $wpdb;

		// Validate required fields.
		if ( empty( $args['notion_id'] ) || empty( $args['notion_title'] ) || empty( $args['notion_type'] ) ) {
			return false;
		}

		// Convert notion_id to UUID format (with dashes).
		$notion_id_uuid = $this->format_as_uuid( $args['notion_id'] );

		// Check if entry exists.
		$existing = $this->find_by_notion_id( $args['notion_id'] );

		// Generate slug if not provided.
		// If updating existing entry, keep existing slug unless:
		// 1. Slug is explicitly provided
		// 2. Slug is a Notion ID placeholder (needs replacement)
		// 3. Title has changed (slug should match new title)
		if ( isset( $args['slug'] ) ) {
			$slug = $args['slug'];
		} elseif ( $existing && $existing->slug !== $args['notion_id'] && $existing->notion_title === $args['notion_title'] ) {
			// Keep existing slug only if title hasn't changed.
			$slug = $existing->slug;
		} else {
			// Generate new slug from title (new entry, placeholder, or title changed).
			$slug = $this->generate_slug( $args['notion_title'], $args['notion_id'] );
		}

		$data = array(
			'notion_id'      => $args['notion_id'],
			'notion_id_uuid' => $notion_id_uuid,
			'notion_type'    => $args['notion_type'],
			'notion_title'   => $args['notion_title'],
			'notion_url'     => 'https://notion.so/' . $args['notion_id'],
			'slug'           => $slug,
			'sync_status'    => isset( $args['wp_post_id'] ) ? 'synced' : 'not_synced',
			'updated_at'     => current_time( 'mysql' ),
		);

		// Add wp_post_id if synced.
		if ( isset( $args['wp_post_id'] ) ) {
			$data['wp_post_id']   = $args['wp_post_id'];
			$data['wp_post_type'] = $args['wp_post_type'] ?? get_post_type( $args['wp_post_id'] );
		}

		// Build format array dynamically based on data keys.
		// Base fields: notion_id, notion_id_uuid, notion_type, notion_title, notion_url, slug, sync_status, updated_at.
		// Optional fields: wp_post_id (int), wp_post_type (string).
		// For INSERT: also created_at (string).
		$format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ); // Base 8 fields.

		// Add format specifiers for optional fields if present.
		if ( isset( $data['wp_post_id'] ) ) {
			$format[] = '%d'; // wp_post_id is integer.
			$format[] = '%s'; // wp_post_type is string.
		}

		if ( $existing ) {
			// Update existing entry.
			$result = $wpdb->update(
				$this->table_name,
				$data,
				array( 'id' => $existing->id ),
				$format,
				array( '%d' )
			);

			return false !== $result ? $existing->id : false;
		}

		// Insert new entry.
		$data['created_at'] = current_time( 'mysql' );
		$format[]           = '%s'; // Add format for created_at.

		$result = $wpdb->insert(
			$this->table_name,
			$data,
			$format
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Find link entry by Notion ID.
	 *
	 * Handles both formats (with and without dashes).
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_id Notion ID (with or without dashes).
	 * @return object|null Link entry object or null if not found.
	 */
	public function find_by_notion_id( string $notion_id ): ?object {
		global $wpdb;

		// Normalize ID (remove dashes).
		$notion_id_normalized = str_replace( '-', '', $notion_id );
		$notion_id_uuid       = $this->format_as_uuid( $notion_id_normalized );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository pattern.
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT * FROM {$this->table_name}
				 WHERE notion_id = %s OR notion_id_uuid = %s
				 LIMIT 1",
				$notion_id_normalized,
				$notion_id_uuid
			)
		);

		return $entry ? $entry : null;
	}

	/**
	 * Find link entry by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug URL slug.
	 * @return object|null Link entry object or null if not found.
	 */
	public function find_by_slug( string $slug ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository pattern.
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT * FROM {$this->table_name} WHERE slug = %s LIMIT 1",
				$slug
			)
		);

		return $entry ? $entry : null;
	}

	/**
	 * Update link when synced to WordPress.
	 *
	 * Called after successfully syncing a page/database to WordPress.
	 * Updates the registry entry with the WordPress post ID and marks as synced.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_id  Notion ID (with or without dashes).
	 * @param int    $wp_post_id WordPress post ID.
	 * @return bool True on success, false on failure.
	 */
	public function mark_as_synced( string $notion_id, int $wp_post_id ): bool {
		global $wpdb;

		$entry = $this->find_by_notion_id( $notion_id );

		if ( ! $entry ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository pattern.
		return false !== $wpdb->update(
			$this->table_name,
			array(
				'wp_post_id'   => $wp_post_id,
				'wp_post_type' => get_post_type( $wp_post_id ),
				'sync_status'  => 'synced',
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $entry->id ),
			array( '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update sync timestamps for a link.
	 *
	 * Called after successfully syncing a page to update the timestamps
	 * used for detecting outdated content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_id          Notion ID (with or without dashes).
	 * @param string $notion_last_edited Notion's last_edited_time timestamp.
	 * @param string $wp_last_synced     WordPress sync timestamp.
	 * @return bool True on success, false on failure.
	 */
	public function update_sync_timestamps( string $notion_id, string $notion_last_edited, string $wp_last_synced ): bool {
		global $wpdb;

		$entry = $this->find_by_notion_id( $notion_id );

		if ( ! $entry ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository pattern.
		return false !== $wpdb->update(
			$this->table_name,
			array(
				'notion_last_edited' => $notion_last_edited,
				'wp_last_synced'     => $wp_last_synced,
				'sync_error'         => null, // Clear any previous errors on successful sync.
				'updated_at'         => current_time( 'mysql' ),
			),
			array( 'id' => $entry->id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update sync error for a link.
	 *
	 * Called when a sync operation fails to store the error message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_id    Notion ID (with or without dashes).
	 * @param string $error_message Error message from sync operation.
	 * @return bool True on success, false on failure.
	 */
	public function update_sync_error( string $notion_id, string $error_message ): bool {
		global $wpdb;

		$entry = $this->find_by_notion_id( $notion_id );

		if ( ! $entry ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository pattern.
		return false !== $wpdb->update(
			$this->table_name,
			array(
				'sync_error' => $error_message,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $entry->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get comprehensive status for a Notion page.
	 *
	 * Determines the current sync status based on multiple factors:
	 * - Existence in link registry
	 * - Active batch processing
	 * - Sync errors
	 * - Timestamp comparison for outdated detection
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_id Notion ID (with or without dashes).
	 * @return array {
	 *     Comprehensive status information.
	 *
	 *     @type string      $status      Status: 'synced', 'not_synced', 'outdated', 'syncing', 'failed'.
	 *     @type string      $label       Human-readable status label.
	 *     @type string      $icon        Dashicon CSS class.
	 *     @type string      $color       Text color (hex).
	 *     @type string      $bg          Background color (hex).
	 *     @type string      $tooltip     Tooltip text.
	 *     @type bool        $animated    Whether to show animation (for syncing state).
	 *     @type int|null    $wp_post_id  WordPress post ID if synced.
	 *     @type string|null $error       Error message if failed.
	 *     @type array|null  $batch_info  Batch information if currently syncing.
	 * }
	 */
	public function get_comprehensive_status( string $notion_id ): array {
		// Default status for pages not in registry.
		$default_status = array(
			'status'     => 'not_synced',
			'label'      => 'Not Synced',
			'icon'       => 'dashicons-minus',
			'color'      => '#757575',
			'bg'         => '#f0f0f0',
			'tooltip'    => 'This page has not been synced to WordPress yet',
			'animated'   => false,
			'wp_post_id' => null,
			'error'      => null,
			'batch_info' => null,
		);

		// Find link entry.
		$entry = $this->find_by_notion_id( $notion_id );

		if ( ! $entry ) {
			return $default_status;
		}

		// Check if currently syncing (in an active batch).
		$batch_info = $this->get_active_batch_for_page( $notion_id );

		if ( $batch_info ) {
			return array(
				'status'     => 'syncing',
				'label'      => 'Syncing',
				'icon'       => 'dashicons-update',
				'color'      => '#2271b1',
				'bg'         => '#e5f5fa',
				'tooltip'    => 'This page is currently being synced',
				'animated'   => true,
				'wp_post_id' => $entry->wp_post_id ?? null,
				'error'      => null,
				'batch_info' => $batch_info,
			);
		}

		// Check for sync errors.
		if ( ! empty( $entry->sync_error ) ) {
			return array(
				'status'     => 'failed',
				'label'      => 'Sync Failed',
				'icon'       => 'dashicons-warning',
				'color'      => '#d63638',
				'bg'         => '#fcf0f1',
				'tooltip'    => 'Last sync failed: ' . esc_attr( $entry->sync_error ),
				'animated'   => false,
				'wp_post_id' => $entry->wp_post_id ?? null,
				'error'      => $entry->sync_error,
				'batch_info' => null,
			);
		}

		// Check if synced but outdated.
		if ( 'synced' === $entry->sync_status ) {
			// Detect outdated content: notion_last_edited > wp_last_synced.
			if (
				! empty( $entry->notion_last_edited ) &&
				! empty( $entry->wp_last_synced ) &&
				strtotime( $entry->notion_last_edited ) > strtotime( $entry->wp_last_synced )
			) {
				return array(
					'status'     => 'outdated',
					'label'      => 'Outdated',
					'icon'       => 'dashicons-update-alt',
					'color'      => '#996800',
					'bg'         => '#fcf9e8',
					'tooltip'    => 'Content has been modified in Notion since last sync',
					'animated'   => false,
					'wp_post_id' => $entry->wp_post_id,
					'error'      => null,
					'batch_info' => null,
				);
			}

			// Synced and up-to-date.
			return array(
				'status'     => 'synced',
				'label'      => 'Synced',
				'icon'       => 'dashicons-yes-alt',
				'color'      => '#00a32a',
				'bg'         => '#e7f5ec',
				'tooltip'    => 'Content is synced and up-to-date',
				'animated'   => false,
				'wp_post_id' => $entry->wp_post_id,
				'error'      => null,
				'batch_info' => null,
			);
		}

		// Entry exists but not yet synced.
		return $default_status;
	}

	/**
	 * Get active batch information for a page.
	 *
	 * Checks if a page is currently being processed in an active batch.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_id Notion ID (with or without dashes).
	 * @return array|null Batch information or null if not in active batch.
	 */
	private function get_active_batch_for_page( string $notion_id ): ?array {
		global $wpdb;

		// Normalize ID for comparison.
		$normalized_id = str_replace( '-', '', $notion_id );

		// Query all batch options to find active batches containing this page.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch status check.
		$batches = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value
				FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_name LIKE %s",
				'%notion_sync_page_batch_%',
				'%' . $wpdb->esc_like( 'notion_sync_page_batch_' ) . '%'
			)
		);

		foreach ( $batches as $batch_option ) {
			$batch = maybe_unserialize( $batch_option->option_value );

			if ( ! is_array( $batch ) ) {
				continue;
			}

			// Check if batch is active (queued or processing).
			if ( ! in_array( $batch['status'] ?? '', array( 'queued', 'processing' ), true ) ) {
				continue;
			}

			// Check if this page is in the batch.
			$page_ids = $batch['page_ids'] ?? array();

			foreach ( $page_ids as $page_id ) {
				if ( str_replace( '-', '', $page_id ) === $normalized_id ) {
					// Extract batch ID from option name.
					$batch_id = str_replace( 'notion_sync_page_batch_', '', $batch_option->option_name );

					// Get page-specific status from results.
					$page_status = $batch['page_statuses'][ $page_id ] ?? 'queued';

					return array(
						'batch_id'    => $batch_id,
						'page_status' => $page_status,
						'batch_total' => $batch['total'] ?? 0,
						'batch_processed' => $batch['processed'] ?? 0,
					);
				}
			}
		}

		return null;
	}

	/**
	 * Increment access counter for analytics.
	 *
	 * Called each time a /notion/{slug} URL is accessed.
	 * Tracks usage patterns for future features.
	 *
	 * @since 1.0.0
	 *
	 * @param int $link_id Link entry ID.
	 * @return bool True on success, false on failure.
	 */
	public function increment_access_count( int $link_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository pattern.
		return false !== $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"UPDATE {$this->table_name}
				 SET access_count = access_count + 1,
					 last_accessed_at = %s
				 WHERE id = %d",
				current_time( 'mysql' ),
				$link_id
			)
		);
	}

	/**
	 * Get slug for a Notion ID.
	 *
	 * Convenience method to get the URL slug for a Notion resource.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_id Notion ID (with or without dashes).
	 * @return string|null Slug or null if not found.
	 */
	public function get_slug_for_notion_id( string $notion_id ): ?string {
		$entry = $this->find_by_notion_id( $notion_id );
		return $entry ? $entry->slug : null;
	}

	/**
	 * Generate unique slug from title and Notion ID.
	 *
	 * Creates a URL-safe slug from the title. If the slug already exists,
	 * appends a counter (-2, -3, etc) to make it unique.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title     Notion page/database title.
	 * @param string $notion_id Notion ID (fallback if slug collision).
	 * @return string Unique slug.
	 */
	private function generate_slug( string $title, string $notion_id ): string {
		// Remove emojis from title before generating slug.
		// This prevents URL-encoded emojis in slugs (e.g., %f0%9f%93%9d).
		$title_without_emojis = $this->remove_emojis( $title );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( sprintf( '[LinkRegistry] Slug generation - Original: "%s", After emoji removal: "%s"', $title, $title_without_emojis ) );

		// Sanitize title to slug format.
		$base_slug = sanitize_title( $title_without_emojis );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( sprintf( '[LinkRegistry] Base slug: "%s"', $base_slug ) );

		if ( empty( $base_slug ) ) {
			// Title couldn't be converted to slug - use Notion ID.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[LinkRegistry] Base slug empty, using Notion ID' );
			$base_slug = $notion_id;
		}

		// Check for uniqueness.
		// Don't add counter suffix if both the new slug and existing slug are Notion IDs
		// (placeholders that will be replaced with real titles).
		$slug    = $base_slug;
		$counter = 1;

		while ( $this->slug_exists( $slug ) ) {
			// Check if the existing entry with this slug is using a Notion ID placeholder.
			$existing_entry = $this->find_by_slug( $slug );
			if ( $existing_entry && $existing_entry->slug === $existing_entry->notion_id && $slug === $notion_id ) {
				// Both are Notion ID placeholders - don't add counter, just use as-is.
				// The slug will be regenerated when the real title is provided.
				break;
			}

			$slug = $base_slug . '-' . $counter;
			++$counter;
		}

		return $slug;
	}

	/**
	 * Check if slug exists in registry.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Slug to check.
	 * @return bool True if exists, false otherwise.
	 */
	private function slug_exists( string $slug ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Repository pattern.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT COUNT(*) FROM {$this->table_name} WHERE slug = %s",
				$slug
			)
		);

		return $count > 0;
	}

	/**
	 * Format Notion ID as UUID (add dashes).
	 *
	 * Converts 32-char Notion ID to 36-char UUID format (8-4-4-4-12).
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_id Notion ID without dashes (32 chars).
	 * @return string UUID format with dashes (36 chars).
	 */
	private function format_as_uuid( string $notion_id ): string {
		// Remove any existing dashes.
		$notion_id = str_replace( '-', '', $notion_id );

		// Format: 8-4-4-4-12.
		return substr( $notion_id, 0, 8 ) . '-' .
				substr( $notion_id, 8, 4 ) . '-' .
				substr( $notion_id, 12, 4 ) . '-' .
				substr( $notion_id, 16, 4 ) . '-' .
				substr( $notion_id, 20, 12 );
	}

	/**
	 * Remove emojis from a string.
	 *
	 * Strips emoji characters to prevent URL-encoded slugs.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text to remove emojis from.
	 * @return string Text without emojis.
	 */
	private function remove_emojis( string $text ): string {
		// Remove emoji characters using regex.
		// This covers most common emoji ranges in Unicode.
		$text = preg_replace(
			'/[\x{1F600}-\x{1F64F}]/u', // Emoticons.
			'',
			$text
		);
		$text = preg_replace(
			'/[\x{1F300}-\x{1F5FF}]/u', // Misc Symbols and Pictographs.
			'',
			$text
		);
		$text = preg_replace(
			'/[\x{1F680}-\x{1F6FF}]/u', // Transport and Map.
			'',
			$text
		);
		$text = preg_replace(
			'/[\x{1F1E0}-\x{1F1FF}]/u', // Flags.
			'',
			$text
		);
		$text = preg_replace(
			'/[\x{2600}-\x{26FF}]/u', // Misc symbols.
			'',
			$text
		);
		$text = preg_replace(
			'/[\x{2700}-\x{27BF}]/u', // Dingbats.
			'',
			$text
		);
		$text = preg_replace(
			'/[\x{1F900}-\x{1F9FF}]/u', // Supplemental Symbols and Pictographs.
			'',
			$text
		);
		$text = preg_replace(
			'/[\x{1F018}-\x{1F270}]/u', // Various asian characters.
			'',
			$text
		);
		$text = preg_replace(
			'/[\x{238C}-\x{2454}]/u', // Misc items.
			'',
			$text
		);
		$text = preg_replace(
			'/[\x{20D0}-\x{20FF}]/u', // Combining Diacritical Marks for Symbols.
			'',
			$text
		);

		// Clean up extra spaces left by emoji removal.
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		return $text;
	}
}
