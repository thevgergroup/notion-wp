<?php
/**
 * CLI Handler for Sync Logs
 *
 * @package NotionSync\CLI
 * @since 0.3.0
 */

namespace NotionSync\CLI;

use NotionSync\Utils\SyncLogger;
use WP_CLI;

/**
 * Handles WP-CLI commands for sync logs.
 *
 * @since 0.3.0
 */
class LogsHandler {

	/**
	 * Handle the logs command.
	 *
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public static function handle_logs_command( array $assoc_args ): void {
		// Handle resolve actions first.
		if ( isset( $assoc_args['resolve'] ) ) {
			self::resolve_log( absint( $assoc_args['resolve'] ) );
			return;
		}

		if ( isset( $assoc_args['resolve-all'] ) ) {
			self::resolve_all_logs();
			return;
		}

		// Show statistics.
		if ( isset( $assoc_args['stats'] ) ) {
			self::show_statistics();
			return;
		}

		// Show log entries.
		self::show_logs( $assoc_args );
	}

	/**
	 * Show log entries.
	 *
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	private static function show_logs( array $assoc_args ): void {
		// Build filter arguments.
		$args = [
			'limit' => absint( $assoc_args['limit'] ?? 20 ),
		];

		if ( isset( $assoc_args['severity'] ) ) {
			$args['severity'] = sanitize_text_field( $assoc_args['severity'] );
		}

		if ( isset( $assoc_args['category'] ) ) {
			$args['category'] = sanitize_text_field( $assoc_args['category'] );
		}

		if ( isset( $assoc_args['notion-page-id'] ) ) {
			$args['notion_page_id'] = sanitize_text_field( $assoc_args['notion-page-id'] );
		}

		if ( isset( $assoc_args['wp-post-id'] ) ) {
			$args['wp_post_id'] = absint( $assoc_args['wp-post-id'] );
		}

		// Get logs.
		$logs = SyncLogger::get_unresolved_logs( $args );

		if ( empty( $logs ) ) {
			WP_CLI::success( 'No unresolved sync logs found!' );
			return;
		}

		// Prepare table data.
		$table_data = [];
		foreach ( $logs as $log ) {
			$severity_color = self::get_severity_color( $log['severity'] );
			$table_data[]   = [
				'ID'          => $log['id'],
				'Severity'    => WP_CLI::colorize( "%{$severity_color}" . strtoupper( $log['severity'] ) . '%n' ),
				'Category'    => ucfirst( $log['category'] ),
				'Message'     => $log['message'],
				'Page ID'     => substr( $log['notion_page_id'], 0, 8 ) . '...',
				'Post ID'     => $log['wp_post_id'] ?? '-',
				'Created'     => human_time_diff( strtotime( $log['created_at'] ), current_time( 'timestamp' ) ) . ' ago',
			];
		}

		// Display table.
		WP_CLI\Utils\format_items( 'table', $table_data, [ 'ID', 'Severity', 'Category', 'Message', 'Page ID', 'Post ID', 'Created' ] );

		// Show count summary.
		$total_count = SyncLogger::get_unresolved_count();
		WP_CLI::log( '' );
		WP_CLI::log( sprintf( 'Showing %d of %d total unresolved logs', count( $logs ), $total_count ) );

		// Show filter hints.
		if ( ! isset( $assoc_args['severity'] ) ) {
			WP_CLI::log( WP_CLI::colorize( '%yTip: Filter by severity with --severity=error|warning|info%n' ) );
		}
		if ( ! isset( $assoc_args['category'] ) ) {
			WP_CLI::log( WP_CLI::colorize( '%yTip: Filter by category with --category=image|block|api|conversion|performance%n' ) );
		}
	}

	/**
	 * Show log statistics.
	 *
	 * @return void
	 */
	private static function show_statistics(): void {
		$total_count   = SyncLogger::get_unresolved_count();
		$error_count   = SyncLogger::get_unresolved_count( [ 'severity' => SyncLogger::SEVERITY_ERROR ] );
		$warning_count = SyncLogger::get_unresolved_count( [ 'severity' => SyncLogger::SEVERITY_WARNING ] );
		$info_count    = SyncLogger::get_unresolved_count( [ 'severity' => SyncLogger::SEVERITY_INFO ] );

		$image_count       = SyncLogger::get_unresolved_count( [ 'category' => SyncLogger::CATEGORY_IMAGE ] );
		$block_count       = SyncLogger::get_unresolved_count( [ 'category' => SyncLogger::CATEGORY_BLOCK ] );
		$api_count         = SyncLogger::get_unresolved_count( [ 'category' => SyncLogger::CATEGORY_API ] );
		$conversion_count  = SyncLogger::get_unresolved_count( [ 'category' => SyncLogger::CATEGORY_CONVERSION ] );
		$performance_count = SyncLogger::get_unresolved_count( [ 'category' => SyncLogger::CATEGORY_PERFORMANCE ] );

		WP_CLI::log( WP_CLI::colorize( '%G' . str_repeat( '=', 60 ) . '%n' ) );
		WP_CLI::log( WP_CLI::colorize( '%GSYNC LOG STATISTICS%n' ) );
		WP_CLI::log( WP_CLI::colorize( '%G' . str_repeat( '=', 60 ) . '%n' ) );
		WP_CLI::log( '' );

		WP_CLI::log( WP_CLI::colorize( '%BTotal Unresolved:%n ' . $total_count ) );
		WP_CLI::log( '' );

		WP_CLI::log( WP_CLI::colorize( '%YBy Severity:%n' ) );
		WP_CLI::log( sprintf( '  %s Errors:   %d', WP_CLI::colorize( '%r●%n' ), $error_count ) );
		WP_CLI::log( sprintf( '  %s Warnings: %d', WP_CLI::colorize( '%y●%n' ), $warning_count ) );
		WP_CLI::log( sprintf( '  %s Info:     %d', WP_CLI::colorize( '%b●%n' ), $info_count ) );
		WP_CLI::log( '' );

		WP_CLI::log( WP_CLI::colorize( '%YBy Category:%n' ) );
		if ( $image_count > 0 ) {
			WP_CLI::log( sprintf( '  Image:       %d', $image_count ) );
		}
		if ( $block_count > 0 ) {
			WP_CLI::log( sprintf( '  Block:       %d', $block_count ) );
		}
		if ( $api_count > 0 ) {
			WP_CLI::log( sprintf( '  API:         %d', $api_count ) );
		}
		if ( $conversion_count > 0 ) {
			WP_CLI::log( sprintf( '  Conversion:  %d', $conversion_count ) );
		}
		if ( $performance_count > 0 ) {
			WP_CLI::log( sprintf( '  Performance: %d', $performance_count ) );
		}

		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%G' . str_repeat( '=', 60 ) . '%n' ) );
	}

