<?php
/**
 * @file: class-post-types.php
 * @description: Handles custom post types registration for forms and submissions
 * @dependencies: WordPress core
 * @created: 2025-07-30
 */

namespace MksDdn\FormsHandler;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles custom post types registration
 */
class PostTypes {
    
    public function __construct() {
        add_action('init', [$this, 'register_post_types']);
    }
    
    /**
     * Register custom post types
     */
    public function register_post_types(): void {
        $this->register_forms_post_type();
        $this->register_submissions_post_type();
    }
    
    /**
     * Register forms post type
     */
    private function register_forms_post_type(): void {
        register_post_type(
            'mksddn_fh_forms',
            [
                'labels'              => [
                    'name'               => __( 'Forms', 'mksddn-forms-handler' ),
                    'singular_name'      => __( 'Form', 'mksddn-forms-handler' ),
                    'menu_name'          => __( 'Forms', 'mksddn-forms-handler' ),
                    'add_new'            => __( 'Add Form', 'mksddn-forms-handler' ),
                    'add_new_item'       => __( 'Add New Form', 'mksddn-forms-handler' ),
                    'edit_item'          => __( 'Edit Form', 'mksddn-forms-handler' ),
                    'new_item'           => __( 'New Form', 'mksddn-forms-handler' ),
                    'view_item'          => __( 'View Form', 'mksddn-forms-handler' ),
                    'search_items'       => __( 'Search Forms', 'mksddn-forms-handler' ),
                    'not_found'          => __( 'No forms found', 'mksddn-forms-handler' ),
                    'not_found_in_trash' => __( 'No forms found in trash', 'mksddn-forms-handler' ),
                ],
                'public'              => true,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'show_in_rest'        => true,
                'rest_base'           => 'mksddn_fh_forms',
                'capability_type'     => 'post',
                'hierarchical'        => false,
                'rewrite'             => ['slug' => 'mksddn_fh_forms'],
                'supports'            => ['title', 'custom-fields'],
                'menu_icon'           => 'dashicons-feedback',
                'show_in_admin_bar'   => true,
                'can_export'          => true,
                'has_archive'         => false,
                'exclude_from_search' => true,
                'publicly_queryable'  => true,
                'capabilities'        => [
                    'create_posts'       => 'manage_options',
                    'edit_post'          => 'manage_options',
                    'read_post'          => 'read',
                    'delete_post'        => 'manage_options',
                    'edit_posts'         => 'manage_options',
                    'edit_others_posts'  => 'manage_options',
                    'publish_posts'      => 'manage_options',
                    'read_private_posts' => 'read',
                    'delete_posts'       => 'manage_options',
                ],
            ]
        );
    }
    
    /**
     * Register form submissions post type
     */
    private function register_submissions_post_type(): void {
        register_post_type(
            'mksddn_fh_submits',
            [
                'labels'              => [
                    'name'               => __( 'Form Submissions', 'mksddn-forms-handler' ),
                    'singular_name'      => __( 'Submission', 'mksddn-forms-handler' ),
                    'menu_name'          => __( 'Submissions', 'mksddn-forms-handler' ),
                    'add_new'            => __( 'Add Submission', 'mksddn-forms-handler' ),
                    'add_new_item'       => __( 'Add New Submission', 'mksddn-forms-handler' ),
                    'edit_item'          => __( 'Edit Submission', 'mksddn-forms-handler' ),
                    'new_item'           => __( 'New Submission', 'mksddn-forms-handler' ),
                    'view_item'          => __( 'View Submission', 'mksddn-forms-handler' ),
                    'search_items'       => __( 'Search Submissions', 'mksddn-forms-handler' ),
                    'not_found'          => __( 'No submissions found', 'mksddn-forms-handler' ),
                    'not_found_in_trash' => __( 'No submissions found in trash', 'mksddn-forms-handler' ),
                ],
                'public'              => false,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'show_in_rest'        => false,
                'capability_type'     => 'post',
                'hierarchical'        => false,
                'supports'            => ['title', 'custom-fields'],
                'menu_icon'           => 'dashicons-list-view',
                'show_in_admin_bar'   => false,
                'can_export'          => true,
                'has_archive'         => false,
                'exclude_from_search' => true,
                'publicly_queryable'  => false,
                'capabilities'        => [
                    'create_posts'       => false,
                    'edit_post'          => 'manage_options',
                    'read_post'          => 'manage_options',
                    'delete_post'        => 'manage_options',
                    'edit_posts'         => 'manage_options',
                    'edit_others_posts'  => 'manage_options',
                    'publish_posts'      => 'manage_options',
                    'read_private_posts' => 'manage_options',
                    'delete_posts'       => 'manage_options',
                ],
            ]
        );
    }
} 