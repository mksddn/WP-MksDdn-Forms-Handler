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
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_post_submit_form', [$this, 'handle_form_submission']);
        add_action('admin_post_nopriv_submit_form', [$this, 'handle_form_submission']);
    }
    
    /**
     * Register REST routes
     */
    public function register_rest_routes(): void {
        register_rest_route(
            'wp/v2',
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
    }
    
    /**
     * Handle REST form submission
     */
    public function handle_rest_form_submission($request): \WP_Error|\WP_REST_Response {
        $slug = $request->get_param('slug');
        $form_data = $request->get_json_params();

        if (!$form_data) {
            return new \WP_Error('invalid_data', 'Invalid form data', ['status' => 400]);
        }

        // Check data size (protection against too large requests)
        if (count($form_data) > 50) {
            return new \WP_Error('too_many_fields', 'Too many form fields submitted', ['status' => 400]);
        }

        // Check total data size
        $total_size = 0;
        foreach ($form_data as $key => $value) {
            $total_size += strlen((string)$key) + strlen((string)$value);
        }

        if ($total_size > 100000) { // Maximum 100KB total data
            return new \WP_Error('data_too_large', 'Form data is too large', ['status' => 400]);
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
     * Handle form submission
     */
    public function handle_form_submission(): void {
        // Check nonce for security
        if (!isset($_POST['form_nonce']) || !wp_verify_nonce($_POST['form_nonce'], 'submit_form_nonce')) {
            wp_send_json_error(['message' => 'Security error. Please try again.']);
        }

        $form_id = sanitize_text_field($_POST['form_id']);
        $form_data = $_POST;

        // Remove service fields
        unset($form_data['form_nonce']);
        unset($form_data['form_id']);
        unset($form_data['action']);
        unset($form_data['_wp_http_referer']);

        // Check data size (protection against too large requests)
        if (count($form_data) > 50) {
            wp_send_json_error(['message' => 'Too many form fields submitted.']);
        }

        // Check total data size
        $total_size = 0;
        foreach ($form_data as $key => $value) {
            $total_size += strlen($key) + strlen((string)$value);
        }

        if ($total_size > 100000) { // Maximum 100KB total data
            wp_send_json_error(['message' => 'Form data is too large.']);
        }

        $result = $this->process_form_submission($form_id, $form_data);

        if (is_wp_error($result)) {
            $response_data = [
                'success' => false,
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
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

            wp_send_json_error($response_data);
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * Process form submission
     */
    private function process_form_submission($form_id, $form_data): \WP_Error|true|array {
        // Get form by slug or ID
        $form = get_page_by_path($form_id, OBJECT, 'forms');
        if (!$form) {
            $form = get_post($form_id);
        }

        if (!$form || $form->post_type !== 'forms') {
            return new \WP_Error('form_not_found', 'Form not found', ['status' => 404]);
        }

        // Get form settings
        $recipients = get_post_meta($form->ID, '_recipients', true);
        $bcc_recipient = get_post_meta($form->ID, '_bcc_recipient', true);
        $subject = get_post_meta($form->ID, '_subject', true);
        $fields_config = get_post_meta($form->ID, '_fields_config', true);
        $send_to_telegram = get_post_meta($form->ID, '_send_to_telegram', true);
        $telegram_bot_token = get_post_meta($form->ID, '_telegram_bot_token', true);
        $telegram_chat_ids = get_post_meta($form->ID, '_telegram_chat_ids', true);
        $send_to_sheets = get_post_meta($form->ID, '_send_to_sheets', true);
        $sheets_spreadsheet_id = get_post_meta($form->ID, '_sheets_spreadsheet_id', true);
        $sheets_sheet_name = get_post_meta($form->ID, '_sheets_sheet_name', true);
        $save_to_admin = get_post_meta($form->ID, '_save_to_admin', true);

        if (!$recipients || !$subject) {
            return new \WP_Error('form_config_error', 'Form is not configured correctly', ['status' => 500]);
        }

        // Filter form data
        $filtered_form_data = $this->filter_form_data($form_data, $fields_config);

        // Check filtering result
        if (is_wp_error($filtered_form_data)) {
            $unauthorized_fields = $this->get_unauthorized_fields($form_data, $fields_config);
            return new \WP_Error(
                'unauthorized_fields',
                'Unauthorized fields detected: ' . implode(', ', $unauthorized_fields),
                [
                    'status'              => 400,
                    'unauthorized_fields' => $unauthorized_fields,
                    'allowed_fields'      => $this->get_allowed_fields($fields_config),
                ]
            );
        }

        // Validate data
        $validation_result = $this->validate_form_data($filtered_form_data, $fields_config);
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
        $email_result = $this->prepare_and_send_email($recipients, $bcc_recipient, $subject, $filtered_form_data, $form->post_title);
        $delivery_results['email']['success'] = !is_wp_error($email_result);
        if (is_wp_error($email_result)) {
            $delivery_results['email']['error'] = $email_result->get_error_message();
        }

        // Send to Telegram
        if ($send_to_telegram && $telegram_bot_token && $telegram_chat_ids) {
            $delivery_results['telegram']['enabled'] = true;
            $telegram_result = \FormsHandler\TelegramHandler::send_message($telegram_bot_token, $telegram_chat_ids, $filtered_form_data, $form->post_title);
            $delivery_results['telegram']['success'] = !is_wp_error($telegram_result);
            if (is_wp_error($telegram_result)) {
                $delivery_results['telegram']['error'] = $telegram_result->get_error_message();
            }
        }

        // Send to Google Sheets
        if ($send_to_sheets && $sheets_spreadsheet_id) {
            $delivery_results['google_sheets']['enabled'] = true;
            $sheets_result = \FormsHandler\GoogleSheetsHandler::send_data($sheets_spreadsheet_id, $sheets_sheet_name, $filtered_form_data, $form->post_title);
            $delivery_results['google_sheets']['success'] = !is_wp_error($sheets_result);
            if (is_wp_error($sheets_result)) {
                $delivery_results['google_sheets']['error'] = $sheets_result->get_error_message();
            }
        }

        // Save to admin
        if ($save_to_admin === '1') {
            $delivery_results['admin_storage']['enabled'] = true;
            $submission_result = $this->save_submission($form->ID, $filtered_form_data, $form->post_title);
            $delivery_results['admin_storage']['success'] = !is_wp_error($submission_result);
            if (is_wp_error($submission_result)) {
                $delivery_results['admin_storage']['error'] = $submission_result->get_error_message();
            }
        }

        // Check overall success
        $email_success = $delivery_results['email']['success'];
        $telegram_success = !$delivery_results['telegram']['enabled'] || $delivery_results['telegram']['success'];
        $sheets_success = !$delivery_results['google_sheets']['enabled'] || $delivery_results['google_sheets']['success'];

        // Return error only if failed to send email, Telegram, and Google Sheets
        if (!$email_success && !$telegram_success && !$sheets_success) {
            return new \WP_Error(
                'send_error',
                'Failed to send email, Telegram notification, and Google Sheets data',
                [
                    'status'           => 500,
                    'delivery_results' => $delivery_results,
                ]
            );
        }

        // Log successful submission
        $this->log_form_submission($form->ID, true);

        return [
            'success'          => true,
            'message'          => 'Form submitted successfully!',
            'form_id'          => $form->ID,
            'form_title'       => $form->post_title,
            'delivery_results' => $delivery_results,
            'submitted_fields' => array_keys($filtered_form_data),
            'timestamp'        => current_time('mysql'),
        ];
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
                'ip'                      => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent'              => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'unauthorized_fields'     => $unauthorized_fields,
                'total_fields_submitted'  => count($form_data),
                'authorized_fields_count' => count($filtered_data),
            ];
            error_log('Form security warning - unauthorized fields attempted: ' . json_encode($log_entry));

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
            return new \WP_Error('validation_error', 'Form fields configuration is missing', ['status' => 400]);
        }

        $fields = json_decode((string)$fields_config, true);
        if (!$fields || !is_array($fields)) {
            return new \WP_Error('validation_error', 'Invalid form fields configuration', ['status' => 400]);
        }

        // Check if there's at least one field
        if ($fields === []) {
            return new \WP_Error('validation_error', 'No fields configured for this form', ['status' => 400]);
        }

        // Check if there's any data
        if (empty($form_data)) {
            return new \WP_Error('validation_error', 'No form data provided', ['status' => 400]);
        }

        foreach ($fields as $field) {
            $field_name = $field['name'];
            $field_label = $field['label'] ?? $field_name;
            $is_required = $field['required'] ?? false;
            $field_type = $field['type'] ?? 'text';

            // Check required fields
            if ($is_required) {
                if ($field_type === 'checkbox') {
                    if (!isset($form_data[$field_name]) || $form_data[$field_name] != '1') {
                        return new \WP_Error('validation_error', sprintf("Field '%s' is required for agreement", $field_label), ['status' => 400]);
                    }
                } elseif (!isset($form_data[$field_name]) || $form_data[$field_name] === '' || $form_data[$field_name] === null) {
                    return new \WP_Error('validation_error', sprintf("Field '%s' is required", $field_label), ['status' => 400]);
                }
            }

            // Check email
            if ($field_type === 'email' && isset($form_data[$field_name]) && !empty($form_data[$field_name]) && !is_email($form_data[$field_name])) {
                return new \WP_Error('validation_error', sprintf("Field '%s' must contain a valid email address", $field_label), ['status' => 400]);
            }

            // Check field length
            if (isset($form_data[$field_name]) && !empty($form_data[$field_name]) && $field_type !== 'checkbox') {
                $value_length = strlen((string)$form_data[$field_name]);
                if ($value_length > 10000) {
                    return new \WP_Error('validation_error', sprintf("Field '%s' is too long (maximum 10,000 characters)", $field_label), ['status' => 400]);
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
            $body .= '<tr>';
            $body .= "<td style='padding: 10px; border: 1px solid #e9e9e9;'><strong>" . esc_html($key) . '</strong></td>';
            $body .= "<td style='padding: 10px; border: 1px solid #e9e9e9;'>" . esc_html($value) . '</td>';
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
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ];

        error_log('Form submission: ' . json_encode($log_entry));
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
            'post_type'    => 'form_submissions',
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
        update_post_meta($submission_id, '_submission_ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        update_post_meta($submission_id, '_submission_user_agent', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

        return $submission_id;
    }
} 