<?php
/**
 * Plugin Name: MAC Unused Images Scanner
 * Plugin URI: https://macusaone.com
 * Description: Quét và xóa ảnh không sử dụng trong WordPress với WP-Cron, có tiến trình và bulk delete. Hỗ trợ WebP cleanup.
 * Version: 1.0.0
 * Author: MAC USA One
 * Author URI: https://macusaone.com
 * Text Domain: mac-unused-images-scanner
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MAC_UIS_VERSION', '1.0.0');
define('MAC_UIS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAC_UIS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAC_UIS_PLUGIN_FILE', __FILE__);
define('MAC_UIS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Autoloader for plugin classes
 */
spl_autoload_register(function ($class) {
    $prefix = 'MAC_UIS\\';
    $base_dir = MAC_UIS_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Main Plugin Class
 */
class MAC_Unused_Images_Scanner {
    
    private static $instance = null;
    private $scanner = null;
    private $admin = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init();
    }
    
    private function load_dependencies() {
        require_once MAC_UIS_PLUGIN_DIR . 'includes/class-scanner.php';
        require_once MAC_UIS_PLUGIN_DIR . 'includes/class-admin.php';
    }
    
    private function init() {
        // Initialize components
        $this->scanner = new MAC_UIS\Scanner();
        $this->admin = new MAC_UIS\Admin($this->scanner);
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function activate() {
        // Clear any existing cron jobs
        wp_clear_scheduled_hook('mac_uis_run_scan_event');
    }
    
    public function deactivate() {
        // Clear cron jobs on deactivation
        wp_clear_scheduled_hook('mac_uis_run_scan_event');
    }
}

// Initialize plugin
MAC_Unused_Images_Scanner::get_instance();
