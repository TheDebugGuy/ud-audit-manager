<?php
/**
 * Template: Settings Page.
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
				<h6><?php esc_html_e( 'Audit Settings & Tools', 'ud-audit-manager' ); ?></h6>
				<p><?php esc_html_e( 'Configure audit scoring weights, schedule cron frequencies, and clean up historical scan database data.', 'ud-audit-manager' ); ?></p>
			</div>
			<div class="udam-actions">
				<?php
				$udam_settings = UDAuditManager\Includes\Container::instance()->get( 'settings' );
				$udam_is_dark  = $udam_settings ? (bool) $udam_settings->get( 'dark_mode', false ) : false;
				$udam_toggle_text = $udam_is_dark ? __( 'Light Mode', 'ud-audit-manager' ) : __( 'Dark Mode', 'ud-audit-manager' );
				?>
				<button id="udam-toggle-dark" class="udam-btn udam-btn-secondary"><?php echo esc_html( $udam_toggle_text ); ?></button>
				<button id="udam-save-settings" class="udam-btn udam-btn-primary">
					<span class="dashicons dashicons-saved"></span>
					<span><?php esc_html_e( 'Save Settings', 'ud-audit-manager' ); ?></span>
				</button>
			</div>
		</div>

		<div class="udam-grid">
			<!-- Settings Config Form -->
			<div class="udam-col-8">
				<form id="udam-settings-form">
					<!-- Module Toggle Card -->
					<div class="udam-card" style="margin-bottom: 24px;">
						<h3 class="udam-card-title" style="margin-bottom: 15px; border-bottom: 1px solid var(--udam-border); padding-bottom: 8px;">
							<?php esc_html_e( 'Active Audit Modules', 'ud-audit-manager' ); ?>
						</h3>
						<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
							<?php
							$udam_modules = [
								'seo'           => __( 'SEO Audit Module', 'ud-audit-manager' ),
								'performance'   => __( 'Performance Audit Module', 'ud-audit-manager' ),
								'accessibility' => __( 'Accessibility Audit Module', 'ud-audit-manager' ),
								'security'      => __( 'Security Audit Module', 'ud-audit-manager' ),
								'database'      => __( 'Database Audit Module', 'ud-audit-manager' ),
								'content'       => __( 'Content Quality Module', 'ud-audit-manager' ),
								'plugin'        => __( 'Plugins Health Module', 'ud-audit-manager' ),
								'theme'         => __( 'Themes Health Module', 'ud-audit-manager' ),
							];

							/**
							 * Filters the settings page active audit modules list.
							 *
							 * @since 1.0.1
							 * @param array $udam_modules Array of module slugs to labels.
							 */
							$udam_modules = apply_filters( 'udam_settings_modules_list', $udam_modules );

							foreach ( $udam_modules as $udam_key => $udam_title ) : ?>
								<label style="display: flex; align-items: center; cursor: pointer; padding: 6px 0;">
									<input type="checkbox" name="modules[<?php echo esc_attr( $udam_key ); ?>]" value="1" class="udam-setting-chk" data-group="modules" data-key="<?php echo esc_attr( $udam_key ); ?>" style="margin-right: 8px;">
									<span><?php echo esc_html( $udam_title ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Advanced Settings Collapsible -->
					<!-- <details class="udam-card" style="margin-bottom: 24px; border: 1px solid var(--udam-border); border-radius: var(--udam-radius); padding: 16px;">
						<summary style="font-weight: 600; font-size: 14px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; user-select: none; outline: none;">
							<span><?php esc_html_e( 'Advanced Settings (Scoring Weights)', 'ud-audit-manager' ); ?></span>
							<span class="dashicons dashicons-arrow-down" style="font-size: 18px; width: 18px; height: 18px; color: var(--udam-text-muted);"></span>
						</summary>
						<div style="margin-top: 16px; border-top: 1px solid var(--udam-border); padding-top: 16px;">
							<p style="font-size: 13px; color: var(--udam-text-muted); margin-bottom: 16px;">
								<?php esc_html_e( 'Set deduction weights applied to category scores for unique failures.', 'ud-audit-manager' ); ?>
							</p>

							<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
								<?php
								$udam_weights = [
									'critical' => __( 'Critical Issue Penalty', 'ud-audit-manager' ),
									'high'     => __( 'High Severity Penalty', 'ud-audit-manager' ),
									'medium'   => __( 'Medium Severity Penalty', 'ud-audit-manager' ),
									'low'      => __( 'Low Severity Penalty', 'ud-audit-manager' ),
								];

								foreach ( $udam_weights as $udam_key => $udam_label ) : ?>
									<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed var(--udam-border); padding-bottom: 8px;">
										<span style="font-weight: 500; font-size: 13px;"><?php echo esc_html( $udam_label ); ?></span>
										<input type="number" class="udam-setting-input" data-group="severity_weights" data-key="<?php echo esc_attr( $udam_key ); ?>" min="0" max="100" style="width: 70px;">
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</details> -->

					<!-- Automation Preferences -->
					<!-- <div class="udam-card">
						<h3 class="udam-card-title" style="margin-bottom: 15px; border-bottom: 1px solid var(--udam-border); padding-bottom: 8px;">
							<?php esc_html_e( 'Scan Preferences & Cron schedules', 'ud-audit-manager' ); ?>
						</h3>

						<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
							<div style="background-color: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--udam-danger); padding: 12px; margin-bottom: 16px; border-radius: 4px;">
								<strong style="color: var(--udam-danger); display: block; margin-bottom: 4px;"><?php esc_html_e( 'WP-Cron is Disabled!', 'ud-audit-manager' ); ?></strong>
								<span style="font-size: 12px; color: var(--udam-text);"><?php esc_html_e( 'WordPress automated cron execution is disabled in your configuration. Scheduled audits will NOT run automatically unless you configure a system-level cron job to trigger wp-cron.php.', 'ud-audit-manager' ); ?></span>
							</div>
						<?php endif; ?>

						<div style="margin-bottom: 16px; background: var(--udam-border); padding: 12px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; opacity: 0.95;">
							<div>
								<span style="font-size: 11px; display: block; text-transform: uppercase; letter-spacing: 0.5px; color: var(--udam-text-muted); font-weight: 600;"><?php esc_html_e( 'Scheduled Testing', 'ud-audit-manager' ); ?></span>
								<p style="margin: 4px 0 0 0; font-size: 12px; color: var(--udam-text);"><?php esc_html_e( 'Test the automated background scan runner instantly.', 'ud-audit-manager' ); ?></p>
							</div>
							<button type="button" id="udam-test-scheduler" class="udam-btn udam-btn-secondary" style="padding: 6px 12px; font-size:12px;">
								<span class="dashicons dashicons-update" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px; vertical-align: middle;"></span>
								<span><?php esc_html_e( 'Run Scheduled Audit Now', 'ud-audit-manager' ); ?></span>
							</button>
						</div>

						<div style="margin-bottom: 16px;">
							<label style="font-weight: 600; display: block; margin-bottom: 6px;"><?php esc_html_e( 'Scheduled Audits Frequency', 'ud-audit-manager' ); ?></label>
							<select id="setting-cron-frequency" class="udam-setting-select" data-key="cron_frequency" style="width: 100%; max-width: 300px;">
								<option value="disabled"><?php esc_html_e( 'Disable Automation', 'ud-audit-manager' ); ?></option>
								<option value="daily"><?php esc_html_e( 'Daily Automated Audit', 'ud-audit-manager' ); ?></option>
								<option value="weekly"><?php esc_html_e( 'Weekly Automated Audit', 'ud-audit-manager' ); ?></option>
								<option value="monthly"><?php esc_html_e( 'Monthly Automated Audit', 'ud-audit-manager' ); ?></option>
							</select>
						</div>

						<div style="margin-bottom: 16px;">
							<label style="font-weight: 600; display: block; margin-bottom: 6px;"><?php esc_html_e( 'Audit Reports Retention Limit', 'ud-audit-manager' ); ?></label>
							<input type="number" id="setting-report-retention" class="udam-setting-input" data-key="report_retention" min="5" max="100" style="width: 80px;">
							<span style="font-size: 12px; color: var(--udam-text-muted); margin-left: 8px;"><?php esc_html_e( 'Keep up to this number of completed scan reports.', 'ud-audit-manager' ); ?></span>
						</div>

						<div style="margin-bottom: 16px;">
							<label style="display: flex; align-items: center; cursor: pointer; font-weight: 600;">
								<input type="checkbox" id="setting-dark-mode" class="udam-setting-chk" data-key="dark_mode" style="margin-right: 8px;">
								<span><?php esc_html_e( 'Enable Dark Mode Theme', 'ud-audit-manager' ); ?></span>
							</label>
						</div>
					</div> -->

					<!-- Performance & Batch Limits -->
					<div class="udam-card" style="margin-bottom: 24px;">
						<h3 class="udam-card-title" style="margin-bottom: 15px; border-bottom: 1px solid var(--udam-border); padding-bottom: 8px;">
							<?php esc_html_e( 'Performance & Batch Limits', 'ud-audit-manager' ); ?>
						</h3>
						<div style="margin-bottom: 16px;">
							<label style="font-weight: 600; display: block; margin-bottom: 6px;"><?php esc_html_e( 'Post/Page Batch Query Limit', 'ud-audit-manager' ); ?></label>
							<input type="number" id="setting-perf-limits-posts" class="udam-setting-input" data-key="perf_limits_posts" min="5" max="500" style="width: 80px;">
							<span style="font-size: 12px; color: var(--udam-text-muted); margin-left: 8px;">
								<?php esc_html_e( 'Define the number of items parsed per sequential crawl step (default is 50). Lower values reduce CPU usage on limited servers.', 'ud-audit-manager' ); ?>
							</span>
						</div>
					</div>
				</form>
			</div>

			<!-- Sidebar Actions & System Log view -->
			<div class="udam-col-4">
				<!-- Data Cleanup Tools -->
				<div class="udam-card" style="margin-bottom: 24px;">
					<h3 class="udam-card-title" style="margin-bottom: 15px; border-bottom: 1px solid var(--udam-border); padding-bottom: 8px; color: var(--udam-danger);">
						<?php esc_html_e( 'Data Cleanup Tools', 'ud-audit-manager' ); ?>
					</h3>
					<p style="font-size: 12px; color: var(--udam-text-muted); margin-bottom: 16px;">
						<?php esc_html_e( 'Permanently wipe scan historical indexes, findings database tables, or reset settings.', 'ud-audit-manager' ); ?>
					</p>

					<div style="display: flex; flex-direction: column; gap: 10px;">
						<button class="udam-btn udam-btn-danger" id="cleanup-history-btn" style="width: 100%; padding: 8px;">
							<span class="dashicons dashicons-trash"></span>
							<span><?php esc_html_e( 'Delete Scan History', 'ud-audit-manager' ); ?></span>
						</button>
						<button class="udam-btn udam-btn-danger" id="cleanup-logs-btn" style="width: 100%; padding: 8px;">
							<span class="dashicons dashicons-document"></span>
							<span><?php esc_html_e( 'Delete Diagnostic Logs', 'ud-audit-manager' ); ?></span>
						</button>
						<button class="udam-btn udam-btn-danger" id="cleanup-all-btn" style="width: 100%; padding: 8px;">
							<span class="dashicons dashicons-admin-settings"></span>
							<span><?php esc_html_e( 'Full Plugin Reset', 'ud-audit-manager' ); ?></span>
						</button>
					</div>
				</div>

				<!-- Live Diagnostic Logs terminal reader -->
				<div class="udam-card">
					<h3 class="udam-card-title" style="margin-bottom: 15px; border-bottom: 1px solid var(--udam-border); padding-bottom: 8px;">
						<?php esc_html_e( 'Diagnostic Logger Output', 'ud-audit-manager' ); ?>
					</h3>

					<textarea id="udam-log-terminal" readonly style="width: 100%; height: 160px; font-family: monospace; font-size: 11px; background: #000; color: #0f0; border-radius: 6px; padding: 10px; margin-bottom: 12px; border: none; resize: none;"></textarea>

					<div style="display: flex; gap: 8px;">
						<button class="udam-btn udam-btn-secondary" id="refresh-logs-btn" style="flex: 1; padding: 6px;">
							<?php esc_html_e( 'Refresh Output', 'ud-audit-manager' ); ?>
						</button>
						<button class="udam-btn udam-btn-secondary" id="clear-logs-btn" style="flex: 1; padding: 6px;">
							<?php esc_html_e( 'Clear Log File', 'ud-audit-manager' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
