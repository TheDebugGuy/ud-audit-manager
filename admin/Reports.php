<?php
namespace UDAuditManager\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for Reports layout.
 *
 * @package UDAuditManager
 * @subpackage Admin
 * @since 1.0.0
 */
class Reports {

	/**
	 * Render the reports list page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render() : void {
		include_once UDAM_PATH . 'templates/reports.php';
	}
}
