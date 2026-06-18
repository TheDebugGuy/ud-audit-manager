=== UD Audit Manager ===
Contributors: undefineddeveloper
Tags: audit, seo, performance, security, database
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, modular website auditing plugin for WordPress that scans and optimizes SEO, performance, security, and database health.

== Description ==

UD Audit Manager is a comprehensive site auditing and health dashboard for WordPress. Designed for site owners, developers, and agency builders, it performs diagnostic scans on your website to identify critical errors, warnings, and optimization opportunities.

Unlike external site audit tools that demand expensive subscriptions, UD Audit Manager runs directly on your server, compiling in-depth analytics securely and privately. The plugin features a centralized scoring engine, modular audit runners, and a Priority Fix Center that helps you optimize your site's health index step by step.

For full documentation, setup wizard guides, and a complete reference of all 52 audit checks, visit the official [UD Audit Manager Documentation Portal](https://retro.great-site.net/docs/ud-audit-manager/).

### Audit Modules Included:
*   **SEO Auditing**: Scans meta title tags, meta descriptions, heading structures, thin content, image alt attributes, XML sitemaps, robots.txt accessibility, and search visibility configs.
*   **Performance Auditing**: Audits next-gen image conversions (WebP/AVIF formats), lazy-load properties, image dimensions, browser compression (Gzip/Brotli), and active caching layers.
*   **Accessibility Auditing**: Verifies WCAG compliance items, checking for empty hyperlinks, unlabeled interactive buttons, orphaned form field labels, and homepage skip-navigation elements.
*   **Security Auditing**: Scans core system versions, active debugging states (WP_DEBUG), admin username exposures, theme/plugin editor configurations, active administrator counts, and update statuses.
*   **Database Auditing**: Scans tables for revisions bloat, spam comment pileups, trash bin sizes, orphaned metadata items, expired transients, autoloaded settings size, and table index overhead fragmentation.
*   **Content Quality Auditing**: Tracks word count metrics, default categorization warnings, tag distributions, content modifications, and post excerpt statuses.
*   **Plugin & Theme Auditing**: Monitors active vs inactive plugin count, duplicate SEO and caching extensions, and missing assets (favicons, custom logos).

### Advanced Diagnostic Features:
*   **Centralized Scoring Engine**: Calculates overall and category-level health scores using a logarithmic penalty scale for repeating issues to prevent double deductions.
*   **Priority Fix Center**: Ranks discovered issues by overall score impact, helping you address high-priority tasks first, with support for automatic resolving of common database bloat.
*   **PDF/CSV/JSON Exports**: Export reports or download raw audits for agency presentations.

== Features ==

*   **Modular Architecture**: Toggle individual scanning modules off or on based on your site needs.
*   **SaaS-Quality Dashboard**: Beautiful UI featuring circular SVGs, live diagnostic console logs, and 5 interactive Chart.js charts.
*   **Priority Fix Center**: Calculates potential score recoveries, prioritizing high-impact optimizations and providing auto-fix capability.
*   **Automated Scans**: Integrates with WP-Cron for automatic auditing.
*   **Secure & Developer-Friendly**: Fully sanitizes inputs, escapes output streams, uses nonce checks, and includes extensible developer hooks.

== Installation ==

1. Upload the `ud-audit-manager` folder to your `/wp-content/plugins/` directory, or search for "UD Audit Manager" in Plugins > Add New and install it.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. You will be redirected to the Setup Wizard to check environment capabilities and configure default modules.
4. Go to UD Audit Manager in your admin sidebar to run your first site audit!

Detailed installation guidelines, system requirement specs, and onboarding steps are available in the [Installation & Activation Section](https://retro.great-site.net/docs/ud-audit-manager/#installation).

== Frequently Asked Questions ==

= What does UD Audit Manager do? =
UD Audit Manager checks your WordPress site for performance, SEO, accessibility, security, database health, content, theme, and plugin issues, giving you an overall health score and actionable ways to fix problems.

= Does it affect site performance? =
No. Audits run asynchronously in small batches to prevent server resource spikes.

= Can I export reports? =
Absolutely. UD Audit Manager supports CSV, JSON, and print-optimized PDF outputs.

= Does it modify my website? =
No. The scan is completely read-only. Optional automatic database cleanup or fix parameters are only triggered when you click "Fix" in the admin dashboard.

= Is it beginner friendly? =
Yes! The Priority Fix Center ranks issues by importance and details "Why It Matters" and "How to Fix" for every single check.

= Where can I find the full documentation? =
The complete documentation, including details for all 52 diagnostic checks, custom scoring weights, and developer reference hooks, is available at the [UD Audit Manager Portal](https://retro.great-site.net/docs/ud-audit-manager/).

== Documentation ==

Official documentation, architecture details, and developer reference hooks are hosted at [https://retro.great-site.net/docs/ud-audit-manager/](https://retro.great-site.net/docs/ud-audit-manager/).

== Support ==

If you encounter issues, require assistance, or want to troubleshoot configuration errors, please consult our [Troubleshooting & FAQ Guide](https://retro.great-site.net/docs/ud-audit-manager/#troubleshooting) or open a ticket on the WordPress.org support forum.

== Screenshots ==

1. **Dashboard Overview**: The central hub displaying overall health score, modular scores, historical trends, and priority fixes.
2. **Full Site Audit**: Step-by-step scanner view showing progress and live diagnostics.
3. **SEO Audit Module**: Discovered metadata, heading hierarchy, and sitemap issues.
4. **Performance Audit Module**: Detailed logs for media formats, caching layers, and asset counts.
5. **Security Audit Module**: Security audit screen highlighting WP_DEBUG status and user accounts.
6. **Database Audit Module**: Transients, revisions bloat, and table optimizations list.
7. **Reports History**: Historic scan runs list and exports download panel.
8. **Settings**: Configurable severity weights, active modules, and automated cron timings.

== Changelog ==

= 1.0.0 =
* Initial release of UD Audit Manager.
* Features 8 core auditing modules including SEO, Performance, Accessibility, Security, Database, Content, Plugins, and Themes.
* SaaS-quality admin dashboard with circular SVG progress rings and 5 interactive Chart.js visualizations.
* Onboarding setup wizard with server requirements check, module activation, and scheduling setup.\
* Reports history center with CSV, JSON, and print-optimized PDF exports.
* Priority Fix Center with score recovery calculations and automatic resolving of common database issues.
* Text-based Dark Mode theme toggle button synchronized with database preferences.
* Real-time toast notifications for asynchronous scanner and fix completions.
* Full WordPress.org compliance: i18n localization, data sanitization/escaping, capability checks, and nonce validation.

== Upgrade Notice ==

= 1.0.0 =
Initial release of UD Audit Manager. Active developers can hook custom audits via registry filters.
