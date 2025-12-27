<?php
namespace MAC_Core;

class Deactivator {
    public static function deactivate() {
        // Clear plugin transients
        delete_transient('mac_core_plugin_updates');
        delete_transient('mac_core_license_status');

        // Clear cache
        wp_cache_flush();
        
        // Always clear MAC Menu cron safely without relying on class methods
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook('mac_menu_domain_check');
        }
    }
} 
