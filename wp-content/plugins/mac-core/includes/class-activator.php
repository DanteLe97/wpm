<?php
namespace MAC_Core;

class Activator {
    public static function activate() {
        // Initialize MAC Core options
        add_option('mac_core_debug_mode', '0');
        
        // Initialize MAC Menu domain options if they don't exist
        if (false === get_option('mac_domain_valid_key')) {
            add_option('mac_domain_valid_key', '');
        }
        if (false === get_option('mac_domain_valid_status')) {
            add_option('mac_domain_valid_status', '');
        }
        if (false === get_option('mac_menu_github_key')) {
            add_option('mac_menu_github_key', '');
        }
    }
} 
