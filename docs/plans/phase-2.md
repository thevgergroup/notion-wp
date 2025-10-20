# Phase 2: Database Sync

**Status:** 🔜 NOT STARTED
**Duration:** 1-2 weeks (estimated)
**Complexity:** M (Medium)
**Version Target:** v0.3-dev

## 🎯 Goal

Sync entire Notion databases to WordPress posts with property field mapping and batch processing. **This phase proves the plugin can handle bulk content operations efficiently.**

**User Story:** "As a WordPress admin, I can select a Notion database, configure how properties map to WordPress fields, and sync all database entries as posts with a single click, seeing real-time progress as the batch import runs."

## ✅ Success Criteria (Gatekeeping)

**DO NOT PROCEED to Phase 3 until ALL criteria are met:**

### Core Functionality
- [ ] User can select a Notion database (not just individual pages)
- [ ] All database entries import as WordPress posts
- [ ] Database properties map to WordPress fields automatically:
    - [ ] Title property → Post title
    - [ ] Date property → Post date/publish date
    - [ ] Select property → WordPress category
    - [ ] Multi-select property → WordPress tags
    - [ ] Rich text property → Post excerpt or custom field
    - [ ] Number property → Custom field
    - [ ] Checkbox property → Custom field
- [ ] User can configure custom field mappings via admin UI
- [ ] Batch import handles 100+ entries without PHP timeout
- [ ] Background processing queues large imports
- [ ] Sync status shows real-time progress (e.g., "Imported 15 of 42 posts")
- [ ] Can pause/resume large batch operations

### Performance Requirements
- [ ] Syncs 100 database entries in under 5 minutes
- [ ] Handles databases with 500+ entries
- [ ] No memory limit errors on large batches
- [ ] Uses WordPress Action Scheduler or WP-Cron for background processing

### Quality Requirements
- [ ] Re-sync updates existing posts (no duplicates created)
- [ ] Zero PHP warnings (all Phase 1 linting warnings eliminated)
- [ ] All linting passes (PHPCS, ESLint, PHPStan level 3+)
- [ ] Zero console errors or PHP notices
- [ ] **Can be demoed to a non-developer in under 5 minutes**

## 📋 Dependencies

**Required from Phase 1 (COMPLETED ✅):**

- ✅ Working single page sync
- ✅ Block converter system
- ✅ Post creation and update logic
- ✅ NotionClient API wrapper
- ✅ Admin UI with PagesListTable
- ✅ AJAX sync handlers

**Pre-Phase 2 Cleanup Required:**

- [ ] Eliminate all 24 PHPCS warnings from Phase 1
- [ ] Verify all files under 500 lines
- [ ] Ensure PHPStan level 3 passes cleanly

## 🔀 Parallel Work Streams

### Stream 0: Code Cleanup (Pre-Phase 2 Blocker)

**Worktree:** `phase-2-database-sync` (create new)
**Duration:** 1-2 days
**Files Modified:** 4 existing files

**What This Fixes:**

Current PHPCS warnings to eliminate:
1. **Line length warnings** (13 instances) - Split long lines
2. **Development function warnings** (9 instances) - Replace error_log() with proper logging
3. **Reserved keyword warning** (1 instance) - Rename $class parameter
4. **Alternative function warning** (1 instance) - Use wp_remote_get() in tests

**Tasks:**

1. **Fix line length violations** (13 warnings):
   - `plugin/templates/admin/settings.php` - Split 10 long lines
   - `plugin/src/Security/Encryption.php` - Split 2 long lines
   - `plugin/src/Admin/SyncAjaxHandler.php` - Split 1 long line

2. **Replace error_log() calls** (9 warnings):
   - `plugin/src/Sync/ContentFetcher.php` - Replace all error_log() with WP error logging or remove debug code

3. **Fix reserved keyword warning** (1 warning):
   - `plugin/notion-sync.php` - Rename parameter `$class` to `$class_name`

4. **Fix file_get_contents() in tests** (1 warning):
   - `tests/unit/Blocks/Converters/BulletedListConverterTest.php` - Use wp_remote_get() or suppress for test fixtures

5. **Verify all linting passes**:
   - Run `composer lint:phpcs` - Should show 0 warnings
   - Run `composer lint:phpstan` - Should pass level 3
   - Run `npm run lint:js` - Should pass

