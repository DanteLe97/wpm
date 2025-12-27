<?php
namespace MAC_Core;

/**
 * CRM API Manager
 * 
 * Handles all API calls to CRM for domain validation, license checking, and domain registration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CRM_API_Manager {
    
    // API endpoints
    // const VALIDATE_KEY_URL = 'https://wpm.macusaone.com/api/v1/menu-license/validate-key';
    // const VALIDATE_URL_URL = 'https://wpm.macusaone.com/api/v1/menu-license/validate-url';
    // const REGISTER_DOMAIN_URL = 'https://wpm.macusaone.com/api/v1/menu-license/register-domain';
    // const PLUGIN_REQUEST_URL_PATTERN = 'https://wpm.macusaone.com/api/v1/plugins/{slug}';
    // const CSV_IMPORT_URL = 'https://wpm.macusaone.com/api/v1/mac-menu/import_csv';

    const VALIDATE_KEY_URL = 'https://dev-wpm.macusaone.com/api/v1/menu-license/validate-key';
    const VALIDATE_URL_URL = 'https://dev-wpm.macusaone.com/api/v1/menu-license/validate-url';
    const REGISTER_DOMAIN_URL = 'https://dev-wpm.macusaone.com/api/v1/menu-license/register-domain';
    const PLUGIN_REQUEST_URL_PATTERN = 'https://dev-wpm.macusaone.com/api/v1/plugins/{slug}';
    const CSV_IMPORT_URL = 'https://dev-wpm.macusaone.com/api/v1/mac-menu/import_csv';
    
    // Timeout for API requests
    const TIMEOUT = 45;
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initialize_options();
    }
    
    private function initialize_options() {
        if (false === get_option('mac_domain_valid_key')) {
            add_option('mac_domain_valid_key', '');
        }
        if (false === get_option('mac_domain_valid_status')) {
            add_option('mac_domain_valid_status', '');
        }
    }
    
    public function validate_key($key, $domain, $version) {
        if (empty($key)) {
            return array('success' => false, 'message' => 'Empty key provided');
        }
        
        $response = wp_remote_post(self::VALIDATE_KEY_URL, array(
            'method' => 'POST',
            'body' => array(
                'key' => $key,
                'url' => $domain,
                'menuversion' => $version
            ),
            'timeout' => self::TIMEOUT,
            'sslverify' => true,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 403) {
            if (class_exists('\MAC_CORE_Domain_Manager')) {
                $domain_manager = \MAC_CORE_Domain_Manager::get_instance();
                if (method_exists($domain_manager, 'handle_403_error')) {
                    $domain_manager->handle_403_error();
                }
            }
            return array('success' => false, 'message' => 'Invalid license key provided.');
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (strpos($body, 'Invalid license key') !== false) {
            if (class_exists('\MAC_CORE_Domain_Manager')) {
                $domain_manager = \MAC_CORE_Domain_Manager::get_instance();
                if (method_exists($domain_manager, 'handle_403_error')) {
                    $domain_manager->handle_403_error();
                }
            }
            return array('success' => false, 'message' => 'Invalid license key provided.');
        }
        
        $this->process_domain_response($data, $key);
        
        return array('success' => true, 'data' => $data);
    }
    
    public function validate_url($domain, $version) {
        $response = wp_remote_post(self::VALIDATE_URL_URL, array(
            'method' => 'POST',
            'body' => array(
                'url' => $domain,
                'menuversion' => $version
            ),
            'timeout' => self::TIMEOUT,
            'sslverify' => true,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return array('success' => true, 'data' => $data);
    }
    
    public function register_domain($key, $domain, $version) {
        $response = wp_remote_post(self::REGISTER_DOMAIN_URL, array(
            'method' => 'POST',
            'body' => array(
                'key' => $key,
                'url' => $domain,
                'menuversion' => $version
            ),
            'timeout' => self::TIMEOUT,
            'sslverify' => true,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return array('success' => true, 'data' => $data);
    }
    
    public function check_update($slug) {
        $url = str_replace('{slug}', $slug, self::PLUGIN_REQUEST_URL_PATTERN);
        $license_key = get_option('mac_domain_valid_key', '');
        $domain = get_site_url() . '/';
        $current_version = $this->get_plugin_version($slug);
        
        $body = array(
            'key' => $license_key,
            'url' => $domain,
            'version' => $current_version,
            'action' => 'check_update'
        );
        
        // Debug: Log the request
        error_log('CRM Update Check Request: ' . print_r($body, true));
        error_log('CRM URL: ' . $url);
        
        $args = array(
            'method' => 'POST',
            'body' => $body,
            'timeout' => self::TIMEOUT,
            'sslverify' => true,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            )
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body_str = wp_remote_retrieve_body($response);
        
        if ($code === 403) {
            set_transient('mac_core_403_error', true, 300);
            if (class_exists('\MAC_CORE_Domain_Manager')) {
                $domain_manager = \MAC_CORE_Domain_Manager::get_instance();
                if (method_exists($domain_manager, 'handle_403_error')) {
                    $domain_manager->handle_403_error();
                }
            }
            return array('success' => false, 'message' => 'CRM request HTTP 403: Invalid license key provided.');
        }
        
        if ($code !== 200) {
            return array('success' => false, 'message' => 'CRM request HTTP ' . $code . ($body_str ? (': ' . substr($body_str, 0, 200)) : ''));
        }
        
        $data = json_decode($body_str, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('success' => false, 'message' => 'CRM response JSON decode error: ' . json_last_error_msg());
        }
        
        if (!isset($data['version'])) {
            return array('success' => false, 'message' => 'Invalid response from CRM: missing version');
        }
        
        // Add current_version to response data
        $data['current_version'] = $current_version;
        
        // Determine if update is needed by comparing versions
        $needs_update = false;
        if (isset($data['version']) && $data['version'] !== $current_version) {
            $needs_update = true;
        }
        $data['needs_update'] = $needs_update;
        
        // Debug: Log the response for troubleshooting
        error_log('CRM Update Check Response: ' . print_r($data, true));
        error_log('Current version: ' . $current_version);
        error_log('CRM version: ' . (isset($data['version']) ? $data['version'] : 'not set'));
        error_log('Needs update: ' . ($needs_update ? 'YES' : 'NO'));
        
        return array('success' => true, 'data' => $data);
    }
    
    public function download_plugin($slug) {
        $url = str_replace('{slug}', $slug, self::PLUGIN_REQUEST_URL_PATTERN);
        $license_key = get_option('mac_domain_valid_key', '');
        $site_url = get_site_url() . '/';
        
        $body = array(
            'key' => $license_key,
            'url' => $site_url,
            'action' => 'download'
        );
        
        $args = array(
            'method' => 'POST',
            'body' => $body,
            'timeout' => self::TIMEOUT,
            'sslverify' => true,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            )
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body_str = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            return array('success' => false, 'message' => 'CRM request HTTP ' . $code);
        }
        
        $data = json_decode($body_str, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('success' => false, 'message' => 'CRM response JSON decode error: ' . json_last_error_msg());
        }
        
        if (!isset($data['download_url'])) {
            return array('success' => false, 'message' => 'Missing download_url in CRM response');
        }
        
        return array('success' => true, 'data' => $data);
    }

    /**
     * Backward-compat helpers expected by Plugin_Installer
     */
    public function get_plugin_request_url($slug) {
        return str_replace('{slug}', $slug, self::PLUGIN_REQUEST_URL_PATTERN);
    }

    /**
     * Request plugin package info (expects download_url in response)
     * Returns array on success or WP_Error on failure (to match caller checks)
     */
    public function crm_wp_get($request_url) {
        $license_key = get_option('mac_domain_valid_key', '');
        $site_url = get_site_url() . '/';

        $args = array(
            'method' => 'POST',
            'body' => array(
                'key' => $license_key,
                'url' => $site_url,
                'action' => 'download'
            ),
            'timeout' => self::TIMEOUT,
            'sslverify' => true,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            )
        );

        $response = wp_remote_post($request_url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($code !== 200) {
            return new \WP_Error('crm_http_error', 'CRM request HTTP ' . $code . ($body ? (': ' . substr($body, 0, 200)) : ''));
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('crm_json_error', 'CRM response JSON decode error: ' . json_last_error_msg());
        }

        if (!isset($data['download_url'])) {
            return new \WP_Error('crm_missing_field', 'Missing download_url in CRM response');
        }

        return $data;
    }

    /**
     * Download a remote file to a temporary path and return it
     */
    public function download_file_to_tmp($download_url) {
        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $tmp_file = download_url($download_url, self::TIMEOUT);
        if (is_wp_error($tmp_file)) {
            return array('success' => false, 'message' => $tmp_file->get_error_message());
        }
        return array('success' => true, 'file' => $tmp_file);
    }
    
    public function upload_csv_to_crm($csv_file_path, $import_mode = 'replace') {
        if (!file_exists($csv_file_path)) {
            return array('success' => false, 'message' => 'CSV file not found');
        }
        
        $url = self::CSV_IMPORT_URL;
        $license_key = get_option('mac_domain_valid_key', '');
        $site_url = get_site_url() . '/';
        
        // API yêu cầu trường file là 'file' (không phải 'csv_file')
        $post_data = array(
            'key' => $license_key,
            'url' => $site_url,
            'import_mode' => $import_mode,
            'file' => new \CURLFile($csv_file_path, 'text/csv', basename($csv_file_path))
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Debug logging
        // error_log(...MAC...);
        error_log('MAC Core CRM: CSV upload response: ' . substr($response, 0, 1000));
        error_log('MAC Core CRM: CSV upload cURL error: ' . ($curl_error ?: 'none'));
        
        if ($curl_error) {
            return array('success' => false, 'message' => 'cURL error: ' . $curl_error);
        }
        
        if ($http_code !== 200) {
            return array('success' => false, 'message' => 'HTTP error: ' . $http_code . ' - ' . substr($response, 0, 500));
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('success' => false, 'message' => 'JSON decode error: ' . json_last_error_msg());
        }
        
        return array('success' => true, 'data' => $data);
    }
    
    public function sync_settings_from_crm() {
        $url = self::VALIDATE_URL_URL;
        $license_key = get_option('mac_domain_valid_key', '');
        $site_url = get_site_url() . '/';
        
        $body = array(
            'key' => $license_key,
            'url' => $site_url,
            'action' => 'get_settings'
        );
        
        $args = array(
            'method' => 'POST',
            'body' => $body,
            'timeout' => self::TIMEOUT,
            'sslverify' => true,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            )
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body_str = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            return array('success' => false, 'message' => 'HTTP error: ' . $code);
        }
        
        $data = json_decode($body_str, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('success' => false, 'message' => 'JSON decode error: ' . json_last_error_msg());
        }
        
        return array('success' => true, 'data' => $data);
    }
    
    public function update_settings_to_crm($settings) {
        $url = self::VALIDATE_URL_URL;
        $license_key = get_option('mac_domain_valid_key', '');
        $site_url = get_site_url() . '/';
        
        $body = array(
            'key' => $license_key,
            'url' => $site_url,
            'action' => 'update_settings',
            'settings' => json_encode($settings)
        );
        
        $args = array(
            'method' => 'POST',
            'body' => $body,
            'timeout' => self::TIMEOUT,
            'sslverify' => true,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            )
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body_str = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            return array('success' => false, 'message' => 'HTTP error: ' . $code);
        }
        
        $data = json_decode($body_str, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('success' => false, 'message' => 'JSON decode error: ' . json_last_error_msg());
        }
        
        return array('success' => true, 'data' => $data);
    }
    
    private function get_plugin_version($slug) {
        $plugin_file = WP_PLUGIN_DIR . '/' . $slug . '/' . $slug . '.php';
        if (file_exists($plugin_file) && function_exists('get_plugin_data')) {
            $plugin_data = get_plugin_data($plugin_file);
            return isset($plugin_data['Version']) ? $plugin_data['Version'] : '1.0.0';
        }
        return '1.0.0';
    }
    
    private function process_domain_response($data, $key = null) {
        if (isset($data['success']) && $data['success']) {
            if (isset($data['key']) && !empty($data['key'])) {
                update_option('mac_domain_valid_key', $data['key']);
            } elseif ($key) {
                update_option('mac_domain_valid_key', $key);
            }
            
            if (isset($data['status'])) {
                update_option('mac_domain_valid_status', $data['status']);
            }
            
            if (isset($data['github_token'])) {
                update_option('mac_menu_github_key', $data['github_token']);
            }
            
            update_option('mac_domain_last_sync', time());
        }
    }
    
    public function get_domain_key() {
        return get_option('mac_domain_valid_key', '');
    }
    
    public function update_domain_options($data) {
        if (isset($data['key'])) {
            update_option('mac_domain_valid_key', $data['key']);
        }
        if (isset($data['status'])) {
            update_option('mac_domain_valid_status', $data['status']);
        }
        if (isset($data['github_token'])) {
            update_option('mac_menu_github_key', $data['github_token']);
        }
        update_option('mac_domain_last_sync', time());
    }
    
    public function reset_domain_options() {
        update_option('mac_domain_valid_key', '');
        update_option('mac_domain_valid_status', '');
        update_option('mac_menu_github_key', '');
        update_option('mac_domain_last_sync', time());
    }

    /**
     * Check if current site has a valid license according to stored options
     * No remote calls here – purely local, used by installers/AJAX flows
     */
    public function is_license_valid() {
        // If a recent 403 was detected, treat as invalid until revalidated
        if (get_transient('mac_core_403_error')) {
            return false;
        }

        $status = get_option('mac_domain_valid_status', '');
        $key    = get_option('mac_domain_valid_key', '');

        if (empty($key)) {
            return false;
        }

        // Accept either 'activate' or 'active' as valid states
        if ($status === 'activate' || $status === 'active') {
            return true;
        }

        return false;
    }
}
