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
		add_action( 'wp_ajax_notion_sync_page', array( $this, 'handle_sync_page_ajax' ) );
		add_action( 'wp_ajax_notion_bulk_sync', array( $this, 'handle_bulk_sync_ajax' ) );
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
			NOTION_SYNC_URL . 'assets/dist/css/admin.min.css',
			array(),
			NOTION_SYNC_VERSION,
			'all'
		);

		// Enqueue custom admin JavaScript.
		wp_enqueue_script(
			'notion-sync-admin',
			NOTION_SYNC_URL . 'assets/dist/js/admin.min.js',
			array(),
			NOTION_SYNC_VERSION,
			true
		);

		// Pass data to JavaScript.
		wp_localize_script(
			'notion-sync-admin',
			'notionSyncAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'notion_sync_ajax' ),
				'i18n'    => array(
					'connecting'      => __( 'Connecting...', 'notion-wp' ),
					'connected'       => __( 'Connected!', 'notion-wp' ),
					'disconnecting'   => __( 'Disconnecting...', 'notion-wp' ),
					'error'           => __( 'An error occurred. Please try again.', 'notion-wp' ),
					'syncing'         => __( 'Syncing...', 'notion-wp' ),
					'synced'          => __( 'Synced', 'notion-wp' ),
					'syncError'       => __( 'Sync failed', 'notion-wp' ),
					'confirmBulkSync' => __( 'Are you sure you want to sync the selected pages?', 'notion-wp' ),
					'selectPages'     => __( 'Please select at least one page to sync.', 'notion-wp' ),
					'copied'          => __( 'Copied!', 'notion-wp' ),
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
		$is_local = in_array( $_SERVER['HTTP_HOST'] ?? '', array( 'localhost', '127.0.0.1' ), true ) ||
					strpos( $_SERVER['HTTP_HOST'] ?? '', '.localtest.me' ) !== false;

		if ( ! is_ssl() && ! defined( 'WP_DEBUG' ) && ! $is_local ) {
			wp_die(
				esc_html__( 'HTTPS is required to configure Notion Sync. Please enable SSL/TLS or add "define( \'FORCE_SSL_ADMIN\', true );" to wp-config.php.', 'notion-wp' ),
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
		$error_message   = '';

		// If connected, fetch workspace info and initialize list table.
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

			// Initialize pages list table.
			try {
				$client  = new NotionClient( $token );
				$fetcher = new \NotionSync\Sync\ContentFetcher( $client );
				$manager = new SyncManager();

				$list_table = new PagesListTable( $fetcher, $manager );

				// Process bulk actions BEFORE preparing items.
				$list_table->process_bulk_action();

				$list_table->prepare_items();
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
			$this->redirect_with_message( 'error', __( 'Invalid token format. Notion API tokens should start with "secret_" or "ntn_".', 'notion-wp' ) );
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

	/**
	 * Handle AJAX request to sync a single Notion page.
	 *
	 * Syncs a Notion page to WordPress and returns the result as JSON.
	 * Used by the "Sync Now" button in the pages list table.
	 *
	 * @since 1.0.0
	 *
	 * @return void Outputs JSON response and exits.
	 */
	public function handle_sync_page_ajax() {
		// Verify nonce.
		check_ajax_referer( 'notion_sync_ajax', 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions to sync pages.', 'notion-wp' ),
				),
				403
			);
		}

		// Get and validate page ID.
		$page_id = isset( $_POST['page_id'] ) ? sanitize_text_field( wp_unslash( $_POST['page_id'] ) ) : '';

		if ( empty( $page_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Page ID is required.', 'notion-wp' ),
				),
				400
			);
		}

		// Attempt to sync the page.
		try {
			$manager = new SyncManager();
			$result  = $manager->sync_page( $page_id );

			if ( $result['success'] ) {
				// Get updated sync status for response.
				$sync_status = $manager->get_sync_status( $page_id );

				wp_send_json_success(
					array(
						'message'     => __( 'Page synced successfully!', 'notion-wp' ),
						'post_id'     => $result['post_id'],
						'edit_url'    => get_edit_post_link( $result['post_id'] ),
						'view_url'    => get_permalink( $result['post_id'] ),
						'last_synced' => $sync_status['last_synced'],
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message' => $result['error'] ?? __( 'An unknown error occurred while syncing.', 'notion-wp' ),
					),
					500
				);
			}
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Sync failed: %s', 'notion-wp' ),
						$e->getMessage()
					),
				),
				500
			);
		}
	}

	/**
	 * Handle AJAX request to bulk sync multiple Notion pages.
	 *
	 * Syncs multiple Notion pages to WordPress and returns aggregated results.
	 * Used by the "Sync Selected" bulk action in the pages list table.
	 *
	 * @since 1.0.0
	 *
	 * @return void Outputs JSON response and exits.
	 */
	public function handle_bulk_sync_ajax() {
		// Verify nonce.
		check_ajax_referer( 'notion_sync_ajax', 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions to sync pages.', 'notion-wp' ),
				),
				403
			);
		}

		// Get and validate page IDs.
		$page_ids = isset( $_POST['page_ids'] ) ? (array) $_POST['page_ids'] : array();
		$page_ids = array_map( 'sanitize_text_field', $page_ids );

		if ( empty( $page_ids ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No pages selected for sync.', 'notion-wp' ),
				),
				400
			);
		}

		// Sync each page and track results.
		$manager       = new SyncManager();
		$success_count = 0;
		$error_count   = 0;
		$results       = array();

		foreach ( $page_ids as $page_id ) {
			try {
				$result = $manager->sync_page( $page_id );

				if ( $result['success'] ) {
					$success_count++;
					$results[ $page_id ] = array(
						'success' => true,
						'post_id' => $result['post_id'],
					);
				} else {
					$error_count++;
					$results[ $page_id ] = array(
						'success' => false,
						'error'   => $result['error'],
					);
				}
			} catch ( \Exception $e ) {
				$error_count++;
				$results[ $page_id ] = array(
					'success' => false,
					'error'   => $e->getMessage(),
				);
			}
		}

		// Build response message.
		if ( $success_count > 0 && $error_count === 0 ) {
			$message = sprintf(
				/* translators: %d: number of successfully synced pages */
				_n(
					'Successfully synced %d page.',
					'Successfully synced %d pages.',
					$success_count,
					'notion-wp'
				),
				$success_count
			);
		} elseif ( $success_count > 0 && $error_count > 0 ) {
			$message = sprintf(
				/* translators: 1: number of successful syncs, 2: number of failed syncs */
				__( 'Synced %1$d pages successfully. %2$d failed.', 'notion-wp' ),
				$success_count,
				$error_count
			);
		} else {
			$message = sprintf(
				/* translators: %d: number of failed pages */
				_n(
					'Failed to sync %d page.',
					'Failed to sync %d pages.',
					$error_count,
					'notion-wp'
				),
				$error_count
			);
		}

		// Send response.
		wp_send_json_success(
			array(
				'message'       => $message,
				'success_count' => $success_count,
				'error_count'   => $error_count,
				'results'       => $results,
			)
		);
	}
}
