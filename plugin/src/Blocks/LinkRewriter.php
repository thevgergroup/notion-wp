<?php
/**
 * Link Rewriter Utility
 *
 * Converts Notion internal links to WordPress permalinks when the target
 * page has been synced to WordPress.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks;

/**
 * Class LinkRewriter
 *
 * Provides utility methods for detecting and rewriting Notion internal links
 * to WordPress permalinks based on sync status.
 *
 * @since 1.0.0
 */
class LinkRewriter {
	/**
	 * Post meta key for storing Notion page ID.
	 *
	 * @var string
	 */
	private const META_NOTION_PAGE_ID = 'notion_page_id';

	/**
	 * Cache for page ID lookups to avoid repeated queries.
	 *
	 * @var array<string, int|null>
	 */
	private static $lookup_cache = array();

	/**
	 * Rewrite a URL if it's a Notion internal link
	 *
	 * Detects Notion internal links (format: /[32-char-hex-id]) and converts
	 * them to WordPress permalinks if the target page has been synced.
	 *
	 * Returns an array with both the URL and the Notion page ID (if found)
	 * so that converters can add data-notion-id attributes for future updates.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to check and potentially rewrite.
	 * @return array {
	 *     Link rewriting result.
	 *
	 *     @type string      $url            The rewritten URL (WordPress permalink) or original URL.
	 *     @type string|null $notion_page_id The Notion page ID if this is a Notion link, null otherwise.
	 * }
	 */
	public static function rewrite_url( string $url ): array {
		// Check if this is a Notion internal link (starts with / and contains page ID).
		$notion_page_id = self::extract_notion_page_id( $url );

		if ( ! $notion_page_id ) {
			// Not a Notion internal link, return as-is.
			return array(
				'url'            => $url,
				'notion_page_id' => null,
			);
		}

		// Look up the WordPress post for this Notion page.
		$post_id = self::find_post_by_notion_id( $notion_page_id );

		if ( ! $post_id ) {
			// Page not synced yet, return Notion URL.
			return array(
				'url'            => 'https://notion.so/' . $notion_page_id,
				'notion_page_id' => $notion_page_id,
			);
		}

		// Get WordPress permalink.
		$permalink = get_permalink( $post_id );

		return array(
			'url'            => $permalink ? $permalink : $url,
			'notion_page_id' => $notion_page_id,
		);
	}

	/**
	 * Rewrite a URL and return just the URL string (backward compatibility)
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to check and potentially rewrite.
	 * @return string The rewritten URL (WordPress permalink) or original URL.
	 */
	public static function rewrite_url_string( string $url ): string {
		$result = self::rewrite_url( $url );
		return $result['url'];
	}

	/**
	 * Extract Notion page ID from a URL
	 *
	 * Checks if the URL is a Notion internal link format and extracts the page ID.
	 * Notion internal links have format: /[32-char-hex-id]
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to analyze.
	 * @return string|null The extracted page ID (without dashes) or null if not a Notion link.
	 */
	private static function extract_notion_page_id( string $url ): ?string {
		// Notion internal links start with / followed by 32 hex characters (page ID without dashes).
		// Example: /75424b1c35d0476b836cbb0e776f3f7c
		if ( preg_match( '#^/([a-f0-9]{32})(?:[/?\\#].*)?$#i', $url, $matches ) ) {
			return $matches[1];
		}

		// Also support full Notion URLs for completeness.
		// Example: https://notion.so/75424b1c35d0476b836cbb0e776f3f7c
		if ( preg_match( '#notion\.so/([a-f0-9]{32}(?:-[a-f0-9]{12})?)#i', $url, $matches ) ) {
			// Remove dashes if present.
			return str_replace( '-', '', $matches[1] );
		}

		return null;
	}

	/**
	 * Find WordPress post ID by Notion page ID
	 *
	 * Searches for a WordPress post that has been synced from the given Notion page.
	 * Results are cached to avoid repeated database queries.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_page_id Notion page ID (without dashes).
	 * @return int|null WordPress post ID if found, null otherwise.
	 */
	private static function find_post_by_notion_id( string $notion_page_id ): ?int {
		// Check cache first.
		if ( isset( self::$lookup_cache[ $notion_page_id ] ) ) {
			return self::$lookup_cache[ $notion_page_id ];
		}

		// Query for post with this Notion page ID in meta.
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'     => self::META_NOTION_PAGE_ID,
						'value'   => $notion_page_id,
						'compare' => '=',
					),
				),
				'fields'         => 'ids',
			)
		);

		$post_id = ! empty( $posts ) ? $posts[0] : null;

		// Cache the result.
		self::$lookup_cache[ $notion_page_id ] = $post_id;

		return $post_id;
	}

	/**
	 * Check if a Notion page has been synced to WordPress
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_page_id Notion page ID (with or without dashes).
	 * @return bool True if the page has been synced, false otherwise.
	 */
	public static function is_page_synced( string $notion_page_id ): bool {
		// Normalize page ID (remove dashes).
		$normalized_id = str_replace( '-', '', $notion_page_id );

		return null !== self::find_post_by_notion_id( $normalized_id );
	}

	/**
	 * Get WordPress permalink for a Notion page
	 *
	 * Returns the WordPress permalink if the page has been synced, null otherwise.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notion_page_id Notion page ID (with or without dashes).
	 * @return string|null WordPress permalink or null if not synced.
	 */
	public static function get_wordpress_permalink( string $notion_page_id ): ?string {
		// Normalize page ID (remove dashes).
		$normalized_id = str_replace( '-', '', $notion_page_id );

		$post_id = self::find_post_by_notion_id( $normalized_id );

		if ( ! $post_id ) {
			return null;
		}

		$permalink = get_permalink( $post_id );

		return $permalink ? $permalink : null;
	}

	/**
	 * Clear the lookup cache
	 *
	 * Should be called after bulk sync operations to ensure fresh data.
	 *
	 * @since 1.0.0
	 */
	public static function clear_cache(): void {
		self::$lookup_cache = array();
	}
}
