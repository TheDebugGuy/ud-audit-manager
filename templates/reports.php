<?php
/**
 * Template: Reports Page.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$udam_db = UDAuditManager\Includes\Container::instance()->get( 'db' );
$udam_history = $udam_db ? $udam_db->get_runs_history( 30 ) : [];
?>

<div class="wrap">
	<div class="udam-wrap">
		<!-- Top Bar Header -->
		<div class="udam-header">
			<div class="udam-title-area">
				<h6><?php esc_html_e( 'Historical Reports', 'ud-audit-manager' ); ?></h6>
				<p><?php esc_html_e( 'Download scan logs, export CSV/JSON audits.', 'ud-audit-manager' ); ?></p>
			</div>
			<div class="udam-actions">
				<?php
				$udam_settings = UDAuditManager\Includes\Container::instance()->get( 'settings' );
				$udam_is_dark  = $udam_settings ? (bool) $udam_settings->get( 'dark_mode', false ) : false;
				$udam_toggle_text = $udam_is_dark ? __( 'Light Mode', 'ud-audit-manager' ) : __( 'Dark Mode', 'ud-audit-manager' );
				?>
				<button id="udam-toggle-dark" class="udam-btn udam-btn-secondary"><?php echo esc_html( $udam_toggle_text ); ?></button>
			</div>
		</div>

		<div class="udam-grid">
			<!-- Runs History and Exports -->
			<div class="udam-col-12" style="margin-bottom: 24px;">
				<div class="udam-card">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid var(--udam-border); padding-bottom: 8px; flex-wrap: wrap; gap: 12px;">
						<h3 class="udam-card-title" style="margin: 0; border: none; padding: 0;">
							<?php esc_html_e( 'Audit Runs History', 'ud-audit-manager' ); ?>
						</h3>
						<!-- <div>
							<select id="udam-report-filter-source" class="udam-setting-select">
								<option value=""><?php esc_html_e( 'All Sources', 'ud-audit-manager' ); ?></option>
								<option value="manual">🖱 <?php esc_html_e( 'Manual', 'ud-audit-manager' ); ?></option>
								<option value="scheduled">⏰ <?php esc_html_e( 'Scheduled', 'ud-audit-manager' ); ?></option>
								<option value="setup">🚀 <?php esc_html_e( 'Setup Wizard', 'ud-audit-manager' ); ?></option>
								<option value="system">⚙ <?php esc_html_e( 'System', 'ud-audit-manager' ); ?></option>
								<option value="api">🔌 <?php esc_html_e( 'API', 'ud-audit-manager' ); ?></option>
								<option value="autofix">🔧 <?php esc_html_e( 'Autofix', 'ud-audit-manager' ); ?></option>
							</select>
						</div> -->
					</div>

					<div style="overflow-x: auto;">
						<table class="udam-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Started', 'ud-audit-manager' ); ?></th>
									<th><?php esc_html_e( 'Completed', 'ud-audit-manager' ); ?></th>
									<th><?php esc_html_e( 'Run Type', 'ud-audit-manager' ); ?></th>
									<!-- <th><?php esc_html_e( 'Source', 'ud-audit-manager' ); ?></th> -->
									<th><?php esc_html_e( 'Status', 'ud-audit-manager' ); ?></th>
									<th><?php esc_html_e( 'Duration', 'ud-audit-manager' ); ?></th>
									<th><?php esc_html_e( 'Score', 'ud-audit-manager' ); ?></th>
									<th style="text-align: right;"><?php esc_html_e( 'Full Audit Export', 'ud-audit-manager' ); ?></th>
								</tr>
							</thead>
							<tbody id="udam-reports-history-tbody">
								<tr>
									<td colspan="8" style="text-align: center; color: var(--udam-text-muted); padding: 24px;">
										<?php esc_html_e( 'Loading audit runs...', 'ud-audit-manager' ); ?>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
