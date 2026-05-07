<?php
/**
 * Plugin Name: Carbon Repro Instant Actions Widget
 * Plugin URI: https://carbonrepro.com/
 * Description: Displays the Carbon Repro instant actions widget on all front-end pages.
 * Version: 1.1.0
 * Author: Carbon Repro
 * License: GPL-2.0-or-later
 * Text Domain: carbon-repro-widget
 */

if (! defined('ABSPATH')) {
    exit;
}

final class Carbon_Repro_Instant_Actions_Widget
{
    private const OPTION_KEY = 'criaw_settings';
    private const STATS_OPTION_KEY = 'criaw_widget_stats';
    private const TABLE_NAME       = 'criaw_analytics';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu_pages']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_footer', [$this, 'render_widget']);
        add_action('wp_ajax_criaw_track_event', [$this, 'track_event']);
        add_action('wp_ajax_nopriv_criaw_track_event', [$this, 'track_event']);
        add_action('wp_ajax_criaw_get_dashboard_data', [$this, 'ajax_get_dashboard_data']);
        add_action('wp_ajax_criaw_get_journey', [$this, 'ajax_get_journey']);
        add_action('wp_ajax_criaw_clear_analytics', [$this, 'ajax_clear_analytics']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Safety check: Ensure table exists even if activation hook was bypassed
        if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'carbon-repro') !== false) {
            self::create_tables();
            $this->retroactively_classify_data();
        }
    }

    public function enqueue_assets(): void
    {
        if (is_admin()) {
            return;
        }

        $plugin_url  = plugin_dir_url(__FILE__);
        $plugin_path = plugin_dir_path(__FILE__);
        $css_path    = $plugin_path . 'assets/css/widget.css';
        $js_path     = $plugin_path . 'assets/js/widget.js';
        $version     = file_exists($css_path) ? (string) filemtime($css_path) : '1.1.0';

        wp_enqueue_style(
            'carbon-repro-widget',
            $plugin_url . 'assets/css/widget.css',
            [],
            $version
        );

        wp_enqueue_script(
            'carbon-repro-widget',
            $plugin_url . 'assets/js/widget.js',
            [],
            file_exists($js_path) ? (string) filemtime($js_path) : '1.1.0',
            true
        );

        wp_localize_script(
            'carbon-repro-widget',
            'carbonReproWidget',
            [
                'smsNumber' => $this->get_setting('phone_number', '7137056097'),
                'ajaxUrl'   => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('criaw_track_event'),
            ]
        );
    }

    public function render_widget(): void
    {
        if (is_admin()) {
            return;
        }

        $label_text   = $this->get_setting('label_text', 'Start Growing Today');
        $menu_title   = $this->get_setting('menu_title', 'Instant Actions');
        $form_title   = $this->get_setting('form_title', 'Get Your Strategy');
        $footer_brand = $this->get_setting('footer_brand', 'CarbonRepro');
        $form_id      = absint($this->get_setting('wpforms_id', '46362'));
        $font_url     = plugin_dir_url(__FILE__) . 'assets/fonts/Chillax-Variable.ttf?v=1.1';
        ?>
        <style>
            @font-face {
                font-family: 'Chillax';
                src: url('<?php echo esc_url($font_url); ?>') format('truetype');
                font-display: swap;
            }
        </style>
        <div id="watchWidget" data-watch-form-id="<?php echo esc_attr((string) $form_id); ?>">
            <div class="watch-launcher" id="watchLauncher">
                <div class="watch-label" id="watchLabel"><?php echo esc_html($label_text); ?></div>
                <button type="button" class="watch-button" id="watchBtn" aria-label="<?php esc_attr_e('Open quick actions', 'carbon-repro-widget'); ?>">
                    <svg class="watch-icon-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                </button>
            </div>

            <div id="watchOverlay"></div>

            <div class="watch-menu" id="watchMenu" aria-hidden="true">
                <div class="watch-menu-header">
                    <span class="watch-menu-title"><?php echo esc_html($menu_title); ?></span>
                    <button type="button" class="watch-menu-close-btn" id="watchMenuCloseBtn" aria-label="<?php esc_attr_e('Close menu', 'carbon-repro-widget'); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="18 15 12 9 6 15"></polyline>
                        </svg>
                    </button>
                </div>

                <div class="watch-menu-body">
                    <div class="watch-menu-action watch-action-1" data-watch-action="show-form" role="button" tabindex="0">
                        <div class="watch-action-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                <line x1="9" y1="10" x2="15" y2="10"></line>
                                <line x1="9" y1="14" x2="15" y2="14"></line>
                            </svg>
                        </div>
                        <div class="watch-action-text">
                            <div class="watch-action-title"><?php echo esc_html($form_title); ?></div>
                        </div>
                    </div>

                    <div class="watch-menu-action watch-action-2" data-watch-action="call" role="button" tabindex="0">
                        <div class="watch-action-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.13 11.91 19.79 19.79 0 0 1 1.06 3.24 2 2 0 0 1 3.03 1h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 21 16.92z"></path>
                            </svg>
                        </div>
                        <div class="watch-action-text">
                            <div class="watch-action-title"><?php esc_html_e('Call Us', 'carbon-repro-widget'); ?></div>
                        </div>
                    </div>

                    <div class="watch-menu-action watch-action-3" data-watch-action="text" role="button" tabindex="0">
                        <div class="watch-action-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect>
                                <line x1="7" y1="7" x2="17" y2="7"></line>
                                <line x1="7" y1="11" x2="17" y2="11"></line>
                                <line x1="7" y1="15" x2="17" y2="15"></line>
                            </svg>
                        </div>
                        <div class="watch-action-text">
                            <div class="watch-action-title"><?php esc_html_e('Text Us', 'carbon-repro-widget'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="watch-menu-footer">
                    <div class="watch-footer-content">
                        <img
                            src="https://carbonrepro.com/wp-content/uploads/2026/04/CR-Logo-03.png"
                            alt="CarbonRepro"
                            class="watch-footer-logo"
                            width="18"
                            height="18"
                            loading="lazy"
                            decoding="async"
                        />
                        <small><?php echo wp_kses(__('Powered by <a href="https://carbonrepro.com" target="_blank" class="watch-footer-link">carbonrepro</a>', 'carbon-repro-widget'), ['a' => ['href' => [], 'target' => [], 'class' => []]]); ?></small>
                    </div>
                </div>
            </div>

            <div class="watch-form-popup" id="watchFormPopup" aria-hidden="true">
                <div class="watch-form-header">
                    <button type="button" class="watch-form-back-btn" id="watchFormBackBtn" aria-label="<?php esc_attr_e('Back to menu', 'carbon-repro-widget'); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>
                    <span class="watch-form-title"><?php echo esc_html($form_title); ?></span>
                    <button type="button" class="watch-form-close-btn" id="watchFormCloseBtn" aria-label="<?php esc_attr_e('Close', 'carbon-repro-widget'); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="watch-form-body">
                    <?php $this->render_form_content($form_id); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function register_menu_pages(): void
    {
        add_menu_page(
            __('CarbonRepro', 'carbon-repro-widget'),
            __('CarbonRepro', 'carbon-repro-widget'),
            'manage_options',
            'carbon-repro-widget',
            [$this, 'render_dashboard_page'],
            'dashicons-chart-area',
            30
        );

        add_submenu_page(
            'carbon-repro-widget',
            __('Analytics Dashboard', 'carbon-repro-widget'),
            __('Dashboard', 'carbon-repro-widget'),
            'manage_options',
            'carbon-repro-widget',
            [$this, 'render_dashboard_page']
        );

        add_submenu_page(
            'carbon-repro-widget',
            __('Widget Settings', 'carbon-repro-widget'),
            __('Settings', 'carbon-repro-widget'),
            'manage_options',
            'carbon-repro-widget-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void
    {
        register_setting(
            'criaw_settings_group',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default'           => $this->get_default_settings(),
            ]
        );

        add_settings_section(
            'criaw_main_section',
            __('Widget Settings', 'carbon-repro-widget'),
            function (): void {
                echo '<p>' . esc_html__('These settings control the site-wide floating widget.', 'carbon-repro-widget') . '</p>';
            },
            'carbon-repro-widget-settings'
        );

        $fields = [
            'label_text'   => __('Launcher Label', 'carbon-repro-widget'),
            'menu_title'   => __('Menu Title', 'carbon-repro-widget'),
            'form_title'   => __('Form Title', 'carbon-repro-widget'),
            'footer_brand' => __('Footer Brand', 'carbon-repro-widget'),
            'phone_number' => __('Phone Number', 'carbon-repro-widget'),
            'wpforms_id'   => __('WPForms ID', 'carbon-repro-widget'),
        ];

        foreach ($fields as $field_key => $label) {
            add_settings_field(
                $field_key,
                $label,
                [$this, 'render_settings_field'],
                'carbon-repro-widget-settings',
                'criaw_main_section',
                ['key' => $field_key]
            );
        }
    }

    public function sanitize_settings(array $input): array
    {
        $defaults = $this->get_default_settings();

        return [
            'label_text'   => sanitize_text_field($input['label_text'] ?? $defaults['label_text']),
            'menu_title'   => sanitize_text_field($input['menu_title'] ?? $defaults['menu_title']),
            'form_title'   => sanitize_text_field($input['form_title'] ?? $defaults['form_title']),
            'footer_brand' => sanitize_text_field($input['footer_brand'] ?? $defaults['footer_brand']),
            'phone_number' => preg_replace('/[^0-9+]/', '', (string) ($input['phone_number'] ?? $defaults['phone_number'])),
            'wpforms_id'   => absint($input['wpforms_id'] ?? $defaults['wpforms_id']),
        ];
    }

    public function render_settings_field(array $args): void
    {
        $key      = $args['key'];
        $settings = wp_parse_args(get_option(self::OPTION_KEY, []), $this->get_default_settings());
        $value    = $settings[$key] ?? '';
        $type     = $key === 'wpforms_id' ? 'number' : 'text';
        $min      = $key === 'wpforms_id' ? ' min="1" step="1"' : '';

        printf(
            '<input type="%1$s" id="%2$s" name="%3$s[%2$s]" value="%4$s" class="regular-text"%5$s />',
            esc_attr($type),
            esc_attr($key),
            esc_attr(self::OPTION_KEY),
            esc_attr((string) $value),
            $min
        );
    }

    public function enqueue_admin_assets(string $hook): void
    {
        $allowed = ['toplevel_page_carbon-repro-widget', 'carbon-repro_page_carbon-repro-widget-settings'];
        if (! in_array($hook, $allowed, true)) {
            return;
        }

        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
        wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', [], null);
        
        wp_add_inline_style('wp-admin', "
            :root {
                --criaw-slate-50: #f8fafc;
                --criaw-slate-100: #f1f5f9;
                --criaw-slate-200: #e2e8f0;
                --criaw-slate-300: #cbd5e1;
                --criaw-slate-500: #64748b;
                --criaw-slate-700: #334155;
                --criaw-slate-800: #1e293b;
                --criaw-purple-500: #333333;
                --criaw-purple-600: #171717;
                --criaw-purple-700: #000000;
                --criaw-sky-500: #0ea5e9;
            }
            .criaw-premium-dash { margin-top: 24px; max-width: 1440px; font-family: 'Inter', -apple-system, sans-serif; color: var(--criaw-slate-800); }
            .criaw-dash-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; border-bottom: 1px solid var(--criaw-slate-200); padding-bottom: 24px; }
            .criaw-dash-header h1 { font-size: 28px; font-weight: 800; color: var(--criaw-slate-800); margin: 0; letter-spacing: -0.5px; }
            .criaw-dash-header p { color: var(--criaw-slate-500); margin: 5px 0 0; font-size: 14px; }

            .criaw-card { background: #fff; border-radius: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02), 0 10px 40px rgba(0,0,0,0.04); border: 1px solid var(--criaw-slate-200); margin-bottom: 24px; padding: 28px; position: relative; }
            
            .criaw-filter-bar { display: flex; flex-wrap: wrap; gap: 20px; align-items: center; background: #fff; padding: 20px 28px; border-radius: 20px; margin-bottom: 24px; border: 1px solid var(--criaw-slate-200); box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
            .criaw-filter-item { display: flex; flex-direction: column; gap: 8px; }
            .criaw-filter-item label { font-size: 11px; font-weight: 700; color: var(--criaw-slate-500); text-transform: uppercase; letter-spacing: 0.05em; }
            .criaw-filter-item select { min-width: 170px; border-radius: 10px; border: 1px solid var(--criaw-slate-200); height: 40px; padding: 0 14px; font-size: 13px; font-weight: 500; background-color: var(--criaw-slate-50); color: var(--criaw-slate-700); cursor: pointer; }
            
            .criaw-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; margin-bottom: 32px; }
            .criaw-chip { background: #fff; padding: 28px; border-radius: 20px; border: 1px solid var(--criaw-slate-200); display: flex; flex-direction: column; position: relative; overflow: hidden; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
            .criaw-chip:hover { border-color: var(--criaw-purple-500); transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.06); }
            .criaw-chip.primary-goal { border: 2px solid var(--criaw-purple-500); background: linear-gradient(145deg, #fff, var(--criaw-slate-50)); }
            .criaw-chip.primary-goal::after { content: 'PRIMARY KPI'; position: absolute; top: 12px; right: 12px; background: var(--criaw-purple-600); color: #fff; font-size: 9px; font-weight: 800; padding: 3px 8px; border-radius: 5px; }

            .criaw-chip-title { font-size: 12px; color: var(--criaw-slate-500); font-weight: 700; text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.05em; }
            .criaw-chip-value { font-size: 38px; font-weight: 800; color: var(--criaw-slate-800); line-height: 1; margin-bottom: 12px; }
            .criaw-chip-meta { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; }
            .criaw-trend-tag { padding: 4px 10px; border-radius: 8px; display: inline-flex; align-items: center; gap: 4px; }
            .criaw-trend-up { background: #ecfdf5; color: #059669; }
            .criaw-trend-down { background: #fef2f2; color: #dc2626; }
            
            .criaw-grid-viz { display: grid; grid-template-columns: 1fr 1.5fr; gap: 24px; margin-bottom: 32px; align-items: stretch; }
            @media (max-width: 1200px) { .criaw-grid-viz { grid-template-columns: 1fr; } }
            
            .criaw-chart-card { height: 100%; display: flex; flex-direction: column; }
            .criaw-chart-container { position: relative; height: 350px; width: 100%; margin-top: 10px; }
            .criaw-table-header { padding: 0 0 24px 0; display: flex; justify-content: space-between; align-items: center; }
            .criaw-table-header h2 { margin: 0; font-size: 17px; font-weight: 700; color: var(--criaw-slate-800); }
            
            .criaw-dash-table { width: 100%; border-collapse: separate; border-spacing: 0; }
            .criaw-dash-table th { background: var(--criaw-slate-50); padding: 18px 20px; text-align: left; font-size: 11px; font-weight: 700; color: var(--criaw-slate-500); border-bottom: 1px solid var(--criaw-slate-200); text-transform: uppercase; letter-spacing: 0.05em; }
            .criaw-dash-table td { padding: 18px 20px; border-bottom: 1px solid var(--criaw-slate-100); font-size: 13px; vertical-align: middle; color: var(--criaw-slate-700); }
            .criaw-dash-table tr:hover td { background: var(--criaw-slate-50); }
            
            .criaw-source-pill { display: inline-flex; align-items: center; padding: 6px 12px; background: var(--criaw-slate-100); border-radius: 10px; font-size: 12px; font-weight: 600; color: var(--criaw-slate-700); gap: 8px; }
            
            .criaw-badge { padding: 6px 12px; border-radius: 10px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }
            
            .criaw-modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); z-index: 10000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(8px); }
            .criaw-modal { background: #fff; border-radius: 24px; width: 550px; max-width: 95%; padding: 40px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); border: 1px solid var(--criaw-slate-200); }
            .criaw-modal-title { font-size: 22px; font-weight: 800; color: var(--criaw-slate-800); }

            /* Timeline Stepper UI - RESPONSIVE FIX */
            .criaw-timeline { position: relative; padding-left: 30px; margin-top: 30px; }
            .criaw-timeline::before { content: ''; position: absolute; left: 14px; top: 0; bottom: 0; width: 2px; background: var(--criaw-slate-200); border-radius: 1px; }
            .criaw-timeline-item { position: relative; margin-bottom: 25px; padding-left: 10px; }
            .criaw-timeline-item:last-child { margin-bottom: 0; }
            .criaw-timeline-icon { position: absolute; left: -30px; top: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; }
            .criaw-timeline-icon .dashicons { font-size: 16px; width: 16px; height: 16px; background: #fff; padding: 6px; border-radius: 50%; border: 2px solid var(--criaw-slate-200); color: var(--criaw-slate-500); z-index: 1; transition: all 0.3s ease; }
            .criaw-timeline-item.active .dashicons { border-color: var(--criaw-purple-600); color: var(--criaw-purple-600); box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.1); }
            .criaw-timeline-content { background: var(--criaw-slate-50); padding: 14px 20px; border-radius: 16px; border: 1px solid var(--criaw-slate-200); }
            .criaw-timeline-title { font-size: 14px; font-weight: 700; color: var(--criaw-slate-800); margin-bottom: 4px; display: block; }
            .criaw-timeline-desc { font-size: 13px; color: var(--criaw-slate-500); line-height: 1.5; }
            .criaw-timeline-time { font-size: 11px; font-weight: 700; color: var(--criaw-slate-500); text-transform: uppercase; margin-bottom: 6px; opacity: 0.8; display: block; }

            .criaw-pro-pagination { display: flex; justify-content: center; align-items: center; padding: 24px; border-top: 1px solid var(--criaw-slate-100); gap: 8px; background: var(--criaw-slate-50); }
            .criaw-page-btn { min-width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 10px; border: 1px solid var(--criaw-slate-200); background: #fff; text-decoration: none; color: var(--criaw-slate-700); font-weight: 600; font-size: 13px; transition: all 0.2s ease; }
            .criaw-page-btn:hover { border-color: var(--criaw-purple-500); color: var(--criaw-purple-600); }
            .criaw-page-btn.active { background: var(--criaw-purple-600); color: #fff; border-color: var(--criaw-purple-600); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
            
            .criaw-audience-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; margin-bottom: 32px; }
            .criaw-insight-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--criaw-slate-100); }
            .criaw-insight-row:last-child { border-bottom: none; }
            .criaw-insight-label { font-size: 13px; font-weight: 600; color: var(--criaw-slate-700); }
            .criaw-insight-value { font-size: 13px; font-weight: 700; color: var(--criaw-purple-600); background: var(--criaw-slate-50); padding: 4px 10px; border-radius: 8px; }
 
            .criaw-loading-overlay { position: absolute; inset: 0; background: rgba(255,255,255,0.8); z-index: 10; display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px); border-radius: 20px; }
            .criaw-loading-overlay.active { display: flex; }
            .criaw-spinner { width: 34px; height: 34px; border: 3px solid var(--criaw-slate-200); border-top-color: var(--criaw-purple-600); border-radius: 50%; animation: spin 0.8s linear infinite; }
            @keyframes spin { to { transform: rotate(360deg); } }
        ");
    }

    public function render_dashboard_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $countries = $this->get_unique_countries();
        ?>
        <div class="wrap criaw-premium-dash">
            <div class="criaw-dash-header">
                <div>
                    <h1><?php esc_html_e('Lead Performance Hub', 'carbon-repro-widget'); ?></h1>
                    <p><?php esc_html_e('Analyze marketing ROI and lead conversion trends in real-time.', 'carbon-repro-widget'); ?></p>
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <div id="criawFilterLoader" class="criaw-spinner" style="width:20px; height:20px; border-width:2px; display:none;"></div>
                    <button type="button" class="button" id="criawRefreshBtn" style="border-radius:10px; height:40px; padding:0 15px;"><span class="dashicons dashicons-update" style="margin-top:2px;"></span></button>
                </div>
            </div>

            <div class="criaw-filter-bar">
                <div class="criaw-filter-item">
                    <label><?php esc_html_e('Date Range', 'carbon-repro-widget'); ?></label>
                    <select id="criawFilterRange">
                        <option value="all" selected><?php esc_html_e('All Time', 'carbon-repro-widget'); ?></option>
                        <option value="30d"><?php esc_html_e('Last 30 Days', 'carbon-repro-widget'); ?></option>
                        <option value="7d"><?php esc_html_e('Last 7 Days', 'carbon-repro-widget'); ?></option>
                    </select>
                </div>
                <div class="criaw-filter-item">
                    <label><?php esc_html_e('Filter Event', 'carbon-repro-widget'); ?></label>
                    <select id="criawFilterAction">
                        <option value="all"><?php _e('All Interactions', 'carbon-repro-widget'); ?></option>
                        <option value="widget_open"><?php _e('Widget Opens', 'carbon-repro-widget'); ?></option>
                        <option value="show_form"><?php _e('Form Views', 'carbon-repro-widget'); ?></option>
                        <option value="form_submit"><?php _e('Submissions', 'carbon-repro-widget'); ?></option>
                    </select>
                </div>
                <div class="criaw-filter-item">
                    <label><?php esc_html_e('Geo-Region', 'carbon-repro-widget'); ?></label>
                    <select id="criawFilterCountry">
                        <option value="all"><?php _e('Global Traffic', 'carbon-repro-widget'); ?></option>
                        <?php foreach ($countries as $country) : ?>
                            <option value="<?php echo esc_attr($country); ?>"><?php echo esc_html($country); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="criaw-stats-grid" id="criawSummaryStats">
                <!-- Data Benchmarking Chips -->
            </div>

            <div class="criaw-grid-viz">
                <div class="criaw-card criaw-chart-card">
                    <div class="criaw-loading-overlay" id="criawFunnelLoader"><div class="criaw-spinner"></div></div>
                    <div class="criaw-table-header">
                        <h2><?php _e('Conversion Funnel', 'carbon-repro-widget'); ?></h2>
                    </div>
                    <div class="criaw-chart-container">
                        <canvas id="criawFunnelChart"></canvas>
                    </div>
                </div>

                <div class="criaw-card criaw-chart-card">
                    <div class="criaw-loading-overlay" id="criawChartLoader"><div class="criaw-spinner"></div></div>
                    <div class="criaw-table-header">
                        <h2><?php _e('Interaction Trends', 'carbon-repro-widget'); ?></h2>
                    </div>
                    <div class="criaw-chart-container">
                        <canvas id="criawMainChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="criaw-table-header" style="margin: 32px 0 16px;">
                <h2 style="font-size:22px; font-weight:800;"><?php _e('Audience & Attribution Intelligence', 'carbon-repro-widget'); ?></h2>
                <p style="font-size:14px; color:var(--criaw-slate-500); margin:4px 0 0;"><?php _e('Multi-dimensional analysis of user sources, devices, and platforms.', 'carbon-repro-widget'); ?></p>
            </div>
            
            <div class="criaw-audience-grid">
                <div class="criaw-card" style="margin-bottom:0;">
                    <div class="criaw-table-header"><h2><?php _e('Traffic Sources', 'carbon-repro-widget'); ?></h2></div>
                    <div class="criaw-chart-container" style="height:250px;">
                        <canvas id="criawDistChart"></canvas>
                    </div>
                </div>
                <div class="criaw-card" style="margin-bottom:0;">
                    <div class="criaw-table-header"><h2><?php _e('Device Distribution', 'carbon-repro-widget'); ?></h2></div>
                    <div class="criaw-chart-container" style="height:250px;">
                        <canvas id="criawDeviceChart"></canvas>
                    </div>
                </div>
                <div class="criaw-card" style="margin-bottom:0;">
                    <div class="criaw-table-header"><h2><?php _e('Top Browsers', 'carbon-repro-widget'); ?></h2></div>
                    <div class="criaw-chart-container" style="height:250px;">
                        <canvas id="criawBrowserChart"></canvas>
                    </div>
                </div>
                <div class="criaw-card" style="margin-bottom:0;">
                    <div class="criaw-table-header"><h2><?php _e('Operating Systems', 'carbon-repro-widget'); ?></h2></div>
                    <div class="criaw-chart-container" style="height:250px;">
                        <canvas id="criawOSChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="criaw-card" style="padding:0; overflow:hidden; margin-top:32px;">
                <div class="criaw-loading-overlay" id="criawLogLoader"><div class="criaw-spinner"></div></div>
                <div class="criaw-table-header" style="padding: 28px 28px 0;">
                    <h2><?php _e('Activity Logs', 'carbon-repro-widget'); ?></h2>
                </div>
                <div id="criawLogTableContainer"></div>
            </div>

            <div class="criaw-card" style="padding:0; overflow:hidden; margin-top:32px;">
                <div class="criaw-loading-overlay" id="criawPagesLoader"><div class="criaw-spinner"></div></div>
                <div class="criaw-table-header" style="padding: 28px 28px 10px;">
                    <h2><?php _e('Top Performing Pages', 'carbon-repro-widget'); ?></h2>
                </div>
                <div id="criawTopPagesContainer" style="padding: 0 28px 28px;"></div>
            </div>

            <div class="criaw-card" style="padding:0; overflow:hidden;">
                <div class="criaw-loading-overlay" id="criawSubmissionLoader"><div class="criaw-spinner"></div></div>
                <div class="criaw-table-header" style="padding: 28px;">
                    <h2><?php _e('Primary Goal: Form Submissions', 'carbon-repro-widget'); ?></h2>
                    <a href="<?php echo admin_url('admin.php?page=wpforms-entries&view=list&form_id=' . absint($this->get_setting('wpforms_id'))); ?>" class="button button-primary" style="border-radius:10px; font-weight:700; height:36px; display:inline-flex; align-items:center;"><?php _e('WPForms Inbox', 'carbon-repro-widget'); ?></a>
                </div>
                <div id="criawSubmissionsContainer"></div>
            </div>
        </div>

        <div class="criaw-modal-overlay" id="criawJourneyModal">
            <div class="criaw-modal">
                <div class="criaw-modal-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <h3 class="criaw-modal-title"><?php _e('Lead Interaction Timeline', 'carbon-repro-widget'); ?></h3>
                    <button type="button" class="button-link" onclick="document.getElementById('criawJourneyModal').style.display='none'"><span class="dashicons dashicons-no-alt" style="font-size:24px;"></span></button>
                </div>
                <div id="criawModalMeta" style="font-size:12px; color:var(--criaw-slate-500); margin-bottom:20px; font-weight:600;"></div>
                <div class="criaw-timeline" id="criawModalContent"></div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            let chartInstance = null;
            let distInstance  = null;
            const container = document.querySelector('.criaw-premium-dash');
            
            function fetchData(log_paged = 1, subs_paged = 1) {
                const loaders = container.querySelectorAll('.criaw-loading-overlay');
                loaders.forEach(l => l.classList.add('active'));

                window.criawLogPaged = log_paged;
                window.criawSubsPaged = subs_paged;

                const params = new URLSearchParams({
                    action: 'criaw_get_dashboard_data',
                    range: document.getElementById('criawFilterRange').value,
                    event: document.getElementById('criawFilterAction').value,
                    country: document.getElementById('criawFilterCountry').value,
                    paged: log_paged,
                    subs_paged: subs_paged
                });

                fetch(ajaxurl + '?' + params.toString())
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            renderDashboard(res.data);
                        }
                        loaders.forEach(l => l.classList.remove('active'));
                    })
                    .catch(e => {
                        console.error('CRIAW Error:', e);
                        loaders.forEach(l => l.classList.remove('active'));
                    });
            }

            function renderDashboard(data) {
                // Render Summaries with Expanded Metrics
                const stats = data.stats;
                const chips = [
                    { key: 'widget_open', label: '<?php _e('Widget Opens', 'carbon-repro-widget'); ?>', color: '#6366f1' },
                    { key: 'form_submit', label: '<?php _e('Form Leads', 'carbon-repro-widget'); ?>', color: '#0d9488', primary: true },
                    { key: 'call_click', label: '<?php _e('Call Clicks', 'carbon-repro-widget'); ?>', color: '#f59e0b' },
                    { key: 'text_click', label: '<?php _e('SMS Clicks', 'carbon-repro-widget'); ?>', color: '#8b5cf6' }
                ];

                let chipsHtml = '';
                chips.forEach(c => {
                    const item = stats[c.key];
                    const val = item.val;
                    const hideTrend = item.hideTrend;
                    const trendPart = !hideTrend ? `<div class="criaw-chip-meta">
                        <span class="criaw-trend-tag criaw-trend-${item.trend}">${item.pct}</span>
                        <span style="opacity:0.5; font-size:10px;">vs prev.</span>
                    </div>` : `<div class="criaw-chip-meta" style="opacity:0.5; font-size:10px;">Cumulative Total</div>`;

                    chipsHtml += `
                        <div class="criaw-chip ${c.primary ? 'primary-goal' : ''}">
                            <span class="criaw-chip-title">${c.label}</span>
                            <span class="criaw-chip-value" style="color:${c.color}">${val}</span>
                            ${trendPart}
                        </div>
                    `;
                });
                document.getElementById('criawSummaryStats').innerHTML = chipsHtml;

                // Render Audience Doughnuts
                const chartRegistry = window.criawChartRegistry || {};
                window.criawChartRegistry = chartRegistry;

                const renderDoughnut = (ctxId, dataObj) => {
                    const canvas = document.getElementById(ctxId);
                    if (!canvas) return;
                    const ctx = canvas.getContext('2d');
                    if (chartRegistry[ctxId]) chartRegistry[ctxId].destroy();
                    chartRegistry[ctxId] = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: dataObj.map(i => i.label),
                            datasets: [{
                                data: dataObj.map(i => i.total),
                                backgroundColor: ['#1e293b', '#0d9488', '#0ea5e9', '#6366f1', '#f59e0b', '#ef4444', '#94a3b8'],
                                borderWidth: 0,
                                cutout: '70%'
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { 
                                legend: { 
                                    position: 'bottom', 
                                    labels: { usePointStyle: true, padding: 10, font: { size: 10, weight: '600' }, color: '#475569' } 
                                } 
                            }
                        }
                    });
                };

                renderDoughnut('criawDeviceChart', data.audience.devices);
                renderDoughnut('criawBrowserChart', data.audience.browsers);
                renderDoughnut('criawOSChart', data.audience.os);
                renderDoughnut('criawDistChart', data.dist.values.map((v, i) => ({ label: data.dist.labels[i], total: v })));

                // 1. Trend Chart (Colorful & Clear)
                const ctx = document.getElementById('criawMainChart').getContext('2d');
                if (chartInstance) chartInstance.destroy();
                
                // Force point markers for better visibility
                data.chart.datasets.forEach(ds => {
                    ds.pointRadius = 4;
                    ds.pointBackgroundColor = ds.borderColor;
                    ds.borderWidth = 3;
                });

                chartInstance = new Chart(ctx, {
                    type: 'line',
                    data: { labels: data.chart.labels, datasets: data.chart.datasets },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: { legend: { display: true, position: 'top', labels: { usePointStyle: true, padding: 15, font: { weight: '600' } } } },
                        scales: { 
                            y: { beginAtZero: true, grid: { color: 'rgba(203, 213, 225, 0.2)' }, ticks: { color: '#64748b' } }, 
                            x: { grid: { display: false }, ticks: { color: '#64748b' } } 
                        }
                    }
                });

                // 2. Funnel Chart (Fixed Colors & Alignment)
                const funnelCtx = document.getElementById('criawFunnelChart').getContext('2d');
                if (window.funnelInstance) window.funnelInstance.destroy();
                window.funnelInstance = new Chart(funnelCtx, {
                    type: 'bar',
                    data: {
                        labels: data.funnel.labels,
                        datasets: [{
                            data: data.funnel.values,
                            backgroundColor: ['#171717', '#333333', '#666666', '#f59e0b'], // Brand Primary, Light, Lighter, Amber
                            borderRadius: 12,
                            barThickness: 40
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false }, ticks: { display: false } },
                            y: { grid: { display: false }, border: { display: false }, ticks: { color: '#1e293b', font: { weight: '700', size: 12 } } }
                        }
                    }
                });

                // Render Top Pages
                let pagesHtml = `
                    <table class="criaw-dash-table" style="box-shadow:none; border:1px solid var(--criaw-slate-100); border-radius:12px; overflow:hidden;">
                        <thead>
                            <tr>
                                <th>Page Path</th>
                                <th style="width:100px; text-align:right;">Interactions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.top_pages.map(p => `
                                <tr>
                                    <td style="font-weight:600;"><a href="${p.url}" target="_blank" style="color:var(--criaw-slate-700); text-decoration:none;">${p.path}</a></td>
                                    <td style="text-align:right;"><span class="criaw-insight-value">${p.total}</span></td>
                                </tr>
                            `).join('')}
                            ${data.top_pages.length === 0 ? '<tr><td colspan="2" style="text-align:center;">No data available</td></tr>' : ''}
                        </tbody>
                    </table>
                `;
                document.getElementById('criawTopPagesContainer').innerHTML = pagesHtml;

                document.getElementById('criawLogTableContainer').innerHTML = data.logs_html;
                document.getElementById('criawSubmissionsContainer').innerHTML = data.submissions_html;
                bindPagination();
            }

            // Auto-refresh on change
            ['criawFilterRange', 'criawFilterAction', 'criawFilterCountry'].forEach(id => {
                document.getElementById(id).addEventListener('change', () => fetchData());
            });

            function bindPagination() {
                document.querySelectorAll('.criaw-page-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const p = new URL(e.target.href).searchParams.get('paged');
                        if (e.target.closest('#criawLogTableContainer')) fetchData(p, window.criawSubsPaged);
                        else fetchData(window.criawLogPaged, p);
                    });
                });
            }

            window.criawShowJourney = function(id) {
                const modal = document.getElementById('criawJourneyModal');
                const content = document.getElementById('criawModalContent');
                const meta = document.getElementById('criawModalMeta');
                content.innerHTML = '<div style="text-align:center; padding:40px;"><div class="criaw-spinner"></div></div>';
                modal.style.display = 'flex';

                fetch(ajaxurl + '?action=criaw_get_journey&id=' + id)
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            meta.innerHTML = `IP Address: ${res.data.ip} &bull; Network: ${res.data.isp}`;
                            let html = '';
                            res.data.steps.forEach((s, idx) => {
                                html += `
                                    <div class="criaw-timeline-item ${idx === res.data.steps.length -1 ? 'active' : ''}">
                                        <div class="criaw-timeline-content">
                                            <span class="criaw-timeline-time">${s.time}</span>
                                            <div class="criaw-timeline-icon"><span class="dashicons ${s.icon}"></span></div>
                                            <span class="criaw-timeline-title">${s.title}</span>
                                            <div class="criaw-timeline-desc">${s.desc}</div>
                                        </div>
                                    </div>
                                `;
                            });
                            content.innerHTML = html;
                        }
                    });
            };

            document.querySelectorAll('select').forEach(s => s.addEventListener('change', () => fetchData()));
            document.getElementById('criawRefreshBtn').addEventListener('click', () => fetchData());
            fetchData();
        });
        </script>
        <?php
    }

    public function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap" style="max-width: 800px; margin-top: 20px;">
            <div style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #f0f0f0; margin-bottom: 24px;">
                <h1 style="margin-bottom: 30px;"><?php esc_html_e('Widget Settings', 'carbon-repro-widget'); ?></h1>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('criaw_settings_group');
                    do_settings_sections('carbon-repro-widget-settings');
                    submit_button();
                    ?>
                </form>
            </div>

            <div style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #f0f0f0; border-left: 4px solid #d63638;">
                <h2 style="color: #d63638; margin-top:0;"><?php esc_html_e('Danger Zone', 'carbon-repro-widget'); ?></h2>
                <p><?php esc_html_e('Deleting analytics data is permanent and cannot be undone.', 'carbon-repro-widget'); ?></p>
                <button type="button" class="button button-link-delete" id="criawClearBtn" style="color:#d63638;"><?php esc_html_e('Clear All Analytics Data', 'carbon-repro-widget'); ?></button>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('criawClearBtn');
            if (btn) {
                btn.addEventListener('click', function() {
                    if (confirm('<?php echo esc_js(__('Are you absolutely sure? This will delete all tracking logs forever.', 'carbon-repro-widget')); ?>')) {
                        btn.disabled = true;
                        btn.textContent = '<?php echo esc_js(__('Clearing...', 'carbon-repro-widget')); ?>';
                        
                        fetch(ajaxurl + '?action=criaw_clear_analytics&_wpnonce=<?php echo wp_create_nonce('criaw_clear_logs'); ?>')
                            .then(r => r.json())
                            .then(res => {
                                if (res.success) {
                                    alert('<?php echo esc_js(__('All analytics have been cleared.', 'carbon-repro-widget')); ?>');
                                    location.reload();
                                }
                            });
                    }
                });
            }
        });
        </script>
        <?php
    }

    public function ajax_clear_analytics(): void
    {
        check_admin_referer('criaw_clear_logs');
        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $wpdb->query("TRUNCATE TABLE $table_name");
        update_option(self::STATS_OPTION_KEY, []);

        wp_send_json_success();
    }

    private function get_unique_countries(): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        return $wpdb->get_col("SELECT DISTINCT country FROM $table_name WHERE country != 'Unknown' ORDER BY country ASC");
    }

    public function ajax_get_dashboard_data(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $range      = sanitize_text_field($_GET['range'] ?? 'all');
        $event      = sanitize_text_field($_GET['event'] ?? 'all');
        $country    = sanitize_text_field($_GET['country'] ?? 'all');
        $paged      = absint($_GET['paged'] ?? 1);
        $subs_paged = absint($_GET['subs_paged'] ?? 1);

        $data = [
            'stats'            => $this->get_filtered_stats($range, $country),
            'chart'            => $this->get_multi_line_chart_data($range, $country),
            'dist'             => $this->get_traffic_distribution_data($range, $country),
            'funnel'           => $this->get_funnel_data($range, $country),
            'audience'         => $this->get_audience_insights_data($range, $country),
            'logs_html'        => $this->render_logs_partial($range, $event, $country, $paged),
            'submissions_html' => $this->render_submissions_partial($subs_paged),
            'top_pages'        => $this->get_top_pages_data($range, $country),
        ];

        wp_send_json_success($data);
    }

    private function get_top_pages_data(string $range, string $country): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $where      = $this->build_where_clause($range, 'all', $country);
        
        $results = $wpdb->get_results("
            SELECT page_url, COUNT(id) as total 
            FROM $table_name 
            $where 
            GROUP BY page_url 
            ORDER BY total DESC 
            LIMIT 10
        ");
        
        return array_map(function($row) {
            return [
                'url'   => $row->page_url,
                'path'  => str_replace(home_url(), '', $row->page_url) ?: '/',
                'total' => (int) $row->total
            ];
        }, $results);
    }

    private function get_funnel_data(string $range, string $country): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $where      = $this->build_where_clause($range, 'all', $country);
        
        $results = $wpdb->get_results("SELECT event_type, COUNT(id) as total FROM $table_name $where GROUP BY event_type");
        
        $counts = ['widget_open' => 0, 'show_form' => 0, 'form_submit' => 0, 'call_click' => 0, 'text_click' => 0];
        foreach ($results as $row) {
            if (isset($counts[$row->event_type])) $counts[$row->event_type] = (int) $row->total;
        }

        return [
            'labels' => [__('Widget Opens', 'carbon-repro-widget'), __('Quote Views', 'carbon-repro-widget'), __('Leads / Subs', 'carbon-repro-widget'), __('Direct Calls', 'carbon-repro-widget')],
            'values' => [$counts['widget_open'], $counts['show_form'], $counts['form_submit'], $counts['call_click']]
        ];
    }

    private function get_traffic_distribution_data(string $range, string $country): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $where      = $this->build_where_clause($range, 'all', $country);
        
        $results = $wpdb->get_results("SELECT traffic_category as label, COUNT(id) as total FROM $table_name $where GROUP BY traffic_category ORDER BY total DESC");
        
        $labels = [];
        $values = [];
        foreach ($results as $row) {
            $labels[] = $row->label ?: 'Unknown';
            $values[] = (int) $row->total;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function ajax_get_journey(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $id = absint($_GET['id'] ?? 0);
        if (! $id) {
            wp_send_json_error('Invalid ID');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));

        if (! $log) {
            wp_send_json_error('Log not found');
        }

        $steps = [];

        // 1. Initial Discovery
        if ($log->first_visit_at) {
            $steps[] = [
                'icon' => 'dashicons-visibility',
                'title' => __('Initial Discovery', 'carbon-repro-widget'),
                'desc' => sprintf(__('User arrived from %s', 'carbon-repro-widget'), '<strong>' . esc_html($log->normalized_source) . '</strong>'),
                'time' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->first_visit_at))
            ];
            
            $steps[] = [
                'icon' => 'dashicons-admin-links',
                'title' => __('First Landing Page', 'carbon-repro-widget'),
                'desc' => esc_html($log->first_landing_page ?: __('Home Page', 'carbon-repro-widget')),
                'time' => ''
            ];
        }

        // 2. Marketing Context
        if ($log->utm_campaign) {
            $steps[] = [
                'icon' => 'dashicons-megaphone',
                'title' => __('Campaign Influence', 'carbon-repro-widget'),
                'desc' => sprintf(__('Attributed to campaign: %s (%s)', 'carbon-repro-widget'), '<strong>' . esc_html($log->utm_campaign) . '</strong>', esc_html($log->utm_medium)),
                'time' => ''
            ];
        }

        if ($log->gclid || $log->fbclid) {
            $steps[] = [
                'icon' => 'dashicons-money',
                'title' => __('Paid Click Tracked', 'carbon-repro-widget'),
                'desc' => $log->gclid ? __('Verified via Google Ads (GCLID)', 'carbon-repro-widget') : __('Verified via Meta Ads (FBCLID)', 'carbon-repro-widget'),
                'time' => ''
            ];
        }

        // 3. Current Interaction
        $event_label = str_replace('_', ' ', $log->event_type);
        $steps[] = [
            'icon' => 'dashicons-yes',
            'title' => __('Recent Interaction', 'carbon-repro-widget'),
            'desc' => sprintf(__('User triggered %s on page: %s', 'carbon-repro-widget'), '<strong>' . esc_html($event_label) . '</strong>', esc_url($log->page_url)),
            'time' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))
        ];

        wp_send_json_success(['steps' => $steps, 'ip' => $log->ip_address, 'isp' => $log->isp]);
    }

    private function get_filtered_stats(string $range, string $country): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        $where_curr = $this->build_where_clause($range, 'all', $country, 'current');
        $where_prev = $this->build_where_clause($range, 'all', $country, 'previous');
        
        $curr = $wpdb->get_results("SELECT event_type, COUNT(id) as total FROM $table_name $where_curr GROUP BY event_type");
        $prev = $wpdb->get_results("SELECT event_type, COUNT(id) as total FROM $table_name $where_prev GROUP BY event_type");
        
        $stats = ['widget_open' => 0, 'show_form' => 0, 'form_submit' => 0, 'call_click' => 0, 'text_click' => 0];
        $prev_stats = $stats;

        foreach ($curr as $row) { if (isset($stats[$row->event_type])) $stats[$row->event_type] = (int) $row->total; }
        foreach ($prev as $row) { if (isset($prev_stats[$row->event_type])) $prev_stats[$row->event_type] = (int) $row->total; }

        $insights = [];
        foreach ($stats as $key => $val) {
            $old = $prev_stats[$key];
            $diff = $val - $old;
            $percent = ($old > 0) ? round(($diff / $old) * 100, 1) : 0;
            
            // For 'All Time', hide trend as there's no previous period comparison that makes sense here
            $insights[$key] = [
                'val' => $val,
                'diff' => ($diff >= 0 ? '+' : '') . $diff,
                'pct' => ($percent >= 0 ? '+' : '') . $percent . '%',
                'trend' => ($range === 'all') ? 'none' : ($diff >= 0 ? 'up' : 'down'),
                'hideTrend' => ($range === 'all')
            ];
        }

        return $insights;
    }

    private function get_audience_insights_data(string $range, string $country): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $where      = $this->build_where_clause($range, 'all', $country);

        $devices = $wpdb->get_results("SELECT device_type as label, COUNT(id) as total FROM $table_name $where GROUP BY device_type ORDER BY total DESC");
        $browsers = $wpdb->get_results("SELECT browser as label, COUNT(id) as total FROM $table_name $where GROUP BY browser ORDER BY total DESC LIMIT 5");
        $os       = $wpdb->get_results("SELECT os as label, COUNT(id) as total FROM $table_name $where GROUP BY os ORDER BY total DESC LIMIT 5");

        return [
            'devices' => $devices,
            'browsers' => $browsers,
            'os' => $os
        ];
    }

    private function get_multi_line_chart_data(string $range, string $country): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $where = $this->build_where_clause($range, 'all', $country, 'current');
        
        $days = ($range === '7d') ? 7 : 30;
        if ($range === 'all') {
            // For All Time, we show the last 30 days in the trend chart for clarity
            $where_chart = str_replace('1=1', "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", $where);
        } else {
            $where_chart = $where;
        }
        
        $results = $wpdb->get_results("
            SELECT DATE(created_at) as date, event_type, COUNT(id) as total 
            FROM $table_name 
            $where_chart 
            GROUP BY DATE(created_at), event_type 
            ORDER BY date ASC
        ");

        $datasets_raw = [
            'widget_open' => array_fill(0, $days, 0),
            'form_submit' => array_fill(0, $days, 0),
            'call_click'  => array_fill(0, $days, 0),
            'text_click'  => array_fill(0, $days, 0),
        ];

        $labels = [];
        $mapped_dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M j', strtotime($date));
            $mapped_dates[$date] = ($days - 1) - $i;
        }

        foreach ($results as $row) {
            if (isset($datasets_raw[$row->event_type]) && isset($mapped_dates[$row->date])) {
                $datasets_raw[$row->event_type][$mapped_dates[$row->date]] = (int) $row->total;
            }
        }

        $palette = [
            'widget_open' => ['#6366f1', 'rgba(99, 102, 241, 0.05)'], // Indigo
            'form_submit' => ['#0d9488', 'rgba(13, 148, 136, 0.05)'], // Teal
            'call_click'  => ['#f59e0b', 'rgba(245, 158, 11, 0.05)'], // Amber
            'text_click'  => ['#8b5cf6', 'rgba(139, 92, 246, 0.05)'], // Violet
        ];

        $datasets = [];
        foreach ($datasets_raw as $type => $values) {
            $datasets[] = [
                'label'           => ucwords(str_replace('_', ' ', $type)),
                'data'            => $values,
                'borderColor'     => $palette[$type][0],
                'backgroundColor' => $palette[$type][1],
                'fill'            => true,
                'tension'         => 0.4,
                'borderWidth'     => 2,
                'pointRadius'     => 0,
                'pointHoverRadius' => 6,
            ];
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }

    private function render_logs_partial(string $range, string $event, string $country, int $paged): string
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $per_page   = 10;
        $offset     = ($paged - 1) * $per_page;
        $where      = $this->build_where_clause($range, $event, $country);

        $items       = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");
        $total_pages = ceil($total_items / $per_page);

        ob_start();
        ?>
        <table class="criaw-dash-table">
            <thead>
                <tr>
                    <th style="width:120px;"><?php _e('Time', 'carbon-repro-widget'); ?></th>
                    <th><?php _e('Action', 'carbon-repro-widget'); ?></th>
                    <th><?php _e('Channel & Source', 'carbon-repro-widget'); ?></th>
                    <th><?php _e('Device & OS', 'carbon-repro-widget'); ?></th>
                    <th><?php _e('Engaged Page', 'carbon-repro-widget'); ?></th>
                    <th><?php _e('User Info', 'carbon-repro-widget'); ?></th>
                    <th style="width:50px; text-align:center;"><?php _e('View', 'carbon-repro-widget'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item) : 
                    $cat_icon = 'globe';
                    $cat_color = 'var(--criaw-slate-500)';
                    switch($item->traffic_category) {
                        case 'Paid Search': case 'Paid Social': $cat_icon = 'money'; $cat_color = 'var(--criaw-purple-600)'; break;
                        case 'Organic Social': $cat_icon = 'share'; $cat_color = '#5F4D9E'; break;
                        case 'Organic Search': $cat_icon = 'search'; $cat_color = 'var(--criaw-sky-500)'; break;
                        case 'Email': $cat_icon = 'email'; $cat_color = '#f59e0b'; break;
                        case 'Direct': $cat_icon = 'admin-home'; $cat_color = 'var(--criaw-slate-800)'; break;
                    }

                    $badge_class = 'open';
                    if (strpos($item->event_type, 'form') !== false) $badge_class = 'form';
                    if (strpos($item->event_type, 'submit') !== false) $badge_class = 'submit';
                    if (strpos($item->event_type, 'call') !== false) $badge_class = 'call';
                ?>
                    <tr>
                        <td style="font-size:12px; color:var(--criaw-slate-500); font-weight:600;"><?php echo human_time_diff(strtotime($item->created_at), current_time('timestamp')) . ' ago'; ?></td>
                        <td><span class="criaw-badge criaw-badge-<?php echo $badge_class; ?>" style="background:var(--criaw-slate-100); color:var(--criaw-slate-700); border:1px solid var(--criaw-slate-200);"><?php echo str_replace('_', ' ', $item->event_type); ?></span></td>
                        <td>
                            <div class="criaw-source-pill">
                                <span class="dashicons dashicons-<?php echo $cat_icon; ?>" style="color:<?php echo $cat_color; ?>; font-size:16px;"></span>
                                <strong><?php echo esc_html($item->normalized_source ?: 'Direct'); ?></strong>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:13px; font-weight:700;"><?php echo esc_html($item->device_type); ?></div>
                            <div style="font-size:11px; color:var(--criaw-slate-500);"><?php echo esc_html($item->os); ?></div>
                        </td>
                        <td style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <a href="<?php echo esc_url($item->page_url); ?>" target="_blank" style="font-size:12px; color:var(--criaw-sky-500); text-decoration:none; font-weight:600;"><?php echo esc_html(str_replace(home_url(), '', $item->page_url) ?: '/'); ?></a>
                        </td>
                        <td>
                            <strong style="display:block; font-size:13px;"><?php echo esc_html($item->ip_address); ?></strong>
                            <span style="font-size:11px; color:var(--criaw-purple-600); font-weight:700;"><?php echo esc_html($item->city . ', ' . $item->country); ?></span>
                        </td>
                        <td style="text-align:center;">
                            <button type="button" class="button button-small" onclick="criawShowJourney(<?php echo (int)$item->id; ?>)" style="border-radius:8px; border-color:var(--criaw-slate-200);"><span class="dashicons dashicons-visibility" style="font-size:16px; margin-top:2px; color:var(--criaw-slate-500);"></span></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($items)) : ?>
                    <tr><td colspan="6" style="text-align:center; padding: 60px; color:var(--criaw-slate-500); font-weight:600;"><?php _e('Queue empty. Waiting for interactions...', 'carbon-repro-widget'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($total_pages > 1) : ?>
            <div class="criaw-pro-pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                    <a href="?paged=<?php echo $i; ?>" class="criaw-page-btn <?php echo ($i === $paged) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    private function render_submissions_partial(int $paged): string
    {
        if (! function_exists('wpforms')) {
            return '<div style="padding:40px; text-align:center;">' . __('WPForms not detected.', 'carbon-repro-widget') . '</div>';
        }

        $entry_handler = wpforms()->entry ?? null;
        if (! $entry_handler) {
            return '<div style="padding:40px; text-align:center;">' . __('WPForms entry system not available.', 'carbon-repro-widget') . '</div>';
        }

        $form_id = absint($this->get_setting('wpforms_id'));
        if (! $form_id) {
            return '<div style="padding:40px; text-align:center;">' . __('No Form ID configured in Settings.', 'carbon-repro-widget') . '</div>';
        }

        $per_page = 10;
        $args     = [
            'form_id' => $form_id,
            'number'  => $per_page,
            'offset'  => ($paged - 1) * $per_page,
        ];

        try {
            $entries     = $entry_handler->get_entries($args) ?: [];
            $total_items = (int) $entry_handler->get_entries(['form_id' => $form_id, 'count' => true]);
            $total_pages = ceil($total_items / $per_page);
        } catch (Exception $e) {
            return '<div style="padding:40px; text-align:center; color:red;">' . __('Error fetching leads: ', 'carbon-repro-widget') . esc_html($e->getMessage()) . '</div>';
        }

        ob_start();
        ?>
        <table class="criaw-dash-table">
            <thead>
                <tr>
                    <th style="width:160px;"><?php _e('Submission Date', 'carbon-repro-widget'); ?></th>
                    <?php 
                    $headers = [];
                    if (! empty($entries) && isset($entries[0]->fields)) {
                        $first_fields = json_decode($entries[0]->fields, true) ?: [];
                        $count = 0;
                        foreach ($first_fields as $f) {
                            if ($count++ > 3) break; 
                            $headers[] = $f['name'];
                            echo '<th>' . esc_html($f['name']) . '</th>';
                        }
                    }
                    ?>
                    <th style="width:60px; text-align:center;"><?php _e('Action', 'carbon-repro-widget'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry) : 
                    $fields = isset($entry->fields) ? json_decode($entry->fields, true) : [];
                ?>
                    <tr>
                        <td style="color:var(--criaw-slate-500); font-size:12px; font-weight:600;"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), empty($entry->date_add) ? time() : strtotime($entry->date_add)); ?></td>
                        <?php 
                        if (is_array($fields)) :
                            $val_count = 0;
                            foreach ($fields as $f) {
                                if ($val_count++ > 3) break;
                                echo '<td style="font-weight:600; color:var(--criaw-slate-800);">' . esc_html($f['value'] ?? '') . '</td>';
                            }
                        endif;
                        ?>
                        <td style="text-align:center;">
                            <a href="<?php echo admin_url('admin.php?page=wpforms-entries&view=details&entry_id=' . $entry->entry_id); ?>" target="_blank" class="button button-small" style="border-radius:8px; border-color:var(--criaw-slate-200);"><span class="dashicons dashicons-external" style="font-size:14px; margin-top:3px; color:var(--criaw-teal-600);"></span></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($entries)) : ?>
                    <tr><td colspan="6" style="text-align:center; padding: 60px; color:var(--criaw-slate-500); font-weight:600;"><?php _e('No leads captured yet for the primary form.', 'carbon-repro-widget'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($total_pages > 1) : ?>
            <div class="criaw-pro-pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                    <a href="?paged=<?php echo $i; ?>" class="criaw-page-btn <?php echo ($i === $paged) ? 'current' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    private function render_breakdown_partial(string $range, string $country): string
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $where      = $this->build_where_clause($range, 'all', $country);
        
        $countries = $wpdb->get_results("SELECT country, COUNT(id) as total FROM $table_name $where AND country != 'Unknown' GROUP BY country ORDER BY total DESC LIMIT 5");
        $actions   = $wpdb->get_results("SELECT event_type, COUNT(id) as total FROM $table_name $where GROUP BY event_type ORDER BY total DESC");

        ob_start();
        ?>
        <div style="padding: 10px 24px;">
            <p style="font-size:12px; font-weight:700; color:#8c8f94; margin: 15px 0 10px;"><?php _e('TOP TRAFFIC SOURCES', 'carbon-repro-widget'); ?></p>
            <?php 
            $sources = $wpdb->get_results("SELECT utm_source as label, COUNT(id) as total FROM $table_name $where AND utm_source IS NOT NULL AND utm_source != '' GROUP BY utm_source ORDER BY total DESC LIMIT 5");
            if (empty($sources)) {
                // Fallback to referrer host if no UTMs
                $sources = $wpdb->get_results("SELECT SUBSTRING_INDEX(REPLACE(REPLACE(referrer_url, 'http://', ''), 'https://', ''), '/', 1) as label, COUNT(id) as total FROM $table_name $where AND referrer_url != '' GROUP BY label ORDER BY total DESC LIMIT 5");
            }
            
            if (empty($sources)): ?>
                <div style="font-size:11px; color:#8c8f94; padding: 10px 0;"><?php _e('No source data tracked yet.', 'carbon-repro-widget'); ?></div>
            <?php else:
                foreach ($sources as $s) : 
                    if (empty($s->label)) continue;
                ?>
                    <div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px solid #f9f9f9; font-size:12px;">
                        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:180px; text-transform:capitalize;"><?php echo esc_html($s->label); ?></span>
                        <span style="font-weight:700; color:#16a085;"><?php echo $s->total; ?></span>
                    </div>
                <?php endforeach; 
            endif;
            ?>

            <p style="font-size:12px; font-weight:700; color:#8c8f94; margin: 25px 0 10px;"><?php _e('TOP ACTIVE PAGES', 'carbon-repro-widget'); ?></p>
            <?php 
            $pages = $wpdb->get_results("SELECT page_url, COUNT(id) as total FROM $table_name $where AND page_url != '' GROUP BY page_url ORDER BY total DESC LIMIT 5");
            foreach ($pages as $p) : 
                $path = parse_url($p->page_url, PHP_URL_PATH) ?: '/';
            ?>
                <div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px solid #f9f9f9; font-size:12px;">
                    <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:180px;" title="<?php echo esc_attr($p->page_url); ?>"><?php echo esc_html($path); ?></span>
                    <span style="font-weight:700; color:#1abc9c;"><?php echo $p->total; ?></span>
                </div>
            <?php endforeach; ?>

            <p style="font-size:12px; font-weight:700; color:#8c8f94; margin: 25px 0 10px;"><?php _e('ACTION SPLIT', 'carbon-repro-widget'); ?></p>
            <?php foreach ($actions as $a) : ?>
                <div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px solid #f9f9f9; font-size:13px;">
                    <span style="text-transform:capitalize;"><?php echo str_replace('_', ' ', $a->event_type); ?></span>
                    <span style="font-weight:700; color:#3498db;"><?php echo $a->total; ?></span>
                </div>
            <?php endforeach; ?>
            
            <p style="font-size:12px; font-weight:700; color:#8c8f94; margin: 25px 0 10px;"><?php _e('POPULAR LOCATIONS', 'carbon-repro-widget'); ?></p>
            <?php foreach ($countries as $c) : ?>
                <div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px solid #f9f9f9; font-size:13px;">
                    <span><?php echo esc_html($c->country); ?></span>
                    <span style="font-weight:700; color:#9b59b6;"><?php echo $c->total; ?></span>
                </div>
            <?php endforeach; ?>
            
            <p style="font-size:12px; font-weight:700; color:#8c8f94; margin: 25px 0 10px;"><?php _e('BROWSER & OS', 'carbon-repro-widget'); ?></p>
            <?php 
            $browsers = $wpdb->get_results("SELECT browser, COUNT(id) as total FROM $table_name $where GROUP BY browser ORDER BY total DESC LIMIT 3");
            $oss      = $wpdb->get_results("SELECT os, COUNT(id) as total FROM $table_name $where GROUP BY os ORDER BY total DESC LIMIT 3");
            ?>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div>
                    <?php foreach ($browsers as $b) : ?>
                        <div style="font-size:11px; padding: 4px 0; border-bottom: 1px solid #f9f9f9; display:flex; justify-content:space-between;">
                            <span><?php echo esc_html($b->browser); ?></span>
                            <span style="font-weight:700;"><?php echo $b->total; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div>
                    <?php foreach ($oss as $o) : ?>
                        <div style="font-size:11px; padding: 4px 0; border-bottom: 1px solid #f9f9f9; display:flex; justify-content:space-between;">
                            <span><?php echo esc_html($o->os); ?></span>
                            <span style="font-weight:700;"><?php echo $o->total; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function build_where_clause(string $range, string $event, string $country, string $period = 'current'): string
    {
        $parts = ["1=1"];
        
        if ($range === 'all') {
            // No date filter for all time
        } else {
            $days = ($range === '7d') ? 7 : 30;
            
            if ($period === 'current') {
                $parts[] = "created_at >= DATE_SUB(CURDATE(), INTERVAL " . (int)$days . " DAY)";
            } else {
                // Previous period: (days*2) ago up to (days) ago
                $parts[] = "created_at < DATE_SUB(CURDATE(), INTERVAL " . (int)$days . " DAY)";
                $parts[] = "created_at >= DATE_SUB(CURDATE(), INTERVAL " . ((int)$days * 2) . " DAY)";
            }
        }

        if ($event !== 'all') {
            $parts[] = "event_type = '" . esc_sql($event) . "'";
        }

        if ($country !== 'all') {
            $parts[] = "country = '" . esc_sql($country) . "'";
        }

        return "WHERE " . implode(" AND ", $parts);
    }

    public function track_event(): void
    {
        check_ajax_referer('criaw_track_event', 'nonce');

        $event = isset($_POST['event']) ? sanitize_key(wp_unslash($_POST['event'])) : '';
        $map   = [
            'widget_open' => 'widget_open',
            'show_form'   => 'show_form',
            'call_click'  => 'call_click',
            'text_click'  => 'text_click',
            'form_submit' => 'form_submit',
        ];

        if (! isset($map[$event])) {
            wp_send_json_error(['message' => __('Unknown tracking event.', 'carbon-repro-widget')], 400);
        }

        $stats = $this->get_stats();
        $stats[$map[$event]]++;
        update_option(self::STATS_OPTION_KEY, $stats, false);

        // Advanced Logging
        $attr = [];
        foreach ($_POST as $key => $val) {
            if (strpos($key, 'attr_') === 0 || strpos($key, 'first_') === 0) {
                $attr[str_replace(['attr_attr_', 'attr_'], ['', ''], $key)] = sanitize_text_field($val);
            }
        }
        
        $this->log_event($map[$event], $attr);

        wp_send_json_success(['stats' => $stats]);
    }

    private function log_event(string $event_type, array $attr = []): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        $ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ua_data  = $this->parse_user_agent($ua);
        $ip       = $this->get_ip_address();
        $geo      = $this->get_geo_data_from_ip($ip);
        
        $insert_data = [
            'event_type' => $event_type,
            'user_id'    => get_current_user_id() ?: null,
            'ip_address' => $ip,
            'country'    => $geo['country'],
            'region'     => $geo['region'],
            'city'       => $geo['city'],
            'isp'        => $geo['isp'],
            'browser'    => $ua_data['browser'],
            'os'         => $ua_data['os'],
            'device_type' => $ua_data['device'],
            'user_agent' => $ua,
            'page_url'   => esc_url_raw($_SERVER['HTTP_REFERER'] ?? ''),
            'created_at' => current_time('mysql'),
        ];

        // Map Attribution Data
        $field_map = [
            'attr_utm_source'   => 'utm_source',
            'attr_utm_medium'   => 'utm_medium',
            'attr_utm_campaign' => 'utm_campaign',
            'attr_utm_term'     => 'utm_term',
            'attr_utm_content'  => 'utm_content',
            'attr_gclid'        => 'gclid',
            'attr_fbclid'       => 'fbclid',
            'attr_referrer'     => 'referrer_url',
            'attr_landing'      => 'landing_page',
            'first_utm_source'  => 'first_utm_source',
            'first_referrer'    => 'first_referrer',
            'first_landing'     => 'first_landing_page',
            'first_at'          => 'first_visit_at',
        ];

        foreach ($field_map as $post_key => $db_key) {
            $val = $_POST[$post_key] ?? ($_POST['attr_' . $post_key] ?? '');
            if ($val) {
                $insert_data[$db_key] = sanitize_text_field($val);
            }
        }

        // Apply Normalization
        $classification = $this->classify_traffic($insert_data);
        $insert_data['traffic_category']  = $classification['category'];
        $insert_data['normalized_source'] = $classification['source'];

        $wpdb->insert($table_name, $insert_data);
    }

    private function classify_traffic(array $data): array
    {
        $source   = $data['utm_source'] ?? '';
        $medium   = $data['utm_medium'] ?? '';
        $referrer = $data['referrer_url'] ?? '';
        $gclid    = $data['gclid'] ?? '';
        $fbclid   = $data['fbclid'] ?? '';

        $category = 'Direct';
        $norm_source = 'Direct';

        if ($referrer) {
            $host = parse_url($referrer, PHP_URL_HOST);
            $norm_source = preg_replace('/^www\./', '', $host);
            $category = 'Referral';
        }

        if ($source) {
            $norm_source = ucfirst($source);
        }

        // Normalization Mapping
        $source_map = [
            'google'    => 'Google',
            'facebook'  => 'Facebook',
            'fb'        => 'Facebook',
            'instagram' => 'Instagram',
            'ig'        => 'Instagram',
            'linkedin'  => 'LinkedIn',
            'tiktok'    => 'TikTok',
            'snapchat'  => 'Snapchat',
            'pinterest' => 'Pinterest',
            'youtube'   => 'YouTube',
            'twitter'   => 'Twitter',
            't.co'      => 'Twitter',
            'bing'      => 'Bing',
        ];

        foreach ($source_map as $key => $val) {
            if (stripos($norm_source, $key) !== false) {
                $norm_source = $val;
                break;
            }
        }

        // Categorization Rules
        if ($gclid || in_array(strtolower($medium), ['cpc', 'ppc', 'paidsearch'])) {
            $category = 'Paid Search';
        } elseif ($fbclid || in_array(strtolower($medium), ['paid-social', 'social-cpc'])) {
            $category = 'Paid Social';
        } elseif (in_array(strtolower($medium), ['social', 'social-network', 'sm'])) {
            $category = 'Organic Social';
        } elseif ($medium === 'organic' || preg_match('/google|bing|yahoo|duckduckgo|baidu/i', $norm_source)) {
            if ($category !== 'Paid Search') $category = 'Organic Search';
        } elseif ($medium === 'email') {
            $category = 'Email';
        } elseif (in_array($norm_source, ['TikTok', 'Snapchat', 'Pinterest', 'Instagram'])) {
            if ($category !== 'Paid Social') $category = 'Organic Social';
        }

        // Refine Social Category if not set and source is social
        if ($category === 'Referral' && in_array($norm_source, ['Facebook', 'Instagram', 'LinkedIn', 'Twitter', 'YouTube', 'TikTok', 'Snapchat', 'Pinterest'])) {
            $category = 'Organic Social';
        }

        return ['category' => $category, 'source' => $norm_source];
    }

    private function retroactively_classify_data(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        // Only run if there are rows without a category
        $count = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE traffic_category IS NULL OR traffic_category = ''");
        if (! $count) {
            return;
        }

        $rows = $wpdb->get_results("SELECT * FROM $table_name WHERE traffic_category IS NULL OR traffic_category = '' LIMIT 500");
        foreach ($rows as $row) {
            $classification = $this->classify_traffic([
                'utm_source'   => $row->utm_source,
                'utm_medium'   => $row->utm_medium,
                'referrer_url' => $row->referrer_url,
                'gclid'        => $row->gclid,
                'fbclid'       => $row->fbclid,
            ]);

            $wpdb->update(
                $table_name,
                [
                    'traffic_category'  => $classification['category'],
                    'normalized_source' => $classification['source'],
                ],
                ['id' => $row->id]
            );
        }
    }

    private function get_ip_address(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (! empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        return sanitize_text_field($ip);
    }

    private function get_geo_data_from_ip(string $ip): array
    {
        $default = ['country' => 'Unknown', 'region' => 'Unknown', 'city' => 'Unknown', 'isp' => 'Unknown'];
        
        if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '192.168.')) {
            return array_merge($default, ['country' => 'Localhost']);
        }

        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,country,regionName,city,isp");
        if (is_wp_error($response)) {
            return $default;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (($data['status'] ?? '') !== 'success') {
            return $default;
        }

        return [
            'country' => $data['country'] ?? 'Unknown',
            'region'  => $data['regionName'] ?? 'Unknown',
            'city'    => $data['city'] ?? 'Unknown',
            'isp'     => $data['isp'] ?? 'Unknown',
        ];
    }

    private function parse_user_agent(string $ua): array
    {
        $browser = 'Unknown';
        $os      = 'Unknown';
        $device  = 'Desktop';

        // OS Detection
        if (preg_match('/windows|win32/i', $ua)) { $os = 'Windows'; }
        elseif (preg_match('/macintosh|mac os x/i', $ua)) { $os = 'macOS'; }
        elseif (preg_match('/linux/i', $ua)) { $os = 'Linux'; }
        elseif (preg_match('/iphone|ipad|ipod/i', $ua)) { $os = 'iOS'; }
        elseif (preg_match('/android/i', $ua)) { $os = 'Android'; }

        // Browser Detection
        if (preg_match('/edg/i', $ua)) { $browser = 'Edge'; }
        elseif (preg_match('/chrome/i', $ua)) { $browser = 'Chrome'; }
        elseif (preg_match('/safari/i', $ua)) { $browser = 'Safari'; }
        elseif (preg_match('/firefox/i', $ua)) { $browser = 'Firefox'; }
        elseif (preg_match('/opera|opr/i', $ua)) { $browser = 'Opera'; }

        // Device Type Detection
        if (preg_match('/tablet|ipad|playbook|silk/i', $ua)) {
            $device = 'Tablet';
        } elseif (preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|opera mobi|skyfire|maemo|windows phone|palm|iemobile|symbian|symbos|series60/i', $ua)) {
            $device = 'Mobile';
        }

        return ['browser' => $browser, 'os' => $os, 'device' => $device];
    }

    private function render_form_content(int $form_id): void
    {
        if ($form_id > 0 && shortcode_exists('wpforms')) {
            echo do_shortcode(sprintf('[wpforms id="%d"]', $form_id));
            return;
        }

        echo '<div class="watch-form-fallback">';
        echo '<p><strong>' . esc_html__('WPForms is not configured yet.', 'carbon-repro-widget') . '</strong></p>';
        echo '<p>' . esc_html__('Install WPForms and add a form ID in Settings > Carbon Repro Widget.', 'carbon-repro-widget') . '</p>';
        echo '</div>';
    }

    private function get_setting(string $key, string $default = ''): string
    {
        $settings = wp_parse_args(get_option(self::OPTION_KEY, []), $this->get_default_settings());
        $value    = $settings[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    private function get_stats(): array
    {
        $defaults = [
            'widget_open' => 0,
            'show_form'   => 0,
            'call_click'  => 0,
            'text_click'  => 0,
            'form_submit' => 0,
        ];

        $stats = get_option(self::STATS_OPTION_KEY, []);

        return wp_parse_args(is_array($stats) ? $stats : [], $defaults);
    }

    private function get_default_settings(): array
    {
        return [
            'label_text'   => 'Start Growing Today',
            'menu_title'   => 'Instant Actions',
            'form_title'   => 'Get Your Strategy',
            'footer_brand' => 'CarbonRepro',
            'phone_number' => '7137056097',
            'wpforms_id'   => '46362',
        ];
    }
    public static function activate(): void
    {
        self::create_tables();
    }

    private static function create_tables(): void
    {
        global $wpdb;
        $table_name      = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(50) NOT NULL,
            user_id BIGINT(20) DEFAULT NULL,
            ip_address VARCHAR(45) NOT NULL,
            country VARCHAR(100) DEFAULT 'Unknown',
            region VARCHAR(100) DEFAULT 'Unknown',
            city VARCHAR(100) DEFAULT 'Unknown',
            isp VARCHAR(150) DEFAULT 'Unknown',
            browser VARCHAR(50) DEFAULT 'Unknown',
            os VARCHAR(50) DEFAULT 'Unknown',
            user_agent TEXT,
            page_url TEXT,
            landing_page TEXT,
            referrer_url TEXT,
            utm_source VARCHAR(100),
            utm_medium VARCHAR(100),
            utm_campaign VARCHAR(100),
            utm_term VARCHAR(100),
            utm_content VARCHAR(100),
            gclid VARCHAR(255),
            fbclid VARCHAR(255),
            first_utm_source VARCHAR(100),
            first_referrer TEXT,
            first_landing_page TEXT,
            first_visit_at DATETIME,
            normalized_source VARCHAR(100),
            traffic_category VARCHAR(50),
            device_type VARCHAR(20) DEFAULT 'Desktop',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

register_activation_hook(__FILE__, ['Carbon_Repro_Instant_Actions_Widget', 'activate']);
new Carbon_Repro_Instant_Actions_Widget();
