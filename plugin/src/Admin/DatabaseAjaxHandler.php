<?php
/**
 * Database AJAX Handler
 *
 * Handles AJAX requests for database sync operations, batch progress, and link updates.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Admin;

use NotionSync\API\NotionClient;
use NotionSync\Security\Encryption;

/**
 * Class DatabaseAjaxHandler
 *
 * Manages AJAX endpoints for database synchronization operations.
 *
 * @since 1.0.0
 */
class DatabaseAjaxHandler {

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_action( 'wp_ajax_notion_sync_database', array( $this, 'ajax_sync_database' ) );
		add_action( 'wp_ajax_notion_sync_batch_progress', array( $this, 'ajax_batch_progress' ) );
		add_action( 'wp_ajax_notion_sync_cancel_batch', array( $this, 'ajax_cancel_batch' ) );
		add_action( 'wp_ajax_notion_sync_update_links', array( $this, 'ajax_update_links' ) );
	}

	/**
	 * AJAX handler for database sync.
	 *
	 * Queues a database sync operation and returns the batch ID.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_sync_database() {
		check_ajax_referer( 'notion_sync_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'vger-sync-for-notion' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$database_id = isset( $_POST['database_id'] ) ? sanitize_text_field( wp_unslash( $_POST['database_id'] ) ) : '';

		if ( empty( $database_id ) ) {
			wp_send_json_error( __( 'Database ID is required', 'vger-sync-for-notion' ) );
		}

		try {
			$encrypted_token = get_option( 'notion_wp_token', '' );
			$token           = Encryption::decrypt( $encrypted_token );

			if ( empty( $token ) ) {
				wp_send_json_error( __( 'Not connected to Notion', 'vger-sync-for-notion' ) );
			}

			$client     = new NotionClient( $token );
			$fetcher    = new \NotionSync\Sync\DatabaseFetcher( $client );
			$repository = new \NotionSync\Database\RowRepository();
			$processor  = new \NotionSync\Sync\BatchProcessor( $fetcher, $repository );

			$batch_id = $processor->queue_database_sync( $database_id );

			wp_send_json_success(
				array(
					'batch_id' => $batch_id,
					'message'  => __( 'Database sync started', 'vger-sync-for-notion' ),
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for batch progress.
	 *
	 * Returns current progress of a batch operation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_batch_progress() {
		check_ajax_referer( 'notion_sync_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'vger-sync-for-notion' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';

		if ( empty( $batch_id ) ) {
			wp_send_json_error( __( 'Batch ID is required', 'vger-sync-for-notion' ) );
		}

		try {
			$encrypted_token = get_option( 'notion_wp_token', '' );
			$token           = Encryption::decrypt( $encrypted_token );

			if ( empty( $token ) ) {
				wp_send_json_error( __( 'Not connected to Notion', 'vger-sync-for-notion' ) );
			}

			$client     = new NotionClient( $token );
			$fetcher    = new \NotionSync\Sync\DatabaseFetcher( $client );
			$repository = new \NotionSync\Database\RowRepository();
			$processor  = new \NotionSync\Sync\BatchProcessor( $fetcher, $repository );

			$progress = $processor->get_batch_progress( $batch_id );

			wp_send_json_success( $progress );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for cancelling batch.
	 *
	 * Cancels an in-progress batch operation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_cancel_batch() {
		check_ajax_referer( 'notion_sync_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'vger-sync-for-notion' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';

		if ( empty( $batch_id ) ) {
			wp_send_json_error( __( 'Batch ID is required', 'vger-sync-for-notion' ) );
		}

		try {
			$encrypted_token = get_option( 'notion_wp_token', '' );
			$token           = Encryption::decrypt( $encrypted_token );

			if ( empty( $token ) ) {
				wp_send_json_error( __( 'Not connected to Notion', 'vger-sync-for-notion' ) );
			}

			$client     = new NotionClient( $token );
			$fetcher    = new \NotionSync\Sync\DatabaseFetcher( $client );
			$repository = new \NotionSync\Database\RowRepository();
			$processor  = new \NotionSync\Sync\BatchProcessor( $fetcher, $repository );

			$success = $processor->cancel_batch( $batch_id );

			if ( $success ) {
				wp_send_json_success( __( 'Batch cancelled', 'vger-sync-for-notion' ) );
			} else {
				wp_send_json_error( __( 'Failed to cancel batch', 'vger-sync-for-notion' ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for updating links.
	 *
	 * Updates all Notion links in synced posts to WordPress permalinks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_update_links() {
		check_ajax_referer( 'notion_sync_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'vger-sync-for-notion' ) );
		}

		try {
			$result = \NotionSync\Sync\LinkUpdater::update_all_links();

			wp_send_json_success(
				array(
					'message'         => sprintf(
						/* translators: 1: posts checked, 2: posts updated, 3: links rewritten */
						__( 'Link update complete. Checked %1$d posts, updated %2$d posts, rewrote %3$d links.', 'vger-sync-for-notion' ),
						$result['posts_checked'],
						$result['posts_updated'],
						$result['links_rewritten']
					),
					'posts_checked'   => $result['posts_checked'],
					'posts_updated'   => $result['posts_updated'],
					'links_rewritten' => $result['links_rewritten'],
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}
}
