=== MksDdn Forms Handler ===
Contributors: mksddn
Tags: forms, telegram, google-sheets, rest-api, form-handler
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.3.1
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

= Technical Features =

* WordPress 5.0+ compatible
* PHP 8.0+ required
* GPL v2+ licensed
* Clean, maintainable code
* Proper error handling
* Comprehensive logging

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/mksddn-forms-handler` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Forms menu to create and manage your forms
4. Use the shortcode `[mksddn_fh_form id="form_id"]` to display forms on your pages

== For Developers ==

= Architecture =

**Component-based structure** following SOLID principles with clear separation of concerns:

**Core Components (includes/)**
* `PostTypes` - custom post types registration (forms, submissions)
* `MetaBoxes` - form settings and submission data management
* `FormsHandler` - main processing logic with REST API support
* `Shortcodes` - form rendering with AJAX functionality
* `AdminColumns` - admin interface customization
* `ExportHandler` - CSV export with filtering
* `Security` - rate limiting and security checks
* `Utilities` - helper functions and form creation utilities
* `GoogleSheetsAdmin` - Google Sheets settings page and OAuth
* `Template Functions` - global functions for PHP template integration

**Handlers (handlers/)**
* `TelegramHandler` - Telegram Bot API integration
* `GoogleSheetsHandler` - Google Sheets API integration

**Assets (assets/)**
* `css/` - Admin and frontend styles
* `js/` - Admin and form scripts
* `images/` - Plugin images

= Technology Stack =

* WordPress 5.0+ - core platform
* PHP 8.0+ - server-side logic
* jQuery - client-side form handling
* REST API - form submission API
* Google Sheets API - spreadsheet integration
* Telegram Bot API - notifications

= File Structure =

```
mksddn-forms-handler/
├── mksddn-forms-handler.php     # Main plugin file
├── includes/                     # Core components
│   ├── class-post-types.php
│   ├── class-meta-boxes.php
│   ├── class-forms-handler.php
│   ├── class-shortcodes.php
│   ├── class-admin-columns.php
│   ├── class-export-handler.php
│   ├── class-security.php
│   ├── class-utilities.php
│   ├── class-google-sheets-admin.php
│   └── template-functions.php
├── handlers/                     # External service handlers
│   ├── class-telegram-handler.php
│   └── class-google-sheets-handler.php
├── templates/                    # Template files
│   ├── form-settings-meta-box.php
│   └── custom-form-examples.php
├── assets/                       # Static resources
│   ├── css/
│   ├── js/
│   └── images/
├── languages/                    # Translations
└── uninstall.php                # Cleanup script
```

= Integration Methods =

**1. Shortcode (Standard)**
```php
[mksddn_fh_form slug="contact-form"]
```
Plugin automatically generates HTML form based on configuration.

**2. PHP Templates (Custom Forms)**
Integrate pre-built forms in theme templates:

```php
<form method="post" action="<?php echo mksddn_fh_get_form_action(); ?>">
    <?php mksddn_fh_form_fields('contact-form'); ?>
    <!-- Your custom fields -->
    <input type="text" name="name" required>
    <input type="email" name="email" required>
    <button type="submit">Send</button>
