# Quick Start: Testing Guide

A practical guide to get started with testing the Notion-WordPress sync plugin.

## Installation

### 1. Install Test Dependencies

```bash
cd /Users/patrick/Projects/thevgergroup/notion-wp/worktrees/testing-improvements

# Install PHPUnit and testing tools
composer require --dev phpunit/phpunit:^9.6
composer require --dev mockery/mockery:^1.6
composer require --dev brain/monkey:^2.6
composer require --dev yoast/phpunit-polyfills:^2.0
```

### 2. Verify Installation

```bash
vendor/bin/phpunit --version
# Should output: PHPUnit 9.6.x
```

## Running Tests

### Run All Tests

```bash
composer test
# or
vendor/bin/phpunit
```

### Run Specific Test Suites

```bash
# Unit tests only (fast)
composer test:unit
# or
vendor/bin/phpunit --testsuite=unit

# Integration tests only (requires WordPress)
composer test:integration
# or
vendor/bin/phpunit --testsuite=integration
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/unit/Blocks/Converters/ImageConverterTest.php
```

### Run Specific Test Method

```bash
vendor/bin/phpunit --filter test_converts_external_image_to_gutenberg_block
```

### Generate Coverage Report

```bash
composer test:coverage
# Then open: coverage-html/index.html
```

## Writing Your First Test

### Step 1: Create Test File

Create a test file matching your source file:
- Source: `plugin/src/Media/ImageDownloader.php`
- Test: `tests/unit/Media/ImageDownloaderTest.php`

### Step 2: Write Test Class

```php
<?php
namespace NotionWP\Tests\Unit\Media;

use NotionWP\Media\ImageDownloader;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class ImageDownloaderTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_download_saves_to_temp_directory(): void {
        // Arrange
        $downloader = new ImageDownloader();
        $image_url = 'https://example.com/image.jpg';

        // Mock WordPress functions
        Functions\expect('wp_remote_get')
            ->once()
            ->with($image_url)
            ->andReturn([
                'response' => ['code' => 200],
                'body' => 'fake-image-data'
            ]);

        // Act
        $result = $downloader->download($image_url);

        // Assert
        $this->assertFileExists($result['file_path']);
    }
}
```

### Step 3: Run Your Test

```bash
vendor/bin/phpunit tests/unit/Media/ImageDownloaderTest.php
```

## Common Testing Patterns

### Pattern 1: Mocking WordPress Functions

```php
use Brain\Monkey\Functions;

// Expect function called once with specific arguments
Functions\expect('get_option')
    ->once()
    ->with('notion_wp_token')
    ->andReturn('encrypted_token');

// Allow function to be called any number of times
Functions\when('esc_html')
    ->returnArg(); // Return first argument
```

### Pattern 2: Testing with Fixtures

```php
public function test_converts_notion_page_from_fixture(): void {
    // Load fixture
    $fixture = json_decode(
        file_get_contents(__DIR__ . '/../../fixtures/notion-responses/page-simple.json'),
        true
    );

    // Use in test
    $converter = new PageConverter();
    $result = $converter->convert($fixture);

    $this->assertEquals('Simple Test Page', $result['title']);
}
```

### Pattern 3: Testing Exceptions

```php
public function test_throws_exception_on_invalid_input(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Page ID cannot be empty');

    $sync_manager = new SyncManager();
    $sync_manager->sync_page(''); // Should throw
}
```

### Pattern 4: Testing Private Methods

Don't test private methods directly. Test them indirectly through public methods:

```php
// Don't do this:
// $reflection = new ReflectionClass($object);
// $method = $reflection->getMethod('privateMethod');

// Do this instead:
public function test_public_method_uses_private_method(): void {
    $object = new MyClass();
    $result = $object->publicMethod(); // This calls privateMethod internally

    $this->assertEquals('expected', $result);
}
```

## Integration Testing (WordPress Test Suite)

### Setup (One-time)

```bash
# Install WP-CLI
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

# Generate test scaffold
cd /Users/patrick/Projects/thevgergroup/notion-wp/worktrees/testing-improvements
wp scaffold plugin-tests notion-wp

# Install WordPress test database
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### Write Integration Test

```php
<?php
namespace NotionWP\Tests\Integration;

use WP_UnitTestCase;

