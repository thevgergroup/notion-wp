<?php
/**
 * Settings Page - Admin interface for Vger Sync for Notion configuration.
 *
 * @package NotionSync
 * @since 0.1.0
 */

namespace NotionSync\Admin;

use NotionSync\API\NotionClient;
use NotionSync\Security\Encryption;
use NotionSync\Sync\SyncManager;

/**
 * Class SettingsPage
 *
 * Handles the admin settings page for Vger Sync for Notion plugin.
 * Provides UI for connecting to Notion API and viewing workspace information.
 */
class SettingsPage {

	/**
	 * Register admin menu and form handlers.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_notion_sync_connect', array( $this, 'handle_connect' ) );
		add_action( 'admin_post_notion_sync_disconnect', array( $this, 'handle_disconnect' ) );
		add_action( 'admin_post_notion_sync_flush_rewrites', array( $this, 'handle_flush_rewrites' ) );
		add_action( 'admin_post_notion_sync_save_navigation_settings', array( $this, 'handle_save_navigation_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Register AJAX handlers via dedicated handler classes.
		$sync_ajax_handler = new SyncAjaxHandler();
		$sync_ajax_handler->register();

		$database_ajax_handler = new DatabaseAjaxHandler();
		$database_ajax_handler->register();

		// Register navigation AJAX handler (Phase 5).
		$navigation_ajax_handler = new \NotionWP\Admin\NavigationAjaxHandler();
		$navigation_ajax_handler->register();
	}

	/**
	 * Add admin menu page.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_menu_page(
			__( 'Vger Sync for Notion', 'vger-sync-for-notion' ),
			__( 'Vger Sync for Notion', 'vger-sync-for-notion' ),
			'manage_options',
			'vger-sync-for-notion',
			array( $this, 'render' ),
			'dashicons-cloud',
			30
		);
	}

	/**
	 * Enqueue admin styles and scripts.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// Only load on our settings page.
		if ( 'toplevel_page_vger-sync-for-notion' !== $hook ) {
			return;
		}

		// Enqueue custom admin CSS.
		wp_enqueue_style(
			'vger-sync-admin',
			VGER_SYNC_URL . 'assets/src/css/admin.css',
			array(),
			VGER_SYNC_VERSION,
			'all'
		);

		// Enqueue custom admin JavaScript (ES6 module).
		wp_enqueue_script(
			'vger-sync-admin',
			VGER_SYNC_URL . 'assets/src/js/admin.js',
			array(),
			VGER_SYNC_VERSION,
			true
		);

		// Enqueue Preact sync dashboard.
		wp_enqueue_script(
			'vger-sync-dashboard',
			VGER_SYNC_URL . 'assets/build/sync-dashboard.js',
			array(),
			VGER_SYNC_VERSION,
			true
		);

		// Add type="module" attribute for ES6 imports.
		add_filter(
			'script_loader_tag',
			function ( $tag, $handle ) {
				if ( 'vger-sync-admin' === $handle ) {
					return str_replace( '<script ', '<script type="module" ', $tag );
				}
				return $tag;
			},
			10,
			2
		);

		// Pass data to JavaScript.
		wp_localize_script(
			'vger-sync-admin',
			'notionSyncAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => rest_url( 'notion-sync/v1/sync-status' ),
				'nonce'   => wp_create_nonce( 'notion_sync_ajax' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'connecting'           => __( 'Connecting...', 'vger-sync-for-notion' ),
					'connected'            => __( 'Connected!', 'vger-sync-for-notion' ),
					'disconnecting'        => __( 'Disconnecting...', 'vger-sync-for-notion' ),
					'error'                => __( 'An error occurred. Please try again.', 'vger-sync-for-notion' ),
					'syncing'              => __( 'Syncing...', 'vger-sync-for-notion' ),
					'synced'               => __( 'Synced', 'vger-sync-for-notion' ),
					'syncError'            => __( 'Sync failed', 'vger-sync-for-notion' ),
					'confirmBulkSync'      => __( 'Are you sure you want to sync the selected pages?', 'vger-sync-for-notion' ),
					'selectPages'          => __( 'Please select at least one page to sync.', 'vger-sync-for-notion' ),
					'copied'               => __( 'Copied!', 'vger-sync-for-notion' ),
					'confirmDatabaseSync'  => __( 'Are you sure you want to sync this database? This will import all entries.', 'vger-sync-for-notion' ),
					'databaseSyncStarted'  => __( 'Database sync started. Please wait...', 'vger-sync-for-notion' ),
					'databaseSyncComplete' => __( 'Database sync complete!', 'vger-sync-for-notion' ),
					'cancelBatch'          => __( 'Are you sure you want to cancel this sync?', 'vger-sync-for-notion' ),
				),
			)
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to access this page.', 'vger-sync-for-notion' ),
				esc_html__( 'Insufficient Permissions', 'vger-sync-for-notion' ),
				array( 'response' => 403 )
			);
		}

		// Require HTTPS for security (except in development environments).
		$http_host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$is_local  = in_array( $http_host, array( 'localhost', '127.0.0.1' ), true ) ||
					false !== strpos( $http_host, '.localtest.me' );

		if ( ! is_ssl() && ! defined( 'WP_DEBUG' ) && ! $is_local ) {
			wp_die(
				sprintf(
					/* translators: SSL/TLS configuration message */
					esc_html__( 'HTTPS is required to configure Vger Sync for Notion. Please enable SSL/TLS or add %s to wp-config.php.', 'vger-sync-for-notion' ),
					'<code>define( \'FORCE_SSL_ADMIN\', true );</code>'
				),
				esc_html__( 'HTTPS Required', 'vger-sync-for-notion' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		// Get connection status.
		$encrypted_token = get_option( 'notion_wp_token', '' );
		$token           = ! empty( $encrypted_token ) ? Encryption::decrypt( $encrypted_token ) : '';
		$is_connected    = ! empty( $token );
		$workspace_info  = array();
		$list_table      = null;
		$databases_table = null;
		$error_message   = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab parameter for display only, no state change.
		$current_tab     = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'pages';

		// If connected, fetch workspace info.
		if ( $is_connected ) {
			$workspace_info = $this->get_cached_workspace_info( $token );

			// Check if we have cached data.
			if ( empty( $workspace_info ) ) {
				// Try to fetch fresh data.
				try {
					$client         = new NotionClient( $token );
					$workspace_info = $client->get_workspace_info();

					if ( ! empty( $workspace_info ) && ! isset( $workspace_info['error'] ) ) {
						// Cache for 1 hour.
						set_transient( 'notion_wp_workspace_info_cache', $workspace_info, HOUR_IN_SECONDS );
						update_option( 'notion_wp_workspace_info', $workspace_info );
					} else {
						$error_message = $workspace_info['error'] ?? __( 'Unable to fetch workspace information.', 'vger-sync-for-notion' );
					}
				} catch ( \Exception $e ) {
					$error_message = $e->getMessage();
				}
			}

			// Initialize appropriate list table based on current tab.
			try {
				$client = new NotionClient( $token );

				if ( 'databases' === $current_tab ) {
					// Initialize databases list table.
					$db_fetcher      = new \NotionSync\Sync\DatabaseFetcher( $client );
					$databases_table = new \NotionSync\Admin\DatabasesListTable( $db_fetcher );
					$databases_table->prepare_items();
				} elseif ( 'pages' === $current_tab ) {
					// Initialize pages list table.
					$fetcher = new \NotionSync\Sync\ContentFetcher( $client );
					$manager = new SyncManager();

					$list_table = new PagesListTable( $fetcher, $manager );

					// Process bulk actions BEFORE preparing items.
					$list_table->process_bulk_action();

					$list_table->prepare_items();
				}
				// Settings tab doesn't need any list table initialization.
			} catch ( \Exception $e ) {
				$error_message = $e->getMessage();
			}
		}

		// Load template.
		require_once VGER_SYNC_PATH . 'templates/admin/settings.php';
	}

	/**
	 * Handle connect form submission.
	 *
	 * @return void
	 */
	public function handle_connect() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to perform this action.', 'vger-sync-for-notion' ),
				esc_html__( 'Insufficient Permissions', 'vger-sync-for-notion' ),
				array( 'response' => 403 )
			);
		}

		// Verify nonce.
		if ( ! isset( $_POST['notion_sync_connect_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['notion_sync_connect_nonce'] ) ), 'notion_sync_connect' ) ) {
			wp_die(
				esc_html__( 'Security check failed. Please try again.', 'vger-sync-for-notion' ),
				esc_html__( 'Security Error', 'vger-sync-for-notion' ),
				array( 'response' => 403 )
			);
		}

		// Get and sanitize token.
		$token = isset( $_POST['notion_token'] ) ? sanitize_text_field( wp_unslash( $_POST['notion_token'] ) ) : '';

		// Validate token format.
		if ( empty( $token ) ) {
			$this->redirect_with_message( 'error', __( 'Please enter a Notion API token.', 'vger-sync-for-notion' ) );
			return;
		}

		// Validate token format (Notion tokens start with "secret_" or "ntn_").
		if ( strpos( $token, 'secret_' ) !== 0 && strpos( $token, 'ntn_' ) !== 0 ) {
			$this->redirect_with_message(
				'error',
				__( 'Invalid token format. Notion API tokens should start with "secret_" or "ntn_".', 'vger-sync-for-notion' )
			);
			return;
		}

		// Check rate limiting.
		if ( $this->is_rate_limited() ) {
			$this->redirect_with_message( 'error', __( 'Too many connection attempts. Please wait 5 minutes and try again.', 'vger-sync-for-notion' ) );
			return;
		}

		// Test connection.
		try {
			$client = new NotionClient( $token );

			if ( ! $client->test_connection() ) {
				$this->redirect_with_message( 'error', __( 'Connection failed. Please check your token and try again.', 'vger-sync-for-notion' ) );
				return;
			}

			// Get workspace info.
			$workspace_info = $client->get_workspace_info();

			if ( isset( $workspace_info['error'] ) ) {
				$this->redirect_with_message( 'error', $workspace_info['error'] );
				return;
			}

			// Save token (encrypted) and workspace info.
			update_option( 'notion_wp_token', Encryption::encrypt( $token ) );
			update_option( 'notion_wp_workspace_info', $workspace_info );

			// Cache workspace info.
			set_transient( 'notion_wp_workspace_info_cache', $workspace_info, HOUR_IN_SECONDS );

			// Clear rate limiting on successful connection.
			$this->clear_rate_limit();

			// Success!
			$workspace_name = $workspace_info['workspace_name'] ?? __( 'Unknown Workspace', 'vger-sync-for-notion' );
			$this->redirect_with_message(
				'success',
				sprintf(
					/* translators: %s: workspace name */
					__( 'Successfully connected to Notion workspace: %s', 'vger-sync-for-notion' ),
					$workspace_name
				)
			);
		} catch ( \Exception $e ) {
			$this->redirect_with_message( 'error', $e->getMessage() );
		}
	}

	/**
	 * Handle disconnect form submission.
	 *
	 * @return void
	 */
	public function handle_disconnect() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to perform this action.', 'vger-sync-for-notion' ),
				esc_html__( 'Insufficient Permissions', 'vger-sync-for-notion' ),
				array( 'response' => 403 )
			);
		}

		// Verify nonce.
		if ( ! isset( $_POST['notion_sync_disconnect_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['notion_sync_disconnect_nonce'] ) ), 'notion_sync_disconnect' ) ) {
			wp_die(
				esc_html__( 'Security check failed. Please try again.', 'vger-sync-for-notion' ),
				esc_html__( 'Security Error', 'vger-sync-for-notion' ),
				array( 'response' => 403 )
			);
		}

		// Delete token and workspace info.
		delete_option( 'notion_wp_token' );
		delete_option( 'notion_wp_workspace_info' );
		delete_transient( 'notion_wp_workspace_info_cache' );

		// Redirect with success message.
		$this->redirect_with_message( 'success', __( 'Successfully disconnected from Notion.', 'vger-sync-for-notion' ) );
	}

	/**
	 * Get cached workspace info or fetch fresh.
	 *
	 * @param string $token Notion API token.
	 * @return array Workspace information.
	 */
	private function get_cached_workspace_info( $token ) {
		// Try to get from transient cache first.
		$cached = get_transient( 'notion_wp_workspace_info_cache' );
		if ( false !== $cached && ! empty( $cached ) ) {
			return $cached;
		}

		// Fallback to option.
		return get_option( 'notion_wp_workspace_info', array() );
	}

	/**
	 * Redirect back to settings page with a message.
	 *
	 * @param string      $type    Message type: 'success' or 'error'.
	 * @param string      $message Message to display.
	 * @param string|null $tab     Optional tab to redirect to. Defaults to current tab or 'pages'.
	 * @return void
	 */
	private function redirect_with_message( $type, $message, $tab = null ) {
		$args = array(
			'page'                 => 'vger-sync-for-notion',
			'notion_sync_' . $type => rawurlencode( $message ),
		);

		// Add tab parameter if specified.
		if ( null !== $tab ) {
			$args['tab'] = $tab;
		}

		$redirect_url = add_query_arg( $args, admin_url( 'admin.php' ) );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Check if current user is rate limited.
	 *
	 * Prevents brute force attacks by limiting connection attempts
	 * to 5 per 5 minutes per user.
	 *
	 * @return bool True if rate limited, false otherwise.
	 */
	private function is_rate_limited() {
		$user_id       = get_current_user_id();
		$transient_key = 'notion_sync_attempts_' . $user_id;
		$attempts      = get_transient( $transient_key );

		if ( false === $attempts ) {
			set_transient( $transient_key, 1, 5 * MINUTE_IN_SECONDS );
			return false;
		}

		if ( $attempts >= 5 ) {
			return true;
		}

		set_transient( $transient_key, $attempts + 1, 5 * MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Clear rate limit for current user.
	 *
	 * Called after successful connection to reset attempt counter.
	 *
	 * @return void
	 */
	private function clear_rate_limit() {
		delete_transient( 'notion_sync_attempts_' . get_current_user_id() );
	}

	/**
	 * Handle flush rewrite rules request.
	 *
	 * Flushes WordPress rewrite rules to ensure /notion/{slug} URLs work correctly.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_flush_rewrites() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to perform this action.', 'vger-sync-for-notion' ),
				esc_html__( 'Insufficient Permissions', 'vger-sync-for-notion' ),
				array( 'response' => 403 )
			);
		}

		// Verify nonce.
		if ( ! isset( $_POST['notion_sync_flush_rewrites_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['notion_sync_flush_rewrites_nonce'] ) ), 'notion_sync_flush_rewrites' ) ) {
			wp_die(
				esc_html__( 'Security check failed. Please try again.', 'vger-sync-for-notion' ),
				esc_html__( 'Security Error', 'vger-sync-for-notion' ),
				array( 'response' => 403 )
			);
		}

		// Register rewrite rules before flushing.
		$link_registry = new \NotionSync\Router\LinkRegistry();
		$notion_router = new \NotionSync\Router\NotionRouter( $link_registry );
		$notion_router->register_rewrite_rules();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Redirect with success message.
		$this->redirect_with_message(
			'success',
			__( 'Rewrite rules have been flushed. /notion/{slug} URLs should now work correctly.', 'vger-sync-for-notion' )
		);
	}

	/**
	 * Handle navigation settings form submission.
	 *
	 * Saves menu sync configuration settings including whether menu sync is enabled
	 * and the name of the WordPress menu to create.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_save_navigation_settings() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to perform this action.', 'vger-sync-for-notion' ),
				esc_html__( 'Insufficient Permissions', 'vger-sync-for-notion' ),
				array( 'response' => 403 )
			);
		}

		// Verify nonce.
		$nonce_value = isset( $_POST['notion_sync_navigation_settings_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['notion_sync_navigation_settings_nonce'] ) )
			: '';
		if ( ! wp_verify_nonce( $nonce_value, 'notion_sync_navigation_settings' ) ) {
			wp_die(
				esc_html__( 'Security check failed. Please try again.', 'vger-sync-for-notion' ),
				esc_html__( 'Security Error', 'vger-sync-for-notion' ),
				array( 'response' => 403 )
			);
		}

		// Get and sanitize settings.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified on line 518.
		$menu_enabled = isset( $_POST['notion_sync_menu_enabled'] ) && '1' === $_POST['notion_sync_menu_enabled'];
		$menu_name    = isset( $_POST['notion_sync_menu_name'] ) ?
			sanitize_text_field( wp_unslash( $_POST['notion_sync_menu_name'] ) ) :
			'Notion Navigation';

		// Validate menu name is not empty.
		if ( empty( trim( $menu_name ) ) ) {
			$this->redirect_with_message(
				'error',
				__( 'Menu name cannot be empty. Please provide a valid menu name.', 'vger-sync-for-notion' ),
				'navigation'
			);
			return;
		}

		// Save settings.
		update_option( 'notion_sync_menu_enabled', $menu_enabled );
		update_option( 'notion_sync_menu_name', $menu_name );

		// Redirect with success message.
		$this->redirect_with_message(
			'success',
			__( 'Navigation settings saved successfully.', 'vger-sync-for-notion' ),
			'navigation'
		);
	}
}
