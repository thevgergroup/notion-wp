<?php
/**
 * PHPUnit Bootstrap File
 *
 * This file is loaded by PHPUnit to set up the test environment.
 * It must load Brain\Monkey BEFORE WordPress stubs to allow function mocking.
 *
 * @package NotionWP\Tests
 */

// Composer autoloader.
require_once __DIR__ . '/../plugin/vendor/autoload.php';

// Define WordPress functions in global namespace BEFORE loading plugin code
// This allows Brain\Monkey to intercept them properly
if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Stub for apply_filters - Brain\Monkey will override this
	 *
	 * @param string $filter_name Filter name.
	 * @param mixed  $value       Value to filter.
	 * @return mixed
	 */
	function apply_filters( $filter_name, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Stub for do_action - Brain\Monkey will override this
	 *
	 * @param string $action_name Action name.
	 */
	function do_action( $action_name ) {
		// Stub - does nothing
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Stub for add_action - Brain\Monkey will override this
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of args.
	 * @return true
	 */
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Stub for add_filter - Brain\Monkey will override this
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of args.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Stub for esc_html - Brain\Monkey will override this
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Stub for esc_attr - Brain\Monkey will override this
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Stub for esc_url - Brain\Monkey will override this
	 *
	 * @param string $url URL to escape.
	 * @return string
	 */
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Stub for sanitize_text_field - Brain\Monkey will override this
	 *
	 * @param string $str String to sanitize.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return strip_tags( (string) $str );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Stub for __ translation function - Brain\Monkey will override this
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	/**
	 * Stub for _e translation function - Brain\Monkey will override this
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 */
	function _e( $text, $domain = 'default' ) {
		echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * Stub for wp_kses_post - Brain\Monkey will override this
	 *
	 * @param string $data Data to sanitize.
	 * @return string
	 */
	function wp_kses_post( $data ) {
		return (string) $data;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Stub for wp_json_encode - Brain\Monkey will override this
	 *
	 * @param mixed $data    Data to encode.
	 * @param int   $options JSON encode options.
	 * @param int   $depth   Maximum depth.
	 * @return string|false
	 */
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

// Define simple WP_Error class for testing
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		protected $errors = array();
		protected $error_data = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( ! empty( $code ) ) {
				$this->errors[ $code ][] = $message;
				if ( ! empty( $data ) ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_message( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			if ( isset( $this->errors[ $code ][0] ) ) {
				return $this->errors[ $code ][0];
			}
			return '';
		}

		public function get_error_code() {
			if ( ! empty( $this->errors ) ) {
				return key( $this->errors );
			}
			return '';
		}
	}
}

// DO NOT call Brain\Monkey\setUp() here!
// It must be called in each test's setUp() method to work properly.
// We just verify Brain\Monkey is available.

// IMPORTANT: DO NOT load WordPress stubs here for unit tests!
// WordPress stubs define functions like apply_filters(), add_action(), etc.
// If these are loaded before Brain\Monkey\setUp() is called in each test,
// Patchwork (used by Brain\Monkey) cannot intercept them and will throw
// DefinedTooEarly exceptions.
//
// For integration tests that need real WordPress functions, load stubs there.
// For unit tests using Brain\Monkey, do NOT load stubs - Brain\Monkey will
// mock the functions when they're called.

// Define plugin constants for tests.
if ( ! defined( 'NOTION_WP_VERSION' ) ) {
	define( 'NOTION_WP_VERSION', '0.1.0-test' );
}

if ( ! defined( 'NOTION_WP_PLUGIN_FILE' ) ) {
	define( 'NOTION_WP_PLUGIN_FILE', __DIR__ . '/../plugin/notion-sync.php' );
}

if ( ! defined( 'NOTION_WP_PLUGIN_DIR' ) ) {
	define( 'NOTION_WP_PLUGIN_DIR', dirname( NOTION_WP_PLUGIN_FILE ) );
}

if ( ! defined( 'NOTION_WP_PLUGIN_URL' ) ) {
	define( 'NOTION_WP_PLUGIN_URL', 'http://localhost/wp-content/plugins/notion-wp/' );
}

if ( ! defined( 'NOTION_SYNC_PATH' ) ) {
	define( 'NOTION_SYNC_PATH', __DIR__ . '/../plugin' );
}

if ( ! defined( 'NOTION_SYNC_URL' ) ) {
	define( 'NOTION_SYNC_URL', 'http://localhost/wp-content/plugins/notion-wp/' );
}

// WordPress core constants.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

if ( ! defined( 'WP_DEBUG_LOG' ) ) {
	define( 'WP_DEBUG_LOG', false );
}

if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
	define( 'WP_DEBUG_DISPLAY', false );
}

// WordPress time constants.
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}

if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 2592000 );
}

if ( ! defined( 'YEAR_IN_SECONDS' ) ) {
	define( 'YEAR_IN_SECONDS', 31536000 );
}

// Load plugin source files for testing.
// The composer autoloader should handle this via PSR-4, but we ensure it's loaded.
$plugin_src_dir = __DIR__ . '/../plugin/src';
if ( is_dir( $plugin_src_dir ) ) {
	// Autoloader will handle loading classes as needed.
	// No manual require_once needed if composer autoload is properly configured.
}

// CRITICAL: Explicitly load base test case classes
// PHPUnit loads test files directly (not via autoloader), so we must manually
// require base classes that tests extend. Otherwise, when PHPUnit tries to
// load a test file that extends a base class, PHP will throw "Class not found".
require_once __DIR__ . '/unit/BaseTestCase.php';
require_once __DIR__ . '/unit/Blocks/Converters/BaseConverterTestCase.php';

// Test environment flag.
if ( ! defined( 'NOTION_TEST_MODE' ) ) {
	define( 'NOTION_TEST_MODE', true );
}

// Skip live API calls in tests.
if ( ! defined( 'NOTION_SKIP_LIVE_API' ) ) {
	define( 'NOTION_SKIP_LIVE_API', true );
}

// Echo confirmation that bootstrap loaded successfully.
if ( defined( 'PHPUNIT_COMPOSER_INSTALL' ) || defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	// Running in PHPUnit - be quiet.
} else {
	echo "\nTest bootstrap loaded successfully.\n";
	echo "Brain\\Monkey: " . ( class_exists( 'Brain\\Monkey' ) ? 'Loaded' : 'Not Found' ) . "\n";
	echo "WordPress Stubs: " . ( function_exists( 'wp_kses_post' ) ? 'Loaded' : 'Not Found' ) . "\n\n";
}
