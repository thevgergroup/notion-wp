<?php
/**
 * WP-CLI Command Helpers
 *
 * Provides common helper methods for WP-CLI commands.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\CLI;

use NotionSync\API\NotionClient;
use NotionSync\Security\Encryption;

/**
 * Class CommandHelpers
 *
 * Common utility methods for WP-CLI commands.
 *
 * @since 1.0.0
 */
class CommandHelpers {

	/**
	 * Get authenticated Notion client.
	 *
	 * @return array [NotionClient|null, string|null] Client and error message.
	 */
	public static function get_notion_client(): array {
		$encrypted_token = get_option( 'notion_wp_token' );

		if ( empty( $encrypted_token ) ) {
			return array( null, 'Notion API token not configured. Please configure it in Settings > Notion Sync.' );
		}

		if ( ! Encryption::is_available() ) {
			return array( null, 'Encryption is not available. OpenSSL extension is required.' );
		}

		$token = Encryption::decrypt( $encrypted_token );

		if ( empty( $token ) ) {
			return array( null, 'Failed to decrypt Notion API token.' );
		}

		$client = new NotionClient( $token );

		// Test connection.
		if ( ! $client->test_connection() ) {
			return array( null, 'Failed to connect to Notion API. Please check your token.' );
		}

		return array( $client, null );
	}

	/**
	 * Format timestamp for display.
	 *
	 * @param string $timestamp ISO 8601 timestamp or MySQL timestamp.
	 * @return string Formatted date/time.
	 */
	public static function format_timestamp( string $timestamp ): string {
		if ( empty( $timestamp ) ) {
			return 'N/A';
		}

		$date = strtotime( $timestamp );
		if ( ! $date ) {
			return $timestamp;
		}

		return gmdate( 'Y-m-d H:i:s', $date );
	}

	/**
	 * Detect if a Notion ID is a page or database.
	 *
	 * @param NotionClient $client     Notion client instance.
	 * @param string       $notion_id  Notion ID.
	 * @return string 'page', 'database', or 'unknown'.
	 */
	public static function detect_resource_type( NotionClient $client, string $notion_id ): string {
		// Try fetching as a page first.
		$page_response = $client->get_page( $notion_id );

		if ( isset( $page_response['object'] ) ) {
			if ( 'page' === $page_response['object'] ) {
				// Check if it's a database page (parent is database).
				if ( isset( $page_response['parent']['type'] ) && 'database_id' === $page_response['parent']['type'] ) {
					return 'page'; // It's a database entry (which is still a page).
				}
				return 'page';
			} elseif ( 'database' === $page_response['object'] ) {
				return 'database';
			}
		}

		// Try as database.
		$db_response = $client->request( 'GET', '/databases/' . $notion_id );

		if ( isset( $db_response['object'] ) && 'database' === $db_response['object'] ) {
			return 'database';
		}

		return 'unknown';
	}
}
