# Phase 2: Database Sync to JSON Storage

**Status:** 🔜 NOT STARTED
**Duration:** 1-2 weeks (estimated)
**Complexity:** M (Medium)
**Version Target:** v0.3-dev

## 🎯 Goal

Sync Notion databases to WordPress as **structured JSON data** in a custom table. Each database becomes a WordPress CPT post for metadata, with rows stored efficiently as JSON documents for future flexibility.

**User Story:** "As a WordPress admin, I can select a Notion database and sync all entries as structured data to WordPress, viewing and searching the synced content in the admin interface."

## 🧠 Architecture Decision

**Database Representation:**

- **Notion Database** → WordPress CPT post (stores metadata: title, ID, last synced)
- **Database Rows** → JSON documents in custom table `wp_notion_database_rows`
- **NOT creating individual posts** for each row (this maintains data integrity and simplifies management)

**Why JSON Storage?**

- Preserves Notion's flexible property structure
- Handles arbitrary property types without schema changes
- Enables future features (search, filtering, custom views)
- Avoids WordPress post table bloat
- Simpler maintenance than hundreds/thousands of posts

**MySQL Compatibility:**

- Use `LONGTEXT` column type (MySQL 5.5+ compatible)
- Store JSON via `json_encode()` / `json_decode()` in PHP
- Extract key fields (title, status, dates) for indexing
- WordPress minimum: MySQL 5.5 or MariaDB 10.0

## ✅ Success Criteria (Gatekeeping)

**DO NOT PROCEED to Phase 3 until ALL criteria are met:**

### Core Functionality

- [ ] User can see list of Notion databases in admin
- [ ] Can sync entire database with one click
- [ ] All database entries stored as JSON in custom table
- [ ] Database CPT posts track sync status and metadata
- [ ] Can view synced rows in admin interface
- [ ] Basic search/filter functionality for rows
- [ ] Re-sync updates existing rows (no duplicates)
- [ ] Batch processing handles 100+ entries without timeout

### Performance Requirements

- [ ] Syncs 100 database entries in under 5 minutes
- [ ] Handles databases with 500+ entries
- [ ] No memory limit errors on large batches
- [ ] Uses Action Scheduler for background processing

### Quality Requirements

- [ ] Re-sync updates existing data (no duplicates created)
- [ ] Zero PHP warnings (all Phase 1 linting warnings eliminated)
- [ ] All linting passes (PHPCS, ESLint, PHPStan level 3+)
- [ ] Zero console errors or PHP notices
- [ ] **Can be demoed to a non-developer in under 5 minutes**

## 📋 Dependencies

**Required from Phase 1 (COMPLETED ✅):**

- ✅ NotionClient API wrapper
- ✅ Admin UI infrastructure
- ✅ AJAX handlers
- ✅ Security (nonce, encryption)

**Pre-Phase 2 Cleanup Required:**

- [x] Eliminate critical PHPCS warnings (completed in Stream 0)

## 🔀 Parallel Work Streams

### Stream 0: Code Cleanup (COMPLETED ✅)

**Worktree:** `phase-2-database-sync`
**Duration:** 1-2 days
**Status:** ✅ COMPLETE

**What Was Fixed:**

Critical PHPCS warnings eliminated:

1. ✅ **error_log() calls** (9 instances) - Added phpcs:ignore comments
2. ✅ **Reserved keyword warning** (1 instance) - Renamed $class → $class_name
3. ✅ **file_get_contents() in tests** (1 instance) - Added phpcs:ignore for fixtures

**Remaining:** ~28 cosmetic line length warnings in templates (ACCEPTED)

**Definition of Done:**

- [x] Critical warnings fixed
- [x] Code functionality unchanged
- [x] Committed: "chore: fix critical PHPCS warnings for Phase 2 prep"

---

### Stream 1: Database Query System

**Worktree:** `phase-2-database-sync`
**Duration:** 2-3 days
**Files Created:** 2 new files, all <400 lines

**What This Builds:**

- Query Notion databases (not just individual pages)
- Fetch all database entries with pagination
- Retrieve database schema (properties/columns)
- Admin UI to list available databases

**Technical Implementation:**

**File 1:** `plugin/src/Sync/DatabaseFetcher.php` (<400 lines)

