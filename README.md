# Launch Digital WP Auditor

WordPress plugin audit tool built for Launch Digital. Scans all installed plugins and generates a comprehensive health report with actionable recommendations.

## What It Scans

### Plugin Inventory
- All installed plugins (active and inactive)
- Version numbers, authors, file sizes
- Categorization by type (SEO, security, caching, forms, etc.)

### WordPress.org API Data
- Last updated date (flags abandoned plugins)
- Compatibility with your WP version
- Active install count and ratings
- Support thread resolution rate

### Usage Detection
- Scans all published content for plugin shortcodes
- Detects active Gutenberg blocks
- Checks for active widgets
- Identifies plugins that are active but not actually being used in any content

### Performance Indicators
- File size per plugin
- Number of database options stored in wp_options
- Enqueued scripts and styles (frontend bloat)
- WP-Cron scheduled tasks per plugin
- Flags aggressive cron intervals

### Redundancy Detection
- Identifies multiple plugins serving the same purpose
- Flags duplicate SEO, caching, security, and backup plugins

### Health Scoring
- Overall site health score (0-100)
- Per-plugin risk level: Critical, High, Medium, Low
- Clear recommendations: DELETE, REPLACE, REVIEW, MONITOR, KEEP

## Installation

1. Upload the `launch-digital-wp-auditor` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress admin
3. Go to **Tools → WP Auditor**
4. Click **Run Full Scan**
5. Click **Export Report** to download the branded HTML report

## Export

The exported report is a standalone HTML file that:
- Can be emailed directly to clients
- Prints cleanly (print-optimized CSS)
- Is branded with Launch Digital branding
- Includes all scan data, issues, and recommendations

## Notes

- The scan makes API calls to wordpress.org to fetch plugin data. Results are cached for 12 hours.
- Usage detection works by scanning the `wp_posts` table for known shortcodes and block patterns. It won't catch every possible integration method (e.g., plugins used purely via PHP in theme files).
- Background plugins (SEO, security, caching, backup, analytics, email, admin) are excluded from "unused" warnings since they operate without visible content output.
- The plugin itself has minimal overhead and can be safely activated, used for the scan, then deactivated/removed.

## Version

1.0.0
