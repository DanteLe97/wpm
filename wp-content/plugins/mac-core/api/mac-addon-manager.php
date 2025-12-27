<?php
/**
 * MAC Addon Manager - Handles add-on install/updates for CRM
 */
if (!defined('ABSPATH')) exit;

class Mac_Addon_Manager {
    
    /**
     * List of allowed addons (synced with dashboard.php)
     */
    private $allowed_addons = [
        'mac-menu',
        'mac-importer-demo',
        'mac-log-viewer',
        'mac-seasonal-effects',
        'mac-core'
    ];
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * Get allowed addons list
     */
    public function get_allowed_addons() {
        return $this->allowed_addons;
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Test endpoint
        register_rest_route('v1', '/addon/test', [
            'methods' => 'GET',
            'callback' => [$this, 'test_endpoint'],
            'permission_callback' => '__return_true',
        ]);
        
        // Check for addon updates
        register_rest_route('v1', '/addon/check-update', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_check_update'],
            'permission_callback' => '__return_true',
            'args' => [
                'auth_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'addon' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        // Install addon (NEW)
        register_rest_route('v1', '/addon/install', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_install_addon'],
            'permission_callback' => '__return_true',
            'args' => [
                'auth_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'addon' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        // Update addon
        register_rest_route('v1', '/addon/update', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_update_addon'],
            'permission_callback' => '__return_true',
            'args' => [
                'auth_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'addon' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                // Optional: minimum current version required to allow update
                'version' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        // Get addon status
        register_rest_route('v1', '/addon/status', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_get_status'],
            'permission_callback' => '__return_true',
            'args' => [
                'auth_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'addon' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }
    
    /**
     * Validate auth key
     */
    private function validate_auth_key($auth_key) {
        $shared_secret = get_option('mac_domain_valid_key', '');
        
        if (empty($shared_secret)) {
            return ['valid' => false, 'error' => 'CRM key is not registered.'];
        }
        
        if ($auth_key !== $shared_secret) {
            return ['valid' => false, 'error' => 'Invalid auth key.'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate addon slug
     */
    private function validate_addon($addon) {
        if (!in_array($addon, $this->allowed_addons)) {
            return ['valid' => false, 'error' => 'Addon not supported: ' . $addon];
        }
        return ['valid' => true];
    }

    /**
     * Handle API POST: /wp-json/v1/addon/check-update
     */
    public function handle_check_update($request) {
        $auth_key = $request->get_param('auth_key');
        $addon = $request->get_param('addon');

        // Validate auth key
        $auth_check = $this->validate_auth_key($auth_key);
        if (!$auth_check['valid']) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 403,
                'message' => $auth_check['error']
            ], 200);
        }

        // Validate addon
        $addon_check = $this->validate_addon($addon);
        if (!$addon_check['valid']) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 400,
                'message' => $addon_check['error']
            ], 200);
        }

        try {
            // Call CRM API to check for updates
            $crm = \MAC_Core\CRM_API_Manager::get_instance();
            $result = $crm->check_update($addon);
            
            if (!$result['success']) {
                return new WP_REST_Response([
                    'success' => false,
                    'code' => 500,
                    'message' => 'Unable to check for updates: ' . $result['message']
                ], 200);
            }

            // Log
            error_log('MAC Addon Manager: Check update for ' . $addon . ' - Current: ' . $result['data']['current_version'] . ', Latest: ' . $result['data']['version'] . ', Needs update: ' . ($result['data']['needs_update'] ? 'Yes' : 'No'));

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'addon' => $addon,
                    'current_version' => $result['data']['current_version'],
                    'latest_version' => $result['data']['version'],
                    'needs_update' => $result['data']['needs_update']
                ]
            ], 200);

        } catch (\Throwable $e) {
            error_log('MAC Addon Manager: Error checking update for ' . $addon . ' - ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'code' => 500,
                'message' => 'Error checking for updates: ' . $e->getMessage()
            ], 200);
        }
    }
    
    /**
     * Handle API POST: /wp-json/v1/addon/install
     */
    public function handle_install_addon($request) {
        $auth_key = $request->get_param('auth_key');
        $addon = $request->get_param('addon');

        // Validate auth key
        $auth_check = $this->validate_auth_key($auth_key);
        if (!$auth_check['valid']) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 403,
                'message' => $auth_check['error']
            ], 200);
        }

