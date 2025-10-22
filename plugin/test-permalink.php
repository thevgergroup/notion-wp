<?php
/**
 * Test permalink resolution
 */

WP_CLI::line( "Testing Permalink Resolution" );
WP_CLI::line( "============================" );
WP_CLI::line( "" );

// Check post 7
$post_7 = get_post(7);
WP_CLI::line( "Post 7:" );
WP_CLI::line( "  Title: " . $post_7->post_title );
WP_CLI::line( "  Type: " . $post_7->post_type );
WP_CLI::line( "  Name: " . $post_7->post_name );
WP_CLI::line( "  Permalink: " . get_permalink(7) );
WP_CLI::line( "" );

// Check post 43
$post_43 = get_post(43);
WP_CLI::line( "Post 43:" );
WP_CLI::line( "  Title: " . $post_43->post_title );
WP_CLI::line( "  Type: " . $post_43->post_type );
WP_CLI::line( "  Name: " . $post_43->post_name );
WP_CLI::line( "  Permalink: " . get_permalink(43) );
WP_CLI::line( "" );

// Check post 31
$post_31 = get_post(31);
WP_CLI::line( "Post 31:" );
WP_CLI::line( "  Title: " . $post_31->post_title );
WP_CLI::line( "  Type: " . $post_31->post_type );
WP_CLI::line( "  Name: " . $post_31->post_name );
WP_CLI::line( "  Permalink: " . get_permalink(31) );
WP_CLI::line( "" );

// Check for slug conflicts
global $wpdb;
$slug = 'ai-education-resources';
$posts = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT ID, post_title, post_name, post_type FROM {$wpdb->posts} WHERE post_name = %s",
		$slug
	)
);

WP_CLI::line( "Posts with slug 'ai-education-resources':" );
foreach ( $posts as $p ) {
	WP_CLI::line( "  ID: {$p->ID}, Title: {$p->post_title}, Type: {$p->post_type}" );
}
WP_CLI::line( "" );

// Check notion_database CPT rewrite settings
$cpt = get_post_type_object('notion_database');
if ( $cpt ) {
	WP_CLI::line( "notion_database CPT settings:" );
	WP_CLI::line( "  Public: " . ( $cpt->public ? 'Yes' : 'No' ) );
	WP_CLI::line( "  Publicly Queryable: " . ( $cpt->publicly_queryable ? 'Yes' : 'No' ) );
	WP_CLI::line( "  Has Archive: " . ( $cpt->has_archive ? 'Yes' : 'No' ) );
	if ( is_array($cpt->rewrite) ) {
		WP_CLI::line( "  Rewrite slug: " . $cpt->rewrite['slug'] );
		WP_CLI::line( "  Rewrite with_front: " . ( $cpt->rewrite['with_front'] ? 'Yes' : 'No' ) );
	}
	WP_CLI::line( "" );
}

// Test URL resolution
WP_CLI::line( "URL Resolution Tests:" );
$url_7 = get_permalink(7);
WP_CLI::line( "  Post 7 URL: " . $url_7 );

// Try to query by the database post type and slug
$query_args = array(
	'post_type' => 'notion_database',
	'name' => 'ai-education-resources',
	'posts_per_page' => 1,
);
$query = new WP_Query($query_args);
if ( $query->have_posts() ) {
	$found_post = $query->posts[0];
	WP_CLI::line( "  Query by slug 'ai-education-resources' found: ID {$found_post->ID} - {$found_post->post_title}" );
}
wp_reset_postdata();
