<?php
namespace FormsHandler;

/**
 * Handles form shortcodes
 */
class Shortcodes {
    
    public function __construct() {
        add_shortcode('form', [$this, 'render_form_shortcode']);
    }
    
    /**
     * Render form shortcode
     */
    public function render_form_shortcode($atts): string|false {
        $atts = shortcode_atts(
            [
                'id'   => '',
                'slug' => '',
            ],
            $atts
        );

        $form_id = $atts['id'] ?: $atts['slug'];
        if (!$form_id) {
            return '<p>Error: form ID or slug not specified</p>';
        }

        // Get form
        $form = get_page_by_path($form_id, OBJECT, 'forms');
        if (!$form) {
            $form = get_post($form_id);
        }

        if (!$form || $form->post_type !== 'forms') {
            return '<p>Form not found</p>';
        }

        $fields_config = get_post_meta($form->ID, '_fields_config', true);
        $fields = json_decode($fields_config, true) ?: [];

        ob_start();
        ?>
        <div class="form-container" data-form-id="<?php echo esc_attr($form->post_name); ?>">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wp-form">
                <?php wp_nonce_field('submit_form_nonce', 'form_nonce'); ?>
                <input type="hidden" name="action" value="submit_form">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form->post_name); ?>">
                
                <?php foreach ($fields as $field) : ?>
                    <div class="form-field">
                        <label for="<?php echo esc_attr($field['name']); ?>">
                            <?php echo esc_html($field['label']); ?>
                            <?php if (isset($field['required']) && $field['required']) : ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php if ($field['type'] === 'textarea') : ?>
                            <textarea 
                                name="<?php echo esc_attr($field['name']); ?>" 
                                id="<?php echo esc_attr($field['name']); ?>"
                                <?php echo (isset($field['required']) && $field['required']) ? 'required' : ''; ?>
                                rows="4"
                            ></textarea>
                        <?php elseif ($field['type'] === 'checkbox') : ?>
                            <input 
                                type="checkbox" 
                                name="<?php echo esc_attr($field['name']); ?>" 
                                id="<?php echo esc_attr($field['name']); ?>" 
                                value="1"
                                <?php echo (isset($field['required']) && $field['required']) ? 'required' : ''; ?>
                            >
                        <?php else : ?>
                            <input 
                                type="<?php echo esc_attr($field['type']); ?>" 
                                name="<?php echo esc_attr($field['name']); ?>" 
                                id="<?php echo esc_attr($field['name']); ?>"
                                <?php echo (isset($field['required']) && $field['required']) ? 'required' : ''; ?>
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="form-submit">
                    <button type="submit" class="submit-button">Send</button>
                </div>
            </form>
            
            <div class="form-message" style="display: none;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.wp-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $message = $form.siblings('.form-message');
                var $submitButton = $form.find('.submit-button');
                
                $submitButton.prop('disabled', true).text('Sending...');
                $message.hide();
                
                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        if (response.success) {
                            var message = response.data.message;
                            
                            // Add delivery information
                            if (response.data.delivery_results) {
                                var delivery = response.data.delivery_results;
                                message += '<br><br><strong>Delivery Status:</strong><br>';
                                
                                // Email
                                if (delivery.email.success) {
                                    message += '✅ Email: Sent successfully<br>';
                                } else {
                                    message += '❌ Email: ' + (delivery.email.error || 'Failed') + '<br>';
                                }
                                
                                // Telegram
                                if (delivery.telegram.enabled) {
                                    if (delivery.telegram.success) {
                                        message += '✅ Telegram: Sent successfully<br>';
                                    } else {
                                        message += '❌ Telegram: ' + (delivery.telegram.error || 'Failed') + '<br>';
                                    }
                                }
                                
                                // Google Sheets
                                if (delivery.google_sheets.enabled) {
                                    if (delivery.google_sheets.success) {
                                        message += '✅ Google Sheets: Data saved<br>';
                                    } else {
                                        message += '❌ Google Sheets: ' + (delivery.google_sheets.error || 'Failed') + '<br>';
                                    }
                                }
                                
                                // Admin Storage
                                if (delivery.admin_storage.enabled) {
                                    if (delivery.admin_storage.success) {
                                        message += '✅ Admin Panel: Submission saved<br>';
                                    } else {
                                        message += '❌ Admin Panel: ' + (delivery.admin_storage.error || 'Failed') + '<br>';
                                    }
                                }
                            }
                            
                            $message.removeClass('error').addClass('success').html(message).show();
                            $form[0].reset();
                        } else {
                            var errorMessage = response.data.message;
                            
                            // Add unauthorized fields information
                            if (response.data.unauthorized_fields && response.data.unauthorized_fields.length > 0) {
                                errorMessage += '<br><br><strong>Unauthorized fields:</strong> ' + response.data.unauthorized_fields.join(', ');
                                if (response.data.allowed_fields && response.data.allowed_fields.length > 0) {
                                    errorMessage += '<br><strong>Allowed fields:</strong> ' + response.data.allowed_fields.join(', ');
                                }
                            }
                            
                            // Add delivery results for send errors
                            if (response.data.delivery_results) {
                                errorMessage += '<br><br><strong>Delivery Status:</strong><br>';
                                var delivery = response.data.delivery_results;
                                
                                if (delivery.email.success) {
                                    errorMessage += '✅ Email: Sent successfully<br>';
                                } else {
                                    errorMessage += '❌ Email: ' + (delivery.email.error || 'Failed') + '<br>';
                                }
                                
                                if (delivery.telegram.enabled) {
                                    if (delivery.telegram.success) {
                                        errorMessage += '✅ Telegram: Sent successfully<br>';
                                    } else {
                                        errorMessage += '❌ Telegram: ' + (delivery.telegram.error || 'Failed') + '<br>';
                                    }
                                }
                                
                                if (delivery.google_sheets.enabled) {
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
                        $submitButton.prop('disabled', false).text('Send');
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
} 