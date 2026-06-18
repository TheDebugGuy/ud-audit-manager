<?php
/**
 * System Requirements Checker class.
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
 * Class Requirements_Checker
 *
 * Verifies system compatibility check constraints, REST API accessibility,
 * and memory requirements for running audits.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class Requirements_Checker {

	/**
	 * Run all system requirement checks.
	 *
	 * @return array The list of requirement check results.
	 */
	public function check_requirements() : array {
		global $wp_version;

		$checks = [];

		// 1. PHP Version.
		$php_version   = phpversion();
		$checks['php'] = [
			'name'     => __( 'PHP Version', 'ud-audit-manager' ),
			'required' => '7.4',
			'current'  => $php_version,
			'passed'   => version_compare( $php_version, '7.4', '>=' ),
		];

		// 2. WordPress Version.
		$checks['wp'] = [
			'name'     => __( 'WordPress Version', 'ud-audit-manager' ),
			'required' => '5.6',
			'current'  => $wp_version,
			'passed'   => version_compare( $wp_version, '5.6', '>=' ),
		];

		// 3. Memory Limit.
		$memory_limit     = ini_get( 'memory_limit' );
		$memory_bytes     = $this->let_to_num( $memory_limit ? $memory_limit : '' );
		$checks['memory'] = [
			'name'     => __( 'PHP Memory Limit', 'ud-audit-manager' ),
			'required' => '128M',
			'current'  => $memory_limit,
			'passed'   => $memory_bytes >= ( 128 * 1024 * 1024 ) || -1 === $memory_bytes,
		];

		// 4. REST API Availability.
		$transient_key = 'udam_rest_api_status';
		$cached        = get_transient( $transient_key );

		if ( false !== $cached && is_array( $cached ) ) {
			$rest_passed  = (bool) $cached['passed'];
			$rest_current = (string) $cached['current'];
		} else {
			$rest_api_url = get_rest_url();
			$response     = wp_remote_get( $rest_api_url, [ 'timeout' => 5 ] );
			$rest_passed  = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
			$rest_current = is_wp_error( $response ) ? $response->get_error_message() : sprintf(
				/* translators: %d: HTTP status code */
				__( 'HTTP %d', 'ud-audit-manager' ),
				wp_remote_retrieve_response_code( $response )
			);

			set_transient( $transient_key, [
				'passed'  => $rest_passed,
				'current' => $rest_current,
			], 12 * HOUR_IN_SECONDS );
		}

		$checks['rest_api'] = [
			'name'     => __( 'WordPress REST API', 'ud-audit-manager' ),
			'required' => __( 'Available', 'ud-audit-manager' ),
			'current'  => $rest_current,
			'passed'   => $rest_passed,
		];

		return $checks;
	}

	/**
	 * Verify if the system meets all minimum requirements.
	 *
	 * @return bool True if compatible, false otherwise.
	 */
	public function is_compatible() : bool {
		$checks = $this->check_requirements();
		foreach ( $checks as $check ) {
			if ( ! $check['passed'] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Convert shorthand memory notations (e.g. 512M) to bytes.
	 *
	 * @param string $size Shorthand size notation.
	 * @return int Number of bytes, or -1 for unlimited.
	 */
	private function let_to_num( string $size ) : int {
		if ( empty( $size ) ) {
			return 0;
		}
		$l    = substr( $size, -1 );
		$ret  = (int) substr( $size, 0, -1 );
		$byte = strtoupper( $l );

		switch ( $byte ) {
			case 'P':
				$ret *= 1024;
				// no break.
			case 'T':
				$ret *= 1024;
				// no break.
			case 'G':
				$ret *= 1024;
				// no break.
			case 'M':
				$ret *= 1024;
				// no break.
			case 'K':
				$ret *= 1024;
				break;
			default:
				$ret = (int) $size;
		}

		return $ret;
	}
}
