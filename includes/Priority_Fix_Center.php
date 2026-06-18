<?php
/**
 * Priority Fix Center Service class.
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
 * Class Priority_Fix_Center
 *
 * Analyzes active open findings, computes potential site score impact for each issue,
 * and prioritizes the highest impact issues for automated or manual resolution.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class Priority_Fix_Center {

	/**
	 * Scans open findings for a run, calculates impact, and ranks the top fixes.
	 *
	 * @param int $run_id The scan run ID.
	 * @param int $limit  Max issues to return. Default 5.
	 * @return array List of prioritized issues with estimated score recoveries.
	 */
	public function get_highest_impact_fixes( int $run_id, int $limit = 5 ) : array {
		$db = Container::instance()->get( 'db' );
		if ( ! $db instanceof Database ) {
			return [];
		}

		$findings = $db->get_findings( $run_id, [ 'status' => 'open' ] );
		if ( empty( $findings ) ) {
			return [];
		}

		// Group findings by Module and Issue Key.
		$grouped = [];
		foreach ( $findings as $finding ) {
			$key = $finding->module . ':' . $finding->issue_key;
			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = [
					'module'         => $finding->module,
					'issue_key'      => $finding->issue_key,
					'title'          => $finding->title,
					'severity'       => $finding->severity,
					'description'    => $finding->description,
					'why_it_matters' => $finding->why_it_matters,
					'how_to_fix'     => $finding->how_to_fix,
					'is_fixable'     => $finding->is_fixable,
					'findings'       => [],
				];
			}
			$grouped[ $key ]['findings'][] = $finding;
		}

		$scoring_engine  = Container::instance()->get( 'scoring' );
		$settings        = Container::instance()->get( 'settings' );
		$enabled_modules = $settings instanceof Settings ? $settings->get_enabled_modules() : [];

		// Module weights for overall impact calculations.
		$module_weights = [
			'seo'           => 10,
			'performance'   => 10,
			'accessibility' => 8,
			'security'      => 12,
			'database'      => 6,
			'content'       => 6,
			'plugin'        => 8,
			'theme'         => 6,
		];

		// Calculate total active weight.
		$total_weight = 0;
		foreach ( $enabled_modules as $mod ) {
			$total_weight += isset( $module_weights[ $mod ] ) ? $module_weights[ $mod ] : 5;
		}
		if ( $total_weight <= 0 ) {
			$total_weight = 1;
		}

		$ranked_fixes = [];

		foreach ( $grouped as $key => $data ) {
			if ( ! in_array( $data['module'], $enabled_modules, true ) ) {
				continue;
			}

			$count  = count( $data['findings'] );
			$weight = $scoring_engine instanceof Scoring_Engine ? $scoring_engine->get_severity_weight( $data['severity'] ) : 0;

			// Compute individual deduction weight (this is what we recover if fixed).
			$log_multiplier = ( $count > 1 ) ? ( 1.0 + 0.3 * log( (float) $count, 2.0 ) ) : 1.0;
			$multiplier     = min( 2.5, $log_multiplier );

			$module_recovery = round( $weight * $multiplier, 1 );

			$mod_weight       = isset( $module_weights[ $data['module'] ] ) ? $module_weights[ $data['module'] ] : 5;
			$overall_recovery = round( ( $module_recovery * $mod_weight ) / $total_weight, 2 );

			$ranked_fixes[] = [
				'module'          => $data['module'],
				'issue_key'       => $data['issue_key'],
				'title'           => $data['title'],
				'severity'        => $data['severity'],
				'count'           => $count,
				'module_impact'   => $module_recovery,
				'overall_impact'  => $overall_recovery,
				'description'     => $data['description'],
				'why_it_matters'  => $data['why_it_matters'],
				'how_to_fix'      => $data['how_to_fix'],
				'is_fixable'      => $data['is_fixable'],
				'sample_location' => isset( $data['findings'][0]->location ) ? $data['findings'][0]->location : '',
			];
		}

		// Sort descending by overall impact.
		usort(
			$ranked_fixes,
			function ( $a, $b ) {
				if ( $a['overall_impact'] === $b['overall_impact'] ) {
					return 0;
				}
				return ( $a['overall_impact'] > $b['overall_impact'] ) ? -1 : 1;
			}
		);

		return array_slice( $ranked_fixes, 0, $limit );
	}
}
