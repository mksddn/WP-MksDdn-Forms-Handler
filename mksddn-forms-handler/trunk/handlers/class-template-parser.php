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
    use TelegramFormatterTrait;
    
    /**
     * Maximum template size in characters
     * Can be overridden via filter: mksddn_fh_max_template_size
     */
    private const MAX_TEMPLATE_SIZE = 10000;
    
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
        // Validate template size (allow override via filter)
        $max_size = apply_filters('mksddn_fh_max_template_size', self::MAX_TEMPLATE_SIZE);
        if (strlen($template) > $max_size) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    'TemplateParser: Template exceeds maximum size (%d chars, max %d)',
                    strlen($template),
                    $max_size
                ));
            }
            // Return default template instead of error message
            return self::get_default_template($fields_config);
        }
        
        // Build field name to label mapping
        $field_labels_map = self::build_field_labels_map($fields_config);
        
        // Replace system placeholders using strtr for better performance
        $replacements = [
            '{form_title}' => self::escape_html_for_telegram($form_title),
            '{date}' => current_time('d.m.Y'),
            '{time}' => current_time('H:i:s'),
            '{datetime}' => current_time('d.m.Y H:i:s'),
        ];
        $template = strtr($template, $replacements);
        
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
            
            // Check for JSON decode errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('TemplateParser: Invalid JSON in fields_config - ' . json_last_error_msg());
                }
            } elseif (is_array($fields)) {
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
     * Parse template for email and replace placeholders with HTML-safe values
     *
     * @param string $template Template string with placeholders
     * @param array $form_data Form submission data
     * @param string $form_title Form title
     * @param string|null $fields_config Fields configuration JSON
     * @return string Parsed template
     */
    public static function parse_for_email($template, $form_data, $form_title, $fields_config = null): string {
        $max_size = (int) apply_filters('mksddn_fh_max_html_template_size', 102400);
        if (strlen($template) > $max_size) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    'TemplateParser: Email template exceeds maximum size (%d chars, max %d)',
                    strlen($template),
                    $max_size
                ));
            }
            return self::get_default_user_reply_template();
        }

        $field_labels_map = self::build_field_labels_map($fields_config);

        $replacements = [
            '{form_title}' => esc_html($form_title),
            '{date}'       => esc_html(current_time('d.m.Y')),
            '{time}'       => esc_html(current_time('H:i:s')),
            '{datetime}'   => esc_html(current_time('d.m.Y H:i:s')),
        ];
        $template = strtr($template, $replacements);

        if (isset($form_data['Page URL'])) {
            $template = str_replace('{page_url}', esc_html($form_data['Page URL']), $template);
        } else {
            $template = str_replace('{page_url}', '', $template);
        }

        foreach ($form_data as $field_name => $field_value) {
            if ($field_name === 'Page URL') {
                continue;
            }

            $field_label = $field_labels_map[$field_name] ?? self::get_system_field_label($field_name);
            $placeholder = '{field:' . $field_name . '}';
            $value = self::format_field_value_for_email($field_value, $fields_config, $field_name);
            $template = str_replace($placeholder, $value, $template);

            $label_placeholder = '{field_label:' . $field_name . '}';
            $template = str_replace($label_placeholder, esc_html($field_label), $template);
        }

        if ($fields_config) {
            $fields = json_decode((string) $fields_config, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($fields)) {
                foreach ($fields as $field) {
                    if (isset($field['name']) && !isset($form_data[$field['name']])) {
                        $field_name = $field['name'];
                        $template = str_replace('{field:' . $field_name . '}', '', $template);

                        $field_label = $field['notification_label'] ?? $field['label'] ?? $field_name;
                        $template = str_replace('{field_label:' . $field_name . '}', esc_html($field_label), $template);
                    }
                }
            }
        }

        return $template;
    }

    /**
     * Format field value for email template
     *
     * @param mixed $value Field value
     * @param string|null $fields_config Fields configuration JSON
     * @param string|null $field_name Field name for nested fields lookup
     * @return string Formatted value
     */
    private static function format_field_value_for_email($value, $fields_config = null, $field_name = null): string {
        if (is_array($value) && TelegramHandler::is_array_of_objects($value)) {
            return esc_html(TelegramHandler::format_array_of_objects($value, $fields_config, $field_name));
        }

        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }

        return esc_html((string) $value);
    }

    /**
     * Generate default user reply email template
     *
     * @return string Default template with placeholders
     */
    public static function get_default_user_reply_template(): string {
        $greeting = __('Hello {field:name},', 'mksddn-forms-handler');
        $message = __('Thank you for your message. We have received your submission and will get back to you soon.', 'mksddn-forms-handler');

        return '<p>' . $greeting . '</p><p>' . $message . '</p>';
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
        // Check if value is array of objects (e.g., products)
        if (is_array($value) && TelegramHandler::is_array_of_objects($value)) {
            return TelegramHandler::format_array_of_objects($value, $fields_config, $field_name);
        }
        
        if (is_array($value)) {
            // Simple array: render as comma-separated list
            $value = implode(', ', array_map('strval', $value));
            return self::escape_html_for_telegram($value);
        } else {
            return self::escape_html_for_telegram((string) $value);
        }
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
            
            // Check for JSON decode errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('TemplateParser::get_default_template: Invalid JSON - ' . json_last_error_msg());
                }
            } elseif (is_array($fields)) {
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
