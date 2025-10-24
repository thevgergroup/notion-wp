# Testing Strategy for Notion-WordPress Sync Plugin

## Executive Summary

This document defines a pragmatic, high-value testing strategy for the Notion-WordPress sync plugin. The goal is to build confidence in our code without excessive time testing third-party integrations (Notion API, WordPress Core).

**Core Philosophy:** Test YOUR code, mock THEIR code.

---

## 1. Testing Pyramid & Ratios

### Recommended Test Distribution

```
        /\
       /  \    E2E Tests (5%)
      /    \   - Critical user workflows only
     /------\
    /        \ Integration Tests (25%)
   /          \- WordPress integration points
  /------------\- Action Scheduler jobs
 /              \- REST API endpoints
/________________\ Unit Tests (70%)
                   - Block converters
                   - Data transformations
                   - Business logic
```

**Target Ratio: 70% Unit / 25% Integration / 5% E2E**

### Why This Ratio?

- **Unit tests (70%)**: Fast, reliable, test OUR logic in isolation
- **Integration tests (25%)**: Verify WordPress and Action Scheduler integration
- **E2E tests (5%)**: Smoke tests for critical workflows only (one full sync end-to-end)

---

## 2. Mocking Strategy

### WordPress Functions

**Use Brain\Monkey for unit tests** (already in use):

```php
use Brain\Monkey\Functions;

// Mock WordPress functions
Functions\expect('get_option')
    ->once()
    ->with('notion_wp_token')
    ->andReturn('encrypted_token_value');

Functions\expect('wp_insert_post')
    ->once()
    ->andReturn(42);
```

**Use WordPress Test Suite for integration tests**:
- Install `wordpress/wordpress` test scaffold
- Use `WP_UnitTestCase` base class
- Access real WordPress database operations
- Test actual post creation, meta data, taxonomies

### Notion API Responses

**Strategy: Fixture-Based Mocking**

1. **Record Real Notion API Responses** (one-time setup):
   - Create test fixtures from actual Notion API responses
   - Store in `/tests/fixtures/notion-responses/`
   - Use JSON files for maintainability

2. **Mock in Tests**:
   ```php
   $mock_client = $this->createMock(NotionClient::class);
   $mock_client->method('fetch_page')
       ->willReturn(json_decode(file_get_contents(__DIR__ . '/fixtures/notion-page.json'), true));
   ```

3. **No Live API Calls in Tests**:
   - NEVER hit Notion API in automated tests
   - Use mock data for all Notion API responses
   - Test error scenarios with mock error responses

### Database Operations

**Unit Tests**: Mock all database functions with Brain\Monkey
```php
Functions\expect('get_posts')
    ->once()
    ->andReturn([42]);
```

**Integration Tests**: Use real WordPress test database
```php
class SyncIntegrationTest extends WP_UnitTestCase {
    public function test_sync_creates_post() {
        $result = $sync_manager->sync_page('test-page-id');
        $this->assertTrue($result['success']);

        // Verify post exists in database
        $post = get_post($result['post_id']);
        $this->assertInstanceOf(WP_Post::class, $post);
    }
}
```

---

## 3. Test Organization

### Directory Structure

