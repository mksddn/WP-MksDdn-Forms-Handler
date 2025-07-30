<?php
/*
Plugin Name: Forms Handler
Description: Unified form processing system with REST API support
Version: 1.0
Author: mksddn
*/

// Prevent direct access
if (!defined('ABSPATH')) {
        exit;
    }

// Define plugin constants
define('FORMS_HANDLER_VERSION', '1.0');
define('FORMS_HANDLER_PLUGIN_DIR', __DIR__);
define('FORMS_HANDLER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load components
require_once FORMS_HANDLER_PLUGIN_DIR . '/includes/class-post-types.php';
require_once FORMS_HANDLER_PLUGIN_DIR . '/includes/class-meta-boxes.php';
require_once FORMS_HANDLER_PLUGIN_DIR . '/includes/class-forms-handler.php';
require_once FORMS_HANDLER_PLUGIN_DIR . '/includes/class-shortcodes.php';
require_once FORMS_HANDLER_PLUGIN_DIR . '/includes/class-admin-columns.php';
require_once FORMS_HANDLER_PLUGIN_DIR . '/includes/class-export-handler.php';
require_once FORMS_HANDLER_PLUGIN_DIR . '/includes/class-security.php';
require_once FORMS_HANDLER_PLUGIN_DIR . '/includes/class-utilities.php';
require_once FORMS_HANDLER_PLUGIN_DIR . '/includes/class-google-sheets-admin.php';

// Load handlers
require_once FORMS_HANDLER_PLUGIN_DIR . '/handlers/class-telegram-handler.php';
require_once FORMS_HANDLER_PLUGIN_DIR . '/handlers/class-google-sheets-handler.php';

// Initialize plugin
add_action('plugins_loaded', function() {
    new FormsHandler\PostTypes();
    new FormsHandler\MetaBoxes();
    new FormsHandler\FormsHandler();
    new FormsHandler\Shortcodes();
    new FormsHandler\AdminColumns();
    new FormsHandler\ExportHandler();
    new FormsHandler\Security();
    new FormsHandler\GoogleSheetsAdmin();
    
    // Create default form on theme activation
    add_action('after_switch_theme', 'FormsHandler\Utilities::create_default_contact_form');
});
