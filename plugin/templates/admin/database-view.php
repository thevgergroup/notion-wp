<?php
/**
 * Database View Template
 *
 * Displays a single Notion database with Tabulator.js
 *
 * @package NotionSync
 * @since 1.0.0
 *
 * Available variables:
 * @var WP_Post $post         Database post object.
 * @var string  $notion_db_id Notion database ID.
 * @var int     $row_count    Number of rows.
 * @var string  $last_synced  Last sync timestamp.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php echo esc_html( $post->post_title ); ?>
	</h1>

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=notion-sync&tab=databases' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '← Back to Databases', 'notion-wp' ); ?>
	</a>

	<hr class="wp-header-end">

	<div class="notion-database-viewer">
		<div class="database-header">
			<div>
				<h2 class="database-title"><?php echo esc_html( $post->post_title ); ?></h2>
				<div class="database-meta">
					<?php
					printf(
						/* translators: 1: row count, 2: last sync time */
						esc_html__( '%1$d rows • Last synced: %2$s', 'notion-wp' ),
						(int) $row_count,
						$last_synced ? esc_html( human_time_diff( strtotime( $last_synced ), current_time( 'timestamp' ) ) . ' ago' ) : esc_html__( 'Never', 'notion-wp' )
					);
					?>
				</div>
			</div>

			<div class="database-actions">
				<button type="button" class="button" id="reset-filters">
					<?php esc_html_e( 'Reset Filters', 'notion-wp' ); ?>
				</button>
				<button type="button" class="button" id="export-csv">
					<?php esc_html_e( 'Export CSV', 'notion-wp' ); ?>
				</button>
				<button type="button" class="button" id="export-json">
					<?php esc_html_e( 'Export JSON', 'notion-wp' ); ?>
				</button>
			</div>
		</div>

		<div id="database-table"></div>

		<div id="table-loading" style="text-align: center; padding: 40px; color: #666;">
			<span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span>
			<?php esc_html_e( 'Loading database...', 'notion-wp' ); ?>
		</div>

		<div id="table-error" style="display: none; padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24; margin-top: 20px;">
			<strong><?php esc_html_e( 'Error:', 'notion-wp' ); ?></strong>
			<span id="error-message"></span>
		</div>
	</div>
</div>
