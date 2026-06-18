<?php
/**
 * REST API Endpoints Manager.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */

namespace UDAuditManager\Includes;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class REST_API
 *
 * Exposes securely authenticated endpoint routes for scanning, fetching runs history,
 * settings, auto-fixes, and log operations.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class REST_API {

	/**
	 * Constructor. Hook routes registration.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() : void {
		$namespace       = 'ud-audit-manager/v1';
		$setup_completed = (bool) get_option( 'udam_toolkit_setup_completed', false );

		// 1. Settings Endpoints (always available for wizard configuration).
		register_rest_route(
			$namespace,
			'/settings',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'check_permissions' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'check_permissions' ],
				],
			]
		);

		// All other endpoints are registered ONLY after onboarding setup is completed.
		if ( $setup_completed ) {
			// Scan Management Endpoints.
			register_rest_route(
				$namespace,
				'/scan/start',
				[
					[
						'methods'             => 'POST',
						'callback'            => [ $this, 'start_scan' ],
						'permission_callback' => [ $this, 'check_permissions' ],
					],
				]
			);

			register_rest_route(
				$namespace,
				'/scan/step',
				[
					[
						'methods'             => 'POST',
						'callback'            => [ $this, 'scan_step' ],
						'permission_callback' => [ $this, 'check_permissions' ],
					],
				]
			);

			register_rest_route(
				$namespace,
				'/scan/complete',
				[
					[
						'methods'             => 'POST',
						'callback'            => [ $this, 'complete_scan' ],
						'permission_callback' => [ $this, 'check_permissions' ],
					],
				]
			);

			// Data Endpoints.
			register_rest_route(
				$namespace,
				'/dashboard/stats',
				[
					[
						'methods'             => 'GET',
						'callback'            => [ $this, 'get_dashboard_stats' ],
						'permission_callback' => [ $this, 'check_permissions' ],
					],
				]
			);

			register_rest_route(
				$namespace,
				'/runs',
				[
					[
						'methods'             => 'GET',
						'callback'            => [ $this, 'get_runs' ],
						'permission_callback' => [ $this, 'check_permissions' ],
					],
				]
			);

			register_rest_route(
				$namespace,
				'/runs/(?P<id>\d+)',
				[
					[
						'methods'             => 'GET',
						'callback'            => [ $this, 'get_run_details' ],
						'permission_callback' => [ $this, 'check_permissions' ],
					],
				]
			);

			register_rest_route(
				$namespace,
				'/findings/fix',
				[
					[
						'methods'             => 'POST',
						'callback'            => [ $this, 'apply_fix' ],
						'permission_callback' => [ $this, 'check_permissions' ],
					],
				]
			);

			register_rest_route(
				$namespace,
				'/settings/cleanup',
				[
					[
						'methods'             => 'POST',
						'callback'            => [ $this, 'cleanup_data' ],
						'permission_callback' => [ $this, 'check_permissions' ],
					],
				]
			);

			// Logger Endpoints.
			register_rest_route(
				$namespace,
				'/logs',
				[
					[
						'methods'             => 'GET',
						'callback'            => [ $this, 'get_logs' ],
						'permission_callback' => [ $this, 'check_permissions' ],
					],
				]
			);

			register_rest_route(
				$namespace,
				'/logs/clear',
				[
					[
						'methods'             => 'POST',
						'callback'            => [ $this, 'clear_logs' ],
						'permission_callback' => [ $this, 'check_permissions' ],
					],
				]
			);

			// Scheduler Test Endpoint.
			register_rest_route(
				$namespace,
				'/scheduler/run_test',
				[
					[
						'methods'             => 'POST',
						'callback'            => [ $this, 'run_scheduler_test' ],
						'permission_callback' => [ $this, 'check_permissions' ],
					],
				]
			);
		}
	}

	/**
	 * Verify permissions (only administrators with manage_options capability).
	 *
	 * @return bool True if permitted, false otherwise.
	 */
	public function check_permissions() : bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * REST Callback: Start a scan run.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or WP_Error on failure.
	 */
	public function start_scan( WP_REST_Request $request ) {
		$type   = sanitize_text_field( $request->get_param( 'type' ) ?: 'full' );
		$source = sanitize_text_field( $request->get_param( 'source' ) ?: 'manual' );
		$engine = Container::instance()->get( 'engine' );

		if ( ! $engine ) {
			return new WP_Error( 'engine_missing', __( 'Scan engine is missing.', 'ud-audit-manager' ), [ 'status' => 500 ] );
		}

		$run = $engine->start_scan( $type, $source );

		if ( is_wp_error( $run ) ) {
			return $run;
		}

		return new WP_REST_Response( $run, 200 );
	}

	/**
	 * REST Callback: Perform a batch scan step.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or WP_Error on failure.
	 */
	public function scan_step( WP_REST_Request $request ) {
		$run_id = (int) $request->get_param( 'run_id' );
		$module = sanitize_text_field( $request->get_param( 'module' ) );
		$offset = (int) $request->get_param( 'offset' ) ?: 0;

		$engine = Container::instance()->get( 'engine' );
		if ( ! $engine ) {
			return new WP_Error( 'engine_missing', __( 'Scan engine is missing.', 'ud-audit-manager' ), [ 'status' => 500 ] );
		}

		$result = $engine->scan_step( $run_id, $module, $offset );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * REST Callback: Finalize scan run.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or WP_Error on failure.
	 */
	public function complete_scan( WP_REST_Request $request ) {
		$run_id = (int) $request->get_param( 'run_id' );

		$engine = Container::instance()->get( 'engine' );
		if ( ! $engine ) {
			return new WP_Error( 'engine_missing', __( 'Scan engine is missing.', 'ud-audit-manager' ), [ 'status' => 500 ] );
		}

		$result = $engine->complete_scan( $run_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * REST Callback: Fetch dashboard metadata.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_dashboard_stats( WP_REST_Request $request ) {
		$db       = Container::instance()->get( 'db' );
		$p_center = Container::instance()->get( 'priority_fix' );

		$latest_run   = $db->get_latest_completed_run();
		$running_scan = $db->get_active_run();
		$history      = $db->get_runs_history( 10 );

		$stats = [
			'latest_run'     => $latest_run,
			'running_scan'   => $running_scan,
			'runs_history'   => array_reverse( $history ),
			'priority_fixes' => [],
			'module_issues'  => [],
		];

		if ( $latest_run ) {
			global $wpdb;
			$findings_table = esc_sql( $db->get_table_name( 'findings' ) );
			$summary = $wpdb->get_results( $wpdb->prepare(
				/**
				 * Direct database query required.
				 *
				 * Audit results and statistics must be fetched in real-time.
				 * Cached values may return stale scan information.
				 *
				 * All dynamic values are sanitized and passed through
				 * $wpdb->prepare() before execution.
				 */
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is required for real-time audit accuracy; caching is bypassed to prevent stale scan results.
				"SELECT module, COUNT(*) as count FROM {$findings_table} WHERE run_id = %d AND status = 'open' GROUP BY module",
				$latest_run->id
			) );

			$modules_manager = Container::instance()->get( 'modules_manager' );
			$active_modules  = $modules_manager instanceof Module_Manager ? array_keys( $modules_manager->get_enabled_modules() ) : [];
			$module_issues   = array_fill_keys( $active_modules, 0 );
			foreach ( $summary as $row ) {
				if ( isset( $module_issues[ $row->module ] ) ) {
					$module_issues[ $row->module ] = (int) $row->count;
				}
			}
			$stats['module_issues'] = $module_issues;

			if ( $p_center ) {
				$stats['priority_fixes'] = $p_center->get_highest_impact_fixes( $latest_run->id, 5 );
			}
		}

		// Compile scheduler and setup health data.
		$cron_freq        = Container::instance()->get( 'settings' )->get( 'cron_frequency', 'disabled' );
		$next_scheduled   = 'N/A';
		$scheduler_status = 'disabled';

		if ( 'disabled' !== $cron_freq ) {
			$timestamp = wp_next_scheduled( 'udam_toolkit_cron_scan' );
			if ( $timestamp ) {
				$next_scheduled = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
				if ( time() > $timestamp ) {
					$scheduler_status = 'delayed';
				} else {
					$scheduler_status = 'active';
				}
			}
		}

		$modules_manager = Container::instance()->get( 'modules_manager' );
		$active_count    = 0;
		$disabled_count  = 0;
		if ( $modules_manager ) {
			$active_count   = count( $modules_manager->get_enabled_modules() );
			$disabled_count = count( $modules_manager->get_discovered_modules() ) - $active_count;
		}

		$last_cron_error     = get_option( 'udam_last_cron_error', '' );
		$last_cron_error_str = '';
		if ( is_array( $last_cron_error ) && isset( $last_cron_error['error'] ) ) {
			$last_cron_error_str = $last_cron_error['error'] . ' (' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_cron_error['time'] ) ) . ')';
		}

		$last_run = 'N/A';
		if ( $latest_run ) {
			$last_run = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $latest_run->completed_at ) );
		}

		$stats['scheduler'] = [
			'setup_completed'  => true,
			'status'           => $scheduler_status,
			'next_run'         => $next_scheduled,
			'last_run'         => $last_run,
			'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'active_modules'   => $active_count,
			'disabled_modules' => $disabled_count,
			'last_cron_error'  => $last_cron_error_str,
		];

		return new WP_REST_Response( $stats, 200 );
	}

	/**
	 * REST Callback: Get runs history list.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_runs( WP_REST_Request $request ) {
		$db      = Container::instance()->get( 'db' );
		$filters = [
			'source' => sanitize_text_field( $request->get_param( 'source' ) ),
			'status' => sanitize_text_field( $request->get_param( 'status' ) ),
		];
		$filters = array_filter( $filters );
		$history = $db->get_runs_history_filtered( 30, $filters );

		foreach ( $history as $run ) {
			$run->duration = 0;
			if ( ! empty( $run->completed_at ) && ! empty( $run->started_at ) ) {
				$run->duration = strtotime( $run->completed_at ) - strtotime( $run->started_at );
			}
			$run->export_nonce = wp_create_nonce( 'udam_export_report_' . $run->id );
		}

		return new WP_REST_Response( $history, 200 );
	}

	/**
	 * REST Callback: Fetch single run and its findings.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or WP_Error on failure.
	 */
	public function get_run_details( WP_REST_Request $request ) {
		$run_id = (int) $request->get_param( 'id' );
		$db     = Container::instance()->get( 'db' );

		$run = $db->get_run( $run_id );
		if ( ! $run ) {
			return new WP_Error( 'not_found', __( 'Audit run not found.', 'ud-audit-manager' ), [ 'status' => 404 ] );
		}

		$run->duration = 0;
		if ( ! empty( $run->completed_at ) && ! empty( $run->started_at ) ) {
			$run->duration = strtotime( $run->completed_at ) - strtotime( $run->started_at );
		}
		$run->export_nonce = wp_create_nonce( 'udam_export_report_' . $run->id );

		$filters = [
			'module'   => sanitize_text_field( $request->get_param( 'module' ) ),
			'severity' => sanitize_text_field( $request->get_param( 'severity' ) ),
			'status'   => sanitize_text_field( $request->get_param( 'status' ) ),
			'search'   => sanitize_text_field( $request->get_param( 'search' ) ),
		];

		$findings = $db->get_findings( $run_id, $filters );
		
		// Deprecated: Snapshot feature has been removed. Returned empty array for backward compatibility.
		$snapshot = [];

		return new WP_REST_Response(
			[
				'run'      => $run,
				'findings' => $findings,
				'snapshot' => $snapshot,
			],
			200
		);
	}

	/**
	 * REST Callback: Apply auto-fix.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or WP_Error on failure.
	 */
	public function apply_fix( WP_REST_Request $request ) {
		$finding_id  = (int) $request->get_param( 'finding_id' );
		$fix_manager = Container::instance()->get( 'fix_manager' );

		if ( ! $fix_manager ) {
			return new WP_Error( 'system_missing', __( 'Fix manager is missing.', 'ud-audit-manager' ), [ 'status' => 500 ] );
		}

		$result = $fix_manager->apply_fix( $finding_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * REST Callback: Get Settings.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_settings( WP_REST_Request $request ) {
		$settings = Container::instance()->get( 'settings' );
		if ( ! $settings ) {
			return new WP_Error( 'settings_missing', __( 'Settings service missing.', 'ud-audit-manager' ), [ 'status' => 500 ] );
		}
		return new WP_REST_Response( $settings->get_all(), 200 );
	}

	/**
	 * REST Callback: Save Settings.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or WP_Error on failure.
	 */
	public function update_settings( WP_REST_Request $request ) {
		$params   = $request->get_json_params();
		$settings = Container::instance()->get( 'settings' );

		if ( ! $settings ) {
			return new WP_Error( 'settings_missing', __( 'Settings service missing.', 'ud-audit-manager' ), [ 'status' => 500 ] );
		}

		$settings->update_all( $params );
		update_option( 'udam_toolkit_setup_completed', true );

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * REST Callback: Clear audit/log files.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or WP_Error on failure.
	 */
	public function cleanup_data( WP_REST_Request $request ) {
		$type = sanitize_text_field( $request->get_param( 'type' ) );
		$db   = Container::instance()->get( 'db' );
		$logs = Container::instance()->get( 'logger' );

		switch ( $type ) {
			case 'history':
				$db->clear_audit_history();
				break;
			case 'logs':
				$logs->clear_logs();
				break;
			case 'all':
				$db->clear_audit_history();
				$logs->clear_logs();
				$settings = Container::instance()->get( 'settings' );
				if ( $settings ) {
					$settings->clear_settings();
					$settings->set_defaults();
				}
				break;
			default:
				return new WP_Error( 'invalid_type', __( 'Invalid cleanup type specified.', 'ud-audit-manager' ), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * REST Callback: View logs content.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_logs( WP_REST_Request $request ) {
		$logger = Container::instance()->get( 'logger' );
		return new WP_REST_Response( [ 'logs' => $logger->get_log_contents() ], 200 );
	}

	/**
	 * REST Callback: Clear logs content.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function clear_logs( WP_REST_Request $request ) {
		$logger = Container::instance()->get( 'logger' );
		$logger->clear_logs();
		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * REST Callback: Run scheduler background scan instantly.
	 *
	 * @since 1.0.1
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object or WP_Error on failure.
	 */
	public function run_scheduler_test( WP_REST_Request $request ) {
		$scheduler = Container::instance()->get( 'scheduler' );
		if ( ! $scheduler ) {
			return new WP_Error( 'scheduler_missing', __( 'Scheduler service missing.', 'ud-audit-manager' ), [ 'status' => 500 ] );
		}
		$scheduler->run_background_scan();
		return new WP_REST_Response( [ 'success' => true ], 200 );
	}
}
