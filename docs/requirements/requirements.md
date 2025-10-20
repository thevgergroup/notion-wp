## Notion to WordPress Sync Plugin — Technical Requirements

### 1. Authentication & Access

- **Notion API Integration Token:**
    - Secure input field in WP admin to store the Notion Internal Integration Token.
    - Store securely using WordPress options or encrypted storage.

- **Access Scope:**
    - Provide clear instructions for the user to share Notion pages/databases with the integration.

- **Workspace/Content Selector:**
    - Use Notion search API to let users browse and select Notion pages or databases to sync.

### 2. Sync Triggers

- **Manual Sync:**
    - Admin UI button to trigger sync on demand.

- **Scheduled Polling:**
    - Use WP-Cron to poll Notion for updates at configured intervals (15 min, hourly, daily).

- **Webhook Support (Optional):**
    - If user's Notion workspace allows it, register webhook for page changes.
    - Webhook handler endpoint in WordPress to process updates.

### 3. Block Mapping & Conversion

- **Block Type Parser:**
    - For each Notion block type, map to equivalent:
        - Gutenberg block
        - Fallback HTML

- **Extensibility:**
    - Provide filter hooks for developers to register custom Notion block -> WP block conversions.

- **Unknown Blocks:**
    - Display as styled warnings or raw HTML for visibility.

### 4. Images & Files

- **Import Images:**
    - Download from Notion's time-limited S3 URLs.
    - Upload to WordPress Media Library.
    - Replace in-content references to use local WP URLs.

- **Metadata Handling:**
    - Capture and set alt text or captions if available.
    - Avoid duplication on re-sync.

- **Support for Files:**
    - Handle PDFs, docs with similar logic.

### 5. Content Type Mapping

- **Target Type:**
    - Option to sync Notion content to:
        - WordPress Pages
        - Posts
        - Custom Post Types

- **Defaults and Per-Sync Settings:**
    - Configure default target post type.
    - Allow override per Notion database/page.

### 6. Page Hierarchy & Navigation

- **Subpage Sync:**
    - Recursively sync child pages of a selected parent.

- **WordPress Hierarchy:**
    - Set parent_page in WP to match Notion structure.

- **Menu Generation:**
    - Create/update a named WP navigation menu reflecting the Notion hierarchy.
    - Auto-add new pages, remove deleted.

### 7. Databases Support

- **Database Sync:**
    - Query Notion database and iterate through items.
    - Fetch content blocks + metadata.

- **Mapping Properties:**
    - Map Notion properties to:
        - Post Title
        - Date
        - Tags/Categories
        - Custom Fields (ACF support)

- **Embedded Views:**
    - Optional shortcode or block to embed Notion DB views in WP.

### 8. Two-Way Sync Support

- **Store Notion ID in WP:**
    - Save Notion page ID in custom meta field.

- **Push to Notion (Optional):**
    - Convert WP content to Notion block format.
    - Upload updated content and metadata via Notion API.

- **Conflict Management:**
    - Compare `last_edited_time` fields.
    - Manual override or push-to-Notion button.

### 9. Sync Strategies

- **Modes:**
    - Add Only
    - Add & Update
    - Full Mirror (add/update/delete)

- **Status Logging:**
    - Maintain sync logs and last updated timestamps per item.

### 10. Admin Interface & Configuration

- **Token Setup & Validation**
- **Content Source Selection (Pages/Databases)**
- **Mapping UI:**
    - Match Notion fields to WP fields.
    - Dropdowns or auto-suggest.

- **Sync Options:**
    - Frequency
    - Content Type Mapping
    - Menu management toggle

- **Debug Logs & Manual Refresh Controls**

### 11. Developer APIs

- **Hooks & Filters:**
    - Block mapping extension hooks
    - Post-processing content filters

- **CLI Commands:**
    - `wp notion-sync run` for scripted syncs

- **Debug Tools:**
    - Logging API calls and block processing

### 12. Testing & Reliability

- **Retry on Failure:**
    - Automatic retries for failed image imports or page fetches

- **Dry Run Mode:**
    - Test a sync without committing changes

- **Unit Test Coverage:**
    - For sync logic, block parsing, field mapping
