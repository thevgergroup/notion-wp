<?php
/**
 * Base Test Case for All Unit Tests
 *
 * Provides common WordPress function mocks and Brain\Monkey setup
 * for all unit tests in the project.
 *
 * @package NotionSync\Tests
 * @since 1.0.0
 */

namespace NotionSync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for all unit tests
 *
 * Automatically sets up Brain\Monkey and mocks common WordPress functions.
 */
abstract class BaseTestCase extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Set up test environment
	 *
	 * Sets up Brain\Monkey and mocks common WordPress functions.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock common WordPress functions
		$this->setup_wordpress_mocks();
	}

	/**
	 * Tear down test environment
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Set up common WordPress function mocks
	 *
	 * Mocks the most commonly used WordPress functions.
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

		Functions\when( 'sanitize_title' )->alias(
			function ( $str ) {
				// Basic WordPress slug sanitization
				$str = strip_tags( $str );
				$str = strtolower( $str );
				$str = preg_replace( '/[^a-z0-9\-]/', '-', $str );
				$str = preg_replace( '/-+/', '-', $str );
				return trim( $str, '-' );
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

		// WordPress post functions
		Functions\when( 'wp_insert_post' )->justReturn( 123 );
		Functions\when( 'wp_update_post' )->justReturn( 123 );
		Functions\when( 'get_post' )->justReturn( (object) array( 'ID' => 123 ) );
		Functions\when( 'get_posts' )->justReturn( array() );

		// WordPress time functions
		Functions\when( 'current_time' )->justReturn( '2025-10-25 10:00:00' );

		// WordPress error handling
		Functions\when( 'is_wp_error' )->justReturn( false );
	}

	/**
	 * Set up wpdb mock
	 *
	 * Creates a mock wpdb object and sets it as the global $wpdb.
	 */
	protected function setup_wpdb_mock(): void {
		global $wpdb;

		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		// Mock get_var to return null by default (not found)
		$wpdb->shouldReceive( 'get_var' )
			->andReturnNull()
			->byDefault();

		// Mock get_row to return null by default (not found)
		$wpdb->shouldReceive( 'get_row' )
			->andReturnNull()
			->byDefault();

		// Mock prepare
		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( $query ) {
					$args = func_get_args();
					array_shift( $args );
					foreach ( $args as $arg ) {
						$query = preg_replace( '/%[sdi]|%i/', "'" . $arg . "'", $query, 1 );
					}
					return $query;
				}
			);

		// Mock insert
		$wpdb->shouldReceive( 'insert' )
			->andReturn( 1 )
			->byDefault();

		// Mock delete
		$wpdb->shouldReceive( 'delete' )
			->andReturn( 1 )
			->byDefault();

		$wpdb->insert_id = 1;
	}
}
