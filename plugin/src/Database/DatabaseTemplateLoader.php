<?php
/**
 * Database Template Loader
 *
 * Handles loading custom templates for notion_database post type on the frontend.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Database;

/**
 * Class DatabaseTemplateLoader
 *
 * Loads custom frontend templates for database posts and enqueues necessary assets.
 *
 * @since 1.0.0
 */
class DatabaseTemplateLoader {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_filter( 'template_include', array( $this, 'load_template' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Load custom template for notion_database posts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Path to template file.
	 * @return string Modified template path.
	 */
	public function load_template( string $template ): string {
		// Only load for single notion_database posts.
		if ( ! is_singular( 'notion_database' ) ) {
			return $template;
		}

		// Check if theme has custom template.
		$theme_template = locate_template( array( 'single-notion_database.php' ) );
		if ( $theme_template ) {
			return $theme_template;
		}

		// Use plugin template.
		$plugin_template = NOTION_SYNC_PATH . 'templates/single-notion_database.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		// Fallback to default template.
		return $template;
	}

	/**
	 * Enqueue assets for database viewer.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets(): void {
		// Only load on single notion_database posts.
		if ( ! is_singular( 'notion_database' ) ) {
			return;
		}

		// Enqueue Tabulator CSS.
		wp_enqueue_style(
			'tabulator',
			'https://unpkg.com/tabulator-tables@6.3.0/dist/css/tabulator.min.css',
			array(),
			'6.3.0'
		);

		// Enqueue Luxon.js (required for datetime sorting).
		wp_enqueue_script(
			'luxon',
			'https://cdn.jsdelivr.net/npm/luxon@3.4.4/build/global/luxon.min.js',
			array(),
			'3.4.4',
			true
		);

		// Enqueue Tabulator JS (depends on luxon for datetime sorting).
		wp_enqueue_script(
			'tabulator',
			'https://unpkg.com/tabulator-tables@6.3.0/dist/js/tabulator.min.js',
			array( 'luxon' ),
			'6.3.0',
			true
		);

		// Enqueue custom database viewer script.
		wp_enqueue_script(
			'notion-sync-database-viewer',
			NOTION_SYNC_URL . 'assets/src/js/database-viewer.js',
			array( 'tabulator' ),
			NOTION_SYNC_VERSION,
			true
		);

		// Localize script with REST API data.
		$post_id = get_the_ID();

		wp_localize_script(
			'notion-sync-database-viewer',
			'notionDatabaseViewer',
			array(
				'restUrl' => rest_url( 'notion-sync/v1' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'postId'  => $post_id,
				'i18n'    => array(
					'loading'      => __( 'Loading database...', 'notion-wp' ),
					'error'        => __( 'Error loading database', 'notion-wp' ),
					'noData'       => __( 'No rows found', 'notion-wp' ),
					'exportCsv'    => __( 'Export CSV', 'notion-wp' ),
					'exportJson'   => __( 'Export JSON', 'notion-wp' ),
					'resetFilters' => __( 'Reset Filters', 'notion-wp' ),
				),
			)
		);

		// Add inline styles for frontend presentation.
		wp_add_inline_style(
			'tabulator',
			'
			.notion-database-viewer {
				background: #fff;
				padding: 30px;
				border: 1px solid #ddd;
				border-radius: 8px;
				margin: 20px 0;
				box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			}
			.database-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 20px;
				padding-bottom: 15px;
				border-bottom: 2px solid #eee;
			}
			.database-title {
				margin: 0;
				font-size: 28px;
				font-weight: 600;
				color: #333;
			}
			.database-meta {
				color: #666;
				font-size: 14px;
				margin-top: 8px;
			}
			.database-actions {
				display: flex;
				gap: 10px;
			}
			.database-actions button {
				padding: 8px 16px;
				background: #2271b1;
				color: #fff;
				border: none;
				border-radius: 4px;
				cursor: pointer;
				font-size: 14px;
				transition: background 0.2s;
			}
			.database-actions button:hover {
				background: #135e96;
			}
			#database-table {
				border: 1px solid #ddd;
				border-radius: 4px;
				overflow: hidden;
			}
			.tabulator .tabulator-header {
				background-color: #f9fafb;
				border-bottom: 2px solid #ddd;
			}
			.tabulator .tabulator-row {
				border-bottom: 1px solid #eee;
			}
			.tabulator .tabulator-row:hover {
				background-color: #f5f5f5;
			}
			#table-loading {
				text-align: center;
				padding: 60px 20px;
				color: #666;
				font-size: 16px;
			}
			#table-error {
				display: none;
				padding: 20px;
				background: #f8d7da;
				border: 1px solid #f5c6cb;
				border-radius: 4px;
				color: #721c24;
				margin-top: 20px;
			}
			.spinner {
				display: inline-block;
				width: 20px;
				height: 20px;
				border: 3px solid rgba(0,0,0,.1);
				border-radius: 50%;
				border-top-color: #2271b1;
				animation: spin 1s ease-in-out infinite;
			}
			@keyframes spin {
				to { transform: rotate(360deg); }
			}
			'
		);
	}
}