```
tests/
├── bootstrap.php                    # PHPUnit bootstrap (already exists)
├── phpunit.xml                      # PHPUnit configuration (to create)
├── fixtures/                        # Test data and mocks
│   ├── notion-responses/           # Notion API response JSON files
│   │   ├── page-simple.json
│   │   ├── page-with-media.json
│   │   ├── database-query.json
│   │   ├── blocks-mixed.json
│   │   └── error-not-found.json
│   ├── wordpress-content/          # Expected WordPress output
│   │   ├── gutenberg-paragraph.html
│   │   └── gutenberg-heading.html
│   └── media/                      # Test images/files
│       ├── test-image.jpg
│       └── test-file.pdf
│
├── unit/                           # Fast unit tests with mocks
│   ├── Blocks/
│   │   ├── BlockConverterTest.php          # Registry tests (exists)
│   │   ├── Converters/
│   │   │   ├── ParagraphConverterTest.php  # (exists)
│   │   │   ├── HeadingConverterTest.php    # (exists)
│   │   │   ├── ImageConverterTest.php      # (to create)
│   │   │   ├── FileConverterTest.php       # (to create)
│   │   │   ├── TableConverterTest.php      # (to create)
│   │   │   └── QuoteConverterTest.php      # (to create)
│   │   └── LinkRewriterTest.php            # (to create)
│   │
│   ├── Media/
│   │   ├── ImageDownloaderTest.php         # (to create)
│   │   ├── FileDownloaderTest.php          # (to create)
│   │   ├── MediaUploaderTest.php           # (to create)
│   │   ├── MediaRegistryTest.php           # (to create)
│   │   └── MediaSyncSchedulerTest.php      # (to create)
│   │
│   ├── Sync/
│   │   ├── SyncManagerTest.php             # (exists)
│   │   ├── ContentFetcherTest.php          # (to create)
│   │   └── LinkUpdaterTest.php             # (to create)
│   │
│   ├── API/
│   │   ├── NotionClientTest.php            # (to create)
│   │   └── RateLimiterTest.php             # (to create, if exists)
│   │
│   ├── Database/
│   │   ├── RowRepositoryTest.php           # (to create)
│   │   └── SyncLogSchemaTest.php           # (to create)
│   │
│   └── Security/
│       └── EncryptionTest.php              # (to create)
│
├── integration/                    # WordPress integration tests
│   ├── SyncWorkflowTest.php               # Full sync workflow
│   ├── MediaImportTest.php                # Media download & upload
│   ├── ActionSchedulerTest.php            # Background jobs
│   ├── RESTAPITest.php                    # REST endpoints
│   └── LinkRegistryTest.php               # Link registry with DB
│
└── e2e/                            # End-to-end tests (optional)
    └── FullSyncTest.php                   # One complete sync scenario
```

### Naming Conventions

**Test Files**: `{ClassName}Test.php`
- Example: `ImageDownloaderTest.php` tests `ImageDownloader.php`

**Test Methods**: `test_{what_is_tested}_{expected_behavior}()`
- Example: `test_download_image_saves_to_temp_directory()`
- Example: `test_upload_file_handles_network_error()`

**Fixture Files**: `{scenario}-{variant}.json`
- Example: `page-simple.json`, `page-with-media.json`
- Example: `error-not-found.json`, `error-rate-limit.json`

---

## 4. High-Value Testing Focus

### Priority 1: Block Conversion (70% effort)

**Why**: Core value proposition - incorrect conversion = broken content

**What to Test**:
- All block types convert to correct Gutenberg HTML
- Rich text annotations (bold, italic, code, links)
- Nested blocks (lists with children, toggles)
- Edge cases: empty blocks, malformed data
- Unsupported blocks fall back gracefully

**Example Test**:
```php
public function test_paragraph_converter_handles_bold_text() {
    $notion_block = [
        'type' => 'paragraph',
        'paragraph' => [
            'rich_text' => [
                [
                    'type' => 'text',
                    'text' => ['content' => 'Bold text'],
                    'annotations' => ['bold' => true],
                ],
            ],
        ],
    ];

    $converter = new ParagraphConverter();
    $result = $converter->convert($notion_block);

    $this->assertStringContainsString('<strong>Bold text</strong>', $result);
}
```

### Priority 2: Media Handling (20% effort)

**Why**: Media downloads can fail, duplicate, or consume excessive storage

**What to Test**:
- Image downloads to temp directory
- MediaRegistry prevents duplicate downloads
- Media upload to WordPress Media Library
- Attachment to correct parent post
- Error handling (network failures, invalid URLs)
- Cleanup of temp files

**Example Test**:
```php
public function test_image_downloader_prevents_duplicate_downloads() {
    $downloader = new ImageDownloader();

    // First download
    $result1 = $downloader->download('https://example.com/image.jpg');
    $this->assertFileExists($result1['file_path']);

    // Second download should use cached version
    $result2 = $downloader->download('https://example.com/image.jpg');
    $this->assertEquals($result1['file_path'], $result2['file_path']);
}
```

### Priority 3: Sync Orchestration (10% effort)

**Why**: Sync errors affect entire workflows

**What to Test**:
- Duplicate post detection works correctly
- Error handling returns user-friendly messages
- Post metadata stored correctly
- Link registry updated on sync
- Performance logging captures metrics

**Already Well-Tested**: `SyncManagerTest.php` has excellent coverage

---

## 5. WordPress-Specific Testing

### Use WP_Mock vs WordPress Test Suite?

