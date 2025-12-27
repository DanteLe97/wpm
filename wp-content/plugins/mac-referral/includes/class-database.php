<?php
/**
 * Class xử lý database operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Referral_Database {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mac_referrals';
    }
    
    /**
     * Tạo bảng khi plugin được kích hoạt
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id INT(11) NOT NULL AUTO_INCREMENT,
            fullname VARCHAR(255) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(20) NOT NULL,
            phone_referral VARCHAR(20) DEFAULT NULL,
            point INT(11) DEFAULT 0,
            create_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY phone (phone),
            KEY phone_referral (phone_referral)
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
     * Lấy tất cả referrals (không phân trang)
     */
    public function get_all_referrals($search = '') {
        return $this->get_referrals($search, 0, 0);
    }
    
    /**
     * Lấy referrals với phân trang
     * @param string $search Từ khóa tìm kiếm
     * @param int $per_page Số item mỗi trang (0 = all)
     * @param int $current_page Trang hiện tại (bắt đầu từ 1)
     * @return array Mảng gồm 'items' và 'total'
     */
    public function get_referrals($search = '', $per_page = 50, $current_page = 1) {
        global $wpdb;
        
        $offset = ($current_page - 1) * $per_page;
        $where = '';
        
        if (!empty($search)) {
            // Normalize số điện thoại trong search (loại bỏ format)
            $search_normalized = preg_replace('/\D/', '', $search);
            // Kiểm tra xem search có chứa chữ cái không
            $has_letters = preg_match('/[a-zA-Z]/', $search);
            // Kiểm tra xem search có chứa @ không (email)
            $has_at = strpos($search, '@') !== false;
            
            // Logic search (tìm theo cụm từ chính xác, không tách từ):
            // 1. Nếu chỉ có số (không có chữ cái) → search theo phone
            // 2. Nếu có @ → search theo email
            // 3. Nếu là text không có @ → search theo fullname
            // Lưu ý: Tất cả đều tìm cụm từ chính xác, ví dụ "hana 1" sẽ tìm "hana 1" chứ không tách thành "hana" và "1"
            
            if (!$has_letters && !empty($search_normalized)) {
                // Chỉ có số → search theo phone (tìm cụm số chính xác)
                $search_phone_term = '%' . $wpdb->esc_like($search_normalized) . '%';
                $search_term = '%' . $wpdb->esc_like($search) . '%';
                
                $where = $wpdb->prepare(
                    "WHERE phone LIKE %s 
                     OR REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), ' ', ''), '-', '') LIKE %s",
                    $search_term, $search_phone_term
                );
            } elseif ($has_at) {
                // Có @ → search theo email (tìm cụm từ chính xác)
                $search_lower = strtolower($search);
                $search_term_lower = '%' . $wpdb->esc_like($search_lower) . '%';
                
                $where = $wpdb->prepare(
                    "WHERE LOWER(email) LIKE %s",
                    $search_term_lower
                );
            } else {
                // Text không có @ → search theo fullname (tìm cụm từ chính xác)
                // Ví dụ: "hana 1" sẽ tìm chính xác "hana 1" trong fullname, không tách từ
                $search_lower = strtolower($search);
                $search_term_lower = '%' . $wpdb->esc_like($search_lower) . '%';
                
                $where = $wpdb->prepare(
                    "WHERE LOWER(fullname) LIKE %s",
                    $search_term_lower
                );
            }
        }
        
        // Đếm tổng số records
        $total_query = "SELECT COUNT(*) FROM {$this->table_name} {$where}";
        $total = $wpdb->get_var($total_query);
        
        // Lấy dữ liệu
        $limit = '';
        if ($per_page > 0) {
            $limit = $wpdb->prepare("LIMIT %d OFFSET %d", $per_page, $offset);
        }
        
        $query = "SELECT * FROM {$this->table_name} {$where} ORDER BY create_date DESC {$limit}";
        $items = $wpdb->get_results($query, ARRAY_A);
        
        return array(
            'items' => $items,
            'total' => intval($total)
        );
    }
    
    /**
     * Lấy referral theo ID
     */
    public function get_referral_by_id($id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
    }
    
    /**
     * Tìm referral theo số điện thoại
     * Hỗ trợ tìm với số điện thoại đã normalize (chỉ số) hoặc có format
     */
    public function get_referral_by_phone($phone) {
        global $wpdb;
        
        // Normalize số điện thoại (chỉ lấy số)
        $phone_normalized = preg_replace('/\D/', '', $phone);
        
        // Tìm theo số điện thoại đã normalize (so sánh với số thuần trong database)
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), ' ', ''), '-', '') = %s LIMIT 1",
                $phone_normalized
            ),
            ARRAY_A
        );
    }
    
    /**
     * Kiểm tra số điện thoại đã tồn tại chưa (trừ ID hiện tại nếu đang edit)
     * Hỗ trợ so sánh với số điện thoại đã normalize
     */
    public function phone_exists($phone, $exclude_id = 0) {
        global $wpdb;
        
        if (empty($phone)) {
            return false;
        }
        
        // Normalize số điện thoại (chỉ lấy số)
        $phone_normalized = preg_replace('/\D/', '', $phone);
        
        $query = "SELECT COUNT(*) FROM {$this->table_name} 
                  WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), ' ', ''), '-', '') = %s";
        $params = array($phone_normalized);
        
        if ($exclude_id > 0) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        $count = $wpdb->get_var(
            $wpdb->prepare($query, $params)
        );
        
        return intval($count) > 0;
    }
    
    /**
     * Kiểm tra email đã tồn tại chưa (trừ ID hiện tại nếu đang edit, chỉ kiểm tra nếu email không rỗng)
     */
    public function email_exists($email, $exclude_id = 0) {
        global $wpdb;
        
        if (empty($email)) {
            return false; // Email rỗng không bị coi là trùng
        }
        
        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE email = %s AND email != ''";
        $params = array($email);
        
        if ($exclude_id > 0) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        $count = $wpdb->get_var(
            $wpdb->prepare($query, $params)
        );
        
        return intval($count) > 0;
    }
    
    /**
     * Thêm referral mới
     */
    public function insert_referral($data) {
        global $wpdb;
        
        $defaults = array(
            'fullname' => '',
            'email' => '',
            'phone' => '',
            'phone_referral' => '',
            'point' => 0
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $insert_data = array(
            'fullname' => sanitize_text_field($data['fullname']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone']),
            'phone_referral' => sanitize_text_field($data['phone_referral']),
            'point' => intval($data['point'])
        );
        
        $format = array('%s', '%s', '%s', '%s', '%d');
        
        $result = $wpdb->insert(
            $this->table_name,
            $insert_data,
            $format
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Cập nhật referral
     */
    public function update_referral($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        if (isset($data['fullname'])) {
            $update_data['fullname'] = sanitize_text_field($data['fullname']);
            $format[] = '%s';
        }
        
        if (isset($data['email'])) {
            $update_data['email'] = sanitize_email($data['email']);
            $format[] = '%s';
        }
        
        if (isset($data['phone'])) {
            $update_data['phone'] = sanitize_text_field($data['phone']);
            $format[] = '%s';
        }
        
        if (isset($data['phone_referral'])) {
            $update_data['phone_referral'] = sanitize_text_field($data['phone_referral']);
            $format[] = '%s';
        }
        
        if (isset($data['point'])) {
            $update_data['point'] = intval($data['point']);
            $format[] = '%d';
        }
        
        if (empty($update_data)) {
            // Không có gì để update, nhưng không phải lỗi
            return true;
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => intval($id)),
            $format,
            array('%d')
        );
        
        // WordPress update trả về số rows affected, hoặc false nếu có lỗi
        // Nếu result là 0 (không có thay đổi) nhưng không có lỗi, vẫn return true
        if ($result === false) {
            // Có lỗi SQL
            return false;
        }
        
        // Trả về true nếu update thành công (kể cả khi result = 0, nghĩa là không có thay đổi)
        return true;
    }
    
    /**
     * Cập nhật điểm (cộng/trừ)
     */
    public function update_point($id, $point_change) {
        global $wpdb;
        
        $referral = $this->get_referral_by_id($id);
        if (!$referral) {
            return false;
        }
        
        $current_point = intval($referral['point']);
        $point_change = intval($point_change);
        $new_point = $current_point + $point_change;
        
        // Kiểm tra điểm không được âm
        if ($new_point < 0) {
            return array(
                'success' => false,
                'error' => 'Điểm không được âm. Điểm hiện tại: ' . $current_point . ', bạn không thể trừ quá ' . $current_point . ' điểm.'
            );
        }
        
        $result = $this->update_referral($id, array('point' => $new_point));
        
        // Trả về thông tin referral để gửi email
        if ($result) {
            return array(
                'success' => true,
                'referral' => $this->get_referral_by_id($id),
                'old_point' => $current_point,
                'new_point' => $new_point
            );
        }
        
        return false;
    }
    
    /**
     * Xóa referral
     */
    public function delete_referral($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_name,
            array('id' => intval($id)),
            array('%d')
        );
    }
    
    /**
     * Xóa bảng (khi deactivate nếu cần)
     */
    public function drop_table() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
    }
}

