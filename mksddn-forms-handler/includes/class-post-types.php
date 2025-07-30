<?php
namespace FormsHandler;

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
        
        // Flush rewrite rules for REST API
        flush_rewrite_rules();
    }
    
    /**
     * Register forms post type
     */
    private function register_forms_post_type(): void {
        register_post_type(
            'forms',
            [
                'labels'              => [
                    'name'               => 'Forms',
                    'singular_name'      => 'Form',
                    'menu_name'          => 'Forms',
                    'add_new'            => 'Add Form',
                    'add_new_item'       => 'Add New Form',
                    'edit_item'          => 'Edit Form',
                    'new_item'           => 'New Form',
                    'view_item'          => 'View Form',
                    'search_items'       => 'Search Forms',
                    'not_found'          => 'No forms found',
                    'not_found_in_trash' => 'No forms found in trash',
                ],
                'public'              => true,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'show_in_rest'        => true,
                'rest_base'           => 'forms',
                'capability_type'     => 'post',
                'hierarchical'        => false,
                'rewrite'             => ['slug' => 'forms'],
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
            'form_submissions',
            [
                'labels'              => [
                    'name'               => 'Form Submissions',
                    'singular_name'      => 'Submission',
                    'menu_name'          => 'Submissions',
                    'add_new'            => 'Add Submission',
                    'add_new_item'       => 'Add New Submission',
                    'edit_item'          => 'Edit Submission',
                    'new_item'           => 'New Submission',
                    'view_item'          => 'View Submission',
                    'search_items'       => 'Search Submissions',
                    'not_found'          => 'No submissions found',
                    'not_found_in_trash' => 'No submissions found in trash',
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