<?php
/**
 * MAC Info Manager - Xử lý API lấy thông tin từ WordPress
 * File chính - đã được tách thành nhiều file con để dễ quản lý
 */
if (!defined('ABSPATH')) exit;

// Include sitecheck functionality (giữ nguyên)
require_once plugin_dir_path(__FILE__) . 'mac-sitecheck.php';

// Include các files con
require_once plugin_dir_path(__FILE__) . '/includes/api-handlers.php';
require_once plugin_dir_path(__FILE__) . '/includes/data-handlers.php';
require_once plugin_dir_path(__FILE__) . '/includes/security-utils.php';
require_once plugin_dir_path(__FILE__) . '/includes/html-utils.php';
require_once plugin_dir_path(__FILE__) . '/includes/cache-utils.php';
require_once plugin_dir_path(__FILE__) . '/includes/misc-utils.php';

class Mac_Info_Manager {
    private $html_cache = []; // In-memory HTML cache
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function register_rest_routes() {
        // Unified get-infor endpoint
        register_rest_route('v1', '/get-infor', [
            'methods' => ['POST'],
            'callback' => 'mac_handle_get_infor', // Di chuyển sang api-handlers.php
            'permission_callback' => '__return_true',
            'args' => [
                'auth_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'data' => [
                    'required' => false,
                    'type' => 'array',
                ],
                'info' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'action' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'fields' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        // Site Health endpoint
        register_rest_route('v1', '/site-health', [
            'methods' => ['POST'],
            'callback' => 'mac_handle_site_health', // Di chuyển sang api-handlers.php
            'permission_callback' => '__return_true',
            'args' => [
                'auth_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }
}

new Mac_Info_Manager();