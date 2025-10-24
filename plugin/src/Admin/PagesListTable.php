<?php
/**
 * Pages List Table - Displays Notion pages in WordPress admin with sync functionality.
 *
 * Extends WP_List_Table to provide a native WordPress interface for browsing
 * Notion pages and syncing them to WordPress. Includes bulk actions, individual
 * sync operations, and real-time status updates.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Admin;

use NotionSync\Sync\ContentFetcher;
use NotionSync\Sync\SyncManager;
use NotionSync\Admin\BulkSyncProcessor;

// Load WP_List_Table if not already loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class PagesListTable
 *
 * WordPress admin table for displaying and syncing Notion pages.
 * Provides bulk sync actions, individual row actions, and status indicators.
 *
 * @since 1.0.0
 */
class PagesListTable extends \WP_List_Table {

	/**
	 * Content fetcher instance.
	 *
	 * @var ContentFetcher
	 */
	private $fetcher;

	/**
	 * Sync manager instance.
	 *
	 * @var SyncManager
	 */
	private $manager;

	/**
	 * Constructor.
	 *
	 * Sets up the list table with required properties and initializes
	 * the content fetcher and sync manager.
	 *
	 * @since 1.0.0
	 *
	 * @param ContentFetcher $fetcher Content fetcher instance.
	 * @param SyncManager    $manager Sync manager instance.
	 */
	public function __construct( ContentFetcher $fetcher, SyncManager $manager ) {
		parent::__construct(
			array(
				'singular' => 'notion_page',
				'plural'   => 'notion_pages',
				'ajax'     => true,
			)
		);

		$this->fetcher = $fetcher;
		$this->manager = $manager;
	}

