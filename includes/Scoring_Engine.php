<?php
/**
 * Centered scoring engine class.
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
 * Class Scoring_Engine
 *
 * Computes health metrics based on severity weights, logarithmic penalty multipliers
 * for repeating issues, and calculates overall weighted site scores.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class Scoring_Engine {

	/**
	 * Retrieve weight for a specific severity.
	 *
	 * @param string $severity The severity level slug (critical, high, medium, low).
	 * @return int Deduction penalty weight.
	 */
	public function get_severity_weight( string $severity ) : int {
		$settings = Container::instance()->get( 'settings' );
		$weights  = $settings ? $settings->get( 'severity_weights', [] ) : [];

		/**
		 * Filters the scoring weights list.
		 *
		 * @since 1.0.0
		 * @param array $weights Current severity weights configurations.
		 */
		$weights = apply_filters( 'udam_scoring_weights', $weights );

		return isset( $weights[ strtolower( $severity ) ] ) ? (int) $weights[ strtolower( $severity ) ] : 0;
	}

	/**
	 * Calculates the score of a module based on its findings.
	 *
	 * @param array $findings List of findings (as database objects).
	 * @return int Score out of 100.
	 */
	public function calculate_module_score( array $findings ) : int {
		if ( empty( $findings ) ) {
			$logger = Container::instance()->get( 'logger' );
			if ( $logger ) {
				$logger->log( 'info', 'Scoring module: No findings. Score = 100.' );
			}
			return 100;
		}

		// Group findings by issue_key to avoid double penalizing.
		$grouped = [];
		foreach ( $findings as $finding ) {
			$grouped[ $finding->issue_key ][] = $finding;
		}

		$total_deduction   = 0.0;
		$deductions_detail = [];

		foreach ( $grouped as $issue_key => $items ) {
			$first    = $items[0];
			$severity = $first->severity;
			$weight   = $this->get_severity_weight( $severity );
			$count    = count( $items );

			// Multiplier = min( 2.5, 1 + 0.3 * log2( count ) ).
			$log_multiplier = ( $count > 1 ) ? ( 1.0 + 0.3 * log( (float) $count, 2.0 ) ) : 1.0;
			$multiplier     = min( 2.5, $log_multiplier );
			$deduction      = $weight * $multiplier;

			$total_deduction += $deduction;
			$deductions_detail[ $issue_key ] = [
				'severity'  => $severity,
				'weight'    => $weight,
				'count'     => $count,
				'deduction' => $deduction,
			];
		}

		$score = max( 0, min( 100, (int) round( 100.0 - $total_deduction ) ) );

		$logger = Container::instance()->get( 'logger' );
		if ( $logger ) {
			$module_slug = isset( $findings[0]->module ) ? $findings[0]->module : 'unknown';
			$logger->log(
				'info',
				sprintf(
					'Scoring module "%s": findings_count=%d, score=%d, total_deduction=%f',
					$module_slug,
					count( $findings ),
					$score,
					$total_deduction
				),
				$deductions_detail
			);
		}

		return $score;
	}

	/**
	 * Calculates the overall health score using a weighted average of active modules.
	 *
	 * @param array $module_scores Associative array: [ 'seo' => 95, 'security' => 88, ... ]
	 * @return int Overall score out of 100.
	 */
	public function calculate_overall_score( array $module_scores ) : int {
		if ( empty( $module_scores ) ) {
			$logger = Container::instance()->get( 'logger' );
			if ( $logger ) {
				$logger->log( 'info', 'Scoring overall: No module scores provided. Score = 0.' );
			}
			return 0;
		}

		$default_weights = [
			'seo'           => 10,
			'performance'   => 10,
			'accessibility' => 8,
			'security'      => 12,
			'database'      => 6,
			'content'       => 6,
			'plugin'        => 8,
			'theme'         => 6,
		];

		// Filter module weights based on active/provided module scores.
		$total_weight = 0;
		$weighted_sum = 0;

		foreach ( $module_scores as $module => $score ) {
			$weight        = $default_weights[ $module ] ?? 5;
			$total_weight += $weight;
			$weighted_sum += ( $score * $weight );
		}

		if ( 0 === $total_weight ) {
			return 0;
		}

		$overall_score = max( 0, min( 100, (int) round( $weighted_sum / $total_weight ) ) );

		$logger = Container::instance()->get( 'logger' );
		if ( $logger ) {
			$logger->log(
				'info',
				sprintf(
					'Scoring overall: score=%d, active_modules_count=%d',
					$overall_score,
					count( $module_scores )
				),
				$module_scores
			);
		}

		return $overall_score;
	}

	/**
	 * Estimate potential score recovery for a specific issue key if resolved.
	 *
	 * @since 1.0.1
	 * @param string $issue_key    The issue key to estimate recovery for.
	 * @param string $module_slug  The module slug.
	 * @param array  $all_findings All current findings for comparison.
	 * @return int Score recovery estimation.
	 */
	public function get_impact_estimation( string $issue_key, string $module_slug, array $all_findings ) : int {
		// Filter findings for this module.
		$module_findings = array_filter(
			$all_findings,
			function ( $finding ) use ( $module_slug ) {
				return $finding->module === $module_slug;
			}
		);

		// Calculate current score.
		$current_score = $this->calculate_module_score( $module_findings );

		// Filter out findings matching the target issue key.
		$filtered_findings = array_filter(
			$module_findings,
			function ( $finding ) use ( $issue_key ) {
				return $finding->issue_key !== $issue_key;
			}
		);

		$projected_score = $this->calculate_module_score( $filtered_findings );
		return max( 0, $projected_score - $current_score );
	}
}