```php
<?php
namespace NotionSync\Sync;

use NotionSync\API\NotionClient;

/**
 * Fetches Notion database entries and schema.
 *
 * Handles pagination, property extraction, and database metadata.
 */
class DatabaseFetcher {
    private NotionClient $client;

    public function __construct( NotionClient $client ) {
        $this->client = $client;
    }

    /**
     * Query all entries from a Notion database.
     *
     * Handles pagination automatically (Notion returns max 100 per request).
     *
     * @param string $database_id Notion database ID.
     * @param array  $filters     Optional query filters.
     * @param array  $sorts       Optional sort configuration.
     * @return array Array of database page objects.
     */
    public function query_database(
        string $database_id,
        array $filters = [],
        array $sorts = []
    ): array {
        // POST /databases/{database_id}/query
        // Handle pagination cursor (100 entries per request)
        // Return array of page objects with properties
    }

    /**
     * Get database schema (properties/columns).
     *
     * @param string $database_id Notion database ID.
     * @return array Database properties configuration.
     */
    public function get_database_schema( string $database_id ): array {
        // GET /databases/{database_id}
        // Extract properties definition
        // Return property types and configurations
    }

    /**
     * Get all accessible databases for current integration.
     *
     * @return array List of databases with metadata.
     */
    public function get_databases(): array {
        // Search API for type='database'
        // Return database metadata (id, title, last_edited)
    }

    /**
     * Extract property value from database page.
     *
     * Converts Notion property format to simple PHP value.
     *
     * @param array  $page          Database page object.
     * @param string $property_name Property name.
     * @return mixed Property value (varies by type).
     */
    private function extract_property_value( array $page, string $property_name ) {
        // Handle different property types:
        // - title -> string
        // - rich_text -> string
        // - number -> int/float
        // - select -> string
        // - multi_select -> array
        // - date -> string (ISO 8601)
        // - checkbox -> bool
        // - url, email, phone_number -> string
        // - relation -> array of IDs
        // - formula, rollup -> computed value
    }

    /**
     * Normalize database entry for storage.
     *
     * Extracts all properties into simple key-value format.
     *
     * @param array $entry Database page object from Notion API.
     * @return array Normalized entry ready for JSON storage.
     */
    public function normalize_entry( array $entry ): array {
        // Return structure:
        // [
        //   'id' => 'notion-page-id',
        //   'created_time' => '2024-01-15T10:30:00.000Z',
        //   'last_edited_time' => '2024-01-16T14:20:00.000Z',
        //   'properties' => [
        //     'Title' => 'Entry Title',
        //     'Status' => 'Published',
        //     'Tags' => ['tag1', 'tag2'],
        //     'Date' => '2024-01-15',
        //     // ... all other properties
        //   ]
        // ]
    }
}
```

**File 2:** `plugin/src/Admin/DatabasesListTable.php` (<350 lines)

Extends `WP_List_Table` to show available Notion databases:

```php
<?php
namespace NotionSync\Admin;

/**
 * List table for Notion databases.
 *
 * Shows all accessible databases with metadata and sync actions.
 */
class DatabasesListTable extends \WP_List_Table {

    public function get_columns(): array {
        return [
            'cb'          => '<input type="checkbox" />',
            'title'       => __( 'Database Title', 'notion-wp' ),
            'entries'     => __( 'Entries', 'notion-wp' ),
            'last_synced' => __( 'Last Synced', 'notion-wp' ),
            'wp_post'     => __( 'WordPress Post', 'notion-wp' ),
            'actions'     => __( 'Actions', 'notion-wp' ),
        ];
    }

    public function column_title( $item ): string {
        return sprintf(
            '<strong>%s</strong><br><code>%s</code>',
            esc_html( $item['title'] ),
            esc_html( $item['id'] )
        );
    }

    public function column_wp_post( $item ): string {
        // Check if database has been synced (has CPT post)
        $post_id = $this->find_database_post( $item['id'] );

        if ( $post_id ) {
            return sprintf(
                '<a href="%s">View Post #%d</a>',
                esc_url( get_edit_post_link( $post_id ) ),
                $post_id
            );
        }

        return '<em>' . esc_html__( 'Not synced yet', 'notion-wp' ) . '</em>';
    }

    public function column_actions( $item ): string {
        $post_id = $this->find_database_post( $item['id'] );

        return sprintf(
            '<a href="#" class="button button-small sync-database" data-database-id="%s">%s</a>',
            esc_attr( $item['id'] ),
            $post_id
                ? esc_html__( 'Re-sync', 'notion-wp' )
                : esc_html__( 'Sync Now', 'notion-wp' )
        );
    }
}
```

**Tasks:**

