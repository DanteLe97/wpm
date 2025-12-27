<?php
/**
 * Plugin Name: MAC Referral
 * Plugin URI: https://mac-marketing.com
 * Description: Plugin quản lý referral theo số điện thoại với hệ thống điểm
 * Version: 1.0.0
 * Author: MAC Marketing
 * Author URI: https://mac-marketing.com
 * License: GPL v2 or later
 * Text Domain: mac-referral
 */

// Ngăn truy cập trực tiếp
if (!defined('ABSPATH')) {
    exit;
}

// Định nghĩa constants
define('MAC_REFERRAL_VERSION', '1.0.0');
define('MAC_REFERRAL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAC_REFERRAL_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include các file cần thiết
require_once MAC_REFERRAL_PLUGIN_DIR . 'includes/class-database.php';
require_once MAC_REFERRAL_PLUGIN_DIR . 'includes/class-log.php';
require_once MAC_REFERRAL_PLUGIN_DIR . 'includes/class-admin.php';

// Khởi tạo plugin
class MAC_Referral {
    
    private static $instance = null;
    private $database;
    private $log;
    private $admin;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->database = new MAC_Referral_Database();
        $this->log = new MAC_Referral_Log();
        $this->admin = new MAC_Referral_Admin($this->database, $this->log);
        
        // Hook activation và deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Init admin - chạy sớm để loại bỏ notices từ plugin khác
        add_action('admin_menu', array($this->admin, 'add_admin_menu'));
        add_action('admin_init', array($this->admin, 'handle_forms'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_scripts'));
        
        // Đảm bảo bảng log được tạo (nếu chưa có)
        add_action('admin_init', array($this, 'maybe_create_log_table'), 1);
        
        // Chạy sớm hơn (priority thấp = chạy sớm) để remove notices trước khi plugin khác thêm vào
        add_action('admin_init', array($this->admin, 'clean_admin_page'), 1);
        add_action('admin_head', array($this->admin, 'clean_admin_page'), 1);
        
        // AJAX handlers
        add_action('wp_ajax_mac_referral_update_point', array($this->admin, 'ajax_update_point'));
        add_action('wp_ajax_mac_referral_get_referral', array($this->admin, 'ajax_get_referral'));
        add_action('wp_ajax_mac_referral_find_by_phone', array($this->admin, 'ajax_find_by_phone'));
        add_action('wp_ajax_mac_referral_add_phone_referral', array($this->admin, 'ajax_add_phone_referral'));
        add_action('wp_ajax_mac_referral_save_referral', array($this->admin, 'ajax_save_referral'));
        add_action('wp_ajax_mac_referral_get_logs', array($this->admin, 'ajax_get_logs'));
        add_action('wp_ajax_mac_referral_get_log_details', array($this->admin, 'ajax_get_log_details'));
        add_action('wp_ajax_mac_referral_check_duplicate', array($this->admin, 'ajax_check_duplicate'));
    }
    
    /**
     * Hook activation - tạo bảng database và bảng log
     */
    public function activate() {
        $this->database->create_table();
        $this->log->create_log_table();
    }
    
    /**
     * Đảm bảo bảng log được tạo (nếu chưa có)
     */
    public function maybe_create_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mac_referral_logs';
        
        // Kiểm tra xem bảng có tồn tại không
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->log->create_log_table();
        }
    }
    
    public function deactivate() {
        // Có thể xóa bảng nếu cần, hoặc giữ lại dữ liệu
        // $this->database->drop_table();
        // $this->log->drop_table();
    }
}

// Khởi chạy plugin
function mac_referral_init() {
    return MAC_Referral::get_instance();
}

mac_referral_init();

