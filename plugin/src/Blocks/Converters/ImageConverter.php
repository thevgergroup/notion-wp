<?php
/**
 * Image Block Converter
 *
 * Converts Notion image blocks to Gutenberg image blocks.
 * Handles both Notion-hosted files and external URLs (Unsplash, Giphy).
 *
 * @package NotionSync
 * @since 0.3.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;
use NotionSync\Media\ImageDownloader;
use NotionSync\Media\MediaUploader;
use NotionSync\Media\MediaRegistry;

/**
 * Converts Notion image blocks to Gutenberg images
 *
 * Strategy:
 * - Notion S3 URLs: Download to Media Library (URLs expire in 1 hour)
 * - External URLs (Unsplash, Giphy): Link directly (legal/CDN)
 * - Uses MediaRegistry to prevent duplicate uploads
 *
 * @since 0.3.0
 */
class ImageConverter implements BlockConverterInterface {

	/**
	 * Image downloader instance.
	 *
	 * @var ImageDownloader
	 */
	private ImageDownloader $downloader;

	/**
	 * Media uploader instance.
	 *
	 * @var MediaUploader
	 */
	private MediaUploader $uploader;

	/**
	 * Parent post ID for attaching media.
	 *
	 * @var int|null
	 */
	private ?int $parent_post_id = null;

	/**
	 * Notion page ID for logging.
	 *
	 * @var string|null
	 */
	private ?string $notion_page_id = null;

	/**
	 * Constructor.
	 *
	 * @param ImageDownloader|null $downloader Optional custom downloader.
	 * @param MediaUploader|null   $uploader   Optional custom uploader.
	 */
	public function __construct( ?ImageDownloader $downloader = null, ?MediaUploader $uploader = null ) {
		$this->downloader = $downloader ?? new ImageDownloader();
		$this->uploader   = $uploader ?? new MediaUploader();
	}

	/**
	 * Set parent post ID for media attachment.
	 *
	 * @param int|null $post_id Parent post ID.
	 * @return void
	 */
	public function set_parent_post_id( ?int $post_id ): void {
		$this->parent_post_id = $post_id;
	}

	/**
	 * Set Notion page ID for logging.
	 *
	 * @param string|null $page_id Notion page ID.
	 * @return void
	 */
	public function set_notion_page_id( ?string $page_id ): void {
		$this->notion_page_id = $page_id;
	}

	/**
	 * Check if this converter supports the given Notion block.
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if block type is 'image'.
	 */
	public function supports( array $notion_block ): bool {
		return isset( $notion_block['type'] ) && 'image' === $notion_block['type'];
	}

