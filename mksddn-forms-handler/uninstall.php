<?php
/**
 * @file: uninstall.php
 * @description: Cleanup routine executed when the plugin is uninstalled. Removes plugin options and related transients. Keeps CPT data as per policy.
 * @dependencies: WordPress core
 * @created: 2025-08-21
 */

// Exit if accessed directly or not via WordPress uninstall process
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options (Google Sheets integration credentials)
delete_option('google_sheets_client_id');
delete_option('google_sheets_client_secret');
delete_option('google_sheets_refresh_token');

// Cleanup of transients created by this plugin (best-effort via API)
$user_id = get_current_user_id();
if ($user_id) {
    delete_transient('fields_config_json_error_' . $user_id);
    delete_transient('fields_config_json_value_' . $user_id);
}

// Intentionally keep CPT data (forms, form_submissions) to avoid data loss
// If in the future a data deletion option is introduced, handle it here conditionally.


