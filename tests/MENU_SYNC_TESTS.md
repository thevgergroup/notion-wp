# Menu Sync Test Suite Documentation

## Overview

This document describes the comprehensive test suite for the Notion-WordPress menu synchronization functionality, with special emphasis on preventing regressions of the critical ID format bug that was fixed.

## Critical Bug Context

### The ID Format Bug

**Problem**: The `HierarchyDetector::get_child_pages()` method was not finding child pages because parent IDs were stored in different formats (with dashes vs without dashes).

**Root Cause**:
- Notion API returns IDs in UUID format with dashes: `2634dac9-b96e-813d-a15e-fd85567b68ff`
- SyncManager stores IDs in normalized format without dashes: `2634dac9b96e813da15efd85567b68ff`
- The `_notion_parent_page_id` meta could be stored in either format depending on the code path
- The query only searched for one format, missing children with IDs in the other format

**Fix**: Query now searches for BOTH formats using an OR condition in the meta_query.

## Test Files

### 1. HierarchyDetectorTest.php
**Location**: `/tests/unit/Hierarchy/HierarchyDetectorTest.php`

**Purpose**: Tests the HierarchyDetector class with focus on ID format compatibility.

**Critical Test Cases**:

#### ID Format Compatibility Tests
- `test_get_child_pages_finds_children_with_parent_stored_without_dashes()` ⭐
  - **WHY**: Primary bug scenario - parent ID stored as normalized (no dashes)
  - **VALIDATES**: Query searches for BOTH normalized and dashed formats
  - **PREVENTS**: Regression where children with normalized parent IDs aren't found

- `test_get_child_pages_finds_children_with_parent_stored_with_dashes()` ⭐
  - **WHY**: Inverse scenario - parent ID stored with dashes
  - **VALIDATES**: Conversion from normalized to dashed format works correctly
  - **PREVENTS**: Regression where children with dashed parent IDs aren't found

- `test_get_child_pages_finds_all_children_with_mixed_formats()` ⭐
  - **WHY**: Real-world scenario with mixed formats
  - **VALIDATES**: All children found regardless of which format was stored
  - **PREVENTS**: Partial data loss in mixed-format scenarios

#### Other Coverage
- Constructor validation (min/max depth enforcement)
- Empty hierarchy handling
- process_page_hierarchy() workflow
- build_hierarchy_map() with various depths
- Orphaned page handling
- Parent-child relationship updates

**Total Tests**: 16
**ID Format Tests**: 3 critical tests marked with ⭐

### 2. MenuBuilderTest.php
**Location**: `/tests/unit/Hierarchy/MenuBuilderTest.php`

**Purpose**: Tests menu creation and updates while preserving manual modifications.

**Test Cases**:
- Menu creation (new vs existing)
- Error handling (WP_Error scenarios)
- Notion-synced item deletion (respecting override flags)
- Manual item preservation
- Nested hierarchy building
- Menu ordering
- Menu item metadata tracking

**Total Tests**: 11

### 3. NavigationAjaxHandlerTest.php
**Location**: `/tests/unit/Admin/NavigationAjaxHandlerTest.php`

**Purpose**: Tests AJAX handler for menu sync operations.

**Test Cases**:
- Security checks (nonce verification, capability checks)
- Root page detection with wpdb queries
- ID format compatibility in AJAX context
- Error handling and exception scenarios
- Success response format
- Theme menu support detection

**Total Tests**: 9

## Test Fixtures

### HierarchyTestFixtures.php
**Location**: `/tests/fixtures/HierarchyTestFixtures.php`

**Provides**:
- Sample Notion page IDs in both formats (normalized and with dashes)
- Mock WordPress post objects
- Pre-built hierarchy structures:
  - Single root (no children)
  - Two-level hierarchy (root + children)
  - Three-level hierarchy (root + child + grandchild)
  - Multiple roots (separate trees)
- ID format bug scenario data
- Helper methods for ID normalization and formatting

## Running the Tests

### Run All Menu Sync Tests
```bash
vendor/bin/phpunit tests/unit/Hierarchy/
vendor/bin/phpunit tests/unit/Admin/NavigationAjaxHandlerTest.php
```

### Run Only ID Format Bug Tests
```bash
vendor/bin/phpunit --filter "test_get_child_pages_finds.*format"
```

### Run with Coverage
```bash
vendor/bin/phpunit --coverage-html coverage-html tests/unit/Hierarchy/
```

### Run Specific Test Class
```bash
vendor/bin/phpunit tests/unit/Hierarchy/HierarchyDetectorTest.php
```

