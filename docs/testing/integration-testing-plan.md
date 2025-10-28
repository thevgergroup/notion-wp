# Integration Testing Plan

## Overview

Integration tests verify that multiple components work together correctly with real WordPress functionality. Unlike unit tests that mock WordPress functions, integration tests use the actual WordPress test framework.

## Test Pyramid Strategy

```
    /\
   /E2E\      5% - End-to-End (Browser automation, full stack)
  /----\
 /  In- \     25% - Integration (WordPress + Plugin components)
/tegration\
/---------\
/   Unit   \  70% - Unit Tests (Fast, isolated, mocked)
/-----------\
```

## Current Status

- ✅ **Unit Tests**: 64 passing tests (70% target met)
- ⚠️ **Integration Tests**: Not yet created (need to create `tests/integration/` directory)
- ⚠️ **E2E Tests**: Not yet created (need to create `tests/e2e/` directory)

## Integration Test Requirements

### 1. WordPress Test Framework Setup

**Required:**
- WordPress core test library at `/tmp/wordpress-tests-lib`
- WordPress core at `/tmp/wordpress/`
- Test database configuration

**Installation:**
```bash
# Install WordPress test framework
bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Or use the official script:
svn co https://develop.svn.wordpress.org/tags/latest/tests/phpunit/includes/ /tmp/wordpress-tests-lib/includes
svn co https://develop.svn.wordpress.org/tags/latest/tests/phpunit/data/ /tmp/wordpress-tests-lib/data
```

### 2. Test Categories for Integration

#### A. ImageConverter Integration Tests (5 tests to migrate)

**File**: `tests/integration/Blocks/Converters/ImageConverterIntegrationTest.php`

**Tests to implement:**
1. `test_downloads_notion_hosted_image_to_media_library()`
   - Download actual image from S3 URL
   - Upload to WordPress Media Library
   - Verify attachment created with correct metadata

2. `test_prevents_duplicate_downloads_via_media_registry()`
   - First sync: Download and upload image
   - Second sync: Verify image reused from Media Library
   - Check MediaRegistry prevents duplicate download

3. `test_attaches_image_to_parent_post()`
   - Create WordPress post
   - Download image with parent_post_id set
   - Verify attachment has correct post_parent

4. `test_handles_unsupported_image_types()`
   - Attempt to download TIFF image
   - Verify fallback to external URL link
   - Ensure no attachment created

5. `test_extracts_and_preserves_image_metadata()`
   - Download image with caption from Notion
   - Verify alt text stored in _wp_attachment_image_alt
   - Verify caption stored in post_excerpt

**Dependencies to mock:**
- Notion S3 URLs (use local test files instead)
- ImageDownloader (could use real implementation with local files)
- MediaUploader (use real WordPress wp_insert_attachment)

#### B. MediaRegistry Integration Tests (2 tests)

**File**: `tests/integration/Media/MediaRegistryIntegrationTest.php`

**Tests to implement:**
1. `test_prevents_duplicate_media_downloads_across_syncs()`
   - Insert media record into database
   - Verify find() retrieves correct attachment_id
   - Verify duplicate check works

2. `test_handles_media_url_changes_in_notion()`
   - Register media with URL
   - Check needs_reupload() when URL changes
   - Verify re-download triggered

#### C. SyncManager Integration Tests (3 tests)

**File**: `tests/integration/Sync/SyncManagerIntegrationTest.php`

**Tests to implement:**
1. `test_full_sync_flow_creates_post_with_media()`
   - Fetch page from Notion (mocked API)
   - Convert blocks including images
   - Create WordPress post
   - Verify all meta fields stored
   - Verify images downloaded and attached

2. `test_update_sync_preserves_wordpress_modifications()`
   - First sync: Create post
   - Modify post in WordPress
   - Second sync: Update from Notion
   - Verify WordPress-only fields preserved

3. `test_sync_handles_wordpress_database_errors()`
   - Mock database connection failure
   - Verify graceful error handling
   - Verify error logged correctly

#### D. LinkRegistry Integration Tests (2 tests)

**File**: `tests/integration/Router/LinkRegistryIntegrationTest.php`

**Tests to implement:**
1. `test_registers_notion_to_wordpress_url_mappings()`
   - Create WordPress post
   - Register Notion page ID → WordPress URL
   - Verify find() retrieves correct mapping

2. `test_rewrites_internal_notion_links_to_wordpress_permalinks()`
   - Register multiple page mappings
   - Process content with Notion links
   - Verify links rewritten to WordPress URLs

## Integration Test Base Class

**File**: `tests/integration/IntegrationTestCase.php`

