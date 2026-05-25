<?php
/**
 * @file: class-meta-boxes.php
 * @description: Handles meta boxes for forms and submissions
 * @dependencies: WordPress core
 * @created: 2025-07-30
 */

namespace MksDdn\FormsHandler;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles meta boxes for forms and submissions
 */
class MetaBoxes {
    
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_forms_meta_boxes']);
        add_action('add_meta_boxes', [$this, 'add_submissions_meta_boxes']);
        add_action('save_post', [$this, 'save_form_settings']);
        add_action('post_edit_form_tag', [$this, 'add_form_edit_enctype']);
    }

    /**
     * Add multipart enctype for form settings file uploads
     */
    public function add_form_edit_enctype(): void {
        global $post;

        if ($post && $post->post_type === 'mksddn_fh_forms') {
            echo ' enctype="multipart/form-data"';
        }
    }
    
    /**
     * Add meta boxes for forms
     */
    public function add_forms_meta_boxes(): void {
        add_meta_box(
            'form_settings',
            __( 'Form Settings', 'mksddn-forms-handler' ),
            [$this, 'render_form_settings_meta_box'],
            'mksddn_fh_forms',
            'normal',
            'high'
        );
    }
    
    /**
     * Add meta boxes for submissions
     */
    public function add_submissions_meta_boxes(): void {
        add_meta_box(
            'submission_data',
            __( 'Submission Data', 'mksddn-forms-handler' ),
            [$this, 'render_submission_data_meta_box'],
            'mksddn_fh_submits',
            'normal',
            'high'
        );

        add_meta_box(
            'submission_info',
            __( 'Submission Info', 'mksddn-forms-handler' ),
            [$this, 'render_submission_info_meta_box'],
            'mksddn_fh_submits',
            'side',
            'high'
        );
    }
    
    /**
     * Render form settings meta box
     */
    public function render_form_settings_meta_box($post): void {
        wp_nonce_field('save_form_settings', 'form_settings_nonce');

        // Check for JSON error temporary data
        $json_error = get_transient('mksddn_fh_fields_config_json_error_' . get_current_user_id());
        $json_error_value = get_transient('mksddn_fh_fields_config_json_value_' . get_current_user_id());

        $recipients = get_post_meta($post->ID, '_recipients', true);
        $bcc_recipient = get_post_meta($post->ID, '_bcc_recipient', true);
        $subject = get_post_meta($post->ID, '_subject', true);
        $send_to_email = get_post_meta($post->ID, '_send_to_email', true);
        $fields_config = get_post_meta($post->ID, '_fields_config', true);
        $telegram_bot_token = get_post_meta($post->ID, '_telegram_bot_token', true);
        $telegram_chat_ids = get_post_meta($post->ID, '_telegram_chat_ids', true);
        $send_to_telegram = get_post_meta($post->ID, '_send_to_telegram', true);
        $use_custom_telegram_template = get_post_meta($post->ID, '_use_custom_telegram_template', true);
        $telegram_template = get_post_meta($post->ID, '_telegram_template', true);
        
        // Generate default template for JavaScript (always generate if fields_config exists)
        $default_telegram_template = '';
        if ($fields_config) {
            $default_telegram_template = TemplateParser::get_default_template($fields_config);
        }
        
        // If custom template is enabled but template is empty, use default template
        if ($use_custom_telegram_template && empty($telegram_template) && $fields_config) {
            $telegram_template = $default_telegram_template;
        }
        $send_to_sheets = get_post_meta($post->ID, '_send_to_sheets', true);
        $sheets_spreadsheet_id = get_post_meta($post->ID, '_sheets_spreadsheet_id', true);
        $sheets_sheet_name = get_post_meta($post->ID, '_sheets_sheet_name', true);
        $save_to_admin = get_post_meta($post->ID, '_save_to_admin', true);
        $allow_any_fields = get_post_meta($post->ID, '_allow_any_fields', true);
        $submit_button_text = get_post_meta($post->ID, '_submit_button_text', true);
        $custom_html_after_button = get_post_meta($post->ID, '_custom_html_after_button', true);
        $success_message_text = get_post_meta($post->ID, '_success_message_text', true);
        $redirect_url = get_post_meta($post->ID, '_redirect_url', true);
        $form_custom_classes = get_post_meta($post->ID, '_form_custom_classes', true);
        $send_user_reply = get_post_meta($post->ID, '_send_user_reply', true);
        $user_reply_email_field = get_post_meta($post->ID, '_user_reply_email_field', true);
        $user_reply_type = get_post_meta($post->ID, '_user_reply_type', true) ?: 'text';
        $user_reply_subject = get_post_meta($post->ID, '_user_reply_subject', true);
        $user_reply_message = get_post_meta($post->ID, '_user_reply_message', true);
        $user_reply_html_template = get_post_meta($post->ID, '_user_reply_html_template', true);
        $user_reply_html_template_filename = get_post_meta($post->ID, '_user_reply_html_template_filename', true);
        $user_reply_email_fields = self::get_email_fields_from_config($fields_config);
        $user_reply_html_max_size = (int) apply_filters('mksddn_fh_max_html_template_size', 102400);
        $user_reply_html_max_kb = max(1, (int) ceil($user_reply_html_max_size / 1024));

        if (empty($user_reply_subject)) {
            $user_reply_subject = __( 'Thank you for contacting us', 'mksddn-forms-handler' );
        }

        if (empty($user_reply_message)) {
            $user_reply_message = TemplateParser::get_default_user_reply_template();
        }

        $user_reply_html_configured = !empty(trim((string) $user_reply_html_template));
        $user_reply_html_ready = ($send_user_reply === '1' && $user_reply_type === 'html' && $user_reply_html_configured);
        $user_reply_admin_notice = $this->get_and_clear_form_admin_notice($post->ID);

        // Set default values based on language if empty (only for new posts or when not set)
        $locale = get_locale();
        
        // Set default submit button text
        if (empty($submit_button_text)) {
            $submit_button_text = __( 'Send', 'mksddn-forms-handler' );
        }
        
        // Set default custom HTML after button based on locale
        if (empty($custom_html_after_button) && strpos($locale, 'ru') === 0) {
            // Only set default if this is a new post (auto-draft) or field is truly empty
            if ($post->post_status === 'auto-draft' || !get_post_meta($post->ID, '_custom_html_after_button', true)) {
                $custom_html_after_button = '<small>' . __( 'By clicking the button, you agree to the', 'mksddn-forms-handler' ) . ' <a href="/privacy-policy">' . __( 'privacy policy', 'mksddn-forms-handler' ) . '</a></small>';
            }
        }
        
        // Set default success message text
        if (empty($success_message_text)) {
            $success_message_text = __( 'Thank you! Your message has been sent successfully.', 'mksddn-forms-handler' );
        }

        if ($json_error && $json_error_value !== false) {
            $fields_config = $json_error_value;
        }

        if (!$fields_config) {
            $fields_config = wp_json_encode([
                [
                    'name'     => 'name',
                    'label'    => 'Name',
                    'type'     => 'text',
                    'required' => true,
                ],
                [
                    'name'     => 'email',
                    'label'    => 'Email',
                    'type'     => 'email',
                    'required' => true,
                ],
                [
                    'name'     => 'message',
                    'label'    => 'Message',
                    'type'     => 'textarea',
                    'required' => true,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        // Show error notification if invalid JSON
        if ($json_error) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Error: Invalid JSON in Fields Configuration! Check syntax.', 'mksddn-forms-handler' ) . '</p></div>';
            delete_transient('mksddn_fh_fields_config_json_error_' . get_current_user_id());
            delete_transient('mksddn_fh_fields_config_json_value_' . get_current_user_id());
        }

        include MKSDDN_FORMS_HANDLER_PLUGIN_DIR . '/templates/form-settings-meta-box.php';
    }
    
    /**
     * Render submission data meta box
     */
    public function render_submission_data_meta_box($post): void {
        $submission_data = get_post_meta($post->ID, '_submission_data', true);
        $data_array = json_decode($submission_data, true);

        if (!$data_array) {
            echo '<p>' . esc_html__( 'No data available', 'mksddn-forms-handler' ) . '</p>';
            return;
        }

        echo '<table class="form-table">';
        foreach ($data_array as $key => $value) {
            echo '<tr>';
            echo '<th scope="row"><label>' . esc_html($key) . '</label></th>';
            echo '<td>';
            
            if (is_array($value) && $this->is_array_of_objects($value)) {
                // Render array of objects (e.g., products) as a table
                echo wp_kses_post($this->render_array_of_objects_table($value));
            } elseif (is_array($value)) {
                // Simple array: render as comma-separated list
                $parts = [];
                foreach ($value as $v) {
                    if (is_array($v)) {
                        // Nested array: convert to JSON string
                        $parts[] = esc_html(wp_json_encode($v, JSON_UNESCAPED_UNICODE));
                    } else {
                        $v_str = (string) $v;
                        if (preg_match('#^https?://#i', $v_str)) {
                            $parts[] = '<a href="' . esc_url($v_str) . '" target="_blank" rel="noopener noreferrer">' . esc_html($v_str) . '</a>';
                        } else {
                            $parts[] = esc_html($v_str);
                        }
                    }
                }
                echo wp_kses_post(implode(', ', $parts));
            } else {
                $v_str = (string) $value;
                if (preg_match('#^https?://#i', $v_str)) {
                    echo '<a href="' . esc_url($v_str) . '" target="_blank" rel="noopener noreferrer">' . esc_html($v_str) . '</a>';
                } else {
                    echo esc_html($v_str);
                }
            }
            
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
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
        
        $first = reset($value);
        if (!is_array($first)) {
            return false;
        }
        
        $keys = array_keys($first);
        return !empty($keys) && array_keys($keys) !== $keys;
    }
    
    /**
     * Render array of objects as HTML table
     *
     * @param array $items Array of objects/associative arrays
     * @return string HTML table
     */
    private function render_array_of_objects_table(array $items): string {
        if (empty($items)) {
            return '';
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
        
        $html = '<table class="widefat" style="margin-top: 10px;">';
        $html .= '<thead><tr>';
        foreach ($all_keys as $key) {
            $html .= '<th style="padding: 8px; background-color: #f0f0f0; border: 1px solid #ddd;">' . esc_html($key) . '</th>';
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
                    $val = wp_json_encode($val, JSON_UNESCAPED_UNICODE);
                }
                $html .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html((string) $val) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    /**
     * Render submission info meta box
     */
    public function render_submission_info_meta_box($post): void {
        $form_title = get_post_meta($post->ID, '_form_title', true);
        $submission_date = get_post_meta($post->ID, '_submission_date', true);
        $submission_ip = get_post_meta($post->ID, '_submission_ip', true);
        $user_agent = get_post_meta($post->ID, '_submission_user_agent', true);

        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html__( 'Form:', 'mksddn-forms-handler' ) . '</th><td>' . esc_html($form_title ?: __( 'Unknown', 'mksddn-forms-handler' )) . '</td></tr>';
        $date_display = $submission_date ? wp_date('d.m.Y H:i:s', strtotime($submission_date)) : __( 'Unknown', 'mksddn-forms-handler' );
        echo '<tr><th>' . esc_html__( 'Date:', 'mksddn-forms-handler' ) . '</th><td>' . esc_html($date_display) . '</td></tr>';
        echo '<tr><th>' . esc_html__( 'IP Address:', 'mksddn-forms-handler' ) . '</th><td>' . esc_html($submission_ip ?: __( 'Unknown', 'mksddn-forms-handler' )) . '</td></tr>';
        echo '<tr><th>' . esc_html__( 'User Agent:', 'mksddn-forms-handler' ) . '</th><td>' . esc_html($user_agent ?: __( 'Unknown', 'mksddn-forms-handler' )) . '</td></tr>';
        echo '</table>';
    }
    
    /**
     * Save form settings
     */
    public function save_form_settings($post_id): void {
        $form_settings_nonce = isset($_POST['form_settings_nonce']) ? sanitize_text_field( wp_unslash($_POST['form_settings_nonce']) ) : '';
        if (!$form_settings_nonce || !wp_verify_nonce( $form_settings_nonce, 'save_form_settings')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (get_post_type($post_id) !== 'mksddn_fh_forms') {
            return;
        }

        if (isset($_POST['recipients'])) {
            update_post_meta($post_id, '_recipients', sanitize_text_field( wp_unslash($_POST['recipients']) ));
        }

        if (isset($_POST['bcc_recipient'])) {
            update_post_meta($post_id, '_bcc_recipient', sanitize_email( wp_unslash($_POST['bcc_recipient']) ));
        }

        if (isset($_POST['subject'])) {
            update_post_meta($post_id, '_subject', sanitize_text_field( wp_unslash($_POST['subject']) ));
        }

        if (isset($_POST['send_to_email'])) {
            update_post_meta($post_id, '_send_to_email', '1');
        } else {
            update_post_meta($post_id, '_send_to_email', '0');
        }

        if (isset($_POST['fields_config'])) {
            // Raw JSON is unslashed first; content is sanitized below
            $raw_json = wp_unslash($_POST['fields_config']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $decoded = json_decode($raw_json, true);
            if (is_array($decoded)) {
                $sanitized = Utilities::sanitize_fields_config_for_storage($decoded);
                $json_encoded = wp_json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                // WordPress will strip slashes when loading, so we need to add them before saving
                update_post_meta($post_id, '_fields_config', wp_slash($json_encoded));
            } else {
                set_transient('mksddn_fh_fields_config_json_error_' . get_current_user_id(), true, 60);
                set_transient('mksddn_fh_fields_config_json_value_' . get_current_user_id(), $raw_json, 60);
            }
        }

        if (isset($_POST['send_to_telegram'])) {
            update_post_meta($post_id, '_send_to_telegram', '1');
        } else {
            update_post_meta($post_id, '_send_to_telegram', '0');
        }

        if (isset($_POST['telegram_bot_token'])) {
            update_post_meta($post_id, '_telegram_bot_token', sanitize_text_field( wp_unslash($_POST['telegram_bot_token']) ));
        }

        if (isset($_POST['telegram_chat_ids'])) {
            update_post_meta($post_id, '_telegram_chat_ids', sanitize_text_field( wp_unslash($_POST['telegram_chat_ids']) ));
        }

        if (isset($_POST['use_custom_telegram_template'])) {
            update_post_meta($post_id, '_use_custom_telegram_template', '1');
            
            // If template is empty, generate default template
            $fields_config = get_post_meta($post_id, '_fields_config', true);
            $current_template = isset($_POST['telegram_template']) ? sanitize_textarea_field(wp_unslash($_POST['telegram_template'])) : '';
            
            if (empty(trim($current_template)) && $fields_config) {
                $default_template = TemplateParser::get_default_template($fields_config);
                update_post_meta($post_id, '_telegram_template', sanitize_textarea_field($default_template));
            } elseif (isset($_POST['telegram_template'])) {
                // Sanitize template but preserve placeholders and HTML tags
                $template = sanitize_textarea_field(wp_unslash($_POST['telegram_template']));
                update_post_meta($post_id, '_telegram_template', $template);
            }
        } else {
            update_post_meta($post_id, '_use_custom_telegram_template', '0');
            
            // Save template even if checkbox is unchecked (user might want to re-enable later)
            if (isset($_POST['telegram_template'])) {
                $template = sanitize_textarea_field(wp_unslash($_POST['telegram_template']));
                update_post_meta($post_id, '_telegram_template', $template);
            }
        }

        if (isset($_POST['send_to_sheets'])) {
            update_post_meta($post_id, '_send_to_sheets', '1');
        } else {
            update_post_meta($post_id, '_send_to_sheets', '0');
        }

        if (isset($_POST['sheets_spreadsheet_id'])) {
            update_post_meta($post_id, '_sheets_spreadsheet_id', sanitize_text_field( wp_unslash($_POST['sheets_spreadsheet_id']) ));
        }

        if (isset($_POST['sheets_sheet_name'])) {
            update_post_meta($post_id, '_sheets_sheet_name', sanitize_text_field( wp_unslash($_POST['sheets_sheet_name']) ));
        }

        if (isset($_POST['save_to_admin'])) {
            update_post_meta($post_id, '_save_to_admin', '1');
        } else {
            update_post_meta($post_id, '_save_to_admin', '0');
        }

        if (isset($_POST['allow_any_fields'])) {
            update_post_meta($post_id, '_allow_any_fields', '1');
        } else {
            update_post_meta($post_id, '_allow_any_fields', '0');
        }

        if (isset($_POST['submit_button_text'])) {
            update_post_meta($post_id, '_submit_button_text', sanitize_text_field( wp_unslash($_POST['submit_button_text']) ));
        }

        if (isset($_POST['custom_html_after_button'])) {
            // Allow HTML but sanitize it
            update_post_meta($post_id, '_custom_html_after_button', wp_kses_post( wp_unslash($_POST['custom_html_after_button']) ));
        }

        if (isset($_POST['success_message_text'])) {
            update_post_meta($post_id, '_success_message_text', sanitize_text_field( wp_unslash($_POST['success_message_text']) ));
        }

        if (isset($_POST['redirect_url'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below based on URL type
            $raw_url = trim(wp_unslash($_POST['redirect_url']));
            if (!empty($raw_url)) {
                // Check if URL is absolute (starts with http:// or https://)
                if (preg_match('#^https?://#i', $raw_url)) {
                    // Sanitize absolute URL before parsing
                    $raw_url = esc_url_raw($raw_url);
                    // Validate absolute URL - only allow same domain for security
                    $url_host = wp_parse_url($raw_url, PHP_URL_HOST);
                    $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
                    
                    if ($url_host === $site_host) {
                        // Same domain - safe to use
                        $redirect_url = $raw_url;
                    } else {
                        // External domain - check whitelist
                        $allowed_hosts = apply_filters('mksddn_fh_allowed_redirect_hosts', []);
                        if (in_array($url_host, $allowed_hosts, true)) {
                            $redirect_url = $raw_url;
                        } else {
                            // External domain not allowed
                            add_settings_error(
                                'mksddn_fh',
                                'redirect_external',
                                sprintf(
                                    /* translators: %s: external domain */
                                    __('External redirect URLs are not allowed (%s). Use relative URLs or add domain to whitelist.', 'mksddn-forms-handler'),
                                    esc_html($url_host)
                                )
                            );
                            $redirect_url = '';
                        }
                    }
                } else {
                    // Relative path - sanitize and ensure it starts with /
                    $redirect_url = sanitize_text_field($raw_url);
                    if (!empty($redirect_url) && !preg_match('#^/#', $redirect_url)) {
                        $redirect_url = '/' . ltrim($redirect_url, '/');
                    }
                }
                
                if (!empty($redirect_url)) {
                    update_post_meta($post_id, '_redirect_url', $redirect_url);
                } else {
                    delete_post_meta($post_id, '_redirect_url');
                }
            } else {
                delete_post_meta($post_id, '_redirect_url');
            }
        }

        if (isset($_POST['form_custom_classes'])) {
            update_post_meta($post_id, '_form_custom_classes', sanitize_text_field( wp_unslash($_POST['form_custom_classes']) ));
        }

        if (isset($_POST['send_user_reply'])) {
            update_post_meta($post_id, '_send_user_reply', '1');
        } else {
            update_post_meta($post_id, '_send_user_reply', '0');
        }

        if (isset($_POST['user_reply_email_field'])) {
            $user_reply_field_name = sanitize_key(wp_unslash($_POST['user_reply_email_field']));
            $fields_config_for_reply = get_post_meta($post_id, '_fields_config', true);
            $allowed_email_fields = array_column(
                self::get_email_fields_from_config($fields_config_for_reply),
                'name'
            );

            if ($user_reply_field_name === '' || in_array($user_reply_field_name, $allowed_email_fields, true)) {
                update_post_meta($post_id, '_user_reply_email_field', $user_reply_field_name);
            } else {
                delete_post_meta($post_id, '_user_reply_email_field');
                if (false === $this->peek_form_admin_notice($post_id)) {
                    $this->set_form_admin_notice(
                        $post_id,
                        'warning',
                        __('Selected user email field is not valid. Choose an email field from the form configuration.', 'mksddn-forms-handler')
                    );
                }
            }
        }

        $user_reply_type = isset($_POST['user_reply_type']) ? sanitize_key( wp_unslash($_POST['user_reply_type']) ) : 'text';
        if (!in_array($user_reply_type, ['text', 'html'], true)) {
            $user_reply_type = 'text';
        }
        update_post_meta($post_id, '_user_reply_type', $user_reply_type);

        if (isset($_POST['user_reply_subject'])) {
            update_post_meta($post_id, '_user_reply_subject', sanitize_text_field( wp_unslash($_POST['user_reply_subject']) ));
        }

        if (isset($_POST['user_reply_message'])) {
            update_post_meta($post_id, '_user_reply_message', wp_kses_post(wp_unslash($_POST['user_reply_message'])));
        }

        if (isset($_POST['remove_user_reply_html_template']) && $_POST['remove_user_reply_html_template'] === '1') {
            delete_post_meta($post_id, '_user_reply_html_template');
            delete_post_meta($post_id, '_user_reply_html_template_filename');
            Utilities::clear_form_config_cache($post_id);
        }

        $this->maybe_save_user_reply_html_template($post_id);

        $send_user_reply_enabled = isset($_POST['send_user_reply']);
        if ($send_user_reply_enabled && $user_reply_type === 'html' && empty($_FILES['user_reply_html_file']['name'])) {
            $html_template = get_post_meta($post_id, '_user_reply_html_template', true);
            if (empty(trim((string) $html_template)) && false === $this->peek_form_admin_notice($post_id)) {
                $this->set_form_admin_notice(
                    $post_id,
                    'warning',
                    __('HTML template is required when HTML file reply type is selected. Upload a file and save the form.', 'mksddn-forms-handler')
                );
            }
        }
    }

    /**
     * Extract email fields from fields configuration JSON
     *
     * @param string|null $fields_config Fields configuration JSON
     * @return array<int, array{name: string, label: string}>
     */
    public static function get_email_fields_from_config($fields_config): array {
        if (empty($fields_config)) {
            return [];
        }

        $fields = json_decode((string) $fields_config, true);
        if (!is_array($fields)) {
            return [];
        }

        $email_fields = [];
        foreach ($fields as $field) {
            if (!isset($field['name'], $field['type']) || $field['type'] !== 'email') {
                continue;
            }

            $email_fields[] = [
                'name'  => (string) $field['name'],
                'label' => (string) ($field['notification_label'] ?? $field['label'] ?? $field['name']),
            ];
        }

        return $email_fields;
    }

    /**
     * Save uploaded HTML template for user reply email
     *
     * Reads the file directly from the upload temp path (no Media Library upload).
     *
     * @param int $post_id Form post ID
     */
    private function maybe_save_user_reply_html_template(int $post_id): void {
        // Nonce verified in save_form_settings() before this method is called.
        if (empty($_FILES['user_reply_html_file']['name'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- file upload validated below via is_uploaded_file() and sanitize_file_name().
        $file = $_FILES['user_reply_html_file'];
        $upload_error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if (UPLOAD_ERR_NO_FILE === $upload_error) {
            return;
        }

        if (UPLOAD_ERR_OK !== $upload_error) {
            $message = $this->get_upload_error_message($upload_error);
            $this->set_form_admin_notice(
                $post_id,
                'error',
                $message ?: __('Failed to upload HTML template file.', 'mksddn-forms-handler')
            );
            return;
        }

        $max_size = (int) apply_filters('mksddn_fh_max_html_template_size', 102400);
        if (!empty($file['size']) && (int) $file['size'] > $max_size) {
            $this->set_form_admin_notice(
                $post_id,
                'error',
                __('HTML template file exceeds maximum allowed size.', 'mksddn-forms-handler')
            );
            return;
        }

        $filename = sanitize_file_name(wp_unslash($file['name']));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, ['html', 'htm'], true)) {
            $this->set_form_admin_notice(
                $post_id,
                'error',
                __('Only .html and .htm files are allowed for email templates.', 'mksddn-forms-handler')
            );
            return;
        }

        $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
            $this->set_form_admin_notice(
                $post_id,
                'error',
                __('Could not read uploaded HTML template file.', 'mksddn-forms-handler')
            );
            return;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading uploaded temp file
        $content = file_get_contents($tmp_name);
        if ($content === false || $content === '') {
            $this->set_form_admin_notice(
                $post_id,
                'error',
                __('Could not read uploaded HTML template file.', 'mksddn-forms-handler')
            );
            return;
        }

        if (strlen($content) > $max_size) {
            $this->set_form_admin_notice(
                $post_id,
                'error',
                __('HTML template file exceeds maximum allowed size.', 'mksddn-forms-handler')
            );
            return;
        }

        if (stripos($content, '<?php') !== false || stripos($content, '<?=') !== false) {
            $this->set_form_admin_notice(
                $post_id,
                'error',
                __('HTML template must not contain PHP code.', 'mksddn-forms-handler')
            );
            return;
        }

        $content = Utilities::sanitize_email_html_template($content);

        update_post_meta($post_id, '_user_reply_html_template', $content);
        update_post_meta($post_id, '_user_reply_html_template_filename', $filename);
        Utilities::clear_form_config_cache($post_id);

        $this->set_form_admin_notice(
            $post_id,
            'success',
            sprintf(
                /* translators: 1: file name, 2: file size in KB */
                __('HTML template saved: %1$s (%2$s KB). It will be used for auto-reply emails.', 'mksddn-forms-handler'),
                $filename,
                number_format(strlen($content) / 1024, 1)
            )
        );
    }

    /**
     * Transient key for form save notices (per post and user)
     *
     * @param int $post_id Form post ID
     */
    private function get_form_admin_notice_key(int $post_id): string {
        return 'mksddn_fh_form_notice_' . $post_id . '_' . get_current_user_id();
    }

    /**
     * Store an admin notice to show after redirect on form save
     *
     * @param int    $post_id Form post ID
     * @param string $type    notice-success|notice-error|notice-warning
     * @param string $message Notice message
     */
    private function set_form_admin_notice(int $post_id, string $type, string $message): void {
        set_transient(
            $this->get_form_admin_notice_key($post_id),
            [
                'type'    => $type,
                'message' => $message,
            ],
            60
        );
    }

    /**
     * Check whether a form save notice is already queued
     *
     * @param int $post_id Form post ID
     */
    private function peek_form_admin_notice(int $post_id): bool {
        return false !== get_transient($this->get_form_admin_notice_key($post_id));
    }

    /**
     * Get and remove a form save notice for display in the meta box
     *
     * @param int $post_id Form post ID
     * @return array{type: string, message: string}|null
     */
    private function get_and_clear_form_admin_notice(int $post_id): ?array {
        $key = $this->get_form_admin_notice_key($post_id);
        $notice = get_transient($key);
        if ($notice) {
            delete_transient($key);
        }

        if (!is_array($notice) || empty($notice['message'])) {
            return null;
        }

        $type = in_array($notice['type'] ?? '', ['success', 'error', 'warning'], true) ? $notice['type'] : 'warning';

        return [
            'type'    => $type,
            'message' => (string) $notice['message'],
        ];
    }

    /**
     * Map PHP upload error codes to a readable message
     *
     * @param int $error_code PHP upload error constant
     */
    private function get_upload_error_message(int $error_code): string {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('HTML template file exceeds maximum allowed size.', 'mksddn-forms-handler');
            case UPLOAD_ERR_PARTIAL:
                return __('HTML template upload was interrupted. Please try again.', 'mksddn-forms-handler');
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                return __('Server could not store the uploaded HTML template file.', 'mksddn-forms-handler');
            default:
                return __('Failed to upload HTML template file.', 'mksddn-forms-handler');
        }
    }

} 