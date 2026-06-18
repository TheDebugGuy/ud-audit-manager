# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2026-06-08

### Added
- **Core Scan Engine**: Introduced modular diagnostic runner with step-by-step progress tracking for full or module-specific audit runs.
- **Priority Fix Center**: Added an automated dashboard sorting and ranking findings by potential score recovery to help focus on highest-impact issues.
- **Audit Modules**:
  - **SEO Audit Module**: Evaluates meta titles, descriptions, heading structures, image alt attributes, sitemaps, and indexing configurations.
  - **Security Audit Module**: Analyzes debug settings, administrative roles, file editor accessibility, and core/plugin/theme version update vectors.
  - **Performance Audit Module**: Inspects asset loading footprint, image dimensions, browser caching, Gzip compression, and lazy loading configurations.
  - **Accessibility Audit Module**: Checks contrast ratios, link titles, form label elements, image alt tags, and ARIA semantic markup compliance.
  - **Database Audit Module**: Scans tables for overhead bloat, identifies orphaned options, and counts revisions, spam comments, and transients.
  - **Content Quality Module**: Monitors article word sizes, blank categorizations, sitemaps, and tag distributions.
  - **Plugins Health Module**: Audits active plugins footprint, identifies inactive scripts, and lists enqueued size weight.
  - **Themes Quality Module**: Validates logo customs, child-theme pairings, and active theme configurations.
- **Report & Data Export Management**: Added support for exporting completed scan results to print-ready PDF, CSV, and developer-friendly raw JSON formats.
- **Persistent Settings Dashboard**: Configurable settings for toggling audit modules, adjusting severity weights, setting report retention limits, and enabling dark mode interfaces.
- **Onboarding Setup Wizard**: Step-by-step wizard to guide administrators through local system requirements check and initial module selection on activation.

### Improved
- **Modern User Interface**: Designed a dark-mode-first dashboard featuring responsive layouts, CSS-animated progress loaders, dynamic SVG metrics rings, and interactive data charts.
- **Data Privacy**: Redesigned all audits to execute locally on the hosting server context without calling external diagnostic platforms or sending telemetry data.
- **AJAX Loading Flow**: Enhanced findings tables and analytics charts to display smooth skeleton loading animations during scan run completions.

### Fixed
- **Setup Wizard Redirect**: Fixed onboarding failure where submitting default wizard settings caused a redirect loop and permission errors on first setup.
- **Asynchronous Results Render**: Fixed race conditions where old results flashed on the screen at the end of a scan run before the new metrics were loaded.