```php
<?php
namespace NotionSync\Tests\Integration;

use WP_UnitTestCase;

/**
 * Base class for integration tests
 *
 * Extends WordPress WP_UnitTestCase which provides:
 * - Real WordPress database
 * - Transaction rollback after each test
 * - WordPress core functions
 */
abstract class IntegrationTestCase extends WP_UnitTestCase {

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();

        // Clean up any leftover data
        $this->clean_database();

        // Initialize plugin tables
        $this->initialize_plugin_tables();
    }

    /**
     * Clean database between tests
     */
    protected function clean_database(): void {
        global $wpdb;

        // Clear plugin tables
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}notion_media_registry" );
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}notion_link_registry" );
    }

    /**
     * Initialize plugin database tables
     */
    protected function initialize_plugin_tables(): void {
        // Run schema creation
        \NotionSync\Database\Schema::create_tables();
    }

    /**
     * Create test attachment
     */
    protected function create_test_attachment( $filename = 'test-image.jpg' ): int {
        $upload_dir = wp_upload_dir();
        $image_path = $upload_dir['path'] . '/' . $filename;

        // Create dummy file
        file_put_contents( $image_path, 'test image data' );

        $attachment_id = wp_insert_attachment(
            [
                'post_title'     => 'Test Image',
                'post_content'   => '',
                'post_status'    => 'inherit',
                'post_mime_type' => 'image/jpeg',
            ],
            $image_path
        );

        return $attachment_id;
    }

    /**
     * Mock Notion API response
     */
    protected function mock_notion_api_response( $page_id, $response_data ): void {
        // Use add_filter to mock Notion API calls
        add_filter( 'pre_http_request', function( $response, $args, $url ) use ( $page_id, $response_data ) {
            if ( strpos( $url, "https://api.notion.com/v1/pages/{$page_id}" ) !== false ) {
                return [
                    'response' => [ 'code' => 200 ],
                    'body'     => json_encode( $response_data ),
                ];
            }
            return $response;
        }, 10, 3 );
    }
}
```

## Running Integration Tests

### Local Development

```bash
# Run only integration tests
php vendor/bin/phpunit --testsuite integration

# Run integration tests with coverage
php vendor/bin/phpunit --testsuite integration --coverage-html coverage-integration
```

### CI/CD Pipeline

Integration tests should run:
1. After unit tests pass
2. Before deployment to staging
3. On pull requests to main branch

```yaml
# .github/workflows/tests.yml
jobs:
  integration-tests:
    runs-on: ubuntu-latest
    needs: unit-tests

    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test

    steps:
      - uses: actions/checkout@v2

      - name: Setup WordPress Test Environment
        run: |
          bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest

      - name: Run Integration Tests
        run: php vendor/bin/phpunit --testsuite integration
```

## Test Data Management

### Fixtures for Integration Tests

**Directory**: `tests/fixtures/`

```
tests/fixtures/
├── notion-responses/
│   ├── page-with-images.json
│   ├── page-with-links.json
│   └── database-query.json
├── test-images/
│   ├── test-image-1.jpg
│   ├── test-image-2.png
│   └── test-pdf.pdf
└── wordpress/
    ├── sample-post.json
    └── sample-attachment.json
```

## Maintenance Strategy

1. **Keep integration tests focused** - Test component interactions, not individual methods
2. **Use real WordPress functions** - Don't mock WordPress core
3. **Clean up after tests** - Use transactions or manual cleanup in tearDown()
4. **Keep tests fast** - Aim for <1 second per test
5. **Document dependencies** - Note which WordPress version/features required

## Migration Plan

### Phase 1: Setup (Week 1)
- [ ] Create `tests/integration/` directory structure
- [ ] Create IntegrationTestCase base class
- [ ] Set up WordPress test framework locally
- [ ] Configure CI/CD for integration tests

### Phase 2: Migrate Existing Tests (Week 2)
- [ ] Migrate 5 ImageConverter tests
- [ ] Migrate 2 MediaRegistry tests
- [ ] Delete from unit tests

### Phase 3: Add New Integration Tests (Week 3)
- [ ] Add SyncManager integration tests
- [ ] Add LinkRegistry integration tests
- [ ] Add end-to-end sync flow test

### Phase 4: Coverage & Documentation (Week 4)
- [ ] Achieve 60%+ integration test coverage
- [ ] Document integration test patterns
- [ ] Create troubleshooting guide

## Success Criteria

- ✅ Integration tests run in <30 seconds
- ✅ All tests can run in CI/CD
- ✅ Tests use real WordPress functions
- ✅ 25% of total tests are integration tests
- ✅ Clear separation between unit/integration/e2e
