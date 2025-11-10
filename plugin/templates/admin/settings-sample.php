<?php
/**
 * Sample HTML Structure for Notion Sync Settings Page
 *
 * This file demonstrates the complete HTML structure for all UI states:
 * 1. Disconnected state (connection form)
 * 2. Connected state (workspace info + pages list)
 * 3. Loading state
 * 4. Admin notices
 *
 * Use this as a reference when implementing the actual settings page template.
 *
 * @package NotionSync
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap notion-sync-settings">

	<!-- Page Header -->
	<div class="notion-sync-header">
		<h1><?php esc_html_e( 'Notion Sync Settings', 'vger-sync-for-notion' ); ?></h1>
		<p class="subtitle">
			<?php esc_html_e( 'Connect your WordPress site to Notion for seamless content synchronization.', 'vger-sync-for-notion' ); ?>
		</p>
	</div>

	<!-- Admin Notices (Success Example) -->
	<div class="notice notice-success is-dismissible notion-sync-notice">
		<p>
			<strong><?php esc_html_e( 'Connected successfully!', 'vger-sync-for-notion' ); ?></strong>
			<?php esc_html_e( 'Your Notion workspace is now connected.', 'vger-sync-for-notion' ); ?>
		</p>
		<button type="button" class="notice-dismiss">
			<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice', 'vger-sync-for-notion' ); ?></span>
		</button>
	</div>

	<!-- Admin Notices (Error Example) -->
	<div class="notice notice-error is-dismissible notion-sync-notice">
		<p>
			<strong><?php esc_html_e( 'Connection failed', 'vger-sync-for-notion' ); ?></strong>
			<?php esc_html_e( 'Invalid token. Please check your Notion integration token and try again.', 'vger-sync-for-notion' ); ?>
		</p>
		<p>
			<?php
			printf(
				/* translators: %s: URL to Notion integrations page */
				esc_html__( 'Get your token from %s', 'vger-sync-for-notion' ),
				'<a href="https://www.notion.com/my-integrations" target="_blank" rel="noopener noreferrer">notion.com/my-integrations</a>'
			);
			?>
		</p>
	</div>

	<?php
	// Determine which state to show.
	$is_connected = false; // This would come from your settings/options.
	$is_loading   = false;

	if ( $is_loading ) :
		?>
		<!-- Loading State -->
		<div class="notion-sync-loading">
			<span class="spinner is-active"></span>
			<span class="loading-text"><?php esc_html_e( 'Connecting to Notion...', 'vger-sync-for-notion' ); ?></span>
		</div>
		<?php
	elseif ( ! $is_connected ) :
		?>
		<!-- STATE 1: Disconnected - Show Connection Form -->
		<div class="notion-sync-connection-form">
			<div class="form-intro">
				<h2><?php esc_html_e( 'Connect to Notion', 'vger-sync-for-notion' ); ?></h2>
				<p>
					<?php esc_html_e( 'To connect your Notion workspace, you\'ll need an Internal Integration Token.', 'vger-sync-for-notion' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'How to get your token:', 'vger-sync-for-notion' ); ?></strong>
				</p>
				<ol>
					<li>
						<?php
						printf(
							/* translators: %s: URL to Notion integrations page */
							esc_html__( 'Go to %s', 'vger-sync-for-notion' ),
							'<a href="https://www.notion.com/my-integrations" target="_blank" rel="noopener noreferrer">notion.com/my-integrations</a>'
						);
						?>
					</li>
					<li><?php esc_html_e( 'Click "New integration"', 'vger-sync-for-notion' ); ?></li>
					<li><?php esc_html_e( 'Give it a name (e.g., "WordPress Sync")', 'vger-sync-for-notion' ); ?></li>
					<li><?php esc_html_e( 'Copy the "Internal Integration Token"', 'vger-sync-for-notion' ); ?></li>
					<li><?php esc_html_e( 'Share your Notion pages with the integration', 'vger-sync-for-notion' ); ?></li>
				</ol>
			</div>

			<form id="notion-sync-connection-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'notion_sync_connect', 'notion_sync_nonce' ); ?>
				<input type="hidden" name="action" value="notion_sync_connect">

				<div class="form-field">
					<label for="notion_token">
						<?php esc_html_e( 'Notion Integration Token', 'vger-sync-for-notion' ); ?>
						<span class="required" aria-label="required">*</span>
					</label>
					<input
						type="password"
						id="notion_token"
						name="notion_token"
						class="token-input"
						placeholder="secret_..."
						required
						autocomplete="off"
						spellcheck="false"
						aria-required="true"
						aria-describedby="token-description"
					>
					<span id="token-description" class="description">
						<?php esc_html_e( 'Your token starts with "secret_" and should be kept confidential.', 'vger-sync-for-notion' ); ?>
					</span>
				</div>

				<div class="form-actions">
					<button type="submit" class="button button-primary" disabled>
						<?php esc_html_e( 'Connect to Notion', 'vger-sync-for-notion' ); ?>
					</button>
					<span class="description">
						<?php esc_html_e( 'This will verify your token and establish the connection.', 'vger-sync-for-notion' ); ?>
					</span>
				</div>
			</form>
		</div>
		<?php
	else :
		?>
		<!-- STATE 2: Connected - Show Workspace Info -->
		<div class="notion-sync-workspace-info">
			<span class="success-icon" aria-label="<?php esc_attr_e( 'Connected', 'vger-sync-for-notion' ); ?>"></span>
			<h2 class="workspace-name">
				<?php
				// In real implementation, this would come from the API response.
				echo esc_html( 'My Notion Workspace' );
				?>
			</h2>

			<div class="workspace-details">
				<p>
					<strong><?php esc_html_e( 'User:', 'vger-sync-for-notion' ); ?></strong>
					<?php echo esc_html( 'user@example.com' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Last connected:', 'vger-sync-for-notion' ); ?></strong>
					<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?>
				</p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'notion_sync_disconnect', 'notion_sync_nonce' ); ?>
				<input type="hidden" name="action" value="notion_sync_disconnect">
				<button
					type="submit"
					id="notion-sync-disconnect"
					class="button disconnect-button"
					aria-label="<?php esc_attr_e( 'Disconnect from Notion', 'vger-sync-for-notion' ); ?>"
				>
					<?php esc_html_e( 'Disconnect', 'vger-sync-for-notion' ); ?>
				</button>
			</form>
		</div>

		<!-- Pages List -->
		<div class="notion-sync-pages">
			<h2><?php esc_html_e( 'Available Pages', 'vger-sync-for-notion' ); ?></h2>
			<div class="pages-list">
				<?php
				// Sample pages - in real implementation, this would come from the API.
				$sample_pages = array(
					array(
						'icon'  => '📄',
						'title' => 'Getting Started Guide',
						'date'  => 'Edited 2 hours ago',
					),
					array(
						'icon'  => '📝',
						'title' => 'Blog Post: WordPress Integration',
						'date'  => 'Edited yesterday',
					),
					array(
						'icon'  => '📊',
						'title' => 'Product Roadmap 2024',
						'date'  => 'Edited 3 days ago',
					),
					array(
						'icon'  => '🚀',
						'title' => 'Launch Checklist',
						'date'  => 'Edited last week',
					),
					array(
						'icon'  => '📚',
						'title' => 'Documentation',
						'date'  => 'Edited 2 weeks ago',
					),
				);

				foreach ( $sample_pages as $notion_page ) :
					?>
					<div class="page-item">
						<span class="page-icon" aria-hidden="true">
							<?php echo esc_html( $notion_page['icon'] ); ?>
						</span>
						<div class="page-info">
							<div class="page-title">
								<?php echo esc_html( $notion_page['title'] ); ?>
							</div>
							<div class="page-meta">
								<?php echo esc_html( $notion_page['date'] ); ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	endif;
	?>

