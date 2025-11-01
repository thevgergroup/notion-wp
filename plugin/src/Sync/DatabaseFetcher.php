<?php
/**
 * Database Fetcher - Queries Notion databases and extracts data
 *
 * This class handles fetching database entries, schemas, and property extraction
 * from the Notion API. It normalizes Notion's complex property structure into
 * simple key-value pairs suitable for JSON storage.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Sync;

use NotionSync\API\NotionClient;

/**
 * Class DatabaseFetcher
 *
 * Fetches Notion database entries and schema, handling pagination and
 * property value extraction.
 *
 * @since 1.0.0
 */
class DatabaseFetcher {

	/**
	 * Notion API client instance.
	 *
	 * @var NotionClient
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param NotionClient $client Notion API client.
	 */
	public function __construct( NotionClient $client ) {
		$this->client = $client;
	}

	/**
	 * Query all entries from a Notion database.
	 *
	 * Handles pagination automatically. Notion returns max 100 entries per request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $database_id Notion database ID.
	 * @param array  $filters     Optional query filters.
	 * @param array  $sorts       Optional sort configuration.
	 * @return array Array of database page objects.
	 * @throws \RuntimeException If API request fails.
	 */
	public function query_database(
		string $database_id,
		array $filters = array(),
		array $sorts = array()
	): array {
		$all_entries  = array();
		$has_more     = true;
		$start_cursor = null;

		while ( $has_more ) {
			$payload = array();

			if ( ! empty( $filters ) ) {
				$payload['filter'] = $filters;
			}

			if ( ! empty( $sorts ) ) {
				$payload['sorts'] = $sorts;
			}

			if ( $start_cursor ) {
				$payload['start_cursor'] = $start_cursor;
			}

			$response = $this->client->request(
				'POST',
				'/databases/' . $database_id . '/query',
				$payload
			);

			if ( ! isset( $response['results'] ) || ! is_array( $response['results'] ) ) {
				throw new \RuntimeException(
					'Invalid response from Notion API when querying database'
				);
			}

			$all_entries = array_merge( $all_entries, $response['results'] );

			$has_more     = $response['has_more'] ?? false;
			$start_cursor = $response['next_cursor'] ?? null;
		}

		return $all_entries;
	}

	/**
	 * Get database schema (properties/columns).
	 *
	 * Fetches database metadata including properties/columns configuration.
	 * Also retrieves the collection_id from the page chunk data, which is distinct
	 * from the database block ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $database_id Notion database block ID.
	 * @return array Database properties configuration with keys:
	 *               - id: The database block ID (normalized)
	 *               - collection_id: The collection ID (normalized, may be null)
	 *               - title: Database title
	 *               - last_edited_time: ISO 8601 timestamp
	 *               - properties: Array of property definitions
	 * @throws \RuntimeException If API request fails.
	 */
	public function get_database_schema( string $database_id ): array {
		$response = $this->client->request(
			'GET',
			'/databases/' . $database_id
		);

		if ( ! isset( $response['properties'] ) ) {
			throw new \RuntimeException(
				'Invalid response from Notion API when fetching database schema'
			);
		}

		// The 'id' field from the Notion database API response is the database block ID.
		$database_id_from_api = $response['id'] ?? '';

		// Retrieve collection_id from page chunk data.
		// This is distinct from the database block ID and is used for certain operations.
		$collection_id = $this->extract_collection_id( $database_id );

		return array(
			'id'               => $database_id_from_api,
			'collection_id'    => $collection_id,
			'title'            => $this->extract_title_from_database( $response ),
			'last_edited_time' => $response['last_edited_time'] ?? '',
			'properties'       => $response['properties'],
		);
	}

	/**
	 * Get all accessible databases for current integration.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of databases with metadata.
	 */
	public function get_databases(): array {
		$all_databases = array();
		$has_more      = true;
		$start_cursor  = null;

		while ( $has_more ) {
			$payload = array(
				'filter' => array(
					'property' => 'object',
					'value'    => 'database',
				),
			);

			if ( $start_cursor ) {
				$payload['start_cursor'] = $start_cursor;
			}

			$response = $this->client->request(
				'POST',
				'/search',
				$payload
			);

			if ( ! isset( $response['results'] ) || ! is_array( $response['results'] ) ) {
				break;
			}

			// Extract database metadata.
			foreach ( $response['results'] as $database ) {
				$all_databases[] = array(
					'id'               => $database['id'] ?? '',
					'title'            => $this->extract_title_from_database( $database ),
					'last_edited_time' => $database['last_edited_time'] ?? '',
					'url'              => $database['url'] ?? '',
				);
			}

			$has_more     = $response['has_more'] ?? false;
			$start_cursor = $response['next_cursor'] ?? null;
		}

		return $all_databases;
	}