1. Create DatabaseFetcher class
2. Implement database query with pagination
3. Add database schema fetching
4. Implement property value extraction for common types
5. Create DatabasesListTable for admin UI
6. Add database list to settings page
7. Write unit tests for property extraction
8. Write integration tests for database queries

**Definition of Done:**

- [ ] Can query all entries from a Notion database
- [ ] Handles pagination correctly (tested with 200+ entries)
- [ ] Extracts common property types (title, select, multi-select, date, etc.)
- [ ] Admin UI shows list of databases with entry counts
- [ ] Unit tests pass for all property types
- [ ] Integration tests pass

---

### Stream 2: JSON Storage System

**Worktree:** `phase-2-database-sync`
**Duration:** 3-4 days
**Files Created:** 4 new files, all <400 lines

**What This Builds:**

- Custom database table for storing rows as JSON
- Database CPT for metadata
- Repository pattern for data access
- CRUD operations for synced data

**Technical Implementation:**

**File 1:** `plugin/src/Database/Schema.php` (<300 lines)

```php
<?php
namespace NotionSync\Database;

/**
 * Database schema manager.
 *
 * Creates and manages custom tables for Notion data storage.
 */
class Schema {
    /**
     * Create custom tables.
     *
     * Called on plugin activation.
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'notion_database_rows';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            database_post_id BIGINT UNSIGNED NOT NULL,
            notion_page_id VARCHAR(36) NOT NULL UNIQUE,

            -- JSON data stored as LONGTEXT (MySQL 5.5+ compatible)
            properties LONGTEXT NOT NULL,

            -- Extract key fields for indexing and queries
            title VARCHAR(500),
            status VARCHAR(50),
            created_time DATETIME,
            last_edited_time DATETIME,
            synced_at DATETIME NOT NULL,

            KEY database_post_id (database_post_id),
            KEY status (status),
            KEY created_time (created_time),
            KEY last_edited_time (last_edited_time),
            FULLTEXT title_fulltext (title)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Check if custom tables exist.
     *
     * @return bool True if tables exist.
     */
    public static function tables_exist(): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';
        return $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table_name
            )
        ) === $table_name;
    }

    /**
     * Drop custom tables.
     *
     * Called on plugin uninstall if user opts to delete data.
     */
    public static function drop_tables(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'notion_database_rows';
        $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
    }
}
```

**File 2:** `plugin/src/Database/DatabasePostType.php` (<250 lines)

```php
<?php
namespace NotionSync\Database;

/**
 * Custom Post Type for Notion Databases.
 *
 * Each Notion database becomes a CPT post storing metadata.
 */
class DatabasePostType {

    public const POST_TYPE = 'notion_database';

    /**
     * Register custom post type.
     */
    public function register(): void {
        register_post_type(
            self::POST_TYPE,
            [
                'labels' => [
                    'name' => __( 'Notion Databases', 'notion-wp' ),
                    'singular_name' => __( 'Notion Database', 'notion-wp' ),
                ],
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => false, // Accessed via settings page
                'capability_type' => 'post',
                'hierarchical' => false,
                'supports' => [ 'title' ],
                'has_archive' => false,
                'rewrite' => false,
            ]
        );
    }

    /**
     * Find or create database post.
     *
     * @param string $notion_database_id Notion database ID.
     * @param array  $database_info      Database metadata from Notion.
     * @return int WordPress post ID.
     */
    public function find_or_create( string $notion_database_id, array $database_info ): int {
        // Check if post exists
        $posts = get_posts( [
            'post_type' => self::POST_TYPE,
            'meta_key' => 'notion_database_id',
            'meta_value' => $notion_database_id,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ] );

        if ( ! empty( $posts ) ) {
            return $posts[0];
        }

        // Create new post
        $post_id = wp_insert_post( [
            'post_type' => self::POST_TYPE,
            'post_title' => $database_info['title'] ?? 'Untitled Database',
            'post_status' => 'publish',
            'meta_input' => [
                'notion_database_id' => $notion_database_id,
                'notion_last_edited' => $database_info['last_edited_time'] ?? '',
                'row_count' => 0,
                'last_synced' => current_time( 'mysql' ),
            ],
        ] );

        return $post_id;
    }
}
```

**File 3:** `plugin/src/Database/RowRepository.php` (<400 lines)

