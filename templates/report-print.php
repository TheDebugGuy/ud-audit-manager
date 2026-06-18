<?php
/**
 * Template: Print & PDF Report Layout.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only access to run_id parameter.
$udam_run_id = isset( $_GET['run_id'] ) ? absint( wp_unslash( $_GET['run_id'] ) ) : 0;
$udam_db     = UDAuditManager\Includes\Container::instance()->get( 'db' );

if ( ! $udam_db ) {
	wp_die( esc_html__( 'Database layer is missing.', 'ud-audit-manager' ) );
}

$udam_run = $udam_db->get_run( $udam_run_id );
if ( ! $udam_run ) {
	wp_die( esc_html__( 'Audit report not found.', 'ud-audit-manager' ) );
}

$udam_findings = $udam_db->get_findings( $udam_run_id );
$udam_snapshot = [];

$udam_scores = json_decode( $udam_run->scores_breakdown, true ) ?: [];
$udam_stats  = json_decode( $udam_run->stats, true ) ?: [];

// Enqueue styles and scripts for this custom print page.
wp_enqueue_style(
	'udam-report-print',
	UDAM_URL . 'assets/css/report-print.css',
	array(),
	UDAM_VERSION
);

wp_enqueue_script(
	'udam-report-print',
	UDAM_URL . 'assets/js/report-print.js',
	array(),
	UDAM_VERSION,
	true
);

wp_localize_script(
	'udam-report-print',
	'udamPrint',
	array(
		'runId'   => $udam_run_id,
		'siteUrl' => home_url(),
	)
);

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<title><?php echo esc_html( sprintf(
		/* translators: %s: Site name */
		__( 'Site Audit Report - %s', 'ud-audit-manager' ),
		get_bloginfo( 'name' )
	) ); ?></title>
	<?php wp_print_styles( 'udam-report-print' ); ?>
</head>
<body>
	<div class="print-container">
		<!-- Header -->
		<div class="print-header">
			<div class="print-title">
				<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
				<p><?php echo esc_html( sprintf(
					/* translators: %s: Site home URL */
					__( 'Site URL: %s', 'ud-audit-manager' ),
					home_url()
				) ); ?></p>
				<p><?php echo esc_html( sprintf(
					/* translators: %s: Completed run timestamp */
					__( 'Audit Run Timestamp: %s', 'ud-audit-manager' ),
					date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $udam_run->completed_at ) )
				) ); ?></p>
			</div>
			<div class="overall-score-box">
				<div class="overall-score-val"><?php echo esc_html( (int) $udam_run->score ); ?></div>
				<div class="overall-score-label"><?php esc_html_e( 'Health Score', 'ud-audit-manager' ); ?></div>
			</div>
		</div>

		<!-- Overview Grid -->
		<div class="print-grid">
			<!-- Category Scores -->
			<div class="print-card">
				<h3><?php esc_html_e( 'Module Score Breakdown', 'ud-audit-manager' ); ?></h3>
				<ul class="scores-list">
					<?php foreach ( $udam_scores as $udam_module => $udam_score ) : ?>
						<li class="scores-item">
							<span style="text-transform: uppercase; font-weight: 600;"><?php echo esc_html( $udam_module ); ?></span>
							<span style="font-weight: 700;"><?php echo esc_html( (int) $udam_score ); ?>/100</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<!-- Issue Summary Stats -->
			<div class="print-card">
				<h3><?php esc_html_e( 'Issue Statistics', 'ud-audit-manager' ); ?></h3>
				<ul class="stats-list">
					<li class="stats-item">
						<span><?php esc_html_e( 'Critical Issues', 'ud-audit-manager' ); ?></span>
						<span style="font-weight: 700; color: #9b2c2c;"><?php echo esc_html( (int) ($udam_stats['critical'] ?? 0) ); ?></span>
					</li>
					<li class="stats-item">
						<span><?php esc_html_e( 'High Severity', 'ud-audit-manager' ); ?></span>
						<span style="font-weight: 700; color: #9c4221;"><?php echo esc_html( (int) ($udam_stats['high'] ?? 0) ); ?></span>
					</li>
					<li class="stats-item">
						<span><?php esc_html_e( 'Medium Severity', 'ud-audit-manager' ); ?></span>
						<span style="font-weight: 700; color: #b7791f;"><?php echo esc_html( (int) ($udam_stats['medium'] ?? 0) ); ?></span>
					</li>
					<li class="stats-item">
						<span><?php esc_html_e( 'Low Severity', 'ud-audit-manager' ); ?></span>
						<span style="font-weight: 700; color: #2b6cb0;"><?php echo esc_html( (int) ($udam_stats['low'] ?? 0) ); ?></span>
					</li>
				</ul>
			</div>
		</div>

		<!-- Detailed Findings -->
		<div class="findings-section">
			<h2><?php esc_html_e( 'Detailed Audit Findings', 'ud-audit-manager' ); ?></h2>
			<?php if ( empty( $udam_findings ) ) : ?>
				<p><?php esc_html_e( 'No open issues found. Excellent work!', 'ud-audit-manager' ); ?></p>
			<?php else : ?>
				<table class="findings-table">
					<thead>
						<tr>
							<th width="15%"><?php esc_html_e( 'Module', 'ud-audit-manager' ); ?></th>
							<th width="45%"><?php esc_html_e( 'Issue Details', 'ud-audit-manager' ); ?></th>
							<th width="15%"><?php esc_html_e( 'Severity', 'ud-audit-manager' ); ?></th>
							<th width="25%"><?php esc_html_e( 'Location', 'ud-audit-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $udam_findings as $udam_finding ) : ?>
							<tr>
								<td style="text-transform: uppercase; font-weight: 600; font-size: 11px;"><?php echo esc_html( $udam_finding->module ); ?></td>
								<td>
									<div class="finding-title"><?php echo esc_html( $udam_finding->title ); ?></div>
									<div class="finding-desc"><?php echo esc_html( $udam_finding->description ); ?></div>
								</td>
								<td>
									<span class="issue-badge badge-<?php echo esc_attr( $udam_finding->severity ); ?>">
										<?php echo esc_html( $udam_finding->severity ); ?>
									</span>
								</td>
								<td style="font-family: monospace; font-size: 11px; word-break: break-all;"><?php echo esc_html( wp_strip_all_tags( $udam_finding->location ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<?php wp_print_scripts( 'udam-report-print' ); ?>
</body>
</html>
