<?php
/**
 * Notion Image Block
 *
 * Dynamic Gutenberg block for rendering Notion images with background download support.
 * Stores only block_id in attributes, checks MediaRegistry at render time.
 *
 * Benefits:
 * - No post content updates needed
 * - Self-healing: automatically shows image once downloaded
 * - Graceful degradation: shows placeholder while downloading
 * - Single source of truth: MediaRegistry
 *
 * @package NotionSync\Blocks
 * @since 0.4.0
 */

namespace NotionSync\Blocks;

use NotionSync\Media\MediaRegistry;

/**
 * Class NotionImageBlock
 *
 * Registers and renders the notion-sync/image Gutenberg block.
 *
 * @since 0.4.0
 */
class NotionImageBlock {

	/**
	 * Block name (without namespace)
	 *
	 * @var string
	 */
	private const BLOCK_NAME = 'notion-image';

	/**
	 * Full block name with namespace
	 *
	 * @var string
	 */
	private const FULL_BLOCK_NAME = 'notion-sync/notion-image';

	/**
	 * Register WordPress hooks.
	 *
	 * Called during init.
	 *
	 * @since 0.4.0
	 */
	public function register(): void {
		$this->register_block();
	}

	/**
	 * Register the Gutenberg block.
	 *
	 * @since 0.4.0
	 */
	public function register_block(): void {
		// Register as a dynamic block with server-side rendering.
		register_block_type(
			self::FULL_BLOCK_NAME,
			array(
				'api_version'     => 2,
				'attributes'      => array(
					'blockId'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'notionUrl' => array(
						'type'    => 'string',
						'default' => '',
					),
					'caption'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'altText'   => array(
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

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( '[NotionImageBlock] Block registered: ' . self::FULL_BLOCK_NAME );
	}

	/**
	 * Render the block on the frontend.
	 *
	 * Checks MediaRegistry to see if image has been downloaded.
	 * If yes: renders proper WordPress image block.
	 * If no: renders placeholder with download status.
	 *
	 * @since 0.4.0
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content (not used for dynamic blocks).
	 * @return string Rendered HTML.
	 *
	 * @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $content required by block API.
	 */
	public function render_block( array $attributes, string $content = '' ): string {
		$block_id   = $attributes['blockId'] ?? '';
		$notion_url = $attributes['notionUrl'] ?? '';
		$caption    = $attributes['caption'] ?? '';
		$alt_text   = $attributes['altText'] ?? '';

		if ( empty( $block_id ) ) {
			// No block ID - show error for logged-in users.
			if ( is_user_logged_in() ) {
				return '<p class="notion-image-error">⚠️ Notion image missing block ID</p>';
			}
			return '';
		}

		// Check if image has been downloaded.
		$attachment_id = MediaRegistry::find( $block_id );

		if ( $attachment_id ) {
			// Image downloaded - render proper WordPress image block.
			return $this->render_wordpress_image( $attachment_id, $caption, $alt_text );
		}

		// Image not yet downloaded - render placeholder.
		return $this->render_placeholder( $notion_url, $caption, $alt_text );
	}

	/**
	 * Render WordPress image block with attachment.
	 *
	 * @since 0.4.0
	 *
	 * @param int    $attachment_id WordPress attachment ID.
	 * @param string $caption       Image caption.
	 * @param string $alt_text      Image alt text.
	 * @return string Rendered HTML.
	 */
	private function render_wordpress_image( int $attachment_id, string $caption, string $alt_text ): string {
		$image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $image_url ) {
			// Attachment not found - show error.
			if ( is_user_logged_in() ) {
				return sprintf(
					'<p class="notion-image-error">⚠️ Image attachment %d not found</p>',
					$attachment_id
				);
			}
			return '';
		}

		// Get image metadata for srcset.
		$image_meta = wp_get_attachment_metadata( $attachment_id );
		$size_array = array(
			isset( $image_meta['width'] ) ? $image_meta['width'] : 0,
			isset( $image_meta['height'] ) ? $image_meta['height'] : 0,
		);

		// Generate srcset and sizes attributes.
		$srcset = wp_get_attachment_image_srcset( $attachment_id, 'large', $image_meta );
		$sizes  = wp_get_attachment_image_sizes( $attachment_id, 'large', $image_meta );

		// Use stored alt text or get from attachment.
		if ( empty( $alt_text ) ) {
			$alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		}
		if ( empty( $alt_text ) ) {
			$alt_text = 'Image from Notion';
		}

		// Build image tag with responsive attributes.
		$img_tag = sprintf(
			'<img src="%s" alt="%s" class="wp-image-%d"%s%s/>',
			esc_url( $image_url ),
			esc_attr( $alt_text ),
			$attachment_id,
			$srcset ? sprintf( ' srcset="%s"', esc_attr( $srcset ) ) : '',
			$sizes ? sprintf( ' sizes="%s"', esc_attr( $sizes ) ) : ''
		);

		// Add caption if present.
		$caption_html = '';
		if ( ! empty( $caption ) ) {
			$caption_html = sprintf(
				'<figcaption class="wp-element-caption">%s</figcaption>',
				wp_kses_post( $caption )
			);
		}

		// Return complete figure.
		return sprintf(
			'<figure class="wp-block-image size-large">%s%s</figure>',
			$img_tag,
			$caption_html
		);
	}

	/**
	 * Render placeholder for image being downloaded.
	 *
	 * Shows Notion URL as temporary image with download status indicator.
	 *
	 * @since 0.4.0
	 *
	 * @param string $notion_url Notion S3 URL (temporary, expires in 1 hour).
	 * @param string $caption    Image caption.
	 * @param string $alt_text   Image alt text.
	 * @return string Rendered HTML.
	 */
	private function render_placeholder( string $notion_url, string $caption, string $alt_text ): string {
		// Use Notion URL as temporary fallback (expires in 1 hour).
		// Add visual indicator that image is downloading.
		$placeholder_caption = $caption
			? sprintf( '%s <em>(downloading in background...)</em>', wp_kses_post( $caption ) )
			: '<em>Image downloading in background...</em>';

		if ( empty( $alt_text ) ) {
			$alt_text = $caption ? $caption : 'Image from Notion';
		}

		// If Notion URL is available, show it as temporary image.
		if ( ! empty( $notion_url ) ) {
			return sprintf(
				'<figure class="wp-block-image notion-image-pending" data-notion-status="downloading">
					<img src="%s" alt="%s (downloading...)" class="pending-download" loading="lazy"/>
					<figcaption class="wp-element-caption">⏳ %s</figcaption>
				</figure>',
				esc_url( $notion_url ),
				esc_attr( $alt_text ),
				$placeholder_caption
			);
		}

		// No Notion URL - show spinner placeholder.
		return sprintf(
			'<figure class="wp-block-image notion-image-pending" data-notion-status="downloading">
				<div class="notion-image-spinner" style="min-height: 200px; display: flex; align-items: center; justify-content: center; background: #f0f0f1; border: 1px solid #ddd; border-radius: 4px;">
					<span style="font-size: 48px;">⏳</span>
				</div>
				<figcaption class="wp-element-caption">%s</figcaption>
			</figure>',
			$placeholder_caption
		);
	}
}
