<?php
/**
 * MAC Core Plugin Installer
 * 
 * Handles downloading and installing plugins from GitHub repositories
 */

namespace MAC_Core;

class Plugin_Installer {
    private static $instance = null;
    private $update_transaction = null;
    private $auto_activate_requested = false;
    private $auto_activate_slug = null;
    
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Add AJAX handlers
        add_action('wp_ajax_mac_core_install_plugin', array($this, 'handle_install_plugin_ajax'));
        add_action('wp_ajax_mac_core_check_install_status', array($this, 'handle_check_install_status_ajax'));
        add_action('wp_ajax_mac_core_check_system_requirements', array($this, 'handle_check_system_requirements_ajax'));
        add_action('wp_ajax_mac_core_debug_tokens', array($this, 'handle_debug_tokens_ajax'));
        add_action('wp_ajax_mac_core_activate_plugin', array($this, 'handle_activate_plugin_ajax'));
        add_action('wp_ajax_mac_core_force_remove_plugin', array($this, 'handle_force_remove_plugin_ajax'));
        add_action('wp_ajax_mac_core_check_options_status', array($this, 'handle_check_options_status_ajax'));
        add_action('wp_ajax_mac_core_check_url', array($this, 'handle_check_url_ajax'));
        add_action('wp_ajax_mac_core_test_validate_url', array($this, 'handle_test_validate_url_ajax'));
        add_action('wp_ajax_mac_core_install_mac_menu', array($this, 'handle_install_mac_menu_ajax'));
        add_action('wp_ajax_mac_core_activate_mac_menu', array($this, 'handle_activate_mac_menu_ajax'));
        add_action('wp_ajax_mac_core_check_update_mac_menu', array($this, 'handle_check_update_mac_menu_ajax'));
        add_action('wp_ajax_mac_core_reset_options', array($this, 'handle_reset_options_ajax'));
        add_action('wp_ajax_mac_core_restore_mac_core', array($this, 'handle_restore_mac_core_ajax'));
        // New: MAC Core self update
        add_action('wp_ajax_mac_core_check_update_mac_core', array($this, 'handle_check_update_mac_core_ajax'));
        add_action('wp_ajax_mac_core_update_mac_core', array($this, 'handle_update_mac_core_ajax'));
        // Generic plugin update handler - supports any plugin
        add_action('wp_ajax_mac_update_plugin', array($this, 'handle_update_plugin_ajax'));
        // Generic check update handler for addon plugins
        add_action('wp_ajax_mac_core_check_update_plugin', array($this, 'handle_check_update_plugin_ajax'));
        // Removed custom force delete handler; rely on WP delete_plugins
    }
    
    /**
     * Get plugin configuration
     */
    public function get_plugin_config($addon_slug) {
        $mac_core_addons = array(
            'mac-menu' => array(
                'name' => 'MAC Menu',
                'description' => 'Create beautiful menu tables for your restaurant website.',
                'slug' => 'mac-menu',
                'file' => 'mac-menu/mac-menu.php',
                // GitHub functionality removed - using CRM only
            ),
            'mac-reservation' => array(
                'name' => 'MAC Reservation', 
                'description' => 'Manage restaurant reservations and table bookings.',
                'slug' => 'mac-reservation',
                'file' => 'mac-reservation/mac-reservation.php',
                // GitHub functionality removed - using CRM only
            ),
            'mac-delivery' => array(
                'name' => 'MAC Delivery',
                'description' => 'Handle food delivery orders and tracking.',
                'slug' => 'mac-delivery',
                'file' => 'mac-delivery/mac-delivery.php',
                // GitHub functionality removed - using CRM only
            ),
            'mac-loyalty' => array(
                'name' => 'MAC Loyalty',
                'description' => 'Customer loyalty program and rewards system.',
                'slug' => 'mac-loyalty',
                'file' => 'mac-loyalty/mac-loyalty.php',
                // GitHub functionality removed - using CRM only
            ),
            'mac-importer-demo' => array(
                'name' => 'MAC Importer Demo',
                'description' => 'Demo importer for MAC design templates and Elementor pages.',
                'slug' => 'mac-importer-demo',
                'file' => 'mac-importer-demo/mac-importer-demo.php',
                // GitHub functionality removed - using CRM only
            ),
            'mac-log-viewer' => array(
                'name' => 'MAC Log Viewer',
                'description' => 'View and manage PHP error logs with syntax highlighting.',
                'slug' => 'mac-log-viewer',
                'file' => 'mac-log-viewer/mac-log-viewer.php',
                // GitHub functionality removed - using CRM only
            )
        );
        
        return isset($mac_core_addons[$addon_slug]) ? $mac_core_addons[$addon_slug] : false;
    }
    
    /**
     * Check if plugin is already installed
     */
    public function is_plugin_installed($addon_slug) {
        $config = $this->get_plugin_config($addon_slug);
        if (!$config) {
            return false;
        }
        
        $plugin_file = WP_PLUGIN_DIR . '/' . $config['file'];
        return file_exists($plugin_file);
    }
    
    /**
     * Check if plugin is active
     */
    public function is_plugin_active($addon_slug) {
        $config = $this->get_plugin_config($addon_slug);
        if (!$config) {
            return false;
        }
        
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        return is_plugin_active($config['file']);
    }
    
    /**
     * Get GitHub token for plugin
     */
    
    /**
     * GitHub API call with retry mechanism
     */
    
    /**
     * List files in GitHub directory
     */
    
    /**
     * Download file content from GitHub
     */
    
    /**
     * Download subdirectory files recursively
     */
    
    /**
     * Delete directory recursively
     */
    private function delete_directory($dir) {
        if (is_dir($dir)) {
            $objects = array_diff(scandir($dir), array('.', '..'));
            foreach ($objects as $object) {
                $file = $dir . '/' . $object;
                (is_dir($file)) ? $this->delete_directory($file) : unlink($file);
            }
            rmdir($dir);
        }
    }
    
    /**
     * Copy directory recursively
     */
    private function copy_directory($source, $destination) {
        if (!is_dir($source)) {
            return false;
        }
        
        if (!wp_mkdir_p($destination)) {
            return false;
        }
        
        $objects = array_diff(scandir($source), array('.', '..'));
        foreach ($objects as $object) {
            $source_path = $source . '/' . $object;
            $dest_path = $destination . '/' . $object;
            
            if (is_dir($source_path)) {
                if (!$this->copy_directory($source_path, $dest_path)) {
                    return false;
                }
            } else {
                if (!copy($source_path, $dest_path)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Recursively remove directory and all its contents
     */
    private function recursive_remove_directory($dir) {
        if (!is_dir($dir)) {
            return true;
        }
        
        $objects = array_diff(scandir($dir), array('.', '..'));
        foreach ($objects as $object) {
            $path = $dir . '/' . $object;
            if (is_dir($path)) {
                if (!$this->recursive_remove_directory($path)) {
                    return false;
                }
            } else {
                if (!unlink($path)) {
                    return false;
                }
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Download plugin from CRM API
     */
    public function download_plugin_from_crm($addon_slug) {
        // $this->log_debug('MAC Core: Starting CRM download for ' . $addon_slug);
        
        global $mac_core_crm_api_manager;
        if (!$mac_core_crm_api_manager) {
            return array(
                'success' => false,
                'message' => 'CRM API manager not available.'
            );
        }
        
        // Request plugin download from CRM
        $request_url = $mac_core_crm_api_manager->get_plugin_request_url($addon_slug);
        $response = $mac_core_crm_api_manager->crm_wp_get($request_url);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Failed to request plugin from CRM: ' . $response->get_error_message()
            );
        }
        
        if (!isset($response['download_url'])) {
            $msg = isset($response['message']) ? $response['message'] : 'CRM did not return download_url for ' . $addon_slug;
            return array(
                'success' => false,
                'message' => $msg
            );
        }
        
        // Download the plugin zip file
        $download_result = $mac_core_crm_api_manager->download_file_to_tmp($response['download_url']);
        if (!$download_result['success']) {
            return array(
                'success' => false,
                'message' => 'Failed to download plugin: ' . $download_result['message']
            );
        }
        
        $zip_file = $download_result['file'];
        
        // Create temporary directory for extraction
        $tmp_dir = WP_CONTENT_DIR . '/' . $addon_slug . '-install-tmp-' . time();
        if (!wp_mkdir_p($tmp_dir)) {
            @unlink($zip_file);
            return array(
                'success' => false,
                'message' => 'Failed to create temporary directory.'
            );
        }
        
        // Extract the zip file
        if (!function_exists('unzip_file')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        $unzipped = unzip_file($zip_file, $tmp_dir);
        @unlink($zip_file); // Clean up zip file
        
        if (is_wp_error($unzipped)) {
            $this->delete_directory($tmp_dir);
            return array(
                'success' => false,
                'message' => 'Failed to extract plugin: ' . $unzipped->get_error_message()
            );
        }
        
        // Find the plugin directory
        $config = $this->get_plugin_config($addon_slug);
        $needle_file = $config['slug'] . '.php';
        $plugin_dir = $this->locate_unzipped_plugin_dir($tmp_dir, $needle_file);
        
        if (!$plugin_dir) {
            $this->delete_directory($tmp_dir);
            return array(
                'success' => false,
                'message' => 'Plugin structure invalid: ' . $needle_file . ' not found'
            );
        }
        
        return array(
            'success' => true,
            'temp_dir' => $tmp_dir,
            'plugin_dir' => $plugin_dir,
            'version' => isset($response['version']) ? $response['version'] : 'latest'
        );
    }

    
    /**
     * Clean up conflicting plugin files
     */
    private function cleanup_conflicting_files($addon_slug) {
        $config = $this->get_plugin_config($addon_slug);
        if (!$config) {
            return;
        }
        
        // Only clean up specific conflicting files, NOT the entire plugin directory
        // The plugin directory should be preserved for activation
        
        // For MAC Menu, only clean up specific conflicting files
        if ($addon_slug === 'mac-menu') {
            $mac_menu_files = array(
                WP_PLUGIN_DIR . '/mac-menu/domain-manager.php'
            );
            
            foreach ($mac_menu_files as $file) {
                if (file_exists($file)) {
                    // $this->log_debug('MAC Core: Removing conflicting MAC Menu file: ' . $file);
                    unlink($file);
                }
            }
            
            // Fix potential output issues in MAC Menu main file
            $this->fix_mac_menu_output_issues();
        }
    }
    
    /**
     * Install plugin from downloaded files
     */
    public function install_plugin($addon_slug) {
        // $this->log_debug('MAC Core: Starting installation for ' . $addon_slug);
        
        // Clean up any conflicting files first
        $this->cleanup_conflicting_files($addon_slug);
        
        // Check if plugin is already installed
        if ($this->is_plugin_installed($addon_slug)) {
            // $this->log_debug('MAC Core: Plugin already installed');
            return array(
                'success' => false,
                'message' => 'Plugin is already installed.'
            );
        }
        
        // Download plugin via CRM
        $crm = CRM_API_Manager::get_instance();
        // $this->log_debug('MAC Core: Calling CRM download_plugin');
        $req = $crm->download_plugin($addon_slug);
        // $this->log_debug('MAC Core: CRM download_plugin result: ' . print_r($req, true));
        if (!$req['success']) {
            // $this->log_debug('MAC Core: CRM request failed: ' . $req['message']);
            return array('success' => false, 'message' => 'CRM request failed: ' . $req['message']);
        }
        $info = $req['data'];
        // $this->log_debug('MAC Core: Download URL: ' . $info['download_url']);
        // $this->log_debug('MAC Core: Downloading file to temp...');
        $dl = $crm->download_file_to_tmp($info['download_url']);
        // $this->log_debug('MAC Core: Download file result: ' . print_r($dl, true));
        if (!$dl['success']) {
            // $this->log_debug('MAC Core: Download failed: ' . $dl['message']);
            return array('success' => false, 'message' => 'Download failed: ' . $dl['message']);
        }
        $zip_file = $dl['file'];
        // $this->log_debug('MAC Core: Zip file path: ' . $zip_file);
        
        // Bỏ verify checksum
        if (!function_exists('unzip_file')) {
            // $this->log_debug('MAC Core: Loading WordPress file functions...');
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
        
        $tmp_dir = WP_CONTENT_DIR . '/' . $addon_slug . '-install-tmp-' . time();
        // $this->log_debug('MAC Core: Creating temp directory: ' . $tmp_dir);
        if (!wp_mkdir_p($tmp_dir)) {
            // $this->log_debug('MAC Core: Failed to create temp directory');
            @unlink($zip_file);
            return array('success' => false, 'message' => 'Failed to create temporary directory.');
        }
        // $this->log_debug('MAC Core: Temp directory created successfully');
        
        // $this->log_debug('MAC Core: Starting unzip process...');
        
        // Try to get filesystem credentials
        $creds = request_filesystem_credentials('', '', false, $tmp_dir);
        if ($creds) {
            // $this->log_debug('MAC Core: Got filesystem credentials');
            if (!WP_Filesystem($creds, $tmp_dir)) {
                // $this->log_debug('MAC Core: Failed to initialize filesystem with credentials');
            } else {
                // $this->log_debug('MAC Core: Filesystem initialized successfully');
            }
        } else {
            // $this->log_debug('MAC Core: No filesystem credentials available');
        }
        
        $unzipped = unzip_file($zip_file, $tmp_dir);
        @unlink($zip_file);
        // $this->log_debug('MAC Core: Unzip result: ' . print_r($unzipped, true));
        
        if (is_wp_error($unzipped)) {
            // $this->log_debug('MAC Core: WordPress unzip failed, trying PHP ZipArchive...');
            
            // Fallback to PHP ZipArchive
            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                $res = $zip->open($zip_file);
                if ($res === TRUE) {
                    // $this->log_debug('MAC Core: ZipArchive opened successfully');
                    $zip->extractTo($tmp_dir);
                    $zip->close();
                    // $this->log_debug('MAC Core: ZipArchive extraction successful');
                    $unzipped = true;
                } else {
                    // $this->log_debug('MAC Core: ZipArchive failed with code: ' . $res);
                    $this->delete_directory($tmp_dir);
                    return array('success' => false, 'message' => 'Failed to unzip package: ZipArchive error ' . $res);
                }
            } else {
                // $this->log_debug('MAC Core: ZipArchive not available');
                $this->delete_directory($tmp_dir);
                return array('success' => false, 'message' => 'Failed to unzip package: ' . $unzipped->get_error_message());
            }
        }
        
        if ($unzipped) {
            // $this->log_debug('MAC Core: Unzip successful');
        }
        
        // Get config to determine needle file
        $config = $this->get_plugin_config($addon_slug);
        if (!$config) {
            $this->delete_directory($tmp_dir);
            return array('success' => false, 'message' => 'Plugin configuration not found.');
        }
        
        $needle_file = basename($config['file']);
        // $this->log_debug('MAC Core: Looking for ' . $needle_file . ' in temp directory...');
        $plugin_dir = $this->locate_unzipped_plugin_dir($tmp_dir, $needle_file);
        // $this->log_debug('MAC Core: Plugin directory found: ' . ($plugin_dir ? $plugin_dir : 'NOT FOUND'));
        if (!$plugin_dir) {
            // $this->log_debug('MAC Core: ' . $needle_file . ' not found in unzipped files');
            $this->delete_directory($tmp_dir);
            return array('success' => false, 'message' => 'Unzipped package invalid: ' . $needle_file . ' not found');
        }
        // Continue with moving plugin to final location
        $temp_dir = $tmp_dir;
        
        // $this->log_debug('MAC Core: Temp dir: ' . $temp_dir);
        // $this->log_debug('MAC Core: Plugin dir: ' . $plugin_dir);
        // $this->log_debug('MAC Core: Config: ' . print_r($config, true));
        
        // Move plugin to final location
        $final_plugin_path = WP_PLUGIN_DIR . '/' . $config['slug'];
        // $this->log_debug('MAC Core: Moving plugin to: ' . $final_plugin_path);
        
        // Check if destination already exists
        if (file_exists($final_plugin_path)) {
            // $this->log_debug('MAC Core: Removing existing plugin directory');
            $this->delete_directory($final_plugin_path);
        }
        
        // Move the plugin directory
        // $this->log_debug('MAC Core: Starting move operation...');
        $result = rename($plugin_dir, $final_plugin_path);
        // $this->log_debug('MAC Core: Move result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        
        if (!$result) {
            $error = error_get_last();
            // $this->log_debug('MAC Core: Failed to move plugin directory. Error: ' . ($error ? $error['message'] : 'Unknown error'));
            $this->delete_directory($temp_dir);
            return array(
                'success' => false,
                'message' => 'Failed to move plugin to final location. Please check file permissions.'
            );
        }
        
        // Clean up temporary directory
        // $this->log_debug('MAC Core: Cleaning up temp directory');
        $this->delete_directory($temp_dir);
        
        // Get plugin version from installed file
        $plugin_file = $final_plugin_path . '/' . basename($config['file']);
        $version = 'unknown';
        if (file_exists($plugin_file)) {
            if (!function_exists('get_plugin_data')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugin_data = get_plugin_data($plugin_file, false, false);
            if (!empty($plugin_data['Version'])) {
                $version = $plugin_data['Version'];
            }
        }
        
        // $this->log_debug('MAC Core: Plugin installation completed successfully');
        return array(
            'success' => true,
            'message' => 'Plugin installed successfully!',
            'version' => $version
        );
    }

    private function locate_unzipped_plugin_dir($base_dir, $needle_file) {
        // When multiple copies of $needle_file exist (e.g., two mac-menu.php),
        // choose the directory that looks like the real plugin root.
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base_dir, \FilesystemIterator::SKIP_DOTS));
        $candidates = array();

        foreach ($iterator as $file) {
            if (!$file->isFile()) { continue; }
            if (strtolower($file->getFilename()) !== strtolower($needle_file)) { continue; }

            $dir = dirname($file->getPathname());
            $score = 0;
            $baseName = basename($dir);

            // Heuristics to detect the correct plugin root
            if ($baseName === 'mac-menu') { $score += 3; }
            if ($baseName === 'mac-core') { $score += 3; }
            $score += is_dir($dir . '/includes') ? 2 : 0;
            $score += is_dir($dir . '/blocks') ? 2 : 0;
            $score += is_dir($dir . '/public') ? 2 : 0;
            $score += is_dir($dir . '/admin') ? 1 : 0;
            $score += file_exists($dir . '/readme.txt') ? 1 : 0;

            // If this mac-menu.php declares a WP plugin header, give bonus
            $headerBonus = 0;
            $contents = @file_get_contents($file->getPathname());
            if ($contents !== false && (stripos($contents, 'Plugin Name:') !== false)) {
                $headerBonus = 2;
            }
            $score += $headerBonus;

            // Track candidate
            $candidates[] = array('dir' => $dir, 'score' => $score);
        }

        if (empty($candidates)) {
            return false;
        }

        // Pick the highest-score candidate
        usort($candidates, function($a, $b) {
            if ($a['score'] === $b['score']) { return 0; }
            return ($a['score'] > $b['score']) ? -1 : 1;
        });

        // Debug log chosen directory and scores for diagnostics
        if (function_exists('error_log')) {
            $summary = array_map(function($c) { return $c['dir'] . ' (score=' . $c['score'] . ')'; }, $candidates);
            // $this->log_debug('MAC Core: locate_unzipped_plugin_dir candidates: ' . implode(' | ', $summary));
            // $this->log_debug('MAC Core: locate_unzipped_plugin_dir chosen: ' . $candidates[0]['dir']);
        }

        return $candidates[0]['dir'];
    }
    
    /**
     * Activate plugin after installation
     */
    public function activate_plugin($addon_slug) {
        try {
            // $this->log_debug('MAC Core: Starting activation for ' . $addon_slug);
            // $this->log_debug('MAC Core: Memory usage: ' . memory_get_usage(true) . ' bytes');
            // $this->log_debug('MAC Core: Memory limit: ' . ini_get('memory_limit'));
        
        $config = $this->get_plugin_config($addon_slug);
        if (!$config) {
            // $this->log_debug('MAC Core: Plugin configuration not found for ' . $addon_slug);
            return array(
                'success' => false,
                'message' => 'Plugin configuration not found.'
            );
        }
        
        // Check if plugin file exists
        $plugin_file = WP_PLUGIN_DIR . '/' . $config['file'];
        // $this->log_debug('MAC Core: Checking plugin file: ' . $plugin_file);
        if (!file_exists($plugin_file)) {
            // $this->log_debug('MAC Core: Plugin file not found: ' . $plugin_file);
            return array(
                'success' => false,
                'message' => 'Plugin file not found: ' . $config['file']
            );
        }
        // $this->log_debug('MAC Core: Plugin file exists, proceeding with activation');
        
        // Check if plugin is already active
        if ($this->is_plugin_active($addon_slug)) {
            // $this->log_debug('MAC Core: Plugin is already active');
            return array(
                'success' => true,
                'message' => 'Plugin is already active.'
            );
        }
        
        // Check plugin requirements
        // $this->log_debug('MAC Core: Checking plugin requirements...');
        $requirements = $this->check_plugin_requirements($addon_slug);
        // $this->log_debug('MAC Core: Requirements check result: ' . print_r($requirements, true));
        if (isset($requirements['errors']) && !empty($requirements['errors'])) {
            // $this->log_debug('MAC Core: Plugin requirements not met: ' . implode(', ', $requirements['errors']));
            return array(
                'success' => false,
                'message' => 'Plugin requirements not met: ' . implode(', ', $requirements['errors'])
            );
        }
        // $this->log_debug('MAC Core: Plugin requirements check passed');
        
        if (!function_exists('activate_plugin')) {
            // $this->log_debug('MAC Core: Loading WordPress plugin functions...');
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            // $this->log_debug('MAC Core: WordPress plugin functions loaded');
        }
        
        // Deactivate any conflicting plugins first
        if (is_plugin_active('mac-menu/mac-menu.php')) {
            // $this->log_debug('MAC Core: Deactivating existing mac-menu plugin');
            deactivate_plugins('mac-menu/mac-menu.php');
            // $this->log_debug('MAC Core: Existing mac-menu plugin deactivated');
        }
        
        // Clean up any remaining conflicting files
        // $this->log_debug('MAC Core: Starting cleanup of conflicting files...');
        $this->cleanup_conflicting_files($addon_slug);
        // $this->log_debug('MAC Core: Cleanup of conflicting files completed');
        
        // Clear function and class cache for MAC Menu
        if ($addon_slug === 'mac-menu') {
            // $this->log_debug('MAC Core: Starting cache clearing for MAC Menu...');
            // Force WordPress to reload function and class definitions
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
                // $this->log_debug('MAC Core: WordPress cache flushed');
            }
            
            // Clear autoloader cache
            if (function_exists('spl_autoload_functions')) {
                $autoloaders = spl_autoload_functions();
                // $this->log_debug('MAC Core: Found ' . count($autoloaders) . ' autoloaders');
                
                // Don't unregister autoloaders as it can cause fatal errors
                // Just log the count for debugging
                // $this->log_debug('MAC Core: Skipping autoloader unregistration to prevent fatal errors');
            }
            
            // $this->log_debug('MAC Core: Cache clearing completed for MAC Menu activation');
        }
        
        // Check for function conflicts
        // $this->log_debug('MAC Core: Checking for function conflicts...');
        $function_conflicts = $this->check_function_conflicts();
        // $this->log_debug('MAC Core: Function conflicts: ' . print_r($function_conflicts, true));
        
        // $this->log_debug('MAC Core: Checking for constant conflicts...');
        $constant_conflicts = $this->check_constant_conflicts();
        // $this->log_debug('MAC Core: Constant conflicts: ' . print_r($constant_conflicts, true));
        
        if (!empty($function_conflicts) || !empty($constant_conflicts)) {
            $all_conflicts = array_merge($function_conflicts, $constant_conflicts);
            // $this->log_debug('MAC Core: Conflicts detected: ' . implode(', ', $all_conflicts));
            
            // For MAC Menu, try to fix constant conflicts by redefining them
            if ($addon_slug === 'mac-menu' && !empty($constant_conflicts)) {
                // $this->log_debug('MAC Core: Attempting to fix constant conflicts for MAC Menu');
                $this->fix_constant_conflicts();
            }
            
            return array(
                'success' => false,
                'message' => 'Conflicts detected. Please deactivate conflicting plugins first: ' . implode(', ', $all_conflicts)
            );
        }
        
        // $this->log_debug('MAC Core: Activating plugin: ' . $config['file']);
        // $this->log_debug('MAC Core: About to call WordPress activate_plugin() function');
        
        // Capture any output during activation
        ob_start();
        // $this->log_debug('MAC Core: Starting output buffering for activation');
        $result = activate_plugin($config['file']);
        // $this->log_debug('MAC Core: activate_plugin() call completed');
        $output = ob_get_clean();
        // $this->log_debug('MAC Core: Output buffering ended, captured output length: ' . strlen($output));
        
        // Log any unexpected output
        if (!empty($output)) {
            // $this->log_debug('MAC Core: Unexpected output during activation: ' . $output);
        }
        
        // $this->log_debug('MAC Core: Checking activation result...');
        // $this->log_debug('MAC Core: Result type: ' . gettype($result));
        if (is_wp_error($result)) {
            // $this->log_debug('MAC Core: Activation failed with WP_Error: ' . $result->get_error_message());
            // $this->log_debug('MAC Core: Error code: ' . $result->get_error_code());
            
            // For MAC Menu, try to force activate by ignoring output
            if ($addon_slug === 'mac-menu' && strpos($result->get_error_message(), 'unexpected output') !== false) {
                // $this->log_debug('MAC Core: Attempting to force activate MAC Menu despite output issues');
                
                // Try to activate again with output buffering
                ob_start();
                $force_result = activate_plugin($config['file'], '', false, true);
                ob_end_clean();
                
                if (!is_wp_error($force_result)) {
                    // $this->log_debug('MAC Core: Force activation successful');
                    return array(
                        'success' => true,
                        'message' => 'Plugin activated successfully! (Force activated due to output issues)',
                        'force_activated' => true
                    );
                }
                
                // If force activation still fails, try to manually activate by updating options
                // $this->log_debug('MAC Core: Force activation failed, trying manual activation');
                $manual_result = $this->manual_activate_plugin($addon_slug);
                if ($manual_result['success']) {
                    return array(
                        'success' => true,
                        'message' => 'Plugin activated successfully! (Manually activated due to output issues)',
                        'force_activated' => true,
                        'manual_activation' => true
                    );
                }
            }
            
            return array(
                'success' => false,
                'message' => 'Activation failed: ' . $result->get_error_message()
            );
        }
        
        // $this->log_debug('MAC Core: Plugin activated successfully!');
        // $this->log_debug('MAC Core: Activation result: ' . print_r($result, true));
        
        // Force reload to clear function and class cache
        if ($addon_slug === 'mac-menu') {
            // $this->log_debug('MAC Core: Forcing reload to clear function and class cache');
            
            // Restore MAC Core files after successful activation
            $this->restore_compatibility_file($addon_slug);
            
            return array(
                'success' => true,
                'message' => 'Plugin activated successfully! Please refresh the page to complete activation.',
                'reload_required' => true,
                'force_restart' => true
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Plugin activated successfully!'
        );
        
        } catch (\Exception $e) {
            // $this->log_debug('MAC Core: Fatal error in activation: ' . $e->getMessage());
            // $this->log_debug('MAC Core: Stack trace: ' . $e->getTraceAsString());
            return array(
                'success' => false,
                'message' => 'Activation failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Handle AJAX install request
     */
    public function handle_install_plugin_ajax() {
        // Ensure any stray output doesn't break JSON
        ob_start();
        try {
            // Check nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
                ob_end_clean();
                wp_send_json_error('Security check failed.');
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                ob_end_clean();
                wp_send_json_error('Insufficient permissions.');
            }

            $addon_slug = sanitize_text_field($_POST['plugin_slug']);

            // Check if plugin is already installed
            if ($this->is_plugin_installed($addon_slug)) {
                ob_end_clean();
                wp_send_json_error('Plugin is already installed.');
            }

            // Check CRM license instead of GitHub token
            global $mac_core_crm_api_manager;
            if (!$mac_core_crm_api_manager) {
                $mac_core_crm_api_manager = \MAC_Core\CRM_API_Manager::get_instance();
            }

            if (!$mac_core_crm_api_manager || !$mac_core_crm_api_manager->is_license_valid()) {
                ob_end_clean();
                wp_send_json_error('License not valid. Please check your license key and domain validation.');
            }

            // Install plugin
            $result = $this->install_plugin($addon_slug);

            if ($result['success']) {
                // Skip auto-activation, just report success
                $result['message'] = 'Plugin downloaded and installed successfully! You can now activate it manually.';
                $result['manual_activation_required'] = true;
                $output = ob_get_clean();
                if (!empty($output)) {
                    // Discard stray output
                }
                wp_send_json_success($result);
            } else {
                $output = ob_get_clean();
                if (!empty($output)) {
                    // Discard stray output
                }
                wp_send_json_error($result['message']);
            }

        } catch (\Throwable $t) {
            // Clean buffer and return JSON error instead of HTML fatal
            if (ob_get_length()) { ob_end_clean(); }
            error_log('MAC Core: Installation throwable: ' . $t->getMessage());
            wp_send_json_error('Installation failed: ' . $t->getMessage());
        }
        // Fallback buffer cleanup (shouldn't reach here due to wp_send_json_* exit)
        if (ob_get_length()) { ob_end_clean(); }
    }
    

    
    /**
     * Handle AJAX status check
     */
    public function handle_check_install_status_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        $addon_slug = sanitize_text_field($_POST['plugin_slug']);
        
        global $mac_core_crm_api_manager;
        if (!$mac_core_crm_api_manager) {
            $mac_core_crm_api_manager = \MAC_Core\CRM_API_Manager::get_instance();
        }
        
        $status = array(
            'installed' => $this->is_plugin_installed($addon_slug),
            'active' => $this->is_plugin_active($addon_slug),
            'has_token' => ($mac_core_crm_api_manager && $mac_core_crm_api_manager->is_license_valid())
        );
        
        wp_send_json_success($status);
    }
    
    /**
     * Check system requirements and permissions
     */
    public function check_system_requirements() {
        $requirements = array();
        
        // Check if wp-content/plugins is writable
        $plugins_dir = WP_PLUGIN_DIR;
        $requirements['plugins_dir_writable'] = is_writable($plugins_dir);
        $requirements['plugins_dir_path'] = $plugins_dir;
        
        // Check if wp-content is writable (for temp files)
        $content_dir = WP_CONTENT_DIR;
        $requirements['content_dir_writable'] = is_writable($content_dir);
        $requirements['content_dir_path'] = $content_dir;
        
        // Check PHP version
        $requirements['php_version'] = PHP_VERSION;
        $requirements['php_version_ok'] = version_compare(PHP_VERSION, '7.0', '>=');
        
        // Check memory limit
        $memory_limit = ini_get('memory_limit');
        $requirements['memory_limit'] = $memory_limit;
        
        // Check max execution time
        $max_execution_time = ini_get('max_execution_time');
        $requirements['max_execution_time'] = $max_execution_time;
        
        // Check if curl is available
        $requirements['curl_available'] = function_exists('curl_init');
        
        // Check if file_get_contents can access URLs
        $requirements['allow_url_fopen'] = ini_get('allow_url_fopen');
        
        return $requirements;
    }
    
    /**
     * Debug all GitHub tokens
     */
    
    /**
     * Check plugin dependencies and requirements
     */
    public function check_plugin_requirements($addon_slug) {
        $requirements = array();
        
        // Check if MAC Core is active (required for all plugins)
        if (!function_exists('mac_core_get_version') && !class_exists('MAC_Core\Admin\Admin')) {
            $requirements['mac_core_active'] = false;
            $requirements['errors'][] = 'MAC Core plugin is not active';
        } else {
            $requirements['mac_core_active'] = true;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            $requirements['php_version_ok'] = false;
            $requirements['errors'][] = 'PHP version ' . PHP_VERSION . ' is too old. Need 7.0+';
        } else {
            $requirements['php_version_ok'] = true;
        }
        
        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.0', '<')) {
            $requirements['wp_version_ok'] = false;
            $requirements['errors'][] = 'WordPress version ' . $wp_version . ' is too old. Need 5.0+';
        } else {
            $requirements['wp_version_ok'] = true;
        }
        
        // Check if Elementor is active (for MAC Menu)
        if ($addon_slug === 'mac-menu') {
            if (!class_exists('Elementor\Plugin')) {
                $requirements['elementor_active'] = false;
                $requirements['warnings'][] = 'Elementor plugin is not active (recommended for MAC Menu)';
            } else {
                $requirements['elementor_active'] = true;
            }
        }
        
        return $requirements;
    }
    
    /**
     * Check for function conflicts
     */
    private function check_function_conflicts() {
        $conflicts = array();
        
        // Check for conflicting functions
        $conflicting_functions = array(
            'kvp_enqueue_scripts',
            'kvp_handle_check_request',
            'kvp_handle_check_request_url',
            'kvp_handle_ajax_request'
        );
        
        foreach ($conflicting_functions as $function) {
            if (function_exists($function)) {
                $conflicts[] = $function;
            }
        }
        
        return $conflicts;
    }
    
    /**
     * Remove conflicting functions
     */
    private function remove_conflicting_functions() {
        $conflicts = $this->check_function_conflicts();
        
        if (!empty($conflicts)) {
            // $this->log_debug('MAC Core: Found conflicting functions: ' . implode(', ', $conflicts));
            
            // Note: We can't actually remove functions in PHP, but we can log them
            // The real solution is to prevent them from being declared in the first place
            foreach ($conflicts as $function) {
                // $this->log_debug('MAC Core: Function conflict detected: ' . $function);
            }
        }
        
        return $conflicts;
    }
    
    /**
     * Check for constant conflicts
     */
    private function check_constant_conflicts() {
        $conflicts = array();
        
        // Check for conflicting constants
        $conflicting_constants = array(
            'MAC_MENU_VALIDATE_KEY',
            'MAC_MENU_VALIDATE_URL',
            'MAC_MENU_REGISTER_DOMAIN'
        );
        
        foreach ($conflicting_constants as $constant) {
            if (defined($constant)) {
                $conflicts[] = $constant;
            }
        }
        
        return $conflicts;
    }
    
    /**
     * Fix constant conflicts by redefining them
     */
    private function fix_constant_conflicts() {
        // Note: We cannot undefine constants in PHP, but we can log the issue
        // $this->log_debug('MAC Core: Constant conflicts detected - MAC Menu will define its own constants');
        // $this->log_debug('MAC Core: This is expected behavior and should not cause activation failure');
    }
    
    /**
     * Fix potential output issues in MAC Menu files
     */
    private function fix_mac_menu_output_issues() {
        try {
            $mac_menu_file = WP_PLUGIN_DIR . '/mac-menu/mac-menu.php';
            $domain_manager_file = WP_PLUGIN_DIR . '/mac-menu/domain-manager.php';
            $update_plugin_file = WP_PLUGIN_DIR . '/mac-menu/update-plugin.php';
        
        if (file_exists($mac_menu_file)) {
            // $this->log_debug('MAC Core: Checking MAC Menu main file for output issues');
            
            // Read the file content with error handling
            $content = @file_get_contents($mac_menu_file);
            if ($content === false) {
                // $this->log_debug('MAC Core: Failed to read MAC Menu main file');
                return;
            }
            
            // Check for potential output issues
            if (strpos($content, 'echo') !== false || strpos($content, 'print') !== false) {
                // $this->log_debug('MAC Core: Found potential output statements in MAC Menu main file');
                // $this->log_debug('MAC Core: This may cause "unexpected output" error during activation');
            }
            
            // Check for constants that need protection
            if (strpos($content, 'define(') !== false && strpos($content, 'if (!defined(') === false) {
                // $this->log_debug('MAC Core: Found unprotected constants in MAC Menu main file');
                // $this->log_debug('MAC Core: This may cause "constant already defined" warnings');
            }
        }
        
        if (file_exists($domain_manager_file)) {
            // $this->log_debug('MAC Core: Checking MAC Menu domain manager file for output issues');
            
            // Read the file content
            $content = file_get_contents($domain_manager_file);
            
            // Check for potential output issues
            if (strpos($content, 'echo') !== false || strpos($content, 'print') !== false) {
                // $this->log_debug('MAC Core: Found potential output statements in MAC Menu domain manager file');
                // $this->log_debug('MAC Core: This may cause "unexpected output" error during activation');
            }
        }
        
        if (file_exists($update_plugin_file)) {
            // $this->log_debug('MAC Core: Checking MAC Menu update plugin file for output issues');
            
            // Read the file content with error handling
            $content = @file_get_contents($update_plugin_file);
            if ($content === false) {
                // $this->log_debug('MAC Core: Failed to read MAC Menu update plugin file');
                return;
            }
            
            // Check for potential output issues
            if (strpos($content, 'echo') !== false || strpos($content, 'print') !== false) {
                // $this->log_debug('MAC Core: Found potential output statements in MAC Menu update plugin file');
                // $this->log_debug('MAC Core: This may cause "unexpected output" error during activation');
            }
            
            // Check for constants that need protection
            if (strpos($content, 'define(') !== false && strpos($content, 'if (!defined(') === false) {
                // $this->log_debug('MAC Core: Found unprotected constants in MAC Menu update plugin file');
                // $this->log_debug('MAC Core: This may cause "constant already defined" warnings');
            }
        }
        
        } catch (\Exception $e) {
            // $this->log_debug('MAC Core: Error in fix_mac_menu_output_issues: ' . $e->getMessage());
            // $this->log_debug('MAC Core: Stack trace: ' . $e->getTraceAsString());
        }
    }
    
    /**
     * Manually activate plugin by updating WordPress options
     */
    private function manual_activate_plugin($addon_slug) {
        // $this->log_debug('MAC Core: Attempting manual activation for ' . $addon_slug);
        
        $config = $this->get_plugin_config($addon_slug);
        if (!$config) {
            return array('success' => false, 'message' => 'Plugin configuration not found');
        }
        
        // Get active plugins
        $active_plugins = get_option('active_plugins', array());
        
        // Add plugin to active plugins list
        if (!in_array($config['file'], $active_plugins)) {
            $active_plugins[] = $config['file'];
            update_option('active_plugins', $active_plugins);
            // $this->log_debug('MAC Core: Added ' . $config['file'] . ' to active plugins');
        }
        
        // Also update active_sitewide_plugins for multisite
        if (is_multisite()) {
            $active_sitewide_plugins = get_site_option('active_sitewide_plugins', array());
            $active_sitewide_plugins[$config['file']] = time();
            update_site_option('active_sitewide_plugins', $active_sitewide_plugins);
            // $this->log_debug('MAC Core: Added ' . $config['file'] . ' to active sitewide plugins');
        }
        
        // $this->log_debug('MAC Core: Manual activation completed for ' . $addon_slug);
        return array('success' => true, 'message' => 'Plugin manually activated');
    }
    
    
    /**
     * Handle AJAX system requirements check
     */
    public function handle_check_system_requirements_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        $requirements = $this->check_system_requirements();
        wp_send_json_success($requirements);
    }
    
    /**
     * Handle AJAX debug tokens
     */
    public function handle_debug_tokens_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        wp_send_json_success(array('message' => 'GitHub functionality removed - using CRM only'));
    }
    
    /**
     * Handle AJAX test token
     */
    
    /**
     * Force remove plugin completely
     */
    public function force_remove_plugin($addon_slug) {
        // $this->log_debug('MAC Core: Force removing plugin: ' . $addon_slug);
        
        $config = $this->get_plugin_config($addon_slug);
        if (!$config) {
            return array(
                'success' => false,
                'message' => 'Plugin configuration not found.'
            );
        }
        
        // Deactivate plugin first
        if ($this->is_plugin_active($addon_slug)) {
            if (!function_exists('deactivate_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            deactivate_plugins($config['file']);
            // $this->log_debug('MAC Core: Plugin deactivated: ' . $config['file']);
        }
        
        // Remove plugin directory completely
        $plugin_dir = WP_PLUGIN_DIR . '/' . $config['slug'];
        if (is_dir($plugin_dir)) {
            $this->delete_directory($plugin_dir);
            // $this->log_debug('MAC Core: Plugin directory removed: ' . $plugin_dir);
        }
        
        // Restore compatibility file if needed
        $this->restore_compatibility_file($addon_slug);
        
        return array(
            'success' => true,
            'message' => 'Plugin removed completely.'
        );
    }
    
    /**
     * Restore compatibility and domain manager files when MAC Menu is removed
     */
    private function restore_compatibility_file($addon_slug) {
        if ($addon_slug === 'mac-menu') {
            // Restore compatibility file
            $compatibility_file = MAC_CORE_PATH . 'includes/mac-menu-compatibility.php';
            $backup_file = $compatibility_file . '.disabled';
            
            if (file_exists($backup_file) && !file_exists($compatibility_file)) {
                // $this->log_debug('MAC Core: Restoring compatibility file: ' . $compatibility_file);
                rename($backup_file, $compatibility_file);
            }
            
            // Restore domain manager file
            $domain_manager_file = MAC_CORE_PATH . 'includes/class-mac-menu-domain-manager.php';
            $backup_file = $domain_manager_file . '.disabled';
            
            if (file_exists($backup_file) && !file_exists($domain_manager_file)) {
                // $this->log_debug('MAC Core: Restoring domain manager file: ' . $domain_manager_file);
                rename($backup_file, $domain_manager_file);
            }
            
            // Restore MAC Core main file
            $mac_core_main_file = MAC_CORE_PATH . 'mac-core.php';
            $backup_file = $mac_core_main_file . '.disabled';
            
            if (file_exists($backup_file) && !file_exists($mac_core_main_file)) {
                // $this->log_debug('MAC Core: Restoring MAC Core main file: ' . $mac_core_main_file);
                rename($backup_file, $mac_core_main_file);
            }
        }
    }
    
    /**
     * Handle AJAX activate plugin
     */
    public function handle_activate_plugin_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        $addon_slug = sanitize_text_field($_POST['plugin_slug']);
        $result = $this->activate_plugin($addon_slug);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Handle AJAX force remove plugin
     */
    public function handle_force_remove_plugin_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        $addon_slug = sanitize_text_field($_POST['plugin_slug']);
        $result = $this->force_remove_plugin($addon_slug);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
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
    
    /**
     * Handle AJAX check URL
     */
    public function handle_check_url_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        // $this->log_debug('MAC Core: AJAX Check URL requested');
        
        // Check current domain status
        $current_key = get_option('mac_domain_valid_key', '');
        $current_status = get_option('mac_domain_valid_status', '');
        
        // Clean up invalid values
        if ($current_key === '0' || $current_key === null) {
            $current_key = '';
        }
        if ($current_status === '0' || $current_status === null) {
            $current_status = '';
        }
        
        // $this->log_debug('MAC Core: Current key: ' . ($current_key ?: 'empty'));
        // $this->log_debug('MAC Core: Current status: ' . ($current_status ?: 'empty'));
        
        // Call the URL check function
        if (function_exists('kvp_handle_check_request_url')) {
            kvp_handle_check_request_url();
            
            // Check if status changed after the check
            $new_key = get_option('mac_domain_valid_key', '');
            $new_status = get_option('mac_domain_valid_status', '');
            
            if ($new_key !== $current_key || $new_status !== $current_status) {
                wp_send_json_success('Domain status updated! Key: ' . ($new_key ?: 'empty') . ', Status: ' . ($new_status ?: 'empty'));
            } else {
                wp_send_json_success('URL check completed. No changes detected.');
            }
        } else {
            wp_send_json_error('URL check function not available.');
        }
    }
    
    /**
     * Handle AJAX test validate URL
     */
    public function handle_test_validate_url_ajax() {
        ob_start();
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            ob_end_clean();
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_send_json_error('Insufficient permissions.');
        }
        
        try {
            if (function_exists('kvp_handle_check_request_url')) {
                kvp_handle_check_request_url();
                ob_end_clean();
                wp_send_json_success('Test Validate URL completed successfully. Check error log for detailed API request/response information.');
            } else {
                ob_end_clean();
                wp_send_json_error('kvp_handle_check_request_url function not available.');
            }
        } catch (\Exception $e) {
            ob_end_clean();
            wp_send_json_error('An error occurred: ' . $e->getMessage());
        }
    }

    public function handle_install_mac_menu_ajax() {
        // $this->log_debug('MAC Core: Starting handle_install_mac_menu_ajax');
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            // $this->log_debug('MAC Core: Security check failed');
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            // $this->log_debug('MAC Core: Insufficient permissions');
            wp_send_json_error('Insufficient permissions.');
        }
        
        // Check if CRM connection is active
        global $mac_core_crm_api_manager;
        // $this->log_debug('MAC Core: Checking CRM connection...');
        if (!$mac_core_crm_api_manager || !$mac_core_crm_api_manager->is_license_valid()) {
            // $this->log_debug('MAC Core: CRM connection not active');
            wp_send_json_error('CRM connection must be active to install MAC Menu.');
        }
        // $this->log_debug('MAC Core: CRM connection is active');
        
        // Check if MAC Menu already exists
        $mac_menu_path = WP_PLUGIN_DIR . '/mac-menu/mac-menu.php';
        if (file_exists($mac_menu_path)) {
            // $this->log_debug('MAC Core: MAC Menu already exists at: ' . $mac_menu_path);
            wp_send_json_error('MAC Menu is already installed. Please activate it instead.');
        }
        // $this->log_debug('MAC Core: MAC Menu not found, proceeding with installation');
        
        // Install MAC Menu plugin from CRM
        // $this->log_debug('MAC Core: Calling install_plugin for mac-menu');
        $result = $this->install_plugin('mac-menu');
        
        // $this->log_debug('MAC Core: Install result: ' . print_r($result, true));
        
        if ($result['success']) {
            // $this->log_debug('MAC Core: Installation successful');
            wp_send_json_success('MAC Menu installed successfully!');
        } else {
            // $this->log_debug('MAC Core: Installation failed: ' . $result['message']);
            wp_send_json_error('Failed to install MAC Menu: ' . $result['message']);
        }
    }

    public function handle_activate_mac_menu_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        // Activate MAC Menu plugin
        $plugin_path = 'mac-menu/mac-menu.php';
        
        if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin_path)) {
            wp_send_json_error('MAC Menu plugin not found.');
        }
        
        $result = activate_plugin($plugin_path);
        
        if (is_wp_error($result)) {
            wp_send_json_error('Failed to activate MAC Menu: ' . $result->get_error_message());
        } else {
            wp_send_json_success('MAC Menu activated successfully!');
        }
    }

    public function handle_reset_options_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        // Reset all MAC Menu options
        update_option('mac_domain_valid_key', '');
        update_option('mac_domain_valid_status', '');
        update_option('mac_menu_github_key', '');
        
        wp_send_json_success('Options reset successfully! Page will reload.');
    }

    /**
     * Restore MAC Core if it was disabled
     */
    public function restore_mac_core_if_disabled() {
        $mac_core_main_file = MAC_CORE_PATH . 'mac-core.php';
        $disabled_file = $mac_core_main_file . '.disabled';
        
        if (!file_exists($mac_core_main_file) && file_exists($disabled_file)) {
            // $this->log_debug('MAC Core: Restoring disabled MAC Core main file');
            rename($disabled_file, $mac_core_main_file);
            return true;
        }
        
        return false;
    }

    public function handle_restore_mac_core_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        $restored = $this->restore_mac_core_if_disabled();
        
        if ($restored) {
            wp_send_json_success('MAC Core restored successfully! Please refresh the page.');
        } else {
            wp_send_json_error('MAC Core is already active or no disabled file found.');
        }
    }

    public function handle_check_update_mac_menu_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        // Check if MAC Menu exists
        $mac_menu_path = WP_PLUGIN_DIR . '/mac-menu/mac-menu.php';
        if (!file_exists($mac_menu_path)) {
            wp_send_json_error('MAC Menu is not installed. Please install it first.');
        }
        
        // Check if CRM connection is active
        global $mac_core_crm_api_manager;
        if (!$mac_core_crm_api_manager || !$mac_core_crm_api_manager->is_license_valid()) {
            wp_send_json_error('CRM connection must be active to check for updates.');
        }
        
        // Check for updates using CRM
        $crm = CRM_API_Manager::get_instance();
        $result = $crm->check_update('mac-menu');
        
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error('Failed to check for updates: ' . $result['message']);
        }
    }

    
    // Removed custom force delete implementation
    
    /**
     * Download and update a plugin via CRM (supports mac-menu, mac-core)
     */
    private function update_plugin_via_crm($slug, $needle_file) {
        // Set longer timeout for update operations
        set_time_limit(300); // 5 minutes
        
        try {
            // Begin update transaction
            $this->begin_update_transaction($slug);
            
            // Step 1: Download plugin from CRM
            $download_result = $this->download_plugin_from_crm_for_update($slug);
            if (!$download_result['success']) {
                $this->rollback_update_transaction();
                return $download_result;
            }
            
            // Step 2: Create backup of current plugin
            $backup_result = $this->create_plugin_backup($slug);
            if (!$backup_result['success']) {
                $this->rollback_update_transaction();
                return $backup_result;
            }
            
            // Step 3: Extract and verify new version
            $extract_result = $this->extract_new_version($download_result['zip_file'], $needle_file);
            if (!$extract_result['success']) {
                $this->rollback_update_transaction();
                return $extract_result;
            }
            
            // Step 4: Atomic replacement
            $replace_result = $this->atomic_replace_plugin($slug, $extract_result['plugin_dir']);
            if (!$replace_result['success']) {
                $this->rollback_update_transaction();
                return $replace_result;
            }
            
            // Step 5: Verify update success
            $verify_result = $this->verify_update_success($slug);
            if (!$verify_result['success']) {
                $this->rollback_update_transaction();
                return $verify_result;
            }
            
            // Step 6: Commit update - cleanup backup and temp files
            $this->commit_update_transaction();
            
            // Force activate plugin after successful update
            if (isset($this->auto_activate_requested) && $this->auto_activate_requested && $slug === $this->auto_activate_slug) {
                $plugin_main = $slug . '/' . $slug . '.php';
                if (function_exists('is_plugin_active') && !is_plugin_active($plugin_main)) {
                    $activation_result = activate_plugin($plugin_main, '', false, true);
                    if (is_wp_error($activation_result)) {
                        error_log('MAC Core: Failed to activate ' . $slug . ' after update: ' . $activation_result->get_error_message());
                        // Fallback: schedule for later activation
                        $pending = get_option('mac_core_pending_activate_plugins', array());
                        if (!is_array($pending)) { $pending = array(); }
                        if (!in_array($slug, $pending, true)) { $pending[] = $slug; }
                        update_option('mac_core_pending_activate_plugins', $pending, false);
                    } else {
                        error_log('MAC Core: ' . $slug . ' activated successfully after update');
                    }
                }
            }
            
            return array('success' => true, 'version' => $download_result['version']);
            
        } catch (\Exception $e) {
            // Automatic rollback on any exception
            $this->rollback_update_transaction();
            return array('success' => false, 'message' => 'Update failed with exception: ' . $e->getMessage());
        }
    }

    /**
     * Public wrapper for REST usage
     * Allows external callers (REST handlers) to trigger the CRM update pipeline
     * @param string $slug Plugin slug
     * @param string $needle_file Main plugin file name
     * @param bool $auto_activate Whether to auto-activate after update (default: false)
     */
    public function update_plugin_via_crm_public($slug, $needle_file, $auto_activate = false) {
        // Store auto-activate flag in a separate property to avoid being overwritten
        $this->auto_activate_requested = $auto_activate;
        $this->auto_activate_slug = $slug;
        
        // Use generic update for all plugins (including mac-core)
        return $this->update_plugin_via_crm($slug, $needle_file);
    }
    
    /**
     * Update mac-core without deactivation to avoid breaking the running plugin
     */
    private function update_mac_core_without_deactivation($slug, $needle_file) {
        set_time_limit(300); // 5 minutes
        
        try {
            // Begin update transaction
            $this->begin_update_transaction($slug);
            
            // Step 1: Download plugin from CRM
            $download_result = $this->download_plugin_from_crm_for_update($slug);
            if (!$download_result['success']) {
                $this->rollback_update_transaction();
                return $download_result;
            }
            
            // Step 2: Create backup of current plugin
            $backup_result = $this->create_plugin_backup($slug);
            if (!$backup_result['success']) {
                $this->rollback_update_transaction();
                return $backup_result;
            }
            
            // Step 3: Extract and verify new version
            $extract_result = $this->extract_new_version($download_result['zip_file'], $needle_file);
            if (!$extract_result['success']) {
                $this->rollback_update_transaction();
                return $extract_result;
            }
            
            // Step 4: Atomic replacement WITHOUT deactivation for mac-core
            $replace_result = $this->atomic_replace_plugin_without_deactivation($slug, $extract_result['plugin_dir']);
            if (!$replace_result['success']) {
                $this->rollback_update_transaction();
                return $replace_result;
            }
            
            // Step 5: Verify update success
            $verify_result = $this->verify_update_success($slug);
            if (!$verify_result['success']) {
                $this->rollback_update_transaction();
                return $verify_result;
            }
            
            // Step 6: Commit update - cleanup backup and temp files
            $this->commit_update_transaction();
            
            // For mac-core, always run activator to re-initialize
            if ($slug === 'mac-core') {
                require_once MAC_CORE_PATH . 'includes/class-activator.php';
                if (class_exists('MAC_Core\\Activator')) {
                    \MAC_Core\Activator::activate();
                    // // // // error_log(...MAC...);
                }
            }
            
            return array('success' => true, 'version' => $download_result['version']);
            
        } catch (\Exception $e) {
            // Automatic rollback on any exception
            $this->rollback_update_transaction();
            return array('success' => false, 'message' => 'Update failed with exception: ' . $e->getMessage());
        }
    }
    
    /**
     * Begin update transaction - initialize paths and check network
     */
    private function begin_update_transaction($slug) {
        // Check network stability
        if (!$this->check_network_connection()) {
            throw new \Exception('Network connection unstable. Please try again later.');
        }
        
        // Initialize transaction paths
        $this->update_transaction = array(
            'slug' => $slug,
            'backup_path' => WP_CONTENT_DIR . '/' . $slug . '-backup-' . time(),
            // Use wp-content for temp dir to avoid hosting restrictions on system temp
            'temp_dir' => WP_CONTENT_DIR . '/' . $slug . '-update-tmp-' . time(),
            'zip_file' => null,
            'plugin_dir' => null,
            'started' => true
        );
        
        // Create temp directory
        if (!wp_mkdir_p($this->update_transaction['temp_dir'])) {
            throw new \Exception('Failed to create temporary directory: ' . $this->update_transaction['temp_dir']);
        }
    }
    
    /**
     * Download plugin from CRM for updates
     */
    private function download_plugin_from_crm_for_update($slug) {
        $crm = CRM_API_Manager::get_instance();
        
        // Download plugin info
        $req = $crm->download_plugin($slug);
        if (!$req['success']) {
            return array('success' => false, 'message' => 'CRM request failed: ' . $req['message']);
        }
        
        $info = $req['data'];
        
        // Download file to temp
        $dl = $crm->download_file_to_tmp($info['download_url']);
        if (!$dl['success']) {
            return array('success' => false, 'message' => 'Download failed: ' . $dl['message']);
        }
        
        $zip_file = $dl['file'];
        
        // Verify ZIP file integrity
        if (!$this->verify_zip_file($zip_file)) {
            @unlink($zip_file);
            return array('success' => false, 'message' => 'Downloaded file is corrupted or invalid');
        }
        
        $this->update_transaction['zip_file'] = $zip_file;
        
        return array(
            'success' => true, 
            'zip_file' => $zip_file,
            'version' => isset($info['version']) ? $info['version'] : 'latest'
        );
    }
    
    /**
     * Create backup of current plugin
     */
    private function create_plugin_backup($slug) {
        $current_path = WP_PLUGIN_DIR . '/' . $slug;
        $backup_path = $this->update_transaction['backup_path'];
        
        if (!is_dir($current_path)) {
            return array('success' => false, 'message' => 'Current plugin directory not found: ' . $current_path);
        }
        
        // Copy current plugin to backup
        if (!$this->copy_directory($current_path, $backup_path)) {
            return array('success' => false, 'message' => 'Failed to create backup of current plugin');
        }
        
        // Verify backup integrity
        if (!$this->verify_backup_integrity($current_path, $backup_path)) {
            $this->delete_directory($backup_path);
            return array('success' => false, 'message' => 'Backup verification failed');
        }
        
        return array('success' => true, 'backup_path' => $backup_path);
    }
    
    /**
     * Extract and verify new version
     */
    private function extract_new_version($zip_file, $needle_file) {
        $tmp_dir = $this->update_transaction['temp_dir'];
        
        // Extract ZIP file
        $unzipped = false;
        
        // Try WordPress unzip_file first
        if (function_exists('unzip_file')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            
            $unzipped = unzip_file($zip_file, $tmp_dir);
            if (is_wp_error($unzipped)) {
                $unzipped = false; // Reset for ZipArchive fallback
            }
        }
        
        // Fallback to ZipArchive if WordPress unzip failed
        if (!$unzipped && class_exists('\ZipArchive')) {
            $zip = new \ZipArchive();
            $res = $zip->open($zip_file);
            if ($res === TRUE) {
                $zip->extractTo($tmp_dir);
                $zip->close();
                $unzipped = true;
            } else {
                throw new \Exception('Failed to unzip package: ZipArchive error ' . $res);
            }
        }
        
        // If both methods failed
        if (!$unzipped) {
            throw new \Exception('Failed to unzip package: No unzip method available');
        }
        
        // Clean up ZIP file after successful extraction
        @unlink($zip_file);
        
        // Locate the extracted plugin directory
        $plugin_dir = $this->locate_unzipped_plugin_dir($tmp_dir, $needle_file);
        if (!$plugin_dir) {
            throw new \Exception('Unzipped package invalid: ' . $needle_file . ' not found');
        }
        
        // Verify the new version is valid
        if (!$this->verify_new_version($plugin_dir, $needle_file)) {
            throw new \Exception('New version validation failed');
        }
        
        $this->update_transaction['plugin_dir'] = $plugin_dir;
        
        return array('success' => true, 'plugin_dir' => $plugin_dir);
    }
    
    /**
     * Atomic replacement of plugin using copy instead of rename
     * WITHOUT deactivation (for mac-core)
     */
    private function atomic_replace_plugin_without_deactivation($slug, $new_plugin_dir) {
        $current_path = WP_PLUGIN_DIR . '/' . $slug;
        
        // Check if current plugin directory exists and is writable
        if (is_dir($current_path)) {
            if (!is_writable($current_path)) {
                throw new \Exception('Current plugin directory is not writable: ' . $current_path);
            }
        }
        
        // Check if new plugin directory exists and is readable
        if (!is_dir($new_plugin_dir)) {
            throw new \Exception('New plugin directory not found: ' . $new_plugin_dir);
        }
        
        // Create a unique temporary name for the new version
        $temp_name = $current_path . '.new-' . time();
        
        // Copy new version to temporary location instead of rename
        if (!$this->copy_directory($new_plugin_dir, $temp_name)) {
            throw new \Exception('Failed to copy new version to temporary location');
        }
        
        // Verify the copy was successful
        // For mac-core, main file is mac-core.php, not slug.php
        $main_file = ($slug === 'mac-core') ? 'mac-core.php' : $slug . '.php';
        if (!is_dir($temp_name) || !file_exists($temp_name . '/' . $main_file)) {
            throw new \Exception('New version verification failed - main file: ' . $main_file);
        }
        
        // Remove old version and rename new version
        if (is_dir($current_path)) {
            if (!$this->recursive_remove_directory($current_path)) {
                throw new \Exception('Failed to remove old version');
            }
        }
        
        // Rename new version to final location
        if (!rename($temp_name, $current_path)) {
            throw new \Exception('Failed to rename new version to final location');
        }
        
        // Verify final result
        // For mac-core, main file is mac-core.php, not slug.php
        $main_file = ($slug === 'mac-core') ? 'mac-core.php' : $slug . '.php';
        if (!is_dir($current_path) || !file_exists($current_path . '/' . $main_file)) {
            throw new \Exception('Final verification failed - main file: ' . $main_file);
        }
        
        return array('success' => true);
    }

    /**
     * Atomic replacement of plugin using copy instead of rename
     */
    private function atomic_replace_plugin($slug, $new_plugin_dir) {
        $current_path = WP_PLUGIN_DIR . '/' . $slug;
        
        // Ensure plugin functions are available
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Deactivate plugin before replacement to release file locks on Windows
        // For mac-core, main file is mac-core.php not slug.php
        $main_file = ($slug === 'mac-core') ? 'mac-core.php' : $slug . '.php';
        $plugin_main_file = $slug . '/' . $main_file;
        $was_active = function_exists('is_plugin_active') && is_plugin_active($plugin_main_file);
        if ($was_active) {
            deactivate_plugins($plugin_main_file, true);
            // Give the filesystem a moment to release locks
            if (function_exists('opcache_reset')) { @opcache_reset(); }
            clearstatcache(true);
            usleep(250000); // 250ms
        }
        
        // Check if current plugin directory exists and is writable
        if (is_dir($current_path)) {
            if (!is_writable($current_path)) {
                throw new \Exception('Current plugin directory is not writable: ' . $current_path);
            }
        }
        
        // Check if new plugin directory exists and is readable
        if (!is_dir($new_plugin_dir)) {
            throw new \Exception('New plugin directory not found: ' . $new_plugin_dir);
        }
        
        // Create a unique temporary name for the new version
        $temp_name = $current_path . '.new-' . time();
        
        // Copy new version to temporary location instead of rename
        if (!$this->copy_directory($new_plugin_dir, $temp_name)) {
            throw new \Exception('Failed to copy new version to temporary location');
        }
        
        // Verify the copy was successful
        // For mac-core, main file is mac-core.php not slug.php
        $main_file = ($slug === 'mac-core') ? 'mac-core.php' : $slug . '.php';
        if (!is_dir($temp_name) || !file_exists($temp_name . '/' . $main_file)) {
            throw new \Exception('Copy verification failed - temporary directory is incomplete. Looking for: ' . $main_file);
        }
        
        // Remove current plugin directory if it exists (with retries and fallback rename)
        if (is_dir($current_path)) {
            $delete_success = false;
            for ($i = 0; $i < 3; $i++) {
                if ($this->delete_directory($current_path)) {
                    $delete_success = true;
                    break;
                }
                if (function_exists('opcache_reset')) { @opcache_reset(); }
                clearstatcache(true);
                usleep(300000); // 300ms
            }
            
            if (!$delete_success) {
                // Fallback: rename current directory to .old-<timestamp>
                $old_backup = $current_path . '.old-' . time();
                if (!@rename($current_path, $old_backup)) {
                    // As a last resort, perform in-place overwrite to avoid directory-level locks
                    // Ensure target is writable
                    if (!is_writable($current_path)) {
                        @chmod($current_path, 0777);
                    }
                    // Copy new files over existing ones
                    if ($this->copy_directory($new_plugin_dir, $current_path)) {
                        // Cleanup temp and return success (keeping existing directory name)
                        $this->delete_directory($temp_name);
                        return array('success' => true, 'fallback' => 'in_place_overwrite');
                    }
                    // Still cannot proceed
                    $this->delete_directory($temp_name);
                    throw new \Exception('Failed to remove or rename current plugin directory. Plugin may be in use.');
                }
            }
        }
        
        // Move temporary directory to final location (with retry)
        $move_success = false;
        for ($i = 0; $i < 3; $i++) {
            if (@rename($temp_name, $current_path)) {
                $move_success = true;
                break;
            }
            if (function_exists('opcache_reset')) { @opcache_reset(); }
            clearstatcache(true);
            usleep(300000);
        }
        if (!$move_success) {
            throw new \Exception('Failed to move new version to final location');
        }
        
        // Reactivate if previously active
        if ($was_active) {
            activate_plugin($plugin_main_file, '', false, true);
        }
        
        return array('success' => true);
    }
    
    /**
     * Verify update success
     */
    private function verify_update_success($slug) {
        $plugin_path = WP_PLUGIN_DIR . '/' . $slug;
        
        // For mac-core, main file is mac-core.php, not slug.php
        $main_file = ($slug === 'mac-core') ? 'mac-core.php' : $slug . '.php';
        
        // Check if main plugin file exists
        if (!file_exists($plugin_path . '/' . $main_file)) {
            throw new \Exception('Main plugin file missing after update: ' . $main_file);
        }
        
        // Check if plugin can be loaded
        if (!$this->can_load_plugin($slug)) {
            throw new \Exception('Plugin cannot be loaded after update');
        }
        
        return array('success' => true);
    }
    
    /**
     * Commit update transaction
     */
    private function commit_update_transaction() {
        // Clean up backup directory
        if (isset($this->update_transaction['backup_path']) && is_dir($this->update_transaction['backup_path'])) {
            $this->delete_directory($this->update_transaction['backup_path']);
        }
        
        // Clean up temp directory
        if (isset($this->update_transaction['temp_dir']) && is_dir($this->update_transaction['temp_dir'])) {
            $this->delete_directory($this->update_transaction['temp_dir']);
        }
        
        // Clear transaction
        $this->update_transaction = null;
    }
    
    /**
     * Rollback update transaction
     */
    private function rollback_update_transaction() {
        if (!$this->update_transaction || !$this->update_transaction['started']) {
            return;
        }
        
        $slug = $this->update_transaction['slug'];
        $current_path = WP_PLUGIN_DIR . '/' . $slug;
        $backup_path = $this->update_transaction['backup_path'];
        
        // Check for .old-timestamp directory (alternative backup)
        $old_backup_pattern = $current_path . '.old-*';
        $old_backups = glob($old_backup_pattern);
        
        // If current plugin is missing or corrupted, restore from backup
        if (!is_dir($current_path) || !file_exists($current_path . '/' . $slug . '.php')) {
            $restored = false;
            
            // First try to restore from .old-timestamp directory
            if (!empty($old_backups)) {
                $latest_old_backup = end($old_backups); // Get the most recent one
                if (is_dir($latest_old_backup)) {
                    // Remove corrupted current version
                    if (is_dir($current_path)) {
                        $this->delete_directory($current_path);
                    }
                    
                    // Restore from .old-timestamp
                    if (rename($latest_old_backup, $current_path)) {
                        // error_log(...MAC...);
                        $restored = true;
                    }
                }
            }
            
            // If .old-timestamp restore failed, try main backup
            if (!$restored && is_dir($backup_path)) {
                // Remove corrupted current version
                if (is_dir($current_path)) {
                    $this->delete_directory($current_path);
                }
                
                // Restore from main backup
                if (!rename($backup_path, $current_path)) {
                    // If rename failed, try copy
                    $this->copy_directory($backup_path, $current_path);
                }
                
                // // // // error_log(...MAC...);
            }
        }
        
        // Clean up temp files
        if (isset($this->update_transaction['temp_dir']) && is_dir($this->update_transaction['temp_dir'])) {
            $this->delete_directory($this->update_transaction['temp_dir']);
        }
        
        if (isset($this->update_transaction['zip_file']) && file_exists($this->update_transaction['zip_file'])) {
            @unlink($this->update_transaction['zip_file']);
        }
        
        // Clean up any remaining .old-timestamp directories
        if (!empty($old_backups)) {
            foreach ($old_backups as $old_backup) {
                if (is_dir($old_backup)) {
                    $this->delete_directory($old_backup);
                }
            }
        }
        
        // Clear transaction
        $this->update_transaction = null;
    }
    
    /**
     * Check network connection stability
     */
    private function check_network_connection() {
        // Check connection to CRM instead of Google
        // Try to make a simple request to verify network is working
        $response = wp_remote_get(home_url(), array('timeout' => 5));
        if (is_wp_error($response)) {
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 500; // Accept any successful response
    }
    
    /**
     * Verify ZIP file integrity
     */
    private function verify_zip_file($zip_file) {
        if (!file_exists($zip_file)) {
            return false;
        }
        
        $file_size = filesize($zip_file);
        if ($file_size < 1000) { // Less than 1KB is suspicious
            return false;
        }
        
        // Try to open ZIP file
        if (class_exists('\ZipArchive')) {
            $zip = new \ZipArchive();
            $res = $zip->open($zip_file);
            if ($res === TRUE) {
                $zip->close();
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verify backup integrity
     */
    private function verify_backup_integrity($original_path, $backup_path) {
        // Check if backup has main plugin file
        $plugin_name = basename($original_path);
        return file_exists($backup_path . '/' . $plugin_name . '.php');
    }
    
    /**
     * Verify new version
     */
    private function verify_new_version($plugin_dir, $needle_file) {
        return file_exists($plugin_dir . '/' . $needle_file);
    }
    
    /**
     * Check if plugin can be loaded
     */
    private function can_load_plugin($slug) {
        // For mac-core, main file is mac-core.php, not slug.php
        $main_file = ($slug === 'mac-core') ? 'mac-core.php' : $slug . '.php';
        $plugin_file = WP_PLUGIN_DIR . '/' . $slug . '/' . $main_file;
        return file_exists($plugin_file) && is_readable($plugin_file);
    }

    public function handle_check_update_mac_core_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        global $mac_core_crm_api_manager;
        if (!$mac_core_crm_api_manager || !$mac_core_crm_api_manager->is_license_valid()) {
            wp_send_json_error('CRM connection must be active to check for updates.');
        }
        $crm = CRM_API_Manager::get_instance();
        $result = $crm->check_update('mac-core');
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error('Failed to check for updates: ' . $result['message']);
        }
    }

    /**
     * Generic check update handler for addon plugins
     */
    public function handle_check_update_plugin_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field($_POST['plugin_slug']) : '';
        if (empty($plugin_slug)) {
            wp_send_json_error('Plugin slug is required.');
        }
        
        // Check if plugin exists
        $config = $this->get_plugin_config($plugin_slug);
        if (!$config) {
            wp_send_json_error('Plugin not found: ' . $plugin_slug);
        }
        
        $plugin_path = WP_PLUGIN_DIR . '/' . $config['file'];
        if (!file_exists($plugin_path)) {
            wp_send_json_error('Plugin is not installed. Please install it first.');
        }
        
        // Check if CRM connection is active
        global $mac_core_crm_api_manager;
        if (!$mac_core_crm_api_manager || !$mac_core_crm_api_manager->is_license_valid()) {
            wp_send_json_error('CRM connection must be active to check for updates.');
        }
        
        // Check for updates using CRM
        $crm = CRM_API_Manager::get_instance();
        $result = $crm->check_update($plugin_slug);
        
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error('Failed to check for updates: ' . $result['message']);
        }
    }

    public function handle_update_mac_core_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        global $mac_core_crm_api_manager;
        if (!$mac_core_crm_api_manager || !$mac_core_crm_api_manager->is_license_valid()) {
            wp_send_json_error('CRM connection must be active to update MAC Core.');
        }
        
        // Redirect to standalone update script (like code cũ)
        $nonce = wp_create_nonce('update_mac_core');
        $update_url = plugins_url('update-mac-core.php', MAC_CORE_PATH . 'update-mac-core.php') . '?update_mac=mac-core&_wpnonce=' . $nonce;
        
        wp_send_json_success(array(
            'redirect' => true,
            'url' => $update_url,
            'message' => 'Redirecting to update page...'
        ));
    }

    /**
     * Generic update handler for any plugin
     * Handles deactivation, update, and auto-activation
     */
    public function handle_update_plugin_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_core_install_plugin')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
        
        $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field($_POST['plugin_slug']) : '';
        if (empty($plugin_slug)) {
            wp_send_json_error('Plugin slug is required.');
        }
        
        // Check if CRM connection is active
        global $mac_core_crm_api_manager;
        if (!$mac_core_crm_api_manager || !$mac_core_crm_api_manager->is_license_valid()) {
            wp_send_json_error('CRM connection must be active to update plugins.');
        }
        
        // Check if plugin exists
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_slug . '/' . $plugin_slug . '.php';
        if (!file_exists($plugin_path)) {
            wp_send_json_error('Plugin is not installed. Please install it first.');
        }
        
        // Two-step update to avoid Windows file locks: deactivate first request, update on retry
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $is_retry = !empty($_POST['retry']);
        $plugin_main = $plugin_slug . '/' . $plugin_slug . '.php';
        
        if (!$is_retry && function_exists('is_plugin_active') && is_plugin_active($plugin_main)) {
            deactivate_plugins($plugin_main, true);
            if (function_exists('opcache_reset')) { @opcache_reset(); }
            clearstatcache(true);
            // Tell client to retry update now
            wp_send_json_success(array(
                'require_retry' => true,
                'message' => ucfirst($plugin_slug) . ' deactivated temporarily. Retrying update...'
            ));
        }
        
        // Update plugin via CRM with auto-activate
        $result = $this->update_plugin_via_crm_public($plugin_slug, $plugin_slug . '.php', true);
        
        if (!empty($result['success'])) {
            // Force activate plugin after successful update
            if (function_exists('is_plugin_active') && !is_plugin_active($plugin_main)) {
                $activation_result = activate_plugin($plugin_main, '', false, true);
                if (is_wp_error($activation_result)) {
                    error_log('MAC Core: Failed to activate ' . $plugin_slug . ' after update: ' . $activation_result->get_error_message());
                } else {
                    error_log('MAC Core: ' . $plugin_slug . ' activated successfully after update');
                }
            }
            
            $version = isset($result['version']) ? $result['version'] : '';
            $msg = ucfirst($plugin_slug) . ' updated successfully' . ($version ? ' to version ' . $version : '') . '!';
            wp_send_json_success($msg);
        } else {
            $error_message = isset($result['message']) ? $result['message'] : 'Unknown error';
            wp_send_json_error('Failed to update ' . $plugin_slug . ': ' . $error_message);
        }
    }


    /**
     * Helper function to register update handler for any plugin
     * Usage: $this->register_plugin_update_handler('my-plugin-slug');
     */
    public function register_plugin_update_handler($plugin_slug) {
        $action_name = 'mac_update_' . str_replace('-', '_', $plugin_slug);
        add_action('wp_ajax_' . $action_name, function() use ($plugin_slug) {
            $_POST['plugin_slug'] = $plugin_slug;
            return $this->handle_update_plugin_ajax();
        });
    }

    /**
     * Helper function to generate update button HTML for any plugin
     * Usage: echo $this->get_plugin_update_button('my-plugin-slug', 'My Plugin Name');
     */
    public function get_plugin_update_button($plugin_slug, $plugin_name, $button_class = 'button') {
        $button_id = 'mac-core-update-' . $plugin_slug;
        
        return sprintf(
            '<button type="button" class="%s mac-core-update-plugin" data-plugin-slug="%s" data-plugin-name="%s" id="%s">Update %s</button>',
            esc_attr($button_class),
            esc_attr($plugin_slug),
            esc_attr($plugin_name),
            esc_attr($button_id),
            esc_html($plugin_name)
        );
    }

    /**
     * Backward-compatible alias for legacy flow name
     * Uses CRM-based update pipeline: download → unzip → backup → atomic replace → verify
     */
    public function download_and_replace_plugin_files($addon_slug) {
        $needle_file = $addon_slug . '.php';
        return $this->update_plugin_via_crm($addon_slug, $needle_file);
    }

}
