<?php
/**
 * Plugin Name: MAC Core
 * Plugin URI: https://macusaone.com
 * Description: Core plugin for managing MAC Marketing plugins, licenses, and updates
 * Version: 1.0.2.0
 * Author: MAC USA One
 * Author URI: https://macusaone.com
 * Text Domain: mac-core
 */

if (!defined('ABSPATH')) {
    exit;
}

// MAC Core is now loaded via mu-plugins to ensure it loads before other MAC plugins

// Define plugin constants
// Get version from plugin header automatically
if (!function_exists('get_plugin_data')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$plugin_data = get_plugin_data(__FILE__);
define('MAC_CORE_VERSION', $plugin_data['Version'] ?? '1.0.0');
define('MAC_CORE_FILE', __FILE__);
define('MAC_CORE_PATH', plugin_dir_path(__FILE__));
define('MAC_CORE_URL', plugin_dir_url(__FILE__));
define('MAC_CORE_BASENAME', plugin_basename(__FILE__));

// Autoloader
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    spl_autoload_register(function ($class) {
        $prefix = 'MAC_Core\\';
        $base_dir = MAC_CORE_PATH . 'includes/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

// Initialize plugin
function mac_core_init() {
    // // // // // error_log(...MAC...);
    // Text domain is now loaded on 'init' (see mac_core_load_textdomain)

    // Initialize components
    if (is_admin()) {
        require_once MAC_CORE_PATH . 'admin/class-admin.php';
        new MAC_Core\Admin\Admin();
    }

    // API and License Manager removed - not used, functionality handled by CRM_API_Manager

    // Initialize CRM API Manager
    require_once MAC_CORE_PATH . 'includes/class-crm-api-manager.php';
    global $mac_core_crm_api_manager;
    $mac_core_crm_api_manager = MAC_Core\CRM_API_Manager::get_instance();

    // Initialize Plugin Manager
    // require_once MAC_CORE_PATH . 'includes/class-plugin-manager.php';
    // new MAC_Core\Plugin_Manager();
    
    // Initialize Plugin Installer
    require_once MAC_CORE_PATH . 'includes/class-plugin-installer.php';
    new MAC_Core\Plugin_Installer();
    
    // Load MAC Menu Domain Manager class FIRST
    require_once MAC_CORE_PATH . 'includes/class-mac-menu-domain-manager.php';
    require_once MAC_CORE_PATH . 'includes/class-update-manager.php';
    
    // Initialize MAC Menu Domain Manager for compatibility
    if (class_exists('MAC_CORE_Domain_Manager')) {
        MAC_CORE_Domain_Manager::get_instance();
    }
    
    // Load MAC Menu compatibility layer AFTER class is available
    require_once MAC_CORE_PATH . 'includes/mac-menu-compatibility.php';
    
    // Initialize options monitor for debugging
    require_once MAC_CORE_PATH . 'includes/class-options-monitor.php';
    global $mac_core_options_monitor;
    $mac_core_options_monitor = MAC_Core\Options_Monitor::get_instance();

    require_once MAC_CORE_PATH . 'api/mac-api.php';
 
}

// Khởi tạo plugin sau khi đã vào init để không gọi hàm dịch quá sớm
add_action('plugins_loaded', 'mac_core_init', 10);
/**/ 
// Load textdomain at init to avoid early loading notices (WP 6.7+)
add_action('init', function() {
    load_plugin_textdomain('mac-core', false, dirname(MAC_CORE_BASENAME) . '/languages');
}, 0);


// After updates, reactivate any plugins flagged in pending list
add_action('admin_init', function() {
    $pending = get_option('mac_core_pending_activate_plugins');
    if (empty($pending) || !is_array($pending)) {
        return;
    }

    // error_log('MAC Core: Processing pending activations: ' . implode(', ', $pending));

    if (!function_exists('activate_plugin')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $remaining = array();
    foreach ($pending as $slug) {
        // Special case: ensure mac-core is in active list and run activator if needed
        if ($slug === 'mac-core') {
            $plugin_main_core = 'mac-core/mac-core.php';
            if (function_exists('is_plugin_active') && is_plugin_active($plugin_main_core)) {
                // // // // error_log(...MAC...);
                continue;
            }

            // Ensure option active_plugins contains mac-core
            $active_plugins = get_option('active_plugins', array());
            if (!in_array($plugin_main_core, $active_plugins, true)) {
                $active_plugins[] = $plugin_main_core;
                update_option('active_plugins', $active_plugins);
                // // // // error_log(...MAC...);
            }

            // Run activator to mimic activation hook effects
            require_once MAC_CORE_PATH . 'includes/class-activator.php';
            if (class_exists('MAC_Core\\Activator')) {
                MAC_Core\Activator::activate();
                // // // // error_log(...MAC...);
            }

            continue;
        }
        
        $plugin_main = $slug . '/' . $slug . '.php';
        
        // Check if plugin file exists
        if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin_main)) {
            // // error_log(...MAC...);
            $remaining[] = $slug;
            continue;
        }
        
        // Check if plugin is already active
        if (function_exists('is_plugin_active') && is_plugin_active($plugin_main)) {
            // // error_log(...MAC...);
            continue;
        }
        
        // Suppress output during activation
        ob_start();
        $res = activate_plugin($plugin_main, '', false, true);
        $output = ob_get_clean();
        
        if (is_wp_error($res)) {
            // Try manual activation as fallback
            // error_log(...MAC...);
            $manual_success = false;
            
            // Get active plugins
            $active_plugins = get_option('active_plugins', array());
            if (!in_array($plugin_main, $active_plugins)) {
                $active_plugins[] = $plugin_main;
                update_option('active_plugins', $active_plugins);
                $manual_success = true;
                // error_log(...MAC...);
            }
            
            if (!$manual_success) {
                // Keep in list to retry next time and log error
                $remaining[] = $slug;
                error_log('MAC Core pending activation failed for ' . $plugin_main . ': ' . $res->get_error_message());
                if (!empty($output)) {
                    // error_log(...MAC...);
                }
            }
        } else {
            // error_log(...MAC...);
            // Verify activation
            if (function_exists('is_plugin_active') && is_plugin_active($plugin_main)) {
                // error_log(...MAC...);
            } else {
                // error_log(...MAC...);
                $remaining[] = $slug;
            }
        }
    }

    if (empty($remaining)) {
        delete_option('mac_core_pending_activate_plugins');
        // // // // error_log(...MAC...);
    } else {
        update_option('mac_core_pending_activate_plugins', $remaining, false);
        error_log('MAC Core: Remaining pending activations: ' . implode(', ', $remaining));
    }
});

// Ensure REST responses are clean JSON even when WP_DEBUG_DISPLAY is on
add_filter('rest_pre_serve_request', function($served) {
    if (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) {
        @ini_set('display_errors', '0');
    }
    return $served;
}, 10, 1);
/* */

// Activation hook
register_activation_hook(__FILE__, function() {
    require_once MAC_CORE_PATH . 'includes/class-activator.php';
    MAC_Core\Activator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Allow deactivation but show warning if MAC Menu is active
    if (function_exists('is_plugin_active') === false) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    if (is_plugin_active('mac-menu/mac-menu.php')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning"><p><strong>MAC Core</strong> has been deactivated. <strong>MAC Menu</strong> will now run in read-only mode without license management features.</p></div>';
        });
    }
    
    require_once MAC_CORE_PATH . 'includes/class-deactivator.php';
    MAC_Core\Deactivator::deactivate();
});

// Uninstall hook
function mac_core_uninstall() {
    require_once MAC_CORE_PATH . 'includes/class-uninstaller.php';
    MAC_Core\Uninstaller::uninstall();
}
register_uninstall_hook(__FILE__, 'mac_core_uninstall');

// Add settings link to plugins page
add_filter('plugin_action_links_' . MAC_CORE_BASENAME, function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=mac-core') . '">' . 'Settings' . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Add plugin row meta
add_filter('plugin_row_meta', function($links, $file) {
    if ($file === MAC_CORE_BASENAME) {
        $row_meta = array(
            'docs' => '<a href="' . esc_url('https://mac-marketing.com/docs') . '" aria-label="' . 'View MAC Core documentation' . '">' . 'Documentation' . '</a>',
            'support' => '<a href="' . esc_url('https://mac-marketing.com/support') . '" aria-label="' . 'Visit support forum' . '">' . 'Support' . '</a>'
        );
        return array_merge($links, $row_meta);
    }
    return $links;
}, 10, 2);