**Decision: Use BOTH (different purposes)**

| Scenario | Tool | Why |
|----------|------|-----|
| Unit tests for business logic | Brain\Monkey | Fast, no DB setup |
| Testing custom post types | WP Test Suite | Need real WordPress |
| Testing meta data storage | WP Test Suite | Need real database |
| Testing Action Scheduler | WP Test Suite | Need real queue system |
| Testing REST API endpoints | WP Test Suite | Need real WP_REST_Request |

### Testing Action Scheduler Jobs

**Integration Test Approach**:

```php
class ActionSchedulerTest extends WP_UnitTestCase {
    public function test_media_sync_job_scheduled() {
        // Schedule a job
        $job_id = MediaSyncScheduler::schedule_sync('test-page-id');

        // Verify job exists in Action Scheduler
        $job = as_get_scheduled_actions([
            'hook' => 'notion_wp_sync_media',
            'status' => ActionScheduler_Store::STATUS_PENDING,
        ]);

        $this->assertNotEmpty($job);
    }

    public function test_media_sync_job_processes() {
        // Create a pending job
        $job_id = as_enqueue_async_action('notion_wp_sync_media', ['page_id' => 'test']);

        // Run the job
        $runner = ActionScheduler::runner();
        $runner->run();

        // Verify job completed
        $status = as_get_scheduled_actions([
            'hook' => 'notion_wp_sync_media',
            'status' => ActionScheduler_Store::STATUS_COMPLETE,
        ]);

        $this->assertNotEmpty($status);
    }
}
```

### Testing REST API Endpoints

**Integration Test Approach**:

```php
class RESTAPITest extends WP_UnitTestCase {
    public function test_sync_status_endpoint_returns_correct_data() {
        // Create a synced post
        $post_id = $this->factory->post->create();
        update_post_meta($post_id, 'notion_page_id', 'test-page-id');

        // Make REST request
        $request = new WP_REST_Request('GET', '/notion-wp/v1/sync-status/test-page-id');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['is_synced']);
        $this->assertEquals($post_id, $data['post_id']);
    }
}
```

---

## 6. Notion API Testing

### Mock ALL Notion API Calls

**Never hit live Notion API in tests** for these reasons:
1. Tests should run without network
2. Notion API has rate limits
3. Test data can change in Notion
4. Tests become slow and brittle

### Recording Fixtures (One-Time Setup)

Create a script to record real Notion responses:

```php
// scripts/record-notion-fixtures.php
$client = new NotionClient(getenv('NOTION_TOKEN'));

// Record page response
$page = $client->fetch_page('YOUR_TEST_PAGE_ID');
file_put_contents(
    __DIR__ . '/../tests/fixtures/notion-responses/page-simple.json',
    json_encode($page, JSON_PRETTY_PRINT)
);

// Record blocks response
$blocks = $client->fetch_blocks('YOUR_TEST_PAGE_ID');
file_put_contents(
    __DIR__ . '/../tests/fixtures/notion-responses/blocks-mixed.json',
    json_encode($blocks, JSON_PRETTY_PRINT)
);
```

### Testing Error Scenarios

Mock Notion API errors:

```php
public function test_sync_handles_notion_not_found_error() {
    $mock_client = $this->createMock(NotionClient::class);
    $mock_client->method('fetch_page')
        ->willThrowException(new NotionAPIException('Page not found', 404));

    $fetcher = new ContentFetcher($mock_client);
    $sync_manager = new SyncManager($fetcher);

    $result = $sync_manager->sync_page('nonexistent-page-id');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
}
```

### Rate Limit Handling

Test retry logic:

```php
public function test_notion_client_retries_on_rate_limit() {
    $mock_client = $this->createMock(NotionClient::class);
    $mock_client->expects($this->exactly(2))
        ->method('fetch_page')
        ->willReturnOnConsecutiveCalls(
            $this->throwException(new NotionAPIException('Rate limited', 429)),
            ['id' => 'page-id', 'title' => 'Success after retry']
        );

    $fetcher = new ContentFetcher($mock_client);
    $result = $fetcher->fetch_page_properties('page-id');

    $this->assertEquals('Success after retry', $result['title']);
}
```

---

## 7. Code Coverage Goals

### Overall Target: 75-85%

**Not all code needs equal coverage:**

