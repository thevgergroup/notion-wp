<?php
/**
 * ContentFetcher - Fetches pages and blocks from Notion API
 *
 * Handles retrieval of Notion pages, their properties, and all blocks with pagination support.
 * This class provides the foundation for content synchronization between Notion and WordPress.
 *
 * @package NotionSync
 * @since 0.2.0
 */

namespace NotionSync\Sync;

use NotionSync\API\NotionClient;

/**
 * Class ContentFetcher
 *
 * Fetches Notion pages and blocks using NotionClient.
 * Handles Notion API pagination automatically for large pages.
 */
class ContentFetcher {

	/**
	 * Notion API client instance.
	 *
	 * @var NotionClient
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param NotionClient $client Authenticated Notion API client.
	 */
	public function __construct( NotionClient $client ) {
		$this->client = $client;
	}

	/**
	 * Fetch list of accessible Notion pages.
	 *
	 * Uses the existing list_pages method from NotionClient which returns
	 * pages sorted by last edited time in descending order.
	 *
	 * @param int $limit Maximum pages to return (default 100, max 100).
	 * @return array Array of page objects with id, title, last_edited_time, created_time, url.
	 */
	public function fetch_pages_list( int $limit = 100 ): array {
		try {
			// Use existing list_pages method from NotionClient.
			$pages = $this->client->list_pages( $limit );

			if ( ! is_array( $pages ) ) {
				error_log( 'NotionSync: fetch_pages_list returned non-array response' );
				return array();
			}

			return $pages;

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'NotionSync: Failed to fetch pages list - %s',
					$e->getMessage()
				)
			);
			return array();
		}
	}

	/**
	 * Fetch page properties (metadata).
	 *
	 * Retrieves page metadata including title, timestamps, and other properties.
	 * Uses Notion API endpoint: GET /pages/{page_id}
	 *
	 * @param string $page_id Notion page ID (with or without dashes).
	 * @return array Page properties: id, title, created_time, last_edited_time, url, properties.
	 *               Returns empty array on error.
	 */
	public function fetch_page_properties( string $page_id ): array {
		if ( empty( $page_id ) ) {
			error_log( 'NotionSync: fetch_page_properties called with empty page_id' );
			return array();
		}

		try {
			// Normalize page ID (remove dashes if present).
			$normalized_id = str_replace( '-', '', $page_id );

			// Call NotionClient's get_page method.
			$response = $this->client->get_page( $normalized_id );

			if ( isset( $response['error'] ) ) {
				error_log(
					sprintf(
						'NotionSync: Failed to fetch page properties for %s - %s',
						$page_id,
						$response['error']
					)
				);
				return array();
			}

			// Extract and format page properties.
			return $this->format_page_properties( $response );

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'NotionSync: Exception fetching page properties for %s - %s',
					$page_id,
					$e->getMessage()
				)
			);
			return array();
		}
	}

	/**
	 * Fetch all blocks from a page with automatic pagination handling.
	 *
	 * Retrieves all content blocks from a Notion page. Handles Notion's pagination
	 * automatically (100 blocks per request) by making multiple API calls as needed.
	 *
	 * @param string $page_id Notion page ID (with or without dashes).
	 * @return array Array of block objects in Notion's native format.
	 *               Returns empty array on error.
	 */
	public function fetch_page_blocks( string $page_id ): array {
		if ( empty( $page_id ) ) {
			error_log( 'NotionSync: fetch_page_blocks called with empty page_id' );
			return array();
		}

		try {
			$all_blocks = array();
			$has_more   = true;
			$cursor     = null;
			$batch_count = 0;
			$max_batches = 50; // Safety limit: 50 batches * 100 blocks = 5000 blocks max.

			// Normalize page ID (remove dashes if present).
			$normalized_id = str_replace( '-', '', $page_id );

			// Fetch blocks in batches until all are retrieved.
			while ( $has_more && $batch_count < $max_batches ) {
				$batch_result = $this->fetch_blocks_batch( $normalized_id, $cursor );

				if ( empty( $batch_result ) || isset( $batch_result['error'] ) ) {
					if ( isset( $batch_result['error'] ) ) {
						error_log(
							sprintf(
								'NotionSync: Error fetching blocks batch for page %s - %s',
								$page_id,
								$batch_result['error']
							)
						);
					}
					break;
				}

				// Add blocks from this batch to collection.
				if ( isset( $batch_result['blocks'] ) && is_array( $batch_result['blocks'] ) ) {
					$all_blocks = array_merge( $all_blocks, $batch_result['blocks'] );
				}

				// Check if there are more blocks to fetch.
				$has_more = $batch_result['has_more'] ?? false;
				$cursor   = $batch_result['next_cursor'] ?? null;
				$batch_count++;
			}

			if ( $batch_count >= $max_batches && $has_more ) {
				error_log(
					sprintf(
						'NotionSync: Reached maximum batch limit (%d) for page %s - some blocks may be missing',
						$max_batches,
						$page_id
					)
				);
			}

			return $all_blocks;

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'NotionSync: Exception fetching blocks for page %s - %s',
					$page_id,
					$e->getMessage()
				)
			);
			return array();
		}
	}

	/**
	 * Fetch a single batch of blocks (max 100 blocks).
	 *
	 * Makes a single API request to retrieve blocks. Used internally by
	 * fetch_page_blocks() to handle pagination.
	 *
	 * Uses Notion API endpoint: GET /blocks/{block_id}/children
	 *
	 * @param string      $block_id Block/page ID to fetch children from.
	 * @param string|null $cursor   Pagination cursor for next batch (null for first batch).
	 * @return array Response containing:
	 *               - 'blocks': Array of block objects
	 *               - 'has_more': Boolean indicating if more blocks exist
	 *               - 'next_cursor': String cursor for next batch (null if no more)
	 *               Returns array with 'error' key on failure.
	 */
	private function fetch_blocks_batch( string $block_id, ?string $cursor = null ): array {
		try {
			// Call NotionClient's get_block_children method.
			$response = $this->client->get_block_children( $block_id, $cursor );

			if ( isset( $response['error'] ) ) {
				return array( 'error' => $response['error'] );
			}

			// Extract pagination info and blocks.
			return array(
				'blocks'      => $response['results'] ?? array(),
				'has_more'    => $response['has_more'] ?? false,
				'next_cursor' => $response['next_cursor'] ?? null,
			);

		} catch ( \Exception $e ) {
			return array(
				'error' => sprintf(
					'Failed to fetch blocks batch: %s',
					$e->getMessage()
				),
			);
		}
	}

	/**
	 * Format raw page data into standardized structure.
	 *
	 * Extracts relevant page properties from Notion API response and
	 * formats them into a consistent structure for use by other components.
	 *
	 * @param array $page_data Raw page data from Notion API.
	 * @return array Formatted page properties with id, title, timestamps, url, and properties.
	 */
	private function format_page_properties( array $page_data ): array {
		$title = 'Untitled';

		// Extract title from properties.
		if ( isset( $page_data['properties'] ) && is_array( $page_data['properties'] ) ) {
			foreach ( $page_data['properties'] as $property ) {
				if ( isset( $property['type'] ) && 'title' === $property['type'] ) {
					if ( isset( $property['title'][0]['plain_text'] ) ) {
						$title = $property['title'][0]['plain_text'];
						break;
					}
				}
			}
		}

		return array(
			'id'               => $page_data['id'] ?? '',
			'title'            => $title,
			'created_time'     => $page_data['created_time'] ?? '',
			'last_edited_time' => $page_data['last_edited_time'] ?? '',
			'url'              => $page_data['url'] ?? '',
			'properties'       => $page_data['properties'] ?? array(),
			'parent'           => $page_data['parent'] ?? array(),
			'icon'             => $page_data['icon'] ?? null,
			'cover'            => $page_data['cover'] ?? null,
		);
	}
}