## Test Data Patterns

### Notion Page IDs Used in Tests

#### Root Pages
- Normalized: `2634dac9b96e813da15efd85567b68ff`
- With dashes: `2634dac9-b96e-813d-a15e-fd85567b68ff`

#### Child Pages
- Normalized: `11112222333344445555666677778888`
- With dashes: `11112222-3333-4444-5555-666677778888`

#### WordPress Post IDs
- Root posts: 100-199
- Child posts: 101-102, 201-202
- Menu items: 200+

## Coverage Goals

### Current Coverage
- **HierarchyDetector**: 95%+ (all public methods, critical paths)
- **MenuBuilder**: 90%+ (menu CRUD, preservation logic)
- **NavigationAjaxHandler**: 85%+ (AJAX flow, error handling)

### Coverage Exclusions
Per `phpunit.xml`, Admin UI classes are excluded from unit test coverage and will be tested via integration tests.

## Preventing Regressions

### Code Review Checklist
When modifying hierarchy or menu sync code:
- [ ] Run all hierarchy tests: `vendor/bin/phpunit tests/unit/Hierarchy/`
- [ ] Verify ID format tests pass
- [ ] Check test output for any new format-related edge cases
- [ ] Review HierarchyTestFixtures for applicable test data

### CI/CD Integration
The test suite should be run:
- ✅ On every commit (pre-commit hook)
- ✅ On pull request creation/update
- ✅ Before merging to main branch

### Required Test Pass Rate
- **Unit tests**: 100% must pass
- **ID format tests**: 100% must pass (no exceptions)
- **Coverage**: Must not decrease below current baseline

## Test Patterns and Best Practices

### 1. Use Brain\Monkey for WordPress Function Mocking
```php
Functions\expect( 'get_posts' )
    ->once()
    ->with( $expected_args )
    ->andReturn( $result );
```

### 2. Test Both ID Formats Explicitly
```php
$normalized = '2634dac9b96e813da15efd85567b68ff';
$with_dashes = '2634dac9-b96e-813d-a15e-fd85567b68ff';
// Test both scenarios
```

### 3. Verify Meta Query Structure
```php
$this->assertEquals( 'OR', $meta_query['relation'] );
$this->assertEquals( $normalized, $meta_query[0]['value'] );
$this->assertEquals( $with_dashes, $meta_query[1]['value'] );
```

### 4. Use HierarchyTestFixtures for Test Data
```php
$page_ids = HierarchyTestFixtures::get_notion_page_ids();
$hierarchy = HierarchyTestFixtures::get_two_level_hierarchy();
```

## Troubleshooting

### Tests Fail with "Function not defined"
**Cause**: Brain\Monkey not properly set up or WordPress function not mocked.
**Solution**: Check `BaseTestCase::setup_wordpress_mocks()` and add missing function stubs.

### Tests Fail with "Expectation not met"
**Cause**: Mockery expectations don't match actual function calls.
**Solution**: Use `--debug` flag and verify function call parameters.

### Coverage Lower Than Expected
**Cause**: Private methods or edge cases not tested.
**Solution**: Test private methods indirectly through public API or add specific edge case tests.

## Future Test Additions

### Recommended Additional Tests
1. **Performance tests**: Large hierarchies (100+ pages)
2. **Circular reference detection**: Pages that reference each other as parents
3. **Concurrent sync tests**: Multiple menu syncs running simultaneously
4. **Database integration tests**: Real WordPress database queries
5. **REST API integration tests**: Full sync workflow via REST endpoints

### Integration Test Scenarios
- Full sync workflow: Notion API → HierarchyDetector → MenuBuilder → WordPress
- Menu assignment in WordPress admin
- Menu display on frontend
- Manual override preservation across multiple syncs

## Related Documentation

- **Bug Fix PR**: [Link to PR with the ID format fix]
- **Phase 5 Plan**: `/docs/phases/phase-5-hierarchy-navigation.md`
- **PHPUnit Config**: `/phpunit.xml`
- **CI/CD Workflow**: `/.github/workflows/tests.yml`

## Test Maintenance

### When to Update Tests
- ✏️ When adding new hierarchy detection features
- ✏️ When changing ID normalization logic
- ✏️ When modifying menu sync workflow
- ✏️ When fixing bugs (add regression test first)

### Test Review Process
1. Run tests locally before committing
2. Ensure all ID format tests pass
3. Review coverage report for gaps
4. Update this documentation if test structure changes

---

**Last Updated**: 2025-10-29
**Test Suite Version**: 1.0
**Total Tests**: 36
**Critical Bug Tests**: 3
