<?php
/**
 * REST API Class
 * 
 * Handles REST API endpoints for tutorial sync (source site only)
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Tutorial_REST_API {
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new MAC_Tutorial_Database();
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('mac-tutorials/v1', '/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'register_site'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route('mac-tutorials/v1', '/tutorial-list', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_tutorial_list'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route('mac-tutorials/v1', '/sync-tutorials', array(
            'methods' => 'POST',
            'callback' => array($this, 'sync_tutorials'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Register site endpoint
     */
    public function register_site($request) {
        $params = $request->get_json_params();
        $site_url = isset($params['site_url']) ? sanitize_text_field($params['site_url']) : '';
        
        if (empty($site_url)) {
            return new WP_Error('missing_site_url', 'Site URL is required', array('status' => 400));
        }
        
        // Validate URL
        if (!filter_var($site_url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid site URL', array('status' => 400));
        }
        
        $result = $this->database->insert_site($site_url);
        
        if ($result['success']) {
            return rest_ensure_response(array(
                'success' => true,
                'status' => 'pending',
                'message' => 'Site registered successfully. Waiting for approval.',
                'data' => $result['data']
            ));
        } else {
            // Site already exists
            if (isset($result['data'])) {
                return rest_ensure_response(array(
                    'success' => true,
                    'status' => $result['data']['status'],
                    'message' => $result['data']['status'] === 'active' ? 'Site is already active' : 'Site is pending approval',
                    'data' => $result['data']
                ));
            }
            
            return new WP_Error('registration_failed', $result['message'], array('status' => 500));
        }
    }
    
    /**
     * Get tutorial list endpoint
     */
    public function get_tutorial_list($request) {
        $params = $request->get_json_params();
        $api_key = isset($params['api_key']) ? sanitize_text_field($params['api_key']) : '';
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'API key is required', array('status' => 401));
        }
        
        // Verify API key
        $site = $this->database->get_site_by_api_key($api_key);
        if (!$site) {
            return new WP_Error('invalid_api_key', 'Invalid API key', array('status' => 401));
        }
        
        if ($site['status'] !== 'active') {
            return new WP_Error('not_approved', 'Site is not approved yet', array('status' => 403));
        }
        
        // Get all posts with tutorial enabled
        $args = array(
            'post_type' => array('post', 'page'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_mac_tutorial_enabled',
                    'value' => '1',
                    'compare' => '='
                )
            )
        );
        
        $query = new WP_Query($args);
        $tutorials = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $tutorials[] = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title()
                );
            }
            wp_reset_postdata();
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'tutorials' => $tutorials
        ));
    }
    
    /**
     * Sync tutorials endpoint
     */
    public function sync_tutorials($request) {
        $params = $request->get_json_params();
        $api_key = isset($params['api_key']) ? sanitize_text_field($params['api_key']) : '';
        $ids = isset($params['ids']) && is_array($params['ids']) ? array_map('intval', $params['ids']) : array();
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'API key is required', array('status' => 401));
        }
        
        if (empty($ids)) {
            return new WP_Error('missing_ids', 'Post IDs are required', array('status' => 400));
        }
        
        // Verify API key
        $site = $this->database->get_site_by_api_key($api_key);
        if (!$site) {
            return new WP_Error('invalid_api_key', 'Invalid API key', array('status' => 401));
        }
        
        if ($site['status'] !== 'active') {
            return new WP_Error('not_approved', 'Site is not approved yet', array('status' => 403));
        }
        
        // Get tutorial data for requested IDs
        $tutorials = array();
        foreach ($ids as $post_id) {
            $post = get_post($post_id);
            if (!$post || !in_array($post->post_type, array('post', 'page'))) {
                continue;
            }
            
            $enabled = get_post_meta($post_id, '_mac_tutorial_enabled', true);
            if (empty($enabled)) {
                continue;
            }
            
            $steps = get_post_meta($post_id, '_mac_tutorial_steps', true);
            $steps = !empty($steps) && is_array($steps) ? $steps : array();
            
            $settings = get_post_meta($post_id, '_mac_tutorial_settings', true);
            $settings = !empty($settings) && is_array($settings) ? $settings : array();
            
            $tutorials[] = array(
                'id' => $post_id,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'enabled' => $enabled,
                'steps' => $steps,
                'settings' => $settings
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'tutorials' => $tutorials
        ));
    }
}

