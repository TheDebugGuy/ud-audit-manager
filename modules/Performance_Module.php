<?php
/**
 * Performance Audit Module.
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
 * Class Performance_Module
 *
 * Scans stylesheets/script counts, identifies attachments lacking WebP/AVIF format,
 * checks lazy loading, explicit width/height dimensions, page cache status, and compression.
 *
 * @package UDAuditManager\Modules
 * @since 1.0.0
 */
class Performance_Module extends Module_Base {

	/**
	 * Constructor. Registers checks.
	 */
	public function __construct() {
		$registry = Container::instance()->get( 'registry' );
		if ( ! $registry instanceof Check_Registry ) {
			return;
		}

		// Assets Checks.
		$registry->register_check(
			'performance',
			'excessive_assets',
			[
				'title'       => __( 'Excessive Stylesheets or Scripts', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'The site loads too many external CSS or JS resources, increasing HTTP requests and slowing page loads.', 'ud-audit-manager' ),
			]
		);

		// Image Optimization Checks.
		$registry->register_check(
			'performance',
			'non_modern_images',
			[
				'title'       => __( 'Images Lacking Modern WebP/AVIF Format', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'Media library contains PNG or JPG images instead of next-gen formats like WebP or AVIF which compress much better.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'performance',
			'missing_lazy_load',
			[
				'title'       => __( 'Images Lacking Lazy Loading', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Images are loaded immediately on page load even if they are below the fold, wasting bandwidth.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'performance',
			'missing_img_dimensions',
			[
				'title'       => __( 'Images Missing Explicit Dimensions', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'Images without defined width and height attributes cause Cumulative Layout Shifts (CLS) as pages load.', 'ud-audit-manager' ),
			]
		);

		// Server Cache Checks.
		$registry->register_check(
			'performance',
			'cache_disabled',
			[
				'title'       => __( 'Page Cache Plugin Not Detected', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'No active WordPress page caching plugin (e.g. WP Rocket, LiteSpeed, WP Super Cache) was detected.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'performance',
			'compression_disabled',
			[
				'title'       => __( 'Gzip or Brotli Compression Disabled', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'Web server response compression is not enabled, leading to larger file transfer sizes.', 'ud-audit-manager' ),
			]
		);
	}

	/**
	 * Get the module slug.
	 *
	 * @return string The module slug.
	 */
	public function get_slug() : string {
		return 'performance';
	}

	/**
	 * Get the module localized title.
	 *
	 * @return string The localized title.
	 */
	public function get_title() : string {
		return __( 'Performance Audit', 'ud-audit-manager' );
	}

	/**
	 * Run the batch performance audit.
	 *
	 * @param int $run_id The current scan run ID.
	 * @param int $offset The current item offset.
	 * @param int $limit  Max items to process in this batch.
	 * @return array { completed: bool, offset: int, total: int }
	 */
	public function scan_batch( int $run_id, int $offset, int $limit ) : array {
		global $wpdb;

		// 1. Global / Server Performance Checks - run on first step.
		if ( 0 === $offset ) {
			$this->scan_global_performance_checks( $run_id );
		}

		// 2. Fetch published pages and posts count.
		/**
		 * Direct database query required.
		 *
		 * Audit results and statistics must be fetched in real-time.
		 * Cached values may return stale scan information.
		 *
		 * All dynamic values are sanitized and passed through
		 * $wpdb->prepare() before execution.
		 */
		$total_posts = (int) wp_count_posts( 'post' )->publish + (int) wp_count_posts( 'page' )->publish;

		if ( 0 === $total_posts ) {
			return [
				'completed' => true,
				'offset'    => 0,
				'total'     => 0,
			];
		}

		// 3. Query batch of posts.
		/**
		 * Direct database query required.
		 *
		 * Audit results and statistics must be fetched in real-time.
		 * Cached values may return stale scan information.
		 *
		 * All dynamic values are sanitized and passed through
		 * $wpdb->prepare() before execution.
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is required for real-time audit accuracy; caching is bypassed to prevent stale scan results.
		$posts_query = $wpdb->prepare(
			"
			SELECT ID, post_title, post_content, post_type 
			FROM {$wpdb->posts} 
			WHERE post_status = 'publish' 
			AND post_type IN ('post', 'page') 
			ORDER BY ID ASC 
			LIMIT %d OFFSET %d
			",
			$limit,
			$offset
		);

		/**
		 * Direct database query required.
		 *
		 * Audit results and statistics must be fetched in real-time.
		 * Cached values may return stale scan information.
		 *
		 * All dynamic values are sanitized and passed through
		 * $wpdb->prepare() before execution.
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is required for real-time audit accuracy; caching is bypassed to prevent stale scan results.
		$posts = $wpdb->get_results( $posts_query );

		// 4. Analyze images inside content.
		foreach ( $posts as $post ) {
			$post_id   = (int) $post->ID;
			$content   = $post->post_content;
			$post_link = get_permalink( $post_id );
			$location  = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $post_link ), esc_html( $post->post_title ?: __( 'Untitled Post', 'ud-audit-manager' ) ) );

			preg_match_all( '/<img\b[^>]*>/is', $content, $images );

			if ( ! empty( $images[0] ) ) {
				$missing_lazy_count = 0;
				$missing_dim_count  = 0;

				foreach ( $images[0] as $img_tag ) {
					// Check lazy loading.
					if ( ! preg_match( '/loading\s*=\s*(["\'])lazy\1/is', $img_tag ) ) {
						$missing_lazy_count++;
					}

					// Check width and height attributes.
					$has_width  = preg_match( '/width\s*=\s*(["\'])\d+\1/is', $img_tag );
					$has_height = preg_match( '/height\s*=\s*(["\'])\d+\1/is', $img_tag );
					if ( ! $has_width || ! $has_height ) {
						$missing_dim_count++;
					}
				}

				if ( $missing_lazy_count > 0 ) {
					$this->add_finding(
						$run_id,
						'missing_lazy_load',
						__( 'Content Images Lacking Lazy Loading', 'ud-audit-manager' ),
						'medium',
						[
							/* translators: %d: Missing lazy load count */
							'description'      => sprintf( __( 'Found %d content images missing loading="lazy" tags.', 'ud-audit-manager' ), $missing_lazy_count ),
							'why_it_matters'   => __( 'Lazy loading delays offscreen image loading, speeding up mobile parsing and rendering speeds.', 'ud-audit-manager' ),
							'how_to_fix'       => __( 'Configure your caching plugin or optimization suite to force lazy loading on content images.', 'ud-audit-manager' ),
							'suggested_action' => __( 'Enable image lazy loading.', 'ud-audit-manager' ),
							'location'         => $location,
						]
					);
				}

				if ( $missing_dim_count > 0 ) {
					$this->add_finding(
						$run_id,
						'missing_img_dimensions',
						__( 'Images Missing Explicit Width/Height', 'ud-audit-manager' ),
						'low',
						[
							/* translators: %d: Missing dimensions image count */
							'description'      => sprintf( __( 'Found %d inline images without explicit width or height attributes.', 'ud-audit-manager' ), $missing_dim_count ),
							'why_it_matters'   => __( 'Images without width/height force the browser to recalculate element flow upon loading, triggering layout shifts (CLS).', 'ud-audit-manager' ),
							'how_to_fix'       => __( 'Edit the post and update image attributes inside block editors, or verify your theme sets dimensions.', 'ud-audit-manager' ),
							'suggested_action' => __( 'Set width/height dimensions on images.', 'ud-audit-manager' ),
							'location'         => $location,
						]
					);
				}
			}
		}

		$next_offset = $offset + count( $posts );
		$completed   = $next_offset >= $total_posts;

		return [
			'completed' => $completed,
			'offset'    => $completed ? 0 : $next_offset,
			'total'     => $total_posts,
		];
	}

	/**
	 * Run server and files performance checks.
	 *
	 * @param int $run_id The current scan run ID.
	 * @return void
	 */
	private function scan_global_performance_checks( int $run_id ) : void {
		global $wpdb;

		// 1. Non-modern image formats query (attachments table).
		/**
		 * Direct database query required.
		 *
		 * Audit results and statistics must be fetched in real-time.
		 * Cached values may return stale scan information.
		 *
		 * All dynamic values are sanitized and passed through
		 * $wpdb->prepare() before execution.
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is required for real-time audit accuracy; caching is bypassed to prevent stale scan results.
		$non_modern_images = (int) $wpdb->get_var( "
			SELECT COUNT(ID) FROM {$wpdb->posts} 
			WHERE post_type = 'attachment' 
			AND post_mime_type IN ('image/jpeg', 'image/png')
		" );

		if ( $non_modern_images > 10 ) {
			$this->add_finding(
				$run_id,
				'non_modern_images',
				__( 'Unoptimized JPEG or PNG Images', 'ud-audit-manager' ),
				'high',
				[
					/* translators: %d: Non-modern images count */
					'description'      => sprintf( __( 'Found %d attachment images using JPEG or PNG format.', 'ud-audit-manager' ), $non_modern_images ),
					'why_it_matters'   => __( 'Next-gen formats like WebP or AVIF compress up to 30-50% better than standard JPG/PNG without quality loss.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Activate an image compression plugin that converts existing images to WebP format, or upload pre-converted images.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Convert attachment images to next-gen formats.', 'ud-audit-manager' ),
					/* translators: %d: Non-modern images count */
					'location'         => sprintf( __( 'Media Library (%d attachments)', 'ud-audit-manager' ), $non_modern_images ),
				]
			);
		}

		// 2. Page Caching Plugin check.
		$cache_plugins = [
			'wp-super-cache/wp-cache.php',
			'w3-total-cache/w3tc.php',
			'wp-fastest-cache/wpFastestCache.php',
			'wp-rocket/wp-rocket.php',
			'litespeed-cache/litespeed-cache.php',
		];
		$active_plugins = get_option( 'active_plugins', [] );
		$cache_found    = false;

		foreach ( $cache_plugins as $plugin ) {
			if ( in_array( $plugin, $active_plugins, true ) ) {
				$cache_found = true;
				break;
			}
		}

		// Also check advanced-cache constant.
		if ( ! $cache_found && defined( 'WP_CACHE' ) && WP_CACHE ) {
			$cache_found = true;
		}

		if ( ! $cache_found ) {
			$this->add_finding(
				$run_id,
				'cache_disabled',
				__( 'Caching Layer is Inactive', 'ud-audit-manager' ),
				'high',
				[
					'description'      => __( 'No standard page cache plugin or caching constant is active.', 'ud-audit-manager' ),
					'why_it_matters'   => __( 'WordPress compiles pages on demand. Without caching, the server compiles PHP and SQL queries on every page load, causing high TTFB.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Install and configure a caching plugin like WP Super Cache or LiteSpeed Cache to store compiled HTML.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Install a page caching plugin.', 'ud-audit-manager' ),
					'location'         => __( 'System Constant / Plugins Registry', 'ud-audit-manager' ),
				]
			);
		}

		// 3. Compression check via Loopback headers.
		$loopback_url = home_url( '/' );
		$response     = wp_remote_get(
			$loopback_url,
			[
				'headers' => [ 'Accept-Encoding' => 'gzip, deflate' ],
				'timeout' => 5,
			]
		);

		if ( ! is_wp_error( $response ) ) {
			$headers     = wp_remote_retrieve_headers( $response );
			$compression = isset( $headers['content-encoding'] ) ? $headers['content-encoding'] : '';

			if ( empty( $compression ) || strpos( $compression, 'gzip' ) === false ) {
				$this->add_finding(
					$run_id,
					'compression_disabled',
					__( 'Response Gzip Compression Disabled', 'ud-audit-manager' ),
					'high',
					[
						'description'      => __( 'Web server headers do not show Content-Encoding: gzip.', 'ud-audit-manager' ),
						'why_it_matters'   => __( 'Uncompressed HTML, CSS, and JS takes much longer to download, slowing down the page rendering process.', 'ud-audit-manager' ),
						'how_to_fix'       => __( 'Add Gzip output compression parameters to your .htaccess or Nginx configuration, or enable compression inside caching plugins.', 'ud-audit-manager' ),
						'suggested_action' => __( 'Enable Gzip or Brotli compression.', 'ud-audit-manager' ),
						'location'         => esc_url( $loopback_url ),
					]
				);
			}
		}

		// 4. Excessive styles and scripts check (Count registered assets in WP).
		global $wp_styles, $wp_scripts;
		$styles_count  = $wp_styles instanceof \WP_Styles ? count( $wp_styles->registered ) : 0;
		$scripts_count = $wp_scripts instanceof \WP_Scripts ? count( $wp_scripts->registered ) : 0;

		if ( $styles_count > 60 || $scripts_count > 80 ) {
			$this->add_finding(
				$run_id,
				'excessive_assets',
				__( 'High Number of Asset Registrations', 'ud-audit-manager' ),
				'medium',
				[
					/* translators: 1: Styles count, 2: Scripts count */
					'description'      => sprintf( __( 'Found %1$d registered styles and %2$d registered scripts.', 'ud-audit-manager' ), $styles_count, $scripts_count ),
					'why_it_matters'   => __( 'Excessive asset footprints slow down parsing. Themes should concatenate stylesheets to speed up loading.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Deactivate unused plugins, use asset optimization plugins to merge CSS/JS files, or load assets conditionally.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Dequeue unused styles and scripts.', 'ud-audit-manager' ),
					'location'         => __( 'WordPress Enqueued Assets list', 'ud-audit-manager' ),
				]
			);
		}
	}
}
