<?php
/**
 * Class xử lý log operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Referral_Log {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mac_referral_logs';
    }
    
    /**
     * Tạo bảng log khi plugin được kích hoạt
     */
    public function create_log_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id INT(11) NOT NULL AUTO_INCREMENT,
            referral_id INT(11) NOT NULL,
            action VARCHAR(20) NOT NULL,
            user_id INT(11) DEFAULT NULL,
            user_name VARCHAR(255) DEFAULT NULL,
            old_data TEXT DEFAULT NULL,
            new_data TEXT DEFAULT NULL,
            changes TEXT DEFAULT NULL,
            point_change INT(11) DEFAULT NULL,
            old_point INT(11) DEFAULT NULL,
            new_point INT(11) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            log_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY referral_id (referral_id),
            KEY action (action),
            KEY log_date (log_date),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Lấy tên bảng
     */
    public function get_table_name() {
        return $this->table_name;
    }
    
    /**
     * So sánh dữ liệu cũ và mới để tìm ra các thay đổi
     * @param array $old_data Dữ liệu cũ
     * @param array $new_data Dữ liệu mới
     * @return array Mảng các thay đổi (field => ['old' => value, 'new' => value])
     */
    public function compare_data($old_data, $new_data) {
        $changes = array();
        
        if (empty($old_data) || empty($new_data)) {
            return $changes;
        }
        
        // Danh sách các field cần so sánh
        $fields = array('fullname', 'email', 'phone', 'phone_referral', 'point');
        
        foreach ($fields as $field) {
            $old_value = isset($old_data[$field]) ? $old_data[$field] : '';
            $new_value = isset($new_data[$field]) ? $new_data[$field] : '';
            
            if ($old_value !== $new_value) {
                $changes[$field] = array(
                    'old' => $old_value,
                    'new' => $new_value
                );
            }
        }
        
        return $changes;
    }
    
    /**
     * Ghi log cho một hành động
     * @param int $referral_id ID của referral
     * @param string $action Loại hành động: 'insert', 'update', 'delete', 'point_update'
     * @param array|null $old_data Dữ liệu cũ (chỉ có khi update)
     * @param array|null $new_data Dữ liệu mới
     * @param array $additional_data Dữ liệu bổ sung (point_change, notes, etc.)
     * @return int|false Log ID nếu thành công, false nếu lỗi
     */
    public function log_action($referral_id, $action, $old_data = null, $new_data = null, $additional_data = array()) {
        global $wpdb;
        
        // Lấy thông tin user hiện tại
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $user_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
        
        // Lấy IP address
        $ip_address = '';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip_address = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_address = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        }
        
        // Lấy User Agent
        $user_agent = '';
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $user_agent = sanitize_text_field(substr($_SERVER['HTTP_USER_AGENT'], 0, 255));
        }
        
        // Chuẩn bị dữ liệu log
        $log_data = array(
            'referral_id' => intval($referral_id),
            'action' => sanitize_text_field($action),
            'user_id' => $user_id > 0 ? $user_id : null,
            'user_name' => $user_name,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent
        );
        
        // Xử lý old_data và new_data
        if ($old_data !== null) {
            $log_data['old_data'] = wp_json_encode($old_data);
        }
        
        if ($new_data !== null) {
            $log_data['new_data'] = wp_json_encode($new_data);
        }
        
        // So sánh và lưu changes nếu là update
        if ($action === 'update' && $old_data !== null && $new_data !== null) {
            $changes = $this->compare_data($old_data, $new_data);
            if (!empty($changes)) {
                $log_data['changes'] = wp_json_encode($changes);
            }
        }
        
        // Xử lý point_change, old_point, new_point nếu có
        if (isset($additional_data['point_change'])) {
            $log_data['point_change'] = intval($additional_data['point_change']);
        }
        
        if (isset($additional_data['old_point'])) {
            $log_data['old_point'] = intval($additional_data['old_point']);
        }
        
        if (isset($additional_data['new_point'])) {
            $log_data['new_point'] = intval($additional_data['new_point']);
        }
        
        // Xử lý notes nếu có
        if (isset($additional_data['notes'])) {
            $log_data['notes'] = sanitize_textarea_field($additional_data['notes']);
        }
        
        // Insert vào database
        $result = $wpdb->insert($this->table_name, $log_data);
        
        if ($result !== false) {
            return $wpdb->insert_id;
        }
        
        // Log error nếu có
        if ($wpdb->last_error) {
            error_log('MAC Referral Log Error: ' . $wpdb->last_error);
            error_log('MAC Referral Log Data: ' . print_r($log_data, true));
        }
        
        return false;
    }
    
    /**
     * Lấy logs theo referral_id
     * @param int $referral_id ID của referral
     * @param int $limit Số lượng log muốn lấy (0 = all)
     * @param int $offset Offset cho pagination
     * @return array Array các log entries
     */
    public function get_logs($referral_id, $limit = 50, $offset = 0) {
        global $wpdb;
        
        $referral_id = intval($referral_id);
        
        $sql = "SELECT * FROM {$this->table_name} WHERE referral_id = %d ORDER BY log_date DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT %d OFFSET %d";
            $sql = $wpdb->prepare($sql, $referral_id, $limit, $offset);
        } else {
            $sql = $wpdb->prepare($sql, $referral_id);
        }
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Lấy tất cả logs với search và pagination
     * @param string $search Từ khóa tìm kiếm
     * @param string $action_filter Filter theo action
     * @param int $per_page Số item mỗi trang (0 = all)
     * @param int $current_page Trang hiện tại (bắt đầu từ 1)
     * @return array Mảng gồm 'items' và 'total'
     */
    public function get_all_logs($search = '', $action_filter = '', $per_page = 50, $current_page = 1) {
        global $wpdb;
        
        $where = array();
        $where_values = array();
        
        // Search
        if (!empty($search)) {
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where[] = "(referral_id LIKE %s OR user_name LIKE %s OR old_data LIKE %s OR new_data LIKE %s)";
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        // Action filter
        if (!empty($action_filter) && in_array($action_filter, array('insert', 'update', 'delete', 'point_update'))) {
            $where[] = "action = %s";
            $where_values[] = $action_filter;
        }
        
        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
            if (!empty($where_values)) {
                $where_clause = $wpdb->prepare($where_clause, $where_values);
            }
        }
        
        // Count total
        $count_sql = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        $total = intval($wpdb->get_var($count_sql));
        
        // Get items
        $items = array();
        if ($total > 0) {
            $offset = ($current_page - 1) * $per_page;
            
            $sql = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY log_date DESC";
            
            if ($per_page > 0) {
                $sql .= " LIMIT %d OFFSET %d";
                $sql = $wpdb->prepare($sql, $per_page, $offset);
            }
            
            $items = $wpdb->get_results($sql, ARRAY_A);
        }
        
        return array(
            'items' => $items,
            'total' => $total
        );
    }
    
    /**
     * Lấy một log entry theo ID
     * @param int $log_id ID của log entry
     * @return array|null Log entry hoặc null
     */
    public function get_log_by_id($log_id) {
        global $wpdb;
        
        $log_id = intval($log_id);
        
        $sql = "SELECT * FROM {$this->table_name} WHERE id = %d";
        $sql = $wpdb->prepare($sql, $log_id);
        
        return $wpdb->get_row($sql, ARRAY_A);
    }
    
    /**
     * Format log entry để hiển thị trên UI
     * @param array $log Log entry
     * @return array Formatted log entry
     */
    public function format_log_for_display($log) {
        $formatted = $log;
        
        // Decode JSON data
        if (!empty($log['old_data'])) {
            $formatted['old_data'] = json_decode($log['old_data'], true);
        }
        
        if (!empty($log['new_data'])) {
            $formatted['new_data'] = json_decode($log['new_data'], true);
        }
        
        if (!empty($log['changes'])) {
            $formatted['changes'] = json_decode($log['changes'], true);
        }
        
        // Format date
        if (!empty($log['log_date'])) {
            $formatted['log_date_formatted'] = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['log_date']));
        }
        
        // Format action badge
        $action_badges = array(
            'insert' => array('label' => 'Created', 'class' => 'success'),
            'update' => array('label' => 'Updated', 'class' => 'info'),
            'delete' => array('label' => 'Deleted', 'class' => 'danger'),
            'point_update' => array('label' => 'Point Updated', 'class' => 'warning')
        );
        
        if (isset($action_badges[$log['action']])) {
            $formatted['action_label'] = $action_badges[$log['action']]['label'];
            $formatted['action_class'] = $action_badges[$log['action']]['class'];
        }
        
        return $formatted;
    }
}

