<?php
/**
 * Uninstall Script for Vger Sync for Notion
 *
 * Fired when the plugin is uninstalled via WordPress admin.
 * Removes all plugin data including options, tables, posts, and post meta.
 *
 * @package NotionSync
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * Delete plugin options
 */
$options = array(
	'notion_wp_token',
	'notion_wp_workspace_info',
	'notion_sync_menu_enabled',
	'notion_sync_menu_name',
	'notion_menu_last_sync_time',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

/**
 * Delete transients
 */
delete_transient( 'notion_wp_workspace_info_cache' );

/**
 * Delete custom database tables
 */
$tables = array(
	$wpdb->prefix . 'notion_database_rows',
	$wpdb->prefix . 'notion_sync_logs',
	$wpdb->prefix . 'notion_media_registry',
	$wpdb->prefix . 'notion_links',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

/**
 * Delete all posts of custom post type 'notion_database'
 */
$database_posts = get_posts(
	array(
		'post_type'      => 'notion_database',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

foreach ( $database_posts as $post_id ) {
	wp_delete_post( $post_id, true );
}

/**
 * Delete all post meta created by the plugin
 *
 * This removes Notion-related meta from all posts.
 */
$meta_keys = array(
	'_notion_page_id',
	'_notion_database_id',
	'_notion_last_edited',
	'_notion_synced_at',
	'notion_database_id',
	'notion_collection_id',
	'notion_last_edited',
	'row_count',
	'last_synced',
);

foreach ( $meta_keys as $meta_key ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete(
		$wpdb->postmeta,
		array( 'meta_key' => $meta_key ),
		array( '%s' )
	);
}

/**
 * Delete scheduled cron events
 */
$cron_hooks = array(
	'notion_sync_check_updates',
	'notion_sync_media_queue',
);

foreach ( $cron_hooks as $hook ) {
	$timestamp = wp_next_scheduled( $hook );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, $hook );
	}
}

/**
 * Delete the Notion menu if it exists
 */
$menu_name = get_option( 'notion_sync_menu_name', 'Notion Navigation' );
$menu      = wp_get_nav_menu_object( $menu_name );

if ( $menu ) {
	wp_delete_nav_menu( $menu->term_id );
}

/**
 * Clear any Action Scheduler actions created by the plugin
 *
 * The Action Scheduler is used for background processing.
 * This removes all scheduled, pending, and completed actions.
 */
if ( class_exists( 'ActionScheduler' ) ) {
	$action_scheduler = ActionScheduler::store();

	// Find all actions with 'notion' or 'vger_sync' in the hook name.
	$actions = $action_scheduler->query_actions(
		array(
			'hook'     => 'notion_%',
			'status'   => ActionScheduler_Store::STATUS_PENDING,
			'per_page' => -1,
		)
	);

	foreach ( $actions as $action_id ) {
		$action_scheduler->cancel_action( $action_id );
	}
}
