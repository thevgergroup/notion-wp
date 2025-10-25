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
	 * Sets up Brain\Monkey and mocks common WordPress functions that are
	 * used across all block converters.
	 */
	protected function setUp(): void {
		parent::setUp(); // BaseTestCase handles Brain\Monkey setup

		// Mock common WordPress functions (additional to BaseTestCase)
		$this->setup_wordpress_mocks();
	}

	/**
	 * Set up common WordPress function mocks
	 *
	 * Mocks the most commonly used WordPress functions in block converters:
	 * - apply_filters: Pass through values unchanged
	 * - Escaping functions: Actually escape content for security tests
	 */
	protected function setup_wordpress_mocks(): void {
		// apply_filters passes through the value unchanged
		Functions\when( 'apply_filters' )->alias(
			function ( $filter_name, $value ) {
				return $value;
			}
		);

		// Escaping functions - implement basic escaping for security tests
		Functions\when( 'esc_html' )->alias(
			function ( $text ) {
				return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
			}
		);

		Functions\when( 'esc_attr' )->alias(
			function ( $text ) {
				return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
			}
		);

		Functions\when( 'esc_url' )->alias(
			function ( $url ) {
				// Strip dangerous protocols
				$url = trim( $url );
				$dangerous = array( 'javascript:', 'data:', 'vbscript:' );
				foreach ( $dangerous as $protocol ) {
					if ( stripos( $url, $protocol ) === 0 ) {
						return '';
					}
				}
				return $url;
			}
		);

		Functions\when( 'wp_kses_post' )->alias(
			function ( $data ) {
				// Strip script tags for security
				return preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $data );
			}
		);

		Functions\when( 'sanitize_text_field' )->alias(
			function ( $str ) {
				return strip_tags( $str );
			}
		);

		// add_action and add_filter do nothing in unit tests
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );

		// WordPress option functions
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );

		// WordPress post meta functions
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'delete_post_meta' )->justReturn( true );
		Functions\when( 'add_post_meta' )->justReturn( true );
	}
}
