<?php if (!defined('ABSPATH')) exit; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WordPress Site Audit Report — <?php echo esc_html($results['site_name']); ?></title>
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

    /* Score display */
    .score-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px; }
    .score-card { background: #FFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; text-align: center; }
    .score-card .value { font-size: 48px; font-weight: 800; letter-spacing: -0.03em; line-height: 1; }
    .score-card .label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #64748B; margin-top: 8px; }
    .score-card .sub { font-size: 13px; color: #94A3B8; margin-top: 4px; }
    .val-good { color: #10B981; }
    .val-fair { color: #F59E0B; }
    .val-poor { color: #EF4444; }
    .val-neutral { color: #0F172A; }
    .val-issues { color: #EF4444; }

    /* Environment */
    .env-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 32px; }
    .env-item { background: #FFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px 18px; }
    .env-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94A3B8; margin-bottom: 4px; }
    .env-value { font-size: 15px; font-weight: 600; color: #0F172A; }
    .env-value.good { color: #10B981; }
    .env-value.warn { color: #F59E0B; }
    .env-value.bad { color: #EF4444; }

    /* Sections */
    .section { margin-bottom: 32px; }
    .section h2 { font-size: 20px; font-weight: 700; color: #0F172A; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #E2E8F0; }

    /* Checklist */
    .checklist-item { display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; background: #FFF; border: 1px solid #E2E8F0; border-radius: 8px; margin-bottom: 8px; page-break-inside: avoid; }
    .checklist-item.severity-critical { border-left: 4px solid #EF4444; }
    .checklist-item.severity-high { border-left: 4px solid #F59E0B; }
    .checklist-item.severity-medium { border-left: 4px solid #3B82F6; }
    .checklist-item.severity-low { border-left: 4px solid #10B981; }
    .checklist-checkbox { font-size: 16px; color: #CBD5E1; flex-shrink: 0; margin-top: 2px; }
    .checklist-content { flex: 1; }
    .checklist-title { font-size: 14px; font-weight: 600; color: #0F172A; margin-bottom: 4px; }
    .checklist-desc { font-size: 13px; color: #334155; margin-bottom: 4px; }
    .checklist-fix { font-size: 12px; color: #166534; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 4px; padding: 6px 10px; }
    .severity-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; margin-left: 8px; }
    .sev-critical { background: #FEE2E2; color: #991B1B; }
    .sev-high { background: #FEF3C7; color: #92400E; }
    .sev-medium { background: #DBEAFE; color: #1E40AF; }
    .sev-low { background: #DCFCE7; color: #166534; }

    /* Warning boxes */
    .warning-box { background: #FEF3C7; border: 1px solid #FCD34D; border-left: 4px solid #F59E0B; border-radius: 8px; padding: 16px 20px; margin-bottom: 12px; font-size: 14px; color: #78350F; }
    .warning-box strong { color: #92400E; }

    /* Plugin rows */
    .plugin-row { background: #FFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 20px 24px; margin-bottom: 12px; page-break-inside: avoid; }
    .plugin-row.risk-critical { border-left: 4px solid #EF4444; }
    .plugin-row.risk-high { border-left: 4px solid #F59E0B; }
    .plugin-row.risk-medium { border-left: 4px solid #3B82F6; }
    .plugin-row.risk-low { border-left: 4px solid #10B981; }
    .plugin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
    .plugin-name { font-size: 16px; font-weight: 700; color: #0F172A; }
    .plugin-badges { display: flex; gap: 6px; flex-wrap: wrap; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }
    .badge-active { background: #DCFCE7; color: #166534; }
    .badge-inactive { background: #FEE2E2; color: #991B1B; }
    .badge-delete { background: #EF4444; color: #FFF; }
    .badge-replace { background: #F59E0B; color: #FFF; }
    .badge-review { background: #3B82F6; color: #FFF; }
    .badge-monitor { background: #8B5CF6; color: #FFF; }
    .badge-keep { background: #10B981; color: #FFF; }
    .plugin-meta { display: flex; gap: 20px; font-size: 13px; color: #64748B; margin-bottom: 12px; flex-wrap: wrap; }
    .plugin-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; margin-bottom: 12px; }
    .detail { background: #F8FAFC; border-radius: 6px; padding: 8px 12px; }
    .detail-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94A3B8; }
    .detail-value { font-size: 13px; font-weight: 500; color: #0F172A; }
    .issue-list { margin-bottom: 12px; }
    .issue { display: flex; align-items: flex-start; gap: 8px; padding: 8px 12px; background: #FFF; border: 1px solid #F1F5F9; border-radius: 6px; margin-bottom: 4px; font-size: 13px; color: #334155; }
    .issue-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 6px; }
    .issue-dot.critical { background: #EF4444; }
    .issue-dot.high { background: #F59E0B; }
    .issue-dot.medium { background: #3B82F6; }
    .issue-dot.low { background: #10B981; }
    .recommendation { background: #0F172A; color: #F8FAFC; border-radius: 8px; padding: 12px 16px; font-size: 13px; font-weight: 500; }

    /* Issue cards */
    .issue-card { background: #FFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 18px 22px; margin-bottom: 12px; page-break-inside: avoid; }
    .issue-card.severity-critical { border-left: 4px solid #EF4444; }
    .issue-card.severity-high { border-left: 4px solid #F59E0B; }
    .issue-card.severity-medium { border-left: 4px solid #3B82F6; }
    .issue-card.severity-low { border-left: 4px solid #10B981; }
    .issue-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; gap: 12px; flex-wrap: wrap; }
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
        body { background: #FFF; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .report { padding: 20px; max-width: none; }
        .cover { break-after: page; }
        .plugin-row, .issue-card, .checklist-item, .config-item { break-inside: avoid; }
        .section { break-inside: avoid; }
        .badge, .score-card, .severity-badge, .issue-dot, .size-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>
<div class="report">

    <!-- Cover -->
    <div class="cover">
        <div class="cover-logo">Launch Digital</div>
        <h1>WordPress Site Audit Report</h1>
        <div class="cover-site"><?php echo esc_html($results['site_name']); ?></div>
        <div class="cover-url"><?php echo esc_html($results['site_url']); ?></div>
        <div class="cover-date">Generated: <?php echo esc_html($results['scan_date']); ?></div>
    </div>

    <!-- Score Breakdown -->
    <?php
    $overall = $results['overall_score'];
    $health  = $results['health_score'];
    $perf    = $results['perf_score'];
    $overallClass = $overall >= 70 ? 'val-good' : ($overall >= 40 ? 'val-fair' : 'val-poor');
    $healthClass  = $health >= 70 ? 'val-good' : ($health >= 40 ? 'val-fair' : 'val-poor');
    $perfClass    = $perf >= 70 ? 'val-good' : ($perf >= 40 ? 'val-fair' : 'val-poor');
    ?>
    <div class="score-grid">
        <div class="score-card">
            <div class="value <?php echo $overallClass; ?>"><?php echo (int) $overall; ?></div>
            <div class="label">Overall Score</div>
            <div class="sub">out of 100</div>
        </div>
        <div class="score-card">
            <div class="value <?php echo $healthClass; ?>"><?php echo (int) $health; ?></div>
            <div class="label">Plugin Health</div>
            <div class="sub"><?php echo (int) $results['total_plugins']; ?> plugins scanned</div>
        </div>
        <div class="score-card">
            <div class="value <?php echo $perfClass; ?>"><?php echo (int) $perf; ?></div>
            <div class="label">Performance</div>
            <div class="sub"><?php echo count($results['perf_issues']); ?> issues found</div>
        </div>
    </div>

    <!-- Environment -->
    <div class="section">
        <h2>Environment</h2>
        <?php
        $php = $results['php_config'];
        $phpClass = version_compare($php['version'], '8.1', '>=') ? 'good' : (version_compare($php['version'], '8.0', '>=') ? 'warn' : 'bad');
        $memMb = $php['memory_limit_bytes'] / 1024 / 1024;
        $memClass = $memMb >= 256 ? 'good' : ($memMb >= 128 ? 'warn' : 'bad');
        ?>
        <div class="env-grid">
            <div class="env-item">
                <div class="env-label">WordPress</div>
                <div class="env-value"><?php echo esc_html($results['wp_version']); ?></div>
            </div>
            <div class="env-item">
                <div class="env-label">PHP Version</div>
                <div class="env-value <?php echo $phpClass; ?>"><?php echo esc_html($php['version']); ?></div>
            </div>
            <div class="env-item">
                <div class="env-label">Memory Limit</div>
                <div class="env-value <?php echo $memClass; ?>"><?php echo esc_html($php['memory_limit']); ?></div>
            </div>
            <div class="env-item">
                <div class="env-label">OPcache</div>
                <div class="env-value <?php echo $php['opcache_enabled'] ? 'good' : 'bad'; ?>"><?php echo $php['opcache_enabled'] ? 'Enabled' : 'Disabled'; ?></div>
            </div>
            <div class="env-item">
                <div class="env-label">Object Cache</div>
                <div class="env-value <?php echo $results['object_cache']['external_cache'] ? 'good' : 'warn'; ?>"><?php echo esc_html($results['object_cache']['backend']); ?></div>
            </div>
            <div class="env-item">
                <div class="env-label">Total Plugins</div>
                <div class="env-value"><?php echo (int) $results['total_plugins']; ?> (<?php echo (int) $results['active_count']; ?> active)</div>
            </div>
            <div class="env-item">
                <div class="env-label">Database Size</div>
                <div class="env-value"><?php echo esc_html($results['database_tables']['total_size_mb']); ?> MB</div>
            </div>
            <div class="env-item">
                <div class="env-label">Autoloaded Data</div>
                <div class="env-value <?php echo ($results['wp_options']['autoloaded_size'] / 1024 / 1024) > 1 ? 'warn' : 'good'; ?>"><?php echo esc_html($this->format_bytes($results['wp_options']['autoloaded_size'])); ?></div>
            </div>
        </div>
    </div>

    <!-- Recommendations Checklist -->
    <?php if (!empty($results['checklist'])): ?>
    <div class="section">
        <h2>Recommendations Checklist (<?php echo count($results['checklist']); ?> items)</h2>
        <?php foreach ($results['checklist'] as $i => $item): ?>
        <div class="checklist-item severity-<?php echo esc_attr($item['severity']); ?>">
            <span class="checklist-checkbox">&#9744;</span>
            <div class="checklist-content">
                <div class="checklist-title">
                    <?php echo ($i + 1) . '. ' . esc_html($item['title']); ?>
                    <span class="severity-badge sev-<?php echo esc_attr($item['severity']); ?>"><?php echo esc_html($item['severity']); ?></span>
                </div>
                <div class="checklist-desc"><?php echo esc_html($item['description']); ?></div>
                <?php if (!empty($item['fix'])): ?>
                <div class="checklist-fix"><?php echo esc_html($item['fix']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Redundancies -->
    <?php if (!empty($results['redundancies'])): ?>
    <div class="section">
        <h2>Redundancy Warnings</h2>
        <?php foreach ($results['redundancies'] as $r): ?>
        <div class="warning-box">
            <strong>Multiple <?php echo esc_html(strtoupper($r['category'])); ?> plugins detected:</strong>
            <?php echo esc_html($r['message']); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Plugin Analysis -->
    <div class="section">
        <h2>Plugin Analysis</h2>
        <?php foreach ($results['plugins'] as $p):
            $recType = 'KEEP';
            if (preg_match('/^(DELETE|REPLACE|REVIEW|MONITOR|KEEP)/', $p['recommendation'], $m)) {
                $recType = $m[1];
            }
            $badgeClass = [
                'DELETE' => 'badge-delete', 'REPLACE' => 'badge-replace',
                'REVIEW' => 'badge-review', 'MONITOR' => 'badge-monitor', 'KEEP' => 'badge-keep'
            ][$recType] ?? 'badge-keep';
        ?>
        <div class="plugin-row risk-<?php echo esc_attr($p['risk_level']); ?>">
            <div class="plugin-header">
                <span class="plugin-name"><?php echo esc_html($p['name']); ?></span>
                <div class="plugin-badges">
                    <span class="badge <?php echo $p['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                        <?php echo $p['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <span class="badge <?php echo esc_attr($badgeClass); ?>"><?php echo esc_html($recType); ?></span>
                </div>
            </div>
            <div class="plugin-meta">
                <span>v<?php echo esc_html($p['version']); ?></span>
                <span><?php echo esc_html($p['author']); ?></span>
                <span><?php echo esc_html($p['category']); ?></span>
            </div>
            <?php if (!empty($p['issues'])): ?>
            <div class="issue-list">
                <?php foreach ($p['issues'] as $issue): ?>
                <div class="issue">
                    <div class="issue-dot <?php echo esc_attr($issue['severity']); ?>"></div>
                    <span><?php echo esc_html($issue['message']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="recommendation"><?php echo esc_html($p['recommendation']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Performance Issues -->
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
            <div class="issue-card-header">
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
    <?php endif; ?>

    <!-- Server Configuration -->
    <div class="section">
        <h2>Server &amp; PHP Configuration</h2>
        <?php
        $cache = $results['object_cache'];
        $heartbeat = $results['heartbeat'];
        $execClass = (int) $php['max_execution_time'] >= 120 ? 'good' : ((int) $php['max_execution_time'] >= 30 ? 'warn' : 'bad');
        ?>
        <div class="config-grid">
            <div class="config-item"><div class="config-label">PHP Version</div><div class="config-value <?php echo $phpClass; ?>"><?php echo esc_html($php['version']); ?></div></div>
            <div class="config-item"><div class="config-label">Memory Limit</div><div class="config-value <?php echo $memClass; ?>"><?php echo esc_html($php['memory_limit']); ?></div></div>
            <div class="config-item"><div class="config-label">Max Execution Time</div><div class="config-value <?php echo $execClass; ?>"><?php echo esc_html($php['max_execution_time']); ?>s</div></div>
            <div class="config-item"><div class="config-label">OPcache</div><div class="config-value <?php echo $php['opcache_enabled'] ? 'good' : 'bad'; ?>"><?php echo $php['opcache_enabled'] ? 'Enabled' : 'Disabled'; ?></div></div>
            <div class="config-item"><div class="config-label">Upload Max Size</div><div class="config-value"><?php echo esc_html($php['upload_max_size']); ?></div></div>
            <div class="config-item"><div class="config-label">PHP SAPI</div><div class="config-value"><?php echo esc_html($php['sapi']); ?></div></div>
            <div class="config-item"><div class="config-label">Object Cache</div><div class="config-value <?php echo $cache['external_cache'] ? 'good' : 'warn'; ?>"><?php echo esc_html($cache['backend']); ?></div></div>
            <div class="config-item"><div class="config-label">Heartbeat API</div><div class="config-value"><?php echo $heartbeat['disabled'] ? 'Disabled' : 'Active (default intervals)'; ?></div></div>
        </div>
    </div>

    <!-- Autoloaded Options -->
    <div class="section">
        <h2>Autoloaded Options (Top 25)</h2>
        <?php
        $wpOptions = $results['wp_options'];
        $autoloaded = $results['autoloaded'];
        ?>
        <p style="color:#64748B;font-size:13px;margin-bottom:12px;">
            Total autoloaded: <?php echo esc_html($this->format_bytes($wpOptions['autoloaded_size'])); ?> across <?php echo number_format($wpOptions['autoloaded_count']); ?> entries.
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
        <?php endif; ?>
    </div>

    <!-- Post Revisions -->
    <div class="section">
        <h2>Post Revisions &amp; Cleanup</h2>
        <?php $rev = $results['post_revisions']; $trans = $results['transients']; ?>
        <div class="config-grid">
            <div class="config-item"><div class="config-label">Total Revisions</div><div class="config-value <?php echo $rev['total_revisions'] > 500 ? 'warn' : 'good'; ?>"><?php echo number_format($rev['total_revisions']); ?></div></div>
            <div class="config-item"><div class="config-label">Published Posts</div><div class="config-value"><?php echo number_format($rev['total_posts']); ?></div></div>
            <div class="config-item"><div class="config-label">Auto-Drafts</div><div class="config-value <?php echo $rev['total_autodrafts'] > 20 ? 'warn' : ''; ?>"><?php echo number_format($rev['total_autodrafts']); ?></div></div>
            <div class="config-item"><div class="config-label">Trashed Posts</div><div class="config-value <?php echo $rev['total_trashed'] > 20 ? 'warn' : ''; ?>"><?php echo number_format($rev['total_trashed']); ?></div></div>
            <div class="config-item"><div class="config-label">Revision Limit</div><div class="config-value <?php echo $rev['revision_limit'] === 'Unlimited (default)' ? 'warn' : 'good'; ?>"><?php echo esc_html($rev['revision_limit']); ?></div></div>
            <div class="config-item"><div class="config-label">Expired Transients</div><div class="config-value <?php echo $trans['expired'] > 50 ? 'warn' : 'good'; ?>"><?php echo number_format($trans['expired']); ?></div></div>
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
             | Overhead: <strong><?php echo esc_html($db['total_overhead']); ?> MB</strong>
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
                <td<?php echo $ohStyle; ?>><?php echo (float) $t['overhead_mb'] > 0 ? esc_html($t['overhead_mb']) . ' MB' : '&mdash;'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="report-footer">
        <p>This report was generated by <strong>Launch Digital WP Auditor v2.0</strong></p>
        <p><a href="https://launchdigital.co.za">launchdigital.co.za</a></p>
    </div>

</div>
</body>
</html>
