<?php
/**
 * Databases List Table - Displays Notion databases in admin
 *
 * This list table shows all accessible Notion databases with sync status
 * and action buttons.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Admin;

use NotionSync\Sync\DatabaseFetcher;

/**
 * Class DatabasesListTable
 *
 * Extends WP_List_Table to show Notion databases with sync controls.
 *
 * @since 1.0.0
 */
class DatabasesListTable extends \WP_List_Table {

	/**
	 * Database fetcher instance.
	 *
	 * @var DatabaseFetcher
	 */
	private $fetcher;

	/**
	 * Constructor.
	 *
	 * @param DatabaseFetcher $fetcher Database fetcher instance.
	 */
	public function __construct( DatabaseFetcher $fetcher ) {
		parent::__construct(
			array(
				'singular' => 'database',
				'plural'   => 'databases',
				'ajax'     => false,
			)
		);

		$this->fetcher = $fetcher;
	}

	/**
	 * Get table columns.
	 *
	 * @return array Column definitions.
	 */
	public function get_columns(): array {
		return array(
			'title'       => __( 'Database Title', 'notion-sync' ),
			'notion_id'   => __( 'Notion ID', 'notion-sync' ),
			'sync_status' => __( 'Status', 'notion-sync' ),
			'entries'     => __( 'Entries', 'notion-sync' ),
			'last_synced' => __( 'Last Synced', 'notion-sync' ),
			'wp_post'     => __( 'WordPress Post', 'notion-sync' ),
			'actions'     => __( 'Actions', 'notion-sync' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array Sortable column key => array( orderby key, default sorted ).
	 */
	protected function get_sortable_columns() {
		return array(
			'title'       => array( 'title', false ),
			'sync_status' => array( 'sync_status', false ),
			'entries'     => array( 'entries', false ),
			'last_synced' => array( 'last_synced', false ),
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
			<label for="filter-status" class="screen-reader-text">
				<?php esc_html_e( 'Filter by status', 'notion-sync' ); ?>
			</label>
			<select name="filter_status" id="filter-status">
				<option value=""><?php esc_html_e( 'All Statuses', 'notion-sync' ); ?></option>
				<option value="synced" <?php selected( $current_filter, 'synced' ); ?>>
					<?php esc_html_e( 'Synced', 'notion-sync' ); ?>
				</option>
				<option value="not_synced" <?php selected( $current_filter, 'not_synced' ); ?>>
					<?php esc_html_e( 'Not Synced', 'notion-sync' ); ?>
				</option>
			</select>
			<?php submit_button( __( 'Filter', 'notion-sync' ), 'button', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Sort databases by specified column.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $databases Array of database data.
	 * @param string $orderby   Column to sort by.
	 * @param string $order     Sort direction ('asc' or 'desc').
	 * @return array Sorted databases array.
	 */
	private function sort_databases( array $databases, string $orderby, string $order ): array {
		usort(
			$databases,
			function ( $a, $b ) use ( $orderby, $order ) {
				$result = 0;

				switch ( $orderby ) {
					case 'title':
						$result = strcasecmp( $a['title'] ?? '', $b['title'] ?? '' );
						break;

					case 'sync_status':
						$val_a = ! empty( $a['wp_post_id'] ) ? 1 : 0;
						$val_b = ! empty( $b['wp_post_id'] ) ? 1 : 0;
						$result = $val_a <=> $val_b;
						break;

					case 'entries':
						$result = ( $a['entry_count'] ?? 0 ) <=> ( $b['entry_count'] ?? 0 );
						break;

					case 'last_synced':
						$time_a = ! empty( $a['last_synced'] ) ? strtotime( $a['last_synced'] ) : 0;
						$time_b = ! empty( $b['last_synced'] ) ? strtotime( $b['last_synced'] ) : 0;
						$result = $time_a <=> $time_b;
						break;
				}

				return 'desc' === $order ? -$result : $result;
			}
		);

		return $databases;
	}

	/**
	 * Prepare items for display.
	 *
	 * Fetches databases from Notion API and prepares for table display.
	 */
	public function prepare_items(): void {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		try {
			$databases = $this->fetcher->get_databases();

			// Enhance each database with WordPress sync status.
			foreach ( $databases as &$database ) {
				$database['wp_post_id']  = $this->find_database_post( $database['id'] );
				$database['entry_count'] = $this->get_database_entry_count( $database['id'] );
				$database['last_synced'] = $this->get_last_synced_time( $database['wp_post_id'] );
			}

			// Apply status filter if requested.
			$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';

			if ( ! empty( $filter_status ) ) {
				$databases = array_filter(
					$databases,
					function ( $database ) use ( $filter_status ) {
						if ( 'synced' === $filter_status ) {
							return ! empty( $database['wp_post_id'] );
						} elseif ( 'not_synced' === $filter_status ) {
							return empty( $database['wp_post_id'] );
						}
						return true;
					}
				);
			}

			// Apply sorting if requested.
			$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : '';
			$order   = isset( $_GET['order'] ) && 'desc' === strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) ?
				'desc' :
				'asc';

			if ( ! empty( $orderby ) ) {
				$databases = $this->sort_databases( $databases, $orderby, $order );
			}

			$this->items = $databases;

		} catch ( \Exception $e ) {
			$this->items = array();
		}
	}

	/**
	 * Render title column.
	 *
	 * @param array $item Database item.
	 * @return string Column content.
	 */
	public function column_title( $item ): string {
		$title = sprintf(
			'<strong>%s</strong>',
			esc_html( $item['title'] ?? __( 'Untitled', 'notion-sync' ) )
		);

		// Build row actions.
		$actions = array();

		// Add "View" link if database has been synced.
		if ( ! empty( $item['wp_post_id'] ) ) {
			$view_url = add_query_arg(
				array(
					'page'    => 'notion-sync-view-database',
					'post_id' => $item['wp_post_id'],
				),
				admin_url( 'admin.php' )
			);

			$actions['view'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $view_url ),
				esc_html__( 'View', 'notion-sync' )
			);
		}

		// Add "Open in Notion" link if URL is available.
		if ( ! empty( $item['url'] ) ) {
			$actions['notion'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $item['url'] ),
				esc_html__( 'Open in Notion', 'notion-sync' )
			);
		}

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Render sync status column.
	 *
	 * Displays icon-based sync status badge.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item Database item.
	 * @return string Column content.
	 */
	protected function column_sync_status( $item ) {
		$is_synced = ! empty( $item['wp_post_id'] );

		if ( $is_synced ) {
			return sprintf(
				'<span class="notion-sync-badge notion-sync-badge-synced" data-database-id="%s" title="%s"' .
				' style="display: inline-flex; align-items: center; padding: 4px 8px; background: #e7f5ec;' .
				' border-radius: 3px;">' .
					'<span class="dashicons dashicons-yes-alt"' .
					' style="color: #00a32a; font-size: 18px; width: 18px; height: 18px;"></span>' .
				'</span>',
				esc_attr( $item['id'] ),
				esc_attr__( 'Synced - WordPress post is up-to-date', 'notion-sync' )
			);
		} else {
			return sprintf(
				'<span class="notion-sync-badge notion-sync-badge-not-synced" data-database-id="%s" title="%s"' .
				' style="display: inline-flex; align-items: center; padding: 4px 8px; background: #f0f0f1;' .
				' border-radius: 3px;">' .
					'<span class="dashicons dashicons-minus"' .
					' style="color: #8c8f94; font-size: 18px; width: 18px; height: 18px;"></span>' .
				'</span>',
				esc_attr( $item['id'] ),
				esc_attr__( 'Not Synced - This database has not been synced yet', 'notion-sync' )
			);
		}
	}

	/**
	 * Render Notion ID column.
	 *
	 * @param array $item Database item.
	 * @return string Column content.
	 */
	public function column_notion_id( $item ): string {
		return sprintf(
			'<code style="font-size: 11px;">%s</code>',
			esc_html( $item['id'] ?? '' )
		);
	}

	/**
	 * Render entries column.
	 *
	 * @param array $item Database item.
	 * @return string Column content.
	 */
	public function column_entries( $item ): string {
		if ( isset( $item['entry_count'] ) ) {
			return sprintf(
				'<span class="badge">%d</span>',
				(int) $item['entry_count']
			);
		}

		return '<em>' . esc_html__( 'Unknown', 'notion-sync' ) . '</em>';
	}

	/**
	 * Render last synced column.
	 *
	 * @param array $item Database item.
	 * @return string Column content.
	 */
	public function column_last_synced( $item ): string {
		if ( ! empty( $item['last_synced'] ) ) {
			return sprintf(
				'<span title="%s">%s</span>',
				esc_attr( $item['last_synced'] ),
				esc_html( human_time_diff( strtotime( $item['last_synced'] ), current_time( 'timestamp' ) ) . ' ago' )
			);
		}

		return '<em>' . esc_html__( 'Never', 'notion-sync' ) . '</em>';
	}

	/**
	 * Render WordPress post column.
	 *
	 * @param array $item Database item.
	 * @return string Column content.
	 */
	public function column_wp_post( $item ): string {
		if ( ! empty( $item['wp_post_id'] ) ) {
			$row_count = (int) get_post_meta( $item['wp_post_id'], 'row_count', true );

			return sprintf(
				'<a href="%s">Post #%d</a><br><span class="description">%d rows</span>',
				esc_url(
					add_query_arg(
						array(
							'page'    => 'notion-sync-view-database',
							'post_id' => $item['wp_post_id'],
						),
						admin_url( 'admin.php' )
					)
				),
				$item['wp_post_id'],
				$row_count
			);
		}

		return '<em>' . esc_html__( 'Not synced yet', 'notion-sync' ) . '</em>';
	}

	/**
	 * Render actions column.
	 *
	 * @param array $item Database item.
	 * @return string Column content.
	 */
	public function column_actions( $item ): string {
		$has_synced = ! empty( $item['wp_post_id'] );

		$button_text = $has_synced
			? __( 'Re-sync', 'notion-sync' )
			: __( 'Sync Now', 'notion-sync' );

		return sprintf(
			'<button type="button" class="button button-small sync-database" data-database-id="%s">%s</button>',
			esc_attr( $item['id'] ?? '' ),
			esc_html( $button_text )
		);
	}

	/**
	 * Find WordPress post for a Notion database.
	 *
	 * @param string $notion_database_id Notion database ID.
	 * @return int|null WordPress post ID if found, null otherwise.
	 */
	private function find_database_post( string $notion_database_id ): ?int {
		$posts = get_posts(
			array(
				'post_type'      => 'notion_database',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'     => 'notion_database_id',
						'value'   => $notion_database_id,
						'compare' => '=',
					),
				),
				'fields'         => 'ids',
			)
		);

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Get entry count for a database.
	 *
	 * @param string $notion_database_id Notion database ID.
	 * @return int Entry count.
	 */
	private function get_database_entry_count( string $notion_database_id ): int {
		global $wpdb;

		$post_id = $this->find_database_post( $notion_database_id );

		if ( ! $post_id ) {
			return 0;
		}

		$table_name = $wpdb->prefix . 'notion_database_rows';

		// Check if table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return 0;
		}

		$count = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT COUNT(*) FROM {$table_name} WHERE database_post_id = %d",
				$post_id
			)
		);

		return (int) $count;
	}

	/**
	 * Get last synced time for a database.
	 *
	 * @param int|null $post_id WordPress post ID.
	 * @return string|null Last synced timestamp in MySQL format, null if not synced.
	 */
	private function get_last_synced_time( ?int $post_id ): ?string {
		if ( ! $post_id ) {
			return null;
		}

		$last_synced = get_post_meta( $post_id, 'last_synced', true );

		return ! empty( $last_synced ) ? $last_synced : null;
	}

	/**
	 * Display table or no items message.
	 */
	public function display(): void {
		if ( empty( $this->items ) ) {
			?>
			<div class="notice notice-info inline">
				<p>
					<?php esc_html_e( 'No databases found. Make sure you have shared databases with your Notion integration.', 'notion-sync' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		parent::display();
	}
}