```php
<?php
namespace NotionSync\Database;

/**
 * Repository for database row CRUD operations.
 *
 * Handles JSON encoding/decoding and database access.
 */
class RowRepository {

    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'notion_database_rows';
    }

    /**
     * Insert or update a database row.
     *
     * @param int    $database_post_id WordPress post ID for database.
     * @param string $notion_page_id   Notion page ID.
     * @param array  $properties       Row data (will be JSON encoded).
     * @param array  $extracted        Extracted fields for indexing.
     * @return bool Success status.
     */
    public function upsert(
        int $database_post_id,
        string $notion_page_id,
        array $properties,
        array $extracted = []
    ): bool {
        global $wpdb;

        // Check if row exists
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE notion_page_id = %s",
            $notion_page_id
        ) );

        $data = [
            'database_post_id' => $database_post_id,
            'notion_page_id' => $notion_page_id,
            'properties' => wp_json_encode( $properties ),
            'title' => $extracted['title'] ?? null,
            'status' => $extracted['status'] ?? null,
            'created_time' => $extracted['created_time'] ?? null,
            'last_edited_time' => $extracted['last_edited_time'] ?? null,
            'synced_at' => current_time( 'mysql' ),
        ];

        if ( $exists ) {
            // Update existing row
            return false !== $wpdb->update(
                $this->table_name,
                $data,
                [ 'notion_page_id' => $notion_page_id ],
                [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ],
                [ '%s' ]
            );
        }

        // Insert new row
        return false !== $wpdb->insert(
            $this->table_name,
            $data,
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * Get all rows for a database.
     *
     * @param int   $database_post_id WordPress post ID for database.
     * @param int   $limit            Results limit.
     * @param int   $offset           Results offset.
     * @param array $filters          Optional filters.
     * @return array Array of row objects with decoded JSON.
     */
    public function get_rows(
        int $database_post_id,
        int $limit = 100,
        int $offset = 0,
        array $filters = []
    ): array {
        global $wpdb;

        $where = $wpdb->prepare( 'WHERE database_post_id = %d', $database_post_id );

        // Add filters
        if ( ! empty( $filters['status'] ) ) {
            $where .= $wpdb->prepare( ' AND status = %s', $filters['status'] );
        }

        if ( ! empty( $filters['search'] ) ) {
            $where .= $wpdb->prepare(
                ' AND MATCH(title) AGAINST(%s IN BOOLEAN MODE)',
                $filters['search']
            );
        }

        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_name}
             {$where}
             ORDER BY last_edited_time DESC
             LIMIT {$limit} OFFSET {$offset}",
            ARRAY_A
        );

        // Decode JSON properties
        foreach ( $results as &$row ) {
            $row['properties'] = json_decode( $row['properties'], true );
        }

        return $results;
    }

    /**
     * Get row count for a database.
     *
     * @param int $database_post_id WordPress post ID for database.
     * @return int Row count.
     */
    public function count_rows( int $database_post_id ): int {
        global $wpdb;

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE database_post_id = %d",
            $database_post_id
        ) );
    }

    /**
     * Delete all rows for a database.
     *
     * @param int $database_post_id WordPress post ID for database.
     * @return bool Success status.
     */
    public function delete_rows( int $database_post_id ): bool {
        global $wpdb;

        return false !== $wpdb->delete(
            $this->table_name,
            [ 'database_post_id' => $database_post_id ],
            [ '%d' ]
        );
    }
}
```

**File 4:** Update `plugin/notion-sync.php` to create tables on activation:

```php
/**
 * Activation hook.
 */
function activate() {
    // Set default options.
    if ( false === get_option( 'notion_wp_token' ) ) {
        add_option( 'notion_wp_token', '' );
    }
    if ( false === get_option( 'notion_wp_workspace_info' ) ) {
        add_option( 'notion_wp_workspace_info', array() );
    }

    // Create custom database tables.
    \NotionSync\Database\Schema::create_tables();

    // Register CPT before flushing rewrite rules.
    $database_cpt = new \NotionSync\Database\DatabasePostType();
    $database_cpt->register();

    // Flush rewrite rules.
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
```

**Tasks:**

1. Create Schema class with table creation SQL
2. Verify MySQL compatibility (test on MySQL 5.5)
3. Create DatabasePostType CPT
4. Implement RowRepository with CRUD operations
5. Update activation hook to create tables
6. Add table existence checks
7. Write unit tests for repository operations
8. Test JSON encoding/decoding

**Definition of Done:**

- [ ] Custom table creates successfully on activation
- [ ] Works on MySQL 5.5+ and MariaDB 10.0+
- [ ] Database CPT registers correctly
- [ ] Can insert rows with JSON data
- [ ] Can query rows with filters
- [ ] Can update existing rows (upsert works)
- [ ] JSON encoding/decoding works correctly
- [ ] Unit tests pass

