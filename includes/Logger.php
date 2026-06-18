<?php
/**
 * Centered Logger Service for debugging and scan tracking.
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
 * Class Logger
 *
 * Handles writing diagnostic traces, errors, and informational events to a secure
 * log file inside the WordPress uploads directory.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class Logger {

	/**
	 * Log directory path.
	 *
	 * @var string
	 */
	private string $log_dir;

	/**
	 * Log file path.
	 *
	 * @var string
	 */
	private string $log_file;

	/**
	 * Constructor. Sets up paths and directory structures.
	 */
	public function __construct() {
		$upload_dir     = wp_upload_dir();
		$this->log_dir  = $upload_dir['basedir'] . '/ud-audit-manager';
		$this->log_file = $this->log_dir . '/ud-audit-manager.log';
		$this->init_log_directory();
	}

	/**
	 * Creates the log directory and adds security protection (.htaccess, index.html).
	 *
	 * @return void
	 */
	private function init_log_directory() : void {
		if ( ! file_exists( $this->log_dir ) ) {
			wp_mkdir_p( $this->log_dir );
		}

		// Write index.html to prevent directory listing.
		$index_file = $this->log_dir . '/index.html';
		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			file_put_contents( $index_file, '<!-- Silence is golden -->' );
		}

		// Write .htaccess to deny web access to logs.
		$htaccess_file = $this->log_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			file_put_contents( $htaccess_file, "Deny from all\n<Files ~ \"^\x2e\">\nOrder allow,deny\nDeny from all\n</Files>" );
		}
	}

	/**
	 * Writes a message to the log file.
	 *
	 * @param string $level   Log severity level (info, warning, error, critical).
	 * @param string $message Log message.
	 * @param array  $context Additional debug variables.
	 * @return void
	 */
	public function log( string $level, string $message, array $context = [] ) : void {
		$timestamp   = current_time( 'mysql' );
		$context_str = ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '';
		$log_entry   = sprintf( "[%s] [%s] %s%s\n", $timestamp, strtoupper( $level ), $message, $context_str );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		@file_put_contents( $this->log_file, $log_entry, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Get all log contents.
	 *
	 * @return string Log file contents or empty string.
	 */
	public function get_log_contents() : string {
		if ( file_exists( $this->log_file ) ) {
			// Read last 2MB to avoid memory exhaustion on large log files.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_filesize
			$size = filesize( $this->log_file );
			if ( $size > 2 * 1024 * 1024 ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
				$fp = fopen( $this->log_file, 'r' );
				if ( $fp ) {
					fseek( $fp, -2 * 1024 * 1024, SEEK_END );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
					$contents = fread( $fp, 2 * 1024 * 1024 );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
					fclose( $fp );
					return $contents ? $contents : '';
				}
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
			$contents = file_get_contents( $this->log_file );
			return $contents ? $contents : '';
		}
		return '';
	}

	/**
	 * Clears all logs.
	 *
	 * @return void
	 */
	public function clear_logs() : void {
		if ( file_exists( $this->log_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $this->log_file );
		}
		$this->init_log_directory();
	}
}
