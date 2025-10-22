# Link Registry & Router System

**Status:** 📋 PLANNED
**Version:** v0.4-dev
**Priority:** HIGH - Foundation for all internal link handling

## 🎯 Overview

Instead of constantly rescanning post content to rewrite Notion links, we maintain a central registry that maps Notion IDs to WordPress resources. A custom router handles `/notion/{slug}` URLs intelligently - serving WordPress content when available, redirecting to Notion when not synced.

## 🧠 Architecture Decision

**Problem:**
- Current approach requires rescanning all post content after each sync
- Links embedded in content become stale
- Performance degrades with content volume
- No single source of truth for Notion ↔ WordPress mappings

**Solution:**
- **Link Registry Table** - Central mapping of Notion IDs → WordPress resources
- **URL Router** - Handles `/notion/{slug}` requests dynamically
- **Block Converters** - Output registry links instead of direct permalinks
- **Sync Integration** - Updates registry automatically during sync

**Benefits:**
✅ **Performance** - No content rescanning needed
✅ **Consistency** - Single source of truth
✅ **Flexibility** - Links work before AND after sync
✅ **Clean URLs** - `/notion/ai-education` vs `?page=notion-sync-view-database&post_id=7`
✅ **Future-proof** - Easy to add analytics, redirects, broken link detection

## 📊 Database Schema

### Table: `wp_notion_links`

```sql
CREATE TABLE wp_notion_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Notion Identifiers (both formats for lookup)
    notion_id VARCHAR(32) NOT NULL COMMENT 'Notion ID without dashes',
    notion_id_uuid VARCHAR(36) NOT NULL COMMENT 'Notion ID with dashes (UUID format)',

    -- Resource Type
    notion_type ENUM('page', 'database') NOT NULL,

    -- Notion Metadata
    notion_title TEXT NOT NULL,
    notion_url VARCHAR(500) COMMENT 'Original Notion URL',

    -- WordPress Mapping (nullable until synced)
    wp_post_id BIGINT UNSIGNED NULL COMMENT 'WordPress post ID if synced',
    wp_post_type VARCHAR(20) NULL COMMENT 'post, page, notion_database, etc',

    -- URL Slug (for /notion/{slug} routes)
    slug VARCHAR(200) NOT NULL UNIQUE,

    -- Access Tracking
    access_count INT UNSIGNED DEFAULT 0,
    last_accessed_at DATETIME NULL,

    -- Sync Status
    sync_status ENUM('not_synced', 'synced', 'deleted') DEFAULT 'not_synced',

    -- Timestamps
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    -- Indexes
    KEY notion_id (notion_id),
    KEY notion_id_uuid (notion_id_uuid),
    KEY slug (slug),
    KEY wp_post_id (wp_post_id),
    KEY notion_type (notion_type),
    KEY sync_status (sync_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Design Decisions:**

1. **Dual ID Storage** - Both formats (with/without dashes) for flexible lookups
2. **Nullable wp_post_id** - Links can be registered before sync
3. **Unique Slug** - Human-readable URLs, auto-generated from title
4. **Access Tracking** - Analytics for future features
5. **Sync Status** - Track lifecycle (not_synced → synced → deleted)

## 🔀 URL Routing System

### Rewrite Rules

Add WordPress rewrite rule in `plugin/src/Router/NotionRouter.php`:

```php
<?php
namespace NotionSync\Router;

class NotionRouter {

    /**
     * Register rewrite rules for /notion/{slug} URLs.
     */
    public function register_rewrite_rules(): void {
        add_rewrite_rule(
            '^notion/([^/]+)/?$',
            'index.php?notion_link=$matches[1]',
            'top'
        );

        add_rewrite_tag( '%notion_link%', '([^&]+)' );
    }

