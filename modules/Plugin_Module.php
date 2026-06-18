<?php
/**
 * Plugin Health Audit Module.
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
 * Class Plugin_Module
 *
 * Checks total active plugins, inactive plugin count, and identifies conflicting
 * SEO or caching plugins running concurrently.
 *
 * @package UDAuditManager\Modules
 * @since 1.0.0
 */
class Plugin_Module extends Module_Base {

	/**
	 * Constructor. Registers checks.
	 */
	public function __construct() {
		$registry = Container::instance()->get( 'registry' );
		if ( ! $registry instanceof Check_Registry ) {
			return;
		}

		$registry->register_check(
			'plugin',
			'high_active_count',
			[
				'title'       => __( 'High Number of Active Plugins', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Too many active plugins increase query loads, PHP memory footprint, and potential software compatibility conflicts.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'plugin',
			'inactive_clutter',
			[
				'title'       => __( 'Inactive Plugins Accumulation', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'Inactive plugins are unnecessary weight on disk storage and increase potential security entry points.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'plugin',
			'duplicate_seo',
			[
				'title'       => __( 'Duplicate SEO Plugins Active', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'Multiple SEO plugins are active simultaneously, causing meta-tag conflicts and database overhead.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'plugin',
			'duplicate_caching',
			[
				'title'       => __( 'Duplicate Caching Plugins Active', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'Multiple page caching plugins are active, which can break site rendering layouts and exhaust resources.', 'ud-audit-manager' ),
			]
		);
	}

	/**
	 * Get the module slug.
	 *
	 * @return string The module slug.
	 */
	public function get_slug() : string {
		return 'plugin';
	}

	/**
	 * Get the module localized title.
	 *
	 * @return string The localized title.
	 */
	public function get_title() : string {
		return __( 'Plugin Audit', 'ud-audit-manager' );
	}

	/**
	 * Run the plugin health audit in a single step.
	 *
	 * @param int $run_id The current scan run ID.
	 * @param int $offset The current item offset.
	 * @param int $limit  Max items to process in this batch.
	 * @return array { completed: bool, offset: int, total: int }
	 */
	public function scan_batch( int $run_id, int $offset, int $limit ) : array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugins = get_option( 'active_plugins', [] );
		$all_plugins    = get_plugins();

		$active_count   = count( is_array( $active_plugins ) ? $active_plugins : [] );
		$total_count    = count( $all_plugins );
		$inactive_count = $total_count - $active_count;

		// 1. High active count check (>30).
		if ( $active_count > 30 ) {
			$this->add_finding(
				$run_id,
				'high_active_count',
				__( 'Too Many Active Plugins Installed', 'ud-audit-manager' ),
				'medium',
				[
					/* translators: %d: Number of active plugins */
					'description'      => sprintf( __( 'You have %d active plugins installed on this site.', 'ud-audit-manager' ), $active_count ),
					'why_it_matters'   => __( 'Every active plugin appends script hooks to WordPress bootstrap cycle, extending compile times and memory sizes.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Deactivate and delete plugins that are not critical to your website operations, or merge features.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Audit and prune active plugins list.', 'ud-audit-manager' ),
					'location'         => __( 'Plugins List Dashboard', 'ud-audit-manager' ),
				]
			);
		}

		// 2. Inactive clutter check.
		if ( $inactive_count > 5 ) {
			$this->add_finding(
				$run_id,
				'inactive_clutter',
				__( 'Excessive Inactive Plugins Clutter', 'ud-audit-manager' ),
				'low',
				[
					/* translators: %d: Number of inactive plugins */
					'description'      => sprintf( __( 'You have %d inactive plugins cluttering your installation.', 'ud-audit-manager' ), $inactive_count ),
					'why_it_matters'   => __( 'Inactive plugins are still stored on the server. If they contain vulnerabilities, they can be targeted directly.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Permanently delete inactive plugins from the plugins dashboard screen.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Delete inactive plugin folders.', 'ud-audit-manager' ),
					'location'         => __( 'Plugins Manager screen', 'ud-audit-manager' ),
				]
			);
		}

		// 3. Duplicate SEO Plugins check.
		$seo_detect = [];
		$seo_slugs  = [
			'wordpress-seo/wp-seo.php'                   => 'Yoast SEO',
			'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
			'seo-by-rank-math/rank-math.php'             => 'Rank Math SEO',
			'the-keytechnology-seo-tool/seopress.php'     => 'SEOPress',
		];
		foreach ( $seo_slugs as $slug => $name ) {
			if ( in_array( $slug, $active_plugins, true ) ) {
				$seo_detect[] = $name;
			}
		}
		if ( count( $seo_detect ) > 1 ) {
			$this->add_finding(
				$run_id,
				'duplicate_seo',
				__( 'Conflicting SEO Plugins Active', 'ud-audit-manager' ),
				'high',
				[
					/* translators: %s: Comma-separated list of active SEO plugins */
					'description'      => sprintf( __( 'Detected multiple active SEO plugins: %s.', 'ud-audit-manager' ), implode( ', ', $seo_detect ) ),
					'why_it_matters'   => __( 'Having multiple SEO plugins triggers double metadata schemas, confusing crawlers and slowing indexing.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Select your primary SEO toolkit and deactivate all other duplicate installations.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Deactivate conflicting SEO plugins.', 'ud-audit-manager' ),
					'location'         => __( 'Plugins Registry', 'ud-audit-manager' ),
				]
			);
		}

		// 4. Duplicate Caching Plugins check.
		$cache_detect = [];
		$cache_slugs  = [
			'wp-super-cache/wp-cache.php'         => 'WP Super Cache',
			'w3-total-cache/w3tc.php'             => 'W3 Total Cache',
			'wp-fastest-cache/wpFastestCache.php' => 'WP Fastest Cache',
			'wp-rocket/wp-rocket.php'             => 'WP Rocket',
			'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
		];
		foreach ( $cache_slugs as $slug => $name ) {
			if ( in_array( $slug, $active_plugins, true ) ) {
				$cache_detect[] = $name;
			}
		}
		if ( count( $cache_detect ) > 1 ) {
			$this->add_finding(
				$run_id,
				'duplicate_caching',
				__( 'Conflicting Cache Plugins Active', 'ud-audit-manager' ),
				'high',
				[
					/* translators: %s: Comma-separated list of active caching plugins */
					'description'      => sprintf( __( 'Detected multiple active page cache plugins: %s.', 'ud-audit-manager' ), implode( ', ', $cache_detect ) ),
					'why_it_matters'   => __( 'Multiple caching plugins interfere with server redirects, clear-caching events, and htaccess files, breaking page layouts.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Choose a single caching manager suitable for your server stack and deactivate other candidates.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Keep only one caching plugin active.', 'ud-audit-manager' ),
					'location'         => __( 'Plugins Registry', 'ud-audit-manager' ),
				]
			);
		}

		// Save plugin metrics (deprecated).
		$this->save_metric( $run_id, 'active_plugins_count', $active_count );

		return [
			'completed' => true,
			'offset'    => 0,
			'total'     => 1,
		];
	}
}
