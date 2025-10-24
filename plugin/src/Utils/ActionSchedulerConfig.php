<?php
/**
 * Action Scheduler Configuration
 *
 * Configures Action Scheduler for improved reliability and performance.
 * Implements mitigation strategies for async request failures and timeouts.
 *
 * @package NotionSync\Utils
 * @since 0.3.1
 */

namespace NotionSync\Utils;

/**
 * Class ActionSchedulerConfig
 *
 * Handles Action Scheduler configuration and reliability improvements.
 *
 * @since 0.3.1
 */
class ActionSchedulerConfig {

	/**
	 * Register all Action Scheduler filters and hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		// Force WP Cron runner instead of async requests for reliability.
		add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );

		// Increase timeout period for long-running actions (image downloads).
		add_filter( 'action_scheduler_timeout_period', [ __CLASS__, 'increase_timeout_period' ] );

		// Auto-retry failed page sync actions.
		add_action( 'action_scheduler_failed_action', [ __CLASS__, 'auto_retry_failed_sync' ], 10, 1 );

		// Add custom cleanup for old retry transients.
		add_action( 'action_scheduler_completed_action', [ __CLASS__, 'cleanup_retry_transient' ], 10, 1 );
	}

	/**
	 * Increase timeout period for Action Scheduler.
	 *
	 * Default is 300 seconds (5 minutes). We increase to 600 seconds (10 minutes)
	 * to accommodate image-heavy pages with multiple downloads.
	 *
	 * @return int Timeout period in seconds.
	 */
	public static function increase_timeout_period(): int {
		return 600; // 10 minutes.
	}

	/**
	 * Auto-retry failed page sync actions.
	 *
	 * When a page sync fails (e.g., due to Action Scheduler timeout), this automatically
	 * reschedules it once with a 1-minute delay. Uses transients to prevent infinite retries.
	 *
	 * @param int $action_id Failed action ID.
	 * @return void
	 */
	public static function auto_retry_failed_sync( int $action_id ): void {
		// Ensure Action Scheduler is loaded.
		if ( ! function_exists( 'as_schedule_single_action' ) || ! class_exists( 'ActionScheduler' ) ) {
			return;
		}

		try {
			$store  = \ActionScheduler::store();
			$action = $store->fetch_action( $action_id );

			// Only retry page sync actions.
			if ( 'notion_sync_process_page_batch' !== $action->get_hook() ) {
				return;
			}

			// Check if we've already retried this action.
			$retry_key = "notion_retry_{$action_id}";
			if ( get_transient( $retry_key ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
				error_log( "ActionSchedulerConfig: Action {$action_id} already retried, skipping" );
				return;
			}

			// Mark as retried (expires in 1 hour to prevent accidental re-runs).
			set_transient( $retry_key, true, HOUR_IN_SECONDS );

			// Get original arguments.
			$args = $action->get_args();

			// Schedule retry with 1-minute delay.
			as_schedule_single_action(
				time() + MINUTE_IN_SECONDS,
				$action->get_hook(),
				$args,
				'notion-sync'
			);

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log(
				sprintf(
					'ActionSchedulerConfig: Retrying failed action %d (page %s) in 1 minute',
					$action_id,
					$args[1] ?? 'unknown'
				)
			);
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging.
			error_log( 'ActionSchedulerConfig: Error in auto_retry_failed_sync: ' . $e->getMessage() );
		}
	}

	/**
	 * Cleanup retry transient when action completes successfully.
	 *
	 * Removes the retry marker when an action completes successfully,
	 * allowing future retries if needed.
	 *
	 * @param int $action_id Completed action ID.
	 * @return void
	 */
	public static function cleanup_retry_transient( int $action_id ): void {
		$retry_key = "notion_retry_{$action_id}";
		delete_transient( $retry_key );
	}

	/**
	 * Check if Action Scheduler is using async request runner.
	 *
	 * @return bool True if using async requests, false if using WP Cron.
	 */
	public static function is_using_async_runner(): bool {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Action Scheduler hook.
		return apply_filters( 'action_scheduler_allow_async_request_runner', true );
	}

	/**
	 * Get current timeout period.
	 *
	 * @return int Timeout period in seconds.
	 */
	public static function get_timeout_period(): int {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Action Scheduler hook.
		return apply_filters( 'action_scheduler_timeout_period', 300 );
	}

	/**
	 * Get configuration status for debugging.
	 *
	 * @return array Configuration details.
	 */
	public static function get_config_status(): array {
		return [
			'async_runner_enabled' => self::is_using_async_runner(),
			'timeout_period'       => self::get_timeout_period(),
			'action_scheduler_version' => defined( 'ACTION_SCHEDULER_VERSION' ) ? ACTION_SCHEDULER_VERSION : 'unknown',
		];
	}
}
