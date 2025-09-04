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
        $existing_form = get_page_by_path('contact-form', OBJECT, 'mksddn_fh_forms');

        if (!$existing_form) {
            $form_data = [
                'post_title'   => 'Contact Form',
                'post_name'    => 'contact-form',
                'post_status'  => 'publish',
                'post_type'    => 'mksddn_fh_forms',
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
            'post_type'      => 'mksddn_fh_forms',
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
        return get_page_by_path($slug, OBJECT, 'mksddn_fh_forms');
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

    /**
     * Sanitize fields configuration for storage preserving arbitrary attributes.
     *
     * - Ensures each field has a non-empty sanitized `name`
     * - `type`/`name` are sanitized via sanitize_key
     * - String attributes are sanitized via sanitize_text_field
     * - Booleans and numbers are preserved
     * - `required`/`multiple` normalized to '1'/'0' strings
     * - `options` arrays are sanitized recursively (strings or {value,label} objects)
     *
     * @param array $decoded Raw decoded JSON array
     * @return array Sanitized array for storage
     */
    public static function sanitize_fields_config_for_storage(array $decoded): array {
        $result = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) { continue; }
            $sanitized = self::sanitize_field_item_for_storage($item);
            if (!empty($sanitized) && !empty($sanitized['name'])) {
                $result[] = $sanitized;
            }
        }
        return $result;
    }

    /**
     * Sanitize single field item for storage.
     *
     * @param array $item Raw field item
     * @return array Sanitized field item
     */
    public static function sanitize_field_item_for_storage(array $item): array {
        $result = [];

        foreach ($item as $key => $value) {
            if ($key === 'name') {
                $result['name'] = sanitize_key((string) $value);
                continue;
            }
            if ($key === 'type') {
                $result['type'] = sanitize_key((string) $value) ?: 'text';
                continue;
            }
            if ($key === 'required' || $key === 'multiple') {
                // Normalize to '1'/'0' for consistency with current storage
                $truthy = ($value === true || $value === '1' || $value === 1 || $value === 'true');
                $result[$key] = $truthy ? '1' : '0';
                continue;
            }
            if ($key === 'label' || $key === 'description' || $key === 'placeholder' || $key === 'help') {
                $result[$key] = is_string($value) ? sanitize_text_field($value) : self::sanitize_any_for_storage($value);
                continue;
            }
            if ($key === 'options' && is_array($value)) {
                $result['options'] = self::sanitize_options_for_storage($value);
                continue;
            }
            // Preserve arbitrary keys with recursive sanitization
            $result[$key] = self::sanitize_any_for_storage($value);
        }

        // Ensure name exists
        if (!isset($result['name']) || $result['name'] === '') {
            return [];
        }

        // Default type
        if (!isset($result['type']) || $result['type'] === '') {
            $result['type'] = 'text';
        }

        return $result;
    }

    /**
     * Recursively sanitize arbitrary value for storage.
     * Strings: sanitize_text_field, numbers/bools preserved, arrays sanitized recursively.
     *
     * @param mixed $value Raw value
     * @return mixed Sanitized value
     */
    public static function sanitize_any_for_storage($value) {
        if (is_string($value)) {
            return sanitize_text_field($value);
        }
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $k => $v) {
                $sanitized[$k] = self::sanitize_any_for_storage($v);
            }
            return $sanitized;
        }
        return sanitize_text_field((string) $value);
    }

    /**
     * Sanitize options for storage: accepts string values or objects with value/label.
     *
     * @param array $options Raw options array
     * @return array Sanitized options array
     */
    public static function sanitize_options_for_storage(array $options): array {
        $result = [];
        foreach ($options as $opt) {
            if (is_array($opt)) {
                $val = isset($opt['value']) ? sanitize_text_field((string) $opt['value']) : '';
                $lab = isset($opt['label']) ? sanitize_text_field((string) $opt['label']) : $val;
                if ($val !== '') {
                    $result[] = ['value' => $val, 'label' => $lab];
                }
            } else {
                $val = sanitize_text_field((string) $opt);
                if ($val !== '') {
                    $result[] = $val;
                }
            }
        }
        return $result;
    }
} 