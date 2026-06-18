<?php
/**
 * Service Container class to manage class dependencies.
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
 * Class Container
 *
 * Simple service container to store and fetch singleton components within the plugin.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class Container {

	/**
	 * Singleton instance.
	 *
	 * @var Container|null
	 */
	private static ?Container $instance = null;

	/**
	 * Registered services.
	 *
	 * @var array
	 */
	private array $services = [];

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Get the container instance.
	 *
	 * @return Container The singleton container instance.
	 */
	public static function instance() : Container {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Set a service instance in the container.
	 *
	 * @param string $id      Service identifier.
	 * @param object $service Service object instance.
	 * @return void
	 */
	public function set( string $id, object $service ) : void {
		$this->services[ $id ] = $service;
	}

	/**
	 * Get a service instance from the container.
	 *
	 * @param string $id Service identifier.
	 * @return object|null Service object instance or null if not found.
	 */
	public function get( string $id ) : ?object {
		return isset( $this->services[ $id ] ) ? $this->services[ $id ] : null;
	}
}