    /**
     * Handle /notion/{slug} requests.
     *
     * @param \WP $wp WordPress environment object.
     */
    public function route_request( \WP $wp ): void {
        if ( ! isset( $wp->query_vars['notion_link'] ) ) {
            return;
        }

        $slug = sanitize_title( $wp->query_vars['notion_link'] );

        // Look up in registry
        $link_entry = $this->registry->find_by_slug( $slug );

        if ( ! $link_entry ) {
            // Slug not found - 404
            status_header( 404 );
            include get_404_template();
            exit;
        }

        // Handle based on sync status
        if ( 'synced' === $link_entry->sync_status && $link_entry->wp_post_id ) {
            // Synced to WordPress - redirect to WordPress permalink
            $this->serve_wordpress_content( $link_entry );
        } else {
            // Not synced - redirect to Notion
            $this->redirect_to_notion( $link_entry );
        }
    }

    /**
     * Serve WordPress content.
     *
     * @param object $link_entry Link registry entry.
     */
    private function serve_wordpress_content( object $link_entry ): void {
        // Track access
        $this->registry->increment_access_count( $link_entry->id );

        if ( 'database' === $link_entry->notion_type ) {
            // Redirect to database viewer
            $url = add_query_arg(
                array(
                    'page'    => 'notion-sync-view-database',
                    'post_id' => $link_entry->wp_post_id,
                ),
                admin_url( 'admin.php' )
            );
            wp_safe_redirect( $url, 302 );
        } else {
            // Redirect to post/page permalink
            $permalink = get_permalink( $link_entry->wp_post_id );

            if ( $permalink ) {
                wp_safe_redirect( $permalink, 302 );
            } else {
                // Post deleted - fall back to Notion
                $this->redirect_to_notion( $link_entry );
            }
        }

        exit;
    }

    /**
     * Redirect to Notion.
     *
     * @param object $link_entry Link registry entry.
     */
    private function redirect_to_notion( object $link_entry ): void {
        // Track access
        $this->registry->increment_access_count( $link_entry->id );

        // Redirect to Notion URL
        $notion_url = 'https://notion.so/' . $link_entry->notion_id;
        wp_safe_redirect( $notion_url, 302 );
        exit;
    }
}
```

### URL Examples

| Original Notion URL | Registry Entry | Public URL | Behavior |
|---------------------|----------------|------------|----------|
| `https://notion.so/4349fe02...` | `slug: 'ai-education-resources'`<br>`wp_post_id: 7`<br>`sync_status: 'synced'` | `/notion/ai-education-resources` | ✅ Redirects to WP admin database viewer |
| `https://notion.so/75424b1c...` | `slug: 'ai-fundamentals'`<br>`wp_post_id: 9`<br>`sync_status: 'synced'` | `/notion/ai-fundamentals` | ✅ Redirects to WordPress post permalink |
| `https://notion.so/6218660...` | `slug: 'interactive-ai-tools'`<br>`wp_post_id: null`<br>`sync_status: 'not_synced'` | `/notion/interactive-ai-tools` | ↗️ Redirects to Notion |

## 📝 Link Registry Repository

### File: `plugin/src/Router/LinkRegistry.php`