        // Validate addon
        $addon_check = $this->validate_addon($addon);
        if (!$addon_check['valid']) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 400,
                'message' => $addon_check['error']
            ], 200);
        }

        try {
            // Use Plugin Installer
            $installer = new \MAC_Core\Plugin_Installer();
            
            // Check if plugin is already installed
            if ($installer->is_plugin_installed($addon)) {
                return new WP_REST_Response([
                    'success' => false,
                    'code' => 409,
                    'message' => 'Plugin is already installed.'
                ], 200);
            }
            
            // Install plugin
            $result = $installer->install_plugin($addon);
            
            if (!$result['success']) {
                error_log('MAC Addon Manager: Install failed for ' . $addon . ' - ' . $result['message']);
                return new WP_REST_Response([
                    'success' => false,
                    'code' => 500,
                    'message' => 'Installation failed: ' . $result['message']
                ], 200);
            }
            
            // Auto activate after install - Force activation in REST API
            $activated = false;
            $activation_error = '';
            $activation_method = '';
            
            if ($addon !== 'mac-core') {
                // Method 1: Try normal activation
                $activate_result = $installer->activate_plugin($addon);
                if (!empty($activate_result['success'])) {
                    $activated = true;
                    $activation_method = 'normal';
                } else {
                    $activation_error = isset($activate_result['message']) ? $activate_result['message'] : 'Unknown activation error';
                    
                    // Method 2: Try force activation (silent mode, ignore output)
                    if (!function_exists('activate_plugin') || !function_exists('is_plugin_active')) {
                        require_once ABSPATH . 'wp-admin/includes/plugin.php';
                    }
                    
                    $config = $installer->get_plugin_config($addon);
                    if ($config && isset($config['file']) && file_exists(WP_PLUGIN_DIR . '/' . $config['file'])) {
                        // Try force activation with silent mode
                        ob_start();
                        $force_result = activate_plugin($config['file'], '', false, true);
                        $output = ob_get_clean();
                        
                        if (!is_wp_error($force_result)) {
                            // Verify it's actually active
                            if (is_plugin_active($config['file'])) {
                                $activated = true;
                                $activation_method = 'force';
                                error_log('MAC Addon Manager: Force activated ' . $addon . ' via REST API');
                            }
                        }
                        
                        // Method 3: If still not activated, try manual activation
                        if (!$activated) {
                            $manual_result = $this->manual_activate_plugin($addon, $config);
                            if ($manual_result && $manual_result['success']) {
                                $activated = true;
                                $activation_method = 'manual';
                                error_log('MAC Addon Manager: Manually activated ' . $addon . ' via REST API');
                            }
                        }
                    }
                    
                    // Only schedule pending if all methods failed
                    if (!$activated) {
                        $pending = get_option('mac_core_pending_activate_plugins', array());
                        if (!is_array($pending)) { $pending = array(); }
                        if (!in_array($addon, $pending, true)) { $pending[] = $addon; }
                        update_option('mac_core_pending_activate_plugins', $pending, false);
                        error_log('MAC Addon Manager: All activation methods failed for ' . $addon . '. Scheduled for admin activation.');
                    }
                }
            } else {
                // mac-core is always considered activated
                $activated = true;
                $activation_method = 'core';
            }
            
            // Get installed version
            $version = isset($result['version']) ? $result['version'] : 'unknown';
            
            // Log
            if ($activated) {
                error_log('MAC Addon Manager: Installed and activated ' . $addon . ' version ' . $version . ' (method: ' . $activation_method . ')');
            } else {
                error_log('MAC Addon Manager: Installed ' . $addon . ' version ' . $version . ' (activation scheduled). Reason: ' . $activation_error);
            }
            
            // Simple response when successful
            if ($activated) {
                return new WP_REST_Response([
                    'success' => true,
                    'data' => [
                        'addon' => $addon,
                        'version' => $version,
                        'activated' => true,
                        'message' => 'Plugin installed and activated successfully.'
                    ]
                ], 200);
            } else {
                // If activation failed, include activation info
                return new WP_REST_Response([
                    'success' => true,
                    'data' => [
                        'addon' => $addon,
                        'version' => $version,
                        'activated' => false,
                        'activation_scheduled' => true,
                        'message' => 'Plugin installed successfully. Activation will complete automatically soon.'
                    ]
                ], 200);
            }

        } catch (\Throwable $e) {
            error_log('MAC Addon Manager: Error installing ' . $addon . ' - ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'code' => 500,
                'message' => 'Error installing addon: ' . $e->getMessage()
            ], 200);
        }
    }

    /**
     * Handle API POST: /wp-json/v1/addon/update
     */
    public function handle_update_addon($request) {
        $auth_key = $request->get_param('auth_key');
        $addon = $request->get_param('addon');
        $min_required_version = $request->get_param('version'); // optional

        // Validate auth key
        $auth_check = $this->validate_auth_key($auth_key);
        if (!$auth_check['valid']) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 403,
                'message' => $auth_check['error']
            ], 200);
        }

        // Validate addon
        $addon_check = $this->validate_addon($addon);
        if (!$addon_check['valid']) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 400,
                'message' => $addon_check['error']
            ], 200);
        }

        try {
            // If min version is provided, verify current installed version meets the requirement
            if (!empty($min_required_version)) {
                $plugin_path = WP_PLUGIN_DIR . '/' . $addon . '/' . $addon . '.php';
                $current_version = '';
                if (file_exists($plugin_path)) {
                    if (!function_exists('get_plugin_data')) {
                        require_once ABSPATH . 'wp-admin/includes/plugin.php';
                    }
                    $plugin_data = get_plugin_data($plugin_path);
                    $current_version = $plugin_data['Version'] ?? '';
                }

                if (empty($current_version) || version_compare($current_version, $min_required_version, '<')) {
                    return new WP_REST_Response([
                        'success' => false,
                        'code' => 400,
                        'message' => 'Current version (' . ($current_version ?: 'unknown') . ') does not meet minimum requirement (' . $min_required_version . '). Skipping update.'
                    ], 200);
                }
            }

            // Call Plugin Installer to update
            $installer = new \MAC_Core\Plugin_Installer();
            
            // Needle file is always addon.php
            $needle_file = $addon . '.php';
            
            // Perform update with auto_activate = true (only from REST API)
            $result = $installer->update_plugin_via_crm_public($addon, $needle_file, true);
            
            if (!$result['success']) {
                return new WP_REST_Response([
                    'success' => false,
                    'code' => 500,
                    'message' => 'Unable to update addon: ' . $result['message']
                ], 200);
            }

            // After successful update, try to activate immediately (don't wait for admin)
            $is_mac_core = ($addon === 'mac-core');
            $activated_immediately = false;
            $activation_error = '';

            if (!$is_mac_core) {
                // Prefer using Installer to handle conflicts/clean cache when activating
                $activate_result = $installer->activate_plugin($addon);
                if (!empty($activate_result['success'])) {
                    $activated_immediately = true;
                } else {
                    $activation_error = isset($activate_result['message']) ? $activate_result['message'] : 'Unknown activation error';
                }
            }

            // If immediate activation fails, fallback: mark as pending to activate later
            if (!$is_mac_core && !$activated_immediately) {
                $pending = get_option('mac_core_pending_activate_plugins', array());
                if (!is_array($pending)) { $pending = array(); }
                if (!in_array($addon, $pending, true)) { $pending[] = $addon; }
                update_option('mac_core_pending_activate_plugins', $pending, false);
            }

            // Log
            if ($is_mac_core) {
                error_log('MAC Addon Manager: Updated mac-core to version ' . $result['version'] . ' (activated immediately)');
            } else if ($activated_immediately) {
                // error_log(...MAC...);
            } else {
                error_log('MAC Addon Manager: Updated ' . $addon . ' to version ' . $result['version'] . ' (activation scheduled). Reason: ' . $activation_error);
            }

            $response_data = [
                'addon' => $addon,
                'new_version' => $result['version'],
                'activated' => $is_mac_core ? true : $activated_immediately,
                'activation_scheduled' => $is_mac_core ? false : !$activated_immediately,
                'message' => $is_mac_core
                    ? 'MAC Core updated and activated successfully.'
                    : ($activated_immediately
                        ? 'Addon updated and activated successfully.'
                        : 'Addon updated successfully. Activation will complete automatically soon.')
            ];

            return new WP_REST_Response([
                'success' => true,
                'data' => $response_data
            ], 200);

        } catch (\Throwable $e) {
            error_log('MAC Addon Manager: Error updating ' . $addon . ' - ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'code' => 500,
                'message' => 'Error updating addon: ' . $e->getMessage()
            ], 200);
        }
    }

    /**
     * Handle API POST: /wp-json/v1/addon/status
     */
    public function handle_get_status($request) {
        $auth_key = $request->get_param('auth_key');
        $addon = $request->get_param('addon');

        // Validate auth key
        $auth_check = $this->validate_auth_key($auth_key);
        if (!$auth_check['valid']) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 403,
                'message' => $auth_check['error']
            ], 200);
        }

        // Validate addon
        $addon_check = $this->validate_addon($addon);
        if (!$addon_check['valid']) {
            return new WP_REST_Response([
                'success' => false,
                'code' => 400,
                'message' => $addon_check['error']
            ], 200);
        }

        try {
            // Get addon information
            $plugin_path = WP_PLUGIN_DIR . '/' . $addon . '/' . $addon . '.php';
            $is_installed = file_exists($plugin_path);
            $is_active = $is_installed ? is_plugin_active($addon . '/' . $addon . '.php') : false;
            
            $current_version = '';
            if ($is_installed && function_exists('get_plugin_data')) {
                $plugin_data = get_plugin_data($plugin_path);
                $current_version = $plugin_data['Version'] ?? '';
            }

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'addon' => $addon,
                    'is_installed' => $is_installed,
                    'is_active' => $is_active,
                    'current_version' => $current_version
                ]
            ], 200);

        } catch (\Throwable $e) {
            error_log('MAC Addon Manager: Error getting status for ' . $addon . ' - ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'code' => 500,
                'message' => 'Error getting addon status: ' . $e->getMessage()
            ], 200);
        }
    }
    
    /**
     * Manually activate plugin by updating WordPress options directly
     */
    private function manual_activate_plugin($addon_slug, $config) {
        if (!$config || !isset($config['file'])) {
            return array('success' => false, 'message' => 'Plugin configuration not found');
        }
        
        // Load WordPress plugin functions if needed
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Get active plugins
        $active_plugins = get_option('active_plugins', array());
        if (!is_array($active_plugins)) {
            $active_plugins = array();
        }
        
        // Add plugin to active plugins list
        if (!in_array($config['file'], $active_plugins, true)) {
            $active_plugins[] = $config['file'];
            update_option('active_plugins', $active_plugins);
        }
        
        // Also update active_sitewide_plugins for multisite
        if (is_multisite()) {
            $active_sitewide_plugins = get_site_option('active_sitewide_plugins', array());
            if (!is_array($active_sitewide_plugins)) {
                $active_sitewide_plugins = array();
            }
            $active_sitewide_plugins[$config['file']] = time();
            update_site_option('active_sitewide_plugins', $active_sitewide_plugins);
        }
        
        // Verify activation
        if (is_plugin_active($config['file'])) {
            return array('success' => true, 'message' => 'Plugin manually activated');
        }
        
        return array('success' => false, 'message' => 'Manual activation failed - plugin not active after update');
    }
    
    /**
     * Test endpoint
     */
    public function test_endpoint($request) {
        return new WP_REST_Response([
            'success' => true,
            'message' => 'MAC Addon Manager API is working!',
            'timestamp' => current_time('c')
        ], 200);
    }
}

new Mac_Addon_Manager();
