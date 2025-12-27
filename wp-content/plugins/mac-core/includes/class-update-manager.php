<?php
/**
 * MAC Core Update Manager
 * 
 * Handles plugin updates for all MAC add-ons using CRM only
 */

namespace MAC_Core;

class Update_Manager {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Handle update requests
        if (isset($_GET['update_mac']) && !empty($_GET['update_mac'])) {
            $this->handle_update_request($_GET['update_mac']);
        }
    }
    
    /**
     * Check if domain license is active
     */
    public function is_domain_license_active() {
        $status = !empty(get_option('mac_domain_valid_status')) ? get_option('mac_domain_valid_status') : "0";
        return $status === 'activate';
    }
    
    /**
     * GitHub functionality removed - using CRM only
     */
    public function get_github_token($addon_slug) {
        return false;
    }
    
    public function check_github_token($addon_slug) {
        return false;
    }
    
    public function get_update_info($addon_slug, $github_repo) {
        return false;
    }
    
    public function is_update_available($addon_slug, $github_repo) {
        return false;
    }
    
    public function download_plugin($addon_slug, $github_repo) {
        return false;
    }
    
    /**
     * Get plugin version
     */
    public function get_plugin_version($addon_slug) {
        $plugin_file = WP_PLUGIN_DIR . '/' . $addon_slug . '/' . $addon_slug . '.php';
        if (file_exists($plugin_file) && function_exists('get_plugin_data')) {
            $plugin_data = get_plugin_data($plugin_file);
            return isset($plugin_data['Version']) ? $plugin_data['Version'] : 'Unknown';
        }
        return 'Not Installed';
    }
    
    /**
     * Handle update request
     */
    private function handle_update_request($addon_slug) {
        // Redirect to admin page for CRM-based updates
        wp_redirect(admin_url('admin.php?page=mac-core'));
        exit;
    }
}
