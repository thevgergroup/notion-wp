<?php
/**
 * Base Test Case for All Unit Tests
 *
 * Provides common WordPress function mocks and Brain\Monkey setup
 * for all unit tests in the project.
 *
 * @package NotionWP\Tests
 * @since 1.0.0
 */

namespace NotionWP\Tests\Unit;

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
		Functions\stubs(
			array(
				'apply_filters'         => function ( $filter_name, $value ) {
					// Return first value argument, ignore any additional args
					return $value;
				},
				'do_action'             => null,
				'add_action'            => true,
				'add_filter'            => true,
				'remove_action'         => true,
				'remove_filter'         => true,

				// Escaping functions - implement basic escaping for security tests
				'esc_html'              => function ( $text ) {
					return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
				},
				'esc_attr'              => function ( $text ) {
					return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
				},
				'esc_url'               => function ( $url ) {
					// Strip dangerous protocols
					$url = trim( (string) $url );
					$dangerous = array( 'javascript:', 'data:', 'vbscript:' );
					foreach ( $dangerous as $protocol ) {
						if ( stripos( $url, $protocol ) === 0 ) {
							return '';
						}
					}
					return $url;
				},
				'esc_js'                => function ( $text ) {
					return addslashes( (string) $text );
				},
				'esc_textarea'          => function ( $text ) {
					return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
				},

				// Sanitization functions
				'wp_kses_post'          => function ( $data ) {
					// Strip script tags for security
					return preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', (string) $data );
				},
				'sanitize_text_field'   => function ( $str ) {
					return strip_tags( (string) $str );
				},
				'sanitize_title'        => function ( $str ) {
					// Basic WordPress slug sanitization
					$str = strip_tags( (string) $str );
					$str = strtolower( $str );
					$str = preg_replace( '/[^a-z0-9\-]/', '-', $str );
					$str = preg_replace( '/-+/', '-', $str );
					return trim( $str, '-' );
				},
				'sanitize_key'          => function ( $str ) {
					return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $str ) );
				},

				// WordPress option functions
				'get_option'            => array(),
				'update_option'         => true,
				'delete_option'         => true,
				'add_option'            => true,

				// WordPress post meta functions
				'get_post_meta'         => '',
				'update_post_meta'      => true,
				'delete_post_meta'      => true,
				'add_post_meta'         => true,

				// WordPress post functions
				'wp_insert_post'        => 123,
				'wp_update_post'        => 123,
				'get_post'              => (object) array( 'ID' => 123 ),
				'get_posts'             => array(),

				// WordPress time functions
				'current_time'          => '2025-10-25 10:00:00',

				// WordPress error handling
				'is_wp_error'           => false,

				// WordPress translation functions
				'__'                    => function ( $text ) {
					return $text;
				},
				'_e'                    => function ( $text ) {
					echo $text;
				},
				'_x'                    => function ( $text ) {
					return $text;
				},
				'esc_html__'            => function ( $text ) {
					return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
				},
				'esc_html_e'            => function ( $text ) {
					echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
				},

				// WordPress upload/media functions
				'wp_upload_dir'         => array(
					'path'    => '/tmp/wp-content/uploads',
					'url'     => 'http://example.com/wp-content/uploads',
					'basedir' => '/tmp/wp-content/uploads',
					'baseurl' => 'http://example.com/wp-content/uploads',
				),
				'wp_get_attachment_url' => 'http://example.com/wp-content/uploads/image.jpg',

				// WordPress misc functions
				'absint'                => function ( $value ) {
					return abs( (int) $value );
				},
				'wp_parse_args'         => function ( $args, $defaults = array() ) {
					if ( is_array( $args ) ) {
						return array_merge( $defaults, $args );
					}
					return $defaults;
				},
			)
		);
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
