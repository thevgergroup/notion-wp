# Testing Documentation

Comprehensive testing resources for the Notion-WordPress sync plugin.

## Quick Links

- **[Testing Strategy](./TESTING-STRATEGY.md)** - Overall testing philosophy, architecture, and guidelines
- **[Quick Start Guide](./QUICK-START-TESTING.md)** - Get up and running with tests immediately
- **[Testing Checklist](./TESTING-CHECKLIST.md)** - Use this when adding features or fixing bugs

## Overview

This plugin uses a **pragmatic, high-value testing approach** focused on:

- Testing OUR code, not third-party integrations
- 70% unit tests / 25% integration tests / 5% E2E tests
- Mocking external dependencies (Notion API, WordPress Core where appropriate)
- Achieving 75-85% overall code coverage with 95%+ on critical paths

## Quick Start

### Install Dependencies

```bash
composer install
```

### Run Tests

```bash
# All tests
composer test

# Unit tests only (fast)
composer test:unit

# Generate coverage report
composer test:coverage
```

### Write Your First Test

1. Create test file: `tests/unit/MyComponentTest.php`
2. Write test class extending `PHPUnit\Framework\TestCase`
3. Use `Brain\Monkey` to mock WordPress functions
4. Run: `vendor/bin/phpunit tests/unit/MyComponentTest.php`

See [Quick Start Guide](./QUICK-START-TESTING.md) for detailed examples.

## Testing Philosophy

### Test Pyramid

```
     /\      E2E (5%)
    /  \     - One full sync workflow
   /----\
  /      \   Integration (25%)
 /        \  - WordPress DB operations
/__________\ - Action Scheduler jobs
             - REST API endpoints

             Unit (70%)
             - Block converters
             - Media handling
             - Business logic
```

### What We Test

**High Priority (95%+ coverage):**
- Block conversion logic (Notion → Gutenberg)
- Media download and upload
- Data transformation
- Security/encryption

**Medium Priority (80%+ coverage):**
- Sync orchestration
- Error handling
- API client wrappers

**Lower Priority (60%+ coverage):**
- Admin UI controllers
- Database operations

**Skip:**
- WordPress Core functionality
- Third-party libraries
- Templates and views

### What We Mock

- **WordPress Functions**: Use `Brain\Monkey`
- **Notion API**: Use fixture JSON files
- **External HTTP Requests**: Mock responses
- **File System**: Mock where possible, use temp directories when needed

### What We Don't Mock

- **WordPress Database** (in integration tests): Use real WP test DB
- **Our Business Logic**: Always test actual implementation
- **Data Structures**: Test with real arrays/objects

## Test Organization

```
tests/
├── phpunit.xml              # PHPUnit configuration
├── bootstrap.php            # Test bootstrap
├── fixtures/                # Test data
│   ├── notion-responses/    # Notion API JSON responses
│   ├── wordpress-content/   # Expected WordPress output
│   └── media/               # Test images/files
├── unit/                    # Unit tests (70%)
│   ├── Blocks/
│   ├── Media/
│   ├── Sync/
│   └── API/
├── integration/             # Integration tests (25%)
│   ├── SyncWorkflowTest.php
│   ├── MediaImportTest.php
│   └── RESTAPITest.php
└── e2e/                     # End-to-end tests (5%)
    └── FullSyncTest.php
```

## Current Test Status

### Existing Tests

- `BlockConverterTest.php` - Block converter registry
- `ParagraphConverterTest.php` - Paragraph block conversion
- `HeadingConverterTest.php` - Heading block conversion
- `NumberedListConverterTest.php` - Numbered list conversion
- `BulletedListConverterTest.php` - Bulleted list conversion
- `SyncManagerTest.php` - Sync orchestration

### Tests to Add (Priority Order)

**Phase 1: Critical Path**
1. ImageConverterTest.php (created)
2. FileConverterTest.php
3. MediaRegistryTest.php (created)
4. MediaDownloaderTest.php
5. MediaUploaderTest.php

**Phase 2: Integration**
6. SyncWorkflowTest.php (integration)
7. MediaImportTest.php (integration)
8. ActionSchedulerTest.php (integration)

**Phase 3: Coverage**
9. TableConverterTest.php
10. QuoteConverterTest.php
11. NotionClientTest.php
12. EncryptionTest.php

See [Testing Strategy](./TESTING-STRATEGY.md) for complete roadmap.

## Tools & Frameworks

