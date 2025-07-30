<table class="form-table">
    <tr>
        <th scope="row"><label for="recipients">Recipients (comma separated)</label></th>
        <td>
            <input type="text" name="recipients" id="recipients" value="<?php echo esc_attr($recipients); ?>" class="regular-text" />
            <p class="description">Email addresses separated by commas</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="bcc_recipient">BCC Recipient</label></th>
        <td>
            <input type="email" name="bcc_recipient" id="bcc_recipient" value="<?php echo esc_attr($bcc_recipient); ?>" class="regular-text" />
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="subject">Email Subject</label></th>
        <td>
            <input type="text" name="subject" id="subject" value="<?php echo esc_attr($subject); ?>" class="regular-text" />
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="fields_config">Fields Configuration</label></th>
        <td>
            <textarea name="fields_config" id="fields_config" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($fields_config); ?></textarea>
            <p class="description">JSON configuration of form fields. Example: [{"name": "name", "label": "Name", "type": "text", "required": true}]</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row" colspan="2">
            <h3 style="margin: 0; padding: 10px 0; border-bottom: 1px solid #ccc;">Telegram Notifications</h3>
        </th>
    </tr>
    <tr>
        <th scope="row">
            <label>
                <input type="checkbox" name="send_to_telegram" id="send_to_telegram" value="1" <?php checked($send_to_telegram, '1'); ?> />
                Send to Telegram
            </label>
        </th>
        <td>
            <p class="description">Enable Telegram notifications for this form</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="telegram_bot_token">Telegram Bot Token</label></th>
        <td>
            <input type="text" name="telegram_bot_token" id="telegram_bot_token" value="<?php echo esc_attr($telegram_bot_token); ?>" class="regular-text" />
            <p class="description">Your Telegram bot token (e.g., 123456789:ABCdefGHIjklMNOpqrsTUVwxyz)</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="telegram_chat_ids">Telegram Chat IDs</label></th>
        <td>
            <input type="text" name="telegram_chat_ids" id="telegram_chat_ids" value="<?php echo esc_attr($telegram_chat_ids); ?>" class="regular-text" />
            <p class="description">Chat IDs separated by commas (e.g., -1001234567890, -1009876543210)</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row" colspan="2">
            <h3 style="margin: 0; padding: 10px 0; border-bottom: 1px solid #ccc;">Google Sheets Integration</h3>
        </th>
    </tr>
    <tr>
        <th scope="row">
            <label>
                <input type="checkbox" name="send_to_sheets" id="send_to_sheets" value="1" <?php checked($send_to_sheets, '1'); ?> />
                Send to Google Sheets
            </label>
        </th>
        <td>
            <p class="description">Enable Google Sheets integration for this form</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="sheets_spreadsheet_id">Spreadsheet ID</label></th>
        <td>
            <input type="text" name="sheets_spreadsheet_id" id="sheets_spreadsheet_id" value="<?php echo esc_attr($sheets_spreadsheet_id); ?>" class="regular-text" />
            <p class="description">Google Sheets spreadsheet ID (from URL: docs.google.com/spreadsheets/d/SPREADSHEET_ID)</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="sheets_sheet_name">Sheet Name</label></th>
        <td>
            <input type="text" name="sheets_sheet_name" id="sheets_sheet_name" value="<?php echo esc_attr($sheets_sheet_name); ?>" class="regular-text" />
            <p class="description">Sheet name (optional, defaults to first sheet)</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row" colspan="2">
            <h3 style="margin: 0; padding: 10px 0; border-bottom: 1px solid #ccc;">Admin Panel Storage</h3>
        </th>
    </tr>
    <tr>
        <th scope="row">
            <label>
                <input type="checkbox" name="save_to_admin" id="save_to_admin" value="1" <?php checked($save_to_admin, '1'); ?> />
                Save submissions to admin panel
            </label>
        </th>
        <td>
            <p class="description">Enable saving form submissions to admin panel for viewing and export</p>
        </td>
    </tr>
</table> 