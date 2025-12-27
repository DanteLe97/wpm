<?php
/**
 * API Handlers - Handles API endpoints
 */
if (!defined('ABSPATH')) exit;

/**
 * Handle get-infor API endpoint
 */
function mac_handle_get_infor($request) {
    $auth_key = $request->get_param('auth_key');
    $auth_result = mac_verify_auth_key($auth_key);
    if ($auth_result !== true) {
        return $auth_result;
    }

    $data = $request->get_param('data');
    
    if (!empty($data) && is_array($data)) {
        return mac_handle_data_array($data);
    }
    
    // Backward compatibility: check for info/action parameter
    $selector = $request->get_param('info');
    if (empty($selector)) {
        $selector = $request->get_param('action');
    }
    
    if (!empty($selector)) {
        $action = strtolower((string) $selector);
        
        // Route to legacy handlers
        switch ($action) {
            case 'web-infor':
                return mac_handle_get_web_info($request);
            case 'smtp':
                return mac_handle_get_smtp_info($request);
            default:
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Invalid info parameter. Please update to the latest mac-core version'
                ], 400);
        }
    }
    
    // No valid parameters provided
    return new WP_REST_Response([
        'success' => false,
        'message' => 'Please provide data array or info/action parameter.'
    ], 400);
}

/**
 * Handle site-health API endpoint
 */
function mac_handle_site_health($request) {
    $auth_key = $request->get_param('auth_key');
    $auth_result = mac_verify_auth_key($auth_key);
    if ($auth_result !== true) {
        return $auth_result;
    }

    try {
        $site_url = get_site_url();
        $html = mac_get_cached_html($site_url);
        
        if (empty($html)) {
            return new WP_REST_Response([
                'success' => false,
                'result' => false,
                'message' => 'Unable to fetch website content'
            ], 200);
        }
        
        $hack_check = mac_detect_hack_by_comparison($html);
        
        if ($hack_check['is_hacked']) {
            return new WP_REST_Response([
                'success' => true,
                'result' => false,
                'message' => $hack_check['message']
            ], 200);
        } else {
            return new WP_REST_Response([
                'success' => true,
                'result' => true,
                'message' => 'Website is safe'
            ], 200);
        }
        
    } catch (Exception $e) {
        return new WP_REST_Response([
            'success' => false,
            'result' => false,
            'message' => 'Error checking: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Handle data array requests
 */
function mac_handle_data_array($data) {
    $results = [];
    
    foreach ($data as $index => $item) {
        try {
            if (!isset($item['type'])) {
                $results[] = [
                    'success' => false,
                    'result' => false,
                    'message' => 'Missing type parameter'
                ];
                continue;
            }
            
            $type = strtolower(sanitize_text_field($item['type']));
            
            switch ($type) {
                case 'sitecheck':
                    $result = mac_handle_sitecheck($item);
                    // If sitecheck returns a complete response (has 'results' key), return it directly
                    if (isset($result['results'])) {
                        return new WP_REST_Response($result, 200);
                    }
                    break;
                case 'option':
                    $result = mac_handle_option($item['name'] ?? '', $item['value'] ?? null);
                    break;
                case 'post':
                    $result = mac_handle_post($item);
                    // If result is a nested structure (has 'results' key), keep it as nested structure
                    if (is_array($result) && isset($result['results'])) {
                        // Add the whole nested structure as one item
                        $results[] = $result;
                        continue 2; // Skip adding to results array below
                    }
                    // If result is an array of individual results, merge them into main results
                    if (is_array($result) && isset($result[0]) && is_array($result[0])) {
                        $results = array_merge($results, $result);
                        continue 2; // Skip adding to results array below
                    }
                    break;
                case 'plugin':
                    $result = mac_handle_plugin($item);
                    break;
                case 'user':
                    $result = mac_handle_user($item['name'] ?? '');
                    break;
                case 'updown':
                    $result = mac_handle_updown($item);
                    break;
                default:
                    $result = [
                        'success' => false,
                        'type' => $type,
                        'result' => false,
                        'message' => 'Unknown request type: ' . $type
                    ];
            }
            
            $results[] = $result;
        } catch (Exception $e) {
            $results[] = [
                'success' => false,
                'result' => false,
                'message' => 'Error processing item: ' . $e->getMessage()
            ];
        }
    }
    
    return new WP_REST_Response([
        'success' => true,
        'total' => count($results),
        'results' => $results
    ], 200);
}

/**
 * Handle sitecheck requests - Delegate to Mac_Sitecheck
 */
function mac_handle_sitecheck($item) {
    try {
        $sitecheck = Mac_Sitecheck::getInstance();
        $result = $sitecheck->handle_sitecheck($item);
        
        // If result already has 'results' key, return it directly (batch sitecheck)
        if (isset($result['results'])) {
            return $result;
        }
        
        // Otherwise, return as single result
        return $result;
    } catch (Exception $e) {
        return [
            'success' => false,
            'type' => 'sitecheck',
            'result' => false,
            'message' => 'Error processing sitecheck: ' . $e->getMessage()
        ];
    }
}

/**
 * Handle web info requests
 */
function mac_handle_get_web_info($request) {
    $fields = $request->get_param('fields');
    
    $web_info = [
        'site_url' => get_site_url(),
        'site_title' => get_bloginfo('name'),
        'site_description' => get_bloginfo('description'),
        'admin_email' => get_option('admin_email'),
        'wordpress_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'theme' => get_option('stylesheet'),
        'active_plugins' => get_option('active_plugins', [])
    ];
    
    if (!empty($fields)) {
        $field_list = explode(',', $fields);
        $filtered_info = [];
        foreach ($field_list as $field) {
            $field = trim($field);
            if (isset($web_info[$field])) {
                $filtered_info[$field] = $web_info[$field];
            }
        }
        $web_info = $filtered_info;
    }
    
    return new WP_REST_Response([
        'success' => true,
        'result' => true,
        'message' => 'Web info retrieved successfully',
        'data' => $web_info
    ], 200);
}

/**
 * Handle SMTP info requests
 */
function mac_handle_get_smtp_info($request) {
    $smtp_info = [
        'smtp_host' => get_option('smtp_host', ''),
        'smtp_port' => get_option('smtp_port', ''),
        'smtp_username' => get_option('smtp_username', ''),
        'smtp_password' => get_option('smtp_password', ''),
        'smtp_secure' => get_option('smtp_secure', ''),
        'smtp_auth' => get_option('smtp_auth', false),
        'from_email' => get_option('from_email', ''),
        'from_name' => get_option('from_name', '')
    ];
    
    return new WP_REST_Response([
        'success' => true,
        'result' => true,
        'message' => 'SMTP info retrieved successfully',
        'data' => $smtp_info
    ], 200);
}
