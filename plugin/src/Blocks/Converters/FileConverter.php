<?php
/**
 * File Block Converter
 *
 * Converts Notion file blocks (PDFs, documents, etc.) to Gutenberg file blocks.
 *
 * @package NotionSync
 * @since 0.3.0
 */

namespace NotionSync\Blocks\Converters;

use NotionSync\Blocks\BlockConverterInterface;
use NotionSync\Media\FileDownloader;
use NotionSync\Media\MediaUploader;
use NotionSync\Media\MediaRegistry;

/**
 * Converts Notion file blocks to Gutenberg file blocks
 *
 * Handles files like PDFs, Word documents, Excel spreadsheets, etc.
 * Downloads from Notion S3 and uploads to WordPress Media Library.
 *
 * @since 0.3.0
 */
class FileConverter implements BlockConverterInterface {

	/**
	 * File downloader instance.
	 *
	 * @var FileDownloader
	 */
	private FileDownloader $downloader;

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
	 * Constructor.
	 *
	 * @param FileDownloader|null $downloader Optional custom downloader.
	 * @param MediaUploader|null  $uploader   Optional custom uploader.
	 */
	public function __construct( ?FileDownloader $downloader = null, ?MediaUploader $uploader = null ) {
		$this->downloader = $downloader ?? new FileDownloader();
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
	 * Check if this converter supports the given Notion block.
	 *
	 * @param array $notion_block The Notion block data.
	 * @return bool True if block type is 'file' or 'pdf'.
	 */
	public function supports( array $notion_block ): bool {
		$type = $notion_block['type'] ?? '';
		return in_array( $type, [ 'file', 'pdf' ], true );
	}

	/**
	 * Convert Notion file block to Gutenberg file block.
	 *
	 * @param array $notion_block The Notion block data.
	 * @return string Gutenberg file block HTML.
	 */
	public function convert( array $notion_block ): string {
		$block_type = $notion_block['type'] ?? '';
		$file_data  = $notion_block[ $block_type ] ?? [];
		$block_id   = $notion_block['id'] ?? '';

		if ( empty( $file_data ) ) {
			return $this->generate_placeholder( 'File data not found' );
		}

		try {
			// Notion files are always type 'file' (hosted on S3).
			$file_type = $file_data['type'] ?? '';

			if ( 'file' !== $file_type ) {
				return $this->generate_placeholder( 'External files not supported' );
			}

			return $this->handle_notion_file( $file_data, $block_id );

		} catch ( \Exception $e ) {
			return $this->generate_placeholder( 'File conversion failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle Notion-hosted file.
	 *
	 * Downloads to WordPress Media Library with deduplication.
	 *
	 * @param array  $file_data File data from Notion.
	 * @param string $block_id  Notion block ID.
	 * @return string Gutenberg file block HTML.
	 * @throws \Exception If download or upload fails.
	 */
	private function handle_notion_file( array $file_data, string $block_id ): string {
		$notion_url = $file_data['file']['url'] ?? '';
		$file_name  = $file_data['name'] ?? '';

		if ( empty( $notion_url ) ) {
			throw new \Exception( 'Notion file URL not found' );
		}

		// Check MediaRegistry first (deduplication).
		$attachment_id = MediaRegistry::find( $block_id );

		// Check if we need to re-upload (file changed in Notion).
		if ( $attachment_id && MediaRegistry::needs_reupload( $block_id, $notion_url ) ) {
			$attachment_id = null;
		}

		// If not found or needs re-upload, download and upload.
		if ( ! $attachment_id ) {
			$attachment_id = $this->download_and_upload( $notion_url, $block_id, $file_data );
		}

		return $this->generate_wordpress_file_block( $attachment_id, $file_data );
	}

	/**
	 * Download file and upload to Media Library.
	 *
	 * @param string $notion_url Notion S3 URL.
	 * @param string $block_id   Notion block ID.
	 * @param array  $file_data  File data for metadata.
	 * @return int WordPress attachment ID.
	 * @throws \Exception If download or upload fails.
	 */
	private function download_and_upload( string $notion_url, string $block_id, array $file_data ): int {
		// Download from Notion S3.
		$downloaded = $this->downloader->download( $notion_url );

		// Extract metadata from Notion.
		$caption = $this->extract_caption( $file_data );
		$file_name = $file_data['name'] ?? basename( $downloaded['filename'] );

		$metadata = [
			'title'       => $file_name,
			'caption'     => $caption,
			'description' => $caption,
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
	 * Generate Gutenberg file block for WordPress attachment.
	 *
	 * @param int   $attachment_id WordPress attachment ID.
	 * @param array $file_data     File data from Notion.
	 * @return string Gutenberg file block HTML.
	 */
	private function generate_wordpress_file_block( int $attachment_id, array $file_data ): string {
		$file_url = wp_get_attachment_url( $attachment_id );
		if ( ! $file_url ) {
			return $this->generate_placeholder( 'Attachment URL not found' );
		}

		$file_name  = $file_data['name'] ?? get_the_title( $attachment_id );
		$caption    = $this->extract_caption( $file_data );
		$file_size  = size_format( filesize( get_attached_file( $attachment_id ) ) );
		$mime_type  = get_post_mime_type( $attachment_id );

		// Determine if this is a PDF (use PDF embed instead of file block).
		if ( 'application/pdf' === $mime_type ) {
			return $this->generate_pdf_block( $file_url, $file_name );
		}

		// Generate download button text.
		$button_text = sprintf( 'Download %s', esc_html( $file_name ) );
		$caption_html = $caption
			? sprintf( '<figcaption class="wp-element-caption">%s</figcaption>', wp_kses_post( $caption ) )
			: '';

		$block_attrs = sprintf( '<!-- wp:file {"id":%d,"href":"%s"} -->', $attachment_id, esc_url( $file_url ) );
		$block_content = sprintf(
			'<div class="wp-block-file"><a href="%s" class="wp-block-file__button" download>%s</a>%s</div>',
			esc_url( $file_url ),
			$button_text,
			$caption_html
		);

		return sprintf(
			"%s\n%s\n<!-- /wp:file -->\n\n",
			$block_attrs,
			$block_content
		);
	}

	/**
	 * Generate PDF embed block for PDF files.
	 *
	 * @param string $file_url  PDF file URL.
	 * @param string $file_name PDF file name.
	 * @return string Gutenberg PDF embed block HTML.
	 */
	private function generate_pdf_block( string $file_url, string $file_name ): string {
		$block_attrs = sprintf( '<!-- wp:file {"href":"%s"} -->', esc_url( $file_url ) );
		$object_tag = sprintf(
			'<object class="wp-block-file__embed" data="%s" type="application/pdf" style="width:100%%;height:600px" aria-label="%s"></object>',
			esc_url( $file_url ),
			esc_attr( $file_name )
		);
		$download_link = sprintf(
			'<a href="%s" class="wp-block-file__button" download aria-label="Download PDF">Download</a>',
			esc_url( $file_url )
		);

		return sprintf(
			"%s\n<div class=\"wp-block-file\">%s%s</div>\n<!-- /wp:file -->\n\n",
			$block_attrs,
			$object_tag,
			$download_link
		);
	}

	/**
	 * Extract caption from Notion file data.
	 *
	 * @param array $file_data File data from Notion.
	 * @return string Caption text.
	 */
	private function extract_caption( array $file_data ): string {
		$caption_array = $file_data['caption'] ?? [];

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
		return sprintf( "<!-- File conversion failed: %s -->\n\n", esc_html( $message ) );
	}
}
