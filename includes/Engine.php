<?php
/**
 * Scan Engine coordinator class.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */

namespace UDAuditManager\Includes;

use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Engine
 *
 * Coordinates scan cycles by scheduling modules, executing batch steps, persisting
 * findings, calculating overall health indexes, and firing notifications.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class Engine {

	/**
	 * Map of module slugs to class names.
	 *
	 * @var array
	 */
	private array $module_classes = [
		'seo'           => 'UDAuditManager\Modules\SEO_Module',
		'performance'   => 'UDAuditManager\Modules\Performance_Module',
		'accessibility' => 'UDAuditManager\Modules\Accessibility_Module',
		'security'      => 'UDAuditManager\Modules\Security_Module',
		'database'      => 'UDAuditManager\Modules\Database_Module',
		'content'       => 'UDAuditManager\Modules\Content_Module',
		'plugin'        => 'UDAuditManager\Modules\Plugin_Module',
		'theme'         => 'UDAuditManager\Modules\Theme_Module',
	];

	/**
	 * Retrieve all registered module classes, filtered dynamically.
	 *
	 * @since 1.0.1
	 * @return array Map of module slugs to class names.
	 */
	public function get_module_classes() : array {
		$manager = Container::instance()->get( 'modules_manager' );
		$modules = $manager instanceof Module_Manager ? $manager->get_enabled_modules() : [];
		/**
		 * Filters the list of registered module classes.
		 *
		 * @since 1.0.1
		 * @param array $module_classes Map of module slugs to class names.
		 */
		return apply_filters( 'udam_module_classes', $modules );
	}

	/**
	 * Initiates a new scan run record in the database.
	 *
	 * @param string $type   Scan type (full, or specific module slug).
	 * @param string $source Scan source.
	 * @return array|WP_Error Run metadata or error.
	 */
	public function start_scan( string $type = 'full', string $source = 'manual' ) {
		$db       = Container::instance()->get( 'db' );
		$settings = Container::instance()->get( 'settings' );
		$logger   = Container::instance()->get( 'logger' );

		if ( ! $db || ! $settings ) {
			return new WP_Error( 'system_missing', __( 'Core systems are not initialized.', 'ud-audit-manager' ) );
		}

		// Check if a scan is already running. If so, return it so the client can resume.
		$active = $db->get_active_run();
		if ( $active ) {
			if ( $logger ) {
				/* translators: %d: Active scan run ID */
				$logger->log( 'info', sprintf( __( 'Found active scan run ID %d. Resuming scan.', 'ud-audit-manager' ), $active->id ) );
			}
			return [
				'run_id'  => (int) $active->id,
				'modules' => $this->get_modules_to_scan( $active->type ),
				'resumed' => true,
			];
		}

		$modules = $this->get_modules_to_scan( $type );
		if ( empty( $modules ) ) {
			return new WP_Error( 'no_modules', __( 'No modules are enabled for this scan type.', 'ud-audit-manager' ) );
		}

		$run_id = $db->save_run( [
			'status'         => 'running',
			'type'           => $type,
			'source'         => $source,
			'score'          => 0,
			'current_module' => $modules[0],
			'current_offset' => 0,
			'started_at'     => current_time( 'mysql' ),
		] );

		if ( ! $run_id ) {
			return new WP_Error( 'db_write_error', __( 'Failed to initialize scan run in the database.', 'ud-audit-manager' ) );
		}

		// Inherit findings for modules not being scanned if partial scan.
		if ( 'full' !== $type ) {
			$latest_completed = $db->get_latest_completed_run();
			if ( $latest_completed ) {
				$all_enabled = $this->get_modules_to_scan( 'full' );
				$scanned     = $this->get_modules_to_scan( $type );
				$to_copy     = array_diff( $all_enabled, $scanned );

				if ( ! empty( $to_copy ) ) {
					$prev_findings = $db->get_findings( $latest_completed->id );
					foreach ( $prev_findings as $finding ) {
						if ( in_array( $finding->module, $to_copy, true ) ) {
							$db->save_finding( [
								'run_id'           => $run_id,
								'module'           => $finding->module,
								'issue_key'        => $finding->issue_key,
								'title'            => $finding->title,
								'severity'         => $finding->severity,
								'description'      => $finding->description,
								'why_it_matters'   => $finding->why_it_matters,
								'how_to_fix'       => $finding->how_to_fix,
								'suggested_action' => $finding->suggested_action,
								'location'         => $finding->location,
								'status'           => $finding->status,
								'is_fixable'       => $finding->is_fixable,
							] );
						}
					}
					if ( $logger ) {
						/* translators: 1: Run ID, 2: Module list */
						$logger->log( 'info', sprintf( __( 'Inherited findings from run ID %1$d for modules: %2$s.', 'ud-audit-manager' ), $latest_completed->id, implode( ', ', $to_copy ) ) );
					}
				}
			}
		}

		if ( $logger ) {
			/* translators: 1: Run ID, 2: Scan type */
			$logger->log( 'info', sprintf( __( 'Started scan run ID %1$d, type: %2$s.', 'ud-audit-manager' ), $run_id, $type ) );
		}

		do_action( 'udam_scan_started', $run_id, $type );

		return [
			'run_id'  => $run_id,
			'modules' => $modules,
			'resumed' => false,
		];
	}

	/**
	 * Run a batch step of an active module.
	 *
	 * @param int    $run_id The scan run ID.
	 * @param string $module Module slug.
	 * @param int    $offset Batch items offset.
	 * @return array|WP_Error Scan step result details or error.
	 */
	public function scan_step( int $run_id, string $module, int $offset = 0 ) {
		$db       = Container::instance()->get( 'db' );
		$settings = Container::instance()->get( 'settings' );
		$logger   = Container::instance()->get( 'logger' );

		if ( ! $db || ! $settings ) {
			return new WP_Error( 'system_missing', __( 'Core systems are not initialized.', 'ud-audit-manager' ) );
		}

		$run = $db->get_run( $run_id );
		if ( ! $run || 'running' !== $run->status ) {
			return new WP_Error( 'invalid_run', __( 'Audit run is not active or missing.', 'ud-audit-manager' ) );
		}

		if ( ! isset( $this->get_module_classes()[ $module ] ) ) {
			return new WP_Error( 'invalid_module', __( 'Module is not registered.', 'ud-audit-manager' ) );
		}

		$class = $this->get_module_classes()[ $module ];
		if ( ! class_exists( $class ) ) {
			/* translators: %s: Class name */
			return new WP_Error( 'class_missing', sprintf( __( 'Module class %s not found.', 'ud-audit-manager' ), $class ) );
		}

		/** @var Module_Base $module_instance */
		$module_instance = new $class();
		if ( ! $module_instance->is_enabled() ) {
			return [ 'completed' => true ];
		}

		// Clear previous findings for this module if offset is 0 (first step).
		if ( 0 === $offset ) {
			$db->delete_findings_for_module( $run_id, $module );
		}

		$limit  = (int) $settings->get( 'perf_limits_posts', 50 );
		$result = $module_instance->scan_batch( $run_id, $offset, $limit );

		// Persist execution tracking to support resuming.
		$db->save_run( [
			'id'             => $run_id,
			'current_module' => $module,
			'current_offset' => isset( $result['offset'] ) ? (int) $result['offset'] : 0,
		] );

		if ( $logger && isset( $result['completed'] ) ) {
			$logger->log( 'info', sprintf(
				/* translators: 1: Module name, 2: Offset, 3: Completed status */
				__( 'Scanned module: %1$s, offset: %2$d. Completed: %3$s.', 'ud-audit-manager' ),
				$module,
				$offset,
				$result['completed'] ? 'yes' : 'no'
			) );
		}

		return $result;
	}

	/**
	 * Finalizes the scan run and compiles scores.
	 *
	 * @param int $run_id The scan run ID.
	 * @return array|WP_Error Final run details or error.
	 */
	public function complete_scan( int $run_id ) {
		global $wpdb;

		$db             = Container::instance()->get( 'db' );
		$scoring_engine = Container::instance()->get( 'scoring' );
		$logger         = Container::instance()->get( 'logger' );
		$settings       = Container::instance()->get( 'settings' );

		if ( ! $db || ! $scoring_engine ) {
			return new WP_Error( 'system_missing', __( 'Core systems are not initialized.', 'ud-audit-manager' ) );
		}

		$run = $db->get_run( $run_id );
		if ( ! $run || 'running' !== $run->status ) {
			return new WP_Error( 'invalid_run', __( 'Audit run is not active or missing.', 'ud-audit-manager' ) );
		}

		// 1. Gather all findings (including newly scanned and copied ones).
		$findings = $db->get_findings( $run_id );

		// 2. Group findings by module and calculate module scores across all active modules.
		$all_enabled_modules = $this->get_modules_to_scan( 'full' );
		$module_scores       = [];
		$stats               = [
			'critical' => 0,
			'high'     => 0,
			'medium'   => 0,
			'low'      => 0,
			'info'     => 0,
		];

		// Calculate stats & module scores for all modules.
		foreach ( $all_enabled_modules as $module_slug ) {
			$module_findings = array_filter( $findings, function ( $finding ) use ( $module_slug ) {
				return $finding->module === $module_slug;
			} );

			$module_scores[ $module_slug ] = $scoring_engine->calculate_module_score( $module_findings );
		}

		foreach ( $findings as $finding ) {
			$sev = strtolower( $finding->severity );
			if ( isset( $stats[ $sev ] ) ) {
				$stats[ $sev ]++;
			}
		}

		// 3. Compute overall score.
		$overall_score = $scoring_engine->calculate_overall_score( $module_scores );

		// 4. Save final fields.
		$db->save_run( [
			'id'               => $run_id,
			'status'           => 'completed',
			'score'            => $overall_score,
			'scores_breakdown' => wp_json_encode( $module_scores ),
			'stats'            => wp_json_encode( $stats ),
			'current_module'   => null,
			'current_offset'   => 0,
			'completed_at'     => current_time( 'mysql' ),
		] );

		if ( $logger ) {
			/* translators: 1: Run ID, 2: Overall score */
			$logger->log( 'info', sprintf( __( 'Completed scan run ID %1$d. Overall Score: %2$d.', 'ud-audit-manager' ), $run_id, $overall_score ) );
		}

		// 6. Invalidate transients.
		delete_transient( 'udam_toolkit_dashboard_data' );

		// 7. Core database maintenance: apply retention limits.
		$retention_limit = $settings ? $settings->get( 'report_retention', 25 ) : 25;
		$runs_table      = esc_sql( $db->get_table_name( 'runs' ) );

		// Fetch older completed runs that exceed retention limit.
		$older_runs = $wpdb->get_col( $wpdb->prepare(
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
			"SELECT id FROM {$runs_table} WHERE status = 'completed' ORDER BY started_at DESC LIMIT 18446744073709551615 OFFSET %d",
			(int) $retention_limit
		) );

		if ( ! empty( $older_runs ) ) {
			foreach ( $older_runs as $old_run_id ) {
				$db->delete_run( (int) $old_run_id );
			}
			if ( $logger ) {
				/* translators: %d: Number of older scans */
				$logger->log( 'info', sprintf( __( 'Cleaned up %d older scans based on retention settings.', 'ud-audit-manager' ), count( $older_runs ) ) );
			}
		}


		do_action( 'udam_scan_completed', $run_id, $overall_score );

		return [
			'run_id'           => $run_id,
			'status'           => 'completed',
			'score'            => $overall_score,
			'scores_breakdown' => $module_scores,
			'stats'            => $stats,
		];
	}

	/**
	 * Retrieve modules list to be scanned based on type.
	 *
	 * @param string $type The scan type identifier.
	 * @return array The list of module slugs.
	 */
	private function get_modules_to_scan( string $type ) : array {
		$settings    = Container::instance()->get( 'settings' );
		$all_enabled = $settings ? $settings->get_enabled_modules() : [];

		if ( 'full' === $type ) {
			return $all_enabled;
		}

		// If a single module is requested, verify if it is registered & enabled.
		if ( in_array( $type, $all_enabled, true ) && isset( $this->get_module_classes()[ $type ] ) ) {
			return [ $type ];
		}

		return [];
	}

}
