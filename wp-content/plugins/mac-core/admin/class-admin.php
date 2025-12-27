<?php
namespace MAC_Core\Admin;

class Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        // Back-compat: redirect old slug mac-core-plugins => mac-core
        add_action('admin_init', array($this, 'redirect_legacy_page'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('MAC Core', 'mac-core'),
            __('MAC Core', 'mac-core'),
            'manage_options',
            'mac-core',
            array($this, 'render_admin_page'),
            'dashicons-admin-plugins',
            30
        );

        add_submenu_page(
            'mac-core',
            __('Dashboard', 'mac-core'),
            __('Dashboard', 'mac-core'),
            'manage_options',
            'mac-core',
            array($this, 'render_admin_page')
        );
        // Removed separate Add-ons submenu; main page now renders plugins list
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'mac-core') === false) {
            return;
        }

        wp_enqueue_style(
            'mac-core-admin',
            MAC_CORE_URL . 'admin/css/admin.css',
            array(),
            MAC_CORE_VERSION
        );

        wp_enqueue_script(
            'mac-core-admin',
            MAC_CORE_URL . 'admin/js/admin.js',
            array('jquery'),
            MAC_CORE_VERSION,
            true
        );

        wp_localize_script('mac-core-admin', 'macCoreAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mac_core_install_plugin'),
            'pluginsUrl' => admin_url('plugins.php'),
            'i18n' => array(
                'confirmDelete' => __('Are you sure you want to delete this item?', 'mac-core'),
                'error' => __('An error occurred. Please try again.', 'mac-core'),
                'success' => __('Operation completed successfully.', 'mac-core')
            )
        ));
    }

    public function render_admin_page() {
        // If there's an action in the URL, switch to plugin handling flow (activate/deactivate/update/install)
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        if (!empty($action)) {
            $this->render_plugins_page();
            return;
        }

        require_once MAC_CORE_PATH . 'admin/views/dashboard.php';
    }



    public function render_plugins_page() {
        // Handle plugin actions
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $addon = isset($_GET['addon']) ? sanitize_text_field($_GET['addon']) : '';
        $notice_message = '';
        $notice_type = '';
        
        if ($action && $addon) {
            switch ($action) {
                case 'update':
                    list($notice_message, $notice_type) = $this->handle_plugin_update($addon);
                    break;
                case 'activate':
                    list($notice_message, $notice_type) = $this->handle_plugin_activate($addon);
                    break;
                case 'deactivate':
                    list($notice_message, $notice_type) = $this->handle_plugin_deactivate($addon);
                    break;
                case 'install':
                    list($notice_message, $notice_type) = $this->handle_plugin_install($addon);
                    break;
            }
        }
        
        // Display notice if exists, then fallback render dashboard (không còn file plugins.php)
        if ($notice_message) {
            echo '<div class="notice notice-' . esc_attr($notice_type) . ' is-dismissible"><p>' . esc_html($notice_message) . '</p></div>';
        }
        require_once MAC_CORE_PATH . 'admin/views/dashboard.php';
    }

    /**
     * Redirect legacy page slug to the current one, preserving params
     * Fixes old links like page=mac-core-plugins&action=... on hosting
     */
    public function redirect_legacy_page() {
        if (!is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if ($page !== 'mac-core-plugins') {
            return;
        }

        // Preserve action params
        $args = array('page' => 'mac-core');
        if (isset($_GET['action'])) {
            $args['action'] = sanitize_text_field($_GET['action']);
        }
        if (isset($_GET['addon'])) {
            $args['addon'] = sanitize_text_field($_GET['addon']);
        }
        if (isset($_GET['_wpnonce'])) {
            $args['_wpnonce'] = sanitize_text_field($_GET['_wpnonce']);
        }

        $url = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
    
    private function handle_plugin_update($addon_slug) {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'mac-core'));
        }
        
        // Check nonce for security
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'mac_core_plugin_action')) {
            return array(__('Security check failed.', 'mac-core'), 'error');
        }
        
        $update_manager = \MAC_Core\Update_Manager::get_instance();
        
        try {
            $result = $update_manager->download_and_replace_plugin_files($addon_slug);
            if ($result) {
                return array(__('Plugin updated successfully!', 'mac-core'), 'success');
            } else {
                return array(__('Failed to update plugin. Please try again.', 'mac-core'), 'error');
            }
        } catch (\Exception $e) {
            return array(__('Error updating plugin: ', 'mac-core') . $e->getMessage(), 'error');
        }
    }
    
    private function handle_plugin_activate($addon_slug) {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'mac-core'));
        }
        
        // Check nonce for security
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'mac_core_plugin_action')) {
            return array(__('Security check failed.', 'mac-core'), 'error');
        }
        
        // Load plugin functions
        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $mac_core_addons = array(
            'mac-menu' => array('file' => 'mac-menu/mac-menu.php'),
            'mac-log-viewer' => array('file' => 'mac-log-viewer/mac-log-viewer.php'),
            'mac-importer-demo' => array('file' => 'mac-importer-demo/mac-importer-demo.php'),
            'mac-seasonal-effects' => array('file' => 'mac-seasonal-effects/mac-seasonal-effects.php'),
            'mac-interactive-tutorials' => array('file' => 'mac-interactive-tutorials/mac-interactive-tutorials.php'),
            'mac-role' => array('file' => 'mac-role/mac-role.php')
        );
        
        if (isset($mac_core_addons[$addon_slug])) {
            $plugin_file = $mac_core_addons[$addon_slug]['file'];
            $result = activate_plugin($plugin_file);
            
            if (is_wp_error($result)) {
                return array(__('Failed to activate plugin: ', 'mac-core') . $result->get_error_message(), 'error');
            } else {
                return array(__('Plugin activated successfully!', 'mac-core'), 'success');
            }
        }
        
        return array(__('Plugin not found.', 'mac-core'), 'error');
    }
    
    private function handle_plugin_deactivate($addon_slug) {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'mac-core'));
        }
        
        // Check nonce for security
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'mac_core_plugin_action')) {
            return array(__('Security check failed.', 'mac-core'), 'error');
        }
        
        // Load plugin functions
        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $mac_core_addons = array(
            'mac-menu' => array('file' => 'mac-menu/mac-menu.php'),
            'mac-log-viewer' => array('file' => 'mac-log-viewer/mac-log-viewer.php'),
            'mac-importer-demo' => array('file' => 'mac-importer-demo/mac-importer-demo.php'),
            'mac-seasonal-effects' => array('file' => 'mac-seasonal-effects/mac-seasonal-effects.php'),
            'mac-interactive-tutorials' => array('file' => 'mac-interactive-tutorials/mac-interactive-tutorials.php'),
            'mac-role' => array('file' => 'mac-role/mac-role.php')
        );
        
        if (isset($mac_core_addons[$addon_slug])) {
            $plugin_file = $mac_core_addons[$addon_slug]['file'];
            deactivate_plugins($plugin_file);
            
            return array(__('Plugin deactivated successfully!', 'mac-core'), 'success');
        }
        
        return array(__('Plugin not found.', 'mac-core'), 'error');
    }
    
    private function handle_plugin_install($addon_slug) {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'mac-core'));
        }
        
        // Check nonce for security
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'mac_core_plugin_action')) {
            return array(__('Security check failed.', 'mac-core'), 'error');
        }
        
        // Use Plugin Installer
        $installer = \MAC_Core\Plugin_Installer::get_instance();
        $result = $installer->install_plugin($addon_slug);
        
        if ($result['success']) {
            // Try to activate the plugin
            $activate_result = $installer->activate_plugin($addon_slug);
            if ($activate_result['success']) {
                $result['message'] .= ' Plugin activated successfully!';
            }
            
            return array($result['message'], 'success');
        } else {
            return array($result['message'], 'error');
        }
    }
    
    /**
     * Handle AJAX check options status
     */
    public function handle_check_options_status_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        global $mac_core_options_monitor;
        if ($mac_core_options_monitor) {
            $status = $mac_core_options_monitor->get_critical_options_status();
            wp_send_json_success($status);
        } else {
            wp_send_json_error('Options monitor not available.');
        }
    }

} 