class MediaImportTest extends WP_UnitTestCase {

    public function test_creates_attachment_in_media_library(): void {
        // Create test post
        $post_id = $this->factory->post->create([
            'post_title' => 'Test Post'
        ]);

        // Upload image
        $uploader = new \NotionWP\Media\MediaUploader();
        $attachment_id = $uploader->upload(
            '/path/to/image.jpg',
            ['title' => 'Test Image'],
            $post_id
        );

        // Assert attachment exists in database
        $attachment = get_post($attachment_id);
        $this->assertInstanceOf(\WP_Post::class, $attachment);
        $this->assertEquals('attachment', $attachment->post_type);
        $this->assertEquals($post_id, $attachment->post_parent);
    }
}
```

## Debugging Tests

### Enable Verbose Output

```bash
vendor/bin/phpunit --testdox --verbose
```

### Run Single Test with Debug

```bash
vendor/bin/phpunit --filter test_name --debug
```

### Check for Test Isolation Issues

```bash
# Run tests in different order
vendor/bin/phpunit --order-by=random

# Run specific test in isolation
vendor/bin/phpunit --filter test_name --no-coverage
```

### Use var_dump in Tests

```php
public function test_something(): void {
    $result = $this->converter->convert($block);

    var_dump($result); // Debug output

    $this->assertEquals('expected', $result);
}
```

## Common Issues & Solutions

### Issue: "Class not found"

**Solution**: Make sure autoloader is working:
```bash
composer dump-autoload
```

### Issue: "Brain\Monkey setup must be called"

**Solution**: Add setUp/tearDown methods:
```php
protected function setUp(): void {
    parent::setUp();
    Monkey\setUp();
}

protected function tearDown(): void {
    Monkey\tearDown();
    parent::tearDown();
}
```

### Issue: "WordPress function not found"

**Solution**: Mock the function with Brain\Monkey:
```php
Functions\when('the_function')->justReturn('value');
```

### Issue: Tests pass individually but fail together

**Solution**: Reset state in setUp/tearDown:
```php
protected function setUp(): void {
    parent::setUp();
    Monkey\setUp();
    // Reset any global state here
}
```

## Test Coverage Best Practices

### View Coverage Report

```bash
composer test:coverage
open coverage-html/index.html
```

### Focus on These Metrics

- **Line Coverage**: Aim for 80%+ on critical classes
- **Branch Coverage**: Test both if/else paths
- **CRAP Score**: Keep below 30 (cyclomatic complexity)

### Prioritize Coverage For

1. Block converters (95% target)
2. Media handling (90% target)
3. Sync orchestration (85% target)
4. Public APIs (100% target)

### Skip Coverage For

1. Admin UI templates
2. WordPress function wrappers
3. Debug logging code
4. One-time migration scripts

## Test Naming Conventions

### Good Test Names

```php
// Describes WHAT is tested and WHAT is expected
public function test_download_image_saves_to_temp_directory(): void
public function test_sync_page_creates_new_post_when_not_exists(): void
public function test_convert_blocks_handles_empty_array(): void
```

### Bad Test Names

```php
// Vague or unclear
public function test_download(): void
public function test_sync(): void
public function test_1(): void
```

## Next Steps

1. **Read**: `/docs/testing/TESTING-STRATEGY.md` for comprehensive strategy
2. **Run**: Existing tests to verify setup
3. **Write**: Tests for your component following examples
4. **Review**: Coverage report to find gaps
5. **Iterate**: Add more tests based on coverage

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Brain\Monkey Documentation](https://giuseppe-mazzapica.gitbook.io/brain-monkey/)
- [WordPress Testing Guide](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [Testing Strategy (this project)](./TESTING-STRATEGY.md)

## Quick Commands Reference

```bash
# Install test dependencies
composer require --dev phpunit/phpunit brain/monkey mockery/mockery

# Run all tests
composer test

# Run unit tests only
composer test:unit

# Run specific test file
vendor/bin/phpunit tests/unit/Media/ImageDownloaderTest.php

# Run specific test method
vendor/bin/phpunit --filter test_download_saves_to_temp_directory

# Generate coverage
composer test:coverage

# Run with verbose output
vendor/bin/phpunit --testdox --verbose

# Run in random order (check test isolation)
vendor/bin/phpunit --order-by=random
```
