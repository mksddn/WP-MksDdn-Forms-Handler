<?php
/**
 * @file: class-forms-handler.php
 * @description: Main forms handler class with REST API support
 * @dependencies: WordPress core, REST API
 * @created: 2025-07-30
 */

namespace MksDdn\FormsHandler;

/**
 * Main forms handler class
 */
class FormsHandler {
    
    /**
     * Cache for form configurations
     * @var array
     */
    private $form_cache = [];
    
    /**
     * Cache TTL in seconds
     * @var int
     */
    private const CACHE_TTL = 3600; // 1 hour
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_post_submit_form', [$this, 'handle_form_submission']);
        add_action('admin_post_nopriv_submit_form', [$this, 'handle_form_submission']);
        
        // Clear cache when form is updated
        add_action('save_post_mksddn_fh_forms', [$this, 'clear_form_cache'], 10, 2);
        add_action('deleted_post', [$this, 'clear_form_cache'], 10, 2);
    }
    
    /**
     * Register REST routes
     */
    public function register_rest_routes(): void {
        register_rest_route(
            'mksddn-forms-handler/v1',
            '/forms/(?P<slug>[a-zA-Z0-9-]+)/submit',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_rest_form_submission'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'slug' => [
                        'validate_callback' => fn($param): bool => !empty($param),
                    ],
                ],
            ]
        );

        // Public list of forms in the same namespace
        register_rest_route(
            'mksddn-forms-handler/v1',
            '/forms',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_rest_get_forms'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'per_page' => [
                        'validate_callback' => fn($param): bool => is_numeric($param) && (int)$param >= 1 && (int)$param <= 100,
                    ],
                    'page' => [
                        'validate_callback' => fn($param): bool => is_numeric($param) && (int)$param >= 1,
                    ],
                    'search' => [
                        'validate_callback' => fn($param): bool => is_string($param),
                    ],
                ],
            ]
        );

        // Public single form meta in the same namespace
        register_rest_route(
            'mksddn-forms-handler/v1',
            '/forms/(?P<slug>[a-zA-Z0-9-]+)',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_rest_get_form'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'slug' => [
                        'validate_callback' => fn($param): bool => !empty($param),
                    ],
                ],
            ]
        );
    }
    
    /**
     * Handle REST form submission
     */
    public function handle_rest_form_submission($request): \WP_Error|\WP_REST_Response {
        $slug = $request->get_param('slug');
        $form_data = $request->get_json_params();

        // Honeypot field check (should be empty)
        $honeypot = $request->get_param('mksddn_fh_hp');
        if (!empty($honeypot)) {
            return new \WP_Error('spam_detected', __( 'Spam detected', 'mksddn-forms-handler' ), ['status' => 400]);
        }

        // Simple rate limiting: 1 request per 10 seconds per IP per form
        $ip = sanitize_text_field( wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown') );
        $rl_key = 'mksddn_fh_rate_' . md5($slug . '|' . $ip);
        $last_ts = get_transient($rl_key);
        if ($last_ts && (time() - (int)$last_ts) < 10) {
            return new \WP_Error('rate_limited', __( 'Too many requests. Please wait a few seconds.', 'mksddn-forms-handler' ), ['status' => 429]);
        }
        set_transient($rl_key, time(), 15);

        if (!$form_data) {
            return new \WP_Error('invalid_data', __( 'Invalid form data', 'mksddn-forms-handler' ), ['status' => 400]);
        }

        // Check data size (protection against too large requests)
        if (count($form_data) > 50) {
            return new \WP_Error('too_many_fields', __( 'Too many form fields submitted', 'mksddn-forms-handler' ), ['status' => 400]);
        }

        // Check total data size
        $total_size = 0;
        foreach ($form_data as $key => $value) {
            $total_size += strlen((string)$key) + strlen((string)$value);
        }

        if ($total_size > 100000) { // Maximum 100KB total data
            return new \WP_Error('data_too_large', __( 'Form data is too large', 'mksddn-forms-handler' ), ['status' => 400]);
        }

        $result = $this->process_form_submission($slug, $form_data);

        if (is_wp_error($result)) {
            $response_data = [
                'success' => false,
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
                'status'  => $result->get_error_data()['status'] ?? 500,
            ];

            // Add additional information for security errors
            if ($result->get_error_code() === 'unauthorized_fields') {
                $response_data['unauthorized_fields'] = $result->get_error_data()['unauthorized_fields'] ?? [];
                $response_data['allowed_fields'] = $result->get_error_data()['allowed_fields'] ?? [];
            }

            // Add delivery results for send errors
            if ($result->get_error_code() === 'send_error') {
                $response_data['delivery_results'] = $result->get_error_data()['delivery_results'] ?? [];
            }

            return new \WP_REST_Response($response_data, $response_data['status']);
        }

        return new \WP_REST_Response($result, 200);
    }

    /**
     * Get public list of forms
     *
     * @param \WP_REST_Request $request Request instance
     * @return \WP_REST_Response
     */
    public function handle_rest_get_forms($request): \WP_REST_Response {
        $per_page = (int) ($request->get_param('per_page') ?? 10);
        if ($per_page < 1) {
            $per_page = 10;
        }
        if ($per_page > 100) {
            $per_page = 100;
        }
        $page = (int) ($request->get_param('page') ?? 1);
        if ($page < 1) {
            $page = 1;
        }
        $search = $request->get_param('search');
        $search = is_string($search) ? sanitize_text_field( wp_unslash($search) ) : '';

        $args = [
            'post_type'      => 'mksddn_fh_forms',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            's'              => $search,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ];

        $query = new \WP_Query($args);
        $form_ids = $query->posts;

        $items = [];
        foreach ($form_ids as $form_id) {
            $post = get_post($form_id);
            if (!$post) {
                continue;
            }
            $items[] = [
                'id'         => $post->ID,
                'slug'       => $post->post_name,
                'title'      => $post->post_title,
                'submit_url' => rest_url('mksddn-forms-handler/v1/forms/' . $post->post_name . '/submit'),
            ];
        }

        $response = new \WP_REST_Response($items, 200);
        $response->header('X-WP-Total', (string) $query->found_posts);
        $total_pages = $per_page > 0 ? (int) ceil($query->found_posts / $per_page) : 1;
        $response->header('X-WP-TotalPages', (string) $total_pages);

        return $response;
    }

    /**
     * Get single form public meta by slug
     *
     * @param \WP_REST_Request $request Request instance
     * @return \WP_REST_Response
     */
    public function handle_rest_get_form($request): \WP_REST_Response {
        $slug = $request->get_param('slug');
        $slug = is_string($slug) ? sanitize_title($slug) : '';
        if ($slug === '') {
            return new \WP_REST_Response(['message' => 'Invalid slug'], 400);
        }

        $post = get_page_by_path($slug, OBJECT, 'mksddn_fh_forms');
        if (!$post) {
            return new \WP_REST_Response(['message' => 'Form not found'], 404);
        }

        // Read and sanitize fields configuration preserving arbitrary attributes
        $raw_fields_config = get_post_meta($post->ID, '_fields_config', true);
        $decoded_fields = json_decode((string) $raw_fields_config, true);
        $sanitized_fields = [];

        if (is_array($decoded_fields)) {
            foreach ($decoded_fields as $item) {
                if (!is_array($item)) { continue; }
                $sanitized_item = $this->sanitize_field_item_public($item);
                if (!empty($sanitized_item) && !empty($sanitized_item['name'])) {
                    $sanitized_fields[] = $sanitized_item;
                }
            }
        }

        $data = [
            'id'         => $post->ID,
            'slug'       => $post->post_name,
            'title'      => $post->post_title,
            'submit_url' => rest_url('mksddn-forms-handler/v1/forms/' . $post->post_name . '/submit'),
            'fields'     => $sanitized_fields,
        ];

        return new \WP_REST_Response($data, 200);
    }

    /**
     * Sanitize a single field item for public output while preserving arbitrary attributes.
     *
     * - Ensures `name` and `type` are safe slugs
     * - Recursively sanitizes strings inside arrays/objects
     * - Keeps booleans and numbers as-is
     *
     * @param array $item Raw field item from meta
     * @return array Sanitized field item suitable for REST output
     */
    private function sanitize_field_item_public(array $item): array {
        $result = [];

        foreach ($item as $key => $value) {
            // Keep original keys to allow custom attributes (e.g., file rules)
            if ($key === 'name') {
                $result['name'] = sanitize_key((string) $value);
                continue;
            }
            if ($key === 'type') {
                $result['type'] = sanitize_key((string) $value);
                continue;
            }
            if ($key === 'label' || $key === 'description' || $key === 'placeholder' || $key === 'help') {
                $result[$key] = is_string($value) ? sanitize_text_field($value) : $this->sanitize_any_public($value);
                continue;
            }
            if ($key === 'options' && is_array($value)) {
                $result['options'] = $this->sanitize_options_public($value);
                continue;
            }
            // For any other key, sanitize value recursively
            $result[$key] = $this->sanitize_any_public($value);
        }

        // Ensure name exists and is not empty; otherwise drop the field
        if (!isset($result['name']) || $result['name'] === '') {
            return [];
        }

        return $result;
    }

    /**
     * Recursively sanitize arbitrary values for public output.
     * Strings: sanitize_text_field
     * Numbers/booleans: keep type
     * Arrays: sanitize each item recursively
     *
     * @param mixed $value Raw value
     * @return mixed Sanitized value
     */
    private function sanitize_any_public($value) {
        if (is_string($value)) {
            return sanitize_text_field($value);
        }
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $k => $v) {
                $sanitized[$k] = $this->sanitize_any_public($v);
            }
            return $sanitized;
        }
        // Fallback: cast to string and sanitize
        return sanitize_text_field((string) $value);
    }

    /**
     * Sanitize options: accepts ["a","b"] or [{"value":"a","label":"A"}]
     * Returns array of strings or array of objects with {value,label}
     *
     * @param array $options Raw options
     * @return array Sanitized options
     */
    private function sanitize_options_public(array $options): array {
        $result = [];
        foreach ($options as $opt) {
            if (is_array($opt)) {
                $val = isset($opt['value']) ? sanitize_text_field((string) $opt['value']) : '';
                $lab = isset($opt['label']) ? sanitize_text_field((string) $opt['label']) : $val;
                if ($val !== '') {
                    $result[] = ['value' => $val, 'label' => $lab];
                }
            } else {
                $val = sanitize_text_field((string) $opt);
                if ($val !== '') {
                    $result[] = $val; // Keep as simple string for lightweight configs
                }
            }
        }
        return $result;
    }
    
    /**
     * Handle form submission
     */
    public function handle_form_submission(): void {
        // Check nonce for security
        $form_nonce_value = isset($_POST['form_nonce']) ? sanitize_text_field( wp_unslash($_POST['form_nonce']) ) : '';
        if (!$form_nonce_value || !wp_verify_nonce( $form_nonce_value, 'submit_form_nonce')) {
            wp_die('Security check failed');
        }

        $form_id = isset($_POST['form_id']) ? sanitize_text_field( wp_unslash($_POST['form_id']) ) : '';

        // Honeypot check
        $honeypot = isset($_POST['mksddn_fh_hp']) ? sanitize_text_field( wp_unslash($_POST['mksddn_fh_hp']) ) : '';
        if (!empty($honeypot)) {
            wp_die('Spam detected');
        }

        // Simple rate limiting per IP+form: 1 request per 10 seconds
        $ip = sanitize_text_field( wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown') );
        $rl_key = 'mksddn_fh_rate_' . md5($form_id . '|' . $ip);
        $last_ts = get_transient($rl_key);
        if ($last_ts && (time() - (int)$last_ts) < 10) {
            wp_die('Too many requests. Please wait a few seconds.');
        }
        set_transient($rl_key, time(), 15);

        // Build form data using whitelist from form configuration
        $form_config = $this->get_form_config($form_id);
        if (is_wp_error($form_config)) {
            wp_die( esc_html( $form_config->get_error_message() ) );
        }
        $allowed_fields = $this->get_allowed_fields($form_config['fields_config']);
        $form_data = [];
        foreach ($allowed_fields as $field_name) {
            if (isset($_POST[$field_name])) {
                // Raw input is unslashed first, then sanitized below
                $value = wp_unslash($_POST[$field_name]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $form_data[$field_name] = is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field((string)$value);
            }
        }

        if (!$form_id || !$form_data) {
            wp_die('Invalid form data');
        }

        $result = $this->process_form_submission($form_id, $form_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * Process form submission with optimized performance
     */
    private function process_form_submission($form_id, $form_data): \WP_Error|true|array {
        // Get cached form configuration
        $form_config = $this->get_form_config($form_id);
        
        if (is_wp_error($form_config)) {
            return $form_config;
        }

        // Filter form data
        $filtered_form_data = $this->filter_form_data($form_data, $form_config['fields_config']);

        // Check filtering result
        if (is_wp_error($filtered_form_data)) {
            $unauthorized_fields = $this->get_unauthorized_fields($form_data, $form_config['fields_config']);
            return new \WP_Error(
                'unauthorized_fields',
                sprintf( /* translators: %s: field names */ __( 'Unauthorized fields detected: %s', 'mksddn-forms-handler' ), implode(', ', $unauthorized_fields) ),
                [
                    'status'              => 400,
                    'unauthorized_fields' => $unauthorized_fields,
                    'allowed_fields'      => $this->get_allowed_fields($form_config['fields_config']),
                ]
            );
        }

        // Validate data
        $validation_result = $this->validate_form_data($filtered_form_data, $form_config['fields_config']);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Initialize delivery results
        $delivery_results = [
            'email'         => [
                'success' => false,
                'error'   => null,
            ],
            'telegram'      => [
                'success' => false,
                'error'   => null,
                'enabled' => false,
            ],
            'google_sheets' => [
                'success' => false,
                'error'   => null,
                'enabled' => false,
            ],
            'admin_storage' => [
                'success' => false,
                'error'   => null,
                'enabled' => false,
            ],
        ];

        // Prepare and send email
        $email_result = $this->prepare_and_send_email(
            $form_config['recipients'], 
            $form_config['bcc_recipient'], 
            $form_config['subject'], 
            $filtered_form_data, 
            $form_config['form_title']
        );
        $delivery_results['email']['success'] = !is_wp_error($email_result);
        if (is_wp_error($email_result)) {
            $delivery_results['email']['error'] = $email_result->get_error_message();
        }

        // Send to Telegram
        if ($form_config['send_to_telegram'] && $form_config['telegram_bot_token'] && $form_config['telegram_chat_ids']) {
            $delivery_results['telegram']['enabled'] = true;
            $telegram_result = \MksDdn\FormsHandler\TelegramHandler::send_message(
                $form_config['telegram_bot_token'], 
                $form_config['telegram_chat_ids'], 
                $filtered_form_data, 
                $form_config['form_title']
            );
            $delivery_results['telegram']['success'] = !is_wp_error($telegram_result);
            if (is_wp_error($telegram_result)) {
                $delivery_results['telegram']['error'] = $telegram_result->get_error_message();
            }
        }

        // Send to Google Sheets
        if ($form_config['send_to_sheets'] && $form_config['sheets_spreadsheet_id']) {
            $delivery_results['google_sheets']['enabled'] = true;
            $sheets_result = \MksDdn\FormsHandler\GoogleSheetsHandler::send_data(
                $form_config['sheets_spreadsheet_id'],
                $form_config['sheets_sheet_name'],
                $filtered_form_data
            );
            $delivery_results['google_sheets']['success'] = !is_wp_error($sheets_result);
            if (is_wp_error($sheets_result)) {
                $delivery_results['google_sheets']['error'] = $sheets_result->get_error_message();
            }
        }

        // Save to admin if enabled
        if ($form_config['save_to_admin']) {
            $save_result = $this->save_submission($form_config['form_id'], $filtered_form_data, $form_config['form_title']);
            $delivery_results['admin_storage']['enabled'] = true;
            $delivery_results['admin_storage']['success'] = !is_wp_error($save_result);
            if (is_wp_error($save_result)) {
                $delivery_results['admin_storage']['error'] = $save_result->get_error_message();
            }
        }

        // Log submission
        $this->log_form_submission($form_config['form_id'], true);

        // Check if at least one delivery method succeeded
        $any_success = $delivery_results['email']['success'] ||
                      ($delivery_results['telegram']['enabled'] && $delivery_results['telegram']['success']) ||
                      ($delivery_results['google_sheets']['enabled'] && $delivery_results['google_sheets']['success']) ||
                      ($delivery_results['admin_storage']['enabled'] && $delivery_results['admin_storage']['success']);

        if (!$any_success) {
            return new \WP_Error(
                'send_error',
                __( 'Failed to deliver form submission', 'mksddn-forms-handler' ),
                [
                    'status'            => 500,
                    'delivery_results'  => $delivery_results,
                ]
            );
        }

        return [
            'success'           => true,
            'message'          => __( 'Form submitted successfully', 'mksddn-forms-handler' ),
            'delivery_results' => $delivery_results,
        ];
    }
    
    /**
     * Get cached form configuration
     */
    private function get_form_config($form_id): \WP_Error|array {
        // Try to get from cache first
        $cache_key = 'form_config_' . md5($form_id);
        $cached_config = wp_cache_get($cache_key, 'mksddn_forms_handler');
        
        if ($cached_config !== false) {
            return $cached_config;
        }
        
        // Get form by slug or ID
        $form = get_page_by_path($form_id, OBJECT, 'mksddn_fh_forms');
        if (!$form) {
            $form = get_post($form_id);
        }

        if (!$form || $form->post_type !== 'mksddn_fh_forms') {
            return new \WP_Error('form_not_found', 'Form not found', ['status' => 404]);
        }

        // Get all form settings in one query to reduce database calls
        $form_config = [
            'form_id' => $form->ID,
            'form_title' => $form->post_title,
            'recipients' => get_post_meta($form->ID, '_recipients', true),
            'bcc_recipient' => get_post_meta($form->ID, '_bcc_recipient', true),
            'subject' => get_post_meta($form->ID, '_subject', true),
            'fields_config' => get_post_meta($form->ID, '_fields_config', true),
            'send_to_telegram' => get_post_meta($form->ID, '_send_to_telegram', true),
            'telegram_bot_token' => get_post_meta($form->ID, '_telegram_bot_token', true),
            'telegram_chat_ids' => get_post_meta($form->ID, '_telegram_chat_ids', true),
            'send_to_sheets' => get_post_meta($form->ID, '_send_to_sheets', true),
            'sheets_spreadsheet_id' => get_post_meta($form->ID, '_sheets_spreadsheet_id', true),
            'sheets_sheet_name' => get_post_meta($form->ID, '_sheets_sheet_name', true),
            'save_to_admin' => get_post_meta($form->ID, '_save_to_admin', true),
        ];

        if (!$form_config['recipients'] || !$form_config['subject']) {
            return new \WP_Error('form_config_error', __( 'Form is not configured correctly', 'mksddn-forms-handler' ), ['status' => 500]);
        }

        // Cache the configuration
        wp_cache_set($cache_key, $form_config, 'mksddn_forms_handler', self::CACHE_TTL);
        
        return $form_config;
    }
    
    /**
     * Clear form cache when form is updated
     */
    public function clear_form_cache($post_id, $post): void {
        if ($post->post_type === 'mksddn_fh_forms') {
            $cache_key = 'form_config_' . md5($post_id);
            wp_cache_delete($cache_key, 'mksddn_forms_handler');
            
            // Also clear by slug
            $cache_key_slug = 'form_config_' . md5($post->post_name);
            wp_cache_delete($cache_key_slug, 'mksddn_forms_handler');
        }
    }
    
    /**
     * Get unauthorized fields list
     */
    private function get_unauthorized_fields($form_data, $fields_config): array {
        if (!$fields_config) {
            return array_keys($form_data);
        }

        $fields = json_decode((string)$fields_config, true);
        if (!$fields || !is_array($fields)) {
            return array_keys($form_data);
        }

        $allowed_fields = [];
        foreach ($fields as $field) {
            $allowed_fields[] = $field['name'];
        }

        $unauthorized_fields = [];
        foreach ($form_data as $field_name => $field_value) {
            if (!in_array($field_name, $allowed_fields)) {
                $unauthorized_fields[] = $field_name;
            }
        }

        return $unauthorized_fields;
    }
    
    /**
     * Get allowed fields list
     */
    private function get_allowed_fields($fields_config): array {
        if (!$fields_config) {
            return [];
        }

        $fields = json_decode((string)$fields_config, true);
        if (!$fields || !is_array($fields)) {
            return [];
        }

        $allowed_fields = [];
        foreach ($fields as $field) {
            $allowed_fields[] = $field['name'];
        }

        return $allowed_fields;
    }
    
    /**
     * Filter form data, keeping only allowed fields
     */
    private function filter_form_data($form_data, $fields_config): \WP_Error|array {
        if (!$fields_config) {
            return new \WP_Error('security_error', 'Form fields configuration is missing', ['status' => 400]);
        }

        $fields = json_decode((string)$fields_config, true);
        if (!$fields || !is_array($fields)) {
            return new \WP_Error('security_error', 'Invalid form fields configuration', ['status' => 400]);
        }

        $filtered_data = [];
        $unauthorized_fields = [];

        // Create allowed fields list
        $allowed_fields = [];
        foreach ($fields as $field) {
            $allowed_fields[] = $field['name'];
        }

        // Extract only allowed fields
        foreach ($form_data as $field_name => $field_value) {
            if (in_array($field_name, $allowed_fields)) {
                $filtered_data[$field_name] = $field_value;
            } else {
                $unauthorized_fields[] = $field_name;
            }
        }

        // Log attempts to send unauthorized fields
        if ($unauthorized_fields !== []) {
            $log_entry = [
                'timestamp'               => current_time('mysql'),
                'ip'                      => sanitize_text_field( wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown') ),
                'user_agent'              => sanitize_text_field( wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') ),
                'unauthorized_fields'     => $unauthorized_fields,
                'total_fields_submitted'  => count($form_data),
                'authorized_fields_count' => count($filtered_data),
            ];
            /**
             * Fires when unauthorized fields are submitted to a form.
             *
             * @param array $log_entry Details about the attempt
             */
            do_action('mksddn_forms_handler_log_security', $log_entry);

            // Return error if unauthorized fields present
            return new \WP_Error(
                'unauthorized_fields',
                'Unauthorized fields detected: ' . implode(', ', $unauthorized_fields),
                ['status' => 400]
            );
        }

        return $filtered_data;
    }
    
    /**
     * Validate form data
     */
    private function validate_form_data(\WP_Error|array $form_data, $fields_config): \WP_Error|true {
        if (!$fields_config) {
            return new \WP_Error('validation_error', __( 'Form fields configuration is missing', 'mksddn-forms-handler' ), ['status' => 400]);
        }

        $fields = json_decode((string)$fields_config, true);
        if (!$fields || !is_array($fields)) {
            return new \WP_Error('validation_error', __( 'Invalid form fields configuration', 'mksddn-forms-handler' ), ['status' => 400]);
        }

        // Check if there's at least one field
        if ($fields === []) {
            return new \WP_Error('validation_error', __( 'No fields configured for this form', 'mksddn-forms-handler' ), ['status' => 400]);
        }

        // Check if there's any data
        if (empty($form_data)) {
            return new \WP_Error('validation_error', __( 'No form data provided', 'mksddn-forms-handler' ), ['status' => 400]);
        }

        foreach ($fields as $field) {
            $field_name = $field['name'];
            $field_label = $field['label'] ?? $field_name;
            $is_required = $field['required'] ?? false;
            $field_type = $field['type'] ?? 'text';
            $options = [];
            if (($field_type === 'select' || $field_type === 'radio') && isset($field['options']) && is_array($field['options'])) {
                foreach ($field['options'] as $opt) {
                    if (is_array($opt)) {
                        if (isset($opt['value'])) { $options[] = (string)$opt['value']; }
                    } else {
                        $options[] = (string)$opt;
                    }
                }
                $options = array_values(array_unique(array_filter($options, fn($v) => $v !== '')));
            }

            // Check required fields
            if ($is_required) {
                if ($field_type === 'checkbox') {
                    if (!isset($form_data[$field_name]) || $form_data[$field_name] != '1') {
                        return new \WP_Error('validation_error', sprintf( /* translators: %s: field label */ __( "Field '%s' is required for agreement", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                    }
                } elseif (!isset($form_data[$field_name]) || $form_data[$field_name] === '' || $form_data[$field_name] === null) {
                    return new \WP_Error('validation_error', sprintf( /* translators: %s: field label */ __( "Field '%s' is required", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                }
            }

            // Check email
            if ($field_type === 'email' && isset($form_data[$field_name]) && !empty($form_data[$field_name]) && !is_email($form_data[$field_name])) {
                return new \WP_Error('validation_error', sprintf( /* translators: %s: field label */ __( "Field '%s' must contain a valid email address", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
            }

            // Validate select/radio against allowed options
            if (($field_type === 'select' || $field_type === 'radio') && isset($form_data[$field_name]) && $options !== []) {
                $value = $form_data[$field_name];
                if (is_array($value)) {
                    // Multiple select
                    foreach ($value as $v) {
                        if (!in_array((string)$v, $options, true)) {
                            return new \WP_Error('validation_error', sprintf( /* translators: %s: field label */ __( "Field '%s' contains an invalid value", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                        }
                    }
                } else {
                    if (!in_array((string)$value, $options, true)) {
                        return new \WP_Error('validation_error', sprintf( /* translators: %s: field label */ __( "Field '%s' contains an invalid value", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                    }
                }
            }

            // Check field length (strings only)
            if (isset($form_data[$field_name]) && !empty($form_data[$field_name]) && $field_type !== 'checkbox') {
                if (is_array($form_data[$field_name])) {
                    // Sum of lengths of array values
                    $total = 0;
                    foreach ($form_data[$field_name] as $v) { $total += strlen((string)$v); }
                    if ($total > 10000) {
                        return new \WP_Error('validation_error', sprintf( /* translators: %s: field label */ __( "Field '%s' is too long (maximum 10,000 characters)", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                    }
                } else {
                    $value_length = strlen((string)$form_data[$field_name]);
                    if ($value_length > 10000) {
                        return new \WP_Error('validation_error', sprintf( /* translators: %s: field label */ __( "Field '%s' is too long (maximum 10,000 characters)", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                    }
                }
            }
        }

        return true;
    }
    
    /**
     * Prepare and send email
     */
    private function prepare_and_send_email($recipients, ?string $bcc_recipient, $subject, \WP_Error|array $form_data, $form_title): \WP_Error|true {
        $recipients_array = array_map('trim', explode(',', (string)$recipients));

        // Validate email addresses
        $valid_emails = [];
        foreach ($recipients_array as $recipient) {
            if (is_email($recipient)) {
                $valid_emails[] = $recipient;
            } else {
                return new \WP_Error('invalid_email', 'Invalid email address: ' . $recipient, ['status' => 500]);
            }
        }

        if ($valid_emails === []) {
            return new \WP_Error('no_recipients', 'Recipient list is empty or invalid', ['status' => 500]);
        }

        // Form email body
        $body = $this->build_email_body($form_data, $form_title);

        // Set headers
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if ($bcc_recipient && is_email($bcc_recipient)) {
            $headers[] = 'Bcc: ' . $bcc_recipient;
        }

        // Send email
        if (wp_mail($valid_emails, $subject, $body, $headers)) {
            return true;
        }

        return new \WP_Error('email_send_error', 'Failed to send email', ['status' => 500]);
    }
    
    /**
     * Build email body
     */
    private function build_email_body(\WP_Error|array $form_data, $form_title): string {
        $body = sprintf('<h2>Form Data: %s</h2>', $form_title);
        $body .= "<table style='width: 100%; border-collapse: collapse;'>";
        $body .= "<tr style='background-color: #f8f8f8;'><th style='padding: 10px; border: 1px solid #e9e9e9; text-align: left;'>Field</th><th style='padding: 10px; border: 1px solid #e9e9e9; text-align: left;'>Value</th></tr>";

        foreach ($form_data as $key => $value) {
            $display_value = '';
            if (is_array($value)) {
                $display_value = implode(', ', array_map('sanitize_text_field', array_map('strval', $value)));
            } else {
                $display_value = (string) $value;
            }

            $body .= '<tr>';
            $body .= "<td style='padding: 10px; border: 1px solid #e9e9e9;'><strong>" . esc_html($key) . '</strong></td>';
            $body .= "<td style='padding: 10px; border: 1px solid #e9e9e9;'>" . esc_html($display_value) . '</td>';
            $body .= '</tr>';
        }

        $body .= '</table>';

        return $body . ('<p><small>Sent: ' . current_time('d.m.Y H:i:s') . '</small></p>');
    }
    
    /**
     * Log form submission
     */
    private function log_form_submission($form_id, bool $success): void {
        $log_entry = [
            'form_id'   => $form_id,
            'success'   => $success,
            'timestamp' => current_time('mysql'),
            'ip'        => sanitize_text_field( wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown') ),
        ];

        /**
         * Fires when a form submission is processed (for logging purposes).
         *
         * @param array $log_entry Submission log entry
         */
        do_action('mksddn_forms_handler_log_submission', $log_entry);
    }
    
    /**
     * Save submission to database
     */
    private function save_submission($form_id, \WP_Error|array $form_data, $form_title) {
        // Create submission title
        $submission_title = sprintf(
            '%s - %s - %s',
            $form_title,
            $form_data['name'] ?? 'Anonymous',
            current_time('d.m.Y H:i:s')
        );

        // Create submission record
        $submission_data = [
            'post_title'   => $submission_title,
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'mksddn_fh_submits',
            'post_author'  => 1,
        ];

        $submission_id = wp_insert_post($submission_data);

        if (is_wp_error($submission_id)) {
            return $submission_id;
        }

        // Save meta data
        update_post_meta($submission_id, '_form_id', $form_id);
        update_post_meta($submission_id, '_form_title', $form_title);
        update_post_meta($submission_id, '_submission_data', json_encode($form_data));
        update_post_meta($submission_id, '_submission_date', current_time('mysql'));
        update_post_meta($submission_id, '_submission_ip', sanitize_text_field( wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown') ) );
        update_post_meta($submission_id, '_submission_user_agent', sanitize_text_field( wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') ) );

        return $submission_id;
    }
} 