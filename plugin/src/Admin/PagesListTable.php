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
				'singular' => 'page',
				'plural'   => 'pages',
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
	 * Defines which columns are sortable (none for MVP - keep it simple).
	 *
	 * @since 1.0.0
	 *
	 * @return array Empty array (no sorting in MVP).
	 */
	protected function get_sortable_columns() {
		return array();
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
			'<input type="checkbox" name="page_ids[]" value="%s" />',
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
				'<span class="notion-sync-badge notion-sync-badge-synced" data-page-id="%s">
					<span class="dashicons dashicons-yes" aria-hidden="true"></span>
					%s
				</span>',
				esc_attr( $item['id'] ),
				esc_html__( 'Synced', 'notion-wp' )
			);
		} else {
			return sprintf(
				'<span class="notion-sync-badge notion-sync-badge-not-synced" data-page-id="%s">
					<span class="dashicons dashicons-minus" aria-hidden="true"></span>
					%s
				</span>',
				esc_attr( $item['id'] ),
				esc_html__( 'Not Synced', 'notion-wp' )
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
	 * Handles bulk sync operations for selected pages.
	 * This method should be called before prepare_items().
	 *
	 * @since 1.0.0
	 */
	public function process_bulk_action() {
		// Check if bulk sync action was triggered.
		$action = $this->current_action();

		if ( 'bulk_sync' !== $action ) {
			return;
		}

		// Verify nonce.
		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		// Get selected page IDs.
		$page_ids = isset( $_REQUEST['page_ids'] ) ? (array) $_REQUEST['page_ids'] : array();

		if ( empty( $page_ids ) ) {
			add_settings_error(
				'notion_sync',
				'no_pages_selected',
				__( 'No pages selected. Please select pages to sync.', 'notion-wp' ),
				'warning'
			);
			return;
		}

		// Sync each selected page.
		$success_count = 0;
		$error_count   = 0;
		$errors        = array();

		foreach ( $page_ids as $page_id ) {
			$page_id = sanitize_text_field( $page_id );

			try {
				$result = $this->manager->sync_page( $page_id );

				if ( $result['success'] ) {
					$success_count++;
				} else {
					$error_count++;
					$errors[] = $result['error'] ?? __( 'Unknown error', 'notion-wp' );
				}
			} catch ( \Exception $e ) {
				$error_count++;
				$errors[] = $e->getMessage();
			}
		}

		// Add admin notice with results.
		if ( $success_count > 0 && $error_count === 0 ) {
			add_settings_error(
				'notion_sync',
				'bulk_sync_success',
				sprintf(
					/* translators: %d: number of pages synced */
					_n(
						'Successfully synced %d page.',
						'Successfully synced %d pages.',
						$success_count,
						'notion-wp'
					),
					$success_count
				),
				'success'
			);
		} elseif ( $success_count > 0 && $error_count > 0 ) {
			$error_details = ! empty( $errors ) ? ' ' . implode( ', ', array_slice( $errors, 0, 3 ) ) : '';
			add_settings_error(
				'notion_sync',
				'bulk_sync_partial',
				sprintf(
					/* translators: 1: number of successful syncs, 2: number of errors, 3: error details */
					__( 'Synced %1$d pages. Failed to sync %2$d pages.%3$s', 'notion-wp' ),
					$success_count,
					$error_count,
					$error_details
				),
				'warning'
			);
		} else {
			$error_details = ! empty( $errors ) ? ' ' . implode( ', ', array_slice( $errors, 0, 3 ) ) : '';
			add_settings_error(
				'notion_sync',
				'bulk_sync_error',
				sprintf(
					/* translators: 1: number of errors, 2: error details */
					__( 'Failed to sync %1$d pages.%2$s', 'notion-wp' ),
					$error_count,
					$error_details
				),
				'error'
			);
		}

		// Redirect to remove the action from URL.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'notion-sync',
					'settings-updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
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
		$pages = $this->fetcher->fetch_pages_list( 100 );

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

	/**
	 * Display the bulk actions dropdown.
	 *
	 * Override to add accessible attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $which Position: 'top' or 'bottom'.
	 */
	protected function bulk_actions( $which = '' ) {
		if ( is_null( $this->_actions ) ) {
			$this->_actions = $this->get_bulk_actions();

			/**
			 * Filters the bulk actions for the list table.
			 *
			 * @since 1.0.0
			 *
			 * @param array  $actions Bulk actions array.
			 * @param string $which   Position: 'top' or 'bottom'.
			 */
			$this->_actions = apply_filters( "bulk_actions-{$this->screen->id}", $this->_actions, $which );

			$two = '';
		} else {
			$two = '2';
		}

		if ( empty( $this->_actions ) ) {
			return;
		}

		echo '<label for="bulk-action-selector-' . esc_attr( $which ) . '" class="screen-reader-text">' .
			esc_html__( 'Select bulk action', 'notion-wp' ) . '</label>';
		echo '<select name="action' . esc_attr( $two ) . '" id="bulk-action-selector-' . esc_attr( $which ) . '">';
		echo '<option value="-1">' . esc_html__( 'Bulk actions', 'notion-wp' ) . '</option>';

		foreach ( $this->_actions as $name => $title ) {
			echo "\t" . '<option value="' . esc_attr( $name ) . '">' . esc_html( $title ) . "</option>\n";
		}

		echo "</select>\n";

		submit_button(
			__( 'Apply', 'notion-wp' ),
			'action',
			'',
			false,
			array(
				'id'    => 'doaction' . esc_attr( $two ),
				'class' => 'button action',
			)
		);
		echo "\n";
	}
}
