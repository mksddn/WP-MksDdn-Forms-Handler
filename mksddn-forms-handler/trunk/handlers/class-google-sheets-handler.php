<?php
/**
 * @file: class-google-sheets-handler.php
 * @description: Google Sheets integration for form submissions
 * @dependencies: WordPress core, Google Sheets API
 * @created: 2025-07-30
 */

namespace MksDdn\FormsHandler;

if (!defined('ABSPATH')) {
    exit;
}
/**
 * Google Sheets Handler for Forms Handler Plugin
 *
 * Handles sending form submissions to Google Sheets
 */
class GoogleSheetsHandler {
    
    /**
     * Send data to Google Sheets
     */
    public static function send_data(?string $spreadsheet_id, ?string $sheet_name, $form_data, $form_title) {
        if (!$spreadsheet_id) {
            return new \WP_Error('sheets_config_error', __( 'Google Sheets spreadsheet ID not configured', 'mksddn-forms-handler' ));
        }

        $access_token = self::get_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }

        // Prepare data for Google Sheets
        $row_data = [
            current_time('Y-m-d H:i:s'), // Timestamp
            $form_title, // Form title
        ];

        // Add form data
        foreach ($form_data as $key => $value) {
            $row_data[] = $value;
        }

        // Get sheet name
        $target_sheet = $sheet_name ?: 'Sheet1';

        // Send to Google Sheets
        $response = wp_remote_post(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$target_sheet}!A:Z:append?valueInputOption=USER_ENTERED",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => json_encode([
                    'values' => [$row_data],
                ]),
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)) {
            return new \WP_Error('sheets_request_error', __( 'Failed to send data to Google Sheets:', 'mksddn-forms-handler' ) . ' ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || isset($data['error'])) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            return new \WP_Error('sheets_api_error', __( 'Google Sheets API error:', 'mksddn-forms-handler' ) . ' ' . $error_message);
        }

        return true;
    }
    
    /**
     * Get access token using refresh token
     */
    private static function get_access_token() {
        $refresh_token = get_option('mksddn_fh_google_sheets_refresh_token');
        $client_id = get_option('mksddn_fh_google_sheets_client_id');
        $client_secret = get_option('mksddn_fh_google_sheets_client_secret');

        if (!$refresh_token || !$client_id || !$client_secret) {
            return new \WP_Error('sheets_auth_error', __( 'Google Sheets authentication not configured', 'mksddn-forms-handler' ));
        }

        $response = wp_remote_post(
            'https://oauth2.googleapis.com/token',
            [
                'body'    => [
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'refresh_token' => $refresh_token,
                    'grant_type'    => 'refresh_token',
                ],
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)) {
            return new \WP_Error('token_request_error', __( 'Failed to get access token:', 'mksddn-forms-handler' ) . ' ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['access_token'])) {
            $error_message = isset($data['error_description']) ? $data['error_description'] : 'Unknown error';
            return new \WP_Error('token_error', __( 'Failed to get access token:', 'mksddn-forms-handler' ) . ' ' . $error_message);
        }

        return $data['access_token'];
    }
    
    /**
     * Validate Google Sheets configuration
     */
    public static function validate_config(?string $spreadsheet_id, $sheet_name = ''): \WP_Error|bool {
        if (!$spreadsheet_id) {
            return new \WP_Error('sheets_config_error', __( 'Spreadsheet ID is required', 'mksddn-forms-handler' ));
        }

        $refresh_token = get_option('mksddn_fh_google_sheets_refresh_token');
        $client_id = get_option('mksddn_fh_google_sheets_client_id');
        $client_secret = get_option('mksddn_fh_google_sheets_client_secret');

        if (!$refresh_token || !$client_id || !$client_secret) {
            return new \WP_Error('sheets_auth_error', __( 'Google Sheets authentication not configured', 'mksddn-forms-handler' ));
        }

        return true;
    }
    
    /**
     * Test Google Sheets connection
     */
    public static function test_connection(string $spreadsheet_id): array {
        $result = [
            'success' => false,
            'message' => '',
            'details' => [],
        ];

        // Check authentication
        $auth_result = self::validate_config($spreadsheet_id);
        if (is_wp_error($auth_result)) {
            $result['message'] = $auth_result->get_error_message();
            return $result;
        }

        // Get access token
        $access_token = self::get_access_token();
        if (is_wp_error($access_token)) {
            $result['message'] = $access_token->get_error_message();
            return $result;
        }

        // Test reading spreadsheet
        $response = wp_remote_get(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                ],
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)) {
            $result['message'] = 'Failed to connect to Google Sheets: ' . $response->get_error_message();
            return $result;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || isset($data['error'])) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            $result['message'] = 'Google Sheets API error: ' . $error_message;
            return $result;
        }

        $result['success'] = true;
        $result['message'] = 'Connection successful';
        $result['details'] = [
            'spreadsheet_title' => $data['properties']['title'] ?? 'Unknown',
            'sheets_count'      => count($data['sheets'] ?? []),
            'sheets'            => array_map(function($sheet) {
                return $sheet['properties']['title'] ?? 'Unknown';
            }, $data['sheets'] ?? []),
        ];

        return $result;
    }
    
    /**
     * Get OAuth authorization URL
     */
    public static function get_auth_url(): string {
        $client_id = get_option('mksddn_fh_google_sheets_client_id');
        if (!$client_id) {
            return '';
        }

        $redirect_uri = admin_url('options-general.php?page=google-sheets-settings');
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'scope'         => 'https://www.googleapis.com/auth/spreadsheets',
            'response_type' => 'code',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);
    }
} 