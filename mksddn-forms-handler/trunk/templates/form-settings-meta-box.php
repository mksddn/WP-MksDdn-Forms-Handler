<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>

<div class="mksddn-form-tabs">
    <ul class="mksddn-form-tabs-nav">
        <li><a href="#mksddn-tab-fields" class="mksddn-tab-nav active"><?php echo esc_html__( 'Form Fields', 'mksddn-forms-handler' ); ?></a></li>
        <li><a href="#mksddn-tab-email" class="mksddn-tab-nav"><?php echo esc_html__( 'Email Settings', 'mksddn-forms-handler' ); ?></a></li>
        <li><a href="#mksddn-tab-telegram" class="mksddn-tab-nav"><?php echo esc_html__( 'Telegram', 'mksddn-forms-handler' ); ?></a></li>
        <li><a href="#mksddn-tab-sheets" class="mksddn-tab-nav"><?php echo esc_html__( 'Google Sheets', 'mksddn-forms-handler' ); ?></a></li>
        <li><a href="#mksddn-tab-admin" class="mksddn-tab-nav"><?php echo esc_html__( 'Admin Storage', 'mksddn-forms-handler' ); ?></a></li>
        <li><a href="#mksddn-tab-display" class="mksddn-tab-nav"><?php echo esc_html__( 'Display', 'mksddn-forms-handler' ); ?></a></li>
        <li><a href="#mksddn-tab-advanced" class="mksddn-tab-nav"><?php echo esc_html__( 'Advanced', 'mksddn-forms-handler' ); ?></a></li>
    </ul>

    <!-- Form Fields Tab -->
    <div id="mksddn-tab-fields" class="mksddn-form-tab-content active">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="fields_config"><?php echo esc_html__( 'Fields Configuration', 'mksddn-forms-handler' ); ?></label></th>
                <td>
                    <textarea name="fields_config" id="fields_config" rows="15" cols="50" class="large-text code"><?php echo esc_textarea($fields_config); ?></textarea>
                    <p class="description">
                        <?php echo esc_html__( 'JSON configuration of form fields. Example: [{"name": "name", "label": "Name", "type": "text", "required": true}]', 'mksddn-forms-handler' ); ?><br>
                        <strong><?php /* translators: Note about regex patterns in JSON */ echo esc_html__( 'Pattern note:', 'mksddn-forms-handler' ); ?></strong> <?php echo esc_html__( 'For regex patterns, use double backslashes in JSON (e.g., "pattern": "^\\\\+?\\\\d{7,15}$")', 'mksddn-forms-handler' ); ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Email Settings Tab -->
    <div id="mksddn-tab-email" class="mksddn-form-tab-content">
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label>
                        <input type="checkbox" name="send_to_email" id="send_to_email" value="1" <?php checked($send_to_email, '1'); ?> />
                        <?php echo esc_html__( 'Send to Email', 'mksddn-forms-handler' ); ?>
                    </label>
                </th>
                <td>
                    <p class="description"><?php echo esc_html__( 'Enable email notifications for this form', 'mksddn-forms-handler' ); ?></p>
                </td>
            </tr>
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
                    <p class="description"><?php echo esc_html__( 'Specified for debugging', 'mksddn-forms-handler' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="subject"><?php echo esc_html__( 'Email Subject', 'mksddn-forms-handler' ); ?></label></th>
                <td>
                    <input type="text" name="subject" id="subject" value="<?php echo esc_attr($subject); ?>" class="regular-text" />
                </td>
            </tr>
        </table>

        <h3><?php echo esc_html__( 'User Reply Email', 'mksddn-forms-handler' ); ?></h3>
        <table class="form-table mksddn-user-reply-settings">
            <tr>
                <th scope="row">
                    <label>
                        <input type="checkbox" name="send_user_reply" id="send_user_reply" value="1" <?php checked($send_user_reply, '1'); ?> />
                        <?php echo esc_html__( 'Send auto-reply to user', 'mksddn-forms-handler' ); ?>
                    </label>
                </th>
                <td>
                    <p class="description"><?php echo esc_html__( 'Send a confirmation email to the user who submitted the form', 'mksddn-forms-handler' ); ?></p>
                </td>
            </tr>
            <tr class="mksddn-user-reply-row" style="<?php echo $send_user_reply === '1' ? '' : 'display: none;'; ?>">
                <th scope="row"><label for="user_reply_email_field"><?php echo esc_html__( 'User email field', 'mksddn-forms-handler' ); ?></label></th>
                <td>
                    <select name="user_reply_email_field" id="user_reply_email_field" class="regular-text">
                        <option value=""><?php echo esc_html__( '— Select field —', 'mksddn-forms-handler' ); ?></option>
                        <?php foreach ($user_reply_email_fields as $mksddn_email_field) : ?>
                            <option value="<?php echo esc_attr($mksddn_email_field['name']); ?>" <?php selected($user_reply_email_field, $mksddn_email_field['name']); ?>>
                                <?php echo esc_html($mksddn_email_field['label'] . ' (' . $mksddn_email_field['name'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description mksddn-user-reply-no-email-fields" style="<?php echo empty($user_reply_email_fields) ? '' : 'display: none;'; ?>">
                        <?php echo esc_html__( 'No email fields found in Fields Configuration. Add a field with type "email".', 'mksddn-forms-handler' ); ?>
                    </p>
                </td>
            </tr>
            <tr class="mksddn-user-reply-row" style="<?php echo $send_user_reply === '1' ? '' : 'display: none;'; ?>">
                <th scope="row"><label for="user_reply_subject"><?php echo esc_html__( 'Reply email subject', 'mksddn-forms-handler' ); ?></label></th>
                <td>
                    <input type="text" name="user_reply_subject" id="user_reply_subject" value="<?php echo esc_attr($user_reply_subject); ?>" class="regular-text" />
                    <p class="description"><?php echo esc_html__( 'Supports placeholders: {form_title}, {field:name}, {datetime}, {page_url}', 'mksddn-forms-handler' ); ?></p>
                </td>
            </tr>
            <tr class="mksddn-user-reply-row" style="<?php echo $send_user_reply === '1' ? '' : 'display: none;'; ?>">
                <th scope="row"><?php echo esc_html__( 'Reply content type', 'mksddn-forms-handler' ); ?></th>
                <td>
                    <label>
                        <input type="radio" name="user_reply_type" value="text" <?php checked($user_reply_type, 'text'); ?> />
                        <?php echo esc_html__( 'Text template', 'mksddn-forms-handler' ); ?>
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="user_reply_type" value="html" <?php checked($user_reply_type, 'html'); ?> />
                        <?php echo esc_html__( 'HTML file', 'mksddn-forms-handler' ); ?>
                    </label>
                </td>
            </tr>
            <tr class="mksddn-user-reply-text-row mksddn-user-reply-row" style="<?php echo ($send_user_reply === '1' && $user_reply_type === 'text') ? '' : 'display: none;'; ?>">
                <th scope="row"><label for="user_reply_message"><?php echo esc_html__( 'Reply message template', 'mksddn-forms-handler' ); ?></label></th>
                <td>
                    <textarea name="user_reply_message" id="user_reply_message" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($user_reply_message); ?></textarea>
                    <p class="description">
                        <?php echo esc_html__( 'Supports placeholders:', 'mksddn-forms-handler' ); ?><br>
                        <code>{form_title}</code>, <code>{date}</code>, <code>{time}</code>, <code>{datetime}</code>, <code>{page_url}</code><br>
                        <code>{field:field_name}</code>, <code>{field_label:field_name}</code>
                    </p>
                </td>
            </tr>
            <tr class="mksddn-user-reply-html-row mksddn-user-reply-row" style="<?php echo ($send_user_reply === '1' && $user_reply_type === 'html') ? '' : 'display: none;'; ?>">
                <th scope="row"><label for="user_reply_html_file"><?php echo esc_html__( 'HTML template file', 'mksddn-forms-handler' ); ?></label></th>
                <td>
                    <?php if (!empty($user_reply_admin_notice)) : ?>
                        <?php
                        $mksddn_notice_class = 'notice-warning';
                        if ($user_reply_admin_notice['type'] === 'success') {
                            $mksddn_notice_class = 'notice-success';
                        } elseif ($user_reply_admin_notice['type'] === 'error') {
                            $mksddn_notice_class = 'notice-error';
                        }
                        ?>
                        <div class="notice <?php echo esc_attr($mksddn_notice_class); ?> inline mksddn-user-reply-html-notice">
                            <p><?php echo esc_html($user_reply_admin_notice['message']); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($user_reply_html_ready) : ?>
                        <div class="mksddn-user-reply-html-status mksddn-user-reply-html-status--ready">
                            <strong><?php echo esc_html__( 'Status:', 'mksddn-forms-handler' ); ?></strong>
                            <?php echo esc_html__( 'HTML template is saved and will be used for auto-reply emails.', 'mksddn-forms-handler' ); ?>
                            <br>
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: 1: file name, 2: file size in KB */
                                    __('Stored template: %1$s (%2$s KB)', 'mksddn-forms-handler'),
                                    $user_reply_html_template_filename ?: __('uploaded file', 'mksddn-forms-handler'),
                                    number_format(strlen($user_reply_html_template) / 1024, 1)
                                )
                            );
                            ?>
                        </div>
                    <?php elseif ($send_user_reply === '1' && $user_reply_type === 'html') : ?>
                        <div class="mksddn-user-reply-html-status mksddn-user-reply-html-status--missing">
                            <strong><?php echo esc_html__( 'Status:', 'mksddn-forms-handler' ); ?></strong>
                            <?php echo esc_html__( 'No HTML template stored. Upload a file and click Update to save.', 'mksddn-forms-handler' ); ?>
                        </div>
                    <?php endif; ?>

                    <input type="file" name="user_reply_html_file" id="user_reply_html_file" accept=".html,.htm" />
                    <?php if ($user_reply_html_configured) : ?>
                        <label style="display: block; margin-top: 8px;">
                            <input type="checkbox" name="remove_user_reply_html_template" value="1" />
                            <?php echo esc_html__( 'Remove current HTML template', 'mksddn-forms-handler' ); ?>
                        </label>
                    <?php endif; ?>
                    <p class="description">
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: maximum HTML template size in kilobytes */
                                __('Upload an HTML file with placeholders. Max size: %d KB.', 'mksddn-forms-handler'),
                                $user_reply_html_max_kb
                            )
                        );
                        ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Telegram Tab -->
    <div id="mksddn-tab-telegram" class="mksddn-form-tab-content">
        <table class="form-table">
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
                <th scope="row">
                    <label>
                        <input type="checkbox" name="use_custom_telegram_template" id="use_custom_telegram_template" value="1" <?php checked($use_custom_telegram_template, '1'); ?> />
                        <?php echo esc_html__( 'Use Custom Template', 'mksddn-forms-handler' ); ?>
                    </label>
                </th>
                <td>
                    <p class="description"><?php echo esc_html__( 'Enable custom template for Telegram notifications', 'mksddn-forms-handler' ); ?></p>
                </td>
            </tr>
            <tr class="mksddn-telegram-template-row" style="<?php echo $use_custom_telegram_template ? '' : 'display: none;'; ?>"<?php echo !empty($default_telegram_template) ? ' data-default-template="' . esc_attr($default_telegram_template) . '"' : ''; ?>>
                <th scope="row"><label for="telegram_template"><?php echo esc_html__( 'Telegram Template', 'mksddn-forms-handler' ); ?></label></th>
                <td>
                    <textarea name="telegram_template" id="telegram_template" rows="15" cols="50" class="large-text code"><?php echo esc_textarea($telegram_template); ?></textarea>
                    <p class="description">
                        <?php echo esc_html__( 'Custom template for Telegram notifications. Use placeholders:', 'mksddn-forms-handler' ); ?><br>
                        <strong><?php echo esc_html__( 'System placeholders:', 'mksddn-forms-handler' ); ?></strong><br>
                        <code>{form_title}</code> - <?php echo esc_html__( 'Form title', 'mksddn-forms-handler' ); ?><br>
                        <code>{date}</code> - <?php echo esc_html__( 'Date (d.m.Y)', 'mksddn-forms-handler' ); ?><br>
                        <code>{time}</code> - <?php echo esc_html__( 'Time (H:i:s)', 'mksddn-forms-handler' ); ?><br>
                        <code>{datetime}</code> - <?php echo esc_html__( 'Date and time (d.m.Y H:i:s)', 'mksddn-forms-handler' ); ?><br>
                        <code>{page_url}</code> - <?php echo esc_html__( 'Page URL', 'mksddn-forms-handler' ); ?><br>
                        <strong><?php echo esc_html__( 'Field placeholders:', 'mksddn-forms-handler' ); ?></strong><br>
                        <code>{field:field_name}</code> - <?php echo esc_html__( 'Field value (replace field_name with actual field name)', 'mksddn-forms-handler' ); ?><br>
                        <code>{field_label:field_name}</code> - <?php echo esc_html__( 'Field label (replace field_name with actual field name)', 'mksddn-forms-handler' ); ?><br>
                        <strong><?php echo esc_html__( 'Note:', 'mksddn-forms-handler' ); ?></strong> <?php echo esc_html__( 'You can use HTML tags supported by Telegram (b, i, u, s, code, pre, a).', 'mksddn-forms-handler' ); ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Google Sheets Tab -->
    <div id="mksddn-tab-sheets" class="mksddn-form-tab-content">
        <table class="form-table">
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
                    <p class="description"><?php echo esc_html__( 'Tab name as shown in Google Sheets (optional). If empty, data is appended to the first tab.', 'mksddn-forms-handler' ); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Admin Storage Tab -->
    <div id="mksddn-tab-admin" class="mksddn-form-tab-content">
        <table class="form-table">
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
    </div>

    <!-- Display Tab -->
    <div id="mksddn-tab-display" class="mksddn-form-tab-content">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="submit_button_text"><?php echo esc_html__( 'Submit Button Text', 'mksddn-forms-handler' ); ?></label></th>
                <td>
                    <input type="text" name="submit_button_text" id="submit_button_text" value="<?php echo esc_attr($submit_button_text ?? ''); ?>" class="regular-text" />
                    <p class="description"><?php echo esc_html__( 'Custom text for the submit button. Leave empty to use default "Send"', 'mksddn-forms-handler' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="custom_html_after_button"><?php echo esc_html__( 'Custom HTML/Text After Button', 'mksddn-forms-handler' ); ?></label></th>
                <td>
                    <textarea name="custom_html_after_button" id="custom_html_after_button" rows="5" cols="50" class="large-text code"><?php echo esc_textarea($custom_html_after_button ?? ''); ?></textarea>
                    <p class="description"><?php echo esc_html__( 'Custom HTML or text to display after the submit button (e.g., privacy policy notice)', 'mksddn-forms-handler' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="success_message_text"><?php echo esc_html__( 'Success Message Text', 'mksddn-forms-handler' ); ?></label></th>
                <td>
                    <input type="text" name="success_message_text" id="success_message_text" value="<?php echo esc_attr($success_message_text ?? ''); ?>" class="regular-text" />
                    <p class="description"><?php echo esc_html__( 'Custom text to display after successful form submission. Leave empty to use default message.', 'mksddn-forms-handler' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="form_custom_classes"><?php echo esc_html__( 'Custom CSS Classes', 'mksddn-forms-handler' ); ?></label></th>
                <td>
                    <input type="text" name="form_custom_classes" id="form_custom_classes" value="<?php echo esc_attr($form_custom_classes ?? ''); ?>" class="regular-text" />
                    <p class="description"><?php echo esc_html__( 'Additional CSS classes to add to the form element. Separate multiple classes with spaces.', 'mksddn-forms-handler' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="redirect_url"><?php echo esc_html__( 'Redirect URL After Submission', 'mksddn-forms-handler' ); ?></label></th>
                <td>
                    <input type="text" name="redirect_url" id="redirect_url" value="<?php echo esc_attr($redirect_url ?? ''); ?>" class="regular-text" />
                    <p class="description"><?php echo esc_html__( 'URL or relative path to redirect to after successful form submission (e.g., https://example.com/page or /thank-you). Leave empty to show success message without redirect.', 'mksddn-forms-handler' ); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Advanced Tab -->
    <div id="mksddn-tab-advanced" class="mksddn-form-tab-content">
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label>
                        <input type="checkbox" name="allow_any_fields" id="allow_any_fields" value="1" <?php checked($allow_any_fields, '1'); ?> />
                        <?php echo esc_html__( 'Accept any fields from frontend', 'mksddn-forms-handler' ); ?>
                    </label>
                </th>
                <td>
                    <p class="description">
                        <strong style="color: #d63638;">⚠️ <?php echo esc_html__( 'Warning:', 'mksddn-forms-handler' ); ?></strong>
                        <?php echo esc_html__( 'When enabled, the form will accept ANY field names from frontend submissions without validation against Fields Configuration. Use this for custom forms where you control field names in templates. All fields will still be sanitized but type validation will be skipped.', 'mksddn-forms-handler' ); ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>
</div> 