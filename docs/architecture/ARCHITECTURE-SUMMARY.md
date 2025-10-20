# Architecture Summary

## Overview

This document provides a high-level summary of the Notion-WP sync plugin architecture, designed for WordPress VIP standards with git worktree and Docker integration.

## Quick Navigation

- [Project Structure](project-structure.md) - Complete directory structure and architectural decisions
- [Worktree Architecture Diagram](worktree-architecture-diagram.md) - Visual diagrams of the worktree/Docker setup
- [Worktrees Guide](worktrees.md) - Git worktree concepts and Docker isolation strategies

## Core Principles

### 1. WordPress VIP Compliance

**Performance**:
- Custom database tables for scalability (avoid post meta queries at scale)
- Object caching via WordPress object cache API
- Query optimization (proper indexes, batch operations, pagination)
- Rate limiting for external API calls
- Action Scheduler for background processing

**Security**:
- All inputs sanitized via `sanitize_*()` functions
- All outputs escaped via `esc_*()` functions
- Nonces for form submissions
- Capability checks (`current_user_can()`)
- Prepared statements for database queries (`$wpdb->prepare()`)

**Code Quality**:
- PSR-4 autoloading
- Single Responsibility Principle
- Dependency injection for testability
- WordPress Coding Standards (PHPCS)
- Comprehensive PHPUnit test coverage

### 2. Extensibility

**For Developers**:
```php
// Register custom block converter
add_filter('notion_sync_block_converters', function($converters) {
    $converters['custom_block'] = MyCustomConverter::class;
    return $converters;
});

// Hook into sync process
add_action('notion_sync_before_import', function($notion_page) {
    // Custom pre-processing
});

add_action('notion_sync_after_import', function($wp_post_id, $notion_page) {
    // Custom post-processing
});

// Modify field mapping
add_filter('notion_sync_field_mapping', function($mapping, $database_id) {
    $mapping['custom_property'] = 'my_custom_field';
    return $mapping;
}, 10, 2);
```

**Configuration-Based Extensibility**:
- JSON config files for block mappings (no code changes needed)
- Field mapping UI for non-technical users
- Sync strategy selection (add-only, add-update, full-mirror)

### 3. Git Worktree Optimization

**What's Shared** (committed to Git):
- Plugin source code (`plugin/src/`)
- Asset source files (`plugin/assets/src/`)
- Docker infrastructure (`docker/`)
- Tests (`plugin/tests/`)
- Documentation (`docs/`)

**What's Isolated** (gitignored, per worktree):
- Environment config (`.env`)
- Runtime configs (`plugin/config/*.json`)
- Compiled assets (`plugin/assets/dist/`)
- Dependencies (`plugin/vendor/`, `plugin/node_modules/`)
- Logs (`logs/`)
- Docker volumes (database, WordPress files)

**Benefits**:
- Parallel feature development without branch switching
- Isolated testing environments
- No merge conflicts on generated files
- Fast setup via automation scripts

## Architecture Layers

### 1. Presentation Layer

**Admin Interface** (`src/Admin/`):
- Settings pages (WordPress Settings API)
- Field mapping UI (drag-and-drop or dropdowns)
- Sync dashboard (status, logs, manual triggers)
- AJAX handlers for real-time updates

**REST API** (`src/REST/`):
- Webhook endpoint for Notion notifications
- Manual sync triggers
- Status endpoints for monitoring

### 2. Application Layer

**Sync Orchestration** (`src/Sync/`):
- `SyncOrchestrator`: Main coordinator
- `NotionToWP`: Notion → WordPress logic
- `WPToNotion`: WordPress → Notion logic (optional)
- `ConflictResolver`: Handles timestamp conflicts
- `BatchProcessor`: Pagination and chunking

**Block Conversion** (`src/Converters/`):
- Registry pattern for converter lookup
- Interface-based design for consistency
- Separate converters for each block type
- Fallback converter for unsupported blocks

**Media Management** (`src/Media/`):
- Download from Notion's S3 URLs
- Upload to WordPress Media Library
- Deduplication via block ID mapping
- Cache for avoiding re-downloads

**Navigation** (`src/Navigation/`):
- Build WordPress menu from Notion hierarchy
- Convert internal Notion links to WP permalinks
- Maintain page parent/child relationships

### 3. Infrastructure Layer

**API Client** (`src/API/`):
- `NotionClient`: Wraps Notion API with retry logic
- `RateLimiter`: Enforces 50 req/sec limit
- `RequestLogger`: Logs all API calls for debugging

**Database** (`src/Database/`):
- Repository pattern for data access
- Custom tables for scalability:
  - `wp_notion_sync_mappings`: Notion ID ↔ WP Post ID
  - `wp_notion_sync_logs`: Sync history and errors
  - `wp_notion_sync_field_maps`: Field mapping configurations

**Queue** (`src/Queue/`):
- Action Scheduler integration
- Job classes for background tasks:
  - `ImportPageJob`: Import single Notion page
  - `ImportImageJob`: Download and upload image
  - `SyncDatabaseJob`: Sync entire Notion database
  - `PollNotionJob`: Scheduled polling for updates

