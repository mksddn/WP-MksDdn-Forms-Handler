<?php
namespace FormsHandler;

/**
 * Handles CSV export functionality
 */
class ExportHandler {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_submissions_export_menu']);
        add_action('admin_post_export_submissions_csv', [$this, 'handle_export_submissions_csv']);
        add_action('admin_post_nopriv_export_submissions_csv', [$this, 'handle_export_submissions_csv']);
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
     * Handle CSV export
     */
    public function handle_export_submissions_csv(): void {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        // Check nonce (for POST and GET requests)
        $nonce = $_POST['export_nonce'] ?? $_GET['export_nonce'] ?? '';
        if (!$nonce || !wp_verify_nonce($nonce, 'export_submissions_csv')) {
            wp_die('Security check failed');
        }

        // Get filter parameters (from POST or GET)
        $form_filter = isset($_POST['form_filter']) ? intval($_POST['form_filter']) : (isset($_GET['form_filter']) ? intval($_GET['form_filter']) : 0);
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : (isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '');
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : (isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '');

        // Check if form is selected
        if ($form_filter === 0) {
            wp_die('Please select a form to export.');
        }

        // Build query
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

        // Add date filter
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

        $submissions = get_posts($args);

        if (empty($submissions)) {
            wp_die('No submissions found for the selected form and criteria.');
        }

        // Get form name for filename
        $form = get_post($form_filter);
        $form_name = $form ? sanitize_title($form->post_title) : 'form';

        // Clear output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers for CSV download
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $form_name . '-submissions-' . date('Y-m-d-H-i-s') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Create file pointer for output
        $output = fopen('php://output', 'w');

        // Add BOM for correct Cyrillic display in Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // CSV headers
        $headers = [
            'ID',
            'Form Title',
            'Submission Date',
            'IP Address',
            'User Agent',
        ];

        // Get all unique fields from submission data
        $all_fields = [];
        foreach ($submissions as $submission) {
            $data = get_post_meta($submission->ID, '_submission_data', true);
            if ($data) {
                $data_array = json_decode($data, true);
                if ($data_array) {
                    foreach (array_keys($data_array) as $field) {
                        if (!in_array($field, $all_fields)) {
                            $all_fields[] = $field;
                        }
                    }
                }
            }
        }

        // Add data fields to headers
        $headers = array_merge($headers, $all_fields);

        // Write headers
        fputcsv($output, $headers);

        // Write data
        foreach ($submissions as $submission) {
            $row = [
                $submission->ID,
                get_post_meta($submission->ID, '_form_title', true),
                get_post_meta($submission->ID, '_submission_date', true),
                get_post_meta($submission->ID, '_submission_ip', true),
                get_post_meta($submission->ID, '_submission_user_agent', true),
            ];

            // Add form data
            $data = get_post_meta($submission->ID, '_submission_data', true);
            $data_array = json_decode($data, true) ?: [];

            foreach ($all_fields as $field) {
                $row[] = $data_array[$field] ?? '';
            }

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
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
    
    /**
     * Get all forms
     */
    private function get_all_forms() {
        return get_posts([
            'post_type'      => 'forms',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
    }
} 