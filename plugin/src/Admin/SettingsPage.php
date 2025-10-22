<?php
/**
 * Settings Page - Admin interface for Notion Sync configuration.
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
 * Handles the admin settings page for Notion Sync plugin.
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Register AJAX handlers via dedicated handler classes.
		$sync_ajax_handler = new SyncAjaxHandler();
		$sync_ajax_handler->register();

		$database_ajax_handler = new DatabaseAjaxHandler();
		$database_ajax_handler->register();
	}

	/**
	 * Add admin menu page.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_menu_page(
			__( 'Notion Sync', 'notion-wp' ),
			__( 'Notion Sync', 'notion-wp' ),
			'manage_options',
			'notion-sync',
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
		if ( 'toplevel_page_notion-sync' !== $hook ) {
			return;
		}

		// Enqueue custom admin CSS.
		wp_enqueue_style(
			'notion-sync-admin',
			NOTION_SYNC_URL . 'assets/src/css/admin.css',
			array(),
			NOTION_SYNC_VERSION,
			'all'
		);

		// Enqueue custom admin JavaScript (ES6 module).
		wp_enqueue_script(
			'notion-sync-admin',
			NOTION_SYNC_URL . 'assets/src/js/admin.js',
			array(),
			NOTION_SYNC_VERSION,
			true
		);

		// Add type="module" attribute for ES6 imports.
		add_filter(
			'script_loader_tag',
			function ( $tag, $handle ) {
				if ( 'notion-sync-admin' === $handle ) {
					return str_replace( '<script ', '<script type="module" ', $tag );
				}
				return $tag;
			},
			10,
			2
		);

		// Pass data to JavaScript.
		wp_localize_script(
			'notion-sync-admin',
			'notionSyncAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'notion_sync_ajax' ),
				'i18n'    => array(
					'connecting'           => __( 'Connecting...', 'notion-wp' ),
					'connected'            => __( 'Connected!', 'notion-wp' ),
					'disconnecting'        => __( 'Disconnecting...', 'notion-wp' ),
					'error'                => __( 'An error occurred. Please try again.', 'notion-wp' ),
					'syncing'              => __( 'Syncing...', 'notion-wp' ),
					'synced'               => __( 'Synced', 'notion-wp' ),
					'syncError'            => __( 'Sync failed', 'notion-wp' ),
					'confirmBulkSync'      => __( 'Are you sure you want to sync the selected pages?', 'notion-wp' ),
					'selectPages'          => __( 'Please select at least one page to sync.', 'notion-wp' ),
					'copied'               => __( 'Copied!', 'notion-wp' ),
					'confirmDatabaseSync'  => __( 'Are you sure you want to sync this database? This will import all entries.', 'notion-wp' ),
					'databaseSyncStarted'  => __( 'Database sync started. Please wait...', 'notion-wp' ),
					'databaseSyncComplete' => __( 'Database sync complete!', 'notion-wp' ),
					'cancelBatch'          => __( 'Are you sure you want to cancel this sync?', 'notion-wp' ),
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
				esc_html__( 'You do not have sufficient permissions to access this page.', 'notion-wp' ),
				esc_html__( 'Insufficient Permissions', 'notion-wp' ),
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
					esc_html__( 'HTTPS is required to configure Notion Sync. Please enable SSL/TLS or add %s to wp-config.php.', 'notion-wp' ),
					'<code>define( \'FORCE_SSL_ADMIN\', true );</code>'
				),
				esc_html__( 'HTTPS Required', 'notion-wp' ),
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
						$error_message = $workspace_info['error'] ?? __( 'Unable to fetch workspace information.', 'notion-wp' );
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
				} else {
					// Initialize pages list table.
					$fetcher = new \NotionSync\Sync\ContentFetcher( $client );
					$manager = new SyncManager();

					$list_table = new PagesListTable( $fetcher, $manager );

					// Process bulk actions BEFORE preparing items.
					$list_table->process_bulk_action();

					$list_table->prepare_items();
				}
			} catch ( \Exception $e ) {
				$error_message = $e->getMessage();
			}
		}

		// Load template.
		require_once NOTION_SYNC_PATH . 'templates/admin/settings.php';
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
				esc_html__( 'You do not have sufficient permissions to perform this action.', 'notion-wp' ),
				esc_html__( 'Insufficient Permissions', 'notion-wp' ),
				array( 'response' => 403 )
			);
		}

		// Verify nonce.
		if ( ! isset( $_POST['notion_sync_connect_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['notion_sync_connect_nonce'] ) ), 'notion_sync_connect' ) ) {
			wp_die(
				esc_html__( 'Security check failed. Please try again.', 'notion-wp' ),
				esc_html__( 'Security Error', 'notion-wp' ),
				array( 'response' => 403 )
			);
		}

		// Get and sanitize token.
		$token = isset( $_POST['notion_token'] ) ? sanitize_text_field( wp_unslash( $_POST['notion_token'] ) ) : '';

		// Validate token format.
		if ( empty( $token ) ) {
			$this->redirect_with_message( 'error', __( 'Please enter a Notion API token.', 'notion-wp' ) );
			return;
		}

		// Validate token format (Notion tokens start with "secret_" or "ntn_").
		if ( strpos( $token, 'secret_' ) !== 0 && strpos( $token, 'ntn_' ) !== 0 ) {
			$this->redirect_with_message(
				'error',
				__( 'Invalid token format. Notion API tokens should start with "secret_" or "ntn_".', 'notion-wp' )
			);
			return;
		}

		// Check rate limiting.
		if ( $this->is_rate_limited() ) {
			$this->redirect_with_message( 'error', __( 'Too many connection attempts. Please wait 5 minutes and try again.', 'notion-wp' ) );
			return;
		}

		// Test connection.
		try {
			$client = new NotionClient( $token );

			if ( ! $client->test_connection() ) {
				$this->redirect_with_message( 'error', __( 'Connection failed. Please check your token and try again.', 'notion-wp' ) );
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
			$workspace_name = $workspace_info['workspace_name'] ?? __( 'Unknown Workspace', 'notion-wp' );
			$this->redirect_with_message(
				'success',
				sprintf(
					/* translators: %s: workspace name */
					__( 'Successfully connected to Notion workspace: %s', 'notion-wp' ),
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
				esc_html__( 'You do not have sufficient permissions to perform this action.', 'notion-wp' ),
				esc_html__( 'Insufficient Permissions', 'notion-wp' ),
				array( 'response' => 403 )
			);
		}

		// Verify nonce.
		if ( ! isset( $_POST['notion_sync_disconnect_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['notion_sync_disconnect_nonce'] ) ), 'notion_sync_disconnect' ) ) {
			wp_die(
				esc_html__( 'Security check failed. Please try again.', 'notion-wp' ),
				esc_html__( 'Security Error', 'notion-wp' ),
				array( 'response' => 403 )
			);
		}

		// Delete token and workspace info.
		delete_option( 'notion_wp_token' );
		delete_option( 'notion_wp_workspace_info' );
		delete_transient( 'notion_wp_workspace_info_cache' );

		// Redirect with success message.
		$this->redirect_with_message( 'success', __( 'Successfully disconnected from Notion.', 'notion-wp' ) );
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
	 * @param string $type    Message type: 'success' or 'error'.
	 * @param string $message Message to display.
	 * @return void
	 */
	private function redirect_with_message( $type, $message ) {
		$redirect_url = add_query_arg(
			array(
				'page'               => 'notion-sync',
				'notion_sync_' . $type => rawurlencode( $message ),
			),
			admin_url( 'admin.php' )
		);

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
}
