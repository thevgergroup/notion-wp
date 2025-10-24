<?php
/**
 * Debug script to check link registry entries
 */

// Load WordPress
require_once __DIR__ . '/../../../../wp-load.php';

global $wpdb;
$table_name = $wpdb->prefix . 'notion_link_registry';

// Find entries with this slug
$slug = 'activity-pack-creative-ai-art-or-not';
$results = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$table_name} WHERE slug = %s OR notion_title LIKE %s ORDER BY updated_at DESC LIMIT 5",
		$slug,
		'%Activity Pack%'
	)
);

echo "Link Registry Entries:\n";
echo "=====================\n\n";

if ( empty( $results ) ) {
	echo "No entries found for slug '{$slug}' or title containing 'Activity Pack'\n";
} else {
	foreach ( $results as $entry ) {
		echo "ID: {$entry->id}\n";
		echo "Notion ID: {$entry->notion_id}\n";
		echo "Slug: {$entry->slug}\n";
		echo "Title: {$entry->notion_title}\n";
		echo "Sync Status: {$entry->sync_status}\n";
		echo "WP Post ID: " . ( $entry->wp_post_id ?? 'NULL' ) . "\n";
		echo "WP Post Type: " . ( $entry->wp_post_type ?? 'NULL' ) . "\n";
		echo "Updated: {$entry->updated_at}\n";
		echo "---\n\n";
	}
}

// Also check post 57
$post = get_post( 57 );
if ( $post ) {
	echo "WordPress Post #57:\n";
	echo "==================\n";
	echo "Title: {$post->post_title}\n";
	echo "Slug: {$post->post_name}\n";
	echo "Type: {$post->post_type}\n";
	echo "Status: {$post->post_status}\n";

	$notion_id = get_post_meta( 57, 'notion_page_id', true );
	echo "Notion Page ID: " . ( $notion_id ?: 'NULL' ) . "\n";
}
