# Phase 3: Media Handling & WordPress Media Library Integration

**Status:** 📋 Ready to Start
**Duration:** 1 week (estimated)
**Complexity:** M (Medium)
**Version Target:** v0.4-dev

## 🎯 Goal

Download images and files from Notion's S3 URLs, upload them to WordPress Media Library with proper metadata, and handle deduplication on re-sync.

**User Story:** "As a WordPress admin, when I sync a Notion page with images, all images automatically download to my Media Library with alt text and captions preserved, and re-syncing doesn't create duplicates."

## 🧠 Architecture Decision

**Media Flow:**

- **Notion Image Block** → Download from S3 → Upload to WP Media Library → Insert in post content
- **Time-Limited URLs** → Download immediately during sync (Notion URLs expire after 1 hour)
- **Deduplication** → Track by Notion block ID to prevent duplicate uploads
- **Background Processing** → Use Action Scheduler for pages with 10+ images

**Why WordPress Media Library?**

- Native WordPress integration (themes, plugins, responsive images)
- Automatic thumbnail generation
- Built-in editing capabilities
- CDN compatibility
- SEO-friendly (image sitemap inclusion)

**Supported File Types:**

- **Images:** PNG, JPEG, GIF, WebP, SVG
- **Documents:** PDF
- **Files:** Notion file attachments (stored, linked but not embedded)

## ✅ Success Criteria (Gatekeeping)

**DO NOT PROCEED to Phase 4 until ALL criteria are met:**

### Core Functionality

- [ ] Image blocks from Notion import to Media Library
- [ ] Images display correctly in synced posts
- [ ] Alt text preserved (if available in Notion)
- [ ] Captions preserved (if available in Notion)
- [ ] Re-sync doesn't create duplicate images
- [ ] Handles 20+ images in single page without timeout
- [ ] PDF file blocks import and link correctly
- [ ] Broken/expired URLs handled gracefully

### Performance Requirements

- [ ] Page with 10 images syncs in under 2 minutes
- [ ] Page with 50 images syncs without timeout (background processing)
- [ ] No memory limit errors on image-heavy pages
- [ ] Failed image downloads don't block entire sync

### Quality Requirements

- [ ] Zero PHP warnings during media operations
- [ ] All linting passes (PHPCS, ESLint, PHPStan level 3+)
- [ ] Zero console errors
- [ ] Image URLs are properly escaped
- [ ] **Can be demoed to a non-developer in under 5 minutes**

## 📋 Dependencies

**Required from Phase 1 (COMPLETED ✅):**

- ✅ NotionClient API wrapper
- ✅ BlockConverter system
- ✅ SyncManager orchestration
- ✅ Post meta storage for Notion IDs

**Optional from Phase 2:**

- Action Scheduler (for background processing of many images)

## 🔀 Parallel Work Streams

### Stream 1: Image Download System

**Worktree:** `phase-3-media-handling`
**Duration:** 2 days
**Files Created:** 2 new files, all <400 lines

**What This Builds:**

- Download images from Notion's time-limited S3 URLs
- Handle various image formats
- Retry failed downloads
- Validate downloaded files

**Technical Implementation:**

**File 1:** `plugin/src/Media/ImageDownloader.php` (<350 lines)