**Definition of Done:**

- [ ] `composer lint:phpcs` shows 0 warnings, 0 errors
- [ ] `composer lint:phpstan` passes cleanly
- [ ] `npm run lint:js` passes cleanly
- [ ] All files remain under 500 lines
- [ ] Code functionality unchanged (tests still pass)
- [ ] Commit message: "chore: eliminate all PHPCS warnings for Phase 2 prep"

**BLOCKER:** Do not proceed to other streams until this is complete and merged.

---

### Stream 1: Database Query System

**Worktree:** `phase-2-database-sync`
**Duration:** 2-3 days
**Files Created:** 2 new files, all <400 lines

**What This Builds:**

- Query Notion databases (not just pages)
- Fetch all database entries with pagination
- Retrieve database schema (properties/columns)
- Handle different database types (inline vs full-page)

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
     * @param string $database_id Notion database ID.
     * @param array  $filters     Optional query filters.
     * @param array  $sorts       Optional sort configuration.
     * @return array Array of database page objects.
     */
    public function query_database( string $database_id, array $filters = [], array $sorts = [] ): array {
        // POST /databases/{database_id}/query
        // Handle pagination (100 entries per request)
        // Support filters and sorts
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
     * @return array List of databases.
     */
    public function get_databases(): array {
        // Search API for type='database'
        // Return database metadata (id, title, last_edited)
    }

    /**
     * Extract property value from database page.
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
        // - url -> string
        // - email -> string
        // - phone_number -> string
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
            'status'      => __( 'Status', 'notion-wp' ),
        ];
    }

    public function column_title( $item ): string {
        $actions = [
            'configure' => sprintf(
                '<a href="%s">%s</a>',
                esc_url( add_query_arg( [
                    'page'        => 'notion-sync',
                    'tab'         => 'database-config',
                    'database_id' => $item['id'],
                ] ) ),
                esc_html__( 'Configure Mapping', 'notion-wp' )
            ),
            'sync'      => sprintf(
                '<a href="#" class="sync-database" data-database-id="%s">%s</a>',
                esc_attr( $item['id'] ),
                esc_html__( 'Sync All', 'notion-wp' )
            ),
        ];

        return sprintf(
            '<strong>%s</strong> %s',
            esc_html( $item['title'] ),
            $this->row_actions( $actions )
        );
    }
}
```

**Tasks:**

1. Create DatabaseFetcher class with database query endpoint
2. Implement pagination for large databases (100+ entries)
3. Add database schema fetching
4. Implement property value extraction for all property types
5. Create DatabasesListTable for admin UI
6. Add database selector to settings page
7. Write unit tests for property extraction
8. Write integration tests for database queries

**Definition of Done:**

- [ ] Can query all entries from a Notion database
- [ ] Handles pagination correctly (tested with 200+ entries)
- [ ] Extracts all common property types (title, select, multi-select, date, etc.)
- [ ] Admin UI shows list of databases with entry counts
- [ ] Unit tests pass for all property types
- [ ] Integration tests pass

---

### Stream 2: Field Mapping System

**Worktree:** `phase-2-database-sync`
**Duration:** 3-4 days
**Files Created:** 3 new files, all <400 lines

**What This Builds:**

- Map Notion database properties to WordPress post fields
- Admin UI for configuring mappings
- Save/load mapping configurations
- Support for built-in WP fields and custom fields

**Technical Implementation:**

**File 1:** `plugin/src/Sync/FieldMapper.php` (<350 lines)

```php
<?php
namespace NotionSync\Sync;

/**
 * Maps Notion database properties to WordPress post fields.
 *
 * Handles type conversion and custom field mapping.
 */
class FieldMapper {
    /**
     * Get default field mappings for common patterns.
     *
     * @param array $database_schema Database property definitions.
     * @return array Suggested mappings.
     */
    public function get_default_mappings( array $database_schema ): array {
        // Auto-detect common patterns:
        // - Property named "Title" or "Name" -> post_title
        // - Property type "date" -> post_date
        // - Property type "select" -> category (if name contains "category")
        // - Property type "multi_select" -> tags (if name contains "tag")
        // - Property type "rich_text" -> post_excerpt (if name contains "excerpt/summary")
        // - Everything else -> custom field
    }

    /**
     * Get saved field mapping for a database.
     *
     * @param string $database_id Notion database ID.
     * @return array Field mapping configuration.
     */
    public function get_mapping( string $database_id ): array {
        // Get from options: notion_wp_field_mapping_{database_id}
    }

    /**
     * Save field mapping configuration.
     *
     * @param string $database_id Notion database ID.
     * @param array  $mapping     Field mapping configuration.
     * @return bool Success status.
     */
    public function save_mapping( string $database_id, array $mapping ): bool {
        // Validate mapping structure
        // Save to options: notion_wp_field_mapping_{database_id}
    }

    /**
     * Convert Notion property to WordPress post field.
     *
     * @param string $wp_field    WordPress field (post_title, post_date, meta:custom_field).
     * @param mixed  $value       Notion property value.
     * @param string $notion_type Notion property type.
     * @return mixed Converted value for WordPress.
     */
    public function convert_value( string $wp_field, $value, string $notion_type ) {
        // Handle conversions:
        // - date -> Y-m-d H:i:s format for post_date
        // - select -> category ID or slug
        // - multi_select -> array of tag IDs or slugs
        // - rich_text -> plain text or HTML
        // - number -> int/float
        // - checkbox -> 1/0 or true/false
    }

    /**
     * Apply mappings to database entry to create post data.
     *
     * @param array $entry        Notion database entry.
     * @param array $mapping      Field mapping configuration.
     * @param array $properties   Database schema.
     * @return array WordPress post data array.
     */
    public function map_entry_to_post( array $entry, array $mapping, array $properties ): array {
        // Build post data array:
        // [
        //   'post_title'   => '',
        //   'post_content' => '', // from page blocks
        //   'post_date'    => '',
        //   'post_excerpt' => '',
        //   'post_status'  => 'publish',
        //   'meta_input'   => [...]
        //   'tax_input'    => [...]
        // ]
    }
}
```

**File 2:** `plugin/src/Admin/FieldMappingUI.php` (<400 lines)

```php
<?php
namespace NotionSync\Admin;

/**
 * Admin UI for configuring database field mappings.
 */
class FieldMappingUI {
    /**
     * Render field mapping configuration interface.
     *
     * @param string $database_id Notion database ID.
     * @param array  $schema      Database schema.
     * @param array  $mapping     Current mapping configuration.
     * @return void
     */
    public function render( string $database_id, array $schema, array $mapping ): void {
        // Show table with:
        // - Column 1: Notion property name (e.g., "Title", "Category", "Tags")
        // - Column 2: Property type (e.g., "title", "select", "multi_select")
        // - Column 3: Dropdown to select WP field:
        //   * Post Title
        //   * Post Date
        //   * Post Excerpt
        //   * Category (creates if doesn't exist)
        //   * Tags (creates if doesn't exist)
        //   * Custom Field: [text input for field name]
        //   * [Ignore - Don't Import]
        // - Save button
    }

    /**
     * Get available WordPress field options.
     *
     * @return array Field options for dropdown.
     */
    private function get_wp_field_options(): array {
        return [
            'post_title'   => __( 'Post Title', 'notion-wp' ),
            'post_date'    => __( 'Post Date', 'notion-wp' ),
            'post_excerpt' => __( 'Post Excerpt', 'notion-wp' ),
            'category'     => __( 'Category', 'notion-wp' ),
            'post_tag'     => __( 'Tags', 'notion-wp' ),
            'custom_field' => __( 'Custom Field', 'notion-wp' ),
            'ignore'       => __( 'Ignore (Don\'t Import)', 'notion-wp' ),
        ];
    }
}
```

**File 3:** Template `plugin/templates/admin/field-mapping.php` (<250 lines)

```php
<?php
/**
 * Template for database field mapping configuration.
 *
 * @var string $database_id Database ID
 * @var array  $schema      Database schema
 * @var array  $mapping     Current mapping
 */
?>
<div class="wrap">
    <h2><?php esc_html_e( 'Configure Field Mapping', 'notion-wp' ); ?></h2>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="notion_sync_save_field_mapping">
        <input type="hidden" name="database_id" value="<?php echo esc_attr( $database_id ); ?>">
        <?php wp_nonce_field( 'notion_sync_field_mapping', 'notion_sync_field_mapping_nonce' ); ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Notion Property', 'notion-wp' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'notion-wp' ); ?></th>
                    <th><?php esc_html_e( 'WordPress Field', 'notion-wp' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $schema as $property_name => $property_config ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $property_name ); ?></strong></td>
                    <td><code><?php echo esc_html( $property_config['type'] ); ?></code></td>
                    <td>
                        <select name="mapping[<?php echo esc_attr( $property_name ); ?>][wp_field]">
                            <!-- Options populated by FieldMappingUI -->
                        </select>
                        <!-- Show custom field input if "custom_field" selected -->
                        <input type="text"
                               name="mapping[<?php echo esc_attr( $property_name ); ?>][custom_field_name]"
                               placeholder="<?php esc_attr_e( 'Custom field name', 'notion-wp' ); ?>"
                               style="display: none;">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php submit_button( __( 'Save Field Mapping', 'notion-wp' ) ); ?>
    </form>
</div>
```

**Tasks:**

1. Create FieldMapper class with type conversion logic
2. Implement auto-detection of common field mappings
3. Add save/load mapping configuration
4. Create FieldMappingUI admin interface
5. Add form handler for saving mappings
6. Implement category/tag creation from select/multi-select
7. Write unit tests for field conversion
8. Test with multiple property types

**Definition of Done:**

- [ ] Admin UI shows all database properties with dropdowns
- [ ] Can map title property to post_title
- [ ] Can map date property to post_date
- [ ] Can map select to category (creates category if needed)
- [ ] Can map multi-select to tags (creates tags if needed)
- [ ] Can map rich text to custom field
- [ ] Mappings save and persist across page loads
- [ ] Auto-detection suggests sensible defaults
- [ ] Unit tests pass for all conversion types

---

### Stream 3: Batch Processing System

**Worktree:** `phase-2-database-sync`
**Duration:** 3-4 days
**Files Created:** 2 new files, all <400 lines

**What This Builds:**

- Queue system for processing large batches
- Background processing using Action Scheduler or WP-Cron
- Progress tracking and status updates
- Pause/resume functionality

**Technical Implementation:**

**File 1:** `plugin/src/Sync/BatchProcessor.php` (<400 lines)

```php
<?php
namespace NotionSync\Sync;

/**
 * Batch processor for large database syncs.
 *
 * Uses Action Scheduler for background processing.
 */
class BatchProcessor {
    private const BATCH_SIZE = 10; // Process 10 entries at a time.

    /**
     * Queue a database for batch sync.
     *
     * @param string $database_id Notion database ID.
     * @param array  $entry_ids   Array of page IDs to sync.
     * @return string Batch ID for tracking.
     */
    public function queue_database_sync( string $database_id, array $entry_ids ): string {
        // Generate unique batch ID.
        $batch_id = 'batch_' . $database_id . '_' . time();

        // Split entries into batches of BATCH_SIZE.
        $batches = array_chunk( $entry_ids, self::BATCH_SIZE );

        // Schedule each batch using Action Scheduler.
        foreach ( $batches as $index => $batch ) {
            as_schedule_single_action(
                time() + ( $index * 5 ), // Stagger by 5 seconds.
                'notion_sync_process_batch',
                [
                    'batch_id'     => $batch_id,
                    'database_id'  => $database_id,
                    'entry_ids'    => $batch,
                    'batch_number' => $index + 1,
                    'total_batches' => count( $batches ),
                ]
            );
        }

        // Save batch metadata.
        update_option( "notion_sync_batch_{$batch_id}", [
            'database_id'   => $database_id,
            'total_entries' => count( $entry_ids ),
            'total_batches' => count( $batches ),
            'completed'     => 0,
            'failed'        => 0,
            'status'        => 'queued',
            'started_at'    => current_time( 'mysql' ),
        ] );

        return $batch_id;
    }

    /**
     * Process a batch of entries.
     *
     * @param string $batch_id     Batch identifier.
     * @param string $database_id  Notion database ID.
     * @param array  $entry_ids    Entry IDs to process.
     * @param int    $batch_number Current batch number.
     * @param int    $total_batches Total batches in job.
     * @return void
     */
    public function process_batch(
        string $batch_id,
        string $database_id,
        array $entry_ids,
        int $batch_number,
        int $total_batches
    ): void {
        // Update status to 'processing'.
        $this->update_batch_status( $batch_id, 'processing' );

        $sync_manager = new SyncManager();
        $completed    = 0;
        $failed       = 0;

        foreach ( $entry_ids as $entry_id ) {
            try {
                $sync_manager->sync_page( $entry_id );
                $completed++;
            } catch ( \Exception $e ) {
                $failed++;
                // Log error.
                error_log( "Batch {$batch_id}: Failed to sync {$entry_id}: " . $e->getMessage() );
            }
        }

        // Update batch progress.
        $this->increment_batch_progress( $batch_id, $completed, $failed );

        // If this is the last batch, mark as complete.
        $batch_meta = get_option( "notion_sync_batch_{$batch_id}" );
        if ( $batch_number === $total_batches ) {
            $batch_meta['status']       = 'completed';
            $batch_meta['completed_at'] = current_time( 'mysql' );
            update_option( "notion_sync_batch_{$batch_id}", $batch_meta );
        }
    }

    /**
     * Get batch progress.
     *
     * @param string $batch_id Batch identifier.
     * @return array Batch metadata with progress.
     */
    public function get_batch_progress( string $batch_id ): array {
        return get_option( "notion_sync_batch_{$batch_id}", [] );
    }

    /**
     * Cancel a batch operation.
     *
     * @param string $batch_id Batch identifier.
     * @return bool Success status.
     */
    public function cancel_batch( string $batch_id ): bool {
        // Cancel scheduled actions.
        as_unschedule_all_actions( 'notion_sync_process_batch', [ 'batch_id' => $batch_id ] );

        // Update status.
        $batch_meta = get_option( "notion_sync_batch_{$batch_id}" );
        if ( $batch_meta ) {
            $batch_meta['status']       = 'cancelled';
            $batch_meta['completed_at'] = current_time( 'mysql' );
            update_option( "notion_sync_batch_{$batch_id}", $batch_meta );
            return true;
        }

        return false;
    }
}
```

**File 2:** `plugin/src/Admin/BatchProgressUI.php` (<300 lines)

```php
<?php
namespace NotionSync\Admin;

/**
 * UI for displaying batch sync progress.
 */
class BatchProgressUI {
    /**
     * Render progress indicator for active batch.
     *
     * @param string $batch_id Batch identifier.
     * @return void
     */
    public function render( string $batch_id ): void {
        $processor = new \NotionSync\Sync\BatchProcessor();
        $progress  = $processor->get_batch_progress( $batch_id );

        if ( empty( $progress ) ) {
            return;
        }

        $total     = $progress['total_entries'];
        $completed = $progress['completed'];
        $failed    = $progress['failed'];
        $remaining = $total - $completed - $failed;
        $percent   = $total > 0 ? round( ( $completed / $total ) * 100 ) : 0;

        ?>
        <div class="notion-sync-progress" data-batch-id="<?php echo esc_attr( $batch_id ); ?>">
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo esc_attr( $percent ); ?>%;"></div>
            </div>
            <div class="progress-text">
                <?php
                printf(
                    /* translators: 1: completed count, 2: total count, 3: percentage */
                    esc_html__( 'Syncing: %1$d of %2$d entries (%3$d%%)', 'notion-wp' ),
                    $completed,
                    $total,
                    $percent
                );
                ?>
            </div>
            <?php if ( $failed > 0 ) : ?>
            <div class="progress-errors">
                <?php
                printf(
                    /* translators: %d: number of failed entries */
                    esc_html__( '%d entries failed', 'notion-wp' ),
                    $failed
                );
                ?>
            </div>
            <?php endif; ?>

            <?php if ( 'processing' === $progress['status'] || 'queued' === $progress['status'] ) : ?>
            <button class="button button-secondary cancel-batch"
                    data-batch-id="<?php echo esc_attr( $batch_id ); ?>">
                <?php esc_html_e( 'Cancel', 'notion-wp' ); ?>
            </button>
            <?php endif; ?>
        </div>
        <?php
    }
}
```

**Tasks:**

1. Install Action Scheduler via Composer
2. Create BatchProcessor with queue management
3. Implement batch splitting (10-20 entries per batch)
4. Add progress tracking with transient storage
5. Implement pause/cancel functionality
6. Create BatchProgressUI component
7. Add AJAX endpoint for progress polling
8. Add JavaScript for real-time progress updates
9. Write integration tests for batch processing

**Definition of Done:**

- [ ] Can queue 100+ entries for background processing
- [ ] Batches process reliably without timeouts
- [ ] Progress bar updates in real-time
- [ ] Shows "X of Y entries synced (Z%)"
- [ ] Can cancel in-progress batch
- [ ] Failed entries are logged with reasons
- [ ] No memory issues with large batches
- [ ] Integration tests pass

---

### Stream 4: Database Sync UI Integration

**Worktree:** `phase-2-database-sync`
**Duration:** 2-3 days
**Files Modified:** Extend existing admin files

**What This Builds:**

- Integrate all database sync features into admin UI
- Add tabs for Databases vs Pages
- Show database configuration and sync controls
- Display batch progress

**Technical Implementation:**

**Update:** `plugin/src/Admin/SettingsPage.php`

Add new tab system:

```php
public function render(): void {
    // ... existing code ...

    // Get current tab.
    $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'pages';

    // Render tab navigation.
    ?>
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
    <?php

    // Render appropriate content.
    if ( 'databases' === $current_tab ) {
        $this->render_databases_tab();
    } else {
        $this->render_pages_tab(); // Existing PagesListTable.
    }
}

private function render_databases_tab(): void {
    // Check if configuring a specific database.
    $database_id = isset( $_GET['database_id'] ) ? sanitize_text_field( wp_unslash( $_GET['database_id'] ) ) : '';

    if ( ! empty( $database_id ) ) {
        // Show field mapping UI.
        $this->render_field_mapping( $database_id );
    } else {
        // Show databases list.
        $databases_table = new DatabasesListTable();
        $databases_table->prepare_items();
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'notion_sync_database_action', 'notion_sync_database_nonce' ); ?>
            <?php $databases_table->display(); ?>
        </form>
        <?php
    }
}
```

**Add AJAX Handlers:**

```php
add_action( 'wp_ajax_notion_sync_database', array( $this, 'ajax_sync_database' ) );
add_action( 'wp_ajax_notion_sync_batch_progress', array( $this, 'ajax_batch_progress' ) );
add_action( 'wp_ajax_notion_sync_cancel_batch', array( $this, 'ajax_cancel_batch' ) );

