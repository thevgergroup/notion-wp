<?php
/**
 * Link Updater - Updates internal Notion links across all synced posts
 *
 * Solves the chicken-and-egg problem: when Page A is synced before Page B,
 * links in Page A will point to Notion. This class updates those links after
 * Page B is synced.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Sync;

use NotionSync\Blocks\LinkRewriter;

/**
 * Class LinkUpdater
 *
 * Updates Notion internal links to WordPress permalinks across all synced posts.
 *
 * @since 1.0.0
 */
class LinkUpdater {
	/**
	 * Post meta key for storing Notion page ID.
	 *
	 * @var string
	 */
	private const META_NOTION_PAGE_ID = 'notion_page_id';

	/**
	 * Update all Notion links in all synced posts
	 *
	 * Finds all posts that were synced from Notion and rewrites any
	 * Notion internal links to WordPress permalinks if the target pages
	 * have been synced.
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *     Update results.
	 *
	 *     @type int $posts_checked   Number of posts checked.
	 *     @type int $posts_updated   Number of posts with links updated.
	 *     @type int $links_rewritten Number of links rewritten.
	 * }
	 */
	public static function update_all_links(): array {
		// Clear the LinkRewriter cache to ensure fresh lookups.
		LinkRewriter::clear_cache();

		// Get all posts that have been synced from Notion.
		$synced_posts = get_posts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'     => self::META_NOTION_PAGE_ID,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$posts_checked   = count( $synced_posts );
		$posts_updated   = 0;
		$links_rewritten = 0;

		foreach ( $synced_posts as $post ) {
			$result = self::update_post_links( $post->ID );

			if ( $result['updated'] ) {
				++$posts_updated;
				$links_rewritten += $result['links_rewritten'];
			}
		}

		return array(
			'posts_checked'   => $posts_checked,
			'posts_updated'   => $posts_updated,
			'links_rewritten' => $links_rewritten,
		);
	}

	/**
	 * Update Notion links in a single post
	 *
	 * Scans the post content for Notion internal links and rewrites them
	 * to WordPress permalinks if the target pages have been synced.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array {
	 *     Update result for this post.
	 *
	 *     @type bool $updated         Whether the post was updated.
	 *     @type int  $links_rewritten Number of links rewritten in this post.
	 * }
	 */
	public static function update_post_links( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array(
				'updated'         => false,
				'links_rewritten' => 0,
			);
		}

		$original_content = $post->post_content;
		$updated_content  = $original_content;
		$links_rewritten  = 0;


		// Check if content has any Notion links.
		$has_notion_links = strpos( $original_content, 'notion.so' ) !== false;

		// Pattern 1: Match links with data-notion-id attribute (newer format).
		// This allows updating links even after they've been rewritten to WordPress permalinks.
		// Matches: <a href="..." data-notion-id="...">.
		$pattern_with_id = '#<a([^>]*?)href="([^"]*)"([^>]*?)data-notion-id="([a-f0-9]{32})"([^>]*)>#i';

		$updated_content = preg_replace_callback(
			$pattern_with_id,
			function ( $matches ) use ( &$links_rewritten ) {
				$before_href = $matches[1];
				$current_url = $matches[2];
				$between     = $matches[3];
				$notion_id   = $matches[4];
				$after_id    = $matches[5];

				// Get the current WordPress permalink for this Notion page.
				$new_url = LinkRewriter::get_wordpress_permalink( $notion_id );

				if ( ! $new_url ) {
					// Page not synced to WordPress post, but may be in link registry.
					// Check registry for current slug.
					$registry = new \NotionSync\Router\LinkRegistry();
					$entry    = $registry->find_by_notion_id( $notion_id );

					if ( $entry ) {
						// Use current registry slug to build /notion/{slug} URL.
						$new_url = home_url( '/notion/' . $entry->slug );
					} else {
						// Not in registry either, keep Notion URL.
						$new_url = 'https://notion.so/' . $notion_id;
					}
				}

				// Only count as rewritten if URL changed.
				if ( $new_url !== $current_url ) {
					$links_rewritten++;
				}

				// Rebuild the anchor tag with updated URL.
				return sprintf(
					'<a%shref="%s"%sdata-notion-id="%s"%s>',
					$before_href,
					esc_url( $new_url ),
					$between,
					esc_attr( $notion_id ),
					$after_id
				);
			},
			$updated_content
		);

		// Pattern 2: Match Notion internal links without data-notion-id (older format).
		// Matches: href="/[32-hex-chars]" or href="https://notion.so/[32-hex-chars]" (with optional query params).
		$pattern_no_id = '~href="(/[a-f0-9]{32}(?:[?#][^"]*)?|https://notion\.so/[a-f0-9]{32}(?:-[a-f0-9]{12})?(?:[?#][^"]*)?)"~i';


		$updated_content = preg_replace_callback(
			$pattern_no_id,
			function ( $matches ) use ( &$links_rewritten ) {
				$original_url  = $matches[1];
				$link_data     = LinkRewriter::rewrite_url( $original_url );
				$rewritten_url = $link_data['url'];

				// Only count as rewritten if the URL actually changed.
				if ( $rewritten_url !== $original_url ) {
					$links_rewritten++;
					return 'href="' . esc_url( $rewritten_url ) . '"';
				}

				return $matches[0]; // No change.
			},
			$updated_content
		);

		// Only update the post if content changed.
		if ( $updated_content !== $original_content ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $updated_content,
				)
			);

			return array(
				'updated'         => true,
				'links_rewritten' => $links_rewritten,
			);
		}

		return array(
			'updated'         => false,
			'links_rewritten' => 0,
		);
	}
}
