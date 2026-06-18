<?php
/**
 * Security Audit Module.
 *
 * @package UDAuditManager\Modules
 * @since 1.0.0
 */

namespace UDAuditManager\Modules;

use UDAuditManager\Includes\Module_Base;
use UDAuditManager\Includes\Container;
use UDAuditManager\Includes\Check_Registry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Security_Module
 *
 * Audits core updates, debug mode indicators, file editor status, administrator user accounts,
 * outdated plugin lists, and unused template/plugin clutter.
 *
 * @package UDAuditManager\Modules
 * @since 1.0.0
 */
class Security_Module extends Module_Base {

	/**
	 * Constructor. Registers checks.
	 */
	public function __construct() {
		$registry = Container::instance()->get( 'registry' );
		if ( ! $registry instanceof Check_Registry ) {
			return;
		}

		// Core Checks.
		$registry->register_check(
			'security',
			'outdated_core',
			[
				'title'       => __( 'Outdated WordPress Core', 'ud-audit-manager' ),
				'severity'    => 'critical',
				'description' => __( 'WordPress core is out of date. Using an outdated version exposes your site to known security vulnerabilities.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'security',
			'debug_mode',
			[
				'title'       => __( 'Debug Mode Enabled (WP_DEBUG)', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'WP_DEBUG is enabled on a production site. This can expose database queries, PHP errors, and directory paths to visitors.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'security',
			'file_editing',
			[
				'title'       => __( 'File Editor Enabled', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'The theme and plugin file editor is active, allowing administrator accounts to execute arbitrary code directly from the admin panel.', 'ud-audit-manager' ),
			]
		);

		// User Accounts.
		$registry->register_check(
			'security',
			'admin_username',
			[
				'title'       => __( 'Default "admin" Username Exists', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'A user with the login username "admin" exists, which simplifies brute-force credential cracking attempts.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'security',
			'excessive_admins',
			[
				'title'       => __( 'Too Many Administrator Accounts', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'There is an excessive number of administrator accounts (more than 3), increasing the attack surface.', 'ud-audit-manager' ),
			]
		);

		// Extensibility: Plugins/Themes Vulnerabilities placeholders.
		$registry->register_check(
			'security',
			'outdated_plugins',
			[
				'title'       => __( 'Outdated Plugins Installed', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'Active or inactive plugins are missing updates. Outdated extensions are a common entry point for malware.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'security',
			'inactive_extensions',
			[
				'title'       => __( 'Inactive Plugins or Themes Present', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'Inactive extensions clutter the server and can still contain executable exploits if targeted directly.', 'ud-audit-manager' ),
			]
		);
	}

	/**
	 * Get the module slug.
	 *
	 * @return string The module slug.
	 */
	public function get_slug() : string {
		return 'security';
	}

	/**
	 * Get the module localized title.
	 *
	 * @return string The localized title.
	 */
	public function get_title() : string {
		return __( 'Security Audit', 'ud-audit-manager' );
	}

	/**
	 * Run the security audit. Complete in a single batch.
	 *
	 * @param int $run_id The current scan run ID.
	 * @param int $offset The current item offset.
	 * @param int $limit  Max items to process in this batch.
	 * @return array { completed: bool, offset: int, total: int }
	 */
	public function scan_batch( int $run_id, int $offset, int $limit ) : array {
		global $wp_version;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// 1. WordPress core update check.
		$core_updates = get_site_transient( 'update_core' );
		$outdated     = false;
		if ( isset( $core_updates->updates ) && is_array( $core_updates->updates ) ) {
			foreach ( $core_updates->updates as $update ) {
				if ( isset( $update->response ) && 'latest' !== $update->response ) {
					$outdated = true;
					break;
				}
			}
		}
		if ( $outdated ) {
			$this->add_finding(
				$run_id,
				'outdated_core',
				__( 'WordPress Core Update Available', 'ud-audit-manager' ),
				'critical',
				[
					/* translators: %s: Current WordPress version */
					'description'      => sprintf( __( 'Your current version of WordPress is %s. An update is available.', 'ud-audit-manager' ), $wp_version ),
					'why_it_matters'   => __( 'Older versions of WordPress lack critical security patches, leaving the database and files vulnerable to automated bots.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Go to Dashboard > Updates and run the latest WordPress core update immediately.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Update WordPress core.', 'ud-audit-manager' ),
					'location'         => __( 'WordPress Version Check', 'ud-audit-manager' ),
				]
			);
		}

		// 2. WP_DEBUG check.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->add_finding(
				$run_id,
				'debug_mode',
				__( 'WordPress Debug Mode is Active', 'ud-audit-manager' ),
				'high',
				[
					'description'      => __( 'The WP_DEBUG setting is configured to true inside wp-config.php.', 'ud-audit-manager' ),
					'why_it_matters'   => __( 'Active debug displays PHP warnings and error call stacks to frontend visitors, helping malicious actors map out code vulnerabilities.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Open wp-config.php, find the WP_DEBUG definition, and set it to false: define(\'WP_DEBUG\', false);', 'ud-audit-manager' ),
					'suggested_action' => __( 'Disable WP_DEBUG configuration.', 'ud-audit-manager' ),
					'location'         => 'wp-config.php',
				]
			);
		}

		// 3. DISALLOW_FILE_EDIT check.
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) || ! DISALLOW_FILE_EDIT ) {
			$this->add_finding(
				$run_id,
				'file_editing',
				__( 'WordPress Theme/Plugin Editor is Enabled', 'ud-audit-manager' ),
				'medium',
				[
					'description'      => __( 'File editing is enabled, letting admins modify themes/plugins in the dashboard.', 'ud-audit-manager' ),
					'why_it_matters'   => __( 'If an administrator account is compromised, the hacker can use the file editor to inject backdoors and malicious scripts directly into PHP files.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Open wp-config.php and add the following setting: define(\'DISALLOW_FILE_EDIT\', true);', 'ud-audit-manager' ),
					'suggested_action' => __( 'Disable File Editor access.', 'ud-audit-manager' ),
					'location'         => 'wp-config.php',
				]
			);
		}

		// 4. Default admin username check.
		if ( username_exists( 'admin' ) ) {
			$this->add_finding(
				$run_id,
				'admin_username',
				__( 'Default "admin" User Profile Detected', 'ud-audit-manager' ),
				'high',
				[
					'description'      => __( 'There is an active user with the username "admin".', 'ud-audit-manager' ),
					'why_it_matters'   => __( 'Brute-force attacks target the "admin" username first. It removes half of the puzzle for attackers cracking credentials.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Create a new user with administrator permissions and a unique username. Log in as the new administrator, delete the "admin" account, and assign their contents to your new account.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Delete or rename default "admin" user.', 'ud-audit-manager' ),
					'location'         => __( 'WordPress User Database', 'ud-audit-manager' ),
				]
			);
		}

		// 5. Excessive admins count.
		$user_counts = count_users();
		$admin_count = isset( $user_counts['avail_roles']['administrator'] ) ? (int) $user_counts['avail_roles']['administrator'] : 0;
		if ( $admin_count > 3 ) {
			$this->add_finding(
				$run_id,
				'excessive_admins',
				__( 'Excessive Administrator Accounts', 'ud-audit-manager' ),
				'medium',
				[
					/* translators: %d: Administrator account count */
					'description'      => sprintf( __( 'Found %d accounts with administrator roles.', 'ud-audit-manager' ), $admin_count ),
					'why_it_matters'   => __( 'More administrator accounts mean more password vectors for compromise. It is harder to maintain strong security configurations across many user accounts.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Review administrator roles. Demote secondary accounts to editor or author roles if they do not require full dashboard privileges.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Reduce administrator role assignments.', 'ud-audit-manager' ),
					'location'         => __( 'User Role Management', 'ud-audit-manager' ),
				]
			);
		}

		// 6. Outdated plugins check.
		$plugin_updates         = get_site_transient( 'update_plugins' );
		$outdated_plugins_count = ! empty( $plugin_updates->response ) ? count( $plugin_updates->response ) : 0;
		if ( $outdated_plugins_count > 0 ) {
			$outdated_list = [];
			foreach ( $plugin_updates->response as $slug => $data ) {
				$outdated_list[] = $slug;
			}

			$this->add_finding(
				$run_id,
				'outdated_plugins',
				__( 'Outdated Plugins Need Updates', 'ud-audit-manager' ),
				'high',
				[
					/* translators: 1: Outdated plugins count, 2: Outdated plugins list */
					'description'      => sprintf( __( 'Found %1$d plugins that are out of date: %2$s.', 'ud-audit-manager' ), $outdated_plugins_count, implode( ', ', $outdated_list ) ),
					'why_it_matters'   => __( 'Exploits targeting outdated plugins represent a massive percentage of successful WordPress hacks.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Go to Plugins > Installed Plugins, and run updates for all plugins highlighted in yellow.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Run pending plugin updates.', 'ud-audit-manager' ),
					'location'         => __( 'Plugins list', 'ud-audit-manager' ),
				]
			);
		}

		// 7. Inactive extensions check (both plugins & themes).
		$all_plugins           = get_plugins();
		$active_plugins        = get_option( 'active_plugins', [] );
		$inactive_plugin_count = count( $all_plugins ) - count( is_array( $active_plugins ) ? $active_plugins : [] );

		$all_themes        = wp_get_themes();
		$active_theme      = wp_get_theme();
		$active_theme_name = $active_theme->get_stylesheet();
		$parent_theme      = $active_theme->parent();
		$parent_theme_name = $parent_theme ? $parent_theme->get_stylesheet() : '';

		$inactive_theme_count = 0;
		foreach ( $all_themes as $theme_slug => $theme_obj ) {
			if ( $theme_slug !== $active_theme_name && $theme_slug !== $parent_theme_name ) {
				$inactive_theme_count++;
			}
		}

		if ( $inactive_plugin_count > 0 || $inactive_theme_count > 0 ) {
			/* translators: 1: Inactive plugins count, 2: Inactive themes count */
			$desc = sprintf( __( 'There are %1$d inactive plugins and %2$d inactive themes installed.', 'ud-audit-manager' ), $inactive_plugin_count, $inactive_theme_count );
			$this->add_finding(
				$run_id,
				'inactive_clutter',
				__( 'Inactive Plugins or Themes Present', 'ud-audit-manager' ),
				'low',
				[
					'description'      => $desc,
					'why_it_matters'   => __( 'Inactive plugins and themes increase disk usage and represent a vulnerability if they contain unpatched security issues.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Clean up the site by permanently deleting any plugins or themes that are not active.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Delete inactive plugins and themes.', 'ud-audit-manager' ),
					'location'         => __( 'Plugins & Themes Directories', 'ud-audit-manager' ),
				]
			);
		}

		return [
			'completed' => true,
			'offset'    => 0,
			'total'     => 1,
		];
	}
}
