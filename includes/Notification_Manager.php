<?php
/**
 * Notification Manager.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.2
 */

namespace UDAuditManager\Includes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notification_Manager
 *
 * Scopes toast alerts queuing to transients.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.2
 */
class Notification_Manager {

	/**
	 * Get the transient key for the current user.
	 *
	 * @return string The transient key.
	 */
	private function get_transient_key() : string {
		$user_id = get_current_user_id();
		return 'udam_queued_notices_' . $user_id;
	}

	/**
	 * Queue a message.
	 *
	 * @param string $message The message text.
	 * @param string $type    The type of notice (success, error, warning, info).
	 * @param string $title   Optional title for the toast.
	 * @param array  $action  Optional action array [ 'text' => '', 'url' => '' ].
	 * @return void
	 */
	public function add_notice( string $message, string $type = 'success', string $title = '', array $action = [] ) : void {
		$key     = $this->get_transient_key();
		$notices = get_transient( $key );
		if ( ! is_array( $notices ) ) {
			$notices = [];
		}

		$notices[] = [
			'message' => $message,
			'type'    => $type,
			'title'   => $title,
			'action'  => $action,
		];

		set_transient( $key, $notices, 3600 ); // Expire in 1 hour.
	}

	/**
	 * Success notice helper.
	 *
	 * @param string $message The message text.
	 * @param string $title   Optional title.
	 * @param array  $action  Optional action.
	 * @return void
	 */
	public function success( string $message, string $title = '', array $action = [] ) : void {
		$this->add_notice( $message, 'success', $title, $action );
	}

	/**
	 * Error notice helper.
	 *
	 * @param string $message The message text.
	 * @param string $title   Optional title.
	 * @param array  $action  Optional action.
	 * @return void
	 */
	public function error( string $message, string $title = '', array $action = [] ) : void {
		$this->add_notice( $message, 'error', $title, $action );
	}

	/**
	 * Warning notice helper.
	 *
	 * @param string $message The message text.
	 * @param string $title   Optional title.
	 * @param array  $action  Optional action.
	 * @return void
	 */
	public function warning( string $message, string $title = '', array $action = [] ) : void {
		$this->add_notice( $message, 'warning', $title, $action );
	}

	/**
	 * Info notice helper.
	 *
	 * @param string $message The message text.
	 * @param string $title   Optional title.
	 * @param array  $action  Optional action.
	 * @return void
	 */
	public function info( string $message, string $title = '', array $action = [] ) : void {
		$this->add_notice( $message, 'info', $title, $action );
	}

	/**
	 * Get and clear all queued notices for current user.
	 *
	 * @return array The queued notices list.
	 */
	public function get_queued_notices() : array {
		$key     = $this->get_transient_key();
		$notices = get_transient( $key );
		if ( ! is_array( $notices ) ) {
			$notices = [];
		} else {
			delete_transient( $key );
		}
		return $notices;
	}
}
