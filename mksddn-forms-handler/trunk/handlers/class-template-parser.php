<?php
/**
 * @file: class-template-parser.php
 * @description: Parses template placeholders and replaces them with actual values
 * @dependencies: WordPress core
 * @created: 2026-02-20
 */

namespace MksDdn\FormsHandler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parses template placeholders for Telegram notifications
 */
class TemplateParser {
    
    /**
     * Parse template and replace placeholders with actual values
     *
     * @param string $template Template string with placeholders
     * @param array $form_data Form submission data
     * @param string $form_title Form title
     * @param string|null $fields_config Fields configuration JSON
     * @return string Parsed template
     */
    public static function parse($template, $form_data, $form_title, $fields_config = null): string {
        // Build field name to label mapping
        $field_labels_map = self::build_field_labels_map($fields_config);
        
        // Replace system placeholders
        $template = str_replace('{form_title}', self::escape_html_for_telegram($form_title), $template);
        $template = str_replace('{date}', current_time('d.m.Y'), $template);
        $template = str_replace('{time}', current_time('H:i:s'), $template);
        $template = str_replace('{datetime}', current_time('d.m.Y H:i:s'), $template);
        
        // Replace Page URL if exists
        if (isset($form_data['Page URL'])) {
            $template = str_replace('{page_url}', self::escape_html_for_telegram($form_data['Page URL']), $template);
        } else {
            $template = str_replace('{page_url}', '', $template);
        }
        
        // Replace field placeholders: {field:field_name}
        foreach ($form_data as $field_name => $field_value) {
            if ($field_name === 'Page URL') {
                continue; // Already handled above
            }
            
            $field_label = $field_labels_map[$field_name] ?? self::get_system_field_label($field_name);
            $escaped_label = self::escape_html_for_telegram($field_label);
            
            // Replace {field:field_name} with value
            $placeholder = '{field:' . $field_name . '}';
            $value = self::format_field_value($field_value, $fields_config, $field_name);
            $template = str_replace($placeholder, $value, $template);
            
            // Replace {field_label:field_name} with label
            $label_placeholder = '{field_label:' . $field_name . '}';
            $template = str_replace($label_placeholder, $escaped_label, $template);
        }
        
        // Replace any remaining placeholders for fields that don't exist in form_data with empty string
        // This handles cases where template has placeholders for fields that weren't submitted
        if ($fields_config) {
            $fields = json_decode((string)$fields_config, true);
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    if (isset($field['name']) && !isset($form_data[$field['name']])) {
                        $field_name = $field['name'];
                        $placeholder = '{field:' . $field_name . '}';
                        $template = str_replace($placeholder, '', $template);
                        
                        $label_placeholder = '{field_label:' . $field_name . '}';
                        $field_label = $field['notification_label'] ?? $field['label'] ?? $field_name;
                        $template = str_replace($label_placeholder, self::escape_html_for_telegram($field_label), $template);
                    }
                }
            }
        }
        
        return $template;
    }
    
    /**
     * Format field value for Telegram message
     *
     * @param mixed $value Field value
     * @param string|null $fields_config Fields configuration JSON
     * @param string|null $field_name Field name for nested fields lookup
     * @return string Formatted value
     */
    private static function format_field_value($value, $fields_config = null, $field_name = null): string {
        if (is_array($value) && TelegramHandler::is_array_of_objects($value)) {
            // Render array of objects (e.g., products)
            return TelegramHandler::format_array_of_objects($value, $fields_config, $field_name);
        } elseif (is_array($value)) {
            // Simple array: render as comma-separated list
            $value = implode(', ', array_map('strval', $value));
            return self::escape_html_for_telegram($value);
        } else {
            return self::escape_html_for_telegram((string) $value);
        }
    }
    
    /**
     * Escape HTML special characters for Telegram
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    private static function escape_html_for_telegram($text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Get localized label for system-added field keys (e.g. Page URL).
     *
     * @param string $key Field key.
     * @return string Label for display.
     */
    private static function get_system_field_label(string $key): string {
        if ($key === 'Page URL') {
            return __('Page URL', 'mksddn-forms-handler');
        }
        return $key;
    }
    
    /**
     * Build field name to label mapping from fields configuration
     * Priority: notification_label → label → name
     *
     * @param string|null $fields_config JSON fields configuration
     * @return array Associative array mapping field names to labels
     */
    private static function build_field_labels_map($fields_config): array {
        $labels_map = [];
        
        if (!$fields_config) {
            return $labels_map;
        }
        
        $fields = json_decode((string)$fields_config, true);
        if (!is_array($fields)) {
            return $labels_map;
        }
        
        foreach ($fields as $field) {
            if (isset($field['name'])) {
                $field_name = $field['name'];
                // Priority: notification_label → label → name
                $field_label = $field['notification_label'] ?? $field['label'] ?? $field_name;
                $labels_map[$field_name] = $field_label;
            }
        }
        
        return $labels_map;
    }
    
    /**
     * Generate default template with placeholders
     *
     * @param array|null $fields_config Fields configuration JSON
     * @return string Default template with placeholders
     */
    public static function get_default_template($fields_config = null): string {
        $template = '📝 <b>' . __('New Form Submission', 'mksddn-forms-handler') . "</b>\n\n";
        $template .= '📋 <b>' . __('Form:', 'mksddn-forms-handler') . '</b> {form_title}' . "\n";
        $template .= '🕐 <b>' . __('Time:', 'mksddn-forms-handler') . '</b> {datetime}' . "\n\n";
        $template .= '<b>' . __('Form Data:', 'mksddn-forms-handler') . "</b>\n";
        
        // Add placeholders for all configured fields
        if ($fields_config) {
            $fields = json_decode((string)$fields_config, true);
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    if (isset($field['name'])) {
                        $field_label = $field['notification_label'] ?? $field['label'] ?? $field['name'];
                        $field_name = $field['name'];
                        $template .= "• <b>{field_label:{$field_name}}:</b> {field:{$field_name}}\n";
                    }
                }
            }
        } else {
            // Generic placeholder if no fields config
            $template .= "• <b>{field_label:field_name}:</b> {field:field_name}\n";
        }
        
        // Add Page URL placeholder if it might be used
        $template .= "\n🔗 <b>" . __('Page URL:', 'mksddn-forms-handler') . '</b> {page_url}';
        
        return $template;
    }
}
