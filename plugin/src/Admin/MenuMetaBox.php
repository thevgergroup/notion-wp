<?php
/**
 * Menu Meta Box
 *
 * Enhances WordPress's native menu editor with Notion sync information.
 * Adds a meta box showing sync status and enhances menu items with
 * Notion-specific fields and controls.
 *
 * @package NotionWP
 * @subpackage Admin
 * @since 0.2.0-dev
 */

declare(strict_types=1);

namespace NotionWP\Admin;

use NotionWP\Navigation\MenuItemMeta;

/**
 * Class MenuMetaBox
 *
 * Integrates Notion sync information into WordPress's native menu editor.
 * Provides visual indicators for synced items and override controls.
 *
 * @since 0.2.0-dev
 */
class MenuMetaBox {
	/**
	 * MenuItemMeta instance for tracking sync status
	 *
	 * @var MenuItemMeta
	 */
	private MenuItemMeta $menu_item_meta;

	/**
	 * Constructor
	 *
	 * @param MenuItemMeta $menu_item_meta MenuItemMeta instance.
	 */
	public function __construct( MenuItemMeta $menu_item_meta ) {
		$this->menu_item_meta = $menu_item_meta;
	}

	/**
	 * Register WordPress hooks
	 *
	 * @since 0.2.0-dev
	 * @return void
	 */
	public function register(): void {
		// Add meta box to menu editor.
		add_action( 'admin_init', array( $this, 'add_meta_box' ) );

		// Add custom fields to menu items.
		add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'add_item_fields' ), 10, 4 );

		// Save menu item override setting.
		add_action( 'wp_update_nav_menu_item', array( $this, 'save_item_override' ), 10, 2 );

		// Enqueue admin styles and scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Add visual indicator to menu item titles.
		add_filter( 'nav_menu_item_title', array( $this, 'add_sync_indicator' ), 10, 4 );
	}

	/**
	 * Add meta box to menu editor
	 *
	 * @since 0.2.0-dev
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'notion-menu-sync',
			__( 'Notion Menu Sync', 'vger-sync-for-notion' ),
			array( $this, 'render_meta_box' ),
			'nav-menus',
			'side',
			'high'
		);
	}

	/**
	 * Render the Notion sync meta box
	 *
	 * @since 0.2.0-dev
	 * @param mixed $object Not used for nav-menus screen.
	 * @return void
	 */
	public function render_meta_box( $object ): void {
		// Get current menu ID.
		// phpcs:ignore Generic.Files.LineLength.MaxExceeded,WordPress.Security.NonceVerification.Recommended
		$nav_menu_selected_id = isset( $_REQUEST['menu'] ) ? (int) $_REQUEST['menu'] : 0;

		if ( ! $nav_menu_selected_id ) {
			echo '<p>' . esc_html__( 'Select or create a menu to view sync status.', 'vger-sync-for-notion' ) . '</p>';
			return;
		}

		// Get menu items.
		$menu_items = wp_get_nav_menu_items( $nav_menu_selected_id );
		$total_items = is_array( $menu_items ) ? count( $menu_items ) : 0;

		// Count Notion-synced items.
		$synced_count = 0;
		if ( $menu_items ) {
			foreach ( $menu_items as $item ) {
				if ( $this->menu_item_meta->is_notion_synced( $item->ID ) ) {
					++$synced_count;
				}
			}
		}

		// Get last sync time from option.
		$last_sync_time = get_option( 'notion_menu_last_sync_time', 0 );

		// Generate nonce for AJAX.
		$nonce = wp_create_nonce( 'notion_sync_menu_now' );

		?>
		<div class="notion-menu-sync-meta-box">
			<div class="notion-sync-stats">
				<?php if ( $last_sync_time > 0 ) : ?>
					<p class="notion-sync-last-synced">
						<strong><?php esc_html_e( 'Last Synced:', 'vger-sync-for-notion' ); ?></strong>
						<br>
						<time datetime="<?php echo esc_attr( gmdate( 'c', $last_sync_time ) ); ?>">
							<?php
							// translators: %s: human-readable time difference.
							printf( esc_html__( '%s ago', 'vger-sync-for-notion' ), esc_html( human_time_diff( $last_sync_time ) ) );
							?>
						</time>
					</p>
				<?php else : ?>
					<p class="notion-sync-never">
						<strong><?php esc_html_e( 'Last Synced:', 'vger-sync-for-notion' ); ?></strong>
						<br>
						<?php esc_html_e( 'Never', 'vger-sync-for-notion' ); ?>
					</p>
				<?php endif; ?>

				<p class="notion-sync-item-count">
					<strong><?php esc_html_e( 'Synced Items:', 'vger-sync-for-notion' ); ?></strong>
					<br>
					<?php
					printf(
						/* translators: 1: number of synced items, 2: total number of items */
						esc_html__( '%1$d of %2$d', 'vger-sync-for-notion' ),
						absint( $synced_count ),
						absint( $total_items )
					);
					?>
				</p>
			</div>

			<div class="notion-sync-actions">
				<button
					type="button"
					id="notion-sync-menu-button"
					class="button button-primary button-large"
					data-nonce="<?php echo esc_attr( $nonce ); ?>"
					aria-label="<?php esc_attr_e( 'Sync menu from Notion now', 'vger-sync-for-notion' ); ?>"
				>
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e( 'Sync from Notion Now', 'vger-sync-for-notion' ); ?>
				</button>
			</div>

			<div id="notion-menu-sync-messages" role="status" aria-live="polite"></div>

			<div class="notion-sync-help">
				<p class="description">
					<span class="dashicons dashicons-info" aria-hidden="true"></span>
					<?php
					// phpcs:ignore Generic.Files.LineLength.MaxExceeded
					esc_html_e( 'Notion-synced items show a sync icon (🔄). Toggle "Prevent Notion Updates" to preserve manual changes.', 'vger-sync-for-notion' );
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Add custom fields to menu items
	 *
	 * Shows Notion sync information for items synced from Notion.
	 *
	 * @since 0.2.0-dev
	 * @param int       $item_id Menu item ID.
	 * @param \WP_Post  $item    Menu item post object.
	 * @param int       $depth   Menu item depth.
	 * @param \stdClass $args    Menu item args (WordPress passes stdClass, not array).
	 * @return void
	 */
	public function add_item_fields( int $item_id, \WP_Post $item, int $depth, \stdClass $args ): void {
		// Only show for Notion-synced items.
		if ( ! $this->menu_item_meta->is_notion_synced( $item_id ) ) {
			return;
		}

		$notion_page_id = $this->menu_item_meta->get_notion_page_id( $item_id );
		$has_override = $this->menu_item_meta->has_override( $item_id );

		?>
		<p class="field-notion-sync description description-wide">
			<label for="notion-synced-<?php echo esc_attr( $item_id ); ?>">
				<span class="dashicons dashicons-update" style="color: #2271b1;" aria-hidden="true"></span>
				<?php esc_html_e( 'Notion Sync', 'vger-sync-for-notion' ); ?>
			</label>
			<span id="notion-synced-<?php echo esc_attr( $item_id ); ?>" class="notion-sync-indicator">
				<?php esc_html_e( 'This item is synced from Notion', 'vger-sync-for-notion' ); ?>
			</span>
		</p>

		<?php if ( $notion_page_id ) : ?>
			<p class="field-notion-page-id description description-wide">
				<label for="notion-page-id-<?php echo esc_attr( $item_id ); ?>">
					<?php esc_html_e( 'Notion Page ID:', 'vger-sync-for-notion' ); ?>
				</label>
				<code id="notion-page-id-<?php echo esc_attr( $item_id ); ?>" class="notion-page-id-display">
					<?php echo esc_html( $notion_page_id ); ?>
				</code>
			</p>
		<?php endif; ?>

		<p class="field-notion-override description description-wide">
			<label for="notion-override-<?php echo esc_attr( $item_id ); ?>">
				<input
					type="checkbox"
					id="notion-override-<?php echo esc_attr( $item_id ); ?>"
					name="menu-item-notion-override[<?php echo esc_attr( $item_id ); ?>]"
					value="1"
					<?php checked( $has_override, true ); ?>
					aria-describedby="notion-override-help-<?php echo esc_attr( $item_id ); ?>"
				>
				<?php esc_html_e( 'Prevent Notion Updates', 'vger-sync-for-notion' ); ?>
			</label>
			<span id="notion-override-help-<?php echo esc_attr( $item_id ); ?>" class="description">
				<?php esc_html_e( 'When checked, this item will not be updated during Notion sync.', 'vger-sync-for-notion' ); ?>
			</span>
		</p>
		<?php
	}

	/**
	 * Save menu item override setting
	 *
	 * @since 0.2.0-dev
	 * @param int $menu_id  Menu ID.
	 * @param int $item_id  Menu item ID.
	 * @return void
	 */
	public function save_item_override( int $menu_id, int $item_id ): void {
		// Verify nonce - WordPress handles this in wp_update_nav_menu_item.
		// We don't need additional verification here.

		// Check if this is a Notion-synced item.
		if ( ! $this->menu_item_meta->is_notion_synced( $item_id ) ) {
			return;
		}

		// Check if override checkbox was submitted.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by WordPress core in wp_update_nav_menu_item.
		$override_value = isset( $_POST['menu-item-notion-override'][ $item_id ] ) ? true : false;

		// Update override setting.
		$this->menu_item_meta->set_override( $item_id, $override_value );
	}

	/**
	 * Add sync indicator to menu item title in editor
	 *
	 * @since 0.2.0-dev
	 * @param string   $title   Menu item title.
	 * @param \WP_Post $item    Menu item post object.
	 * @param array    $args    Menu item args.
	 * @param int      $depth   Menu item depth.
	 * @return string Modified title with sync indicator.
	 */
	public function add_sync_indicator( string $title, \WP_Post $item, array $args, int $depth ): string {
		// Only add indicator in admin menu editor.
		if ( ! is_admin() ) {
			return $title;
		}

		// Check if on nav-menus screen.
		$screen = get_current_screen();
		if ( ! $screen || 'nav-menus' !== $screen->id ) {
			return $title;
		}

		// Only add for Notion-synced items.
		if ( ! $this->menu_item_meta->is_notion_synced( $item->ID ) ) {
			return $title;
		}

		// Add sync emoji indicator.
		return '<span class="notion-sync-icon" aria-label="' . esc_attr__( 'Synced from Notion', 'vger-sync-for-notion' ) . '">🔄</span> ' . $title;
	}

	/**
	 * Enqueue admin styles and scripts
	 *
	 * @since 0.2.0-dev
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		// Only load on nav-menus screen.
		if ( 'nav-menus.php' !== $hook ) {
			return;
		}

		// Enqueue admin navigation script.
		$script_path = VGER_SYNC_PATH . 'assets/dist/js/admin-navigation.js';
		$script_url  = VGER_SYNC_URL . 'assets/dist/js/admin-navigation.js';

		if ( file_exists( $script_path ) ) {
			wp_enqueue_script(
				'notion-admin-navigation',
				$script_url,
				array( 'jquery' ),
				filemtime( $script_path ),
				true
			);

			// Localize script with AJAX URL.
			wp_localize_script(
				'notion-admin-navigation',
				'notionAdminNavigation',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'notion_sync_menu_now' ),
				)
			);
		}

		// Enqueue inline styles for meta box.
		$this->enqueue_inline_styles();
	}

	/**
	 * Enqueue inline styles for meta box
	 *
	 * @since 0.2.0-dev
	 * @return void
	 */
	private function enqueue_inline_styles(): void {
		$css = '
		/* Notion Menu Sync Meta Box */
		.notion-menu-sync-meta-box {
			padding: 12px;
		}

		.notion-sync-stats {
			margin-bottom: 16px;
		}

		.notion-sync-stats p {
			margin: 0 0 12px;
			color: #50575e;
			font-size: 13px;
		}

		.notion-sync-stats strong {
			color: #1d2327;
			font-weight: 600;
		}

		.notion-sync-stats time {
			color: #2271b1;
		}

		.notion-sync-actions {
			margin-bottom: 16px;
		}

		.notion-sync-actions .button-primary {
			width: 100%;
			height: auto;
			padding: 8px 16px;
			text-align: center;
			white-space: normal;
		}

		.notion-sync-actions .button-primary .dashicons {
			vertical-align: middle;
			margin-right: 4px;
		}

		.notion-sync-actions .button-primary.updating-message .dashicons {
			animation: rotation 1s infinite linear;
		}

		#notion-menu-sync-messages {
			margin: 12px 0;
			min-height: 20px;
		}

		#notion-menu-sync-messages .notice {
			margin: 5px 0;
			padding: 8px 12px;
		}

		#notion-menu-sync-messages .notice p {
			margin: 0.5em 0;
			font-size: 13px;
		}

		.notion-sync-help {
			padding-top: 12px;
			border-top: 1px solid #dcdcde;
		}

		.notion-sync-help .description {
			margin: 0;
			font-size: 12px;
			line-height: 1.5;
		}

		.notion-sync-help .dashicons {
			color: #2271b1;
			vertical-align: middle;
			margin-right: 4px;
		}

		/* Menu Item Custom Fields */
		.field-notion-sync,
		.field-notion-page-id,
		.field-notion-override {
			padding: 10px 0;
			border-top: 1px solid #f0f0f1;
		}

		.field-notion-sync .dashicons {
			vertical-align: middle;
			margin-right: 4px;
		}

		.notion-sync-indicator {
			display: inline-block;
			padding: 4px 8px;
			background: #d5f5e3;
			color: #00712e;
			border: 1px solid #00a32a;
			border-radius: 3px;
			font-size: 12px;
			font-weight: 600;
		}

		.notion-page-id-display {
			display: inline-block;
			padding: 4px 8px;
			background: #f0f0f1;
			border: 1px solid #c3c4c7;
			border-radius: 3px;
			font-family: "Menlo", "Monaco", "Consolas", monospace;
			font-size: 11px;
			color: #1d2327;
			word-break: break-all;
		}

		.field-notion-override input[type="checkbox"] {
			margin-right: 6px;
		}

		.field-notion-override .description {
			display: block;
			margin-top: 4px;
			margin-left: 24px;
			color: #646970;
			font-size: 12px;
			font-style: italic;
		}

		/* Sync Icon in Menu Item Title */
		.notion-sync-icon {
			display: inline-block;
			font-size: 14px;
		}

		/* Animation for sync button */
		@keyframes rotation {
			from {
				transform: rotate(0deg);
			}
			to {
				transform: rotate(359deg);
			}
		}

		/* Accessibility - Focus States */
		#notion-sync-menu-button:focus {
			outline: 2px solid #2271b1;
			outline-offset: 2px;
			box-shadow: none;
		}

		.field-notion-override input[type="checkbox"]:focus {
			outline: 2px solid #2271b1;
			outline-offset: 2px;
		}

		/* Mobile Responsive */
		@media screen and (max-width: 782px) {
			.notion-sync-actions .button-primary {
				min-height: 44px;
				font-size: 14px;
			}

			.notion-sync-stats p,
			.notion-sync-help .description {
				font-size: 14px;
			}
		}
		';

		wp_add_inline_style( 'wp-admin', $css );
	}
}