```php
<?php
namespace NotionSync\Router;

/**
 * Link Registry Repository
 *
 * Manages the wp_notion_links table - central mapping of Notion resources.
 */
class LinkRegistry {

    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'notion_links';
    }

    /**
     * Register or update a link entry.
     *
     * Called during sync operations to register newly discovered links.
     *
     * @param array $args {
     *     @type string $notion_id       Notion ID without dashes (required)
     *     @type string $notion_title    Human-readable title (required)
     *     @type string $notion_type     'page' or 'database' (required)
     *     @type int    $wp_post_id      WordPress post ID (optional)
     *     @type string $wp_post_type    WordPress post type (optional)
     *     @type string $slug            Custom slug (optional, auto-generated from title)
     * }
     * @return int|false Link entry ID or false on failure.
     */
    public function register( array $args ) {
        global $wpdb;

        // Validate required fields
        if ( empty( $args['notion_id'] ) || empty( $args['notion_title'] ) || empty( $args['notion_type'] ) ) {
            return false;
        }

        // Convert notion_id to UUID format (with dashes)
        $notion_id_uuid = $this->format_as_uuid( $args['notion_id'] );

        // Generate slug if not provided
        $slug = $args['slug'] ?? $this->generate_slug( $args['notion_title'], $args['notion_id'] );

        // Check if entry exists
        $existing = $this->find_by_notion_id( $args['notion_id'] );

        $data = array(
            'notion_id'      => $args['notion_id'],
            'notion_id_uuid' => $notion_id_uuid,
            'notion_type'    => $args['notion_type'],
            'notion_title'   => $args['notion_title'],
            'notion_url'     => 'https://notion.so/' . $args['notion_id'],
            'slug'           => $slug,
            'sync_status'    => isset( $args['wp_post_id'] ) ? 'synced' : 'not_synced',
            'updated_at'     => current_time( 'mysql' ),
        );

        // Add wp_post_id if synced
        if ( isset( $args['wp_post_id'] ) ) {
            $data['wp_post_id'] = $args['wp_post_id'];
            $data['wp_post_type'] = $args['wp_post_type'] ?? get_post_type( $args['wp_post_id'] );
        }

        if ( $existing ) {
            // Update existing entry
            $result = $wpdb->update(
                $this->table_name,
                $data,
                array( 'id' => $existing->id ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
                array( '%d' )
            );

            return $result !== false ? $existing->id : false;
        }

        // Insert new entry
        $data['created_at'] = current_time( 'mysql' );

        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Find link entry by Notion ID.
     *
     * Handles both formats (with and without dashes).
     *
     * @param string $notion_id Notion ID (with or without dashes).
     * @return object|null Link entry or null if not found.
     */
    public function find_by_notion_id( string $notion_id ): ?object {
        global $wpdb;

        // Normalize ID (remove dashes)
        $notion_id_normalized = str_replace( '-', '', $notion_id );
        $notion_id_uuid = $this->format_as_uuid( $notion_id_normalized );

        $entry = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE notion_id = %s OR notion_id_uuid = %s
             LIMIT 1",
            $notion_id_normalized,
            $notion_id_uuid
        ) );

        return $entry ?: null;
    }

    /**
     * Find link entry by slug.
     *
     * @param string $slug URL slug.
     * @return object|null Link entry or null if not found.
     */
    public function find_by_slug( string $slug ): ?object {
        global $wpdb;

        $entry = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE slug = %s LIMIT 1",
            $slug
        ) );

        return $entry ?: null;
    }

    /**
     * Update link when synced to WordPress.
     *
     * Called after successfully syncing a page/database.
     *
     * @param string $notion_id   Notion ID.
     * @param int    $wp_post_id  WordPress post ID.
     * @return bool Success status.
     */
    public function mark_as_synced( string $notion_id, int $wp_post_id ): bool {
        global $wpdb;

        $entry = $this->find_by_notion_id( $notion_id );

        if ( ! $entry ) {
            return false;
        }

        return false !== $wpdb->update(
            $this->table_name,
            array(
                'wp_post_id'   => $wp_post_id,
                'wp_post_type' => get_post_type( $wp_post_id ),
                'sync_status'  => 'synced',
                'updated_at'   => current_time( 'mysql' ),
            ),
            array( 'id' => $entry->id ),
            array( '%d', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Increment access counter for analytics.
     *
     * @param int $link_id Link entry ID.
     * @return bool Success status.
     */
    public function increment_access_count( int $link_id ): bool {
        global $wpdb;

        return false !== $wpdb->query( $wpdb->prepare(
            "UPDATE {$this->table_name}
             SET access_count = access_count + 1,
                 last_accessed_at = %s
             WHERE id = %d",
            current_time( 'mysql' ),
            $link_id
        ) );
    }

    /**
     * Generate unique slug from title and Notion ID.
     *
     * @param string $title      Notion page/database title.
     * @param string $notion_id  Notion ID (fallback if slug collision).
     * @return string Unique slug.
     */
    private function generate_slug( string $title, string $notion_id ): string {
        global $wpdb;

        // Sanitize title to slug format
        $base_slug = sanitize_title( $title );

        if ( empty( $base_slug ) ) {
            // Title couldn't be converted to slug - use Notion ID
            $base_slug = $notion_id;
        }

        // Check for uniqueness
        $slug = $base_slug;
        $counter = 1;

        while ( $this->slug_exists( $slug ) ) {
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists.
     *
     * @param string $slug Slug to check.
     * @return bool True if exists.
     */
    private function slug_exists( string $slug ): bool {
        global $wpdb;

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE slug = %s",
            $slug
        ) );

        return $count > 0;
    }

    /**
     * Format Notion ID as UUID (add dashes).
     *
     * @param string $notion_id Notion ID without dashes (32 chars).
     * @return string UUID format with dashes (36 chars).
     */
    private function format_as_uuid( string $notion_id ): string {
        // Remove any existing dashes
        $notion_id = str_replace( '-', '', $notion_id );

        // Format: 8-4-4-4-12
        return substr( $notion_id, 0, 8 ) . '-' .
               substr( $notion_id, 8, 4 ) . '-' .
               substr( $notion_id, 12, 4 ) . '-' .
               substr( $notion_id, 16, 4 ) . '-' .
               substr( $notion_id, 20, 12 );
    }
}
```

