# Stream 3 Integration Guide: Block Converter Quick Reference

**For**: SyncManager Implementation (Stream 3)
**Created**: 2025-10-20
**Status**: Ready for Integration

## Quick Start (3 Steps)

### 1. Import the BlockConverter

```php
use NotionSync\Blocks\BlockConverter;
```

### 2. Initialize in Your Class

```php
class SyncManager {
    private BlockConverter $block_converter;

    public function __construct() {
        $this->block_converter = new BlockConverter();
    }
}
```

### 3. Use in Sync Method

```php
public function sync_page(string $page_id): int {
    // Fetch blocks from Notion API (you implement this)
    $notion_blocks = $this->fetch_page_blocks($page_id);

    // Convert to Gutenberg HTML (BlockConverter does this)
    $post_content = $this->block_converter->convert_blocks($notion_blocks);

    // Create WordPress post
    return wp_insert_post([
        'post_content' => $post_content,
        'post_title' => $page_title,
        'post_status' => 'publish'
    ]);
}
```

## Method Signature

```php
BlockConverter::convert_blocks(array $notion_blocks): string
```

**Input**: Array of Notion block objects (from Notion API)
**Output**: Complete Gutenberg HTML string (ready for `post_content`)

## Input Format (Notion API Response)

```php
$notion_blocks = [
    [
        'type' => 'paragraph',
        'paragraph' => [
            'rich_text' => [
                [
                    'type' => 'text',
                    'text' => ['content' => 'Hello World'],
                    'annotations' => [
                        'bold' => true,
                        'italic' => false,
                        'strikethrough' => false,
                        'underline' => false,
                        'code' => false
                    ]
                ]
            ]
        ]
    ],
    [
        'type' => 'heading_2',
        'heading_2' => [
            'rich_text' => [
                [
                    'type' => 'text',
                    'text' => ['content' => 'Section Title'],
                    'annotations' => [...]
                ]
            ]
        ]
    ]
];
```

## Output Format (Gutenberg HTML)

```html
<!-- wp:paragraph -->
<p><strong>Hello World</strong></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Section Title</h2>
<!-- /wp:heading -->
```

## Complete Integration Example

```php
<?php

namespace NotionSync\Sync;

use NotionSync\Blocks\BlockConverter;
use NotionSync\API\NotionClient;

class SyncManager {
    private BlockConverter $block_converter;
    private NotionClient $notion_client;

    public function __construct(NotionClient $notion_client) {
        $this->notion_client = $notion_client;
        $this->block_converter = new BlockConverter();
    }

    /**
     * Sync a Notion page to WordPress
     */
    public function sync_page(string $page_id): int {
        // 1. Fetch page properties
        $page = $this->notion_client->get_page($page_id);
        $title = $this->extract_title($page);

        // 2. Fetch page blocks
        $notion_blocks = $this->notion_client->get_page_blocks($page_id);

        // 3. Convert blocks to Gutenberg
        $post_content = $this->block_converter->convert_blocks($notion_blocks);

        // 4. Create/update WordPress post
        $post_id = $this->create_or_update_post($page_id, $title, $post_content);

        return $post_id;
    }

    /**
     * Extract title from page properties
     */
    private function extract_title(array $page): string {
        // Implementation depends on your Notion page structure
        return $page['properties']['Name']['title'][0]['plain_text'] ?? 'Untitled';
    }

    /**
     * Create or update WordPress post
     */
    private function create_or_update_post(
        string $page_id,
        string $title,
        string $content
    ): int {
        // Check if post already exists
        $existing_post_id = $this->find_post_by_notion_id($page_id);

        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'meta_input' => [
                'notion_page_id' => $page_id,
                'last_synced' => current_time('mysql')
            ]
        ];

        if ($existing_post_id) {
            $post_data['ID'] = $existing_post_id;
            wp_update_post($post_data);
            return $existing_post_id;
        } else {
            return wp_insert_post($post_data);
        }
    }

    /**
     * Find WordPress post by Notion page ID
     */
    private function find_post_by_notion_id(string $page_id): ?int {
        $posts = get_posts([
            'meta_key' => 'notion_page_id',
            'meta_value' => $page_id,
            'post_type' => 'post',
            'posts_per_page' => 1
        ]);

        return $posts ? $posts[0]->ID : null;
    }
}
```

## Supported Block Types (Phase 1)

| Notion Type | Gutenberg Block | Status |
|-------------|-----------------|--------|
| `paragraph` | `wp:paragraph` | ✓ |
| `heading_1` | `wp:heading` (level 1) | ✓ |
| `heading_2` | `wp:heading` (level 2) | ✓ |
| `heading_3` | `wp:heading` (level 3) | ✓ |
| `bulleted_list_item` | `wp:list` | ✓ |
| `numbered_list_item` | `wp:list` (ordered) | ✓ |

