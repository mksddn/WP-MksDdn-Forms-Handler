=== MksDdn Forms Handler ===
Contributors: mksddn
Tags: forms, contact-form, telegram, google-sheets, rest-api, form-handler
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced form processing system with REST API support, Telegram notifications, and Google Sheets integration.

== Description ==

MksDdn Forms Handler is a powerful and flexible form processing plugin that allows you to create and manage forms with multiple delivery methods. Perfect for websites that need reliable form handling with modern integrations.

= Key Features =

* **Multiple Delivery Methods**: Send form submissions via email, Telegram, Google Sheets, or store in WordPress admin
* **REST API Support**: Submit forms via AJAX or REST API endpoints
* **Telegram Integration**: Instant notifications to Telegram channels
* **Google Sheets Integration**: Automatically save submissions to Google Sheets
* **Custom Post Types**: Dedicated forms and submissions management
* **Security First**: Built-in validation, sanitization, and security measures
* **Developer Friendly**: Clean code structure with proper namespacing

= Use Cases =

* Contact forms with multiple delivery options
* Lead generation forms with instant notifications
* Data collection forms with Google Sheets backup
* Custom forms with REST API integration
* Multi-step forms with conditional logic

= Technical Features =

* WordPress 5.0+ compatible
* PHP 7.4+ required
* GPL v2+ licensed
* Clean, maintainable code
* Proper error handling
* Comprehensive logging

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/mksddn-forms-handler` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Forms menu to create and manage your forms
4. Use the shortcode `[mksddn_form id="form_id"]` to display forms on your pages

== Frequently Asked Questions ==

= How do I create my first form? =

1. Go to Forms > Add New in your WordPress admin
2. Fill in the form title and description
3. Configure the form settings in the meta box
4. Add form fields in JSON format
5. Set up delivery methods (email, Telegram, Google Sheets)
6. Publish the form and use the shortcode to display it

= How do I set up Telegram notifications? =

1. Create a Telegram bot using @BotFather
2. Get your bot token
3. Find your chat ID (you can use @userinfobot)
4. Add the bot token and chat IDs in the form settings
5. Enable "Send to Telegram" option

= How do I integrate with Google Sheets? =

1. Set up Google Sheets API credentials
2. Create a spreadsheet and get the ID from the URL
3. Configure the sheet name and API credentials
4. Enable "Send to Google Sheets" option

= Can I use this with custom themes? =

Yes! The plugin is designed to work with any WordPress theme. Forms are displayed using shortcodes and can be styled with CSS.

= Is the plugin secure? =

Yes, the plugin includes comprehensive security measures:
* Input validation and sanitization
* Nonce verification
* Capability checks
* Rate limiting protection
* SQL injection prevention

= Can I submit forms via AJAX? =

Yes! The plugin provides REST API endpoints for AJAX form submissions. Check the documentation for API details.

== Screenshots ==

1. Form creation interface
2. Form settings configuration
3. Submissions management
4. Telegram integration setup
5. Google Sheets integration

== Changelog ==

= 1.0.0 =
* Initial release
* Multiple delivery methods (email, Telegram, Google Sheets)
* REST API support
* Custom post types for forms and submissions
* Security measures and validation
* Clean, maintainable code structure

== Upgrade Notice ==

= 1.0.0 =
Initial release of MksDdn Forms Handler. 