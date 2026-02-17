<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap ld-auditor-wrap">
    <!-- WordPress admin notices will render here, above our UI -->
    <h1 style="display:none;">WP Auditor</h1>

    <div class="ld-auditor-header">
        <div class="ld-auditor-logo">
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="32" height="32" rx="6" fill="#0F172A"/>
                <path d="M8 22V10h3v9.5h5.5V22H8z" fill="#38BDF8"/>
                <path d="M18 22V10h3.5c3 0 4.5 1.8 4.5 4.2 0 2.4-1.5 4.2-4.5 4.2H21V22h-3z M21 12.5v3.4h.5c1.2 0 1.8-.7 1.8-1.7s-.6-1.7-1.8-1.7H21z" fill="#38BDF8"/>
            </svg>
            <div>
                <h2 style="color:#F8FAFC;font-size:22px;font-weight:700;margin:0;padding:0;letter-spacing:-0.02em;">Launch Digital WP Auditor</h2>
                <p class="ld-auditor-subtitle">Plugin Health & Performance Audit Tool</p>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="ld-tabs">
        <button class="ld-tab active" data-tab="plugins">Plugin Audit</button>
        <button class="ld-tab" data-tab="performance">Backend Performance</button>
    </div>

    <!-- ==================== PLUGIN AUDIT TAB ==================== -->
    <div class="ld-tab-content active" id="tab-plugins">

        <div class="ld-auditor-actions">
            <button id="ld-run-scan" class="button button-primary button-hero">
                <span class="dashicons dashicons-search" style="margin-top: 4px;"></span>
                Run Plugin Scan
            </button>
            <button id="ld-export-report" class="button button-secondary button-hero" disabled>
                <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                Export Report
            </button>
        </div>

        <div id="ld-scan-progress" style="display:none;">
            <div class="ld-progress-bar">
                <div class="ld-progress-fill"></div>
            </div>
            <p class="ld-progress-text">Scanning plugins...</p>
        </div>

        <div id="ld-results" style="display:none;">
            <div class="ld-summary-grid" id="ld-summary-cards"></div>
            <div id="ld-redundancies"></div>
            <div class="ld-table-header">
                <h2>Plugin Details</h2>
                <div class="ld-filters">
                    <select id="ld-filter-status">
                        <option value="all">All Plugins</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                    <select id="ld-filter-risk">
                        <option value="all">All Risk Levels</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low / OK</option>
                    </select>
                    <select id="ld-filter-recommendation">
                        <option value="all">All Recommendations</option>
                        <option value="DELETE">Can Delete</option>
                        <option value="REPLACE">Should Replace</option>
                        <option value="REVIEW">Needs Review</option>
                        <option value="KEEP">Keep</option>
                    </select>
                </div>
            </div>
            <div id="ld-plugin-list"></div>
            <div id="ld-cron-section">
                <h2>Scheduled Tasks (WP-Cron)</h2>
                <div id="ld-cron-list"></div>
            </div>
        </div>

    </div>

    <!-- ==================== PERFORMANCE TAB ==================== -->
    <div class="ld-tab-content" id="tab-performance">

        <div class="ld-auditor-actions">
            <button id="ld-run-perf-scan" class="button button-primary button-hero">
                <span class="dashicons dashicons-performance" style="margin-top: 4px;"></span>
                Run Performance Scan
            </button>
            <button id="ld-export-perf-pdf" class="button button-secondary button-hero" disabled>
                <span class="dashicons dashicons-pdf" style="margin-top: 4px;"></span>
                Export PDF Report
            </button>
            <button id="ld-optimize" class="button button-secondary button-hero" disabled>
                <span class="dashicons dashicons-admin-tools" style="margin-top: 4px;"></span>
                Optimize Now
            </button>
        </div>

        <div id="ld-perf-progress" style="display:none;">
            <div class="ld-progress-bar">
                <div class="ld-progress-fill"></div>
            </div>
            <p class="ld-progress-text">Analyzing backend performance...</p>
        </div>

        <div id="ld-perf-results" style="display:none;">
            <div id="ld-optimize-results"></div>
            <div class="ld-summary-grid" id="ld-perf-summary"></div>
            <div id="ld-perf-issues"></div>
            <div class="ld-perf-section" id="ld-perf-php"></div>
            <div class="ld-perf-section" id="ld-perf-autoload"></div>
            <div class="ld-perf-section" id="ld-perf-revisions"></div>
            <div class="ld-perf-section" id="ld-perf-database"></div>
        </div>

    </div>

</div>
