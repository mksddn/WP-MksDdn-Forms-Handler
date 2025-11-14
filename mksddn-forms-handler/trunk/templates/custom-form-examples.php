<?php
/**
 * @file: custom-form-examples.php
 * @description: Examples of custom form integration in theme templates
 * @created: 2025-10-11
 * 
 * This file contains examples of how to integrate custom forms in theme templates.
 * Copy and adapt these examples to your theme files.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

?>

<!-- 
====================================================================================
EXAMPLE 1: Basic Custom Form (POST submission)
====================================================================================
This is the simplest way to integrate a custom form in your theme.
The form will submit via POST and reload the page with a success message.
-->

<form method="post" action="<?php echo esc_url(mksddn_fh_get_form_action()); ?>" class="custom-contact-form">
    <?php mksddn_fh_form_fields('contact-form'); ?>
    
    <div class="form-group">
        <label for="name">Name *</label>
        <input type="text" name="name" id="name" required>
    </div>
    
    <div class="form-group">
        <label for="email">Email *</label>
        <input type="email" name="email" id="email" required>
    </div>
    
    <div class="form-group">
        <label for="phone">Phone</label>
        <input type="tel" name="phone" id="phone">
    </div>
    
    <div class="form-group">
        <label for="message">Message *</label>
        <textarea name="message" id="message" rows="5" required></textarea>
    </div>
    
    <button type="submit">Send Message</button>
</form>

<!-- 
====================================================================================
EXAMPLE 2: Form with File Upload
====================================================================================
If your form configuration includes file upload fields, add enctype attribute.
-->

<?php 
$mksddn_fh_form_slug = 'contact-form';
$mksddn_fh_has_files = mksddn_fh_form_has_files($mksddn_fh_form_slug);
?>

<form method="post" 
      action="<?php echo esc_url(mksddn_fh_get_form_action()); ?>" 
      <?php echo $mksddn_fh_has_files ? 'enctype="multipart/form-data"' : ''; ?>>
    
    <?php mksddn_fh_form_fields($mksddn_fh_form_slug); ?>
    
    <div class="form-group">
        <label for="name">Name *</label>
        <input type="text" name="name" id="name" required>
    </div>
    
    <div class="form-group">
        <label for="email">Email *</label>
        <input type="email" name="email" id="email" required>
    </div>
    
    <div class="form-group">
        <label for="attachment">Attachment</label>
        <input type="file" name="attachment" id="attachment">
    </div>
    
    <div class="form-group">
        <label for="message">Message *</label>
        <textarea name="message" id="message" rows="5" required></textarea>
    </div>
    
    <button type="submit">Send Message</button>
</form>

<!-- 
====================================================================================
EXAMPLE 3: Dynamic Form Rendering Based on Configuration
====================================================================================
This example reads field configuration from the form and renders fields dynamically.
Useful when you want to maintain form structure through WordPress admin.
-->

<?php 
$mksddn_fh_form_config = mksddn_fh_get_form_config('contact-form');

if ($mksddn_fh_form_config): 
    $mksddn_fh_has_files = mksddn_fh_form_has_files($mksddn_fh_form_config['slug']);
?>
    <form method="post" 
          action="<?php echo esc_url(mksddn_fh_get_form_action()); ?>"
          <?php echo $mksddn_fh_has_files ? 'enctype="multipart/form-data"' : ''; ?>>
        
        <?php mksddn_fh_form_fields($mksddn_fh_form_config['slug']); ?>
        
        <?php foreach ($mksddn_fh_form_config['fields'] as $mksddn_fh_field): ?>
            <div class="form-group">
                <label for="<?php echo esc_attr($mksddn_fh_field['name']); ?>">
                    <?php echo esc_html($mksddn_fh_field['label']); ?>
                    <?php if (!empty($mksddn_fh_field['required']) && $mksddn_fh_field['required']): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
                
                <?php if ($mksddn_fh_field['type'] === 'textarea'): ?>
                    <textarea 
                        name="<?php echo esc_attr($mksddn_fh_field['name']); ?>" 
                        id="<?php echo esc_attr($mksddn_fh_field['name']); ?>"
                        <?php echo !empty($mksddn_fh_field['required']) && $mksddn_fh_field['required'] ? 'required' : ''; ?>
                    ></textarea>
                
                <?php elseif ($mksddn_fh_field['type'] === 'select'): ?>
                    <select 
                        name="<?php echo esc_attr($mksddn_fh_field['name']); ?>" 
                        id="<?php echo esc_attr($mksddn_fh_field['name']); ?>"
                        <?php echo !empty($mksddn_fh_field['required']) && $mksddn_fh_field['required'] ? 'required' : ''; ?>
                    >
                        <?php if (!empty($mksddn_fh_field['options']) && is_array($mksddn_fh_field['options'])): ?>
                            <?php foreach ($mksddn_fh_field['options'] as $mksddn_fh_option): ?>
                                <?php 
                                $mksddn_fh_value = is_array($mksddn_fh_option) ? $mksddn_fh_option['value'] : $mksddn_fh_option;
                                $mksddn_fh_label = is_array($mksddn_fh_option) ? $mksddn_fh_option['label'] : $mksddn_fh_option;
                                ?>
                                <option value="<?php echo esc_attr($mksddn_fh_value); ?>">
                                    <?php echo esc_html($mksddn_fh_label); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                
                <?php else: ?>
                    <input 
                        type="<?php echo esc_attr($mksddn_fh_field['type']); ?>" 
                        name="<?php echo esc_attr($mksddn_fh_field['name']); ?>" 
                        id="<?php echo esc_attr($mksddn_fh_field['name']); ?>"
                        <?php echo !empty($mksddn_fh_field['required']) && $mksddn_fh_field['required'] ? 'required' : ''; ?>
                        <?php if (!empty($mksddn_fh_field['placeholder'])): ?>
                            placeholder="<?php echo esc_attr($mksddn_fh_field['placeholder']); ?>"
                        <?php endif; ?>
                    >
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <button type="submit">Send</button>
    </form>
<?php else: ?>
    <p>Form not found.</p>
<?php endif; ?>

<!-- 
====================================================================================
EXAMPLE 4: AJAX Form Submission with REST API
====================================================================================
This example uses JavaScript to submit the form via REST API without page reload.
-->

<div class="ajax-form-container">
    <form id="custom-ajax-form" class="ajax-contact-form">
        <div class="form-group">
            <label for="ajax_name">Name *</label>
            <input type="text" name="name" id="ajax_name" required>
        </div>
        
        <div class="form-group">
            <label for="ajax_email">Email *</label>
            <input type="email" name="email" id="ajax_email" required>
        </div>
        
        <div class="form-group">
            <label for="ajax_message">Message *</label>
            <textarea name="message" id="ajax_message" rows="5" required></textarea>
        </div>
        
        <!-- Honeypot field -->
        <input type="text" name="mksddn_fh_hp" value="" style="display:none" tabindex="-1" autocomplete="off">
        
        <button type="submit" id="submit-btn">Send Message</button>
        <div class="form-message" style="display:none;"></div>
    </form>
</div>

<script>
(function() {
    const form = document.getElementById('custom-ajax-form');
    const submitBtn = document.getElementById('submit-btn');
    const messageDiv = form.querySelector('.form-message');
    const endpoint = '<?php echo esc_url(mksddn_fh_get_rest_endpoint('contact-form')); ?>';
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        messageDiv.style.display = 'none';
        
        // Prepare form data
        const formData = new FormData(form);
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                messageDiv.className = 'form-message success';
                messageDiv.textContent = result.message || 'Form submitted successfully!';
                form.reset();
            } else {
                messageDiv.className = 'form-message error';
                messageDiv.textContent = result.message || 'An error occurred. Please try again.';
            }
        } catch (error) {
            messageDiv.className = 'form-message error';
            messageDiv.textContent = 'Network error. Please try again.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Message';
            messageDiv.style.display = 'block';
        }
    });
})();
</script>

<!-- 
====================================================================================
EXAMPLE 5: AJAX Form with File Upload
====================================================================================
When uploading files via AJAX, use multipart/form-data instead of JSON.
-->

<div class="ajax-form-with-files">
    <form id="ajax-upload-form">
        <div class="form-group">
            <label for="file_name">Name *</label>
            <input type="text" name="name" id="file_name" required>
        </div>
        
        <div class="form-group">
            <label for="file_email">Email *</label>
            <input type="email" name="email" id="file_email" required>
        </div>
        
        <div class="form-group">
            <label for="file_attachment">Attachment</label>
            <input type="file" name="attachment" id="file_attachment">
        </div>
        
        <div class="form-group">
            <label for="file_message">Message *</label>
            <textarea name="message" id="file_message" rows="5" required></textarea>
        </div>
        
        <!-- Honeypot field -->
        <input type="text" name="mksddn_fh_hp" value="" style="display:none" tabindex="-1" autocomplete="off">
        
        <button type="submit" id="upload-submit-btn">Send with Attachment</button>
        <div class="form-message" style="display:none;"></div>
    </form>
</div>

<script>
(function() {
    const form = document.getElementById('ajax-upload-form');
    const submitBtn = document.getElementById('upload-submit-btn');
    const messageDiv = form.querySelector('.form-message');
    const endpoint = '<?php echo esc_url(mksddn_fh_get_rest_endpoint('contact-form')); ?>';
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'Uploading...';
        messageDiv.style.display = 'none';
        
        // Use FormData for file uploads (multipart/form-data)
        const formData = new FormData(form);
        
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData // Don't set Content-Type, browser will set it with boundary
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                messageDiv.className = 'form-message success';
                messageDiv.textContent = result.message || 'Form submitted successfully!';
                form.reset();
            } else {
                messageDiv.className = 'form-message error';
                messageDiv.textContent = result.message || 'An error occurred. Please try again.';
            }
        } catch (error) {
            messageDiv.className = 'form-message error';
            messageDiv.textContent = 'Network error. Please try again.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send with Attachment';
            messageDiv.style.display = 'block';
        }
    });
})();
</script>

<!-- 
====================================================================================
NOTES:
====================================================================================
1. Field names in your custom form MUST match field names in form configuration
2. All forms need honeypot field (mksddn_fh_hp) for spam protection
3. For POST forms, nonce and hidden fields are added via mksddn_fh_form_fields()
4. For AJAX forms, only honeypot field is required (no nonce needed for REST API)
5. Make sure form slug matches the slug in WordPress admin (e.g., 'contact-form')
6. File uploads via AJAX must use FormData, not JSON
7. The plugin will validate all fields according to configuration in admin
-->

