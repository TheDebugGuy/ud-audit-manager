<?php
/**
 * WP-Cron Scanner Scheduler class.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */

namespace UDAuditManager\Includes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Scheduler
 *
 * Registers schedules and listens to triggers to run the auditor in the background
 * via standard WordPress cron functions.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class Scheduler {

	/**
	 * Hook event name.
	 *
	 * @var string
	 */
	private const CRON_HOOK = 'udam_toolkit_cron_scan';

	/**
	 * Constructor. Registers hooks.
	 */
	public function __construct() {
		// Listen to settings updates to reschedule cron jobs dynamically.
		add_action( 'udam_scan_completed', [ $this, 'maybe_reschedule_scans' ] );
		add_action( 'update_option_udam_toolkit_settings', [ $this, 'maybe_reschedule_scans' ] );
		add_action( 'add_option_udam_toolkit_settings', [ $this, 'maybe_reschedule_scans' ] );

		// Hook the background runner.
		add_action( self::CRON_HOOK, [ $this, 'run_background_scan' ] );

		// Hook custom intervals.
		add_filter( 'cron_schedules', [ $this, 'register_cron_intervals' ] );
	}

	/**
	 * Registers weekly and monthly schedules if missing in WP Core.
	 *
	 * @param array $schedules Current registered schedules.
	 * @return array Modified schedules list.
	 */
	public function register_cron_intervals( array $schedules ) : array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = [
				'interval' => 604800,
				'display'  => __( 'Once Weekly', 'ud-audit-manager' ),
			];
		}
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = [
				'interval' => 2635200,
				'display'  => __( 'Once Monthly', 'ud-audit-manager' ),
			];
		}
		return $schedules;
	}

	/**
	 * Synchronize scheduled cron event based on user configuration.
	 *
	 * @return void
	 */
	public function maybe_reschedule_scans() : void {
		$settings = Container::instance()->get( 'settings' );
		if ( ! $settings instanceof Settings ) {
			return;
		}

		$frequency = $settings->get( 'cron_frequency', 'disabled' );
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( 'disabled' === $frequency ) {
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK );
			}
			return;
		}

		// If scheduled but frequency changed, unschedule and recreate.
		if ( $timestamp ) {
			$schedule = wp_get_schedule( self::CRON_HOOK );
			if ( $schedule !== $frequency ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK );
				$timestamp = false;
			}
		}

		if ( ! $timestamp ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $frequency, self::CRON_HOOK );
		}
	}

	/**
	 * Performs a full audit scan sequentially in the background.
	 *
	 * @return void
	 */
	public function run_background_scan() : void {
		$engine = Container::instance()->get( 'engine' );
		$logger = Container::instance()->get( 'logger' );

		if ( ! $engine instanceof Engine ) {
			return;
		}

		if ( $logger instanceof Logger ) {
			$logger->log( 'info', __( 'Scheduled background scan cron task triggered.', 'ud-audit-manager' ) );
		}

		// Start scan.
		$run = $engine->start_scan( 'full', 'scheduled' );
		if ( is_wp_error( $run ) ) {
			$err_msg = $run->get_error_message();
			if ( $logger instanceof Logger ) {
				$logger->log( 'error', __( 'Scheduled background scan failed to initialize.', 'ud-audit-manager' ), [ 'error' => $err_msg ] );
			}
			update_option( 'udam_last_cron_error', [
				'time'  => current_time( 'mysql' ),
				/* translators: %s: Error message details */
				'error' => sprintf( __( 'Failed to initialize: %s', 'ud-audit-manager' ), $err_msg ),
			] );
			return;
		}

		$run_id  = (int) $run['run_id'];
		$modules = $run['modules'];

		// Extend execution limit for the cron process.
		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Extending execution limit is required for background scans to complete safely.
			@set_time_limit( 600 );
		}

		// Run modules step-by-step.
		foreach ( $modules as $module ) {
			$offset = 0;
			do {
				$result = $engine->scan_step( $run_id, $module, $offset );
				if ( is_wp_error( $result ) ) {
					$err_msg = $result->get_error_message();
					if ( $logger instanceof Logger ) {
						/* translators: %s: Module slug name */
						$logger->log( 'error', sprintf( __( 'Scheduled scan step failed on module %s.', 'ud-audit-manager' ), $module ), [ 'error' => $err_msg ] );
					}

					// Update database status to failed.
					$db = Container::instance()->get( 'db' );
					if ( $db ) {
						$db->save_run( [
							'id'     => $run_id,
							'status' => 'failed',
							'stats'  => $err_msg,
						] );
					}

					update_option( 'udam_last_cron_error', [
						'time'  => current_time( 'mysql' ),
						/* translators: 1: Module name, 2: Error message */
						'error' => sprintf( __( 'Module %1$s failed: %2$s', 'ud-audit-manager' ), strtoupper( $module ), $err_msg ),
					] );
					return;
				}
				$offset = isset( $result['offset'] ) ? (int) $result['offset'] : 0;
			} while ( isset( $result['completed'] ) && ! $result['completed'] );
		}

		// Complete scan.
		$engine->complete_scan( $run_id );
		delete_option( 'udam_last_cron_error' );
	}
}
