# Phase 4: Advanced Blocks - Quick Start Guide

## Environment Setup

### Worktree Location
```bash
cd /Users/patrick/Projects/thevgergroup/notion-wp/worktrees/phase-4-advanced-blocks
```

### Branch Info
- **Branch:** `phase-4-advanced-blocks`
- **Based on:** `main` (commit 82c6998)
- **Status:** 🚧 In Progress

## Implementation Order (Priority-Based)

### Week 1: Quote, Callout, Code, Toggle

**Day 1-2: Quote & Callout Blocks**
```bash
# Create converters
touch plugin/src/Blocks/Converters/QuoteConverter.php
touch plugin/src/Blocks/Converters/CalloutConverter.php

# Create tests
touch tests/unit/Blocks/Converters/QuoteConverterTest.php
touch tests/unit/Blocks/Converters/CalloutConverterTest.php

# Create CSS
touch plugin/assets/css/callout-blocks.css
```

**Day 3-4: Code & Toggle Blocks**
```bash
# Create converters
touch plugin/src/Blocks/Converters/CodeConverter.php
touch plugin/src/Blocks/Converters/ToggleConverter.php

# Create tests
touch tests/unit/Blocks/Converters/CodeConverterTest.php
touch tests/unit/Blocks/Converters/ToggleConverterTest.php

# Create CSS
touch plugin/assets/css/toggle-blocks.css
```

**Day 5: Integration & Testing**
```bash
# Run unit tests
composer test:unit

# Run linting
composer lint

# Manual testing with real Notion pages
```

### Week 2: Table, Column, Embed, Fallback

**Day 1-3: Table Block**
```bash
# Create converter (most complex)
touch plugin/src/Blocks/Converters/TableConverter.php
touch tests/unit/Blocks/Converters/TableConverterTest.php
```

**Day 4: Column Block**
```bash
touch plugin/src/Blocks/Converters/ColumnConverter.php
touch tests/unit/Blocks/Converters/ColumnConverterTest.php
```

**Day 5-6: Embed & Fallback**
```bash
touch plugin/src/Blocks/Converters/EmbedConverter.php
touch plugin/src/Blocks/Converters/FallbackConverter.php
touch tests/unit/Blocks/Converters/EmbedConverterTest.php
touch tests/unit/Blocks/Converters/FallbackConverterTest.php
```

**Day 7: Final Polish**
```bash
# Combine CSS files
cat plugin/assets/css/callout-blocks.css plugin/assets/css/toggle-blocks.css > plugin/assets/css/advanced-blocks.css

# Final testing
composer test
composer lint

# Create PR
```

## Testing Strategy

### Run Tests During Development
```bash
# Test specific converter
vendor/bin/phpunit tests/unit/Blocks/Converters/QuoteConverterTest.php

# Test all converters
vendor/bin/phpunit tests/unit/Blocks/Converters/

# Watch mode (re-run on file changes)
vendor/bin/phpunit --testdox --colors=always tests/unit/Blocks/Converters/ --watch
```

### Code Quality Checks
```bash
# PHPCS (WordPress Coding Standards)
composer lint:phpcs

# PHPStan (Static Analysis)
composer lint:phpstan

# All linting
composer lint
```

### Manual Testing Checklist

Create a test Notion page with:
- [ ] Quote block (simple text)
- [ ] Quote block (with formatting)
- [ ] Callout block (gray background, emoji icon)
- [ ] Callout block (different colors)
- [ ] Code block (JavaScript)
- [ ] Code block (Python)
- [ ] Toggle block (simple)
- [ ] Toggle block (nested content)
- [ ] Table (3x3, with headers)
- [ ] Table (complex formatting)
- [ ] Two-column layout
- [ ] Three-column layout
- [ ] YouTube embed
- [ ] Twitter embed
- [ ] Bookmark block

Sync to WordPress and verify:
- [ ] All blocks render correctly
- [ ] CSS styling works
- [ ] Mobile responsive
- [ ] Re-sync doesn't duplicate
- [ ] Can edit in Gutenberg

## Block Converter Template

Use this template for each new converter:

```php
<?php
/**
 * [Block Type] Block Converter
 *
 * Converts Notion [block_type] blocks to WordPress [output].
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Blocks\Converters;

/**
 * Converts Notion [block_type] blocks
 */
class [BlockType]Converter implements ConverterInterface {

    /**
     * Check if this converter supports the given block
     *
     * @param array $block Notion block data.
     * @return bool
     */
    public function supports(array $block): bool {
        return isset($block['type']) && '[block_type]' === $block['type'];
    }

    /**
     * Convert Notion block to WordPress content
     *
     * @param array $block Notion block data.
     * @return string WordPress block HTML.
     */
    public function convert(array $block): string {
        if (!$this->supports($block)) {
            return '';
        }

        // Extract block data
        $block_data = $block['[block_type]'] ?? [];

        // Convert to WordPress format
        // TODO: Implement conversion logic

        return '';
    }
}
```

## Test Template

```php
<?php
/**
 * Tests for [Block Type] Converter
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Tests\Unit\Blocks\Converters;

use NotionSync\Blocks\Converters\[BlockType]Converter;

/**
 * Test [BlockType]Converter functionality
 */
class [BlockType]ConverterTest extends BaseConverterTestCase {

    /**
     * Converter instance
     *
     * @var [BlockType]Converter
     */
    private [BlockType]Converter $converter;

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->converter = new [BlockType]Converter();
    }

    /**
     * Test supports method returns true for [block_type] blocks
     */
    public function test_supports_[block_type]_block_type(): void {
        $block = array('type' => '[block_type]');
        $this->assertTrue($this->converter->supports($block));

        $other_block = array('type' => 'paragraph');
        $this->assertFalse($this->converter->supports($other_block));
    }

    /**
     * Test basic [block_type] conversion
     */
    public function test_converts_simple_[block_type](): void {
        $notion_block = array(
            'type' => '[block_type]',
            '[block_type]' => array(
                // Add test data
            ),
        );

        $result = $this->converter->convert($notion_block);

        // Add assertions
        $this->assertStringContainsString('expected content', $result);
    }
}
```