```php
<?php
namespace NotionSync\Media;

/**
 * Downloads images from Notion S3 URLs.
 *
 * Handles time-limited URLs, retries, and file validation.
 */
class ImageDownloader {

    private const MAX_RETRIES = 3;
    private const TIMEOUT_SECONDS = 30;
    private const MAX_FILE_SIZE = 10485760; // 10MB

    /**
     * Download image from URL.
     *
     * @param string $url          Image URL (Notion S3).
     * @param array  $options      Optional download options.
     * @return array {
     *     Download result.
     *
     *     @type string $file_path  Temporary file path.
     *     @type string $filename   Original filename.
     *     @type string $mime_type  MIME type.
     *     @type int    $file_size  File size in bytes.
     * }
     * @throws \Exception If download fails.
     */
    public function download( string $url, array $options = [] ): array {
        $retry_count = 0;
        $last_error = null;

        while ( $retry_count < self::MAX_RETRIES ) {
            try {
                return $this->attempt_download( $url, $options );
            } catch ( \Exception $e ) {
                $last_error = $e;
                $retry_count++;

                if ( $retry_count < self::MAX_RETRIES ) {
                    // Exponential backoff: 1s, 2s, 4s
                    sleep( pow( 2, $retry_count - 1 ) );
                }
            }
        }

        throw new \Exception(
            sprintf(
                'Failed to download image after %d attempts: %s',
                self::MAX_RETRIES,
                $last_error->getMessage()
            )
        );
    }

    /**
     * Attempt a single download.
     *
     * @param string $url     Image URL.
     * @param array  $options Download options.
     * @return array Download result.
     * @throws \Exception If download fails.
     */
    private function attempt_download( string $url, array $options ): array {
        // Validate URL
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            throw new \Exception( 'Invalid URL provided' );
        }

        // Download using wp_remote_get
        $response = wp_remote_get( $url, [
            'timeout' => self::TIMEOUT_SECONDS,
            'stream' => false,
            'sslverify' => true,
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \Exception( $response->get_error_message() );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            throw new \Exception(
                sprintf( 'HTTP %d: %s', $response_code, $url )
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $body_size = strlen( $body );

        if ( empty( $body ) ) {
            throw new \Exception( 'Empty response body' );
        }

        if ( $body_size > self::MAX_FILE_SIZE ) {
            throw new \Exception(
                sprintf(
                    'File too large: %s (max %s)',
                    size_format( $body_size ),
                    size_format( self::MAX_FILE_SIZE )
                )
            );
        }

        // Determine filename
        $filename = $this->extract_filename( $url, $response );

        // Determine MIME type
        $mime_type = $this->detect_mime_type( $body, $filename );

        // Validate it's actually an image
        if ( ! $this->is_valid_image_type( $mime_type ) ) {
            throw new \Exception(
                sprintf( 'Invalid image type: %s', $mime_type )
            );
        }

        // Save to temp file
        $temp_file = $this->save_to_temp_file( $body, $filename );

        return [
            'file_path' => $temp_file,
            'filename'  => $filename,
            'mime_type' => $mime_type,
            'file_size' => $body_size,
        ];
    }

    /**
     * Extract filename from URL or headers.
     *
     * @param string $url      Image URL.
     * @param array  $response HTTP response.
     * @return string Filename.
     */
    private function extract_filename( string $url, array $response ): string {
        // Try Content-Disposition header first
        $headers = wp_remote_retrieve_headers( $response );
        if ( isset( $headers['content-disposition'] ) ) {
            if ( preg_match( '/filename="([^"]+)"/', $headers['content-disposition'], $matches ) ) {
                return sanitize_file_name( $matches[1] );
            }
        }

        // Extract from URL
        $path = wp_parse_url( $url, PHP_URL_PATH );
        $filename = basename( $path );

        // Clean query parameters
        $filename = preg_replace( '/\?.*$/', '', $filename );

        // Ensure file extension
        if ( ! preg_match( '/\.[a-z]{3,4}$/i', $filename ) ) {
            $filename .= '.png'; // Default extension
        }

        return sanitize_file_name( $filename );
    }

    /**
     * Detect MIME type from file content.
     *
     * @param string $content  File content.
     * @param string $filename Filename hint.
     * @return string MIME type.
     */
    private function detect_mime_type( string $content, string $filename ): string {
        // Use WordPress built-in MIME detection
        $temp_file = wp_tempnam( $filename );
        file_put_contents( $temp_file, $content );

        $filetype = wp_check_filetype( $temp_file, null );
        unlink( $temp_file );

        return $filetype['type'] ?? 'application/octet-stream';
    }

    /**
     * Check if MIME type is valid image.
     *
     * @param string $mime_type MIME type.
     * @return bool True if valid image type.
     */
    private function is_valid_image_type( string $mime_type ): bool {
        $allowed_types = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
        ];

        return in_array( $mime_type, $allowed_types, true );
    }

    /**
     * Save content to temporary file.
     *
     * @param string $content  File content.
     * @param string $filename Filename.
     * @return string Temporary file path.
     * @throws \Exception If save fails.
     */
    private function save_to_temp_file( string $content, string $filename ): string {
        $temp_file = wp_tempnam( $filename );

        $bytes_written = file_put_contents( $temp_file, $content );

        if ( false === $bytes_written ) {
            throw new \Exception( 'Failed to write temporary file' );
        }

        return $temp_file;
    }

    /**
     * Clean up temporary file.
     *
     * @param string $file_path File path.
     * @return bool Success status.
     */
    public function cleanup( string $file_path ): bool {
        if ( file_exists( $file_path ) ) {
            return unlink( $file_path );
        }
        return true;
    }
}
```

