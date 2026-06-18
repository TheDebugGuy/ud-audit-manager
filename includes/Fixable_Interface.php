<?php
/**
 * Interface for modules that support automatic issue resolution.
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
 * Interface Fixable_Interface
 *
 * Defines contract for modules offering automated fixes for identified issues.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
interface Fixable_Interface {

	/**
	 * Checks if the module can automatically fix a specific issue key.
	 *
	 * @param string $issue_key The issue identifier.
	 * @return bool True if fixable, false otherwise.
	 */
	public function can_fix( string $issue_key ) : bool;

	/**
	 * Executes the automatic fix for the given issue key and target.
	 *
	 * @param string $issue_key The issue identifier.
	 * @param string $location  Target file/path/DB row.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function execute_fix( string $issue_key, string $location );
}
