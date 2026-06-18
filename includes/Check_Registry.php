<?php
/**
 * Dynamic registry for individual audit checks.
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
 * Class Check_Registry
 *
 * Provides a dynamic registry for third-party developers or modules to register
 * custom audit checks and retrieve registered specifications.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class Check_Registry {

	/**
	 * Array of registered checks grouped by module.
	 *
	 * @var array
	 */
	private array $checks = [];

	/**
	 * Register a single audit check.
	 *
	 * @param string $module     Module slug (e.g. 'seo').
	 * @param string $check_slug Check slug (e.g. 'missing_title').
	 * @param array  $args       Check details (title, severity, description).
	 * @return void
	 */
	public function register_check( string $module, string $check_slug, array $args ) : void {
		if ( ! isset( $this->checks[ $module ] ) ) {
			$this->checks[ $module ] = [];
		}

		$this->checks[ $module ][ $check_slug ] = array_merge(
			[
				'title'       => '',
				'severity'    => 'medium',
				'description' => '',
			],
			$args
		);
	}

	/**
	 * Retrieve all registered checks, optionally filtered by module.
	 *
	 * @param string|null $module Optional module slug.
	 * @return array The list of registered checks.
	 */
	public function get_checks( ?string $module = null ) : array {
		/**
		 * Filters the registered checks map.
		 *
		 * @since 1.0.0
		 * @param array $checks The registered checks list.
		 */
		$all_checks = apply_filters( 'udam_registered_checks', $this->checks );

		if ( $module ) {
			return isset( $all_checks[ $module ] ) ? $all_checks[ $module ] : [];
		}

		return $all_checks;
	}
}
