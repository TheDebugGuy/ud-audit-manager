<?php
/**
 * Content Audit Module.
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
 * Class Content_Module
 *
 * Checks default classifications, taxonomy tag usage, content freshness, handcrafted
 * excerpts, user author biographies, and accumulates draft counts.
 *
 * @package UDAuditManager\Modules
 * @since 1.0.0
 */
class Content_Module extends Module_Base {

	/**
	 * Constructor. Registers checks.
	 */
	public function __construct() {
		$registry = Container::instance()->get( 'registry' );
		if ( ! $registry instanceof Check_Registry ) {
			return;
		}

		// Content Quality checks.
		$registry->register_check(
			'content',
			'uncategorized_posts',
			[
				'title'       => __( 'Posts Categorized as "Uncategorized"', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Published posts assigned only to the default "Uncategorized" category, reducing taxonomy organization benefits.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'content',
			'missing_tags',
			[
				'title'       => __( 'Posts Lacking Taxonomy Tags', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'Published posts without any tags assigned, reducing cross-linking and related post search capabilities.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'content',
			'old_content',
			[
				'title'       => __( 'Outdated Content (Over 1 Year Old)', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'Articles that have not been modified or reviewed for more than a year. Search engines prefer fresh content.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'content',
			'missing_manual_excerpt',
			[
				'title'       => __( 'Missing Custom Post Excerpt', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'Posts lacking a handcrafted excerpt, forcing themes to auto-trim content paragraphs which can look sloppy.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'content',
			'missing_author_bio',
			[
				'title'       => __( 'Author Missing Biographical Info', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Post authors lacking a bio, which is crucial for building Expertise, Authoritativeness, and Trustworthiness (E-E-A-T) rankings.', 'ud-audit-manager' ),
			]
		);

		// Global Content checks.
		$registry->register_check(
			'content',
			'draft_accumulation',
			[
				'title'       => __( 'Excessive Accumulation of Drafts', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'Large amounts of unfinished drafts clutter the post management screen and database tables.', 'ud-audit-manager' ),
			]
		);
	}

	/**
	 * Get the module slug.
	 *
	 * @return string The module slug.
	 */
	public function get_slug() : string {
		return 'content';
	}

	/**
	 * Get the module localized title.
	 *
	 * @return string The localized title.
	 */
	public function get_title() : string {
		return __( 'Content Audit', 'ud-audit-manager' );
	}

	/**
	 * Run the content audit checks.
	 *
	 * @param int $run_id The current scan run ID.
	 * @param int $offset The current item offset.
	 * @param int $limit  Max items to process in this batch.
	 * @return array { completed: bool, offset: int, total: int }
	 */
	public function scan_batch( int $run_id, int $offset, int $limit ) : array {
		global $wpdb;

		// 1. Global Content Checks - run on first batch step.
		if ( 0 === $offset ) {
			$this->scan_global_content_checks( $run_id );
		}

		// 2. Query total published posts.
		/**
		 * Direct database query required.
		 *
		 * Audit results and statistics must be fetched in real-time.
		 * Cached values may return stale scan information.
		 *
		 * All dynamic values are sanitized and passed through
		 * $wpdb->prepare() before execution.
		 */
		$total_posts = (int) wp_count_posts( 'post' )->publish;

		if ( 0 === $total_posts ) {
			return [
				'completed' => true,
				'offset'    => 0,
				'total'     => 0,
			];
		}

		// 3. Retrieve batch of posts.
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
			SELECT ID, post_title, post_excerpt, post_author, post_modified 
			FROM {$wpdb->posts} 
			WHERE post_status = 'publish' 
			AND post_type = 'post' 
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

		// 4. Run post-level audits.
		$one_year_ago = strtotime( '-1 year' );

		foreach ( $posts as $post ) {
			$post_id   = (int) $post->ID;
			$author_id = (int) $post->post_author;
			$post_link = get_permalink( $post_id );
			$location  = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $post_link ), esc_html( $post->post_title ?: __( 'Untitled Post', 'ud-audit-manager' ) ) );

			// Check 1: Categorization check (only default category "Uncategorized").
			$categories = get_the_category( $post_id );
			if ( ! empty( $categories ) ) {
				$cat_slugs = wp_list_pluck( $categories, 'slug' );
				if ( 1 === count( $cat_slugs ) && in_array( 'uncategorized', $cat_slugs, true ) ) {
					$this->add_finding(
						$run_id,
						'uncategorized_posts',
						__( 'Post Classified as "Uncategorized"', 'ud-audit-manager' ),
						'medium',
						[
							'description'      => __( 'This published post is only assigned to the default "Uncategorized" category.', 'ud-audit-manager' ),
							'why_it_matters'   => __( 'Assigning posts only to the default category reduces catalog search usability and structural relevance for bots.', 'ud-audit-manager' ),
							'how_to_fix'       => __( 'Edit the post and select or create a descriptive, targeted category.', 'ud-audit-manager' ),
							'suggested_action' => __( 'Assign a custom category to the post.', 'ud-audit-manager' ),
							'location'         => $location,
						]
					);
				}
			}

			// Check 2: Tags presence.
			$tags = get_the_tags( $post_id );
			if ( ! $tags ) {
				$this->add_finding(
					$run_id,
					'missing_tags',
					__( 'Post has No Assigned Tags', 'ud-audit-manager' ),
					'low',
					[
						'description'      => __( 'This published post has no tag taxonomies assigned.', 'ud-audit-manager' ),
						'why_it_matters'   => __( 'Tags help create semantic connections between posts and expand tag index pages for search visibility.', 'ud-audit-manager' ),
						'how_to_fix'       => __( 'Add a few relevant, targeted keyword tags in the post options sidebar.', 'ud-audit-manager' ),
						'suggested_action' => __( 'Assign keywords tags to this post.', 'ud-audit-manager' ),
						'location'         => $location,
					]
				);
			}

			// Check 3: Old/Outdated Content.
			$modified_time = strtotime( $post->post_modified );
			if ( $modified_time < $one_year_ago ) {
				$formatted_date = date_i18n( get_option( 'date_format' ), $modified_time );
				$this->add_finding(
					$run_id,
					'old_content',
					__( 'Outdated Content Alert', 'ud-audit-manager' ),
					'low',
					[
						/* translators: %s: Formatted modification date */
						'description'      => sprintf( __( 'This post was last updated on %s (over 1 year ago).', 'ud-audit-manager' ), $formatted_date ),
						'why_it_matters'   => __( 'Search algorithms prioritize fresh, regularly updated content. Outdated information can degrade user trust.', 'ud-audit-manager' ),
						'how_to_fix'       => __( 'Review the article. Update stats, links, and text to ensure relevance, and re-publish.', 'ud-audit-manager' ),
						'suggested_action' => __( 'Review and refresh outdated post content.', 'ud-audit-manager' ),
						'location'         => $location,
					]
				);
			}

			// Check 4: Missing manual excerpt.
			if ( empty( trim( $post->post_excerpt ) ) ) {
				$this->add_finding(
					$run_id,
					'missing_manual_excerpt',
					__( 'Missing Handcrafted Excerpt', 'ud-audit-manager' ),
					'low',
					[
						'description'      => __( 'This post does not have a manual excerpt summary defined.', 'ud-audit-manager' ),
						'why_it_matters'   => __( 'Handcrafted excerpts display clean summaries on archive pages and social schemas instead of broken, auto-chopped paragraphs.', 'ud-audit-manager' ),
						'how_to_fix'       => __( 'Open the post editor, locate the Excerpt box, and write a summary sentences.', 'ud-audit-manager' ),
						'suggested_action' => __( 'Write a custom excerpt summary.', 'ud-audit-manager' ),
						'location'         => $location,
					]
				);
			}

			// Check 5: Author bio missing description.
			$author_bio = get_the_author_meta( 'description', $author_id );
			if ( empty( trim( $author_bio ) ) ) {
				$author_name = get_the_author_meta( 'display_name', $author_id );
				$this->add_finding(
					$run_id,
					'missing_author_bio',
					__( 'Author Bio is Empty', 'ud-audit-manager' ),
					'medium',
					[
						/* translators: %s: Author\'s display name */
						'description'      => sprintf( __( 'Author "%s" has no biographical information in their user profile.', 'ud-audit-manager' ), esc_html( $author_name ) ),
						'why_it_matters'   => __( 'E-E-A-T guidelines emphasize author authority. Lacking a biography hurts trust factors and SEO context.', 'ud-audit-manager' ),
						/* translators: %s: Author\'s display name */
						'how_to_fix'       => sprintf( __( 'Edit User Profile for user "%s" and fill out the Biographical Info section.', 'ud-audit-manager' ), esc_html( $author_name ) ),
						'suggested_action' => __( 'Populate user profile biographical text.', 'ud-audit-manager' ),
						/* translators: %s: Author\'s display name */
						'location'         => sprintf( __( 'User profile: %s', 'ud-audit-manager' ), esc_html( $author_name ) ),
					]
				);
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
	 * Run global content audits.
	 *
	 * @param int $run_id The current scan run ID.
	 * @return void
	 */
	private function scan_global_content_checks( int $run_id ) : void {
		global $wpdb;

		// 1. Draft accumulation check.
		/**
		 * Direct database query required.
		 *
		 * Audit results and statistics must be fetched in real-time.
		 * Cached values may return stale scan information.
		 *
		 * All dynamic values are sanitized and passed through
		 * $wpdb->prepare() before execution.
		 */
		$drafts_count = (int) wp_count_posts( 'post' )->draft;
		if ( $drafts_count > 15 ) {
			$this->add_finding(
				$run_id,
				'draft_accumulation',
				__( 'Excessive Drafts Accumulation', 'ud-audit-manager' ),
				'low',
				[
					/* translators: %d: Number of drafts */
					'description'      => sprintf( __( 'Found %d unpublished drafts sitting in the system.', 'ud-audit-manager' ), $drafts_count ),
					'why_it_matters'   => __( 'Dozens of inactive drafts clutter editorial dashboards and bloat wp_posts database search queries.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Audit the draft list. Publish finished articles, or delete outdated drafts permanently.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Clean up and delete outdated drafts.', 'ud-audit-manager' ),
					'location'         => __( 'Post drafts index screen', 'ud-audit-manager' ),
				]
			);
		}
	}
}