	/**
	 * Resolve a specific log entry.
	 *
	 * @param int $log_id Log entry ID.
	 * @return void
	 */
	private static function resolve_log( int $log_id ): void {
		if ( ! $log_id ) {
			WP_CLI::error( 'Invalid log ID' );
			return;
		}

		$result = SyncLogger::resolve_log( $log_id );

		if ( $result ) {
			WP_CLI::success( sprintf( 'Log #%d marked as resolved', $log_id ) );
		} else {
			WP_CLI::error( sprintf( 'Failed to resolve log #%d (does it exist?)', $log_id ) );
		}
	}

	/**
	 * Resolve all unresolved logs.
	 *
	 * @return void
	 */
	private static function resolve_all_logs(): void {
		$count = SyncLogger::get_unresolved_count();

		if ( 0 === $count ) {
			WP_CLI::success( 'No unresolved logs to resolve!' );
			return;
		}

		WP_CLI::confirm( sprintf( 'Are you sure you want to mark all %d unresolved logs as resolved?', $count ) );

		global $wpdb;
		$table_name = $wpdb->prefix . 'notion_sync_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"UPDATE {$table_name} SET resolved = 1, resolved_at = %s WHERE resolved = 0",
				current_time( 'mysql' )
			)
		);

		if ( false !== $result ) {
			WP_CLI::success( sprintf( 'Marked %d logs as resolved', $count ) );
		} else {
			WP_CLI::error( 'Failed to resolve logs' );
		}
	}

	/**
	 * Get color code for severity level.
	 *
	 * @param string $severity Severity level.
	 * @return string Color code.
	 */
	private static function get_severity_color( string $severity ): string {
		switch ( $severity ) {
			case SyncLogger::SEVERITY_ERROR:
				return 'r'; // Red.
			case SyncLogger::SEVERITY_WARNING:
				return 'y'; // Yellow.
			case SyncLogger::SEVERITY_INFO:
				return 'b'; // Blue.
			default:
				return 'n'; // Normal.
		}
	}
}