**File 2:** `plugin/src/Media/FileDownloader.php` (<250 lines)

Similar to ImageDownloader but supports PDFs and other file types:

```php
<?php
namespace NotionSync\Media;

/**
 * Downloads files (PDFs, documents) from Notion.
 *
 * Extends image download logic to support document files.
 */
class FileDownloader extends ImageDownloader {

    private const MAX_FILE_SIZE = 52428800; // 50MB for files

    /**
     * Check if MIME type is valid file.
     *
     * @param string $mime_type MIME type.
     * @return bool True if valid file type.
     */
    protected function is_valid_file_type( string $mime_type ): bool {
        $allowed_types = [
            // Images
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            // Archives
            'application/zip',
        ];

        return in_array( $mime_type, $allowed_types, true );
    }
}
```

**Tasks:**

1. Create ImageDownloader class
2. Implement download with retry logic
3. Add exponential backoff for retries
4. Implement MIME type detection
5. Add file size validation
6. Create FileDownloader for PDFs
7. Write unit tests for download logic
8. Test with various image formats

**Definition of Done:**

- [ ] Can download PNG, JPEG, GIF, WebP images
- [ ] Retries 3 times with exponential backoff
- [ ] Validates MIME types correctly
- [ ] Rejects files >10MB (images) or >50MB (files)
- [ ] Handles expired URLs gracefully
- [ ] Cleans up temporary files
- [ ] Unit tests pass

---

### Stream 2: WordPress Media Library Integration

**Worktree:** `phase-3-media-handling`
**Duration:** 2-3 days
**Files Created:** 2 new files, all <400 lines

**What This Builds:**

- Upload downloaded images to Media Library
- Set attachment metadata (alt text, caption, description)
- Generate thumbnails
- Track uploaded media

**Technical Implementation:**

**File 1:** `plugin/src/Media/MediaUploader.php` (<400 lines)

```php
<?php
namespace NotionSync\Media;

/**
 * Uploads media to WordPress Media Library.
 *
 * Handles attachment creation, metadata, and thumbnail generation.
 */
class MediaUploader {

    /**
     * Upload file to Media Library.
     *
     * @param string $file_path Temporary file path.
     * @param array  $metadata  {
     *     Optional metadata.
     *
     *     @type string $title       Image title.
     *     @type string $alt_text    Alt text.
     *     @type string $caption     Caption.
     *     @type string $description Description.
     * }
     * @param int    $parent_post_id Optional parent post ID.
     * @return int Attachment ID.
     * @throws \Exception If upload fails.
     */
    public function upload(
        string $file_path,
        array $metadata = [],
        int $parent_post_id = 0
    ): int {
        if ( ! file_exists( $file_path ) ) {
            throw new \Exception( 'File does not exist: ' . $file_path );
        }

        // Prepare file array for WordPress
        $file = [
            'name'     => basename( $file_path ),
            'type'     => mime_content_type( $file_path ),
            'tmp_name' => $file_path,
            'error'    => 0,
            'size'     => filesize( $file_path ),
        ];

        // WordPress media upload
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Handle the upload
        $upload = wp_handle_sideload(
            $file,
            [
                'test_form' => false,
                'test_type' => true,
            ]
        );

        if ( isset( $upload['error'] ) ) {
            throw new \Exception( 'Upload failed: ' . $upload['error'] );
        }

        // Prepare attachment data
        $attachment_data = [
            'post_mime_type' => $upload['type'],
            'post_title'     => $metadata['title'] ?? sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
            'post_content'   => $metadata['description'] ?? '',
            'post_excerpt'   => $metadata['caption'] ?? '',
            'post_status'    => 'inherit',
            'post_parent'    => $parent_post_id,
        ];

        // Insert attachment
        $attachment_id = wp_insert_attachment( $attachment_data, $upload['file'], $parent_post_id );

        if ( is_wp_error( $attachment_id ) ) {
            throw new \Exception( 'Failed to create attachment: ' . $attachment_id->get_error_message() );
        }

        // Generate attachment metadata (thumbnails, etc.)
        $attach_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
        wp_update_attachment_metadata( $attachment_id, $attach_data );

        // Set alt text
        if ( ! empty( $metadata['alt_text'] ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $metadata['alt_text'] ) );
        }

        return $attachment_id;
    }

    /**
     * Update attachment metadata.
     *
     * @param int   $attachment_id Attachment ID.
     * @param array $metadata      Metadata to update.
     * @return bool Success status.
     */
    public function update_metadata( int $attachment_id, array $metadata ): bool {
        $updated = false;

        if ( isset( $metadata['title'] ) ) {
            wp_update_post( [
                'ID'         => $attachment_id,
                'post_title' => sanitize_text_field( $metadata['title'] ),
            ] );
            $updated = true;
        }

        if ( isset( $metadata['caption'] ) ) {
            wp_update_post( [
                'ID'           => $attachment_id,
                'post_excerpt' => sanitize_text_field( $metadata['caption'] ),
            ] );
            $updated = true;
        }

        if ( isset( $metadata['description'] ) ) {
            wp_update_post( [
                'ID'           => $attachment_id,
                'post_content' => wp_kses_post( $metadata['description'] ),
            ] );
            $updated = true;
        }

        if ( isset( $metadata['alt_text'] ) ) {
            update_post_meta(
                $attachment_id,
                '_wp_attachment_image_alt',
                sanitize_text_field( $metadata['alt_text'] )
            );
            $updated = true;
        }

        return $updated;
    }

    /**
     * Get attachment URL.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $size          Image size.
     * @return string|false Attachment URL or false.
     */
    public function get_attachment_url( int $attachment_id, string $size = 'full' ) {
        return wp_get_attachment_image_url( $attachment_id, $size );
    }

    /**
     * Delete attachment.
     *
     * @param int  $attachment_id Attachment ID.
     * @param bool $force_delete  Bypass trash.
     * @return bool Success status.
     */
    public function delete_attachment( int $attachment_id, bool $force_delete = false ): bool {
        $result = wp_delete_attachment( $attachment_id, $force_delete );
        return ! empty( $result );
    }
}
```

