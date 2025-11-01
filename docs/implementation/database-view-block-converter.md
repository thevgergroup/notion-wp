# Database View Block Converter Implementation

## Overview

Enhanced the `ChildDatabaseConverter` to automatically create interactive `notion-wp/database-view` blocks when syncing Notion pages that contain database references, instead of static links.

## Problem Statement

Previously, when syncing Notion pages containing database views or links, the sync would create:
- `notion-sync/notion-link` blocks (just links to Notion)
- Static `wp:table` blocks (converted database content)

This resulted in poor user experience because users had to click through to Notion to interact with databases, even when those databases had been synced to WordPress.

## Solution

The converter now intelligently detects whether a referenced Notion database has been synced to WordPress as a `notion_database` post. Based on this:

1. **If database is synced**: Creates a `notion-wp/database-view` block with interactive Tabulator table
2. **If database is not synced**: Falls back to `notion-sync/notion-link` block pointing to Notion

## Implementation Details

### File Modified

**Location**: `/Users/patrick/Projects/thevgergroup/notion-wp-phase-5.3/plugin/src/Blocks/Converters/ChildDatabaseConverter.php`

### Key Changes

#### 1. Added DatabasePostType Dependency

```php
use NotionSync\Database\DatabasePostType;

class ChildDatabaseConverter implements BlockConverterInterface {
    private DatabasePostType $database_post_type;

    public function __construct() {
        $this->database_post_type = new DatabasePostType();
    }
}
```

#### 2. Updated Convert Logic

```php
public function convert( array $notion_block ): string {
    // Normalize database ID (remove dashes)
    $normalized_id = str_replace( '-', '', $database_id );

    // Check if database has been synced to WordPress
    $db_post_id = $this->find_database_post_by_notion_id( $normalized_id );

    if ( $db_post_id ) {
        // Database exists - create interactive database-view block
        return $this->create_database_view_block( $db_post_id );
    }

    // Database not synced - fall back to notion-link block
    return $this->create_notion_link_block( $normalized_id );
}
```

#### 3. Added Helper Methods

**`find_database_post_by_notion_id()`**
- Searches for a `notion_database` post with the specified Notion ID
- Uses `DatabasePostType::find_by_notion_id()` for lookup
- Returns WordPress post ID if found, null otherwise

**`create_database_view_block()`**
- Creates a `notion-wp/database-view` Gutenberg block
- Sets default attributes: table view, filters enabled, export enabled
- Returns formatted block HTML

**`create_notion_link_block()`**
- Creates a `notion-sync/notion-link` Gutenberg block
- Opens in new tab by default for databases
- Returns formatted block HTML

## Block Output Examples

### Synced Database

**Input**: Notion `child_database` block with ID `2654dac9b96e808ab3b7ffb185d4fd92`

**Output**:
```html
<!-- wp:notion-wp/database-view {"databaseId":6,"viewType":"table","showFilters":true,"showExport":true} /-->
```

**Rendered Result**: Interactive Tabulator table with:
- Sortable columns
- Filterable data
- CSV export
- Pagination

### Unsynced Database

**Input**: Notion `child_database` block with ID `3a45b6c7d890123456789abcdef123456`

**Output**:
```html
<!-- wp:notion-sync/notion-link {"notionId":"3a45b6c7d890123456789abcdef123456","showIcon":true,"openInNewTab":true} /-->
```

**Rendered Result**: Link to database in Notion with database icon

## Testing

### Unit Tests

Created comprehensive unit tests in `/Users/patrick/Projects/thevgergroup/notion-wp-phase-5.3/tests/unit/Blocks/Converters/ChildDatabaseConverterTest.php`:

1. **test_supports_child_database_blocks**: Verifies converter handles `child_database` type
2. **test_convert_creates_database_view_block_for_synced_database**: Tests creation of database-view blocks
3. **test_convert_creates_notion_link_block_for_unsynced_database**: Tests fallback to notion-link
4. **test_convert_handles_missing_database_id**: Tests error handling
5. **test_convert_handles_missing_title**: Tests default title behavior
6. **test_convert_normalizes_database_id**: Verifies ID normalization (dash removal)

### Code Quality

**PHPCS**: ✓ Passes (0 errors, 0 warnings)
**PHPStan**: ✓ Passes (level 8)
**PHP Syntax**: ✓ Valid

## How to Test with Post 53

### Before Implementation

```bash
# View post 53 content
wp post get 53 --field=post_content

# You would see:
# <!-- wp:notion-sync/notion-link {"notionId":"2654dac9b96e808ab3b7ffb185d4fd92",...} /-->
```

### After Implementation

1. **Ensure database is synced**:
   ```bash
   # Check if database exists
   wp post list --post_type=notion_database --meta_key=notion_database_id --meta_value=2654dac9b96e808ab3b7ffb185d4fd92
   ```

2. **Re-sync post 53**:
   ```bash
   wp notion sync page <page-id>
   # or via admin UI: Notion Sync > Pages > Re-sync
   ```

3. **Verify new block**:
   ```bash
   wp post get 53 --field=post_content

   # You should see:
   # <!-- wp:notion-wp/database-view {"databaseId":6,...} /-->
   ```

4. **View on frontend**:
   - Navigate to the post URL
   - Should see interactive Tabulator table instead of link
   - Table should have filtering, sorting, and export capabilities

## Database Sync Workflow

For the converter to create database-view blocks, databases must be synced first:

1. **Manual Sync**: Admin → Notion Databases → Sync Database
2. **Automatic Sync**: During page sync, child databases are registered in LinkRegistry
3. **Batch Sync**: CLI command or scheduled sync job

