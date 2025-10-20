<?php
/**
 * Sync Manager - Orchestrates Notion to WordPress synchronization
 *
 * This class coordinates the entire sync workflow: fetching Notion content,
 * converting blocks to Gutenberg format, and creating/updating WordPress posts.
 * It handles duplicate detection, error handling, and maintains bidirectional
 * mapping between Notion pages and WordPress posts.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Sync;

use NotionSync\API\NotionClient;
use NotionSync\Blocks\BlockConverter;
use NotionSync\Security\Encryption;
use NotionSync\Sync\LinkUpdater;

/**
 * Class SyncManager
 *
 * Orchestrates the synchronization workflow between Notion and WordPress.
 * Provides methods for syncing individual pages and checking sync status.
 *
 * @since 1.0.0
 */
class SyncManager {

	/**
	 * Content fetcher instance.
	 *
	 * @var ContentFetcher
	 */
	private $fetcher;

	/**
	 * Block converter instance.
	 *
	 * @var BlockConverter
	 */
	private $converter;

	/**
	 * Post meta key for storing Notion page ID.
	 *
	 * @var string
	 */
	private const META_NOTION_PAGE_ID = 'notion_page_id';

	/**
	 * Post meta key for storing last sync timestamp.
	 *
	 * @var string
	 */
	private const META_LAST_SYNCED = 'notion_last_synced';

	/**
	 * Post meta key for storing Notion's last edited timestamp.
	 *
	 * @var string
	 */
	private const META_LAST_EDITED = 'notion_last_edited';

	/**
	 * Maximum length for Notion page ID validation.
	 *
	 * @var int
	 */
	private const MAX_PAGE_ID_LENGTH = 50;

	/**
	 * Constructor.
	 *
	 * Initializes the sync manager with optional dependencies.
	 * If no dependencies provided, creates them using stored Notion token.
	 *
	 * @since 1.0.0
	 *
	 * @param ContentFetcher|null $fetcher   Optional. Content fetcher instance.
	 * @param BlockConverter|null $converter Optional. Block converter instance.
	 *
	 * @throws \RuntimeException If Notion token is not configured and no fetcher provided.
	 */
	public function __construct( ?ContentFetcher $fetcher = null, ?BlockConverter $converter = null ) {
		if ( null === $fetcher ) {
			// Initialize fetcher with stored Notion token.
			$fetcher = $this->create_default_fetcher();
		}

		if ( null === $converter ) {
			// Initialize converter with default converters.
			$converter = new BlockConverter();
		}

		$this->fetcher   = $fetcher;
		$this->converter = $converter;
	}

	/**
	 * Sync a Notion page to WordPress.
	 *
	 * This method orchestrates the complete sync workflow:
	 * 1. Validates the Notion page ID
	 * 2. Fetches page properties and blocks from Notion
	 * 3. Checks for existing WordPress post (duplicate detection)
	 * 4. Converts Notion blocks to Gutenberg format
	 * 5. Creates new post or updates existing post
	 * 6. Stores Notion-WordPress mapping in post meta
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_page_id Notion page ID (with or without dashes).
	 * @return array {
	 *     Sync result array.
	 *
	 *     @type bool      $success  Whether sync was successful.
	 *     @type int|null  $post_id  WordPress post ID if successful, null otherwise.
	 *     @type string|null $error  Error message if unsuccessful, null otherwise.
	 * }
	 */
	public function sync_page( string $notion_page_id ): array {
		// Validate Notion page ID format.
		$validation_result = $this->validate_page_id( $notion_page_id );
		if ( ! $validation_result['valid'] ) {
			return array(
				'success' => false,
				'post_id' => null,
				'error'   => $validation_result['error'],
			);
		}

		try {
			// Step 1: Fetch page properties from Notion.
			$page_properties = $this->fetcher->fetch_page_properties( $notion_page_id );
			if ( empty( $page_properties ) ) {
				return array(
					'success' => false,
					'post_id' => null,
					'error'   => 'Failed to fetch page properties from Notion. ' .
					'The page may not exist or the integration may not have access.',
				);
			}

			// Step 2: Fetch page blocks from Notion.
			$notion_blocks = $this->fetcher->fetch_page_blocks( $notion_page_id );
			if ( false === $notion_blocks ) {
				return array(
					'success' => false,
					'post_id' => null,
					'error'   => 'Failed to fetch page blocks from Notion API.',
				);
			}

			// Step 3: Check if page already synced (duplicate detection).
			$existing_post_id = $this->find_existing_post( $notion_page_id );

			// Step 4: Convert Notion blocks to Gutenberg HTML.
			try {
				$gutenberg_html = $this->converter->convert_blocks( $notion_blocks );
			} catch ( \Exception $e ) {
				return array(
					'success' => false,
					'post_id' => null,
					'error'   => sprintf(
						'Block conversion failed: %s',
						$e->getMessage()
					),
				);
			}

			// Step 5: Prepare post data.
			$post_data = $this->prepare_post_data(
				$page_properties,
				$gutenberg_html,
				$notion_page_id,
				$existing_post_id
			);

			// Step 6: Create or update WordPress post.
			if ( $existing_post_id ) {
				$post_data['ID'] = $existing_post_id;
				$post_id         = wp_update_post( $post_data, true );
			} else {
				$post_id = wp_insert_post( $post_data, true );
			}

			// Check for WordPress errors.
			if ( is_wp_error( $post_id ) ) {
				return array(
					'success' => false,
					'post_id' => null,
					'error'   => sprintf(
						'WordPress post creation failed: %s',
						$post_id->get_error_message()
					),
				);
			}

			// Step 7: Store Notion metadata.
			$this->store_post_metadata( $post_id, $notion_page_id, $page_properties );

			// Step 8: Update links across all synced posts.
			// This solves the chicken-and-egg problem where Page A is synced before Page B,
			// causing links in Page A to point to Notion. After syncing Page B, we update
			// all posts to rewrite those Notion links to WordPress permalinks.
			LinkUpdater::update_all_links();

			// Return success result.
			return array(
				'success' => true,
				'post_id' => $post_id,
				'error'   => null,
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'post_id' => null,
				'error'   => sprintf(
					'Sync failed with exception: %s',
					$e->getMessage()
				),
			);
		}
	}

