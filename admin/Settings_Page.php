<?php
namespace UDAuditManager\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for Settings configuration layout.
 *
 * @package UDAuditManager
 * @subpackage Admin
 * @since 1.0.0
 */
class Settings_Page {

	/**
	 * Render the settings form and options page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render() : void {
		include_once UDAM_PATH . 'templates/settings.php';
	}
}
