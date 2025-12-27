<?php
/**
 * Class quản lý admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Referral_Admin {
    
    private $database;
    private $log;
    private $page_slug = 'mac-referral';
    private $logs_page_slug = 'mac-referral-logs';
    
    public function __construct($database, $log) {
        $this->database = $database;
        $this->log = $log;
    }
    
    /**
     * Thêm admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'MAC Referral',
            'MAC Referral',
            'manage_options',
            $this->page_slug,
            array($this, 'render_list_page'),
            'dashicons-groups',
            30
        );
        
        add_submenu_page(
            $this->page_slug,
            'Manage Referrals',
            'Manage Referrals',
            'manage_options',
            $this->page_slug,
            array($this, 'render_list_page')
        );
        
        add_submenu_page(
            $this->page_slug,
            'Logs',
            'Logs',
            'manage_options',
            $this->logs_page_slug,
            array($this, 'render_logs_page')
        );
    }
    
    /**
     * Render trang danh sách
     */
    public function render_list_page() {
        $database = $this->database;
        
        // Xử lý search
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Phân trang
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Lấy dữ liệu với phân trang
        $data = $database->get_referrals($search, $per_page, $current_page);
        $referrals = $data['items'];
        $total_items = $data['total'];
        $total_pages = $per_page > 0 ? ceil($total_items / $per_page) : 1;
        
        // Bắt đầu output buffering để có thể xử lý output
        ob_start();
        require_once MAC_REFERRAL_PLUGIN_DIR . 'admin/views/list-referrals.php';
        $content = ob_get_clean();
        
        // Loại bỏ các script/style không mong muốn từ output (nếu có)
        // Có thể thêm logic lọc ở đây nếu cần
        
        echo $content;
    }
    
    /**
     * Render trang logs
     */
    public function render_logs_page() {
        $log = $this->log;
        
        // Xử lý search
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Xử lý action filter
        $action_filter = isset($_GET['action_filter']) ? sanitize_text_field($_GET['action_filter']) : '';
        
        // Phân trang
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Lấy dữ liệu với phân trang
        $data = $log->get_all_logs($search, $action_filter, $per_page, $current_page);
        $logs = $data['items'];
        $total_items = $data['total'];
        $total_pages = $per_page > 0 ? ceil($total_items / $per_page) : 1;
        
        // Bắt đầu output buffering
        ob_start();
        require_once MAC_REFERRAL_PLUGIN_DIR . 'admin/views/logs.php';
        $content = ob_get_clean();
        
        echo $content;
    }
    
    /**
     * Enqueue scripts và styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, $this->page_slug) === false && strpos($hook, $this->logs_page_slug) === false) {
            return;
        }
        
        // Loại bỏ các admin notices từ plugin khác
        $this->remove_other_plugin_notices();
        
        wp_enqueue_style(
            'mac-referral-admin',
            MAC_REFERRAL_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MAC_REFERRAL_VERSION
        );
        
        wp_enqueue_script(
            'mac-referral-admin',
            MAC_REFERRAL_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MAC_REFERRAL_VERSION,
            true
        );
        
        wp_localize_script('mac-referral-admin', 'macReferralAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mac_referral_nonce')
        ));
    }
    
    /**
     * Loại bỏ các notices và output từ plugin khác trên trang này
     */
    private function remove_other_plugin_notices() {
        // Loại bỏ tất cả admin notices từ plugin khác
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        
        // Chỉ giữ lại notices của plugin này
        add_action('admin_notices', array($this, 'show_plugin_notices'), 1);
    }
    
    /**
     * Chỉ hiển thị notices của plugin này
     */
    public function show_plugin_notices() {
        // Chỉ hiển thị settings errors của plugin này
        settings_errors('mac_referral_messages');
    }
    
    /**
     * AJAX: Lấy logs theo referral_id
     */
    public function ajax_get_logs() {
        check_ajax_referer('mac_referral_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to access this.'));
        }
        
        $referral_id = isset($_POST['referral_id']) ? intval($_POST['referral_id']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        
        if ($referral_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid referral ID.'));
        }
        
        $logs = $this->log->get_logs($referral_id, $limit);
        
        // Format logs for display
        $formatted_logs = array();
        foreach ($logs as $log) {
            $formatted_logs[] = $this->log->format_log_for_display($log);
        }
        
        wp_send_json_success(array(
            'logs' => $formatted_logs,
            'total' => count($formatted_logs)
        ));
    }
    
    /**
     * Làm sạch trang admin từ các output không mong muốn
     * Phương pháp này sử dụng PHP để remove hooks từ plugin khác
     */
    public function clean_admin_page() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, $this->page_slug) === false) {
            return;
        }
        
        // Phương pháp 1: Remove tất cả actions admin_notices (đơn giản và hiệu quả nhất)
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_all_actions('admin_footer_text');
        remove_all_filters('admin_footer_text');
        
        // Phương pháp 2: Thêm lại chỉ notices của plugin này
        add_action('admin_notices', array($this, 'show_plugin_notices'), 999);
        
        // Phương pháp 3: Remove các output từ các hook khác nếu cần
        // Có thể thêm các hooks khác ở đây
    }
    
    /**
     * Xử lý form submit (chỉ dùng cho bulk actions, không dùng cho save_referral nữa - đã chuyển sang AJAX)
     */
    public function handle_forms() {
        if (!isset($_POST['mac_referral_action'])) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['mac_referral_nonce']) || 
            !wp_verify_nonce($_POST['mac_referral_nonce'], 'mac_referral_action')) {
            return;
        }
        
        // Kiểm tra quyền
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $action = sanitize_text_field($_POST['mac_referral_action']);
        
        // Không xử lý save_referral ở đây nữa - đã chuyển sang AJAX
        // if ($action === 'save_referral') {
        //     $this->process_save_referral();
        // } elseif 
        if ($action === 'update_point') {
            $this->handle_update_point();
        } elseif ($action === 'delete_referrals') {
            $this->handle_delete_referrals();
        }
    }
    
    /**
     * AJAX: Lưu referral (add hoặc update)
     */
    public function ajax_save_referral() {
        check_ajax_referer('mac_referral_action', 'mac_referral_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền truy cập'));
        }
        
        // Validation
        if (empty($_POST['fullname']) || empty($_POST['phone'])) {
            wp_send_json_error(array('message' => 'Full Name and Phone Number are required.'));
        }
        
        // Lấy ID của referral đang edit (nếu có)
        $id = isset($_POST['referral_id']) ? intval($_POST['referral_id']) : 0;
        
        $phone = sanitize_text_field($_POST['phone']);
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        // Normalize số điện thoại (loại bỏ format, chỉ lấy số)
        $phone = preg_replace('/\D/', '', $phone);
        
        // Kiểm tra số điện thoại có đủ 10 số không
        if (strlen($phone) !== 10) {
            wp_send_json_error(array('message' => 'Phone number must have 10 digits.'));
        }
        
        // Kiểm tra số điện thoại trùng
        if ($this->database->phone_exists($phone, $id)) {
            wp_send_json_error(array(
                'message' => 'This phone number already exists in the system. Please enter a different phone number.',
                'field' => 'phone'
            ));
        }
        
        // Kiểm tra email trùng (chỉ kiểm tra nếu email không rỗng)
        if (!empty($email) && $this->database->email_exists($email, $id)) {
            wp_send_json_error(array(
                'message' => 'This email already exists in the system. Please enter a different email.',
                'field' => 'email'
            ));
        }
        
        // Normalize phone_referral nếu có
        $phone_referral = isset($_POST['phone_referral']) ? sanitize_text_field($_POST['phone_referral']) : '';
        $phone_referral_normalized = '';
        
        // Lưu phone_referral ban đầu và điểm ban đầu để so sánh khi update
        $old_phone_referral = '';
        $old_point = 0;
        if ($id > 0) {
            $old_referral = $this->database->get_referral_by_id($id);
            if ($old_referral) {
                $old_phone_referral = $old_referral['phone_referral'] ?? '';
                $old_point = intval($old_referral['point'] ?? 0);
            }
        }
        
        if (!empty($phone_referral)) {
            $phone_referral_normalized = preg_replace('/\D/', '', $phone_referral);
            // Kiểm tra phone_referral có đủ 10 số không (nếu có)
            if (strlen($phone_referral_normalized) !== 10) {
                wp_send_json_error(array('message' => 'Referrer phone number must have 10 digits.'));
            }
        }
        
        // Chuẩn bị data để update/insert
        $data = array(
            'fullname' => sanitize_text_field($_POST['fullname']),
            'email' => $email,
            'phone' => $phone,
            'phone_referral' => $phone_referral_normalized
        );
        
        // Chỉ set point khi insert (người được giới thiệu luôn có điểm = 0)
        // Khi update, không set point để giữ nguyên điểm cũ
        if ($id <= 0) {
            $data['point'] = 0;
        }
        
        if ($id > 0) {
            // Update - lấy dữ liệu cũ trước khi update
            $old_referral_data = $this->database->get_referral_by_id($id);
            
            $result = $this->database->update_referral($id, $data);
            if ($result) {
                // Lấy dữ liệu mới sau khi update
                $new_referral_data = $this->database->get_referral_by_id($id);
                
                // Ghi log cho việc update
                if ($old_referral_data && $new_referral_data && $this->log) {
                    $log_result = $this->log->log_action($id, 'update', $old_referral_data, $new_referral_data);
                    if (!$log_result) {
                        error_log('MAC Referral: Failed to log update action for referral ID: ' . $id);
                    }
                }
                
                // Xử lý điểm cho người giới thiệu (referrer)
                // Chỉ xử lý nếu phone_referral thay đổi
                $messages = array();
                
                if ($phone_referral_normalized !== $old_phone_referral) {
                    // Trừ điểm cho referrer cũ (nếu có)
                    if (!empty($old_phone_referral)) {
                        $old_referrer = $this->database->get_referral_by_phone($old_phone_referral);
                        if ($old_referrer && $old_referrer['id'] != $id) {
                            // Trừ 10 điểm cho referrer cũ
                            $point_result = $this->database->update_point($old_referrer['id'], -10);
                            if ($point_result && isset($point_result['success']) && $point_result['success']) {
                                // Format phone number
                                $formatted_old_phone = $this->format_phone($old_phone_referral);
                                $messages[] = sprintf('Subtracted 10 points from referrer ID %d (phone: %s). Old points: %d, New points: %d', 
                                    $old_referrer['id'], 
                                    $formatted_old_phone,
                                    $point_result['old_point'],
                                    $point_result['new_point']
                                );
                            }
                        }
                    }
                    
                    // Thêm điểm cho referrer mới (nếu có)
                    if (!empty($phone_referral_normalized)) {
                        $new_referrer = $this->database->get_referral_by_phone($phone_referral_normalized);
                        if ($new_referrer && $new_referrer['id'] != $id) {
                            // Thêm 10 điểm cho referrer mới
                            $point_result = $this->database->update_point($new_referrer['id'], 10);
                            if ($point_result && isset($point_result['success']) && $point_result['success']) {
                                // Format phone number
                                $formatted_new_phone = $this->format_phone($phone_referral_normalized);
                                $messages[] = sprintf('Added 10 points to referrer ID %d (phone: %s). Old points: %d, New points: %d', 
                                    $new_referrer['id'], 
                                    $formatted_new_phone,
                                    $point_result['old_point'],
                                    $point_result['new_point']
                                );
                            }
                        }
                    }
                }
                
                // Tạo message response
                $response_message = 'Referral updated successfully!';
                if (!empty($messages)) {
                    $response_message .= ' ' . implode(' ', $messages);
                }
                
                wp_send_json_success(array('message' => $response_message));
            } else {
                global $wpdb;
                $error_message = 'An error occurred while updating.';
                if ($wpdb->last_error) {
                    $error_message .= ' SQL Error: ' . $wpdb->last_error;
                }
                wp_send_json_error(array('message' => $error_message));
            }
        } else {
            // Insert
            $result = $this->database->insert_referral($data);
            if ($result) {
                // Lấy dữ liệu mới sau khi insert
                $new_referral_data = $this->database->get_referral_by_id($result);
                
                // Ghi log cho việc insert
                if ($new_referral_data && $this->log) {
                    $log_result = $this->log->log_action($result, 'insert', null, $new_referral_data);
                    if (!$log_result) {
                        error_log('MAC Referral: Failed to log insert action for referral ID: ' . $result);
                    }
                }
                
                // Thêm điểm cho người giới thiệu (referrer) nếu có phone_referral
                if (!empty($phone_referral_normalized)) {
                    $referrer = $this->database->get_referral_by_phone($phone_referral_normalized);
                    if ($referrer) {
                        // Thêm 10 điểm cho referrer (người giới thiệu)
                        $this->database->update_point($referrer['id'], 10);
                    }
                }
                
                wp_send_json_success(array('message' => 'Referral added successfully!'));
            } else {
                wp_send_json_error(array('message' => 'An error occurred while adding.'));
            }
        }
    }
    
    /**
     * Xử lý lưu referral (add hoặc update)
     */
    private function process_save_referral() {
        // Validation
        if (empty($_POST['fullname']) || empty($_POST['phone'])) {
            add_settings_error(
                'mac_referral_messages',
                'mac_referral_error',
                'Họ tên và Số điện thoại là bắt buộc.',
                'error'
            );
            return;
        }
        
        // Lấy ID của referral đang edit (nếu có)
        $id = isset($_POST['referral_id']) ? intval($_POST['referral_id']) : 0;
        
        $data = array(
            'fullname' => sanitize_text_field($_POST['fullname']),
            'email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : '',
            'phone' => sanitize_text_field($_POST['phone']),
            'phone_referral' => isset($_POST['phone_referral']) ? sanitize_text_field($_POST['phone_referral']) : '',
            'point' => isset($_POST['point']) ? intval($_POST['point']) : 0
        );
        
        if ($id > 0) {
            // Update
            $result = $this->database->update_referral($id, $data);
            if ($result) {
                $message = 'Cập nhật referral thành công!';
            } else {
                // Log error để debug
                global $wpdb;
                $error_message = 'Có lỗi xảy ra khi cập nhật.';
                if ($wpdb->last_error) {
                    $error_message .= ' SQL Error: ' . $wpdb->last_error;
                }
                $message = $error_message;
            }
        } else {
            // Insert
            $result = $this->database->insert_referral($data);
            $message = $result ? 'Thêm referral thành công!' : 'Có lỗi xảy ra khi thêm.';
        }
        
        $type = $result ? 'success' : 'error';
        add_settings_error(
            'mac_referral_messages',
            'mac_referral_message',
            $message,
            $type
        );
    }
    
    /**
     * Xử lý cập nhật điểm
     */
    private function handle_update_point() {
        if (!isset($_POST['referral_id']) || !isset($_POST['point_change'])) {
            return;
        }
        
        $id = intval($_POST['referral_id']);
        $point_change = intval($_POST['point_change']);
        
        $result = $this->database->update_point($id, $point_change);
        
        if ($result) {
            add_settings_error(
                'mac_referral_messages',
                'mac_referral_message',
                'Cập nhật điểm thành công!',
                'success'
            );
        }
    }
    
    /**
     * Xử lý xóa referrals
     */
    private function handle_delete_referrals() {
        // Kiểm tra có action không (tránh submit khi chọn per_page)
        if (!isset($_POST['bulk_action']) || empty($_POST['bulk_action'])) {
            return; // Không làm gì nếu không có bulk_action
        }
        
        if (!isset($_POST['referral_ids']) || !is_array($_POST['referral_ids'])) {
            add_settings_error(
                'mac_referral_messages',
                'mac_referral_error',
                'No items selected for deletion.',
                'error'
            );
            return;
        }
        
        $ids = array_map('intval', $_POST['referral_ids']);
        $ids = array_filter($ids);
        
        if (empty($ids)) {
            add_settings_error(
                'mac_referral_messages',
                'mac_referral_error',
                'No items selected for deletion.',
                'error'
            );
            return;
        }
        
        $deleted_count = 0;
        foreach ($ids as $id) {
            // Lấy dữ liệu trước khi xóa để ghi log
            $referral_data = $this->database->get_referral_by_id($id);
            
            if ($this->database->delete_referral($id)) {
                // Ghi log cho việc delete
                if ($referral_data && $this->log) {
                    $log_result = $this->log->log_action($id, 'delete', $referral_data, null);
                    if (!$log_result) {
                        error_log('MAC Referral: Failed to log delete action for referral ID: ' . $id);
                    }
                }
                $deleted_count++;
            }
        }
        
        if ($deleted_count > 0) {
            add_settings_error(
                'mac_referral_messages',
                'mac_referral_message',
                sprintf('Successfully deleted %d referral(s)!', $deleted_count),
                'success'
            );
        } else {
            add_settings_error(
                'mac_referral_messages',
                'mac_referral_error',
                'An error occurred while deleting referrals.',
                'error'
            );
        }
    }
    
    /**
     * AJAX: Cập nhật điểm
     */
    public function ajax_update_point() {
        check_ajax_referer('mac_referral_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền truy cập'));
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $point_change = isset($_POST['point_change']) ? intval($_POST['point_change']) : 0;
        
        if ($id <= 0) {
            wp_send_json_error(array('message' => 'ID không hợp lệ'));
        }
        
        $result = $this->database->update_point($id, $point_change);
        
        // Kiểm tra nếu có lỗi (điểm âm)
        if (is_array($result) && isset($result['success']) && $result['success'] === false) {
            wp_send_json_error(array('message' => $result['error']));
            return;
        }
        
        if ($result && isset($result['success']) && $result['success']) {
            $referral = $result['referral'];
            
            // Ghi log cho việc update point
            if ($this->log) {
                $log_result = $this->log->log_action(
                    $id,
                    'point_update',
                    null,
                    null,
                    array(
                        'old_point' => $result['old_point'],
                        'new_point' => $result['new_point'],
                        'point_change' => $point_change
                    )
                );
                if (!$log_result) {
                    error_log('MAC Referral: Failed to log point_update action for referral ID: ' . $id);
                }
            }
            
            // Gửi email nếu có email
            if (!empty($referral['email'])) {
                $this->send_point_update_email($referral, $result['old_point'], $result['new_point']);
            }
            
            wp_send_json_success(array(
                'message' => 'Cập nhật điểm thành công!',
                'new_point' => $result['new_point'],
                'email_sent' => !empty($referral['email'])
            ));
        } else {
            wp_send_json_error(array('message' => 'Có lỗi xảy ra khi cập nhật điểm.'));
        }
    }
    
    /**
     * AJAX: Lấy thông tin referral theo ID
     */
    public function ajax_get_referral() {
        check_ajax_referer('mac_referral_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền truy cập'));
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            wp_send_json_error(array('message' => 'ID không hợp lệ'));
        }
        
        $referral = $this->database->get_referral_by_id($id);
        
        if ($referral) {
            wp_send_json_success($referral);
        } else {
            wp_send_json_error(array('message' => 'Không tìm thấy referral'));
        }
    }
    
    /**
     * AJAX: Get log details by ID
     */
    public function ajax_get_log_details() {
        check_ajax_referer('mac_referral_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to access this.'));
        }
        
        $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
        
        if ($log_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid log ID.'));
        }
        
        $log = $this->log->get_log_by_id($log_id);
        
        if (!$log) {
            wp_send_json_error(array('message' => 'Log not found.'));
        }
        
        // Format log for display
        $formatted_log = $this->log->format_log_for_display($log);
        
        wp_send_json_success(array('log' => $formatted_log));
    }
    
    /**
     * AJAX: Check if phone or email exists
     */
    public function ajax_check_duplicate() {
        check_ajax_referer('mac_referral_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to access this.'));
        }
        
        $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
        $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';
        $exclude_id = isset($_POST['exclude_id']) ? intval($_POST['exclude_id']) : 0;
        
        if ($field !== 'phone' && $field !== 'email') {
            wp_send_json_error(array('message' => 'Invalid field.'));
        }
        
        if (empty($value)) {
            wp_send_json_success(array('exists' => false));
        }
        
        $exists = false;
        if ($field === 'phone') {
            $value = preg_replace('/\D/', '', $value);
            if (strlen($value) === 10) {
                $exists = $this->database->phone_exists($value, $exclude_id);
            }
        } else if ($field === 'email') {
            $exists = $this->database->email_exists($value, $exclude_id);
        }
        
        wp_send_json_success(array('exists' => $exists));
    }
    
    public function ajax_find_by_phone() {
        check_ajax_referer('mac_referral_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền truy cập'));
        }
        
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        
        if (empty($phone)) {
            wp_send_json_error(array('message' => 'Số điện thoại không hợp lệ'));
        }
        
        // Normalize số điện thoại (chỉ lấy số) - đảm bảo tìm đúng dù có format hay không
        $phone = preg_replace('/\D/', '', $phone);
        
        // Kiểm tra có đủ 10 số không
        if (strlen($phone) !== 10) {
            wp_send_json_error(array('message' => 'Số điện thoại phải có đủ 10 chữ số'));
        }
        
        $referral = $this->database->get_referral_by_phone($phone);
        
        if ($referral) {
            wp_send_json_success($referral);
        } else {
            wp_send_json_error(array('message' => 'Không tìm thấy referral với số điện thoại này'));
        }
    }
    
    /**
     * AJAX: Tích điểm cho người giới thiệu
     */
    public function ajax_add_phone_referral() {
        check_ajax_referer('mac_referral_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền truy cập'));
        }
        
        $phone_referral = isset($_POST['phone_referral']) ? sanitize_text_field($_POST['phone_referral']) : '';
        $points = isset($_POST['points']) ? intval($_POST['points']) : 10; // Mặc định 10 điểm
        
        if (empty($phone_referral)) {
            wp_send_json_error(array('message' => 'Số điện thoại không hợp lệ'));
        }
        
        // Normalize số điện thoại (chỉ lấy số)
        $phone_referral = preg_replace('/\D/', '', $phone_referral);
        
        // Kiểm tra có đủ 10 số không
        if (strlen($phone_referral) !== 10) {
            wp_send_json_error(array('message' => 'Số điện thoại phải có đủ 10 chữ số'));
        }
        
        $referral = $this->database->get_referral_by_phone($phone_referral);
        
        if (!$referral) {
            wp_send_json_error(array('message' => 'Không tìm thấy người giới thiệu với số điện thoại này'));
        }
        
        $result = $this->database->update_point($referral['id'], $points);
        
        if ($result && $result['success']) {
            $updated_referral = $result['referral'];
            
            // Gửi email nếu có email
            if (!empty($updated_referral['email'])) {
                $this->send_point_update_email($updated_referral, $result['old_point'], $result['new_point']);
            }
            
            wp_send_json_success(array(
                'message' => sprintf('Đã tích %d điểm thành công cho %s (ID: %d)! Điểm mới: %d', 
                    $points, 
                    $updated_referral['fullname'], 
                    $updated_referral['id'],
                    $result['new_point']
                ),
                'referral' => $updated_referral,
                'old_point' => $result['old_point'],
                'new_point' => $result['new_point'],
                'email_sent' => !empty($updated_referral['email'])
            ));
        } else {
            wp_send_json_error(array('message' => 'Có lỗi xảy ra khi tích điểm'));
        }
    }
    
    /**
     * Format phone number thành (XXX) XXX-XXXX
     */
    private function format_phone($phone) {
        $numbers = preg_replace('/\D/', '', $phone);
        if (strlen($numbers) === 10) {
            return '(' . substr($numbers, 0, 3) . ') ' . substr($numbers, 3, 3) . '-' . substr($numbers, 6);
        }
        return $phone;
    }
    
    /**
     * Gửi email thông báo cập nhật điểm
     */
    private function send_point_update_email($referral, $old_point, $new_point) {
        $to = $referral['email'];
        $subject = 'Thông báo cập nhật điểm - MAC Referral';
        
        $point_change = $new_point - $old_point;
        $point_change_text = $point_change > 0 ? '+' . $point_change : (string)$point_change;
        
        $message = "Xin chào " . $referral['fullname'] . ",\n\n";
        $message .= "Điểm của bạn đã được cập nhật:\n";
        $message .= "Số điện thoại: " . $referral['phone'] . "\n";
        $message .= "Điểm cũ: " . $old_point . "\n";
        $message .= "Thay đổi: " . $point_change_text . "\n";
        $message .= "Điểm mới: " . $new_point . "\n\n";
        $message .= "Trân trọng,\n";
        $message .= "Hệ thống MAC Referral";
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        wp_mail($to, $subject, $message, $headers);
    }
}

