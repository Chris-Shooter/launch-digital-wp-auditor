<?php
/**
 * Plugin Name: Launch Digital WP Auditor
 * Plugin URI: https://launchdigital.co.za
 * Description: Comprehensive WordPress plugin audit tool. Scans all installed plugins for performance impact, usage, redundancy, and security risks. Generates branded audit reports.
 * Version: 1.0.0
 * Author: Launch Digital
 * Author URI: https://launchdigital.co.za
 * License: GPL v2 or later
 * Text Domain: ld-wp-auditor
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LD_AUDITOR_VERSION', '1.0.0');
define('LD_AUDITOR_PATH', plugin_dir_path(__FILE__));
define('LD_AUDITOR_URL', plugin_dir_url(__FILE__));

class LD_WP_Auditor {

    private static $instance = null;
    private $scan_results = [];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_ld_auditor_run_scan', [$this, 'ajax_run_scan']);
        add_action('wp_ajax_ld_auditor_export_report', [$this, 'ajax_export_report']);
        add_action('wp_ajax_ld_auditor_run_perf_scan', [$this, 'ajax_run_perf_scan']);
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_management_page(
            'Launch Digital WP Auditor',
            'WP Auditor',
            'manage_options',
            'ld-wp-auditor',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Enqueue admin CSS and JS
     */
    public function enqueue_admin_assets($hook) {
        if ('tools_page_ld-wp-auditor' !== $hook) {
            return;
        }
        wp_enqueue_style('ld-auditor-admin', LD_AUDITOR_URL . 'assets/admin.css', [], LD_AUDITOR_VERSION);
        wp_enqueue_script('ld-auditor-admin', LD_AUDITOR_URL . 'assets/admin.js', ['jquery'], LD_AUDITOR_VERSION, true);
        wp_localize_script('ld-auditor-admin', 'ldAuditor', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ld_auditor_nonce'),
        ]);
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        include LD_AUDITOR_PATH . 'templates/admin-page.php';
    }

    /**
     * AJAX: Run full scan
     */
    public function ajax_run_scan() {
        check_ajax_referer('ld_auditor_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $results = $this->run_full_scan();
        
        // Cache results for export
        set_transient('ld_auditor_last_scan', $results, HOUR_IN_SECONDS);

        wp_send_json_success($results);
    }

    /**
     * AJAX: Export report as HTML
     */
    public function ajax_export_report() {
        check_ajax_referer('ld_auditor_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $results = get_transient('ld_auditor_last_scan');
        if (!$results) {
            $results = $this->run_full_scan();
        }

        $html = $this->generate_report_html($results);
        wp_send_json_success(['html' => $html]);
    }

    /**
     * Run the full plugin audit scan
     */
    public function run_full_scan() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        $site_url = get_site_url();
        $wp_version = get_bloginfo('version');

        $results = [
            'site_url'       => $site_url,
            'site_name'      => get_bloginfo('name'),
            'wp_version'     => $wp_version,
            'php_version'    => phpversion(),
            'scan_date'      => current_time('Y-m-d H:i:s'),
            'total_plugins'  => count($all_plugins),
            'active_count'   => count($active_plugins),
            'inactive_count' => count($all_plugins) - count($active_plugins),
            'plugins'        => [],
            'summary'        => [],
            'cron_jobs'      => $this->scan_cron_jobs(),
            'enqueued_assets'=> $this->scan_enqueued_assets(),
        ];

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $is_active = in_array($plugin_file, $active_plugins);
            $slug = $this->get_plugin_slug($plugin_file);

            $plugin_info = [
                'file'            => $plugin_file,
                'name'            => $plugin_data['Name'],
                'version'         => $plugin_data['Version'],
                'author'          => $plugin_data['AuthorName'] ?? $plugin_data['Author'] ?? 'Unknown',
                'description'     => $plugin_data['Description'],
                'is_active'       => $is_active,
                'slug'            => $slug,
                'wp_org_data'     => $this->get_wp_org_data($slug),
                'usage_detected'  => $is_active ? $this->detect_plugin_usage($slug, $plugin_file) : false,
                'category'        => $this->categorize_plugin($slug, $plugin_data['Name'], $plugin_data['Description']),
                'file_size'       => $this->get_plugin_size($plugin_file),
                'db_options_count'=> $this->count_plugin_options($slug),
                'cron_jobs'       => $this->get_plugin_cron_jobs($slug),
                'assets'          => $this->get_plugin_assets($plugin_file),
                'issues'          => [],
                'recommendation'  => '',
                'risk_level'      => 'low', // low, medium, high, critical
            ];

            // Analyze and generate recommendations
            $plugin_info = $this->analyze_plugin($plugin_info, $wp_version);

            $results['plugins'][] = $plugin_info;
        }

        // Check for redundant plugins
        $results['redundancies'] = $this->detect_redundancies($results['plugins']);

        // Generate summary
        $results['summary'] = $this->generate_summary($results);

        // Sort plugins: critical issues first, then high, medium, low
        $risk_order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($results['plugins'], function($a, $b) use ($risk_order) {
            return ($risk_order[$a['risk_level']] ?? 4) - ($risk_order[$b['risk_level']] ?? 4);
        });

        return $results;
    }

    /**
     * Get plugin slug from file path
     */
    private function get_plugin_slug($plugin_file) {
        $parts = explode('/', $plugin_file);
        return $parts[0];
    }

    /**
     * Fetch data from WordPress.org Plugin API
     */
    private function get_wp_org_data($slug) {
        $cache_key = 'ld_auditor_wporg_' . $slug;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get(
            "https://api.wordpress.org/plugins/info/1.0/{$slug}.json",
            ['timeout' => 5]
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Not on WP.org (premium or custom plugin)
            $data = ['not_on_wporg' => true];
            set_transient($cache_key, $data, 12 * HOUR_IN_SECONDS);
            return $data;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!$body || isset($body['error'])) {
            $data = ['not_on_wporg' => true];
            set_transient($cache_key, $data, 12 * HOUR_IN_SECONDS);
            return $data;
        }

        $data = [
            'not_on_wporg'    => false,
            'last_updated'    => $body['last_updated'] ?? null,
            'tested'          => $body['tested'] ?? null,
            'active_installs' => $body['active_installs'] ?? null,
            'rating'          => $body['rating'] ?? null,
            'num_ratings'     => $body['num_ratings'] ?? null,
            'requires'        => $body['requires'] ?? null,
            'requires_php'    => $body['requires_php'] ?? null,
            'support_threads' => $body['support_threads'] ?? null,
            'support_threads_resolved' => $body['support_threads_resolved'] ?? null,
        ];

        set_transient($cache_key, $data, 12 * HOUR_IN_SECONDS);
        return $data;
    }

    /**
     * Detect if a plugin is actually being used in content
     */
    private function detect_plugin_usage($slug, $plugin_file) {
        global $wpdb;

        $usage = [
            'shortcodes_found'  => false,
            'widgets_active'    => false,
            'blocks_found'      => false,
            'shortcode_details' => [],
        ];

        // Known shortcode mappings
        $shortcode_map = $this->get_shortcode_map();

        if (isset($shortcode_map[$slug])) {
            foreach ($shortcode_map[$slug] as $shortcode) {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} 
                     WHERE post_status = 'publish' 
                     AND (post_content LIKE %s OR post_content LIKE %s)",
                    '%[' . $shortcode . '%',
                    '%[' . $shortcode . ' %'
                ));
                if ($count > 0) {
                    $usage['shortcodes_found'] = true;
                    $usage['shortcode_details'][] = [
                        'shortcode' => $shortcode,
                        'count'     => (int) $count,
                    ];
                }
            }
        }

        // Generic shortcode detection: scan registered shortcodes
        global $shortcode_tags;
        if (!empty($shortcode_tags)) {
            foreach ($shortcode_tags as $tag => $callback) {
                if ($this->callback_belongs_to_plugin($callback, $plugin_file)) {
                    $count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->posts} 
                         WHERE post_status = 'publish' 
                         AND post_content LIKE %s",
                        '%[' . $tag . '%'
                    ));
                    if ($count > 0 && !in_array($tag, array_column($usage['shortcode_details'], 'shortcode'))) {
                        $usage['shortcodes_found'] = true;
                        $usage['shortcode_details'][] = [
                            'shortcode' => $tag,
                            'count'     => (int) $count,
                        ];
                    }
                }
            }
        }

        // Check for Gutenberg blocks
        $block_prefix = str_replace('-', '', $slug);
        $block_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_status = 'publish' 
             AND post_content LIKE %s",
            '%<!-- wp:' . $slug . '%'
        ));
        if ($block_count > 0) {
            $usage['blocks_found'] = true;
        }

        // Check active widgets
        $active_widgets = get_option('sidebars_widgets', []);
        $widget_prefix = str_replace('-', '_', $slug);
        foreach ($active_widgets as $sidebar => $widgets) {
            if ($sidebar === 'wp_inactive_widgets' || !is_array($widgets)) continue;
            foreach ($widgets as $widget_id) {
                if (stripos($widget_id, $widget_prefix) !== false || stripos($widget_id, $slug) !== false) {
                    $usage['widgets_active'] = true;
                    break 2;
                }
            }
        }

        return $usage;
    }

    /**
     * Check if a shortcode callback belongs to a plugin
     */
    private function callback_belongs_to_plugin($callback, $plugin_file) {
        $plugin_dir = dirname($plugin_file);
        if ($plugin_dir === '.') return false;

        if (is_string($callback)) {
            return false; // Can't easily trace function to plugin
        }

        if (is_array($callback) && is_object($callback[0])) {
            $class_file = (new \ReflectionClass($callback[0]))->getFileName();
            if ($class_file && strpos($class_file, $plugin_dir) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map of common plugin slugs to their shortcodes
     */
    private function get_shortcode_map() {
        return [
            'contact-form-7'          => ['contact-form-7', 'contact-form'],
            'wpforms-lite'            => ['wpforms'],
            'wpforms'                 => ['wpforms'],
            'ninja-forms'             => ['ninja_form', 'ninja_forms'],
            'gravityforms'            => ['gravityform', 'gravityforms'],
            'formidable'              => ['formidable'],
            'woocommerce'             => ['woocommerce_cart', 'woocommerce_checkout', 'woocommerce_my_account', 'products', 'product', 'add_to_cart', 'woocommerce_order_tracking'],
            'elementor'               => ['elementor-template'],
            'js_composer'             => ['vc_row', 'vc_column', 'vc_column_text'],
            'revslider'               => ['rev_slider'],
            'tablepress'              => ['table', 'tablepress'],
            'shortcodes-ultimate'     => ['su_button', 'su_tabs', 'su_accordion', 'su_spoiler'],
            'easy-table-of-contents'  => ['ez-toc'],
            'the-events-calendar'     => ['tribe_events'],
            'mailchimp-for-wp'        => ['mc4wp_form'],
            'wordpress-seo'           => ['wpseo_breadcrumb', 'wpseo_sitemap'],
            'all-in-one-seo-pack'     => ['aioseo_breadcrumbs'],
        ];
    }

    /**
     * Categorize plugin by type
     */
    private function categorize_plugin($slug, $name, $description) {
        // Explicit slug mapping for popular plugins that may not match keywords
        $slug_map = [
            'seo-by-rank-math'        => 'seo',
            'seo-by-rank-math-pro'    => 'seo',
            'rank-math-pro'           => 'seo',
            'wordpress-seo'           => 'seo',
            'wordpress-seo-premium'   => 'seo',
            'all-in-one-seo-pack'     => 'seo',
            'the-seo-framework'       => 'seo',
            'redirection'             => 'seo',
            'wordfence'               => 'security',
            'better-wp-security'      => 'security',
            'limit-login-attempts-reloaded' => 'security',
            'really-simple-ssl'       => 'security',
            'updraftplus'             => 'backup',
            'wp-rocket'               => 'caching',
            'w3-total-cache'          => 'caching',
            'wp-super-cache'          => 'caching',
            'litespeed-cache'         => 'caching',
            'wp-fastest-cache'        => 'caching',
            'google-site-kit'         => 'analytics',
            'google-analytics-for-wordpress' => 'analytics',
            'wp-mail-smtp'            => 'email',
            'fluent-smtp'             => 'email',
            'post-smtp'               => 'email',
            'classic-editor'          => 'admin',
            'duplicate-post'          => 'admin',
            'wp-clone-by-wp-academy'  => 'admin',
            'regenerate-thumbnails'   => 'admin',
            'user-role-editor'        => 'admin',
            'members'                 => 'admin',
            'wp-crontrol'             => 'admin',
            'query-monitor'           => 'admin',
            'code-snippets'           => 'functionality',
            'advanced-custom-fields'  => 'functionality',
            'acf-pro'                 => 'functionality',
        ];

        if (isset($slug_map[$slug])) {
            return $slug_map[$slug];
        }

        $text = strtolower($slug . ' ' . $name . ' ' . $description);
        
        $categories = [
            'seo' => ['seo', 'search engine', 'sitemap', 'schema', 'meta tag', 'yoast', 'rank math', 'rankmath', 'all in one seo'],
            'security' => ['security', 'firewall', 'malware', 'wordfence', 'sucuri', 'ithemes security', 'login protect', 'captcha', 'recaptcha', 'two factor', '2fa', 'ssl'],
            'caching' => ['cache', 'caching', 'speed', 'performance', 'optimize', 'minif', 'lazy load', 'autoptimize', 'wp rocket', 'litespeed', 'w3 total'],
            'backup' => ['backup', 'migrate', 'migration', 'updraft', 'duplicator', 'all-in-one wp migration'],
            'forms' => ['form', 'contact', 'wpforms', 'gravity', 'ninja form', 'formidable', 'caldera'],
            'page_builder' => ['elementor', 'beaver builder', 'divi', 'visual composer', 'wpbakery', 'page builder', 'brizy', 'oxygen'],
            'ecommerce' => ['woocommerce', 'commerce', 'shop', 'cart', 'payment', 'stripe', 'paypal', 'checkout'],
            'analytics' => ['analytics', 'tracking', 'google analytics', 'pixel', 'tag manager', 'gtm', 'monsterinsights', 'site kit'],
            'media' => ['image', 'gallery', 'slider', 'video', 'smush', 'imagify', 'shortpixel', 'compress'],
            'social' => ['social', 'share', 'facebook', 'twitter', 'instagram', 'feed'],
            'email' => ['email', 'newsletter', 'mailchimp', 'smtp', 'mail'],
            'admin' => ['admin', 'dashboard', 'white label', 'maintenance', 'coming soon', 'under construction', 'classic editor', 'duplicate post', 'clone'],
            'functionality' => ['custom post', 'custom field', 'acf', 'advanced custom', 'cpt', 'meta box', 'pods', 'snippet'],
        ];

        foreach ($categories as $cat => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    return $cat;
                }
            }
        }

        return 'other';
    }

    /**
     * Get total file size of a plugin directory
     */
    private function get_plugin_size($plugin_file) {
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin_file);
        
        if (dirname($plugin_file) === '.' || !is_dir($plugin_dir)) {
            $single_file = WP_PLUGIN_DIR . '/' . $plugin_file;
            return file_exists($single_file) ? filesize($single_file) : 0;
        }

        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    /**
     * Count wp_options entries belonging to a plugin
     */
    private function count_plugin_options($slug) {
        global $wpdb;
        $clean_slug = str_replace('-', '_', $slug);
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s 
             OR option_name LIKE %s",
            '%' . $wpdb->esc_like($slug) . '%',
            '%' . $wpdb->esc_like($clean_slug) . '%',
            '%' . $wpdb->esc_like(str_replace('-', '', $slug)) . '%'
        ));

        return (int) $count;
    }

    /**
     * Scan all WordPress cron jobs
     */
    private function scan_cron_jobs() {
        $crons = _get_cron_array();
        $jobs = [];

        if (!is_array($crons)) return $jobs;

        foreach ($crons as $timestamp => $hooks) {
            foreach ($hooks as $hook => $events) {
                foreach ($events as $key => $event) {
                    $jobs[] = [
                        'hook'       => $hook,
                        'next_run'   => date('Y-m-d H:i:s', $timestamp),
                        'schedule'   => $event['schedule'] ?? 'once',
                        'interval'   => isset($event['interval']) ? $event['interval'] : null,
                    ];
                }
            }
        }

        return $jobs;
    }

    /**
     * Get cron jobs likely belonging to a plugin
     */
    private function get_plugin_cron_jobs($slug) {
        $crons = _get_cron_array();
        $plugin_crons = [];
        $clean_slug = str_replace('-', '_', $slug);

        if (!is_array($crons)) return $plugin_crons;

        foreach ($crons as $timestamp => $hooks) {
            foreach ($hooks as $hook => $events) {
                if (stripos($hook, $slug) !== false || stripos($hook, $clean_slug) !== false) {
                    foreach ($events as $event) {
                        $plugin_crons[] = [
                            'hook'     => $hook,
                            'schedule' => $event['schedule'] ?? 'once',
                            'interval' => $event['interval'] ?? null,
                        ];
                    }
                }
            }
        }

        return $plugin_crons;
    }

    /**
     * Scan enqueued scripts and styles (captures what's registered)
     */
    private function scan_enqueued_assets() {
        global $wp_scripts, $wp_styles;
        
        $assets = ['scripts' => [], 'styles' => []];

        if ($wp_scripts) {
            foreach ($wp_scripts->registered as $handle => $script) {
                if ($script->src && strpos($script->src, 'wp-content/plugins/') !== false) {
                    $assets['scripts'][] = [
                        'handle' => $handle,
                        'src'    => $script->src,
                        'plugin' => $this->extract_plugin_from_path($script->src),
                    ];
                }
            }
        }

        if ($wp_styles) {
            foreach ($wp_styles->registered as $handle => $style) {
                if ($style->src && strpos($style->src, 'wp-content/plugins/') !== false) {
                    $assets['styles'][] = [
                        'handle' => $handle,
                        'src'    => $style->src,
                        'plugin' => $this->extract_plugin_from_path($style->src),
                    ];
                }
            }
        }

        return $assets;
    }

    /**
     * Get assets belonging to a specific plugin
     */
    private function get_plugin_assets($plugin_file) {
        $plugin_dir = dirname($plugin_file);
        if ($plugin_dir === '.') return ['scripts' => 0, 'styles' => 0];

        global $wp_scripts, $wp_styles;
        $script_count = 0;
        $style_count = 0;

        if ($wp_scripts) {
            foreach ($wp_scripts->registered as $script) {
                if ($script->src && strpos($script->src, 'plugins/' . $plugin_dir . '/') !== false) {
                    $script_count++;
                }
            }
        }

        if ($wp_styles) {
            foreach ($wp_styles->registered as $style) {
                if ($style->src && strpos($style->src, 'plugins/' . $plugin_dir . '/') !== false) {
                    $style_count++;
                }
            }
        }

        return ['scripts' => $script_count, 'styles' => $style_count];
    }

    /**
     * Extract plugin directory name from asset path
     */
    private function extract_plugin_from_path($path) {
        if (preg_match('/wp-content\/plugins\/([^\/]+)/', $path, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }

    /**
     * Analyze a plugin and generate recommendations
     */
    private function analyze_plugin($plugin, $wp_version) {
        $issues = [];
        $risk_level = 'low';

        // Issue: Inactive plugin
        if (!$plugin['is_active']) {
            $issues[] = [
                'type'     => 'inactive',
                'severity' => 'medium',
                'message'  => 'Plugin is installed but not active. Inactive plugins are a security risk and should be removed.',
            ];
            $risk_level = 'medium';
            $plugin['recommendation'] = 'DELETE — Inactive plugins serve no purpose and create security vulnerabilities. Remove immediately.';
            $plugin['issues'] = $issues;
            $plugin['risk_level'] = $risk_level;
            return $plugin;
        }

        $wp_org = $plugin['wp_org_data'];

        // Issue: Not updated recently
        if (!empty($wp_org['last_updated']) && !($wp_org['not_on_wporg'] ?? false)) {
            $last_updated = strtotime($wp_org['last_updated']);
            $days_since = (time() - $last_updated) / DAY_IN_SECONDS;

            if ($days_since > 730) { // 2+ years
                $issues[] = [
                    'type'     => 'abandoned',
                    'severity' => 'critical',
                    'message'  => 'Plugin has not been updated in over 2 years. Likely abandoned and a significant security risk.',
                ];
                $risk_level = 'critical';
            } elseif ($days_since > 365) {
                $issues[] = [
                    'type'     => 'stale',
                    'severity' => 'high',
                    'message'  => 'Plugin has not been updated in over 1 year.',
                ];
                $risk_level = $this->escalate_risk($risk_level, 'high');
            }
        }

        // Issue: Not tested with current WP version
        if (!empty($wp_org['tested']) && !($wp_org['not_on_wporg'] ?? false)) {
            if (version_compare($wp_org['tested'], $wp_version, '<')) {
                $issues[] = [
                    'type'     => 'compatibility',
                    'severity' => 'medium',
                    'message'  => "Plugin only tested up to WordPress {$wp_org['tested']} (you're running {$wp_version}).",
                ];
                $risk_level = $this->escalate_risk($risk_level, 'medium');
            }
        }

        // Issue: Low rating
        if (!empty($wp_org['rating']) && $wp_org['rating'] < 60 && ($wp_org['num_ratings'] ?? 0) > 10) {
            $issues[] = [
                'type'     => 'poor_quality',
                'severity' => 'medium',
                'message'  => "Plugin has a poor rating ({$wp_org['rating']}%) from {$wp_org['num_ratings']} reviews.",
            ];
            $risk_level = $this->escalate_risk($risk_level, 'medium');
        }

        // Issue: Large file size (over 10MB)
        if ($plugin['file_size'] > 10 * 1024 * 1024) {
            $issues[] = [
                'type'     => 'bloated',
                'severity' => 'low',
                'message'  => 'Plugin is over 10MB in size (' . $this->format_bytes($plugin['file_size']) . ').',
            ];
        }

        // Issue: Many database options (over 50)
        if ($plugin['db_options_count'] > 50) {
            $issues[] = [
                'type'     => 'db_heavy',
                'severity' => 'medium',
                'message'  => "Plugin stores {$plugin['db_options_count']} entries in the options table, which can slow down database queries.",
            ];
            $risk_level = $this->escalate_risk($risk_level, 'medium');
        }

        // Issue: Active but not detected in use
        if ($plugin['is_active'] && $plugin['usage_detected']) {
            $usage = $plugin['usage_detected'];
            if (!$usage['shortcodes_found'] && !$usage['widgets_active'] && !$usage['blocks_found']) {
                // Background plugins operate without visible content output — don't flag these
                $background_types = ['seo', 'security', 'caching', 'backup', 'analytics', 'email', 'admin', 'functionality', 'media', 'ecommerce'];
                if (!in_array($plugin['category'], $background_types)) {
                    $issues[] = [
                        'type'     => 'unused',
                        'severity' => 'medium',
                        'message'  => 'Plugin is active but no shortcodes, widgets, or blocks were detected in published content. Worth checking if it\'s still needed.',
                    ];
                    $risk_level = $this->escalate_risk($risk_level, 'medium');
                }
            }
        }

        // Issue: Aggressive cron jobs (more than 3 or interval under 5 min)
        if (count($plugin['cron_jobs']) > 3) {
            $issues[] = [
                'type'     => 'cron_heavy',
                'severity' => 'medium',
                'message'  => "Plugin has {$plugin['cron_jobs'][0]['hook']} and " . (count($plugin['cron_jobs']) - 1) . " other scheduled tasks.",
            ];
            $risk_level = $this->escalate_risk($risk_level, 'medium');
        }
        foreach ($plugin['cron_jobs'] as $cron) {
            if (!empty($cron['interval']) && $cron['interval'] < 300) {
                $issues[] = [
                    'type'     => 'cron_aggressive',
                    'severity' => 'high',
                    'message'  => "Cron job '{$cron['hook']}' runs every " . round($cron['interval'] / 60) . " minutes — very aggressive.",
                ];
                $risk_level = $this->escalate_risk($risk_level, 'high');
                break;
            }
        }

        // Generate recommendation
        if (empty($issues)) {
            $plugin['recommendation'] = 'KEEP — No issues detected. Plugin appears healthy and well-maintained.';
        } else {
            $max_severity = 'low';
            foreach ($issues as $issue) {
                $max_severity = $this->escalate_risk($max_severity, $issue['severity']);
            }

            switch ($max_severity) {
                case 'critical':
                    $plugin['recommendation'] = 'REPLACE — Critical issues found. Find an alternative plugin or remove if not essential.';
                    break;
                case 'high':
                    $plugin['recommendation'] = 'REVIEW — Significant issues detected. Evaluate if this plugin is necessary and consider alternatives.';
                    break;
                case 'medium':
                    $plugin['recommendation'] = 'MONITOR — Some concerns flagged. Keep an eye on updates and performance.';
                    break;
                default:
                    $plugin['recommendation'] = 'KEEP — Minor issues only. Plugin is generally fine.';
            }
        }

        $plugin['issues'] = $issues;
        $plugin['risk_level'] = $risk_level;
        return $plugin;
    }

    /**
     * Escalate risk level
     */
    private function escalate_risk($current, $new) {
        $levels = ['low' => 0, 'medium' => 1, 'high' => 2, 'critical' => 3];
        if (($levels[$new] ?? 0) > ($levels[$current] ?? 0)) {
            return $new;
        }
        return $current;
    }

    /**
     * Detect redundant plugins (multiple plugins doing the same job)
     */
    private function detect_redundancies($plugins) {
        $category_groups = [];
        foreach ($plugins as $plugin) {
            if (!$plugin['is_active']) continue;
            $cat = $plugin['category'];
            if (!isset($category_groups[$cat])) {
                $category_groups[$cat] = [];
            }
            $category_groups[$cat][] = $plugin['name'];
        }

        $redundancies = [];
        $singular_categories = ['seo', 'caching', 'security', 'backup'];
        
        foreach ($singular_categories as $cat) {
            if (isset($category_groups[$cat]) && count($category_groups[$cat]) > 1) {
                $redundancies[] = [
                    'category' => $cat,
                    'plugins'  => $category_groups[$cat],
                    'message'  => 'Multiple ' . strtoupper($cat) . ' plugins active: ' . implode(', ', $category_groups[$cat]) . '. These often conflict — pick one and remove the rest.',
                ];
            }
        }

        return $redundancies;
    }

    /**
     * Generate summary stats
     */
    private function generate_summary($results) {
        $critical = $high = $medium = $can_delete = 0;
        
        foreach ($results['plugins'] as $p) {
            switch ($p['risk_level']) {
                case 'critical': $critical++; break;
                case 'high': $high++; break;
                case 'medium': $medium++; break;
            }
            if (strpos($p['recommendation'], 'DELETE') === 0 || strpos($p['recommendation'], 'REPLACE') === 0) {
                $can_delete++;
            }
        }

        return [
            'critical_issues' => $critical,
            'high_issues'     => $high,
            'medium_issues'   => $medium,
            'can_delete'      => $can_delete,
            'inactive_count'  => $results['inactive_count'],
            'redundancies'    => count($results['redundancies']),
            'total_cron_jobs' => count($results['cron_jobs']),
            'health_score'    => $this->calculate_health_score($results),
        ];
    }

    /**
     * Calculate overall health score (0-100)
     * 
     * Scoring approach: start at 100, deduct proportionally.
     * A site with a few minor issues should still score 70+.
     * Only genuinely problematic sites should score below 50.
     */
    private function calculate_health_score($results) {
        $score = 100;
        $total = max(1, $results['total_plugins']);

        // Per-plugin issue penalties (scaled down)
        foreach ($results['plugins'] as $p) {
            switch ($p['risk_level']) {
                case 'critical': $score -= 8; break;
                case 'high':     $score -= 4; break;
                case 'medium':   $score -= 1; break;
            }
        }

        // Inactive plugins: mild penalty, capped
        $inactive_penalty = min(15, $results['inactive_count'] * 2);
        $score -= $inactive_penalty;

        // Redundancies: these are genuinely bad
        $score -= count($results['redundancies']) * 6;

        // Bonus: if most plugins are healthy, don't punish too hard
        $healthy_count = 0;
        foreach ($results['plugins'] as $p) {
            if ($p['risk_level'] === 'low') $healthy_count++;
        }
        $healthy_ratio = $healthy_count / $total;
        if ($healthy_ratio > 0.7) {
            $score = max($score, 65); // Floor at 65 if 70%+ plugins are fine
        }

        return max(0, min(100, round($score)));
    }

    /**
     * Format bytes to human readable
     */
    private function format_bytes($bytes, $precision = 1) {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Generate branded HTML report
     */
    private function generate_report_html($results) {
        ob_start();
        include LD_AUDITOR_PATH . 'templates/report.php';
        return ob_get_clean();
    }

    // =========================================================================
    // BACKEND PERFORMANCE PROFILER
    // =========================================================================

    /**
     * AJAX: Run performance scan
     */
    public function ajax_run_perf_scan() {
        check_ajax_referer('ld_auditor_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $results = [
            'scan_date'       => current_time('Y-m-d H:i:s'),
            'php_config'      => $this->check_php_config(),
            'wp_options'      => $this->check_wp_options(),
            'autoloaded'      => $this->check_autoloaded_options(),
            'post_revisions'  => $this->check_post_revisions(),
            'transients'      => $this->check_transients(),
            'database_tables' => $this->check_database_tables(),
            'object_cache'    => $this->check_object_cache(),
            'heartbeat'       => $this->check_heartbeat(),
            'cron_load'       => $this->check_cron_load(),
            'perf_score'      => 0,
            'perf_issues'     => [],
        ];

        // Analyze and score
        $analysis = $this->analyze_performance($results);
        $results['perf_score'] = $analysis['score'];
        $results['perf_issues'] = $analysis['issues'];

        set_transient('ld_auditor_last_perf_scan', $results, HOUR_IN_SECONDS);
        wp_send_json_success($results);
    }

    /**
     * Check PHP configuration
     */
    private function check_php_config() {
        $opcache_enabled = function_exists('opcache_get_status') && @opcache_get_status() !== false;
        
        return [
            'version'            => phpversion(),
            'memory_limit'       => ini_get('memory_limit'),
            'memory_limit_bytes' => $this->return_bytes(ini_get('memory_limit')),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_vars'     => ini_get('max_input_vars'),
            'post_max_size'      => ini_get('post_max_size'),
            'upload_max_size'    => ini_get('upload_max_filesize'),
            'opcache_enabled'    => $opcache_enabled,
            'sapi'               => php_sapi_name(),
        ];
    }

    /**
     * Check wp_options table health
     */
    private function check_wp_options() {
        global $wpdb;

        $total_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}");
        $autoloaded_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload = 'yes'");
        $autoloaded_size = (int) $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'");
        $total_size = (int) $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options}");

        return [
            'total_count'      => $total_count,
            'autoloaded_count' => $autoloaded_count,
            'autoloaded_size'  => $autoloaded_size,
            'total_size'       => $total_size,
        ];
    }

    /**
     * Get the biggest autoloaded options (the usual suspects)
     */
    private function check_autoloaded_options() {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT option_name, LENGTH(option_value) as size 
             FROM {$wpdb->options} 
             WHERE autoload = 'yes' 
             ORDER BY LENGTH(option_value) DESC 
             LIMIT 25",
            ARRAY_A
        );

        return array_map(function($row) {
            return [
                'name' => $row['option_name'],
                'size' => (int) $row['size'],
            ];
        }, $results ?: []);
    }

    /**
     * Check post revisions
     */
    private function check_post_revisions() {
        global $wpdb;

        $total_revisions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
        );

        $total_posts = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish'"
        );

        $total_autodrafts = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"
        );

        $total_trashed = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'"
        );

        // Posts with the most revisions
        $worst_offenders = $wpdb->get_results(
            "SELECT p.post_title, p.ID, COUNT(r.ID) as revision_count 
             FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->posts} r ON r.post_parent = p.ID AND r.post_type = 'revision'
             WHERE p.post_type IN ('post', 'page')
             GROUP BY p.ID 
             ORDER BY revision_count DESC 
             LIMIT 10",
            ARRAY_A
        );

        // Revision limit setting
        $revision_limit = defined('WP_POST_REVISIONS') ? WP_POST_REVISIONS : 'Unlimited (default)';

        return [
            'total_revisions'  => $total_revisions,
            'total_posts'      => $total_posts,
            'total_autodrafts' => $total_autodrafts,
            'total_trashed'    => $total_trashed,
            'revision_limit'   => $revision_limit,
            'worst_offenders'  => $worst_offenders ?: [],
        ];
    }

    /**
     * Check transients
     */
    private function check_transients() {
        global $wpdb;

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '%_transient_%'"
        );

        $expired = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );

        $transient_size = (int) $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} 
             WHERE option_name LIKE '%_transient_%'"
        );

        return [
            'total'    => $total,
            'expired'  => $expired,
            'size'     => $transient_size,
        ];
    }

    /**
     * Check database table sizes
     */
    private function check_database_tables() {
        global $wpdb;

        $tables = $wpdb->get_results(
            "SELECT table_name, 
                    ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb,
                    table_rows,
                    ROUND(data_free / 1024 / 1024, 2) as overhead_mb
             FROM information_schema.TABLES 
             WHERE table_schema = DATABASE()
             ORDER BY (data_length + index_length) DESC
             LIMIT 20",
            ARRAY_A
        );

        $total_size = 0;
        $total_overhead = 0;
        foreach ($tables as $t) {
            $total_size += (float) $t['size_mb'];
            $total_overhead += (float) $t['overhead_mb'];
        }

        return [
            'tables'         => $tables ?: [],
            'total_size_mb'  => round($total_size, 2),
            'total_overhead'=> round($total_overhead, 2),
        ];
    }

    /**
     * Check object cache status
     */
    private function check_object_cache() {
        $has_external = wp_using_ext_object_cache();
        
        $drop_in_exists = file_exists(WP_CONTENT_DIR . '/object-cache.php');
        
        // Try to detect which cache backend
        $backend = 'Database (default)';
        if ($has_external) {
            if (class_exists('Redis')) {
                $backend = 'Redis';
            } elseif (class_exists('Memcached')) {
                $backend = 'Memcached';
            } elseif (class_exists('Memcache')) {
                $backend = 'Memcache';
            } elseif ($drop_in_exists) {
                $backend = 'External (drop-in detected)';
            }
        }

        return [
            'external_cache' => $has_external,
            'backend'        => $backend,
            'drop_in'        => $drop_in_exists,
        ];
    }

    /**
     * Check Heartbeat API settings
     */
    private function check_heartbeat() {
        // Default heartbeat intervals
        $intervals = [
            'admin'    => 60,  // Default: every 60s on admin pages
            'post_edit'=> 15,  // Default: every 15s on post editor
            'frontend' => 60,  // Default: every 60s (if enabled)
        ];

        // Check if any plugin has modified heartbeat
        $heartbeat_setting = get_option('heartbeat_control_settings', null);
        
        // Check if heartbeat is likely disabled
        $heartbeat_disabled = false;
        if (defined('DISABLE_WP_HEARTBEAT') && DISABLE_WP_HEARTBEAT) {
            $heartbeat_disabled = true;
        }

        return [
            'disabled'   => $heartbeat_disabled,
            'intervals'  => $intervals,
            'settings'   => $heartbeat_setting,
        ];
    }

    /**
     * Check cron load
     */
    private function check_cron_load() {
        $crons = _get_cron_array();
        if (!is_array($crons)) {
            return ['total_events' => 0, 'overdue' => 0, 'frequent' => []];
        }

        $total = 0;
        $overdue = 0;
        $now = time();
        $frequent = [];

        foreach ($crons as $timestamp => $hooks) {
            foreach ($hooks as $hook => $events) {
                foreach ($events as $event) {
                    $total++;
                    if ($timestamp < $now) {
                        $overdue++;
                    }
                    // Track frequently recurring jobs
                    if (!empty($event['interval']) && $event['interval'] < 3600) {
                        $frequent[] = [
                            'hook'     => $hook,
                            'interval' => $event['interval'],
                        ];
                    }
                }
            }
        }

        // Check if WP-Cron is disabled (using system cron instead)
        $wp_cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;

        return [
            'total_events'    => $total,
            'overdue'         => $overdue,
            'frequent'        => $frequent,
            'wp_cron_disabled'=> $wp_cron_disabled,
        ];
    }

    /**
     * Analyze performance results and generate score + issues
     */
    private function analyze_performance($results) {
        $score = 100;
        $issues = [];

        // PHP Version
        if (version_compare($results['php_config']['version'], '8.0', '<')) {
            $issues[] = [
                'category' => 'PHP Configuration',
                'severity' => 'critical',
                'title'    => 'Outdated PHP Version',
                'message'  => "Running PHP {$results['php_config']['version']}. Upgrade to PHP 8.1+ for significant performance gains and security patches.",
                'fix'      => 'Contact your hosting provider to upgrade PHP version.',
            ];
            $score -= 15;
        } elseif (version_compare($results['php_config']['version'], '8.1', '<')) {
            $issues[] = [
                'category' => 'PHP Configuration',
                'severity' => 'medium',
                'title'    => 'PHP Version Could Be Newer',
                'message'  => "Running PHP {$results['php_config']['version']}. PHP 8.1+ offers better performance.",
                'fix'      => 'Consider upgrading to PHP 8.1 or 8.2.',
            ];
            $score -= 5;
        }

        // Memory limit
        $mem_bytes = $results['php_config']['memory_limit_bytes'];
        if ($mem_bytes > 0 && $mem_bytes < 256 * 1024 * 1024) {
            $issues[] = [
                'category' => 'PHP Configuration',
                'severity' => $mem_bytes < 128 * 1024 * 1024 ? 'high' : 'medium',
                'title'    => 'Low PHP Memory Limit',
                'message'  => "Memory limit is {$results['php_config']['memory_limit']}. WordPress admin needs at least 256MB for reliable operation, especially with page builders or WooCommerce.",
                'fix'      => "Add to wp-config.php: define('WP_MEMORY_LIMIT', '256M');",
            ];
            $score -= ($mem_bytes < 128 * 1024 * 1024) ? 10 : 5;
        }

        // OPcache
        if (!$results['php_config']['opcache_enabled']) {
            $issues[] = [
                'category' => 'PHP Configuration',
                'severity' => 'high',
                'title'    => 'OPcache Not Enabled',
                'message'  => 'PHP OPcache is not active. This means PHP recompiles every script on every request, which dramatically slows down the backend.',
                'fix'      => 'Enable OPcache in php.ini or contact your hosting provider.',
            ];
            $score -= 12;
        }

        // Autoloaded options size
        $autoload_mb = $results['wp_options']['autoloaded_size'] / 1024 / 1024;
        if ($autoload_mb > 2) {
            $issues[] = [
                'category' => 'Database',
                'severity' => 'critical',
                'title'    => 'Excessive Autoloaded Data',
                'message'  => 'Autoloaded options total ' . round($autoload_mb, 1) . 'MB. This data is loaded on EVERY page request. Should be under 1MB.',
                'fix'      => 'Audit the top autoloaded options below and set unnecessary ones to autoload=no, or remove orphaned plugin data.',
            ];
            $score -= 15;
        } elseif ($autoload_mb > 1) {
            $issues[] = [
                'category' => 'Database',
                'severity' => 'high',
                'title'    => 'High Autoloaded Data',
                'message'  => 'Autoloaded options total ' . round($autoload_mb, 1) . 'MB. Ideally should be under 800KB.',
                'fix'      => 'Review the largest autoloaded options and disable autoload for non-essential entries.',
            ];
            $score -= 8;
        }

        // Total options count
        if ($results['wp_options']['total_count'] > 1000) {
            $issues[] = [
                'category' => 'Database',
                'severity' => 'medium',
                'title'    => 'Large wp_options Table',
                'message'  => "wp_options has {$results['wp_options']['total_count']} rows. Many are likely from deleted plugins that didn't clean up after themselves.",
                'fix'      => 'Use a plugin like Advanced Database Cleaner to remove orphaned options.',
            ];
            $score -= 4;
        }

        // Post revisions
        if ($results['post_revisions']['total_revisions'] > 500) {
            $severity = $results['post_revisions']['total_revisions'] > 2000 ? 'high' : 'medium';
            $issues[] = [
                'category' => 'Database',
                'severity' => $severity,
                'title'    => 'Excessive Post Revisions',
                'message'  => number_format($results['post_revisions']['total_revisions']) . ' post revisions stored in the database. These bloat the wp_posts table and slow down queries.',
                'fix'      => "Add to wp-config.php: define('WP_POST_REVISIONS', 5); Then delete existing revisions with WP-Sweep or WP-Optimize.",
            ];
            $score -= ($severity === 'high') ? 8 : 4;
        }

        // Auto-drafts and trashed posts
        $waste = $results['post_revisions']['total_autodrafts'] + $results['post_revisions']['total_trashed'];
        if ($waste > 50) {
            $issues[] = [
                'category' => 'Database',
                'severity' => 'low',
                'title'    => 'Orphaned Drafts & Trash',
                'message'  => "{$results['post_revisions']['total_autodrafts']} auto-drafts and {$results['post_revisions']['total_trashed']} trashed posts can be cleaned up.",
                'fix'      => 'Empty trash and remove auto-drafts via Tools or a cleanup plugin.',
            ];
            $score -= 2;
        }

        // Expired transients
        if ($results['transients']['expired'] > 50) {
            $issues[] = [
                'category' => 'Database',
                'severity' => 'medium',
                'title'    => 'Expired Transients Not Cleaned',
                'message'  => "{$results['transients']['expired']} expired transients sitting in the database. These should have been auto-deleted.",
                'fix'      => 'Install Transient Cleaner or run: DELETE FROM wp_options WHERE option_name LIKE \'_transient_timeout_%\' AND option_value < UNIX_TIMESTAMP()',
            ];
            $score -= 4;
        }

        // Database overhead
        if ($results['database_tables']['total_overhead'] > 10) {
            $issues[] = [
                'category' => 'Database',
                'severity' => 'medium',
                'title'    => 'Database Tables Need Optimization',
                'message'  => round($results['database_tables']['total_overhead'], 1) . 'MB of overhead (wasted space) across database tables.',
                'fix'      => 'Run OPTIMIZE TABLE on affected tables, or use WP-Optimize plugin.',
            ];
            $score -= 4;
        }

        // Object cache
        if (!$results['object_cache']['external_cache']) {
            $issues[] = [
                'category' => 'Caching',
                'severity' => 'medium',
                'title'    => 'No Object Cache',
                'message'  => 'No persistent object cache (Redis/Memcached) detected. WordPress is storing all transients and cache in the database, causing extra queries on every request.',
                'fix'      => 'Install Redis or Memcached and a corresponding WordPress plugin (e.g., Redis Object Cache).',
            ];
            $score -= 6;
        }

        // Overdue cron events
        if ($results['cron_load']['overdue'] > 10) {
            $issues[] = [
                'category' => 'WP-Cron',
                'severity' => 'high',
                'title'    => 'Overdue Cron Events',
                'message'  => "{$results['cron_load']['overdue']} cron events are overdue. This usually means WP-Cron isn't firing reliably, causing a backlog that slams the server when it finally runs.",
                'fix'      => "Switch to a real system cron: define('DISABLE_WP_CRON', true); in wp-config.php, then set up a server cron to hit wp-cron.php every 5 minutes.",
            ];
            $score -= 8;
        }

        // Frequent cron jobs
        if (count($results['cron_load']['frequent']) > 5) {
            $issues[] = [
                'category' => 'WP-Cron',
                'severity' => 'medium',
                'title'    => 'Many Frequent Cron Jobs',
                'message'  => count($results['cron_load']['frequent']) . ' cron jobs run more than once per hour. This adds background load.',
                'fix'      => 'Review scheduled tasks and disable non-essential frequent jobs via WP Crontrol.',
            ];
            $score -= 4;
        }

        // Database total size
        if ($results['database_tables']['total_size_mb'] > 500) {
            $issues[] = [
                'category' => 'Database',
                'severity' => 'medium',
                'title'    => 'Large Database',
                'message'  => 'Total database size is ' . round($results['database_tables']['total_size_mb']) . 'MB. This may slow down backups and certain queries.',
                'fix'      => 'Review the largest tables below and clean up where possible.',
            ];
            $score -= 4;
        }

        return [
            'score'  => max(0, min(100, round($score))),
            'issues' => $issues,
        ];
    }

    /**
     * Convert shorthand memory notation to bytes
     */
    private function return_bytes($val) {
        $val = trim($val);
        if (empty($val)) return 0;
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;
        switch ($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }
}

// Initialize
LD_WP_Auditor::get_instance();
