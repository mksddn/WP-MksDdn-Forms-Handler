<?php
/**
 * @file: class-forms-handler.php
 * @description: Main forms handler class with REST API support
 * @dependencies: WordPress core, REST API
 * @created: 2025-07-30
 */

namespace MksDdn\FormsHandler;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
    
    /**
     * Maximum number of form fields allowed
     * @var int
     */
    private const MAX_FORM_FIELDS = 50;
    
    /**
     * Maximum total data size in bytes (100KB)
     * @var int
     */
    private const MAX_DATA_SIZE = 100000;
    
    /**
     * Rate limit window in seconds
     * @var int
     */
    private const RATE_LIMIT_SECONDS = 10;
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_post_submit_form', [$this, 'handle_form_submission']);
        add_action('admin_post_nopriv_submit_form', [$this, 'handle_form_submission']);
        
        // Clear cache when form is updated
        add_action('save_post_mksddn_fh_forms', [$this, 'clear_form_cache'], 10, 2);
        add_action('deleted_post', [$this, 'clear_form_cache'], 10, 2);
    }

    /**
     * Process uploaded files according to fields config
     * Returns ['data_updates'=> [field=>urls...], 'attachments'=> [filepaths]]
     */
    private function process_uploaded_files(array $file_params, $fields_config) {
        $fields = json_decode((string)$fields_config, true);
        if (!$fields || !is_array($fields)) {
            return new \WP_Error('validation_error', __( 'Invalid form fields configuration', 'mksddn-forms-handler' ), ['status' => 400]);
        }

        // Build rules
        $file_fields = [];
        foreach ($fields as $f) {
            if (($f['type'] ?? '') === 'file' && !empty($f['name'])) {
                $name = (string)$f['name'];
                $file_fields[$name] = [
                    'multiple'           => !empty($f['multiple']) && ($f['multiple'] === '1' || $f['multiple'] === true),
                    'allowed_extensions' => isset($f['allowed_extensions']) && is_array($f['allowed_extensions']) ? array_map('strtolower', array_map('strval', $f['allowed_extensions'])) : [],
                    'max_size_mb'        => isset($f['max_size_mb']) && is_numeric($f['max_size_mb']) ? (float)$f['max_size_mb'] : 10.0,
                    'max_files'          => isset($f['max_files']) && is_numeric($f['max_files']) ? (int)$f['max_files'] : 5,
                    'required'           => !empty($f['required']),
                ];
            }
        }

        if ($file_fields === []) {
            return ['data_updates' => [], 'attachments' => []];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $overrides = ['test_form' => false];
        $data_updates = [];
        $attachments = [];

        foreach ($file_fields as $field_name => $rules) {
            if (!isset($file_params[$field_name])) {
                if ($rules['required']) {
                    /* translators: %s: field name */
                    return new \WP_Error('validation_error', sprintf( __( "Field '%s' is required", 'mksddn-forms-handler' ), $field_name), ['status' => 400]);
                }
                continue;
            }

            $f = $file_params[$field_name];
            // Normalize to array of files
            $files = [];
            if (is_array($f['name'])) {
                $count = count($f['name']);
                for ($i = 0; $i < $count; $i++) {
                    if ((int)$f['error'][$i] === UPLOAD_ERR_NO_FILE) { continue; }
                    $files[] = [
                        'name'     => $f['name'][$i],
                        'type'     => $f['type'][$i] ?? '',
                        'tmp_name' => $f['tmp_name'][$i],
                        'error'    => (int)$f['error'][$i],
                        'size'     => (int)$f['size'][$i],
                    ];
                }
            } else {
                if ((int)$f['error'] !== UPLOAD_ERR_NO_FILE) {
                    $files[] = $f;
                }
            }

            if ($files === []) {
                if ($rules['required']) {
                    /* translators: %s: field name */
                    return new \WP_Error('validation_error', sprintf( __( "Field '%s' is required", 'mksddn-forms-handler' ), $field_name), ['status' => 400]);
                }
                continue;
            }

            if (count($files) > $rules['max_files']) {
                /* translators: 1: field name, 2: max files count */
                return new \WP_Error('validation_error', sprintf( __( "Field '%1\$s' exceeds max files (%2\$d)", 'mksddn-forms-handler' ), $field_name, $rules['max_files']), ['status' => 400]);
            }

            $urls = [];
            foreach ($files as $one) {
                if ($one['error'] !== UPLOAD_ERR_OK) {
                    /* translators: %s: field name */
                    return new \WP_Error('validation_error', sprintf( __( "File upload error for field '%s'", 'mksddn-forms-handler' ), $field_name), ['status' => 400]);
                }
                $size_mb = $one['size'] / (1024 * 1024);
                if ($size_mb > $rules['max_size_mb']) {
                    /* translators: 1: field name, 2: max size in MB */
                    return new \WP_Error('validation_error', sprintf( __( "File too large for field '%1\$s' (max %2\$s MB)", 'mksddn-forms-handler' ), $field_name, $rules['max_size_mb']), ['status' => 400]);
                }
                // Extension check
                $ext = strtolower(pathinfo($one['name'], PATHINFO_EXTENSION));
                if ($rules['allowed_extensions'] !== [] && !in_array($ext, $rules['allowed_extensions'], true)) {
                    /* translators: %s: field name */
                    return new \WP_Error('validation_error', sprintf( __( "File type not allowed for field '%s'", 'mksddn-forms-handler' ), $field_name), ['status' => 400]);
                }

                $moved = wp_handle_upload($one, $overrides);
                if (isset($moved['error'])) {
                    return new \WP_Error('upload_error', $moved['error'], ['status' => 500]);
                }

                $file_url = $moved['url'];
                $file_path = $moved['file'];

                // Add to media library
                $attachment_id = wp_insert_attachment([
                    'post_mime_type' => $moved['type'] ?? '',
                    'post_title'     => sanitize_file_name(basename($file_path)),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                ], $file_path);
                if (!is_wp_error($attachment_id)) {
                    // Only generate metadata for images to avoid slow processing for large JPEG files
                    $file_type = wp_check_filetype($file_path);
                    if (strpos($file_type['type'] ?? '', 'image/') === 0) {
                        require_once ABSPATH . 'wp-admin/includes/image.php';
                        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file_path));
                    }
                }

                $urls[] = $file_url;
                $attachments[] = $file_path; // for email attachments
            }

            $data_updates[$field_name] = $rules['multiple'] ? $urls : ($urls[0] ?? '');
        }

        return [
            'data_updates' => $data_updates,
            'attachments'  => $attachments,
        ];
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
        // Support both JSON and multipart/form-data
        $content_type = isset($_SERVER['CONTENT_TYPE']) ? strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['CONTENT_TYPE'] ) ) ) : '';
        $form_data = false;
        $file_params = [];
        if (strpos($content_type, 'application/json') !== false) {
            $form_data = $request->get_json_params();
        } else {
            $form_data = $request->get_body_params();
            $file_params = $request->get_file_params();
        }

        // Honeypot field check (should be empty)
        $honeypot = $request->get_param('mksddn_fh_hp');
        if (!empty($honeypot)) {
            return new \WP_Error('spam_detected', __( 'Spam detected', 'mksddn-forms-handler' ), ['status' => 400]);
        }

        // Simple rate limiting: 1 request per RATE_LIMIT_SECONDS per IP per form
        $ip = sanitize_text_field( wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown') );
        $rl_key = 'mksddn_fh_rate_' . md5($slug . '|' . $ip);
        $last_ts = get_transient($rl_key);
        if ($last_ts && (time() - (int)$last_ts) < self::RATE_LIMIT_SECONDS) {
            return new \WP_Error('rate_limited', __( 'Too many requests. Please wait a few seconds.', 'mksddn-forms-handler' ), ['status' => 429]);
        }
        set_transient($rl_key, time(), 15);

        if (!$form_data && empty($file_params)) {
            return new \WP_Error('invalid_data', __( 'Invalid form data', 'mksddn-forms-handler' ), ['status' => 400]);
        }

        // Check data size (protection against too large requests)
        if (count($form_data) > self::MAX_FORM_FIELDS) {
            return new \WP_Error('too_many_fields', __( 'Too many form fields submitted', 'mksddn-forms-handler' ), ['status' => 400]);
        }

        // Check total data size (recursively calculate for nested arrays)
        $total_size = 0;
        foreach ($form_data as $key => $value) {
            $size_key = strlen((string) $key);
            $size_val = $this->calculate_value_size($value);
            $total_size += $size_key + $size_val;
        }

        if ($total_size > self::MAX_DATA_SIZE) {
            return new \WP_Error('data_too_large', __( 'Form data is too large', 'mksddn-forms-handler' ), ['status' => 400]);
        }

        // Build files if present
        $email_attachments = [];
        if (!empty($file_params)) {
            $fields_config = $this->get_form_config($slug);
            if (is_wp_error($fields_config)) {
                return $fields_config;
            }
            $files_result = $this->process_uploaded_files($file_params, $fields_config['fields_config']);
            if (is_wp_error($files_result)) {
                return $files_result;
            }
            if (!empty($files_result['data_updates'])) {
                foreach ($files_result['data_updates'] as $k => $v) { $form_data[$k] = $v; }
            }
            if (!empty($files_result['attachments'])) { $email_attachments = $files_result['attachments']; }
        }

        $result = $this->process_form_submission($slug, $form_data, $email_attachments);

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
            return new \WP_REST_Response(['message' => __( 'Invalid slug', 'mksddn-forms-handler' )], 400);
        }

        $post = get_page_by_path($slug, OBJECT, 'mksddn_fh_forms');
        if (!$post) {
            return new \WP_REST_Response(['message' => __( 'Form not found', 'mksddn-forms-handler' )], 404);
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
            wp_send_json_error([
                'message' => __( 'Security check failed. Please refresh the page and try again.', 'mksddn-forms-handler' ),
                'code'    => 'nonce_verification_failed',
            ]);
            return;
        }

        $form_id = isset($_POST['form_id']) ? sanitize_text_field( wp_unslash($_POST['form_id']) ) : '';

        // Honeypot check
        $honeypot = isset($_POST['mksddn_fh_hp']) ? sanitize_text_field( wp_unslash($_POST['mksddn_fh_hp']) ) : '';
        if (!empty($honeypot)) {
            wp_die( esc_html__( 'Spam detected', 'mksddn-forms-handler' ) );
        }

        // Simple rate limiting per IP+form: 1 request per RATE_LIMIT_SECONDS
        $ip = sanitize_text_field( wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown') );
        $rl_key = 'mksddn_fh_rate_' . md5($form_id . '|' . $ip);
        $last_ts = get_transient($rl_key);
        if ($last_ts && (time() - (int)$last_ts) < self::RATE_LIMIT_SECONDS) {
            wp_die( esc_html__( 'Too many requests. Please wait a few seconds.', 'mksddn-forms-handler' ) );
        }
        set_transient($rl_key, time(), 15);

        // Build form data using whitelist from form configuration
        $form_config = $this->get_form_config($form_id);
        if (is_wp_error($form_config)) {
            wp_die( esc_html( $form_config->get_error_message() ) );
        }
        
        // Check if allow_any_fields is enabled
        $allow_any_fields = get_post_meta($form_config['form_id'], '_allow_any_fields', true);
        $form_data = [];
        
        // Build fields map for proper sanitization
        $fields = json_decode((string)$form_config['fields_config'], true);
        $fields_map = [];
        if (is_array($fields)) {
            foreach ($fields as $field) {
                if (isset($field['name'])) {
                    $fields_map[$field['name']] = $field;
                }
            }
        }
        
        if ($allow_any_fields === '1') {
            // Accept all fields from POST
            foreach ($_POST as $field_name => $value) {
                // Skip system fields
                if (in_array($field_name, ['form_nonce', 'action', 'form_id', 'mksddn_fh_hp', '_wp_http_referer'], true)) {
                    continue;
                }
                $unslashed = wp_unslash($value); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $field_config = $fields_map[$field_name] ?? null;
                $form_data[$field_name] = $this->sanitize_value_recursive($unslashed, $field_config);
            }
        } else {
            // Use whitelist from configuration
            $allowed_fields = $this->get_allowed_fields($form_config['fields_config'], $form_config['form_id'], $form_config['form_slug']);
            foreach ($allowed_fields as $field_name) {
                if (isset($_POST[$field_name])) {
                    // Raw input is unslashed first, then sanitized below
                    $value = wp_unslash($_POST[$field_name]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $field_config = $fields_map[$field_name] ?? null;
                    $form_data[$field_name] = $this->sanitize_value_recursive($value, $field_config);
                }
            }
        }

        // Process uploaded files (if any)
        $email_attachments = [];
        if (!empty($_FILES)) {
            $files_result = $this->process_uploaded_files($_FILES, $form_config['fields_config']);
            if (is_wp_error($files_result)) {
                wp_die( esc_html( $files_result->get_error_message() ) );
            }
            if (!empty($files_result['data_updates'])) {
                foreach ($files_result['data_updates'] as $k => $v) {
                    $form_data[$k] = $v;
                }
            }
            if (!empty($files_result['attachments'])) {
                $email_attachments = $files_result['attachments'];
            }
        }

        if (!$form_id || (!$form_data && empty($email_attachments))) {
            wp_die( esc_html__( 'Invalid form data', 'mksddn-forms-handler' ) );
        }

        $result = $this->process_form_submission($form_id, $form_data, $email_attachments);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * Process form submission with optimized performance
     */
    private function process_form_submission($form_id, $form_data, array $email_attachments = []): \WP_Error|bool|array {
        // Get cached form configuration
        $form_config = $this->get_form_config($form_id);
        
        if (is_wp_error($form_config)) {
            return $form_config;
        }

        // Filter form data
        $filtered_form_data = $this->filter_form_data(
            $form_data, 
            $form_config['fields_config'], 
            $form_config['form_id'], 
            $form_config['form_slug']
        );

        // Check filtering result
        if (is_wp_error($filtered_form_data)) {
            $unauthorized_fields = $this->get_unauthorized_fields($form_data, $form_config['fields_config']);
            return new \WP_Error(
                'unauthorized_fields',
                sprintf( /* translators: %s: field names */ __( 'Unauthorized fields detected: %s', 'mksddn-forms-handler' ), implode(', ', $unauthorized_fields) ),
                [
                    'status'              => 400,
                    'unauthorized_fields' => $unauthorized_fields,
                    'allowed_fields'      => $this->get_allowed_fields(
                        $form_config['fields_config'],
                        $form_config['form_id'],
                        $form_config['form_slug']
                    ),
                ]
            );
        }

        // Validate data (skip validation if allow_any_fields is enabled)
        $allow_any_fields = get_post_meta($form_config['form_id'], '_allow_any_fields', true);
        if ($allow_any_fields !== '1') {
            $validation_result = $this->validate_form_data($filtered_form_data, $form_config['fields_config']);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }
        }

        // Add page URL to submission data for notifications
        $page_url = $this->get_page_url();
        if (!empty($page_url)) {
            $filtered_form_data['Page URL'] = $page_url;
        }

        // Initialize delivery results
        $delivery_results = [
            'email'         => [
                'success' => false,
                'error'   => null,
                'enabled' => false,
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

        // Send email if enabled
        if ($form_config['send_to_email'] && $form_config['recipients'] && $form_config['subject']) {
            $delivery_results['email']['enabled'] = true;
            $email_result = $this->prepare_and_send_email(
                $form_config['recipients'], 
                $form_config['bcc_recipient'], 
                $form_config['subject'], 
                $filtered_form_data, 
                $form_config['form_title'],
                $email_attachments,
                $form_config['fields_config']
            );
            $delivery_results['email']['success'] = !is_wp_error($email_result);
            if (is_wp_error($email_result)) {
                $delivery_results['email']['error'] = $email_result->get_error_message();
            }
        }

        // Send to Telegram
        if ($form_config['send_to_telegram'] && $form_config['telegram_bot_token'] && $form_config['telegram_chat_ids']) {
            $delivery_results['telegram']['enabled'] = true;
            
            // Get custom template if enabled
            $custom_template = null;
            if ($form_config['use_custom_telegram_template'] && !empty($form_config['telegram_template'])) {
                $custom_template = $form_config['telegram_template'];
            }
            
            $telegram_result = \MksDdn\FormsHandler\TelegramHandler::send_message(
                $form_config['telegram_bot_token'], 
                $form_config['telegram_chat_ids'], 
                $filtered_form_data, 
                $form_config['form_title'],
                $form_config['fields_config'],
                $custom_template
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
        $any_success = ($delivery_results['email']['enabled'] && $delivery_results['email']['success']) ||
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
            return new \WP_Error('form_not_found', __( 'Form not found', 'mksddn-forms-handler' ), ['status' => 404]);
        }

        // Get all form settings in one query to reduce database calls
        // Use get_post_meta() without second parameter to fetch all meta at once
        $all_meta = get_post_meta($form->ID);
        
        // Helper function to get meta value safely
        $get_meta = function($key) use ($all_meta) {
            return isset($all_meta[$key][0]) ? $all_meta[$key][0] : '';
        };
        
        $form_config = [
            'form_id' => $form->ID,
            'form_slug' => $form->post_name,
            'form_title' => $form->post_title,
            'recipients' => $get_meta('_recipients'),
            'bcc_recipient' => $get_meta('_bcc_recipient'),
            'subject' => $get_meta('_subject'),
            'send_to_email' => $get_meta('_send_to_email'),
            'fields_config' => $get_meta('_fields_config'),
            'send_to_telegram' => $get_meta('_send_to_telegram'),
            'telegram_bot_token' => $get_meta('_telegram_bot_token'),
            'telegram_chat_ids' => $get_meta('_telegram_chat_ids'),
            'use_custom_telegram_template' => $get_meta('_use_custom_telegram_template'),
            'telegram_template' => $get_meta('_telegram_template'),
            'send_to_sheets' => $get_meta('_send_to_sheets'),
            'sheets_spreadsheet_id' => $get_meta('_sheets_spreadsheet_id'),
            'sheets_sheet_name' => $get_meta('_sheets_sheet_name'),
            'save_to_admin' => $get_meta('_save_to_admin'),
        ];

        // Validate email configuration only if email is enabled
        if ($form_config['send_to_email'] && (!$form_config['recipients'] || !$form_config['subject'])) {
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
     * Get allowed fields list with filter support
     *
     * @param string $fields_config JSON fields configuration
     * @param int    $form_id       Form ID
     * @param string $form_slug     Form slug
     * @return array Array of allowed field names
     */
    private function get_allowed_fields($fields_config, $form_id = 0, $form_slug = ''): array {
        $allowed_fields = [];
        
        if ($fields_config) {
            $fields = json_decode((string)$fields_config, true);
            if ($fields && is_array($fields)) {
                foreach ($fields as $field) {
                    if (isset($field['name'])) {
                        $allowed_fields[] = $field['name'];
                    }
                }
            }
        }

        /**
         * Filter allowed field names for a form
         * 
         * Allows developers to dynamically modify which fields are accepted.
         * Return ['*'] to allow all fields (bypass field filtering).
         *
         * @param array  $allowed_fields Current allowed fields from configuration
         * @param int    $form_id        Form ID
         * @param string $form_slug      Form slug
         * @since 1.1.0
         */
        $allowed_fields = apply_filters('mksddn_fh_allowed_fields', $allowed_fields, $form_id, $form_slug);

        return $allowed_fields;
    }
    
    /**
     * Sanitize all fields without type validation
     * Used when allow_any_fields is enabled
     * Recursively sanitizes nested arrays and objects
     *
     * @param array $form_data Raw form data
     * @return array Sanitized form data
     */
    private function sanitize_all_fields(array $form_data): array {
        $sanitized = [];
        
        foreach ($form_data as $key => $value) {
            // Don't sanitize key - it's already sanitized in handle_form_submission()
            // Just ensure it's a string and not empty
            $safe_key = (string) $key;
            
            // Skip empty keys
            if ($safe_key === '') {
                continue;
            }
            
            // Recursively sanitize value
            $sanitized[$safe_key] = $this->sanitize_value_recursive($value);
        }
        
        return $sanitized;
    }
    
    /**
     * Recursively sanitize a value (handles arrays, objects, and primitives)
     * Optionally uses field configuration for proper type-based sanitization
     *
     * @param mixed $value Value to sanitize
     * @param array|null $field_config Optional field configuration for type-based sanitization
     * @return mixed Sanitized value
     */
    private function sanitize_value_recursive($value, $field_config = null) {
        // If field config provided and it's array_of_objects, use specialized sanitization
        if ($field_config && ($field_config['type'] ?? '') === 'array_of_objects' && is_array($value)) {
            return $this->sanitize_array_of_objects($value, $field_config);
        }
        
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $k => $v) {
                $sanitized_key = is_string($k) ? sanitize_key($k) : $k;
                $sanitized[$sanitized_key] = $this->sanitize_value_recursive($v);
            }
            return $sanitized;
        }
        
        if (is_object($value)) {
            // Convert object to array and sanitize recursively
            return $this->sanitize_value_recursive((array) $value);
        }
        
        // Primitive types: sanitize strings, keep numbers and booleans
        if (is_string($value)) {
            return sanitize_text_field($value);
        }
        
        if (is_numeric($value)) {
            return is_float($value) ? (float) $value : (int) $value;
        }
        
        if (is_bool($value)) {
            return $value;
        }
        
        // Fallback: convert to string and sanitize
        return sanitize_text_field((string) $value);
    }
    
    /**
     * Sanitize array of objects using field configuration
     *
     * @param array $array_value Array of objects to sanitize
     * @param array $field_config Field configuration with nested fields
     * @return array Sanitized array
     */
    private function sanitize_array_of_objects(array $array_value, array $field_config): array {
        $nested_fields = $field_config['fields'] ?? [];
        $sanitized = [];
        
        foreach ($array_value as $item) {
            if (!is_array($item)) {
                continue;
            }
            
            $sanitized_item = [];
            
            // If nested fields config exists, sanitize according to it
            if (!empty($nested_fields) && is_array($nested_fields)) {
                foreach ($nested_fields as $nested_field) {
                    $nested_field_name = $nested_field['name'] ?? '';
                    $nested_field_type = $nested_field['type'] ?? 'text';
                    
                    if (isset($item[$nested_field_name])) {
                        $nested_value = $item[$nested_field_name];
                        
                        // Type-based sanitization
                        if ($nested_field_type === 'email') {
                            $sanitized_item[$nested_field_name] = sanitize_email($nested_value);
                        } elseif ($nested_field_type === 'url') {
                            $sanitized_item[$nested_field_name] = esc_url_raw($nested_value);
                        } elseif ($nested_field_type === 'number') {
                            $sanitized_item[$nested_field_name] = is_numeric($nested_value) ? ($nested_value + 0) : 0;
                        } elseif (is_array($nested_value)) {
                            $sanitized_item[$nested_field_name] = $this->sanitize_value_recursive($nested_value);
                        } else {
                            $sanitized_item[$nested_field_name] = sanitize_text_field((string) $nested_value);
                        }
                    }
                }
            } else {
                // No nested config: sanitize all fields generically
                foreach ($item as $k => $v) {
                    $sanitized_key = is_string($k) ? sanitize_key($k) : $k;
                    $sanitized_item[$sanitized_key] = $this->sanitize_value_recursive($v);
                }
            }
            
            $sanitized[] = $sanitized_item;
        }
        
        return $sanitized;
    }
    
    /**
     * Filter form data, keeping only allowed fields
     *
     * @param array  $form_data     Raw form data
     * @param string $fields_config JSON fields configuration
     * @param int    $form_id       Form ID
     * @param string $form_slug     Form slug
     * @return \WP_Error|array Filtered data or error
     */
    private function filter_form_data($form_data, $fields_config, $form_id = 0, $form_slug = ''): \WP_Error|array {
        // Check if form allows any fields
        $allow_any_fields = get_post_meta($form_id, '_allow_any_fields', true);
        
        // If allow_any_fields is enabled, skip field validation
        if ($allow_any_fields === '1') {
            return $this->sanitize_all_fields($form_data);
        }
        
        // Original validation logic
        if (!$fields_config) {
            return new \WP_Error('security_error', __( 'Form fields configuration is missing', 'mksddn-forms-handler' ), ['status' => 400]);
        }

        $fields = json_decode((string)$fields_config, true);
        if (!$fields || !is_array($fields)) {
            return new \WP_Error('security_error', 'Invalid form fields configuration', ['status' => 400]);
        }

        $filtered_data = [];
        $unauthorized_fields = [];

        // Get allowed fields with filter support
        $allowed_fields = $this->get_allowed_fields($fields_config, $form_id, $form_slug);
        
        // Check for wildcard (allow all fields)
        if (in_array('*', $allowed_fields, true)) {
            return $this->sanitize_all_fields($form_data);
        }

        // Extract only allowed fields and sanitize using field config
        $fields = json_decode((string)$fields_config, true);
        $fields_map = [];
        if (is_array($fields)) {
            foreach ($fields as $field) {
                if (isset($field['name'])) {
                    $fields_map[$field['name']] = $field;
                }
            }
        }
        
        foreach ($form_data as $field_name => $field_value) {
            if (in_array($field_name, $allowed_fields, true)) {
                // Get field config for proper sanitization
                $field_config = $fields_map[$field_name] ?? null;
                $filtered_data[$field_name] = $this->sanitize_value_recursive($field_value, $field_config);
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
                sprintf( /* translators: %s: field names */ __( 'Unauthorized fields detected: %s', 'mksddn-forms-handler' ), implode(', ', $unauthorized_fields) ),
                ['status' => 400]
            );
        }

        return $filtered_data;
    }
    
    /**
     * Validate form data
     */
    private function validate_form_data(\WP_Error|array $form_data, $fields_config): \WP_Error|bool {
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
            
            // Handle array_of_objects type
            if ($field_type === 'array_of_objects') {
                $validation_result = $this->validate_array_of_objects($form_data, $field_name, $field_label, $is_required, $field);
                if (is_wp_error($validation_result)) {
                    return $validation_result;
                }
                continue;
            }
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

            // Security: Reject arrays for simple field types (only array_of_objects allows arrays)
            // This prevents bypassing validation by sending arrays to simple fields
            $simple_types = ['text', 'email', 'tel', 'url', 'number', 'date', 'time', 'datetime-local', 'textarea', 'password'];
            if (in_array($field_type, $simple_types, true) && isset($form_data[$field_name]) && is_array($form_data[$field_name])) {
                return new \WP_Error('validation_error', sprintf( /* translators: %s: field label */ __( "Field '%s' must be a single value, not an array. Use 'array_of_objects' type for arrays.", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
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
            if ($field_type === 'email' && isset($form_data[$field_name]) && $form_data[$field_name] !== '') {
                if (is_array($form_data[$field_name])) {
                    /* translators: %s: field label */
                    return new \WP_Error('validation_error', sprintf( __( "Field '%s' must be a single email address", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                }
                if (!is_email($form_data[$field_name])) {
                    /* translators: %s: field label */
                    return new \WP_Error('validation_error', sprintf( __( "Field '%s' must contain a valid email address", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                }
            }
            // Validate file fields (store URLs in form data)
            if ($field_type === 'file') {
                // Accept strings (single URL) or arrays of URLs
                if (isset($form_data[$field_name]) && $form_data[$field_name] !== '') {
                    $val = $form_data[$field_name];
                    $urls = is_array($val) ? $val : [$val];
                    foreach ($urls as $u) {
                        if (esc_url_raw((string)$u) === '') {
                            /* translators: %s: field label */
                            return new \WP_Error('validation_error', sprintf( __( "Field '%s' contains invalid file URL", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                        }
                    }
                } elseif ($is_required) {
                    /* translators: %s: field label */
                    return new \WP_Error('validation_error', sprintf( __( "Field '%s' is required", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                }
            }

            // Check URL
            if ($field_type === 'url' && isset($form_data[$field_name]) && $form_data[$field_name] !== '') {
                if (is_array($form_data[$field_name])) {
                    /* translators: %s: field label */
                    return new \WP_Error('validation_error', sprintf( __( "Field '%s' must be a single URL", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                }
                $sanitized_url = esc_url_raw((string) $form_data[$field_name]);
                if ($sanitized_url === '') {
                    /* translators: %s: field label */
                    return new \WP_Error('validation_error', sprintf( __( "Field '%s' must contain a valid URL", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                }
            }

            // Check number with optional min/max/step
            if ($field_type === 'number' && isset($form_data[$field_name]) && $form_data[$field_name] !== '') {
                $value = $form_data[$field_name];
                if (is_array($value)) {
                    /* translators: %s: field label */
                    return new \WP_Error('validation_error', sprintf( __( "Field '%s' must be a single number", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                }
                if (!is_numeric($value)) {
                    /* translators: %s: field label */
                    return new \WP_Error('validation_error', sprintf( __( "Field '%s' must be a number", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                }
                $num = $value + 0; // cast numeric
                if (isset($field['min']) && $field['min'] !== '' && $num < ($field['min'] + 0)) {
                    /* translators: 1: field label, 2: min value */
                    return new \WP_Error('validation_error', sprintf( __( "Field '%1\$s' must be greater than or equal to %2\$s", 'mksddn-forms-handler' ), $field_label, $field['min']), ['status' => 400]);
                }
                if (isset($field['max']) && $field['max'] !== '' && $num > ($field['max'] + 0)) {
                    /* translators: 1: field label, 2: max value */
                    return new \WP_Error('validation_error', sprintf( __( "Field '%1\$s' must be less than or equal to %2\$s", 'mksddn-forms-handler' ), $field_label, $field['max']), ['status' => 400]);
                }
                if (isset($field['step']) && $field['step'] !== '' && is_numeric($field['step']) && (float)$field['step'] > 0) {
					$step = (float)$field['step'];
					$remainder = fmod((float)$num, $step);
					if ($remainder !== 0.0 && $step !== 1.0) {
						/* translators: 1: field label, 2: step value */
						return new \WP_Error('validation_error', sprintf( __( "Field '%1\$s' must follow step %2\$s", 'mksddn-forms-handler' ), $field_label, $field['step']), ['status' => 400]);
					}
				}
            }

            // Check telephone pattern (server-side, optional)
            if ($field_type === 'tel' && isset($form_data[$field_name]) && $form_data[$field_name] !== '') {
                $pattern = '';
                if (!empty($field['pattern']) && is_string($field['pattern'])) {
                    $pattern = (string)$field['pattern'];
                } else {
                    $pattern = '^\+?\d{7,15}$';
                }
                $delimited = '/'.$pattern.'/';
                if (@preg_match($delimited, '') === false) {
                    // Fallback to default if provided pattern is invalid
                    $delimited = '/^\+?\d{7,15}$/';
                }
                if (is_array($form_data[$field_name])) {
                    /* translators: %s: field label */
                    return new \WP_Error('validation_error', sprintf( __( "Field '%s' must be a single phone number", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                }
                if (!preg_match($delimited, (string)$form_data[$field_name])) {
                    /* translators: %s: field label */
                    return new \WP_Error('validation_error', sprintf( __( "Field '%s' must be a valid phone number", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                }
            }

            // Check date/time/datetime-local formats (if present)
            if (isset($form_data[$field_name]) && $form_data[$field_name] !== '') {
                $raw_value = $form_data[$field_name];
                if ($field_type === 'date') {
                    if (is_array($raw_value)) {
                        /* translators: %s: field label */
                        return new \WP_Error('validation_error', sprintf( __( "Field '%s' must be a single date value", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                    }
                    $val = (string) $raw_value;
                    $dt = \DateTime::createFromFormat('Y-m-d', $val);
                    if (!$dt || $dt->format('Y-m-d') !== $val) {
                        /* translators: %s: field label */
                        return new \WP_Error('validation_error', sprintf( __( "Field '%s' must be a valid date (YYYY-MM-DD)", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                    }
                }
                if ($field_type === 'time') {
                    if (is_array($raw_value)) {
                        /* translators: %s: field label */
                        return new \WP_Error('validation_error', sprintf( __( "Field '%s' must be a single time value", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                    }
                    $val = (string) $raw_value;
                    $dt = \DateTime::createFromFormat('H:i', $val);
                    if (!$dt || $dt->format('H:i') !== $val) {
                        /* translators: %s: field label */
                        return new \WP_Error('validation_error', sprintf( __( "Field '%s' must be a valid time (HH:MM)", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                    }
                }
                if ($field_type === 'datetime-local') {
                    if (is_array($raw_value)) {
                        /* translators: %s: field label */
                        return new \WP_Error('validation_error', sprintf( __( "Field '%s' must be a single datetime value", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                    }
                    // HTML datetime-local typically 'YYYY-MM-DDTHH:MM'
                    $val = (string) $raw_value;
                    $dt = \DateTime::createFromFormat('Y-m-d\\TH:i', $val);
                    if (!$dt || $dt->format('Y-m-d\\TH:i') !== $val) {
                        /* translators: %s: field label */
                        return new \WP_Error('validation_error', sprintf( __( "Field '%s' must be a valid datetime (YYYY-MM-DDTHH:MM)", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
                    }
                }
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
     * Validate array of objects field
     *
     * @param array $form_data Form data
     * @param string $field_name Field name
     * @param string $field_label Field label
     * @param bool $is_required Is field required
     * @param array $field_config Field configuration
     * @return \WP_Error|bool
     */
    private function validate_array_of_objects($form_data, $field_name, $field_label, $is_required, $field_config): \WP_Error|bool {
        // Check if field is required
        if ($is_required) {
            if (!isset($form_data[$field_name]) || !is_array($form_data[$field_name]) || empty($form_data[$field_name])) {
                return new \WP_Error('validation_error', sprintf( /* translators: %s: field label */ __( "Field '%s' is required and must contain at least one item", 'mksddn-forms-handler' ), $field_label), ['status' => 400]);
            }
        }
        
        // If field is not set or empty, skip validation
        if (!isset($form_data[$field_name]) || !is_array($form_data[$field_name]) || empty($form_data[$field_name])) {
            return true;
        }
        
        // Get nested fields configuration
        $nested_fields = $field_config['fields'] ?? [];
        if (empty($nested_fields) || !is_array($nested_fields)) {
            // If no nested fields config, just validate that it's an array
            return true;
        }
        
        // Validate each item in the array
        $array_value = $form_data[$field_name];
        foreach ($array_value as $item_index => $item) {
            if (!is_array($item)) {
                return new \WP_Error('validation_error', sprintf( /* translators: 1: field label, 2: item index */ __( "Field '%1\$s' item #%2\$d must be an object", 'mksddn-forms-handler' ), $field_label, $item_index + 1), ['status' => 400]);
            }
            
            // Validate each nested field
            foreach ($nested_fields as $nested_field) {
                $nested_field_name = $nested_field['name'] ?? '';
                $nested_field_label = $nested_field['label'] ?? $nested_field_name;
                $nested_is_required = $nested_field['required'] ?? false;
                $nested_field_type = $nested_field['type'] ?? 'text';
                
                // Check required nested fields
                if ($nested_is_required) {
                    if (!isset($item[$nested_field_name]) || $item[$nested_field_name] === '' || $item[$nested_field_name] === null) {
                        return new \WP_Error('validation_error', sprintf( /* translators: 1: nested field label, 2: field label, 3: item index */ __( "Field '%1\$s' in '%2\$s' item #%3\$d is required", 'mksddn-forms-handler' ), $nested_field_label, $field_label, $item_index + 1), ['status' => 400]);
                    }
                }
                
                // Skip validation if field is not set
                if (!isset($item[$nested_field_name]) || $item[$nested_field_name] === '') {
                    continue;
                }
                
                $nested_value = $item[$nested_field_name];
                
                // Validate email
                if ($nested_field_type === 'email') {
                    if (!is_email($nested_value)) {
                        return new \WP_Error('validation_error', sprintf( /* translators: 1: nested field label, 2: field label, 3: item index */ __( "Field '%1\$s' in '%2\$s' item #%3\$d must be a valid email", 'mksddn-forms-handler' ), $nested_field_label, $field_label, $item_index + 1), ['status' => 400]);
                    }
                }
                
                // Validate number
                if ($nested_field_type === 'number') {
                    if (!is_numeric($nested_value)) {
                        return new \WP_Error('validation_error', sprintf( /* translators: 1: nested field label, 2: field label, 3: item index */ __( "Field '%1\$s' in '%2\$s' item #%3\$d must be a number", 'mksddn-forms-handler' ), $nested_field_label, $field_label, $item_index + 1), ['status' => 400]);
                    }
                    $num = $nested_value + 0;
                    if (isset($nested_field['min']) && $nested_field['min'] !== '' && $num < ($nested_field['min'] + 0)) {
                        return new \WP_Error('validation_error', sprintf( /* translators: 1: nested field label, 2: field label, 3: item index, 4: min value */ __( "Field '%1\$s' in '%2\$s' item #%3\$d must be greater than or equal to %4\$s", 'mksddn-forms-handler' ), $nested_field_label, $field_label, $item_index + 1, $nested_field['min']), ['status' => 400]);
                    }
                    if (isset($nested_field['max']) && $nested_field['max'] !== '' && $num > ($nested_field['max'] + 0)) {
                        return new \WP_Error('validation_error', sprintf( /* translators: 1: nested field label, 2: field label, 3: item index, 4: max value */ __( "Field '%1\$s' in '%2\$s' item #%3\$d must be less than or equal to %4\$s", 'mksddn-forms-handler' ), $nested_field_label, $field_label, $item_index + 1, $nested_field['max']), ['status' => 400]);
                    }
                }
                
                // Validate tel
                if ($nested_field_type === 'tel') {
                    $pattern = $nested_field['pattern'] ?? '^\+?\d{7,15}$';
                    $delimited = '/'.$pattern.'/';
                    if (@preg_match($delimited, '') === false) {
                        $delimited = '/^\+?\d{7,15}$/';
                    }
                    if (!preg_match($delimited, (string)$nested_value)) {
                        return new \WP_Error('validation_error', sprintf( /* translators: 1: nested field label, 2: field label, 3: item index */ __( "Field '%1\$s' in '%2\$s' item #%3\$d must be a valid phone number", 'mksddn-forms-handler' ), $nested_field_label, $field_label, $item_index + 1), ['status' => 400]);
                    }
                }
                
                // Validate URL
                if ($nested_field_type === 'url') {
                    $sanitized_url = esc_url_raw((string) $nested_value);
                    if ($sanitized_url === '') {
                        return new \WP_Error('validation_error', sprintf( /* translators: 1: nested field label, 2: field label, 3: item index */ __( "Field '%1\$s' in '%2\$s' item #%3\$d must be a valid URL", 'mksddn-forms-handler' ), $nested_field_label, $field_label, $item_index + 1), ['status' => 400]);
                    }
                }
            }
        }
        
        return true;
    }
    
    /**
     * Prepare and send email
     */
    private function prepare_and_send_email($recipients, ?string $bcc_recipient, $subject, \WP_Error|array $form_data, $form_title, array $attachments = [], $fields_config = null): \WP_Error|bool {
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
        $body = $this->build_email_body($form_data, $form_title, $fields_config);

        // Set headers
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if ($bcc_recipient && is_email($bcc_recipient)) {
            $headers[] = 'Bcc: ' . $bcc_recipient;
        }

        // Send email
        if (wp_mail($valid_emails, $subject, $body, $headers, $attachments)) {
            return true;
        }

        return new \WP_Error('email_send_error', __( 'Failed to send email', 'mksddn-forms-handler' ), ['status' => 500]);
    }
    
    /**
     * Build email body
     */
    private function build_email_body(\WP_Error|array $form_data, $form_title, $fields_config = null): string {
        // Build field name to label mapping
        $field_labels_map = $this->build_field_labels_map($fields_config);

        /* translators: %s: form title */
        $body = sprintf( '<h2>' . __( 'Form Data: %s', 'mksddn-forms-handler' ) . '</h2>', esc_html( $form_title ) );
        $body .= "<table style='width: 100%; border-collapse: collapse;'>";
        $body .= '<tr style="background-color: #f8f8f8;"><th style="padding: 10px; border: 1px solid #e9e9e9; text-align: left;">' . esc_html__( 'Field', 'mksddn-forms-handler' ) . '</th><th style="padding: 10px; border: 1px solid #e9e9e9; text-align: left;">' . esc_html__( 'Value', 'mksddn-forms-handler' ) . '</th></tr>';

        foreach ($form_data as $key => $value) {
            $field_label = $field_labels_map[ $key ] ?? $this->get_system_field_label( $key );
            $body .= '<tr>';
            $body .= "<td style='padding: 10px; border: 1px solid #e9e9e9;'><strong>" . esc_html($field_label) . '</strong></td>';
            
            if ($this->looks_like_urls($value)) {
                $urls = is_array($value) ? $value : [$value];
                $links = array_map(function($u) { $su = esc_url($u); return $su ? '<a href="' . $su . '">' . esc_html($su) . '</a>' : esc_html((string)$u); }, $urls);
                $body .= "<td style='padding: 10px; border: 1px solid #e9e9e9;'>" . implode('<br>', $links) . '</td>';
            } elseif (is_array($value) && $this->is_array_of_objects($value)) {
                // Render array of objects as a nested table (e.g., products)
                $body .= "<td style='padding: 10px; border: 1px solid #e9e9e9;'>";
                $body .= $this->render_array_of_objects($value, $fields_config, $key);
                $body .= '</td>';
            } elseif (is_array($value)) {
                // Simple array: render as comma-separated list
                $display_value = implode(', ', array_map('sanitize_text_field', array_map('strval', $value)));
                $body .= "<td style='padding: 10px; border: 1px solid #e9e9e9;'>" . esc_html($display_value) . '</td>';
            } else {
                $body .= "<td style='padding: 10px; border: 1px solid #e9e9e9;'>" . esc_html((string) $value) . '</td>';
            }
            $body .= '</tr>';
        }

        $body .= '</table>';

        /* translators: %s: date and time */
        return $body . ( '<p><small>' . sprintf( __( 'Sent: %s', 'mksddn-forms-handler' ), current_time( 'd.m.Y H:i:s' ) ) . '</small></p>' );
    }

    /**
     * Get localized label for system-added field keys (e.g. Page URL).
     *
     * @param string $key Field key.
     * @return string Label for display.
     */
    private function get_system_field_label( string $key ): string {
        if ( $key === 'Page URL' ) {
            return __( 'Page URL', 'mksddn-forms-handler' );
        }
        return $key;
    }

    /**
     * Build field name to label mapping from fields configuration
     * Priority: notification_label → label → name
     *
     * @param string|null $fields_config JSON fields configuration
     * @return array Associative array mapping field names to labels
     */
    private function build_field_labels_map($fields_config): array {
        $labels_map = [];
        
        if (!$fields_config) {
            return $labels_map;
        }
        
        $fields = json_decode((string)$fields_config, true);
        if (!is_array($fields)) {
            return $labels_map;
        }
        
        foreach ($fields as $field) {
            if (isset($field['name'])) {
                $field_name = $field['name'];
                // Priority: notification_label → label → name
                $field_label = $field['notification_label'] ?? $field['label'] ?? $field_name;
                $labels_map[$field_name] = $field_label;
            }
        }
        
        return $labels_map;
    }
    
    /**
     * Check if array contains objects (associative arrays with multiple keys)
     *
     * @param array $value Array to check
     * @return bool True if array contains objects
     */
    private function is_array_of_objects(array $value): bool {
        if (empty($value)) {
            return false;
        }
        
        // Check if first element is an associative array (object-like)
        $first = reset($value);
        if (!is_array($first)) {
            return false;
        }
        
        // Check if it's associative (has string keys)
        $keys = array_keys($first);
        return !empty($keys) && array_keys($keys) !== $keys;
    }
    
    /**
     * Render array of objects as HTML table
     *
     * @param array $items Array of objects/associative arrays
     * @param string|null $fields_config Fields configuration JSON
     * @param string|null $parent_field_name Parent field name for nested fields lookup
     * @return string HTML table
     */
    private function render_array_of_objects(array $items, $fields_config = null, $parent_field_name = null): string {
        if (empty($items)) {
            return '';
        }
        
        // Build nested field labels map if parent field config exists
        $nested_labels_map = [];
        if ($fields_config && $parent_field_name) {
            $fields = json_decode((string)$fields_config, true);
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    if (($field['name'] ?? '') === $parent_field_name && isset($field['fields']) && is_array($field['fields'])) {
                        foreach ($field['fields'] as $nested_field) {
                            $nested_name = $nested_field['name'] ?? '';
                            // Priority: notification_label → label → name
                            $nested_label = $nested_field['notification_label'] ?? $nested_field['label'] ?? $nested_name;
                            if ($nested_name) {
                                $nested_labels_map[$nested_name] = $nested_label;
                            }
                        }
                        break;
                    }
                }
            }
        }
        
        // Get all unique keys from all items
        $all_keys = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $all_keys = array_merge($all_keys, array_keys($item));
            }
        }
        $all_keys = array_unique($all_keys);
        
        if (empty($all_keys)) {
            return '';
        }
        
        $html = '<table style="width: 100%; border-collapse: collapse; margin: 5px 0;">';
        $html .= '<thead><tr style="background-color: #f0f0f0;">';
        foreach ($all_keys as $key) {
            $header_label = $nested_labels_map[$key] ?? $key;
            $html .= '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . esc_html($header_label) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $html .= '<tr>';
            foreach ($all_keys as $key) {
                $val = $item[$key] ?? '';
                if (is_array($val)) {
                    $val = implode(', ', array_map('strval', $val));
                }
                $html .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html((string) $val) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    /**
     * Recursively calculate size of a value for data size validation
     *
     * @param mixed $value Value to calculate size for
     * @return int Size in bytes
     */
    private function calculate_value_size($value): int {
        if (is_array($value)) {
            $size = 0;
            foreach ($value as $k => $v) {
                $size += strlen((string) $k);
                $size += $this->calculate_value_size($v);
            }
            return $size;
        }
        
        if (is_object($value)) {
            return $this->calculate_value_size((array) $value);
        }
        
        return strlen((string) $value);
    }

    /**
     * Heuristic: check if value is URL(s)
     */
    private function looks_like_urls($value): bool {
        if (is_array($value)) {
            if ($value === []) { return false; }
            foreach ($value as $v) {
                if (!is_string($v) || !wp_http_validate_url($v)) {
                    return false;
                }
            }
            return true;
        }
        return is_string($value) && (bool) wp_http_validate_url($value);
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

        // Get page URL and add to form data if not already present
        $page_url = $this->get_page_url();
        if (!empty($page_url) && !isset($form_data['Page URL'])) {
            $form_data['Page URL'] = $page_url;
        }

        // Save meta data
        update_post_meta($submission_id, '_form_id', $form_id);
        update_post_meta($submission_id, '_form_title', $form_title);
        update_post_meta($submission_id, '_submission_data', json_encode($form_data, JSON_UNESCAPED_UNICODE));
        update_post_meta($submission_id, '_submission_date', current_time('mysql'));
        update_post_meta($submission_id, '_submission_ip', sanitize_text_field( wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown') ) );
        update_post_meta($submission_id, '_submission_user_agent', sanitize_text_field( wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') ) );
        if (!empty($page_url)) {
            update_post_meta($submission_id, '_submission_page_url', $page_url);
        }

        return $submission_id;
    }
    
    /**
     * Get page URL from referer or POST
     *
     * @return string Page URL or empty string
     */
    private function get_page_url(): string {
        // Note: This method is called from process_form_submission/save_submission which are already protected by nonce verification in handle_form_submission
        $page_url = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submission
        if (isset($_POST['_wp_http_referer'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submission
            $raw_url = sanitize_text_field( wp_unslash( $_POST['_wp_http_referer'] ) );
        } elseif (isset($_SERVER['HTTP_REFERER'])) {
            $raw_url = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
        } else {
            $raw_url = '';
        }
        
        if (!empty($raw_url)) {
            // Check if URL is absolute (starts with http:// or https://)
            if (preg_match('#^https?://#i', $raw_url)) {
                $page_url = esc_url_raw($raw_url);
            } else {
                // Relative URL - convert to absolute using home_url
                $page_url = esc_url_raw(home_url($raw_url));
            }
        }
        
        return $page_url;
    }
} 