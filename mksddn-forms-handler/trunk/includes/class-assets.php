<?php
/**
 * @file: class-assets.php
 * @description: Registers and enqueues plugin assets for admin and frontend
 * @dependencies: WordPress core
 * @created: 2025-08-25
 */

namespace MksDdn\FormsHandler;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages asset registration and conditional enqueues
 */
class Assets {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_frontend_assets' ] );
    }

    /**
     * Enqueue admin styles and scripts only on plugin-related screens
     */
    public function enqueue_admin_assets(): void {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        // Always register
        wp_register_style(
            'mksddn-fh-admin',
            MKSDDN_FORMS_HANDLER_PLUGIN_URL . 'assets/css/admin.css',
            [],
            MKSDDN_FORMS_HANDLER_VERSION
        );

        wp_register_script(
            'mksddn-fh-admin',
            MKSDDN_FORMS_HANDLER_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            MKSDDN_FORMS_HANDLER_VERSION,
            true
        );

        // Determine if current admin screen is related to this plugin
        $should_enqueue = false;
        $screen_id = $screen ? $screen->id : '';
        $post_type = $screen && isset( $screen->post_type ) ? $screen->post_type : '';

        if ( in_array( $post_type, [ 'mksddn_fh_forms', 'mksddn_fh_submits' ], true ) ) {
            $should_enqueue = true;
        }

        // Settings page: options-general.php?page=google-sheets-settings
        if ( strpos( $screen_id, 'settings_page_google-sheets-settings' ) !== false ) {
            $should_enqueue = true;
        }

        if ( $should_enqueue ) {
            wp_enqueue_style( 'mksddn-fh-admin' );
            wp_enqueue_script( 'mksddn-fh-admin' );

            // Localize data for admin JS where needed (e.g., AJAX nonces)
            wp_localize_script(
                'mksddn-fh-admin',
                'mksddn_fh_admin',
                [
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce_test_sheets' => wp_create_nonce( 'test_sheets_nonce' ),
                    'export_by_date_text' => __( 'Export by Date', 'mksddn-forms-handler' ),
                    'confirm_remove_field' => __( 'Are you sure you want to remove this field?', 'mksddn-forms-handler' ),
                    'error_generating_preview' => __( 'Error generating preview:', 'mksddn-forms-handler' ),
                    'error_generating_preview_retry' => __( 'Error generating preview. Please try again.', 'mksddn-forms-handler' ),
                    'field_required' => __( 'This field is required.', 'mksddn-forms-handler' ),
                    'enter_valid_email' => __( 'Please enter a valid email address.', 'mksddn-forms-handler' ),
                ]
            );
        }
    }

    /**
     * Register (but do not enqueue) frontend assets. Shortcode will enqueue on demand.
     */
    public function register_frontend_assets(): void {
        wp_register_script(
            'mksddn-fh-form',
            MKSDDN_FORMS_HANDLER_PLUGIN_URL . 'assets/js/form.js',
            [ 'jquery' ],
            MKSDDN_FORMS_HANDLER_VERSION,
            true
        );
    }
}


