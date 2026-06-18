<?php
namespace UDAuditManager\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for Dashboard layout.
 *
 * @package UDAuditManager
 * @subpackage Admin
 * @since 1.0.0
 */
class Dashboard {

	/**
	 * Render the dashboard template page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render() : void {
		include_once UDAM_PATH . 'templates/dashboard.php';
	}
}