---

### Stream 3: Batch Processing System

**Worktree:** `phase-2-database-sync`
**Duration:** 2-3 days
**Files Created:** 1 new file (<400 lines)

**What This Builds:**

- Queue system for processing large database syncs
- Background processing using Action Scheduler
- Progress tracking
- Simplified to just store data (no complex post creation)

**Technical Implementation:**

**File 1:** `plugin/src/Sync/BatchProcessor.php` (<350 lines)

```php
<?php
namespace NotionSync\Sync;

use NotionSync\Database\RowRepository;
use NotionSync\Database\DatabasePostType;

/**
 * Batch processor for large database syncs.
 *
 * Uses Action Scheduler for background processing.
 */
class BatchProcessor {

    private const BATCH_SIZE = 20; // Process 20 entries at a time

    private DatabaseFetcher $fetcher;
    private RowRepository $repository;

    public function __construct( DatabaseFetcher $fetcher, RowRepository $repository ) {
        $this->fetcher = $fetcher;
        $this->repository = $repository;
    }

    /**
     * Queue a database for batch sync.
     *
     * @param string $database_id Notion database ID.
     * @return string Batch ID for tracking.
     */
    public function queue_database_sync( string $database_id ): string {
        // Fetch all entries
        $entries = $this->fetcher->query_database( $database_id );

        if ( empty( $entries ) ) {
            throw new \RuntimeException( 'No entries found in database' );
        }

        // Find or create database post
        $database_info = $this->fetcher->get_database_schema( $database_id );
        $database_cpt = new DatabasePostType();
        $post_id = $database_cpt->find_or_create( $database_id, $database_info );

        // Generate batch ID
        $batch_id = 'batch_' . $database_id . '_' . time();

        // Split into batches
        $batches = array_chunk( $entries, self::BATCH_SIZE );

        // Schedule batches
        foreach ( $batches as $index => $batch ) {
            as_schedule_single_action(
                time() + ( $index * 3 ), // Stagger by 3 seconds
                'notion_sync_process_batch',
                [
                    'batch_id' => $batch_id,
                    'post_id' => $post_id,
                    'entries' => $batch,
                    'batch_number' => $index + 1,
                    'total_batches' => count( $batches ),
                ]
            );
        }

        // Save batch metadata
        update_option( "notion_sync_batch_{$batch_id}", [
            'database_id' => $database_id,
            'post_id' => $post_id,
            'total_entries' => count( $entries ),
            'total_batches' => count( $batches ),
            'completed' => 0,
            'failed' => 0,
            'status' => 'queued',
            'started_at' => current_time( 'mysql' ),
        ] );

        return $batch_id;
    }

    /**
     * Process a batch of entries.
     *
     * @param string $batch_id     Batch identifier.
     * @param int    $post_id      Database post ID.
     * @param array  $entries      Entries to process.
     * @param int    $batch_number Current batch number.
     * @param int    $total_batches Total batches.
     */
    public function process_batch(
        string $batch_id,
        int $post_id,
        array $entries,
        int $batch_number,
        int $total_batches
    ): void {
        $this->update_batch_status( $batch_id, 'processing' );

        $completed = 0;
        $failed = 0;

        foreach ( $entries as $entry ) {
            try {
                // Normalize entry
                $normalized = $this->fetcher->normalize_entry( $entry );

                // Extract indexed fields
                $extracted = [
                    'title' => $normalized['properties']['Title'] ??
                               $normalized['properties']['Name'] ??
                               'Untitled',
                    'status' => $normalized['properties']['Status'] ?? null,
                    'created_time' => $normalized['created_time'],
                    'last_edited_time' => $normalized['last_edited_time'],
                ];

                // Store in database
                $this->repository->upsert(
                    $post_id,
                    $normalized['id'],
                    $normalized['properties'],
                    $extracted
                );

                $completed++;

            } catch ( \Exception $e ) {
                $failed++;
                error_log(
                    sprintf(
                        'Batch %s: Failed to sync entry %s: %s',
                        $batch_id,
                        $entry['id'] ?? 'unknown',
                        $e->getMessage()
                    )
                );
            }
        }

        // Update progress
        $this->increment_batch_progress( $batch_id, $completed, $failed );

        // If last batch, mark complete
        if ( $batch_number === $total_batches ) {
            $this->complete_batch( $batch_id, $post_id );
        }
    }

    /**
     * Get batch progress.
     *
     * @param string $batch_id Batch identifier.
     * @return array Batch metadata.
     */
    public function get_batch_progress( string $batch_id ): array {
        return get_option( "notion_sync_batch_{$batch_id}", [] );
    }

    /**
     * Cancel batch operation.
     *
     * @param string $batch_id Batch identifier.
     * @return bool Success status.
     */
    public function cancel_batch( string $batch_id ): bool {
        as_unschedule_all_actions(
            'notion_sync_process_batch',
            [ 'batch_id' => $batch_id ]
        );

        $batch_meta = $this->get_batch_progress( $batch_id );
        if ( $batch_meta ) {
            $batch_meta['status'] = 'cancelled';
            $batch_meta['completed_at'] = current_time( 'mysql' );
            update_option( "notion_sync_batch_{$batch_id}", $batch_meta );
            return true;
        }

        return false;
    }

    private function update_batch_status( string $batch_id, string $status ): void {
        $batch_meta = $this->get_batch_progress( $batch_id );
        $batch_meta['status'] = $status;
        update_option( "notion_sync_batch_{$batch_id}", $batch_meta );
    }

    private function increment_batch_progress(
        string $batch_id,
        int $completed,
        int $failed
    ): void {
        $batch_meta = $this->get_batch_progress( $batch_id );
        $batch_meta['completed'] += $completed;
        $batch_meta['failed'] += $failed;
        update_option( "notion_sync_batch_{$batch_id}", $batch_meta );
    }

    private function complete_batch( string $batch_id, int $post_id ): void {
        $batch_meta = $this->get_batch_progress( $batch_id );
        $batch_meta['status'] = 'completed';
        $batch_meta['completed_at'] = current_time( 'mysql' );
        update_option( "notion_sync_batch_{$batch_id}", $batch_meta );

        // Update database post row count
        $row_count = $this->repository->count_rows( $post_id );
        update_post_meta( $post_id, 'row_count', $row_count );
        update_post_meta( $post_id, 'last_synced', current_time( 'mysql' ) );
    }
}
```

