<?php
/**
 * Bulk Sync Processor - Handles bulk synchronization operations for Notion pages.
 *
 * Extracted from PagesListTable to maintain file size compliance and separation of concerns.
 * Processes bulk sync actions, tracks results, and provides user feedback via admin notices.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Admin;

use NotionSync\Sync\SyncManager;

/**
 * Class BulkSyncProcessor
 *
 * Handles the processing of bulk sync operations for multiple Notion pages.
 * Manages result tracking, error handling, and admin notice generation.
 *
 * @since 1.0.0
 */
class BulkSyncProcessor {

	/**
	 * Sync manager instance.
	 *
	 * @var SyncManager
	 */
	private $manager;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param SyncManager $manager Sync manager instance.
	 */
	public function __construct( SyncManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Process bulk sync action for selected pages.
	 *
	 * Syncs multiple pages, tracks results, displays admin notices,
	 * and redirects to clean URL.
	 *
	 * @since 1.0.0
	 *
	 * @param array $page_ids Array of Notion page IDs to sync.
	 * @return void Exits after redirect.
	 */
	public function process( array $page_ids ) {
		// Validate input.
		if ( empty( $page_ids ) ) {
			add_settings_error(
				'notion_sync',
				'no_pages_selected',
				__( 'No pages selected. Please select pages to sync.', 'notion-wp' ),
				'warning'
			);
			return;
		}

		// Sync each selected page and track results.
		$results = $this->sync_pages( $page_ids );

		// Add admin notice with results.
		$this->add_result_notice(
			$results['success_count'],
			$results['error_count'],
			$results['errors']
		);

		// Redirect to remove the action from URL.
		$this->redirect_after_bulk_action();
	}

	/**
	 * Sync multiple pages and track results.
	 *
	 * @since 1.0.0
	 *
	 * @param array $page_ids Array of Notion page IDs.
	 * @return array Results with success_count, error_count, and errors array.
	 */
	private function sync_pages( array $page_ids ) {
		$success_count = 0;
		$error_count   = 0;
		$errors        = array();

		foreach ( $page_ids as $page_id ) {
			$page_id = sanitize_text_field( $page_id );

			try {
				$result = $this->manager->sync_page( $page_id );

				if ( $result['success'] ) {
					++$success_count;
				} else {
					++$error_count;
					$errors[] = $result['error'] ?? __( 'Unknown error', 'notion-wp' );
				}
			} catch ( \Exception $e ) {
				++$error_count;
				$errors[] = $e->getMessage();
			}
		}

		return array(
			'success_count' => $success_count,
			'error_count'   => $error_count,
			'errors'        => $errors,
		);
	}

	/**
	 * Add admin notice based on sync results.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $success_count Number of successful syncs.
	 * @param int   $error_count   Number of failed syncs.
	 * @param array $errors        Array of error messages.
	 * @return void
	 */
	private function add_result_notice( $success_count, $error_count, $errors ) {
		if ( $success_count > 0 && $error_count === 0 ) {
			// All succeeded.
			add_settings_error(
				'notion_sync',
				'bulk_sync_success',
				sprintf(
					/* translators: %d: number of pages synced */
					_n(
						'Successfully synced %d page.',
						'Successfully synced %d pages.',
						$success_count,
						'notion-wp'
					),
					$success_count
				),
				'success'
			);
		} elseif ( $success_count > 0 && $error_count > 0 ) {
			// Partial success.
			$error_details = $this->format_error_details( $errors );
			add_settings_error(
				'notion_sync',
				'bulk_sync_partial',
				sprintf(
					/* translators: 1: number of successful syncs, 2: number of errors, 3: error details */
					__( 'Synced %1$d pages. Failed to sync %2$d pages.%3$s', 'notion-wp' ),
					$success_count,
					$error_count,
					$error_details
				),
				'warning'
			);
		} else {
			// All failed.
			$error_details = $this->format_error_details( $errors );
			add_settings_error(
				'notion_sync',
				'bulk_sync_error',
				sprintf(
					/* translators: 1: number of errors, 2: error details */
					__( 'Failed to sync %1$d pages.%2$s', 'notion-wp' ),
					$error_count,
					$error_details
				),
				'error'
			);
		}
	}

	/**
	 * Format error details for display in admin notice.
	 *
	 * Shows first 3 errors to avoid overwhelming the user.
	 *
	 * @since 1.0.0
	 *
	 * @param array $errors Array of error messages.
	 * @return string Formatted error details or empty string.
	 */
	private function format_error_details( $errors ) {
		if ( empty( $errors ) ) {
			return '';
		}

		return ' ' . implode( ', ', array_slice( $errors, 0, 3 ) );
	}

	/**
	 * Redirect to settings page after bulk action.
	 *
	 * Removes the bulk action from the URL to prevent resubmission.
	 *
	 * @since 1.0.0
	 *
	 * @return void Exits after redirect.
	 */
	private function redirect_after_bulk_action() {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => 'notion-sync',
					'settings-updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