	/**
	 * Get sync status for a Notion page.
	 *
	 * Checks if a Notion page has been synced to WordPress and returns
	 * sync status information including the WordPress post ID and timestamps.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_page_id Notion page ID (with or without dashes).
	 * @return array {
	 *     Sync status information.
	 *
	 *     @type bool        $is_synced   Whether the page has been synced.
	 *     @type int|null    $post_id     WordPress post ID if synced, null otherwise.
	 *     @type string|null $last_synced Last sync timestamp (MySQL format) if synced, null otherwise.
	 * }
	 */
	public function get_sync_status( string $notion_page_id ): array {
		// Normalize page ID for consistent lookup.
		$normalized_id = $this->normalize_page_id( $notion_page_id );

		// Find existing post by meta query.
		$existing_post_id = $this->find_existing_post( $normalized_id );

		if ( ! $existing_post_id ) {
			return array(
				'is_synced'   => false,
				'post_id'     => null,
				'last_synced' => null,
			);
		}

		// Get last synced timestamp from post meta.
		$last_synced = get_post_meta( $existing_post_id, self::META_LAST_SYNCED, true );

		return array(
			'is_synced'   => true,
			'post_id'     => $existing_post_id,
			'last_synced' => $last_synced ? $last_synced : null,
		);
	}

	/**
	 * Validate Notion page ID format.
	 *
	 * Ensures the page ID contains only valid characters (alphanumeric and hyphens)
	 * and is within reasonable length limits.
	 *
	 * @since 1.0.0
	 *
	 * @param string $page_id Notion page ID to validate.
	 * @return array {
	 *     Validation result.
	 *
	 *     @type bool   $valid Whether the ID is valid.
	 *     @type string $error Error message if invalid, empty string if valid.
	 * }
	 */
	private function validate_page_id( string $page_id ): array {
		if ( empty( $page_id ) ) {
			return array(
				'valid' => false,
				'error' => 'Notion page ID cannot be empty.',
			);
		}

		if ( strlen( $page_id ) > self::MAX_PAGE_ID_LENGTH ) {
			return array(
				'valid' => false,
				'error' => sprintf(
					'Notion page ID exceeds maximum length of %d characters.',
					self::MAX_PAGE_ID_LENGTH
				),
			);
		}

		// Allow only alphanumeric characters and hyphens.
		if ( ! preg_match( '/^[a-zA-Z0-9\-]+$/', $page_id ) ) {
			return array(
				'valid' => false,
				'error' => 'Notion page ID contains invalid characters. Only alphanumeric characters and hyphens are allowed.',
			);
		}

		return array(
			'valid' => true,
			'error' => '',
		);
	}

