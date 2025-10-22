# Database Migration Strategy

## Overview

This document outlines the migration path for the Notion database rows schema, including upgrade procedures, rollback strategies, and future evolution scenarios.

## Schema Versioning

Schema versions follow semantic versioning (MAJOR.MINOR.PATCH):

- **MAJOR**: Breaking changes requiring data migration
- **MINOR**: New features/columns, backward compatible
- **PATCH**: Index optimizations, bug fixes

Current version: **1.0.0**

Version is stored in WordPress options table:

```php
get_option('notionwp_schema_version', '0.0.0');
```

## Initial Installation (v1.0.0)

### Prerequisites Check

Before installation, verify MySQL capabilities:

```php
$capabilities = Schema::check_capabilities();

if (!$capabilities['json_support']) {
    // Error: MySQL 5.7.8+ or MariaDB 10.2.7+ required
    return false;
}

if (!$capabilities['virtual_columns']) {
    // Warning: Virtual columns not available
    // Plugin will work but with reduced performance
}
```

### Installation Steps

1. **Create base tables** via `dbDelta()`
    - `wp_notion_database_rows`
    - `wp_notion_database_property_config`
    - `wp_notion_sync_history`

2. **Verify table creation**

    ```php
    $table_exists = $wpdb->get_var(
        "SHOW TABLES LIKE '{$wpdb->prefix}notion_database_rows'"
    );
    ```

3. **Set initial schema version**

    ```php
    update_option('notionwp_schema_version', '1.0.0');
    ```

4. **Create initial property configurations** (if databases already imported)

## Migration Scenarios

### Scenario 1: Add New Virtual Column (v1.1.0)

**Use Case**: User wants to enable indexing on a previously unindexed property.

**Steps**:

1. Check current schema version

    ```php
    $current_version = Schema::get_schema_version();
    if (version_compare($current_version, '1.1.0', '<')) {
        // Perform upgrade
    }
    ```

2. Register property for indexing

    ```php
    Schema::register_property_for_indexing(
        $database_post_id,
        $property_id,
        'Status',
        'select',
        true // Enable index immediately
    );
    ```

3. Virtual column is created automatically
    - No data migration needed
    - Existing rows immediately accessible via new column

4. Update schema version
    ```php
    Schema::update_schema_version('1.1.0');
    ```

**Rollback**: Simply drop the virtual column

```php
Schema::drop_virtual_column('property_status');
```

**Risk Level**: Low (non-destructive, reversible)

---

### Scenario 2: Change Extracted Column (v1.2.0)

**Use Case**: Need to change which common columns are extracted (e.g., add `icon` field).

**Steps**:

1. Create upgrade function

    ```php
    public static function upgrade_to_1_2_0() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'notion_database_rows';

        // Add new column
        $wpdb->query("
            ALTER TABLE $table_name
            ADD COLUMN icon_url VARCHAR(500) DEFAULT NULL AFTER title
        ");

        // Add index
        $wpdb->query("
            ALTER TABLE $table_name
            ADD INDEX idx_icon (icon_url)
        ");

        // Backfill data from JSON for existing rows
        $wpdb->query("
            UPDATE $table_name
            SET icon_url = JSON_UNQUOTE(JSON_EXTRACT(properties_json, '$.icon.external.url'))
            WHERE icon_url IS NULL
        ");

        Schema::update_schema_version('1.2.0');
    }
    ```

2. Run during plugin update
    ```php
    if (Schema::needs_upgrade('1.2.0')) {
        Schema::upgrade_to_1_2_0();
    }
    ```

**Rollback**:

```sql
ALTER TABLE wp_notion_database_rows DROP COLUMN icon_url;
```

**Risk Level**: Medium (requires data backfill, but non-destructive)

---

### Scenario 3: Optimize Indexes (v1.2.1)

**Use Case**: Query analysis shows need for composite index.

**Steps**:

1. Add composite index

    ```php
    public static function upgrade_to_1_2_1() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'notion_database_rows';

        // Add composite index for common query pattern
        $wpdb->query("
            ALTER TABLE $table_name
            ADD INDEX idx_database_status (database_post_id, sync_status)
        ");

        Schema::update_schema_version('1.2.1');
    }
    ```

