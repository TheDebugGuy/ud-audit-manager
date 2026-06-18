<?php
/**
 * Plugin Name:       UD Audit Manager
 * Plugin URI:        https://retro.great-site.net/docs/ud-audit-manager/
 * Description:       A modern, lightweight, modular website auditing toolkit for WordPress. Audits SEO, Performance, Accessibility, Security, Database, and Content.
 * Version:           1.0.0
 * Author:            Undefined Developer
 * Author URI:        https://profiles.wordpress.org/undefineddeveloper/
 * Text Domain:       ud-audit-manager
 * Domain Path:       /languages
 * Requires PHP:      8.0
 * Requires at least: 6.5
 * Tested up to:      7.0
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package UDAuditManager
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Version of the plugin.
 */
define( 'UDAM_VERSION', '1.0.0' );

/**
 * Database schema version of the plugin.
 */
define( 'UDAM_DB_VERSION', '1.0.2' );

/**
 * Path to the plugin directory.
 */
define( 'UDAM_PATH', plugin_dir_path( __FILE__ ) );

/**
 * URL to the plugin directory.
 */
define( 'UDAM_URL', plugin_dir_url( __FILE__ ) );

/**
 * Basename of the plugin file.
 */
define( 'UDAM_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4 Autoloader for UDAuditManager namespace.
 *
 * @since 1.0.0
 *
 * @param string $class Class name to load.
 * @return void
 */
spl_autoload_register( function ( string $class ) : void {
	$prefix = 'UDAuditManager\\';
	$base_dir = UDAM_PATH;
	$len = strlen( $prefix );

	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$parts          = explode( '\\', $relative_class );

	if ( empty( $parts ) ) {
		return;
	}

	// First directory name mapped to lowercase folder (e.g. Includes, Admin, Modules).
	$parts[0] = strtolower( $parts[0] );
	$file     = $base_dir . implode( '/', $parts ) . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

/**
 * Main initializer function.
 *
 * @since 1.0.0
 * @return void
 */
function udam_init() : void {
	// Initialize the dependency injection container.
	$container = UDAuditManager\Includes\Container::instance();

	// Set up core services.
	$container->set( 'logger', new UDAuditManager\Includes\Logger() );
	$container->set( 'db', new UDAuditManager\Includes\Database() );
	$container->set( 'settings', new UDAuditManager\Includes\Settings() );
	$container->set( 'requirements', new UDAuditManager\Includes\Requirements_Checker() );
	$container->set( 'registry', new UDAuditManager\Includes\Check_Registry() );
	$container->set( 'scoring', new UDAuditManager\Includes\Scoring_Engine() );
	$container->set( 'modules_manager', new UDAuditManager\Includes\Module_Manager() );
	$container->set( 'notification_manager', new UDAuditManager\Includes\Notification_Manager() );

	$setup_completed = (bool) get_option( 'udam_toolkit_setup_completed', false );

	// REST API service is loaded to accept onboarding POST requests.
	$container->set( 'rest_api', new UDAuditManager\Includes\REST_API() );

	if ( $setup_completed ) {
		$container->set( 'engine', new UDAuditManager\Includes\Engine() );
		$container->set( 'priority_fix', new UDAuditManager\Includes\Priority_Fix_Center() );
		$container->set( 'fix_manager', new UDAuditManager\Includes\Fix_Manager() );
		$container->set( 'exporter', new UDAuditManager\Includes\Exporter_Manager() );
		$container->set( 'scheduler', new UDAuditManager\Includes\Scheduler() );
	}

	// Initialize admin menu and routes.
	if ( is_admin() ) {
		new UDAuditManager\Admin\Menu();
	}
}
add_action( 'plugins_loaded', 'udam_init' );

/**
 * Activation Hook Callback.
 *
 * @since 1.0.0
 * @return void
 */
register_activation_hook( __FILE__, function () : void {

	// Install custom database tables.
	$db = new UDAuditManager\Includes\Database();
	$db->install();

	// Set setup wizard redirect transient.
	set_transient( 'udam_toolkit_activation_redirect', true, 60 );

	// Trigger default options setup.
	$settings = new UDAuditManager\Includes\Settings();
	$settings->set_defaults();
} );

/**
 * Setup Wizard Redirection.
 *
 * @since 1.0.0
 * @return void
 */
add_action( 'admin_init', function () : void {
	$setup_completed = (bool) get_option( 'udam_toolkit_setup_completed', false );
	if ( get_transient( 'udam_toolkit_activation_redirect' ) ) {
		delete_transient( 'udam_toolkit_activation_redirect' );

		// Only redirect if it is not an AJAX action, cron, or WP-CLI run.
		if ( ! wp_doing_ajax() && ! wp_doing_cron() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			if ( $setup_completed ) {
				wp_safe_redirect( admin_url( 'admin.php?page=ud-audit-manager' ) );
			} else {
				wp_safe_redirect( admin_url( 'admin.php?page=ud-audit-manager-setup' ) );
			}
			exit;
		}
	}
} );


