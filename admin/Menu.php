<?php
namespace UDAuditManager\Admin;

use UDAuditManager\Includes\Container;
use UDAuditManager\Includes\Module_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu Router and Asset Loader.
 */
class Menu {

	/**
	 * Constructor. Hook menu registration and asset enqueues.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu_pages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'admin_body_class', [ $this, 'add_admin_body_class' ] );
	}

	/**
	 * Registers all audit pages in the admin dashboard.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_menu_pages() : void {
		$capability      = 'manage_options';
		$setup_completed = (bool) get_option( 'udam_toolkit_setup_completed', false );

		if ( ! $setup_completed ) {
			// Before setup completion: Show only UD Audit Manager parent menu and Setup Wizard submenu.
			add_menu_page(
				__( 'UD Audit Manager', 'ud-audit-manager' ),
				__( 'UD Audit Manager', 'ud-audit-manager' ),
				$capability,
				'ud-audit-manager-setup',
				[ $this, 'render_setup_wizard' ],
				'dashicons-performance',
				80
			);

			add_submenu_page(
				'ud-audit-manager-setup',
				__( 'Setup Wizard', 'ud-audit-manager' ),
				__( 'Setup Wizard', 'ud-audit-manager' ),
				$capability,
				'ud-audit-manager-setup',
				[ $this, 'render_setup_wizard' ]
			);
			return;
		}

		$parent_slug = 'ud-audit-manager';

		// After setup completion: Show normal menu structure.
		add_menu_page(
			__( 'UD Audit Manager', 'ud-audit-manager' ),
			__( 'UD Audit Manager', 'ud-audit-manager' ),
			$capability,
			$parent_slug,
			[ $this, 'render_dashboard_page' ],
			'dashicons-performance',
			80
		);

		add_submenu_page(
			$parent_slug,
			__( 'Dashboard', 'ud-audit-manager' ),
			__( 'Dashboard', 'ud-audit-manager' ),
			$capability,
			$parent_slug,
			[ $this, 'render_dashboard_page' ]
		);

		add_submenu_page(
			$parent_slug,
			__( 'Full Site Audit Results', 'ud-audit-manager' ),
			__( 'Full Site Audit Results', 'ud-audit-manager' ),
			$capability,
			'ud-audit-manager-audit',
			[ $this, 'render_audit_page' ]
		);

		// Get dynamically registered, enabled modules from Module_Manager.
		$modules_manager = Container::instance()->get( 'modules_manager' );
		$enabled_modules = $modules_manager instanceof Module_Manager ? $modules_manager->get_enabled_modules() : [];

		$modules_labels = [
			'seo'           => __( 'SEO Audit', 'ud-audit-manager' ),
			'performance'   => __( 'Performance Audit', 'ud-audit-manager' ),
			'accessibility' => __( 'Accessibility Audit', 'ud-audit-manager' ),
			'security'      => __( 'Security Audit', 'ud-audit-manager' ),
			'database'      => __( 'Database Audit', 'ud-audit-manager' ),
			'content'       => __( 'Content Audit', 'ud-audit-manager' ),
			'plugin'        => __( 'Plugin Audit', 'ud-audit-manager' ),
			'theme'         => __( 'Theme Audit', 'ud-audit-manager' ),
		];

		/**
		 * Filters the list of modules registered in the admin submenus.
		 *
		 * @since 1.0.1
		 * @param array $modules Array of module slugs to labels.
		 */
		$modules_labels = apply_filters( 'udam_admin_modules', $modules_labels );

