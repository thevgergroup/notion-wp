<?php
/**
 * Base Test Case for Block Converters
 *
 * Provides common WordPress function mocks and Brain\Monkey setup
 * for all block converter tests.
 *
 * @package NotionSync\Tests
 * @since 1.0.0
 */

namespace NotionSync\Tests\Unit\Blocks\Converters;

use Brain\Monkey;
use Brain\Monkey\Functions;
use NotionSync\Tests\Unit\BaseTestCase;

/**
 * Base test case for block converter tests
 *
 * Automatically sets up Brain\Monkey and mocks common WordPress functions.
 */
abstract class BaseConverterTestCase extends BaseTestCase {

	/**
	 * Set up test environment
	 *
	 * BaseTestCase handles all Brain\Monkey setup and WordPress function mocking.
	 * No additional setup needed for converter tests.
	 */
	protected function setUp(): void {
		parent::setUp(); // BaseTestCase handles Brain\Monkey setup and all mocking
	}
}
