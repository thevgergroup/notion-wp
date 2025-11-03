# Testing Guide

Comprehensive testing documentation for Notion Sync for WordPress. This guide covers our testing infrastructure, how to run tests, and contribution guidelines.

---

## Table of Contents

- [Quick Start](#quick-start)
- [Test Status](#test-status)
- [Testing Infrastructure](#testing-infrastructure)
- [Running Tests](#running-tests)
- [Test Organization](#test-organization)
- [Writing Tests](#writing-tests)
- [Code Coverage](#code-coverage)
- [Continuous Integration](#continuous-integration)
- [Testing Best Practices](#testing-best-practices)

---

## Quick Start

```bash
# Install dependencies
composer install

# Run all tests
make test

# Run tests with coverage
make test-coverage

# Run specific test suite
php vendor/bin/phpunit tests/unit/Sync/SyncManagerTest.php

# Run tests in watch mode (requires Docker)
make test-watch
```

---

## Test Status

[![Tests](https://github.com/thevgergroup/notion-wp/actions/workflows/test.yml/badge.svg)](https://github.com/thevgergroup/notion-wp/actions/workflows/test.yml)
[![Coverage](https://img.shields.io/endpoint?url=https://gist.githubusercontent.com/pjaol/2cb753e52d7fcf0a1176d34f406ad613/raw/notion-wp-coverage.json)](https://gist.github.com/pjaol/2cb753e52d7fcf0a1176d34f406ad613)

### Current Metrics

✅ **261+ tests passing**
✅ **0 errors, 0 failures**
✅ **Comprehensive test coverage across all core components**

### Test Distribution

| Test Type | Count | Coverage |
|-----------|-------|----------|
| Unit Tests | 250+ | API Client, Block Converters, Sync Engine, Media Handling |
| Integration Tests | 11+ | Database Operations, WordPress Integration |
| Total | 261+ | All Core Functionality |

---

## Testing Infrastructure

### Test Framework

- **PHPUnit 9.x** - Primary testing framework
- **Brain Monkey** - WordPress function mocking for unit tests
- **Mockery** - Advanced mocking capabilities
- **WP_Mock** - WordPress-specific test helpers

### Docker Test Environment

```yaml
Services:
- WordPress (latest)
- MySQL 8.0
- PHPUnit with Xdebug
- WP-CLI for database setup
```

### Directory Structure

```
tests/
├── bootstrap.php           # Test bootstrap with WP_Error stub
├── unit/                   # Unit tests (fast, no WordPress)
│   ├── BaseTestCase.php   # WordPress function mocks
│   ├── API/               # Notion API client tests
│   ├── Blocks/            # Block converter tests
│   │   └── Converters/
│   │       └── BaseConverterTestCase.php
│   ├── Sync/              # Sync engine tests
│   ├── Media/             # Media handling tests
│   └── Database/          # Database operation tests
├── integration/            # Integration tests (with WordPress)
│   └── [Coming soon]
└── fixtures/              # Test data and fixtures
    └── notion-blocks/     # Notion API response samples
```

---

## Running Tests

### Using Make Commands

```bash
# Run all tests
make test

# Run with coverage report
make test-coverage

# Run in watch mode (auto-rerun on file changes)
make test-watch

# Run specific test file
make test FILE=tests/unit/Sync/SyncManagerTest.php

# Run with detailed output
make test ARGS="--testdox --verbose"
```

### Using Docker

```bash
# Start test environment
make up

# Run tests in container
docker-compose exec wp vendor/bin/phpunit

# Run with coverage
docker-compose exec wp vendor/bin/phpunit --coverage-html coverage

# Stop environment
make down
```

### Direct PHPUnit

```bash
# All tests
php vendor/bin/phpunit

# Specific test suite
php vendor/bin/phpunit --testsuite unit

# Specific test file
php vendor/bin/phpunit tests/unit/Blocks/Converters/ParagraphConverterTest.php

# Specific test method
php vendor/bin/phpunit --filter test_converts_simple_paragraph

# With coverage
php vendor/bin/phpunit --coverage-html coverage-html
```

### Using Composer Scripts

```bash
# Run tests
composer test

# Run with coverage
composer test:coverage

# Run code quality checks
composer lint

# Run both tests and linting
composer check
```

---

## Test Organization

### Unit Tests

**Location:** `tests/unit/`

**Purpose:** Test individual classes and methods in isolation

**Characteristics:**
- Fast execution (< 1 second total)
- No WordPress dependencies
- Uses mocked WordPress functions via Brain Monkey
- Tests business logic and algorithms

**Example:**
```php
class ParagraphConverterTest extends BaseConverterTestCase {
    public function test_converts_simple_paragraph(): void {
        $notion_block = $this->get_fixture( 'paragraph-simple.json' );

        $converter = new ParagraphConverter();
        $result = $converter->convert( $notion_block );

        $this->assertStringContainsString( '<!-- wp:paragraph -->', $result );
    }
}
```

### Integration Tests

**Location:** `tests/integration/` (Coming soon)

**Purpose:** Test WordPress integration and component interactions

**Characteristics:**
- Uses real WordPress test framework (WP_UnitTestCase)
- Tests database operations with actual WordPress database
- Tests WordPress hooks, filters, and APIs
- Slower execution but verifies real-world behavior

**Planned:**
- ImageConverter with Media Library integration
- MediaRegistry database operations
- LinkRegistry with WordPress posts
- Menu generation with WordPress nav menus

### Test Fixtures

**Location:** `tests/fixtures/notion-blocks/`

**Purpose:** Realistic Notion API response data for testing

**Available Fixtures:**
- `paragraph-simple.json` - Basic paragraph block
- `paragraph-formatted.json` - Paragraph with formatting
- `paragraph-with-link.json` - Paragraph with inline links
- `heading-1.json`, `heading-2.json` - Heading blocks
- `bulleted-list.json`, `numbered-list.json` - List blocks

**Usage:**
```php
$fixture = json_decode(
    file_get_contents( __DIR__ . '/../fixtures/notion-blocks/paragraph-simple.json' ),
    true
);
```

---

## Writing Tests

### Test Class Structure

```php
<?php
namespace NotionSync\Tests\Unit\Blocks\Converters;

use NotionSync\Blocks\Converters\ParagraphConverter;
use NotionSync\Tests\Unit\Blocks\Converters\BaseConverterTestCase;

class ParagraphConverterTest extends BaseConverterTestCase {
    private ParagraphConverter $converter;

    protected function setUp(): void {
        parent::setUp();
        $this->converter = new ParagraphConverter();
    }

    public function test_converts_simple_paragraph(): void {
        // Arrange
        $notion_block = [
            'type' => 'paragraph',
            'paragraph' => [
                'rich_text' => [
                    [ 'plain_text' => 'Hello world' ]
                ]
            ]
        ];

        // Act
        $result = $this->converter->convert( $notion_block );

        // Assert
        $this->assertStringContainsString( 'Hello world', $result );
        $this->assertStringContainsString( '<!-- wp:paragraph -->', $result );
    }
}
```

### Using Base Test Cases

**BaseTestCase** - For classes that use WordPress functions:

```php
class SyncManagerTest extends BaseTestCase {
    protected function setUp(): void {
        parent::setUp();
        // All WordPress functions are mocked
        // wp_insert_post, get_post_meta, etc.
    }
}
```

**BaseConverterTestCase** - For block converters:

```php
class HeadingConverterTest extends BaseConverterTestCase {
    // Provides:
    // - esc_html(), esc_url(), esc_attr()
    // - apply_filters() pass-through
    // - get_fixture() helper
}
```

### Mocking WordPress Functions

```php
use function Brain\Monkey\Functions\when;

// Simple return value
when( 'get_option' )->justReturn( 'some_value' );

// Return based on argument
when( 'get_post_meta' )
    ->with( 123, 'notion_page_id', true )
    ->justReturn( 'abc-123' );

// Return different values on subsequent calls
when( 'wp_insert_post' )
    ->justReturn( 123, 456, 789 );
```

### Testing Error Conditions

```php
public function test_handles_api_error(): void {
    // Arrange
    $client = $this->createMock( NotionClient::class );
    $client->method( 'get_page' )
           ->willThrowException( new \Exception( 'API Error' ) );

    // Act
    $result = $manager->sync_page( 'page-id' );

    // Assert
    $this->assertInstanceOf( WP_Error::class, $result );
    $this->assertEquals( 'api_error', $result->get_error_code() );
}
```

### Using Test Fixtures

```php
protected function get_fixture( string $filename ): array {
    $path = __DIR__ . '/../../fixtures/notion-blocks/' . $filename;
    return json_decode( file_get_contents( $path ), true );
}
```

---

## Code Coverage

### Generating Coverage Reports

```bash
# HTML report (most detailed)
php vendor/bin/phpunit --coverage-html coverage-html
open coverage-html/index.html

# Text summary
php vendor/bin/phpunit --coverage-text

# Clover XML (for CI/CD)
php vendor/bin/phpunit --coverage-clover coverage.xml
```

### Coverage Targets

| Component | Target | Status |
|-----------|--------|--------|
| API Client | 90%+ | ✅ Achieved |
| Block Converters | 85%+ | ✅ Achieved |
| Sync Engine | 80%+ | ✅ Achieved |
| Media Handling | 75%+ | ✅ Achieved |
| **Overall** | **70%+** | **✅ Achieved** |

### Installing Coverage Tools

**PCOV (Recommended - Fast):**
```bash
bash scripts/install-pcov.sh
```

**Xdebug (Slower but more detailed):**
```bash
# Already configured in Docker environment
docker-compose exec wp php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-html coverage
```

### Coverage Configuration

Coverage settings in `phpunit.xml`:

```xml
<coverage processUncoveredFiles="true">
    <include>
        <directory suffix=".php">plugin/src</directory>
    </include>
    <exclude>
        <directory suffix=".php">plugin/src/Admin</directory>
        <directory suffix=".php">plugin/src/Database/Schema*.php</directory>
    </exclude>
</coverage>
```

**Excluded from coverage:**
- Admin UI templates (manual testing)
- Database schema definitions
- Third-party libraries

---

## Continuous Integration

### GitHub Actions Workflow

**File:** `.github/workflows/test.yml`

**Runs on:**
- Every push to `main` branch
- Every pull request
- Manual trigger via workflow_dispatch

**Test Matrix:**
```yaml
php: [8.0, 8.1, 8.2]
wordpress: [6.0, 6.1, 6.2, latest]
```

**Steps:**
1. Checkout code
2. Install PHP dependencies (Composer)
3. Install Node dependencies (npm)
4. Run PHPUnit tests
5. Run PHPCS (coding standards)
6. Run PHPStan (static analysis)
7. Run ESLint (JavaScript)
8. Upload coverage to Codecov

### Local CI Simulation

```bash
# Run all CI checks locally
make ci

# Or manually:
composer install
npm install
php vendor/bin/phpunit
vendor/bin/phpcs
vendor/bin/phpstan analyse
npm run lint
```

### Code Quality Checks

```bash
# PHP CodeSniffer (WordPress Coding Standards)
vendor/bin/phpcs

# Auto-fix coding standards
vendor/bin/phpcbf

# PHPStan (Static Analysis)
vendor/bin/phpstan analyse

# ESLint (JavaScript)
npm run lint

# All checks together
make lint
```

---

## Testing Best Practices

### 1. Test Behavior, Not Implementation

❌ **Bad:**
```php
public function test_calls_get_option(): void {
    Functions\expect( 'get_option' )
        ->once()
        ->with( 'notion_wp_token' );

    $manager->get_token();
}
```

✅ **Good:**
```php
public function test_returns_decrypted_token(): void {
    when( 'get_option' )->justReturn( 'encrypted_token' );

    $token = $manager->get_token();

    $this->assertEquals( 'decrypted_token', $token );
}
```

### 2. Use Descriptive Test Names

❌ **Bad:**
```php
public function test_convert(): void { ... }
```

✅ **Good:**
```php
public function test_converts_notion_paragraph_to_gutenberg_block(): void { ... }
public function test_handles_empty_rich_text_gracefully(): void { ... }
public function test_preserves_inline_formatting_and_links(): void { ... }
```

### 3. Follow Arrange-Act-Assert Pattern

```php
public function test_syncs_page_successfully(): void {
    // Arrange - Set up test data and mocks
    $page_id = 'abc-123';
    when( 'wp_insert_post' )->justReturn( 42 );

    // Act - Execute the code under test
    $result = $this->sync_manager->sync_page( $page_id );

    // Assert - Verify the outcome
    $this->assertEquals( 42, $result );
    $this->assertNotInstanceOf( WP_Error::class, $result );
}
```

### 4. Test Edge Cases

Always test:
- Empty input
- Null values
- Invalid formats
- Error conditions
- Boundary values

```php
public function test_handles_empty_page_id(): void { ... }
public function test_handles_null_content(): void { ... }
public function test_handles_malformed_notion_block(): void { ... }
```

### 5. Keep Tests Fast

- Unit tests should run in < 1 second total
- Use mocks instead of real API calls
- Avoid unnecessary setup/teardown
- Run slow tests in separate integration suite

### 6. Make Tests Independent

Each test should be able to run independently:

```php
protected function setUp(): void {
    parent::setUp();
    // Reset state for each test
    $this->converter = new ParagraphConverter();
}

protected function tearDown(): void {
    // Clean up after test
    Mockery::close();
    parent::tearDown();
}
```

### 7. Don't Test Third-Party Code

❌ Don't test WordPress core functions
❌ Don't test Notion API responses
✅ DO test your code that uses them

```php
// Bad - testing WordPress
public function test_wp_insert_post_creates_post(): void {
    $this->assertEquals( 123, wp_insert_post( [...] ) );
}

// Good - testing your sync logic
public function test_creates_wordpress_post_from_notion_page(): void {
    when( 'wp_insert_post' )->justReturn( 123 );

    $result = $this->sync_manager->sync_page( 'page-id' );

    $this->assertEquals( 123, $result );
}
```

---

## Troubleshooting

### Common Issues

**"Class WP_Error not found"**
```bash
# WP_Error stub is in tests/bootstrap.php
# Make sure phpunit.xml includes it
```

**"Undefined function esc_html"**
```bash
# Extend BaseTestCase or BaseConverterTestCase
class MyTest extends BaseTestCase { ... }
```

**"Too few arguments to function when()"**
```bash
# Import the function at the top of your test
use function Brain\Monkey\Functions\when;
```

**Tests slow on macOS with Docker**
```bash
# Use Docker volume delegation
# Already configured in docker-compose.yml with :delegated flag
```

### Debug Mode

```bash
# Run single test with debug output
php vendor/bin/phpunit --debug --filter test_converts_paragraph

# Stop on failure
php vendor/bin/phpunit --stop-on-failure

# Verbose output
php vendor/bin/phpunit --testdox --verbose
```

---

## Contributing Tests

When contributing code, please:

1. **Write tests for new features**
   - Unit tests for business logic
   - Integration tests for WordPress integration

2. **Update tests for bug fixes**
   - Add test case that reproduces the bug
   - Verify test fails before fix
   - Verify test passes after fix

3. **Maintain coverage**
   - Aim for 80%+ coverage on new code
   - Don't decrease overall project coverage

4. **Follow existing patterns**
   - Use BaseTestCase for WordPress function mocking
   - Use BaseConverterTestCase for block converters
   - Follow Arrange-Act-Assert pattern

5. **Run tests before submitting PR**
   ```bash
   make test
   make lint
   ```

---

## Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Brain Monkey Documentation](https://giuseppe-mazzapica.gitbook.io/brain-monkey/)
- [WordPress Plugin Testing](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [Testing Best Practices](https://github.com/testdouble/contributing-tests/wiki/Testing-Best-Practices)

---

## Questions?

For testing-related questions:

- **General questions:** [GitHub Discussions](https://github.com/thevgergroup/notion-wp/discussions)
- **Bug reports:** [GitHub Issues](https://github.com/thevgergroup/notion-wp/issues)
- **Testing docs:** See [docs/testing/](testing/) for detailed documentation

---

**Last Updated:** November 3, 2025
**Test Infrastructure Status:** Production-Ready ✅
