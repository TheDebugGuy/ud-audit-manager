<?php
/**
 * Database schema and execution manager.
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
 * Class Database
 *
 * Handles custom database table installation, runs tracking, findings persistence,
 * and snapshots comparison storage.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class Database {

	/**
	 * Runs table name.
	 *
	 * @var string
	 */
	private string $runs_table;

	/**
	 * Findings table name.
	 *
	 * @var string
	 */
	private string $findings_table;

	/**
	 * Snapshots table name.
	 *
	 * @var string
	 */
	private string $snapshots_table;

	/**
	 * Constructor. Registers table names with prefix.
	 */
	public function __construct() {
		global $wpdb;
		$this->runs_table      = $wpdb->prefix . 'wp_audit_runs';
		$this->findings_table  = $wpdb->prefix . 'wp_audit_findings';
		$this->snapshots_table = $wpdb->prefix . 'wp_audit_snapshots';

		// Run version upgrade if needed.
		if ( defined( 'UDAM_DB_VERSION' ) ) {
			$db_version = get_option( 'udam_toolkit_db_version' );
			if ( ! $db_version || version_compare( $db_version, UDAM_DB_VERSION, '<' ) ) {
				$this->install();
			}
		}
	}

	/**
	 * Get table names.
	 *
	 * @param string $table The short table name key (e.g. 'runs', 'findings', 'snapshots').
	 * @return string The fully prefixed table name or empty string if invalid.
	 */
	public function get_table_name( string $table ) : string {
		$prop = $table . '_table';
		return $this->$prop ?? '';
	}

	/**
	 * Creates custom DB tables using dbDelta.
	 *
	 * @return void
	 */
	public function install() : void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql_runs = "CREATE TABLE {$this->runs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			status varchar(20) NOT NULL DEFAULT 'pending',
			type varchar(20) NOT NULL DEFAULT 'full',
			source varchar(20) NOT NULL DEFAULT 'manual',
			score int(3) NOT NULL DEFAULT 0,
			scores_breakdown longtext DEFAULT NULL,
			stats longtext DEFAULT NULL,
			current_module varchar(50) DEFAULT NULL,
			current_offset int(11) DEFAULT 0,
			started_at datetime NOT NULL,
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY started_at (started_at)
		) $charset_collate;";

		$sql_findings = "CREATE TABLE {$this->findings_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_id bigint(20) unsigned NOT NULL,
			module varchar(20) NOT NULL,
			issue_key varchar(100) NOT NULL,
			title varchar(255) NOT NULL,
			severity varchar(15) NOT NULL,
			description text DEFAULT NULL,
			why_it_matters text DEFAULT NULL,
			how_to_fix text DEFAULT NULL,
			suggested_action text DEFAULT NULL,
			location text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			is_fixable tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY run_id (run_id),
			KEY module (module),
			KEY severity (severity),
			KEY status (status)
		) $charset_collate;";

		$sql_snapshots = "CREATE TABLE {$this->snapshots_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			metric_key varchar(100) NOT NULL,
			metric_value varchar(255) NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY run_metric (run_id,metric_key),
			KEY metric_key (metric_key)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_runs );
		dbDelta( $sql_findings );
		dbDelta( $sql_snapshots );

		if ( defined( 'UDAM_DB_VERSION' ) ) {
			update_option( 'udam_toolkit_db_version', UDAM_DB_VERSION );
		}
	}

	/**
	 * Saves or updates a scan run record.
	 *
	 * @param array $data Run details.
	 * @return int Inserted ID or updated rows.
	 */
	public function save_run( array $data ) : int {
		global $wpdb;

		if ( isset( $data['id'] ) ) {
			$id = (int) $data['id'];
			unset( $data['id'] );
			$wpdb->update( $this->runs_table, $data, [ 'id' => $id ] );
			return $id;
		}

		$wpdb->insert( $this->runs_table, $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Get a run record.
	 *
	 * @param int $id The run ID.
	 * @return object|null The run record object or null if not found.
	 */
	public function get_run( int $id ) : ?object {
		global $wpdb;
		$runs_table = esc_sql( $this->runs_table );
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
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$runs_table} WHERE id = %d", $id ) );
		return $row ? $row : null;
	}

	/**
	 * Get latest completed run.
	 *
	 * @return object|null The run record object or null if not found.
	 */
	public function get_latest_completed_run() : ?object {
		global $wpdb;
		$runs_table = esc_sql( $this->runs_table );
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
		$row = $wpdb->get_row( "SELECT * FROM {$runs_table} WHERE status = 'completed' ORDER BY started_at DESC LIMIT 1" );
		return $row ? $row : null;
	}

	/**
	 * Get currently active running scan.
	 *
	 * @return object|null The run record object or null if not found.
	 */
	public function get_active_run() : ?object {
		global $wpdb;
		$runs_table = esc_sql( $this->runs_table );
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
		$row = $wpdb->get_row( "SELECT * FROM {$runs_table} WHERE status = 'running' ORDER BY started_at DESC LIMIT 1" );
		return $row ? $row : null;
	}

	/**
	 * Get runs history for chart/comparison.
	 *
	 * @param int $limit The maximum number of runs to fetch. Default is 10.
	 * @return array The list of run records.
	 */
	public function get_runs_history( int $limit = 10 ) : array {
		global $wpdb;
		$runs_table = esc_sql( $this->runs_table );
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
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$runs_table} WHERE status = 'completed' ORDER BY started_at DESC LIMIT %d", $limit ) );
	}

	/**
	 * Get filtered runs history.
	 *
	 * @param int   $limit   The maximum number of runs to fetch. Default is 10.
	 * @param array $filters Filters (source, status).
	 * @return array The list of run records.
	 */
	public function get_runs_history_filtered( int $limit = 10, array $filters = [] ) : array {
		global $wpdb;

		$query  = "SELECT * FROM {$this->runs_table} WHERE 1=1";
		$params = [];

		if ( ! empty( $filters['status'] ) ) {
			$query   .= ' AND status = %s';
			$params[] = $filters['status'];
		}

		if ( ! empty( $filters['source'] ) ) {
			$query   .= ' AND source = %s';
			$params[] = $filters['source'];
		}

		$query   .= ' ORDER BY started_at DESC LIMIT %d';
		$params[] = $limit;

		/**
		 * Direct database query required.
		 *
		 * Audit results and statistics must be fetched in real-time.
		 * Cached values may return stale scan information.
		 *
		 * All dynamic values are sanitized and passed through
		 * $wpdb->prepare() before execution.
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is required for real-time audit accuracy; caching is bypassed to prevent stale scan results.
		return $wpdb->get_results( $wpdb->prepare( $query, $params ) );
	}

	/**
	 * Saves an issue finding.
	 *
	 * @param array $data Finding details.
	 * @return int Inserted ID or updated ID.
	 */
	public function save_finding( array $data ) : int {
		global $wpdb;

		if ( isset( $data['id'] ) ) {
			$id = (int) $data['id'];
			unset( $data['id'] );
			$wpdb->update( $this->findings_table, $data, [ 'id' => $id ] );
			return $id;
		}

		$wpdb->insert( $this->findings_table, $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Gets findings for a run.
	 *
	 * @param int   $run_id  The run ID.
	 * @param array $filters Optional filters (module, severity, status, search).
	 * @return array The list of findings.
	 */
	public function get_findings( int $run_id, array $filters = [] ) : array {
		global $wpdb;

		$query  = "SELECT * FROM {$this->findings_table} WHERE run_id = %d";
		$params = [ $run_id ];

		if ( ! empty( $filters['module'] ) ) {
			$query   .= ' AND module = %s';
			$params[] = $filters['module'];
		}

		if ( ! empty( $filters['severity'] ) ) {
			$query   .= ' AND severity = %s';
			$params[] = $filters['severity'];
		}

		if ( ! empty( $filters['status'] ) ) {
			$query   .= ' AND status = %s';
			$params[] = $filters['status'];
		}

		if ( ! empty( $filters['search'] ) ) {
			$query       .= ' AND (title LIKE %s OR description LIKE %s OR location LIKE %s)';
			$search_term  = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$params[]     = $search_term;
			$params[]     = $search_term;
			$params[]     = $search_term;
		}

		/**
		 * Direct database query required.
		 *
		 * Audit results and statistics must be fetched in real-time.
		 * Cached values may return stale scan information.
		 *
		 * All dynamic values are sanitized and passed through
		 * $wpdb->prepare() before execution.
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is required for real-time audit accuracy; caching is bypassed to prevent stale scan results.
		return $wpdb->get_results( $wpdb->prepare( $query, $params ) );
	}

	/**
	 * Deletes all findings of a run.
	 *
	 * @param int $run_id The run ID.
	 * @return void
	 */
	public function delete_findings_for_run( int $run_id ) : void {
		global $wpdb;
		$wpdb->delete( $this->findings_table, [ 'run_id' => $run_id ] );
	}

	/**
	 * Deletes all findings of a run for a specific module.
	 *
	 * @param int    $run_id The run ID.
	 * @param string $module The module slug.
	 * @return void
	 */
	public function delete_findings_for_module( int $run_id, string $module ) : void {
		global $wpdb;
		$wpdb->delete( $this->findings_table, [ 'run_id' => $run_id, 'module' => $module ] );
	}

	/**
	 * Saves metrics snapshot.
	 *
	 * @param int   $run_id  The run ID.
	 * @param array $metrics Key-value metrics data.
	 * @return void
	 */
	public function save_snapshot( int $run_id, array $metrics ) : void {
		global $wpdb;

		$created_at = current_time( 'mysql' );
		foreach ( $metrics as $key => $value ) {
			$wpdb->replace(
				$this->snapshots_table,
				[
					'run_id'       => $run_id,
					'metric_key'   => $key,
					'metric_value' => (string) $value,
					'created_at'   => $created_at,
				]
			);
		}
	}

	/**
	 * Get metrics snapshot for a run.
	 *
	 * @param int $run_id The run ID.
	 * @return array The list of metrics key-value pairs.
	 */
	public function get_snapshot( int $run_id ) : array {
		global $wpdb;
		$snapshots_table = esc_sql( $this->snapshots_table );
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
		$results  = $wpdb->get_results( $wpdb->prepare( "SELECT metric_key, metric_value FROM {$snapshots_table} WHERE run_id = %d", $run_id ) );
		$snapshot = [];

		foreach ( $results as $row ) {
			$snapshot[ $row->metric_key ] = $row->metric_value;
		}

		return $snapshot;
	}

	/**
	 * Deletes specific run and its findings.
	 *
	 * @param int $run_id The run ID.
	 * @return void
	 */
	public function delete_run( int $run_id ) : void {
		global $wpdb;
		$wpdb->delete( $this->runs_table, [ 'id' => $run_id ] );
		$wpdb->delete( $this->findings_table, [ 'run_id' => $run_id ] );
		$wpdb->delete( $this->snapshots_table, [ 'run_id' => $run_id ] );
	}

	/**
	 * Clear Audit History data.
	 *
	 * @return void
	 */
	public function clear_audit_history() : void {
		global $wpdb;
		$runs_table      = esc_sql( $this->runs_table );
		$findings_table  = esc_sql( $this->findings_table );
		$snapshots_table = esc_sql( $this->snapshots_table );
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
		$wpdb->query( "TRUNCATE TABLE {$runs_table}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is required for real-time audit accuracy; caching is bypassed to prevent stale scan results.
		$wpdb->query( "TRUNCATE TABLE {$findings_table}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is required for real-time audit accuracy; caching is bypassed to prevent stale scan results.
		$wpdb->query( "TRUNCATE TABLE {$snapshots_table}" );
	}
}