## 🔗 Integration Points

### 1. During Block Conversion

**Current Code (LinkRewriter):**
```php
// Old approach - tries to find synced post and rewrite directly
$permalink = LinkRewriter::rewrite_url_string( 'https://notion.so/abc123...' );
// Returns: WordPress permalink or original Notion URL

// Output in content:
<a href="<?php echo esc_url( $permalink ); ?>">Link Text</a>
```

**New Approach (Link Registry):**
```php
// Register link in database (idempotent)
$registry->register( array(
    'notion_id'    => 'abc123...',
    'notion_title' => 'AI Education Resources',
    'notion_type'  => 'database',
    'wp_post_id'   => null, // Not synced yet
) );

// Get slug for URL
$slug = $registry->get_slug_for_notion_id( 'abc123...' );

// Output in content:
<a href="/notion/<?php echo esc_attr( $slug ); ?>">Link Text</a>
```

### 2. During Page/Database Sync

**After Successful Sync:**
```php
// Page was synced to WordPress
$post_id = 123; // Created WordPress post ID
$notion_id = 'abc123...'; // Notion page ID

// Update registry to mark as synced
$registry->mark_as_synced( $notion_id, $post_id );

// Now /notion/{slug} will redirect to WordPress instead of Notion
```

### 3. Link Discovery (Automatic Registration)

**When Converting Blocks:**
```php
// In block converter, when encountering a Notion link
preg_match_all( '/notion\.so\/([a-f0-9-]{32,36})/', $content, $matches );

foreach ( $matches[1] as $notion_id ) {
    // Check if already registered
    if ( ! $registry->find_by_notion_id( $notion_id ) ) {
        // Auto-register with placeholder title
        $registry->register( array(
            'notion_id'    => $notion_id,
            'notion_title' => 'Link to Notion Page',
            'notion_type'  => 'page', // Default assumption
        ) );
    }
}
```

