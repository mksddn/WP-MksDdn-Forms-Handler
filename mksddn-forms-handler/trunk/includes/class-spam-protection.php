<?php
/**
 * @file: class-spam-protection.php
 * @description: Spam protection — global rate limit, heuristics, Turnstile verification
 * @dependencies: WordPress core
 * @created: 2026-08-29
 */

namespace MksDdn\FormsHandler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles spam protection for form submissions
 */
class SpamProtection {

    /**
     * Internal request fields excluded from heuristics and field filtering helpers
     *
     * @var string[]
     */
    private const INTERNAL_FIELDS = [
        'mksddn_fh_hp',
        'mksddn_fh_turnstile_response',
        'cf-turnstile-response',
        'form_nonce',
        'action',
        'form_id',
        '_wp_http_referer',
    ];

    /**
     * Get client IP address
     *
     * Uses REMOTE_ADDR only. Behind Cloudflare/a reverse proxy, restore the real
     * visitor IP at the server (or via this filter) — do not trust X-Forwarded-For.
     */
    public static function get_client_ip(): string {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        /**
         * Filter detected client IP used for rate limiting and Turnstile.
         *
         * @param string $ip Sanitized REMOTE_ADDR (or "unknown")
         */
        $filtered = apply_filters('mksddn_fh_client_ip', $ip);
        if (!is_string($filtered) || $filtered === '') {
            return $ip;
        }

        return sanitize_text_field($filtered);
    }

    /**
     * Whether a POST/JSON key is an internal plugin field
     */
    public static function is_internal_field(string $name): bool {
        return in_array($name, self::INTERNAL_FIELDS, true);
    }

    /**
     * Whether global rate limiting is enabled
     */
    public static function is_global_rate_limit_enabled(): bool {
        return get_option('mksddn_fh_global_rate_limit_enabled', '0') === '1';
    }

    /**
     * Whether global spam heuristics are enabled
     */
    public static function is_global_heuristics_enabled(): bool {
        return get_option('mksddn_fh_spam_heuristics_enabled', '0') === '1';
    }

    /**
     * Whether Turnstile is required for a form
     *
     * @param array $form_config Cached form configuration
     */
    public static function is_turnstile_required(array $form_config): bool {
        if (($form_config['require_turnstile'] ?? '0') !== '1') {
            return false;
        }

        return self::are_turnstile_keys_configured();
    }

    /**
     * Whether both Turnstile site key and secret key are saved
     */
    public static function are_turnstile_keys_configured(): bool {
        return self::get_turnstile_site_key() !== '' && self::get_turnstile_secret_key() !== '';
    }

    /**
     * Whether spam heuristics are enabled for a form
     *
     * @param array $form_config Cached form configuration
     */
    public static function is_heuristics_enabled(array $form_config): bool {
        $mode = (string) ($form_config['spam_heuristics'] ?? 'inherit');

        if ($mode === 'on') {
            return true;
        }

        if ($mode === 'off') {
            return false;
        }

        return self::is_global_heuristics_enabled();
    }

    /**
     * Get Turnstile site key
     */
    public static function get_turnstile_site_key(): string {
        return (string) get_option('mksddn_fh_turnstile_site_key', '');
    }

    /**
     * Get Turnstile secret key
     */
    public static function get_turnstile_secret_key(): string {
        return (string) get_option('mksddn_fh_turnstile_secret_key', '');
    }

