(function($) {
    'use strict';

    $(function() {

    var auditData = null;

    // =========================================================================
    // START AUDIT (unified)
    // =========================================================================
    $('#ld-start-audit, #ld-rerun-audit').on('click', function() {
        var $btn = $('#ld-start-audit');
        $btn.prop('disabled', true).text('Auditing...');
        $('#ld-audit-progress').show();
        $('#ld-audit-results').hide();
        $('#ld-before-after').hide();
        $('#ld-optimize-results').html('');

        // Reset phases
        $('.ld-phase').removeClass('active done');

        var $fill = $('#ld-audit-progress .ld-progress-fill');
        $fill.css('width', '0%');
        var progress = 0;
        var interval = setInterval(function() {
            progress += Math.random() * 8;
            if (progress > 90) progress = 90;
            $fill.css('width', progress + '%');
            if (progress < 35) {
                $('#ld-phase-plugins').addClass('active');
            } else if (progress < 70) {
                $('#ld-phase-plugins').removeClass('active').addClass('done');
                $('#ld-phase-perf').addClass('active');
            } else {
                $('#ld-phase-perf').removeClass('active').addClass('done');
                $('#ld-phase-analysis').addClass('active');
            }
        }, 400);

        $.ajax({
            url: ldAuditor.ajaxUrl,
            type: 'POST',
            timeout: 180000,
            data: {
                action: 'ld_auditor_run_audit',
                nonce: ldAuditor.nonce
            },
            success: function(response) {
                clearInterval(interval);
                $fill.css('width', '100%');
                $('.ld-phase').removeClass('active').addClass('done');

                if (response.success) {
                    auditData = response.data;
                    setTimeout(function() {
                        renderAuditResults(auditData);
                        $('#ld-audit-progress').fadeOut();
                        $('#ld-audit-results').fadeIn();
                        $('#ld-download-pdf').prop('disabled', false);
                    }, 500);
                } else {
                    alert('Audit failed: ' + (response.data || 'Unknown error'));
                    $('#ld-audit-progress').hide();
                }
            },
            error: function(xhr, status, error) {
                clearInterval(interval);
                alert('Audit failed: ' + error);
                $('#ld-audit-progress').hide();
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-shield" style="margin-top:4px;"></span> Start Audit');
            }
        });
    });

    // =========================================================================
    // MASTER RENDER
    // =========================================================================
    function renderAuditResults(data) {
        renderOverallSummary(data);
        renderEnvironment(data);
        renderChecklist(data.checklist);
        renderRedundancies(data.redundancies);
        renderPlugins(data.plugins);
        renderPerfIssues(data.perf_issues);
        renderPhpConfig(data.php_config, data.object_cache, data.heartbeat);
        renderAutoloadTable(data.autoloaded, data.wp_options);
        renderRevisions(data.post_revisions, data.transients);
        renderDatabaseTables(data.database_tables);
        renderCronJobs(data.cron_jobs);
        renderOptimizePreview(data.optimize_preview);
    }

    // =========================================================================
    // OVERALL SUMMARY
    // =========================================================================
    function renderOverallSummary(data) {
        var o = data.overall_score;
        var h = data.health_score;
        var p = data.perf_score;
        var overallClass = o >= 70 ? 'ld-card-health' : (o >= 40 ? 'ld-card-health fair' : 'ld-card-health poor');
        var healthClass = h >= 70 ? 'ld-card-health' : (h >= 40 ? 'ld-card-health fair' : 'ld-card-health poor');
        var perfClass = p >= 70 ? 'ld-card-health' : (p >= 40 ? 'ld-card-health fair' : 'ld-card-health poor');

        var totalIssues = data.checklist ? data.checklist.length : 0;

        var html = '';
        html += '<div class="ld-summary-card ld-card-overall">' +
            '<div class="ld-card-value ' + overallClass + '">' + o + '<span style="font-size:18px;color:#94A3B8;">/100</span></div>' +
            '<div class="ld-card-label">Overall Score</div></div>';
        html += card(h + '/100', 'Plugin Health', healthClass);
        html += card(p + '/100', 'Performance', perfClass);
        html += card(data.total_plugins, 'Total Plugins', 'ld-card-total');
        html += card(totalIssues, 'Issues Found', totalIssues > 0 ? 'ld-card-critical' : 'ld-card-health');
        html += card(data.optimize_preview && data.optimize_preview.has_work ? 'Yes' : 'Clean', 'Can Optimise', data.optimize_preview && data.optimize_preview.has_work ? 'ld-card-delete' : 'ld-card-health');

        $('#ld-overall-cards').html(html);
    }

    function card(value, label, cls) {
        return '<div class="ld-summary-card">' +
            '<div class="ld-card-value ' + cls + '">' + value + '</div>' +
            '<div class="ld-card-label">' + label + '</div>' +
            '</div>';
    }

    // =========================================================================
    // ENVIRONMENT BENCHMARK
    // =========================================================================
    function renderEnvironment(data) {
        var php = data.php_config;
        var cache = data.object_cache;

        var phpClass = parseFloat(php.version) >= 8.1 ? 'good' : (parseFloat(php.version) >= 8.0 ? 'warn' : 'bad');
        var memMb = php.memory_limit_bytes / 1024 / 1024;
        var memClass = memMb >= 256 ? 'good' : (memMb >= 128 ? 'warn' : 'bad');
        var dbMb = data.database_tables.total_size_mb;
        var autoloadMb = (data.wp_options.autoloaded_size / 1024 / 1024).toFixed(1);

        var html = '<h3>Environment Benchmark</h3>';
        html += '<div class="ld-config-grid">';
        html += configItem('WordPress', data.wp_version, '');
        html += configItem('PHP Version', php.version, phpClass);
        html += configItem('OPcache', php.opcache_enabled ? 'Enabled' : 'Disabled', php.opcache_enabled ? 'good' : 'bad');
        html += configItem('Object Cache', cache.backend, cache.external_cache ? 'good' : 'warn');
        html += configItem('Memory Limit', php.memory_limit, memClass);
        html += configItem('Database Size', dbMb + ' MB', '');
        html += configItem('Autoloaded Data', autoloadMb + ' MB', parseFloat(autoloadMb) > 1 ? 'warn' : 'good');
        html += configItem('Active / Inactive', data.active_count + ' / ' + data.inactive_count, '');
        html += '</div>';

        $('#ld-environment').html(html);
    }

    function configItem(label, value, cls) {
        return '<div class="ld-config-item">' +
            '<div class="ld-detail-label">' + escHtml(label) + '</div>' +
            '<div class="ld-detail-value ' + (cls || '') + '">' + escHtml(String(value)) + '</div>' +
            '</div>';
    }

    // =========================================================================
    // CHECKLIST
    // =========================================================================
    function renderChecklist(items) {
        if (!items || !items.length) {
            $('#ld-checklist-section').hide();
            return;
        }

        $('#ld-checklist-section').show();

        var html = '';
        items.forEach(function(item) {
            var storageKey = 'ld_audit_' + item.id;
            var isChecked = localStorage.getItem(storageKey) === '1';

            html += '<div class="ld-checklist-item severity-' + item.severity + (isChecked ? ' ld-checked' : '') + '" data-id="' + escHtml(item.id) + '">';
            html += '<label>';
            html += '<input type="checkbox" class="ld-check" data-id="' + escHtml(item.id) + '"' + (isChecked ? ' checked' : '') + '>';
            html += '<span class="ld-check-title">' + escHtml(item.title) + '</span>';
            html += '<span class="ld-check-severity ld-badge ld-badge-' + item.severity + '">' + item.severity + '</span>';
            html += '</label>';
            html += '<div class="ld-check-description">' + escHtml(item.description) + '</div>';
            if (item.fix) {
                html += '<div class="ld-check-fix"><strong>Fix:</strong> ' + escHtml(item.fix) + '</div>';
            }
            html += '</div>';
        });

        $('#ld-checklist').html(html);
        updateChecklistProgress(items);
    }

    function updateChecklistProgress(items) {
        if (!items) items = auditData ? auditData.checklist : [];
        if (!items || !items.length) return;

        var checked = 0;
        items.forEach(function(item) {
            if (localStorage.getItem('ld_audit_' + item.id) === '1') checked++;
        });

        var pct = Math.round((checked / items.length) * 100);

        var html = '<div class="ld-checklist-progress"><div class="ld-checklist-progress-fill" style="width:' + pct + '%;"></div></div>';
        html += '<div class="ld-checklist-count">' + checked + ' of ' + items.length + ' items addressed</div>';
        $('#ld-checklist-progress-wrap').html(html);
    }

    $(document).on('change', '.ld-check', function() {
        var id = $(this).data('id');
        var checked = $(this).is(':checked');
        var storageKey = 'ld_audit_' + id;

        if (checked) {
            localStorage.setItem(storageKey, '1');
            $(this).closest('.ld-checklist-item').addClass('ld-checked');
        } else {
            localStorage.removeItem(storageKey);
            $(this).closest('.ld-checklist-item').removeClass('ld-checked');
        }
        updateChecklistProgress();
    });

    // =========================================================================
    // REDUNDANCIES
    // =========================================================================
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

    // =========================================================================
    // PLUGINS
    // =========================================================================
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
                    usageText += ' &mdash; [' + escHtml(sc.shortcode) + '] in ' + sc.count + ' post(s)';
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
    // PERFORMANCE DETAILS
    // =========================================================================
    function renderPerfIssues(issues) {
        if (!issues || !issues.length) {
            $('#ld-perf-issues').html('<div class="ld-perf-issue severity-low" style="text-align:center;padding:24px;"><strong style="color:#10B981;">No performance issues detected. Backend looks healthy.</strong></div>');
            return;
        }

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

        var phpClass = parseFloat(php.version) >= 8.1 ? 'good' : (parseFloat(php.version) >= 8.0 ? 'warn' : 'bad');
        html += configItem('PHP Version', php.version, phpClass);

        var memMb = php.memory_limit_bytes / 1024 / 1024;
        var memClass = memMb >= 256 ? 'good' : (memMb >= 128 ? 'warn' : 'bad');
        html += configItem('Memory Limit', php.memory_limit, memClass);

        var execClass = parseInt(php.max_execution_time) >= 120 ? 'good' : (parseInt(php.max_execution_time) >= 30 ? 'warn' : 'bad');
        html += configItem('Max Execution Time', php.max_execution_time + 's', execClass);

        html += configItem('OPcache', php.opcache_enabled ? 'Enabled' : 'Disabled', php.opcache_enabled ? 'good' : 'bad');
        html += configItem('Upload Max Size', php.upload_max_size, '');
        html += configItem('PHP SAPI', php.sapi, '');
        html += configItem('Object Cache', cache.backend, cache.external_cache ? 'good' : 'warn');

        var hbStatus = heartbeat.disabled ? 'Disabled' : 'Active (default intervals)';
        html += configItem('Heartbeat API', hbStatus, '');

        html += '</div>';
        $('#ld-perf-php').html(html);
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

    // =========================================================================
    // CRON JOBS
    // =========================================================================
    function renderCronJobs(crons) {
        if (!crons || !crons.length) {
            $('#ld-cron-list').html('<p>No scheduled tasks found.</p>');
            return;
        }

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

    // =========================================================================
    // OPTIMIZE PREVIEW
    // =========================================================================
    function renderOptimizePreview(preview) {
        if (!preview) {
            $('#ld-optimize').prop('disabled', true);
            return;
        }

        if (!preview.has_work && (!preview.inactive_plugins || !preview.inactive_plugins.length)) {
            $('#ld-optimize-preview').html('<p class="ld-optimize-hint">Nothing to optimise — your site is already clean.</p>');
            $('#ld-optimize').prop('disabled', true);
            return;
        }

        var html = '<div class="ld-optimize-preview-card">';
        html += '<p><strong>The following actions will be performed:</strong></p>';
        html += '<ul class="ld-optimize-list">';

        if (preview.expired_transients > 0) {
            html += '<li>' + numberFormat(preview.expired_transients) + ' expired transient' + (preview.expired_transients !== 1 ? 's' : '') + ' will be deleted</li>';
        }
        if (preview.revisions > 0) {
            html += '<li>' + numberFormat(preview.revisions) + ' post revision' + (preview.revisions !== 1 ? 's' : '') + ' will be deleted</li>';
        }
        if (preview.trashed > 0) {
            html += '<li>' + numberFormat(preview.trashed) + ' trashed post' + (preview.trashed !== 1 ? 's' : '') + ' will be removed</li>';
        }
        if (preview.autodrafts > 0) {
            html += '<li>' + numberFormat(preview.autodrafts) + ' auto-draft' + (preview.autodrafts !== 1 ? 's' : '') + ' will be removed</li>';
        }
        if (preview.tables_with_overhead > 0) {
            html += '<li>' + preview.tables_with_overhead + ' database table' + (preview.tables_with_overhead !== 1 ? 's' : '') + ' will be optimised</li>';
        }
        if (preview.large_autoload > 0) {
            html += '<li>' + preview.large_autoload + ' large non-critical option' + (preview.large_autoload !== 1 ? 's' : '') + ' will have autoload disabled</li>';
        }

        html += '</ul>';

        // Inactive plugin recommendations
        if (preview.inactive_plugins && preview.inactive_plugins.length > 0) {
            html += '<p style="margin-top:16px;"><strong>Plugin Cleanup (manual action required):</strong></p>';
            html += '<ul class="ld-optimize-list">';
            preview.inactive_plugins.forEach(function(p) {
                html += '<li>' + escHtml(p.name) + ' — inactive, recommend deletion</li>';
            });
            html += '</ul>';
            html += '<p class="ld-optimize-hint">Inactive plugins must be deleted manually via <a href="' + escHtml(ldAuditor.siteUrl) + '/wp-admin/plugins.php?plugin_status=inactive" target="_blank">Plugins &rarr; Inactive</a>.</p>';
        }

        html += '</div>';

        $('#ld-optimize-preview').html(html);
        $('#ld-optimize').prop('disabled', !preview.has_work);
    }

    // =========================================================================
    // 1-CLICK OPTIMIZE
    // =========================================================================
    $('#ld-optimize').on('click', function() {
        if (!confirm('This will delete post revisions, trash, auto-drafts, expired transients, optimise database tables, and disable autoload on large non-critical options. A new performance score will be calculated.\n\nContinue?')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Optimising...');

        $.ajax({
            url: ldAuditor.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ld_auditor_optimize',
                nonce: ldAuditor.nonce
            },
            timeout: 120000,
            success: function(response) {
                if (response.success) {
                    renderOptimizeResults(response.data);
                    if (response.data.before_after) {
                        renderBeforeAfter(response.data.before_after);
                    }
                } else {
                    alert('Optimisation failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                alert('Optimisation failed: ' + error);
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools" style="margin-top:4px;"></span> Optimise Now');
            }
        });
    });

    function renderOptimizeResults(data) {
        var total = data.revisions + data.expired_transients + data.trashed + data.autodrafts;
        var html = '<div class="ld-optimize-card">';
        html += '<div class="ld-optimize-header">';
        html += '<span class="dashicons dashicons-yes-alt"></span>';
        html += '<strong>Optimisation Complete</strong>';
        html += '</div>';
        html += '<div class="ld-optimize-items">';

        if (data.revisions > 0) {
            html += optimizeItem(numberFormat(data.revisions), 'post revision' + (data.revisions !== 1 ? 's' : '') + ' deleted');
        }
        if (data.expired_transients > 0) {
            html += optimizeItem(numberFormat(data.expired_transients), 'expired transient' + (data.expired_transients !== 1 ? 's' : '') + ' cleared');
        }
        if (data.trashed > 0) {
            html += optimizeItem(numberFormat(data.trashed), 'trashed post' + (data.trashed !== 1 ? 's' : '') + ' removed');
        }
        if (data.autodrafts > 0) {
            html += optimizeItem(numberFormat(data.autodrafts), 'auto-draft' + (data.autodrafts !== 1 ? 's' : '') + ' removed');
        }
        if (data.optimized_tables > 0) {
            html += optimizeItem(data.optimized_tables, 'database table' + (data.optimized_tables !== 1 ? 's' : '') + ' optimised');
        }
        if (data.autoload_disabled > 0) {
            html += optimizeItem(data.autoload_disabled, 'large option' + (data.autoload_disabled !== 1 ? 's' : '') + ' removed from autoload (' + escHtml(data.autoload_freed_formatted) + ' freed)');
        }

        if (total === 0 && data.optimized_tables === 0 && data.autoload_disabled === 0) {
            html += '<div class="ld-optimize-item">Nothing to clean up — your site is already optimised.</div>';
        }

        html += '</div>';
        html += '</div>';

        $('#ld-optimize-results').html(html).hide().fadeIn();
    }

    function optimizeItem(count, label) {
        return '<div class="ld-optimize-item"><span class="ld-optimize-count">' + count + '</span> ' + label + '</div>';
    }

    // =========================================================================
    // BEFORE / AFTER COMPARISON
    // =========================================================================
    function renderBeforeAfter(ba) {
        var html = '<h3>Before / After Comparison</h3>';
        html += '<div class="ld-before-after-grid">';
        html += beforeAfterCard('Overall Score', ba.before.overall_score, ba.after.overall_score);
        html += beforeAfterCard('Plugin Health', ba.before.health_score, ba.after.health_score);
        html += beforeAfterCard('Performance', ba.before.perf_score, ba.after.perf_score);
        html += '</div>';

        $('#ld-before-after').html(html).fadeIn();
        $('html, body').animate({ scrollTop: $('#ld-before-after').offset().top - 50 }, 400);
    }

    function beforeAfterCard(label, before, after) {
        var delta = after - before;
        var deltaClass = delta > 0 ? 'ld-delta-positive' : (delta < 0 ? 'ld-delta-negative' : 'ld-delta-neutral');
        var deltaSign = delta > 0 ? '+' : '';

        return '<div class="ld-ba-card">' +
            '<div class="ld-ba-label">' + escHtml(label) + '</div>' +
            '<div class="ld-ba-scores">' +
                '<span class="ld-ba-before">' + before + '</span>' +
                '<span class="ld-ba-arrow">&rarr;</span>' +
                '<span class="ld-ba-after">' + after + '</span>' +
            '</div>' +
            '<div class="ld-ba-delta ' + deltaClass + '">' + deltaSign + delta + ' points</div>' +
        '</div>';
    }

    // =========================================================================
    // DOWNLOAD PDF
    // =========================================================================
    $('#ld-download-pdf').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Generating...');

        $.ajax({
            url: ldAuditor.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ld_auditor_export_audit_report',
                nonce: ldAuditor.nonce
            },
            success: function(response) {
                if (response.success) {
                    var printWindow = window.open('', '_blank');
                    if (printWindow) {
                        printWindow.document.write(response.data.html);
                        printWindow.document.close();
                        printWindow.onload = function() {
                            printWindow.print();
                        };
                    } else {
                        // Fallback: download as HTML if popup blocked
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
                } else {
                    alert('Export failed: ' + (response.data || 'Please run an audit first.'));
                }
            },
            error: function(xhr, status, error) {
                alert('Export failed: ' + error);
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-pdf" style="margin-top:4px;"></span> Download PDF');
            }
        });
    });

    // =========================================================================
    // HELPERS
    // =========================================================================
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
