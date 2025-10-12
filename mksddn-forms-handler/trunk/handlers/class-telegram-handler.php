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
    public static function send_message($bot_token, $chat_ids, $form_data, $form_title): \WP_Error|bool {
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

            $message = self::build_telegram_message($form_data, $form_title);
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
    private static function build_telegram_message($form_data, $form_title): string {
        $message = "📝 <b>New Form Submission</b>\n\n";
        $message .= "📋 <b>Form:</b> " . self::escape_html_for_telegram($form_title) . "\n";
        $message .= "🕐 <b>Time:</b> " . current_time('d.m.Y H:i:s') . "\n\n";
        $message .= "<b>Form Data:</b>\n";

        foreach ($form_data as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }
            $escaped_key = self::escape_html_for_telegram(ucfirst($key));
            $escaped_value = self::escape_html_for_telegram($value);
            $message .= "• <b>" . $escaped_key . ":</b> " . $escaped_value . "\n";
        }

        return $message;
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
            return new \WP_Error('telegram_request_error', 'Failed to send Telegram request: ' . $response->get_error_message());
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