# Forms Handler Plugin

Unified form processing system with REST API support, Telegram notifications, and Google Sheets integration.

## Structure

The plugin follows WordPress coding standards with a modular architecture:

```
forms-handler/
├── forms-handler.php          # Main plugin loader
├── includes/                  # Core components
│   ├── class-post-types.php      # Custom post types registration
│   ├── class-meta-boxes.php      # Meta boxes for forms and submissions
│   ├── class-forms-handler.php   # Main form processing logic
│   ├── class-shortcodes.php      # Form shortcode rendering
│   ├── class-admin-columns.php   # Admin columns customization
│   ├── class-export-handler.php  # CSV export functionality
│   ├── class-security.php        # Security restrictions
│   ├── class-utilities.php       # Helper functions
│   └── class-google-sheets-admin.php # Google Sheets admin interface
├── handlers/                 # External service handlers
│   ├── class-telegram-handler.php    # Telegram notifications
│   └── class-google-sheets-handler.php # Google Sheets integration
└── templates/               # Template files
    └── form-settings-meta-box.php    # Form settings template
```

## Components

### Core Components (includes/)

- **PostTypes**: Registers custom post types for forms and submissions
- **MetaBoxes**: Handles meta boxes for form settings and submission data
- **FormsHandler**: Main form processing logic with REST API support
- **Shortcodes**: Renders form shortcodes with AJAX functionality
- **AdminColumns**: Customizes admin columns for better UX
- **ExportHandler**: Handles CSV export with filtering options
- **Security**: Implements security restrictions for submissions
- **Utilities**: Helper functions and form creation utilities
- **GoogleSheetsAdmin**: Google Sheets settings page and OAuth handling

### Handlers (handlers/)

- **TelegramHandler**: Sends form submissions to Telegram
- **GoogleSheetsHandler**: Integrates with Google Sheets API

## Features

- **REST API Support**: Submit forms via REST API
- **Multiple Delivery Methods**: Email, Telegram, Google Sheets, Admin storage
- **Security**: Field validation, unauthorized field detection
- **Export**: CSV export with date filtering
- **Admin Interface**: Custom columns, meta boxes, settings pages
- **Shortcodes**: Easy form embedding with `[form id="slug"]`
- **Google Sheets Integration**: OAuth2 authentication and data export

## Usage

### Creating Forms

1. Go to Forms → Add New
2. Configure form settings (recipients, subject, fields)
3. Set up integrations (Telegram, Google Sheets)
4. Use shortcode `[form id="form-slug"]` to display

### REST API

Submit forms via POST to:
```
/wp-json/wp/v2/forms/{slug}/submit
```

### Google Sheets Setup

1. Go to Settings → Google Sheets
2. Enter your Google OAuth 2.0 credentials
3. Authorize access to Google Sheets
4. Configure forms to send data to Google Sheets

### Security Features

- Field validation and sanitization
- Unauthorized field detection
- Request size limits
- Nonce verification
- Capability checks

## Dependencies

- WordPress 5.0+
- PHP 7.4+
- jQuery (for frontend forms)

## License

This plugin is part of a corporate WordPress starter template. 