<?php
/**
 * @file: class-security.php
 * @description: Handles security restrictions for form submissions
 * @dependencies: WordPress core
 * @created: 2025-07-30
 */

namespace MksDdn\FormsHandler;

/**
 * Handles security restrictions for submissions
 */
class Security {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'hide_add_new_submission_button'], 999);
        add_filter('user_has_cap', [$this, 'block_submission_creation_cap'], 10, 4);
        add_filter('post_row_actions', [$this, 'hide_add_new_submission_row_action'], 10, 2);
        add_action('admin_init', [$this, 'disable_submission_editing']);
        add_filter('rest_pre_insert_mksddn_fh_submits', [$this, 'disable_submission_creation_via_rest'], 10, 2);
        add_action('admin_notices', [$this, 'submission_creation_blocked_notice']);
    }
    
    /**
     * Hide "Add New" button for submissions
     */
    public function hide_add_new_submission_button(): void {
        remove_submenu_page('edit.php?post_type=mksddn_fh_submits', 'post-new.php?post_type=mksddn_fh_submits');
    }
    
    /**
     * Block submission creation via capabilities
     */
    public function block_submission_creation_cap(array $allcaps, $caps, $args, $user): array {
        if (isset($args[0]) && $args[0] === 'create_posts' && isset($args[2]) && $args[2] === 'mksddn_fh_submits') {
            $allcaps['create_posts'] = false;
        }

        return $allcaps;
    }
    
    /**
     * Hide "Add New" button from submissions list
     */
    public function hide_add_new_submission_row_action(array $actions, $post): array {
        if ($post && $post->post_type === 'mksddn_fh_submits') {
            unset($actions['inline hide-if-no-js']);
            unset($actions['edit']);
            unset($actions['trash']);
            // Keep only delete
        }

        return $actions;
    }
    
    /**
     * Disable submission editing
     */
    public function disable_submission_editing(): void {
        global $pagenow, $post_type, $post;

        // Check if we're on submission edit page
        if ($pagenow === 'post.php' && $post_type === 'mksddn_fh_submits') {
            // Redirect to submissions list when trying to edit
            wp_safe_redirect( esc_url_raw( admin_url('edit.php?post_type=mksddn_fh_submits&message=1') ) );
            exit;
        }

        // Also block new submission creation
        if ($pagenow === 'post-new.php' && $post_type === 'mksddn_fh_submits') {
            wp_safe_redirect( esc_url_raw( admin_url('edit.php?post_type=mksddn_fh_submits&message=2') ) );
            exit;
        }
    }
    
    /**
     * Disable submission creation via REST API
     */
    public function disable_submission_creation_via_rest($prepared, $request) {
        return new \WP_Error('rest_forbidden', __( 'Creating submissions manually is not allowed', 'mksddn-forms-handler' ), ['status' => 403]);
    }
    
    /**
     * Add notifications about submission creation blocking
     */
    public function submission_creation_blocked_notice(): void {
        global $pagenow, $post_type;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($post_type === 'mksddn_fh_submits' && isset($_GET['message'])) {
            // phpcs:disable WordPress.Security.NonceVerification.Recommended
            $message = '';
            $msg_code = sanitize_text_field( wp_unslash($_GET['message']) );
            switch ($msg_code) {
                case '1':
                    $message = __( 'Editing submissions is not allowed. Submissions can only be created automatically when forms are submitted.', 'mksddn-forms-handler' );
                    break;
                case '2':
                    $message = __( 'Creating submissions manually is not allowed. Submissions are created automatically when forms are submitted.', 'mksddn-forms-handler' );
                    break;
            }

            if ($message !== '' && $message !== '0') {
                echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
        }
    }
} 