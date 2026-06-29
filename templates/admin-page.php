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
                <p class="ld-auditor-subtitle">Comprehensive WordPress Site Audit Tool</p>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="ld-auditor-actions">
        <button id="ld-start-audit" class="button button-primary button-hero">
            <span class="dashicons dashicons-shield" style="margin-top: 4px;"></span>
            Start Audit
        </button>
        <button id="ld-download-pdf" class="button button-secondary button-hero" disabled>
            <span class="dashicons dashicons-pdf" style="margin-top: 4px;"></span>
            Download PDF
        </button>
    </div>

    <!-- Progress -->
    <div id="ld-audit-progress" style="display:none;">
        <div class="ld-progress-bar">
            <div class="ld-progress-fill"></div>
        </div>
        <p class="ld-progress-text">Running comprehensive audit...</p>
        <div class="ld-progress-phases">
            <span id="ld-phase-plugins" class="ld-phase">Scanning plugins...</span>
            <span id="ld-phase-perf" class="ld-phase">Checking performance...</span>
            <span id="ld-phase-analysis" class="ld-phase">Analysing results...</span>
        </div>
    </div>

    <!-- Results -->
    <div id="ld-audit-results" style="display:none;">

        <!-- Overall Summary -->
        <div class="ld-summary-grid" id="ld-overall-cards"></div>

        <!-- Environment Benchmark -->
        <div id="ld-environment" class="ld-perf-section"></div>

        <!-- Recommendations Checklist -->
        <div id="ld-checklist-section" class="ld-perf-section" style="display:none;">
            <h3>Recommendations Checklist</h3>
            <div id="ld-checklist-progress-wrap"></div>
            <div id="ld-checklist"></div>
        </div>

        <!-- Plugin Health -->
        <div id="ld-plugin-section">
            <div class="ld-table-header">
                <h2>Plugin Health</h2>
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
            <div id="ld-redundancies"></div>
            <div id="ld-plugin-list"></div>
        </div>

        <!-- Performance Details -->
        <div id="ld-perf-details">
            <h2 style="font-size:18px;color:#0F172A;margin:32px 0 16px;">Performance Details</h2>
            <div id="ld-perf-issues"></div>
            <div class="ld-perf-section" id="ld-perf-php"></div>
            <div class="ld-perf-section" id="ld-perf-autoload"></div>
            <div class="ld-perf-section" id="ld-perf-revisions"></div>
            <div class="ld-perf-section" id="ld-perf-database"></div>
        </div>

        <!-- Cron Jobs -->
        <div id="ld-cron-section" style="margin-top:32px;">
            <h2>Scheduled Tasks (WP-Cron)</h2>
            <div id="ld-cron-list"></div>
        </div>

        <!-- 1-Click Optimise -->
        <div id="ld-optimize-section" class="ld-perf-section" style="margin-top:32px;">
            <h3>1-Click Optimise</h3>
            <div id="ld-optimize-preview"></div>
            <button id="ld-optimize" class="button button-primary button-hero" disabled>
                <span class="dashicons dashicons-admin-tools" style="margin-top: 4px;"></span>
                Optimise Now
            </button>
            <div id="ld-optimize-results"></div>
        </div>

        <!-- Before/After -->
        <div id="ld-before-after" style="display:none;" class="ld-perf-section"></div>

        <!-- Re-Run Audit -->
        <div style="text-align:center;margin:40px 0 20px;">
            <button id="ld-rerun-audit" class="button button-primary button-hero">
                <span class="dashicons dashicons-update" style="margin-top: 4px;"></span>
                Run Audit Again
            </button>
        </div>

    </div>

</div>
