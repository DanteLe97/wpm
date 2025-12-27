<?php
/**
 * Plugin Name: MAC Seasonal Effects
 * Plugin URI: https://macusaone.com
 * Description: Plugin cho phép chọn và tùy chỉnh seasonal effects (animations) chạy trên website theo sự kiện (Halloween, Thanksgiving, etc.)
 * Version: 1.0.1
 * Author: MAC USA One
 * Author URI: https://macusaone.com
 * Text Domain: mac-seasonal-effects
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MAC_SEASONAL_EFFECTS_VERSION', '1.0.0');
define('MAC_SEASONAL_EFFECTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAC_SEASONAL_EFFECTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAC_SEASONAL_EFFECTS_PLUGIN_FILE', __FILE__);

// Include core files
require_once MAC_SEASONAL_EFFECTS_PLUGIN_DIR . 'includes/class-animation-manager.php';
require_once MAC_SEASONAL_EFFECTS_PLUGIN_DIR . 'includes/class-animation-discovery.php';
require_once MAC_SEASONAL_EFFECTS_PLUGIN_DIR . 'includes/class-animation-loader.php';
require_once MAC_SEASONAL_EFFECTS_PLUGIN_DIR . 'includes/class-date-validator.php';

// Include admin files
if (is_admin()) {
    require_once MAC_SEASONAL_EFFECTS_PLUGIN_DIR . 'admin/class-admin.php';
    require_once MAC_SEASONAL_EFFECTS_PLUGIN_DIR . 'admin/class-settings.php';
}

/**
 * Main plugin class
 */
class MAC_Seasonal_Effects {
    
    const PLUGIN_SLUG = 'mac-seasonal-effects';
    const VERSION = '1.0.0';
    
    private static $instance = null;
    
    public $manager;
    public $discovery;
    public $loader;
    public $validator;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Register as MAC Core add-on
        if (defined('MAC_CORE_PATH')) {
            $addon_manager_path = MAC_CORE_PATH . 'includes/class-addon-manager.php';
            if (file_exists($addon_manager_path)) {
                require_once $addon_manager_path;
                if (class_exists('MAC_Addon_Manager') && method_exists('MAC_Addon_Manager', 'register_addon')) {
                    MAC_Addon_Manager::register_addon($this);
                }
            }
        }
        
        // Initialize core classes
        $this->discovery = new MAC_Animation_Discovery();
        $this->validator = new MAC_Date_Validator();
        $this->manager = new MAC_Animation_Manager($this->discovery, $this->validator);
        $this->loader = new MAC_Animation_Loader($this->manager);
        
        // Initialize admin
        if (is_admin()) {
            new MAC_Animation_Admin($this->manager, $this->discovery);
        }
        
        // Initialize frontend loader
        add_action('wp_loaded', array($this->loader, 'init'));
    }
    
    /**
     * Get plugin slug (for MAC Core add-on compatibility)
     */
    public function get_plugin_slug() {
        return self::PLUGIN_SLUG;
    }
}

// Initialize plugin
function mac_seasonal_effects() {
    return MAC_Seasonal_Effects::get_instance();
}

// Start the plugin
mac_seasonal_effects();

