# Media Sync Integration with Pages and Databases

**Status:** Architecture Document
**Related:** Phase 2 (Database Sync), Phase 3 (Media Handling)
**Pattern:** Extends LinkRegistry pattern to media

## Overview

This document describes how media downloading/uploading integrates with page and database synchronization, handling the "chicken-and-egg" problem of cross-references.

## Architecture Principle: Registry Pattern

**Proven Pattern from Phase 2:**
```
LinkRegistry: notion_page_id → wordpress_post_id → permalink
```

**Extended to Media:**
```
MediaRegistry: notion_file_url|block_id → wordpress_attachment_id → media_url
```

## Integration Scenarios

### Scenario 1: Inline Images in Pages ✅ (Phase 3)

**When:** Syncing a Notion page that contains image blocks

**Flow:**
```
1. Start page sync
2. Create WordPress post (draft)
3. Process blocks sequentially:
   - Text blocks → Convert immediately
   - Image blocks → Download → Upload → Track in MediaRegistry
4. Generate final post content with image blocks
5. Publish post
```

**Code Example:**
```php
class SyncManager {
    public function sync_page(string $notion_page_id): int {
        // Fetch page blocks
        $blocks = $this->client->get_blocks($notion_page_id);

        // Create post (draft status)
        $post_id = wp_insert_post([
            'post_status' => 'draft',
            'post_title' => $this->extract_title($blocks),
        ]);

        // Convert blocks (includes media download)
        $content = $this->blockConverter->convert($blocks, $post_id);

        // Update with final content
        wp_update_post([
            'ID' => $post_id,
            'post_content' => $content,
            'post_status' => 'publish',
        ]);

        return $post_id;
    }
}
```

**Timing:**
- **Small pages (<10 images):** Synchronous - download during sync
- **Large pages (10+ images):**
  ```
  1. Create post with placeholder comments: <!-- Image: downloading... -->
  2. Queue background job for media
  3. Background job downloads, updates post content
  4. Publish post when complete
  ```

### Scenario 2: Database Rows with Media (Phase 2 + Future)

**Phase 2 (Current): Store JSON Only**

Database rows stored as-is with file URLs:

```sql
CREATE TABLE wp_notion_database_rows (
    id BIGINT,
    notion_page_id VARCHAR(36),
    properties LONGTEXT,  -- JSON with file URLs
    ...
);
```

**Example Row JSON:**
```json
{
  "properties": {
    "Title": "Product ABC",
    "Cover Image": {
      "type": "files",
      "files": [{
        "name": "product-image.png",
        "type": "file",
        "file": {
          "url": "https://s3.us-west-2.amazonaws.com/secure.notion-static.com/...",
          "expiry_time": "2024-01-15T12:00:00.000Z"
        }
      }]
    },
    "Attachments": {
      "type": "files",
      "files": [...]
    }
  }
}
```

**No Media Downloaded** during database sync - URLs stored as-is.

**Why?**
- Database might have 1000s of rows
- Not all rows will become posts
- Notion URLs expire in 1 hour (would need constant re-fetching)
- User might only want specific rows as posts

**Future Enhancement (Phase 3.1 or Phase 4):**

When user converts a specific database row to a WordPress post:

```php
class RowToPostConverter {
    public function convert_row_to_post(int $row_id): int {
        // Get row data
        $row = $this->rowRepository->get_row($row_id);

        // Create post
        $post_id = wp_insert_post([
            'post_title' => $row['title'],
            'post_status' => 'draft',
        ]);

        // NOW download media for this specific row
        $this->download_row_media($row, $post_id);

        // Generate content with media
        $content = $this->generate_content_from_row($row, $post_id);

        wp_update_post([
            'ID' => $post_id,
            'post_content' => $content,
            'post_status' => 'publish',
        ]);

        return $post_id;
    }

    private function download_row_media(array $row, int $post_id): void {
        foreach ($row['properties'] as $property) {
            if ($property['type'] === 'files') {
                foreach ($property['files'] as $file) {
                    // Download if not already downloaded
                    if (!MediaRegistry::exists($file['file']['url'])) {
                        $attachment_id = $this->mediaUploader->upload(
                            $this->imageDownloader->download($file['file']['url']),
                            [],
                            $post_id
                        );

                        MediaRegistry::register(
                            $file['file']['url'],
                            $attachment_id
                        );
                    }
                }
            }
        }
    }
}
```

**User Workflow:**
```
1. Sync database (stores JSON with URLs)
2. View rows in admin
3. Select specific rows: "Convert to Posts"
4. Plugin downloads media ONLY for selected rows
5. Creates posts with media in Media Library
```

### Scenario 3: Cross-Page Media References (Chicken-Egg Problem)

**Problem:**

```
Notion Structure:
├── Page A: "Documentation"
│   └── Image Block: References diagram
├── Page B: "Architecture"
│   └── Synced Block: Shows same diagram from Page A

If we sync Page B before Page A, the image doesn't exist yet!
```

**Solution: Two-Pass Sync with MediaRegistry**