| Component | Target | Rationale |
|-----------|--------|-----------|
| Block Converters | 95% | Core value, high complexity |
| Media Handling | 90% | Critical, many edge cases |
| Sync Orchestration | 85% | Business logic, error paths |
| API Client | 70% | Mostly wrapper, mock responses |
| Admin UI | 50% | Low risk, visual components |
| Database Schema | 60% | WordPress handles most logic |
| Security/Encryption | 95% | Critical for security |

### Must Have Tests

**100% Coverage Required**:
- Public API methods (anything user-facing)
- Block conversion logic
- Media download/upload logic
- Data transformation functions
- Security-sensitive code (encryption, token handling)

### Safe to Skip

**Lower priority or skip**:
- WordPress template files (`templates/`)
- Admin UI JavaScript interactions
- WordPress Core function wrappers (if just calling WP functions)
- Debug/logging code
- One-time migration scripts

---

## 8. Testing Tools & Frameworks

### Required Packages

Add to `composer.json`:

```json
{
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "mockery/mockery": "^1.6",
        "brain/monkey": "^2.6",
        "yoast/phpunit-polyfills": "^2.0"
    }
}
```

### WordPress Test Suite Setup

Install WordPress test scaffold:

```bash
# Install WP-CLI if not already installed
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

# Generate test scaffold
wp scaffold plugin-tests notion-wp

# Install test database
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### BDD/E2E Framework (Optional)

For E2E integration tests, consider **Codeception**:

```json
{
    "require-dev": {
        "codeception/codeception": "^5.0",
        "codeception/module-webdriver": "^3.0",
        "codeception/module-db": "^3.0"
    }
}
```

**Why Codeception?**
- Human-readable BDD syntax
- WordPress module available
- Database assertions
- Selenium integration for browser testing

**Example E2E Test**:
```php
// tests/e2e/FullSyncCept.php
$I = new AcceptanceTester($scenario);
$I->wantTo('Sync a Notion page to WordPress');
$I->loginAsAdmin();
$I->amOnPage('/wp-admin/admin.php?page=notion-wp');
$I->fillField('notion_page_id', 'test-page-id');
$I->click('Sync Page');
$I->see('Sync completed successfully');
$I->seeInDatabase('wp_posts', ['post_title' => 'Test Page Title']);
```

---

## 9. Configuration

### phpunit.xml

Create `/tests/phpunit.xml`:

```xml
<?xml version="1.0"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.6/phpunit.xsd"
    bootstrap="bootstrap.php"
    colors="true"
    stopOnFailure="false"
    verbose="true"
>
    <testsuites>
        <!-- Fast unit tests run first -->
        <testsuite name="unit">
            <directory>unit</directory>
        </testsuite>

        <!-- Integration tests (require WordPress) -->
        <testsuite name="integration">
            <directory>integration</directory>
        </testsuite>

        <!-- E2E tests (slowest) -->
        <testsuite name="e2e">
            <directory>e2e</directory>
        </testsuite>
    </testsuites>

    <coverage>
        <include>
            <directory suffix=".php">../plugin/src</directory>
        </include>
        <exclude>
            <directory>../plugin/src/Admin</directory>
            <file>../plugin/src/Database/Schema.php</file>
        </exclude>
        <report>
            <html outputDirectory="../coverage-html"/>
            <text outputFile="php://stdout" showUncoveredFiles="true"/>
        </report>
    </coverage>

    <php>
        <env name="WP_TESTS_DIR" value="/tmp/wordpress-tests-lib"/>
        <env name="WP_CORE_DIR" value="/tmp/wordpress/"/>
        <const name="WP_DEBUG" value="true"/>
    </php>
