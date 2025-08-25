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
                    <button type="submit" class="submit-button"><?php echo esc_html__( 'Send', 'mksddn-forms-handler' ); ?></button>
                </div>
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
            ]
        );
        ?>
        <?php
        return ob_get_clean();
    }
} 