**Pass 1: Create All Posts, Download All Media**
```php
class MediaRegistry {
    /**
     * Register downloaded media.
     *
     * @param string $notion_identifier Notion block ID or file URL.
     * @param int    $attachment_id     WordPress attachment ID.
     */
    public static function register(
        string $notion_identifier,
        int $attachment_id
    ): void {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'notion_media_registry',
            [
                'notion_identifier' => $notion_identifier,
                'attachment_id' => $attachment_id,
                'registered_at' => current_time('mysql'),
            ]
        );
    }

    /**
     * Find existing attachment by Notion identifier.
     *
     * @param string $notion_identifier Notion block ID or file URL.
     * @return int|null Attachment ID or null.
     */
    public static function find(string $notion_identifier): ?int {
        global $wpdb;

        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT attachment_id
             FROM {$wpdb->prefix}notion_media_registry
             WHERE notion_identifier = %s
             LIMIT 1",
            $notion_identifier
        ));

        return $attachment_id ? (int) $attachment_id : null;
    }

    /**
     * Get WordPress media URL from Notion identifier.
     *
     * @param string $notion_identifier Notion block ID or file URL.
     * @return string|null Media URL or null.
     */
    public static function get_media_url(string $notion_identifier): ?string {
        $attachment_id = self::find($notion_identifier);

        if (!$attachment_id) {
            return null;
        }

        return wp_get_attachment_url($attachment_id);
    }
}
```

**Pass 2: Update Cross-References**

After all pages synced:

```php
class LinkUpdater {
    public function update_cross_page_media(): void {
        // Find all posts with placeholder media comments
        $posts_with_placeholders = $this->find_posts_with_media_placeholders();

        foreach ($posts_with_placeholders as $post_id) {
            $content = get_post_field('post_content', $post_id);

            // Replace placeholders with actual media
            $updated_content = preg_replace_callback(
                '/<!-- notion-media: ([a-f0-9-]+) -->/',
                function($matches) {
                    $notion_block_id = $matches[1];
                    $attachment_id = MediaRegistry::find($notion_block_id);

                    if ($attachment_id) {
                        return $this->generate_image_block($attachment_id);
                    }

                    return $matches[0]; // Keep placeholder if not found
                },
                $content
            );

            if ($updated_content !== $content) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $updated_content,
                ]);
            }
        }
    }
}
```

## Sync Workflows

### Workflow 1: Single Page Sync (Simple Case)

```
User clicks "Sync Page"
    ↓
1. Fetch Notion page blocks
2. Create WordPress post (draft)
3. For each block:
   - Text blocks: Convert immediately
   - Image blocks:
     a. Check MediaRegistry - already exists?
     b. If yes: Use existing attachment ID
     c. If no: Download → Upload → Register
4. Generate final post content
5. Publish post
    ↓
Done ✅
```

**Timeline:** ~30 seconds for page with 5 images

### Workflow 2: Large Page Sync (10+ Images)

```
User clicks "Sync Page"
    ↓
1. Fetch Notion page blocks
2. Create WordPress post (draft)
3. For each block:
   - Text blocks: Convert immediately
   - Image blocks: Insert placeholder comment
4. Publish post with placeholders
5. Queue background job for media
    ↓
User sees: "Post created. Images downloading in background..."
    ↓
Background Job (Action Scheduler):
6. For each placeholder:
   a. Download image
   b. Upload to Media Library
   c. Register in MediaRegistry
7. Update post content (replace placeholders)
8. Mark job complete
    ↓
User sees: "Sync complete. 12 images uploaded."
    ↓
Done ✅
```

**Timeline:**
- Post visible immediately
- Images complete in ~2-3 minutes

### Workflow 3: Database Sync (Phase 2)

```
User clicks "Sync Database"
    ↓
1. Fetch all database entries
2. For each entry:
   - Store as JSON in custom table
   - DO NOT download media (URLs stored as-is)
3. Create database CPT post for metadata
4. Mark sync complete
    ↓
User sees: "Database synced. 150 rows stored."
    ↓
Later: User selects 5 rows → "Convert to Posts"
    ↓
5. For each selected row:
   a. Create WordPress post
   b. Download media from URLs in JSON
   c. Register in MediaRegistry
   d. Generate content with media
   e. Publish post
    ↓
Done ✅
```

**Timeline:**
- Database sync: ~1-2 minutes (no media)
- Row → Post conversion: ~10 seconds per row

### Workflow 4: Bulk Page Sync with Cross-References

```
User clicks "Sync All Pages" (50 pages)
    ↓
PASS 1: Create Posts & Download Media
1. Queue batch job
2. For each page:
   a. Create WordPress post
   b. Download inline images
   c. Register in MediaRegistry
   d. Insert placeholders for cross-references
3. Register all pages in LinkRegistry
    ↓
PASS 2: Update Cross-References
4. Run LinkUpdater to fix page links
5. Run MediaUpdater to fix media references
6. Publish all posts
    ↓
Done ✅
```

**Timeline:** ~5-10 minutes for 50 pages

## Database Schema Addition

**New Table: Media Registry**