**File 2:** `plugin/src/Media/MediaTracker.php` (<300 lines)

```php
<?php
namespace NotionSync\Media;

/**
 * Tracks media uploads to prevent duplicates.
 *
 * Uses Notion block IDs to identify already-uploaded media.
 */
class MediaTracker {

    private const META_KEY_NOTION_BLOCK_ID = 'notion_block_id';
    private const META_KEY_NOTION_FILE_URL = 'notion_file_url';

    /**
     * Find existing attachment by Notion block ID.
     *
     * @param string $notion_block_id Notion block ID.
     * @return int|null Attachment ID or null if not found.
     */
    public function find_by_block_id( string $notion_block_id ): ?int {
        $attachments = get_posts( [
            'post_type'      => 'attachment',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => self::META_KEY_NOTION_BLOCK_ID,
                    'value' => $notion_block_id,
                ],
            ],
        ] );

        return ! empty( $attachments ) ? (int) $attachments[0] : null;
    }

    /**
     * Track uploaded attachment.
     *
     * @param int    $attachment_id   Attachment ID.
     * @param string $notion_block_id Notion block ID.
     * @param string $notion_file_url Original Notion file URL.
     * @return bool Success status.
     */
    public function track_attachment(
        int $attachment_id,
        string $notion_block_id,
        string $notion_file_url
    ): bool {
        update_post_meta( $attachment_id, self::META_KEY_NOTION_BLOCK_ID, $notion_block_id );
        update_post_meta( $attachment_id, self::META_KEY_NOTION_FILE_URL, $notion_file_url );
        update_post_meta( $attachment_id, 'notion_synced_at', current_time( 'mysql' ) );

        return true;
    }

    /**
     * Check if attachment needs re-upload.
     *
     * Compares file URL to detect if Notion file changed.
     *
     * @param int    $attachment_id   Attachment ID.
     * @param string $notion_file_url Current Notion file URL.
     * @return bool True if needs re-upload.
     */
    public function needs_reupload( int $attachment_id, string $notion_file_url ): bool {
        $stored_url = get_post_meta( $attachment_id, self::META_KEY_NOTION_FILE_URL, true );

        // If URL changed, file might have been replaced in Notion
        return $stored_url !== $notion_file_url;
    }

    /**
     * Get all attachments for a Notion page.
     *
     * @param string $notion_page_id Notion page ID.
     * @return array Array of attachment IDs.
     */
    public function get_page_attachments( string $notion_page_id ): array {
        $post_id = $this->find_post_by_notion_id( $notion_page_id );

        if ( ! $post_id ) {
            return [];
        }

        return get_children( [
            'post_parent'    => $post_id,
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );
    }

    /**
     * Find WordPress post by Notion page ID.
     *
     * @param string $notion_page_id Notion page ID.
     * @return int|null Post ID or null.
     */
    private function find_post_by_notion_id( string $notion_page_id ): ?int {
        $posts = get_posts( [
            'post_type'      => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => 'notion_page_id',
                    'value' => $notion_page_id,
                ],
            ],
        ] );

        return ! empty( $posts ) ? (int) $posts[0] : null;
    }
}
```

