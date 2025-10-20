<?php
/**
 * Sync AJAX Handler - Handles AJAX requests for page synchronization.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Admin;

use NotionSync\Sync\SyncManager;

/**
 * Class SyncAjaxHandler
 *
 * Handles AJAX requests for syncing Notion pages to WordPress.
 * Extracted from SettingsPage to maintain single responsibility and file size compliance.
 */
class SyncAjaxHandler {

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_notion_sync_page', array( $this, 'handle_sync_page_ajax' ) );
		add_action( 'wp_ajax_notion_bulk_sync', array( $this, 'handle_bulk_sync_ajax' ) );
	}

	/**
	 * Handle AJAX request to sync a single Notion page.
	 *
	 * Syncs a single Notion page to WordPress and returns the result.
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
		$page_ids = isset( $_POST['page_ids'] ) ? (array) wp_unslash( $_POST['page_ids'] ) : array();
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
					++$success_count;
					$results[ $page_id ] = array(
						'success' => true,
						'post_id' => $result['post_id'],
					);
				} else {
					++$error_count;
					$results[ $page_id ] = array(
						'success' => false,
						'error'   => $result['error'],
					);
				}
			} catch ( \Exception $e ) {
				++$error_count;
				$results[ $page_id ] = array(
					'success' => false,
					'error'   => $e->getMessage(),
				);
			}
		}

		// Build response message.
		if ( 0 < $success_count && 0 === $error_count ) {
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
