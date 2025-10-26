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
	 * If no: fetches fresh S3 URL from Notion and renders placeholder.
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

		// Check MediaRegistry for status.
		$status        = MediaRegistry::get_status( $block_id );
		$attachment_id = MediaRegistry::find( $block_id );

		// If image is marked as unsupported, show permanent Notion URL fallback.
		if ( 'unsupported' === $status ) {
			// Fetch fresh URL if expired.
			if ( empty( $notion_url ) || $this->is_url_expired( $notion_url ) ) {
				$notion_url = $this->get_fresh_notion_url( $block_id );
			}
			return $this->render_unsupported_placeholder( $notion_url, $caption, $alt_text );
		}

		if ( $attachment_id ) {
			// Verify attachment still exists in Media Library.
			if ( ! wp_get_attachment_url( $attachment_id ) ) {
				// Attachment deleted - try to get fresh Notion URL.
				$notion_url = $this->get_fresh_notion_url( $block_id );
			} else {
				// Image downloaded and exists - render proper WordPress image block.
				return $this->render_wordpress_image( $attachment_id, $caption, $alt_text );
			}
		}

		// Image not yet downloaded or attachment missing - fetch fresh URL if needed.
		if ( empty( $notion_url ) || $this->is_url_expired( $notion_url ) ) {
			$notion_url = $this->get_fresh_notion_url( $block_id );
		}

		// Image not yet downloaded - render placeholder.
		return $this->render_placeholder( $notion_url, $caption, $alt_text );
	}

	/**
	 * Get fresh S3 URL from Notion API.
	 *
	 * Notion S3 URLs expire after 1 hour. This fetches a fresh authenticated URL.
	 *
	 * @since 0.4.0
	 *
	 * @param string $block_id Notion block ID.
	 * @return string Fresh Notion S3 URL, or empty string on error.
	 */
	private function get_fresh_notion_url( string $block_id ): string {
		// Get Notion API token.
		$encrypted_token = get_option( 'notion_wp_token' );
		if ( empty( $encrypted_token ) ) {
			return '';
		}

		try {
			$client = new \NotionSync\API\NotionClient( \NotionSync\Security\Encryption::decrypt( $encrypted_token ) );
			$block  = $client->get_block( $block_id );

			if ( isset( $block['type'] ) && 'image' === $block['type'] ) {
				// Check for file type (Notion-hosted).
				if ( isset( $block['image']['file']['url'] ) ) {
					return $block['image']['file']['url'];
				}
				// Check for external type (Unsplash, etc.).
				if ( isset( $block['image']['external']['url'] ) ) {
					return $block['image']['external']['url'];
				}
			}
		} catch ( \Exception $e ) {
			error_log( '[NotionImageBlock] Failed to fetch fresh URL for block ' . $block_id . ': ' . $e->getMessage() );
		}

		return '';
	}

	/**
	 * Check if Notion S3 URL has expired.
	 *
	 * Notion S3 URLs contain X-Amz-Date and X-Amz-Expires parameters.
	 *
	 * @since 0.4.0
	 *
	 * @param string $url Notion S3 URL.
	 * @return bool True if URL has expired or expires soon (within 5 minutes).
	 */
	private function is_url_expired( string $url ): bool {
		// Parse URL and query parameters.
		$parsed = wp_parse_url( $url );
		if ( ! isset( $parsed['query'] ) ) {
			return false;
		}

		parse_str( $parsed['query'], $params );

		// Check for X-Amz-Date and X-Amz-Expires.
		if ( ! isset( $params['X-Amz-Date'] ) || ! isset( $params['X-Amz-Expires'] ) ) {
			return false;
		}

		// Parse X-Amz-Date (format: 20251026T184942Z).
		$amz_date = \DateTime::createFromFormat( 'Ymd\THis\Z', $params['X-Amz-Date'], new \DateTimeZone( 'UTC' ) );
		if ( ! $amz_date ) {
			return false;
		}

		// Calculate expiration time.
		$expires_seconds = (int) $params['X-Amz-Expires'];
		$expiration_time = $amz_date->getTimestamp() + $expires_seconds;

		// Consider expired if within 5 minutes of expiration (300 seconds buffer).
		return ( time() + 300 ) >= $expiration_time;
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

	/**
	 * Render placeholder for unsupported image type.
	 *
	 * Shows Notion URL with message explaining the image format is not supported.
	 *
	 * @since 0.4.0
	 *
	 * @param string $notion_url Notion S3 URL (temporary, expires in 1 hour).
	 * @param string $caption    Image caption.
	 * @param string $alt_text   Image alt text.
	 * @return string Rendered HTML.
	 */
	private function render_unsupported_placeholder( string $notion_url, string $caption, string $alt_text ): string {
		// Unsupported format (e.g., TIFF) - show Notion URL with explanation.
		$placeholder_caption = $caption
			? sprintf( '%s <em>(unsupported format - linked to Notion)</em>', wp_kses_post( $caption ) )
			: '<em>Unsupported image format - linked to Notion</em>';

		if ( empty( $alt_text ) ) {
			$alt_text = $caption ? $caption : 'Image from Notion';
		}

		// Show Notion URL as temporary image.
		if ( ! empty( $notion_url ) ) {
			return sprintf(
				'<figure class="wp-block-image notion-image-unsupported" data-notion-status="unsupported">
					<img src="%s" alt="%s" class="unsupported-format" loading="lazy"/>
					<figcaption class="wp-element-caption">⚠️ %s</figcaption>
				</figure>',
				esc_url( $notion_url ),
				esc_attr( $alt_text ),
				$placeholder_caption
			);
		}

		// No Notion URL - show error placeholder.
		return sprintf(
			'<figure class="wp-block-image notion-image-unsupported" data-notion-status="unsupported">
				<div class="notion-image-error" style="min-height: 200px; display: flex; align-items: center; justify-content: center; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
					<span style="font-size: 48px;">⚠️</span>
				</div>
				<figcaption class="wp-element-caption">%s</figcaption>
			</figure>',
			$placeholder_caption
		);
	}
}
