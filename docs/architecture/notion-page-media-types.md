# Notion Page Media Types & Handling Strategy

**Related:** Phase 3 (Media Handling)
**Status:** Architecture Decision

## Notion Page Media Types

A Notion page can contain several types of media:

### 1. Page Properties (Metadata)

#### Cover Image (`page.cover`)

```json
{
	"cover": {
		"type": "external",
		"external": {
			"url": "https://images.unsplash.com/photo-..."
		}
	}
}
```

**OR**

```json
{
	"cover": {
		"type": "file",
		"file": {
			"url": "https://s3.us-west-2.amazonaws.com/secure.notion-static.com/...",
			"expiry_time": "2024-01-15T12:00:00.000Z"
		}
	}
}
```

**Types:**

- `external` - URL from Unsplash, Giphy, or other external source
- `file` - Uploaded to Notion (hosted on Notion's S3)

#### Page Icon (`page.icon`)

```json
{
	"icon": {
		"type": "emoji",
		"emoji": "📚"
	}
}
```

**OR**

```json
{
	"icon": {
		"type": "external",
		"external": {
			"url": "https://example.com/icon.png"
		}
	}
}
```

**OR**

```json
{
	"icon": {
		"type": "file",
		"file": {
			"url": "https://s3.us-west-2.amazonaws.com/...",
			"expiry_time": "..."
		}
	}
}
```

**Types:**

- `emoji` - Unicode emoji character (e.g., 📚, 🎓, 💡)
- `external` - External URL
- `file` - Uploaded custom icon

### 2. Content Blocks (Within Page)

#### Image Block

```json
{
  "type": "image",
  "image": {
    "type": "file",
    "file": {
      "url": "https://s3.us-west-2.amazonaws.com/...",
      "expiry_time": "..."
    },
    "caption": [...]
  }
}
```

**OR**

```json
{
	"type": "image",
	"image": {
		"type": "external",
		"external": {
			"url": "https://images.unsplash.com/..."
		}
	}
}
```

#### File/PDF Block

```json
{
  "type": "file",
  "file": {
    "type": "file",
    "file": {
      "url": "https://s3.us-west-2.amazonaws.com/...",
      "expiry_time": "..."
    },
    "caption": [...]
  }
}
```

#### Video Block

```json
{
	"type": "video",
	"video": {
		"type": "external",
		"external": {
			"url": "https://www.youtube.com/watch?v=..."
		}
	}
}
```

#### Embed Block

```json
{
	"type": "embed",
	"embed": {
		"url": "https://twitter.com/..."
	}
}
```

## Handling Strategy by Type

### External URLs (Unsplash, Giphy, etc.)

**Strategy: Link, Don't Download**

**Why?**

- Copyright/licensing concerns
- Unsplash/Giphy have their own CDN
- URLs don't expire (unlike Notion S3 URLs)
- Downloading would violate terms of service
- WordPress can handle external images in `<img>` tags

**Implementation:**

```php
class ImageConverter {
    public function convert(array $block, int $parent_post_id = 0): string {
        $image_data = $block['image'];

        if ($image_data['type'] === 'external') {
            // External URL - don't download, just link
            $external_url = $image_data['external']['url'];

            return $this->generate_external_image_block($external_url, $image_data);
        }

        if ($image_data['type'] === 'file') {
            // Notion-hosted file - download to Media Library
            $notion_url = $image_data['file']['url'];

            return $this->download_and_convert($notion_url, $parent_post_id, $image_data);
        }
    }

    private function generate_external_image_block(string $url, array $image_data): string {
        $caption = $this->extract_caption($image_data);
        $alt = $caption ?: 'External image';

        // Gutenberg image block with external URL
        return sprintf(
            '<!-- wp:image -->
            <figure class="wp-block-image">
                <img src="%s" alt="%s" class="external-image"/>
                %s
            </figure>
            <!-- /wp:image -->',
            esc_url($url),
            esc_attr($alt),
            $caption ? '<figcaption>' . wp_kses_post($caption) . '</figcaption>' : ''
        );
    }
}
```

### Notion S3 URLs (Uploaded Files)

**Strategy: Download to WordPress Media Library**

- Time-limited URLs (expire after 1 hour)
- Must download during sync
- Store in Media Library for permanence

### Cover Images

**Strategy: Hybrid Approach**

```php
class PageSyncManager {
    public function sync_page(string $notion_page_id): int {
        $page = $this->client->get_page($notion_page_id);

        $post_id = wp_insert_post([
            'post_title' => $this->extract_title($page),
            'post_status' => 'draft',
        ]);

        // Handle cover image
        if (!empty($page['cover'])) {
            $this->set_featured_image($post_id, $page['cover']);
        }

        // Handle icon
        if (!empty($page['icon'])) {
            $this->set_page_icon($post_id, $page['icon']);
        }

        // ... rest of sync
    }

    private function set_featured_image(int $post_id, array $cover): void {
        if ($cover['type'] === 'external') {
            // Option 1: Store URL in post meta (no download)
            update_post_meta($post_id, 'notion_cover_url', $cover['external']['url']);

            // Option 2: Download from external URL (risky for Unsplash/Giphy)
            // Only do this if user configures it
            if (get_option('notion_sync_download_external_covers')) {
                $attachment_id = $this->download_external_cover($cover['external']['url']);
                set_post_thumbnail($post_id, $attachment_id);
            }
        } else if ($cover['type'] === 'file') {
            // Download Notion-hosted cover to Media Library
            $downloaded = $this->imageDownloader->download($cover['file']['url']);
            $attachment_id = $this->mediaUploader->upload($downloaded['file_path'], [], $post_id);

            // Set as WordPress featured image
            set_post_thumbnail($post_id, $attachment_id);

            // Register in MediaRegistry
            MediaRegistry::register('cover_' . $notion_page_id, $attachment_id, $cover['file']['url']);
        }
    }

    private function set_page_icon(int $post_id, array $icon): void {
        if ($icon['type'] === 'emoji') {
            // Store emoji as post meta
            update_post_meta($post_id, 'notion_icon_emoji', $icon['emoji']);
        } else if ($icon['type'] === 'external') {
            // Store external icon URL
            update_post_meta($post_id, 'notion_icon_url', $icon['external']['url']);
        } else if ($icon['type'] === 'file') {
            // Download custom icon
            $downloaded = $this->imageDownloader->download($icon['file']['url']);
            $attachment_id = $this->mediaUploader->upload($downloaded['file_path'], [], $post_id);

            update_post_meta($post_id, 'notion_icon_attachment_id', $attachment_id);

            MediaRegistry::register('icon_' . $notion_page_id, $attachment_id, $icon['file']['url']);
        }
    }
}
```

### Displaying Cover & Icon in WordPress

**Option 1: Use WordPress Featured Image**

```php
// Cover becomes featured image
set_post_thumbnail($post_id, $attachment_id);

// Theme displays featured image automatically
```

**Option 2: Custom Template**

```php
// In theme or custom template
$icon_emoji = get_post_meta($post_id, 'notion_icon_emoji', true);
$cover_url = get_post_meta($post_id, 'notion_cover_url', true);

if ($cover_url) {
    echo '<div class="notion-page-cover" style="background-image: url(' . esc_url($cover_url) . ')"></div>';
}

if ($icon_emoji) {
    echo '<span class="notion-page-icon">' . $icon_emoji . '</span>';
}
```

**Option 3: Prepend to Content**

```php
$content = get_post_field('post_content', $post_id);

// Add cover image at top of content
if ($cover_url) {
    $cover_html = sprintf(
        '<figure class="wp-block-cover notion-page-cover">
            <img src="%s" alt="Page cover"/>
        </figure>',
        esc_url($cover_url)
    );
    $content = $cover_html . $content;
}

// Add icon near title
if ($icon_emoji) {
    $content = '<p class="notion-icon">' . $icon_emoji . '</p>' . $content;
}

wp_update_post([
    'ID' => $post_id,
    'post_content' => $content,
]);
```

## Decision Matrix

| Media Type                       | Source         | Download? | Storage           | WordPress Mapping                    |
| -------------------------------- | -------------- | --------- | ----------------- | ------------------------------------ |
| **Cover Image (Unsplash/Giphy)** | External URL   | ❌ No     | Post meta URL     | Featured image or prepend to content |
| **Cover Image (Uploaded)**       | Notion S3      | ✅ Yes    | Media Library     | Featured image                       |
| **Icon (Emoji)**                 | Unicode        | N/A       | Post meta         | Display in template or prepend       |
| **Icon (External URL)**          | External       | ❌ No     | Post meta URL     | Display in template                  |
| **Icon (Uploaded)**              | Notion S3      | ✅ Yes    | Media Library     | Display in template                  |
| **Image Block (Unsplash/Giphy)** | External URL   | ❌ No     | Inline in content | External `<img>` in Gutenberg block  |
| **Image Block (Uploaded)**       | Notion S3      | ✅ Yes    | Media Library     | Gutenberg image block                |
| **File/PDF Block**               | Notion S3      | ✅ Yes    | Media Library     | Gutenberg file block                 |
| **Video (YouTube)**              | External embed | ❌ No     | Inline in content | Gutenberg embed block                |
| **Embed (Twitter, etc.)**        | External embed | ❌ No     | Inline in content | Gutenberg embed block                |

## Legal & Licensing Considerations

### Unsplash Images

**Terms:** Free to use, but attribution recommended

**Our Approach:**

- Link to original URL (don't download)
- Preserve caption if it contains attribution
- Let Unsplash CDN serve the image

**Why not download?**

- Unsplash license allows usage but downloading copies might violate spirit
- Their CDN is optimized
- Saves WordPress storage

### Giphy

**Terms:** Embed via Giphy API/URLs only

**Our Approach:**

- MUST link to Giphy URL (don't download)
- Violates TOS to download and re-host
- Use their embed code or direct URL

### User-Uploaded Media

**Terms:** User owns the content

**Our Approach:**

- Download to WordPress Media Library
- User has full rights to their content
- Necessary because Notion S3 URLs expire

## Configuration Options

**Settings Page Options:**

```php
// Admin setting
add_settings_field(
    'notion_sync_external_media_strategy',
    'External Media Handling',
    'render_external_media_setting',
    'notion-sync',
    'notion_sync_settings'
);

function render_external_media_setting() {
    $current = get_option('notion_sync_external_media_strategy', 'link');
    ?>
    <select name="notion_sync_external_media_strategy">
        <option value="link" <?php selected($current, 'link'); ?>>
            Link to external URL (Recommended)
        </option>
        <option value="download" <?php selected($current, 'download'); ?>>
            Download to Media Library (May violate TOS)
        </option>
        <option value="skip" <?php selected($current, 'skip'); ?>>
            Skip external images
        </option>
    </select>
    <p class="description">
        How to handle images from Unsplash, Giphy, etc.
        Linking is recommended to avoid copyright issues.
    </p>
    <?php
}
```

## Phase 3 Implementation Updates

### Stream 1: Add External URL Handling

Update `ImageDownloader.php`:

```php
class ImageDownloader {

    /**
     * Should download this URL or link externally?
     *
     * @param string $url Image URL.
     * @return bool True if should download.
     */
    public function should_download(string $url): bool {
        // Check if it's a Notion S3 URL (must download - expires)
        if (strpos($url, 's3.us-west-2.amazonaws.com/secure.notion-static.com') !== false) {
            return true;
        }

        // Check if it's Unsplash (link, don't download)
        if (strpos($url, 'images.unsplash.com') !== false) {
            return false;
        }

        // Check if it's Giphy (link, don't download - TOS violation)
        if (strpos($url, 'giphy.com') !== false || strpos($url, 'media.giphy.com') !== false) {
            return false;
        }

        // Check user preference for other external URLs
        $strategy = get_option('notion_sync_external_media_strategy', 'link');

        return $strategy === 'download';
    }
}
```

### Stream 2: Add Cover & Icon Handling

Update `SyncManager.php`:

```php
class SyncManager {

    private function sync_page_metadata(int $post_id, array $page): void {
        // Handle cover image
        if (!empty($page['cover'])) {
            $this->handle_cover_image($post_id, $page['cover']);
        }

        // Handle icon
        if (!empty($page['icon'])) {
            $this->handle_page_icon($post_id, $page['icon']);
        }
    }

    private function handle_cover_image(int $post_id, array $cover): void {
        if ($cover['type'] === 'file') {
            // Notion-hosted - must download
            try {
                $downloaded = $this->imageDownloader->download($cover['file']['url']);
                $attachment_id = $this->mediaUploader->upload(
                    $downloaded['file_path'],
                    ['title' => 'Cover Image'],
                    $post_id
                );

                set_post_thumbnail($post_id, $attachment_id);
                MediaRegistry::register("cover_{$post_id}", $attachment_id, $cover['file']['url']);

            } catch (\Exception $e) {
                error_log("Failed to download cover image: " . $e->getMessage());
            }
        } else if ($cover['type'] === 'external') {
            // External (Unsplash, etc.) - store URL
            update_post_meta($post_id, '_notion_cover_url', $cover['external']['url']);
            update_post_meta($post_id, '_notion_cover_type', 'external');
        }
    }

    private function handle_page_icon(int $post_id, array $icon): void {
        if ($icon['type'] === 'emoji') {
            update_post_meta($post_id, '_notion_icon_emoji', $icon['emoji']);
            update_post_meta($post_id, '_notion_icon_type', 'emoji');
        } else if ($icon['type'] === 'external') {
            update_post_meta($post_id, '_notion_icon_url', $icon['external']['url']);
            update_post_meta($post_id, '_notion_icon_type', 'external');
        } else if ($icon['type'] === 'file') {
            try {
                $downloaded = $this->imageDownloader->download($icon['file']['url']);
                $attachment_id = $this->mediaUploader->upload(
                    $downloaded['file_path'],
                    ['title' => 'Page Icon'],
                    $post_id
                );

                update_post_meta($post_id, '_notion_icon_attachment_id', $attachment_id);
                update_post_meta($post_id, '_notion_icon_type', 'file');

                MediaRegistry::register("icon_{$post_id}", $attachment_id, $icon['file']['url']);

            } catch (\Exception $e) {
                error_log("Failed to download page icon: " . $e->getMessage());
            }
        }
    }
}
```

## Summary

### Download to WordPress Media Library ✅

- Notion S3 URLs (expire in 1 hour)
- User-uploaded cover images
- User-uploaded icons
- User-uploaded files/PDFs

### Link Externally (Don't Download) ✅

- Unsplash images (licensing, CDN)
- Giphy images (TOS violation to download)
- YouTube embeds
- Twitter embeds
- Other external embeds

### Store as Post Meta 📝

- Emoji icons (Unicode characters)
- External cover URLs
- External icon URLs

### WordPress Mapping

- **Cover Image**: WordPress Featured Image (if downloaded) or post meta
- **Icon**: Post meta (display in theme/template)
- **Content Images**: Gutenberg image blocks (downloaded or external)
- **Embeds**: Gutenberg embed blocks (external URLs)

## User Configurable

Plugin settings let users choose:

1. **Link external images** (recommended, legal, saves space)
2. **Download external images** (risk copyright issues)
3. **Skip external images** (text only)

Default: **Link** (safest option)
