<?php
/**
 * Single Database Template
 *
 * Frontend template for displaying a Notion database with Tabulator.js
 * Self-contained template that doesn't rely on theme files.
 *
 * @package NotionSync
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get database metadata.
$notionsync_post_id      = get_the_ID();
$notionsync_notion_db_id = get_post_meta( $notionsync_post_id, 'notion_database_id', true );
$notionsync_row_count    = get_post_meta( $notionsync_post_id, 'row_count', true );
$notionsync_last_synced  = get_post_meta( $notionsync_post_id, 'last_synced', true );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php the_title(); ?> - <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
	<style>
		body {
			margin: 0;
			padding: 0;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			background: #f5f5f5;
			color: #333;
		}
		.site-container {
			max-width: 1400px;
			margin: 0 auto;
			padding: 40px 20px;
		}
		.site-header {
			background: #fff;
			border-bottom: 1px solid #ddd;
			margin-bottom: 40px;
			padding: 20px 0;
		}
		.site-header .container {
			max-width: 1400px;
			margin: 0 auto;
			padding: 0 20px;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.site-title {
			margin: 0;
			font-size: 20px;
			font-weight: 600;
		}
		.site-title a {
			color: #333;
			text-decoration: none;
		}
		.site-title a:hover {
			color: #2271b1;
		}
		.entry-header {
			margin-bottom: 30px;
		}
		.entry-title {
			margin: 0 0 10px 0;
			font-size: 36px;
			font-weight: 700;
			color: #333;
		}
		.entry-content {
			background: #fff;
		}
	</style>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
	<div class="container">
		<h1 class="site-title">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
		</h1>
	</div>
</header>

<div class="site-container">
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<header class="entry-header">
			<h1 class="entry-title"><?php the_title(); ?></h1>
		</header>

		<div class="entry-content">
			<div class="notion-database-viewer">
				<div class="database-header">
					<div>
						<h2 class="database-title"><?php the_title(); ?></h2>
						<div class="database-meta">
							<?php
							$notionsync_time_diff = $notionsync_last_synced
								? human_time_diff( strtotime( $notionsync_last_synced ), current_time( 'timestamp' ) ) . ' ago'
								: __( 'Never', 'vger-sync-for-notion' );
							printf(
								/* translators: 1: row count, 2: last sync time */
								esc_html__( '%1$d rows • Last synced: %2$s', 'vger-sync-for-notion' ),
								(int) $notionsync_row_count,
								esc_html( $notionsync_time_diff )
							);
							?>
						</div>
					</div>

					<div class="database-actions">
						<button type="button" id="reset-filters">
							<?php esc_html_e( 'Reset Filters', 'vger-sync-for-notion' ); ?>
						</button>
						<button type="button" id="export-csv">
							<?php esc_html_e( 'Export CSV', 'vger-sync-for-notion' ); ?>
						</button>
						<button type="button" id="export-json">
							<?php esc_html_e( 'Export JSON', 'vger-sync-for-notion' ); ?>
						</button>
					</div>
				</div>

				<div id="database-table"></div>

				<div id="table-loading">
					<span class="spinner"></span>
					<?php esc_html_e( 'Loading database...', 'vger-sync-for-notion' ); ?>
				</div>

				<div id="table-error">
					<strong><?php esc_html_e( 'Error:', 'vger-sync-for-notion' ); ?></strong>
					<span id="error-message"></span>
				</div>
			</div>
		</div>
	</article>
</div>

<?php wp_footer(); ?>
</body>
</html>
