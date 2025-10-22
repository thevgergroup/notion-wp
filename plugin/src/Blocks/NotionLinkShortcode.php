<?php
/**
 * Notion Link Shortcode
 *
 * Provides [notion_link] shortcode for inline Notion links within rich text.
 * Used in paragraphs, lists, and other rich text contexts where blocks can't be nested.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks;

use NotionSync\Router\LinkRegistry;

/**
 * Class NotionLinkShortcode
 *
 * Registers and handles the [notion_link] shortcode.
 *
 * Usage: [notion_link id="abc123" text="Custom Text"]
 * Or: [notion_link id="abc123"] (uses current title from registry)
 *
 * @since 1.0.0
 */
class NotionLinkShortcode {

	/**
	 * Shortcode tag
	 *
	 * @var string
	 */
	private const SHORTCODE_TAG = 'notion_link';

	/**
	 * Link registry instance.
	 *
	 * @var LinkRegistry
	 */
	private LinkRegistry $registry;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->registry = new LinkRegistry();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_shortcode( self::SHORTCODE_TAG, array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content (not used).
	 * @return string Rendered HTML link.
	 */
	public function render_shortcode( $atts, $content = null ): string {
		// Parse attributes.
		$atts = shortcode_atts(
			array(
				'id'   => '',
				'text' => '',
			),
			$atts,
			self::SHORTCODE_TAG
		);

		$notion_id = $atts['id'];

		if ( empty( $notion_id ) ) {
			// No ID - return empty for public, error for logged-in users.
			if ( is_user_logged_in() ) {
				return '<span class="notion-link-error" title="Missing Notion ID">⚠️</span>';
			}
			return '';
		}

		// Fetch current data from registry.
		$entry = $this->registry->find_by_notion_id( $notion_id );

		if ( ! $entry ) {
			// Not in registry - return empty for public, error for logged-in users.
			if ( is_user_logged_in() ) {
				return sprintf(
					'<span class="notion-link-error" title="Notion ID: %s">⚠️ Link not found</span>',
					esc_attr( $notion_id )
				);
			}
			return '';
		}

		// Always use /notion/{slug} route for consistency.
		// The NotionRouter will handle redirecting to:
		// - WordPress post permalink if synced
		// - Notion.so URL if not synced
		$url = home_url( '/notion/' . $entry->slug );

		// Determine link text.
		$link_text = ! empty( $atts['text'] ) ? $atts['text'] : $entry->notion_title;

		// Build and return link.
		return sprintf(
			'<a href="%s" data-notion-id="%s" class="notion-link">%s</a>',
			esc_url( $url ),
			esc_attr( $notion_id ),
			esc_html( $link_text )
		);
	}
}
