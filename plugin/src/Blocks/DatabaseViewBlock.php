<?php
/**
 * Database View Gutenberg Block
 *
 * Registers and renders the notion-wp/database-view block for embedding
 * interactive Notion database views in WordPress posts and pages.
 *
 * @package NotionWP\Blocks
 * @since 0.3.0
 */

namespace NotionWP\Blocks;

use WP_Block;

/**
 * Database View Block Class
 *
 * Handles registration and server-side rendering of the database-view block.
 */
class DatabaseViewBlock {
	/**
	 * Block name (without namespace).
	 *
	 * @var string
	 */
	private const BLOCK_NAME = 'database-view';

	/**
	 * Block namespace.
	 *
	 * @var string
	 */
	private const BLOCK_NAMESPACE = 'notion-wp';

	/**
	 * Full block name including namespace.
	 *
	 * @var string
	 */
	private const FULL_BLOCK_NAME = self::BLOCK_NAMESPACE . '/' . self::BLOCK_NAME;

	/**
	 * Path to block directory.
	 *
	 * @var string
	 */
	private string $block_path;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->block_path = NOTION_SYNC_PATH . 'blocks/' . self::BLOCK_NAME;
	}

	/**
	 * Register the block.
	 *
	 * @return void
	 */
	public function register(): void {
		// Register block type.
		add_action( 'init', array( $this, 'register_block_type' ) );

		// Enqueue editor assets.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register the block type with WordPress.
	 *
	 * @return void
	 */
	public function register_block_type(): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( '[DatabaseViewBlock] Attempting to register block at path: ' . $this->block_path );

		// Only register if block.json exists.
		if ( ! file_exists( $this->block_path . '/block.json' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[DatabaseViewBlock] ERROR: block.json not found at: ' . $this->block_path );
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( '[DatabaseViewBlock] block.json found, proceeding with registration' );

		$result = register_block_type(
			$this->block_path,
			array(
				'render_callback' => array( $this, 'render_callback' ),
			)
		);

		if ( $result instanceof \WP_Block_Type ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[DatabaseViewBlock] SUCCESS: Block registered as ' . self::FULL_BLOCK_NAME );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[DatabaseViewBlock] ERROR: Block registration failed' );
		}
	}

	/**
	 * Enqueue editor-specific assets and localize data.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		// Localize script with database posts data.
		wp_localize_script(
			'notion-wp-database-view-editor-script',
			'notionWpDatabaseView',
			array(
				'databases' => $this->get_database_posts(),
				'restUrl'   => rest_url(),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Get all notion_database posts for the editor.
	 *
	 * @return array Array of database posts with ID, title, and row count.
	 */
	private function get_database_posts(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'notion_database',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$databases = array();
		foreach ( $posts as $post ) {
			$row_count = get_post_meta( $post->ID, '_notion_database_row_count', true );

			$databases[] = array(
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'rowCount' => $row_count ? intval( $row_count ) : 0,
			);
		}

		return $databases;
	}

	/**
	 * Server-side render callback for the block.
	 *
	 * @param array    $attributes Block attributes from the editor.
	 * @param string   $content    Block inner content (not used for this block).
	 * @param WP_Block $block      Block instance.
	 *
	 * @return string Rendered HTML output.
	 */
	public function render_callback( array $attributes, string $content, WP_Block $block ): string {
		// Extract attributes with defaults.
		$database_id  = isset( $attributes['databaseId'] ) ? intval( $attributes['databaseId'] ) : 0;
		$view_type    = isset( $attributes['viewType'] ) ? sanitize_key( $attributes['viewType'] ) : 'table';
		$show_filters = isset( $attributes['showFilters'] ) ? (bool) $attributes['showFilters'] : true;
		$show_export  = isset( $attributes['showExport'] ) ? (bool) $attributes['showExport'] : true;

		// Validate database ID.
		if ( 0 === $database_id || 'notion_database' !== get_post_type( $database_id ) ) {
			return $this->render_error( __( 'Please select a valid Notion database.', 'notion-wp' ) );
		}

		// Validate view type.
		$valid_view_types = array( 'table', 'board', 'gallery', 'timeline', 'calendar' );
		if ( ! in_array( $view_type, $valid_view_types, true ) ) {
			$view_type = 'table';
		}

		// Get database post.
		$database_post = get_post( $database_id );
		if ( ! $database_post ) {
			return $this->render_error( __( 'Database not found.', 'notion-wp' ) );
		}

		// Enqueue frontend assets.
		$this->enqueue_frontend_assets();

		// Build wrapper classes.
		$wrapper_classes = array(
			'notion-wp-database-view',
			'notion-wp-database-view--' . $view_type,
		);

		// Add alignment class if set.
		if ( ! empty( $block->context['align'] ) ) {
			$wrapper_classes[] = 'align' . $block->context['align'];
		}

		// Prepare data attributes for JavaScript.
		$data_attributes = array(
			'data-database-id'   => esc_attr( $database_id ),
			'data-view-type'     => esc_attr( $view_type ),
			'data-show-filters'  => esc_attr( $show_filters ? '1' : '0' ),
			'data-show-export'   => esc_attr( $show_export ? '1' : '0' ),
		);

		// Start output buffering.
		ob_start();

		// Load the render template.
		include $this->block_path . '/render.php';

		return ob_get_clean();
	}

	/**
	 * Render an error message.
	 *
	 * @param string $message Error message to display.
	 *
	 * @return string Rendered error HTML.
	 */
	private function render_error( string $message ): string {
		return sprintf(
			'<div class="notion-wp-database-view-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Enqueue frontend assets (CSS and JS).
	 *
	 * @return void
	 */
	private function enqueue_frontend_assets(): void {
		// Enqueue Tabulator CSS (from CDN for now).
		wp_enqueue_style(
			'tabulator',
			'https://unpkg.com/tabulator-tables@6.3.0/dist/css/tabulator.min.css',
			array(),
			'6.3.0'
		);

		// Enqueue Tabulator JS (from CDN for now).
		wp_enqueue_script(
			'tabulator',
			'https://unpkg.com/tabulator-tables@6.3.0/dist/js/tabulator.min.js',
			array(),
			'6.3.0',
			true
		);

		// Enqueue our block frontend script (will be created later).
		wp_enqueue_script(
			'notion-wp-database-view',
			NOTION_SYNC_URL . 'blocks/database-view/build/frontend.js',
			array( 'tabulator' ),
			NOTION_SYNC_VERSION,
			true
		);

		// Enqueue block frontend styles.
		wp_enqueue_style(
			'notion-wp-database-view',
			NOTION_SYNC_URL . 'blocks/database-view/build/style.css',
			array( 'tabulator' ),
			NOTION_SYNC_VERSION
		);

		// Localize script with REST API data.
		wp_localize_script(
			'notion-wp-database-view',
			'notionWpDatabaseViewFrontend',
			array(
				'restUrl' => rest_url(),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