</form>
```

**Available Functions:**
* `mksddn_fh_get_form_action()` - get form action URL
* `mksddn_fh_form_fields($slug)` - output hidden fields (nonce, form_id, honeypot)
* `mksddn_fh_get_form_config($slug)` - get form configuration
* `mksddn_fh_get_rest_endpoint($slug)` - get REST API endpoint for AJAX
* `mksddn_fh_form_has_files($slug)` - check for file fields
* `mksddn_fh_enqueue_form_script()` - enqueue AJAX script

**Accept Any Fields (Advanced):**
For custom forms where you control field names in templates, enable "Accept any fields from frontend" in form settings to skip field validation. This allows submitting ANY field names without defining them in Fields Configuration. All fields are still sanitized but type validation is skipped.

See `/templates/custom-form-examples.php` for detailed examples.

**3. REST API (AJAX)**
Submit forms via REST API without page reload:

```javascript
fetch('<?php echo mksddn_fh_get_rest_endpoint("contact-form"); ?>', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(formData)
});
```

= Development Standards =

**Coding**
* WordPress Coding Standards compliance
* PSR-4 autoloading
* SOLID principles
* DRY (Don't Repeat Yourself)
* KISS (Keep It Simple)

**Security**
* Input validation for all data
* Output sanitization
* Nonce verification (CSRF protection)
* Capability checks
* Rate limiting (1 request per 10 seconds per IP per form)

**Performance**
* Minimal database queries
* Data caching
* Lazy loading of resources
* Conditional script enqueuing

**Compatibility**
* WordPress 5.0+ minimum
* PHP 8.0+ minimum
* Multisite support
* RTL support
* Accessibility standards (WCAG)

= WordPress Hooks & Filters =

**Filters:**

`mksddn_fh_allowed_fields` - Modify allowed field names for a form
```php
add_filter('mksddn_fh_allowed_fields', function($allowed_fields, $form_id, $form_slug) {
    // Allow all fields for specific form
    if ($form_slug === 'my-custom-form') {
        return ['*'];
    }
    // Add specific fields
    return array_merge($allowed_fields, ['custom_field_1', 'custom_field_2']);
}, 10, 3);
```

**Actions:**

`mksddn_forms_handler_log_security` - Fired when unauthorized fields are detected
`mksddn_forms_handler_log_submission` - Fired when form submission is processed

== REST API ==

Namespace: `mksddn-forms-handler/v1`

### List Forms
- **Method**: GET
- **Path**: `/wp-json/mksddn-forms-handler/v1/forms`
- **Query Parameters**:
  - `per_page` (1–100, default: 10)
  - `page` (>=1, default: 1)
  - `search` (string, optional)
- **Response Headers**: `X-WP-Total`, `X-WP-TotalPages`

### Get Single Form
- **Method**: GET
- **Path**: `/wp-json/mksddn-forms-handler/v1/forms/{slug}`
- **Response**: Includes `id`, `slug`, `title`, `submit_url`, `fields` (sanitized config)

### Submit Form
- **Method**: POST
- **Path**: `/wp-json/mksddn-forms-handler/v1/forms/{slug}/submit`
- **Content Types**: JSON or multipart/form-data
- **Body (JSON)**: Key/value pairs according to field configuration. The `mksddn_fh_hp` honeypot field may be present and must be empty.
- **Body (Multipart)**: Fields and file uploads supported. For multiple files, use `name[]`.

#### Validation & Limits
- Only configured fields accepted; unauthorized fields return `unauthorized_fields` error
- Required fields, email, URL, number (min/max/step), tel (pattern), date, time, datetime-local are validated
- Maximum 50 fields; total payload size ≤ 100 KB
- Rate limiting: 1 request per 10 seconds per IP per form

#### Examples

**List forms:**
```bash
curl -s 'https://example.com/wp-json/mksddn-forms-handler/v1/forms'
```

**Get single form:**
```bash
curl -s 'https://example.com/wp-json/mksddn-forms-handler/v1/forms/contact'
```

**Submit form (JSON):**
```bash
curl -s -X POST \
  -H 'Content-Type: application/json' \
  -d '{"name":"John","email":"john@example.com","message":"Hi","mksddn_fh_hp":""}' \
  'https://example.com/wp-json/mksddn-forms-handler/v1/forms/contact/submit'
```

**Submit form with files (multipart):**
```bash
curl -s -X POST \
  -F 'name=John' \
  -F 'email=john@example.com' \
  -F 'attachments[]=@/path/to/file1.pdf' \
  -F 'attachments[]=@/path/to/file2.png' \
  'https://example.com/wp-json/mksddn-forms-handler/v1/forms/contact/submit'
