<?php
/**
 * @file: class-utilities.php
 * @description: Utility functions for forms handler
 * @dependencies: WordPress core
 * @created: 2025-07-30
 */

namespace MksDdn\FormsHandler;

/**
 * Utility functions for forms handler
 */
class Utilities {
    
    /**
     * Create default contact form on theme activation
     */
    public static function create_default_contact_form(): void {
        // Check if form with this slug already exists
        $existing_form = get_page_by_path('contact-form', OBJECT, 'forms');

        if (!$existing_form) {
            $form_data = [
                'post_title'   => 'Contact Form',
                'post_name'    => 'contact-form',
                'post_status'  => 'publish',
                'post_type'    => 'forms',
                'post_content' => 'Default contact form',
            ];

            $form_id = wp_insert_post($form_data);

            if ($form_id && !is_wp_error($form_id)) {
                // Set meta fields
                update_post_meta($form_id, '_recipients', get_option('admin_email'));
                update_post_meta($form_id, '_subject', 'New message from website');

                // Default fields configuration
                $default_fields = json_encode([
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
                        'name'     => 'phone',
                        'label'    => 'Phone',
                        'type'     => 'tel',
                        'required' => false,
                    ],
                    [
                        'name'     => 'message',
                        'label'    => 'Message',
                        'type'     => 'textarea',
                        'required' => true,
                    ],
                ]);
                update_post_meta($form_id, '_fields_config', $default_fields);
            }
        }
    }
    
    /**
     * Get all forms
     */
    public static function get_all_forms() {
        return get_posts([
            'post_type'      => 'forms',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
    }
    
    /**
     * Get form by slug
     */
    public static function get_form_by_slug($slug) {
        return get_page_by_path($slug, OBJECT, 'forms');
    }
    
    /**
     * Get form fields configuration
     */
    public static function get_form_fields_config($form_id) {
        $fields_config = get_post_meta($form_id, '_fields_config', true);
        return json_decode($fields_config, true) ?: [];
    }
    
    /**
     * Validate form data
     */
    public static function validate_form_data($form_data, $form_id): \WP_Error|true {
        $fields_config = self::get_form_fields_config($form_id);

        foreach ($fields_config as $field) {
            $field_name = $field['name'];
            $is_required = $field['required'] ?? false;
            $field_type = $field['type'] ?? 'text';

            // Check required fields
            if ($is_required && (empty($form_data[$field_name]) || $form_data[$field_name] === '')) {
                return new \WP_Error('validation_error', sprintf("Field '%s' is required", $field['label']));
            }

            // Check email
            if ($field_type === 'email' && !empty($form_data[$field_name]) && !is_email($form_data[$field_name])) {
                return new \WP_Error('validation_error', sprintf("Field '%s' must contain a valid email", $field['label']));
            }
        }

        return true;
    }
} 