<?php
/**
 * @file: class-google-sheets-admin.php
 * @description: Google Sheets admin functionality and OAuth handling
 * @dependencies: WordPress core, Google Sheets API
 * @created: 2025-07-30
 */

namespace MksDdn\FormsHandler;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Sheets Admin functionality
 */
class GoogleSheetsAdmin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'handle_oauth_callback']);
        add_action('admin_init', [$this, 'save_settings']);
        add_action('admin_post_mksddn_fh_test_google_sheets_connection', [$this, 'handle_test_connection']);
        add_action('wp_ajax_mksddn_fh_test_google_sheets_connection', [$this, 'handle_ajax_test_connection']);
    }
    
    /**
     * Add settings page to admin panel as submenu under Forms
     */
    public function add_settings_page(): void {
        add_submenu_page(
            'edit.php?post_type=mksddn_fh_forms',
            'Google Sheets Settings',
            'Google Sheets',
            'manage_options',
            'mksddn-fh-google-sheets-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth callback relies on GET params from Google
        if (isset($_GET['page']) && $_GET['page'] === 'mksddn-fh-google-sheets-settings' && isset($_GET['code'])) {
            $code = sanitize_text_field( wp_unslash($_GET['code']) );
            $client_id = get_option('mksddn_fh_google_sheets_client_id');
            $client_secret = get_option('mksddn_fh_google_sheets_client_secret');

            if ($client_id && $client_secret) {
                $response = wp_remote_post(
                    'https://oauth2.googleapis.com/token',
                    [
                        'body'    => [
                            'client_id'     => $client_id,
                            'client_secret' => $client_secret,
                            'code'          => $code,
                            'grant_type'    => 'authorization_code',
                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth redirect target
                            'redirect_uri'  => admin_url('admin.php?page=mksddn-fh-google-sheets-settings'),
                        ],
                        'timeout' => 30,
                    ]
                );

                if (!is_wp_error($response)) {
                    $result = json_decode(wp_remote_retrieve_body($response), true);

                    if (isset($result['refresh_token'])) {
                        update_option('mksddn_fh_google_sheets_refresh_token', $result['refresh_token']);
                        wp_safe_redirect( esc_url_raw( admin_url('admin.php?page=mksddn-fh-google-sheets-settings&success=1') ) );
                        exit;
                    }

                    wp_safe_redirect( esc_url_raw( admin_url('admin.php?page=mksddn-fh-google-sheets-settings&error=1') ) );
                    exit;
                }

                wp_safe_redirect( esc_url_raw( admin_url('admin.php?page=mksddn-fh-google-sheets-settings&error=1') ) );
                exit;
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    }
    
    /**
     * Save settings
     */
    public function save_settings(): void {
        $settings_nonce = isset($_POST['google_sheets_settings_nonce']) ? sanitize_text_field( wp_unslash($_POST['google_sheets_settings_nonce']) ) : '';
        if ($settings_nonce && wp_verify_nonce( $settings_nonce, 'save_google_sheets_settings')) {
            if (isset($_POST['google_sheets_client_id'])) {
                update_option('mksddn_fh_google_sheets_client_id', sanitize_text_field( wp_unslash($_POST['google_sheets_client_id']) ));
            }

            if (isset($_POST['google_sheets_client_secret'])) {
                update_option('mksddn_fh_google_sheets_client_secret', sanitize_text_field( wp_unslash($_POST['google_sheets_client_secret']) ));
            }

            wp_safe_redirect( esc_url_raw( admin_url('admin.php?page=mksddn-fh-google-sheets-settings&saved=1') ) );
            exit;
        }

        // Handle authentication revocation
        $revoke_nonce = isset($_POST['revoke_auth_nonce']) ? sanitize_text_field( wp_unslash($_POST['revoke_auth_nonce']) ) : '';
        if ($revoke_nonce && wp_verify_nonce( $revoke_nonce, 'revoke_google_sheets_auth')) {
            delete_option('mksddn_fh_google_sheets_refresh_token');
            wp_safe_redirect( esc_url_raw( admin_url('admin.php?page=mksddn-fh-google-sheets-settings&revoked=1') ) );
            exit;
        }

        // Handle full settings clearing
        $clear_nonce = isset($_POST['clear_all_nonce']) ? sanitize_text_field( wp_unslash($_POST['clear_all_nonce']) ) : '';
        if ($clear_nonce && wp_verify_nonce( $clear_nonce, 'clear_google_sheets_all')) {
            delete_option('mksddn_fh_google_sheets_client_id');
            delete_option('mksddn_fh_google_sheets_client_secret');
            delete_option('mksddn_fh_google_sheets_refresh_token');

            wp_safe_redirect( esc_url_raw( admin_url('admin.php?page=mksddn-fh-google-sheets-settings&cleared=1') ) );
            exit;
        }
    }
    
    /**
     * Handle test connection
     */
    public function handle_test_connection(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        $test_nonce = isset($_POST['test_connection_nonce']) ? sanitize_text_field( wp_unslash($_POST['test_connection_nonce']) ) : '';
        if (!$test_nonce || !wp_verify_nonce( $test_nonce, 'test_google_sheets_connection')) {
            wp_die('Security check failed');
        }

        $spreadsheet_id = isset($_POST['spreadsheet_id']) ? sanitize_text_field( wp_unslash($_POST['spreadsheet_id']) ) : '';
        if (!$spreadsheet_id) {
            wp_safe_redirect( esc_url_raw( admin_url('admin.php?page=mksddn-fh-google-sheets-settings&error=no_spreadsheet_id') ) );
            exit;
        }

        $result = GoogleSheetsHandler::test_connection($spreadsheet_id);

        if ($result['success']) {
            wp_safe_redirect( esc_url_raw( admin_url('admin.php?page=mksddn-fh-google-sheets-settings&test_success=1&details=' . urlencode(json_encode($result['details']))) ) );
        } else {
            wp_safe_redirect( esc_url_raw( admin_url('admin.php?page=mksddn-fh-google-sheets-settings&test_error=' . urlencode($result['message'])) ) );
        }
        exit;
    }

    /**
     * Handle AJAX test connection
     */
    public function handle_ajax_test_connection(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        $ajax_nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash($_POST['nonce']) ) : '';
        if (!$ajax_nonce || !wp_verify_nonce( $ajax_nonce, 'test_sheets_nonce')) {
            wp_die('Security check failed');
        }

        $spreadsheet_id = isset($_POST['spreadsheet_id']) ? sanitize_text_field( wp_unslash($_POST['spreadsheet_id']) ) : '';
        if (!$spreadsheet_id) {
            wp_send_json_error('Spreadsheet ID is required.');
        }

        $result = GoogleSheetsHandler::test_connection($spreadsheet_id);

        if ($result['success']) {
            wp_send_json_success('✅ Google Sheets connection successful!');
        } else {
            wp_send_json_error('❌ Google Sheets connection failed: ' . $result['message']);
        }
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading benign query params for notices only
        $qs = isset($_GET) ? wp_unslash($_GET) : [];
        $has_success = isset($qs['success']);
        $has_error   = isset($qs['error']);
        $has_saved   = isset($qs['saved']);
        $has_revoked = isset($qs['revoked']);
        $has_cleared = isset($qs['cleared']);
        $client_id = get_option('mksddn_fh_google_sheets_client_id');
        $client_secret = get_option('mksddn_fh_google_sheets_client_secret');
        $refresh_token = get_option('mksddn_fh_google_sheets_refresh_token');

        ?>
        <div class="wrap">
            <h1>Google Sheets Settings</h1>
            
            <?php if ($has_success) : ?>
                <div class="notice notice-success">
                    <p>✅ Google Sheets authentication successful! You can now use Google Sheets integration in your forms.</p>
                </div>
            <?php endif; ?>
            
            <?php if ($has_error) : ?>
                <div class="notice notice-error">
                    <p>❌ Authentication failed. Please try again.</p>
                </div>
            <?php endif; ?>
            
            <?php if ($has_saved) : ?>
                <div class="notice notice-success">
                    <p>Settings saved successfully!</p>
                </div>
            <?php endif; ?>
            
            <?php if ($has_revoked) : ?>
                <div class="notice notice-warning">
                    <p>✅ Google Sheets authentication has been revoked. You can now re-authenticate with different credentials.</p>
                </div>
            <?php endif; ?>
            
            <?php if ($has_cleared) : ?>
                <div class="notice notice-info">
                    <p>🗑️ All Google Sheets settings have been cleared. You can start fresh setup.</p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Step 1: Google Cloud Console Setup</h2>
                <ol>
                    <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                    <li>Create a new project or select existing one</li>
                    <li>Enable Google Sheets API:
                        <ul>
                            <li>Go to "APIs &amp; Services" → "Library"</li>
                            <li>Search for "Google Sheets API"</li>
                            <li>Click "Enable"</li>
                        </ul>
                    </li>
                    <li>Create OAuth 2.0 credentials:
                        <ul>
                            <li>Go to "APIs &amp; Services" → "Credentials"</li>
                            <li>Click "Create Credentials" → "OAuth 2.0 Client IDs"</li>
                            <li>Choose "Web application"</li>
                            <li>Add authorized redirect URI: <code><?php echo esc_html( admin_url('admin.php?page=mksddn-fh-google-sheets-settings') ); ?></code></li>
                            <li><strong>Important:</strong> Make sure this exact URL is added to your Google Cloud Console OAuth credentials</li>
                            <li>Save Client ID and Client Secret</li>
                        </ul>
                    </li>
                </ol>
            </div>
            
            <div class="card">
                <h2>Step 2: Enter Credentials</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('save_google_sheets_settings', 'google_sheets_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="google_sheets_client_id">Client ID</label></th>
                            <td>
                                <input type="text" name="google_sheets_client_id" id="google_sheets_client_id" 
                                        value="<?php echo esc_attr($client_id); ?>" class="regular-text" />
                                <p class="description">OAuth 2.0 Client ID from Google Cloud Console</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="google_sheets_client_secret">Client Secret</label></th>
                            <td>
                                <input type="password" name="google_sheets_client_secret" id="google_sheets_client_secret"
                                        value="<?php echo esc_attr($client_secret); ?>" class="regular-text" />
                                <p class="description">OAuth 2.0 Client Secret from Google Cloud Console</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Credentials">
                    </p>
                </form>
            </div>
            
            <div class="card">
                <h2>Step 3: Authorize Access</h2>
                <?php if ($refresh_token) : ?>
                    <div class="notice notice-success">
                        <p>✅ <strong>Authenticated!</strong> Google Sheets integration is ready to use.</p>
                        <p>Refresh Token: <code><?php echo esc_html( substr($refresh_token, 0, 20) . '...' ); ?></code></p>
                    </div>
                    
                    <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
                        <h4>🔐 Authentication Management</h4>
                        <p>If you need to switch to a different Google account or re-authenticate:</p>
                        <form method="post" action="" style="display: inline;">
                            <?php wp_nonce_field('revoke_google_sheets_auth', 'revoke_auth_nonce'); ?>
                            <button type="submit" class="button button-secondary" onclick="return confirm('Are you sure you want to revoke Google Sheets authentication? This will disconnect the current integration.')">
                                🚫 Revoke Authentication
                            </button>
                        </form>
                        <p style="margin-top: 10px; font-size: 12px; color: #666;">
                            <strong>Note:</strong> This will remove the current authentication. You'll need to re-authenticate to use Google Sheets integration again.
                        </p>
                    </div>
                <?php elseif ($client_id && $client_secret) : ?>
                    <p>Click the button below to authorize access to Google Sheets:</p>
                    <a href="<?php echo esc_url( \MksDdn\FormsHandler\GoogleSheetsHandler::get_auth_url() ); ?>" class="button button-primary">
                        🔐 Authorize Google Sheets Access
                    </a>
                <?php else : ?>
                    <div class="notice notice-warning">
                        <p>⚠️ Please save your Client ID and Client Secret first.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($refresh_token) : ?>
                <div class="card">
                    <h2>Step 4: Test Connection</h2>
                    <p>Test your Google Sheets connection:</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="test_spreadsheet_id">Spreadsheet ID</label></th>
                            <td>
                                <input type="text" id="test_spreadsheet_id" class="regular-text" 
                                       placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms" />
                                <p class="description">Enter a spreadsheet ID to test the connection</p>
                            </td>
                        </tr>
                    </table>
                    <button type="button" id="test-sheets-btn" class="button button-secondary">
                        Test Google Sheets Connection
                    </button>
                    <div id="test-result" style="margin-top: 10px;"></div>
                </div>
                
            <?php endif; ?>
            
            <div class="card">
                <h2>Usage</h2>
                <p>Once configured, you can enable Google Sheets integration in your forms:</p>
                <ol>
                    <li>Go to <strong>Forms</strong> → <strong>Add New</strong> or edit existing form</li>
                    <li>In the <strong>Google Sheets Integration</strong> section:</li>
                    <ul>
                        <li>Check "Send to Google Sheets"</li>
                        <li>Enter your Spreadsheet ID (from URL: docs.google.com/spreadsheets/d/SPREADSHEET_ID)</li>
                        <li>Optionally specify Sheet Name</li>
                    </ul>
                    <li>Save the form</li>
                </ol>
            </div>
            
            <?php if ($client_id || $client_secret || $refresh_token) : ?>
            <div class="card" style="border-color: #dc3545;">
                <h2 style="color: #dc3545;">⚠️ Clear All Settings</h2>
                <p>If you want to completely remove all Google Sheets settings and start over:</p>
                <form method="post" action="" style="display: inline;">
                    <?php wp_nonce_field('clear_google_sheets_all', 'clear_all_nonce'); ?>
                    <button type="submit" class="button button-secondary" style="background: #dc3545; border-color: #dc3545; color: white;" onclick="return confirm('⚠️ WARNING: This will permanently delete ALL Google Sheets settings including Client ID, Client Secret, and Refresh Token. This action cannot be undone. Are you absolutely sure?')">
                         🗑️ Clear All Google Sheets Settings
                    </button>
                </form>
                <p style="margin-top: 10px; font-size: 12px; color: #666;">
                    <strong>Use this when:</strong> You want to switch to different Google account, fix OAuth issues, or start fresh setup.
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
} 