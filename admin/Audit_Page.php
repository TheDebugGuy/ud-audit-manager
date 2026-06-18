<?php
namespace UDAuditManager\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for Audit modular layout.
 *
 * @package UDAuditManager
 * @subpackage Admin
 * @since 1.0.0
 */
class Audit_Page {

	/**
	 * Render the modular category page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render() : void {
		include_once UDAM_PATH . 'templates/audit-page.php';
	}
}
