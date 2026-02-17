<?php if (!defined('ABSPATH')) exit; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WordPress Plugin Audit Report — <?php echo esc_html($results['site_name']); ?></title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1E293B; line-height: 1.6; background: #F8FAFC; }

    .report { max-width: 900px; margin: 0 auto; padding: 40px 24px; }

    /* Cover */
    .cover { background: #0F172A; color: #F8FAFC; border-radius: 16px; padding: 48px 40px; margin-bottom: 32px; text-align: center; }
    .cover-logo { font-size: 14px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #38BDF8; margin-bottom: 24px; }
    .cover h1 { font-size: 32px; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 8px; }
    .cover .cover-site { font-size: 18px; color: #94A3B8; margin-bottom: 4px; }
    .cover .cover-date { font-size: 14px; color: #64748B; }

    /* Summary */
    .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px; }
    .summary-card { background: #FFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; text-align: center; }
    .summary-card .value { font-size: 42px; font-weight: 800; letter-spacing: -0.03em; line-height: 1; }
    .summary-card .label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #64748B; margin-top: 8px; }
    .val-health { color: #10B981; }
    .val-health.poor { color: #EF4444; }
    .val-health.fair { color: #F59E0B; }
    .val-total { color: #0F172A; }
    .val-issues { color: #EF4444; }
    .val-delete { color: #8B5CF6; }
    .val-inactive { color: #F59E0B; }

    /* Sections */
    .section { margin-bottom: 32px; }
    .section h2 { font-size: 20px; font-weight: 700; color: #0F172A; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #E2E8F0; }

    /* Redundancy warnings */
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

    /* Footer */
    .report-footer { text-align: center; color: #94A3B8; font-size: 12px; padding: 32px 0; border-top: 1px solid #E2E8F0; margin-top: 40px; }
    .report-footer a { color: #38BDF8; text-decoration: none; }

    /* Environment info */
    .env-info { display: flex; gap: 24px; flex-wrap: wrap; font-size: 13px; color: #64748B; margin-bottom: 24px; }
    .env-info span { background: #FFF; padding: 6px 14px; border-radius: 6px; border: 1px solid #E2E8F0; }

    @media print {
        body { background: #FFF; }
        .report { padding: 20px; }
        .cover { break-after: page; }
        .plugin-row { break-inside: avoid; }
    }
</style>
</head>
<body>
<div class="report">

    <!-- Cover -->
    <div class="cover">
        <div class="cover-logo">Launch Digital</div>
        <h1>WordPress Plugin Audit Report</h1>
        <div class="cover-site"><?php echo esc_html($results['site_name']); ?> — <?php echo esc_html($results['site_url']); ?></div>
        <div class="cover-date">Generated: <?php echo esc_html($results['scan_date']); ?></div>
    </div>

    <!-- Environment -->
    <div class="env-info">
        <span><strong>WordPress:</strong> <?php echo esc_html($results['wp_version']); ?></span>
        <span><strong>PHP:</strong> <?php echo esc_html($results['php_version']); ?></span>
        <span><strong>Total Plugins:</strong> <?php echo esc_html($results['total_plugins']); ?></span>
    </div>

    <!-- Summary -->
    <div class="summary-grid">
        <?php
        $s = $results['summary'];
        $healthClass = $s['health_score'] >= 70 ? 'val-health' : ($s['health_score'] >= 40 ? 'val-health fair' : 'val-health poor');
        ?>
        <div class="summary-card">
            <div class="value <?php echo $healthClass; ?>"><?php echo $s['health_score']; ?></div>
            <div class="label">Health Score</div>
        </div>
        <div class="summary-card">
            <div class="value val-issues"><?php echo $s['critical_issues'] + $s['high_issues']; ?></div>
            <div class="label">Issues Found</div>
        </div>
        <div class="summary-card">
            <div class="value val-delete"><?php echo $s['can_delete']; ?></div>
            <div class="label">Can Remove</div>
        </div>
        <div class="summary-card">
            <div class="value val-inactive"><?php echo $s['inactive_count']; ?></div>
            <div class="label">Inactive Plugins</div>
        </div>
        <div class="summary-card">
            <div class="value val-total"><?php echo $results['active_count']; ?></div>
            <div class="label">Active Plugins</div>
        </div>
        <div class="summary-card">
            <div class="value val-total"><?php echo $s['total_cron_jobs']; ?></div>
            <div class="label">Cron Jobs</div>
        </div>
    </div>

    <!-- Redundancies -->
    <?php if (!empty($results['redundancies'])): ?>
    <div class="section">
        <h2>⚠️ Redundancy Warnings</h2>
        <?php foreach ($results['redundancies'] as $r): ?>
        <div class="warning-box">
            <strong>Multiple <?php echo esc_html(strtoupper($r['category'])); ?> plugins detected:</strong>
            <?php echo esc_html($r['message']); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Plugins -->
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

            <div class="plugin-details">
                <?php
                $wpOrg = $p['wp_org_data'] ?? [];
                $notOnWpOrg = !empty($wpOrg['not_on_wporg']);
                ?>
                <?php if (!$notOnWpOrg): ?>
                <div class="detail">
                    <div class="detail-label">Last Updated</div>
                    <div class="detail-value"><?php echo esc_html(!empty($wpOrg['last_updated']) ? substr($wpOrg['last_updated'], 0, 10) : 'Unknown'); ?></div>
                </div>
                <div class="detail">
                    <div class="detail-label">Tested Up To</div>
                    <div class="detail-value">WP <?php echo esc_html($wpOrg['tested'] ?? 'Unknown'); ?></div>
                </div>
                <div class="detail">
                    <div class="detail-label">Active Installs</div>
                    <div class="detail-value"><?php echo !empty($wpOrg['active_installs']) ? number_format($wpOrg['active_installs']) . '+' : 'Unknown'; ?></div>
                </div>
                <?php else: ?>
                <div class="detail">
                    <div class="detail-label">WP.org</div>
                    <div class="detail-value">Not listed (premium/custom)</div>
                </div>
                <?php endif; ?>
                <div class="detail">
                    <div class="detail-label">File Size</div>
                    <div class="detail-value"><?php
                        $bytes = $p['file_size'];
                        $units = ['B', 'KB', 'MB', 'GB'];
                        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) $bytes /= 1024;
                        echo round($bytes, 1) . ' ' . $units[$i];
                    ?></div>
                </div>
                <div class="detail">
                    <div class="detail-label">DB Options</div>
                    <div class="detail-value"><?php echo esc_html($p['db_options_count']); ?> entries</div>
                </div>
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

    <!-- Footer -->
    <div class="report-footer">
        <p>This report was generated by <strong>Launch Digital WP Auditor</strong></p>
        <p><a href="https://launchdigital.co.za">launchdigital.co.za</a></p>
    </div>

</div>
</body>
</html>