**Tasks:**

1. Install Action Scheduler via Composer
2. Create BatchProcessor class
3. Implement batch splitting and scheduling
4. Add progress tracking
5. Implement cancel functionality
6. Register Action Scheduler hook
7. Write integration tests

**Definition of Done:**

- [ ] Can queue 100+ entries for background processing
- [ ] Batches process without timeouts
- [ ] Progress tracking works correctly
- [ ] Can cancel in-progress batch
- [ ] Failed entries are logged
- [ ] Row count updates on completion
- [ ] Integration tests pass

---

### Stream 4: Admin UI Integration

**Worktree:** `phase-2-database-sync`
**Duration:** 2-3 days
**Files Modified:** 3 existing files, 2 new templates

**What This Builds:**

- Databases tab in settings page
- Sync controls and progress display
- Basic row viewer
- Search/filter interface

**Technical Implementation:**

**Update:** `plugin/src/Admin/SettingsPage.php`

Add tab system and database sync UI:

```php
public function render(): void {
    $current_tab = $_GET['tab'] ?? 'pages';

    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <?php if ( $this->is_connected ) : ?>
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'pages' ) ); ?>"
               class="nav-tab <?php echo 'pages' === $current_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Pages', 'notion-wp' ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'databases' ) ); ?>"
               class="nav-tab <?php echo 'databases' === $current_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Databases', 'notion-wp' ); ?>
            </a>
        </h2>
        <?php endif; ?>

        <?php
        if ( 'databases' === $current_tab && $this->is_connected ) {
            $this->render_databases_tab();
        } else {
            $this->render_pages_tab();
        }
        ?>
    </div>
    <?php
}

private function render_databases_tab(): void {
    // Check if viewing specific database rows
    $database_id = $_GET['database_id'] ?? '';

    if ( ! empty( $database_id ) ) {
        $this->render_database_rows( $database_id );
    } else {
        // Show databases list
        $databases_table = new \NotionSync\Admin\DatabasesListTable();
        $databases_table->prepare_items();
        ?>
        <div id="notion-sync-messages"></div>
        <form method="post">
            <?php $databases_table->display(); ?>
        </form>
        <?php
    }
}
```

**Create:** `plugin/src/Admin/DatabaseRowsListTable.php` (<400 lines)

