<?php
/**
 * @file: template-functions.php
 * @description: Global template functions for custom form integration
 * @dependencies: class-utilities.php
 * @created: 2025-10-11
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get form action URL for template integration
 *
 * Usage in templates:
 * <form method="post" action="<?php echo mksddn_fh_get_form_action(); ?>">
 *
 * @return string Form action URL
 */
function mksddn_fh_get_form_action(): string {
    return \MksDdn\FormsHandler\Utilities::get_form_action();
}

/**
 * Render required hidden form fields for template integration
 *
 * Usage in templates:
 * <form method="post" action="<?php echo mksddn_fh_get_form_action(); ?>">
 *     <?php mksddn_fh_form_fields('contact-form'); ?>
 *     <!-- Your custom fields here -->
 * </form>
 *
 * @param string $form_slug Form slug or ID
 * @return void
 */
function mksddn_fh_form_fields(string $form_slug): void {
    \MksDdn\FormsHandler\Utilities::render_form_fields($form_slug);
}

/**
 * Get form configuration (fields, settings, etc.)
 *
 * Usage in templates:
 * $form_config = mksddn_fh_get_form_config('contact-form');
 * if ($form_config) {
 *     foreach ($form_config['fields'] as $field) {
 *         // Render field based on configuration
 *     }
 * }
 *
 * @param string $form_slug Form slug or ID
 * @return array|null Form configuration or null if not found
 */
function mksddn_fh_get_form_config(string $form_slug): ?array {
    return \MksDdn\FormsHandler\Utilities::get_form_config($form_slug);
}

/**
 * Get REST API endpoint URL for AJAX form submissions
 *
 * Usage in JavaScript:
 * const endpoint = '<?php echo mksddn_fh_get_rest_endpoint('contact-form'); ?>';
 * fetch(endpoint, { method: 'POST', body: formData });
 *
 * @param string $form_slug Form slug
 * @return string REST API endpoint URL
 */
function mksddn_fh_get_rest_endpoint(string $form_slug): string {
    return \MksDdn\FormsHandler\Utilities::get_rest_endpoint($form_slug);
}

/**
 * Check if form has file upload fields
 *
 * Usage in templates:
 * <form <?php echo mksddn_fh_form_has_files('contact-form') ? 'enctype="multipart/form-data"' : ''; ?>>
 *
 * @param string $form_slug Form slug or ID
 * @return bool True if form has file fields
 */
function mksddn_fh_form_has_files(string $form_slug): bool {
    return \MksDdn\FormsHandler\Utilities::form_has_file_fields($form_slug);
}

/**
 * Enqueue form script for AJAX submission
 * Call this function when you want to enable AJAX form submission
 *
 * Usage in templates:
 * <?php mksddn_fh_enqueue_form_script(); ?>
 *
 * @return void
 */
function mksddn_fh_enqueue_form_script(): void {
    wp_enqueue_script('mksddn-fh-form');
    wp_localize_script(
        'mksddn-fh-form',
        'mksddn_fh_form',
        [
            'sending_text' => __('Sending...', 'mksddn-forms-handler'),
            'send_text'    => __('Send', 'mksddn-forms-handler'),
        ]
    );
}