	/**
	 * Normalize database entry for storage.
	 *
	 * Extracts all properties into simple key-value format suitable for JSON storage.
	 *
	 * @since 1.0.0
	 *
	 * @param array $entry Database page object from Notion API.
	 * @return array Normalized entry ready for JSON storage.
	 */
	public function normalize_entry( array $entry ): array {
		$normalized = array(
			'id'               => $entry['id'] ?? '',
			'created_time'     => $entry['created_time'] ?? '',
			'last_edited_time' => $entry['last_edited_time'] ?? '',
			'properties'       => array(),
		);

		// Extract all properties.
		if ( isset( $entry['properties'] ) && is_array( $entry['properties'] ) ) {
			foreach ( $entry['properties'] as $property_name => $property_data ) {
				$normalized['properties'][ $property_name ] = $this->extract_property_value(
					$property_data
				);
			}
		}

		return $normalized;
	}

	/**
	 * Extract property value from Notion property data.
	 *
	 * Converts Notion property format to simple PHP value.
	 *
	 * @since 1.0.0
	 *
	 * @param array $property_data Property data from Notion API.
	 * @return mixed Property value (varies by type).
	 */
	private function extract_property_value( array $property_data ) {
		$type = $property_data['type'] ?? '';

		switch ( $type ) {
			case 'title':
				return $this->extract_rich_text( $property_data['title'] ?? array() );

			case 'rich_text':
				return $this->extract_rich_text( $property_data['rich_text'] ?? array() );

			case 'number':
				return $property_data['number'];

			case 'select':
				return isset( $property_data['select']['name'] )
					? $property_data['select']['name']
					: null;

			case 'multi_select':
				$values = array();
				if ( isset( $property_data['multi_select'] ) && is_array( $property_data['multi_select'] ) ) {
					foreach ( $property_data['multi_select'] as $item ) {
						if ( isset( $item['name'] ) ) {
							$values[] = $item['name'];
						}
					}
				}
				return $values;

			case 'date':
				if ( isset( $property_data['date']['start'] ) ) {
					$start = $property_data['date']['start'];
					$end   = $property_data['date']['end'] ?? null;
					return $end ? array(
						'start' => $start,
						'end'   => $end,
					) : $start;
				}
				return null;

			case 'checkbox':
				return $property_data['checkbox'] ?? false;

			case 'url':
				return $property_data['url'] ?? null;

			case 'email':
				return $property_data['email'] ?? null;

			case 'phone_number':
				return $property_data['phone_number'] ?? null;

			case 'relation':
				$ids = array();
				if ( isset( $property_data['relation'] ) && is_array( $property_data['relation'] ) ) {
					foreach ( $property_data['relation'] as $item ) {
						if ( isset( $item['id'] ) ) {
							$ids[] = $item['id'];
						}
					}
				}
				return $ids;

			case 'formula':
				// Extract computed value from formula.
				if ( isset( $property_data['formula']['type'] ) ) {
					$formula_type = $property_data['formula']['type'];
					return $property_data['formula'][ $formula_type ] ?? null;
				}
				return null;

			case 'rollup':
				// Extract computed value from rollup.
				if ( isset( $property_data['rollup']['type'] ) ) {
					$rollup_type = $property_data['rollup']['type'];
					return $property_data['rollup'][ $rollup_type ] ?? null;
				}
				return null;

			case 'people':
				$people = array();
				if ( isset( $property_data['people'] ) && is_array( $property_data['people'] ) ) {
					foreach ( $property_data['people'] as $person ) {
						if ( isset( $person['name'] ) ) {
							$people[] = $person['name'];
						}
					}
				}
				return $people;

			case 'files':
				$files = array();
				if ( isset( $property_data['files'] ) && is_array( $property_data['files'] ) ) {
					foreach ( $property_data['files'] as $file ) {
						if ( isset( $file['name'] ) ) {
							$files[] = array(
								'name' => $file['name'],
								'url'  => $file['file']['url'] ?? $file['external']['url'] ?? '',
							);
						}
					}
				}
				return $files;

			case 'status':
				return isset( $property_data['status']['name'] )
					? $property_data['status']['name']
					: null;

			default:
				// Unknown property type - return raw data.
				return $property_data;
		}
	}

