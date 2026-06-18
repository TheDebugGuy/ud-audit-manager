<?php
/**
 * Template: Setup Wizard Onboarding.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$udam_container = UDAuditManager\Includes\Container::instance();
$udam_req_checker = $udam_container->get( 'requirements' );
$udam_settings    = $udam_container->get( 'settings' );

$udam_checks = $udam_req_checker ? $udam_req_checker->check_requirements() : [];
$udam_is_compatible = $udam_req_checker ? $udam_req_checker->is_compatible() : false;
$udam_defaults = $udam_settings ? $udam_settings->get_defaults() : [];
?>

<div class="wrap">
	<div class="udam-wrap udam-setup-box udam-card">
		<div class="udam-header" style="border: none; margin-bottom: 12px; padding-bottom: 0;">
			<div class="udam-title-area">
				<h6><?php esc_html_e( 'UD Audit Manager Setup', 'ud-audit-manager' ); ?></h6>
				<p><?php esc_html_e( 'Configure your website auditor in just a few quick steps.', 'ud-audit-manager' ); ?></p>
			</div>
		</div>

		<!-- Steps Timeline -->
		<div class="udam-wizard-steps">
			<div class="udam-wizard-step active" data-step="1">
				<div class="udam-wizard-dot">1</div>
				<span class="udam-wizard-label"><?php esc_html_e( 'Requirements', 'ud-audit-manager' ); ?></span>
			</div>
			<div class="udam-wizard-step" data-step="2">
				<div class="udam-wizard-dot">2</div>
				<span class="udam-wizard-label"><?php esc_html_e( 'Modules', 'ud-audit-manager' ); ?></span>
			</div>
			<div class="udam-wizard-step" data-step="3">
				<div class="udam-wizard-dot">3</div>
				<span class="udam-wizard-label"><?php esc_html_e( 'Ready', 'ud-audit-manager' ); ?></span>
			</div>
		</div>

		<form id="udam-setup-form">
			<!-- Step 1 Content -->
			<div class="udam-wizard-content" data-step="1">
				<h3><?php esc_html_e( 'System Compatibility Check', 'ud-audit-manager' ); ?></h3>
				<p><?php esc_html_e( 'We verify that your local environment meets requirements for performing loopback audits.', 'ud-audit-manager' ); ?></p>

				<ul class="udam-req-list">
					<?php foreach ( $udam_checks as $udam_key => $udam_check ) : ?>
						<li class="udam-req-item">
							<div>
								<span class="udam-req-name"><?php echo esc_html( $udam_check['name'] ); ?></span>
								<span style="font-size: 11px; display: block; color: var(--udam-text-muted);">
									<?php
									/* translators: 1: Required setting, 2: Current setting */
									echo esc_html( sprintf( __( 'Required: %1$s | Current: %2$s', 'ud-audit-manager' ), $udam_check['required'], $udam_check['current'] ) );
									?>
								</span>
							</div>
							<div class="udam-req-status">
								<?php if ( $udam_check['passed'] ) : ?>
									<span class="badge badge-success"><?php esc_html_e( 'Compatible', 'ud-audit-manager' ); ?></span>
								<?php else : ?>
									<span class="badge badge-critical"><?php esc_html_e( 'Warning', 'ud-audit-manager' ); ?></span>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php if ( ! $udam_is_compatible ) : ?>
					<div class="notice notice-warning inline" style="margin-top: 15px; border-radius: 6px;">
						<p><?php esc_html_e( 'Some non-critical configurations are below recommended thresholds. You may proceed, but you might encounter execution issues on larger pages.', 'ud-audit-manager' ); ?></p>
					</div>
				<?php endif; ?>
			</div>

			<!-- Step 2 Content -->
			<div class="udam-wizard-content" data-step="2" style="display: none;">
				<h3><?php esc_html_e( 'Select Audit Modules', 'ud-audit-manager' ); ?></h3>
				<p><?php esc_html_e( 'Enable or disable the specific site auditing modules according to your needs. All can be adjusted later in settings.', 'ud-audit-manager' ); ?></p>

				<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 20px;">
					<?php
					$udam_available_modules = [
						'seo'           => [ 'label' => __( 'SEO Audit', 'ud-audit-manager' ), 'desc' => __( 'Validate meta tags, alt tags, and structure.', 'ud-audit-manager' ) ],
						'performance'   => [ 'label' => __( 'Performance Audit', 'ud-audit-manager' ), 'desc' => __( 'Inspect media formats, dimensions, and assets.', 'ud-audit-manager' ) ],
						'accessibility' => [ 'label' => __( 'Accessibility Audit', 'ud-audit-manager' ), 'desc' => __( 'Evaluate contrast, buttons, links, and ARIA.', 'ud-audit-manager' ) ],
						'security'      => [ 'label' => __( 'Security Audit', 'ud-audit-manager' ), 'desc' => __( 'Assess debug modes, roles, and software versions.', 'ud-audit-manager' ) ],
						'database'      => [ 'label' => __( 'Database Audit', 'ud-audit-manager' ), 'desc' => __( 'Clean transients, junk comments, and tables.', 'ud-audit-manager' ) ],
						'content'       => [ 'label' => __( 'Content Quality Audit', 'ud-audit-manager' ), 'desc' => __( 'Monitor word counts, categorizations, and tags.', 'ud-audit-manager' ) ],
						'plugin'        => [ 'label' => __( 'Plugins Health Audit', 'ud-audit-manager' ), 'desc' => __( 'Check active plugin counts and update vectors.', 'ud-audit-manager' ) ],
						'theme'         => [ 'label' => __( 'Themes Health Audit', 'ud-audit-manager' ), 'desc' => __( 'Evaluate favicon, logo, and theme parameters.', 'ud-audit-manager' ) ],
					];

					foreach ( $udam_available_modules as $udam_mod_slug => $udam_mod_data ) :
						$udam_checked = ! isset( $udam_defaults['modules'][ $udam_mod_slug ] ) || $udam_defaults['modules'][ $udam_mod_slug ] === true;
					?>
						<label class="udam-card udam-cat-card" style="display: block; text-align: left; cursor: pointer; padding: 14px;">
							<input type="checkbox" name="modules[<?php echo esc_attr( $udam_mod_slug ); ?>]" value="1" <?php checked( $udam_checked ); ?> style="margin-right: 8px;">
							<span style="font-weight: 600; font-size: 14px;"><?php echo esc_html( $udam_mod_data['label'] ); ?></span>
							<p style="margin: 4px 0 0 24px; font-size: 11px; color: var(--udam-text-muted);"><?php echo esc_html( $udam_mod_data['desc'] ); ?></p>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Step 3 Content -->
			<div class="udam-wizard-content" data-step="3" style="display: none;">
				<div style="text-align: center; padding: 30px 10px;">
					<span class="dashicons dashicons-yes-alt" style="font-size: 64px; width: 64px; height: 64px; color: var(--udam-success); margin-bottom: 16px;"></span>
					<h3><?php esc_html_e( 'Configuration Complete!', 'ud-audit-manager' ); ?></h3>
					<p><?php esc_html_e( 'Your settings have been configured successfully. We are now ready to run the initial site audit scan.', 'ud-audit-manager' ); ?></p>
					<p style="font-size: 13px; color: var(--udam-text-muted); margin-top: 10px;">
						<?php esc_html_e( 'This scan will run asynchronously in the background and might take a moment depending on website size.', 'ud-audit-manager' ); ?>
					</p>
				</div>
			</div>

			<!-- Form Navigation Buttons -->
			<div class="udam-wizard-footer">
				<button type="button" class="udam-btn udam-btn-secondary" id="wizard-prev" style="display: none;">
					<?php esc_html_e( 'Previous Step', 'ud-audit-manager' ); ?>
				</button>
				<div style="margin-left: auto;">
					<button type="button" class="udam-btn udam-btn-primary" id="wizard-next">
						<?php esc_html_e( 'Continue', 'ud-audit-manager' ); ?>
					</button>
					<button type="button" class="udam-btn udam-btn-primary" id="wizard-finish" style="display: none;">
						<?php esc_html_e( 'Finish & Run Initial Scan', 'ud-audit-manager' ); ?>
					</button>
				</div>
			</div>
		</form>
	</div>
</div>
