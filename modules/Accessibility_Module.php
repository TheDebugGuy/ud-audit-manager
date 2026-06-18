<?php
/**
 * Accessibility Audit Module.
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
 * Class Accessibility_Module
 *
 * Scans public post and page content blocks to identify empty hyperlinks, empty buttons,
 * unassociated form input labels, and missing skip-to-content links.
 *
 * @package UDAuditManager\Modules
 * @since 1.0.0
 */
class Accessibility_Module extends Module_Base {

	/**
	 * Constructor. Registers checks.
	 */
	public function __construct() {
		$registry = Container::instance()->get( 'registry' );
		if ( ! $registry instanceof Check_Registry ) {
			return;
		}

		// Content accessibility checks.
		$registry->register_check(
			'accessibility',
			'empty_links',
			[
				'title'       => __( 'Empty Hyperlinks Present', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Hyperlink anchor tags containing no readable text or ARIA description labels, making them unusable for screen readers.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'accessibility',
			'empty_buttons',
			[
				'title'       => __( 'Empty Button Tags', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Interactive buttons containing no readable label description, leaving screen-reader users in the dark on their action.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'accessibility',
			'missing_form_labels',
			[
				'title'       => __( 'Input Elements Lacking Form Labels', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Form inputs (text fields, checkboxes) lacking an associated <label> tag or ARIA identifier.', 'ud-audit-manager' ),
			]
		);

		// Global structural accessibility checks.
		$registry->register_check(
			'accessibility',
			'missing_skip_link',
			[
				'title'       => __( 'Skip Navigation Link Missing', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'Homepage lacks a "Skip to Content" shortcut link at the top, hindering keyboard navigation accessibility.', 'ud-audit-manager' ),
			]
		);
	}

	/**
	 * Get the module slug.
	 *
	 * @return string The module slug.
	 */
	public function get_slug() : string {
		return 'accessibility';
	}

	/**
	 * Get the module localized title.
	 *
	 * @return string The localized title.
	 */
	public function get_title() : string {
		return __( 'Accessibility Audit', 'ud-audit-manager' );
	}

	/**
	 * Run the batch accessibility audit.
	 *
	 * @param int $run_id The current scan run ID.
	 * @param int $offset The current item offset.
	 * @param int $limit  Max items to process in this batch.
	 * @return array { completed: bool, offset: int, total: int }
	 */
	public function scan_batch( int $run_id, int $offset, int $limit ) : array {
		global $wpdb;

		// 1. Global Accessibility Checks - run on first step.
		if ( 0 === $offset ) {
			$this->scan_global_accessibility_checks( $run_id );
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
			SELECT ID, post_title, post_content 
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

		// 4. Audit each page's content.
		foreach ( $posts as $post ) {
			$post_id   = (int) $post->ID;
			$content   = $post->post_content;
			$post_link = get_permalink( $post_id );
			$location  = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $post_link ), esc_html( $post->post_title ?: __( 'Untitled Post', 'ud-audit-manager' ) ) );

			// Check 1: Empty Links check.
			preg_match_all( '/<a\b[^>]*>(.*?)<\/a>/is', $content, $links );
			if ( ! empty( $links[0] ) ) {
				$empty_link_count = 0;
				foreach ( $links[0] as $i => $link_tag ) {
					$link_text = trim( wp_strip_all_tags( $links[1][ $i ] ) );
					if ( empty( $link_text ) ) {
						// Verify if link has aria-label or title attributes.
						$has_aria  = preg_match( '/aria-label\s*=\s*(["\'])(.*?)\1/is', $link_tag );
						$has_title = preg_match( '/title\s*=\s*(["\'])(.*?)\1/is', $link_tag );
						if ( ! $has_aria && ! $has_title ) {
							$empty_link_count++;
						}
					}
				}
				if ( $empty_link_count > 0 ) {
					$this->add_finding(
						$run_id,
						'empty_links',
						__( 'Empty Content Links Present', 'ud-audit-manager' ),
						'medium',
						[
							/* translators: %d: Empty link count */
							'description'      => sprintf( __( 'Found %d links containing no readable label description.', 'ud-audit-manager' ), $empty_link_count ),
							'why_it_matters'   => __( 'Screen readers rely on link titles to announce target actions. Empty links represent dead-ends for blind visitors.', 'ud-audit-manager' ),
							'how_to_fix'       => __( 'Edit the post and ensure all hyperlink elements have text characters inside, or append an aria-label attribute.', 'ud-audit-manager' ),
							'suggested_action' => __( 'Add labels or titles to links.', 'ud-audit-manager' ),
							'location'         => $location,
						]
					);
				}
			}

			// Check 2: Empty Buttons check.
			preg_match_all( '/<button\b[^>]*>(.*?)<\/button>/is', $content, $buttons );
			if ( ! empty( $buttons[0] ) ) {
				$empty_btn_count = 0;
				foreach ( $buttons[0] as $i => $btn_tag ) {
					$btn_text = trim( wp_strip_all_tags( $buttons[1][ $i ] ) );
					if ( empty( $btn_text ) ) {
						$has_aria  = preg_match( '/aria-label\s*=\s*(["\'])(.*?)\1/is', $btn_tag );
						$has_title = preg_match( '/title\s*=\s*(["\'])(.*?)\1/is', $btn_tag );
						if ( ! $has_aria && ! $has_title ) {
							$empty_btn_count++;
						}
					}
				}
				if ( $empty_btn_count > 0 ) {
					$this->add_finding(
						$run_id,
						'empty_buttons',
						__( 'Empty Button Tags Present', 'ud-audit-manager' ),
						'medium',
						[
							/* translators: %d: Empty button count */
							'description'      => sprintf( __( 'Found %d button tags lacking label details.', 'ud-audit-manager' ), $empty_btn_count ),
							'why_it_matters'   => __( 'Unlabeled buttons block keyboard/screen-reader navigation. Visitors cannot determine what action clicking trigger.', 'ud-audit-manager' ),
							'how_to_fix'       => __( 'Add descriptive text inside the button container or use aria-label attributes.', 'ud-audit-manager' ),
							'suggested_action' => __( 'Set labels on all button tags.', 'ud-audit-manager' ),
							'location'         => $location,
						]
					);
				}
			}

			// Check 3: Form inputs labels check.
			preg_match_all( '/<input\b[^>]*>/is', $content, $inputs );
			if ( ! empty( $inputs[0] ) ) {
				$missing_label_count = 0;
				foreach ( $inputs[0] as $input_tag ) {
					// Skip hidden, submit, and button types.
					if ( preg_match( '/type\s*=\s*(["\'])(hidden|submit|button|image)\1/is', $input_tag ) ) {
						continue;
					}

					$has_label = preg_match( '/id\s*=\s*(["\'])(.*?)\1/is', $input_tag, $id_match );
					$has_aria  = preg_match( '/aria-label|aria-labelledby\s*=/is', $input_tag );

					if ( ! $has_aria ) {
						if ( ! $has_label ) {
							$missing_label_count++;
						} else {
							// Verify if a <label for="ID"> tag exists in content.
							$target_id     = $id_match[2];
							$label_pattern = '/<label\b[^>]*for\s*=\s*(["\'])' . preg_quote( $target_id, '/' ) . '\1/is';
							if ( ! preg_match( $label_pattern, $content ) ) {
								$missing_label_count++;
							}
						}
					}
				}
				if ( $missing_label_count > 0 ) {
					$this->add_finding(
						$run_id,
						'missing_form_labels',
						__( 'Form Inputs Lacking Labels', 'ud-audit-manager' ),
						'medium',
						[
							/* translators: %d: Unlabeled input count */
							'description'      => sprintf( __( 'Found %d text inputs lacking form labels or ARIA descriptions.', 'ud-audit-manager' ), $missing_label_count ),
							'why_it_matters'   => __( 'Form labels prompt visitors on what criteria to input. Without labels, forms are unusable for blind readers.', 'ud-audit-manager' ),
							'how_to_fix'       => __( 'Ensure all form fields have an associated label tag matching the input ID (e.g. <label for="field">Name</label><input id="field">) or an aria-label.', 'ud-audit-manager' ),
							'suggested_action' => __( 'Define form labels or ARIA identifiers.', 'ud-audit-manager' ),
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
	 * Run global accessibility checks.
	 *
	 * @param int $run_id The current scan run ID.
	 * @return void
	 */
	private function scan_global_accessibility_checks( int $run_id ) : void {
		// 1. Skip Navigation Link Check on homepage.
		$loopback_url = home_url( '/' );
		$response     = wp_remote_get( $loopback_url, [ 'timeout' => 5 ] );

		if ( ! is_wp_error( $response ) ) {
			$html = wp_remote_retrieve_body( $response );

			// Look for common skip link anchors (e.g. #content, #main, skip-link class).
			$has_skip = false;
			if ( preg_match( '/href\s*=\s*(["\'])(#content|#main|#primary|#main-content)\1/is', $html ) ) {
				$has_skip = true;
			} elseif ( strpos( strtolower( $html ), 'skip-link' ) !== false || strpos( strtolower( $html ), 'skip to content' ) !== false ) {
				$has_skip = true;
			}

			if ( ! $has_skip ) {
				$this->add_finding(
					$run_id,
					'missing_skip_link',
					__( 'Missing "Skip to Content" Link', 'ud-audit-manager' ),
					'low',
					[
						'description'      => __( 'No accessibility skip navigation link was detected on the site homepage.', 'ud-audit-manager' ),
						'why_it_matters'   => __( 'Skip links allow keyboard-only and screen-reader users to bypass heavy header navigation lists and jump directly to articles.', 'ud-audit-manager' ),
						'how_to_fix'       => __( 'Update theme templates (header.php) to render a hidden-by-default skip link (e.g. <a href="#content" class="screen-reader-text">Skip to content</a>) right at the top opening body tag.', 'ud-audit-manager' ),
						'suggested_action' => __( 'Implement a skip to content link.', 'ud-audit-manager' ),
						'location'         => esc_url( $loopback_url ),
					]
				);
			}
		}
	}
}
