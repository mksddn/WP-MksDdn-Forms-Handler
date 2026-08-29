<?php
/**
 * @file: class-spam-settings-admin.php
 * @description: Global spam protection settings page
 * @dependencies: class-spam-protection.php
 * @created: 2026-08-29
 */

namespace MksDdn\FormsHandler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Spam protection settings admin page
 */
class SpamSettingsAdmin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'save_settings']);
    }

    /**
     * Register submenu under Forms
     */
    public function add_settings_page(): void {
        add_submenu_page(
            'edit.php?post_type=mksddn_fh_forms',
            __('Spam Protection', 'mksddn-forms-handler'),
            __('Spam Protection', 'mksddn-forms-handler'),
            'manage_options',
            'mksddn-fh-spam-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Save settings form
     */
    public function save_settings(): void {
        $nonce = isset($_POST['mksddn_fh_spam_settings_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['mksddn_fh_spam_settings_nonce']))
            : '';

        if ($nonce === '' || !wp_verify_nonce($nonce, 'save_mksddn_fh_spam_settings')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        update_option(
            'mksddn_fh_global_rate_limit_enabled',
            isset($_POST['global_rate_limit_enabled']) ? '1' : '0'
        );

        if (isset($_POST['global_rate_limit_max'])) {
            update_option(
                'mksddn_fh_global_rate_limit_max',
                max(1, (int) wp_unslash($_POST['global_rate_limit_max']))
            );
        }

        if (isset($_POST['global_rate_limit_window'])) {
            update_option(
                'mksddn_fh_global_rate_limit_window',
                max(60, (int) wp_unslash($_POST['global_rate_limit_window']))
            );
        }

        update_option(
            'mksddn_fh_spam_heuristics_enabled',
            isset($_POST['spam_heuristics_enabled']) ? '1' : '0'
        );

        if (isset($_POST['turnstile_site_key'])) {
            update_option(
                'mksddn_fh_turnstile_site_key',
                sanitize_text_field(wp_unslash($_POST['turnstile_site_key']))
            );
        }

        if (isset($_POST['turnstile_secret_key'])) {
            update_option(
                'mksddn_fh_turnstile_secret_key',
                sanitize_text_field(wp_unslash($_POST['turnstile_secret_key'])),
                false
            );
        }

        wp_safe_redirect(
            esc_url_raw(
                admin_url('admin.php?page=mksddn-fh-spam-settings&saved=1')
            )
        );
        exit;
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied', 'mksddn-forms-handler'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['saved']) && sanitize_text_field(wp_unslash($_GET['saved'])) === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__('Settings saved.', 'mksddn-forms-handler')
                . '</p></div>';
        }

        $global_rate_limit_enabled = get_option('mksddn_fh_global_rate_limit_enabled', '0');
        $global_rate_limit_max = (int) get_option('mksddn_fh_global_rate_limit_max', 20);
        $global_rate_limit_window = (int) get_option('mksddn_fh_global_rate_limit_window', 3600);
        $spam_heuristics_enabled = get_option('mksddn_fh_spam_heuristics_enabled', '0');
        $turnstile_site_key = SpamProtection::get_turnstile_site_key();
        $turnstile_secret_key = SpamProtection::get_turnstile_secret_key();
        $turnstile_keys_incomplete = ($turnstile_site_key === '') !== ($turnstile_secret_key === '');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Spam Protection', 'mksddn-forms-handler'); ?></h1>
            <p><?php echo esc_html__('Global anti-spam settings for all forms. Per-form options are available in each form Advanced tab.', 'mksddn-forms-handler'); ?></p>
            <?php if ($turnstile_keys_incomplete) : ?>
                <div class="notice notice-warning"><p><?php echo esc_html__('Turnstile is not active until both the site key and the secret key are saved. Forms that require Turnstile will skip captcha until then.', 'mksddn-forms-handler'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('save_mksddn_fh_spam_settings', 'mksddn_fh_spam_settings_nonce'); ?>

                <h2><?php echo esc_html__('Global rate limit', 'mksddn-forms-handler'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable global rate limit', 'mksddn-forms-handler'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="global_rate_limit_enabled" value="1" <?php checked($global_rate_limit_enabled, '1'); ?> />
                                <?php echo esc_html__('Limit total submissions per IP across all forms', 'mksddn-forms-handler'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Works in addition to the existing per-form limit (1 request per 10 seconds).', 'mksddn-forms-handler'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="global_rate_limit_max"><?php echo esc_html__('Max submissions', 'mksddn-forms-handler'); ?></label></th>
                        <td>
                            <input type="number" min="1" step="1" class="small-text" id="global_rate_limit_max" name="global_rate_limit_max" value="<?php echo esc_attr((string) $global_rate_limit_max); ?>" />
                            <p class="description"><?php echo esc_html__('Maximum submissions per IP within the time window.', 'mksddn-forms-handler'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="global_rate_limit_window"><?php echo esc_html__('Time window (seconds)', 'mksddn-forms-handler'); ?></label></th>
                        <td>
                            <input type="number" min="60" step="60" class="small-text" id="global_rate_limit_window" name="global_rate_limit_window" value="<?php echo esc_attr((string) $global_rate_limit_window); ?>" />
                            <p class="description"><?php echo esc_html__('Default: 3600 (1 hour).', 'mksddn-forms-handler'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Spam heuristics', 'mksddn-forms-handler'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable heuristics globally', 'mksddn-forms-handler'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="spam_heuristics_enabled" value="1" <?php checked($spam_heuristics_enabled, '1'); ?> />
                                <?php echo esc_html__('Detect bot patterns (random Latin names, all checkbox options selected)', 'mksddn-forms-handler'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Forms can override this in Advanced settings (inherit / on / off).', 'mksddn-forms-handler'); ?></p>
                            <p class="description"><?php echo esc_html__('Heuristic-based: may rarely misfire on unusual legitimate input. Disable per form if this happens.', 'mksddn-forms-handler'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Cloudflare Turnstile', 'mksddn-forms-handler'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="turnstile_site_key"><?php echo esc_html__('Site key', 'mksddn-forms-handler'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text code" id="turnstile_site_key" name="turnstile_site_key" value="<?php echo esc_attr($turnstile_site_key); ?>" autocomplete="off" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="turnstile_secret_key"><?php echo esc_html__('Secret key', 'mksddn-forms-handler'); ?></label></th>
                        <td>
                            <input type="password" class="regular-text code" id="turnstile_secret_key" name="turnstile_secret_key" value="<?php echo esc_attr($turnstile_secret_key); ?>" autocomplete="new-password" />
                            <p class="description"><?php echo esc_html__('Enable Turnstile per form in Advanced settings. For custom REST forms, send cf-turnstile-response or mksddn_fh_turnstile_response in the request body.', 'mksddn-forms-handler'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'mksddn-forms-handler')); ?>
            </form>
        </div>
        <?php
    }
}