</div>

<?php
/*
 * WordPress Admin Classes Used (for consistency):
 * ================================================
 *
 * Layout & Structure:
 * - .wrap                         Main admin page wrapper
 *
 * Buttons:
 * - .button                       Standard WordPress button
 * - .button-primary              Primary action button (blue)
 * - .button-secondary            Secondary button (white/grey)
 *
 * Form Elements:
 * - input[type="text"]           Text inputs (inherit WP styles)
 * - input[type="password"]       Password inputs
 * - label                         Form labels
 *
 * Notices:
 * - .notice                       Base notice class
 * - .notice-success              Success notice (green)
 * - .notice-error                Error notice (red)
 * - .notice-warning              Warning notice (yellow)
 * - .notice-info                 Info notice (blue)
 * - .is-dismissible              Makes notice dismissible
 * - .notice-dismiss              Dismiss button
 *
 * Loading:
 * - .spinner                      WordPress loading spinner
 * - .is-active                   Makes spinner visible
 *
 * Accessibility:
 * - .screen-reader-text          Hide visually but keep for screen readers
 *
 * Typography:
 * - h1, h2                        Page headings (styled by WP)
 *
 * WordPress Color Variables (CSS Custom Properties):
 * ================================================
 * These are available in WordPress 5.9+:
 *
 * - --wp-admin-theme-color        Primary admin color (#2271b1)
 * - --wp-admin-theme-color-darker Darker shade (#135e96)
 * - --wp-admin-border-color       Border color (#c3c4c7)
 *
 * WordPress Default Colors (hex values):
 * =====================================
 *
 * Primary:
 * - #2271b1                       Primary blue (links, buttons)
 * - #135e96                       Darker blue (hover states)
 *
 * Success:
 * - #00a32a                       Success green
 * - #008a20                       Darker green (hover)
 *
 * Error:
 * - #d63638                       Error red
 * - #b32d2e                       Darker red (hover)
 *
 * Warning:
 * - #dba617                       Warning yellow
 *
 * Text:
 * - #1d2327                       Primary text color
 * - #50575e                       Secondary text color
 * - #646970                       Tertiary text color (meta)
 *
 * Borders:
 * - #c3c4c7                       Default borders
 * - #8c8f94                       Input borders
 *
 * Backgrounds:
 * - #ffffff                       White backgrounds
 * - #f0f0f1                       Light grey background
 * - #f6f7f7                       Hover background
 */