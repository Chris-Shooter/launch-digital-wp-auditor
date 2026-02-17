# CLAUDE.md — Project Instructions for Claude Code

## Project Overview

**Launch Digital WP Auditor** is a WordPress plugin that audits installed plugins and backend performance. It's used internally by Launch Digital to assess client WordPress sites.

The plugin lives under **Tools → WP Auditor** in the WordPress admin and has two tabs:

1. **Plugin Audit** — Scans all installed plugins for health, usage, redundancy, and security risks. Generates a branded HTML export report.
2. **Backend Performance** — Profiles PHP config, database bloat, autoloaded options, post revisions, object cache, transients, cron jobs, and more.

## File Structure

```
launch-digital-wp-auditor/
├── launch-digital-wp-auditor.php   # Main plugin file — all PHP logic (scanner, API, analysis)
├── assets/
│   ├── admin.css                   # Admin dashboard styles (both tabs)
│   └── admin.js                    # Admin JS — AJAX calls, rendering, tab switching
├── templates/
│   ├── admin-page.php              # Admin page HTML template (tab structure)
│   └── report.php                  # Branded HTML export report template
├── README.md                       # Usage documentation
├── CLAUDE.md                       # This file
└── .gitignore
```

## Architecture

- **Single-class PHP plugin** (`LD_WP_Auditor`) using singleton pattern
- All scan logic is server-side PHP, triggered via WordPress AJAX
- Frontend is vanilla JS with jQuery (WordPress bundled), no build step required
- Results render client-side from JSON responses
- Plugin data is cached in WordPress transients (12hr for WP.org API, 1hr for scan results)
- Export report is a self-contained HTML file generated from `templates/report.php`

## Key AJAX Endpoints

All use `wp_ajax_` hooks and require `manage_options` capability + nonce verification:

- `ld_auditor_run_scan` — Full plugin audit scan
- `ld_auditor_export_report` — Generate HTML report from cached scan
- `ld_auditor_run_perf_scan` — Backend performance profiler

## Important Design Decisions

- **Background plugin detection**: Plugins categorised as seo, security, caching, backup, analytics, email, admin, functionality, media, or ecommerce are excluded from "unused content" warnings since they operate without shortcodes/blocks/widgets
- **Health score**: Calibrated so a typical healthy site scores 70+. Only genuinely problematic sites should score below 50. There's a floor of 65 if 70%+ of plugins are healthy
- **Performance score**: Starts at 100, deducts per issue found. Weighted toward things that actually impact backend speed (autoloaded data, OPcache, PHP version, object cache)
- **Plugin categorisation**: Uses both explicit slug mapping (for ~35 popular plugins) and keyword matching against slug/name/description
- **WP.org API calls**: Cached per-plugin for 12 hours to avoid rate limiting
- **Admin notices**: Uses a hidden `<h1>` trick so WordPress injects notices above the branded header, not inside it

## Coding Conventions

- PHP: WordPress coding standards, use `$wpdb->prepare()` for all queries
- JS: jQuery IIFE wrapper, `escHtml()` helper for all user-facing output
- CSS: BEM-ish naming with `ld-` prefix, no CSS framework
- All user-facing strings should be escaped (`esc_html()`, `esc_attr()`)

## Testing

No automated tests yet. To test manually:

1. Install on a WordPress site with a mix of active/inactive plugins
2. Go to Tools → WP Auditor
3. Run Plugin Scan — verify all plugins appear with correct categorisation
4. Check that inactive plugins are flagged, WP.org data pulls correctly
5. Export report — verify HTML downloads and renders standalone
6. Switch to Backend Performance tab
7. Run Performance Scan — verify PHP config, autoloaded options, revisions, database tables all populate
8. Check issues list has actionable recommendations with fixes

## Common Tasks

### Adding a new plugin to the explicit slug map
In `launch-digital-wp-auditor.php`, find the `$slug_map` array inside `categorize_plugin()` and add the slug → category mapping.

### Adding a new background category
Update the `$background_types` array in the `analyze_plugin()` method.

### Adding a new performance check
1. Add a private method (e.g., `check_something()`) 
2. Call it in `ajax_run_perf_scan()` and add to the results array
3. Add analysis logic in `analyze_performance()` 
4. Add a render function in `admin.js`
5. Add a container div in `templates/admin-page.php`

### Adjusting health score weights
Modify `calculate_health_score()` — the per-risk-level deductions and the healthy ratio floor.

## Future Improvements (V2 ideas)

- PDF export instead of / in addition to HTML
- Database query profiling per plugin (needs SAVEQUERIES)
- HTTP request monitoring (plugins phoning home)
- Frontend asset weight per plugin (actual file sizes of enqueued JS/CSS)
- Scheduled automated scans with email reports
- Multi-site support
- REST API endpoint for headless access
