<?php
/**
 * Settings Page Template
 *
 * Template for the Notion Sync settings page.
 * Displays connection form or workspace information based on connection status.
 *
 * @package NotionSync
 * @since 0.1.0
 *
 * Available variables:
 * @var bool                                         $is_connected     Whether user is connected to Notion.
 * @var array                                        $workspace_info   Workspace information array.
 * @var \NotionSync\Admin\PagesListTable|null        $list_table       Pages list table instance.
 * @var \NotionSync\Admin\DatabasesListTable|null    $databases_table  Databases list table instance.
 * @var string                                       $current_tab      Current active tab.
 * @var string                                       $error_message    Error message to display (if any).
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors( 'notion_sync' ); ?>

	<?php if ( ! empty( $error_message ) ) : ?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Warning:', 'notion-wp' ); ?></strong>
				<?php echo esc_html( $error_message ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="notion-sync-settings">

		<?php if ( ! $is_connected ) : ?>

			<!-- Connection Form -->
			<div class="card connection-card">
				<h2><?php esc_html_e( 'Connect to Notion', 'notion-wp' ); ?></h2>

				<p>
					<?php
					echo wp_kses(
						__( 'To get started, you need to create a Notion integration and obtain an API token.', 'notion-wp' ),
						array()
					);
					?>
				</p>

				<ol style="margin-left: 20px; line-height: 1.8;">
					<li>
						<?php
						printf(
						/* translators: %s: URL to Notion integrations page */
							wp_kses(
							/* translators: %s: URL to Notion integrations page */
								__(
									// phpcs:ignore Generic.Files.LineLength.MaxExceeded
									'Visit <a href="%s" target="_blank" rel="noopener noreferrer">Notion Integrations</a> and create a new integration.',
									'notion-wp'
								),
								array(
									'a' => array(
										'href'   => array(),
										'target' => array(),
										'rel'    => array(),
									),
								)
							),
							esc_url( 'https://www.notion.com/my-integrations' )
						);

						?>
					</li>
					<li><?php esc_html_e( 'Copy the "Internal Integration Token" (starts with "secret_").', 'notion-wp' ); ?></li>
					<li><?php esc_html_e( 'Paste the token below and click "Connect to Notion".', 'notion-wp' ); ?></li>
					<li><?php esc_html_e( 'Share your Notion pages with the integration to grant access.', 'notion-wp' ); ?></li>
				</ol>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;">
					<input type="hidden" name="action" value="notion_sync_connect">
					<?php wp_nonce_field( 'notion_sync_connect', 'notion_sync_connect_nonce' ); ?>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="notion_token">
										<?php esc_html_e( 'Notion API Token', 'notion-wp' ); ?>
									</label>
								</th>
								<td>
									<input
										type="password"
										name="notion_token"
										id="notion_token"
										class="regular-text"
										style="font-family: monospace; width: 100%; max-width: 500px;"
										placeholder="secret_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
										required
										autocomplete="off"
									>
									<p class="description">
										<?php esc_html_e( 'Your Notion Internal Integration Token. Starts with "secret_".', 'notion-wp' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary button-large">
							<?php esc_html_e( 'Connect to Notion', 'notion-wp' ); ?>
						</button>
					</p>
				</form>
			</div>

		<?php else : ?>

			<!-- Tab Navigation -->
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'pages', admin_url( 'admin.php?page=notion-sync' ) ) ); ?>"
					class="nav-tab <?php echo 'pages' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Pages', 'notion-wp' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'databases', admin_url( 'admin.php?page=notion-sync' ) ) ); ?>"
					class="nav-tab <?php echo 'databases' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Databases', 'notion-wp' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', admin_url( 'admin.php?page=notion-sync' ) ) ); ?>"
					class="nav-tab <?php echo 'settings' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'notion-wp' ); ?>
				</a>
			</h2>

			<?php if ( 'databases' === $current_tab ) : ?>

				<!-- Databases Tab Content -->
				<div id="notion-sync-dashboard"></div>
				<div id="notion-sync-messages" style="margin-top: 20px;"></div>

				<?php if ( null !== $databases_table && ! empty( $databases_table->items ) ) : ?>
					<div class="card" style="margin-top: 20px;">
						<form method="post">
							<?php $databases_table->display(); ?>
						</form>
					</div>
				<?php else : ?>
					<div class="card" style="margin-top: 20px;">
						<h2><?php esc_html_e( 'No Databases Found', 'notion-wp' ); ?></h2>
						<p>
							<?php esc_html_e( 'No databases are currently accessible by this integration.', 'notion-wp' ); ?>
						</p>
						<p>
							<?php esc_html_e( 'To grant access to your Notion databases:', 'notion-wp' ); ?>
						</p>
						<ol style="margin-left: 20px; line-height: 1.8;">
							<li><?php esc_html_e( 'Open a database in Notion', 'notion-wp' ); ?></li>
							<li><?php esc_html_e( 'Click the "..." menu in the top right', 'notion-wp' ); ?></li>
							<li><?php esc_html_e( 'Select "Add connections"', 'notion-wp' ); ?></li>
							<li><?php esc_html_e( 'Choose your integration from the list', 'notion-wp' ); ?></li>
						</ol>
					</div>
				<?php endif; ?>

			<?php elseif ( 'settings' === $current_tab ) : ?>

				<!-- Settings Tab Content -->

				<!-- Connection Status -->
				<div class="card connection-card" style="margin-top: 20px;">
					<h2><?php esc_html_e( 'Connection Status', 'notion-wp' ); ?></h2>

					<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
						<span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 24px;"></span>
						<strong style="color: #46b450; font-size: 16px;">
							<?php esc_html_e( 'Connected to Notion', 'notion-wp' ); ?>
						</strong>
					</div>

					<table class="form-table" role="presentation">
						<tbody>
							<?php if ( ! empty( $workspace_info['workspace_name'] ) ) : ?>
								<tr>
									<th scope="row"><?php esc_html_e( 'Workspace', 'notion-wp' ); ?></th>
									<td><strong><?php echo esc_html( $workspace_info['workspace_name'] ); ?></strong></td>
								</tr>
							<?php endif; ?>

							<?php if ( ! empty( $workspace_info['user_name'] ) ) : ?>
								<tr>
									<th scope="row"><?php esc_html_e( 'Integration Name', 'notion-wp' ); ?></th>
									<td><?php echo esc_html( $workspace_info['user_name'] ); ?></td>
								</tr>
							<?php endif; ?>

							<?php if ( ! empty( $workspace_info['bot_id'] ) ) : ?>
								<tr>
									<th scope="row"><?php esc_html_e( 'Integration ID', 'notion-wp' ); ?></th>
									<td><code><?php echo esc_html( $workspace_info['bot_id'] ); ?></code></td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;">
						<input type="hidden" name="action" value="notion_sync_disconnect">
						<?php wp_nonce_field( 'notion_sync_disconnect', 'notion_sync_disconnect_nonce' ); ?>

						<button
							type="submit"
							class="button button-secondary"
						onclick="return confirm(
							<?php
							echo esc_js(
								__( 'Are you sure you want to disconnect from Notion? This will remove your API token.', 'notion-wp' )
							);
							?>
						);"
						>
							<?php esc_html_e( 'Disconnect', 'notion-wp' ); ?>
						</button>
					</form>
				</div>

				<!-- Maintenance Tools -->
				<div class="card" style="margin-top: 20px; max-width: 800px;">
					<h2><?php esc_html_e( 'Maintenance Tools', 'notion-wp' ); ?></h2>

					<p>
						<?php esc_html_e( 'Use these tools to troubleshoot issues with the plugin.', 'notion-wp' ); ?>
					</p>

					<h3 style="margin-top: 20px;"><?php esc_html_e( 'Flush Rewrite Rules', 'notion-wp' ); ?></h3>
					<p class="description">
						<?php
						esc_html_e(
							'If /notion/{slug} URLs are not working correctly, ' .
							'click this button to flush and regenerate WordPress rewrite rules.',
							'notion-wp'
						);
						?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 10px;">
						<input type="hidden" name="action" value="notion_sync_flush_rewrites">
						<?php wp_nonce_field( 'notion_sync_flush_rewrites', 'notion_sync_flush_rewrites_nonce' ); ?>

						<button type="submit" class="button button-secondary">
							<?php esc_html_e( 'Flush Rewrite Rules', 'notion-wp' ); ?>
						</button>
					</form>
				</div>

			<?php else : ?>

				<!-- Pages Tab Content -->

				<!-- Notion Pages List Table -->
				<?php if ( null !== $list_table ) : ?>
				<div class="card" style="margin-top: 20px;">
					<h2><?php esc_html_e( 'Notion Pages', 'notion-wp' ); ?></h2>

					<p>
						<?php esc_html_e( 'Select pages to sync to WordPress. Pages will be created as draft posts.', 'notion-wp' ); ?>
					</p>

					<!-- Admin notice container for AJAX messages -->
					<div id="notion-sync-dashboard"></div>
					<div id="notion-sync-messages" style="margin-top: 15px;"></div>

					<form id="notion-pages-form" method="post" style="margin-top: 15px;">
						<?php wp_nonce_field( 'bulk-pages' ); ?>
						<?php $list_table->display(); ?>
					</form>

					<p class="description" style="margin-top: 15px;">
						<?php
						printf(
							esc_html(
								/* translators: %d: number of pages accessible to integration */
								_n(
									'Showing %d page. To access more pages, share them with your integration in Notion.',
									'Showing %d pages. To access more pages, share them with your integration in Notion.',
									count( $list_table->items ),
									'notion-wp'
								)
							),
							count( $list_table->items )
						);
						?>
					</p>
				</div>
			<?php else : ?>
				<div class="card" style="margin-top: 20px;">
					<h2><?php esc_html_e( 'No Pages Found', 'notion-wp' ); ?></h2>

					<p>
						<?php esc_html_e( 'No pages are currently accessible by this integration.', 'notion-wp' ); ?>
					</p>

					<p>
						<?php esc_html_e( 'To grant access to your Notion pages:', 'notion-wp' ); ?>
					</p>

					<ol style="margin-left: 20px; line-height: 1.8;">
						<li><?php esc_html_e( 'Open a page in Notion', 'notion-wp' ); ?></li>
						<li><?php esc_html_e( 'Click the "..." menu in the top right', 'notion-wp' ); ?></li>
						<li><?php esc_html_e( 'Select "Add connections"', 'notion-wp' ); ?></li>
						<li><?php esc_html_e( 'Choose your integration from the list', 'notion-wp' ); ?></li>
					</ol>
				</div>
			<?php endif; ?>

		<?php endif; ?>

	<?php endif; ?>

	</div>
