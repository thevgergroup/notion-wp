<?php
/**
 * Notion API Client - Handles all communication with Notion API.
 *
 * @package NotionSync
 * @since 0.1.0
 */

namespace NotionSync\API;

/**
 * Class NotionClient
 *
 * Wrapper for Notion API v1 endpoints.
 * Handles authentication, rate limiting, and error handling.
 */
class NotionClient {

	/**
	 * Notion API base URL.
	 *
	 * @var string
	 */
	private $base_url = 'https://api.notion.com/v1';

	/**
	 * Notion API token.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * Notion API version.
	 *
	 * @var string
	 */
	private $api_version = '2022-06-28';

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private $timeout = 30;

	/**
	 * Constructor.
	 *
	 * @param string $token Notion API token.
	 */
	public function __construct( $token ) {
		$this->token = $token;
	}

	/**
	 * Test connection to Notion API.
	 *
	 * @return bool True if connection successful, false otherwise.
	 */
	public function test_connection() {
		try {
			$response = $this->request( 'GET', '/users/me' );
			return ! isset( $response['error'] );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Get workspace information.
	 *
	 * @return array Workspace information or error.
	 */
	public function get_workspace_info() {
		try {
			$user_response = $this->request( 'GET', '/users/me' );

			if ( isset( $user_response['error'] ) ) {
				return $user_response;
			}

			// Extract workspace information.
			$workspace_name = 'Unknown Workspace';
			$user_name      = $user_response['name'] ?? __( 'Unknown User', 'vger-sync-for-notion' );
			$user_email     = $user_response['person']['email'] ?? '';

			// Try to get workspace name from bot object.
			if ( isset( $user_response['bot']['workspace_name'] ) ) {
				$workspace_name = $user_response['bot']['workspace_name'];
			} elseif ( isset( $user_response['bot']['owner']['workspace'] ) && $user_response['bot']['owner']['workspace'] ) {
				$workspace_name = __( 'Workspace', 'vger-sync-for-notion' );
			}

			return array(
				'workspace_name' => $workspace_name,
				'user_name'      => $user_name,
				'user_email'     => $user_email,
				'bot_id'         => $user_response['id'] ?? '',
				'type'           => $user_response['type'] ?? 'bot',
			);
		} catch ( \Exception $e ) {
			return array(
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * List accessible pages.
	 *
	 * @param int $limit Maximum number of pages to return (default 10, max 100).
	 * @return array List of pages or error.
	 */
	public function list_pages( $limit = 10 ) {
		try {
			// Ensure limit is within bounds.
			$limit = max( 1, min( 100, $limit ) );

			// Search for pages (returns both pages and databases).
			$response = $this->request(
				'POST',
				'/search',
				array(
					'page_size' => $limit,
					'filter'    => array(
						'value'    => 'page',
						'property' => 'object',
					),
					'sort'      => array(
						'direction' => 'descending',
						'timestamp' => 'last_edited_time',
					),
				)
			);

			if ( isset( $response['error'] ) ) {
				return array();
			}

			$pages = array();

			if ( isset( $response['results'] ) && is_array( $response['results'] ) ) {
				foreach ( $response['results'] as $result ) {
					$pages[] = $this->format_page_info( $result );
				}
			}

			return $pages;
		} catch ( \Exception $e ) {
			return array();
		}
	}

	/**
	 * Format page information for display.
	 *
	 * @param array $page_data Raw page data from Notion API.
	 * @return array Formatted page information.
	 */
	private function format_page_info( $page_data ) {
		$title = __( 'Untitled', 'vger-sync-for-notion' );

		// Try to get title from properties.
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

		// Fallback: Try to get title from child_page or other sources.
		if ( __( 'Untitled', 'vger-sync-for-notion' ) === $title ) {
			if ( isset( $page_data['child_page']['title'] ) ) {
				$title = $page_data['child_page']['title'];
			}
		}

		return array(
			'id'               => $page_data['id'] ?? '',
			'title'            => $title,
			'url'              => $page_data['url'] ?? '',
			'last_edited_time' => $page_data['last_edited_time'] ?? '',
			'created_time'     => $page_data['created_time'] ?? '',
			'object_type'      => $page_data['object'] ?? 'page',
			'parent_type'      => $this->get_parent_type( $page_data ),
		);
	}

	/**
	 * Determine the parent type of a page to distinguish database entries.
	 *
	 * @param array $page_data Raw page data from Notion API.
	 * @return string Parent type: 'database', 'page', or 'workspace'.
	 */
	private function get_parent_type( $page_data ) {
		if ( isset( $page_data['parent']['type'] ) ) {
			return $page_data['parent']['type'];
		}
		return 'unknown';
	}

	/**
	 * Get a Notion page by ID.
	 *
	 * @param string $page_id Notion page ID.
	 * @return array Page data or error.
	 */
	public function get_page( $page_id ) {
		try {
			$response = $this->request( 'GET', '/pages/' . $page_id );
			return $response;
		} catch ( \Exception $e ) {
			return array(
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * Retrieve a single block by ID.
	 *
	 * Returns block data including type, content, and metadata.
	 * Useful for refreshing S3 URLs for image/file blocks.
	 *
	 * @since 0.4.0
	 *
	 * @param string $block_id Block ID to retrieve.
	 * @return array Block data or error array.
	 */
	public function get_block( $block_id ) {
		try {
			$response = $this->request( 'GET', '/blocks/' . $block_id );
			return $response;
		} catch ( \Exception $e ) {
			return array(
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * Get block children (content blocks from a page or block).
	 *
	 * @param string      $block_id Block or page ID to fetch children from.
	 * @param string|null $cursor   Pagination cursor (null for first page).
	 * @return array Block children data with pagination info, or error.
	 */
	public function get_block_children( $block_id, $cursor = null ) {
		try {
			$endpoint = '/blocks/' . $block_id . '/children';

			// Add pagination parameters if cursor provided.
			$query_params = array();
			if ( null !== $cursor ) {
				$query_params['start_cursor'] = $cursor;
			}

			// Add page size for consistent batches.
			$query_params['page_size'] = 100;

			// Build query string if parameters exist.
			if ( ! empty( $query_params ) ) {
				$endpoint .= '?' . http_build_query( $query_params );
			}

			$response = $this->request( 'GET', $endpoint );
			return $response;
		} catch ( \Exception $e ) {
			return array(
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * Make a request to Notion API.
	 *
	 * @param string $method   HTTP method (GET, POST, PATCH, DELETE).
	 * @param string $endpoint API endpoint (e.g., '/users/me').
	 * @param array  $body     Request body for POST/PATCH requests.
	 * @return array Response data or error.
	 * @throws \Exception If request fails.
	 */
	public function request( $method, $endpoint, $body = array() ) {
		$url = $this->base_url . $endpoint;

		// Prepare headers.
		$headers = array(
			'Authorization'  => 'Bearer ' . $this->token,
			'Content-Type'   => 'application/json',
			'Notion-Version' => $this->api_version,
		);

		// Prepare request arguments.
		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => $this->timeout,
		);

		// Add body for POST/PATCH requests.
		if ( in_array( $method, array( 'POST', 'PATCH' ), true ) && ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		// Make request using WordPress HTTP API.
		$response = wp_remote_request( $url, $args );

		// Handle HTTP errors.
		if ( is_wp_error( $response ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output to users.
			throw new \Exception(
				sprintf(
					/* translators: %s: error message */
					__( 'HTTP request failed: %s', 'vger-sync-for-notion' ),
					$response->get_error_message()
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		// Get response code and body.
		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Decode JSON response.
		$data = json_decode( $response_body, true );

		// Handle API errors.
		if ( $status_code >= 400 ) {
			$error_message = $this->format_api_error( $status_code, $data );
			return array( 'error' => $error_message );
		}

		return $data;
	}

	/**
	 * Load page chunk data from Notion's internal API.
	 *
	 * This queries Notion's loadCachedPageChunkV2 endpoint which provides
	 * collection metadata including collection_id, view configuration,
	 * filters, and sorts for child database blocks.
	 *
	 * @param string $page_id The page or block ID to load chunk data for.
	 * @return array The page chunk response data.
	 */
	public function load_page_chunk( string $page_id ): array {
		// Ensure ID has dashes for the API call.
		if ( 32 === strlen( str_replace( '-', '', $page_id ) ) ) {
			// Add dashes if not present.
			$normalized = str_replace( '-', '', $page_id );
			$page_id    = substr( $normalized, 0, 8 ) . '-' .
				substr( $normalized, 8, 4 ) . '-' .
				substr( $normalized, 12, 4 ) . '-' .
				substr( $normalized, 16, 4 ) . '-' .
				substr( $normalized, 20 );
		}

		// Build request payload.
		$payload = array(
			'page'            => array( 'id' => $page_id ),
			'limit'           => 50,
			'cursor'          => array( 'stack' => array() ),
			'chunkNumber'     => 0,
			'verticalColumns' => false,
		);

		// Use Notion's public site API (doesn't require authentication for public pages).
		// However, for integration access, we may need to use authenticated endpoint.
		$url = 'https://www.notion.so/api/v3/loadCachedPageChunkV2';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => $this->timeout,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'error' => $response->get_error_message(),
			);
		}

		$body        = wp_remote_retrieve_body( $response );
		$status_code = wp_remote_retrieve_response_code( $response );

		$data = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			return array(
				'error'       => 'API request failed',
				'status_code' => $status_code,
				'response'    => $data,
			);
		}

		return $data ?? array();
	}

	/**
	 * Format API error message for user display.
	 *
	 * @param int   $status_code HTTP status code.
	 * @param array $data        Response data containing error details.
	 * @return string Formatted error message.
	 */
	private function format_api_error( $status_code, $data ) {
		// Extract error message from response.
		$api_message = '';
		if ( isset( $data['message'] ) ) {
			$api_message = $data['message'];
		} elseif ( isset( $data['error'] ) ) {
			$api_message = $data['error'];
		}

		// Format user-friendly error messages based on status code.
		switch ( $status_code ) {
			case 400:
				return sprintf(
					/* translators: %s: API error message */
					__( 'Bad request: %s', 'vger-sync-for-notion' ),
					$api_message ? $api_message : __( 'The request was invalid.', 'vger-sync-for-notion' )
				);

			case 401:
				return __( 'Authentication failed. Please check that your API token is correct and has not been revoked.', 'vger-sync-for-notion' );

			case 403:
				return __( 'Access forbidden. Make sure you have shared your Notion pages with this integration.', 'vger-sync-for-notion' );

			case 404:
				return sprintf(
					/* translators: %s: API error message */
					__( 'Resource not found: %s', 'vger-sync-for-notion' ),
					$api_message ? $api_message : __( 'The requested resource does not exist.', 'vger-sync-for-notion' )
				);

			case 429:
				return __( 'Too many requests. Please wait a moment and try again. Notion has rate limits to ensure service stability.', 'vger-sync-for-notion' );

			case 500:
			case 502:
			case 503:
			case 504:
				return sprintf(
					/* translators: %s: API error message */
					__( 'Notion server error: %s. Please try again later.', 'vger-sync-for-notion' ),
					$api_message ? $api_message : __( 'The Notion API is experiencing issues.', 'vger-sync-for-notion' )
				);

			default:
				return sprintf(
					/* translators: 1: HTTP status code, 2: API error message */
					__( 'API error (Code %1$d): %2$s', 'vger-sync-for-notion' ),
					$status_code,
					$api_message ? $api_message : __( 'An unknown error occurred.', 'vger-sync-for-notion' )
				);
		}
	}
}
