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
        $send_to_sheets = get_post_meta($post->ID, '_send_to_sheets', true);
        $sheets_spreadsheet_id = get_post_meta($post->ID, '_sheets_spreadsheet_id', true);
        $sheets_sheet_name = get_post_meta($post->ID, '_sheets_sheet_name', true);
        $save_to_admin = get_post_meta($post->ID, '_save_to_admin', true);
        $allow_any_fields = get_post_meta($post->ID, '_allow_any_fields', true);
        $submit_button_text = get_post_meta($post->ID, '_submit_button_text', true);
        $custom_html_after_button = get_post_meta($post->ID, '_custom_html_after_button', true);
        $success_message_text = get_post_meta($post->ID, '_success_message_text', true);
        $form_custom_classes = get_post_meta($post->ID, '_form_custom_classes', true);

        // Set default values based on language if empty (only for new posts or when not set)
        $locale = get_locale();
        
        // Set default submit button text
        if (empty($submit_button_text)) {
            $submit_button_text = __( 'Send', 'mksddn-forms-handler' );
        }
        
        // Set default custom HTML after button for Russian
        if (empty($custom_html_after_button) && strpos($locale, 'ru') === 0) {
            // Only set default if this is a new post (auto-draft) or field is truly empty
            if ($post->post_status === 'auto-draft' || !get_post_meta($post->ID, '_custom_html_after_button', true)) {
                $custom_html_after_button = '<small>Нажимая кнопку, вы соглашаетесь с <a href="/privacy-policy">политикой конфиденциальности</a></small>';
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

        if (isset($_POST['form_custom_classes'])) {
            update_post_meta($post_id, '_form_custom_classes', sanitize_text_field( wp_unslash($_POST['form_custom_classes']) ));
        }
    }
} 