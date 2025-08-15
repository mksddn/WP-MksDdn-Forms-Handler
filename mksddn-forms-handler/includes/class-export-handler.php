<?php
/**
 * @file: class-export-handler.php
 * @description: Handles CSV export functionality for form submissions
 * @dependencies: WordPress core
 * @created: 2025-07-30
 */

namespace MksDdn\FormsHandler;

/**
 * Handles CSV export functionality
 */
class ExportHandler {
    
    /**
     * Cache for forms list
     * @var array
     */
    private $forms_cache = [];
    
    /**
     * Batch size for large exports
     * @var int
     */
    private const BATCH_SIZE = 1000;
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_submissions_export_menu']);
        add_action('admin_post_export_submissions_csv', [$this, 'handle_export_submissions_csv']);
        add_action('admin_post_nopriv_export_submissions_csv', [$this, 'handle_export_submissions_csv']);
        
        // Clear cache when forms are updated
        add_action('save_post_forms', [$this, 'clear_forms_cache'], 10, 2);
        add_action('deleted_post', [$this, 'clear_forms_cache'], 10, 2);
    }
    
    /**
     * Add export menu
     */
    public function add_submissions_export_menu(): void {
        add_submenu_page(
            'edit.php?post_type=form_submissions',
            'Export Submissions',
            'Export Submissions',
            'manage_options',
            'export-by-form',
            [$this, 'render_export_by_form_page']
        );
    }
    
    /**
     * Handle CSV export with optimized performance
     */
    public function handle_export_submissions_csv(): void {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Access denied', 'mksddn-forms-handler' ) );
        }

        // Check nonce (for POST and GET requests)
        $nonce = $_POST['export_nonce'] ?? $_GET['export_nonce'] ?? '';
        if (!$nonce || !wp_verify_nonce($nonce, 'export_submissions_csv')) {
            wp_die( esc_html__( 'Security check failed', 'mksddn-forms-handler' ) );
        }

        // Get filter parameters (from POST or GET)
        $form_filter = isset($_POST['form_filter']) ? intval($_POST['form_filter']) : (isset($_GET['form_filter']) ? intval($_GET['form_filter']) : 0);
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : (isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '');
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : (isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '');

        // Check if form is selected
        if ($form_filter === 0) {
            wp_die( esc_html__( 'Please select a form to export.', 'mksddn-forms-handler' ) );
        }

        // Get form for filename
        $form = get_post($form_filter);
        if (!$form) {
            wp_die( esc_html__( 'Form not found.', 'mksddn-forms-handler' ) );
        }

        // Set headers for CSV download
        $filename = sanitize_title($form->post_title) . '_submissions_' . gmdate('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Get submissions with optimized query
        $submissions = $this->get_submissions_for_export($form_filter, $date_from, $date_to);

        if (empty($submissions)) {
            fclose($output);
            wp_die( esc_html__( 'No submissions found for the selected form and criteria.', 'mksddn-forms-handler' ) );
        }

        // Write CSV headers
        $headers = $this->get_csv_headers($submissions);
        fputcsv($output, $headers);

        // Write data in batches for better performance
        $this->write_csv_data($output, $submissions, $headers);

        fclose($output);
        exit;
    }
    
    /**
     * Get submissions for export with optimized query
     */
    private function get_submissions_for_export($form_filter, $date_from, $date_to): array {
        $args = [
            'post_type'      => 'form_submissions',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'     => '_form_id',
                    'value'   => $form_filter,
                    'compare' => '=',
                ],
            ],
        ];

        // Add date filter if specified
        if ($date_from || $date_to) {
            $date_query = [];
            if ($date_from) {
                $date_query['after'] = $date_from;
            }
            if ($date_to) {
                $date_query['before'] = $date_to . ' 23:59:59';
            }
            $args['date_query'] = $date_query;
        }

        return get_posts($args);
    }
    
    /**
     * Get CSV headers from submissions data
     */
    private function get_csv_headers($submissions): array {
        $headers = ['ID', 'Date', 'Form Title'];
        $field_names = [];

        // Collect all unique field names from submissions
        foreach ($submissions as $submission) {
            $form_data = get_post_meta($submission->ID, '_form_data', true);
            if ($form_data && is_array($form_data)) {
                foreach ($form_data as $field_name => $value) {
                    if (!in_array($field_name, $field_names)) {
                        $field_names[] = $field_name;
                    }
                }
            }
        }

        // Add field names to headers
        $headers = array_merge($headers, $field_names);
        
        return $headers;
    }
    
    /**
     * Write CSV data in batches for better performance
     */
    private function write_csv_data($output, $submissions, $headers): void {
        $batch = [];
        $count = 0;

        foreach ($submissions as $submission) {
            $row = [
                $submission->ID,
                get_the_date('Y-m-d H:i:s', $submission->ID),
                get_post_meta($submission->ID, '_form_title', true) ?: 'Unknown Form'
            ];

            // Get form data
            $form_data = get_post_meta($submission->ID, '_form_data', true);
            if ($form_data && is_array($form_data)) {
                // Add field values in the same order as headers
                for ($i = 3; $i < count($headers); $i++) {
                    $field_name = $headers[$i];
                    $row[] = isset($form_data[$field_name]) ? $form_data[$field_name] : '';
                }
            } else {
                // Fill empty values for missing data
                for ($i = 3; $i < count($headers); $i++) {
                    $row[] = '';
                }
            }

            fputcsv($output, $row);
            $count++;

            // Flush output every batch to prevent memory issues
            if ($count % self::BATCH_SIZE === 0) {
                flush();
            }
        }
    }
    
    /**
     * Clear forms cache when forms are updated
     */
    public function clear_forms_cache($post_id, $post): void {
        if ($post->post_type === 'forms') {
            wp_cache_delete('forms_list', 'mksddn_forms_handler');
        }
    }
    
    /**
     * Get cached list of all forms
     */
    private function get_all_forms(): array {
        // Try to get from cache first
        $cached_forms = wp_cache_get('forms_list', 'mksddn_forms_handler');
        if ($cached_forms !== false) {
            return $cached_forms;
        }

        // Get all forms
        $forms = get_posts([
            'post_type'      => 'forms',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        // Cache the result for 1 hour
        wp_cache_set('forms_list', $forms, 'mksddn_forms_handler', 3600);

        return $forms;
    }
    
    /**
     * Render export page
     */
    public function render_export_by_form_page(): void {
        $forms = $this->get_all_forms();

        // Get form statistics
        $form_stats = [];
        foreach ($forms as $form) {
            $submissions_count = get_posts([
                'post_type'      => 'form_submissions',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_query'     => [
                    [
                        'key'     => '_form_id',
                        'value'   => $form->ID,
                        'compare' => '=',
                    ],
                ],
            ]);

            $form_stats[$form->ID] = count($submissions_count);
        }

        ?>
        <div class="wrap">
            <h1>Export Submissions</h1>
            <p>Select a form to export all its submissions to CSV:</p>
            
            <div class="form-export-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                <?php foreach ($forms as $form) : ?>
                    <div class="form-export-card" style="border: 1px solid #ddd; padding: 20px; border-radius: 5px; background: #fff;">
                        <h3><?php echo esc_html($form->post_title); ?></h3>
                        <p><strong>Submissions:</strong> <?php echo $form_stats[$form->ID]; ?></p>
                        <p><strong>Slug:</strong> <code><?php echo esc_html($form->post_name); ?></code></p>
                        
                        <div style="margin-top: 15px;">
                            <a href="<?php echo admin_url('admin-post.php?action=export_submissions_csv&form_filter=' . $form->ID . '&export_nonce=' . wp_create_nonce('export_submissions_csv')); ?>" 
                                class="button button-primary" 
                                target="_blank"
                                style="margin-right: 10px;">
                                Export All
                            </a>
                            
                            <button type="button" 
                                    class="button button-secondary export-with-filters" 
                                    data-form-id="<?php echo $form->ID; ?>"
                                    data-form-name="<?php echo esc_attr($form->post_title); ?>">
                                Export by Date
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Modal for filters -->
            <div id="export-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100000;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 5px; min-width: 400px;">
                    <h2 id="modal-title">Export by Date</h2>
                    
                    <form id="export-filters-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>" target="_blank">
                        <input type="hidden" name="action" value="export_submissions_csv">
                        <input type="hidden" name="export_nonce" value="<?php echo wp_create_nonce('export_submissions_csv'); ?>">
                        <input type="hidden" name="form_filter" id="modal-form-filter">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="modal_date_from">Date From</label>
                                </th>
                                <td>
                                    <input type="date" name="date_from" id="modal_date_from" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="modal_date_to">Date To</label>
                                </th>
                                <td>
                                    <input type="date" name="date_to" id="modal_date_to" />
                                </td>
                            </tr>
                        </table>
                        
                        <div style="margin-top: 20px; text-align: right;">
                            <button type="button" class="button" onclick="closeExportModal()">Cancel</button>
                            <button type="submit" class="button button-primary">Export</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
            function openExportModal(formId, formName) {
                document.getElementById('modal-title').textContent = 'Export ' + formName + ' by Date';
                document.getElementById('modal-form-filter').value = formId;
                document.getElementById('export-modal').style.display = 'block';
            }
            
            function closeExportModal() {
                document.getElementById('export-modal').style.display = 'none';
                document.getElementById('export-filters-form').reset();
            }
            
            // Close modal when clicking outside
            document.getElementById('export-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeExportModal();
                }
            });
            
            // Handle "Export by Date" buttons
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.export-with-filters').forEach(function(button) {
                    button.addEventListener('click', function() {
                        var formId = this.getAttribute('data-form-id');
                        var formName = this.getAttribute('data-form-name');
                        openExportModal(formId, formName);
                    });
                });
            });
            </script>
        </div>
        <?php
    }
} 