public function ajax_sync_database(): void {
    check_ajax_referer( 'notion_sync_ajax' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'notion-wp' ) );
    }

    $database_id = sanitize_text_field( $_POST['database_id'] ?? '' );

    try {
        $fetcher   = new \NotionSync\Sync\DatabaseFetcher( $this->client );
        $entries   = $fetcher->query_database( $database_id );
        $entry_ids = wp_list_pluck( $entries, 'id' );

        $processor = new \NotionSync\Sync\BatchProcessor();
        $batch_id  = $processor->queue_database_sync( $database_id, $entry_ids );

        wp_send_json_success( [
            'batch_id'      => $batch_id,
            'total_entries' => count( $entry_ids ),
        ] );
    } catch ( \Exception $e ) {
        wp_send_json_error( $e->getMessage() );
    }
}
```

**Update:** `plugin/assets/src/js/admin.js`

Add database sync handling:

```javascript
// Handle database sync button click
document.querySelectorAll('.sync-database').forEach(button => {
    button.addEventListener('click', async function(e) {
        e.preventDefault();

        const databaseId = this.dataset.databaseId;

        if (!confirm(notionSyncAdmin.i18n.confirmDatabaseSync)) {
            return;
        }

        try {
            const response = await fetch(notionSyncAdmin.ajaxUrl, {
                method: 'POST',
                body: new FormData(/* ... */)
            });

            const data = await response.json();

            if (data.success) {
                // Start polling for batch progress
                startProgressPolling(data.data.batch_id);
            }
        } catch (error) {
            alert(error.message);
        }
    });
});