    /**
     * Strip internal/meta fields from submission payload
     *
     * @param array $data Raw submission data
     * @return array
     */
    public static function strip_internal_fields(array $data): array {
        foreach (self::INTERNAL_FIELDS as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /**
     * Extract Turnstile token from request parameters
     *
     * @param array $params Request body or $_POST data
     */
    public static function extract_turnstile_token(array $params): string {
        foreach (['mksddn_fh_turnstile_response', 'cf-turnstile-response'] as $key) {
            if (!empty($params[$key]) && is_string($params[$key])) {
                return sanitize_text_field($params[$key]);
            }
        }

        return '';
    }

    /**
     * Validate global rate limit, Turnstile, and heuristics before processing
     *
     * @param array  $form_config Form configuration
     * @param array  $params      Raw request parameters (including internal fields)
     * @param string $form_slug   Form slug for logging
     * @return \WP_Error|true
     */
    public static function validate_pre_submission(array $form_config, array $params, string $form_slug = ''): \WP_Error|true {
        $rate_check = self::check_global_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Count this attempt before outbound Turnstile verification.
        self::record_submission_attempt();

        if (self::is_turnstile_required($form_config)) {
            $token = self::extract_turnstile_token($params);
            if ($token === '') {
                return new \WP_Error(
                    'turnstile_required',
                    __('Captcha verification is required.', 'mksddn-forms-handler'),
                    ['status' => 400]
                );
            }

            $turnstile_check = self::verify_turnstile($token);
            if (is_wp_error($turnstile_check)) {
                return $turnstile_check;
            }
        }

        if (self::is_heuristics_enabled($form_config)) {
            $heuristics_check = self::check_heuristics(
                self::strip_internal_fields($params),
                $form_config
            );
            if (is_wp_error($heuristics_check)) {
                return $heuristics_check;
            }
        }

        return true;
    }

    /**
     * Record a submission attempt for the global rate limit counter
     */
    public static function record_submission_attempt(): void {
        if (!self::is_global_rate_limit_enabled()) {
            return;
        }

        $max = max(1, (int) get_option('mksddn_fh_global_rate_limit_max', 20));
        $window = max(60, (int) get_option('mksddn_fh_global_rate_limit_window', 3600));
        $ip = self::get_client_ip();
        $key = 'mksddn_fh_global_rl_' . md5($ip);

        $timestamps = get_transient($key);
        if (!is_array($timestamps)) {
            $timestamps = [];
        }

        $now = time();
        $timestamps = array_values(array_filter(
            $timestamps,
            static function ($timestamp) use ($now, $window): bool {
                if (!is_numeric($timestamp)) {
                    return false;
                }
                $timestamp = (int) $timestamp;
                return $timestamp > 0 && ($now - $timestamp) < $window;
            }
        ));

        $timestamps[] = $now;
        set_transient($key, $timestamps, $window);

        if (count($timestamps) > $max) {
            // Keep the array bounded if something bypassed the pre-check.
            $timestamps = array_slice($timestamps, -$max);
            set_transient($key, $timestamps, $window);
        }
    }

    /**
     * Run before_submit filter after validation
     *
     * @param array $filtered_form_data Sanitized submission data
     * @param array $form_config        Form configuration
     * @return \WP_Error|true
     */
    public static function apply_before_submit_filter(array $filtered_form_data, array $form_config): \WP_Error|true {
        /**
         * Filter submission before delivery channels run.
         *
         * Return WP_Error to reject with a custom message, or false to block silently.
         *
         * @param bool|true|\WP_Error $allowed           Whether submission may continue
         * @param array               $filtered_form_data Sanitized submission data
         * @param array               $form_config        Form configuration
         */
        $result = apply_filters('mksddn_fh_before_submit', true, $filtered_form_data, $form_config);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        if ($result === false) {
            return new \WP_Error(
                'submission_blocked',
                __('Submission blocked.', 'mksddn-forms-handler'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Check global rate limit for current IP
     *
     * @return \WP_Error|true
     */
    public static function check_global_rate_limit(): \WP_Error|true {
        if (!self::is_global_rate_limit_enabled()) {
            return true;
        }

        $max = max(1, (int) get_option('mksddn_fh_global_rate_limit_max', 20));
        $window = max(60, (int) get_option('mksddn_fh_global_rate_limit_window', 3600));
        $ip = self::get_client_ip();
        $key = 'mksddn_fh_global_rl_' . md5($ip);

        $timestamps = get_transient($key);
        if (!is_array($timestamps)) {
            return true;
        }

        $now = time();
        $timestamps = array_filter(
            $timestamps,
            static function ($timestamp) use ($now, $window): bool {
                if (!is_numeric($timestamp)) {
                    return false;
                }
                $timestamp = (int) $timestamp;
                return $timestamp > 0 && ($now - $timestamp) < $window;
            }
        );

        if (count($timestamps) >= $max) {
            return new \WP_Error(
                'global_rate_limited',
                __('Too many form submissions. Please try again later.', 'mksddn-forms-handler'),
                ['status' => 429]
            );
        }

        return true;
    }

    /**
     * Verify Cloudflare Turnstile token
     *
     * @param string $token Turnstile response token
     * @return \WP_Error|true
     */
    public static function verify_turnstile(string $token): \WP_Error|true {
        $secret = self::get_turnstile_secret_key();
        if ($secret === '') {
            return new \WP_Error(
                'turnstile_not_configured',
                __('Captcha is not configured.', 'mksddn-forms-handler'),
                ['status' => 500]
            );
        }

        $response = wp_remote_post(
            self::get_turnstile_verify_url(),
            [
                'timeout' => 15,
                'body'    => [
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => self::get_client_ip(),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return new \WP_Error(
                'turnstile_request_failed',
                __('Captcha verification failed. Please try again.', 'mksddn-forms-handler'),
                ['status' => 502]
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['success'])) {
            return new \WP_Error(
                'turnstile_failed',
                __('Captcha verification failed. Please try again.', 'mksddn-forms-handler'),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Run built-in spam heuristics
     *
     * @param array $form_data   Submission data without internal fields
     * @param array $form_config Form configuration
     * @return \WP_Error|true
     */
    public static function check_heuristics(array $form_data, array $form_config): \WP_Error|true {
        if (self::has_gibberish_name($form_data, $form_config)) {
            return new \WP_Error(
                'spam_detected',
                __('Spam detected', 'mksddn-forms-handler'),
                ['status' => 400]
            );
        }

        if (self::has_excessive_multi_select($form_data, $form_config)) {
            return new \WP_Error(
                'spam_detected',
                __('Spam detected', 'mksddn-forms-handler'),
                ['status' => 400]
            );
        }

        /**
         * Final spam decision hook for custom rules.
         *
         * @param bool  $is_spam     Whether submission is spam
         * @param array $form_data   Submission data
         * @param array $form_config Form configuration
         */
        $is_spam = apply_filters('mksddn_fh_is_spam', false, $form_data, $form_config);
        if ($is_spam) {
            return new \WP_Error(
                'spam_detected',
                __('Spam detected', 'mksddn-forms-handler'),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Detect random Latin-only names (common bot pattern)
     *
     * @param array $form_data   Submission data
     * @param array $form_config Form configuration
     */
    private static function has_gibberish_name(array $form_data, array $form_config): bool {
        $name_keys = self::get_name_field_keys($form_config);

        foreach ($name_keys as $key) {
            if (!isset($form_data[$key]) || !is_string($form_data[$key])) {
                continue;
            }

            if (self::is_gibberish_name($form_data[$key])) {
                return true;
            }
        }

        foreach ($form_data as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            if (!preg_match('/name|имя|fio|fname|lname|fullname/i', $key)) {
                continue;
            }

            if (self::is_gibberish_name($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect submissions that select too many options on a multi-select/checkbox field
     *
     * @param array $form_data   Submission data
     * @param array $form_config Form configuration
     */
    private static function has_excessive_multi_select(array $form_data, array $form_config): bool {
        $threshold = (int) apply_filters('mksddn_fh_spam_multi_select_threshold', 7, $form_config);
        if ($threshold < 2) {
            $threshold = 2;
        }

        foreach ($form_data as $key => $value) {
            $field_config = self::find_field_config((string) $key, $form_config);
            if ($field_config === null || !self::is_multi_option_field($field_config)) {
                continue;
            }

            $options = $field_config['options'] ?? [];
            $total = is_array($options) ? count($options) : 0;
            if ($total < $threshold) {
                continue;
            }

            $selected = self::count_selected_options($value, $field_config);
            if ($selected >= $threshold || $selected >= $total) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve configured name-like field keys
     *
     * @param array $form_config Form configuration
     * @return string[]
     */
    private static function get_name_field_keys(array $form_config): array {
        $keys = ['name', 'Name', 'Имя', 'имя', 'your_name', 'full_name', 'fname'];
        $fields = json_decode((string) ($form_config['fields_config'] ?? ''), true);

        if (!is_array($fields)) {
            return $keys;
        }

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $field_name = (string) ($field['name'] ?? '');
            $field_type = (string) ($field['type'] ?? '');
            $label = (string) ($field['label'] ?? '');

            if ($field_name === '' || !in_array($field_type, ['text', 'textarea'], true)) {
                continue;
            }

            if (preg_match('/name|имя|fio|fullname/i', $field_name . ' ' . $label)) {
                $keys[] = $field_name;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Check if a value looks like a bot-generated name
     *
     * Long Latin-only strings alone are not enough — real transliterated names
     * and merged full names can be 15+ letters. Random bot strings additionally
     * tend to be vowel-starved or contain long consonant runs, so both signals
     * are required to reduce false positives on legitimate submissions.
     */
    private static function is_gibberish_name(string $value): bool {
        $value = trim($value);
        if ($value === '' || !preg_match('/^[A-Za-z]{15,}$/', $value)) {
            return false;
        }

        $length = strlen($value);
        $vowel_count = preg_match_all('/[aeiouAEIOU]/', $value);
        $vowel_ratio = $vowel_count / $length;

        if ($vowel_ratio < 0.15) {
            return true;
        }

        return (bool) preg_match('/[b-df-hj-np-tv-xz]{6,}/i', $value);
    }

    /**
     * Whether the field is a multi-select or a checkbox group with options
     *
     * @param array $field_config Field configuration
     */
    private static function is_multi_option_field(array $field_config): bool {
        $type = (string) ($field_config['type'] ?? '');

        if ($type === 'select') {
            $multiple = $field_config['multiple'] ?? false;
            return $multiple === true || $multiple === '1' || $multiple === 1;
        }

        if ($type === 'checkbox') {
            $options = $field_config['options'] ?? [];
            return is_array($options) && count($options) >= 2;
        }

        return false;
    }

    /**
     * Count selected options for a configured multi-option field
     *
     * @param mixed $value        Submitted value
     * @param array $field_config Field configuration
     */
    private static function count_selected_options($value, array $field_config): int {
        $options = $field_config['options'] ?? [];
        if (!is_array($options) || $options === []) {
            return 0;
        }

        $option_values = [];
        foreach ($options as $option) {
            $option_value = is_array($option) ? (string) ($option['value'] ?? $option['label'] ?? '') : (string) $option;
            if ($option_value !== '') {
                $option_values[] = $option_value;
            }
        }

        if ($option_values === []) {
            return 0;
        }

        $submitted = [];
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_scalar($item) && (string) $item !== '') {
                    $submitted[] = (string) $item;
                }
            }
        } elseif (is_string($value) && $value !== '') {
            if (in_array($value, $option_values, true)) {
                return 1;
            }
            $submitted = array_values(array_filter(array_map('trim', explode(',', $value)), static fn($part): bool => $part !== ''));
        } else {
            return 0;
        }

        $selected = 0;
        foreach ($submitted as $item) {
            if (in_array($item, $option_values, true)) {
                $selected++;
            }
        }

        return $selected;
    }

    /**
     * Find field configuration by name
     *
     * @param string $field_name  Field name
     * @param array  $form_config Form configuration
     * @return array|null
     */
    private static function find_field_config(string $field_name, array $form_config): ?array {
        $fields = json_decode((string) ($form_config['fields_config'] ?? ''), true);
        if (!is_array($fields)) {
            return null;
        }

        foreach ($fields as $field) {
            if (is_array($field) && ($field['name'] ?? '') === $field_name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Cloudflare Turnstile siteverify endpoint URL.
     */
    private static function get_turnstile_verify_url(): string {
        $host = implode('.', ['challenges', 'cloudflare', 'com']);

        /**
         * Filters the Cloudflare Turnstile siteverify endpoint URL.
         *
         * @param string $url Siteverify endpoint URL.
         */
        return (string) apply_filters(
            'mksddn_fh_turnstile_verify_url',
            'https://' . $host . '/turnstile/v0/siteverify'
        );
    }

    /**
     * Enqueue Cloudflare Turnstile loader (local script loads the official widget).
     */
    public static function enqueue_turnstile_script(): void {
        if (wp_script_is('mksddn-fh-turnstile-loader', 'enqueued')) {
            return;
        }

        wp_enqueue_script(
            'mksddn-fh-turnstile-loader',
            MKSDDN_FORMS_HANDLER_PLUGIN_URL . 'assets/js/turnstile-loader.js',
            [],
            MKSDDN_FORMS_HANDLER_VERSION,
            [
                'strategy'  => 'async',
                'in_footer' => true,
            ]
        );
    }

    /**
     * Render Turnstile widget markup
     *
     * @param string $form_slug Optional form slug for data attribute
     */
    public static function render_turnstile_widget(string $form_slug = ''): void {
        if (!self::are_turnstile_keys_configured()) {
            echo '<!-- MksDdn Forms Handler: Turnstile keys are not configured -->';
            return;
        }

        $site_key = self::get_turnstile_site_key();

        self::enqueue_turnstile_script();

        $attributes = [
            'class'           => 'mksddn-fh-turnstile cf-turnstile',
            'data-sitekey'    => $site_key,
            'data-action'     => 'mksddn_fh_submit',
            'data-theme'      => 'auto',
        ];

        if ($form_slug !== '') {
            $attributes['data-form-slug'] = sanitize_title($form_slug);
        }

        $html = '<div';
        foreach ($attributes as $name => $value) {
            $html .= ' ' . $name . '="' . esc_attr($value) . '"';
        }
        $html .= '></div>';

        echo wp_kses($html, [
            'div' => [
                'class'          => true,
                'data-sitekey'   => true,
                'data-action'    => true,
                'data-theme'     => true,
                'data-form-slug' => true,
            ],
        ]);
    }
}
