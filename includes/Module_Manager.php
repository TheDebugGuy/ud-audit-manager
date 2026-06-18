<?php
/**
 * Module Manager service.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.1
 */

namespace UDAuditManager\Includes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Module_Manager
 *
 * Single source of truth for discovering, loading, and dynamic gating of scanner modules.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.1
 */
class Module_Manager {

	/**
	 * All available core modules.
	 *
	 * @var array
	 */
	private array $module_classes = [
		'seo'           => 'UDAuditManager\Modules\SEO_Module',
		'performance'   => 'UDAuditManager\Modules\Performance_Module',
		'accessibility' => 'UDAuditManager\Modules\Accessibility_Module',
		'security'      => 'UDAuditManager\Modules\Security_Module',
		'database'      => 'UDAuditManager\Modules\Database_Module',
		'content'       => 'UDAuditManager\Modules\Content_Module',
		'plugin'        => 'UDAuditManager\Modules\Plugin_Module',
		'theme'         => 'UDAuditManager\Modules\Theme_Module',
	];

	/**
	 * Retrieves all discovered modules (including disabled ones).
	 *
	 * @since 1.0.1
	 * @return array Discovered module mappings.
	 */
	public function get_discovered_modules() : array {
		$modules = $this->module_classes;

		/**
		 * Filters the list of discovered module classes.
		 *
		 * @since 1.0.1
		 * @param array $modules Array of module slugs to class names.
		 */
		return apply_filters( 'udam_discovered_modules', $modules );
	}

	/**
	 * Retrieves currently enabled module classes list.
	 *
	 * @since 1.0.1
	 * @return array Enabled module slug => class name.
	 */
	public function get_enabled_modules() : array {
		$settings = Container::instance()->get( 'settings' );
		if ( ! $settings instanceof Settings ) {
			return [];
		}

		$modules_config = $settings->get( 'modules', [] );
		$defaults       = $settings->get_defaults();
		$enabled        = [];

		foreach ( $this->get_discovered_modules() as $slug => $class ) {
			// If settings specifically disable this module, skip it. Default to enabled if not set.
			$is_enabled = isset( $modules_config[ $slug ] ) ? (bool) $modules_config[ $slug ] : ( $defaults['modules'][ $slug ] ?? true );
			if ( $is_enabled ) {
				$enabled[ $slug ] = $class;
			}
		}

		/**
		 * Filters the list of enabled module classes.
		 *
		 * @since 1.0.1
		 * @param array $enabled Array of enabled module slugs to class names.
		 */
		return apply_filters( 'udam_enabled_modules', $enabled );
	}
}