</div>

<style>
/* Minimal inline styles for better presentation */
.notion-sync-settings .card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 20px;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

/* Connection card has limited width for better form UX */
.notion-sync-settings .connection-card {
	max-width: 800px;
}

/* Pages list table uses full width for better data visibility */
.notion-sync-settings .card:not(.connection-card) {
	max-width: none;
}

.notion-sync-settings .card h2 {
	margin-top: 0;
	font-size: 18px;
	border-bottom: 1px solid #e0e0e0;
	padding-bottom: 10px;
	margin-bottom: 15px;
}

/* Ensure WP_List_Table uses available width */
.notion-sync-settings .wp-list-table {
	max-width: 100%;
}

/* Improve table spacing and readability */
.notion-sync-settings .wp-list-table th,
.notion-sync-settings .wp-list-table td {
	padding: 12px 10px;
}

.notion-sync-settings .wp-list-table thead th {
	font-weight: 600;
	background: #f9fafb;
}

/* Column width optimization */
.notion-sync-settings .wp-list-table .column-cb {
	width: 2.5%;
}

.notion-sync-settings .wp-list-table .column-title {
	width: 30%;
}

.notion-sync-settings .wp-list-table .column-type {
	width: 12%;
}

.notion-sync-settings .wp-list-table .column-notion_id {
	width: 12%;
}

.notion-sync-settings .wp-list-table .column-sync_status {
	width: 12%;
}

.notion-sync-settings .wp-list-table .column-wp_post {
	width: 12%;
}

.notion-sync-settings .wp-list-table .column-last_synced {
	width: 14%;
}

/* Better row hover state */
.notion-sync-settings .wp-list-table tbody tr:hover {
	background-color: #f9fafb;
}

@media screen and (max-width: 782px) {
	.notion-sync-settings .connection-card {
		max-width: 100% !important;
	}

	.notion-sync-settings .form-table th,
	.notion-sync-settings .form-table td {
		display: block;
		width: 100% !important;
		padding: 10px 0 !important;
	}

	.notion-sync-settings input[type="password"] {
		width: 100% !important;
		max-width: 100% !important;
	}
}
</style>
