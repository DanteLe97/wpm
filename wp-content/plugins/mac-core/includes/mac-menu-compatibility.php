<?php
/**
 * MAC Menu Compatibility Layer
 * 
 * This file provides compatibility functions for MAC Menu plugin
 * to ensure it continues working after domain management is moved to MAC Core
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Wrapper functions for MAC Menu compatibility
// Only define these functions if MAC Menu is not installed to avoid conflicts
//if (!file_exists(WP_PLUGIN_DIR . '/mac-menu/mac-menu.php')) {
    if (!function_exists('kvp_enqueue_scripts')) {
        function kvp_enqueue_scripts() {
        if (class_exists('MAC_CORE_Domain_Manager')) {
            $manager = MAC_CORE_Domain_Manager::get_instance();
            $manager->enqueue_scripts();
        } else {
            // // // // error_log(...MAC...);
        }
        }
    }

    if (!function_exists('kvp_handle_check_request')) {
        function kvp_handle_check_request($keyDomainCheck = null) {
            if (class_exists('MAC_CORE_Domain_Manager')) {
                $manager = MAC_CORE_Domain_Manager::get_instance();
                $manager->handle_check_request($keyDomainCheck);
            } else {
                // // // // error_log(...MAC...);
            }
        }
    }

    if (!function_exists('kvp_handle_check_request_url')) {
        function kvp_handle_check_request_url() {
            try {
                if (class_exists('MAC_CORE_Domain_Manager')) {
                    $manager = MAC_CORE_Domain_Manager::get_instance();
                    if ($manager) {
                        $manager->handle_check_request_url();
                    }
                }
            } catch (\Exception $e) {
                // Silent fail
            }
        }
    }

    // Removed kvp_handle_ajax_request function to avoid conflicts
    // MAC Core now uses mac_core_add_license action directly
//}

// Note: Update functionality is now handled by MAC_Core\Update_Manager class

// Helper functions for add-ons to easily use Update Manager
if (!function_exists('mac_get_update_manager')) {
    function mac_get_update_manager() {
        if (class_exists('MAC_Core\Update_Manager')) {
            return MAC_Core\Update_Manager::get_instance();
        }
        return null;
    }
}

// GitHub functionality removed - using CRM only
if (!function_exists('mac_check_github_token')) {
    function mac_check_github_token($addon_slug) {
        return false;
    }
}

if (!function_exists('mac_check_plugin_update')) {
    function mac_check_plugin_update($addon_slug, $github_repo) {
        return false;
    }
}

if (!function_exists('mac_is_update_available')) {
    function mac_is_update_available($addon_slug, $github_repo) {
        return false;
    }
}

if (!function_exists('mac_get_update_url')) {
    function mac_get_update_url($addon_slug) {
        $update_manager = mac_get_update_manager();
        if ($update_manager) {
            return $update_manager->get_update_url($addon_slug);
        }
        return admin_url('plugins.php?update_mac=' . $addon_slug);
    }
}

// Register the old function hooks for MAC Menu compatibility
// Only register if MAC Menu is not installed to avoid conflicts
if (!file_exists(WP_PLUGIN_DIR . '/mac-menu/mac-menu.php')) {
    add_action('admin_enqueue_scripts', 'kvp_enqueue_scripts');
    
    // Auto-check domain URL when no key exists or key is invalid
    // DISABLED: Now using manual check via button
    // add_action('admin_init', 'kvp_auto_check_domain_url');
}

// Auto-check domain URL function
if (!function_exists('kvp_auto_check_domain_url')) {
    function kvp_auto_check_domain_url() {
        error_log('=== MAC Menu: kvp_auto_check_domain_url() CALLED ===');
        error_log('MAC Menu: kvp_auto_check_domain_url - Timestamp: ' . date('Y-m-d H:i:s'));
        
        // Only check if we're in admin and not doing AJAX
        if (!is_admin() || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
            // // // // error_log(...MAC...);
            // // // // error_log(...MAC...);
            return;
        }
        
        // Check if we have a valid key
        $current_key = get_option('mac_domain_valid_key', '');
        $current_status = get_option('mac_domain_valid_status', '');
        
        error_log('MAC Menu: kvp_auto_check_domain_url - Current key: ' . ($current_key ?: 'empty'));
        error_log('MAC Menu: kvp_auto_check_domain_url - Current status: ' . ($current_status ?: 'empty'));
        
        // If no key or invalid status, check with CRM
        if (empty($current_key) || $current_status !== 'activate') {
            // // // // error_log(...MAC...);
            
            // Check last sync time to avoid too frequent requests
            $last_sync = get_option('mac_domain_last_sync', 0);
            $current_time = time();
            
            // Ensure $last_sync is an integer
            $last_sync = intval($last_sync);
            
            $time_diff = $current_time - $last_sync;
            
            error_log('MAC Menu: kvp_auto_check_domain_url - Last sync: ' . date('Y-m-d H:i:s', $last_sync));
            error_log('MAC Menu: kvp_auto_check_domain_url - Current time: ' . date('Y-m-d H:i:s', $current_time));
            // error_log(...MAC...);
            
            // Only check if it's been more than 1 hour since last check
            if (($current_time - $last_sync) > 3600) {
                // // // // error_log(...MAC...);
                kvp_handle_check_request_url();
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
}
