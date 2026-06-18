<?php
/**
 * Exporter registry and download orchestrator.
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
 * Class Exporter_Manager
 *
 * Manages registered exporter formats and intercepts admin requests to deliver CSV,
 * JSON, or PDF/Print downloads.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class Exporter_Manager {

	/**
	 * Map of registered format slugs to class names.
	 *
	 * @var array
	 */
	private array $exporters = [];

	/**
	 * Constructor. Registers default formats and hook.
	 */
	public function __construct() {
		$this->register_exporter( 'csv', 'UDAuditManager\Includes\CSV_Exporter' );
		$this->register_exporter( 'json', 'UDAuditManager\Includes\JSON_Exporter' );

		// Hook into admin_init to capture download triggers.
		add_action( 'admin_init', [ $this, 'maybe_export_report' ] );
	}

	/**
	 * Registers a new exporter format class.
	 *
	 * @param string $format     The format slug (e.g. 'csv').
	 * @param string $class_name The fully qualified class name.
	 * @return void
	 */
	public function register_exporter( string $format, string $class_name ) : void {
		$this->exporters[ $format ] = $class_name;
	}

	/**
	 * Intercepts requests for report downloads.
	 *
	 * @return void
	 */
	public function maybe_export_report() : void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$format = isset( $_GET['udam_export'] ) ? sanitize_text_field( wp_unslash( $_GET['udam_export'] ) ) : '';
		$run_id = isset( $_GET['run_id'] ) ? absint( wp_unslash( $_GET['run_id'] ) ) : 0;

		if ( empty( $format ) || ! $run_id ) {
			return;
		}

		// Verify nonce for security.
		check_admin_referer( 'udam_export_report_' . $run_id );

		// Apply dynamic filter for registered exporters.
		/**
		 * Filters the registered exporters list.
		 *
		 * @since 1.0.0
		 * @param array $exporters Current format to class mappings.
		 */
		$registered = apply_filters( 'udam_exporters', $this->exporters );

		if ( ! isset( $registered[ $format ] ) && 'print' !== $format ) {
			wp_die( esc_html__( 'Invalid export format requested.', 'ud-audit-manager' ) );
		}

		// Handle print view separately by skipping file headers and including template.
		if ( 'print' === $format ) {
			include UDAM_PATH . 'templates/report-print.php';
			exit;
		}

		$class = $registered[ $format ];
		if ( ! class_exists( $class ) ) {
			wp_die( esc_html__( 'Exporter handler class not found.', 'ud-audit-manager' ) );
		}

		/** @var Exporter_Base $exporter */
		$exporter = new $class();

		// Set download headers.
		$filename = sprintf( 'udam-report-run-%d.%s', $run_id, $format );
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_action
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: ' . $exporter->get_mime_type() );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );

		// Run exporter and exit.
		$exporter->export( $run_id );
		exit;
	}
}

/**
 * Class CSV_Exporter
 *
 * Concrete class: CSV Exporter.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class CSV_Exporter extends Exporter_Base {

	/**
	 * Retrieve exporter format name.
	 *
	 * @return string The format name.
	 */
	public function get_name() : string {
		return 'CSV';
	}

	/**
	 * Retrieve exporter HTTP mime type.
	 *
	 * @return string The mime type.
	 */
	public function get_mime_type() : string {
		return 'text/csv; charset=utf-8';
	}

	/**
	 * Exports audit findings as a downloadable CSV stream.
	 *
	 * @param int $run_id The scan run ID.
	 * @return void
	 */
	public function export( int $run_id ) : void {
		$db = Container::instance()->get( 'db' );
		if ( ! $db instanceof Database ) {
			return;
		}

		$findings = $db->get_findings( $run_id );

		// Open output stream.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$output = fopen( 'php://output', 'w' );
		if ( ! $fp = $output ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found
			return;
		}

		// Write UTF-8 BOM for Excel compatibility.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fprintf
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Header Row.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fputcsv
		fputcsv(
			$output,
			[
				__( 'Module', 'ud-audit-manager' ),
				__( 'Check Key', 'ud-audit-manager' ),
				__( 'Title', 'ud-audit-manager' ),
				__( 'Severity', 'ud-audit-manager' ),
				__( 'Location', 'ud-audit-manager' ),
				__( 'Status', 'ud-audit-manager' ),
				__( 'Description', 'ud-audit-manager' ),
			]
		);

		foreach ( $findings as $finding ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fputcsv
			fputcsv(
				$output,
				[
					strtoupper( $finding->module ),
					$finding->issue_key,
					$finding->title,
					strtoupper( $finding->severity ),
					wp_strip_all_tags( $finding->location ),
					$finding->status,
					$finding->description,
				]
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
	}
}

/**
 * Class JSON_Exporter
 *
 * Concrete class: JSON Exporter.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class JSON_Exporter extends Exporter_Base {

	/**
	 * Retrieve format name.
	 *
	 * @return string The format name.
	 */
	public function get_name() : string {
		return 'JSON';
	}

	/**
	 * Retrieve format mime type.
	 *
	 * @return string The mime type.
	 */
	public function get_mime_type() : string {
		return 'application/json; charset=utf-8';
	}

	/**
	 * Exports run details, snapshot values, and findings in raw JSON format.
	 *
	 * @param int $run_id The scan run ID.
	 * @return void
	 */
	public function export( int $run_id ) : void {
		$db = Container::instance()->get( 'db' );
		if ( ! $db instanceof Database ) {
			return;
		}

		$run      = $db->get_run( $run_id );
		$findings = $db->get_findings( $run_id );
		
		// Deprecated: Snapshot feature has been removed. Returned empty array for backward compatibility.
		$snapshot = [];

		$report = [
			'run'       => $run,
			'findings'  => $findings,
			'snapshot'  => $snapshot,
			'generator' => 'UD Audit Manager v' . UDAM_VERSION,
			'timestamp' => current_time( 'mysql' ),
		];

		echo wp_json_encode( $report, JSON_PRETTY_PRINT );
	}
}