```sql
CREATE TABLE wp_notion_media_registry (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notion_identifier VARCHAR(255) NOT NULL UNIQUE,  -- Block ID or file URL
    attachment_id BIGINT UNSIGNED NOT NULL,
    notion_file_url TEXT,  -- Original Notion URL (for debugging)
    registered_at DATETIME NOT NULL,

    KEY attachment_id (attachment_id),
    KEY registered_at (registered_at)
);
```

**Why Two Identifiers?**
- `notion_identifier`: Unique key - could be block ID (preferred) or file URL hash
- `notion_file_url`: Full URL for debugging/logging

## Phase 3 Implementation Updates

### Update Stream 2: Add MediaRegistry

**New File:** `plugin/src/Media/MediaRegistry.php`

```php
<?php
namespace NotionSync\Media;

/**
 * Registry for tracking Notion media → WordPress attachments.
 *
 * Extends the LinkRegistry pattern to media files.
 */
class MediaRegistry {

    private const TABLE_NAME = 'notion_media_registry';

    /**
     * Create registry table.
     */
    public static function create_table(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            notion_identifier VARCHAR(255) NOT NULL UNIQUE,
            attachment_id BIGINT UNSIGNED NOT NULL,
            notion_file_url TEXT,
            registered_at DATETIME NOT NULL,
            KEY attachment_id (attachment_id),
            KEY registered_at (registered_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    // ... (methods from code above)
}
```

### Update ImageConverter to Use Registry

```php
class ImageConverter {
    public function convert(array $block, int $parent_post_id = 0): string {
        $block_id = $block['id'];
        $image_url = $block['image']['file']['url'];

        // Check registry first (deduplication)
        $attachment_id = MediaRegistry::find($block_id);

        if ($attachment_id) {
            // Already uploaded - reuse
            return $this->generate_image_block($attachment_id, $block['image']);
        }

        // Download and upload
        $downloaded = $this->downloader->download($image_url);
        $attachment_id = $this->uploader->upload($downloaded['file_path'], [], $parent_post_id);

        // Register in MediaRegistry
        MediaRegistry::register($block_id, $attachment_id, $image_url);

        return $this->generate_image_block($attachment_id, $block['image']);
    }
}
```

## Benefits of This Architecture

✅ **Deduplication**: MediaRegistry prevents duplicate uploads (like LinkRegistry prevents duplicate posts)

✅ **Consistency**: Same pattern for both links and media

✅ **Cross-References**: Two-pass sync handles chicken-egg scenarios

✅ **Performance**: Registry lookup is fast (indexed table)

✅ **Flexibility**: Can handle:
- Inline images in pages
- Database row media (future)
- Cross-page references
- Synced blocks

✅ **Debugging**: Registry provides audit trail of all media syncs

## Edge Cases Handled

### 1. Notion URL Expiration
```php
// URLs expire after 1 hour
// Always download during sync, not lazily
if (time() - strtotime($block['created_time']) > 3600) {
    // URL might be expired - fetch fresh from API
    $fresh_block = $this->client->get_block($block['id']);
    $image_url = $fresh_block['image']['file']['url'];
}
```

### 2. Image Changed in Notion
```php
// Check if file URL changed (indicates replacement)
$existing_url = MediaRegistry::get_file_url($block_id);

if ($existing_url !== $current_url) {
    // Image was replaced in Notion - re-download
    $attachment_id = $this->reupload_image($block_id, $current_url);
    MediaRegistry::update($block_id, $attachment_id, $current_url);
}
```

### 3. Deleted Media in Notion
```php
// If media no longer in Notion page but exists in WP
if (!in_array($attachment_id, $current_page_media)) {
    // Option 1: Keep in Media Library (safe)
    // Option 2: Move to trash
    // Option 3: Delete permanently
    // User configurable via settings
}
```

## Future Enhancements

### Phase 4: Galleries
```php
// Multiple images in sequence → WordPress gallery block
if ($this->is_image_sequence($blocks)) {
    $attachment_ids = [];
    foreach ($blocks as $block) {
        $attachment_ids[] = MediaRegistry::find($block['id']);
    }
    return $this->generate_gallery_block($attachment_ids);
}
```

### Phase 5: Image CDN Integration
```php
// Optionally use CDN for Notion images instead of downloading
if (get_option('notion_sync_use_cdn')) {
    // Keep Notion URL, don't download
    return $this->generate_external_image_block($image_url);
}
```

## Summary

| Scenario | Media Handling | Timing | Registry |
|----------|---------------|--------|----------|
| **Page with inline images** | Download during sync | Synchronous (<10 images) or Background (10+) | MediaRegistry tracks block_id → attachment_id |
| **Database rows with files** | Store URLs in JSON | No download during DB sync | Download only when row → post |
| **Cross-page references** | Two-pass sync | Pass 1: Download all, Pass 2: Update refs | MediaRegistry enables lookup |
| **Synced blocks** | Deduplicate via registry | Check registry first | Prevents duplicate uploads |

**Key Principle**: Extend the proven LinkRegistry pattern to media, enabling the same two-pass sync strategy that handles cross-references elegantly.
