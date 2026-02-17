<?php if (!defined('ABSPATH')) exit; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Backend Performance Report — <?php echo esc_html($results['site_name']); ?></title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1E293B; line-height: 1.6; background: #F8FAFC; }

    .report { max-width: 900px; margin: 0 auto; padding: 40px 24px; }

    /* Cover */
    .cover { background: #0F172A; color: #F8FAFC; border-radius: 16px; padding: 48px 40px; margin-bottom: 32px; text-align: center; }
    .cover-logo { font-size: 14px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #38BDF8; margin-bottom: 24px; }
    .cover h1 { font-size: 32px; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 8px; }
    .cover .cover-site { font-size: 18px; color: #94A3B8; margin-bottom: 4px; }
    .cover .cover-url { font-size: 15px; color: #64748B; margin-bottom: 4px; }
    .cover .cover-date { font-size: 14px; color: #64748B; }

    /* Summary */
    .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px; }
    .summary-card { background: #FFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; text-align: center; }
    .summary-card .value { font-size: 42px; font-weight: 800; letter-spacing: -0.03em; line-height: 1; }
    .summary-card .label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #64748B; margin-top: 8px; }
    .val-score { color: #10B981; }
    .val-score.poor { color: #EF4444; }
    .val-score.fair { color: #F59E0B; }
    .val-issues { color: #EF4444; }
    .val-neutral { color: #0F172A; }

    /* Sections */
    .section { margin-bottom: 32px; }
    .section h2 { font-size: 20px; font-weight: 700; color: #0F172A; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #E2E8F0; }

    /* Issues */
    .issue-card { background: #FFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 18px 22px; margin-bottom: 12px; page-break-inside: avoid; }
    .issue-card.severity-critical { border-left: 4px solid #EF4444; }
    .issue-card.severity-high { border-left: 4px solid #F59E0B; }
    .issue-card.severity-medium { border-left: 4px solid #3B82F6; }
    .issue-card.severity-low { border-left: 4px solid #10B981; }

    .issue-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; gap: 12px; flex-wrap: wrap; }
    .issue-title { font-size: 15px; font-weight: 600; color: #0F172A; }
    .issue-category { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; background: #F1F5F9; padding: 3px 10px; border-radius: 20px; }
    .issue-message { font-size: 14px; color: #334155; margin-bottom: 10px; line-height: 1.5; }
    .issue-fix { background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 6px; padding: 10px 14px; font-size: 13px; color: #166534; }
    .issue-fix strong { color: #15803D; }

    /* Config grid */
    .config-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .config-item { background: #FFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px 18px; }
    .config-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94A3B8; margin-bottom: 4px; }
    .config-value { font-size: 15px; font-weight: 600; color: #0F172A; }
    .config-value.good { color: #10B981; }
    .config-value.warn { color: #F59E0B; }
    .config-value.bad { color: #EF4444; }

    /* Tables */
    .data-table { width: 100%; background: #FFF; border: 1px solid #E2E8F0; border-radius: 10px; overflow: hidden; border-collapse: collapse; margin-bottom: 16px; }
    .data-table th { background: #F8FAFC; padding: 10px 16px; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; border-bottom: 1px solid #E2E8F0; }
    .data-table td { padding: 8px 16px; font-size: 13px; color: #334155; border-bottom: 1px solid #F1F5F9; }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table code { background: #F1F5F9; padding: 2px 8px; border-radius: 4px; font-size: 12px; word-break: break-all; }

    .size-bar { display: inline-block; height: 6px; border-radius: 3px; background: #38BDF8; vertical-align: middle; }
    .size-bar.large { background: #EF4444; }
    .size-bar.medium { background: #F59E0B; }

    /* Footer */
    .report-footer { text-align: center; color: #94A3B8; font-size: 12px; padding: 32px 0; border-top: 1px solid #E2E8F0; margin-top: 40px; }
    .report-footer a { color: #38BDF8; text-decoration: none; }

    @media print {
        body { background: #FFF; }
        .report { padding: 20px; }
        .cover { break-after: page; }
        .issue-card, .config-item { break-inside: avoid; }
        .section { break-inside: avoid; }
    }
</style>
</head>
<body>
<div class="report">

    <!-- Cover -->
    <div class="cover">
        <div class="cover-logo">Launch Digital</div>
        <h1>Backend Performance Report</h1>
        <div class="cover-site"><?php echo esc_html($results['site_name']); ?></div>
        <div class="cover-url"><?php echo esc_html($results['site_url']); ?></div>
        <div class="cover-date">Generated: <?php echo esc_html($results['scan_date']); ?></div>
    </div>

    <!-- Summary -->
    <?php
    $score = $results['perf_score'];
    $scoreClass = $score >= 70 ? 'val-score' : ($score >= 40 ? 'val-score fair' : 'val-score poor');
    $issueCount = count($results['perf_issues']);
    $critCount = 0;
    foreach ($results['perf_issues'] as $issue) {
        if ($issue['severity'] === 'critical' || $issue['severity'] === 'high') {
            $critCount++;
        }
    }
    $autoloadMb = round($results['wp_options']['autoloaded_size'] / 1024 / 1024, 1);
    $dbMb = $results['database_tables']['total_size_mb'];
    ?>
    <div class="summary-grid">
        <div class="summary-card">
            <div class="value <?php echo $scoreClass; ?>"><?php echo (int) $score; ?></div>
            <div class="label">Performance Score</div>
        </div>
        <div class="summary-card">
            <div class="value <?php echo $issueCount > 0 ? 'val-issues' : 'val-score'; ?>"><?php echo $issueCount; ?></div>
            <div class="label">Issues Found</div>
        </div>
        <div class="summary-card">
            <div class="value <?php echo $critCount > 0 ? 'val-issues' : 'val-score'; ?>"><?php echo $critCount; ?></div>
            <div class="label">Critical / High</div>
        </div>
        <div class="summary-card">
            <div class="value <?php echo $autoloadMb > 1 ? 'val-issues' : 'val-neutral'; ?>"><?php echo $autoloadMb; ?></div>
            <div class="label">Autoloaded MB</div>
        </div>
        <div class="summary-card">
            <div class="value val-neutral"><?php echo number_format($results['post_revisions']['total_revisions']); ?></div>
            <div class="label">Post Revisions</div>
        </div>
        <div class="summary-card">
            <div class="value val-neutral"><?php echo $dbMb; ?></div>
            <div class="label">Database MB</div>
        </div>
    </div>

    <!-- Issues -->
    <?php if (!empty($results['perf_issues'])): ?>
    <div class="section">
        <h2>Performance Issues</h2>
        <?php
        $sorted_issues = $results['perf_issues'];
        usort($sorted_issues, function($a, $b) {
            $order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
            return ($order[$a['severity']] ?? 4) - ($order[$b['severity']] ?? 4);
        });
        foreach ($sorted_issues as $issue):
        ?>
        <div class="issue-card severity-<?php echo esc_attr($issue['severity']); ?>">
            <div class="issue-header">
                <span class="issue-title"><?php echo esc_html($issue['title']); ?></span>
                <span class="issue-category"><?php echo esc_html($issue['category']); ?></span>
            </div>
            <div class="issue-message"><?php echo esc_html($issue['message']); ?></div>
            <?php if (!empty($issue['fix'])): ?>
            <div class="issue-fix"><strong>Fix:</strong> <?php echo esc_html($issue['fix']); ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="section">
        <h2>Performance Issues</h2>
        <div class="issue-card severity-low" style="text-align:center;padding:24px;">
            <strong style="color:#10B981;">No performance issues detected. Backend looks healthy.</strong>
        </div>
    </div>
    <?php endif; ?>

    <!-- PHP & Server Configuration -->
    <div class="section">
        <h2>Server &amp; PHP Configuration</h2>
        <?php
        $php = $results['php_config'];
        $cache = $results['object_cache'];
        $heartbeat = $results['heartbeat'];

        $phpClass = version_compare($php['version'], '8.1', '>=') ? 'good' : (version_compare($php['version'], '8.0', '>=') ? 'warn' : 'bad');
        $memMb = $php['memory_limit_bytes'] / 1024 / 1024;
        $memClass = $memMb >= 256 ? 'good' : ($memMb >= 128 ? 'warn' : 'bad');
        $execClass = (int) $php['max_execution_time'] >= 120 ? 'good' : ((int) $php['max_execution_time'] >= 30 ? 'warn' : 'bad');
        ?>
        <div class="config-grid">
            <div class="config-item">
                <div class="config-label">PHP Version</div>
                <div class="config-value <?php echo $phpClass; ?>"><?php echo esc_html($php['version']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Memory Limit</div>
                <div class="config-value <?php echo $memClass; ?>"><?php echo esc_html($php['memory_limit']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Max Execution Time</div>
                <div class="config-value <?php echo $execClass; ?>"><?php echo esc_html($php['max_execution_time']); ?>s</div>
            </div>
            <div class="config-item">
                <div class="config-label">OPcache</div>
                <div class="config-value <?php echo $php['opcache_enabled'] ? 'good' : 'bad'; ?>"><?php echo $php['opcache_enabled'] ? 'Enabled' : 'Disabled'; ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Upload Max Size</div>
                <div class="config-value"><?php echo esc_html($php['upload_max_size']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">PHP SAPI</div>
                <div class="config-value"><?php echo esc_html($php['sapi']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Object Cache</div>
                <div class="config-value <?php echo $cache['external_cache'] ? 'good' : 'warn'; ?>"><?php echo esc_html($cache['backend']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Heartbeat API</div>
                <div class="config-value"><?php echo $heartbeat['disabled'] ? 'Disabled' : 'Active (default intervals)'; ?></div>
            </div>
        </div>
    </div>

    <!-- Autoloaded Options -->
    <div class="section">
        <h2>Autoloaded Options (Top 25)</h2>
        <?php
        $wpOptions = $results['wp_options'];
        $autoloaded = $results['autoloaded'];
        $autoloadedSizeFmt = $this->format_bytes($wpOptions['autoloaded_size']);
        ?>
        <p style="color:#64748B;font-size:13px;margin-bottom:12px;">
            These options load on <strong>every single page request</strong>.
            Total autoloaded: <?php echo esc_html($autoloadedSizeFmt); ?> across <?php echo number_format($wpOptions['autoloaded_count']); ?> entries.
            Total wp_options: <?php echo number_format($wpOptions['total_count']); ?> rows.
        </p>
        <?php if (!empty($autoloaded)):
            $maxSize = $autoloaded[0]['size'] ?: 1;
        ?>
        <table class="data-table">
            <thead><tr><th>Option Name</th><th>Size</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($autoloaded as $opt):
                $pct = max(2, ($opt['size'] / $maxSize) * 100);
                $barClass = $opt['size'] > 500000 ? 'large' : ($opt['size'] > 100000 ? 'medium' : '');
            ?>
            <tr>
                <td><code><?php echo esc_html($opt['name']); ?></code></td>
                <td style="white-space:nowrap;"><?php echo esc_html($this->format_bytes($opt['size'])); ?></td>
                <td style="width:40%;"><span class="size-bar <?php echo $barClass; ?>" style="width:<?php echo $pct; ?>%;"></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No autoloaded options data available.</p>
        <?php endif; ?>
    </div>

    <!-- Post Revisions & Cleanup -->
    <div class="section">
        <h2>Post Revisions &amp; Cleanup</h2>
        <?php $rev = $results['post_revisions']; $trans = $results['transients']; ?>
        <div class="config-grid">
            <div class="config-item">
                <div class="config-label">Total Revisions</div>
                <div class="config-value <?php echo $rev['total_revisions'] > 500 ? 'warn' : 'good'; ?>"><?php echo number_format($rev['total_revisions']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Published Posts</div>
                <div class="config-value"><?php echo number_format($rev['total_posts']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Auto-Drafts</div>
                <div class="config-value <?php echo $rev['total_autodrafts'] > 20 ? 'warn' : ''; ?>"><?php echo number_format($rev['total_autodrafts']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Trashed Posts</div>
                <div class="config-value <?php echo $rev['total_trashed'] > 20 ? 'warn' : ''; ?>"><?php echo number_format($rev['total_trashed']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Revision Limit</div>
                <div class="config-value <?php echo $rev['revision_limit'] === 'Unlimited (default)' ? 'warn' : 'good'; ?>"><?php echo esc_html($rev['revision_limit']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Expired Transients</div>
                <div class="config-value <?php echo $trans['expired'] > 50 ? 'warn' : 'good'; ?>"><?php echo number_format($trans['expired']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Total Transients</div>
                <div class="config-value"><?php echo number_format($trans['total']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Transient Data</div>
                <div class="config-value"><?php echo esc_html($this->format_bytes($trans['size'])); ?></div>
            </div>
        </div>

        <?php if (!empty($rev['worst_offenders'])): ?>
        <p style="color:#64748B;font-size:13px;margin:12px 0 8px;"><strong>Posts with most revisions:</strong></p>
        <table class="data-table">
            <thead><tr><th>Post</th><th>Revisions</th></tr></thead>
            <tbody>
            <?php foreach ($rev['worst_offenders'] as $p): ?>
            <tr>
                <td><?php echo esc_html($p['post_title'] ?: 'Post #' . $p['ID']); ?></td>
                <td><?php echo (int) $p['revision_count']; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Database Tables -->
    <div class="section">
        <h2>Database Tables</h2>
        <?php $db = $results['database_tables']; ?>
        <p style="color:#64748B;font-size:13px;margin-bottom:12px;">
            Total database size: <strong><?php echo esc_html($db['total_size_mb']); ?> MB</strong>
            <?php if ($db['total_overhead'] > 0): ?>
             | Overhead (reclaimable): <strong><?php echo esc_html($db['total_overhead']); ?> MB</strong>
            <?php endif; ?>
        </p>
        <?php if (!empty($db['tables'])): ?>
        <table class="data-table">
            <thead><tr><th>Table</th><th>Rows</th><th>Size</th><th>Overhead</th></tr></thead>
            <tbody>
            <?php foreach ($db['tables'] as $t):
                $ohStyle = (float) $t['overhead_mb'] > 1 ? ' style="color:#EF4444;font-weight:600;"' : '';
            ?>
            <tr>
                <td><code><?php echo esc_html($t['table_name']); ?></code></td>
                <td><?php echo number_format((int) ($t['table_rows'] ?? 0)); ?></td>
                <td><?php echo esc_html($t['size_mb']); ?> MB</td>
                <td<?php echo $ohStyle; ?>><?php echo (float) $t['overhead_mb'] > 0 ? esc_html($t['overhead_mb']) . ' MB' : '—'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No table data available.</p>
        <?php endif; ?>
    </div>

    <!-- Cron Load -->
    <div class="section">
        <h2>WP-Cron Status</h2>
        <?php $cron = $results['cron_load']; ?>
        <div class="config-grid">
            <div class="config-item">
                <div class="config-label">Total Cron Events</div>
                <div class="config-value"><?php echo number_format($cron['total_events']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Overdue Events</div>
                <div class="config-value <?php echo $cron['overdue'] > 10 ? 'bad' : ($cron['overdue'] > 0 ? 'warn' : 'good'); ?>"><?php echo number_format($cron['overdue']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Frequent Jobs (&lt;1hr)</div>
                <div class="config-value <?php echo count($cron['frequent']) > 5 ? 'warn' : ''; ?>"><?php echo count($cron['frequent']); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">WP-Cron</div>
                <div class="config-value"><?php echo !empty($cron['wp_cron_disabled']) ? 'Disabled (system cron)' : 'Active'; ?></div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="report-footer">
        <p>This report was generated by <strong>Launch Digital WP Auditor</strong></p>
        <p><a href="https://launchdigital.co.za">launchdigital.co.za</a></p>
    </div>

</div>
</body>
</html>