```php
<?php
namespace NotionSync\Admin;

use NotionSync\Database\RowRepository;

/**
 * List table for viewing database rows.
 */
class DatabaseRowsListTable extends \WP_List_Table {

    private int $database_post_id;
    private RowRepository $repository;

    public function __construct( int $database_post_id ) {
        parent::__construct( [
            'singular' => 'row',
            'plural' => 'rows',
            'ajax' => false,
        ] );

        $this->database_post_id = $database_post_id;
        $this->repository = new RowRepository();
    }

    public function get_columns(): array {
        // Dynamic columns based on properties
        return [
            'title' => __( 'Title', 'notion-wp' ),
            'status' => __( 'Status', 'notion-wp' ),
            'last_edited' => __( 'Last Edited', 'notion-wp' ),
            'notion_id' => __( 'Notion ID', 'notion-wp' ),
        ];
    }

    public function prepare_items(): void {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $filters = [
            'search' => $_GET['s'] ?? '',
            'status' => $_GET['status'] ?? '',
        ];

        $this->items = $this->repository->get_rows(
            $this->database_post_id,
            $per_page,
            $offset,
            $filters
        );

        $total_items = $this->repository->count_rows( $this->database_post_id );

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page' => $per_page,
        ] );
    }

    public function column_title( $item ): string {
        $view_url = add_query_arg( [
            'action' => 'view',
            'row_id' => $item['id'],
        ] );

        return sprintf(
            '<strong><a href="%s">%s</a></strong>',
            esc_url( $view_url ),
            esc_html( $item['title'] ?? 'Untitled' )
        );
    }
}
```

**Add AJAX Handlers:**

```php
// In SettingsPage.php register() method
add_action( 'wp_ajax_notion_sync_database', [ $this, 'ajax_sync_database' ] );
add_action( 'wp_ajax_notion_sync_batch_progress', [ $this, 'ajax_batch_progress' ] );

public function ajax_sync_database(): void {
    check_ajax_referer( 'notion_sync_ajax' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'notion-wp' ) );
    }

    $database_id = sanitize_text_field( $_POST['database_id'] ?? '' );

    try {
        $processor = new \NotionSync\Sync\BatchProcessor(
            new \NotionSync\Sync\DatabaseFetcher( $this->client ),
            new \NotionSync\Database\RowRepository()
        );

        $batch_id = $processor->queue_database_sync( $database_id );

        wp_send_json_success( [
            'batch_id' => $batch_id,
            'message' => __( 'Sync started', 'notion-wp' ),
        ] );

    } catch ( \Exception $e ) {
        wp_send_json_error( $e->getMessage() );
    }
}

public function ajax_batch_progress(): void {
    check_ajax_referer( 'notion_sync_ajax' );

    $batch_id = sanitize_text_field( $_POST['batch_id'] ?? '' );

    $processor = new \NotionSync\Sync\BatchProcessor(
        new \NotionSync\Sync\DatabaseFetcher( $this->client ),
        new \NotionSync\Database\RowRepository()
    );

    $progress = $processor->get_batch_progress( $batch_id );

    wp_send_json_success( $progress );
}
```

**Update:** `plugin/assets/src/js/admin.js`

Add progress polling:

```javascript
// Handle database sync
document.querySelectorAll('.sync-database').forEach((button) => {
	button.addEventListener('click', async function (e) {
		e.preventDefault();

		const databaseId = this.dataset.databaseId;

		if (!confirm(notionSyncAdmin.i18n.confirmDatabaseSync)) {
			return;
		}

		try {
			const formData = new FormData();
			formData.append('action', 'notion_sync_database');
			formData.append('database_id', databaseId);
			formData.append('_ajax_nonce', notionSyncAdmin.nonce);

			const response = await fetch(notionSyncAdmin.ajaxUrl, {
				method: 'POST',
				body: formData,
			});

			const data = await response.json();

			if (data.success) {
				startProgressPolling(data.data.batch_id);
			} else {
				showMessage(data.data, 'error');
			}
		} catch (error) {
			showMessage(error.message, 'error');
		}
	});
});

async function startProgressPolling(batchId) {
	const progressContainer = createProgressBar();

	const interval = setInterval(async () => {
		try {
			const progress = await getBatchProgress(batchId);
			updateProgressBar(progressContainer, progress);

			if (
				progress.status === 'completed' ||
				progress.status === 'cancelled'
			) {
				clearInterval(interval);
				showCompletionMessage(progress);
			}
		} catch (error) {
			clearInterval(interval);
			showMessage(error.message, 'error');
		}
	}, 2000);
}
```

**Tasks:**

1. Add tab navigation to settings page
2. Create DatabasesListTable display
3. Create DatabaseRowsListTable for viewing rows
4. Add AJAX endpoints for sync and progress
5. Add JavaScript for progress polling
6. Create progress bar component
7. Add completion messages
8. Test full sync workflow

