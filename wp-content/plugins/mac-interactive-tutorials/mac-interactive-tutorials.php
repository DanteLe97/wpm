<?php
/**
 * Plugin Name: MAC Interactive Tutorials
 * Plugin URI: https://macusaone.com
 * Description: tạo tutorials trực tiếp trong WordPress admin
 * Version: 1.0.0
 * Author: MAC USA One
 * Author URI: https://macusaone.com
 * Text Domain: mac-interactive-tutorials
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MAC_TUTORIALS_VERSION', '1.0.0');
define('MAC_TUTORIALS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAC_TUTORIALS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAC_TUTORIALS_PLUGIN_FILE', __FILE__);
define('MAC_TUTORIALS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// SETUP WEB GỐC: Set constant này = true để plugin hoạt động như web gốc
// Set = false hoặc không define để plugin hoạt động như web con
if (!defined('MAC_TUTORIALS_IS_SOURCE')) {
    define('MAC_TUTORIALS_IS_SOURCE', false); // Default: web con
}

/**
 * Main Plugin Class
 */
class MAC_Interactive_Tutorials {
    
    /**
     * Plugin version
     */
    const VERSION = '1.0.0';
    
    /**
     * Plugin slug
     */
    const PLUGIN_SLUG = 'mac-interactive-tutorials';
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Post Type instance
     */
    public $post_type = null;
    
    /**
     * Meta Boxes instance
     */
    public $meta_boxes = null;
    
    /**
     * State Manager instance
     */
    public $state_manager = null;
    
    /**
     * Frontend instance
     */
    public $frontend = null;
    
    /**
     * Admin instance
     */
    public $admin = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init();
    }
    
    /**
     * Check if this is a source site (web gốc) or client site (web con)
     * 
     * SETUP WEB GỐC: Set constant MAC_TUTORIALS_IS_SOURCE = true trong file này (dòng ~25)
     * SETUP WEB CON: Set constant MAC_TUTORIALS_IS_SOURCE = false hoặc không define (default)
     */
    private function is_source_site() {
        return defined('MAC_TUTORIALS_IS_SOURCE') && MAC_TUTORIALS_IS_SOURCE === true;
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        $is_source = $this->is_source_site();
        
        if ($is_source) {
            // Source site: Load database and REST API classes
            require_once MAC_TUTORIALS_PLUGIN_DIR . 'includes/class-database.php';
            require_once MAC_TUTORIALS_PLUGIN_DIR . 'includes/class-rest-api.php';
            require_once MAC_TUTORIALS_PLUGIN_DIR . 'includes/class-meta-boxes.php';
            if (is_admin()) {
                require_once MAC_TUTORIALS_PLUGIN_DIR . 'admin/class-admin.php';
            }
        } else {
            // Client site: Load CPT and sync classes
            require_once MAC_TUTORIALS_PLUGIN_DIR . 'includes/class-synced-post-type.php';
            require_once MAC_TUTORIALS_PLUGIN_DIR . 'admin/class-sync-admin.php';
            require_once MAC_TUTORIALS_PLUGIN_DIR . 'includes/class-meta-boxes.php';
            if (is_admin()) {
                require_once MAC_TUTORIALS_PLUGIN_DIR . 'admin/class-admin.php';
            }
        }
        
        // Core classes (for source site: attach to regular posts)
        if ($is_source) {
            require_once MAC_TUTORIALS_PLUGIN_DIR . 'includes/class-state-manager.php';
            require_once MAC_TUTORIALS_PLUGIN_DIR . 'includes/class-frontend.php';
            
            // Admin classes
            if (is_admin()) {
                require_once MAC_TUTORIALS_PLUGIN_DIR . 'admin/class-admin.php';
                require_once MAC_TUTORIALS_PLUGIN_DIR . 'admin/class-source-admin.php';
            }
        } else {
            // Client site: Load state manager and frontend for synced tutorials
            require_once MAC_TUTORIALS_PLUGIN_DIR . 'includes/class-state-manager.php';
            require_once MAC_TUTORIALS_PLUGIN_DIR . 'includes/class-frontend.php';
        }
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        $is_source = $this->is_source_site();
        
        if ($is_source) {
            // Source site: Initialize regular post tutorial classes
            $this->meta_boxes = new MAC_Tutorial_Meta_Boxes();
            $this->state_manager = new MAC_Tutorial_State_Manager();
            $this->frontend = new MAC_Tutorial_Frontend();
            
            // Initialize admin
            if (is_admin()) {
                $this->admin = new MAC_Tutorial_Admin();
                new MAC_Tutorial_Source_Admin();
            }
            
            // Initialize REST API
            new MAC_Tutorial_REST_API();
        } else {
            // Client site: Initialize synced tutorial classes
            $this->post_type = new MAC_Tutorial_Synced_Post_Type();
            $this->meta_boxes = new MAC_Tutorial_Meta_Boxes();
            $this->state_manager = new MAC_Tutorial_State_Manager();
            $this->frontend = new MAC_Tutorial_Frontend();
            
            // Initialize admin and sync admin
            if (is_admin()) {
                $this->admin = new MAC_Tutorial_Admin();
                new MAC_Tutorial_Sync_Admin();
            }
        }
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'mac-interactive-tutorials',
            false,
            dirname(MAC_TUTORIALS_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check if this is source site
        $is_source = defined('MAC_TUTORIALS_IS_SOURCE') && MAC_TUTORIALS_IS_SOURCE === true;
        
        if ($is_source) {
            // Create database table for source site
            require_once MAC_TUTORIALS_PLUGIN_DIR . 'includes/class-database.php';
            $database = new MAC_Tutorial_Database();
            $database->create_table();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Get plugin URL
     */
    public function get_plugin_url() {
        return MAC_TUTORIALS_PLUGIN_URL;
    }
    
    /**
     * Get plugin path
     */
    public function get_plugin_path() {
        return MAC_TUTORIALS_PLUGIN_DIR;
    }
}

/**
 * Get plugin instance
 */
function mac_tutorials() {
    return MAC_Interactive_Tutorials::get_instance();
}

// Initialize plugin
mac_tutorials();

