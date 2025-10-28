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
	 * Uses WordPress dynamic block API with server-side rendering via render_callback.
	 * The block will be rendered on every page load, checking MediaRegistry for the
	 * latest image status.
	 *
	 * @since 0.4.0
	 */
	public function register_block(): void {
		// Verify WP_Block_Type_Registry is available.
		if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
			error_log( '[NotionImageBlock] WP_Block_Type_Registry not available - cannot register block' );
			return;
		}

		// Register as a dynamic block with server-side rendering.
		$result = register_block_type(
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

		if ( $result instanceof \WP_Block_Type ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[NotionImageBlock] Block registered successfully: ' . self::FULL_BLOCK_NAME );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[NotionImageBlock] Block registration failed for: ' . self::FULL_BLOCK_NAME );
		}
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
	 * @param string $content    Block content (inner HTML from post_content).
	 * @return string Rendered HTML.
	 *
	 * @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $content required by block API.
	 */
	public function render_block( array $attributes, string $content = '' ): string {
		// Enhanced debug logging to verify render_callback is being invoked.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log(
			sprintf(
				'[NotionImageBlock] render_block CALLED - Attributes: %s | Content length: %d | Content preview: %s',
				wp_json_encode( $attributes ),
				strlen( $content ),
				substr( $content, 0, 100 )
			)
		);

		$block_id   = $attributes['blockId'] ?? '';
		$notion_url = $attributes['notionUrl'] ?? '';
		$caption    = $attributes['caption'] ?? '';
		$alt_text   = $attributes['altText'] ?? '';

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log(
			sprintf(
				'[NotionImageBlock] Extracted values - block_id: %s | notion_url length: %d | caption: %s',
				substr( $block_id, 0, 8 ),
				strlen( $notion_url ),
				$caption ? substr( $caption, 0, 30 ) : 'empty'
			)
		);

		if ( empty( $block_id ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[NotionImageBlock] EARLY RETURN: Empty block_id' );
			// No block ID - show error for users with proper capabilities only.
			if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' ) ) {
				return '<p class="notion-image-error">⚠️ Notion image missing block ID</p>';
			}
			return '';
		}

		// Check MediaRegistry for status.
		$status        = MediaRegistry::get_status( $block_id );
		$attachment_id = MediaRegistry::find( $block_id );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log(
			sprintf(
				'[NotionImageBlock] MediaRegistry lookup - status: %s | attachment_id: %s',
				$status ?? 'null',
				$attachment_id ?? 'null'
			)
		);

		// If image is marked as unsupported, show permanent Notion URL fallback.
		if ( 'unsupported' === $status ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[NotionImageBlock] Rendering UNSUPPORTED placeholder' );
			// Fetch fresh URL if expired.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log(
				sprintf(
					'[NotionImageBlock] Checking URL expiration - empty: %s | expired: %s | url preview: %s',
					empty( $notion_url ) ? 'YES' : 'NO',
					$this->is_url_expired( $notion_url ) ? 'YES' : 'NO',
					substr( $notion_url, 0, 100 )
				)
			);
			if ( empty( $notion_url ) || $this->is_url_expired( $notion_url ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( '[NotionImageBlock] URL expired or empty, fetching fresh URL for unsupported image' );
				$notion_url = $this->get_fresh_notion_url( $block_id );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( '[NotionImageBlock] Fresh URL length: ' . strlen( $notion_url ) );
			}
			$html = $this->render_unsupported_placeholder( $notion_url, $caption, $alt_text );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[NotionImageBlock] Unsupported HTML length: ' . strlen( $html ) );
			return $html;
		}

		if ( $attachment_id ) {
			// Verify attachment still exists in Media Library.
			if ( ! wp_get_attachment_url( $attachment_id ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( '[NotionImageBlock] Attachment deleted, fetching fresh URL' );
				// Attachment deleted - try to get fresh Notion URL.
				$notion_url = $this->get_fresh_notion_url( $block_id );
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( '[NotionImageBlock] Rendering WORDPRESS IMAGE for attachment ' . $attachment_id );
				// Image downloaded and exists - render proper WordPress image block.
				$html = $this->render_wordpress_image( $attachment_id, $caption, $alt_text );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( '[NotionImageBlock] WordPress image HTML length: ' . strlen( $html ) );
				return $html;
			}
		}

		// Image not yet downloaded or attachment missing - fetch fresh URL if needed.
		if ( empty( $notion_url ) || $this->is_url_expired( $notion_url ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[NotionImageBlock] Fetching fresh Notion URL' );
			$notion_url = $this->get_fresh_notion_url( $block_id );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[NotionImageBlock] Fresh URL length: ' . strlen( $notion_url ) );
		}

		// Image not yet downloaded - render placeholder.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( '[NotionImageBlock] Rendering PLACEHOLDER' );
		$html = $this->render_placeholder( $notion_url, $caption, $alt_text );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( '[NotionImageBlock] Placeholder HTML length: ' . strlen( $html ) );
		return $html;
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
		// Fix WordPress JSON encoding artifact: \u0026 becomes u0026.
		// When WordPress stores blocks as JSON, & is encoded as \u0026.
		// When that JSON is stored in the database and re-parsed, the backslash is lost.
		// This leaves literal "u0026" in the string instead of "&".
		// Additional encoding makes this "u0026amp;" (u0026 + &amp;).
		// We need to decode both: u0026 → & and &amp; → &.
		$url = str_replace( 'u0026amp;', '&', $url );
		$url = str_replace( 'u0026', '&', $url );
		$url = html_entity_decode( $url, ENT_QUOTES | ENT_HTML5 );

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
			// Attachment not found - show error for users with proper capabilities only.
			if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' ) ) {
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
	 * Shows image from Notion URL while download processes in background.
	 * Image will automatically switch to Media Library version once downloaded.
	 *
	 * @since 0.4.0
	 *
	 * @param string $notion_url Notion S3 URL (temporary, expires in 1 hour).
	 * @param string $caption    Image caption.
	 * @param string $alt_text   Image alt text.
	 * @return string Rendered HTML.
	 */
	private function render_placeholder( string $notion_url, string $caption, string $alt_text ): string {
		if ( empty( $alt_text ) ) {
			$alt_text = $caption ? $caption : 'Image from Notion';
		}

		// Show image from Notion URL (will be replaced with Media Library version once downloaded).
		if ( ! empty( $notion_url ) ) {
			$caption_html = $caption ? sprintf( '<figcaption class="wp-element-caption">%s</figcaption>', wp_kses_post( $caption ) ) : '';

			return sprintf(
				'<figure class="wp-block-image notion-image-pending" data-notion-status="downloading">
					<img decoding="async" src="%s" alt="%s" class="notion-pending-download" loading="lazy"/>
					%s
				</figure>',
				esc_url( $notion_url ),
				esc_attr( $alt_text ),
				$caption_html
			);
		}

		// No Notion URL - show spinner placeholder.
		$spinner_caption = $caption
			? sprintf( '%s <em>(loading...)</em>', wp_kses_post( $caption ) )
			: '<em>Loading image...</em>';

		return sprintf(
			'<figure class="wp-block-image notion-image-pending" data-notion-status="downloading">
				<div class="notion-image-spinner" style="min-height: 200px; display: flex; align-items: center; justify-content: center; background: #f0f0f1; border: 1px solid #ddd; border-radius: 4px;">
					<span style="font-size: 48px;">⏳</span>
				</div>
				<figcaption class="wp-element-caption">%s</figcaption>
			</figure>',
			$spinner_caption
		);
	}

	/**
	 * Render external/linked image (not uploaded to Media Library).
	 *
	 * Displays image from Notion URL. Used for:
	 * - Formats WordPress Media Library doesn't support (TIFF, etc.) - still display in browser
	 * - External URLs (Unsplash, etc.)
	 *
	 * Note: TIFF and other formats CAN be displayed in browsers, they just can't be
	 * uploaded to WordPress Media Library. We show them using fresh Notion S3 URLs.
	 *
	 * @since 0.4.0
	 *
	 * @param string $notion_url Notion S3 URL (temporary, expires in 1 hour).
	 * @param string $caption    Image caption.
	 * @param string $alt_text   Image alt text.
	 * @return string Rendered HTML.
	 */
	private function render_unsupported_placeholder( string $notion_url, string $caption, string $alt_text ): string {
		if ( empty( $alt_text ) ) {
			$alt_text = $caption ? $caption : 'Image from Notion';
		}

		// Show image from Notion URL.
		if ( ! empty( $notion_url ) ) {
			$caption_html = $caption ? sprintf( '<figcaption class="wp-element-caption">%s</figcaption>', wp_kses_post( $caption ) ) : '';

			return sprintf(
				'<figure class="wp-block-image notion-image-external" data-notion-status="external">
					<img decoding="async" src="%s" alt="%s" class="notion-external-image" loading="lazy"/>
					%s
				</figure>',
				esc_url( $notion_url ),
				esc_attr( $alt_text ),
				$caption_html
			);
		}

		// No Notion URL - show error placeholder.
		$error_caption = $caption
			? sprintf( '%s <em>(image URL unavailable)</em>', wp_kses_post( $caption ) )
			: '<em>Image URL unavailable</em>';

		return sprintf(
			'<figure class="wp-block-image notion-image-error" data-notion-status="error">
				<div class="notion-image-error" style="min-height: 200px; display: flex; align-items: center; justify-content: center; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
					<span style="font-size: 48px;">⚠️</span>
				</div>
				<figcaption class="wp-element-caption">%s</figcaption>
			</figure>',
			$error_caption
		);
	}
}
