<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<table class="form-table">
    <tr>
        <th scope="row"><label for="recipients"><?php echo esc_html__( 'Recipients (comma separated)', 'mksddn-forms-handler' ); ?></label></th>
        <td>
            <input type="text" name="recipients" id="recipients" value="<?php echo esc_attr($recipients); ?>" class="regular-text" />
            <p class="description"><?php echo esc_html__( 'Email addresses separated by commas', 'mksddn-forms-handler' ); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="bcc_recipient"><?php echo esc_html__( 'BCC Recipient', 'mksddn-forms-handler' ); ?></label></th>
        <td>
            <input type="email" name="bcc_recipient" id="bcc_recipient" value="<?php echo esc_attr($bcc_recipient); ?>" class="regular-text" />
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="subject"><?php echo esc_html__( 'Email Subject', 'mksddn-forms-handler' ); ?></label></th>
        <td>
            <input type="text" name="subject" id="subject" value="<?php echo esc_attr($subject); ?>" class="regular-text" />
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="fields_config"><?php echo esc_html__( 'Fields Configuration', 'mksddn-forms-handler' ); ?></label></th>
        <td>
            <textarea name="fields_config" id="fields_config" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($fields_config); ?></textarea>
            <p class="description"><?php echo esc_html__( 'JSON configuration of form fields. Example: [{"name": "name", "label": "Name", "type": "text", "required": true}]', 'mksddn-forms-handler' ); ?></p>
        </td>
    </tr>
    
    <tr>
        <th scope="row" colspan="2">
            <h3 style="margin: 0; padding: 10px 0; border-bottom: 1px solid #ccc;"><?php echo esc_html__( 'Telegram Notifications', 'mksddn-forms-handler' ); ?></h3>
        </th>
    </tr>
    <tr>
        <th scope="row">
            <label>
                <input type="checkbox" name="send_to_telegram" id="send_to_telegram" value="1" <?php checked($send_to_telegram, '1'); ?> />
                <?php echo esc_html__( 'Send to Telegram', 'mksddn-forms-handler' ); ?>
            </label>
        </th>
        <td>
            <p class="description"><?php echo esc_html__( 'Enable Telegram notifications for this form', 'mksddn-forms-handler' ); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="telegram_bot_token"><?php echo esc_html__( 'Telegram Bot Token', 'mksddn-forms-handler' ); ?></label></th>
        <td>
            <input type="text" name="telegram_bot_token" id="telegram_bot_token" value="<?php echo esc_attr($telegram_bot_token); ?>" class="regular-text" />
            <p class="description"><?php echo esc_html__( 'Your Telegram bot token (e.g., 123456789:ABCdefGHIjklMNOpqrsTUVwxyz)', 'mksddn-forms-handler' ); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="telegram_chat_ids"><?php echo esc_html__( 'Telegram Chat IDs', 'mksddn-forms-handler' ); ?></label></th>
        <td>
            <input type="text" name="telegram_chat_ids" id="telegram_chat_ids" value="<?php echo esc_attr($telegram_chat_ids); ?>" class="regular-text" />
            <p class="description"><?php echo esc_html__( 'Chat IDs separated by commas (e.g., -1001234567890, -1009876543210)', 'mksddn-forms-handler' ); ?></p>
        </td>
    </tr>
    
    <tr>
        <th scope="row" colspan="2">
            <h3 style="margin: 0; padding: 10px 0; border-bottom: 1px solid #ccc;"><?php echo esc_html__( 'Google Sheets Integration', 'mksddn-forms-handler' ); ?></h3>
        </th>
    </tr>
    <tr>
        <th scope="row">
            <label>
                <input type="checkbox" name="send_to_sheets" id="send_to_sheets" value="1" <?php checked($send_to_sheets, '1'); ?> />
                <?php echo esc_html__( 'Send to Google Sheets', 'mksddn-forms-handler' ); ?>
            </label>
        </th>
        <td>
            <p class="description"><?php echo esc_html__( 'Enable Google Sheets integration for this form', 'mksddn-forms-handler' ); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="sheets_spreadsheet_id"><?php echo esc_html__( 'Spreadsheet ID', 'mksddn-forms-handler' ); ?></label></th>
        <td>
            <input type="text" name="sheets_spreadsheet_id" id="sheets_spreadsheet_id" value="<?php echo esc_attr($sheets_spreadsheet_id); ?>" class="regular-text" />
            <p class="description"><?php echo esc_html__( 'Google Sheets spreadsheet ID (from URL: docs.google.com/spreadsheets/d/SPREADSHEET_ID)', 'mksddn-forms-handler' ); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="sheets_sheet_name"><?php echo esc_html__( 'Sheet Name', 'mksddn-forms-handler' ); ?></label></th>
        <td>
            <input type="text" name="sheets_sheet_name" id="sheets_sheet_name" value="<?php echo esc_attr($sheets_sheet_name); ?>" class="regular-text" />
            <p class="description"><?php echo esc_html__( 'Sheet name (optional, defaults to first sheet)', 'mksddn-forms-handler' ); ?></p>
        </td>
    </tr>
    
    <tr>
        <th scope="row" colspan="2">
            <h3 style="margin: 0; padding: 10px 0; border-bottom: 1px solid #ccc;"><?php echo esc_html__( 'Admin Panel Storage', 'mksddn-forms-handler' ); ?></h3>
        </th>
    </tr>
    <tr>
        <th scope="row">
            <label>
                <input type="checkbox" name="save_to_admin" id="save_to_admin" value="1" <?php checked($save_to_admin, '1'); ?> />
                <?php echo esc_html__( 'Save submissions to admin panel', 'mksddn-forms-handler' ); ?>
            </label>
        </th>
        <td>
            <p class="description"><?php echo esc_html__( 'Enable saving form submissions to admin panel for viewing and export', 'mksddn-forms-handler' ); ?></p>
        </td>
    </tr>
</table> 