	/**
	 * Extract plain text from Notion rich text array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $rich_text Rich text array from Notion API.
	 * @return string Concatenated plain text.
	 */
	private function extract_rich_text( array $rich_text ): string {
		$text = '';

		foreach ( $rich_text as $segment ) {
			if ( isset( $segment['plain_text'] ) ) {
				$text .= $segment['plain_text'];
			}
		}

		return $text;
	}

	/**
	 * Extract title from database object.
	 *
	 * @since 1.0.0
	 *
	 * @param array $database Database object from Notion API.
	 * @return string Database title.
	 */
	private function extract_title_from_database( array $database ): string {
		if ( isset( $database['title'] ) && is_array( $database['title'] ) ) {
			return $this->extract_rich_text( $database['title'] );
		}

		return 'Untitled Database';
	}

	/**
	 * Extract collection_id from page chunk data.
	 *
	 * The collection_id is distinct from the database block ID and is required
	 * for certain Notion API operations. This method queries the page chunk data
	 * to retrieve it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $database_id Database block ID (with or without dashes).
	 * @return string|null Collection ID (normalized without dashes), or null if not found.
	 */
	private function extract_collection_id( string $database_id ): ?string {
		try {
			// Load page chunk data from Notion's internal API.
			$chunk_data = $this->client->load_page_chunk( $database_id );

			// Check for errors in the response.
			if ( isset( $chunk_data['error'] ) ) {
				// Log the error but don't fail the entire operation.
				// Collection ID is optional for some operations.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging is intentional.
					error_log(
						sprintf(
							'NotionSync: Failed to retrieve collection_id for database %s: %s',
							$database_id,
							$chunk_data['error']
						)
					);
				}
				return null;
			}

			// Normalize database ID to match the format in recordMap.
			// The chunk API uses UUIDs with dashes.
			$formatted_id = $this->normalize_id_with_dashes( $database_id );

			// Extract collection_id from the block data.
			if (
				isset( $chunk_data['recordMap']['block'][ $formatted_id ]['value']['collection_id'] )
			) {
				$collection_id = $chunk_data['recordMap']['block'][ $formatted_id ]['value']['collection_id'];
				// Normalize to remove dashes for consistency.
				return str_replace( '-', '', $collection_id );
			}

			// Collection ID not found in expected location.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging is intentional.
				error_log(
					sprintf(
						'NotionSync: Collection ID not found in page chunk data for database %s',
						$database_id
					)
				);
			}
			return null;

		} catch ( \Exception $e ) {
			// Log exception but don't fail the entire operation.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging is intentional.
				error_log(
					sprintf(
						'NotionSync: Exception while extracting collection_id for database %s: %s',
						$database_id,
						$e->getMessage()
					)
				);
			}
			return null;
		}
	}

	/**
	 * Normalize UUID to include dashes.
	 *
	 * Converts a UUID from either format (with or without dashes) to the standard
	 * format with dashes: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
	 *
	 * @since 1.0.0
	 *
	 * @param string $id UUID with or without dashes.
	 * @return string UUID with dashes in standard format.
	 */
	private function normalize_id_with_dashes( string $id ): string {
		// Remove any existing dashes.
		$normalized = str_replace( '-', '', $id );

		// Validate length (should be 32 hex characters).
		if ( 32 !== strlen( $normalized ) ) {
			return $id; // Return original if invalid.
		}

		// Add dashes in UUID format: 8-4-4-4-12.
		return sprintf(
			'%s-%s-%s-%s-%s',
			substr( $normalized, 0, 8 ),
			substr( $normalized, 8, 4 ),
			substr( $normalized, 12, 4 ),
			substr( $normalized, 16, 4 ),
			substr( $normalized, 20 )
		);
	}
}
