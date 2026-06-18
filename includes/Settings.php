<?php
/**
 * Settings configuration and retrieval class.
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
 * Class Settings
 *
 * Handles plugin configurations, default options initialization, settings storage,
 * and strict input parameter sanitization.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
class Settings {

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'udam_toolkit_settings';

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private ?array $settings = null;

	/**
	 * Constructor. Pre-loads settings.
	 */
	public function __construct() {
		$this->load_settings();
	}

	/**
	 * Loads settings from the database, falling back to defaults if empty.
	 *
	 * @return void
	 */
	private function load_settings() : void {
		$saved          = get_option( self::OPTION_NAME, [] );
		$defaults       = $this->get_defaults();
		$this->settings = wp_parse_args( is_array( $saved ) ? $saved : [], $defaults );
	}

	/**
	 * Get default settings values.
	 *
	 * @return array The default settings values.
	 */
	public function get_defaults() : array {
		$defaults = [
			'modules'           => [
				'seo'           => true,
				'performance'   => true,
				'accessibility' => true,
				'security'      => true,
				'database'      => true,
				'content'       => true,
				'plugin'        => true,
				'theme'         => true,
			],
			'severity_weights'  => [
				'critical' => 25,
				'high'     => 15,
				'medium'   => 8,
				'low'      => 3,
				'info'     => 0,
			],
			'cron_frequency'    => 'disabled',
			'report_retention'  => 25,
			'dark_mode'         => false,
			'perf_limits_posts' => 50,
		];

		/**
		 * Filters the default settings.
		 *
		 * @since 1.0.1
		 * @param array $defaults Default settings values.
		 */
		return apply_filters( 'udam_settings_defaults', $defaults );
	}

	/**
	 * Retrieve all settings.
	 *
	 * @since 1.0.1
	 * @return array The settings array.
	 */
	public function get_all() : array {
		if ( null === $this->settings ) {
			$this->load_settings();
		}
		return $this->settings;
	}

	/**
	 * Retrieve a specific setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Optional default fallback.
	 * @return mixed The setting value or default value.
	 */
	public function get( string $key, $default = null ) {
		if ( null === $this->settings ) {
			$this->load_settings();
		}
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Update settings values.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True on success, false on failure.
	 */
	public function set( string $key, $value ) : bool {
		if ( null === $this->settings ) {
			$this->load_settings();
		}

		if ( in_array( $key, [ 'report_retention' ], true ) ) {
			$value = absint( $value );
		} elseif ( in_array( $key, [ 'dark_mode' ], true ) ) {
			$value = (bool) $value;
		}

		$this->settings[ $key ] = $value;
		return (bool) update_option( self::OPTION_NAME, $this->settings );
	}

	/**
	 * Mass update setting array with strict sanitization.
	 *
	 * @param array $settings Array of settings values.
	 * @return bool True on success, false on failure.
	 */
	public function update_all( array $settings ) : bool {
		$defaults  = $this->get_defaults();
		$sanitized = [];

		if ( isset( $settings['modules'] ) && is_array( $settings['modules'] ) ) {
			$sanitized['modules'] = [];
			foreach ( $defaults['modules'] as $module => $default_val ) {
				$val = isset( $settings['modules'][ $module ] ) ? (bool) $settings['modules'][ $module ] : false;
				$sanitized['modules'][ $module ] = $val;
			}
		}

		if ( isset( $settings['severity_weights'] ) && is_array( $settings['severity_weights'] ) ) {
			$sanitized['severity_weights'] = [];
			foreach ( $defaults['severity_weights'] as $severity => $default_weight ) {
				$weight = isset( $settings['severity_weights'][ $severity ] ) ? absint( $settings['severity_weights'][ $severity ] ) : $default_weight;
				$sanitized['severity_weights'][ $severity ] = $weight;
			}
		}

		if ( isset( $settings['cron_frequency'] ) ) {
			$freq = sanitize_key( $settings['cron_frequency'] );
			if ( in_array( $freq, [ 'disabled', 'daily', 'weekly', 'monthly' ], true ) ) {
				$sanitized['cron_frequency'] = $freq;
			}
		}

		if ( isset( $settings['report_retention'] ) ) {
			$sanitized['report_retention'] = absint( $settings['report_retention'] );
		}

		if ( isset( $settings['perf_limits_posts'] ) ) {
			$sanitized['perf_limits_posts'] = absint( $settings['perf_limits_posts'] );
		}

		if ( isset( $settings['dark_mode'] ) ) {
			$sanitized['dark_mode'] = (bool) $settings['dark_mode'];
		}

		$this->settings = wp_parse_args( $sanitized, $defaults );
		return (bool) update_option( self::OPTION_NAME, $this->settings );
	}

	/**
	 * Returns the list of active module slugs.
	 *
	 * @return array List of enabled module slugs.
	 */
	public function get_enabled_modules() : array {
		$manager = Container::instance()->get( 'modules_manager' );
		return $manager instanceof Module_Manager ? array_keys( $manager->get_enabled_modules() ) : [];
	}

	/**
	 * Initialize default options.
	 *
	 * @return void
	 */
	public function set_defaults() : void {
		if ( ! get_option( self::OPTION_NAME ) ) {
			update_option( self::OPTION_NAME, $this->get_defaults() );
		}
	}

	/**
	 * Clears options from the database.
	 *
	 * @return void
	 */
	public function clear_settings() : void {
		delete_option( self::OPTION_NAME );
		$this->settings = null;
	}
}
