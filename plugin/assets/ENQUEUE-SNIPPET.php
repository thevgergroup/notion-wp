<?php
/**
 * Asset Enqueue Code Snippet for SettingsPage.php
 *
 * Add this code to your SettingsPage class to properly enqueue
 * the admin CSS and JavaScript files on the settings page only.
 *
 * @package NotionSync
 */

/**
 * Add this method to your SettingsPage class:
 */

/**
 * Register hooks for the settings page
 *
 * Call this method from your Plugin.php initialization
 */
public function register(): void {
	// Register menu page
	add_action( 'admin_menu', array( $this, 'add_menu_page' ) );

	// Enqueue assets only on settings page
	add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

	// Handle form submissions
	add_action( 'admin_post_notion_sync_connect', array( $this, 'handle_connect' ) );
	add_action( 'admin_post_notion_sync_disconnect', array( $this, 'handle_disconnect' ) );
}

/**
 * Add settings page to admin menu
 */
public function add_menu_page(): void {
	add_menu_page(
		__( 'Vger Sync for Notion', 'vger-sync-for-notion' ),
		__( 'Vger Sync for Notion', 'vger-sync-for-notion' ),
		'manage_options',
		'vger-sync-for-notion',
		array( $this, 'render' ),
		'dashicons-cloud',
		30 // Position in menu (after Settings)
	);
}

/**
 * Enqueue admin assets on settings page only
 *
 * @param string $hook_suffix The current admin page hook suffix
 */
public function enqueue_assets( string $hook_suffix ): void {
	// Only load on our settings page
	// The hook suffix format is: toplevel_page_vger-sync-for-notion
	if ( 'toplevel_page_vger-sync-for-notion' !== $hook_suffix ) {
		return;
	}

	// Define asset paths
	$plugin_url = plugin_dir_url( VGER_SYNC_FILE );
	$plugin_version = VGER_SYNC_VERSION; // From your main plugin file constant

	// Enqueue CSS
	wp_enqueue_style(
		'vger-sync-admin', // Handle
		$plugin_url . 'assets/dist/css/admin.min.css', // Source (adjust path if using build process)
		array(), // Dependencies (none)
		$plugin_version, // Version
		'all' // Media
	);

	// Enqueue JavaScript
	wp_enqueue_script(
		'vger-sync-admin', // Handle
		$plugin_url . 'assets/dist/js/admin.min.js', // Source (adjust path if using build process)
		array(), // Dependencies (vanilla JS, no jQuery needed)
		$plugin_version, // Version
		true // Load in footer
	);

	// Optional: Pass PHP data to JavaScript
	wp_localize_script(
		'vger-sync-admin',
		'notionSyncAdmin', // JavaScript object name
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'notion_sync_ajax' ),
			'i18n'    => array(
				'connecting'    => __( 'Connecting...', 'vger-sync-for-notion' ),
				'connected'     => __( 'Connected!', 'vger-sync-for-notion' ),
				'disconnecting' => __( 'Disconnecting...', 'vger-sync-for-notion' ),
				'error'         => __( 'An error occurred. Please try again.', 'vger-sync-for-notion' ),
			),
		)
	);
}

/**
 * ALTERNATIVE: If using a build process (Webpack, Gulp, etc.)
 *
 * Update the paths to point to your compiled assets:
 */

/*
public function enqueue_assets( string $hook_suffix ): void {
	if ( 'toplevel_page_vger-sync-for-notion' !== $hook_suffix ) {
		return;
	}

	$plugin_url = plugin_dir_url( VGER_SYNC_FILE );
	$plugin_version = VGER_SYNC_VERSION;

	// Get asset file data (if using @wordpress/scripts or similar)
	$asset_file = include plugin_dir_path( VGER_SYNC_FILE ) . 'assets/dist/js/admin.asset.php';

	wp_enqueue_style(
		'vger-sync-admin',
		$plugin_url . 'assets/dist/css/admin.min.css',
		array(),
		$asset_file['version'] ?? $plugin_version,
		'all'
	);

	wp_enqueue_script(
		'vger-sync-admin',
		$plugin_url . 'assets/dist/js/admin.min.js',
		$asset_file['dependencies'] ?? array(),
		$asset_file['version'] ?? $plugin_version,
		true
	);

	wp_localize_script(
		'vger-sync-admin',
		'notionSyncAdmin',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'notion_sync_ajax' ),
			'i18n'    => array(
				'connecting'    => __( 'Connecting...', 'vger-sync-for-notion' ),
				'connected'     => __( 'Connected!', 'vger-sync-for-notion' ),
				'disconnecting' => __( 'Disconnecting...', 'vger-sync-for-notion' ),
				'error'         => __( 'An error occurred. Please try again.', 'vger-sync-for-notion' ),
			),
		)
	);
}
*/

