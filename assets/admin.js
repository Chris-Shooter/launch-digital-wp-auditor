(function($) {
    'use strict';

    $(function() {

    var scanData = null;

    // Run Scan
    $('#ld-run-scan').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Scanning...');
        $('#ld-scan-progress').show();
        $('#ld-results').hide();

        // Animate progress bar
        var $fill = $('.ld-progress-fill');
        $fill.css('width', '0%');
        var progress = 0;
        var interval = setInterval(function() {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            $fill.css('width', progress + '%');
        }, 300);

        $.ajax({
            url: ldAuditor.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ld_auditor_run_scan',
                nonce: ldAuditor.nonce
            },
            success: function(response) {
                clearInterval(interval);
                $fill.css('width', '100%');

                if (response.success) {
                    scanData = response.data;
                    setTimeout(function() {
                        renderResults(scanData);
                        $('#ld-scan-progress').fadeOut();
                        $('#ld-results').fadeIn();
                        $('#ld-export-report').prop('disabled', false);
                    }, 500);
                } else {
                    alert('Scan failed: ' + (response.data || 'Unknown error'));
                    $('#ld-scan-progress').hide();
                }
            },
            error: function(xhr, status, error) {
                clearInterval(interval);
                alert('Scan failed: ' + error);
                $('#ld-scan-progress').hide();
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="margin-top:4px;"></span> Run Full Scan');
            }
        });
    });

    // Export Report
    $('#ld-export-report').on('click', function() {
        if (!scanData) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text('Generating...');

        $.ajax({
            url: ldAuditor.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ld_auditor_export_report',
                nonce: ldAuditor.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Download as HTML file
                    var blob = new Blob([response.data.html], { type: 'text/html' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'wp-audit-report-' + new Date().toISOString().split('T')[0] + '.html';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top:4px;"></span> Export Report');
            }
        });
    });

    // Render results
    function renderResults(data) {
        renderSummary(data);
        renderRedundancies(data.redundancies);
        renderPlugins(data.plugins);
        renderCronJobs(data.cron_jobs);
    }

    // Summary cards
    function renderSummary(data) {
        var s = data.summary;
        var healthClass = s.health_score >= 70 ? 'ld-card-health' : (s.health_score >= 40 ? 'ld-card-health fair' : 'ld-card-health poor');

        var html = '';
        html += card(s.health_score + '/100', 'Health Score', healthClass);
        html += card(data.total_plugins, 'Total Plugins', 'ld-card-total');
        html += card(data.active_count, 'Active', 'ld-card-active');
        html += card(data.inactive_count, 'Inactive', 'ld-card-inactive');
        html += card(s.critical_issues + s.high_issues, 'Issues Found', 'ld-card-critical');
        html += card(s.can_delete, 'Can Remove', 'ld-card-delete');

        $('#ld-summary-cards').html(html);
    }

    function card(value, label, cls) {
        return '<div class="ld-summary-card">' +
            '<div class="ld-card-value ' + cls + '">' + value + '</div>' +
            '<div class="ld-card-label">' + label + '</div>' +
            '</div>';
    }

    // Redundancies
    function renderRedundancies(redundancies) {
        if (!redundancies || !redundancies.length) {
            $('#ld-redundancies').html('');
            return;
        }

        var html = '';
        redundancies.forEach(function(r) {
            html += '<div class="ld-redundancy-warning">' +
                '<span class="dashicons dashicons-warning"></span>' +
                '<div><strong>Redundant Plugins Detected:</strong> ' +
                '<p>' + escHtml(r.message) + '</p></div></div>';
        });
        $('#ld-redundancies').html(html);
    }

    // Plugin list
    function renderPlugins(plugins) {
        var html = '';
        plugins.forEach(function(p, i) {
            html += renderPluginCard(p, i);
        });
        $('#ld-plugin-list').html(html);

        // Toggle expand
        $('.ld-plugin-main').on('click', function() {
            $(this).closest('.ld-plugin-card').toggleClass('expanded');
        });
    }

    function renderPluginCard(p, index) {
        var recType = getRecType(p.recommendation);
        var recBadgeClass = {
            'DELETE': 'ld-badge-delete',
            'REPLACE': 'ld-badge-replace',
            'REVIEW': 'ld-badge-review',
            'MONITOR': 'ld-badge-monitor',
            'KEEP': 'ld-badge-keep'
        }[recType] || 'ld-badge-keep';

        var html = '<div class="ld-plugin-card risk-' + p.risk_level + '" ' +
            'data-status="' + (p.is_active ? 'active' : 'inactive') + '" ' +
            'data-risk="' + p.risk_level + '" ' +
            'data-recommendation="' + recType + '">';

        // Main row
        html += '<div class="ld-plugin-main">';
        html += '<div class="ld-plugin-info">';
        html += '<div class="ld-plugin-name">' + escHtml(p.name) + '</div>';
        html += '<div class="ld-plugin-meta">';
        html += '<span>v' + escHtml(p.version) + '</span>';
        html += '<span>' + escHtml(p.author) + '</span>';
        html += '<span>' + formatBytes(p.file_size) + '</span>';
        html += '<span>' + escHtml(p.category) + '</span>';
        html += '</div></div>';

        html += '<div class="ld-plugin-badges">';
        html += '<span class="ld-badge ' + (p.is_active ? 'ld-badge-active' : 'ld-badge-inactive') + '">' +
            (p.is_active ? 'Active' : 'Inactive') + '</span>';
        if (p.issues.length > 0) {
            html += '<span class="ld-badge ld-badge-' + p.risk_level + '">' +
                p.issues.length + ' issue' + (p.issues.length > 1 ? 's' : '') + '</span>';
        }
        html += '<span class="ld-badge ' + recBadgeClass + '">' + recType + '</span>';
        html += '</div>';

        html += '<span class="ld-plugin-expand dashicons dashicons-arrow-down-alt2"></span>';
        html += '</div>';

        // Detail panel
        html += '<div class="ld-plugin-detail">';
        html += renderDetailGrid(p);

        // Issues
        if (p.issues.length > 0) {
            html += '<div class="ld-issues-list">';
            p.issues.forEach(function(issue) {
                html += '<div class="ld-issue-item">';
                html += '<div class="ld-issue-icon ' + issue.severity + '">!</div>';
                html += '<span>' + escHtml(issue.message) + '</span>';
                html += '</div>';
            });
            html += '</div>';
        }

        // Recommendation
        html += '<div class="ld-recommendation">' +
            '<strong>Recommendation:</strong> ' + escHtml(p.recommendation) + '</div>';

        html += '</div>'; // detail
        html += '</div>'; // card

        return html;
    }

    function renderDetailGrid(p) {
        var html = '<div class="ld-detail-grid">';

        // WP.org data
        var wpOrg = p.wp_org_data || {};
        if (!wpOrg.not_on_wporg) {
            html += detailItem('Last Updated', wpOrg.last_updated ? wpOrg.last_updated.split('T')[0] || wpOrg.last_updated.substring(0, 10) : 'Unknown');
            html += detailItem('Tested Up To', wpOrg.tested || 'Unknown');
            html += detailItem('Active Installs', wpOrg.active_installs ? numberFormat(wpOrg.active_installs) + '+' : 'Unknown');
            html += detailItem('Rating', wpOrg.rating ? wpOrg.rating + '% (' + wpOrg.num_ratings + ' reviews)' : 'N/A');
        } else {
            html += detailItem('WP.org', 'Not listed (premium or custom plugin)');
        }

        html += detailItem('File Size', formatBytes(p.file_size));
        html += detailItem('DB Options', p.db_options_count + ' entries in wp_options');
        html += detailItem('Cron Jobs', p.cron_jobs.length > 0 ? p.cron_jobs.length + ' scheduled task(s)' : 'None');
        html += detailItem('Assets', p.assets.scripts + ' script(s), ' + p.assets.styles + ' style(s)');

        // Usage detection
        if (p.is_active && p.usage_detected) {
            var u = p.usage_detected;
            var usageText = '';
            if (u.shortcodes_found) {
                usageText += '<span class="ld-usage-found">Shortcodes found</span>';
                u.shortcode_details.forEach(function(sc) {
                    usageText += ' — [' + escHtml(sc.shortcode) + '] in ' + sc.count + ' post(s)';
                });
            }
            if (u.blocks_found) {
                usageText += (usageText ? '<br>' : '') + '<span class="ld-usage-found">Gutenberg blocks detected</span>';
            }
            if (u.widgets_active) {
                usageText += (usageText ? '<br>' : '') + '<span class="ld-usage-found">Active widgets found</span>';
            }
            if (!u.shortcodes_found && !u.blocks_found && !u.widgets_active) {
                usageText = '<span class="ld-usage-none">No content usage detected</span>';
            }
            html += detailItem('Usage Detection', usageText, true);
        }

        html += '</div>';
        return html;
    }

    function detailItem(label, value, isHtml) {
        return '<div class="ld-detail-item">' +
            '<div class="ld-detail-label">' + escHtml(label) + '</div>' +
            '<div class="ld-detail-value">' + (isHtml ? value : escHtml(value)) + '</div>' +
            '</div>';
    }

    // Cron jobs table
    function renderCronJobs(crons) {
        if (!crons || !crons.length) {
            $('#ld-cron-list').html('<p>No scheduled tasks found.</p>');
            return;
        }

        // Limit to most relevant / show count
        var limit = 30;
        var html = '<table class="ld-cron-table">';
        html += '<thead><tr><th>Hook</th><th>Schedule</th><th>Interval</th><th>Next Run</th></tr></thead>';
        html += '<tbody>';

        var shown = crons.slice(0, limit);
        shown.forEach(function(c) {
            html += '<tr>';
            html += '<td><code>' + escHtml(c.hook) + '</code></td>';
            html += '<td>' + escHtml(c.schedule || 'once') + '</td>';
            html += '<td>' + (c.interval ? formatInterval(c.interval) : '—') + '</td>';
            html += '<td>' + escHtml(c.next_run) + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table>';

        if (crons.length > limit) {
            html += '<p style="color:#64748B;font-size:13px;margin-top:8px;">Showing ' + limit + ' of ' + crons.length + ' scheduled tasks.</p>';
        }

        $('#ld-cron-list').html(html);
    }

    // Filters
    $(document).on('change', '#ld-filter-status, #ld-filter-risk, #ld-filter-recommendation', function() {
        var statusFilter = $('#ld-filter-status').val();
        var riskFilter = $('#ld-filter-risk').val();
        var recFilter = $('#ld-filter-recommendation').val();

        $('.ld-plugin-card').each(function() {
            var $card = $(this);
            var show = true;

            if (statusFilter !== 'all' && $card.data('status') !== statusFilter) show = false;
            if (riskFilter !== 'all' && $card.data('risk') !== riskFilter) show = false;
            if (recFilter !== 'all' && $card.data('recommendation') !== recFilter) show = false;

            $card.toggle(show);
        });
    });

    // =========================================================================
    // TABS
    // =========================================================================
    $('.ld-tab').on('click', function() {
        var tab = $(this).data('tab');
        $('.ld-tab').removeClass('active');
        $(this).addClass('active');
        $('.ld-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });

    // =========================================================================
    // PERFORMANCE SCAN
    // =========================================================================
    $('#ld-run-perf-scan').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Scanning...');
        $('#ld-perf-progress').show();
        $('#ld-perf-results').hide();

        var $fill = $('#ld-perf-progress .ld-progress-fill');
        $fill.css('width', '0%');
        var progress = 0;
        var interval = setInterval(function() {
            progress += Math.random() * 20;
            if (progress > 90) progress = 90;
            $fill.css('width', progress + '%');
        }, 200);

        $.ajax({
            url: ldAuditor.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ld_auditor_run_perf_scan',
                nonce: ldAuditor.nonce
            },
            success: function(response) {
                clearInterval(interval);
                $fill.css('width', '100%');

                if (response.success) {
                    setTimeout(function() {
                        renderPerfResults(response.data);
                        $('#ld-perf-progress').fadeOut();
                        $('#ld-perf-results').fadeIn();
                    }, 400);
                } else {
                    alert('Performance scan failed: ' + (response.data || 'Unknown error'));
                    $('#ld-perf-progress').hide();
                }
            },
            error: function(xhr, status, error) {
                clearInterval(interval);
                alert('Performance scan failed: ' + error);
                $('#ld-perf-progress').hide();
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-performance" style="margin-top:4px;"></span> Run Performance Scan');
            }
        });
    });

    function renderPerfResults(data) {
        renderPerfSummary(data);
        renderPerfIssues(data.perf_issues);
        renderPhpConfig(data.php_config, data.object_cache, data.heartbeat);
        renderAutoloadTable(data.autoloaded, data.wp_options);
        renderRevisions(data.post_revisions, data.transients);
        renderDatabaseTables(data.database_tables);
    }

    function renderPerfSummary(data) {
        var s = data.perf_score;
        var healthClass = s >= 70 ? 'ld-card-health' : (s >= 40 ? 'ld-card-health fair' : 'ld-card-health poor');
        var issueCount = data.perf_issues.length;
        var critCount = data.perf_issues.filter(function(i) { return i.severity === 'critical' || i.severity === 'high'; }).length;

        var autoloadMb = (data.wp_options.autoloaded_size / 1024 / 1024).toFixed(1);
        var dbMb = data.database_tables.total_size_mb;

        var html = '';
        html += card(s + '/100', 'Performance Score', healthClass);
        html += card(issueCount, 'Issues Found', issueCount > 0 ? 'ld-card-critical' : 'ld-card-health');
        html += card(critCount, 'Critical/High', critCount > 0 ? 'ld-card-critical' : 'ld-card-health');
        html += card(autoloadMb + ' MB', 'Autoloaded Data', parseFloat(autoloadMb) > 1 ? 'ld-card-critical' : 'ld-card-health');
        html += card(numberFormat(data.post_revisions.total_revisions), 'Post Revisions', 'ld-card-total');
        html += card(dbMb + ' MB', 'Database Size', 'ld-card-total');

        $('#ld-perf-summary').html(html);
    }

    function renderPerfIssues(issues) {
        if (!issues || !issues.length) {
            $('#ld-perf-issues').html('<div class="ld-perf-issue severity-low" style="text-align:center;padding:24px;"><strong style="color:#10B981;">No performance issues detected. Backend looks healthy.</strong></div>');
            return;
        }

        // Sort: critical first
        var order = { 'critical': 0, 'high': 1, 'medium': 2, 'low': 3 };
        issues.sort(function(a, b) { return (order[a.severity] || 4) - (order[b.severity] || 4); });

        var html = '';
        issues.forEach(function(issue) {
            html += '<div class="ld-perf-issue severity-' + issue.severity + '">';
            html += '<div class="ld-perf-issue-header">';
            html += '<span class="ld-perf-issue-title">' + escHtml(issue.title) + '</span>';
            html += '<span class="ld-perf-issue-category">' + escHtml(issue.category) + '</span>';
            html += '</div>';
            html += '<div class="ld-perf-issue-message">' + escHtml(issue.message) + '</div>';
            if (issue.fix) {
                html += '<div class="ld-perf-issue-fix"><strong>Fix:</strong> ' + escHtml(issue.fix) + '</div>';
            }
            html += '</div>';
        });

        $('#ld-perf-issues').html(html);
    }

    function renderPhpConfig(php, cache, heartbeat) {
        var html = '<h3>Server & PHP Configuration</h3>';
        html += '<div class="ld-config-grid">';

        // PHP Version
        var phpClass = 'good';
        if (parseFloat(php.version) < 8.0) phpClass = 'bad';
        else if (parseFloat(php.version) < 8.1) phpClass = 'warn';
        html += configItem('PHP Version', php.version, phpClass);

        // Memory
        var memMb = php.memory_limit_bytes / 1024 / 1024;
        var memClass = memMb >= 256 ? 'good' : (memMb >= 128 ? 'warn' : 'bad');
        html += configItem('Memory Limit', php.memory_limit, memClass);

        // Max Execution
        var execClass = parseInt(php.max_execution_time) >= 120 ? 'good' : (parseInt(php.max_execution_time) >= 30 ? 'warn' : 'bad');
        html += configItem('Max Execution Time', php.max_execution_time + 's', execClass);

        // OPcache
        html += configItem('OPcache', php.opcache_enabled ? 'Enabled' : 'Disabled', php.opcache_enabled ? 'good' : 'bad');

        // Upload
        html += configItem('Upload Max Size', php.upload_max_size, '');

        // SAPI
        html += configItem('PHP SAPI', php.sapi, '');

        // Object Cache
        html += configItem('Object Cache', cache.backend, cache.external_cache ? 'good' : 'warn');

        // Heartbeat
        var hbStatus = heartbeat.disabled ? 'Disabled' : 'Active (default intervals)';
        html += configItem('Heartbeat API', hbStatus, '');

        html += '</div>';
        $('#ld-perf-php').html(html);
    }

    function configItem(label, value, cls) {
        return '<div class="ld-config-item">' +
            '<div class="ld-detail-label">' + escHtml(label) + '</div>' +
            '<div class="ld-detail-value ' + (cls || '') + '">' + escHtml(String(value)) + '</div>' +
            '</div>';
    }

    function renderAutoloadTable(autoloaded, wpOptions) {
        var html = '<h3>Autoloaded Options (Top 25)</h3>';
        html += '<p style="color:#64748B;font-size:13px;margin-bottom:12px;">These options load on <strong>every single page request</strong>. Total autoloaded: ' +
            formatBytes(wpOptions.autoloaded_size) + ' across ' + numberFormat(wpOptions.autoloaded_count) + ' entries. Total wp_options: ' + numberFormat(wpOptions.total_count) + ' rows.</p>';

        if (!autoloaded || !autoloaded.length) {
            html += '<p>No autoloaded options data available.</p>';
            $('#ld-perf-autoload').html(html);
            return;
        }

        var maxSize = autoloaded[0].size || 1;

        html += '<table class="ld-autoload-table">';
        html += '<thead><tr><th>Option Name</th><th>Size</th><th></th></tr></thead>';
        html += '<tbody>';

        autoloaded.forEach(function(opt) {
            var pct = Math.max(2, (opt.size / maxSize) * 100);
            var barClass = opt.size > 500000 ? 'large' : (opt.size > 100000 ? 'medium' : '');
            html += '<tr>';
            html += '<td><code>' + escHtml(opt.name) + '</code></td>';
            html += '<td style="white-space:nowrap;">' + formatBytes(opt.size) + '</td>';
            html += '<td style="width:40%;"><span class="size-bar ' + barClass + '" style="width:' + pct + '%;"></span></td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $('#ld-perf-autoload').html(html);
    }

    function renderRevisions(revisions, transients) {
        var html = '<h3>Post Revisions & Cleanup</h3>';
        html += '<div class="ld-config-grid">';
        html += configItem('Total Revisions', numberFormat(revisions.total_revisions), revisions.total_revisions > 500 ? 'warn' : 'good');
        html += configItem('Published Posts', numberFormat(revisions.total_posts), '');
        html += configItem('Auto-Drafts', numberFormat(revisions.total_autodrafts), revisions.total_autodrafts > 20 ? 'warn' : '');
        html += configItem('Trashed Posts', numberFormat(revisions.total_trashed), revisions.total_trashed > 20 ? 'warn' : '');
        html += configItem('Revision Limit', String(revisions.revision_limit), revisions.revision_limit === 'Unlimited (default)' ? 'warn' : 'good');
        html += configItem('Expired Transients', numberFormat(transients.expired), transients.expired > 50 ? 'warn' : 'good');
        html += configItem('Total Transients', numberFormat(transients.total), '');
        html += configItem('Transient Data', formatBytes(transients.size), '');
        html += '</div>';

        // Worst offender posts
        if (revisions.worst_offenders && revisions.worst_offenders.length) {
            html += '<p style="color:#64748B;font-size:13px;margin:12px 0 8px;"><strong>Posts with most revisions:</strong></p>';
            html += '<table class="ld-autoload-table">';
            html += '<thead><tr><th>Post</th><th>Revisions</th></tr></thead><tbody>';
            revisions.worst_offenders.forEach(function(p) {
                html += '<tr><td>' + escHtml(p.post_title || 'Post #' + p.ID) + '</td><td>' + p.revision_count + '</td></tr>';
            });
            html += '</tbody></table>';
        }

        $('#ld-perf-revisions').html(html);
    }

    function renderDatabaseTables(db) {
        var html = '<h3>Database Tables</h3>';
        html += '<p style="color:#64748B;font-size:13px;margin-bottom:12px;">Total database size: <strong>' + db.total_size_mb + ' MB</strong>';
        if (db.total_overhead > 0) {
            html += ' | Overhead (reclaimable): <strong>' + db.total_overhead + ' MB</strong>';
        }
        html += '</p>';

        if (!db.tables || !db.tables.length) {
            html += '<p>No table data available.</p>';
            $('#ld-perf-database').html(html);
            return;
        }

        html += '<table class="ld-autoload-table">';
        html += '<thead><tr><th>Table</th><th>Rows</th><th>Size</th><th>Overhead</th></tr></thead>';
        html += '<tbody>';

        db.tables.forEach(function(t) {
            var rowClass = parseFloat(t.overhead_mb) > 1 ? ' style="color:#EF4444;font-weight:600;"' : '';
            html += '<tr>';
            html += '<td><code>' + escHtml(t.table_name) + '</code></td>';
            html += '<td>' + numberFormat(parseInt(t.table_rows) || 0) + '</td>';
            html += '<td>' + t.size_mb + ' MB</td>';
            html += '<td' + rowClass + '>' + (parseFloat(t.overhead_mb) > 0 ? t.overhead_mb + ' MB' : '—') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $('#ld-perf-database').html(html);
    }

    // Helpers
    function getRecType(rec) {
        if (!rec) return 'KEEP';
        var match = rec.match(/^(DELETE|REPLACE|REVIEW|MONITOR|KEEP)/);
        return match ? match[1] : 'KEEP';
    }

    function formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = 0;
        while (bytes > 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }
        return bytes.toFixed(1) + ' ' + units[i];
    }

    function formatInterval(seconds) {
        if (seconds < 60) return seconds + 's';
        if (seconds < 3600) return Math.round(seconds / 60) + ' min';
        if (seconds < 86400) return Math.round(seconds / 3600) + ' hr';
        return Math.round(seconds / 86400) + ' day(s)';
    }

    function numberFormat(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    }); // end $(document).ready

})(jQuery);
