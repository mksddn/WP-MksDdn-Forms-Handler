<?php
/**
 * @file: class-shortcodes.php
 * @description: Handles form shortcodes rendering
 * @dependencies: WordPress core, jQuery
 * @created: 2025-07-30
 */

namespace MksDdn\FormsHandler;

/**
 * Handles form shortcodes
 */
class Shortcodes {
    
    public function __construct() {
        add_shortcode('mksddn_fh_form', [$this, 'render_form_shortcode']);
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
            return '<p>' . esc_html__( 'Error: form ID or slug not specified', 'mksddn-forms-handler' ) . '</p>';
        }

        // Get form
        $form = get_page_by_path($form_id, OBJECT, 'mksddn_fh_forms');
        if (!$form) {
            $form = get_post($form_id);
        }

        if (!$form || $form->post_type !== 'mksddn_fh_forms') {
            return '<p>' . esc_html__( 'Form not found', 'mksddn-forms-handler' ) . '</p>';
        }

        $fields_config = get_post_meta($form->ID, '_fields_config', true);
        $fields = json_decode($fields_config, true) ?: [];
        $submit_button_text = get_post_meta($form->ID, '_submit_button_text', true);
        $custom_html_after_button = get_post_meta($form->ID, '_custom_html_after_button', true);
        $success_message_text = get_post_meta($form->ID, '_success_message_text', true);

        // Set default success message if empty
        if (empty($success_message_text)) {
            $success_message_text = __( 'Thank you! Your message has been sent successfully.', 'mksddn-forms-handler' );
        }

