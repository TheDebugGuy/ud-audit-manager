<?php
/**
 * Abstract class Module_Base.
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
 * Class Module_Base
 *
 * Shared foundation for all specific auditing module engines, providing helper utilities
 * for persisting finding logs.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
abstract class Module_Base implements Fixable_Interface {

	/**
	 * Get the module slug.
	 *
	 * @return string The module identifier slug.
	 */
	abstract public function get_slug() : string;

	/**
	 * Get the module localized title.
	 *
	 * @return string The title.
	 */
	abstract public function get_title() : string;

	/**
	 * Execute a batch scan step.
	 *
	 * @param int $run_id The current scan run ID.
	 * @param int $offset The current item offset.
	 * @param int $limit  Max items to process in this batch.
	 * @return array { completed: bool, offset: int, total: int }
	 */
	abstract public function scan_batch( int $run_id, int $offset, int $limit ) : array;

	/**
	 * Checks if this module is enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled() : bool {
		$settings = Container::instance()->get( 'settings' );
		if ( ! $settings instanceof Settings ) {
			return false;
		}
		$enabled_modules = $settings->get_enabled_modules();
		return in_array( $this->get_slug(), $enabled_modules, true );
	}

	/**
	 * Insert a scan finding into the database.
	 *
	 * @param int    $run_id    The scan run ID.
	 * @param string $issue_key Unique check key.
	 * @param string $title     Title of the issue.
	 * @param string $severity  Severity level.
	 * @param array  $args      Optional metadata (why_it_matters, description, how_to_fix, location, suggested_action).
	 * @return void
	 */
	protected function add_finding( int $run_id, string $issue_key, string $title, string $severity, array $args = [] ) : void {
		$db = Container::instance()->get( 'db' );
		if ( ! $db instanceof Database ) {
			return;
		}

		$data = array_merge(
			[
				'run_id'           => $run_id,
				'module'           => $this->get_slug(),
				'issue_key'        => $issue_key,
				'title'            => $title,
				'severity'         => $severity,
				'description'      => '',
				'why_it_matters'   => '',
				'how_to_fix'       => '',
				'suggested_action' => '',
				'location'         => '',
				'status'           => 'open',
				'is_fixable'       => $this->can_fix( $issue_key ) ? 1 : 0,
			],
			$args
		);

		$db->save_finding( $data );
	}

	/**
	 * Save a metric to the snapshot.
	 *
	 * @deprecated 1.1.0 Snapshot feature removed. No-op for backward compatibility.
	 * @param int    $run_id Scan run ID.
	 * @param string $key    Metric key.
	 * @param mixed  $value  Metric value.
	 * @return void
	 */
	protected function save_metric( int $run_id, string $key, $value ) : void {
		// Deprecated: Snapshot feature has been removed.
	}

	/**
	 * Default check if an issue is fixable. Modules override this.
	 *
	 * @param string $issue_key The issue identifier.
	 * @return bool True if fixable, false otherwise.
	 */
	public function can_fix( string $issue_key ) : bool {
		return false;
	}

	/**
	 * Default auto-fix executor. Modules override this.
	 *
	 * @param string $issue_key The issue identifier.
	 * @param string $location  Target file/path/DB row.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function execute_fix( string $issue_key, string $location ) {
		return new WP_Error( 'not_implemented', __( 'Auto-fix is not implemented for this finding.', 'ud-audit-manager' ) );
	}
}