	/**
	 * Find existing WordPress post for a Notion page.
	 *
	 * Searches for a WordPress post that has been previously synced from the
	 * given Notion page by querying post meta for the Notion page ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_page_id Notion page ID.
	 * @return int|null WordPress post ID if found, null otherwise.
	 */
	private function find_existing_post( string $notion_page_id ): ?int {
		$normalized_id = $this->normalize_page_id( $notion_page_id );

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'     => self::META_NOTION_PAGE_ID,
						'value'   => $normalized_id,
						'compare' => '=',
					),
				),
				'fields'         => 'ids',
			)
		);

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Normalize Notion page ID.
	 *
	 * Removes dashes from page ID for consistent storage and comparison.
	 * Notion page IDs can be provided with or without dashes, but we store
	 * them without dashes for consistency.
	 *
	 * @since 1.0.0
	 *
	 * @param string $page_id Notion page ID (with or without dashes).
	 * @return string Normalized page ID (without dashes).
	 */
	private function normalize_page_id( string $page_id ): string {
		return str_replace( '-', '', $page_id );
	}

	/**
	 * Prepare WordPress post data array.
	 *
	 * Builds the post data array for wp_insert_post() or wp_update_post()
	 * using Notion page properties and converted content.
	 *
	 * @since 1.0.0
	 *
	 * @param array       $page_properties   Notion page properties from ContentFetcher.
	 * @param string      $gutenberg_html    Converted Gutenberg HTML content.
	 * @param string      $notion_page_id    Notion page ID.
	 * @param int|null    $existing_post_id  Existing post ID if updating, null if creating.
	 * @return array WordPress post data array for wp_insert_post/wp_update_post.
	 */
	private function prepare_post_data(
		array $page_properties,
		string $gutenberg_html,
		string $notion_page_id,
		?int $existing_post_id = null
	): array {
		// Extract title from page properties.
		$title = $page_properties['title'] ?? 'Untitled';

		// Sanitize title.
		$title = sanitize_text_field( $title );

		// Use wp_kses_post to allow safe HTML in content.
		$content = wp_kses_post( $gutenberg_html );

		$post_data = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'draft', // Always draft for safety in MVP.
			'post_type'    => 'post',  // Standard posts for MVP.
		);

		// Don't include meta_input when updating - use update_post_meta instead.
		// This prevents overwriting other meta fields.
		if ( ! $existing_post_id ) {
			$post_data['meta_input'] = array(
				self::META_NOTION_PAGE_ID => $this->normalize_page_id( $notion_page_id ),
				self::META_LAST_SYNCED    => current_time( 'mysql' ),
				self::META_LAST_EDITED    => $page_properties['last_edited_time'] ?? '',
			);
		}

		return $post_data;
	}

	/**
	 * Store Notion metadata in post meta.
	 *
	 * Saves the Notion page ID, sync timestamp, and Notion's last edited
	 * timestamp in WordPress post meta for tracking and duplicate detection.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id         WordPress post ID.
	 * @param string $notion_page_id  Notion page ID.
	 * @param array  $page_properties Notion page properties.
	 */
	private function store_post_metadata( int $post_id, string $notion_page_id, array $page_properties ): void {
		update_post_meta( $post_id, self::META_NOTION_PAGE_ID, $this->normalize_page_id( $notion_page_id ) );
		update_post_meta( $post_id, self::META_LAST_SYNCED, current_time( 'mysql' ) );
		update_post_meta( $post_id, self::META_LAST_EDITED, $page_properties['last_edited_time'] ?? '' );
	}

	/**
	 * Create default content fetcher.
	 *
	 * Creates a ContentFetcher instance using the stored Notion API token.
	 * This is used when no fetcher is provided to the constructor.
	 *
	 * @since 1.0.0
	 *
	 * @return ContentFetcher Configured content fetcher instance.
	 * @throws \RuntimeException If Notion token is not configured or encryption fails.
	 */
	private function create_default_fetcher(): ContentFetcher {
		// Get encrypted token from WordPress options.
		$encrypted_token = get_option( 'notion_wp_token' );

		if ( ! $encrypted_token ) {
			throw new \RuntimeException(
				'Notion API token is not configured. Please configure the token in plugin settings.'
			);
		}

		// Decrypt token.
		if ( ! Encryption::is_available() ) {
			throw new \RuntimeException(
				'Encryption is not available. OpenSSL extension is required.'
			);
		}

		$token = Encryption::decrypt( $encrypted_token );

		if ( empty( $token ) ) {
			throw new \RuntimeException(
				'Failed to decrypt Notion API token. The token may be corrupted.'
			);
		}

		// Create Notion client and content fetcher.
		$client = new NotionClient( $token );

		return new ContentFetcher( $client );
	}
}
