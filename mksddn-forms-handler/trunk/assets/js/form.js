(function($){
    'use strict';

    $(document).on('submit', '.wp-form', function(e){
        e.preventDefault();

        var $form = $(this);
        var $message = $form.siblings('.form-message');
        var $submitButton = $form.find('.submit-button');

        var i18n = mksddn_fh_form || {};
        $submitButton.prop('disabled', true).text(i18n.sending_text || 'Sending...');
        $message.hide();

        // Check if form has file inputs
        var hasFiles = $form.find('input[type="file"]').length > 0;
        var formData;
        var ajaxOptions = {
            url: $form.attr('action'),
            method: 'POST',
            success: function(response) {
                if (response && response.success) {
                    // Check if redirect URL is configured and validate it
                    if (i18n.redirect_url && i18n.redirect_url.trim() !== '') {
                        var redirectUrl = i18n.redirect_url.trim();
                        // Validate URL: only allow http:// or https:// protocols
                        if (/^https?:\/\//.test(redirectUrl)) {
                            // Redirect to specified URL
                            window.location.href = redirectUrl;
                            return;
                        } else {
                            // Log error but don't block success message
                            console.warn('Invalid redirect URL format:', redirectUrl);
                        }
                    }

                    // Use custom success message if available, otherwise use default
                    var message = i18n.success_message 
                        ? i18n.success_message 
                        : (response.data && response.data.message ? response.data.message : 'Thank you! Your message has been sent successfully.');

                    // Hide technical delivery information from users on success
                    // Only show user-friendly message

                    $message.removeClass('error').addClass('success').html(message).show();
                    $form[0].reset();
                } else {
                    var errorMessage = (response && response.data && response.data.message) ? response.data.message : (i18n.error_default || 'Error');

                    if (response && response.data && response.data.unauthorized_fields && response.data.unauthorized_fields.length > 0) {
                        errorMessage += '<br><br><strong>' + (i18n.unauthorized_fields_label || 'Unauthorized fields:') + '</strong> ' + response.data.unauthorized_fields.join(', ');
                        if (response.data.allowed_fields && response.data.allowed_fields.length > 0) {
                            errorMessage += '<br><strong>' + (i18n.allowed_fields_label || 'Allowed fields:') + '</strong> ' + response.data.allowed_fields.join(', ');
                        }
                    }

                    if (response && response.data && response.data.delivery_results) {
                        errorMessage += '<br><br><strong>' + (i18n.delivery_status_label || 'Delivery Status:') + '</strong><br>';
                        var delivery = response.data.delivery_results;

                        if (delivery.email && delivery.email.success) {
                            errorMessage += '✅ ' + (i18n.email_sent_successfully || 'Email: Sent successfully') + '<br>';
                        } else if (delivery.email) {
                            errorMessage += '❌ ' + (i18n.email_label || 'Email:') + ' ' + (delivery.email.error || (i18n.failed || 'Failed')) + '<br>';
                        }

                        if (delivery.telegram && delivery.telegram.enabled) {
                            if (delivery.telegram.success) {
                                errorMessage += '✅ ' + (i18n.telegram_sent_successfully || 'Telegram: Sent successfully') + '<br>';
                            } else {
                                errorMessage += '❌ ' + (i18n.telegram_label || 'Telegram:') + ' ' + (delivery.telegram.error || (i18n.failed || 'Failed')) + '<br>';
                            }
                        }

                        if (delivery.google_sheets && delivery.google_sheets.enabled) {
                            if (delivery.google_sheets.success) {
                                errorMessage += '✅ ' + (i18n.google_sheets_data_saved || 'Google Sheets: Data saved') + '<br>';
                            } else {
                                errorMessage += '❌ ' + (i18n.google_sheets_label || 'Google Sheets:') + ' ' + (delivery.google_sheets.error || (i18n.failed || 'Failed')) + '<br>';
                            }
                        }
                    }

                    $message.removeClass('success').addClass('error').html(errorMessage).show();
                }
            },
            error: function() {
                $message.removeClass('success').addClass('error').html(i18n.error_sending || 'An error occurred while sending the form').show();
            },
            complete: function() {
                $submitButton.prop('disabled', false).text(i18n.send_text || 'Send');
            }
        };

        // Prepare form data
        if (hasFiles) {
            // Use FormData for forms with files
            formData = new FormData($form[0]);
            ajaxOptions.data = formData;
            ajaxOptions.processData = false;
            ajaxOptions.contentType = false;
        } else {
            // Use serialize for forms without files
            ajaxOptions.data = $form.serialize();
        }

        $.ajax(ajaxOptions);
    });
})(jQuery);


