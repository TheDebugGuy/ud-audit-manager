<?php
/**
 * Template: Dashboard View.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<div class="udam-wrap">
		<!-- Top Bar Header -->
		<div class="udam-header">
			<div class="udam-title-area">
				<h6><?php esc_html_e( 'UD Audit Manager', 'ud-audit-manager' ); ?></h6>
				<p><?php esc_html_e( 'Overview of your website\'s health and performance.', 'ud-audit-manager' ); ?></p>
			</div>
			<div class="udam-actions">
				<?php
				$udam_settings = UDAuditManager\Includes\Container::instance()->get( 'settings' );
				$udam_is_dark  = $udam_settings ? (bool) $udam_settings->get( 'dark_mode', false ) : false;
				$udam_toggle_text = $udam_is_dark ? __( 'Light Mode', 'ud-audit-manager' ) : __( 'Dark Mode', 'ud-audit-manager' );
				?>
				<button id="udam-toggle-dark" class="udam-btn udam-btn-secondary"><?php echo esc_html( $udam_toggle_text ); ?></button>
				<button id="udam-trigger-scan" class="udam-btn udam-btn-primary">
					<span class="dashicons dashicons-update"></span>
					<span><?php esc_html_e( 'Run Site Audit', 'ud-audit-manager' ); ?></span>
				</button>
			</div>
		</div>

		<!-- Scanner Progress overlay (Hidden by default) -->
		<div id="udam-progress-overlay" class="udam-scanner-overlay" style="display: none;">
			<h3 id="udam-scan-status-text"><?php esc_html_e( 'Initiating Site Scan...', 'ud-audit-manager' ); ?></h3>
			<div class="udam-progress-bar-bg">
				<div id="udam-progress-bar-fill" class="udam-progress-bar-fill"></div>
			</div>
			<p style="font-size: 13px; color: var(--udam-text-muted);">
				<?php esc_html_e( 'Checking components. Please keep this browser window open during analysis.', 'ud-audit-manager' ); ?>
			</p>
		</div>

		<!-- Main Dashboard Stats View (Populated via JS) -->
		<div id="udam-dashboard-content">
			<!-- Dashboard Status Card Container -->
			<div id="udam-status-card-wrap" class="udam-card skeleton" style="margin-bottom: 24px; height: 120px;"></div>

			<!-- Quick Stats Row (Placeholder Skeletons) -->
			<div class="udam-quick-stats">
				<div class="udam-stat-item skeleton" style="height: 80px;"></div>
				<div class="udam-stat-item skeleton" style="height: 80px;"></div>
				<div class="udam-stat-item skeleton" style="height: 80px;"></div>
				<div class="udam-stat-item skeleton" style="height: 80px;"></div>
				<div class="udam-stat-item skeleton" style="height: 80px;"></div>
			</div>

			<div class="udam-grid">
				<!-- Score Ring Card -->
				<div class="udam-col-4 udam-card skeleton" style="height: 280px;"></div>

				<!-- Category Scores Cards -->
				<div class="udam-col-8 skeleton" style="height: 280px; border-radius: var(--udam-radius); background: var(--udam-border); opacity: 0.5;"></div>
			</div>

			<div class="udam-grid">
				<!-- History Graph Card -->
				<div class="udam-col-5 udam-card skeleton" style="height: 350px;"></div>

				<!-- Severity Breakdown Card -->
				<div class="udam-col-4 udam-card skeleton" style="height: 350px;"></div>

				<!-- Priority Fix Center Card -->
				<div class="udam-col-3 udam-card skeleton" style="height: 350px;"></div>
			</div>
		</div>

		<!-- Dashboard Empty State layout (Shown if no runs in DB) -->
		<div id="udam-empty-state" class="udam-card udam-empty-state" style="display: none;">
			<span class="dashicons dashicons-chart-area" style="font-size: 64px; width: 64px; height: 64px; color: var(--udam-text-muted); opacity: 0.5;"></span>
			<h3><?php esc_html_e( 'No Audit Scans Run Yet', 'ud-audit-manager' ); ?></h3>
			<p><?php esc_html_e( 'Run your first site audit scan to generate custom recommendations, SEO diagnostics, and performance indexes.', 'ud-audit-manager' ); ?></p>
			<button id="empty-state-scan-btn" class="udam-btn udam-btn-primary" style="margin-top: 16px;">
				<span class="dashicons dashicons-update"></span>
				<span><?php esc_html_e( 'Run First Site Audit', 'ud-audit-manager' ); ?></span>
			</button>
		</div>
	</div>
</div>
