# Testing Checklist

Use this checklist when adding new features or fixing bugs to ensure proper test coverage.

## Before You Start Coding

- [ ] Read the [Testing Strategy](./TESTING-STRATEGY.md)
- [ ] Review existing tests for similar components
- [ ] Identify what needs to be tested (business logic, edge cases, errors)
- [ ] Plan test scenarios before implementation

## For Every New Feature

### 1. Unit Tests (Required)

- [ ] Create test file matching source file name (`{ClassName}Test.php`)
- [ ] Test happy path (successful operation)
- [ ] Test edge cases (empty input, null values, boundary conditions)
- [ ] Test error handling (exceptions, invalid input)
- [ ] Mock all external dependencies (WordPress functions, Notion API)
- [ ] Achieve 80%+ code coverage for the feature

### 2. Integration Tests (If Applicable)

Add integration tests if your feature:

- [ ] Interacts with WordPress database (posts, meta, options)
- [ ] Uses Action Scheduler jobs
- [ ] Involves REST API endpoints
- [ ] Requires WordPress Media Library operations

### 3. Documentation

- [ ] Add docblocks to test methods explaining what is tested
- [ ] Update test documentation if adding new patterns
- [ ] Add fixture files for complex test data

## For Block Converters

When adding a new Notion block type converter:

- [ ] Test conversion to correct Gutenberg block format
- [ ] Test handling of empty block content
- [ ] Test rich text annotations (bold, italic, code, links)
- [ ] Test nested blocks (if applicable)
- [ ] Test captions/metadata
- [ ] Test unsupported variations fall back gracefully
- [ ] Add fixture JSON file with real Notion block data
- [ ] Verify HTML output matches WordPress Gutenberg spec

**Example Test Checklist for Image Converter:**
- [ ] External image URL preserved
- [ ] Notion-hosted image downloaded and uploaded
- [ ] Caption converted to figcaption
- [ ] Empty caption handled (no figcaption element)
- [ ] Alt text preserved
- [ ] MediaRegistry prevents duplicate downloads
- [ ] Attachment linked to parent post

## For Media Handling

When working on media download/upload:

- [ ] Test successful download to temp directory
- [ ] Test network error handling (timeout, 404, 500)
- [ ] Test file cleanup after upload
- [ ] Test MediaRegistry deduplication
- [ ] Test attachment to correct parent post
- [ ] Test metadata stored correctly (alt text, title)
- [ ] Test large file handling (if applicable)
- [ ] Test invalid/malformed URLs

## For Sync Operations

When modifying sync logic:

- [ ] Test new post creation
- [ ] Test existing post update (duplicate detection)
- [ ] Test metadata storage (notion_page_id, last_synced)
- [ ] Test error scenarios (Notion API failure, WordPress error)
- [ ] Test link registry updates
- [ ] Test performance logging captures metrics
- [ ] Test dry-run mode (if applicable)

## For API Integration

When working with Notion API:

- [ ] Create fixture JSON files for API responses
- [ ] Test successful API call handling
- [ ] Test rate limit handling (429 errors)
- [ ] Test not found errors (404)
- [ ] Test permission errors (403)
- [ ] Test malformed response handling
- [ ] Test pagination (if applicable)
- [ ] Never hit live Notion API in tests

## Code Quality Checks

Before submitting:

- [ ] All tests pass: `composer test`
- [ ] Unit tests pass: `composer test:unit`
- [ ] Code coverage meets targets: `composer test:coverage`
- [ ] No failing assertions
- [ ] No skipped tests (unless intentional with reason)
- [ ] Tests run in reasonable time (< 2 minutes for unit tests)
- [ ] Tests are isolated (can run in any order)

## Running Tests

```bash
# Run all tests
composer test

# Run unit tests only (fast)
composer test:unit

# Run specific test file
vendor/bin/phpunit tests/unit/Blocks/Converters/ImageConverterTest.php

# Run specific test method
vendor/bin/phpunit --filter test_converts_external_image

# Generate coverage report
composer test:coverage
```

## Test Isolation Verification

- [ ] Tests pass when run individually
- [ ] Tests pass when run in random order: `vendor/bin/phpunit --order-by=random`
- [ ] No global state pollution between tests
- [ ] setUp/tearDown methods reset state properly

## Common Test Scenarios

### Scenario: Adding New Block Type Converter

**Required Tests:**
1. Test `supports()` method returns true for correct block type
2. Test `convert()` produces valid Gutenberg HTML
3. Test empty block handling
4. Test rich text with formatting
5. Test edge cases specific to block type

**Example:**
```php
public function test_supports_quote_block_type(): void {
    $converter = new QuoteConverter();
    $this->assertTrue($converter->supports(['type' => 'quote']));
    $this->assertFalse($converter->supports(['type' => 'paragraph']));
}

public function test_converts_quote_to_gutenberg_block(): void {
    $notion_block = ['type' => 'quote', /* ... */];
    $result = $converter->convert($notion_block);
    $this->assertStringContainsString('<!-- wp:quote -->', $result);
}
```