</phpunit>
```

### Composer Scripts

Update `composer.json`:

```json
{
    "scripts": {
        "test": "phpunit --testdox",
        "test:unit": "phpunit --testsuite=unit --testdox",
        "test:integration": "phpunit --testsuite=integration --testdox",
        "test:coverage": "phpunit --coverage-html coverage-html",
        "test:watch": "phpunit-watcher watch"
    }
}
```

---

## 10. Prioritized Testing Roadmap

### Phase 1: Critical Path (Week 1)

1. **Block Converters** (HIGH VALUE)
   - [ ] Test all existing converters (Paragraph, Heading, Lists)
   - [ ] Add tests for Image/File converters
   - [ ] Test rich text annotations (bold, italic, links)
   - [ ] Test edge cases (empty blocks, malformed data)

2. **Media Handling** (HIGH VALUE)
   - [ ] Test ImageDownloader (download, caching, errors)
   - [ ] Test MediaRegistry (duplicate prevention)
   - [ ] Test MediaUploader (WordPress integration)

### Phase 2: Integration Points (Week 2)

3. **Sync Orchestration**
   - [ ] Verify existing SyncManagerTest coverage
   - [ ] Add tests for error scenarios
   - [ ] Test duplicate post detection

4. **WordPress Integration**
   - [ ] Setup WordPress test suite
   - [ ] Test post creation/updates with real DB
   - [ ] Test meta data storage
   - [ ] Test Action Scheduler jobs

### Phase 3: API & Security (Week 3)

5. **Notion API Client**
   - [ ] Create fixture responses
   - [ ] Mock all API calls
   - [ ] Test error handling
   - [ ] Test rate limiting

6. **Security**
   - [ ] Test encryption/decryption
   - [ ] Test token storage
   - [ ] Test input validation

### Phase 4: Polish (Week 4)

7. **Database & Utilities**
   - [ ] Test RowRepository
   - [ ] Test LinkRegistry
   - [ ] Test performance logging

8. **E2E Smoke Test**
   - [ ] One full sync workflow
   - [ ] Verify post appears in WordPress
   - [ ] Verify media imported

---

## 11. Example Test Files

### Unit Test Example: ImageConverterTest.php

```php
<?php
namespace NotionWP\Tests\Unit\Blocks\Converters;

use NotionWP\Blocks\Converters\ImageConverter;
use NotionWP\Media\ImageDownloader;
use NotionWP\Media\MediaUploader;
use NotionWP\Media\MediaRegistry;
use PHPUnit\Framework\TestCase;
use Brain\Monkey\Functions;

class ImageConverterTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_converts_external_image_to_gutenberg_block() {
        // Arrange
        $notion_block = [
            'type' => 'image',
            'id' => 'abc123',
            'image' => [
                'type' => 'external',
                'external' => [
                    'url' => 'https://example.com/image.jpg'
                ]
            ]
        ];

        // Mock WordPress functions
        Functions\expect('esc_url')
            ->once()
            ->andReturnUsing(fn($url) => $url);

        $converter = new ImageConverter();

        // Act
        $result = $converter->convert($notion_block);

        // Assert
        $this->assertStringContainsString('<!-- wp:image -->', $result);
        $this->assertStringContainsString('https://example.com/image.jpg', $result);
    }

    public function test_handles_image_with_caption() {
        $notion_block = [
            'type' => 'image',
            'id' => 'abc123',
            'image' => [
                'type' => 'external',
                'external' => ['url' => 'https://example.com/image.jpg'],
                'caption' => [
                    [
                        'type' => 'text',
                        'text' => ['content' => 'My caption']
                    ]
                ]
            ]
        ];

        Functions\expect('esc_url')->andReturnUsing(fn($url) => $url);
        Functions\expect('esc_html')->andReturnUsing(fn($text) => $text);

        $converter = new ImageConverter();
        $result = $converter->convert($notion_block);

        $this->assertStringContainsString('<figcaption>My caption</figcaption>', $result);
    }

    public function test_downloads_notion_file_image() {
        // Test that Notion-hosted images (expiring URLs) are downloaded
        $notion_block = [
            'type' => 'image',
            'id' => 'abc123',
            'image' => [
                'type' => 'file',
                'file' => [
                    'url' => 'https://s3.amazonaws.com/notion/image.jpg?expires=123'
                ]
            ]
        ];

        // Mock downloader
        $mock_downloader = $this->createMock(ImageDownloader::class);
        $mock_downloader->expects($this->once())
            ->method('download')
            ->willReturn(['file_path' => '/tmp/image.jpg']);

        // Mock uploader
        $mock_uploader = $this->createMock(MediaUploader::class);
        $mock_uploader->expects($this->once())
            ->method('upload')
            ->willReturn(42); // Attachment ID

        Functions\expect('wp_get_attachment_url')
            ->once()
            ->with(42)
            ->andReturn('https://wordpress.test/wp-content/uploads/image.jpg');

        $converter = new ImageConverter($mock_downloader, $mock_uploader);
        $converter->set_parent_post_id(1);

        $result = $converter->convert($notion_block);

        $this->assertStringContainsString('https://wordpress.test/wp-content/uploads/image.jpg', $result);
    }
}
```

### Integration Test Example: MediaImportTest.php

```php
<?php
namespace NotionWP\Tests\Integration;

