<?php
/**
 * Plugin Name: MksDdn Forms Handler
 * Plugin URI: https://github.com/mksddn/mksddn-forms-handler
 * Description: Advanced form processing system with REST API support, Telegram notifications, and Google Sheets integration. Create and manage forms with multiple delivery methods including email, Telegram, Google Sheets, and admin storage.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 8.0
 * Author: mksddn
 * Author URI: https://github.com/mksddn
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mksddn-forms-handler
 * Domain Path: /languages
 * 
 * @package MksDdnFormsHandler
 * @version 1.0.0
 * @author mksddn
 * @license GPL v2 or later
*/

// Prevent direct access
if (!defined('ABSPATH')) {
        exit;
    }

// Define plugin constants
define('MKSDDN_FORMS_HANDLER_VERSION', '1.0.0');
define('MKSDDN_FORMS_HANDLER_PLUGIN_DIR', __DIR__);
define('MKSDDN_FORMS_HANDLER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MKSDDN_FORMS_HANDLER_PLUGIN_FILE', __FILE__);
define('MKSDDN_FORMS_HANDLER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load components
require_once MKSDDN_FORMS_HANDLER_PLUGIN_DIR . '/includes/class-post-types.php';
require_once MKSDDN_FORMS_HANDLER_PLUGIN_DIR . '/includes/class-meta-boxes.php';
require_once MKSDDN_FORMS_HANDLER_PLUGIN_DIR . '/includes/class-forms-handler.php';
require_once MKSDDN_FORMS_HANDLER_PLUGIN_DIR . '/includes/class-shortcodes.php';
require_once MKSDDN_FORMS_HANDLER_PLUGIN_DIR . '/includes/class-admin-columns.php';
require_once MKSDDN_FORMS_HANDLER_PLUGIN_DIR . '/includes/class-export-handler.php';
require_once MKSDDN_FORMS_HANDLER_PLUGIN_DIR . '/includes/class-security.php';
require_once MKSDDN_FORMS_HANDLER_PLUGIN_DIR . '/includes/class-utilities.php';
require_once MKSDDN_FORMS_HANDLER_PLUGIN_DIR . '/includes/class-google-sheets-admin.php';

// Load handlers
require_once MKSDDN_FORMS_HANDLER_PLUGIN_DIR . '/handlers/class-telegram-handler.php';
require_once MKSDDN_FORMS_HANDLER_PLUGIN_DIR . '/handlers/class-google-sheets-handler.php';

// Initialize plugin
add_action('plugins_loaded', function() {
    // Load plugin textdomain for translations
    load_plugin_textdomain('mksddn-forms-handler', false, dirname(MKSDDN_FORMS_HANDLER_PLUGIN_BASENAME) . '/languages');

    new MksDdn\FormsHandler\PostTypes();
    new MksDdn\FormsHandler\MetaBoxes();
    new MksDdn\FormsHandler\FormsHandler();
    new MksDdn\FormsHandler\Shortcodes();
    new MksDdn\FormsHandler\AdminColumns();
    new MksDdn\FormsHandler\ExportHandler();
    new MksDdn\FormsHandler\Security();
    new MksDdn\FormsHandler\GoogleSheetsAdmin();
    
    // Nothing else here
});

// Run tasks on plugin activation
register_activation_hook(MKSDDN_FORMS_HANDLER_PLUGIN_FILE, function () {
    // Register post types so rewrite rules are aware of them, then flush
    $post_types = new MksDdn\FormsHandler\PostTypes();
    $post_types->register_post_types();
    flush_rewrite_rules();

    // Create a default contact form
    MksDdn\FormsHandler\Utilities::create_default_contact_form();
});
