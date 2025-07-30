<?php
/**
 * @file: class-admin-columns.php
 * @description: Handles admin columns customization for forms and submissions
 * @dependencies: WordPress core
 * @created: 2025-07-30
 */

namespace MksDdn\FormsHandler;

/**
 * Handles admin columns for forms and submissions
 */
class AdminColumns {
    
    public function __construct() {
        add_filter('manage_forms_posts_columns', [$this, 'add_forms_admin_columns']);
        add_action('manage_forms_posts_custom_column', [$this, 'fill_forms_admin_columns'], 10, 2);
        add_filter('manage_form_submissions_posts_columns', [$this, 'add_submissions_admin_columns']);
        add_action('manage_form_submissions_posts_custom_column', [$this, 'fill_submissions_admin_columns'], 10, 2);
    }
    
    /**
     * Add admin columns for forms
     */
    public function add_forms_admin_columns($columns): array {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['recipients'] = 'Recipients';
                $new_columns['telegram'] = 'Telegram';
                $new_columns['sheets'] = 'Google Sheets';
                $new_columns['admin_storage'] = 'Admin Storage';
                $new_columns['shortcode'] = 'Shortcode';
                $new_columns['export'] = 'Export';
            }
        }

        return $new_columns;
    }
    
    /**
     * Fill forms admin columns
     */
    public function fill_forms_admin_columns($column, string $post_id): void {
        switch ($column) {
            case 'recipients':
                $recipients = get_post_meta($post_id, '_recipients', true);
                echo esc_html($recipients ?: 'Not configured');
                break;
            case 'telegram':
                $telegram_enabled = get_post_meta($post_id, '_send_to_telegram', true) === '1';
                echo $telegram_enabled ? 'Enabled' : 'Disabled';
                break;
            case 'sheets':
                $sheets_enabled = get_post_meta($post_id, '_send_to_sheets', true) === '1';
                echo $sheets_enabled ? 'Enabled' : 'Disabled';
                break;
            case 'admin_storage':
                $save_to_admin = get_post_meta($post_id, '_save_to_admin', true) === '1';
                echo $save_to_admin ? 'Enabled' : 'Disabled';
                break;
            case 'export':
                $submissions_count = get_posts([
                    'post_type'      => 'form_submissions',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'meta_query'     => [
                        [
                            'key'     => '_form_id',
                            'value'   => $post_id,
                            'compare' => '=',
                        ],
                    ],
                ]);
                $count = count($submissions_count);
                if ($count > 0) {
                    echo '<a href="' . admin_url('admin-post.php?action=export_submissions_csv&form_filter=' . $post_id . '&export_nonce=' . wp_create_nonce('export_submissions_csv')) . '" target="_blank" class="button button-small">Export (' . $count . ')</a>';
                } else {
                    echo '<span style="color: #999;">No submissions</span>';
                }
                break;
            case 'shortcode':
                $post = get_post($post_id);
                echo '<code>[form id="' . esc_attr($post->post_name) . '"]</code>';
                break;
        }
    }
    
    /**
     * Add admin columns for submissions
     */
    public function add_submissions_admin_columns($columns): array {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['form_name'] = 'Form';
                $new_columns['submission_date'] = 'Date';
                $new_columns['submission_ip'] = 'IP Address';
                $new_columns['submission_data'] = 'Data Preview';
            }
        }

        return $new_columns;
    }
    
    /**
     * Fill submissions admin columns
     */
    public function fill_submissions_admin_columns($column, $post_id): void {
        switch ($column) {
            case 'form_name':
                $form_title = get_post_meta($post_id, '_form_title', true);
                echo esc_html($form_title ?: 'Unknown Form');
                break;
            case 'submission_date':
                $date = get_post_meta($post_id, '_submission_date', true);
                echo $date ? date('d.m.Y H:i:s', strtotime($date)) : 'Unknown';
                break;
            case 'submission_ip':
                $ip = get_post_meta($post_id, '_submission_ip', true);
                echo esc_html($ip ?: 'Unknown');
                break;
            case 'submission_data':
                $data = get_post_meta($post_id, '_submission_data', true);
                if ($data) {
                    $data_array = json_decode($data, true);
                    if ($data_array) {
                        $preview = [];
                        foreach ($data_array as $key => $value) {
                            if (count($preview) < 3) {
                                $preview[] = $key . ': ' . substr((string)$value, 0, 20) . (strlen((string)$value) > 20 ? '...' : '');
                            }
                        }

                        echo esc_html(implode(', ', $preview));
                    }
                }
                break;
        }
    }
} 