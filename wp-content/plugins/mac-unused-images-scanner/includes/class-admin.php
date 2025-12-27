<?php
/**
 * Admin Class
 * Handles admin page and actions
 */

namespace MAC_UIS;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    
    private $scanner;
    
    public function __construct($scanner) {
        $this->scanner = $scanner;
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // WP-Cron event handler
        add_action('mac_uis_run_scan_event', array($this->scanner, 'run_scan'));
        
        // AJAX handlers
        add_action('wp_ajax_mac_uis_check_progress', array($this, 'ajax_check_progress'));
        
        // Manual run handler
        add_action('admin_init', array($this, 'handle_manual_run'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'upload.php',
            __('Unused Images (Cron)', 'mac-unused-images-scanner'),
            __('Unused Images (Cron)', 'mac-unused-images-scanner'),
            'manage_options',
            'mac-unused-images-scanner',
            array($this, 'render_admin_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'mac-unused-images-scanner') === false) {
            return;
        }
        
        wp_enqueue_style(
            'mac-uis-admin',
            MAC_UIS_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            MAC_UIS_VERSION
        );
        
        wp_enqueue_script(
            'mac-uis-admin',
            MAC_UIS_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            MAC_UIS_VERSION,
            true
        );
        
        wp_localize_script('mac-uis-admin', 'macUIS', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mac_uis_ajax'),
            'i18n' => array(
                'processing' => __('Đang xử lý:', 'mac-unused-images-scanner'),
                'completed' => __('Hoàn tất!', 'mac-unused-images-scanner'),
                'reloadMessage' => __('Vui lòng tải lại trang để xem kết quả.', 'mac-unused-images-scanner'),
                'notStarted' => __('Chưa bắt đầu.', 'mac-unused-images-scanner'),
                'confirmDelete' => __('Bạn có chắc chắn muốn xóa', 'mac-unused-images-scanner'),
                'confirmDeleteSuffix' => __('ảnh đã chọn không?\nHành động này không thể hoàn tác.', 'mac-unused-images-scanner'),
                'selectAtLeastOne' => __('Vui lòng chọn ít nhất một ảnh để xóa.', 'mac-unused-images-scanner'),
            )
        ));
    }
    
    public function render_admin_page() {
        // Handle bulk delete
        if (isset($_POST['bulk_delete_action']) && !empty($_POST['delete_ids'])) {
            check_admin_referer('bulk_delete_unused_images');
            $this->handle_bulk_delete();
        }
        
        // Handle start scan
        if (isset($_POST['start_scan'])) {
            check_admin_referer('start_unused_scan');
            $this->scanner->start_scan();
            echo '<div class="updated"><p>' . esc_html__('Đã lên lịch quét ảnh trong nền (chạy sau 5 giây). Tiến trình sẽ hiển thị bên dưới.', 'mac-unused-images-scanner') . '</p></div>';
        }
        
        // Load view template
        require_once MAC_UIS_PLUGIN_DIR . 'admin/views/admin-page.php';
    }
    
    private function handle_bulk_delete() {
        $delete_ids = array_map('intval', $_POST['delete_ids']);
        $deleted = 0;
        
        $result = get_option('mac_uis_scan_result', []);
        
        foreach ($delete_ids as $id) {
            if ($this->scanner->delete_attachment($id)) {
                $deleted++;
            }
            
            if (isset($result[$id])) {
                unset($result[$id]);
            }
        }
        
        update_option('mac_uis_scan_result', $result);
        
        echo '<div class="updated"><p>' . sprintf(esc_html__('Đã xóa %d ảnh và cập nhật lại danh sách.', 'mac-unused-images-scanner'), $deleted) . '</p></div>';
    }
    
    public function handle_manual_run() {
        if (isset($_GET['page']) && $_GET['page'] === 'mac-unused-images-scanner' && 
            isset($_GET['run_scan_now']) && current_user_can('manage_options')) {
            check_admin_referer('run_scan_now', 'mac_uis_nonce');
            $this->scanner->run_scan();
            wp_die(
                '<h1>' . esc_html__('Đã chạy job thủ công', 'mac-unused-images-scanner') . '</h1>' .
                '<p>' . esc_html__('Xem log để kiểm tra. Quay lại', 'mac-unused-images-scanner') . 
                ' <a href="' . esc_url(admin_url('upload.php?page=mac-unused-images-scanner')) . '">' . 
                esc_html__('trang quét ảnh', 'mac-unused-images-scanner') . '</a>.</p>'
            );
        }
    }
    
    public function ajax_check_progress() {
        $progress = get_option('mac_uis_scan_progress', []);
        $status = get_option('mac_uis_scan_status', 'idle');
        wp_send_json(array_merge($progress, ['status' => $status]));
    }
    
    /**
     * Get scan status
     */
    public function get_scan_status() {
        return get_option('mac_uis_scan_status', 'idle');
    }
    
    /**
     * Get scan result
     */
    public function get_scan_result() {
        return get_option('mac_uis_scan_result', []);
    }
}