**Caching** (`src/Caching/`):
- Object cache wrapper (uses WordPress object cache)
- Transient cache for temporary data
- Cache warming for frequently accessed data

## Key Design Patterns

### 1. Dependency Injection

```php
// Container-based DI
class SyncOrchestrator {
    public function __construct(
        NotionClient $notion_client,
        BlockConverterRegistry $converter_registry,
        SyncMappingRepository $mapping_repo
    ) {
        $this->notion_client = $notion_client;
        $this->converter_registry = $converter_registry;
        $this->mapping_repo = $mapping_repo;
    }
}

// Resolution
$orchestrator = Container::get(SyncOrchestrator::class);
```

**Benefits**:
- Loose coupling
- Easy testing (inject mocks)
- Swappable implementations

### 2. Repository Pattern

```php
// Repository interface
interface SyncMappingRepositoryInterface {
    public function find_wp_post_id($notion_id): ?int;
    public function save_mapping($notion_id, $wp_post_id): bool;
    public function delete_mapping($notion_id): bool;
}

// Implementation
class SyncMappingRepository implements SyncMappingRepositoryInterface {
    public function find_wp_post_id($notion_id): ?int {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT wp_post_id FROM {$wpdb->prefix}notion_sync_mappings WHERE notion_id = %s",
            $notion_id
        ));
    }
}
```

**Benefits**:
- Data access abstraction
- Easy to switch storage (e.g., Redis)
- Centralized query logic

### 3. Strategy Pattern

```php
// Sync strategies
interface SyncStrategyInterface {
    public function should_create($notion_page): bool;
    public function should_update($notion_page, $wp_post): bool;
    public function should_delete($wp_post): bool;
}

class AddOnlyStrategy implements SyncStrategyInterface {
    public function should_create($notion_page): bool { return true; }
    public function should_update($notion_page, $wp_post): bool { return false; }
    public function should_delete($wp_post): bool { return false; }
}

class FullMirrorStrategy implements SyncStrategyInterface {
    public function should_create($notion_page): bool { return true; }
    public function should_update($notion_page, $wp_post): bool { return true; }
    public function should_delete($wp_post): bool { return true; }
}
```

**Benefits**:
- User-configurable behavior
- Easy to add new strategies
- Clear separation of concerns

### 4. Registry Pattern

```php
// Block converter registry
class BlockConverterRegistry {
    private $converters = [];

    public function register($block_type, $converter_class) {
        $this->converters[$block_type] = $converter_class;
    }

    public function get_converter($block_type) {
        $converters = apply_filters('notion_sync_block_converters', $this->converters);
        $class = $converters[$block_type] ?? FallbackConverter::class;
        return Container::get($class);
    }
}
```

**Benefits**:
- Centralized converter management
- Extensible via filters
- Lazy loading of converters

## Database Schema

### Custom Tables

```sql
-- Notion ID to WordPress Post ID mapping
CREATE TABLE wp_notion_sync_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notion_id VARCHAR(255) NOT NULL,
    wp_post_id BIGINT UNSIGNED NOT NULL,
    notion_type VARCHAR(50) NOT NULL, -- 'page' or 'database'
    last_synced_at DATETIME NOT NULL,
    notion_last_edited DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_notion_id (notion_id),
    INDEX idx_wp_post_id (wp_post_id),
    UNIQUE KEY unique_mapping (notion_id, wp_post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sync logs for debugging
CREATE TABLE wp_notion_sync_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sync_type VARCHAR(50) NOT NULL, -- 'import', 'update', 'delete'
    notion_id VARCHAR(255),
    wp_post_id BIGINT UNSIGNED,
    status VARCHAR(20) NOT NULL, -- 'success', 'error', 'warning'
    message TEXT,
    context LONGTEXT, -- JSON data
    created_at DATETIME NOT NULL,
    INDEX idx_sync_type (sync_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Field mapping configurations
CREATE TABLE wp_notion_sync_field_maps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    database_id VARCHAR(255) NOT NULL,
    config LONGTEXT NOT NULL, -- JSON config
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY unique_database (database_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Post Meta Usage

```php
// Store Notion metadata in post meta
update_post_meta($wp_post_id, '_notion_id', $notion_id);
update_post_meta($wp_post_id, '_notion_last_edited', $notion_last_edited);
update_post_meta($wp_post_id, '_notion_url', $notion_url);

