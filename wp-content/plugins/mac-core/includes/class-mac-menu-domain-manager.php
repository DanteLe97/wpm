<?php
/**
 * MAC Menu Domain Manager Class
 * 
 * This class was moved from mac-menu plugin to provide compatibility
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// API endpoints centralized in MAC_Core\CRM_API_Manager; no local defines

// Define the class to handle AJAX requests for MAC Core
// This class provides AJAX handlers for license management
class MAC_CORE_Domain_Manager {
    private static $instance = null;
    private $current_version;
    private $plugin_file;

    // Constants
    const DEFAULT_VERSION = '1.6.2';
    const TIMEOUT = 45;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
        $this->init_hooks();
    }

    private function init() {
        $this->plugin_file = WP_PLUGIN_DIR . '/mac-menu/mac-menu.php';
        $this->current_version = $this->get_plugin_version();
        
        
        // Fix options if they were incorrectly initialized with '0'
        $this->fix_options_if_needed();
    }

    private function init_hooks() {
        // Hooks are now handled by compatibility layer to avoid conflicts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // MAC Core AJAX hooks only - chỉ cho user đã đăng nhập
        add_action('wp_ajax_mac_core_add_license', array($this, 'handle_ajax_request'));
        // Removed: wp_ajax_nopriv_mac_core_add_license - không cho phép guest gọi API này
        
        // Test AJAX handler
        add_action('wp_ajax_test_ajax', array($this, 'test_ajax_handler'));
        
        // Auto-validate key when visiting target admin pages
        add_action('admin_notices', array($this, 'validate_on_target_pages')); // Run after page has loaded
        
        // REMOVED: Cron job chạy theo giờ - chỉ check khi vào trang admin cụ thể
        // Clear any existing scheduled cron job
        if (wp_next_scheduled('mac_menu_domain_check')) {
            wp_clear_scheduled_hook('mac_menu_domain_check');
        }
        
        // Check domain ONLY when accessing specific admin pages (mac-core, mac-menu, mac-cat-menu)
        add_action('admin_notices', array($this, 'check_domain_on_admin_pages'));
        
        // AJAX handler moved to class-plugin-installer.php to avoid duplication
    }

    private function get_plugin_version() {
        if (is_admin() && function_exists('get_plugin_data') && file_exists($this->plugin_file)) {
            $plugin_data = get_plugin_data($this->plugin_file);
            return isset($plugin_data['Version']) ? $plugin_data['Version'] : self::DEFAULT_VERSION;
        }
        return self::DEFAULT_VERSION;
    }

    public function enqueue_scripts() {
        wp_localize_script('admin-script', 'kvp_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mac_menu_domain_nonce')
        ));
    }

    public function handle_check_request($key_domain_check = null) {
        error_log('=== MAC Menu: handle_check_request() CALLED ===');
        error_log('MAC Menu: handle_check_request - Key: ' . ($key_domain_check ?: 'null'));
        error_log('MAC Menu: handle_check_request - Timestamp: ' . date('Y-m-d H:i:s'));
        error_log('MAC Menu: handle_check_request - Backtrace: ' . $this->get_backtrace());
        
        if (empty($key_domain_check)) {
            // // // // error_log(...MAC...);
            $this->reset_domain_options();
            // // // // error_log(...MAC...);
            return;
        }

        // Use CRM_API_Manager instead of direct URL
        $domain = get_site_url() . '/';
        
        // error_log(...MAC...);
        // error_log(...MAC...);
        // error_log(...MAC...);
        
        $crm = \MAC_Core\CRM_API_Manager::get_instance();
        $response = $crm->validate_key($key_domain_check, $domain, $this->current_version);

        if (is_wp_error($response)) {
            error_log('MAC Menu: handle_check_request - API Error: ' . $response->get_error_message());
            // // // // error_log(...MAC...);
            return;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            // // // // error_log(...MAC...);
            // // // // error_log(...MAC...);
            return;
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // // // // error_log(...MAC...);
            // // // // error_log(...MAC...);
            return;
        }

        // // // // error_log(...MAC...);
        $this->process_domain_response($data, $key_domain_check);
        // // // // error_log(...MAC...);
    }

    public function handle_check_request_url() {
        error_log('=== MAC Menu: handle_check_request_url() CALLED ===');
        error_log('MAC Menu: handle_check_request_url - Timestamp: ' . date('Y-m-d H:i:s'));
        
        // Use CRM_API_Manager instead of direct URL
        $domain = get_site_url() . '/';
        
        // Log what we're sending to CRM
        $request_data = array(
            'url' => $domain,
            'menuversion' => $this->current_version
        );
        // error_log(...MAC...);
        // error_log(...MAC...);
        error_log('Data: ' . json_encode($request_data));
        
        $crm = \MAC_Core\CRM_API_Manager::get_instance();
        $result = $crm->validate_url($domain, $this->current_version);
        
        error_log('MAC Menu: handle_check_request_url - CRM result: ' . print_r($result, true));

        // Check if the result is an error array
        if (is_array($result) && isset($result['success']) && !$result['success']) {
            error_log('CRM Response: ERROR - ' . ($result['message'] ?? 'Unknown error'));
            return;
        }

        // Check if we got valid data
        if (!is_array($result) || !isset($result['data'])) {
            error_log('CRM Response: INVALID FORMAT - ' . json_encode($result));
            return;
        }

        $data = $result['data'];

        // Log what we received from CRM
        // error_log(...MAC...);
        error_log('Response: ' . json_encode($data));
        
        $this->process_domain_url_response($data);
    }

    public function force_sync_license() {
        // Public method to force sync license with CRM
        // // // // error_log(...MAC...);
        $this->force_sync_with_crm();
    }
    
    public function check_domain_if_needed() {
        error_log('=== MAC Menu: check_domain_if_needed() CALLED ===');
        error_log('MAC Menu: check_domain_if_needed - Timestamp: ' . date('Y-m-d H:i:s'));
        error_log('MAC Menu: check_domain_if_needed - Backtrace: ' . $this->get_backtrace());
        
        // Only check if we're in admin and not doing AJAX
        if (!is_admin() || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
            // // // // error_log(...MAC...);
            // // // // error_log(...MAC...);
            return;
        }
        
        // Check if we have a valid key
        $current_key = get_option('mac_domain_valid_key', '');
        $current_status = get_option('mac_domain_valid_status', '');
        
        // Clean up invalid values (0, null, etc.)
        if ($current_key === '0' || $current_key === null) {
            $current_key = '';
        }
        if ($current_status === '0' || $current_status === null) {
            $current_status = '';
        }
        
        error_log('MAC Menu: check_domain_if_needed - Current key: ' . ($current_key ?: 'empty'));
        error_log('MAC Menu: check_domain_if_needed - Current status: ' . ($current_status ?: 'empty'));
        
        // If no key or invalid status, check with CRM
        if (empty($current_key) || $current_status !== 'activate') {
            // // // // error_log(...MAC...);
            
            // Check last sync time to avoid too frequent requests
            $last_sync = get_option('mac_domain_last_sync', 0);
            $current_time = time();
            
            // Ensure $last_sync is an integer
            $last_sync = intval($last_sync);
            
            $time_diff = $current_time - $last_sync;
            
            error_log('MAC Menu: check_domain_if_needed - Last sync: ' . date('Y-m-d H:i:s', $last_sync));
            error_log('MAC Menu: check_domain_if_needed - Current time: ' . date('Y-m-d H:i:s', $current_time));
            // error_log(...MAC...);
            
            // Only check if it's been more than 1 hour since last check
            if ($time_diff > 3600) {
                // // // // error_log(...MAC...);
                $this->handle_check_request_url();
                // // // // error_log(...MAC...);
            } else {
                // // // // error_log(...MAC...);
                // // // // error_log(...MAC...);
            }
        } else {
            // // // // error_log(...MAC...);
            // // // // error_log(...MAC...);
        }
    }
    
    public function handle_ajax_request() {
        ob_start();
        
        // Verify nonce (accept both 'nonce' and 'mac_core_license_nonce' from form)
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : (isset($_POST['mac_core_license_nonce']) ? $_POST['mac_core_license_nonce'] : null);
        
        if ($nonce !== null && !wp_verify_nonce($nonce, 'mac_core_add_license')) {
            wp_send_json_error('Invalid security token.');
            return;
        }

        if (!isset($_POST['key'])) {
            wp_send_json_error('Key is required.');
            return;
        }

        $key = sanitize_text_field($_POST['key']);
        $domain = get_site_url() . '/';

        $crm = \MAC_Core\CRM_API_Manager::get_instance();
        $response = $crm->register_domain($key, $domain, $this->current_version);
        error_log('MAC Menu: handle_ajax_request - Response: ' . print_r($response, true));
        // Handle new response format: ['success' => bool, 'data'| 'message']
        if (!is_array($response)) {
            wp_send_json_error('Unexpected API response.');
            return;
        }
        if (isset($response['success']) && !$response['success']) {
            $msg = isset($response['message']) ? $response['message'] : 'API request failed.';
            wp_send_json_error($msg);
            return;
        }
        if (!isset($response['data']) || !is_array($response['data'])) {
            wp_send_json_error('Invalid response format from server.');
            return;
        }

        $result = $this->process_ajax_response($response['data']);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    // API calls are handled by CRM_API_Manager

    private function process_domain_response($data, $key = null) {
        // Check if this is an API error
        if ($this->is_api_error($data)) {
            return; // Don't reset when API has error
        }
        
        // Check if key is valid
        if (!isset($data['valid']) || !$data['valid']) {
            // Key không hợp lệ, reset options
            $this->reset_domain_options();
            return;
        }

        $current_status = get_option('mac_domain_valid_status', '');
        
        // Clean up invalid values (0, null, etc.)
        if ($current_status === '0' || $current_status === null) {
            $current_status = '';
        }
        
        if ($current_status !== $data['statusDomain']) {
            $this->update_domain_options($data);
            $this->reload_page();
        }
    }
    
    private function process_domain_url_response($data) {
        // Check if this is an API error
        if ($this->is_api_error($data)) {
            return;
        }
        
        // Check if domain is registered in CRM
        if (!isset($data['valid']) || !$data['valid']) {
            $this->reset_domain_options();
            return;
        }
        
        // If domain is valid but has no key, also reset
        if (!isset($data['keyDomain']) || empty($data['keyDomain'])) {
            $this->reset_domain_options();
            return;
        }
        
        // Check if current key in database matches key from CRM
        $current_key = get_option('mac_domain_valid_key', '');
        
        // Clean up invalid values (0, null, etc.)
        if ($current_key === '0' || $current_key === null) {
            $current_key = '';
        }
        
        if (!empty($current_key) && $current_key !== $data['keyDomain']) {
            $this->reset_domain_options();
            return;
        }
        
        // If domain and key are both valid, update options
        $current_status = get_option('mac_domain_valid_status', '');
        
        // Clean up invalid values (0, null, etc.)
        if ($current_status === '0' || $current_status === null) {
            $current_status = '';
        }
        
        if ($current_status !== $data['statusDomain']) {
            $this->update_domain_options($data);
            $this->reload_page();
        }
    }
    
    private function is_api_error($data) {
        // Check for API error cases
        if (empty($data)) {
            return true; // Response rỗng
        }
        
        // Kiểm tra có phải error response không
        if (isset($data['error']) || isset($data['error_code'])) {
            return true;
        }
        
        // Kiểm tra có phải HTML error page không
        if (is_string($data) && (strpos($data, '<html') !== false || strpos($data, 'error') !== false)) {
            return true;
        }
        
        // Kiểm tra có phải JSON parse error không
        if (json_last_error() !== JSON_ERROR_NONE) {
            return true;
        }
        
        return false;
    }

    private function process_ajax_response($data) {
        // Debug: Log the response data
        error_log('MAC Menu Process AJAX Response - Data: ' . print_r($data, true));
        
        if (!isset($data['valid']) || !$data['valid']) {
            // // // // error_log(...MAC...);
            return array('success' => false, 'message' => $data['message'] ?? 'Invalid key.');
        }

        // // // // error_log(...MAC...);
        $this->update_domain_options($data);
        
        // Return success result instead of sending JSON directly
        return array('success' => true, 'message' => $data['message'] ?? 'Key is valid!');
    }

    private function update_domain_options($data) {
        update_option('mac_domain_valid_key', $data['keyDomain']);
        update_option('mac_domain_valid_status', $data['statusDomain']);
        update_option('mac_domain_last_sync', time());

        if (in_array($data['statusDomain'], array('activate', 'deactivate'))) {
            update_option('mac_menu_github_key', $data['keytoken']);
        } else {
            update_option('mac_menu_github_key', null);
        }
        
        error_log('Updated: Key=' . $data['keyDomain'] . ', Status=' . $data['statusDomain'] . ', GitHub=' . ($data['keytoken'] ?? 'null'));
    }

    private function reset_domain_options() {
        update_option('mac_domain_valid_key', null);
        update_option('mac_domain_valid_status', null);
        update_option('mac_menu_github_key', null);
        update_option('mac_domain_last_sync', time());
        
    }
    
    

    /**
     * Check domain when accessing admin pages
     * Logic: Check current key validity, if invalid reset, if valid check URL with CRM
     */
    public function check_domain_on_admin_pages() {
        
        // Check if we're on specific MAC admin pages ONLY
        // Allowed pages: mac-core, mac-menu, mac-cat-menu
        $allowed_pages = ['mac-core', 'mac-menu', 'mac-cat-menu'];
        if (!isset($_GET['page']) || !in_array($_GET['page'], $allowed_pages)) {
            return;
        }
        
        // Check if we already ran this check in this page load
        static $already_checked = false;
        if ($already_checked) {
            return;
        }
        $already_checked = true;
        
        
        // Force check if we have a 403 error in recent logs
        $recent_403 = get_transient('mac_core_403_error');
        if ($recent_403) {
            delete_transient('mac_core_403_error');
        }

        
        // Get current key
        $current_key = get_option('mac_domain_valid_key', '');
        
        if (empty($current_key)) {
            // No key exists, try to get key from CRM via URL
            $this->handle_check_request_url();
            return;
        }

        // Check if current key is valid
        $crm = \MAC_Core\CRM_API_Manager::get_instance();
        $domain = get_site_url() . '/';
        $result = $crm->validate_key($current_key, $domain, $this->current_version);
        
        
        if (is_array($result) && isset($result['success']) && !$result['success']) {
            // Key is invalid, reset domain options
            $this->reset_domain_options();
            
            $this->handle_check_request_url();
        } else {
            $this->handle_check_request_url();
        }
    }
    
    private function has_active_license() {
        $status = get_option('mac_domain_valid_status', '');
        return $status === 'activate';
    }
    
    private function should_preserve_license() {
        // If license is active, only reset when key is definitely invalid
        if ($this->has_active_license()) {
            // // // // error_log(...MAC...);
            return true;
        }
        return false;
    }
    
    private function check_crm_sync_status() {
        // Check if plugin is synced with CRM
        $current_key = get_option('mac_domain_valid_key', '');
        $current_status = get_option('mac_domain_valid_status', '');
        
        if (empty($current_key) || empty($current_status)) {
            // // // // error_log(...MAC...);
            return false;
        }
        
        // error_log(...MAC...);
        return true;
    }
    
    private function force_sync_with_crm() {
        // Force sync by calling check URL
        // // // // error_log(...MAC...);
        
        // Update last sync time before force sync
        update_option('mac_domain_last_sync', time());
        error_log('MAC Menu: Last sync time updated before force sync: ' . date('Y-m-d H:i:s'));
        
        $this->handle_check_request_url();
    }

    private function reload_page() {
        // Check if we're already on a reloaded page to prevent infinite loop
        if (isset($_GET['reloaded']) && $_GET['reloaded'] == '1') {
            // // // // error_log(...MAC...);
            return;
        }
        
        // Never reload during plugin activation
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        foreach ($backtrace as $trace) {
            if (isset($trace['function']) && in_array($trace['function'], ['activate_plugin', 'plugin_activate'])) {
                return; // Don't reload during plugin activation
            }
        }
        
        // Only reload if we're in admin context and not doing AJAX
        if (!is_admin() || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
            // // // // error_log(...MAC...);
            return;
        }
        
        // Only reload if we're in a proper admin page context
        if (isset($_GET['page']) && strpos($_GET['page'], 'mac-') === 0) {
            // // // // error_log(...MAC...);
            if (!headers_sent()) {
                wp_redirect(admin_url('admin.php?page=' . $_GET['page'] . '&reloaded=1'));
                exit;
            } else {
                ?>
                <script type="text/javascript">
                    window.location.reload();
                </script>
                <?php
            }
        }
    }
    
    /**
     * Get backtrace for debugging
     */
    private function get_backtrace() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $trace_string = '';
        foreach ($backtrace as $i => $trace) {
            if ($i === 0) continue; // Skip current function
            $trace_string .= "\n" . ($i) . ". " . 
                (isset($trace['class']) ? $trace['class'] . '::' : '') . 
                $trace['function'] . '() in ' . 
                (isset($trace['file']) ? basename($trace['file']) : 'unknown') . 
                ':' . (isset($trace['line']) ? $trace['line'] : 'unknown');
        }
        return $trace_string;
    }

    /**
     * Test AJAX handler
     */
    public function test_ajax_handler() {
        wp_send_json_success('Test AJAX working');
    }

    
    /**
     * Fix options if they were incorrectly initialized with '0'
     */
    private function fix_options_if_needed() {
        // Get current values
        $current_key = get_option('mac_domain_valid_key', '');
        $current_status = get_option('mac_domain_valid_status', '');
        
        // Fix key if it's '0'
        if ($current_key === '0') {
            update_option('mac_domain_valid_key', '');
            // // // // error_log(...MAC...);
        }
        
        // Fix status if it's '0'
        if ($current_status === '0') {
            update_option('mac_domain_valid_status', '');
            // // // // error_log(...MAC...);
        }
    }
    
    /**
     * Test function to send API call to MAC_MENU_VALIDATE_URL
     */
    public function testValidateUrl() {
        error_log('=== MAC Menu: testValidateUrl() CALLED ===');
        error_log('MAC Menu: testValidateUrl - Timestamp: ' . date('Y-m-d H:i:s'));
        
        // Get the API URL
        // Use CRM_API_Manager instead of direct URL
        $domain = get_site_url() . '/';
        
        // error_log(...MAC...);
        // error_log(...MAC...);
        // error_log(...MAC...);
        
        // Prepare request data for POST method
        $request_data = array(
            'url' => $domain,
            'menuversion' => $this->current_version
        );
        
        // error_log(...MAC...);
        // error_log(...MAC...);
        error_log('Method: POST');
        error_log('Data: ' . json_encode($request_data));
        
        $crm = \MAC_Core\CRM_API_Manager::get_instance();
        $response = $crm->validate_url($domain, $this->current_version);
        
        if (is_wp_error($response)) {
            error_log('CRM Response: ERROR - ' . $response->get_error_message());
            return false;
        }
        
        if (!$response['success']) {
            return false;
        }
        return $response['data'];
    }
    
    /**
     * Static method to test validate URL without creating instance
     */
    public static function test_validate_url_static() {
        $domain = get_site_url() . '/';
        $version = self::DEFAULT_VERSION;
        
        // Try to get version from mac-menu plugin if exists
        $plugin_file = WP_PLUGIN_DIR . '/mac-menu/mac-menu.php';
        if (file_exists($plugin_file) && function_exists('get_plugin_data')) {
            $plugin_data = get_plugin_data($plugin_file);
            if (isset($plugin_data['Version'])) {
                $version = $plugin_data['Version'];
            }
        }
        
        error_log('=== MAC Menu: testValidateUrl() CALLED (Static) ===');
        error_log('MAC Menu: testValidateUrl - Timestamp: ' . date('Y-m-d H:i:s'));
        // error_log(...MAC...);
        // error_log(...MAC...);
        // error_log(...MAC...);
        
        $crm = \MAC_Core\CRM_API_Manager::get_instance();
        $response = $crm->validate_url($domain, $version);
        
        if (is_wp_error($response)) {
            error_log('CRM Response: ERROR - ' . $response->get_error_message());
            return false;
        }
        
        if (!$response['success']) {
            return false;
        }
        return $response['data'];
    }

    // AJAX handler moved to class-plugin-installer.php to avoid duplication

        /**
     * Validate key automatically when visiting specific admin pages
     */
    public function validate_on_target_pages() {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $target_pages = array('mac-core', 'mac-cat-menu', 'mac-menu');
        
        if (!in_array($page, $target_pages, true)) {
            return;
        }
        
        if (!is_admin()) {
            return;
        }

        $key = get_option('mac_domain_valid_key', '');
        
        if (empty($key)) {
            return; // No key to validate
        }

        $domain = get_site_url() . '/';
        $version = $this->current_version;
        
        $crm = \MAC_Core\CRM_API_Manager::get_instance();
        $crm->validate_key($key, $domain, $version);
    }

    /**
     * Cleanup cron job when plugin is deactivated
     */
    public static function cleanup_cron_job() {
        wp_clear_scheduled_hook('mac_menu_domain_check');
    }
}
