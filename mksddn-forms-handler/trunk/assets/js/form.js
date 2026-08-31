(function($){
    'use strict';

    function ensureLoaderStyles() {
        if (document.getElementById('mksddn-fh-loader-style')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'mksddn-fh-loader-style';
        style.textContent =
            '.mksddn-form .submit-button.is-loading{position:relative;min-width:44px;}' +
            '.mksddn-form .submit-button .mksddn-fh-loader{' +
            'display:inline-block;width:1em;height:1em;border:2px solid currentColor;' +
            'border-right-color:transparent;border-radius:50%;animation:mksddn-fh-spin .7s linear infinite;' +
            'vertical-align:middle;}' +
            '@keyframes mksddn-fh-spin{to{transform:rotate(360deg);}}';
        document.head.appendChild(style);
    }

    function getTurnstileWidgets($form) {
        return $form.find('.cf-turnstile, .mksddn-fh-turnstile');
    }

    function getTurnstileToken($form) {
        var token = '';
        $form.find('[name="cf-turnstile-response"], [name="mksddn_fh_turnstile_response"]').each(function() {
            var value = $.trim($(this).val() || '');
            if (value) {
                token = value;
            }
        });
        return token;
    }

    function resetTurnstile($form) {
        if (typeof window.turnstile === 'undefined' || typeof window.turnstile.reset !== 'function') {
            return;
        }
        getTurnstileWidgets($form).each(function() {
            try {
                window.turnstile.reset(this);
            } catch (err) {
                // Widget may not be mounted yet.
            }
        });
    }

    function extractErrorMessage(payload, fallback) {
        if (!payload) {
            return fallback;
        }
        if (typeof payload.data === 'string' && payload.data) {
            return payload.data;
        }
        if (payload.data && payload.data.message) {
            return payload.data.message;
        }
        if (payload.message) {
            return payload.message;
        }
        return fallback;
    }

    $(document).on('submit', '.mksddn-form', function(e){
        e.preventDefault();

        var $form = $(this);
        var $message = $form.siblings('.form-message');
        var $submitButton = $form.find('.submit-button');
        var originalButtonHtml = $submitButton.html();
        var originalButtonText = $.trim($submitButton.text());
        var i18n = mksddn_fh_form || {};

        if (getTurnstileWidgets($form).length && !getTurnstileToken($form)) {
            $message
                .removeClass('success')
                .addClass('error')
                .html(i18n.turnstile_required || 'Captcha verification is required.')
                .show();
            return;
        }

        ensureLoaderStyles();

        $submitButton
            .prop('disabled', true)
            .attr('aria-busy', 'true')
            .attr('aria-label', originalButtonText || 'Loading')
            .addClass('is-loading')
            .html('<span class="mksddn-fh-loader" aria-hidden="true"></span>');
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
                        
                        try {
                            // Parse URL (relative URLs will be resolved against current origin)
                            var url = new URL(redirectUrl, window.location.origin);
                            
                            // Allow only same origin redirects for security
                            if (url.origin === window.location.origin) {
                                window.location.href = url.href;
                                return;
                            } else {
                                console.warn('Redirect to external domain not allowed:', redirectUrl);
                            }
                        } catch(e) {
                            // If URL parsing fails, it might be a malformed URL
                            console.warn('Invalid redirect URL:', redirectUrl, e);
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
                    var errorMessage = extractErrorMessage(response, i18n.error_default || 'Error');

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
            error: function(xhr) {
                var fallback = i18n.error_sending || 'An error occurred while sending the form';
                var errorMessage = extractErrorMessage(xhr && xhr.responseJSON, fallback);
                $message.removeClass('success').addClass('error').html(errorMessage).show();
            },
            complete: function() {
                // Restore per-form button label from markup to keep custom text and locale.
                $submitButton
                    .prop('disabled', false)
                    .removeAttr('aria-busy')
                    .removeClass('is-loading')
                    .html(originalButtonHtml);
                resetTurnstile($form);
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