## Converter Registration

After implementing each converter, register it in `BlockConverter.php`:

```php
// Around line 50 in plugin/src/Blocks/BlockConverter.php
$this->converters = [
    // Existing converters
    new ParagraphConverter(),
    new HeadingConverter(),
    new BulletedListConverter(),
    new NumberedListConverter(),
    new ImageConverter(),
    new FileConverter(),

    // Phase 4 converters (add as implemented)
    new QuoteConverter(),
    new CalloutConverter(),
    new CodeConverter(),
    new ToggleConverter(),
    new TableConverter(),
    new ColumnConverter(),
    new EmbedConverter(),

    // FallbackConverter MUST be last
    new FallbackConverter(),
];
```

## CSS Integration

Enqueue the advanced blocks CSS in the main plugin file:

```php
// In plugin/notion-sync.php or create plugin/src/Assets.php

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'notion-sync-advanced-blocks',
        NOTION_SYNC_URL . 'assets/css/advanced-blocks.css',
        [],
        NOTION_WP_VERSION
    );
});
```

## Common Notion Block Structures

### Quote Block
```json
{
    "type": "quote",
    "quote": {
        "rich_text": [
            {
                "type": "text",
                "text": {"content": "Quote text"},
                "plain_text": "Quote text"
            }
        ],
        "color": "default"
    }
}
```

### Callout Block
```json
{
    "type": "callout",
    "callout": {
        "rich_text": [...],
        "icon": {
            "type": "emoji",
            "emoji": "💡"
        },
        "color": "gray_background"
    }
}
```

### Code Block
```json
{
    "type": "code",
    "code": {
        "rich_text": [{
            "text": {"content": "code here"}
        }],
        "language": "javascript",
        "caption": []
    }
}
```

### Toggle Block
```json
{
    "type": "toggle",
    "toggle": {
        "rich_text": [{"text": {"content": "Toggle title"}}],
        "color": "default"
    },
    "has_children": true
}
```

### Table Block
```json
{
    "type": "table",
    "table": {
        "table_width": 3,
        "has_column_header": true,
        "has_row_header": false
    },
    "has_children": true
}
```

## Git Workflow

### Committing Work
```bash
# After implementing each converter
git add plugin/src/Blocks/Converters/QuoteConverter.php
git add tests/unit/Blocks/Converters/QuoteConverterTest.php
git commit -m "feat(blocks): add QuoteConverter with tests

- Converts Notion quote blocks to WordPress quote blocks
- Preserves formatting and colors
- 90% test coverage

Tests: 8 passing"
```

### Creating PR
```bash
# When phase complete
git push origin phase-4-advanced-blocks

gh pr create \
  --title "feat: Phase 4 - Advanced Blocks Support" \
  --body "Implements support for 8 additional Notion block types:
- Quote blocks
- Callout blocks
- Code blocks (with language support)
- Toggle blocks
- Table blocks
- Column layouts
- Embed blocks (YouTube, Twitter, etc.)
- Fallback handling for unsupported types

All converters have 80%+ test coverage.
CSS styling included for custom blocks."
```

## Performance Considerations

### Blocks with Children (Toggle, Table, Column)

These blocks require additional API calls:

```php
// In ToggleConverter::convert()
if ($block['has_children']) {
    $children = $this->fetch_children($block['id']);
    $content .= $this->convert_children($children);
}

// Use NotionClient to fetch children
private function fetch_children(string $block_id): array {
    $notion_client = new NotionClient();
    return $notion_client->get_block_children($block_id);
}
```

**Optimization:** Cache children blocks to avoid duplicate API calls on re-sync.

## Troubleshooting

### Tests Failing
```bash
# Run with verbose output
vendor/bin/phpunit --testdox --colors=always tests/unit/Blocks/Converters/QuoteConverterTest.php

# Check for syntax errors
composer lint:phpcs plugin/src/Blocks/Converters/QuoteConverter.php
```

### CSS Not Loading
```bash
# Check file exists
ls -la plugin/assets/css/advanced-blocks.css

# Verify enqueue in plugin
grep -r "wp_enqueue_style" plugin/

# Clear WordPress cache
wp cache flush  # If using WP-CLI
```

### Converter Not Running
```bash
# Verify registration in BlockConverter
grep "QuoteConverter" plugin/src/Blocks/BlockConverter.php

# Check converter order (FallbackConverter must be last)
```

## Documentation

As you implement each converter, update:

1. **User Docs:** `docs/user-guide.md` - Add block type to supported list
2. **API Docs:** Add inline PHPDoc comments
3. **Changelog:** `CHANGELOG.md` - Note new block support
4. **Main Plan:** Mark success criteria as complete

## Success Indicators

You know Phase 4 is complete when:

- [ ] All 8 converters implemented
- [ ] All tests passing (80%+ coverage)
- [ ] Manual testing checklist complete
- [ ] CSS works in Twenty Twenty-Four theme
- [ ] No PHPCS/PHPStan errors
- [ ] PR approved and ready to merge
- [ ] Can demo all block types in < 10 minutes

## Next Phase

After Phase 4 is complete and merged:
- **Phase 5:** Hierarchy & Navigation (page parent/child relationships, menu generation)
- **Phase 6:** Polish & Release (WordPress.org submission prep)
