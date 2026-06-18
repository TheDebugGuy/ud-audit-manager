<?php
/**
 * SEO Audit Module.
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
 * Class SEO_Module
 *
 * Audits titles, descriptions, heading structures, thin content, featured images,
 * alternative image texts, internal/outbound link profiles, sitemaps, and indexing availability.
 *
 * @package UDAuditManager\Modules
 * @since 1.0.0
 */
class SEO_Module extends Module_Base {

	/**
	 * Constructor. Registers checks.
	 */
	public function __construct() {
		$registry = Container::instance()->get( 'registry' );
		if ( ! $registry instanceof Check_Registry ) {
			return;
		}

		// Metadata Checks.
		$registry->register_check(
			'seo',
			'missing_title',
			[
				'title'       => __( 'Missing Title Tag', 'ud-audit-manager' ),
				'severity'    => 'critical',
				'description' => __( 'Pages or posts missing a valid title tag, which search engines display in search results.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'seo',
			'duplicate_titles',
			[
				'title'       => __( 'Duplicate Titles', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'Multiple pages share the exact same title tag, causing cannibalization in search results.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'seo',
			'title_length',
			[
				'title'       => __( 'Incorrect Title Length', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Titles should be between 10 and 60 characters to avoid being truncated in search result pages.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'seo',
			'missing_meta_description',
			[
				'title'       => __( 'Missing Meta Description', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'Meta descriptions summarize a page\'s content in search listings and improve click-through rates.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'seo',
			'duplicate_meta_descriptions',
			[
				'title'       => __( 'Duplicate Meta Descriptions', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Multiple pages share the exact same meta description, making them appear identical to search engines.', 'ud-audit-manager' ),
			]
		);

		// Headings Checks.
		$registry->register_check(
			'seo',
			'missing_h1',
			[
				'title'       => __( 'Missing H1 Heading', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'The main page topic must be outlined by a single H1 heading tag for structural SEO.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'seo',
			'multiple_h1',
			[
				'title'       => __( 'Multiple H1 Headings', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Multiple H1 tags dilute the primary keyword focus and confuse structural document readers.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'seo',
			'heading_hierarchy',
			[
				'title'       => __( 'Heading Hierarchy Issue', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'Heading tags must follow a structured order (e.g. H1 followed by H2, then H3; skipping levels is discouraged).', 'ud-audit-manager' ),
			]
		);

		// Content Quality.
		$registry->register_check(
			'seo',
			'thin_content',
			[
				'title'       => __( 'Thin Content (Word Count)', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Articles with fewer than 300 words have low topical authority and rank poorly.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'seo',
			'missing_featured_image',
			[
				'title'       => __( 'Missing Featured Image', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'Missing featured images reduce social sharing click-throughs and structural presentation.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'seo',
			'missing_image_alt',
			[
				'title'       => __( 'Missing Image Alt Text', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'Images are missing alternative text, which search engine bots use to understand image content.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'seo',
			'missing_internal_links',
			[
				'title'       => __( 'No Internal Outbound Links', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Posts lacking links to other pages on your own website hinder site authority distribution.', 'ud-audit-manager' ),
			]
		);

		// Technical SEO.
		$registry->register_check(
			'seo',
			'search_visibility_disabled',
			[
				'title'       => __( 'Search Engine Visibility Disabled', 'ud-audit-manager' ),
				'severity'    => 'critical',
				'description' => __( 'Discourage search engines from indexing this site settings flag is checked in Reading Settings.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'seo',
			'robots_txt_missing',
			[
				'title'       => __( 'robots.txt Missing or Unreachable', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'A robots.txt file guides crawlers on which directories and files to bypass or scan.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'seo',
			'sitemap_missing',
			[
				'title'       => __( 'XML Sitemap Not Found', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'An XML sitemap lists important pages to index, ensuring bots discover your updates quickly.', 'ud-audit-manager' ),
			]
		);
	}

	/**
	 * Get the module slug.
	 *
	 * @return string The slug.
	 */
	public function get_slug() : string {
		return 'seo';
	}

	/**
	 * Get the module localized title.
	 *
	 * @return string The title.
	 */
	public function get_title() : string {
		return __( 'SEO Audit', 'ud-audit-manager' );
	}

	/**
	 * Run the batch SEO audit scan.
	 *
	 * @param int $run_id The current scan run ID.
	 * @param int $offset The current item offset.
	 * @param int $limit  Max items to process in this batch.
	 * @return array { completed: bool, offset: int, total: int }
	 */
	public function scan_batch( int $run_id, int $offset, int $limit ) : array {
		global $wpdb;

		// 1. Technical / Global SEO Checks - execute only on the first batch step.
		if ( 0 === $offset ) {
			$this->scan_global_seo_checks( $run_id );
		}

		// 2. Fetch published posts and pages count.
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
			SELECT ID, post_title, post_content, post_excerpt, post_type 
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

		// 4. Audit each post in the batch.
		$site_url = home_url();

		foreach ( $posts as $post ) {
			$post_id   = (int) $post->ID;
			$title     = trim( $post->post_title );
			$content   = $post->post_content;
			$excerpt   = trim( $post->post_excerpt );
			$post_link = get_permalink( $post_id );
			$location  = sprintf( '<a href="%s" target="_blank">%s</a> (%s)', esc_url( $post_link ), esc_html( $post->post_title ?: __( 'Untitled Post', 'ud-audit-manager' ) ), esc_html( $post->post_type ) );

			// Check 1: Missing Title.
			if ( empty( $title ) ) {
				$this->add_finding(
					$run_id,
					'missing_title',
					__( 'Missing Post/Page Title', 'ud-audit-manager' ),
					'critical',
					[
						'description'      => __( 'This published post or page has no title defined.', 'ud-audit-manager' ),
						'why_it_matters'   => __( 'Without a title, search engines cannot display a meaningful link in search results, hurting visibility.', 'ud-audit-manager' ),
						'how_to_fix'       => __( 'Edit the post and add an appropriate title.', 'ud-audit-manager' ),
						'suggested_action' => __( 'Open the editor and insert a title.', 'ud-audit-manager' ),
						'location'         => $location,
					]
				);
			} else {
				// Check 2: Title Length (Recommended 10-60 chars).
				$title_len = mb_strlen( $title );
				if ( $title_len < 10 || $title_len > 60 ) {
					$this->add_finding(
						$run_id,
						'title_length',
						__( 'Title Tag Length is Out of Range', 'ud-audit-manager' ),
						'medium',
						[
							/* translators: %d: Title character length */
							'description'      => sprintf( __( 'Title length is %d characters. Ideal length is 10 to 60 characters.', 'ud-audit-manager' ), $title_len ),
							'why_it_matters'   => __( 'Titles too long will be truncated in search pages. Titles too short miss optimization opportunities.', 'ud-audit-manager' ),
							'how_to_fix'       => __( 'Optimize the title length to fit within the 10-60 characters range.', 'ud-audit-manager' ),
							'suggested_action' => __( 'Edit the title to optimize length.', 'ud-audit-manager' ),
							'location'         => $location,
						]
					);
				}

				// Check 3: Duplicate titles detection (check within published list).
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
				$duplicate_query = $wpdb->prepare(
					"
					SELECT COUNT(ID) FROM {$wpdb->posts} 
					WHERE post_status = 'publish' 
					AND post_type IN ('post', 'page') 
					AND post_title = %s 
					AND ID != %d
					",
					$title,
					$post_id
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
				$dup_count = (int) $wpdb->get_var( $duplicate_query );
				if ( $dup_count > 0 ) {
					$this->add_finding(
						$run_id,
						'duplicate_titles',
						__( 'Duplicate Page Title Found', 'ud-audit-manager' ),
						'high',
						[
							/* translators: %s: Duplicate title text */
							'description'      => sprintf( __( 'Title "%s" is shared with other active pages.', 'ud-audit-manager' ), esc_html( $title ) ),
							'why_it_matters'   => __( 'Duplicate titles confuse search crawlers regarding which page to rank for a query, causing cannibalization.', 'ud-audit-manager' ),
							'how_to_fix'       => __( 'Assign a unique title indicating the distinct value of this page.', 'ud-audit-manager' ),
							'suggested_action' => __( 'Rewrite this title to be unique.', 'ud-audit-manager' ),
							'location'         => $location,
						]
					);
				}
			}

			// Check 4: Missing Meta Description (Check Yoast/RM fields, or fallback to excerpt).
			$meta_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
			if ( empty( $meta_desc ) ) {
				$meta_desc = get_post_meta( $post_id, '_rank_math_description', true );
			}
			if ( empty( $meta_desc ) ) {
				$meta_desc = $excerpt;
			}

			if ( empty( $meta_desc ) ) {
				$this->add_finding(
					$run_id,
					'missing_meta_description',
					__( 'Missing Meta Description', 'ud-audit-manager' ),
					'high',
					[
						'description'      => __( 'No description meta or post excerpt is configured.', 'ud-audit-manager' ),
						'why_it_matters'   => __( 'Descriptions directly affect organic CTR. If empty, search engines generate random text snippets.', 'ud-audit-manager' ),
						'how_to_fix'       => __( 'Create a concise summary (120-160 characters) and save it in your SEO plugin or the post excerpt field.', 'ud-audit-manager' ),
						'suggested_action' => __( 'Write a descriptive post summary.', 'ud-audit-manager' ),
						'location'         => $location,
					]
				);
			} else {
				// Check 5: Duplicate Meta Descriptions.
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
				$description_query = $wpdb->prepare(
					"
					SELECT COUNT(p.ID) FROM {$wpdb->posts} p 
					LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_yoast_wpseo_metadesc' 
					WHERE p.post_status = 'publish' 
					AND p.post_type IN ('post', 'page') 
					AND (pm.meta_value = %s OR p.post_excerpt = %s) 
					AND p.ID != %d
					",
					$meta_desc,
					$meta_desc,
					$post_id
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
				$dup_desc_count = (int) $wpdb->get_var( $description_query );
				if ( $dup_desc_count > 0 && strlen( $meta_desc ) > 5 ) {
					$this->add_finding(
						$run_id,
						'duplicate_meta_descriptions',
						__( 'Duplicate Meta Description', 'ud-audit-manager' ),
						'medium',
						[
							'description'      => __( 'This meta description is used on multiple pages.', 'ud-audit-manager' ),
							'why_it_matters'   => __( 'Identical summaries make different pages look redundant to search engines.', 'ud-audit-manager' ),
							'how_to_fix'       => __( 'Draft a unique, targeted meta description for this content.', 'ud-audit-manager' ),
							'suggested_action' => __( 'Customize the description text.', 'ud-audit-manager' ),
							'location'         => $location,
						]
					);
				}
			}

			// Check 6: Heading Audits (Regex extraction).
			preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $content, $headings );
			$h1_count       = 0;
			$heading_levels = [];

			if ( ! empty( $headings[1] ) ) {
				foreach ( $headings[1] as $level ) {
					$level            = (int) $level;
					$heading_levels[] = $level;
					if ( 1 === $level ) {
						$h1_count++;
					}
				}
			}

			if ( 0 === $h1_count ) {
				$this->add_finding(
					$run_id,
					'missing_h1',
					__( 'Missing H1 Heading Tag', 'ud-audit-manager' ),
					'high',
					[
						'description'      => __( 'There is no H1 tag in the page content.', 'ud-audit-manager' ),
						'why_it_matters'   => __( 'The H1 is the primary semantic title of the page text. Lacking it makes outline reading hard for bots.', 'ud-audit-manager' ),
						'how_to_fix'       => __( 'Add an H1 heading at the start of your content layout.', 'ud-audit-manager' ),
						'suggested_action' => __( 'Insert an H1 tag in the content.', 'ud-audit-manager' ),
						'location'         => $location,
					]
				);
			} elseif ( $h1_count > 1 ) {
				$this->add_finding(
					$run_id,
					'multiple_h1',
					__( 'Multiple H1 Headings Found', 'ud-audit-manager' ),
					'medium',
					[
						/* translators: %d: H1 heading count */
						'description'      => sprintf( __( 'Found %d H1 tags in the content.', 'ud-audit-manager' ), $h1_count ),
						'why_it_matters'   => __( 'Using more than one H1 dilutes topic clarity and complicates structured page reading.', 'ud-audit-manager' ),
						'how_to_fix'       => __( 'Change secondary H1 headings to H2 or H3 structures.', 'ud-audit-manager' ),
						'suggested_action' => __( 'Demote secondary H1s to H2 tags.', 'ud-audit-manager' ),
						'location'         => $location,
					]
				);
			}

			// Heading Hierarchy (Ensure they don't skip levels, e.g. H2 followed directly by H4).
			if ( count( $heading_levels ) > 1 ) {
				$previous_level = $heading_levels[0];
				$skipped        = false;
				for ( $i = 1; $i < count( $heading_levels ); $i++ ) {
					$current_level = $heading_levels[ $i ];
					if ( $current_level - $previous_level > 1 ) {
						$skipped = true;
						break;
					}
					$previous_level = $current_level;
				}
				if ( $skipped ) {
					$this->add_finding(
						$run_id,
						'heading_hierarchy',
						__( 'Non-Sequential Heading Hierarchy', 'ud-audit-manager' ),
						'low',
						[
							'description'      => __( 'The content structure jumps heading levels (e.g. H2 directly to H4, skipping H3).', 'ud-audit-manager' ),
							'why_it_matters'   => __( 'Search crawlers and screen readers rely on nested sequential structures to parse layouts.', 'ud-audit-manager' ),
							'how_to_fix'       => __( 'Restructure headings so that tags increment sequentially.', 'ud-audit-manager' ),
							'suggested_action' => __( 'Audit structural nested flow.', 'ud-audit-manager' ),
							'location'         => $location,
						]
					);
				}
			}

			// Check 7: Thin Content (Word count check, stripped tags).
			$word_count = str_word_count( wp_strip_all_tags( $content ) );
			if ( $word_count < 300 && 'page' !== $post->post_type ) { // Skip pages since they are often landing pages.
				$this->add_finding(
					$run_id,
					'thin_content',
					__( 'Thin Content Warning', 'ud-audit-manager' ),
					'medium',
					[
						/* translators: %d: Word count */
						'description'      => sprintf( __( 'This post contains only %d words, which is below the 300-word limit.', 'ud-audit-manager' ), $word_count ),
						'why_it_matters'   => __( 'Thin pages rarely contain sufficient information to answer search intents, leading to lower rankings.', 'ud-audit-manager' ),
						'how_to_fix'       => __( 'Expand the content with more depth, answers, explanations, or media.', 'ud-audit-manager' ),
						'suggested_action' => __( 'Increase content details.', 'ud-audit-manager' ),
						'location'         => $location,
					]
				);
			}

			// Check 8: Missing Featured Image.
			if ( ! has_post_thumbnail( $post_id ) ) {
				$this->add_finding(
					$run_id,
					'missing_featured_image',
					__( 'Missing Featured Image', 'ud-audit-manager' ),
					'low',
					[
						'description'      => __( 'No featured image has been assigned to this page/post.', 'ud-audit-manager' ),
						'why_it_matters'   => __( 'Featured images are used by social sharing schemas and structured lists. Lacking one reduces click appeal.', 'ud-audit-manager' ),
						'how_to_fix'       => __( 'Assign an eye-catching featured image in the document sidebar.', 'ud-audit-manager' ),
						'suggested_action' => __( 'Upload a featured thumbnail.', 'ud-audit-manager' ),
						'location'         => $location,
					]
				);
			}

			// Check 9: Missing Image Alt Text in content.
			preg_match_all( '/<img\b[^>]*>/is', $content, $images );
			if ( ! empty( $images[0] ) ) {
				$missing_alt_count = 0;
				foreach ( $images[0] as $img_tag ) {
					// Check if alt attribute is missing, or matches alt="" / alt=''.
					if ( ! preg_match( '/alt\s*=\s*(["\'])(.*?)\1/is', $img_tag, $alt_val ) || empty( trim( $alt_val[2] ) ) ) {
						$missing_alt_count++;
					}
				}
				if ( $missing_alt_count > 0 ) {
					$this->add_finding(
						$run_id,
						'missing_image_alt',
						__( 'Missing Image Alt Attribute', 'ud-audit-manager' ),
						'high',
						[
							/* translators: %d: Missing alt tag images count */
							'description'      => sprintf( __( 'Found %d inline images missing descriptive alt tags.', 'ud-audit-manager' ), $missing_alt_count ),
							'why_it_matters'   => __( 'Alt tags tell search bots what the image represents, which ranks them in image search and provides accessibility.', 'ud-audit-manager' ),
							'how_to_fix'       => __( 'Add descriptive alt text directly to images within the post editor or the media library.', 'ud-audit-manager' ),
							'suggested_action' => __( 'Populate image alternate attributes.', 'ud-audit-manager' ),
							'location'         => $location,
						]
					);
				}
			}

			// Check 10: Missing Internal links.
			preg_match_all( '/href\s*=\s*(["\'])(.*?)\1/is', $content, $links );
			$has_internal = false;
			if ( ! empty( $links[2] ) ) {
				foreach ( $links[2] as $link_url ) {
					// Check if link starts with site URL or matches local relative paths.
					if ( strpos( $link_url, $site_url ) === 0 || ( strpos( $link_url, '/' ) === 0 && strpos( $link_url, '//' ) !== 0 ) ) {
						$has_internal = true;
						break;
					}
				}
			}
			if ( ! $has_internal && 'page' !== $post->post_type ) {
				$this->add_finding(
					$run_id,
					'missing_internal_links',
					__( 'No Outbound Internal Links', 'ud-audit-manager' ),
					'medium',
					[
						'description'      => __( 'There are no links pointing to other pages on this site.', 'ud-audit-manager' ),
						'why_it_matters'   => __( 'Internal linking creates pathways for crawlers, distributes page authority, and aids reader navigation.', 'ud-audit-manager' ),
						'how_to_fix'       => __( 'Link to related pages, posts, or contact options from within the text.', 'ud-audit-manager' ),
						'suggested_action' => __( 'Insert internal text hyperlinks.', 'ud-audit-manager' ),
						'location'         => $location,
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
	 * Scans global and technical SEO options.
	 *
	 * @param int $run_id The current scan run ID.
	 * @return void
	 */
	private function scan_global_seo_checks( int $run_id ) : void {
		// 1. Search Engine Visibility disabled check.
		$blog_public = (int) get_option( 'blog_public', 1 );
		if ( 0 === $blog_public ) {
			$this->add_finding(
				$run_id,
				'search_visibility_disabled',
				__( 'Site is Blocked from Indexing', 'ud-audit-manager' ),
				'critical',
				[
					'description'      => __( 'Search engine visibility is disabled in WordPress reading options ("blog_public" is set to 0).', 'ud-audit-manager' ),
					'why_it_matters'   => __( 'This instructs search engines like Google to completely ignore your website. Search bots will not index any content.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Go to Settings > Reading in your WordPress admin menu, and uncheck "Discourage search engines from indexing this site".', 'ud-audit-manager' ),
					'suggested_action' => __( 'Enable Search Engine Visibility.', 'ud-audit-manager' ),
					'location'         => __( 'Settings > Reading Settings option', 'ud-audit-manager' ),
				]
			);
		}

		// 2. robots.txt validation.
		$robots_url      = home_url( '/robots.txt' );
		$robots_response = wp_remote_get( $robots_url, [ 'timeout' => 5 ] );
		$robots_code     = wp_remote_retrieve_response_code( $robots_response );

		if ( is_wp_error( $robots_response ) || 200 !== $robots_code ) {
			$this->add_finding(
				$run_id,
				'robots_txt_missing',
				__( 'robots.txt file is missing or returns error', 'ud-audit-manager' ),
				'high',
				[
					/* translators: 1: robots.txt URL, 2: HTTP status code or error text */
					'description'      => sprintf( __( 'Target URL %1$s returned status %2$s.', 'ud-audit-manager' ), esc_url( $robots_url ), $robots_code ? 'HTTP ' . $robots_code : 'Connection Fail' ),
					'why_it_matters'   => __( 'Without robots.txt, crawlers might waste budget indexing temporary or administrative directories, hurting performance.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Create a physical robots.txt file in the root folder or use an SEO plugin to configure virtual robots paths.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Verify or upload a robots.txt file.', 'ud-audit-manager' ),
					'location'         => esc_url( $robots_url ),
				]
			);
		}

		// 3. XML Sitemap check (check Yoast, RM, Core sitemaps).
		$sitemaps = [
			home_url( '/sitemap.xml' ),
			home_url( '/wp-sitemap.xml' ),
			home_url( '/sitemap_index.xml' ),
		];
		$sitemap_found = false;
		$checked_urls  = [];

		foreach ( $sitemaps as $url ) {
			$checked_urls[] = $url;
			$response       = wp_remote_get( $url, [ 'timeout' => 5 ] );
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$sitemap_found = true;
				break;
			}
		}

		if ( ! $sitemap_found ) {
			$this->add_finding(
				$run_id,
				'sitemap_missing',
				__( 'XML Sitemap file is missing', 'ud-audit-manager' ),
				'high',
				[
					/* translators: %s: Comma-separated list of checked sitemap URLs */
					'description'      => sprintf( __( 'Checked URLs: %s. None returned a successful status code.', 'ud-audit-manager' ), implode( ', ', $checked_urls ) ),
					'why_it_matters'   => __( 'Sitemaps tell search engines which URLs to prioritize and when content is updated. Lacking one slows indexation.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Activate XML sitemaps in your SEO plugin or let WP Core build it. Ensure redirects point correctly.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Enable or verify XML sitemap links.', 'ud-audit-manager' ),
					'location'         => implode( ' | ', $checked_urls ),
				]
			);
		}
	}
}