		foreach ( $enabled_modules as $slug => $class ) {
			if ( isset( $modules_labels[ $slug ] ) ) {
				$title = $modules_labels[ $slug ];
				add_submenu_page(
					$parent_slug,
					$title,
					str_replace( __( ' Audit', 'ud-audit-manager' ), '', $title ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
					$capability,
					'ud-audit-manager-' . $slug,
					[ $this, 'render_audit_page' ]
				);
			}
		}

		add_submenu_page(
			$parent_slug,
			__( 'Audit Reports', 'ud-audit-manager' ),
			__( 'Reports', 'ud-audit-manager' ),
			$capability,
			'ud-audit-manager-reports',
			[ $this, 'render_reports_page' ]
		);

		add_submenu_page(
			$parent_slug,
			__( 'Audit Settings', 'ud-audit-manager' ),
			__( 'Settings', 'ud-audit-manager' ),
			$capability,
			'ud-audit-manager-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Load admin stylesheet and javascript.
	 *
	 * @since 1.0.0
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ) : void {
		// Enqueue assets only on plugin pages.
		if ( false === strpos( $hook, 'ud-audit-manager' ) ) {
			return;
		}

		// Enqueue custom CSS.
		wp_enqueue_style( 'udam-style', UDAM_URL . 'assets/css/admin-style.css', [], UDAM_VERSION );

		// Enqueue Chart.js.
		wp_register_script(
			'udam-chartjs',
			UDAM_URL . 'assets/js/chart.umd.min.js',
			[],
			'4.5.0',
			true
		);
		wp_enqueue_script( 'udam-chartjs' );

		// Enqueue custom JS.
		wp_enqueue_script(
			'udam-admin',
			UDAM_URL . 'assets/js/admin-script.js',
			[ 'jquery', 'udam-chartjs' ],
			UDAM_VERSION,
			true
		);

		$settings = Container::instance()->get( 'settings' );

		$notices_manager = Container::instance()->get( 'notification_manager' );
		$queued_notices  = $notices_manager ? $notices_manager->get_queued_notices() : [];

		// Pass REST API configurations to Javascript.
		wp_localize_script( 'udam-admin', 'udamAdmin', [
			'rest_url'       => esc_url_raw( get_rest_url( null, 'ud-audit-manager/v1' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'page'           => $this->get_current_page_slug(),
			'dark_mode'      => $settings ? (bool) $settings->get( 'dark_mode', false ) : false,
			'queued_notices' => $queued_notices,
			'l10n'           => [
				'scan_started'    => __( 'Starting scan execution...', 'ud-audit-manager' ),
				'scan_completed'  => __( 'Audit run completed successfully!', 'ud-audit-manager' ),
				'scan_failed'     => __( 'Audit execution failed. Please check logs.', 'ud-audit-manager' ),
				'confirm_cleanup' => __( 'Are you sure you want to delete this data? This action is irreversible.', 'ud-audit-manager' ),
				'fixing'          => __( 'Applying fix...', 'ud-audit-manager' ),
				'fixed_success'   => __( 'Issue resolved successfully!', 'ud-audit-manager' ),
				'fixing_failed'   => __( 'Failed to apply automatic fix.', 'ud-audit-manager' ),
				'no_findings'     => __( 'No issues found. Excellent work!', 'ud-audit-manager' ),
			],
		] );
	}

	/**
	 * Callback: Renders Dashboard Page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_dashboard_page() : void {
		$controller = new Dashboard();
		$controller->render();
	}

	/**
	 * Callback: Renders general Auditing Module Pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_audit_page() : void {
		$controller = new Audit_Page();
		$controller->render();
	}

	/**
	 * Callback: Renders Reports page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_reports_page() : void {
		$controller = new Reports();
		$controller->render();
	}

	/**
	 * Callback: Renders Settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings_page() : void {
		$controller = new Settings_Page();
		$controller->render();
	}

	/**
	 * Callback: Renders Setup wizard.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_setup_wizard() : void {
		$this->load_template( 'setup-wizard' );
	}

	/**
	 * Helper: Enforces secure template inclusion.
	 *
	 * @since 1.0.0
	 * @param string $template_name Template file basename.
	 * @return void
	 */
	private function load_template( string $template_name ) : void {
		$file = UDAM_PATH . 'templates/' . $template_name . '.php';
		if ( file_exists( $file ) ) {
			include_once $file;
		} else {
			/* translators: %s: template name */
			echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'Template "%s" is missing.', 'ud-audit-manager' ), $template_name ) ) . '</p></div>';
		}
	}

	/**
	 * Helper: Identifies active sub-page slug.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_current_page_slug() : string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only access to page slug parameter.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'ud-audit-manager' === $page ) {
			return 'dashboard';
		}
		if ( 0 === strpos( $page, 'ud-audit-manager-' ) ) {
			return str_replace( 'ud-audit-manager-', '', $page );
		}
		return 'dashboard';
	}

	/**
	 * Adds dynamic class to the admin body element for dark mode styling.
	 *
	 * @since 1.0.1
	 * @param string $classes Space-separated list of admin body classes.
	 * @return string Modified list of body classes.
	 */
	public function add_admin_body_class( string $classes ) : string {
		$screen = get_current_screen();
		if ( $screen && false !== strpos( $screen->id, 'ud-audit-manager' ) ) {
			$settings = Container::instance()->get( 'settings' );
			if ( $settings && $settings->get( 'dark_mode', false ) ) {
				$classes .= ' udam-dark-mode ';
			}
		}
		return $classes;
	}
}