The converter automatically detects sync status during page conversion.

## Benefits

### User Experience
- Interactive tables embedded directly in WordPress content
- No need to navigate to Notion for basic data viewing
- Consistent UI/UX with other WordPress content

### Performance
- Database data cached in WordPress
- Faster page loads (no external API calls on frontend)
- Works offline once synced

### Flexibility
- Automatic fallback for unsynced databases
- Graceful degradation
- No manual configuration needed

## Edge Cases Handled

1. **Missing Database ID**: Creates paragraph with database title
2. **Missing Title**: Uses "Untitled Database" as default
3. **Database Not Synced Yet**: Creates notion-link with registry entry for future reference
4. **ID Normalization**: Removes dashes from Notion IDs for consistent lookups
5. **Re-sync After Database Sync**: Automatically upgrades notion-link to database-view on next sync

## Integration Points

### BlockConverter Registry

The converter is registered in `BlockConverter::register_default_converters()`:

```php
new Converters\ChildDatabaseConverter(),
```

No changes needed - constructor instantiation handles dependency injection.

### Database Post Type

Leverages existing `DatabasePostType::find_by_notion_id()` method:

```php
public function find_by_notion_id( string $notion_database_id ): ?int {
    // Searches wp_postmeta for notion_database_id
    // Returns post ID if found, null otherwise
}
```

### Link Registry

Still used for unsynced databases to track references:

```php
$registry->register([
    'notion_id'    => $normalized_id,
    'notion_title' => $title,
    'notion_type'  => 'database',
]);
```

## Future Enhancements

### Configurable Block Attributes

Allow users to set default view preferences:

```php
// In future: read from plugin settings
$view_type = get_option( 'notion_wp_default_db_view', 'table' );
$show_filters = get_option( 'notion_wp_db_show_filters', true );
```

### View Type Detection

Detect Notion database view type and map to appropriate WordPress block:

- Table view → Tabulator table
- Board view → Kanban board
- Gallery view → Gallery block
- Calendar view → Calendar block

### Custom Field Mapping

Allow mapping of database properties to ACF or custom fields:

```php
// Custom field mapping configuration
$field_mappings = get_post_meta( $db_post_id, 'notion_field_mappings', true );
```

## Related Files

- **Block Renderer**: `/Users/patrick/Projects/thevgergroup/notion-wp-phase-5.3/plugin/blocks/database-view/render.php`
- **Block Registration**: `/Users/patrick/Projects/thevgergroup/notion-wp-phase-5.3/plugin/src/Blocks/DatabaseViewBlock.php`
- **Database Post Type**: `/Users/patrick/Projects/thevgergroup/notion-wp-phase-5.3/plugin/src/Database/DatabasePostType.php`
- **Link Registry**: `/Users/patrick/Projects/thevgergroup/notion-wp-phase-5.3/plugin/src/Router/LinkRegistry.php`
- **Examples**: `/Users/patrick/Projects/thevgergroup/notion-wp-phase-5.3/docs/examples/child-database-converter-example.php`

## Troubleshooting

### Database View Block Not Created

**Symptom**: Still seeing notion-link blocks after sync

**Possible Causes**:
1. Database not synced yet
   - **Solution**: Sync the database first via admin UI
2. Notion ID mismatch
   - **Solution**: Check `wp_postmeta` for exact `notion_database_id` value
3. Database post deleted
   - **Solution**: Re-sync database from Notion

**Debug Commands**:
```bash
# Check if database post exists
wp post list --post_type=notion_database

# Check specific database by Notion ID
wp db query "SELECT post_id FROM wp_postmeta WHERE meta_key='notion_database_id' AND meta_value='<notion-id>'"

# Enable debug logging
tail -f wp-content/debug.log | grep ChildDatabaseConverter
```

### Interactive Table Not Rendering

**Symptom**: Block created but table doesn't show

**Possible Causes**:
1. Tabulator assets not loading
   - **Solution**: Check browser console for JS errors
2. Database data not available
   - **Solution**: Verify data in custom table
3. Block registration failed
   - **Solution**: Check `DatabaseViewBlock::register_block()`

## Performance Considerations

### Database Lookups

The converter performs one database query per child_database block:

```sql
SELECT post_id FROM wp_postmeta
WHERE meta_key = 'notion_database_id'
AND meta_value = '<notion-id>'
LIMIT 1
```

**Optimization**: For pages with many databases, consider:
- Caching database lookups during sync session
- Batch querying for multiple database IDs
- Using object cache for frequently accessed databases

### Memory Usage

No significant memory impact:
- No additional data structures stored
- Database lookup result is an integer (post ID)
- Block HTML is generated on-the-fly

## Backwards Compatibility

This change is **fully backwards compatible**:

1. **Existing notion-link blocks**: Continue to work as before
2. **Unsynced databases**: Still create notion-link blocks (same behavior)
3. **Re-sync**: Old links automatically upgraded to database-view blocks

No migration or upgrade script needed.

## Conclusion

The enhanced `ChildDatabaseConverter` provides intelligent, automatic block selection based on database sync status. This creates a seamless user experience where synced databases are displayed interactively while unsynced databases gracefully fall back to links.

The implementation:
- ✓ Is fully tested
- ✓ Passes code quality checks
- ✓ Maintains backwards compatibility
- ✓ Handles edge cases gracefully
- ✓ Provides excellent user experience
- ✓ Requires no manual configuration

Users can now sync Notion pages containing databases and automatically get interactive tables embedded in their WordPress content.