**Rollback**: Drop the index

```sql
ALTER TABLE wp_notion_database_rows DROP INDEX idx_database_status;
```

**Risk Level**: Low (index changes only)

---

### Scenario 4: Migrate to Option C (EAV) (v2.0.0)

**Use Case**: Performance requirements necessitate full EAV pattern.

**This is a MAJOR version upgrade requiring data migration.**

**Steps**:

1. **Create new table structure**

    ```php
    public static function upgrade_to_2_0_0() {
        global $wpdb;

        // Create new EAV table
        $property_table = $wpdb->prefix . 'notion_row_properties';
        $wpdb->query("
            CREATE TABLE $property_table (
                row_id BIGINT(20) UNSIGNED NOT NULL,
                property_name VARCHAR(100) NOT NULL,
                property_type VARCHAR(50) NOT NULL,
                property_value TEXT,
                property_value_text VARCHAR(255),
                property_value_number DECIMAL(20,6),
                property_value_date DATETIME,
                PRIMARY KEY (row_id, property_name),
                INDEX idx_property (property_name, property_value_text),
                INDEX idx_number (property_name, property_value_number),
                INDEX idx_date (property_name, property_value_date),
                FOREIGN KEY (row_id) REFERENCES {$wpdb->prefix}notion_database_rows(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Migrate data in batches
        self::migrate_json_to_eav();

        Schema::update_schema_version('2.0.0');
    }
    ```

2. **Migrate data** (batched for large datasets)

    ```php
    private static function migrate_json_to_eav() {
        global $wpdb;

        $rows_table = $wpdb->prefix . 'notion_database_rows';
        $props_table = $wpdb->prefix . 'notion_row_properties';

        $batch_size = 100;
        $offset = 0;

        do {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, properties_json FROM $rows_table LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            ));

            foreach ($rows as $row) {
                $properties = json_decode($row->properties_json, true);

                foreach ($properties as $prop_name => $prop_data) {
                    $prop_type = $prop_data['type'] ?? 'unknown';
                    $value = self::extract_property_value($prop_data, $prop_type);

                    // Insert into EAV table
                    $wpdb->insert($props_table, [
                        'row_id' => $row->id,
                        'property_name' => $prop_name,
                        'property_type' => $prop_type,
                        'property_value' => json_encode($value),
                        'property_value_text' => self::get_text_value($value, $prop_type),
                        'property_value_number' => self::get_number_value($value, $prop_type),
                        'property_value_date' => self::get_date_value($value, $prop_type),
                    ]);
                }
            }

            $offset += $batch_size;
        } while (count($rows) === $batch_size);
    }
    ```

3. **Update query layer** to use EAV tables

4. **Keep JSON column** for backward compatibility and full data access

**Rollback**: Complex - requires restoring from backup

```php
// Drop EAV table
DROP TABLE wp_notion_row_properties;

// Revert to v1.x query logic
```

**Risk Level**: High (major architectural change, requires thorough testing)

---

## Rollback Procedures

### General Rollback Strategy

1. **Backup before upgrade**

    ```php
    public static function backup_table($table_name) {
        global $wpdb;
        $backup_name = $table_name . '_backup_' . date('Ymd_His');

        $wpdb->query("CREATE TABLE $backup_name LIKE $table_name");
        $wpdb->query("INSERT INTO $backup_name SELECT * FROM $table_name");

        return $backup_name;
    }
    ```

2. **Rollback function template**

    ```php
    public static function rollback_to_version($target_version) {
        $current_version = self::get_schema_version();

        if (version_compare($current_version, $target_version, '<=')) {
            // Already at or below target version
            return true;
        }

        // Execute version-specific rollback procedures
        switch ($target_version) {
            case '1.2.0':
                self::rollback_from_1_2_1();
                // Fall through
            case '1.1.0':
                self::rollback_from_1_2_0();
                // Fall through
            case '1.0.0':
                self::rollback_from_1_1_0();
                break;
        }

        self::update_schema_version($target_version);
    }
    ```

