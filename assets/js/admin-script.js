/**
 * UD Audit Manager Admin Script.
 * Orchestrates AJAX/REST auditing loops, renders custom SVG charts/progress rings,
 * toggles theme modes, and binds settings forms.
 */
(function($) {
	'use strict';

	// Center text plugin for Doughnut charts
	const centerTextPlugin = {
		id: 'centerText',
		beforeDraw: function(chart) {
			const centerConfig = chart.config.options.plugins.centerText;
			if (centerConfig) {
				const ctx = chart.ctx;
				const chartArea = chart.chartArea;
				if (chartArea) {
					const x = (chartArea.left + chartArea.right) / 2;
					const y = (chartArea.top + chartArea.bottom) / 2;
					
					ctx.save();
					
					// Main text
					ctx.font = "bold 22px Outfit";
					ctx.fillStyle = $('body').hasClass('udam-dark-mode') ? '#f9fafb' : '#0f172a';
					ctx.textAlign = 'center';
					ctx.textBaseline = 'middle';
					ctx.fillText(centerConfig.text, x, y - 6);
					
					// Subtext
					ctx.font = "500 10px Outfit";
					ctx.fillStyle = $('body').hasClass('udam-dark-mode') ? '#9ca3af' : '#64748b';
					ctx.fillText(centerConfig.subtext, x, y + 12);
					
					ctx.restore();
				}
			}
		}
	};

	if ( typeof Chart !== 'undefined' ) {
		Chart.register(centerTextPlugin);
	} else {
		console.error( 'UD Audit Manager: Chart.js failed to load.' );
	}

	// Main controller registry
	const App = {
		// API Configuration (passed via wp_localize_script)
		api: window.udamAdmin || {},
		
		// Currently active scan variables
		scanActive: false,
		scanModules: [],
		currentModuleIndex: 0,
		currentRunId: null,

		/**
		 * Initialization.
		 */
		init: function() {
			this.initTheme();
			this.bindEvents();
			
			// Show queued notices
			if (this.api.queued_notices && this.api.queued_notices.length > 0) {
				const self = this;
				this.api.queued_notices.forEach(notice => {
					if (self && typeof self.toast === 'function') {
						self.toast(notice.message, notice.type, notice.title, notice.action);
					}
				});
			}

			this.initScheduledPolling();
			
			// Initialize specific page controllers based on localized page slug
			if (this.api.page === 'dashboard') {
				this.loadDashboardData();
			} else if (this.api.page === 'reports') {
				this.loadReportsList();
			} else if (this.api.page === 'settings') {
				this.loadSettings();
			} else if (this.api.page === 'setup') {
				this.initSetupWizard();
			} else {
				// Dynamic Category Page
				this.loadModuleData(this.api.page);
			}
		},

		/**
		 * Handles Dark Mode loading and body classes toggles.
		 */
		initTheme: function() {
			const dbDark = this.api.dark_mode;
			const isDarkMode = dbDark !== undefined ? dbDark : (localStorage.getItem('udam_dark_mode') === 'true');
			
			localStorage.setItem('udam_dark_mode', isDarkMode ? 'true' : 'false');
			
			if (isDarkMode) {
				$('body').addClass('udam-dark-mode');
				$('#udam-toggle-dark').text('Light Mode');
				$('#setting-dark-mode').prop('checked', true);
			} else {
				$('body').removeClass('udam-dark-mode');
				$('#udam-toggle-dark').text('Dark Mode');
				$('#setting-dark-mode').prop('checked', false);
			}
		},

		toggleTheme: function() {
			const body = $('body');
			const isDark = body.hasClass('udam-dark-mode');
			const self = this;
			
			const newDarkState = !isDark;
			
			if (newDarkState) {
				body.addClass('udam-dark-mode');
				localStorage.setItem('udam_dark_mode', 'true');
				this.api.dark_mode = true;
				$('#udam-toggle-dark').text('Light Mode');
				$('#setting-dark-mode').prop('checked', true);
			} else {
				body.removeClass('udam-dark-mode');
				localStorage.setItem('udam_dark_mode', 'false');
				this.api.dark_mode = false;
				$('#udam-toggle-dark').text('Dark Mode');
				$('#setting-dark-mode').prop('checked', false);
			}

			// Redraw dashboard charts dynamically on theme toggles
			if (this.api.page === 'dashboard' && this.dashboardData) {
				this.renderDashboard(this.dashboardData);
			}

			// Synchronize with database settings
			this.request('/settings', 'POST', { dark_mode: newDarkState });
		},

		/**
		 * Bind standard event listeners.
		 */
		bindEvents: function() {
			const self = this;

			// Theme mode toggle
			$(document).on('click', '#udam-toggle-dark', function(e) {
				e.preventDefault();
				self.toggleTheme();
			});

			// Trigger Scans
			$(document).on('click', '#udam-trigger-scan, #empty-state-scan-btn', function(e) {
				e.preventDefault();
				self.runScan('full');
			});

			$(document).on('click', '#udam-trigger-module-scan', function(e) {
				e.preventDefault();
				const target = self.api.page === 'audit' ? 'full' : self.api.page;
				self.runScan(target);
			});

			// Accordion Rows toggles in findings list
			$(document).on('click', '.udam-row-header', function() {
				const detailsRow = $(this).next('.udam-row-details');
				detailsRow.fadeToggle(150);
			});

			// Save Settings
			$(document).on('click', '#udam-save-settings', function(e) {
				e.preventDefault();
				self.saveSettings();
			});

			// Run Auto-Fix
			$(document).on('click', '.apply-auto-fix-btn', function(e) {
				e.preventDefault();
				const btn = $(this);
				const id = btn.data('id');
				
				btn.prop('disabled', true).text(self.api.l10n.fixing);
				
				self.request('/findings/fix', 'POST', { finding_id: id })
					.done(function(response) {
						if (response.success) {
							btn.text(self.api.l10n.fixed_success).css('background-color', 'var(--udam-success)');
							setTimeout(() => {
								self.loadModuleData(self.api.page);
							}, 1000);
						} else {
							btn.prop('disabled', false).text('Run Auto-Fix');
							if (self && typeof self.toast === 'function') {
								self.toast(response.message || self.api.l10n.fixing_failed, 'error', 'Fix Failed');
							}
						}
					})
					.fail(function(xhr) {
						btn.prop('disabled', false).text('Run Auto-Fix');
						if (self && typeof self.toast === 'function') {
							self.toast(xhr.responseJSON?.message || self.api.l10n.fixing_failed, 'error', 'Fix Failed');
						}
					});
			});

			// Cleanup Data Triggers
			$(document).on('click', '#cleanup-history-btn', function() { self.cleanupData('history'); });
			$(document).on('click', '#cleanup-logs-btn', function() { self.cleanupData('logs'); });
			$(document).on('click', '#cleanup-all-btn', function() { self.cleanupData('all'); });

			// Log management buttons
			$(document).on('click', '#refresh-logs-btn', function() { self.loadLogs(); });
			$(document).on('click', '#clear-logs-btn', function() { self.clearLogs(); });

			// Run scheduler test from Settings page
			$(document).on('click', '#udam-test-scheduler', function(e) {
				e.preventDefault();
				const btn = $(this);
				const label = btn.find('span').last();
				const origText = label.text();
				btn.prop('disabled', true);
				label.text('Running...');
				
				self.request('/scheduler/run_test', 'POST')
					.done(function(response) {
						if (response.success) {
							label.text('Scheduler Run Finished!');
							btn.css('background-color', 'var(--udam-success)');
							setTimeout(() => {
								label.text(origText);
								btn.prop('disabled', false).css('background-color', '');
								self.loadLogs(); // Refresh logs if we are on settings page
							}, 2000);
						} else {
							label.text(origText);
							btn.prop('disabled', false);
							if (self && typeof self.toast === 'function') {
								self.toast('Failed to trigger scheduler.', 'error', 'Scheduler Error');
							}
						}
					})
					.fail(function(xhr) {
						label.text(origText);
						btn.prop('disabled', false);
						if (self && typeof self.toast === 'function') {
							self.toast(xhr.responseJSON?.message || 'Failed to trigger scheduler.', 'error', 'Scheduler Error');
						}
					});
			});

			// Run Cron Audit testing button from dashboard Status Card
			$(document).on('click', '#dashboard-run-cron-btn', function(e) {
				e.preventDefault();
				const btn = $(this);
				btn.prop('disabled', true).text('Running cron scan...');
				
				self.request('/scheduler/run_test', 'POST')
					.done(function(response) {
						if (response.success) {
							btn.text('Cron Started!').css('background-color', 'var(--udam-success)');
							setTimeout(() => {
								self.loadDashboardData();
							}, 1500);
						} else {
							btn.prop('disabled', false).text('Run Scheduled Audit Now');
							if (self && typeof self.toast === 'function') {
								self.toast('Failed to trigger background scan.', 'error', 'Background Scan Error');
							}
						}
					})
					.fail(function(xhr) {
						btn.prop('disabled', false).text('Run Scheduled Audit Now');
						if (self && typeof self.toast === 'function') {
							self.toast(xhr.responseJSON?.message || 'Failed to trigger background scan.', 'error', 'Background Scan Error');
						}
					});
			});
		},

		/**
		 * Fetch REST URL with authorization headers.
		 */
		request: function(endpoint, method, data = {}) {
			return $.ajax({
				url: this.api.rest_url + endpoint,
				method: method,
				dataType: 'json',
				contentType: 'application/json',
				headers: {
					'X-WP-Nonce': this.api.nonce
				},
				data: method === 'POST' ? JSON.stringify(data) : data
			});
		},

		/**
		 * AJAX Scan Orchestration Loop.
		 */
		runScan: function(type, source = 'manual') {
			if (this.scanActive) return;
			this.scanActive = true;
			this.scanType = type;

			const overlay = $('#udam-progress-overlay');
			const content = $('#udam-dashboard-content');
			const emptyState = $('#udam-empty-state');
			const progressBar = $('#udam-progress-bar-fill');
			const statusText = $('#udam-scan-status-text');

			if (this.api.page === 'dashboard') {
				content.html(`
					<div id="udam-status-card-wrap" class="udam-card skeleton" style="margin-bottom: 24px; height: 120px;"></div>
					<div class="udam-quick-stats">
						<div class="udam-stat-item skeleton" style="height: 80px;"></div>
						<div class="udam-stat-item skeleton" style="height: 80px;"></div>
						<div class="udam-stat-item skeleton" style="height: 80px;"></div>
						<div class="udam-stat-item skeleton" style="height: 80px;"></div>
						<div class="udam-stat-item skeleton" style="height: 80px;"></div>
					</div>
					<div class="udam-grid">
						<div class="udam-col-4 udam-card skeleton" style="height: 280px;"></div>
						<div class="udam-col-8 skeleton" style="height: 280px; border-radius: var(--udam-radius); background: var(--udam-border); opacity: 0.5;"></div>
					</div>
					<div class="udam-grid">
						<div class="udam-col-5 udam-card skeleton" style="height: 350px;"></div>
						<div class="udam-col-4 udam-card skeleton" style="height: 350px;"></div>
						<div class="udam-col-3 udam-card skeleton" style="height: 350px;"></div>
					</div>
				`);
			} else {
				$('#udam-module-score-val').text('-');
				this.setCircleOffset('#udam-module-score-fill', 0);
				$('#module-metric-critical').text('-');
				$('#module-metric-warnings').text('-');
				$('#module-metric-recommendations').text('-');
				
				const colspanVal = this.api.page === 'audit' ? 5 : 4;
				$('#udam-findings-empty').hide();
				$('#udam-findings-table').show();
				$('#udam-findings-body').html(`
					<tr class="skeleton-row"><td colspan="${colspanVal}"><div class="skeleton skeleton-text" style="height: 20px;"></div></td></tr>
					<tr class="skeleton-row"><td colspan="${colspanVal}"><div class="skeleton skeleton-text" style="height: 20px;"></div></td></tr>
					<tr class="skeleton-row"><td colspan="${colspanVal}"><div class="skeleton skeleton-text" style="height: 20px;"></div></td></tr>
				`);
			}

			content.css('opacity', '0.4');
			emptyState.hide();
			overlay.slideDown(200);
			progressBar.css('width', '0%');
			statusText.text(this.api.l10n.scan_started);

			const self = this;

			// 1. Initialize scan run
			this.request('/scan/start', 'POST', { type: type, source: source })
				.done(function(response) {
					self.currentRunId = response.run_id;
					self.scanModules = response.modules;
					self.currentModuleIndex = 0;
					
					if (response.resumed) {
						statusText.text('Resuming active scan...');
					}
					
					// Begin the step iteration
					self.executeScanStep(0);
				})
				.fail(function(xhr) {
					self.scanFailed(xhr.responseJSON?.message || self.api.l10n.scan_failed);
				});
		},

		executeScanStep: function(offset = 0) {
			if (this.currentModuleIndex >= this.scanModules.length) {
				this.finalizeScan();
				return;
			}

			const self = this;
			const currentModule = this.scanModules[this.currentModuleIndex];
			const progressBar = $('#udam-progress-bar-fill');
			const statusText = $('#udam-scan-status-text');

			// Calculate progress percent
			const moduleProgress = Math.round(((self.currentModuleIndex) / self.scanModules.length) * 100);
			progressBar.css('width', moduleProgress + '%');
			statusText.text(`Scanning: ${currentModule.toUpperCase()}...`);

			this.request('/scan/step', 'POST', {
				run_id: this.currentRunId,
				module: currentModule,
				offset: offset
			})
			.done(function(response) {
				if (response.completed) {
					// Move to next module
					self.currentModuleIndex++;
					self.executeScanStep(0);
				} else {
					// Process next batch of current module
					self.executeScanStep(response.offset);
				}
			})
			.fail(function(xhr) {
				self.scanFailed(xhr.responseJSON?.message || self.api.l10n.scan_failed);
			});
		},

		finalizeScan: function() {
			const self = this;
			const progressBar = $('#udam-progress-bar-fill');
			const statusText = $('#udam-scan-status-text');
			const overlay = $('#udam-progress-overlay');
			const content = $('#udam-dashboard-content');

			progressBar.css('width', '100%');
			statusText.text('Finalizing site calculations...');

			const finishedRunId = this.currentRunId;
			const type = this.scanType || 'full';

			this.request('/scan/complete', 'POST', { run_id: finishedRunId })
				.done(function() {
					self.scanActive = false;

					// Keep overlay visible showing progress state
					progressBar.css('width', '100%');
					statusText.text('Generating Report...');

					// Update history URL synchronously so loadModuleData reads the new run_id
					const url_slug = type == 'full' ? 'audit' : type;
					const newUrl = window.location.origin + window.location.pathname + `?page=ud-audit-manager-${url_slug}&run_id=${finishedRunId}`;
					window.history.replaceState( {}, document.title, newUrl );

					// Reload page content to show changes
					let refreshPromise;
					if (self.api.page === 'dashboard') {
						refreshPromise = self.loadDashboardData();
					} else {
						refreshPromise = self.loadModuleData(self.api.page);
					}

					// Hide overlay and reset opacity ONLY after new results are loaded and rendered
					if (refreshPromise && typeof refreshPromise.always === 'function') {
						refreshPromise.always(function() {
							setTimeout(function() {
								overlay.slideUp(200);
								content.css('opacity', '1');
							}, 400); // Small delay to prevent visual flashes
						});
					} else {
						setTimeout(function() {
							overlay.slideUp(200);
							content.css('opacity', '1');
						}, 800);
					}
				})
				.fail(function(xhr) {
					self.scanFailed(xhr.responseJSON?.message || self.api.l10n.scan_failed);
				});
		},

		scanFailed: function(message) {
			this.scanActive = false;
			const overlay = $('#udam-progress-overlay');
			const content = $('#udam-dashboard-content');
			const statusText = $('#udam-scan-status-text');

			statusText.text(message).css('color', 'var(--udam-danger)');
			progressBar.css('background-color', 'var(--udam-danger)');
			
			setTimeout(() => {
				overlay.slideUp(200);
				content.css('opacity', '1');
			}, 3000);
		},

		/**
		 * Dashboard View lazy-loading.
		 */
		loadDashboardData: function() {
			const self = this;
			const wrap = $('#udam-dashboard-content');
			const emptyState = $('#udam-empty-state');

			// Clear previous content and show loading skeletons immediately
			wrap.html(`
				<div id="udam-status-card-wrap" class="udam-card skeleton" style="margin-bottom: 24px; height: 120px;"></div>
				<div class="udam-quick-stats">
					<div class="udam-stat-item skeleton" style="height: 80px;"></div>
					<div class="udam-stat-item skeleton" style="height: 80px;"></div>
					<div class="udam-stat-item skeleton" style="height: 80px;"></div>
					<div class="udam-stat-item skeleton" style="height: 80px;"></div>
					<div class="udam-stat-item skeleton" style="height: 80px;"></div>
				</div>
				<div class="udam-grid">
					<div class="udam-col-4 udam-card skeleton" style="height: 280px;"></div>
					<div class="udam-col-8 skeleton" style="height: 280px; border-radius: var(--udam-radius); background: var(--udam-border); opacity: 0.5;"></div>
				</div>
				<div class="udam-grid">
					<div class="udam-col-5 udam-card skeleton" style="height: 350px;"></div>
					<div class="udam-col-4 udam-card skeleton" style="height: 350px;"></div>
					<div class="udam-col-3 udam-card skeleton" style="height: 350px;"></div>
				</div>
			`);

			return this.request('/dashboard/stats', 'GET')
				.done(function(data) {
					self.dashboardData = data;
					self.renderDashboard(data);
					if (data.running_scan) {
						self.runScan(data.running_scan.type, data.running_scan.source);
					}
				})
				.fail(function() {
					wrap.html('<div class="notice notice-error"><p>Failed to retrieve dashboard metrics.</p></div>');
				});
		},

		renderDashboard: function(data) {
			const self = this;
			const wrap = $('#udam-dashboard-content');
			const emptyState = $('#udam-empty-state');

			if (!data.latest_run) {
				if (data.running_scan || self.scanActive) {
					wrap.show();
					emptyState.hide();
				} else {
					wrap.hide();
					const autoScanQueued = window.location.search.indexOf('auto_scan=true') > -1;
					if (!autoScanQueued) {
						emptyState.show();
					} else {
						emptyState.hide();
					}
				}
				return;
			}

			emptyState.hide();
			wrap.show();

			const run = data.latest_run;
			const stats = JSON.parse(run.stats) || {};
			const scores = JSON.parse(run.scores_breakdown) || {};
			const history = data.runs_history || [];

			// 1. Calculate Quick Stats trends
			const prevRun = history.length > 1 ? history[history.length - 2] : null;
			const prevStats = prevRun ? (JSON.parse(prevRun.stats) || {}) : {};

			const calculateTrend = (latestVal, prevVal, isHigherBad = true) => {
				const latest = parseInt(latestVal || 0);
				const prev = parseInt(prevVal || 0);
				if (!prevRun) return `<span class="udam-stat-trend trend-neutral">-</span>`;
				
				const diff = latest - prev;
				if (diff > 0) {
					return `<span class="udam-stat-trend ${isHigherBad ? 'trend-bad' : 'trend-good'}">▲ +${diff} from last scan</span>`;
				} else if (diff < 0) {
					return `<span class="udam-stat-trend ${isHigherBad ? 'trend-good' : 'trend-bad'}">▼ ${diff} from last scan</span>`;
				} else {
					return `<span class="udam-stat-trend trend-neutral">No change</span>`;
				}
			};

			const totalIssuesLatest = parseInt(stats.critical || 0) + parseInt(stats.high || 0) + parseInt(stats.medium || 0) + parseInt(stats.low || 0);
			const totalIssuesPrev = prevRun ? (parseInt(prevStats.critical || 0) + parseInt(prevStats.high || 0) + parseInt(prevStats.medium || 0) + parseInt(prevStats.low || 0)) : 0;
			
			const criticalLatest = parseInt(stats.critical || 0) + parseInt(stats.high || 0);
			const criticalPrev = prevRun ? (parseInt(prevStats.critical || 0) + parseInt(prevStats.high || 0)) : 0;

			const totalTrend = calculateTrend(totalIssuesLatest, totalIssuesPrev, true);
			const criticalTrend = calculateTrend(criticalLatest, criticalPrev, true);
			const warningTrend = calculateTrend(stats.medium || 0, prevStats.medium || 0, true);
			const recommendationTrend = calculateTrend(stats.low || 0, prevStats.low || 0, true);

			// Format Last Scan Date/Time
			let lastScanTime = run.completed_at || '-';
			let lastScanDate = '-';
			if (run.completed_at) {
				const parts = run.completed_at.split(' ');
				lastScanDate = parts[0];
				lastScanTime = parts[1] ? parts[1] + (parts[2] ? ' ' + parts[2] : '') : '';
			}

			// Overall Score pill / info text
			let overallStatus = 'Excellent';
			let overallStatusClass = 'score-excellent';
			let overallText = 'Your site is in excellent shape! Keep up the good work.';
			if (run.score < 50) {
				overallStatus = 'Critical';
				overallStatusClass = 'score-critical';
				overallText = 'Critical issues detected. Resolve them immediately to protect your site.';
			} else if (run.score < 80) {
				overallStatus = 'Needs Improvement';
				overallStatusClass = 'score-warning';
				overallText = 'Your site has room for improvement. Follow recommendations below.';
			} else if (run.score < 90) {
				overallStatus = 'Good';
				overallStatusClass = 'score-good';
				overallText = 'Your site is healthy, but has minor areas of improvement.';
			}

			let statusCardHtml = '';
			// if (data.scheduler) {
			// 	statusCardHtml = self.buildStatusCardHtml(data.scheduler);
			// }

			let html = `
				${statusCardHtml}
				<!-- Quick Stats Row -->
				<div class="udam-quick-stats">
					<div class="udam-stat-item">
						<div class="udam-stat-icon-wrap icon-purple">
							<span class="dashicons dashicons-warning"></span>
						</div>
						<div class="udam-stat-details">
							<span class="udam-stat-label">Total Issues</span>
							<span class="udam-stat-value">${totalIssuesLatest}</span>
							${totalTrend}
						</div>
					</div>
					<div class="udam-stat-item">
						<div class="udam-stat-icon-wrap icon-red">
							<span class="dashicons dashicons-shield"></span>
						</div>
						<div class="udam-stat-details">
							<span class="udam-stat-label">Critical Issues</span>
							<span class="udam-stat-value">${criticalLatest}</span>
							${criticalTrend}
						</div>
					</div>
					<div class="udam-stat-item">
						<div class="udam-stat-icon-wrap icon-orange">
							<span class="dashicons dashicons-info"></span>
						</div>
						<div class="udam-stat-details">
							<span class="udam-stat-label">Warnings</span>
							<span class="udam-stat-value">${stats.medium || 0}</span>
							${warningTrend}
						</div>
					</div>
					<div class="udam-stat-item">
						<div class="udam-stat-icon-wrap icon-blue">
							<span class="dashicons dashicons-lightbulb"></span>
						</div>
						<div class="udam-stat-details">
							<span class="udam-stat-label">Recommendations</span>
							<span class="udam-stat-value">${stats.low || 0}</span>
							${recommendationTrend}
						</div>
					</div>
					<div class="udam-stat-item">
						<div class="udam-stat-icon-wrap icon-green">
							<span class="dashicons dashicons-calendar"></span>
						</div>
						<div class="udam-stat-details">
							<span class="udam-stat-label">Last Scan</span>
							<span class="udam-stat-value" style="font-size:14px; margin-top:2px;">${lastScanDate}</span>
							<span style="font-size:10px; color:var(--udam-text-muted);">${lastScanTime}</span>
						</div>
					</div>
				</div>

				<!-- Scores Grid -->
				<div class="udam-grid">
					<!-- Overall site health score -->
					<div class="udam-col-4 udam-card" style="display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding: 24px;">
						<h3 class="udam-card-title" style="margin-bottom: 12px; align-self: flex-start;">Overall Site Health Score</h3>
						<div class="udam-score-ring-wrap" style="margin-top: 10px; margin-bottom: 15px;">
							<div class="udam-score-ring">
								<svg width="140" height="140" viewBox="0 0 140 140">
									<circle class="bg" cx="70" cy="70" r="55" stroke="var(--udam-border)" stroke-width="10" fill="transparent"></circle>
									<circle id="overall-progress" class="progress" cx="70" cy="70" r="55" stroke="var(--udam-primary)" stroke-width="10" fill="transparent" stroke-dasharray="345.575" stroke-dashoffset="345.575" style="stroke-linecap: round; transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dashoffset 1s ease-out;"></circle>
								</svg>
								<div class="udam-score-val" style="display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1;">
									<span style="font-size: 36px; font-weight: 800;">${run.score}</span>
									<span style="font-size: 11px; color: var(--udam-text-muted); margin-top: 4px;">/100</span>
								</div>
							</div>
						</div>
						<span class="badge badge-${overallStatusClass.replace('score-', '')}" style="font-size: 12px; padding: 6px 12px; font-weight: 700; margin-bottom: 8px;">${overallStatus}</span>
						<p style="font-size: 12px; color: var(--udam-text-muted); margin: 0; line-height: 1.4; max-width: 220px;">${overallText}</p>
					</div>

					<!-- Category Scores Grid -->
					<div class="udam-col-8 udam-category-scores-grid">
			`;

			// Render individual category score cards
			const prevScores = prevRun ? (JSON.parse(prevRun.scores_breakdown) || {}) : {};
			Object.entries(scores).forEach(([mod, val]) => {
				let statusLabel = 'Excellent';
				let colorClass = 'success';
				if (val < 50) {
					statusLabel = 'Critical';
					colorClass = 'critical';
				} else if (val < 80) {
					statusLabel = 'Needs Work';
					colorClass = 'warning';
				} else if (val < 90) {
					statusLabel = 'Good';
					colorClass = 'good';
				}

				const prevVal = prevScores[mod];
				let trendHtml = '';
				if (prevVal !== undefined) {
					const diff = val - prevVal;
					if (diff > 0) {
						trendHtml = `<span style="color:var(--udam-success); font-size:11px; font-weight:700; margin-left:2px;">▲ +${diff}</span>`;
					} else if (diff < 0) {
						trendHtml = `<span style="color:var(--udam-danger); font-size:11px; font-weight:700; margin-left:2px;">▼ ${diff}</span>`;
					}
				}

				// Format Mod Name for Title
				const modTitle = mod.charAt(0).toUpperCase() + mod.slice(1) + ' Score';

				html += `
					<a href="admin.php?page=ud-audit-manager-${mod}" class="udam-cat-card">
						<span class="udam-cat-card-header">${modTitle}</span>
						<div class="udam-cat-card-body">
							<div style="position:relative; width:70px; height:70px; flex-shrink:0;">
								<svg width="70" height="70" viewBox="0 0 50 50">
									<circle cx="25" cy="25" r="20" stroke="var(--udam-border)" stroke-width="4" fill="transparent" />
									<circle id="${mod}-progress" class="progress" cx="25" cy="25" r="20" stroke="var(--udam-primary)" stroke-width="4" fill="transparent" stroke-dasharray="125.66" stroke-dashoffset="125.66" style="stroke-linecap: round; transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dashoffset 0.8s ease;" />
								</svg>
								<div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); text-align:center; line-height:1; display:flex; flex-direction:column; align-items:center;">
									<span style="font-size:12px; font-weight:700; color:var(--udam-text);">${val}</span>
									<span style="font-size:6px; color:var(--udam-text-muted);">/100</span>
								</div>
							</div>
							<div style="display:flex; flex-direction:column; align-items:flex-end;">
								<span class="badge badge-${colorClass}" style="font-size:10px; padding:3px 8px; font-weight:600;">${statusLabel}</span>
								<div style="margin-top: 4px; display:flex; align-items:center;">${trendHtml}</div>
							</div>
						</div>
					</a>
				`;
			});

			html += `
					</div>
				</div>

				<!-- Tabs Control Header -->
				<div class="udam-card" style="margin-bottom: 24px; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--udam-border);">
					<h3 class="udam-card-title" style="margin: 0;">Analytics & Deep Metrics</h3>
					<div style="display: flex; gap: 8px;">
						<button id="udam-tab-overview" class="udam-btn udam-btn-secondary active" style="padding: 6px 12px; font-size: 12px; border-radius: 6px;">Overview Analytics</button>
						<button id="udam-tab-advanced" class="udam-btn udam-btn-secondary" style="padding: 6px 12px; font-size: 12px; border-radius: 6px;">Advanced Metrics</button>
					</div>
				</div>

				<!-- Overview Row -->
				<div id="udam-overview-row" class="udam-grid">
					<!-- Score History Card -->
					<div class="udam-col-5 udam-card">
						<div id="udam-history-chart-container" style="height:220px; position:relative;">
							<!-- Canvas rendered dynamically -->
						</div>
					</div>

					<!-- Issues by Severity Card -->
					<div class="udam-col-4 udam-card">
						<h3 class="udam-card-title" style="margin-bottom: 16px;">Issues By Severity</h3>
						<div style="display:flex; flex-direction:column; align-items:center; gap:20px;">
							<div id="udam-severity-chart-container" style="width:160px; height:160px; position:relative;">
								<!-- Canvas rendered dynamically -->
							</div>
							<div id="udam-severity-legend-container" class="udam-severity-legend">
								<!-- Legend rendered dynamically -->
							</div>
						</div>
					</div>

					<!-- Priority Fix Center Card -->
					<div class="udam-col-3 udam-card" style="padding: 0; display:flex; flex-direction:column; justify-content:space-between;">
						<div style="padding: 16px; border-bottom: 1px solid var(--udam-border); display:flex; justify-content:space-between; align-items:center;">
							<h3 class="udam-card-title" style="margin: 0;">Top Issues</h3>
							<a href="admin.php?page=ud-audit-manager-audit" style="font-size:11px; text-decoration:none; color:var(--udam-primary); font-weight:600;">View All Issues →</a>
						</div>
						<div id="priority-fixes-list" class="udam-fix-list" style="flex-grow:1; justify-content: flex-start;">
							<!-- Priority fixes list -->
						</div>
					</div>
				</div>

				<!-- Advanced Row -->
				<div id="udam-advanced-row" class="udam-grid" style="display: none;">
					<!-- Audit Trends Card -->
					<div class="udam-col-6 udam-card">
						<h3 class="udam-card-title" style="margin-bottom: 16px;">Audit Issue Trends</h3>
						<div id="udam-trends-chart-container" style="height:220px; position:relative;">
							<!-- Canvas rendered dynamically -->
						</div>
					</div>

					<!-- Issue Distribution Card -->
					<div class="udam-col-3 udam-card">
						<h3 class="udam-card-title" style="margin-bottom: 16px;">Issue Distribution</h3>
						<div id="udam-distribution-chart-container" style="height:220px; position:relative;">
							<!-- Canvas rendered dynamically -->
						</div>
					</div>

					<!-- Module Comparisons Card -->
					<div class="udam-col-3 udam-card">
						<h3 class="udam-card-title" style="margin-bottom: 16px;">Module Comparisons</h3>
						<div id="udam-comparisons-chart-container" style="height:220px; position:relative;">
							<!-- Canvas rendered dynamically -->
						</div>
					</div>
				</div>
			`;

			wrap.html(html);

			// 2. Animate Circular Rings with Unified exact calculations
			setTimeout(() => {
				self.setCircleOffset('#overall-progress', run.score, 55);
				Object.entries(scores).forEach(([mod, val]) => {
					self.setCircleOffset(`#${mod}-progress`, val, 20);
				});
			}, 100);

			// 3. Render charts
			self.renderCharts(data);

			// 4. Render Priority Fixes
			self.renderPriorityFixes(data.priority_fixes);

			// 5. Setup Tab toggles
			$('#udam-tab-overview').on('click', function() {
				$(this).addClass('active');
				$('#udam-tab-advanced').removeClass('active');
				$('#udam-overview-row').show();
				$('#udam-advanced-row').hide();
			});

			$('#udam-tab-advanced').on('click', function() {
				$(this).addClass('active');
				$('#udam-tab-overview').removeClass('active');
				$('#udam-advanced-row').show();
				$('#udam-overview-row').hide();
				// Redraw advanced charts to ensure responsiveness works after displaying hidden elements
				if (self.distributionChartInstance) self.distributionChartInstance.resize();
				if (self.comparisonsChartInstance) self.comparisonsChartInstance.resize();
				if (self.trendsChartInstance) self.trendsChartInstance.resize();
			});

			// History scans filter change handler
			$('#history-filter-scans').off('change').on('change', function() {
				const isDarkMode = $('body').hasClass('udam-dark-mode');
				const gridColor = isDarkMode ? '#1f2937' : '#e2e8f0';
				const textColor = isDarkMode ? '#9ca3af' : '#64748b';
				self.renderHistoryChart(data.runs_history, gridColor, textColor, isDarkMode);
			});
		},

		buildStatusCardHtml: function(scheduler) {
			const statusLabel = scheduler.status === 'active' ? 'Active' : scheduler.status === 'delayed' ? 'Delayed' : 'Disabled';
			const statusClass = scheduler.status === 'active' ? 'success' : scheduler.status === 'delayed' ? 'warning' : 'critical';
			
			return `
				<div class="udam-card" style="margin-bottom: 24px; padding: 20px;">
					<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid var(--udam-border); padding-bottom: 12px; margin-bottom: 16px;">
						<h3 class="udam-card-title" style="display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-admin-settings" style="color: var(--udam-primary);"></span>
							System & Scheduler Status
						</h3>
						<div style="display: flex; gap: 8px; align-items: center;">
							<button id="dashboard-run-cron-btn" class="udam-btn udam-btn-secondary" style="padding: 6px 12px; font-size: 12px; border-radius: 6px;">
								<span class="dashicons dashicons-update" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px; vertical-align: middle;"></span>
								Run Scheduled Audit Now
							</button>
						</div>
					</div>
					
					${scheduler.wp_cron_disabled ? `
					<div style="background: var(--udam-danger-light); color: var(--udam-danger); border: 1px solid var(--udam-danger); border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 13px; font-weight: 500;">
						<span class="dashicons dashicons-warning" style="margin-right: 6px; float: left;"></span>
						<strong>Warning:</strong> DISABLE_WP_CRON is enabled in wp-config.php. Scheduled audits will not execute automatically.
					</div>
					` : ''}

					${scheduler.last_cron_error ? `
					<div style="background: var(--udam-danger-light); color: var(--udam-danger); border: 1px solid var(--udam-danger); border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 13px; font-weight: 500;">
						<span class="dashicons dashicons-warning" style="margin-right: 6px; float: left;"></span>
						<strong>Last Cron Error:</strong> ${scheduler.last_cron_error}
					</div>
					` : ''}

					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px;">
						<div style="display: flex; flex-direction: column; gap: 4px;">
							<span style="font-size: 11px; text-transform: uppercase; color: var(--udam-text-muted); font-weight: 600;">Setup Status</span>
							<span class="badge badge-success" style="width: fit-content; font-size: 11px; padding: 3px 8px;">Completed</span>
						</div>
						<div style="display: flex; flex-direction: column; gap: 4px;">
							<span style="font-size: 11px; text-transform: uppercase; color: var(--udam-text-muted); font-weight: 600;">Scheduler Health</span>
							<span class="badge badge-${statusClass}" style="width: fit-content; font-size: 11px; padding: 3px 8px;">
								${statusLabel}
							</span>
						</div>
						<div style="display: flex; flex-direction: column; gap: 4px;">
							<span style="font-size: 11px; text-transform: uppercase; color: var(--udam-text-muted); font-weight: 600;">Next Scheduled Audit</span>
							<span style="font-size: 13px; font-weight: 600; color: var(--udam-text);">${scheduler.next_run}</span>
						</div>
						<div style="display: flex; flex-direction: column; gap: 4px;">
							<span style="font-size: 11px; text-transform: uppercase; color: var(--udam-text-muted); font-weight: 600;">Last Scheduled Audit</span>
							<span style="font-size: 13px; font-weight: 600; color: var(--udam-text);">${scheduler.last_run}</span>
						</div>
						<div style="display: flex; flex-direction: column; gap: 4px;">
							<span style="font-size: 11px; text-transform: uppercase; color: var(--udam-text-muted); font-weight: 600;">Active Modules</span>
							<span style="font-size: 13px; font-weight: 600; color: var(--udam-text);">${scheduler.active_modules} Active / ${scheduler.disabled_modules} Disabled</span>
						</div>
					</div>
				</div>
			`;
		},

		setCircleOffset: function(selector, score, radius) {
			const circle = $(selector);
			if (circle.length) {
				let parsedScore = parseFloat(score);
				if (isNaN(parsedScore)) {
					parsedScore = 0;
				}
				parsedScore = Math.max(0, Math.min(100, parsedScore));
				
				const r = radius || parseFloat(circle.attr('r')) || 55;
				const c = 2 * Math.PI * r; // Circumference
				const offset = c * (1 - parsedScore / 100);
				
				// Clear any inline styles to avoid conflicts
				circle.css({
					'stroke-dasharray': '',
					'stroke-dashoffset': ''
				});
				
				// Set SVG presentation attributes directly
				circle.attr('stroke-dasharray', c);
				circle.attr('stroke-dashoffset', offset);
				
				// Dynamically color progress ring based on score value
				let strokeColor = 'var(--udam-success)';
				if (parsedScore < 50) {
					strokeColor = 'var(--udam-danger)';
				} else if (parsedScore < 80) {
					strokeColor = 'var(--udam-warning)';
				} else if (parsedScore < 90) {
					strokeColor = 'var(--udam-info)';
				}
				circle.attr('stroke', strokeColor);
			}
		},

		renderCharts: function(data) {
			const self = this;
			const isDarkMode = $('body').hasClass('udam-dark-mode');
			const gridColor = isDarkMode ? '#1f2937' : '#e2e8f0';
			const textColor = isDarkMode ? '#9ca3af' : '#64748b';

			// 1. Score History Chart
			self.renderHistoryChart(data.runs_history, gridColor, textColor, isDarkMode);

			// 2. Severity Breakdown Chart
			self.renderSeverityChart(JSON.parse(data.latest_run.stats) || {}, textColor, isDarkMode);

			// 3. Audit Trends Chart
			self.renderTrendsChart(data.runs_history, gridColor, textColor, isDarkMode);

			// 4. Issue Distribution Chart
			self.renderDistributionChart(data.module_issues || {}, gridColor, textColor, isDarkMode);

			// 5. Module Comparisons Chart
			self.renderComparisonsChart(JSON.parse(data.latest_run.scores_breakdown) || {}, gridColor, textColor, isDarkMode);
		},

		renderHistoryChart: function(history, gridColor, textColor, isDarkMode) {
			const container = $('#udam-history-chart-container');
			if (!container.length || history.length < 1) {
				container.html('<p style="text-align:center; color:var(--udam-text-muted); padding-top:60px;">Scans count insufficient for graphing.</p>');
				return;
			}
			
			container.html('<canvas id="udam-history-chart" style="width:100%; height:220px;"></canvas>');
			const ctx = document.getElementById('udam-history-chart').getContext('2d');
			
			const filterVal = parseInt($('#history-filter-scans').val()) || 10;
			const filteredHistory = history.slice(-filterVal);
			
			const labels = [];
			const scores = [];
			filteredHistory.forEach(run => {
				labels.push(run.completed_at.split(' ')[0]);
				scores.push(run.score);
			});

			const gradient = ctx.createLinearGradient(0, 0, 0, 220);
			gradient.addColorStop(0, 'rgba(99, 102, 241, 0.2)');
			gradient.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

			const self = this;
			if (typeof Chart !== 'undefined') {
				if (self.historyChartInstance) {
					self.historyChartInstance.destroy();
				}

				self.historyChartInstance = new Chart(ctx, {
					type: 'line',
					data: {
						labels: labels,
						datasets: [{
							label: 'Health Score',
							data: scores,
							borderColor: '#6366f1',
							borderWidth: 3,
							backgroundColor: gradient,
							fill: true,
							tension: 0.35,
							pointBackgroundColor: '#6366f1',
							pointBorderColor: isDarkMode ? '#111827' : '#ffffff',
							pointBorderWidth: 2,
							pointRadius: 5,
							pointHoverRadius: 7
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: { display: false },
							tooltip: {
								padding: 10,
								cornerRadius: 8,
								backgroundColor: isDarkMode ? '#1f2937' : '#0f172a',
								titleFont: { family: 'Outfit', size: 12 },
								bodyFont: { family: 'Outfit', size: 12 }
							}
						},
						scales: {
							x: {
								grid: { display: false },
								ticks: { color: textColor, font: { family: 'Outfit', size: 10 } }
							},
							y: {
								min: 0,
								max: 100,
								grid: { color: gridColor },
								ticks: { color: textColor, font: { family: 'Outfit', size: 10 } }
							}
						}
					}
				});
			} else {
				console.error( 'UD Audit Manager: Chart.js failed to load history chart.' );
			}
		},

		renderSeverityChart: function(stats, textColor, isDarkMode) {
			const container = $('#udam-severity-chart-container');
			const legendContainer = $('#udam-severity-legend-container');
			if (!container.length) return;

			container.html('<canvas id="udam-severity-chart" style="width:140px; height:140px;"></canvas>');
			const ctx = document.getElementById('udam-severity-chart').getContext('2d');

			const critical = parseInt(stats.critical || 0);
			const high     = parseInt(stats.high || 0);
			const medium   = parseInt(stats.medium || 0);
			const low      = parseInt(stats.low || 0);
			const info     = parseInt(stats.info || 0);
			const total    = critical + high + medium + low + info;

			if (total === 0) {
				container.parent().html('<p style="text-align:center; color:var(--udam-text-muted); padding-top:60px; width:100%;">No issues found to display.</p>');
				return;
			}

			const self = this;
			if (typeof Chart !== 'undefined') {
				if (self.severityChartInstance) {
					self.severityChartInstance.destroy();
				}

				self.severityChartInstance = new Chart(ctx, {
					type: 'doughnut',
					data: {
						labels: ['Critical', 'High', 'Medium', 'Low', 'Info'],
						datasets: [{
							data: [critical, high, medium, low, info],
							backgroundColor: ['#ef4444', '#f97316', '#f59e0b', '#3b82f6', '#64748b'],
							borderWidth: isDarkMode ? 3 : 2,
							borderColor: isDarkMode ? '#111827' : '#ffffff'
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: { display: false },
							tooltip: {
								padding: 10,
								cornerRadius: 8,
								backgroundColor: isDarkMode ? '#1f2937' : '#0f172a',
								titleFont: { family: 'Outfit', size: 11 },
								bodyFont: { family: 'Outfit', size: 11 }
							},
							centerText: {
								text: total.toString(),
								subtext: 'Total'
							}
						},
						cutout: '72%'
					}
				});
			} else {
				console.error( 'UD Audit Manager: Chart.js failed to load severity chart.' );
			}

			// Render custom legend
			const severities = [
				{ label: 'Critical', val: critical, color: '#ef4444' },
				{ label: 'High', val: high, color: '#f97316' },
				{ label: 'Medium', val: medium, color: '#f59e0b' },
				{ label: 'Low', val: low, color: '#3b82f6' },
				{ label: 'Info', val: info, color: '#64748b' }
			];

			let legendHtml = '';
			severities.forEach(item => {
				const pct = total > 0 ? Math.round((item.val / total) * 100) : 0;
				legendHtml += `
					<div class="udam-severity-legend-item">
						<div class="udam-severity-legend-label">
							<span class="udam-severity-dot" style="background-color: ${item.color};"></span>
							<span>${item.label}</span>
						</div>
						<div class="udam-severity-legend-val">
							${item.val}<span class="udam-severity-legend-pct">(${pct}%)</span>
						</div>
					</div>
				`;
			});
			legendContainer.html(legendHtml);
		},

		renderTrendsChart: function(history, gridColor, textColor, isDarkMode) {
			const container = $('#udam-trends-chart-container');
			if (!container.length || history.length < 1) {
				container.html('<p style="text-align:center; color:var(--udam-text-muted); padding-top:60px;">Scans count insufficient for graphing.</p>');
				return;
			}
			
			container.html('<canvas id="udam-trends-chart" style="width:100%; height:220px;"></canvas>');
			const ctx = document.getElementById('udam-trends-chart').getContext('2d');
			
			const labels = [];
			const criticalData = [];
			const warningData = [];
			const recommendationData = [];

			history.slice(-10).forEach(run => {
				labels.push(run.completed_at.split(' ')[0]);
				const stats = JSON.parse(run.stats) || {};
				criticalData.push(parseInt(stats.critical || 0) + parseInt(stats.high || 0));
				warningData.push(parseInt(stats.medium || 0));
				recommendationData.push(parseInt(stats.low || 0) + parseInt(stats.info || 0));
			});

			const self = this;
			if (typeof Chart !== 'undefined') {
				if (self.trendsChartInstance) {
					self.trendsChartInstance.destroy();
				}

				self.trendsChartInstance = new Chart(ctx, {
					type: 'line',
					data: {
						labels: labels,
						datasets: [
							{
								label: 'Critical',
								data: criticalData,
								borderColor: '#ef4444',
								borderWidth: 2.5,
								fill: false,
								tension: 0.35,
								pointBackgroundColor: '#ef4444',
								pointRadius: 3
							},
							{
								label: 'Warnings',
								data: warningData,
								borderColor: '#f59e0b',
								borderWidth: 2.5,
								fill: false,
								tension: 0.35,
								pointBackgroundColor: '#f59e0b',
								pointRadius: 3
							},
							{
								label: 'Recommendations',
								data: recommendationData,
								borderColor: '#3b82f6',
								borderWidth: 2.5,
								fill: false,
								tension: 0.35,
								pointBackgroundColor: '#3b82f6',
								pointRadius: 3
							}
						]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: {
								display: true,
								position: 'top',
								labels: {
									color: textColor,
									font: { family: 'Outfit', size: 10 }
								}
							},
							tooltip: {
								padding: 10,
								cornerRadius: 8,
								backgroundColor: isDarkMode ? '#1f2937' : '#0f172a',
								titleFont: { family: 'Outfit', size: 11 },
								bodyFont: { family: 'Outfit', size: 11 }
							}
						},
						scales: {
							x: {
								grid: { display: false },
								ticks: { color: textColor, font: { family: 'Outfit', size: 9 } }
							},
							y: {
								grid: { color: gridColor },
								ticks: { color: textColor, font: { family: 'Outfit', size: 9 } }
							}
						}
					}
				});
			} else {
				console.error( 'UD Audit Manager: Chart.js failed to load trends chart.' );
			}
		},

		renderDistributionChart: function(moduleIssues, gridColor, textColor, isDarkMode) {
			const container = $('#udam-distribution-chart-container');
			if (!container.length) return;

			container.html('<canvas id="udam-distribution-chart" style="width:100%; height:220px;"></canvas>');
			const ctx = document.getElementById('udam-distribution-chart').getContext('2d');

			const labels = [];
			const counts = [];

			Object.entries(moduleIssues).forEach(([mod, count]) => {
				labels.push(mod.toUpperCase());
				counts.push(count);
			});

			const self = this;
			if (typeof Chart !== 'undefined') {
				if (self.distributionChartInstance) {
					self.distributionChartInstance.destroy();
				}

				self.distributionChartInstance = new Chart(ctx, {
					type: 'bar',
					data: {
						labels: labels,
						datasets: [{
							data: counts,
							backgroundColor: 'rgba(99, 102, 241, 0.75)',
							borderColor: '#6366f1',
							borderWidth: 1,
							borderRadius: 4
						}]
					},
					options: {
						indexAxis: 'y',
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: { display: false },
							tooltip: {
								padding: 10,
								cornerRadius: 8,
								backgroundColor: isDarkMode ? '#1f2937' : '#0f172a',
								titleFont: { family: 'Outfit', size: 11 },
								bodyFont: { family: 'Outfit', size: 11 }
							}
						},
						scales: {
							x: {
								grid: { color: gridColor },
								ticks: { color: textColor, font: { family: 'Outfit', size: 9 } }
							},
							y: {
								grid: { display: false },
								ticks: { color: textColor, font: { family: 'Outfit', size: 9 } }
							}
						}
					}
				});
			} else {
				console.error( 'UD Audit Manager: Chart.js failed to load distribution chart.' );
			}
		},

		renderComparisonsChart: function(scores, gridColor, textColor, isDarkMode) {
			const container = $('#udam-comparisons-chart-container');
			if (!container.length) return;

			container.html('<canvas id="udam-comparisons-chart" style="width:100%; height:220px;"></canvas>');
			const ctx = document.getElementById('udam-comparisons-chart').getContext('2d');

			const labels = [];
			const scoreValues = [];
			const backgroundColors = [];
			const borderColors = [];

			Object.entries(scores).forEach(([mod, score]) => {
				labels.push(mod.toUpperCase());
				scoreValues.push(score);

				// Set colors based on status
				if (score < 50) {
					backgroundColors.push('rgba(239, 68, 68, 0.7)');
					borderColors.push('#ef4444');
				} else if (score < 80) {
					backgroundColors.push('rgba(245, 158, 11, 0.7)');
					borderColors.push('#f59e0b');
				} else if (score < 90) {
					backgroundColors.push('rgba(59, 130, 246, 0.7)');
					borderColors.push('#3b82f6');
				} else {
					backgroundColors.push('rgba(16, 185, 129, 0.7)');
					borderColors.push('#10b981');
				}
			});

			const self = this;
			if (typeof Chart !== 'undefined') {
				if (self.comparisonsChartInstance) {
					self.comparisonsChartInstance.destroy();
				}

				self.comparisonsChartInstance = new Chart(ctx, {
					type: 'bar',
					data: {
						labels: labels,
						datasets: [{
							data: scoreValues,
							backgroundColor: backgroundColors,
							borderColor: borderColors,
							borderWidth: 1.5,
							borderRadius: 4
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: { display: false },
							tooltip: {
								padding: 10,
								cornerRadius: 8,
								backgroundColor: isDarkMode ? '#1f2937' : '#0f172a',
								titleFont: { family: 'Outfit', size: 11 },
								bodyFont: { family: 'Outfit', size: 11 }
							}
						},
						scales: {
							x: {
								grid: { display: false },
								ticks: { color: textColor, font: { family: 'Outfit', size: 9 } }
							},
							y: {
								min: 0,
								max: 100,
								grid: { color: gridColor },
								ticks: { color: textColor, font: { family: 'Outfit', size: 9 } }
							}
						}
					}
				});
			} else {
				console.error( 'UD Audit Manager: Chart.js failed to load comparisons chart.' );
			}
		},

		renderPriorityFixes: function(fixes) {
			const container = $('#priority-fixes-list');
			if (!fixes || fixes.length === 0) {
				container.html('<p style="padding: 30px; text-align:center; color:var(--udam-text-muted);">No open issues. Site is completely healthy!</p>');
				return;
			}

			let html = '';
			fixes.forEach(fix => {
				let badgeClass = 'badge-info';
				if (fix.severity === 'critical' || fix.severity === 'high') {
					badgeClass = 'badge-critical';
				} else if (fix.severity === 'medium') {
					badgeClass = 'badge-warning';
				} else if (fix.severity === 'low') {
					badgeClass = 'badge-low';
				}

				const severityLabel = fix.severity === 'critical' ? 'Critical' : fix.severity === 'high' ? 'High' : fix.severity === 'medium' ? 'Medium' : fix.severity === 'low' ? 'Low' : fix.severity === 'info' ? 'Info' : 'High';

				html += `
					<a href="admin.php?page=ud-audit-manager-${fix.module}" class="udam-fix-item">
						<div class="udam-fix-details">
							<span class="badge ${badgeClass}" style="font-size:10px; width: 50px; text-align:center; justify-content:center; flex-shrink: 0;">${severityLabel}</span>
							<div class="udam-fix-meta">
								<strong class="udam-fix-title">${fix.title}</strong>
								<span class="udam-fix-sub">${fix.module.toUpperCase()} · ${fix.count} Occurrences</span>
							</div>
						</div>
						<div class="udam-fix-impact">
							<span class="badge badge-success" style="font-size:11px; font-weight:700;">+${fix.overall_impact} Pts</span>
						</div>
					</a>
				`;
			});

			container.html(html);
		},

		/**
		 * Module Specific Audit details loading.
		 */
		loadModuleData: function(moduleSlug) {
			const self = this;
			const scoreFill = $('#udam-module-score-fill');
			const scoreText = $('#udam-module-score-val');
			
			// Set correct module title/description based on slug
			const moduleTitles = {
				'audit': ['Full Site Audit Results', 'Comprehensive list of all optimization opportunities and issues found across your website.', 'Run Full Audit'],
				'seo': ['SEO Audit Module', 'Detailed crawl data for titles, tags, content hierarchies, sitemaps, and indexing properties.', 'Rescan SEO'],
				'performance': ['Performance Audit Module', 'Asset loading footprint assessments, image tags dimensions, lazy loadings, and caches.', 'Rescan PERFORMANCE'],
				'accessibility': ['Accessibility Audit Module', 'Checks for WCAG structure compliance, alternate names, form label controls, and ARIA.', 'Rescan ACCESSIBILITY'],
				'security': ['Security Audit Module', 'Audit user permissions, core system debugging switches, file editors, and plugin security updates.', 'Rescan SECURITY'],
				'database': ['Database Audit Module', 'Reviews table bloats, post revisions counts, comment trashes, and autoload metadata sizes.', 'Rescan DATABASE'],
				'content': ['Content Quality Module', 'Monitors taxonomies categorization issues, article word sizes, and draft indices clutter.', 'Rescan CONTENT'],
				'plugin': ['Plugins Health Module', 'Inspect active vs inactive plugins, update vectors, and heavy enqueued style footprints.', 'Rescan PLUGIN'],
				'theme': ['Themes Quality Module', 'Validate favicon status, logo customizers, active themes, and parent/child setups.', 'Rescan THEME']
			};

			if (moduleTitles[moduleSlug]) {
				$('#udam-module-title').text(moduleTitles[moduleSlug][0]);
				$('#udam-module-desc').text(moduleTitles[moduleSlug][1]);
				$('#udam-scan-btn-text').text(moduleTitles[moduleSlug][2]);
			}

			let apiModule = moduleSlug;
			if (moduleSlug === 'audit') {
				apiModule = '';
			}

			// Hook filter values for request
			const filters = {
				module: apiModule,
				severity: $('#udam-filter-severity').val(),
				status: $('#udam-filter-status').val(),
				search: $('#udam-filter-search').val()
			};

			// Reset header stats and score elements while loading to show fresh loading states
			scoreText.text('-');
			self.setCircleOffset(scoreFill, 0);
			$('#module-metric-critical').text('-');
			$('#module-metric-warnings').text('-');
			$('#module-metric-recommendations').text('-');

			// Show loading skeletons inside the findings table body and fade the table slightly for visual feedback
			const colspanVal = moduleSlug === 'audit' ? 5 : 4;
			$('#udam-findings-empty').hide();
			$('#udam-findings-table').show().css('opacity', '0.5');
			$('#udam-findings-body').html(`
				<tr class="skeleton-row"><td colspan="${colspanVal}"><div class="skeleton skeleton-text" style="height: 20px;"></div></td></tr>
				<tr class="skeleton-row"><td colspan="${colspanVal}"><div class="skeleton skeleton-text" style="height: 20px;"></div></td></tr>
				<tr class="skeleton-row"><td colspan="${colspanVal}"><div class="skeleton skeleton-text" style="height: 20px;"></div></td></tr>
			`);

			// Bind filters triggers
			$('#udam-filter-severity, #udam-filter-status').off('change').on('change', function() {
				self.loadModuleData(moduleSlug);
			});
			$('#udam-filter-search').off('keyup').on('keyup', function() {
				// Simple debounce for keystrokes
				clearTimeout(window.udamAdminSearchTimer);
				window.udamAdminSearchTimer = setTimeout(() => {
					self.loadModuleData(moduleSlug);
				}, 400);
			});

			const paramRunId = this.getUrlParam('run_id');
			if (paramRunId) {
				return self.request(`/runs/${paramRunId}`, 'GET', filters)
					.done(function(data) {
						const run = data.run;
						const breakdown = JSON.parse(run.scores_breakdown) || {};
						const score = moduleSlug === 'audit' ? run.score : (breakdown[moduleSlug] !== undefined ? breakdown[moduleSlug] : 100);
						
						scoreText.text(score);
						self.setCircleOffset(scoreFill, score);

						const findings = data.findings || [];
						let criticalCount = 0;
						let warningCount = 0;
						let infoCount = 0;

						findings.forEach(f => {
							if (f.severity === 'critical' || f.severity === 'high') criticalCount++;
							else if (f.severity === 'medium') warningCount++;
							else infoCount++;
						});

						$('#module-metric-critical').text(criticalCount);
						$('#module-metric-warnings').text(warningCount);
						$('#module-metric-recommendations').text(infoCount);

						self.renderFindingsTable(findings);
					});
			}

			// Fetch database entries
			const deferred = $.Deferred();
			this.request('/dashboard/stats', 'GET')
				.done(function(statsData) {
					if (statsData.running_scan) {
						self.runScan(statsData.running_scan.type, statsData.running_scan.source);
						deferred.resolve();
						return;
					}
					if (!statsData.latest_run) {
						// Display empty state
						scoreText.text('-');
						self.setCircleOffset(scoreFill, 0);
						const colspanVal = moduleSlug === 'audit' ? 5 : 4;
						$('#udam-findings-body').html(`<tr><td colspan="${colspanVal}" style="text-align:center;">Run an audit scan to get started.</td></tr>`);
						deferred.resolve();
						return;
					}

					const runId = statsData.latest_run.id;
					
					self.request(`/runs/${runId}`, 'GET', filters)
						.done(function(data) {
							const run = data.run;
							const breakdown = JSON.parse(run.scores_breakdown) || {};
							const score = moduleSlug === 'audit' ? run.score : (breakdown[moduleSlug] !== undefined ? breakdown[moduleSlug] : 100);
							
							// Animate score ring
							scoreText.text(score);
							self.setCircleOffset(scoreFill, score);

							// Set overview statistics
							const findings = data.findings || [];
							let criticalCount = 0;
							let warningCount = 0;
							let infoCount = 0;

							findings.forEach(f => {
								if (f.severity === 'critical' || f.severity === 'high') criticalCount++;
								else if (f.severity === 'medium') warningCount++;
								else infoCount++;
							});

							$('#module-metric-critical').text(criticalCount);
							$('#module-metric-warnings').text(warningCount);
							$('#module-metric-recommendations').text(infoCount);

							// Render table rows
							self.renderFindingsTable(findings);
							deferred.resolve();
						})
						.fail(function() {
							deferred.reject();
						});
				})
				.fail(function() {
					deferred.reject();
				});
			return deferred.promise();
		},

		renderFindingsTable: function(findings) {
			const body = $('#udam-findings-body');
			const emptyState = $('#udam-findings-empty');
			const table = $('#udam-findings-table');
			const isFullAudit = this.api.page === 'audit';

			// Restore table opacity
			table.css('opacity', '1.0');

			if (findings.length === 0) {
				body.empty();
				table.hide();
				emptyState.show();
				return;
			}

			emptyState.hide();
			table.show();

			let html = '';
			findings.forEach(finding => {
				const fixBtn = finding.is_fixable == 1 
					? `<div class="udam-fix-action">
							<button class="udam-btn udam-btn-primary apply-auto-fix-btn" data-id="${finding.id}" style="padding:6px 12px; font-size:12px;">
								<span class="dashicons dashicons-admin-tools"></span> Run Auto-Fix
							</button>
					   </div>` 
					: '';

				const moduleBadge = isFullAudit 
					? `<td><span class="badge badge-info" style="font-size:10px; text-transform:uppercase;">${finding.module}</span></td>`
					: '';

				html += `
					<tr class="udam-row-header">
						${moduleBadge}
						<td>
							<strong>${finding.title}</strong>
							<span style="display:block; font-size:11px; color:var(--udam-text-muted);">${finding.issue_key}</span>
						</td>
						<td>
							<span class="badge badge-${finding.severity}">${finding.severity === 'critical' ? 'Critical' : finding.severity === 'high' ? 'High' : finding.severity === 'medium' ? 'Medium' : finding.severity === 'low' ? 'Low' : finding.severity === 'info' ? 'Info' : finding.severity}</span>
						</td>
						<td style="font-family: monospace; font-size: 11px;">
							${finding.location}
						</td>
						<td>
							<span class="badge badge-${finding.status === 'open' ? 'warning' : 'success'}">${finding.status}</span>
						</td>
					</tr>
					<tr class="udam-row-details">
						<td colspan="${isFullAudit ? 5 : 4}">
							<div class="udam-row-details-content">
								<div class="udam-detail-block">
									<h4>Description & Context</h4>
									<p>${finding.description}</p>
									<h4 style="margin-top:14px;">Why It Matters</h4>
									<p>${finding.why_it_matters}</p>
								</div>
								<div class="udam-detail-block">
									<h4>How to Resolve</h4>
									<p>${finding.how_to_fix}</p>
									<h4 style="margin-top:14px;">Suggested Action</h4>
									<p style="font-weight: 500; color:var(--udam-text);">${finding.suggested_action}</p>
									${fixBtn}
								</div>
							</div>
						</td>
					</tr>
				`;
			});

			body.html(html);
		},

		/**
		 * Onboarding Setup Wizard actions.
		 */
		initSetupWizard: function() {
			const self = this;
			let currentStep = 1;

			$(document).on('click', '#wizard-next', function(e) {
				e.preventDefault();
				if (currentStep < 3) {
					$(`.udam-wizard-content[data-step="${currentStep}"]`).hide();
					$(`.udam-wizard-step[data-step="${currentStep}"]`).removeClass('active').addClass('completed');
					
					currentStep++;
					
					$(`.udam-wizard-content[data-step="${currentStep}"]`).show();
					$(`.udam-wizard-step[data-step="${currentStep}"]`).addClass('active');

					$('#wizard-prev').show();
					if (currentStep === 3) {
						$('#wizard-next').hide();
						$('#wizard-finish').show();
					}
				}
			});

			$(document).on('click', '#wizard-prev', function(e) {
				e.preventDefault();
				if (currentStep > 1) {
					$(`.udam-wizard-content[data-step="${currentStep}"]`).hide();
					$(`.udam-wizard-step[data-step="${currentStep}"]`).removeClass('active');
					
					currentStep--;
					
					$(`.udam-wizard-content[data-step="${currentStep}"]`).show();
					$(`.udam-wizard-step[data-step="${currentStep}"]`).addClass('active').removeClass('completed');

					if (currentStep === 1) {
						$('#wizard-prev').hide();
					}
					$('#wizard-next').show();
					$('#wizard-finish').hide();
				}
			});

			$(document).on('click', '#wizard-finish', function(e) {
				e.preventDefault();
				
				// 1. Serialize parameters
				const formData = $('#udam-setup-form').serializeArray();
				const payload = {
					modules: {},
					cron_frequency: 'disabled'
				};

				formData.forEach(item => {
					if (item.name.startsWith('modules[')) {
						const key = item.name.substring(8, item.name.length - 1);
						payload.modules[key] = true;
					} else if (item.name === 'cron_frequency') {
						payload.cron_frequency = item.value;
					}
				});

				// Set default missing modules checkmarks as false
				const list = ['seo', 'performance', 'accessibility', 'security', 'database', 'content', 'plugin', 'theme'];
				list.forEach(item => {
					if (!payload.modules[item]) {
						payload.modules[item] = false;
					}
				});

				// 2. Save settings
				self.request('/settings', 'POST', payload)
					.done(function() {
						// Redirect back to dashboard page and auto-trigger scan
						window.location.href = 'admin.php?page=ud-audit-manager&auto_scan=true';
					});
			});

		},

		/**
		 * Settings management and logs view.
		 */
		loadSettings: function() {
			const self = this;
			
			this.request('/settings', 'GET')
				.done(function(data) {
					// Toggle checkboxes for modules
					if (data.modules) {
						Object.entries(data.modules).forEach(([key, val]) => {
							$(`input[data-group="modules"][data-key="${key}"]`).prop('checked', val);
						});
					}

					// Penalty Weights
					if (data.severity_weights) {
						Object.entries(data.severity_weights).forEach(([key, val]) => {
							$(`input[data-group="severity_weights"][data-key="${key}"]`).val(val);
						});
					}

					// Cron frequency dropdown
					$('#setting-cron-frequency').val(data.cron_frequency || 'disabled');
					
					// Dark mode toggle
					$('#setting-dark-mode').prop('checked', data.dark_mode || false);
					
					// Retention numbers
					$('#setting-report-retention').val(data.report_retention || 25);

					// Performance limits posts
					$('#setting-perf-limits-posts').val(data.perf_limits_posts || 50);
				});

			// Lazy load logs view
			this.loadLogs();
		},

		saveSettings: function() {
			const self = this;
			const payload = {
				modules: {},
				severity_weights: {},
				cron_frequency: $('#setting-cron-frequency').val(),
				report_retention: parseInt($('#setting-report-retention').val()),
				perf_limits_posts: parseInt($('#setting-perf-limits-posts').val() || 50),
				dark_mode: $('#setting-dark-mode').is(':checked')
			};

			$('input[data-group="modules"]').each(function() {
				const key = $(this).data('key');
				payload.modules[key] = $(this).is(':checked');
			});

			$('input[data-group="severity_weights"]').each(function() {
				const key = $(this).data('key');
				payload.severity_weights[key] = parseInt($(this).val());
			});

			this.request('/settings', 'POST', payload)
				.done(function() {
					if (self && typeof self.toast === 'function') {
						self.toast('Settings saved successfully!', 'success', 'Settings Saved');
					}
					// Update theme body class based on the saved checkbox state
					if (payload.dark_mode) {
						$('body').addClass('udam-dark-mode');
						localStorage.setItem('udam_dark_mode', 'true');
					} else {
						$('body').removeClass('udam-dark-mode');
						localStorage.setItem('udam_dark_mode', 'false');
					}
				});
		},

		cleanupData: function(type) {
			if (!confirm(this.api.l10n.confirm_cleanup)) return;

			const self = this;
			this.request('/settings/cleanup', 'POST', { type: type })
				.done(function() {
					if (self && typeof self.toast === 'function') {
						self.toast('Data cleanup completed successfully!', 'success', 'Cleanup Successful');
					}
					setTimeout(() => {
						window.location.reload();
					}, 1500);
				});
		},

		loadLogs: function() {
			const term = $('#udam-log-terminal');
			if (!term.length) return;

			term.val('Reading log file contents...');

			this.request('/logs', 'GET')
				.done(function(data) {
					term.val(data.logs || '--- Log file empty ---');
					// Auto scroll to bottom
					term.scrollTop(term[0].scrollHeight);
				});
		},

		clearLogs: function() {
			if (!confirm(this.api.l10n.confirm_cleanup)) return;

			const self = this;
			this.request('/logs/clear', 'POST')
				.done(function() {
					self.loadLogs();
				});
		},

		getUrlParam: function(name) {
			const results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
			return results ? results[1] : null;
		},

		toast: function(message, type = 'success', title = '', action = null) {
			let container = $('#udam-toast-container');
			if (!container.length) {
				container = $('<div id="udam-toast-container"></div>');
				$('body').append(container);
			}

			const iconMap = {
				success: 'dashicons-yes-alt',
				error: 'dashicons-warning',
				warning: 'dashicons-warning',
				info: 'dashicons-info'
			};
			const iconClass = iconMap[type] || 'dashicons-info';
			
			const toastId = 'toast-' + Math.floor(Math.random() * 1000000);
			let toastHtml = `
				<div id="${toastId}" class="udam-toast udam-toast-${type}">
					<div class="udam-toast-icon">
						<span class="dashicons ${iconClass}"></span>
					</div>
					<div class="udam-toast-body">
			`;

			if (title) {
				toastHtml += `<div class="udam-toast-title">${title}</div>`;
			}

			toastHtml += `<div class="udam-toast-message">${message}</div>`;

			if (action && action.text && action.url) {
				toastHtml += `
					<div class="udam-toast-action">
						<a href="${action.url}" class="udam-toast-action-btn">${action.text}</a>
					</div>
				`;
			}

			toastHtml += `
					</div>
					<button class="udam-toast-close" data-dismiss="${toastId}">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			`;

			const toastElement = $(toastHtml);
			container.append(toastElement);

			toastElement.find('.udam-toast-close').on('click', function() {
				const id = $(this).data('dismiss');
				App.dismissToast(id);
			});

			setTimeout(() => {
				App.dismissToast(toastId);
			}, 6000);
		},

		dismissToast: function(id) {
			const toast = $('#' + id);
			if (toast.length && !toast.hasClass('dismissing')) {
				toast.addClass('dismissing');
				setTimeout(() => {
					toast.remove();
				}, 300);
			}
		},

		formatDate: function(dateStr) {
			if (!dateStr) return '-';
			const date = new Date(dateStr.replace(/-/g, "/"));
			if (isNaN(date.getTime())) return dateStr;
			
			const y = date.getFullYear();
			const m = String(date.getMonth() + 1).padStart(2, '0');
			const d = String(date.getDate()).padStart(2, '0');
			const h = String(date.getHours()).padStart(2, '0');
			const min = String(date.getMinutes()).padStart(2, '0');
			
			return `${y}-${m}-${d} ${h}:${min}`;
		},

		initScheduledPolling: function() {
			if (this.api.page === 'setup') return;

			const self = this;
			let lastScheduledRunId = localStorage.getItem('udam_last_scheduled_run_id');

			if (!lastScheduledRunId) {
				self.request('/runs', 'GET', { source: 'scheduled', status: 'completed' })
					.done(function(runs) {
						if (runs && runs.length > 0) {
							lastScheduledRunId = runs[0].id;
							localStorage.setItem('udam_last_scheduled_run_id', lastScheduledRunId);
						}
					});
			}

			setInterval(() => {
				self.request('/runs', 'GET', { source: 'scheduled', status: 'completed' })
					.done(function(runs) {
						if (runs && runs.length > 0) {
							const latestRun = runs[0];
							const latestRunId = latestRun.id;
							
							if (lastScheduledRunId && parseInt(latestRunId) > parseInt(lastScheduledRunId)) {
								lastScheduledRunId = latestRunId;
								localStorage.setItem('udam_last_scheduled_run_id', lastScheduledRunId);
								
								const scoreVal = latestRun.score || 0;
								const message = `Scheduled scan completed with score of ${scoreVal}/100.`;
								const viewUrl = `admin.php?page=ud-audit-manager-audit&run_id=${latestRunId}`;
								if (self && typeof self.toast === 'function') {
									self.toast(message, 'success', 'Scheduled Audit Complete', {
										text: 'View Report',
										url: viewUrl
									});
								}
							} else if (!lastScheduledRunId) {
								lastScheduledRunId = latestRunId;
								localStorage.setItem('udam_last_scheduled_run_id', lastScheduledRunId);
							}
						}
					});
			}, 30000);
		},

		loadReportsList: function() {
			const self = this;
			const tbody = $('#udam-reports-history-tbody');
			if (!tbody.length) return;

			const sourceFilter = $('#udam-report-filter-source').val();

			tbody.html(`
				<tr>
					<td colspan="8" style="text-align: center; color: var(--udam-text-muted); padding: 24px;">
						Loading audit runs...
					</td>
				</tr>
			`);

			if (!window.udamAdminReportsBound) {
				window.udamAdminReportsBound = true;
				$('#udam-report-filter-source').off('change').on('change', function() {
					self.loadReportsList();
				});
			}

			this.request('/runs', 'GET', { source: sourceFilter })
				.done(function(runs) {
					if (!runs || runs.length === 0) {
						tbody.html(`
							<tr>
								<td colspan="8" style="text-align: center; color: var(--udam-text-muted); padding: 24px;">
									No completed runs found.
								</td>
							</tr>
						`);
						return;
					}

					let html = '';
					runs.forEach(run => {
						const startedDate = run.started_at ? self.formatDate(run.started_at) : '-';
						const completedDate = run.completed_at ? self.formatDate(run.completed_at) : '-';
						const scores_breakdown = JSON.parse(run.scores_breakdown);
						const score = run.type == 'full' ? run.score : scores_breakdown[run.type];
						
						const sourceLabels = {
							manual: '🖱 Manual',
							scheduled: '⏰ Scheduled',
							setup: '🚀 Setup Wizard',
							system: '⚙ System',
							api: '🔌 API',
							autofix: '🔧 Autofix'
						};
						const sourceLabel = sourceLabels[run.source] || run.source;

						let statusBadge = '';
						if (run.status === 'completed') {
							statusBadge = `<span class="badge badge-success" style="font-size: 11px;">Completed</span>`;
						} else if (run.status === 'running') {
							statusBadge = `<span class="badge badge-warning" style="font-size: 11px;">Running</span>`;
						} else {
							statusBadge = `<span class="badge badge-critical" style="font-size: 11px;">Failed</span>`;
						}

						let durationStr = '-';
						if (run.duration) {
							if (run.duration < 60) {
								durationStr = `${run.duration}s`;
							} else {
								const minutes = Math.floor(run.duration / 60);
								const seconds = run.duration % 60;
								durationStr = `${minutes}m ${seconds}s`;
							}
						}

						const csv_url   = `admin.php?page=ud-audit-manager-reports&udam_export=csv&run_id=${run.id}&_wpnonce=${run.export_nonce}`;
						const json_url  = `admin.php?page=ud-audit-manager-reports&udam_export=json&run_id=${run.id}&_wpnonce=${run.export_nonce}`;
						const print_url = `admin.php?page=ud-audit-manager-reports&udam_export=print&run_id=${run.id}&_wpnonce=${run.export_nonce}`;

						let actionsHtml = '';
						if (run.status === 'completed') {
							actionsHtml = `
								<a href="${print_url}" target="_blank" class="udam-btn udam-btn-secondary" style="padding: 4px 8px; font-size: 11px;">
									<span class="dashicons dashicons-media-text" style="font-size: 14px; width: 14px; height: 14px;"></span> Print / PDF
								</a>
								<a href="${csv_url}" class="udam-btn udam-btn-secondary" style="padding: 4px 8px; font-size: 11px;">CSV</a>
								<a href="${json_url}" class="udam-btn udam-btn-secondary" style="padding: 4px 8px; font-size: 11px;">JSON</a>
							`;
						}

						const scoreBadge = run.status === 'completed' 
							? `<span class="badge badge-info" style="font-size: 13px;">${parseInt(score)}/100</span>`
							: `<span class="badge badge-critical" style="font-size: 13px; background-color: var(--udam-danger-light); color: var(--udam-danger);">-</span>`;

						html += `
							<tr>
								<td><strong>${startedDate}</strong></td>
								<td><strong>${completedDate}</strong></td>
								<td style="text-transform: capitalize;">${run.type}</td>
								<td>${statusBadge}</td>
								<td>${durationStr}</td>
								<td>${scoreBadge}</td>
								<td style="text-align: right; display: flex; gap: 6px; justify-content: flex-end; padding: 13px;">${actionsHtml}</td>
							</tr>
						`;
					});

					tbody.html(html);
				});
		}
	};

	// DOM ready initialization
	$(function() {
		App.init();

		// Auto trigger check if URL contains auto_scan=true
		if (window.location.search.indexOf('auto_scan=true') > -1) {
			setTimeout(() => {
				App.runScan('full', 'setup');
			}, 600);
		}
	});

})(jQuery);
