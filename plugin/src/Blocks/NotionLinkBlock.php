<?php
/**
 * Notion Link Block
 *
 * Dynamic Gutenberg block for rendering Notion links with always-current data.
 * Stores only the Notion ID in block attributes, fetches title/slug from registry at render time.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks;

use NotionSync\Router\LinkRegistry;

/**
 * Class NotionLinkBlock
 *
 * Registers and renders the notion-sync/link Gutenberg block.
 *
 * @since 1.0.0
 */
class NotionLinkBlock {

	/**
	 * Block name (without namespace)
	 *
	 * @var string
	 */
	private const BLOCK_NAME = 'notion-link';

	/**
	 * Full block name with namespace
	 *
	 * @var string
	 */
	private const FULL_BLOCK_NAME = 'notion-sync/notion-link';

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
	 * Called during init, so register block immediately.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		// Register block immediately (we're already in the init hook).
		$this->register_block();

		// Hook for editor assets.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_editor_assets(): void {
		wp_enqueue_script(
			'notion-link-block-editor',
			NOTION_SYNC_URL . 'assets/src/js/blocks/notion-link.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch' ),
			NOTION_SYNC_VERSION,
			true
		);

		// Add inline CSS for better editor preview
		wp_add_inline_style(
			'wp-edit-blocks',
			'
			.notion-link-block-editor {
				padding: 15px;
				border: 1px solid #ddd;
				border-radius: 4px;
				background: #f9f9f9;
			}
			.notion-link-preview {
				margin-top: 10px;
			}
			.notion-link {
				font-weight: 500;
				text-decoration: none;
			}
			.notion-link--page {
				color: #2271b1;
			}
			.notion-link--database {
				color: #d63638;
			}
			.notion-link-error {
				color: #d63638;
				padding: 10px;
				background: #fcf0f1;
				border-left: 4px solid #d63638;
			}
			'
		);
	}

	/**
	 * Register the Gutenberg block.
	 *
	 * @since 1.0.0
	 */
	public function register_block(): void {
		// Register as a dynamic block with server-side rendering.
		register_block_type(
			self::FULL_BLOCK_NAME,
			array(
				'api_version'     => 2,
				'attributes'      => array(
					'notionId'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'showIcon'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'openInNewTab' => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'customText'   => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => array( $this, 'render_block' ),
				'supports'        => array(
					'html'   => false,
					'anchor' => false,
				),
			)
		);

	}

	/**
	 * Render the block on the frontend.
	 *
	 * Fetches current data from link registry to ensure always-fresh title/slug.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content (not used for dynamic blocks).
	 * @return string Rendered HTML.
	 *
	 * @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $content required by block API.
	 */
	public function render_block( array $attributes, string $content = '' ): string {

		$notion_id = $attributes['notionId'] ?? '';


		if ( empty( $notion_id ) ) {
			// No Notion ID - show placeholder for logged-in users.
			if ( is_user_logged_in() ) {
				return '<p class="notion-link-error">⚠️ Notion link missing ID</p>';
			}
			return '';
		}

		// Fetch current data from registry.
		$entry = $this->registry->find_by_notion_id( $notion_id );

		if ( ! $entry ) {
			// Link not in registry - show placeholder for logged-in users.
			if ( is_user_logged_in() ) {
				return sprintf(
					'<p class="notion-link-error">⚠️ Notion link not found (ID: %s)</p>',
					esc_html( substr( $notion_id, 0, 8 ) . '...' )
				);
			}
			return '';
		}

		// Use appropriate URL based on sync status.
		// If synced to WordPress, link directly to the post permalink.
		// If not synced, link to Notion.
		if ( 'synced' === $entry->sync_status && ! empty( $entry->wp_post_id ) ) {
			// Synced - use WordPress permalink.
			$url = get_permalink( $entry->wp_post_id );
			if ( ! $url ) {
				// Permalink not available - fall back to Notion.
				$url = 'https://notion.so/' . $entry->notion_id;
			}
		} else {
			// Not synced - link directly to Notion.
			$url = 'https://notion.so/' . $entry->notion_id;
		}

		// Determine link text.
		$link_text = $attributes['customText'] ?? '';
		if ( empty( $link_text ) ) {
			// Use current title from registry.
			$link_text = $entry->notion_title;
		}

		// Add icon if enabled.
		if ( $attributes['showIcon'] ?? true ) {
			$icon      = 'database' === $entry->notion_type ? '📊' : '📄';
			$link_text = $icon . ' ' . $link_text;
		}

		// Build link attributes.
		$link_attrs = array(
			'href'           => esc_url( $url ),
			'data-notion-id' => esc_attr( $notion_id ),
			'class'          => 'notion-link notion-link--' . esc_attr( $entry->notion_type ),
		);

		if ( $attributes['openInNewTab'] ?? false ) {
			$link_attrs['target'] = '_blank';
			$link_attrs['rel']    = 'noopener noreferrer';
		}

		// Build attribute string.
		$attrs_string = '';
		foreach ( $link_attrs as $key => $value ) {
			$attrs_string .= sprintf( ' %s="%s"', $key, $value );
		}

		// Wrap link in paragraph for proper block-level rendering.
		return sprintf(
			'<p><a%s>%s</a></p>',
			$attrs_string,
			esc_html( $link_text )
		);
	}
}