### Emergency Rollback

If migration fails catastrophically:

1. **Restore from backup**

    ```sql
    DROP TABLE wp_notion_database_rows;
    CREATE TABLE wp_notion_database_rows LIKE wp_notion_database_rows_backup_YYYYMMDD;
    INSERT INTO wp_notion_database_rows SELECT * FROM wp_notion_database_rows_backup_YYYYMMDD;
    ```

2. **Revert schema version**

    ```php
    update_option('notionwp_schema_version', '1.0.0');
    ```

3. **Revert plugin code** to previous version

---

## Testing Upgrades

### Automated Migration Tests

```php
class SchemaUpgradeTest extends WP_UnitTestCase {

    public function test_upgrade_from_1_0_to_1_1() {
        // Set up v1.0 schema
        update_option('notionwp_schema_version', '1.0.0');
        Schema::create_tables();

        // Insert test data
        $this->insert_test_rows(100);

        // Perform upgrade
        Schema::upgrade_to_1_1_0();

        // Verify schema changes
        $this->assertTrue($this->column_exists('property_status'));

        // Verify data integrity
        $this->assertEquals(100, $this->count_rows());
    }

    public function test_rollback_from_1_1_to_1_0() {
        // Set up v1.1 schema
        update_option('notionwp_schema_version', '1.1.0');
        Schema::upgrade_to_1_1_0();

        // Rollback
        Schema::rollback_to_version('1.0.0');

        // Verify rollback
        $this->assertFalse($this->column_exists('property_status'));
        $this->assertEquals('1.0.0', Schema::get_schema_version());
    }
}
```

### Manual Testing Checklist

Before deploying schema upgrade:

- [ ] Backup production database
- [ ] Test upgrade on staging environment
- [ ] Verify all queries still work
- [ ] Check query performance (run EXPLAIN)
- [ ] Test rollback procedure
- [ ] Verify data integrity (row counts, checksums)
- [ ] Test with large dataset (1000+ rows)
- [ ] Test on MySQL 5.7 and 8.0
- [ ] Test on MariaDB 10.2 and 10.6
- [ ] Document any manual steps required

---

## Performance Monitoring

### Track Migration Performance

```php
public static function upgrade_with_metrics($version_function) {
    $start_time = microtime(true);
    $start_memory = memory_get_usage();

    $result = call_user_func($version_function);

    $end_time = microtime(true);
    $end_memory = memory_get_usage();

    $metrics = [
        'execution_time' => $end_time - $start_time,
        'memory_used' => $end_memory - $start_memory,
        'timestamp' => current_time('mysql'),
    ];

    // Log metrics
    error_log(sprintf(
        'Schema upgrade completed in %.2fs, memory: %s',
        $metrics['execution_time'],
        size_format($metrics['memory_used'])
    ));

    return $result;
}
```

---

## Future Evolution Considerations

### Potential Schema Changes (v1.x)

1. **Add relation tracking** (v1.3.0)
    - New column: `related_page_ids` (JSON array)
    - Index on relation properties

2. **Add revision history** (v1.4.0)
    - New table: `wp_notion_row_revisions`
    - Track property changes over time

3. **Add computed properties** (v1.5.0)
    - Virtual columns for formula results
    - Cached rollup values

### Long-term Considerations (v2.x+)

1. **Multi-database support** (v2.0.0)
    - Migrate to EAV pattern for maximum flexibility
    - Support for cross-database queries

2. **Advanced caching layer** (v2.1.0)
    - Redis/Memcached integration
    - Materialized views for common queries

3. **Partitioning strategy** (v2.2.0)
    - Partition by `database_post_id` for very large installations
    - Requires MySQL 8.0+ or MariaDB 10.2+

---

## Documentation Requirements

Each migration must include:

1. **Upgrade Notes** - What changed and why
2. **Breaking Changes** - API changes affecting developers
3. **Performance Impact** - Expected query time changes
4. **Rollback Instructions** - Step-by-step rollback
5. **Testing Checklist** - Required tests before deployment

Store in: `/docs/database/migrations/vX.X.X.md`