**Tasks:**

1. Create MediaUploader class
2. Implement upload to Media Library
3. Add metadata handling (alt text, caption)
4. Ensure thumbnail generation works
5. Create MediaTracker for deduplication
6. Implement block ID tracking
7. Add tests for upload process
8. Test metadata preservation

**Definition of Done:**

- [ ] Can upload images to Media Library
- [ ] Thumbnails generate correctly
- [ ] Alt text and captions preserve
- [ ] Deduplication works (tracks by block ID)
- [ ] Can find existing attachments
- [ ] Parent post relationship works
- [ ] Tests pass

---

### Stream 3: Block Converter Integration

**Worktree:** `phase-3-media-handling`
**Duration:** 1-2 days
**Files Modified:** 2 existing files, 1 new converter

**What This Builds:**

- Image block converter
- File block converter
- Integration with existing BlockConverter
- Proper Gutenberg image block output

**Technical Implementation:**

**File 1:** `plugin/src/Blocks/Converters/ImageConverter.php` (<300 lines)

```php
<?php
namespace NotionSync\Blocks\Converters;

use NotionSync\Media\ImageDownloader;
use NotionSync\Media\MediaUploader;
use NotionSync\Media\MediaTracker;

/**
 * Converts Notion image blocks to WordPress image blocks.
 */
class ImageConverter implements ConverterInterface {

    private ImageDownloader $downloader;
    private MediaUploader $uploader;
    private MediaTracker $tracker;

    public function __construct(
        ImageDownloader $downloader,
        MediaUploader $uploader,
        MediaTracker $tracker
    ) {
        $this->downloader = $downloader;
        $this->uploader = $uploader;
        $this->tracker = $tracker;
    }

    /**
     * Convert Notion image block to WordPress.
     *
     * @param array $block       Notion block data.
     * @param int   $parent_post_id Parent post ID.
     * @return string Gutenberg block HTML.
     */
    public function convert( array $block, int $parent_post_id = 0 ): string {
        $block_id = $block['id'] ?? '';
        $image_data = $block['image'] ?? [];

        // Get image URL (external or file)
        $image_url = $image_data['file']['url'] ?? $image_data['external']['url'] ?? '';

        if ( empty( $image_url ) ) {
            return '<!-- Image block: No URL found -->';
        }

        // Check if already uploaded
        $attachment_id = $this->tracker->find_by_block_id( $block_id );

        if ( $attachment_id && ! $this->tracker->needs_reupload( $attachment_id, $image_url ) ) {
            // Use existing attachment
            return $this->generate_image_block( $attachment_id, $image_data );
        }

        try {
            // Download image
            $downloaded = $this->downloader->download( $image_url );

            // Prepare metadata
            $metadata = [
                'alt_text' => $image_data['caption'][0]['plain_text'] ?? '',
                'caption'  => $this->extract_caption( $image_data ),
            ];

            // Upload to Media Library
            $attachment_id = $this->uploader->upload(
                $downloaded['file_path'],
                $metadata,
                $parent_post_id
            );

            // Track upload
            $this->tracker->track_attachment( $attachment_id, $block_id, $image_url );

            // Clean up temp file
            $this->downloader->cleanup( $downloaded['file_path'] );

            return $this->generate_image_block( $attachment_id, $image_data );

        } catch ( \Exception $e ) {
            error_log( sprintf( 'Image sync failed: %s', $e->getMessage() ) );

            // Return fallback HTML comment
            return sprintf(
                '<!-- Image sync failed: %s -->',
                esc_html( $e->getMessage() )
            );
        }
    }

    /**
     * Generate Gutenberg image block HTML.
     *
     * @param int   $attachment_id Attachment ID.
     * @param array $image_data    Notion image data.
     * @return string Block HTML.
     */
    private function generate_image_block( int $attachment_id, array $image_data ): string {
        $url = wp_get_attachment_image_url( $attachment_id, 'full' );
        $alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        $caption = $this->extract_caption( $image_data );

        // Build Gutenberg image block
        $block = sprintf(
            '<!-- wp:image {"id":%d} -->',
            $attachment_id
        );

        $block .= '<figure class="wp-block-image">';
        $block .= sprintf(
            '<img src="%s" alt="%s" class="wp-image-%d"/>',
            esc_url( $url ),
            esc_attr( $alt ),
            $attachment_id
        );

        if ( ! empty( $caption ) ) {
            $block .= sprintf(
                '<figcaption>%s</figcaption>',
                wp_kses_post( $caption )
            );
        }

        $block .= '</figure>';
        $block .= '<!-- /wp:image -->';

        return $block;
    }

    /**
     * Extract caption from Notion image data.
     *
     * @param array $image_data Notion image data.
     * @return string Caption text.
     */
    private function extract_caption( array $image_data ): string {
        if ( empty( $image_data['caption'] ) ) {
            return '';
        }

        $caption_parts = [];
        foreach ( $image_data['caption'] as $rich_text ) {
            $caption_parts[] = $rich_text['plain_text'] ?? '';
        }

        return implode( '', $caption_parts );
    }

    public function supports( string $block_type ): bool {
        return $block_type === 'image';
    }
}
```

