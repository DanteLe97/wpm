<?php
/**
 * Custom Post Type Class
 * 
 * Handles registration of mac_tutorial post type
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Tutorial_Post_Type {
    
    /**
     * Post type slug
     */
    const POST_TYPE = 'mac_tutorial';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
    }
    
    /**
     * Register custom post type
     */
    public function register_post_type() {
        $args = array(
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-welcome-learn-more',
            'menu_position'      => 25,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest'       => true, // Enable Gutenberg
            'labels'             => $this->get_labels(),
        );
        
        register_post_type(self::POST_TYPE, $args);
    }
    
    /**
     * Get post type labels
     */
    private function get_labels() {
        return array(
            'name'               => __('Interactive Tutorials', 'mac-interactive-tutorials'),
            'singular_name'      => __('Tutorial', 'mac-interactive-tutorials'),
            'add_new'            => __('Add New Tutorial', 'mac-interactive-tutorials'),
            'add_new_item'       => __('Add New Tutorial', 'mac-interactive-tutorials'),
            'edit_item'          => __('Edit Tutorial', 'mac-interactive-tutorials'),
            'new_item'           => __('New Tutorial', 'mac-interactive-tutorials'),
            'view_item'          => __('View Tutorial', 'mac-interactive-tutorials'),
            'search_items'       => __('Search Tutorials', 'mac-interactive-tutorials'),
            'not_found'          => __('No tutorials found', 'mac-interactive-tutorials'),
            'not_found_in_trash' => __('No tutorials found in Trash', 'mac-interactive-tutorials'),
            'all_items'          => __('All Tutorials', 'mac-interactive-tutorials'),
            'menu_name'          => __('Interactive Tutorials', 'mac-interactive-tutorials'),
        );
    }
}

