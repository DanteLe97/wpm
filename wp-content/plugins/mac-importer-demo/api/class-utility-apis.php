<?php
/**
 * Utility APIs for MAC Importer Demo
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Importer_Utility_APIs {
    
    /**
     * Register utility API endpoints
     */
    public static function register_endpoints() {
        // Ping endpoint
        register_rest_route('ltp/v1', '/ping', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array(__CLASS__, 'ping')
        ));

        // Check key endpoint
        register_rest_route('ltp/v1', '/check-key', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array(__CLASS__, 'check_key')
        ));
    }
    
    /**
     * Ping endpoint
     */
    public static function ping() {
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'pong'
        ), 200);
    }
    
    /**
     * Check key endpoint
     */
    public static function check_key($request) {
        $auth_key = MAC_Importer_API_Base::get_auth_key_from_request($request);

        $stored = (string) get_option('mac_domain_valid_key');
        $ok = (!empty($auth_key) && $stored !== '' && hash_equals(trim((string)$auth_key), trim($stored)));
        $masked = $stored === '' ? '' : (strlen($stored) <= 6 ? '****' : substr($stored, 0, 2) . '****' . substr($stored, -2));
        return new WP_REST_Response(array(
            'success' => $ok,
            'message' => $ok ? 'Key matched' : 'Key not matched or not set',
            'diagnostics' => array(
                'mac_domain_valid_key' => array(
                    'exists' => $stored !== '',
                    'masked' => $masked,
                    'match' => $ok
                )
            )
        ), 200);
    }
}