**Definition of Done:**

- [ ] Settings page has Databases tab
- [ ] Can see list of databases
- [ ] Click "Sync Now" starts batch
- [ ] Progress bar updates in real-time
- [ ] Can view synced rows
- [ ] Can search/filter rows
- [ ] Completion message shows stats
- [ ] All UI is responsive

---

## 📦 Deliverables

### Visible to Users

- ✅ Navigate to **WP Admin > Notion Sync > Databases**
- ✅ See list of all accessible Notion databases
- ✅ Click "Sync Now" to import database
- ✅ Watch real-time progress bar
- ✅ View synced data in table format
- ✅ Search and filter rows
- ✅ Re-sync updates existing data (no duplicates)

### Technical

**New Files:**

- ✅ `plugin/src/Sync/DatabaseFetcher.php` - Database queries
- ✅ `plugin/src/Database/Schema.php` - Table creation
- ✅ `plugin/src/Database/DatabasePostType.php` - CPT
- ✅ `plugin/src/Database/RowRepository.php` - Data access
- ✅ `plugin/src/Sync/BatchProcessor.php` - Background processing
- ✅ `plugin/src/Admin/DatabasesListTable.php` - Databases list
- ✅ `plugin/src/Admin/DatabaseRowsListTable.php` - Rows viewer

**Modified Files:**

- ✅ `plugin/notion-sync.php` - Activation hook, table creation
- ✅ `plugin/src/Admin/SettingsPage.php` - Tabs, AJAX handlers
- ✅ `plugin/assets/src/js/admin.js` - Progress polling
- ✅ `composer.json` - Action Scheduler dependency

**Not Built (Deferred):**

- ❌ Field mapping to posts (future feature)
- ❌ Gutenberg blocks from rows (future)
- ❌ Advanced filtering/sorting (future)
- ❌ Export functionality (future)

---

## 🔍 Testing Checklist

### Functional Testing

- [ ] Custom table creates on activation
- [ ] Works on MySQL 5.5+
- [ ] Can query database with 10 entries
- [ ] Can query database with 100+ entries
- [ ] Can query database with 500+ entries
- [ ] Database CPT posts create correctly
- [ ] Rows store as JSON in custom table
- [ ] Can view all rows in admin
- [ ] Search functionality works
- [ ] Batch processing completes successfully
- [ ] Progress bar updates every 2 seconds
- [ ] Re-sync updates existing rows (no duplicates)
- [ ] Can cancel in-progress sync

### Performance Testing

- [ ] 100 entries sync in under 5 minutes
- [ ] 500 entries sync without memory errors
- [ ] No PHP timeouts
- [ ] Background jobs complete reliably

### Code Quality

- [ ] All files under 500 lines
- [ ] `composer lint:phpcs` clean
- [ ] `composer lint:phpstan` passes level 3
- [ ] `npm run lint:js` passes
- [ ] No console errors
- [ ] No PHP warnings

---

## 📊 Success Metrics

**Time Metrics:**

- 100 entries sync in <5 minutes
- Progress updates every 2 seconds

**Quality Metrics:**

- Zero linting warnings
- 100% data accuracy
- Zero duplicate rows
- 95%+ batch success rate

**User Metrics:**

- Can demo in under 5 minutes
- Non-developer can understand workflow

---

## 🚧 Risks & Mitigation

| Risk                       | Impact | Mitigation                             |
| -------------------------- | ------ | -------------------------------------- |
| Large databases timeout    | High   | Action Scheduler background processing |
| Memory exhaustion          | High   | Process in batches, unset variables    |
| JSON encoding fails        | Medium | Validate data, log errors              |
| Action Scheduler conflicts | Medium | Version check dependencies             |

---

## ✋ Gatekeeping Review

**Demo Script** (5 minutes):

1. Show Notion database with 20+ entries (30s)
2. Navigate to Databases tab (15s)
3. Click "Sync Now" and watch progress (2min)
4. View synced data in rows table (1min)
5. Search/filter rows (1min)

**Pass Criteria:**

- Sync completed successfully
- All data stored correctly in JSON
- UI is clear and responsive
- No errors or confusion

---

## ⏭️ Next Phase Preview

**Phase 3: Field Mapping & Post Creation**

- Map database rows to WordPress posts
- Configure property → field mappings
- Selective post creation from rows
- Category and tag assignment

**Requires:** Working database JSON storage from Phase 2
