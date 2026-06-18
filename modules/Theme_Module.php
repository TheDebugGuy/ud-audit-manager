<?php
/**
 * Theme Quality Audit Module.
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
 * Class Theme_Module
 *
 * Checks site icon (favicon) settings, custom branding logo presence, and social network
 * integration links within menus.
 *
 * @package UDAuditManager\Modules
 * @since 1.0.0
 */
class Theme_Module extends Module_Base {

	/**
	 * Constructor. Registers checks.
	 */
	public function __construct() {
		$registry = Container::instance()->get( 'registry' );
		if ( ! $registry instanceof Check_Registry ) {
			return;
		}

		$registry->register_check(
			'theme',
			'missing_favicon',
			[
				'title'       => __( 'Missing Site Favicon Icon', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'The site does not have a favicon configured, affecting tab presentation in modern browsers.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'theme',
			'missing_logo',
			[
				'title'       => __( 'Custom Logo Not Configured', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'No custom brand logo has been registered in the Customizer settings for the current active theme.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'theme',
			'missing_social_links',
			[
				'title'       => __( 'Social Media Profile Links Missing', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'No external links pointing to profiles like Facebook, Twitter, or Instagram were found in active menus.', 'ud-audit-manager' ),
			]
		);
	}

	/**
	 * Get the module slug.
	 *
	 * @return string The module slug.
	 */
	public function get_slug() : string {
		return 'theme';
	}

	/**
	 * Get the module localized title.
	 *
	 * @return string The localized title.
	 */
	public function get_title() : string {
		return __( 'Theme Audit', 'ud-audit-manager' );
	}

	/**
	 * Run the theme quality audit in a single step.
	 *
	 * @param int $run_id The current scan run ID.
	 * @param int $offset The current item offset.
	 * @param int $limit  Max items to process in this batch.
	 * @return array { completed: bool, offset: int, total: int }
	 */
	public function scan_batch( int $run_id, int $offset, int $limit ) : array {

		// 1. Favicon check.
		$favicon_url = get_site_icon_url();
		if ( empty( $favicon_url ) ) {
			$this->add_finding(
				$run_id,
				'missing_favicon',
				__( 'Site Icon (Favicon) is Missing', 'ud-audit-manager' ),
				'medium',
				[
					'description'      => __( 'No site icon has been uploaded under Customizer properties.', 'ud-audit-manager' ),
					'why_it_matters'   => __( 'Favicons brand browser tabs, mobile homescreens, and bookmarks. Without it, the default browser sheet icon is shown.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Go to Appearance > Customize > Site Identity, and upload a square image of at least 512x512 pixels.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Upload a Site Icon in Customizer.', 'ud-audit-manager' ),
					'location'         => __( 'Site Identity Settings', 'ud-audit-manager' ),
				]
			);
		}

		// 2. Custom Logo check.
		if ( ! has_custom_logo() ) {
			$this->add_finding(
				$run_id,
				'missing_logo',
				__( 'Custom Site Logo is Missing', 'ud-audit-manager' ),
				'low',
				[
					'description'      => __( 'No custom logo has been assigned inside the theme customizer.', 'ud-audit-manager' ),
					'why_it_matters'   => __( 'Custom logos strengthen business branding presentation in headers instead of default text fallback titles.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Go to Appearance > Customize > Header/Site Identity, and upload your brand logo file.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Upload a custom theme logo.', 'ud-audit-manager' ),
					'location'         => __( 'Theme Settings Customizer', 'ud-audit-manager' ),
				]
			);
		}

		// 3. Social profiles check in menus.
		$menus      = wp_get_nav_menus();
		$has_social = false;

		if ( ! empty( $menus ) && is_array( $menus ) ) {
			foreach ( $menus as $menu ) {
				if ( ! isset( $menu->term_id ) ) {
					continue;
				}
				$items = wp_get_nav_menu_items( $menu->term_id );
				if ( ! empty( $items ) && is_array( $items ) ) {
					foreach ( $items as $item ) {
						// Look for social domains in URL.
						if ( isset( $item->url ) && preg_match( '/facebook\.com|twitter\.com|x\.com|instagram\.com|linkedin\.com|youtube\.com/i', $item->url ) ) {
							$has_social = true;
							break 2;
						}
					}
				}
			}
		}

		if ( ! $has_social ) {
			$this->add_finding(
				$run_id,
				'missing_social_links',
				__( 'No Social Network Profiles Linked', 'ud-audit-manager' ),
				'low',
				[
					'description'      => __( 'No active navigation menus contain links to Facebook, Instagram, YouTube, X, or LinkedIn.', 'ud-audit-manager' ),
					'why_it_matters'   => __( 'Social links allow customers to connect on other channels and build signals of a legitimate, active brand.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Go to Appearance > Menus, create a custom link item with your profile URL, and add it to your header or footer menu.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Add social profile links to navigation.', 'ud-audit-manager' ),
					'location'         => __( 'Navigation Menus config screen', 'ud-audit-manager' ),
				]
			);
		}

		// Save theme meta (deprecated).
		$active_theme = wp_get_theme();
		$this->save_metric( $run_id, 'active_theme_version', $active_theme->get( 'Version' ) );

		return [
			'completed' => true,
			'offset'    => 0,
			'total'     => 1,
		];
	}
}