## 📐 Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        User Request                              │
│                   GET /notion/ai-education                       │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                                ▼
                ┌───────────────────────────────┐
                │    WordPress Rewrite Rules    │
                │  (NotionRouter::register())   │
                └───────────────┬───────────────┘
                                │
                                ▼
                ┌───────────────────────────────┐
                │   Route Request Handler       │
                │  (NotionRouter::route())      │
                └───────────────┬───────────────┘
                                │
                                ▼
                ┌───────────────────────────────┐
                │      Link Registry Lookup     │
                │  SELECT * FROM wp_notion_links│
                │  WHERE slug = 'ai-education'  │
                └───────────────┬───────────────┘
                                │
                    ┌───────────┴───────────┐
                    │                       │
           ┌────────▼────────┐    ┌────────▼─────────┐
           │   Not Found     │    │   Entry Found    │
           │   (404)         │    │                  │
           └─────────────────┘    └────────┬─────────┘
                                            │
                                ┌───────────┴─────────────┐
                                │                         │
                    ┌───────────▼──────────┐  ┌──────────▼──────────┐
                    │  sync_status:        │  │  sync_status:       │
                    │  'synced'            │  │  'not_synced'       │
                    │  wp_post_id: 7       │  │  wp_post_id: NULL   │
                    └───────────┬──────────┘  └──────────┬──────────┘
                                │                        │
                ┌───────────────▼──────────┐  ┌─────────▼──────────┐
                │  Get WordPress Permalink │  │  Build Notion URL  │
                │  get_permalink( 7 )      │  │  notion.so/abc123  │
                └───────────────┬──────────┘  └─────────┬──────────┘
                                │                        │
                ┌───────────────▼──────────┐  ┌─────────▼──────────┐
                │  302 Redirect to WP      │  │  302 Redirect to   │
                │  /ai-education-resources │  │  Notion            │
                └──────────────────────────┘  └────────────────────┘
```

## 🔄 Sync Workflow

### Before Sync (Link Discovery)

1. **Block Converter** encounters Notion link in content
2. **Extract Notion ID** from URL
3. **Check Registry** - Is it already registered?
4. **Auto-Register** if not found (with placeholder info)
5. **Get Slug** from registry
6. **Output** `/notion/{slug}` in converted content

### During Sync

1. **Page/Database Synced** to WordPress (post ID created)
2. **Update Registry** - Mark as synced, store wp_post_id
3. **No Content Rewriting Needed** - Links already point to `/notion/{slug}`

### After Sync

1. **User Clicks** `/notion/ai-education-resources`
2. **Router Checks** registry for slug
3. **Finds** entry with `sync_status: 'synced', wp_post_id: 7`
4. **Redirects** to WordPress permalink or database viewer
5. **Tracks** access count for analytics

## 🚀 Implementation Phases

### Phase 1: Database Schema & Registry (1-2 days)

**Files:**
- `plugin/src/Database/Schema.php` - Add table creation
- `plugin/src/Router/LinkRegistry.php` - Repository class

**Tasks:**
1. Add `wp_notion_links` table to Schema::create_tables()
2. Implement LinkRegistry CRUD methods
3. Write unit tests for registry operations
4. Test UUID format conversion

### Phase 2: URL Router (1-2 days)

**Files:**
- `plugin/src/Router/NotionRouter.php` - Route handler
- `plugin/notion-sync.php` - Register hooks

**Tasks:**
1. Add rewrite rules registration
2. Implement route_request() handler
3. Add redirect logic (synced vs not_synced)
4. Test with sample URLs
5. Flush rewrite rules on activation

### Phase 3: Sync Integration (1-2 days)

**Files:**
- `plugin/src/Sync/SyncManager.php` - Register links after sync
- `plugin/src/Sync/BatchProcessor.php` - Register database links
- `plugin/src/Blocks/LinkRewriter.php` - Use registry instead of direct rewrites

**Tasks:**
1. Call registry->register() after page sync
2. Call registry->mark_as_synced() after successful sync
3. Update LinkRewriter to use registry
4. Update block converters to output `/notion/{slug}` links

### Phase 4: Block Converter Updates (1 day)

**Files:**
- `plugin/src/Blocks/NotionBlockConverter.php` - Auto-register discovered links

**Tasks:**
1. Scan converted content for Notion links
2. Auto-register any discovered links
3. Replace Notion URLs with `/notion/{slug}` format
4. Test with various link formats

### Phase 5: Testing & Polish (1 day)

**Tasks:**
1. Integration testing with real Notion content
2. Test link discovery and registration
3. Test routing behavior (synced vs not_synced)
4. Performance testing (1000+ links)
5. Add admin UI to view/manage link registry

## 📊 Success Criteria

- [ ] Can register Notion links before sync
- [ ] `/notion/{slug}` redirects to WordPress when synced
- [ ] `/notion/{slug}` redirects to Notion when not synced
- [ ] Links work immediately after first conversion
- [ ] No content rescanning needed after sync
- [ ] Slug generation handles duplicates gracefully
- [ ] UUID format conversion works both directions
- [ ] Access tracking increments correctly
- [ ] Link registry admin UI shows all entries
- [ ] Performance: 1000+ links with <100ms lookup

## 🔮 Future Enhancements

**Link Analytics:**
- Track which links are accessed most
- Identify broken links (deleted posts)
- Report on unsync'd links to prioritize syncs

**Smart Redirects:**
- Preserve fragment identifiers (#section)
- Handle query parameters
- Support custom redirect rules

**Link Management UI:**
- Admin page to view all registry entries
- Bulk operations (delete, regenerate slugs)
- Manual slug editing
- Sync status indicators

**SEO Optimization:**
- Generate proper meta tags for `/notion/{slug}` URLs
- Add canonical URLs
- Implement 301 vs 302 redirect strategies

## 📝 Migration from Current System

**Backwards Compatibility:**

1. **Existing Posts** - Run migration script to:
   - Scan all post content for Notion links
   - Register discovered links in registry
   - Replace links with `/notion/{slug}` format

2. **LinkRewriter Deprecation** - Keep as fallback but:
   - Primary method: Use LinkRegistry
   - LinkRewriter: Only for legacy support

**Migration Script:**

```php
<?php
// wp-cli command: wp notion-sync migrate-links