// Media attachment metadata
update_post_meta($attachment_id, '_notion_block_id', $block_id);
update_post_meta($attachment_id, '_notion_file_url', $original_url);
```

## Performance Considerations

### 1. Query Optimization

**Bad** (N+1 queries):
```php
$posts = get_posts(['post_type' => 'post']);
foreach ($posts as $post) {
    $notion_id = get_post_meta($post->ID, '_notion_id', true); // Query per post
}
```

**Good** (single query):
```php
global $wpdb;
$mappings = $wpdb->get_results("
    SELECT p.ID, pm.meta_value as notion_id
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'post' AND pm.meta_key = '_notion_id'
");
```

### 2. Batch Processing

```php
// Process in batches to avoid timeouts
$batch_size = 10;
$offset = 0;

do {
    $pages = $this->notion_client->query_database($database_id, [
        'page_size' => $batch_size,
        'start_cursor' => $offset
    ]);

    foreach ($pages['results'] as $page) {
        // Dispatch background job instead of processing synchronously
        $this->job_dispatcher->dispatch(ImportPageJob::class, [
            'notion_page' => $page
        ]);
    }

    $offset = $pages['next_cursor'];
} while ($pages['has_more']);
```

### 3. Caching Strategy

```php
// Cache Notion API responses
$cache_key = 'notion_page_' . $notion_id;
$page_data = wp_cache_get($cache_key, 'notion_sync');

if (false === $page_data) {
    $page_data = $this->notion_client->get_page($notion_id);
    wp_cache_set($cache_key, $page_data, 'notion_sync', HOUR_IN_SECONDS);
}
```

### 4. Rate Limiting

```php
class RateLimiter {
    private $max_requests = 50; // Notion API limit
    private $per_seconds = 1;

    public function throttle() {
        $current = get_transient('notion_api_requests');

        if ($current >= $this->max_requests) {
            sleep(1); // Wait for rate limit to reset
            delete_transient('notion_api_requests');
            $current = 0;
        }

        set_transient('notion_api_requests', $current + 1, $this->per_seconds);
    }
}
```

## Security Measures

### 1. Input Sanitization

```php
// Sanitize user inputs
$notion_token = sanitize_text_field($_POST['notion_token']);
$database_id = sanitize_text_field($_POST['database_id']);

// Validate format
if (!preg_match('/^secret_[a-zA-Z0-9]+$/', $notion_token)) {
    wp_die('Invalid Notion token format');
}
```

### 2. Output Escaping

```php
// Escape all output
<h1><?php echo esc_html($page_title); ?></h1>
<a href="<?php echo esc_url($notion_url); ?>">View in Notion</a>
<div><?php echo wp_kses_post($content); ?></div>
```

### 3. Capability Checks

```php
// Check user permissions
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

// REST API permission callback
public function check_permissions($request) {
    return current_user_can('edit_posts');
}
```

### 4. Nonce Verification

```php
// Form with nonce
wp_nonce_field('notion_sync_settings', 'notion_sync_nonce');

// Verify nonce
if (!isset($_POST['notion_sync_nonce']) ||
    !wp_verify_nonce($_POST['notion_sync_nonce'], 'notion_sync_settings')) {
    wp_die('Invalid nonce');
}
```

## Testing Strategy

### Unit Tests
```php
class ParagraphConverterTest extends WP_UnitTestCase {
    public function test_converts_simple_paragraph() {
        $converter = new ParagraphConverter();
        $notion_block = [
            'type' => 'paragraph',
            'paragraph' => [
                'rich_text' => [
                    ['plain_text' => 'Hello world']
                ]
            ]
        ];

        $result = $converter->convert($notion_block);
        $this->assertStringContainsString('<p>Hello world</p>', $result);
    }
}
```

### Integration Tests
```php
class SyncWorkflowTest extends WP_UnitTestCase {
    public function test_full_sync_workflow() {
        // Setup: Mock Notion API responses
        $this->mock_notion_api([
            'pages' => [/* ... */]
        ]);

        // Execute sync
        $orchestrator = Container::get(SyncOrchestrator::class);
        $orchestrator->sync_database('test-database-id');

        // Assert: WordPress posts created
        $posts = get_posts(['post_type' => 'post']);
        $this->assertCount(2, $posts);
        $this->assertEquals('Test Post', $posts[0]->post_title);
    }
}
```

## Deployment

### Production Checklist

1. **Environment Setup**:
   - Set production Notion token in `.env` or `wp-config.php`
   - Configure Action Scheduler for background jobs
   - Set up cron for scheduled polling
   - Enable webhook endpoint (if using real-time sync)

2. **Performance**:
   - Enable object caching (Redis/Memcached)
   - Configure transient caching
   - Set up CDN for media files
   - Optimize database indexes

3. **Security**:
   - Encrypt Notion token at rest
   - Use HTTPS for all API calls
   - Implement webhook signature verification
   - Set up rate limiting for REST endpoints

4. **Monitoring**:
   - Log sync operations
   - Set up error notifications
   - Monitor Action Scheduler queue
   - Track API rate limit usage

## Further Reading

- [Complete Project Structure](project-structure.md)
- [Worktree Visual Diagrams](worktree-architecture-diagram.md)
- [Git Worktrees Guide](worktrees.md)
- [Product Requirements](../product/prd.md)
- [Technical Requirements](../requirements/requirements.md)
- [WordPress VIP Coding Standards](https://docs.wpvip.com/technical-references/vip-codebase/)
