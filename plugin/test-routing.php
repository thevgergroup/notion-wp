<?php
/**
 * Test NotionRouter behavior
 */

use NotionSync\Router\LinkRegistry;

WP_CLI::line( "Testing NotionRouter Logic" );
WP_CLI::line( "==========================" );
WP_CLI::line( "" );

$registry = new LinkRegistry();

// Test the database link
$slug = 'ai-education-resources';
$link_entry = $registry->find_by_slug( $slug );

WP_CLI::line( "Link Registry Entry for '{$slug}':" );
if ( $link_entry ) {
	WP_CLI::line( "  Notion ID: " . $link_entry->notion_id );
	WP_CLI::line( "  Title: " . $link_entry->notion_title );
	WP_CLI::line( "  Type: " . $link_entry->notion_type );
	WP_CLI::line( "  Sync Status: " . $link_entry->sync_status );
	WP_CLI::line( "  WP Post ID: " . ( $link_entry->wp_post_id ?? 'null' ) );
	WP_CLI::line( "" );

	if ( 'synced' === $link_entry->sync_status && $link_entry->wp_post_id ) {
		$permalink = get_permalink( $link_entry->wp_post_id );
		WP_CLI::line( "  Router Action: Redirect to WordPress permalink" );
		WP_CLI::line( "  Target URL: " . $permalink );
	} else {
		$notion_url = 'https://notion.so/' . $link_entry->notion_id;
		WP_CLI::line( "  Router Action: Redirect to Notion" );
		WP_CLI::line( "  Target URL: " . $notion_url );
	}
} else {
	WP_CLI::line( "  NOT FOUND - would return 404" );
}
WP_CLI::line( "" );

// Check if the database post is accessible
WP_CLI::line( "Database Post Accessibility:" );
$post_7 = get_post( 7 );
if ( $post_7 ) {
	WP_CLI::line( "  Post exists: Yes" );
	WP_CLI::line( "  Post status: " . $post_7->post_status );
	WP_CLI::line( "  Public permalink: " . get_permalink( 7 ) );

	// Try to access the post
	$query = new WP_Query(
		array(
			'post_type' => 'notion_database',
			'name'      => 'ai-education-resources',
		)
	);

	if ( $query->have_posts() ) {
		WP_CLI::line( "  Query successful: Yes (post is publicly accessible)" );
	} else {
		WP_CLI::line( "  Query successful: No (post may not be accessible)" );
	}
	wp_reset_postdata();
} else {
	WP_CLI::line( "  Post exists: No" );
}