function startProgressPolling(batchId) {
    const interval = setInterval(async () => {
        const progress = await getBatchProgress(batchId);

        updateProgressBar(progress);

        if (progress.status === 'completed' || progress.status === 'cancelled') {
            clearInterval(interval);
            showCompletionMessage(progress);
        }
    }, 2000); // Poll every 2 seconds
}
```

**Tasks:**

1. Add tab navigation to settings page
2. Create Databases tab with DatabasesListTable
3. Add field mapping configuration view
4. Integrate BatchProgressUI into admin
5. Add AJAX endpoints for database sync
6. Add JavaScript for progress polling
7. Show completion summary with stats
8. Add error handling and user feedback

**Definition of Done:**

- [ ] Settings page has Pages and Databases tabs
- [ ] Databases tab shows all accessible databases
- [ ] Can click "Configure Mapping" to set field mappings
- [ ] Can click "Sync All" to start batch sync
- [ ] Progress bar appears and updates in real-time
- [ ] Shows completion message with stats
- [ ] Can cancel in-progress sync
- [ ] Errors are displayed clearly

---

## 📦 Deliverables

### Visible to Users (What They Can Do)

- ✅ Navigate to **WP Admin > Notion Sync > Databases** tab
- ✅ See list of all Notion databases with entry counts
- ✅ Click "Configure Mapping" to set up field mappings
- ✅ Map database properties to WordPress fields (title, date, categories, tags, custom fields)
- ✅ Click "Sync All" to import entire database
- ✅ Watch real-time progress bar showing "X of Y entries (Z%)"
- ✅ See completion summary with success/failure counts
- ✅ Re-sync updates existing posts (no duplicates)
- ✅ All database entries appear as WordPress posts with correct metadata

### Technical (What We Built)

**New Files:**
- ✅ `plugin/src/Sync/DatabaseFetcher.php` - Database queries and schema
- ✅ `plugin/src/Sync/FieldMapper.php` - Property to field mapping
- ✅ `plugin/src/Sync/BatchProcessor.php` - Background processing
- ✅ `plugin/src/Admin/DatabasesListTable.php` - Databases list table
- ✅ `plugin/src/Admin/FieldMappingUI.php` - Mapping configuration UI
- ✅ `plugin/src/Admin/BatchProgressUI.php` - Progress tracking UI
- ✅ `plugin/templates/admin/field-mapping.php` - Mapping template

**Modified Files:**
- ✅ `plugin/src/Admin/SettingsPage.php` - Tab system and AJAX handlers
- ✅ `plugin/assets/src/js/admin.js` - Database sync and progress polling
- ✅ `composer.json` - Add Action Scheduler dependency

**Removed Issues:**
- ✅ All 24 PHPCS warnings eliminated
- ✅ All error_log() calls replaced with proper logging
- ✅ All line length violations fixed

### Not Built (Deferred to Later Phases)

- ❌ Images (Phase 3)
- ❌ Advanced blocks (callouts, toggles, code) (Phase 4)
- ❌ Page hierarchy and menus (Phase 5)
- ❌ Bi-directional sync (Future)
- ❌ Webhooks (Future)

---

## 🔍 Testing Checklist

### Functional Testing

- [ ] Can query database with 10 entries
- [ ] Can query database with 100+ entries (pagination works)
- [ ] Can query database with 500+ entries (no timeout)
- [ ] Database schema fetching works for all property types
- [ ] Field mapping auto-detection suggests correct defaults
- [ ] Can manually configure all field mappings
- [ ] Title property maps to post_title
- [ ] Date property maps to post_date
- [ ] Select property creates categories
- [ ] Multi-select property creates tags
- [ ] Rich text property becomes custom field
- [ ] Batch sync completes successfully (100+ entries)
- [ ] Progress bar updates every 2 seconds
- [ ] Completion shows accurate stats
- [ ] Can cancel in-progress batch
- [ ] Re-sync updates posts (no duplicates)

### Performance Testing

- [ ] 100 entries sync in under 5 minutes
- [ ] 500 entries sync without memory errors
- [ ] No PHP timeouts during batch processing
- [ ] Background jobs complete reliably
- [ ] Progress polling doesn't overload server

### Error Handling

- [ ] Invalid database ID shows helpful error
- [ ] Missing field mapping shows warning
- [ ] Network errors during batch are logged
- [ ] Failed entries don't stop batch
- [ ] Cancelled batch cleans up properly

### Code Quality

- [ ] All files under 500 lines
- [ ] `composer lint:phpcs` shows 0 warnings, 0 errors
- [ ] `composer lint:phpstan` passes level 3
- [ ] `npm run lint:js` passes
- [ ] No console errors or warnings
- [ ] No PHP notices or warnings

### UI/UX Testing

- [ ] Tab navigation works smoothly
- [ ] Field mapping UI is intuitive
- [ ] Progress bar is clearly visible
- [ ] Completion message is celebratory
- [ ] Error messages are specific and helpful
- [ ] Works on mobile (responsive)

---

## 📊 Success Metrics

**Time Metrics:**
- 100 entries should sync in <5 minutes
- Field mapping configuration in <2 minutes
- Progress updates every 2 seconds

**Quality Metrics:**
- Zero linting warnings
- 100% of database properties mapped correctly
- Zero duplicate posts created
- 95%+ batch success rate (allowing for API errors)

**User Metrics:**
- 5/5 test users can configure mapping
- 5/5 test users can start database sync
- Zero confusion about progress
- Completion feels rewarding

---

## 🚧 Risks & Mitigation

| Risk                                | Impact | Mitigation                                              |
| ----------------------------------- | ------ | ------------------------------------------------------- |
| Large databases cause timeout       | High   | Use Action Scheduler for background processing          |
| Memory exhaustion on 500+ entries   | High   | Process in batches of 10, unset variables after use     |
| Action Scheduler conflicts          | Medium | Version check, graceful fallback to WP-Cron             |
| Complex property types unmappable   | Medium | Provide "ignore" option, log unsupported types          |
| Category/tag creation floods system | Low    | Cache created terms, batch create taxonomy terms        |

---

## ✋ Gatekeeping Review

Before proceeding to Phase 3, schedule a **5-minute demo** with someone who:

- Is NOT a developer
- Has access to a Notion workspace with a database
- Can provide honest feedback

**Demo Script:**

1. Show Notion database with 20+ entries (30 seconds)
2. Navigate to WP Admin > Notion Sync > Databases (15 seconds)
3. Click "Configure Mapping" and set up fields (2 minutes)
4. Click "Sync All" and watch progress bar (2 minutes)
5. Show WordPress posts with correct categories/tags (30 seconds)

**Pass Criteria:**

- They understood the mapping configuration
- Batch sync completed successfully
- All posts created with correct metadata
- No confusion or errors during process
- They could repeat it without help

**If demo fails:**

- Document specific confusion points
- Fix UX issues
- Schedule another demo
- **DO NOT** proceed to Phase 3

---

## 📝 Phase 2 Completion Checklist

### Pre-Phase 2 (BLOCKER)

- [ ] All 24 PHPCS warnings eliminated
- [ ] error_log() calls replaced with proper logging
- [ ] Reserved keyword parameter renamed
- [ ] Line length violations fixed
- [ ] `composer lint:phpcs` shows 0 warnings

### Code Complete

- [ ] All 4 work streams merged to `phase-2-database-sync` branch
- [ ] All files under 500 lines
- [ ] Zero linting errors or warnings
- [ ] All TODO comments resolved

### Testing Complete

- [ ] All functional tests pass
- [ ] Performance tests pass (100+ entries)
- [ ] Tested with 3+ different database structures
- [ ] Tested all property types
- [ ] Batch processing reliable

### Documentation Complete

- [ ] README.md updated with database sync instructions
- [ ] Field mapping guide created
- [ ] API documentation for new classes
- [ ] Troubleshooting guide updated

### Demo Complete

- [ ] 5-minute demo successful with non-developer
- [ ] Field mapping was intuitive
- [ ] Batch sync completed smoothly
- [ ] Ready to show stakeholders

### Ready for Phase 3

- [ ] All gatekeeping criteria met
- [ ] No critical bugs
- [ ] No security issues
- [ ] Team confident to proceed

---

## ⏭️ Next Phase Preview

**Phase 3: Media Handling** will build on this foundation:

- Download images from Notion to WordPress Media Library
- Handle image blocks in database entries
- Deduplicate media across re-syncs
- Support for file attachments (PDFs)
- **Requires:** Working database sync from Phase 2

**Do not start Phase 3 until this checklist is 100% complete.**

---

**Document Version:** 1.0
**Last Updated:** 2025-10-20
**Status:** Ready for Planning Review
