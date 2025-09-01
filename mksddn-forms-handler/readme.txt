=== MksDdn Forms Handler ===
Contributors: mksddn
Tags: forms, telegram, google-sheets, rest-api, form-handler
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.4
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
4. Use the shortcode `[mksddn_fh_form id="form_id"]` to display forms on your pages

== REST API ==

Namespace: `mksddn-forms-handler/v1`

1) List forms
- Method: GET
- Path: `/wp-json/mksddn-forms-handler/v1/forms`
- Query params:
  - `per_page` (1–100, default: 10)
  - `page` (>=1, default: 1)
  - `search` (string, optional)
- Response headers: `X-WP-Total`, `X-WP-TotalPages`

2) Get single form
- Method: GET
- Path: `/wp-json/mksddn-forms-handler/v1/forms/{slug}`
- Response body includes: `id`, `slug`, `title`, `submit_url`, `fields` (sanitized config)

3) Submit form (JSON or multipart)
- Method: POST
- Path: `/wp-json/mksddn-forms-handler/v1/forms/{slug}/submit`
- Body (application/json): key/value пары согласно конфигу полей. Поле `mksddn_fh_hp` (honeypot) может присутствовать и должно быть пустым.
- Body (multipart/form-data): поддерживаются поля и файлы (`type":"file"`). Для множественных файлов используйте `name[]`.
- Validation & limits:
  - Only configured fields are accepted; others return `unauthorized_fields` error
  - Required fields, `email`, `url`, `number(min/max/step)`, `tel(pattern)`, `date`, `time`, `datetime-local` are validated
  - Max 50 fields; total payload size ≤ 100 KB
  - Rate limiting: 1 request per 10s per IP per form

Examples:

List forms:
```
curl -s 'https://example.com/wp-json/mksddn-forms-handler/v1/forms'
```

Get single form:
```
curl -s 'https://example.com/wp-json/mksddn-forms-handler/v1/forms/contact'
```

Submit form:
```
curl -s -X POST \
  -H 'Content-Type: application/json' \
  -d '{"name":"John","email":"john@example.com","message":"Hi","mksddn_fh_hp":""}' \
  'https://example.com/wp-json/mksddn-forms-handler/v1/forms/contact/submit'
```

Submit form with files (multipart):
```
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

Yes! The plugin provides REST API endpoints for AJAX form submissions. Check the documentation for API details.

== Supported Field Types ==

Fields are configured as JSON in the form settings. Supported types:

* text, email, password
* tel, url, number, date, time, datetime-local
* textarea
* checkbox (if required, must be checked)
* select (supports multiple)
* radio
* file (uploads via form and REST multipart)

Notes:

* `options` can be an array of strings or objects `{ "value": "...", "label": "..." }`.
* For `select` with multiple choice, set `multiple: true` (shortcode renders `name[]`).
* For `number`, optional attributes: `min`, `max`, `step`.
* For `tel`, optional `pattern` (default server validation uses `^\+?\d{7,15}$`).
* For `date/time/datetime-local`, server validates formats: `YYYY-MM-DD`, `HH:MM`, `YYYY-MM-DDTHH:MM`.
* For REST submissions, send arrays for multiple selects.
* File fields:
  - `allowed_extensions`: array of extensions, e.g. `["pdf","png","jpg"]`
  - `max_size_mb`: max size per file (default 10)
  - `max_files`: max files per field (default 5)
  - `multiple`: allow multiple files

Example JSON config:

```
[
  {"name":"name","label":"Name","type":"text","required":true,"placeholder":"Your name"},
  {"name":"email","label":"Email","type":"email","required":true},
  {"name":"password","label":"Password","type":"password","required":true},
  {"name":"phone","label":"Phone","type":"tel","pattern":"^\\+?\\d{7,15}$"},
  {"name":"website","label":"Website","type":"url"},
  {"name":"age","label":"Age","type":"number","min":1,"max":120,"step":1},
  {"name":"birth","label":"Birth date","type":"date"},
  {"name":"meet_time","label":"Meeting time","type":"time"},
  {"name":"meet_at","label":"Meeting at","type":"datetime-local"},
  {"name":"message","label":"Message","type":"textarea","required":true},
  {"name":"agree","label":"I agree to Terms","type":"checkbox","required":true},
  {
    "name":"services",
    "label":"Choose a service (or multiple)",
    "type":"select",
    "required":false,
    "multiple":true,
    "options":["seo","smm","ads"]
  },
  {
    "name":"contact_method",
    "label":"Preferred contact",
    "type":"radio",
    "required":true,
    "options":[
      {"value":"email","label":"Email"},
      {"value":"phone","label":"Phone"}
    ]
  },
  {
    "name":"attachments",
    "label":"Attach files",
    "type":"file",
    "required":false,
    "multiple":true,
    "allowed_extensions":["pdf","png","jpg","jpeg"],
    "max_size_mb":10,
    "max_files":3
  }
]
```

== Screenshots ==

1. Form creation interface
2. Form settings configuration
3. Submissions management
4. Telegram integration setup
5. Google Sheets integration

== Changelog ==

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

== Upgrade Notice ==

= 1.0.3 =
Unified REST namespace and added GET endpoints. Update recommended.

= 1.0.2 =
Compliance update: enqueue fixes, security hardening, and REST adjustments. No breaking changes.

== External services ==

This plugin can connect to external services when explicitly enabled in a form's settings:

1) Google OAuth2 and Google Sheets API
- What: Authenticate and append rows to a spreadsheet
- When: Only if "Send to Google Sheets" is enabled for a form and valid credentials are provided
- Data sent: Form fields configured for the form, form title, timestamp
- Endpoints used: `https://oauth2.googleapis.com/token`, `https://sheets.googleapis.com/v4/spreadsheets/...`
- Terms: https://policies.google.com/terms
- Privacy: https://policies.google.com/privacy

2) Telegram Bot API
- What: Send a message with submission content to specified chat(s)
- When: Only if "Send to Telegram" is enabled for a form and bot token + chat IDs are configured
- Data sent: Form fields configured for the form, form title
- Endpoint used: `https://api.telegram.org/bot<token>/sendMessage`
- Terms/Privacy: https://telegram.org/privacy

Notes:
- No IP address or user agent is transmitted to external services; only form field values are sent.
- External delivery is opt-in per form and disabled by default.