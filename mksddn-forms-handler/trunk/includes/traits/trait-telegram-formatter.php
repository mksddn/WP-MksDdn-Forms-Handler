<?php
/**
 * @file: trait-telegram-formatter.php
 * @description: Shared formatting methods for Telegram messages
 * @dependencies: WordPress core
 * @created: 2026-02-20
 */

namespace MksDdn\FormsHandler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait providing shared formatting methods for Telegram messages
 */
trait TelegramFormatterTrait {
    
    /**
     * Escape HTML special characters for Telegram
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    protected static function escape_html_for_telegram($text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Get localized label for system-added field keys (e.g. Page URL).
     *
     * @param string $key Field key.
     * @return string Label for display.
     */
    protected static function get_system_field_label(string $key): string {
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
    protected static function build_field_labels_map($fields_config): array {
        $labels_map = [];
        
        if (!$fields_config) {
            return $labels_map;
        }
        
        $fields = json_decode((string)$fields_config, true);
        
        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('TelegramFormatterTrait: Invalid JSON in fields_config - ' . json_last_error_msg());
            return $labels_map;
        }
        
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
}
