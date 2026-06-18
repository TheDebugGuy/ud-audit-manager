<?php
/**
 * Manager class to apply automated solutions for resolved findings.
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
 * Class Fix_Manager
 *
 * Checks if a finding is auto-fixable and delegates the execution to the corresponding
 * module class, marking the finding as resolved in the database afterward.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class Fix_Manager {

	/**
	 * Resolves a finding by triggering its module's execute_fix callback.
	 *
	 * @param int $finding_id The finding database ID.
	 * @return array|WP_Error Success response array or error.
	 */
	public function apply_fix( int $finding_id ) {
		global $wpdb;

		$db = Container::instance()->get( 'db' );
		if ( ! $db ) {
			return new WP_Error( 'db_missing', __( 'Database layer is missing.', 'ud-audit-manager' ) );
		}

		// Fetch finding.
		$findings_table = esc_sql( $db->get_table_name( 'findings' ) );
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
		$finding = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$findings_table} WHERE id = %d", $finding_id ) );

		if ( ! $finding ) {
			return new WP_Error( 'not_found', __( 'Finding not found.', 'ud-audit-manager' ) );
		}

		if ( 'fixed' === $finding->status ) {
			return [
				'success' => true,
				'message' => __( 'Issue already resolved.', 'ud-audit-manager' ),
			];
		}

		// Get active engine to resolve the module class.
		$engine = Container::instance()->get( 'engine' );
		if ( ! $engine ) {
			return new WP_Error( 'engine_missing', __( 'Scan engine is missing.', 'ud-audit-manager' ) );
		}

		// Instantiate module class dynamically.
		$module_slug     = $finding->module;
		$modules_manager = Container::instance()->get( 'modules_manager' );
		$module_classes  = $modules_manager instanceof Module_Manager ? $modules_manager->get_enabled_modules() : [];

		if ( ! isset( $module_classes[ $module_slug ] ) ) {
			return new WP_Error( 'invalid_module', __( 'Module is disabled or not registered.', 'ud-audit-manager' ) );
		}

		$class = $module_classes[ $module_slug ];
		if ( ! class_exists( $class ) ) {
			return new WP_Error( 'class_missing', __( 'Module handler class is missing.', 'ud-audit-manager' ) );
		}

		/** @var Module_Base $module_instance */
		$module_instance = new $class();

		if ( ! $module_instance instanceof Fixable_Interface || ! $module_instance->can_fix( $finding->issue_key ) ) {
			return new WP_Error( 'not_fixable', __( 'This issue does not support automatic fixing.', 'ud-audit-manager' ) );
		}

		// Execute the auto-fix.
		$result = $module_instance->execute_fix( $finding->issue_key, $finding->location );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update database finding status to fixed.
		$wpdb->update( $findings_table, [ 'status' => 'fixed' ], [ 'id' => $finding_id ] );

		// Invalidate transients.
		delete_transient( 'udam_toolkit_dashboard_data' );

		return [
			'success' => true,
			'message' => __( 'Issue resolved and database record updated successfully!', 'ud-audit-manager' ),
		];
	}
}