**File 2:** `plugin/src/Blocks/Converters/FileConverter.php` (<250 lines)

Similar to ImageConverter but generates link instead of embedded image:

```php
<?php
namespace NotionSync\Blocks\Converters;

/**
 * Converts Notion file blocks to WordPress file links.
 */
class FileConverter implements ConverterInterface {

    // Similar structure to ImageConverter
    // But returns download link instead of image block

    private function generate_file_block( int $attachment_id, array $file_data ): string {
        $url = wp_get_attachment_url( $attachment_id );
        $filename = get_the_title( $attachment_id );

        return sprintf(
            '<!-- wp:file {"id":%d} -->
            <div class="wp-block-file">
                <a href="%s">%s</a>
                <a href="%s" class="wp-block-file__button" download>Download</a>
            </div>
            <!-- /wp:file -->',
            $attachment_id,
            esc_url( $url ),
            esc_html( $filename ),
            esc_url( $url )
        );
    }

    public function supports( string $block_type ): bool {
        return $block_type === 'file' || $block_type === 'pdf';
    }
}
```

**Update:** `plugin/src/Blocks/BlockConverter.php`

Register new converters:

```php
public function register_default_converters(): void {
    // Existing converters...

    // Register media converters
    $this->register_converter( new ImageConverter(
        new ImageDownloader(),
        new MediaUploader(),
        new MediaTracker()
    ) );

    $this->register_converter( new FileConverter(
        new FileDownloader(),
        new MediaUploader(),
        new MediaTracker()
    ) );
}
```

**Tasks:**

1. Create ImageConverter class
2. Implement download-upload flow
3. Generate Gutenberg image blocks
4. Create FileConverter for PDFs
5. Update BlockConverter registration
6. Add error handling for failed downloads
7. Write integration tests
8. Test with real Notion pages

**Definition of Done:**

- [ ] Image blocks convert correctly
- [ ] File blocks convert correctly
- [ ] Gutenberg blocks render properly
- [ ] Deduplication works on re-sync
- [ ] Failed downloads don't break sync
- [ ] Integration tests pass

---

### Stream 4: Background Processing for Large Media Sets

**Worktree:** `phase-3-media-handling`
**Duration:** 1-2 days
**Files Created:** 1 new file (<300 lines)

**What This Builds:**

- Queue system for pages with many images
- Progress tracking for media downloads
- Prevent timeouts on image-heavy pages

**Technical Implementation:**

**File 1:** `plugin/src/Media/MediaSyncScheduler.php` (<300 lines)