### Scenario: Adding Media Download Feature

**Required Tests:**
1. Test successful download
2. Test network errors
3. Test file validation
4. Test temp file cleanup
5. Test MediaRegistry integration

**Example:**
```php
public function test_download_saves_to_temp_directory(): void {
    $downloader = new ImageDownloader();
    $result = $downloader->download('https://example.com/image.jpg');

    $this->assertFileExists($result['file_path']);
    $this->assertStringContainsString('/tmp/', $result['file_path']);
}

public function test_download_handles_404_error(): void {
    $this->expectException(DownloadException::class);
    $downloader = new ImageDownloader();
    $downloader->download('https://example.com/nonexistent.jpg');
}
```

### Scenario: Modifying Sync Manager

**Required Tests:**
1. Test happy path sync
2. Test duplicate detection
3. Test error handling
4. Test metadata storage
5. Test validation logic

**Example:**
```php
public function test_sync_page_prevents_duplicate_posts(): void {
    // First sync
    $result1 = $sync_manager->sync_page('page-id-123');
    $this->assertTrue($result1['success']);

    // Second sync should update, not create
    $result2 = $sync_manager->sync_page('page-id-123');
    $this->assertTrue($result2['success']);
    $this->assertEquals($result1['post_id'], $result2['post_id']);
}
```

## Test Coverage Guidelines

### Must Have 95%+ Coverage

- Block converters
- Security/encryption code
- Data transformation functions
- Public API methods

### Should Have 80%+ Coverage

- Sync orchestration
- Media handling
- Error handling logic
- Validation functions

### Lower Priority (60%+)

- Admin UI controllers
- Database schema
- Logging/debugging code

### Can Skip

- WordPress template files
- One-time migration scripts
- Development tools
- Vendor code

## Anti-Patterns to Avoid

### Don't Test WordPress Core

```php
// Bad - testing WordPress, not your code
public function test_wp_insert_post_creates_post(): void {
    $post_id = wp_insert_post(['post_title' => 'Test']);
    $this->assertIsInt($post_id);
}

// Good - testing your code that uses WordPress
public function test_sync_manager_creates_post_with_correct_data(): void {
    $result = $sync_manager->sync_page('notion-page-id');
    $this->assertTrue($result['success']);
    // Verify YOUR logic, not WordPress
}
```

### Don't Hit External APIs

```php
// Bad - hitting live Notion API
public function test_fetch_notion_page(): void {
    $client = new NotionClient(getenv('NOTION_TOKEN'));
    $page = $client->fetch_page('real-page-id'); // NO!
}

// Good - using mocked response
public function test_fetch_notion_page(): void {
    $mock_client = $this->createMock(NotionClient::class);
    $mock_client->method('fetch_page')
        ->willReturn(['id' => 'page-id', 'title' => 'Test']);

    $fetcher = new ContentFetcher($mock_client);
    $result = $fetcher->fetch_page_properties('page-id');
    $this->assertEquals('Test', $result['title']);
}
```

### Don't Test Implementation Details

```php
// Bad - testing private method
public function test_private_method(): void {
    $reflection = new ReflectionClass($object);
    $method = $reflection->getMethod('privateMethod');
    $result = $method->invoke($object, 'input');
}

// Good - testing public behavior
public function test_public_method_behavior(): void {
    $result = $object->publicMethod('input');
    $this->assertEquals('expected', $result);
}
```

### Don't Create Brittle Tests

```php
// Bad - overly specific assertions
public function test_convert_blocks(): void {
    $result = $converter->convert($blocks);
    // Breaks if whitespace changes
    $this->assertEquals("<!-- wp:paragraph -->\n<p>Text</p>\n<!-- /wp:paragraph -->", $result);
}

// Good - test behavior, not exact formatting
public function test_convert_blocks(): void {
    $result = $converter->convert($blocks);
    $this->assertStringContainsString('<!-- wp:paragraph -->', $result);
    $this->assertStringContainsString('<p>Text</p>', $result);
}
```

## When to Write Tests

### Test-Driven Development (Recommended)

1. Write failing test first
2. Implement minimum code to pass
3. Refactor with confidence

### Test-After Development (Acceptable)

1. Implement feature
2. Write comprehensive tests
3. Refactor if needed

### No Tests (Not Acceptable)

Tests are required for:
- All new features
- All bug fixes
- All refactors of critical code

## Getting Help

- Review [Testing Strategy](./TESTING-STRATEGY.md) for philosophy
- Check [Quick Start Guide](./QUICK-START-TESTING.md) for commands
- Look at existing tests for examples
- Ask team for test review

## Final Checklist Before Commit

- [ ] All tests pass locally
- [ ] Coverage report shows adequate coverage
- [ ] Test names clearly describe what is tested
- [ ] No commented-out tests
- [ ] No skipped tests without reason
- [ ] Tests are isolated and repeatable
- [ ] Tests run quickly (< 2 minutes for unit tests)
- [ ] Added fixtures for complex test data
- [ ] Updated documentation if needed
