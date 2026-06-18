<?php
/**
 * UD Audit Manager Uninstall Script.
 *
 * Cleans up options, database tables, and log files upon plugin deletion.
 *
 * @package UDAuditManager
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clean up plugin resources on uninstall.
 *
 * @since 1.0.6
 * @return void
 */
function udam_uninstall() : void {
	global $wpdb;

	// Drop custom tables.
	$runs_table      = $wpdb->prefix . 'wp_audit_runs';
	$findings_table  = $wpdb->prefix . 'wp_audit_findings';
	$snapshots_table = $wpdb->prefix . 'wp_audit_snapshots';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$runs_table}`" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$findings_table}`" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$snapshots_table}`" );

	// Delete plugin options & transients.
	delete_option( 'udam_toolkit_settings' );
	delete_option( 'udam_toolkit_setup_completed' );
	delete_option( 'udam_last_cron_error' );
	delete_transient( 'udam_toolkit_activation_redirect' );
	delete_transient( 'udam_toolkit_dashboard_data' );
	delete_transient( 'udam_rest_api_status' );

	// Clean up log files via native WP_Filesystem.
	$upload_dir = wp_upload_dir();
	$log_dir    = $upload_dir['basedir'] . '/ud-audit-manager';

	global $wp_filesystem;
	if ( empty( $wp_filesystem ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}

	if ( $wp_filesystem && $wp_filesystem->exists( $log_dir ) ) {
		$wp_filesystem->delete( $log_dir, true );
	}
}

udam_uninstall();
