<?php
/**
 * Sync Logs Admin Page
 *
 * Displays sync issues and warnings for review.
 *
 * @package NotionSync\Admin
 * @since 0.3.0
 */

namespace NotionSync\Admin;

use NotionSync\Utils\SyncLogger;

/**
 * Class SyncLogsPage
 *
 * Admin page for viewing and managing sync logs.
 *
 * @since 0.3.0
 */
class SyncLogsPage {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'notion-sync-logs';

	/**
	 * Register the admin page.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_post_notion_sync_resolve_log', [ $this, 'handle_resolve_log' ] );
		add_action( 'admin_post_notion_sync_resolve_all', [ $this, 'handle_resolve_all' ] );
	}

	/**
	 * Add the menu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$unresolved_count = SyncLogger::get_unresolved_count();
		$menu_title       = 'Sync Logs';

		if ( $unresolved_count > 0 ) {
			$menu_title .= sprintf(
				' <span class="update-plugins count-%d"><span class="plugin-count">%d</span></span>',
				$unresolved_count,
				$unresolved_count
			);
		}

		add_submenu_page(
			'vger-sync-for-notion',
			'Sync Logs',
			$menu_title,
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		// Get filter parameters.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$severity = isset( $_GET['severity'] ) ? sanitize_text_field( wp_unslash( $_GET['severity'] ) ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : null;

		$args = [];
		if ( $severity ) {
			$args['severity'] = $severity;
		}
		if ( $category ) {
			$args['category'] = $category;
		}

		// Get logs.
		$logs = SyncLogger::get_unresolved_logs( $args );

		// Get counts for filters.
		$total_count   = SyncLogger::get_unresolved_count();
		$error_count   = SyncLogger::get_unresolved_count( [ 'severity' => SyncLogger::SEVERITY_ERROR ] );
		$warning_count = SyncLogger::get_unresolved_count( [ 'severity' => SyncLogger::SEVERITY_WARNING ] );
		$info_count    = SyncLogger::get_unresolved_count( [ 'severity' => SyncLogger::SEVERITY_INFO ] );

		?>
		<div class="wrap">
			<h1>Sync Logs</h1>

			<?php if ( ! empty( $_GET['resolved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p>Log entry marked as resolved.</p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $_GET['resolved_all'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p>All log entries marked as resolved.</p>
				</div>
			<?php endif; ?>

			<div class="notion-sync-logs">
				<!-- Filters -->
				<ul class="subsubsub">
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?>"
							<?php echo ! $severity && ! $category ? 'class="current"' : ''; ?>>
							All <span class="count">(<?php echo esc_html( $total_count ); ?>)</span>
						</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&severity=error' ) ); ?>"
							<?php echo 'error' === $severity ? 'class="current"' : ''; ?>>
							Errors <span class="count">(<?php echo esc_html( $error_count ); ?>)</span>
						</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&severity=warning' ) ); ?>"
							<?php echo 'warning' === $severity ? 'class="current"' : ''; ?>>
							Warnings <span class="count">(<?php echo esc_html( $warning_count ); ?>)</span>
						</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&severity=info' ) ); ?>"
							<?php echo 'info' === $severity ? 'class="current"' : ''; ?>>
							Info <span class="count">(<?php echo esc_html( $info_count ); ?>)</span>
						</a>
					</li>
				</ul>

				<div style="clear: both;"></div>

				<?php if ( ! empty( $logs ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;">
						<?php wp_nonce_field( 'notion_sync_resolve_all', 'notion_sync_nonce' ); ?>
						<input type="hidden" name="action" value="notion_sync_resolve_all">
						<button type="submit" class="button">Mark All as Resolved</button>
					</form>

					<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
						<thead>
							<tr>
								<th style="width: 100px;">Severity</th>
								<th style="width: 100px;">Category</th>
								<th>Message</th>
								<th style="width: 150px;">Date</th>
								<th style="width: 100px;">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $log ) : ?>
								<tr>
									<td>
										<?php
										$severity_class = 'notice-' . $log['severity'];
										$severity_label = ucfirst( $log['severity'] );
										?>
										<span class="notice inline <?php echo esc_attr( $severity_class ); ?>" style="padding: 2px 8px; margin: 0;">
											<?php echo esc_html( $severity_label ); ?>
										</span>
									</td>
									<td><?php echo esc_html( ucfirst( $log['category'] ) ); ?></td>
									<td>
										<strong><?php echo esc_html( $log['message'] ); ?></strong>

										<?php if ( ! empty( $log['context'] ) ) : ?>
											<details style="margin-top: 8px;">
												<summary style="cursor: pointer; color: #2271b1;">View Details</summary>
												<div style="margin-top: 8px; padding: 12px; background: #f6f7f7; border-left: 3px solid #2271b1;">
													<table class="widefat">
														<tbody>
															<?php if ( ! empty( $log['context']['url'] ) ) : ?>
																<tr>
																	<th style="width: 120px; text-align: left;">URL</th>
																	<td>
																		<a href="<?php echo esc_url( $log['context']['url'] ); ?>" target="_blank">
																			<?php echo esc_html( $log['context']['url'] ); ?>
																		</a>
																	</td>
																</tr>
															<?php endif; ?>
															<?php if ( ! empty( $log['context']['mime_type'] ) ) : ?>
																<tr>
																	<th style="text-align: left;">MIME Type</th>
																	<td><code><?php echo esc_html( $log['context']['mime_type'] ); ?></code></td>
																</tr>
															<?php endif; ?>
															<?php if ( ! empty( $log['context']['filename'] ) ) : ?>
																<tr>
																	<th style="text-align: left;">Filename</th>
																	<td><code><?php echo esc_html( $log['context']['filename'] ); ?></code></td>
																</tr>
															<?php endif; ?>
														</tbody>
													</table>

													<?php if ( $log['wp_post_id'] ) : ?>
														<p style="margin-top: 12px; margin-bottom: 0;">
															<a href="<?php echo esc_url( get_edit_post_link( $log['wp_post_id'] ) ); ?>"
																class="button button-small">
																Edit Post #<?php echo esc_html( $log['wp_post_id'] ); ?>
															</a>
														</p>
													<?php endif; ?>
												</div>
											</details>
										<?php endif; ?>
									</td>
									<td>
										<?php
										echo esc_html( human_time_diff( strtotime( $log['created_at'] ), current_time( 'timestamp' ) ) );
										?>
										ago
									</td>
									<td>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
											<?php wp_nonce_field( 'notion_sync_resolve_log', 'notion_sync_nonce' ); ?>
											<input type="hidden" name="action" value="notion_sync_resolve_log">
											<input type="hidden" name="log_id" value="<?php echo esc_attr( $log['id'] ); ?>">
											<button type="submit" class="button button-small">Resolve</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<div class="notice notice-info" style="margin-top: 20px;">
						<p><strong>No sync issues found!</strong> All your syncs are running smoothly.</p>
					</div>
				<?php endif; ?>
			</div>

			<style>
				.notion-sync-logs .notice.inline {
					display: inline-block;
					margin: 0;
					padding: 2px 8px;
				}
				.notice-error {
					background-color: #f8d7da;
					color: #721c24;
				}
				.notice-warning {
					background-color: #fff3cd;
					color: #856404;
				}
				.notice-info {
					background-color: #d1ecf1;
					color: #0c5460;
				}
			</style>
		</div>
		<?php
	}

	/**
	 * Handle resolve log action.
	 *
	 * @return void
	 */
	public function handle_resolve_log(): void {
		// Verify nonce and capabilities.
		if ( ! isset( $_POST['notion_sync_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['notion_sync_nonce'] ) ), 'notion_sync_resolve_log' ) ||
			! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;

		if ( $log_id ) {
			SyncLogger::resolve_log( $log_id );
		}

		// Redirect back.
		wp_safe_redirect(
			add_query_arg(
				[ 'resolved' => '1' ],
				wp_get_referer()
			)
		);
		exit;
	}

	/**
	 * Handle resolve all action.
	 *
	 * @return void
	 */
	public function handle_resolve_all(): void {
		// Verify nonce and capabilities.
		if ( ! isset( $_POST['notion_sync_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['notion_sync_nonce'] ) ), 'notion_sync_resolve_all' ) ||
			! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'notion_sync_logs';

		// Resolve all unresolved logs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"UPDATE {$table_name} SET resolved = 1, resolved_at = %s, resolved_by = %d WHERE resolved = 0",
				current_time( 'mysql' ),
				get_current_user_id()
			)
		);

		// Redirect back.
		wp_safe_redirect(
			add_query_arg(
				[ 'resolved_all' => '1' ],
				remove_query_arg( [ 'severity', 'category' ], wp_get_referer() )
			)
		);
		exit;
	}
}
