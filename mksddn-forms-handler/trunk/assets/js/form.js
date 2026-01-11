(function($){
    'use strict';

    $(document).on('submit', '.wp-form', function(e){
        e.preventDefault();

        var $form = $(this);
        var $message = $form.siblings('.form-message');
        var $submitButton = $form.find('.submit-button');

        $submitButton.prop('disabled', true).text(mksddn_fh_form && mksddn_fh_form.sending_text ? mksddn_fh_form.sending_text : 'Sending...');
        $message.hide();

        // Check if form has file inputs
        var hasFiles = $form.find('input[type="file"]').length > 0;
        var formData;
        var ajaxOptions = {
            url: $form.attr('action'),
            method: 'POST',
            success: function(response) {
                if (response && response.success) {
                    // Use custom success message if available, otherwise use default
                    var message = (mksddn_fh_form && mksddn_fh_form.success_message) 
                        ? mksddn_fh_form.success_message 
                        : (response.data && response.data.message ? response.data.message : 'Thank you! Your message has been sent successfully.');

                    // Hide technical delivery information from users on success
                    // Only show user-friendly message

                    $message.removeClass('error').addClass('success').html(message).show();
                    $form[0].reset();
                } else {
                    var errorMessage = (response && response.data && response.data.message) ? response.data.message : 'Error';

                    if (response && response.data && response.data.unauthorized_fields && response.data.unauthorized_fields.length > 0) {
                        errorMessage += '<br><br><strong>Unauthorized fields:</strong> ' + response.data.unauthorized_fields.join(', ');
                        if (response.data.allowed_fields && response.data.allowed_fields.length > 0) {
                            errorMessage += '<br><strong>Allowed fields:</strong> ' + response.data.allowed_fields.join(', ');
                        }
                    }

                    if (response && response.data && response.data.delivery_results) {
                        errorMessage += '<br><br><strong>Delivery Status:</strong><br>';
                        var delivery = response.data.delivery_results;

                        if (delivery.email && delivery.email.success) {
                            errorMessage += '✅ Email: Sent successfully<br>';
                        } else if (delivery.email) {
                            errorMessage += '❌ Email: ' + (delivery.email.error || 'Failed') + '<br>';
                        }

                        if (delivery.telegram && delivery.telegram.enabled) {
                            if (delivery.telegram.success) {
                                errorMessage += '✅ Telegram: Sent successfully<br>';
                            } else {
                                errorMessage += '❌ Telegram: ' + (delivery.telegram.error || 'Failed') + '<br>';
                            }
                        }

                        if (delivery.google_sheets && delivery.google_sheets.enabled) {
                            if (delivery.google_sheets.success) {
                                errorMessage += '✅ Google Sheets: Data saved<br>';
                            } else {
                                errorMessage += '❌ Google Sheets: ' + (delivery.google_sheets.error || 'Failed') + '<br>';
                            }
                        }
                    }

                    $message.removeClass('success').addClass('error').html(errorMessage).show();
                }
            },
            error: function() {
                $message.removeClass('success').addClass('error').html('An error occurred while sending the form').show();
            },
            complete: function() {
                $submitButton.prop('disabled', false).text(mksddn_fh_form && mksddn_fh_form.send_text ? mksddn_fh_form.send_text : 'Send');
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