```php
<?php
namespace NotionSync\Media;

/**
 * Schedules media downloads as background jobs.
 *
 * For pages with 10+ images, queue downloads to prevent timeouts.
 */
class MediaSyncScheduler {

    private const IMAGE_THRESHOLD = 10;

    /**
     * Should use background processing?
     *
     * @param array $blocks Page blocks.
     * @return bool True if should queue.
     */
    public function should_queue( array $blocks ): bool {
        $image_count = $this->count_media_blocks( $blocks );
        return $image_count >= self::IMAGE_THRESHOLD;
    }

    /**
     * Count media blocks in page.
     *
     * @param array $blocks Page blocks.
     * @return int Media block count.
     */
    private function count_media_blocks( array $blocks ): int {
        $count = 0;

        foreach ( $blocks as $block ) {
            if ( in_array( $block['type'], [ 'image', 'file', 'pdf' ], true ) ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Queue media sync job.
     *
     * @param string $page_id    Notion page ID.
     * @param int    $post_id    WordPress post ID.
     * @param array  $blocks     Page blocks.
     * @return string Job ID.
     */
    public function queue_media_sync(
        string $page_id,
        int $post_id,
        array $blocks
    ): string {
        $media_blocks = array_filter( $blocks, function( $block ) {
            return in_array( $block['type'], [ 'image', 'file', 'pdf' ], true );
        } );

        $job_id = 'media_sync_' . $page_id . '_' . time();

        // Schedule Action Scheduler job
        as_schedule_single_action(
            time() + 10, // Start in 10 seconds
            'notion_sync_media',
            [
                'job_id'   => $job_id,
                'page_id'  => $page_id,
                'post_id'  => $post_id,
                'blocks'   => $media_blocks,
            ]
        );

        // Store job metadata
        update_option( "notion_media_job_{$job_id}", [
            'page_id'     => $page_id,
            'post_id'     => $post_id,
            'total'       => count( $media_blocks ),
            'completed'   => 0,
            'failed'      => 0,
            'status'      => 'queued',
            'started_at'  => current_time( 'mysql' ),
        ] );

        return $job_id;
    }

    /**
     * Process media sync job.
     *
     * @param string $job_id  Job ID.
     * @param string $page_id Notion page ID.
     * @param int    $post_id WordPress post ID.
     * @param array  $blocks  Media blocks to process.
     */
    public function process_media_job(
        string $job_id,
        string $page_id,
        int $post_id,
        array $blocks
    ): void {
        $this->update_job_status( $job_id, 'processing' );

        $downloader = new ImageDownloader();
        $uploader = new MediaUploader();
        $tracker = new MediaTracker();
        $converter = new \NotionSync\Blocks\Converters\ImageConverter(
            $downloader,
            $uploader,
            $tracker
        );

        $completed = 0;
        $failed = 0;

        foreach ( $blocks as $block ) {
            try {
                $converter->convert( $block, $post_id );
                $completed++;
            } catch ( \Exception $e ) {
                $failed++;
                error_log( sprintf(
                    'Media job %s: Failed block %s: %s',
                    $job_id,
                    $block['id'],
                    $e->getMessage()
                ) );
            }

            $this->update_job_progress( $job_id, $completed, $failed );
        }

        $this->complete_job( $job_id );
    }

    /**
     * Get job progress.
     *
     * @param string $job_id Job ID.
     * @return array Job metadata.
     */
    public function get_job_progress( string $job_id ): array {
        return get_option( "notion_media_job_{$job_id}", [] );
    }

    private function update_job_status( string $job_id, string $status ): void {
        $job_data = $this->get_job_progress( $job_id );
        $job_data['status'] = $status;
        update_option( "notion_media_job_{$job_id}", $job_data );
    }

    private function update_job_progress( string $job_id, int $completed, int $failed ): void {
        $job_data = $this->get_job_progress( $job_id );
        $job_data['completed'] = $completed;
        $job_data['failed'] = $failed;
        update_option( "notion_media_job_{$job_id}", $job_data );
    }

    private function complete_job( string $job_id ): void {
        $job_data = $this->get_job_progress( $job_id );
        $job_data['status'] = 'completed';
        $job_data['completed_at'] = current_time( 'mysql' );
        update_option( "notion_media_job_{$job_id}", $job_data );
    }
}
```

**Register Action Scheduler Hook:**

In `plugin/notion-sync.php`:

```php
// Register media sync action
add_action( 'notion_sync_media', function( $job_id, $page_id, $post_id, $blocks ) {
    $scheduler = new \NotionSync\Media\MediaSyncScheduler();
    $scheduler->process_media_job( $job_id, $page_id, $post_id, $blocks );
}, 10, 4 );
```

**Tasks:**

1. Create MediaSyncScheduler class
2. Implement media block counting
3. Add queueing logic (>10 images)
4. Implement background processing
5. Add progress tracking
6. Register Action Scheduler hook
7. Test with image-heavy pages
8. Test timeout prevention

**Definition of Done:**

- [ ] Pages with <10 images sync inline
- [ ] Pages with 10+ images queue to background
- [ ] Progress tracking works
- [ ] No timeouts on 50+ image pages
- [ ] Failed images don't block job
- [ ] Integration tests pass

---

## 📦 Deliverables