        ob_start();
        $has_file = false;
        if (is_array($fields)) {
            foreach ($fields as $f) {
                if (isset($f['type']) && $f['type'] === 'file') { $has_file = true; break; }
            }
        }
        ?>
        <div class="form-container" data-form-id="<?php echo esc_attr($form->post_name); ?>">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wp-form" <?php echo $has_file ? 'enctype="multipart/form-data"' : ''; ?>>
                <?php wp_nonce_field('submit_form_nonce', 'form_nonce'); ?>
                <input type="hidden" name="action" value="submit_form">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form->post_name); ?>">
                <input type="text" name="mksddn_fh_hp" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true" />
                
                <?php foreach ($fields as $field) : ?>
                    <?php 
                    $field_label = isset($field['label']) ? trim((string) $field['label']) : '';
                    $has_label = !empty($field_label);
                    $is_required = isset($field['required']) && $field['required'];
                    ?>
                    <div class="form-field">
                        <?php if ($has_label) : ?>
                            <label for="<?php echo esc_attr($field['name']); ?>">
                                <?php echo esc_html($field_label); ?>
                                <?php if ($is_required) : ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                        <?php endif; ?>
                        
                        <?php if ($field['type'] === 'textarea') : ?>
                            <textarea 
                                name="<?php echo esc_attr($field['name']); ?>" 
                                id="<?php echo esc_attr($field['name']); ?>"
                                <?php echo $is_required ? 'required' : ''; ?>
                                <?php if (!$has_label && $is_required) : ?>aria-label="<?php echo esc_attr(sprintf(/* translators: %s: field name */ __('%s (required)', 'mksddn-forms-handler'), ucfirst(str_replace(['_', '-'], ' ', $field['name'])))); ?>"<?php elseif (!$has_label) : ?>aria-label="<?php echo esc_attr(ucfirst(str_replace(['_', '-'], ' ', $field['name']))); ?>"<?php endif; ?>
                                rows="4"
                                <?php if (!empty($field['placeholder'])) : ?>placeholder="<?php echo esc_attr($field['placeholder']); ?>"<?php endif; ?>
                            ></textarea>
                        <?php elseif ($field['type'] === 'checkbox') : ?>
                            <?php if ($has_label) : ?>
                                <label for="<?php echo esc_attr($field['name']); ?>">
                                    <input 
                                        type="checkbox" 
                                        name="<?php echo esc_attr($field['name']); ?>" 
                                        id="<?php echo esc_attr($field['name']); ?>" 
                                        value="1"
                                        <?php echo $is_required ? 'required' : ''; ?>
                                    >
                                    <?php echo esc_html($field_label); ?>
                                    <?php if ($is_required) : ?>
                                        <span class="required">*</span>
                                    <?php endif; ?>
                                </label>
                            <?php else : ?>
                                <input 
                                    type="checkbox" 
                                    name="<?php echo esc_attr($field['name']); ?>" 
                                    id="<?php echo esc_attr($field['name']); ?>" 
                                    value="1"
                                    <?php echo $is_required ? 'required' : ''; ?>
                                    <?php if ($is_required) : ?>aria-label="<?php echo esc_attr(sprintf(/* translators: %s: field name */ __('%s (required)', 'mksddn-forms-handler'), ucfirst(str_replace(['_', '-'], ' ', $field['name'])))); ?>"<?php else : ?>aria-label="<?php echo esc_attr(ucfirst(str_replace(['_', '-'], ' ', $field['name']))); ?>"<?php endif; ?>
                                >
                            <?php endif; ?>
                        <?php elseif ($field['type'] === 'select') : ?>
                            <?php 
                            $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
                            $is_multiple = isset($field['multiple']) && ($field['multiple'] === '1' || $field['multiple'] === true);
                            $name_attr = $is_multiple ? $field['name'] . '[]' : $field['name'];
                            ?>
                            <select 
                                name="<?php echo esc_attr($name_attr); ?>" 
                                id="<?php echo esc_attr($field['name']); ?>"
                                <?php echo $is_multiple ? 'multiple' : ''; ?>
                                <?php echo $is_required ? 'required' : ''; ?>
                                <?php if (!$has_label && $is_required) : ?>aria-label="<?php echo esc_attr(sprintf(/* translators: %s: field name */ __('%s (required)', 'mksddn-forms-handler'), ucfirst(str_replace(['_', '-'], ' ', $field['name'])))); ?>"<?php elseif (!$has_label) : ?>aria-label="<?php echo esc_attr(ucfirst(str_replace(['_', '-'], ' ', $field['name']))); ?>"<?php endif; ?>
                            >
                                <?php foreach ($options as $opt) : 
                                    $opt_value = is_array($opt) ? ($opt['value'] ?? '') : (string) $opt;
                                    $opt_label = is_array($opt) ? ($opt['label'] ?? $opt_value) : (string) $opt;
                                    if ($opt_value === '') { continue; }
                                ?>
                                    <option value="<?php echo esc_attr($opt_value); ?>"><?php echo esc_html($opt_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($field['type'] === 'radio') : ?>
                            <?php 
                            $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
                            ?>
                            <?php if ($has_label) : ?>
                                <label id="<?php echo esc_attr($field['name']); ?>-label" class="radio-group-label">
                                    <?php echo esc_html($field_label); ?>
                                    <?php if ($is_required) : ?>
                                        <span class="required">*</span>
                                    <?php endif; ?>
                                </label>
                            <?php endif; ?>
                            <div class="radio-group" role="radiogroup" <?php if ($has_label) : ?>aria-labelledby="<?php echo esc_attr($field['name']); ?>-label"<?php elseif ($is_required) : ?>aria-label="<?php echo esc_attr(sprintf(/* translators: %s: field name */ __('%s (required)', 'mksddn-forms-handler'), ucfirst(str_replace(['_', '-'], ' ', $field['name'])))); ?>"<?php else : ?>aria-label="<?php echo esc_attr(ucfirst(str_replace(['_', '-'], ' ', $field['name']))); ?>"<?php endif; ?>>
                                <?php foreach ($options as $idx => $opt) : 
                                    $opt_value = is_array($opt) ? ($opt['value'] ?? '') : (string) $opt;
                                    $opt_label = is_array($opt) ? ($opt['label'] ?? $opt_value) : (string) $opt;
                                    if ($opt_value === '') { continue; }
                                    $input_id = $field['name'] . '_' . sanitize_title($opt_value);
                                ?>
                                    <label for="<?php echo esc_attr($input_id); ?>" class="radio-option">
                                        <input 
                                            type="radio" 
                                            name="<?php echo esc_attr($field['name']); ?>" 
                                            id="<?php echo esc_attr($input_id); ?>" 
                                            value="<?php echo esc_attr($opt_value); ?>"
                                            <?php echo (isset($field['required']) && $field['required']) ? 'required' : ''; ?>
                                        >
                                        <?php echo esc_html($opt_label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($field['type'] === 'file') : ?>
                            <?php 
                            $is_multiple = isset($field['multiple']) && ($field['multiple'] === '1' || $field['multiple'] === true);
                            $name_attr = $is_multiple ? $field['name'] . '[]' : $field['name'];
                            $accept = '';
                            if (!empty($field['allowed_extensions']) && is_array($field['allowed_extensions'])) {
                                $exts = array_filter(array_map('sanitize_text_field', $field['allowed_extensions']));
                                if ($exts !== []) { $accept = implode(',', array_map(fn($e) => '.' . ltrim($e, '.'), $exts)); }
                            }
                            ?>
                            <input 
                                type="file" 
                                name="<?php echo esc_attr($name_attr); ?>" 
                                id="<?php echo esc_attr($field['name']); ?>"
                                <?php echo $is_multiple ? 'multiple' : ''; ?>
                                <?php echo $is_required ? 'required' : ''; ?>
                                <?php if (!$has_label && $is_required) : ?>aria-label="<?php echo esc_attr(sprintf(/* translators: %s: field name */ __('%s (required)', 'mksddn-forms-handler'), ucfirst(str_replace(['_', '-'], ' ', $field['name'])))); ?>"<?php elseif (!$has_label) : ?>aria-label="<?php echo esc_attr(ucfirst(str_replace(['_', '-'], ' ', $field['name']))); ?>"<?php endif; ?>
                                <?php echo $accept ? 'accept="' . esc_attr($accept) . '"' : ''; ?>
                            >
                        <?php else : ?>
                            <?php 
                            $type = isset($field['type']) ? (string) $field['type'] : 'text';
                            $has_min = isset($field['min']) && $field['min'] !== '';
                            $has_max = isset($field['max']) && $field['max'] !== '';
                            $has_step = isset($field['step']) && $field['step'] !== '';
                            $pattern = '';
                            if (!empty($field['pattern'])) {
                                $pattern = (string) $field['pattern'];
                            } elseif ($type === 'tel') {
                                // Reasonable default pattern for phone numbers (E.164-like)
                                $pattern = '^\\+?\\d{7,15}$';
                            }
                            ?>
                            <input 
                                type="<?php echo esc_attr($type); ?>" 
                                name="<?php echo esc_attr($field['name']); ?>" 
                                id="<?php echo esc_attr($field['name']); ?>"
                                <?php echo $is_required ? 'required' : ''; ?>
                                <?php if (!$has_label && $is_required) : ?>aria-label="<?php echo esc_attr(sprintf(/* translators: %s: field name */ __('%s (required)', 'mksddn-forms-handler'), ucfirst(str_replace(['_', '-'], ' ', $field['name'])))); ?>"<?php elseif (!$has_label) : ?>aria-label="<?php echo esc_attr(ucfirst(str_replace(['_', '-'], ' ', $field['name']))); ?>"<?php endif; ?>
                                <?php if (!empty($field['placeholder'])) : ?>placeholder="<?php echo esc_attr($field['placeholder']); ?>"<?php endif; ?>
                                <?php if ($has_min) : ?>min="<?php echo esc_attr($field['min']); ?>"<?php endif; ?>
                                <?php if ($has_max) : ?>max="<?php echo esc_attr($field['max']); ?>"<?php endif; ?>
                                <?php if ($has_step) : ?>step="<?php echo esc_attr($field['step']); ?>"<?php endif; ?>
                                <?php if ($pattern !== '') : ?>pattern="<?php echo esc_attr($pattern); ?>"<?php endif; ?>
                                <?php if ($type === 'tel') : ?>inputmode="tel"<?php endif; ?>
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="form-submit">
                    <button type="submit" class="submit-button"><?php echo esc_html($submit_button_text ?: __( 'Send', 'mksddn-forms-handler' )); ?></button>
                </div>
                <?php if (!empty($custom_html_after_button)) : ?>
                    <div class="form-custom-html">
                        <?php echo wp_kses_post($custom_html_after_button); ?>
                    </div>
                <?php endif; ?>
            </form>
            
            <div class="form-message" style="display: none;"></div>
        </div>
        
        <?php
        wp_enqueue_script('mksddn-fh-form');
        wp_localize_script(
            'mksddn-fh-form',
            'mksddn_fh_form',
            [
                'sending_text' => __( 'Sending...', 'mksddn-forms-handler' ),
                'send_text'    => __( 'Send', 'mksddn-forms-handler' ),
                'success_message' => $success_message_text,
            ]
        );
        ?>
        <?php
        return ob_get_clean();
    }
} 