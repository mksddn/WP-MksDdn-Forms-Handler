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
    
    /**
     * Cache for submissions count
     * @var array
     */
    private $submissions_cache = [];
    
    public function __construct() {
        add_filter('manage_mksddn_fh_forms_posts_columns', [$this, 'add_forms_admin_columns']);
        add_action('manage_mksddn_fh_forms_posts_custom_column', [$this, 'fill_forms_admin_columns'], 10, 2);
        add_filter('manage_mksddn_fh_submits_posts_columns', [$this, 'add_submissions_admin_columns']);
        add_action('manage_mksddn_fh_submits_posts_custom_column', [$this, 'fill_submissions_admin_columns'], 10, 2);
        
        // Clear cache when submissions are added/updated
        add_action('save_post_form_submissions', [$this, 'clear_submissions_cache'], 10, 2);
        add_action('deleted_post', [$this, 'clear_submissions_cache'], 10, 2);
    }
    
    /**
     * Add admin columns for forms
     */
    public function add_forms_admin_columns($columns): array {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['recipients'] = __( 'Recipients', 'mksddn-forms-handler' );
                $new_columns['telegram'] = __( 'Telegram', 'mksddn-forms-handler' );
                $new_columns['sheets'] = __( 'Google Sheets', 'mksddn-forms-handler' );
                $new_columns['admin_storage'] = __( 'Admin Storage', 'mksddn-forms-handler' );
                $new_columns['shortcode'] = __( 'Shortcode', 'mksddn-forms-handler' );
                $new_columns['export'] = __( 'Export', 'mksddn-forms-handler' );
            }
        }

        return $new_columns;
    }
    
    /**
     * Fill forms admin columns with optimized performance
     */
    public function fill_forms_admin_columns($column, string $post_id): void {
        switch ($column) {
            case 'recipients':
                $recipients = get_post_meta($post_id, '_recipients', true);
                echo esc_html($recipients ?: 'Not configured');
                break;
            case 'telegram':
                $telegram_enabled = get_post_meta($post_id, '_send_to_telegram', true) === '1';
                echo $telegram_enabled ? '<span style="color: green;">' . esc_html__( '✓ Enabled', 'mksddn-forms-handler' ) . '</span>' : '<span style="color: #999;">' . esc_html__( '✗ Disabled', 'mksddn-forms-handler' ) . '</span>';
                break;
            case 'sheets':
                $sheets_enabled = get_post_meta($post_id, '_send_to_sheets', true) === '1';
                echo $sheets_enabled ? '<span style="color: green;">' . esc_html__( '✓ Enabled', 'mksddn-forms-handler' ) . '</span>' : '<span style="color: #999;">' . esc_html__( '✗ Disabled', 'mksddn-forms-handler' ) . '</span>';
                break;
            case 'admin_storage':
                $save_to_admin = get_post_meta($post_id, '_save_to_admin', true) === '1';
                echo $save_to_admin ? '<span style="color: green;">' . esc_html__( '✓ Enabled', 'mksddn-forms-handler' ) . '</span>' : '<span style="color: #999;">' . esc_html__( '✗ Disabled', 'mksddn-forms-handler' ) . '</span>';
                break;
            case 'export':
                $count = $this->get_submissions_count($post_id);
                if ($count > 0) {
                    $export_url = admin_url('admin-post.php?action=export_submissions_csv&form_filter=' . $post_id . '&export_nonce=' . wp_create_nonce('export_submissions_csv'));
                    echo '<a href="' . esc_url($export_url) . '" target="_blank" class="button button-small">' . esc_html__( 'Export', 'mksddn-forms-handler' ) . ' (' . intval($count) . ')</a>';
                } else {
                    echo '<span style="color: #999;">' . esc_html__( 'No submissions', 'mksddn-forms-handler' ) . '</span>';
                }
                break;
            case 'shortcode':
                $post = get_post($post_id);
                if ($post) {
                    echo '<code>[form id="' . esc_attr($post->post_name) . '"]</code>';
                    echo '<br><small style="color: #666;">' . esc_html__( 'Copy to clipboard', 'mksddn-forms-handler' ) . '</small>';
                }
                break;
        }
    }
    
    /**
     * Get cached submissions count for a form
     */
    private function get_submissions_count($form_id): int {
        $cache_key = 'submissions_count_' . $form_id;
        
        // Try to get from cache first
        $cached_count = wp_cache_get($cache_key, 'mksddn_forms_handler');
        if ($cached_count !== false) {
            return (int)$cached_count;
        }
        
        // Count submissions with optimized query
        $submissions = get_posts([
            'post_type'      => 'mksddn_fh_submits',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids', // Only get IDs for better performance
            'meta_query'     => [
                [
                    'key'     => '_form_id',
                    'value'   => $form_id,
                    'compare' => '=',
                ],
            ],
        ]);
        
        $count = count($submissions);
        
        // Cache the result for 1 hour
        wp_cache_set($cache_key, $count, 'mksddn_forms_handler', 3600);
        
        return $count;
    }
    
    /**
     * Clear submissions cache when submissions are updated
     */
    public function clear_submissions_cache($post_id, $post): void {
        if ($post->post_type === 'mksddn_fh_submits') {
            $form_id = get_post_meta($post_id, '_form_id', true);
            if ($form_id) {
                $cache_key = 'submissions_count_' . $form_id;
                wp_cache_delete($cache_key, 'mksddn_forms_handler');
            }
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
                $new_columns['form_name'] = __( 'Form', 'mksddn-forms-handler' );
                $new_columns['submission_date'] = __( 'Date', 'mksddn-forms-handler' );
                $new_columns['submission_data'] = __( 'Data', 'mksddn-forms-handler' );
                $new_columns['delivery_status'] = __( 'Delivery Status', 'mksddn-forms-handler' );
            }
        }

        return $new_columns;
    }
    
    /**
     * Fill submissions admin columns with optimized performance
     */
    public function fill_submissions_admin_columns($column, $post_id): void {
        switch ($column) {
            case 'form_name':
                $form_id = get_post_meta($post_id, '_form_id', true);
                if ($form_id) {
                    $form = get_post($form_id);
                    if ($form) {
                        echo '<a href="' . esc_url( get_edit_post_link($form_id) ) . '">' . esc_html($form->post_title) . '</a>';
                    } else {
                        echo '<span style="color: #999;">' . esc_html__( 'Form deleted', 'mksddn-forms-handler' ) . '</span>';
                    }
                } else {
                    echo '<span style="color: #999;">' . esc_html__( 'Unknown form', 'mksddn-forms-handler' ) . '</span>';
                }
                break;
            case 'submission_date':
                $post = get_post($post_id);
                if ($post) {
                    echo get_the_date('Y-m-d H:i:s', $post_id);
                }
                break;
            case 'submission_data':
                $raw = get_post_meta($post_id, '_submission_data', true);
                $form_data = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
                if ($form_data && is_array($form_data)) {
                    $preview = [];
                    $count = 0;
                    foreach ($form_data as $key => $value) {
                        if ($count >= 3) break; // Show only first 3 fields
                        $preview[] = '<strong>' . esc_html($key) . ':</strong> ' . esc_html(substr((string)$value, 0, 50));
                        $count++;
                    }
                    echo wp_kses_post( implode('<br>', $preview) );
                    if (count($form_data) > 3) {
                        echo '<br><small style="color: #666;">+' . (count($form_data) - 3) . ' more fields</small>';
                    }
                } else {
                    echo '<span style="color: #999;">' . esc_html__( 'No data', 'mksddn-forms-handler' ) . '</span>';
                }
                break;
            case 'delivery_status':
                $delivery_results = get_post_meta($post_id, '_delivery_results', true);
                if ($delivery_results && is_array($delivery_results)) {
                    $statuses = [];
                    foreach ($delivery_results as $method => $result) {
                        if (isset($result['enabled']) && $result['enabled']) {
                            $status = $result['success'] ? '✓' : '✗';
                            $color = $result['success'] ? 'green' : 'red';
                            $statuses[] = '<span style="color: ' . $color . ';">' . ucfirst($method) . ': ' . $status . '</span>';
                        }
                    }
                    echo wp_kses_post( implode('<br>', $statuses) );
                } else {
                    echo '<span style="color: #999;">' . esc_html__( 'Unknown', 'mksddn-forms-handler' ) . '</span>';
                }
                break;
        }
    }
} 