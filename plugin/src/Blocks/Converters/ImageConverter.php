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
		$alt     = $caption ?: 'External image';

		return $this->generate_external_image_block( $external_url, $alt, $caption );
	}

	/**
	 * Handle Notion-hosted file.
	 *
	 * Downloads to WordPress Media Library with deduplication.
	 *
	 * @param array  $image_data Image data from Notion.
	 * @param string $block_id   Notion block ID.
	 * @return string Gutenberg image block HTML.
	 * @throws \Exception If download or upload fails.
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
			error_log( "ImageConverter: Image changed in Notion, re-uploading block {$block_id}" );
			$attachment_id = null;
		}

		// If not found or needs re-upload, download and upload.
		if ( ! $attachment_id ) {
			$result = $this->download_and_upload( $notion_url, $block_id, $image_data );

			// Check if result is a URL (unsupported type) or attachment ID.
			if ( is_string( $result ) ) {
				// Unsupported type - link to original URL.
				$caption = $this->extract_caption( $image_data );
				$alt     = $caption ?: 'Unsupported image type';
				return $this->generate_external_image_block( $result, $alt, $caption );
			}

			$attachment_id = $result;
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
			'alt_text' => $caption ?: 'Image from Notion',
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

		$alt     = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
		$caption = $this->extract_caption( $image_data );

		$caption_html = $caption ? sprintf( '<figcaption class="wp-element-caption">%s</figcaption>', wp_kses_post( $caption ) ) : '';

		// Note: Do NOT add inline width/height attributes - Gutenberg handles sizing through
		// the sizeSlug attribute and WordPress automatically adds responsive image attributes.
		return sprintf(
			"<!-- wp:image {\"id\":%d,\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"%s\" alt=\"%s\" class=\"wp-image-%d\"/>%s</figure>\n<!-- /wp:image -->\n\n",
			$attachment_id,
			esc_url( $image_url ),
			esc_attr( $alt ),
			$attachment_id,
			$caption_html
		);
	}

	/**
	 * Generate Gutenberg image block for external URL.
	 *
	 * @param string $url     External image URL.
	 * @param string $alt     Alt text.
	 * @param string $caption Caption text.
	 * @return string Gutenberg image block HTML.
	 */
	private function generate_external_image_block( string $url, string $alt, string $caption ): string {
		$caption_html = $caption ? sprintf( '<figcaption class="wp-element-caption">%s</figcaption>', wp_kses_post( $caption ) ) : '';

		return sprintf(
			"<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"%s\" alt=\"%s\" class=\"external-image\"/>%s</figure>\n<!-- /wp:image -->\n\n",
			esc_url( $url ),
			esc_attr( $alt ),
			$caption_html
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
	 * Generate placeholder comment for failed conversions.
	 *
	 * @param string $message Error message.
	 * @return string HTML comment placeholder.
	 */
	private function generate_placeholder( string $message ): string {
		return sprintf( "<!-- Image conversion failed: %s -->\n\n", esc_html( $message ) );
	}
}
