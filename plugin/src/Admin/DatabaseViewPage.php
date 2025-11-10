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
	 * Added as submenu of Vger Sync for Notion but hidden from display using CSS.
	 * This prevents PHP 8.3 deprecation warnings with hidden pages.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_page(): void {
		add_submenu_page(
			'vger-sync-for-notion', // Parent page slug (main settings page).
			__( 'View Database', 'vger-sync-for-notion' ),
			__( 'View Database', 'vger-sync-for-notion' ),
			'manage_options',
			'notion-sync-view-database',
			array( $this, 'render_page' )
		);

		// Hide this submenu item from the admin menu using CSS.
		add_action(
			'admin_head',
			function () {
				echo '<style>#toplevel_page_vger-sync-for-notion .wp-submenu li a[href*="vger-sync-view-database"] { display: none !important; }</style>';
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

		// Enqueue Tabulator CSS (bundled locally for WordPress.org compliance).
		wp_enqueue_style(
			'tabulator',
			VGER_SYNC_URL . 'assets/vendor/tabulator/tabulator.min.css',
			array(),
			'6.3.0'
		);

		// Enqueue Luxon.js (bundled locally - required for datetime sorting).
		wp_enqueue_script(
			'luxon',
			VGER_SYNC_URL . 'assets/vendor/tabulator/luxon.min.js',
			array(),
			'3.4.4',
			true
		);

		// Enqueue Tabulator JS (bundled locally - depends on luxon for datetime sorting).
		wp_enqueue_script(
			'tabulator',
			VGER_SYNC_URL . 'assets/vendor/tabulator/tabulator.min.js',
			array( 'luxon' ),
			'6.3.0',
			true
		);

		// Enqueue custom database viewer script.
		wp_enqueue_script(
			'notion-sync-database-viewer',
			VGER_SYNC_URL . 'assets/src/js/database-viewer.js',
			array( 'tabulator' ),
			VGER_SYNC_VERSION,
			true
		);

		// Localize script with REST API data.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only parameter for determining which database to display.
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		wp_localize_script(
			'notion-sync-database-viewer',
			'notionDatabaseViewer',
			array(
				'restUrl' => rest_url( 'notion-sync/v1' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'postId'  => $post_id,
				'i18n'    => array(
					'loading'      => __( 'Loading database...', 'vger-sync-for-notion' ),
					'error'        => __( 'Error loading database', 'vger-sync-for-notion' ),
					'noData'       => __( 'No rows found', 'vger-sync-for-notion' ),
					'exportCsv'    => __( 'Export CSV', 'vger-sync-for-notion' ),
					'exportJson'   => __( 'Export JSON', 'vger-sync-for-notion' ),
					'resetFilters' => __( 'Reset Filters', 'vger-sync-for-notion' ),
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only parameter for determining which database to display.
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				return $post->post_title . ' &lsaquo; ' . get_bloginfo( 'name' );
			}
		}

		// Fallback to generic title.
		return __( 'View Database', 'vger-sync-for-notion' ) . ' &lsaquo; ' . get_bloginfo( 'name' );
	}

	/**
	 * Render admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_page(): void {
		// Get database post ID.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only parameter for determining which database to display.
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_die( esc_html__( 'Invalid database ID', 'vger-sync-for-notion' ) );
		}

		// Verify post exists and is a database.
		$post = get_post( $post_id );
		if ( ! $post || 'notion_database' !== $post->post_type ) {
			wp_die( esc_html__( 'Database not found', 'vger-sync-for-notion' ) );
		}

		// Get database metadata.
		$notion_db_id = get_post_meta( $post_id, 'notion_database_id', true );
		$row_count    = get_post_meta( $post_id, 'row_count', true );
		$last_synced  = get_post_meta( $post_id, 'last_synced', true );

		// Render page template.
		include VGER_SYNC_PATH . 'templates/admin/database-view.php';
	}
}