### Visible to Users

- ✅ Images from Notion appear in WordPress posts
- ✅ Images visible in Media Library
- ✅ Alt text preserved from Notion captions
- ✅ Re-sync doesn't create duplicate images
- ✅ PDF files download and link correctly
- ✅ Large image sets sync without timeout

### Technical

**New Files:**

- ✅ `plugin/src/Media/ImageDownloader.php` - Image downloads
- ✅ `plugin/src/Media/FileDownloader.php` - File downloads
- ✅ `plugin/src/Media/MediaUploader.php` - Media Library uploads
- ✅ `plugin/src/Media/MediaTracker.php` - Deduplication
- ✅ `plugin/src/Media/MediaSyncScheduler.php` - Background jobs
- ✅ `plugin/src/Blocks/Converters/ImageConverter.php` - Image blocks
- ✅ `plugin/src/Blocks/Converters/FileConverter.php` - File blocks

**Modified Files:**

- ✅ `plugin/src/Blocks/BlockConverter.php` - Register media converters
- ✅ `plugin/notion-sync.php` - Register Action Scheduler hook

**Not Built (Deferred):**

- ❌ Image optimization/compression (future)
- ❌ CDN integration (future)
- ❌ Lazy loading (WordPress handles this)
- ❌ Image galleries (Phase 4)
- ❌ Video embeds (Phase 4)

---

## 🔍 Testing Checklist

### Functional Testing

- [ ] Single image block syncs correctly
- [ ] Multiple image blocks (5) sync correctly
- [ ] Large image set (20+) syncs without timeout
- [ ] Alt text preserved from Notion
- [ ] Captions preserved from Notion
- [ ] PDF file blocks sync and link
- [ ] Re-sync doesn't duplicate images
- [ ] Re-sync updates changed images
- [ ] Expired URL handling (graceful failure)
- [ ] Invalid image type handling
- [ ] File size limit enforcement (10MB images, 50MB files)
- [ ] Orphaned temp files cleaned up

### WordPress Integration

- [ ] Images appear in Media Library
- [ ] Thumbnails generate correctly
- [ ] Image editing works in WP admin
- [ ] Responsive images work (srcset)
- [ ] Images attached to correct post
- [ ] Image metadata searchable in Media Library

### Performance Testing

- [ ] 10 images sync in under 2 minutes
- [ ] 50 images sync without timeout
- [ ] No memory errors on large syncs
- [ ] Background jobs complete reliably
- [ ] Retry logic works on network failures

### Code Quality

- [ ] All files under 500 lines
- [ ] `composer lint:phpcs` clean
- [ ] `composer lint:phpstan` passes level 3
- [ ] No PHP warnings
- [ ] No console errors

---

## 📊 Success Metrics

**Time Metrics:**

- 10 images sync in <2 minutes
- 50 images sync in <10 minutes (background)
- Download retry succeeds within 10 seconds

**Quality Metrics:**

- Zero linting warnings
- 95%+ successful image downloads
- Zero duplicate images on re-sync
- 100% metadata preservation (alt text, caption)

**User Metrics:**

- Can demo in under 5 minutes
- Non-developer understands media sync

---

## 🚧 Risks & Mitigation

| Risk                        | Impact | Mitigation                         |
| --------------------------- | ------ | ---------------------------------- |
| Expired Notion URLs         | High   | Download immediately during sync   |
| Large file timeouts         | High   | Background processing via scheduler |
| Memory exhaustion           | Medium | Process in batches, cleanup temps  |
| Network failures            | Medium | Retry logic with exponential backoff |
| Duplicate images            | Medium | Track by Notion block ID           |
| Unsupported image formats   | Low    | Validate MIME type before upload   |

---

## ✋ Gatekeeping Review

**Demo Script** (5 minutes):

1. Show Notion page with 10 images (30s)
2. Sync page to WordPress (1min)
3. View post - verify images display (1min)
4. Check Media Library - verify uploads (1min)
5. Re-sync - verify no duplicates (1.5min)

**Pass Criteria:**

- All images synced successfully
- Alt text and captions preserved
- No duplicates created
- UI responsive and clear
- No errors or warnings

---

## ⏭️ Next Phase Preview

**Phase 4: Advanced Blocks**

- Quote blocks
- Callout blocks
- Toggle/accordion blocks
- Code blocks with syntax highlighting
- Tables
- Column layouts
- Embed blocks (YouTube, Twitter)

**Requires:** Working media handling from Phase 3
