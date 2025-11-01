<?php
/**
 * Database View Gutenberg Block
 *
 * Dynamic block for rendering interactive Notion database views.
 * Stores database ID and display options, fetches data at render time.
 *
 * @package NotionWP\Blocks
 * @since 0.3.0
 */

namespace NotionWP\Blocks;

/**
 * Class DatabaseViewBlock
 *
 * Registers and renders the notion-wp/database-view Gutenberg block.
 */
class DatabaseViewBlock {

	/**
	 * Block name (without namespace)
	 *
	 * @var string
	 */
	private const BLOCK_NAME = 'database-view';

	/**
	 * Full block name with namespace
	 *
	 * @var string
	 */
	private const FULL_BLOCK_NAME = 'notion-wp/database-view';

	/**
	 * Plugin file path
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Main plugin file path.
	 */
	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	/**
	 * Initialize the block.
	 *
	 * Register WordPress hooks and register the block.
	 *
	 * @since 0.3.0
	 */
	public function init(): void {
		// Register block immediately (we're already in the init hook).
		$this->register_block();

		// Hook for editor assets.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * @since 0.3.0
	 */
	public function enqueue_editor_assets(): void {
		wp_enqueue_script(
			'notion-wp-database-view-editor',
			NOTION_SYNC_URL . 'assets/src/js/blocks/database-view.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-core-data' ),
			NOTION_SYNC_VERSION,
			true
		);

		// Localize script with available databases.
		wp_localize_script(
			'notion-wp-database-view-editor',
			'notionWpDatabaseView',
			array(
				'databases' => $this->get_database_posts(),
			)
		);

		// Add inline CSS for better editor preview.
		wp_add_inline_style(
			'wp-edit-blocks',
			'
			.notion-wp-database-view-editor {
				padding: 20px;
				border: 2px solid #ddd;
				border-radius: 8px;
				background: #f9f9f9;
			}
			.notion-wp-database-view-header {
				display: flex;
				align-items: center;
				gap: 15px;
				margin-bottom: 15px;
				padding-bottom: 15px;
				border-bottom: 2px solid #ddd;
			}
			.notion-wp-database-icon {
				font-size: 32px;
			}
			.notion-wp-database-info h3 {
				margin: 0 0 5px 0;
				font-size: 18px;
			}
			.notion-wp-database-info p {
				margin: 0;
				color: #666;
			}
			.notion-wp-database-view-settings {
				background: white;
				padding: 15px;
				border-radius: 4px;
			}
			.notion-wp-database-view-settings ul {
				margin: 0;
				padding: 0;
				list-style: none;
			}
			.notion-wp-database-view-settings li {
				padding: 5px 0;
			}
			'
		);
	}

	/**
	 * Get all notion_database posts for the editor.
	 *
	 * @since 0.3.0
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
	 * Register the Gutenberg block.
	 *
	 * @since 0.3.0
	 */
	public function register_block(): void {
		// Register as a dynamic block with server-side rendering.
		register_block_type(
			self::FULL_BLOCK_NAME,
			array(
				'api_version'     => 2,
				'attributes'      => array(
					'databaseId'  => array(
						'type'    => 'number',
						'default' => 0,
					),
					'viewType'    => array(
						'type'    => 'string',
						'default' => 'table',
					),
					'showFilters' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showExport'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'pageSize'    => array(
						'type'    => 'number',
						'default' => 50,
					),
				),
				'render_callback' => array( $this, 'render_block' ),
				'supports'        => array(
					'html'   => false,
					'align'  => array( 'wide', 'full' ),
					'anchor' => true,
				),
			)
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( '[DatabaseViewBlock] Block registered: ' . self::FULL_BLOCK_NAME );
	}

	/**
	 * Render the block on the frontend.
	 *
	 * @since 0.3.0
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content (not used for dynamic blocks).
	 * @return string Rendered HTML.
	 *
	 * @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $content required by block API.
	 */
	public function render_block( array $attributes, string $content = '' ): string {

		// Extract attributes with defaults.
		$database_id  = $attributes['databaseId'] ?? 0;
		$view_type    = $attributes['viewType'] ?? 'table';
		$show_filters = $attributes['showFilters'] ?? true;
		$show_export  = $attributes['showExport'] ?? true;
		$page_size    = $attributes['pageSize'] ?? 50;

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
		error_log( sprintf( '[DatabaseViewBlock] Rendering block for database ID: %d', $database_id ) );

		// Validate database ID.
		if ( 0 === $database_id || 'notion_database' !== get_post_type( $database_id ) ) {
			// Show placeholder for logged-in users.
			if ( is_user_logged_in() ) {
				return '<div class="notion-wp-database-view-error"><p>⚠️ Please select a valid Notion database.</p></div>';
			}
			return '';
		}

		// Validate view type.
		$valid_view_types = array( 'table', 'board', 'gallery', 'timeline', 'calendar' );
		if ( ! in_array( $view_type, $valid_view_types, true ) ) {
			$view_type = 'table';
		}

		// Get database post.
		$database_post = get_post( $database_id );
		if ( ! $database_post ) {
			if ( is_user_logged_in() ) {
				return '<div class="notion-wp-database-view-error"><p>⚠️ Database not found.</p></div>';
			}
			return '';
		}

		// Enqueue frontend assets.
		$this->enqueue_frontend_assets();

		// Build wrapper classes.
		$wrapper_classes = array(
			'notion-wp-database-view',
			'notion-wp-database-view--' . esc_attr( $view_type ),
		);

		// Prepare template variables.
		$database_title    = esc_html( $database_post->post_title );
		$wrapper_class     = implode( ' ', $wrapper_classes );
		$data_database_id  = esc_attr( $database_id );
		$data_view_type    = esc_attr( $view_type );
		$data_show_filters = esc_attr( $show_filters ? '1' : '0' );
		$data_show_export  = esc_attr( $show_export ? '1' : '0' );
		$data_page_size    = esc_attr( $page_size );

		// Render the block HTML.
		ob_start();
		include NOTION_SYNC_PATH . 'templates/blocks/database-view.php';
		return ob_get_clean();
	}

	/**
	 * Enqueue frontend assets (CSS and JS).
	 *
	 * @since 0.3.0
	 * @return void
	 */
	private function enqueue_frontend_assets(): void {
		// Enqueue Tabulator CSS (from CDN).
		wp_enqueue_style(
			'tabulator',
			'https://unpkg.com/tabulator-tables@6.3.0/dist/css/tabulator.min.css',
			array(),
			'6.3.0'
		);

		// Enqueue Tabulator JS (from CDN).
		wp_enqueue_script(
			'tabulator',
			'https://unpkg.com/tabulator-tables@6.3.0/dist/js/tabulator.min.js',
			array(),
			'6.3.0',
			true
		);

		// Enqueue our block frontend script.
		wp_enqueue_script(
			'notion-wp-database-view-frontend',
			NOTION_SYNC_URL . 'assets/src/js/frontend/database-view.js',
			array( 'tabulator' ),
			NOTION_SYNC_VERSION,
			true
		);

		// Enqueue block frontend styles.
		wp_enqueue_style(
			'notion-wp-database-view-frontend',
			NOTION_SYNC_URL . 'assets/src/css/blocks/database-view.css',
			array( 'tabulator' ),
			NOTION_SYNC_VERSION
		);

		// Localize script with REST API data.
		wp_localize_script(
			'notion-wp-database-view-frontend',
			'notionWpDatabaseViewFrontend',
			array(
				'restUrl' => rest_url(),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