	/**
	 * Get table columns.
	 *
	 * Defines the columns to display in the table.
	 *
	 * @since 1.0.0
	 *
	 * @return array Column key => label pairs.
	 */
	public function get_columns() {
		return array(
			'cb'          => '<input type="checkbox" />',
			'title'       => __( 'Page Title', 'notion-wp' ),
			'type'        => __( 'Type', 'notion-wp' ),
			'notion_id'   => __( 'Notion ID', 'notion-wp' ),
			'sync_status' => __( 'Status', 'notion-wp' ),
			'wp_post'     => __( 'WordPress Post', 'notion-wp' ),
			'last_synced' => __( 'Last Synced', 'notion-wp' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * Defines which columns are sortable.
	 *
	 * @since 1.0.0
	 *
	 * @return array Sortable column key => array( orderby key, default sorted ).
	 */
	protected function get_sortable_columns() {
		return array(
			'title'       => array( 'title', false ),
			'type'        => array( 'type', false ),
			'sync_status' => array( 'sync_status', false ),
			'last_synced' => array( 'last_synced', false ),
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * Defines bulk actions available for selected pages.
	 *
	 * @since 1.0.0
	 *
	 * @return array Action key => label pairs.
	 */
	protected function get_bulk_actions() {
		return array(
			'bulk_sync' => __( 'Sync Selected', 'notion-wp' ),
		);
	}

	/**
	 * Render checkbox column for bulk selection.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item Page data array.
	 * @return string HTML checkbox input.
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="notion_page[]" value="%s" />',
			esc_attr( $item['id'] )
		);
	}

	/**
	 * Render title column with row actions.
	 *
	 * This is the primary column that includes row action links.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item Page data array.
	 * @return string HTML for title column with actions.
	 */
	protected function column_title( $item ) {
		$title = ! empty( $item['title'] ) ? $item['title'] : __( 'Untitled', 'notion-wp' );

		// Build row actions.
		$actions = array();

		// Get sync status to determine available actions.
		$sync_status = $this->manager->get_sync_status( $item['id'] );

		// Sync Now action (always available).
		$actions['sync'] = sprintf(
			'<a href="#" class="notion-sync-now" data-page-id="%s" data-page-title="%s">%s</a>',
			esc_attr( $item['id'] ),
			esc_attr( $title ),
			esc_html__( 'Sync Now', 'notion-wp' )
		);

		// Edit Post action (only if synced).
		if ( $sync_status['is_synced'] && $sync_status['post_id'] ) {
			$edit_url = get_edit_post_link( $sync_status['post_id'] );
			if ( $edit_url ) {
				$actions['edit'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $edit_url ),
					esc_html__( 'Edit Post', 'notion-wp' )
				);
			}

			// View Post action (only if synced).
			$view_url = get_permalink( $sync_status['post_id'] );
			if ( $view_url ) {
				$actions['view'] = sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $view_url ),
					esc_html__( 'View Post', 'notion-wp' )
				);
			}
		}

		// View in Notion action.
		if ( ! empty( $item['url'] ) ) {
			$actions['notion'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $item['url'] ),
				esc_html__( 'View in Notion', 'notion-wp' )
			);
		}

		return sprintf(
			'<strong>%s</strong>%s',
			esc_html( $title ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Render type column.
	 *
	 * Displays the type of Notion object (Page, Database Entry, Database).
	 *
	 * @since 1.0.0
	 *
	 * @param array $item Page data array.
	 * @return string HTML for type column.
	 */
	protected function column_type( $item ) {
		$parent_type = $item['parent_type'] ?? 'unknown';
		$object_type = $item['object_type'] ?? 'page';

		// Determine display label and icon
		if ( 'database' === $object_type ) {
			$label = __( 'Database', 'notion-wp' );
			$icon  = 'dashicons-database';
			$color = '#7c3aed'; // Purple
		} elseif ( 'database_id' === $parent_type ) {
			$label = __( 'DB Entry', 'notion-wp' );
			$icon  = 'dashicons-list-view';
			$color = '#2563eb'; // Blue
		} elseif ( 'page_id' === $parent_type ) {
			$label = __( 'Child Page', 'notion-wp' );
			$icon  = 'dashicons-media-document';
			$color = '#059669'; // Emerald green
		} else {
			$label = __( 'Page', 'notion-wp' );
			$icon  = 'dashicons-media-document';
			$color = '#16a34a'; // Green
		}

		return sprintf(
			'<span style="display: inline-flex; align-items: center; gap: 4px; color: %s;">
				<span class="dashicons %s" style="font-size: 16px;"></span>
				<span>%s</span>
			</span>',
			esc_attr( $color ),
			esc_attr( $icon ),
			esc_html( $label )
		);
	}

	/**
	 * Render Notion ID column.
	 *
	 * Displays first 8 characters of Notion page ID with copy button.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item Page data array.
	 * @return string HTML for Notion ID column.
	 */
	protected function column_notion_id( $item ) {
		$normalized_id = str_replace( '-', '', $item['id'] );
		$short_id      = substr( $normalized_id, 0, 8 );

		return sprintf(
			'<code>%s...</code> <button type="button" class="button button-small notion-copy-id" data-copy="%s" title="%s" aria-label="%s">
				<span class="dashicons dashicons-admin-page" style="font-size: 13px; vertical-align: middle;"></span>
			</button>',
			esc_html( $short_id ),
			esc_attr( $normalized_id ),
			esc_attr__( 'Copy full Notion ID', 'notion-wp' ),
			esc_attr__( 'Copy full Notion ID', 'notion-wp' )
		);
	}

	/**
	 * Render sync status column.
	 *
	 * Displays color-coded badge indicating sync status.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item Page data array.
	 * @return string HTML for status column.
	 */
	protected function column_sync_status( $item ) {
		$sync_status = $this->manager->get_sync_status( $item['id'] );

		if ( $sync_status['is_synced'] ) {
			return sprintf(
				'<span class="notion-sync-badge notion-sync-badge-synced" data-page-id="%s" title="%s" style="display: inline-flex; align-items: center; padding: 4px 8px; background: #e7f5ec; border-radius: 3px;">
					<span class="dashicons dashicons-yes-alt" style="color: #00a32a; font-size: 18px; width: 18px; height: 18px;"></span>
				</span>',
				esc_attr( $item['id'] ),
				esc_attr__( 'Synced - WordPress post is up-to-date', 'notion-wp' )
			);
		} else {
			return sprintf(
				'<span class="notion-sync-badge notion-sync-badge-not-synced" data-page-id="%s" title="%s" style="display: inline-flex; align-items: center; padding: 4px 8px; background: #f0f0f1; border-radius: 3px;">
					<span class="dashicons dashicons-minus" style="color: #8c8f94; font-size: 18px; width: 18px; height: 18px;"></span>
				</span>',
				esc_attr( $item['id'] ),
				esc_attr__( 'Not Synced - This page has not been synced yet', 'notion-wp' )
			);
		}
	}

	/**
	 * Render WordPress post column.
	 *
	 * Displays link to WordPress post if synced, or dash if not synced.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item Page data array.
	 * @return string HTML for WordPress post column.
	 */
	protected function column_wp_post( $item ) {
		$sync_status = $this->manager->get_sync_status( $item['id'] );

		if ( $sync_status['is_synced'] && $sync_status['post_id'] ) {
			$edit_url = get_edit_post_link( $sync_status['post_id'] );
			if ( $edit_url ) {
				return sprintf(
					'<a href="%s" data-page-id="%s">#%d</a>',
					esc_url( $edit_url ),
					esc_attr( $item['id'] ),
					(int) $sync_status['post_id']
				);
			}
		}

		return sprintf(
			'<span data-page-id="%s">—</span>',
			esc_attr( $item['id'] )
		);
	}

	/**
	 * Render last synced column.
	 *
	 * Displays human-readable time since last sync or "Never" if not synced.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item Page data array.
	 * @return string HTML for last synced column.
	 */
	protected function column_last_synced( $item ) {
		$sync_status = $this->manager->get_sync_status( $item['id'] );

		if ( $sync_status['is_synced'] && $sync_status['last_synced'] ) {
			$timestamp = strtotime( $sync_status['last_synced'] );
			if ( $timestamp ) {
				return sprintf(
					'<time datetime="%s" data-page-id="%s">%s</time>',
					esc_attr( $sync_status['last_synced'] ),
					esc_attr( $item['id'] ),
					sprintf(
						/* translators: %s: human-readable time difference */
						esc_html__( '%s ago', 'notion-wp' ),
						human_time_diff( $timestamp, current_time( 'timestamp' ) )
					)
				);
			}
		}

		return sprintf(
			'<span data-page-id="%s">%s</span>',
			esc_attr( $item['id'] ),
			esc_html__( 'Never', 'notion-wp' )
		);
	}

	/**
	 * Default column renderer.
	 *
	 * Fallback for columns without a specific render method.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $item        Page data array.
	 * @param string $column_name Column name.
	 * @return string Column value or empty string.
	 */
	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Process bulk actions.
	 *
	 * NOTE: Bulk sync is handled entirely via JavaScript/AJAX (see page-sync.js).
	 * This method handles the fallback case when JavaScript fails to intercept.
	 * It prevents WordPress's "Please select at least one item" error by validating
	 * and gracefully handling the bulk_sync action server-side.
	 *
	 * @since 1.0.0
	 */
	public function process_bulk_action() {
		$action = $this->current_action();

		// Only handle bulk_sync action.
		if ( 'bulk_sync' !== $action ) {
			return;
		}

		// Check if any pages were selected.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in SettingsPage.
		$page_ids = isset( $_REQUEST['notion_page'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_REQUEST['notion_page'] ) ) : array();

		if ( empty( $page_ids ) ) {
			// No pages selected - JavaScript should have prevented this, but handle gracefully.
			// Don't show an error; the JavaScript will handle it.
			return;
		}

		// If we reach here, it means JavaScript failed to intercept the form submission.
		// Fall back to a simple redirect with a notice that JavaScript is required.
		add_settings_error(
			'notion_sync',
			'bulk_sync_js_required',
			__( 'Bulk sync requires JavaScript to be enabled. Please enable JavaScript and try again.', 'notion-wp' ),
			'warning'
		);
	}

	/**
	 * Display extra table navigation (filters).
	 *
	 * @since 1.0.0
	 *
	 * @param string $which Position of the navigation ('top' or 'bottom').
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current_filter = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';
		?>
		<div class="alignleft actions">
			<label for="filter-status" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'notion-wp' ); ?></label>
			<select name="filter_status" id="filter-status">
				<option value=""><?php esc_html_e( 'All Statuses', 'notion-wp' ); ?></option>
				<option value="synced" <?php selected( $current_filter, 'synced' ); ?>><?php esc_html_e( 'Synced', 'notion-wp' ); ?></option>
				<option value="not_synced" <?php selected( $current_filter, 'not_synced' ); ?>><?php esc_html_e( 'Not Synced', 'notion-wp' ); ?></option>
			</select>
			<?php submit_button( __( 'Filter', 'notion-wp' ), 'button', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Sort pages by specified column.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $pages   Array of page data.
	 * @param string $orderby Column to sort by.
	 * @param string $order   Sort direction ('asc' or 'desc').
	 * @return array Sorted pages array.
	 */
	private function sort_pages( array $pages, string $orderby, string $order ): array {
		usort(
			$pages,
			function ( $a, $b ) use ( $orderby, $order ) {
				$result = 0;

				switch ( $orderby ) {
					case 'title':
						$result = strcasecmp( $a['title'] ?? '', $b['title'] ?? '' );
						break;

					case 'type':
						$result = strcasecmp( $a['object_type'] ?? '', $b['object_type'] ?? '' );
						break;

					case 'sync_status':
						// Get sync status for comparison.
						$status_a = $this->manager->get_sync_status( $a['id'] );
						$status_b = $this->manager->get_sync_status( $b['id'] );

						// Convert boolean to sortable value: synced = 1, not_synced = 0.
						$val_a = $status_a['is_synced'] ? 1 : 0;
						$val_b = $status_b['is_synced'] ? 1 : 0;

						$result = $val_a <=> $val_b;
						break;

					case 'last_synced':
						// Get last_synced timestamps.
						$status_a = $this->manager->get_sync_status( $a['id'] );
						$status_b = $this->manager->get_sync_status( $b['id'] );

						$time_a = $status_a['last_synced'] ?? 0;
						$time_b = $status_b['last_synced'] ?? 0;

						$result = $time_a <=> $time_b;
						break;
				}

				// Apply order direction.
				return 'desc' === $order ? -$result : $result;
			}
		);

		return $pages;
	}

	/**
	 * Prepare table items for display.
	 *
	 * Fetches Notion pages and prepares them for table display.
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		// Set up columns.
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Fetch pages from Notion.
		$all_pages = $this->fetcher->fetch_pages_list( 100 );

		// Filter out:
		// 1. Databases (object_type = 'database') - shown on Databases tab
		// 2. Database entries (parent_type = 'database_id') - synced via database sync
		$pages = array_filter(
			$all_pages,
			function ( $page ) {
				$object_type = $page['object_type'] ?? 'page';
				$parent_type = $page['parent_type'] ?? 'unknown';

				// Exclude databases themselves.
				if ( 'database' === $object_type ) {
					return false;
				}

				// Exclude database entries (rows from databases).
				if ( 'database_id' === $parent_type ) {
					return false;
				}

				return true;
			}
		);

		// Apply status filter if requested.
		$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';

		if ( ! empty( $filter_status ) ) {
			$pages = array_filter(
				$pages,
				function ( $page ) use ( $filter_status ) {
					$sync_status = $this->manager->get_sync_status( $page['id'] );

					if ( 'synced' === $filter_status ) {
						return $sync_status['is_synced'];
					} elseif ( 'not_synced' === $filter_status ) {
						return ! $sync_status['is_synced'];
					}

					return true;
				}
			);
		}

		// Apply sorting if requested.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : '';
		$order   = isset( $_GET['order'] ) && 'desc' === strtolower( $_GET['order'] ) ? 'desc' : 'asc';

		if ( ! empty( $orderby ) ) {
			$pages = $this->sort_pages( $pages, $orderby, $order );
		}

		// Set items.
		$this->items = $pages;

		// Set pagination (not implemented in MVP - showing all pages).
		$this->set_pagination_args(
			array(
				'total_items' => count( $pages ),
				'per_page'    => 100,
				'total_pages' => 1,
			)
		);
	}

	/**
	 * Message to display when no pages are found.
	 *
	 * @since 1.0.0
	 */
	public function no_items() {
		esc_html_e( 'No Notion pages found. Please share pages with your integration in Notion.', 'notion-wp' );
	}
}
