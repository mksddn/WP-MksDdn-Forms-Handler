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
            __( 'Google Sheets Settings', 'mksddn-forms-handler' ),
            __( 'Google Sheets Settings', 'mksddn-forms-handler' ),
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
            wp_die( esc_html__( 'Access denied', 'mksddn-forms-handler' ) );
        }

        $test_nonce = isset($_POST['test_connection_nonce']) ? sanitize_text_field( wp_unslash($_POST['test_connection_nonce']) ) : '';
        if (!$test_nonce || !wp_verify_nonce( $test_nonce, 'test_google_sheets_connection')) {
            wp_die( esc_html__( 'Security check failed', 'mksddn-forms-handler' ) );
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
            wp_die( esc_html__( 'Access denied', 'mksddn-forms-handler' ) );
        }

        $ajax_nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash($_POST['nonce']) ) : '';
        if (!$ajax_nonce || !wp_verify_nonce( $ajax_nonce, 'test_sheets_nonce')) {
            wp_die( esc_html__( 'Security check failed', 'mksddn-forms-handler' ) );
        }

        $spreadsheet_id = isset($_POST['spreadsheet_id']) ? sanitize_text_field( wp_unslash($_POST['spreadsheet_id']) ) : '';
        if (!$spreadsheet_id) {
            wp_send_json_error( __( 'Spreadsheet ID is required.', 'mksddn-forms-handler' ) );
        }

        $result = GoogleSheetsHandler::test_connection($spreadsheet_id);

        if ($result['success']) {
            wp_send_json_success( __( '✅ Google Sheets connection successful!', 'mksddn-forms-handler' ) );
        } else {
            /* translators: %s: Error message */
            wp_send_json_error( sprintf( __( '❌ Google Sheets connection failed: %s', 'mksddn-forms-handler' ), $result['message'] ) );
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
            <h1><?php esc_html_e( 'Google Sheets Settings', 'mksddn-forms-handler' ); ?></h1>
            
            <?php if ($has_success) : ?>
                <div class="notice notice-success">
                    <p><?php esc_html_e( '✅ Google Sheets authentication successful! You can now use Google Sheets integration in your forms.', 'mksddn-forms-handler' ); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($has_error) : ?>
                <div class="notice notice-error">
                    <p><?php esc_html_e( '❌ Authentication failed. Please try again.', 'mksddn-forms-handler' ); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($has_saved) : ?>
                <div class="notice notice-success">
                    <p><?php esc_html_e( 'Settings saved successfully!', 'mksddn-forms-handler' ); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($has_revoked) : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e( '✅ Google Sheets authentication has been revoked. You can now re-authenticate with different credentials.', 'mksddn-forms-handler' ); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($has_cleared) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( '🗑️ All Google Sheets settings have been cleared. You can start fresh setup.', 'mksddn-forms-handler' ); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2><?php esc_html_e( 'Step 1: Google Cloud Console Setup', 'mksddn-forms-handler' ); ?></h2>
                <ol>
                    <li><?php esc_html_e( 'Go to', 'mksddn-forms-handler' ); ?> <a href="https://console.cloud.google.com/" target="_blank"><?php esc_html_e( 'Google Cloud Console', 'mksddn-forms-handler' ); ?></a></li>
                    <li><?php esc_html_e( 'Create a new project or select existing one', 'mksddn-forms-handler' ); ?></li>
                    <li><?php esc_html_e( 'Enable Google Sheets API:', 'mksddn-forms-handler' ); ?>
                        <ul>
                            <li><?php esc_html_e( 'Go to "APIs &amp; Services" → "Library"', 'mksddn-forms-handler' ); ?></li>
                            <li><?php esc_html_e( 'Search for "Google Sheets API"', 'mksddn-forms-handler' ); ?></li>
                            <li><?php esc_html_e( 'Click "Enable"', 'mksddn-forms-handler' ); ?></li>
                        </ul>
                    </li>
                    <li><?php esc_html_e( 'Create OAuth 2.0 credentials:', 'mksddn-forms-handler' ); ?>
                        <ul>
                            <li><?php esc_html_e( 'Go to "APIs &amp; Services" → "Credentials"', 'mksddn-forms-handler' ); ?></li>
                            <li><?php esc_html_e( 'Click "Create Credentials" → "OAuth 2.0 Client IDs"', 'mksddn-forms-handler' ); ?></li>
                            <li><?php esc_html_e( 'Choose "Web application"', 'mksddn-forms-handler' ); ?></li>
                            <li><?php esc_html_e( 'Add authorized redirect URI:', 'mksddn-forms-handler' ); ?> <code><?php echo esc_html( admin_url('admin.php?page=mksddn-fh-google-sheets-settings') ); ?></code></li>
                            <li><strong><?php esc_html_e( 'Important:', 'mksddn-forms-handler' ); ?></strong> <?php esc_html_e( 'Make sure this exact URL is added to your Google Cloud Console OAuth credentials', 'mksddn-forms-handler' ); ?></li>
                            <li><?php esc_html_e( 'Save Client ID and Client Secret', 'mksddn-forms-handler' ); ?></li>
                        </ul>
                    </li>
                </ol>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e( 'Step 2: Enter Credentials', 'mksddn-forms-handler' ); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('save_google_sheets_settings', 'google_sheets_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="google_sheets_client_id"><?php esc_html_e( 'Client ID', 'mksddn-forms-handler' ); ?></label></th>
                            <td>
                                <input type="text" name="google_sheets_client_id" id="google_sheets_client_id" 
                                        value="<?php echo esc_attr($client_id); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e( 'OAuth 2.0 Client ID from Google Cloud Console', 'mksddn-forms-handler' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="google_sheets_client_secret"><?php esc_html_e( 'Client Secret', 'mksddn-forms-handler' ); ?></label></th>
                            <td>
                                <input type="password" name="google_sheets_client_secret" id="google_sheets_client_secret"
                                        value="<?php echo esc_attr($client_secret); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e( 'OAuth 2.0 Client Secret from Google Cloud Console', 'mksddn-forms-handler' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Credentials', 'mksddn-forms-handler' ); ?>">
                    </p>
                </form>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e( 'Step 3: Authorize Access', 'mksddn-forms-handler' ); ?></h2>
                <?php if ($refresh_token) : ?>
                    <div class="notice notice-success">
                        <p><?php esc_html_e( '✅ Authenticated! Google Sheets integration is ready to use.', 'mksddn-forms-handler' ); ?></p>
                        <p><?php esc_html_e( 'Refresh Token:', 'mksddn-forms-handler' ); ?> <code><?php echo esc_html( substr($refresh_token, 0, 20) . '...' ); ?></code></p>
                    </div>
                    
                    <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
                        <h4><?php esc_html_e( '🔐 Authentication Management', 'mksddn-forms-handler' ); ?></h4>
                        <p><?php esc_html_e( 'If you need to switch to a different Google account or re-authenticate:', 'mksddn-forms-handler' ); ?></p>
                        <form method="post" action="" style="display: inline;">
                            <?php wp_nonce_field('revoke_google_sheets_auth', 'revoke_auth_nonce'); ?>
                            <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to revoke Google Sheets authentication? This will disconnect the current integration.', 'mksddn-forms-handler' ); ?>')">
                                <?php esc_html_e( '🚫 Revoke Authentication', 'mksddn-forms-handler' ); ?>
                            </button>
                        </form>
                        <p style="margin-top: 10px; font-size: 12px; color: #666;">
                            <strong><?php esc_html_e( 'Note:', 'mksddn-forms-handler' ); ?></strong> <?php esc_html_e( "This will remove the current authentication. You'll need to re-authenticate to use Google Sheets integration again.", 'mksddn-forms-handler' ); ?>
                        </p>
                    </div>
                <?php elseif ($client_id && $client_secret) : ?>
                    <p><?php esc_html_e( 'Click the button below to authorize access to Google Sheets:', 'mksddn-forms-handler' ); ?></p>
                    <a href="<?php echo esc_url( \MksDdn\FormsHandler\GoogleSheetsHandler::get_auth_url() ); ?>" class="button button-primary">
                        <?php esc_html_e( '🔐 Authorize Google Sheets Access', 'mksddn-forms-handler' ); ?>
                    </a>
                <?php else : ?>
                    <div class="notice notice-warning">
                        <p><?php esc_html_e( '⚠️ Please save your Client ID and Client Secret first.', 'mksddn-forms-handler' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($refresh_token) : ?>
                <div class="card">
                    <h2><?php esc_html_e( 'Step 4: Test Connection', 'mksddn-forms-handler' ); ?></h2>
                    <p><?php esc_html_e( 'Test your Google Sheets connection:', 'mksddn-forms-handler' ); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="test_spreadsheet_id"><?php esc_html_e( 'Spreadsheet ID', 'mksddn-forms-handler' ); ?></label></th>
                            <td>
                                <input type="text" id="test_spreadsheet_id" class="regular-text" 
                                       placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms" />
                                <p class="description"><?php esc_html_e( 'Enter a spreadsheet ID to test the connection', 'mksddn-forms-handler' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <button type="button" id="test-sheets-btn" class="button button-secondary">
                        <?php esc_html_e( 'Test Google Sheets Connection', 'mksddn-forms-handler' ); ?>
                    </button>
                    <div id="test-result" style="margin-top: 10px;"></div>
                </div>
                
            <?php endif; ?>
            
            <div class="card">
                <h2><?php esc_html_e( 'Usage', 'mksddn-forms-handler' ); ?></h2>
                <p><?php esc_html_e( 'Once configured, you can enable Google Sheets integration in your forms:', 'mksddn-forms-handler' ); ?></p>
                <ol>
                    <li><?php esc_html_e( 'Go to', 'mksddn-forms-handler' ); ?> <strong><?php esc_html_e( 'Forms', 'mksddn-forms-handler' ); ?></strong> → <strong><?php esc_html_e( 'Add New', 'mksddn-forms-handler' ); ?></strong> <?php esc_html_e( 'or edit existing form', 'mksddn-forms-handler' ); ?></li>
                    <li><?php esc_html_e( 'In the', 'mksddn-forms-handler' ); ?> <strong><?php esc_html_e( 'Google Sheets Integration', 'mksddn-forms-handler' ); ?></strong> <?php esc_html_e( 'section:', 'mksddn-forms-handler' ); ?></li>
                    <ul>
                        <li><?php esc_html_e( 'Check "Send to Google Sheets"', 'mksddn-forms-handler' ); ?></li>
                        <li><?php esc_html_e( 'Enter your Spreadsheet ID (from URL: docs.google.com/spreadsheets/d/SPREADSHEET_ID)', 'mksddn-forms-handler' ); ?></li>
                        <li><?php esc_html_e( 'Optionally specify Sheet Name', 'mksddn-forms-handler' ); ?></li>
                    </ul>
                    <li><?php esc_html_e( 'Save the form', 'mksddn-forms-handler' ); ?></li>
                </ol>
            </div>
            
            <?php if ($client_id || $client_secret || $refresh_token) : ?>
            <div class="card" style="border-color: #dc3545;">
                <h2 style="color: #dc3545;"><?php esc_html_e( '⚠️ Clear All Settings', 'mksddn-forms-handler' ); ?></h2>
                <p><?php esc_html_e( 'If you want to completely remove all Google Sheets settings and start over:', 'mksddn-forms-handler' ); ?></p>
                <form method="post" action="" style="display: inline;">
                    <?php wp_nonce_field('clear_google_sheets_all', 'clear_all_nonce'); ?>
                    <button type="submit" class="button button-secondary" style="background: #dc3545; border-color: #dc3545; color: white;" onclick="return confirm('<?php esc_attr_e( '⚠️ WARNING: This will permanently delete ALL Google Sheets settings including Client ID, Client Secret, and Refresh Token. This action cannot be undone. Are you absolutely sure?', 'mksddn-forms-handler' ); ?>')">
                         <?php esc_html_e( '🗑️ Clear All Google Sheets Settings', 'mksddn-forms-handler' ); ?>
                    </button>
                </form>
                <p style="margin-top: 10px; font-size: 12px; color: #666;">
                    <strong><?php esc_html_e( 'Use this when:', 'mksddn-forms-handler' ); ?></strong> <?php esc_html_e( 'You want to switch to different Google account, fix OAuth issues, or start fresh setup.', 'mksddn-forms-handler' ); ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
} 