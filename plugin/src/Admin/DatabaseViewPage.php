<?php
/**
 * Database View Admin Page
 *
 * Displays a single database with Tabulator.js for interactive sorting/filtering.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Admin;

/**
 * Class DatabaseViewPage
 *
 * Handles the database view admin page.
 *
 * @since 1.0.0
 */
class DatabaseViewPage {

	/**
	 * Register admin page and hooks.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_title', array( $this, 'set_admin_title' ), 10, 2 );
	}

	/**
	 * Add admin menu page.
	 *
	 * Added as submenu of Notion Sync but hidden from display using CSS.
	 * This prevents PHP 8.3 deprecation warnings with hidden pages.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_page(): void {
		add_submenu_page(
			'notion-sync', // Parent page slug (Notion Sync main page).
			__( 'View Database', 'notion-wp' ),
			__( 'View Database', 'notion-wp' ),
			'manage_options',
			'notion-sync-view-database',
			array( $this, 'render_page' )
		);

		// Hide this submenu item from the admin menu using CSS.
		add_action(
			'admin_head',
			function () {
				echo '<style>#toplevel_page_notion-sync .wp-submenu li a[href*="notion-sync-view-database"] { display: none !important; }</style>';
			}
		);
	}

	/**
	 * Enqueue Tabulator.js and custom scripts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		// Only load on our page.
		// Check using the page query parameter which is more reliable than hook suffix.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking page slug, not processing data.
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'notion-sync-view-database' !== $current_page ) {
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
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

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

		// Add inline styles for better presentation.
		wp_add_inline_style(
			'tabulator',
			'
			.notion-database-viewer {
				background: #fff;
				padding: 20px;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				margin-top: 20px;
			}
			.database-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 20px;
				padding-bottom: 15px;
				border-bottom: 1px solid #ddd;
			}
			.database-title {
				margin: 0;
				font-size: 24px;
			}
			.database-meta {
				color: #666;
				font-size: 13px;
			}
			.database-actions {
				display: flex;
				gap: 10px;
			}
			#database-table {
				border: 1px solid #ddd;
			}
			.tabulator .tabulator-header {
				background-color: #f9fafb;
			}
			'
		);
	}

	/**
	 * Set admin page title.
	 *
	 * Prevents strip_tags() deprecation warning by ensuring title is never null.
	 *
	 * @since 1.0.0
	 *
	 * @param string $admin_title The page title.
	 * @param string $title       The title of the admin page.
	 * @return string The page title.
	 */
	public function set_admin_title( $admin_title, $title ): string {
		// Only modify title on our page.
		$screen = get_current_screen();
		if ( ! $screen || 'notion-sync_page_notion-sync-view-database' !== $screen->id ) {
			return $admin_title;
		}

		// Get database title if available.
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				return $post->post_title . ' &lsaquo; ' . get_bloginfo( 'name' );
			}
		}

		// Fallback to generic title.
		return __( 'View Database', 'notion-wp' ) . ' &lsaquo; ' . get_bloginfo( 'name' );
	}

	/**
	 * Render admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_page(): void {
		// Get database post ID.
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_die( esc_html__( 'Invalid database ID', 'notion-wp' ) );
		}

		// Verify post exists and is a database.
		$post = get_post( $post_id );
		if ( ! $post || 'notion_database' !== $post->post_type ) {
			wp_die( esc_html__( 'Database not found', 'notion-wp' ) );
		}

		// Get database metadata.
		$notion_db_id = get_post_meta( $post_id, 'notion_database_id', true );
		$row_count    = get_post_meta( $post_id, 'row_count', true );
		$last_synced  = get_post_meta( $post_id, 'last_synced', true );

		// Render page template.
		include NOTION_SYNC_PATH . 'templates/admin/database-view.php';
	}
}