### Core Testing Stack

- **PHPUnit 9.6** - Test runner and assertions
- **Brain\Monkey 2.6** - WordPress function mocking
- **Mockery 1.6** - Object mocking
- **Yoast PHPUnit Polyfills** - PHP compatibility

### WordPress Integration

- **WordPress Test Suite** - Real WordPress environment for integration tests
- **WP_UnitTestCase** - WordPress-aware test base class
- **WP_Mock** - Alternative mocking library (not currently used)

### Optional Tools

- **Codeception** - BDD framework for E2E tests (future consideration)
- **PHPUnit Watcher** - Auto-run tests on file changes

## Common Commands

```bash
# Installation
composer install

# Run all tests
composer test

# Run unit tests only (fast, no WordPress needed)
composer test:unit

# Run integration tests (requires WordPress test suite)
composer test:integration

# Run specific test file
vendor/bin/phpunit tests/unit/Blocks/Converters/ImageConverterTest.php

# Run specific test method
vendor/bin/phpunit --filter test_converts_external_image

# Generate coverage report
composer test:coverage
open coverage-html/index.html

# Run tests with verbose output
vendor/bin/phpunit --testdox --verbose

# Check test isolation (run in random order)
vendor/bin/phpunit --order-by=random
```

## Writing Tests

### Unit Test Template

```php
<?php
namespace NotionWP\Tests\Unit\ComponentName;

use NotionWP\ComponentName\ClassName;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class ClassNameTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_method_does_something(): void {
        // Arrange
        $object = new ClassName();

        // Mock WordPress functions
        Functions\expect('get_option')
            ->once()
            ->with('option_name')
            ->andReturn('value');

        // Act
        $result = $object->method();

        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Integration Test Template

```php
<?php
namespace NotionWP\Tests\Integration;

use WP_UnitTestCase;

class FeatureIntegrationTest extends WP_UnitTestCase {

    public function test_feature_with_wordpress_database(): void {
        // Create test post
        $post_id = $this->factory->post->create([
            'post_title' => 'Test Post'
        ]);

        // Execute feature
        $result = $this->execute_feature($post_id);

        // Assert database state
        $post = get_post($post_id);
        $this->assertEquals('expected_title', $post->post_title);
    }
}
```

## Best Practices

### Do

- Test behavior, not implementation
- Use descriptive test names
- Mock external dependencies
- Test edge cases and errors
- Keep tests fast and isolated
- Use fixtures for complex data
- Achieve adequate coverage

### Don't

- Test WordPress Core functions
- Hit live APIs in tests
- Test private methods directly
- Create interdependent tests
- Ignore failing tests
- Skip error scenarios
- Write brittle tests

## Coverage Goals

| Component | Target | Current |
|-----------|--------|---------|
| Block Converters | 95% | ~70% |
| Media Handling | 90% | ~0% (to add) |
| Sync Orchestration | 85% | ~80% |
| API Client | 70% | ~0% (to add) |
| Overall | 75-85% | ~50% (estimated) |

## CI/CD Integration

Tests run automatically on:
- Every commit (via GitHub Actions - to configure)
- Pull requests
- Before deployments

See `.github/workflows/tests.yml` (to create) for CI configuration.

## Troubleshooting

### Tests failing with "Class not found"

```bash
composer dump-autoload
```

### WordPress functions not mocked

```php
// Add to setUp():
Functions\when('the_function')->justReturn('value');
```

### Tests pass individually but fail together

Check for global state pollution. Ensure `setUp()` and `tearDown()` reset all state.

### Coverage report not generating

Install xdebug:
```bash
pecl install xdebug
```

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Brain\Monkey Documentation](https://giuseppe-mazzapica.gitbook.io/brain-monkey/)
- [WordPress Testing Handbook](https://make.wordpress.org/core/handbook/testing/)
- [Testing Strategy (this project)](./TESTING-STRATEGY.md)

## Contributing

When adding new features:

1. Read the [Testing Checklist](./TESTING-CHECKLIST.md)
2. Write tests before or with your code
3. Ensure all tests pass: `composer test`
4. Check coverage: `composer test:coverage`
5. Follow existing test patterns

## Questions?

- Review documentation in this directory
- Look at existing tests for examples
- Check [Testing Strategy](./TESTING-STRATEGY.md) for philosophy
- Ask team for guidance

---

**Last Updated**: 2025-10-24
**Maintained By**: Development Team