function migrate_to_link_registry() {
    $registry = new \NotionSync\Router\LinkRegistry();

    // Find all posts with notion_page_id meta
    $synced_posts = get_posts( array(
        'post_type'      => 'any',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => 'notion_page_id',
                'compare' => 'EXISTS',
            ),
        ),
    ) );

    foreach ( $synced_posts as $post ) {
        // Extract and register all Notion links from content
        preg_match_all(
            '/notion\.so\/([a-f0-9-]{32,36})/',
            $post->post_content,
            $matches
        );

        foreach ( $matches[1] as $notion_id ) {
            $registry->register( array(
                'notion_id'    => str_replace( '-', '', $notion_id ),
                'notion_title' => 'Migrated Link',
                'notion_type'  => 'page',
            ) );
        }

        // Replace links in content with /notion/{slug} format
        $updated_content = preg_replace_callback(
            '/https:\/\/notion\.so\/([a-f0-9-]{32,36})/',
            function( $matches ) use ( $registry ) {
                $notion_id = str_replace( '-', '', $matches[1] );
                $entry = $registry->find_by_notion_id( $notion_id );

                if ( $entry ) {
                    return site_url( '/notion/' . $entry->slug );
                }

                return $matches[0]; // Keep original if not found
            },
            $post->post_content
        );

        // Update post if content changed
        if ( $updated_content !== $post->post_content ) {
            wp_update_post( array(
                'ID'           => $post->ID,
                'post_content' => $updated_content,
            ) );
        }
    }
}
```

## ✅ Definition of Done

- [ ] Link registry table created and populated
- [ ] URL routing works for `/notion/{slug}` requests
- [ ] Block converters output registry links
- [ ] Sync operations update registry automatically
- [ ] Migration script converts existing content
- [ ] All tests passing (unit + integration)
- [ ] Documentation complete
- [ ] Admin UI for link management
- [ ] Performance benchmarks met (<100ms lookups)
- [ ] Backwards compatibility maintained

---

## 📚 References

- **Rewrite API**: https://developer.wordpress.org/reference/functions/add_rewrite_rule/
- **Custom Routing**: https://wordpress.stackexchange.com/questions/17385/custom-post-type-permalinks
- **URL Slugs**: https://developer.wordpress.org/reference/functions/sanitize_title/
