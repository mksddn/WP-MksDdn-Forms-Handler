<?php
/**
 * @file: class-meta-boxes.php
 * @description: Handles meta boxes for forms and submissions
 * @dependencies: WordPress core
 * @created: 2025-07-30
 */

namespace MksDdn\FormsHandler;

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
            'forms',
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
            'form_submissions',
            'normal',
            'high'
        );

        add_meta_box(
            'submission_info',
            __( 'Submission Info', 'mksddn-forms-handler' ),
            [$this, 'render_submission_info_meta_box'],
            'form_submissions',
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
        $json_error = get_transient('fields_config_json_error_' . get_current_user_id());
        $json_error_value = get_transient('fields_config_json_value_' . get_current_user_id());

        $recipients = get_post_meta($post->ID, '_recipients', true);
        $bcc_recipient = get_post_meta($post->ID, '_bcc_recipient', true);
        $subject = get_post_meta($post->ID, '_subject', true);
        $fields_config = get_post_meta($post->ID, '_fields_config', true);
        $telegram_bot_token = get_post_meta($post->ID, '_telegram_bot_token', true);
        $telegram_chat_ids = get_post_meta($post->ID, '_telegram_chat_ids', true);
        $send_to_telegram = get_post_meta($post->ID, '_send_to_telegram', true);
        $send_to_sheets = get_post_meta($post->ID, '_send_to_sheets', true);
        $sheets_spreadsheet_id = get_post_meta($post->ID, '_sheets_spreadsheet_id', true);
        $sheets_sheet_name = get_post_meta($post->ID, '_sheets_sheet_name', true);
        $save_to_admin = get_post_meta($post->ID, '_save_to_admin', true);

        if ($json_error && $json_error_value !== false) {
            $fields_config = $json_error_value;
        }

        if (!$fields_config) {
            $fields_config = json_encode([
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
            ]);
        }

        // Show error notification if invalid JSON
        if ($json_error) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Error: Invalid JSON in Fields Configuration! Check syntax.', 'mksddn-forms-handler' ) . '</p></div>';
            delete_transient('fields_config_json_error_' . get_current_user_id());
            delete_transient('fields_config_json_value_' . get_current_user_id());
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
            echo '<td>' . esc_html($value) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
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
        if (!isset($_POST['form_settings_nonce']) || !wp_verify_nonce( wp_unslash($_POST['form_settings_nonce']), 'save_form_settings')) {
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

        if (isset($_POST['fields_config'])) {
            $fields_config = wp_unslash($_POST['fields_config']);
            // Validate JSON to avoid saving invalid data
            if (json_decode($fields_config) !== null) {
                update_post_meta($post_id, '_fields_config', $fields_config);
            } else {
                // Save error and entered data to temporary storage
                set_transient('fields_config_json_error_' . get_current_user_id(), true, 60);
                set_transient('fields_config_json_value_' . get_current_user_id(), $fields_config, 60);
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
    }
} 