use NotionWP\Media\ImageDownloader;
use NotionWP\Media\MediaUploader;
use NotionWP\Media\MediaRegistry;
use WP_UnitTestCase;

class MediaImportTest extends WP_UnitTestCase {

    public function test_full_media_import_workflow() {
        // Create a test post
        $post_id = $this->factory->post->create(['post_title' => 'Test Post']);

        // Download image (mock HTTP request)
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'example.com/test.jpg') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => file_get_contents(__DIR__ . '/../fixtures/media/test-image.jpg')
                ];
            }
            return $preempt;
        }, 10, 3);

        $downloader = new ImageDownloader();
        $downloaded = $downloader->download('https://example.com/test.jpg');

        // Verify file downloaded
        $this->assertFileExists($downloaded['file_path']);

        // Upload to WordPress
        $uploader = new MediaUploader();
        $attachment_id = $uploader->upload($downloaded['file_path'], [
            'title' => 'Test Image',
            'alt_text' => 'Test alt text'
        ], $post_id);

        // Verify attachment created
        $this->assertIsInt($attachment_id);
        $attachment = get_post($attachment_id);
        $this->assertEquals('attachment', $attachment->post_type);
        $this->assertEquals($post_id, $attachment->post_parent);

        // Verify alt text stored
        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $this->assertEquals('Test alt text', $alt_text);

        // Register in MediaRegistry
        MediaRegistry::register('test-image-id', $attachment_id, 'https://example.com/test.jpg');

        // Verify registry entry
        $found_id = MediaRegistry::find('test-image-id');
        $this->assertEquals($attachment_id, $found_id);
    }

    public function test_prevents_duplicate_media_import() {
        $post_id = $this->factory->post->create();

        // First import
        $attachment_id_1 = $this->factory->attachment->create_upload_object(
            __DIR__ . '/../fixtures/media/test-image.jpg',
            $post_id
        );

        MediaRegistry::register('unique-image-id', $attachment_id_1, 'https://example.com/image.jpg');

        // Second import attempt - should return existing
        $found_id = MediaRegistry::find('unique-image-id');

        $this->assertEquals($attachment_id_1, $found_id);

        // Verify only one attachment in database
        $attachments = get_posts(['post_type' => 'attachment', 'posts_per_page' => -1]);
        $this->assertCount(1, $attachments);
    }
}
```

---

## 12. Running Tests

### Commands

```bash
# Run all tests
composer test

# Run only unit tests (fast)
composer test:unit

# Run integration tests (requires WordPress)
composer test:integration

# Generate coverage report
composer test:coverage
open coverage-html/index.html

# Run specific test file
vendor/bin/phpunit tests/unit/Blocks/Converters/ImageConverterTest.php

# Run specific test method
vendor/bin/phpunit --filter test_converts_external_image_to_gutenberg_block

# Run tests with verbose output
vendor/bin/phpunit --testdox --colors=always
```

### CI Integration (GitHub Actions)

Create `.github/workflows/tests.yml`:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  unit-tests:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.0', '8.1', '8.2']

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, xml, zip
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run unit tests
        run: composer test:unit

      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml

  integration-tests:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        ports:
          - 3306:3306

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'

      - name: Install WordPress test suite
        run: bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest

      - name: Install dependencies
        run: composer install

      - name: Run integration tests
        run: composer test:integration
```

---

## Summary

This testing strategy balances comprehensive coverage with practical development velocity:

1. **70% unit tests** - Fast, reliable, test OUR logic
2. **25% integration tests** - Verify WordPress integration
3. **5% E2E tests** - Smoke test critical workflows
4. **Mock all external APIs** - No live Notion API calls
5. **Focus on high-value code** - Block converters, media handling, sync logic
6. **Realistic coverage goals** - 75-85% overall, 95% for critical paths
7. **Use appropriate tools** - Brain\Monkey for unit, WP Test Suite for integration

**Next Steps**: Follow the Phase 1 roadmap to build test coverage incrementally, starting with block converters and media handling.
