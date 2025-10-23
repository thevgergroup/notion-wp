<?php
/**
 * Sync Status REST API Controller
 *
 * Provides REST API endpoints for querying sync status of Notion pages.
 * Supports real-time status polling during bulk sync operations.
 *
 * @package NotionSync\API
 * @since 1.0.0
 */

namespace NotionSync\API;

use NotionSync\Router\LinkRegistry;
use NotionSync\Sync\PageSyncScheduler;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class SyncStatusRestController
 *
 * REST API controller for sync status operations.
 *
 * @since 1.0.0
 */
class SyncStatusRestController extends WP_REST_Controller {

	/**
	 * Link registry instance.
	 *
	 * @var LinkRegistry
	 */
	private LinkRegistry $link_registry;

	/**
	 * Page sync scheduler instance.
	 *
	 * @var PageSyncScheduler
	 */
	private PageSyncScheduler $scheduler;

	/**
	 * Namespace for REST API routes.
	 *
	 * @var string
	 */
	protected $namespace = 'notion-sync/v1';

	/**
	 * Constructor.
	 *
	 * @param LinkRegistry|null      $link_registry Optional. Link registry instance.
	 * @param PageSyncScheduler|null $scheduler     Optional. Page sync scheduler instance.
	 */
	public function __construct( ?LinkRegistry $link_registry = null, ?PageSyncScheduler $scheduler = null ) {
		$this->link_registry = $link_registry ?? new LinkRegistry();
		$this->scheduler     = $scheduler ?? new PageSyncScheduler();
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /notion-sync/v1/sync-status - Get sync status for pages or batch.
		register_rest_route(
			$this->namespace,
			'/sync-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_sync_status' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'page_ids' => array(
						'type'        => 'array',
						'description' => 'Array of Notion page IDs to get status for',
						'items'       => array(
							'type' => 'string',
						),
						'required'    => false,
					),
					'batch_id' => array(
						'type'        => 'string',
						'description' => 'Batch ID to get status for',
						'required'    => false,
					),
				),
			)
		);
	}

	/**
	 * Get sync status for pages and/or batch.
	 *
	 * Returns comprehensive status information for requested pages
	 * and/or active batch operations.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function get_sync_status( WP_REST_Request $request ) {
		$page_ids = $request->get_param( 'page_ids' );
		$batch_id = $request->get_param( 'batch_id' );

		$response = array(
			'pages' => array(),
			'batch' => null,
		);

		// Get status for individual pages if requested.
		if ( ! empty( $page_ids ) && is_array( $page_ids ) ) {
			foreach ( $page_ids as $page_id ) {
				// Validate page ID format.
				if ( ! $this->is_valid_page_id( $page_id ) ) {
					continue;
				}

				// Get comprehensive status from link registry.
				$status = $this->link_registry->get_comprehensive_status( $page_id );

				// Add to response with normalized page ID as key.
				$normalized_id                 = str_replace( '-', '', $page_id );
				$response['pages'][ $normalized_id ] = $status;
			}
		}

		// Get batch status if requested.
		if ( ! empty( $batch_id ) ) {
			$batch_progress = $this->scheduler->get_batch_progress( $batch_id );

			if ( null !== $batch_progress ) {
				// Include batch metadata and per-page statuses.
				$response['batch'] = array(
					'batch_id'       => $batch_id,
					'status'         => $batch_progress['status'] ?? 'unknown',
					'total'          => $batch_progress['total'] ?? 0,
					'processed'      => $batch_progress['processed'] ?? 0,
					'successful'     => $batch_progress['successful'] ?? 0,
					'failed'         => $batch_progress['failed'] ?? 0,
					'percentage'     => $batch_progress['percentage'] ?? 0,
					'started_at'     => $batch_progress['started_at'] ?? null,
					'completed_at'   => $batch_progress['completed_at'] ?? null,
					'current_page_id' => $batch_progress['current_page_id'] ?? null,
					'page_statuses'  => $batch_progress['page_statuses'] ?? array(),
					'results'        => $batch_progress['results'] ?? array(),
				);

				// If batch is active and page_ids weren't explicitly requested,
				// include status for all pages in the batch.
				if ( empty( $page_ids ) && in_array( $batch_progress['status'] ?? '', array( 'queued', 'processing' ), true ) ) {
					$batch_page_ids = $batch_progress['page_ids'] ?? array();

					foreach ( $batch_page_ids as $page_id ) {
						if ( ! $this->is_valid_page_id( $page_id ) ) {
							continue;
						}

						$status                        = $this->link_registry->get_comprehensive_status( $page_id );
						$normalized_id                 = str_replace( '-', '', $page_id );
						$response['pages'][ $normalized_id ] = $status;
					}
				}
			}
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Check permissions for sync status endpoint.
	 *
	 * Only users with 'manage_options' capability can access sync status.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user has permission, false otherwise.
	 */
	public function check_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Validate Notion page ID format.
	 *
	 * Ensures page ID is alphanumeric with optional hyphens.
	 *
	 * @since 1.0.0
	 *
	 * @param string $page_id Page ID to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_page_id( string $page_id ): bool {
		// Allow only alphanumeric characters and hyphens.
		return ! empty( $page_id ) && preg_match( '/^[a-zA-Z0-9\-]+$/', $page_id ) === 1;
	}
}
