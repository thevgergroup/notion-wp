<?php
/**
 * Server-side render template for notion-wp/database-view block.
 *
 * Available variables:
 *
 * @var int    $database_id       The selected database post ID.
 * @var string $view_type         The view type (table, board, gallery, timeline, calendar).
 * @var bool   $show_filters      Whether to show filter controls.
 * @var bool   $show_export       Whether to show export button.
 * @var array  $wrapper_classes   Array of CSS classes for the wrapper.
 * @var array  $data_attributes   Array of data attributes for the wrapper.
 * @var WP_Post $database_post    The database post object.
 *
 * @package NotionWP\Blocks
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>"
<?php
// Data attributes output - values are already escaped in the PHP render function.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
echo implode(
	' ',
	array_map(
		function ( $key, $value ) {
			return $key . '="' . $value . '"';
		},
		array_keys( $data_attributes ),
		$data_attributes
	)
);
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
?>
>

	<div class="notion-wp-database-view__header">
		<h3 class="notion-wp-database-view__title">
			<?php echo esc_html( $database_post->post_title ); ?>
		</h3>

		<?php if ( $show_export ) : ?>
			<button class="notion-wp-database-view__export-btn" type="button">
				<?php esc_html_e( 'Export CSV', 'vger-sync-for-notion' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( $show_filters ) : ?>
		<div class="notion-wp-database-view__filters">
			<!-- Filters will be rendered by JavaScript -->
		</div>
	<?php endif; ?>

	<div class="notion-wp-database-view__content">
		<?php if ( 'table' === $view_type ) : ?>
			<!-- Tabulator table container - initialized by JavaScript -->
			<div id="notion-wp-database-<?php echo esc_attr( $database_id ); ?>"
				class="notion-wp-database-view__table"></div>
		<?php else : ?>
			<!-- Placeholder for future view types -->
			<div class="notion-wp-database-view__placeholder">
				<p>
					<?php
					printf(
						/* translators: %s: view type name */
						esc_html__( '%s view coming soon!', 'vger-sync-for-notion' ),
						esc_html( ucfirst( $view_type ) )
					);
					?>
				</p>
			</div>
		<?php endif; ?>
	</div>

	<div class="notion-wp-database-view__loading" style="display: none;">
		<span class="spinner is-active"></span>
		<span><?php esc_html_e( 'Loading database...', 'vger-sync-for-notion' ); ?></span>
	</div>

	<div class="notion-wp-database-view__error" style="display: none;">
		<p><?php esc_html_e( 'Error loading database. Please try again.', 'vger-sync-for-notion' ); ?></p>
	</div>
</div>
