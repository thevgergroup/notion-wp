<?php
/**
 * Admin Notices - Display success and error messages in WordPress admin.
 *
 * @package NotionSync
 * @since 0.1.0
 */

namespace NotionSync\Admin;

/**
 * Class AdminNotices
 *
 * Handles displaying admin notices for Notion Sync plugin.
 * Shows success and error messages based on query parameters.
 */
class AdminNotices {

	/**
	 * Register admin notice hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', array( $this, 'display_notices' ) );
	}

	/**
	 * Display admin notices based on query parameters.
	 *
	 * @return void
	 */
	public function display_notices() {
		// Only show on our settings page.
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_notion-sync' !== $screen->id ) {
			return;
		}

		// Check for success message.
		if ( isset( $_GET['notion_sync_success'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['notion_sync_success'] ) );
			$this->show_success( rawurldecode( $message ) );
		}

		// Check for error message.
		if ( isset( $_GET['notion_sync_error'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['notion_sync_error'] ) );
			$this->show_error( rawurldecode( $message ) );
		}
	}

	/**
	 * Display a success notice.
	 *
	 * @param string $message Message to display.
	 * @return void
	 */
	public function show_success( $message ) {
		if ( empty( $message ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Success:', 'notion-wp' ),
			esc_html( $message )
		);
	}

	/**
	 * Display an error notice.
	 *
	 * @param string $message Message to display.
	 * @return void
	 */
	public function show_error( $message ) {
		if ( empty( $message ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error is-dismissible"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Error:', 'notion-wp' ),
			esc_html( $message )
		);
	}
}
