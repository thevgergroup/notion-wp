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
			'title'       => __( 'Database Title', 'notion-wp' ),
			'notion_id'   => __( 'Notion ID', 'notion-wp' ),
			'entries'     => __( 'Entries', 'notion-wp' ),
			'last_synced' => __( 'Last Synced', 'notion-wp' ),
			'wp_post'     => __( 'WordPress Post', 'notion-wp' ),
			'actions'     => __( 'Actions', 'notion-wp' ),
		);
	}

	/**
	 * Prepare items for display.
	 *
	 * Fetches databases from Notion API and prepares for table display.
	 */
	public function prepare_items(): void {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		try {
			$databases = $this->fetcher->get_databases();

			// Enhance each database with WordPress sync status.
			foreach ( $databases as &$database ) {
				$database['wp_post_id']  = $this->find_database_post( $database['id'] );
				$database['entry_count'] = $this->get_database_entry_count( $database['id'] );
				$database['last_synced'] = $this->get_last_synced_time( $database['wp_post_id'] );
			}

			$this->items = $databases;

		} catch ( \Exception $e ) {
			$this->items = array();
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for development.
			error_log( 'NotionSync: Failed to fetch databases: ' . $e->getMessage() );
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
			esc_html( $item['title'] ?? __( 'Untitled', 'notion-wp' ) )
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
				esc_html__( 'View', 'notion-wp' )
			);
		}

		// Add "Open in Notion" link if URL is available.
		if ( ! empty( $item['url'] ) ) {
			$actions['notion'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $item['url'] ),
				esc_html__( 'Open in Notion', 'notion-wp' )
			);
		}

		return $title . $this->row_actions( $actions );
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

		return '<em>' . esc_html__( 'Unknown', 'notion-wp' ) . '</em>';
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

		return '<em>' . esc_html__( 'Never', 'notion-wp' ) . '</em>';
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

		return '<em>' . esc_html__( 'Not synced yet', 'notion-wp' ) . '</em>';
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
			? __( 'Re-sync', 'notion-wp' )
			: __( 'Sync Now', 'notion-wp' );

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
					<?php esc_html_e( 'No databases found. Make sure you have shared databases with your Notion integration.', 'notion-wp' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		parent::display();
	}
}
