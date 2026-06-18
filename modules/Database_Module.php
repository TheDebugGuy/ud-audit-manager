<?php
/**
 * Database Audit Module.
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
 * Class Database_Module
 *
 * Scans tables for revisions bloat, spam comment count, trash bins overhead,
 * orphaned metadata records, expired transients, autoloaded option sizes, and fragmented space.
 *
 * @package UDAuditManager\Modules
 * @since 1.0.0
 */
class Database_Module extends Module_Base {

	/**
	 * Constructor. Registers checks.
	 */
	public function __construct() {
		$registry = Container::instance()->get( 'registry' );
		if ( ! $registry instanceof Check_Registry ) {
			return;
		}

		// DB Bloat Checks.
		$registry->register_check(
			'database',
			'excessive_revisions',
			[
				'title'       => __( 'Excessive Post Revisions', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Large amounts of post revisions accumulate in the database over time, bloating SQL query response times.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'database',
			'spam_comments',
			[
				'title'       => __( 'Accumulated Spam Comments', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'Spam comments clutter comment tables and increase backup storage requirements.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'database',
			'trash_bloat',
			[
				'title'       => __( 'Accumulated Trash Items', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'Items sitting in post or comment trash bins bloat the database and should be cleared periodically.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'database',
			'orphan_metadata',
			[
				'title'       => __( 'Orphaned Metadata Rows', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Post or user metadata rows remaining in the database after the parent post or user has been deleted.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'database',
			'expired_transients',
			[
				'title'       => __( 'Expired Transients Present', 'ud-audit-manager' ),
				'severity'    => 'low',
				'description' => __( 'Temporary cache records (transients) that have expired but remain in the database option tables.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'database',
			'autoload_bloat',
			[
				'title'       => __( 'Autoloaded Options Size Warning', 'ud-audit-manager' ),
				'severity'    => 'high',
				'description' => __( 'Autoloaded options exceed the recommended 800KB size limit, slowing down every page load on the site.', 'ud-audit-manager' ),
			]
		);
		$registry->register_check(
			'database',
			'table_overhead',
			[
				'title'       => __( 'Database Table Overhead Detected', 'ud-audit-manager' ),
				'severity'    => 'medium',
				'description' => __( 'Database tables show engine overhead (unused space free memory space) requiring OPTIMIZE queries.', 'ud-audit-manager' ),
			]
		);
	}

	/**
	 * Get the module slug.
	 *
	 * @return string The module slug.
	 */
	public function get_slug() : string {
		return 'database';
	}

	/**
	 * Get the module localized title.
	 *
	 * @return string The localized title.
	 */
	public function get_title() : string {
		return __( 'Database Audit', 'ud-audit-manager' );
	}

	/**
	 * Run the database audit. Complete in a single batch.
	 *
	 * @param int $run_id The current scan run ID.
	 * @param int $offset The current item offset.
	 * @param int $limit  Max items to process in this batch.
	 * @return array { completed: bool, offset: int, total: int }
	 */
	public function scan_batch( int $run_id, int $offset, int $limit ) : array {
		global $wpdb;

		$estimated_savings_bytes = 0;

		// 1. Revisions Check.
		/**
		 * Direct database query required.
		 *
		 * Audit results and statistics must be fetched in real-time.
		 * Cached values may return stale scan information.
		 *
		 * All dynamic values are sanitized and passed through
		 * $wpdb->prepare() before execution.
		 */
		$revisions_count = (int) wp_count_posts( 'revision' )->revision;
		if ( $revisions_count > 100 ) {
			// Estimate revisions savings (approx 25KB per revision average).
			$revisions_savings        = $revisions_count * 25600;
			$estimated_savings_bytes += $revisions_savings;

			$this->add_finding(
				$run_id,
				'excessive_revisions',
				__( 'Excessive Post Revisions Bloat', 'ud-audit-manager' ),
				'medium',
				[
					/* translators: %d: Revisions count */
					'description'      => sprintf( __( 'Found %d post revisions in the database.', 'ud-audit-manager' ), $revisions_count ),
					'why_it_matters'   => __( 'Revisions store a full copy of past content updates. Hundreds of revisions slow down wp_posts database index queries.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Clean up revisions using a database cleanup plugin or limit revisions by adding define(\'WP_POST_REVISIONS\', 5); in wp-config.php.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Prune post revisions database rows.', 'ud-audit-manager' ),
					/* translators: %s: Posts table name */
					'location'         => sprintf( __( '%s posts table', 'ud-audit-manager' ), $wpdb->posts ),
				]
			);
		}
		$this->save_metric( $run_id, 'db_revisions_count', $revisions_count );

		// 2. Spam Comments Check.
		/**
		 * Direct database query required.
		 *
		 * Audit results and statistics must be fetched in real-time.
		 * Cached values may return stale scan information.
		 *
		 * All dynamic values are sanitized and passed through
		 * $wpdb->prepare() before execution.
		 */
		$spam_comments = (int) wp_count_comments()->spam;
		if ( $spam_comments > 50 ) {
			$spam_savings             = $spam_comments * 2048; // 2KB per comment.
			$estimated_savings_bytes += $spam_savings;

			$this->add_finding(
				$run_id,
				'spam_comments',
				__( 'Accumulated Spam Comments Clutter', 'ud-audit-manager' ),
				'low',
				[
					/* translators: %d: Spam comment count */
					'description'      => sprintf( __( 'Found %d comments classified as spam.', 'ud-audit-manager' ), $spam_comments ),
					'why_it_matters'   => __( 'Spam comments fill database indexes, making comment moderations and queries slower.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Go to Comments > Spam in your dashboard, and click Empty Spam.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Delete all spam comments.', 'ud-audit-manager' ),
					/* translators: %s: Comments table name */
					'location'         => sprintf( __( '%s comments table', 'ud-audit-manager' ), $wpdb->comments ),
				]
			);
		}
		$this->save_metric( $run_id, 'db_spam_count', $spam_comments );

		// 3. Trash Bloat Check (both posts and comments).
		/**
		 * Direct database query required.
		 *
		 * Audit results and statistics must be fetched in real-time.
		 * Cached values may return stale scan information.
		 *
		 * All dynamic values are sanitized and passed through
		 * $wpdb->prepare() before execution.
		 */
		$trash_posts    = (int) wp_count_posts( 'post' )->trash;
		$trash_comments = (int) wp_count_comments()->trash;
		$total_trash    = $trash_posts + $trash_comments;

		if ( $total_trash > 50 ) {
			$trash_savings            = ( $trash_posts * 15360 ) + ( $trash_comments * 1024 );
			$estimated_savings_bytes += $trash_savings;

			$this->add_finding(
				$run_id,
				'trash_bloat',
				__( 'Trash Bins Require Emptying', 'ud-audit-manager' ),
				'low',
				[
					/* translators: 1: Trash posts count, 2: Trash comments count */
					'description'      => sprintf( __( 'Found %1$d posts and %2$d comments sitting inside Trash bins.', 'ud-audit-manager' ), $trash_posts, $trash_comments ),
					'why_it_matters'   => __( 'Trash entries are Kept for 30 days by default, bloating indexes until they are permanently deleted.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Empty the trash folders for Posts, Pages, and Comments in the dashboard settings panels.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Empty posts and comments trash.', 'ud-audit-manager' ),
					'location'         => __( 'Post & Comments Trash Folders', 'ud-audit-manager' ),
				]
			);
		}
		$this->save_metric( $run_id, 'db_trash_count', $total_trash );

		// 4. Orphaned postmeta.
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
		$orphan_meta = (int) $wpdb->get_var(
			"
			SELECT COUNT(pm.meta_id) 
			FROM {$wpdb->postmeta} pm 
			LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
			WHERE p.ID IS NULL
			"
		);
		if ( $orphan_meta > 100 ) {
			$meta_savings             = $orphan_meta * 512;
			$estimated_savings_bytes += $meta_savings;

			$this->add_finding(
				$run_id,
				'orphan_metadata',
				__( 'Orphaned Post Metadata Rows', 'ud-audit-manager' ),
				'medium',
				[
					/* translators: %d: Orphaned metadata rows count */
					'description'      => sprintf( __( 'Found %d postmeta rows linked to non-existent post IDs.', 'ud-audit-manager' ), $orphan_meta ),
					'why_it_matters'   => __( 'When plugins are uninstalled or posts are deleted forcefully, meta rows remain, bloating post meta keys query searches.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Run a database cleanup script or plugin to delete orphaned postmeta entries.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Delete orphaned metadata entries.', 'ud-audit-manager' ),
					/* translators: %s: Postmeta table name */
					'location'         => sprintf( __( '%s database table', 'ud-audit-manager' ), $wpdb->postmeta ),
				]
			);
		}
		$this->save_metric( $run_id, 'db_orphan_meta_count', $orphan_meta );

		// 5. Expired Transients Check.
		$now        = time();
		$like_query = $wpdb->esc_like( '_transient_timeout_' ) . '%';
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
		$expired_transients = (int) $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT COUNT(option_name) 
				FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				AND option_value < %d
				",
				$like_query,
				$now
			)
		);

		if ( $expired_transients > 20 ) {
			$transient_savings        = $expired_transients * 1024;
			$estimated_savings_bytes += $transient_savings;

			$this->add_finding(
				$run_id,
				'expired_transients',
				__( 'Expired Transients Clog Options', 'ud-audit-manager' ),
				'low',
				[
					/* translators: %d: Expired transients count */
					'description'      => sprintf( __( 'Found %d expired temporary transients inside the options table.', 'ud-audit-manager' ), $expired_transients ),
					'why_it_matters'   => __( 'Transients expire automatically but WordPress does not sweep them out unless queried again, slowing options database updates.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Run a transient clearing script or plugin to clean up expired rows.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Clear expired transients.', 'ud-audit-manager' ),
					/* translators: %s: Options table name */
					'location'         => sprintf( __( '%s options table', 'ud-audit-manager' ), $wpdb->options ),
				]
			);
		}
		$this->save_metric( $run_id, 'db_expired_transients_count', $expired_transients );

		// 6. Autoloaded options size (Standard recommended limit 800KB).
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
		$autoload_size = (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'" );
		if ( $autoload_size > 800 * 1024 ) {
			$formatted_size = number_format( $autoload_size / 1024.0, 1 ) . ' KB';
			$this->add_finding(
				$run_id,
				'autoload_bloat',
				__( 'Autoloaded Options Size is Too Large', 'ud-audit-manager' ),
				'high',
				[
					/* translators: %s: Autoloaded options size */
					'description'      => sprintf( __( 'Total size of autoloaded options is %s. Recommended threshold is < 800 KB.', 'ud-audit-manager' ), $formatted_size ),
					'why_it_matters'   => __( 'Autoloaded options load in memory on every single page request. Excessive sizes (>800KB) cause server bottlenecks.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Audit active plugins using options. Identify third-party settings that store heavy transient arrays and disable autoload for those options.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Perform an autoloaded options cleanup.', 'ud-audit-manager' ),
					/* translators: %s: Options table name */
					'location'         => sprintf( __( '%s autoload options query', 'ud-audit-manager' ), $wpdb->options ),
				]
			);
		}
		$this->save_metric( $run_id, 'db_autoloaded_size', $autoload_size );

		// 7. Database overhead (Data free).
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
		$tables               = $wpdb->get_results( 'SHOW TABLE STATUS' );
		$total_overhead       = 0;
		$overhead_table_count = 0;

		foreach ( $tables as $table ) {
			if ( isset( $table->Data_free ) && $table->Data_free > 0 ) {
				$total_overhead += (int) $table->Data_free;
				$overhead_table_count++;
			}
		}

		if ( $total_overhead > 1024 * 1024 ) { // Over 1MB.
			$estimated_savings_bytes += $total_overhead;
			$formatted_overhead       = number_format( $total_overhead / 1048576.0, 2 ) . ' MB';

			$this->add_finding(
				$run_id,
				'table_overhead',
				__( 'Database Tables have Overhead Space', 'ud-audit-manager' ),
				'medium',
				[
					/* translators: 1: Fragmented space size, 2: Number of tables */
					'description'      => sprintf( __( 'Found %1$s of fragmented space across %2$d database tables.', 'ud-audit-manager' ), $formatted_overhead, $overhead_table_count ),
					'why_it_matters'   => __( 'Overhead represents free space that the database engine has allocated but not released after row deletions. Fragmented tables increase disk footprint.', 'ud-audit-manager' ),
					'how_to_fix'       => __( 'Run an OPTIMIZE TABLE command on the affected tables to reconstruct indexes and release empty space.', 'ud-audit-manager' ),
					'suggested_action' => __( 'Optimize database tables.', 'ud-audit-manager' ),
					/* translators: %d: Number of tables */
					'location'         => sprintf( __( '%d fragmented database tables', 'ud-audit-manager' ), $overhead_table_count ),
				]
			);
		}
		$this->save_metric( $run_id, 'db_overhead_bytes', $total_overhead );

		// Save the total estimated savings (deprecated).
		$this->save_metric( $run_id, 'db_estimated_savings_bytes', $estimated_savings_bytes );

		return [
			'completed' => true,
			'offset'    => 0,
			'total'     => 1,
		];
	}
}
