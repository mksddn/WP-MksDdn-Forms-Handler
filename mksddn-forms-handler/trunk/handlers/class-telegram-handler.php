<?php
/**
 * @file: class-telegram-handler.php
 * @description: Handles Telegram notifications for form submissions
 * @dependencies: WordPress core, Telegram Bot API
 * @created: 2025-07-30
 */

namespace MksDdn\FormsHandler;

if (!defined('ABSPATH')) {
    exit;
}
/**
 * Handles Telegram notifications
 */
class TelegramHandler {
    
    /**
     * Send message to Telegram
     */
    public static function send_message($bot_token, $chat_ids, $form_data, $form_title, $fields_config = null): \WP_Error|bool {
        if (!$bot_token || !$chat_ids) {
            return new \WP_Error('telegram_config_error', __( 'Telegram bot token or chat IDs not configured', 'mksddn-forms-handler' ));
        }

        $chat_ids_array = array_map('trim', explode(',', $chat_ids));
        $success_count = 0;
        $error_messages = [];

        foreach ($chat_ids_array as $chat_id) {
            $chat_id = trim($chat_id);
            if (empty($chat_id)) {
                continue;
            }

            $message = self::build_telegram_message($form_data, $form_title, $fields_config);
            $result = self::send_telegram_request($bot_token, $chat_id, $message);

            if (is_wp_error($result)) {
                $error_messages[] = 'Chat ' . $chat_id . ': ' . $result->get_error_message();
            } else {
                $success_count++;
            }
        }

        if ($success_count === 0) {
            return new \WP_Error('telegram_send_error', __( 'Failed to send to any Telegram chat:', 'mksddn-forms-handler' ) . ' ' . implode(', ', $error_messages));
        }

        return true;
    }
    
    /**
     * Escape HTML special characters for Telegram
     */
    private static function escape_html_for_telegram($text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Build Telegram message
     */
    private static function build_telegram_message($form_data, $form_title, $fields_config = null): string {
        // Build field name to label mapping
        $field_labels_map = self::build_field_labels_map($fields_config);

        $message = '📝 <b>' . self::escape_html_for_telegram( __( 'New Form Submission', 'mksddn-forms-handler' ) ) . "</b>\n\n";
        $message .= '📋 <b>' . self::escape_html_for_telegram( __( 'Form:', 'mksddn-forms-handler' ) ) . '</b> ' . self::escape_html_for_telegram( $form_title ) . "\n";
        $message .= '🕐 <b>' . self::escape_html_for_telegram( __( 'Time:', 'mksddn-forms-handler' ) ) . '</b> ' . current_time( 'd.m.Y H:i:s' ) . "\n\n";
        $message .= '<b>' . self::escape_html_for_telegram( __( 'Form Data:', 'mksddn-forms-handler' ) ) . "</b>\n";

        foreach ($form_data as $key => $value) {
            $field_label = $field_labels_map[ $key ] ?? self::get_system_field_label( $key );
            $escaped_key = self::escape_html_for_telegram($field_label);
            
            if (is_array($value) && self::is_array_of_objects($value)) {
                // Render array of objects (e.g., products)
                $message .= "• <b>" . $escaped_key . ":</b>\n";
                $message .= self::format_array_of_objects($value, $fields_config, $key);
            } elseif (is_array($value)) {
                // Simple array: render as comma-separated list
                $value = implode(', ', array_map('strval', $value));
                $escaped_value = self::escape_html_for_telegram($value);
                $message .= "• <b>" . $escaped_key . ":</b> " . $escaped_value . "\n";
            } else {
                $escaped_value = self::escape_html_for_telegram((string) $value);
                $message .= "• <b>" . $escaped_key . ":</b> " . $escaped_value . "\n";
            }
        }

        return $message;
    }

    /**
     * Get localized label for system-added field keys (e.g. Page URL).
     *
     * @param string $key Field key.
     * @return string Label for display.
     */
    private static function get_system_field_label( string $key ): string {
        if ( $key === 'Page URL' ) {
            return __( 'Page URL', 'mksddn-forms-handler' );
        }
        return $key;
    }

    /**
     * Check if array contains objects (associative arrays with multiple keys)
     *
     * @param array $value Array to check
     * @return bool True if array contains objects
     */
    private static function is_array_of_objects(array $value): bool {
        if (empty($value)) {
            return false;
        }
        
        $first = reset($value);
        if (!is_array($first)) {
            return false;
        }
        
        $keys = array_keys($first);
        return !empty($keys) && array_keys($keys) !== $keys;
    }
    
    /**
     * Format array of objects for Telegram message
     *
     * @param array $items Array of objects/associative arrays
     * @param string|null $fields_config Fields configuration JSON
     * @param string|null $parent_field_name Parent field name for nested fields lookup
     * @return string Formatted string
     */
    private static function format_array_of_objects(array $items, $fields_config = null, $parent_field_name = null): string {
        // Build nested field labels map if parent field config exists
        $nested_labels_map = [];
        if ($fields_config && $parent_field_name) {
            $fields = json_decode((string)$fields_config, true);
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    if (($field['name'] ?? '') === $parent_field_name && isset($field['fields']) && is_array($field['fields'])) {
                        foreach ($field['fields'] as $nested_field) {
                            $nested_name = $nested_field['name'] ?? '';
                            // Priority: notification_label → label → name
                            $nested_label = $nested_field['notification_label'] ?? $nested_field['label'] ?? $nested_name;
                            if ($nested_name) {
                                $nested_labels_map[$nested_name] = $nested_label;
                            }
                        }
                        break;
                    }
                }
            }
        }
        
        $output = '';
        $item_num = 1;
        
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            
            $output .= "  <i>Item #{$item_num}:</i>\n";
            foreach ($item as $k => $v) {
                $nested_label = $nested_labels_map[$k] ?? $k;
                $escaped_k = self::escape_html_for_telegram($nested_label);
                $escaped_v = is_array($v) ? implode(', ', array_map('strval', $v)) : (string) $v;
                $escaped_v = self::escape_html_for_telegram($escaped_v);
                $output .= "    • <b>{$escaped_k}:</b> {$escaped_v}\n";
            }
            $item_num++;
        }
        
        return $output;
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
     * Send request to Telegram API
     */
    private static function send_telegram_request($bot_token, $chat_id, $message): \WP_Error|bool {
        $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        
        $response = wp_remote_post($url, [
            'body' => [
                'chat_id'    => $chat_id,
                'text'       => $message,
                'parse_mode' => 'HTML',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('telegram_request_error', __( 'Failed to send Telegram request:', 'mksddn-forms-handler' ) . ' ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['ok']) || !$data['ok']) {
            $error_message = isset($data['description']) ? $data['description'] : 'Unknown error';
            return new \WP_Error('telegram_api_error', 'Telegram API error: ' . $error_message);
        }

        return true;
    }
} 