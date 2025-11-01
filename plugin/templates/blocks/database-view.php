<?php
/**
 * Database View Block Template
 *
 * Template for rendering the notion-wp/database-view block on the frontend.
 *
 * Variables available in this template:
 * - $database_title: Database post title (escaped)
 * - $wrapper_class: CSS classes for wrapper (escaped)
 * - $data_database_id: Database post ID (escaped)
 * - $data_view_type: View type (escaped)
 * - $data_show_filters: Show filters flag (escaped)
 * - $data_show_export: Show export flag (escaped)
 * - $data_page_size: Page size (escaped)
 *
 * @package NotionWP\Blocks
 * @since 0.3.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="<?php echo esc_attr( $wrapper_class ); ?>"
	data-database-id="<?php echo esc_attr( $data_database_id ); ?>"
	data-view-type="<?php echo esc_attr( $data_view_type ); ?>"
	data-show-filters="<?php echo esc_attr( $data_show_filters ); ?>"
	data-show-export="<?php echo esc_attr( $data_show_export ); ?>"
	data-page-size="<?php echo esc_attr( $data_page_size ); ?>">

	<div class="notion-wp-database-view__header">
		<h2 class="notion-wp-database-view__title"><?php echo esc_html( $database_title ); ?></h2>
	</div>

	<div class="notion-wp-database-view__content">
		<div class="notion-wp-database-view__table"></div>
	</div>
</div>