**Unsupported blocks**: Converted to HTML comment placeholders

## Rich Text Support

All converters support these Notion annotations:

- **bold** → `<strong>`
- **italic** → `<em>`
- **code** → `<code>`
- **strikethrough** → `<s>`
- **underline** → `<u>`
- **link** → `<a href="">`

## Error Handling

The converter **never throws exceptions**. Instead:

1. **Unsupported blocks**: Logged to `error_log()` and replaced with HTML comment
2. **Missing fields**: Use default values (empty strings/arrays)
3. **Malformed data**: Skip and continue

Example unsupported block output:
```html
<!-- Unsupported Notion block: callout (ID: 12345678-...) -->
```

## Security

All content is automatically secured:

- **HTML escaping**: `esc_html()` on all text content
- **URL validation**: `esc_url()` on all links
- **XSS prevention**: Dangerous protocols stripped

You **don't need** to sanitize the output - it's already safe.

## Performance

Expected performance:
- **Single block**: < 0.1ms
- **100 blocks**: < 100ms
- **1000 blocks**: < 1 second

For very large documents (1000+ blocks), consider:
- Background processing
- Batch operations
- Progress indicators

## Testing Your Integration

```php
// Test with sample data
public function test_sync_integration() {
    $sync_manager = new SyncManager($notion_client);

    // Create test Notion blocks
    $test_blocks = [
        [
            'type' => 'paragraph',
            'paragraph' => [
                'rich_text' => [
                    [
                        'type' => 'text',
                        'text' => ['content' => 'Test paragraph'],
                        'annotations' => [
                            'bold' => false,
                            'italic' => false,
                            'strikethrough' => false,
                            'underline' => false,
                            'code' => false
                        ]
                    ]
                ]
            ]
        ]
    ];

    // Convert
    $converter = new BlockConverter();
    $result = $converter->convert_blocks($test_blocks);

    // Verify
    assert(str_contains($result, '<!-- wp:paragraph -->'));
    assert(str_contains($result, 'Test paragraph'));
}
```

## Common Issues & Solutions

### Issue: Empty output from converter

**Cause**: Empty `rich_text` array
**Solution**: Converter handles this automatically with `&nbsp;`

### Issue: Malformed Gutenberg blocks in WordPress

**Cause**: Extra characters or incorrect format
**Solution**: Ensure you're passing raw Notion API response, not modified data

### Issue: XSS warnings in WordPress

**Cause**: Using unsafe output functions
**Solution**: BlockConverter already escapes everything - just use output directly

### Issue: Slow conversion on large documents

**Cause**: Too many blocks processed at once
**Solution**: Implement batch processing in SyncManager

## Debugging

Enable logging to see what blocks are being processed:

```php
// In your SyncManager
error_log('[SyncManager] Converting ' . count($notion_blocks) . ' blocks');
$post_content = $this->block_converter->convert_blocks($notion_blocks);
error_log('[SyncManager] Generated ' . strlen($post_content) . ' bytes of HTML');
```

Check WordPress error log for unsupported block warnings:
```
[NotionSync] Unsupported block type: callout (ID: 12345...)
```

## Next Steps

After integrating BlockConverter:

1. Test with sample Notion pages
2. Verify Gutenberg blocks render correctly in WordPress
3. Check for unsupported block warnings in logs
4. Implement batch processing if needed
5. Add progress indicators for large syncs

## Reference Files

- **Complete Documentation**: `docs/implementation/block-conversion-system.md`
- **Usage Examples**: `docs/examples/block-converter-usage.php`
- **Implementation Summary**: `BLOCK-CONVERSION-IMPLEMENTATION.md`
- **Test Cases**: `tests/unit/Blocks/`

## Support

For implementation questions:
1. Check `docs/examples/block-converter-usage.php` for 7 complete examples
2. Review test cases in `tests/unit/Blocks/` for edge cases
3. Consult `docs/implementation/block-conversion-system.md` for technical details

## Quick Reference Card

```
┌─────────────────────────────────────────────────┐
│ BlockConverter Quick Reference                  │
├─────────────────────────────────────────────────┤
│ Import:                                         │
│   use NotionSync\Blocks\BlockConverter;         │
│                                                 │
│ Initialize:                                     │
│   $converter = new BlockConverter();            │
│                                                 │
│ Convert:                                        │
│   $html = $converter->convert_blocks($blocks);  │
│                                                 │
│ Input:  array (Notion API blocks)              │
│ Output: string (Gutenberg HTML)                 │
│                                                 │
│ Supported: paragraph, heading, lists            │
│ Security: Automatic (esc_html, esc_url)        │
│ Errors:   Never throws exceptions               │
│ Performance: < 1s for 1000 blocks              │
└─────────────────────────────────────────────────┘
```

---

**Ready to integrate!** If you have questions, check the reference files above.