	/**
	 * Convert Notion image block to Gutenberg image block.
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string Gutenberg image block HTML.
	 */
	public function convert( array $notion_block ): string {
		$image_data = $notion_block['image'] ?? [];
		$block_id   = $notion_block['id'] ?? '';

		if ( empty( $image_data ) ) {
			return $this->generate_placeholder( 'Image data not found' );
		}

		try {
			// Check type: 'external' or 'file'.
			$image_type = $image_data['type'] ?? '';

			if ( 'external' === $image_type ) {
				return $this->handle_external_image( $image_data, $block_id );
			}

			if ( 'file' === $image_type ) {
				return $this->handle_notion_file( $image_data, $block_id );
			}

			return $this->generate_placeholder( 'Unknown image type: ' . $image_type );

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'ImageConverter: Failed to convert image block %s: %s',
					$block_id,
					$e->getMessage()
				)
			);
			return $this->generate_placeholder( 'Image conversion failed' );
		}
	}

	/**
	 * Handle external image (Unsplash, Giphy, etc.).
	 *
	 * Links to external URL without downloading.
	 *
	 * @param array  $image_data Image data from Notion.
	 * @param string $block_id   Notion block ID.
	 * @return string Gutenberg image block HTML.
	 */
	private function handle_external_image( array $image_data, string $block_id ): string {
		$external_url = $image_data['external']['url'] ?? '';

		if ( empty( $external_url ) ) {
			return $this->generate_placeholder( 'External URL not found' );
		}

		// Extract caption.
		$caption = $this->extract_caption( $image_data );
		$alt     = $caption ? $caption : 'External image';

		return $this->generate_external_image_block( $external_url, $alt, $caption );
	}

	/**
	 * Handle Notion-hosted file.
	 *
	 * Queues image for background download or returns existing attachment.
	 *
	 * @param array  $image_data Image data from Notion.
	 * @param string $block_id   Notion block ID.
	 * @return string Gutenberg image block HTML.
	 * @throws \Exception If queueing fails.
	 */
	private function handle_notion_file( array $image_data, string $block_id ): string {
		$notion_url = $image_data['file']['url'] ?? '';

		if ( empty( $notion_url ) ) {
			throw new \Exception( 'Notion file URL not found' );
		}

		// Check MediaRegistry first (deduplication).
		$attachment_id = MediaRegistry::find( $block_id );

		// Check if we need to re-upload (image changed in Notion).
		if ( $attachment_id && MediaRegistry::needs_reupload( $block_id, $notion_url ) ) {
			error_log( "ImageConverter: Image changed in Notion, queueing re-upload for block {$block_id}" );
			// Delete old registry entry to prevent race condition where another process
			// finds the stale attachment before we queue the new download.
			MediaRegistry::delete( $block_id );
			$attachment_id = null;
		}

		// If image not yet downloaded, queue it for background processing.
		if ( ! $attachment_id ) {
			// Queue image for background download.
			$this->queue_image_download( $block_id, $notion_url, $image_data );

			// Return placeholder that will be replaced after background processing.
			return $this->generate_pending_image_placeholder( $block_id, $notion_url, $image_data );
		}

		return $this->generate_wordpress_image_block( $attachment_id, $image_data );
	}

	/**
	 * Download image and upload to Media Library.
	 *
	 * @param string $notion_url  Notion S3 URL.
	 * @param string $block_id    Notion block ID.
	 * @param array  $image_data  Image data for metadata.
	 * @return int|string WordPress attachment ID, or URL string if unsupported type.
	 * @throws \Exception If download or upload fails.
	 */
	private function download_and_upload( string $notion_url, string $block_id, array $image_data ) {
		// Download from Notion S3 (with logging context).
		$downloaded = $this->downloader->download(
			$notion_url,
			[
				'notion_page_id' => $this->notion_page_id,
				'wp_post_id'     => $this->parent_post_id,
			]
		);

		// Check if image type is unsupported (e.g., TIFF).
		if ( ! empty( $downloaded['unsupported'] ) ) {
			// Return the linked URL instead of attachment ID.
			return $downloaded['linked_url'];
		}

		// Extract metadata from Notion.
		$caption = $this->extract_caption( $image_data );
		$metadata = [
			'alt_text' => $caption ? $caption : 'Image from Notion',
			'caption'  => $caption,
		];

		// Upload to WordPress Media Library.
		$attachment_id = $this->uploader->upload(
			$downloaded['file_path'],
			$metadata,
			$this->parent_post_id
		);

		// Register in MediaRegistry.
		MediaRegistry::register( $block_id, $attachment_id, $notion_url );

		return $attachment_id;
	}

	/**
	 * Generate Gutenberg image block for WordPress attachment.
	 *
	 * @param int   $attachment_id WordPress attachment ID.
	 * @param array $image_data    Image data for caption.
	 * @return string Gutenberg image block HTML.
	 */
	private function generate_wordpress_image_block( int $attachment_id, array $image_data ): string {
		$image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $image_url ) {
			return $this->generate_placeholder( 'Attachment URL not found' );
		}

		$alt     = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$alt     = $alt ? $alt : '';
		$caption = $this->extract_caption( $image_data );

		$caption_html = $caption ? sprintf( '<figcaption class="wp-element-caption">%s</figcaption>', wp_kses_post( $caption ) ) : '';

		// Note: Do NOT add inline width/height attributes - Gutenberg handles sizing through
		// the sizeSlug attribute and WordPress automatically adds responsive image attributes.
		$block_attrs = sprintf( '<!-- wp:image {"id":%d,"sizeSlug":"large"} -->', $attachment_id );
		$figure_start = '<figure class="wp-block-image size-large">';
		$img_tag = sprintf(
			'<img src="%s" alt="%s" class="wp-image-%d"/>',
			esc_url( $image_url ),
			esc_attr( $alt ),
			$attachment_id
		);
		$figure_end = '</figure>';
		$block_end = '<!-- /wp:image -->';

		return sprintf(
			"%s\n%s%s%s%s\n%s\n\n",
			$block_attrs,
			$figure_start,
			$img_tag,
			$caption_html,
			$figure_end,
			$block_end
		);
	}

	/**
	 * Generate Gutenberg image block for external URL.
	 *
	 * Uses HTML block to avoid validation errors (external images have no attachment ID).
	 *
	 * @param string $url     External image URL.
	 * @param string $alt     Alt text.
	 * @param string $caption Caption text.
	 * @return string Gutenberg HTML block.
	 */
	private function generate_external_image_block( string $url, string $alt, string $caption ): string {
		$caption_html = $caption ? sprintf( '<figcaption class="wp-element-caption">%s</figcaption>', wp_kses_post( $caption ) ) : '';

		$html = sprintf(
			'<figure class="wp-block-image"><img src="%s" alt="%s" class="external-image"/>%s</figure>',
			esc_url( $url ),
			esc_attr( $alt ),
			$caption_html
		);

		// Use HTML block to avoid Gutenberg validation errors for images without attachment IDs.
		return sprintf(
			"<!-- wp:html -->\n%s\n<!-- /wp:html -->\n\n",
			$html
		);
	}

	/**
	 * Extract caption from Notion image data.
	 *
	 * @param array $image_data Image data from Notion.
	 * @return string Caption text.
	 */
	private function extract_caption( array $image_data ): string {
		$caption_array = $image_data['caption'] ?? [];

		if ( empty( $caption_array ) ) {
			return '';
		}

		$caption_text = '';
		foreach ( $caption_array as $text_item ) {
			$caption_text .= $text_item['plain_text'] ?? '';
		}

		return trim( $caption_text );
	}

	/**
	 * Queue image for background download.
	 *
	 * @param string $block_id    Notion block ID.
	 * @param string $notion_url  Notion S3 URL.
	 * @param array  $image_data  Image data for metadata.
	 * @return void
	 */
	private function queue_image_download( string $block_id, string $notion_url, array $image_data ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			error_log( 'ImageConverter: Action Scheduler not available, cannot queue image download' );
			return;
		}

		// Prevent race condition: Check if download already queued for this block.
		// We check only the first arg (block_id) to detect duplicates regardless of other params.
		if ( function_exists( 'as_get_scheduled_actions' ) && function_exists( 'as_get_scheduled_action' ) ) {
			$pending_actions = as_get_scheduled_actions(
				[
					'hook'   => 'notion_sync_download_image',
					'group'  => 'notion-sync-media',
					'status' => 'pending',
				],
				'ids'
			);

			foreach ( $pending_actions as $action_id ) {
				$action = as_get_scheduled_action( $action_id );
				if ( $action && isset( $action->get_args()[0] ) && $action->get_args()[0] === $block_id ) {
					error_log( "ImageConverter: Download already queued for block {$block_id}, skipping duplicate" );
					return;
				}
			}
		}

		// Extract caption for metadata.
		$caption = $this->extract_caption( $image_data );

		// Ensure Action Scheduler is available before scheduling.
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			error_log( "ImageConverter: Action Scheduler not available, cannot schedule image download for block {$block_id}" );
			return;
		}

		// Schedule background download task.
		// Pass parameters as separate args (not array) - Action Scheduler unpacks them.
		as_schedule_single_action(
			time(),
			'notion_sync_download_image',
			[
				$block_id,
				$notion_url,
				$this->notion_page_id ?? '',
				$this->parent_post_id ?? 0,
				$caption,
			],
			'notion-sync-media'
		);

		error_log(
			sprintf(
				'ImageConverter: Queued image download for block %s (post %d, page %s)',
				$block_id,
				$this->parent_post_id ?? 0,
				$this->notion_page_id ?? 'unknown'
			)
		);
	}

	/**
	 * Generate pending image placeholder using dynamic block.
	 *
	 * Creates a notion-sync/notion-image dynamic block that checks MediaRegistry
	 * at render time and automatically shows the image once downloaded.
	 *
	 * Benefits over static HTML:
	 * - No post content updates needed
	 * - Self-healing: automatically shows image once available
	 * - Single source of truth: MediaRegistry
	 *
	 * @param string $block_id    Notion block ID.
	 * @param string $notion_url  Notion S3 URL (for fallback display).
	 * @param array  $image_data  Image data for caption.
	 * @return string Gutenberg dynamic block.
	 */
	private function generate_pending_image_placeholder( string $block_id, string $notion_url, array $image_data ): string {
		$caption  = $this->extract_caption( $image_data );
		$alt_text = $caption ? $caption : 'Image from Notion';

		// Generate dynamic block attributes as JSON.
		$attributes = array(
			'blockId'   => $block_id,
			'notionUrl' => $notion_url,
			'caption'   => $caption,
			'altText'   => $alt_text,
		);

		// Encode attributes for block comment.
		$attributes_json = wp_json_encode( $attributes );

		// Return dynamic block that will check MediaRegistry at render time.
		// NOTE: Dynamic blocks with render_callback should have empty content.
		// The render_callback generates all HTML at render time.
		return sprintf(
			"<!-- wp:notion-sync/notion-image %s /-->\n\n",
			$attributes_json
		);
	}

	/**
	 * Generate placeholder comment for failed conversions.
	 *
	 * @param string $message Error message.
	 * @return string HTML comment placeholder.
	 */
	private function generate_placeholder( string $message ): string {
		return sprintf( "<!-- Image conversion failed: %s -->\n\n", esc_html( $message ) );
	}
}