/**
 * CONSTANTS TO DEFINE IN YOUR MAIN PLUGIN FILE:
 */

/*
// In vger-sync-for-notion.php or your main plugin file:

define( 'VGER_SYNC_VERSION', '0.1.0' );
define( 'VGER_SYNC_FILE', __FILE__ );
define( 'VGER_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'VGER_SYNC_URL', plugin_dir_url( __FILE__ ) );
*/

/**
 * BUILD PROCESS SETUP (OPTIONAL):
 *
 * If you want to compile SCSS to CSS and minify JavaScript:
 *
 * 1. Install build dependencies:
 *    npm install --save-dev sass postcss autoprefixer cssnano terser
 *
 * 2. Add build scripts to package.json:
 *    {
 *      "scripts": {
 *        "build:css": "sass plugin/assets/src/scss/admin.scss plugin/assets/dist/css/admin.css --style compressed",
 *        "build:js": "terser plugin/assets/src/js/admin.js -o plugin/assets/dist/js/admin.min.js -c -m",
 *        "build": "npm run build:css && npm run build:js",
 *        "watch": "npm run watch:css & npm run watch:js",
 *        "watch:css": "sass --watch plugin/assets/src/scss/admin.scss:plugin/assets/dist/css/admin.css",
 *        "watch:js": "terser --watch plugin/assets/src/js/admin.js -o plugin/assets/dist/js/admin.min.js -c -m"
 *      }
 *    }
 *
 * 3. Create dist directories:
 *    mkdir -p plugin/assets/dist/css plugin/assets/dist/js
 *
 * 4. Run build:
 *    npm run build
 *
 * 5. For development with auto-compilation:
 *    npm run watch
 */

/**
 * DEVELOPMENT WITHOUT BUILD PROCESS:
 *
 * If you prefer not to use a build process for Phase 0,
 * you can load the source files directly:
 */

/*
public function enqueue_assets( string $hook_suffix ): void {
	if ( 'toplevel_page_vger-sync-for-notion' !== $hook_suffix ) {
		return;
	}

	$plugin_url = plugin_dir_url( VGER_SYNC_FILE );
	$plugin_version = VGER_SYNC_VERSION;

	// Load source files directly (for development only)
	wp_enqueue_style(
		'vger-sync-admin',
		$plugin_url . 'assets/src/scss/admin.scss', // Browser won't compile SCSS
		array(),
		$plugin_version,
		'all'
	);

	// Note: You'll need to compile SCSS manually or use browser that supports it
	// Better approach: Use simple CSS instead of SCSS for Phase 0

	wp_enqueue_script(
		'vger-sync-admin',
		$plugin_url . 'assets/src/js/admin.js', // Raw JS works fine
		array(),
		$plugin_version,
		true
	);
}
*/

/**
 * DEBUGGING TIPS:
 *
 * 1. Check if assets are enqueued:
 *    - View page source and search for "vger-sync-admin"
 *    - Check Network tab in browser DevTools
 *
 * 2. Verify hook suffix:
 *    - Add this temporarily to see all hook suffixes:
 *      add_action( 'admin_notices', function() use ( $hook_suffix ) {
 *          echo '<div class="notice notice-info"><p>Hook: ' . esc_html( $hook_suffix ) . '</p></div>';
 *      } );
 *
 * 3. Check for JavaScript errors:
 *    - Open browser Console (F12)
 *    - Look for 404s or syntax errors
 *
 * 4. Verify file paths:
 *    - Check that VGER_SYNC_FILE constant points to main plugin file
 *    - Verify directory structure matches path in enqueue calls
 */

/**
 * WORDPRESS STANDARDS COMPLIANCE:
 *
 * This code follows WordPress coding standards:
 * - Uses wp_enqueue_style() and wp_enqueue_script()
 * - Proper versioning for cache busting
 * - Only loads on relevant admin page
 * - Proper dependency management
 * - Translation-ready strings
 * - Secure nonce generation
 */