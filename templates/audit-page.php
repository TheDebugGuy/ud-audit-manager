<?php
/**
 * Template: Audit Category View.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only access to page parameter.
$udam_is_full_audit = ( 'ud-audit-manager-audit' === ( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '' ) );
$udam_title         = $udam_is_full_audit ? __( 'Full Site Audit Results', 'ud-audit-manager' ) : __( 'Category Audit', 'ud-audit-manager' );
$udam_desc          = $udam_is_full_audit ? __( 'Comprehensive list of all optimization opportunities and issues found across your website.', 'ud-audit-manager' ) : __( 'Detailed findings and custom recommendations for this category.', 'ud-audit-manager' );
$udam_scan_btn_text = $udam_is_full_audit ? __( 'Run Full Audit', 'ud-audit-manager' ) : __( 'Analyze Category', 'ud-audit-manager' );
$udam_summary_title = $udam_is_full_audit ? __( 'Audit Summary & Issue Breakdown', 'ud-audit-manager' ) : __( 'Category Summary', 'ud-audit-manager' );
?>

<div class="wrap">
	<div class="udam-wrap">
		<!-- Top Bar Header -->
		<div class="udam-header">
			<div class="udam-title-area">
				<h6 id="udam-module-title"><?php echo esc_html( $udam_title ); ?></h6>
				<p id="udam-module-desc"><?php echo esc_html( $udam_desc ); ?></p>
			</div>
			<div class="udam-actions">
				<?php
				$udam_settings = UDAuditManager\Includes\Container::instance()->get( 'settings' );
				$udam_is_dark  = $udam_settings ? (bool) $udam_settings->get( 'dark_mode', false ) : false;
				$udam_toggle_text = $udam_is_dark ? __( 'Light Mode', 'ud-audit-manager' ) : __( 'Dark Mode', 'ud-audit-manager' );
				?>
				<button id="udam-toggle-dark" class="udam-btn udam-btn-secondary"><?php echo esc_html( $udam_toggle_text ); ?></button>
				<button id="udam-trigger-module-scan" class="udam-btn udam-btn-primary">
					<span class="dashicons dashicons-update"></span>
					<span id="udam-scan-btn-text"><?php echo esc_html( $udam_scan_btn_text ); ?></span>
				</button>
			</div>
		</div>

		<!-- Scanner Progress overlay -->
		<div id="udam-progress-overlay" class="udam-scanner-overlay" style="display: none;">
			<h3 id="udam-scan-status-text"><?php esc_html_e( 'Scanning Category...', 'ud-audit-manager' ); ?></h3>
			<div class="udam-progress-bar-bg">
				<div id="udam-progress-bar-fill" class="udam-progress-bar-fill"></div>
			</div>
			<p style="font-size: 13px; color: var(--udam-text-muted);">
				<?php esc_html_e( 'Analyzing database tables. Please keep this screen open.', 'ud-audit-manager' ); ?>
			</p>
		</div>

		<!-- Score Panel Grid -->
		<div class="udam-grid">
			<div class="udam-col-4 udam-card">
				<h3 class="udam-card-title" style="margin-bottom: 12px;" id="udam-score-title"><?php echo $udam_is_full_audit ? esc_html__( 'Overall Site Score', 'ud-audit-manager' ) : esc_html__( 'Score', 'ud-audit-manager' ); ?></h3>
				<div class="udam-score-ring-wrap">
					<div class="udam-score-ring">
						<svg width="140" height="140" viewBox="0 0 140 140">
							<circle class="bg" cx="70" cy="70" r="55" stroke="var(--udam-border)" stroke-width="10" fill="transparent"></circle>
							<circle id="udam-module-score-fill" class="progress" cx="70" cy="70" r="55" stroke="var(--udam-primary)" stroke-width="10" fill="transparent" stroke-dasharray="345.575" stroke-dashoffset="345.575" style="stroke-linecap: round; transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dashoffset 1s ease-out;"></circle>
						</svg>
						<div id="udam-module-score-val" class="udam-score-val">-</div>
					</div>
				</div>
			</div>

			<div class="udam-col-8 udam-card">
				<h3 class="udam-card-title" id="udam-summary-title"><?php echo esc_html( $udam_summary_title ); ?></h3>
				<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 24px;" id="udam-module-metrics">
					<div class="udam-cat-card">
						<span class="udam-cat-title"><?php esc_html_e( 'Critical Issues', 'ud-audit-manager' ); ?></span>
						<div id="module-metric-critical" class="udam-cat-score score-critical">-</div>
					</div>
					<div class="udam-cat-card">
						<span class="udam-cat-title"><?php esc_html_e( 'Warnings', 'ud-audit-manager' ); ?></span>
						<div id="module-metric-warnings" class="udam-cat-score score-warning">-</div>
					</div>
					<div class="udam-cat-card">
						<span class="udam-cat-title"><?php esc_html_e( 'Recommendations', 'ud-audit-manager' ); ?></span>
						<div id="module-metric-recommendations" class="udam-cat-score score-good">-</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Filter and Table List area -->
		<div class="udam-card" style="padding: 0;">
			<div style="padding: 20px; border-bottom: 1px solid var(--udam-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
				<h3 class="udam-card-title" style="margin: 0;"><?php esc_html_e( 'Discovered Audit Findings', 'ud-audit-manager' ); ?></h3>
				
				<!-- Filters -->
				<div style="display: flex; gap: 8px; flex-wrap: wrap;">
					<input type="text" id="udam-filter-search" class="udam-setting-input" placeholder="<?php esc_attr_e( 'Search findings...', 'ud-audit-manager' ); ?>" style="width: 180px;">
					
					<select id="udam-filter-severity" class="udam-setting-select">
						<option value=""><?php esc_html_e( 'All Severities', 'ud-audit-manager' ); ?></option>
						<option value="critical"><?php esc_html_e( 'Critical', 'ud-audit-manager' ); ?></option>
						<option value="high"><?php esc_html_e( 'High', 'ud-audit-manager' ); ?></option>
						<option value="medium"><?php esc_html_e( 'Medium', 'ud-audit-manager' ); ?></option>
						<option value="low"><?php esc_html_e( 'Low', 'ud-audit-manager' ); ?></option>
						<option value="info"><?php esc_html_e( 'Info', 'ud-audit-manager' ); ?></option>
					</select>

					<select id="udam-filter-status" class="udam-setting-select">
						<option value="open"><?php esc_html_e( 'Open Issues', 'ud-audit-manager' ); ?></option>
						<option value="fixed"><?php esc_html_e( 'Fixed Issues', 'ud-audit-manager' ); ?></option>
						<option value="ignored"><?php esc_html_e( 'Ignored Issues', 'ud-audit-manager' ); ?></option>
						<option value=""><?php esc_html_e( 'All Issues', 'ud-audit-manager' ); ?></option>
					</select>
				</div>
			</div>

			<!-- Findings Table -->
			<div style="overflow-x: auto; padding: 0 20px 20px 20px;">
				<table class="udam-table" id="udam-findings-table" style="display: none;">
					<thead>
						<tr>
							<?php if ( $udam_is_full_audit ) : ?>
								<th width="15%"><?php esc_html_e( 'Module', 'ud-audit-manager' ); ?></th>
							<?php endif; ?>
							<th width="<?php echo $udam_is_full_audit ? '35%' : '45%'; ?>"><?php esc_html_e( 'Issue Details', 'ud-audit-manager' ); ?></th>
							<th width="15%"><?php esc_html_e( 'Severity', 'ud-audit-manager' ); ?></th>
							<th width="25%"><?php esc_html_e( 'Location / Context', 'ud-audit-manager' ); ?></th>
							<th width="15%"><?php esc_html_e( 'Status', 'ud-audit-manager' ); ?></th>
						</tr>
					</thead>
					<tbody id="udam-findings-body">
						<!-- Loaded via JS -->
						<tr class="skeleton-row"><td colspan="<?php echo $udam_is_full_audit ? 5 : 4; ?>"><div class="skeleton skeleton-text" style="height: 20px;"></div></td></tr>
						<tr class="skeleton-row"><td colspan="<?php echo $udam_is_full_audit ? 5 : 4; ?>"><div class="skeleton skeleton-text" style="height: 20px;"></div></td></tr>
						<tr class="skeleton-row"><td colspan="<?php echo $udam_is_full_audit ? 5 : 4; ?>"><div class="skeleton skeleton-text" style="height: 20px;"></div></td></tr>
					</tbody>
				</table>
			</div>

			<!-- Empty State for Findings -->
			<div id="udam-findings-empty" class="udam-empty-state" style="display: none; padding: 60px 0;">
				<span class="dashicons dashicons-yes" style="font-size: 48px; width: 48px; height: 48px; color: var(--udam-success); opacity: 0.8; margin-bottom: 12px;"></span>
				<h3><?php esc_html_e( 'No Findings Found', 'ud-audit-manager' ); ?></h3>
				<p><?php esc_html_e( 'Congratulations! Your site meets all compliance checks for this category.', 'ud-audit-manager' ); ?></p>
			</div>
		</div>
	</div>
</div>