```

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

Yes! The plugin provides REST API endpoints for AJAX form submissions. Check the REST API section for details.

= Can I use custom forms from my theme without configuring fields? =

Yes! Enable "Accept any fields from frontend" in form settings (Advanced Settings section). This allows submitting any field names without defining them in Fields Configuration - perfect for custom forms where you control the HTML. All fields are still sanitized, but type validation is skipped. You can also use the `mksddn_fh_allowed_fields` filter in your theme's functions.php to dynamically allow specific fields or all fields (`return ['*']`).

== Supported Field Types ==

Fields are configured as JSON in the form settings. Supported types:

* **Basic**: text, email, password
* **Input**: tel, url, number, date, time, datetime-local
* **Text**: textarea
* **Choice**: checkbox, select (supports multiple), radio
* **File**: file uploads (form and REST multipart)
* **Array**: array_of_objects - array of objects with nested field validation

### Field Configuration Notes

* `options` can be an array of strings or objects `{ "value": "...", "label": "..." }`
* For `select` with multiple choice, set `multiple: true` (shortcode renders `name[]`)
* For `number`, optional attributes: `min`, `max`, `step`
* For `tel`, optional `pattern` (default server validation uses `^\+?\d{7,15}$`)
* For `date/time/datetime-local`, server validates formats: `YYYY-MM-DD`, `HH:MM`, `YYYY-MM-DDTHH:MM`
* For REST submissions, send arrays for multiple selects

### File Field Options

* `allowed_extensions`: Array of extensions, e.g. `["pdf","png","jpg"]`
* `max_size_mb`: Maximum size per file (default: 10)
* `max_files`: Maximum files per field (default: 5)
* `multiple`: Allow multiple files

### Example JSON Configuration

```json
[
  {"name":"name","label":"Name","type":"text","required":true,"placeholder":"Your name"},
  {"name":"email","label":"Email","type":"email","required":true},
  {"name":"phone","label":"Phone","type":"tel","pattern":"^\\+?\\d{7,15}$"},
  {"name":"website","label":"Website","type":"url"},
  {"name":"age","label":"Age","type":"number","min":1,"max":120,"step":1},
  {"name":"birth","label":"Birth date","type":"date"},
  {"name":"message","label":"Message","type":"textarea","required":true},
  {"name":"agree","label":"I agree to Terms","type":"checkbox","required":true},
  {
    "name":"services",
    "label":"Choose services",
    "type":"select",
    "multiple":true,
    "options":["seo","smm","ads"]
  },
  {
    "name":"attachments",
    "label":"Attach files",
    "type":"file",
    "multiple":true,
    "allowed_extensions":["pdf","png","jpg"],
    "max_size_mb":10,
    "max_files":3
  },
  {
    "name":"products",
    "label":"Products",
    "type":"array_of_objects",
    "required":true,
    "fields":[
      {"name":"name","label":"Product Name","type":"text","required":true},
      {"name":"size","label":"Size","type":"text","required":true},
      {"name":"color","label":"Color","type":"text","required":true},
      {"name":"quantity","label":"Quantity","type":"number","required":true,"min":1},
      {"name":"price","label":"Price","type":"number","required":true,"min":0}
    ]
  }
]
```

### Array of Objects Field Type

The `array_of_objects` type allows you to define arrays with nested field validation. Each item in the array is validated according to the nested `fields` configuration.

**Configuration:**
- `name`: Field name (required)
- `label`: Field label (required)
- `type`: Must be `"array_of_objects"` (required)
- `required`: Whether the array is required (default: false)
- `fields`: Array of field configurations for each object in the array (required)

**Nested fields support all standard field types** (text, email, tel, url, number, textarea, etc.) with full validation.

**Example REST API submission:**
```json
{
  "email": "user@example.com",
  "phone": "+1234567890",
  "products": [
    {
      "name": "T-Shirt",
      "size": "M",
      "color": "Red",
      "quantity": 2,
      "price": 1500
    },
    {
      "name": "Jeans",
      "size": "L",
      "color": "Blue",
      "quantity": 1,
      "price": 3000
    }
  ]
}
```

== Screenshots ==

1. Form creation interface
2. Form settings configuration
3. Submissions management
4. Telegram integration setup
5. Google Sheets integration

== Upgrade Notice ==

= 1.3.1 =
Compatibility update: Tested with WordPress 6.9. Recommended update for users upgrading to WordPress 6.9.

= 1.3.0 =
New `array_of_objects` field type with nested validation. Security: Arrays restricted to `array_of_objects` only. Update forms using `text` for arrays. Recommended for better security.

= 1.2.0 =
New feature: Support for nested arrays and objects in form submissions (e.g., product arrays). Improved display of complex data structures in admin, email, and Telegram. Recommended update for e-commerce and complex form integrations.

= 1.1.1 =
Security update: Fixed URL escaping in template examples. Recommended update for WordPress Coding Standards compliance.

= 1.1.0 =
New feature: Template functions for custom forms integration. Bug fix: Improved Telegram message formatting. Fully backward compatible.

== Changelog ==

= 1.3.1 =
* Compatibility: Tested with WordPress 6.9

= 1.3.0 =
* Feature: New `array_of_objects` field type with full nested field validation
* Feature: Type-specific validation for nested fields (email, number, tel, url, etc.)
* Feature: Type-specific sanitization for nested fields in arrays
* Security: Arrays are now restricted to `array_of_objects` type only - prevents validation bypass
* Security: Simple field types (text, email, number, etc.) now reject arrays for better security
* Improved: Each array item is validated according to nested field configuration
* Improved: Better error messages for array validation with item index
* Technical: Added `validate_array_of_objects()` method for comprehensive array validation
* Technical: Added `sanitize_array_of_objects()` method for type-based sanitization
* Technical: Updated field configuration sanitization to support nested `fields` property
* Breaking: Forms using `text` type for arrays must be updated to `array_of_objects` type
* Full backward compatibility for existing form submissions (data format unchanged)

= 1.2.0 =
* Feature: Added support for nested arrays and objects in REST API submissions
* Feature: Recursive sanitization for complex data structures (arrays of objects)
* Improved: Array of objects now displays as formatted tables in email notifications
* Improved: Array of objects formatted in Telegram messages with proper structure
* Improved: Array of objects saved as JSON in Google Sheets for better data handling
* Improved: Admin submission detail view now shows arrays of objects as HTML tables
* Improved: Admin submissions list shows item count for arrays of objects instead of "Array"
* Improved: Better handling of nested data structures in all delivery methods
* Technical: Added recursive value sanitization method (sanitize_value_recursive)
* Technical: Added recursive data size calculation for nested structures
* Technical: Added helper methods for detecting and rendering arrays of objects
* Full backward compatibility - all existing forms continue to work

= 1.1.1 =
* Security: Added esc_url() escaping for all URL outputs in custom-form-examples.php template
* Fixed: WordPress Coding Standards compliance for template output functions

= 1.1.0 =
* Added support for custom forms in PHP templates
* New template functions for easy integration: mksddn_fh_get_form_action(), mksddn_fh_form_fields(), mksddn_fh_get_form_config()
* New helper functions: mksddn_fh_get_rest_endpoint(), mksddn_fh_form_has_files(), mksddn_fh_enqueue_form_script()
* Added "Accept any fields from frontend" option - skip field validation for custom forms (Advanced Settings)
* New filter: mksddn_fh_allowed_fields - dynamically control allowed field names (supports wildcard '*')
* Added comprehensive examples in templates/custom-form-examples.php
* Extended Utilities class with methods for template integration
* Fixed: Telegram message formatting switched from Markdown to HTML to prevent parsing errors
* Improved: Better handling of special characters in Cyrillic text (dashes, dots, brackets)
* Added: escape_html_for_telegram() method for proper HTML escaping
* Full backward compatibility - all existing shortcodes continue to work

= 1.0.5 =
* REST: Fixed warnings caused by implicit array-to-string conversions (multipart submissions)
* Validation: Hardened guards for email/url/tel/number; refined date/time/datetime-local handling
* REST: Correct total payload size calculation for array values

= 1.0.4 =
* Fields: Added select (with multiple) and radio support in shortcode
* Config: `options` and `multiple` support in fields JSON
* Validation: Ensured submitted values match configured options
* Emails/Admin: Proper rendering of array values (comma-separated)
* Docs: README and readme.txt updated with field types and examples

= 1.0.3 =
* REST: Removed legacy `/wp/v2/forms` route; unified custom namespace
* REST: Added GET endpoints in custom namespace:
  - `GET /wp-json/mksddn-forms-handler/v1/forms`
  - `GET /wp-json/mksddn-forms-handler/v1/forms/{slug}`
* Docs: Updated README and user guides (FAQ, Integrations)
* Meta: Bumped plugin version to 1.0.3

= 1.0.2 =
* Enqueued scripts/styles properly, removed inline JS/CSS
* Prefixed options/transients and custom post types
* REST: custom namespace only; added honeypot and rate limiting
* Security: strict sanitization/validation for fields config JSON
* Compliance updates per Plugin Review feedback
* Prefixes, REST adjustments, enqueue fixes, security hardening
* Readme External services section added

= 1.0.1 =
* Added `uninstall.php` to clean plugin options and transients (keeps CPT data)
* Version metadata adjusted

= 1.0.0 =
* Initial release
* Multiple delivery methods (email, Telegram, Google Sheets)
* REST API support
* Custom post types for forms and submissions
* Security measures and validation
* Clean, maintainable code structure

== External Services ==

This plugin can connect to external services when explicitly enabled in a form's settings:

### Google OAuth2 and Google Sheets API
- **Purpose**: Authenticate and append rows to a spreadsheet
- **When**: Only if "Send to Google Sheets" is enabled for a form and valid credentials are provided
- **Data sent**: Form fields configured for the form, form title, timestamp
- **Endpoints used**: `https://oauth2.googleapis.com/token`, `https://sheets.googleapis.com/v4/spreadsheets/...`
- **Terms**: https://policies.google.com/terms
- **Privacy**: https://policies.google.com/privacy

### Telegram Bot API
- **Purpose**: Send a message with submission content to specified chat(s)
- **When**: Only if "Send to Telegram" is enabled for a form and bot token + chat IDs are configured
- **Data sent**: Form fields configured for the form, form title
- **Endpoint used**: `https://api.telegram.org/bot<token>/sendMessage`
- **Terms/Privacy**: https://telegram.org/privacy

### Privacy Notes
- No IP address or user agent is transmitted to external services; only form field values are sent
- External delivery is opt-in per form and disabled by default
