<?php
/**
 * Performance Logger - Tracks execution time and resource usage during sync operations.
 *
 * @package NotionSync
 * @since 1.0.0
 */

namespace NotionSync\Utils;

/**
 * Class PerformanceLogger
 *
 * Provides detailed performance profiling for sync operations.
 * Tracks execution time, memory usage, and API call counts.
 *
 * @since 1.0.0
 */
class PerformanceLogger {

	/**
	 * Active timers.
	 *
	 * @var array<string, array{start: float, memory_start: int}>
	 */
	private static $timers = array();

	/**
	 * Completed measurements.
	 *
	 * @var array<string, array{duration: float, memory_delta: int, count: int}>
	 */
	private static $measurements = array();

	/**
	 * Start a performance timer.
	 *
	 * @param string $label Timer label.
	 */
	public static function start( string $label ): void {
		self::$timers[ $label ] = array(
			'start'        => microtime( true ),
			'memory_start' => memory_get_usage( true ),
		);
	}

	/**
	 * Stop a performance timer and record measurement.
	 *
	 * @param string $label Timer label.
	 */
	public static function stop( string $label ): void {
		if ( ! isset( self::$timers[ $label ] ) ) {
			error_log( "PerformanceLogger: Attempted to stop non-existent timer: {$label}" );
			return;
		}

		$end_time   = microtime( true );
		$end_memory = memory_get_usage( true );

		$duration     = $end_time - self::$timers[ $label ]['start'];
		$memory_delta = $end_memory - self::$timers[ $label ]['memory_start'];

		// Initialize measurement if not exists.
		if ( ! isset( self::$measurements[ $label ] ) ) {
			self::$measurements[ $label ] = array(
				'duration'     => 0,
				'memory_delta' => 0,
				'count'        => 0,
			);
		}

		// Accumulate measurements.
		self::$measurements[ $label ]['duration']     += $duration;
		self::$measurements[ $label ]['memory_delta'] += $memory_delta;
		self::$measurements[ $label ]['count']++;

		// Remove timer.
		unset( self::$timers[ $label ] );

		// Log individual measurement.
		error_log(
			sprintf(
				'[PERF] %s: %.3fs, %s memory',
				$label,
				$duration,
				self::format_bytes( $memory_delta )
			)
		);
	}

	/**
	 * Log a measurement point without starting/stopping timer.
	 *
	 * @param string $label       Measurement label.
	 * @param float  $duration    Duration in seconds.
	 * @param int    $memory_used Memory used in bytes.
	 */
	public static function log( string $label, float $duration, int $memory_used = 0 ): void {
		// Initialize measurement if not exists.
		if ( ! isset( self::$measurements[ $label ] ) ) {
			self::$measurements[ $label ] = array(
				'duration'     => 0,
				'memory_delta' => 0,
				'count'        => 0,
			);
		}

		// Accumulate measurements.
		self::$measurements[ $label ]['duration']     += $duration;
		self::$measurements[ $label ]['memory_delta'] += $memory_used;
		self::$measurements[ $label ]['count']++;

		// Log measurement.
		error_log(
			sprintf(
				'[PERF] %s: %.3fs, %s memory',
				$label,
				$duration,
				self::format_bytes( $memory_used )
			)
		);
	}

	/**
	 * Get summary of all measurements.
	 *
	 * @return array<string, array{duration: float, memory_delta: int, count: int, avg_duration: float}>
	 */
	public static function get_summary(): array {
		$summary = array();

		foreach ( self::$measurements as $label => $data ) {
			$summary[ $label ] = array(
				'duration'     => $data['duration'],
				'memory_delta' => $data['memory_delta'],
				'count'        => $data['count'],
				'avg_duration' => $data['count'] > 0 ? $data['duration'] / $data['count'] : 0,
			);
		}

		return $summary;
	}

	/**
	 * Log summary of all measurements.
	 *
	 * @param string $context Context label for the summary.
	 */
	public static function log_summary( string $context = 'Sync Operation' ): void {
		$summary = self::get_summary();

		if ( empty( $summary ) ) {
			error_log( "[PERF SUMMARY] {$context}: No measurements recorded" );
			return;
		}

		error_log( "[PERF SUMMARY] {$context}:" );
		error_log( str_repeat( '=', 80 ) );

		// Sort by total duration descending.
		uasort(
			$summary,
			function ( $a, $b ) {
				return $b['duration'] <=> $a['duration'];
			}
		);

		foreach ( $summary as $label => $data ) {
			error_log(
				sprintf(
					'  %-50s | Total: %.3fs | Avg: %.3fs | Calls: %d | Memory: %s',
					$label,
					$data['duration'],
					$data['avg_duration'],
					$data['count'],
					self::format_bytes( $data['memory_delta'] )
				)
			);
		}

		error_log( str_repeat( '=', 80 ) );

		// Calculate totals.
		$total_duration = array_sum( array_column( $summary, 'duration' ) );
		$total_memory   = array_sum( array_column( $summary, 'memory_delta' ) );

		error_log(
			sprintf(
				'  TOTAL: %.3fs | Memory: %s',
				$total_duration,
				self::format_bytes( $total_memory )
			)
		);
		error_log( str_repeat( '=', 80 ) );
	}

	/**
	 * Reset all measurements.
	 */
	public static function reset(): void {
		self::$timers       = array();
		self::$measurements = array();
	}

	/**
	 * Format bytes to human-readable format.
	 *
	 * @param int $bytes Number of bytes.
	 * @return string Formatted string.
	 */
	private static function format_bytes( int $bytes ): string {
		if ( $bytes < 0 ) {
			return '-' . self::format_bytes( abs( $bytes ) );
		}

		$units = array( 'B', 'KB', 'MB', 'GB' );
		$pow   = $bytes > 0 ? floor( log( $bytes, 1024 ) ) : 0;
		$pow   = min( $pow, count( $units ) - 1 );

		$bytes /= ( 1024 ** $pow );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
}
