<?php
/**
 * Base API class for MAC Importer Demo
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Importer_API_Base {
    
    /**
     * Validate license key với mac-core
     */
    public static function validate_license_key($auth_key) {
        if (empty($auth_key)) {
            return false;
        }

        // Chỉ dùng khóa từ mac-core: mac_domain_valid_key
        $stored = trim((string) get_option('mac_domain_valid_key'));
        if ($stored === '') {
            return false;
        }
        return hash_equals($stored, trim((string)$auth_key));
    }
    
    /**
     * Lấy auth_key từ request (hỗ trợ nhiều cách)
     */
    public static function get_auth_key_from_request($request) {
        // Lấy auth_key từ query/body hoặc header Authorization: Bearer <key>
        $auth_key = $request->get_param('auth_key');
        if (empty($auth_key)) {
            $auth_header = $request->get_header('authorization');
            if (!empty($auth_header) && stripos($auth_header, 'Bearer ') === 0) {
                $auth_key = trim(substr($auth_header, 7));
            }
            // Thử các header phổ biến khác
            if (empty($auth_key)) {
                $xauth = $request->get_header('x-auth-key');
                if (!empty($xauth)) {
                    $auth_key = trim($xauth);
                }
            }
            if (empty($auth_key)) {
                $xapi = $request->get_header('x-api-key');
                if (!empty($xapi)) {
                    $auth_key = trim($xapi);
                }
            }
        }
        
        return $auth_key;
    }
    
    /**
     * Tạo error response
     */
    public static function create_error_response($code, $message, $debug = false) {
        $payload = array(
            'success' => false,
            'code' => $code,
            'message' => $message
        );
        
        if ($debug) {
            $val = (string) get_option('mac_domain_valid_key');
            $masked = $val === '' ? '' : (strlen($val) <= 6 ? '****' : substr($val, 0, 2) . '****' . substr($val, -2));
            $payload['auth_diagnostics'] = array(
                'mac_domain_valid_key' => array(
                    'exists' => $val !== '',
                    'masked' => $masked,
                    'match' => ($val !== '' && trim($val) === trim((string)$auth_key))
                )
            );
        }
        
        return new WP_REST_Response($payload, 200);
    }
}
