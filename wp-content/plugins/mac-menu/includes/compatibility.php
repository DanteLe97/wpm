<?php
/**
 * MAC Menu Compatibility Layer
 * 
 * Provides fallback functions when MAC Core is not available
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if MAC Core is available
 */
function mac_menu_is_core_available() {
    return class_exists('MAC_Core\CRM_API_Manager');
}

/**
 * Fallback function for kvp_handle_check_request
 */
if (!function_exists('kvp_handle_check_request')) {
    function kvp_handle_check_request($key = null) {
        if (!mac_menu_is_core_available()) {
            // Log that function is not available
            error_log('MAC Menu: kvp_handle_check_request() called but MAC Core is not available');
            return false;
        }
        
        // If MAC Core is available, it should handle this
        return true;
    }
}

/**
 * Fallback function for kvp_handle_check_request_url
 */
if (!function_exists('kvp_handle_check_request_url')) {
    function kvp_handle_check_request_url() {
        if (!mac_menu_is_core_available()) {
            // Log that function is not available
            error_log('MAC Menu: kvp_handle_check_request_url() called but MAC Core is not available');
            return false;
        }
        
        // If MAC Core is available, it should handle this
        return true;
    }
}

/**
 * Safe function to get CRM API Manager
 */
function mac_menu_get_crm_api() {
    if (mac_menu_is_core_available()) {
        return \MAC_Core\CRM_API_Manager::get_instance();
    }
    return null;
}

/**
 * Check if management features are available
 */
function mac_menu_can_manage() {
    $keyDomain = get_option('mac_domain_valid_key', '');
    $statusDomain = get_option('mac_domain_valid_status', '');
    
    // Kiểm tra key trước
    $has_valid_key = !empty($keyDomain) && $keyDomain !== "0";
    if (!$has_valid_key) {
        return false; // Không có key = không thể manage
    }
    
    // Kiểm tra MAC Core và status
    $mac_core_available = mac_menu_is_core_available();
    $has_valid_status = !empty($statusDomain) && ($statusDomain =='activate' || $statusDomain =='deactivate');
    
    return $mac_core_available && $has_valid_status;
}
