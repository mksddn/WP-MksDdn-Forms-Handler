<?php
/**
 * Plugin Name: MksDdn Forms Handler
 * Plugin URI: https://github.com/mksddn/mksddn-forms-handler
 * Description: Advanced form processing system with REST API support, Telegram notifications, and Google Sheets integration. Create and manage forms with multiple delivery methods including email, Telegram, Google Sheets, and admin storage.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: mksddn
 * Author URI: https://github.com/mksddn
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mksddn-forms-handler
 * Domain Path: /languages
 * Network: false
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
