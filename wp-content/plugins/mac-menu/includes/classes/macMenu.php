<?php
class macMenu {
    private $_cat_menu = '';
    public function __construct(){
        global $wpdb;
        $this->_cat_menu = $wpdb->prefix.'mac_cat_menu';
    }
    public function get_cat_menu_name() {
        return $this->_cat_menu;
    }
    /* list cat */
    public function all_cat(){
        global $wpdb;
        $sql = "SELECT * FROM $this->_cat_menu WHERE `is_hidden` = '0' ORDER BY `order`  ASC";
        $items = $wpdb->get_results($sql);
        return $items;
    }
    public function find_cat_menu($id){
        global $wpdb;
        // Nếu id không phải số (ví dụ 'new') thì bỏ qua
        if (!is_numeric($id)) {
            return array();
        }
        $sql = $wpdb->prepare("SELECT * FROM $this->_cat_menu WHERE `id` = %d", intval($id));
        $items = $wpdb->get_results($sql);
        return $items;
    }
    public function find_cat_menu_by_name($name){
        global $wpdb;
        //$sql = "SELECT * FROM $this->_cat_menu WHERE `category_name` = '$name'";
        $sql = $wpdb->prepare("SELECT * FROM $this->_cat_menu WHERE category_name = %s  and `is_hidden` = '0' ",$name);
        $items = $wpdb->get_results($sql);
        return $items;
    }
    public function find_cat_menu_by_name_all_child_cats($names) {
        global $wpdb;
    
        if (!is_array($names) || empty($names)) {
            return []; // Trả về mảng rỗng nếu đầu vào không phải là mảng hoặc mảng rỗng
        }
    
        // Thoát chuỗi để tránh SQL injection
        $names_placeholder = implode(',', array_map(function($name) use ($wpdb) {
            return $wpdb->prepare('%s', $name);
        }, $names));
        var_dump($names_placeholder);
        // Xây dựng câu truy vấn SQL
        $sql = "
            SELECT *
            FROM $this->_cat_menu
            WHERE `category_name` IN ($names_placeholder)
                OR `parents_category` IN (
                    SELECT `id`
                    FROM $this->_cat_menu AS sub1
                    WHERE sub1.`parents_category` IN ($names_placeholder)
                    OR sub1.`parents_category` IN (
                        SELECT `id`
                        FROM $this->_cat_menu AS sub2
                        WHERE sub2.`parents_category` IN ($names_placeholder)
                        OR sub2.`parents_category` IN (
                            SELECT `id`
                            FROM $this->_cat_menu AS sub3
                            WHERE sub3.`parents_category` IN ($names_placeholder)
                            OR sub3.`parents_category` IN (
                                SELECT `id`
                                FROM $this->_cat_menu AS sub4
                                WHERE sub4.`parents_category` IN ($names_placeholder)
                            )
                        )
                    )
                )
            and `is_hidden` = '0'
            ORDER BY `order` ASC
        ";
        // Thực thi truy vấn và kiểm tra lỗi
        $items = $wpdb->get_results($sql);
    
        if ($wpdb->last_error) {
            // In lỗi cơ sở dữ liệu nếu có
            echo 'Database error: ' . $wpdb->last_error;
        }
    
        return $items;
    }
    public function find_cat_menu_all_child_cats($ids) {
        global $wpdb;
    
        if (!is_array($ids) || empty($ids)) {
            return []; // Trả về mảng rỗng nếu đầu vào không phải là mảng hoặc mảng rỗng
        }
    
        // Chuyển mảng các ID thành một chuỗi các giá trị được phân tách bởi dấu phẩy
        $ids_placeholder = implode(',', array_map('intval', $ids));
    
        $sql = "
            SELECT *
            FROM $this->_cat_menu
            WHERE (`id` IN ($ids_placeholder)
                OR `parents_category` IN ($ids_placeholder)
                OR `parents_category` IN (
                    SELECT `id`
                    FROM $this->_cat_menu AS sub1
                    WHERE sub1.`parents_category` IN ($ids_placeholder)
                    OR sub1.`parents_category` IN (
                        SELECT `id`
                        FROM $this->_cat_menu AS sub2
                        WHERE sub2.`parents_category` IN ($ids_placeholder)
                        OR sub2.`parents_category` IN (
                            SELECT `id`
                            FROM $this->_cat_menu AS sub3
                            WHERE sub3.`parents_category` IN ($ids_placeholder)
                            OR sub3.`parents_category` IN (
                                SELECT `id`
                                FROM $this->_cat_menu AS sub4
                                WHERE sub4.`parents_category` IN ($ids_placeholder)
                            )
                        )
                    )
                )
				)
                AND `is_hidden` = '0'
                ORDER BY `order` ASC
        ";
    
        $items = $wpdb->get_results($sql);
        return $items;
    }
    public function save_cat($data){
        global $wpdb;
        
        // Lấy toàn bộ table TRƯỚC khi insert (old_data)
        $old_full_table = $this->getFullTableData();
        
        // DISABLED: Không tạo snapshot nữa, dùng activity_log thay thế
        // $this->maybeCreateSnapshot('before_create');
        
        $wpdb->insert($this->_cat_menu, $data);
        $lastId = $wpdb->insert_id;
        
        // Lấy toàn bộ table SAU khi insert (new_data)
        $new_full_table = $this->getFullTableData();
        
        // Log the creation with full table data
        if (function_exists('log_activity')) {
            $category_name = $data['category_name'] ?? 'Unknown';
            $description = "Created new category: '$category_name'";
            
            // Lưu toàn bộ table vào old_data và new_data (compressed)
            log_activity('category_create', $description, 'mac_cat_menu', 1, null, $old_full_table, $new_full_table, true);
        }
        
        $item = $this->find_cat_menu($lastId);
        return $item;
    }
    public function update_cat($id,$data){
        global $wpdb;
        
        // DISABLED: Không tạo snapshot nữa, dùng activity_log thay thế
        // $this->maybeCreateSnapshot('before_update');
        
        // Get current data for comparison
        $current_data = $this->find_cat_menu($id);
        if (empty($current_data)) {
            return false;
        }
        
        $current = $current_data[0];
        
        // Compare all fields to detect changes
        $changed_fields = array();
        $old_values = array();
        $new_values = array();
        
        foreach ($data as $key => $new_value) {
            $old_value = $current->$key ?? '';
            
            // Handle different data types for comparison
            if (is_array($new_value)) {
                $new_value = json_encode($new_value);
            }
            if (is_array($old_value)) {
                $old_value = json_encode($old_value);
            }
            
            // Convert to string for comparison
            $new_value = (string)$new_value;
            $old_value = (string)$old_value;
            
            if ($new_value !== $old_value) {
                $changed_fields[] = $key;
                $old_values[$key] = $old_value;
                $new_values[$key] = $new_value;
            }
        }
        
        // Only update if there are changes
        if (empty($changed_fields)) {
            return $current_data; // No changes, return current data
        }
        
        // Lấy toàn bộ table TRƯỚC khi update (old_data)
        $old_full_table = $this->getFullTableData();
        
        // Perform the update
        $result = $wpdb->update(
            $this->_cat_menu,
            $data,
            ['id' => $id]
        );
        
        // Lấy toàn bộ table SAU khi update (new_data)
        $new_full_table = $this->getFullTableData();
        
        // Log the update with full table data
        if (function_exists('log_activity')) {
            $category_name = $current->category_name ?? 'Unknown';
            
            // Log to activity log - SIMPLE: log exactly what was saved to database
            $changes_description = array();
            foreach ($changed_fields as $field) {
                $old_val = $old_values[$field];
                $new_val = $new_values[$field];
                
                // Log exactly what was saved with HTML formatting
                $changes_description[] = "$field: '<span style=\"color: #999;\">$old_val</span>' → '<span style=\"color: #d32f2f; font-weight: bold;\">$new_val</span>'";
            }
            
            $description = "Updated category '$category_name': " . implode(', ', $changes_description);
            
            // Lưu toàn bộ table vào old_data và new_data (compressed)
            log_activity('category_update', $description, 'mac_cat_menu', 1, null, $old_full_table, $new_full_table, true);
            
            // Disabled: per-update file logs are no longer written to disk
        }
        
        if ($result === false) {
            if (function_exists('log_activity')) {
                log_activity('database_error', "Failed to update category ID: $id", 'mac_cat_menu', 0, $wpdb->last_error);
            }
            return false;
        }
        
        return $this->find_cat_menu($id);
    }
    public function update_cat_inside($id, $data) {
        global $wpdb;
        
        // Lấy dữ liệu hiện tại
        $current_data = $this->find_cat_menu($id);
        if (empty($current_data)) {
            return false;
        }
        
        // Chỉ giữ lại những trường có thay đổi
        $update_data = array();
        foreach ($data as $key => $value) {
            if ($current_data[0]->$key !== $value) {
                $update_data[$key] = $value;
            }
        }
        
        // Nếu không có trường nào thay đổi, return luôn
        if (empty($update_data)) {
            return $current_data;
        }
        
        // Log the update with simplified changes
        if (function_exists('log_activity') && !empty($update_data)) {
            $category_name = $current_data[0]->category_name ?? 'Unknown';
            
            // Log to activity log - SIMPLE: log exactly what was saved to database
            $changes_description = array();
            foreach ($update_data as $field => $new_value) {
                $old_value = $current_data[0]->$field ?? '';
                
                // Handle different data types for comparison
                if (is_array($new_value)) {
                    $new_value_str = json_encode($new_value);
                } else {
                    $new_value_str = (string)$new_value;
                }
                if (is_array($old_value)) {
                    $old_value_str = json_encode($old_value);
                } else {
                    $old_value_str = (string)$old_value;
                }
                
                // Log exactly what was saved with HTML formatting
                $changes_description[] = "$field: '<span style=\"color: #999;\">$old_value_str</span>' → '<span style=\"color: #d32f2f; font-weight: bold;\">$new_value_str</span>'";
            }
            
            $description = "Updated category '$category_name': " . implode(', ', $changes_description);
            
            // Prepare old and new data for separate columns (update_cat_inside function)
            $old_data_json = json_encode($current_data[0], JSON_PRETTY_PRINT);
            $new_data_json = json_encode($data, JSON_PRETTY_PRINT);
            
            log_activity('category_update', $description, 'mac_cat_menu', 1, null, $old_data_json, $new_data_json);
            
            // Disabled: per-update file logs are no longer written to disk
        }
        
        // Update chỉ những trường thay đổi
        $wpdb->update(
            $this->_cat_menu, 
            $update_data,
            ['id' => $id]
        );
        
        // Return dữ liệu mới
        return $this->find_cat_menu($id);
    }
    
    /**
     * Update backup data for a category
     */
    public function update_cat_backup($id, $backup_data_json) {
        global $wpdb;
        
        // Check if backup column exists, if not create it
        $this->ensure_backup_column_exists();
        
        // Update backup data
        $result = $wpdb->update(
            $this->_cat_menu,
            ['backup_data' => $backup_data_json],
            ['id' => $id]
        );
        
        if ($result === false) {
            error_log('MAC Menu Error: Failed to update backup data for ID: ' . $id);
            return false;
        }
        
        error_log('MAC Menu Success: Backup data updated for ID: ' . $id);
        return true;
    }
    
    /**
     * Get backup data for a category
     */
    public function get_cat_backup($id) {
        global $wpdb;
        
        // Check if backup column exists
        $this->ensure_backup_column_exists();
        
        $sql = $wpdb->prepare("SELECT backup_data FROM $this->_cat_menu WHERE id = %d", $id);
        $result = $wpdb->get_var($sql);
        
        if ($result) {
            return json_decode($result, true);
        }
        
        return null;
    }
    
    /**
     * Ensure backup_data column exists in the table
     */
    private function ensure_backup_column_exists() {
        global $wpdb;
        
        // Check if column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $this->_cat_menu LIKE 'backup_data'");
        
        if (empty($column_exists)) {
            // Add backup_data column
            $sql = "ALTER TABLE $this->_cat_menu ADD COLUMN backup_data LONGTEXT NULL AFTER category_inside_order";
            $wpdb->query($sql);
            error_log('MAC Menu: Added backup_data column to table');
        }
    }
    
    /**
     * Lấy toàn bộ data từ table mac_cat_menu
     * @return array - Tất cả records từ table
     */
    private function getFullTableData() {
        global $wpdb;
        $cat_menu_table = $wpdb->prefix . 'mac_cat_menu';
        $all_data = $wpdb->get_results("SELECT * FROM {$cat_menu_table}", ARRAY_A);
        return $all_data ?: array();
    }
    
    public function destroy_Cat($id){
        global $wpdb;
    
        // Lấy toàn bộ table TRƯỚC khi delete (old_data)
        $old_full_table = $this->getFullTableData();
    
        // DISABLED: Không tạo snapshot nữa, dùng activity_log thay thế
        // $this->maybeCreateSnapshot('before_delete');
    
        // Get category info before deletion for logging
        $category_data = $this->find_cat_menu($id);
        $category_name = $category_data[0]->category_name ?? 'Unknown';
        
        // Lấy tất cả id con của $id
        $child_ids = $wpdb->get_col(
            $wpdb->prepare("SELECT id FROM $this->_cat_menu WHERE parents_category = %d", $id)
        );
    
        // Đệ quy xóa từng id con
        foreach ($child_ids as $child_id) {
            $this->destroy_Cat($child_id);
        }
    
        // Xóa chính nó
        $wpdb->delete($this->_cat_menu, [ 'id' => $id ]);
        
        // Lấy toàn bộ table SAU khi delete (new_data)
        $new_full_table = $this->getFullTableData();
        
        // Log the deletion with full table data
        if (function_exists('log_activity')) {
            $child_count = count($child_ids);
            $description = "Deleted category '$category_name' with $child_count child categories";
            
            // Lưu toàn bộ table vào old_data và new_data (compressed)
            log_activity('category_delete', $description, 'mac_cat_menu', 1 + $child_count, null, $old_full_table, $new_full_table, true);
        }
        
        return true;
    }
    
    /**
     * DISABLED: Không tạo snapshot nữa, dùng activity_log thay thế
     * Tạo snapshot toàn bộ table TRƯỚC khi thay đổi database
     * Mỗi khi có thay đổi (create/update/delete) → tạo snapshot ngay để có thể restore về trạng thái trước đó
     * @param string $action_type - 'before_create', 'before_update', 'before_delete'
     */
    private function maybeCreateSnapshot($action_type) {
        // DISABLED: Không tạo snapshot nữa, dùng activity_log thay thế
        return;
        
        /* OLD CODE - DISABLED
        if (!class_exists('MacMenuTableSnapshot')) {
            return;
        }
        
        // Mỗi khi có thay đổi database → tạo snapshot toàn bộ table ngay
        if (function_exists('create_table_snapshot')) {
            $snapshot_id = create_table_snapshot($action_type, "Auto snapshot - {$action_type}");
            if ($snapshot_id) {
                update_option('mac_menu_last_snapshot_id', $snapshot_id);
            }
        }
        */
    }
    // public function destroy_Cat($id){
    //     global $wpdb;
    //     $wpdb->delete($this->_cat_menu,[
    //         'id' => $id
    //     ]);
    //     return true;
    // }
    public function paginate_cat($limit = 100){
        global $wpdb;

        $s = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : '';
        $paged = isset( $_REQUEST['paged'] ) ? $_REQUEST['paged'] : 1;

        // Lấy tổng số records
        //$sql = "SELECT count(id) FROM $this->_cat_menu ";
        $sql = "SELECT count(id) FROM $this->_cat_menu WHERE `parents_category` = '0'";
        // Tim kiếm
        if( $s ){
            $sql .= " AND ( id LIKE '%$s%' )";
        }
        $total_items = $wpdb->get_var($sql);
        
        // Thuật toán phân trang
        /*
        Limit: limit
        Tổng số trang: total_pages
        Tính offset
        */
        $total_pages    = ceil( $total_items / $limit );
        $offset         = ( $paged * $limit ) - $limit;

        //$sql = "SELECT * FROM $this->_cat_menu WHERE `parents_category` = '0'";
        $sql = "
            SELECT t1.*
            FROM $this->_cat_menu AS t1
            LEFT JOIN $this->_cat_menu AS t2 ON t1.parents_category = t2.id
            WHERE t1.parents_category = 0 OR (t1.parents_category != 0 AND t2.id IS NULL)
        ";
        //$sql = "SELECT * FROM $this->_cat_menu ";
        // Tim kiếm
        if( $s ){
            $sql .= " AND ( id LIKE '%$s%' )";
        }
        $sql .= " ORDER BY `order` ASC";
        $sql .= " LIMIT $limit OFFSET $offset";


        $items = $wpdb->get_results($sql);

        return [
            'total_pages'   => $total_pages,
            'total_items'   => $total_items,
            'items'         => $items
        ];

    }
    /* all  */
    public function all_cat_by_not_is_table(){
        global $wpdb;
        $sql = "SELECT *
                FROM $this->_cat_menu WHERE 
                `is_table` = '0' ORDER BY `order` ASC ";

        $items = $wpdb->get_results($sql);
        return $items;
    }
    public function all_cat_menu_has_item(){
        global $wpdb;
        $sql = "SELECT *
        FROM $this->_cat_menu WHERE `parents_category` = '0' and `is_hidden` = '0' ORDER BY `order` ASC";

        $items = $wpdb->get_results($sql);
        return $items;
    }
    public function all_cat_by_parent_cat_menu($id){
        global $wpdb;
        // Nếu id không phải số (ví dụ 'new') thì bỏ qua
        if (!is_numeric($id)) {
            return array();
        }
        $sql = $wpdb->prepare(
            "SELECT * FROM $this->_cat_menu WHERE `parents_category` = %d ORDER BY `order` ASC ",
            intval($id)
        );
        $items = $wpdb->get_results($sql);
        return $items;
    }
    public function all_cat_by_parent_cat_menu_html($id){
        global $wpdb;
        // Nếu id không phải số (ví dụ 'new') thì bỏ qua
        if (!is_numeric($id)) {
            return array();
        }
        $sql = $wpdb->prepare(
            "SELECT * FROM $this->_cat_menu WHERE `parents_category` = %d and `is_hidden` = '0' ORDER BY `order` ASC ",
            intval($id)
        );
        $items = $wpdb->get_results($sql);
        return $items;
    }
    
    public function change_status($order_id,$status){
        global $wpdb;
        $wpdb->update(
            $this->_menu, 
            [
                'status' => $status
            ], 
            [
                'id' => $order_id
            ]
        );
        return true;
    }
    public function change_position($id,$position){
        global $wpdb;
        $wpdb->update(
            $this->_cat_menu, 
            
            [
                'order' => $position
            ],
            [
                'ID' => $id
            ]
        );
        
        return true;
    }
    public function change_position_cat($id,$position){
        global $wpdb;
        $wpdb->update(
            $this->_menu, 
            
            [
                'order' => $position
            ],
            [
                'ID' => $id
            ]
        );
        
        return true;
    }

    public function getAllIdCatChildInside($id_category) {
        $arrayCatId = array();
        $objmacMenu = new macMenu();
        $result = $objmacMenu->all_cat_by_parent_cat_menu($id_category);
       
            foreach( $result as $item ){
                if ($item->category_inside == 1){
                    $arrayCatId[] = $item->id;
                }
            }
      
        return $arrayCatId;
    }
    public function getAllIdCatChildInsideHTML($id_category) {
        $arrayCatId = array();
        $objmacMenu = new macMenu();
        $result = $objmacMenu->all_cat_by_parent_cat_menu_html($id_category);
       
            foreach( $result as $item ){
                if ($item->category_inside == 1){
                    $arrayCatId[] = $item->id;
                }
            }
      
        return $arrayCatId;
    }
}


class MacMenuActivityLog {
	private $_activity_log = '';

	public function __construct() {
		global $wpdb;
		$this->_activity_log = $wpdb->prefix . 'mac_menu_activity_log';
	}

	public function createTable() {
		global $wpdb;
		$table_name = $this->_activity_log;
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
		if ($table_exists) { return; }
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE $table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			action_type varchar(50) NOT NULL,
			action_description text,
			old_data longtext,
			new_data longtext,
			affected_table varchar(100),
			affected_records int(11),
			user_id int(11),
			user_name varchar(100),
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			additional_data longtext,
			error_message text,
			error_code varchar(50),
			plugin_version varchar(20),
			PRIMARY KEY (id),
			KEY idx_created_at (created_at),
			KEY idx_action_type (action_type)
		) $charset_collate;";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	public function forceCreateTable() {
		global $wpdb;
		$table_name = $this->_activity_log;
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			action_type varchar(50) NOT NULL,
			action_description text,
			old_data longtext,
			new_data longtext,
			affected_table varchar(100),
			affected_records int(11),
			user_id int(11),
			user_name varchar(100),
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			additional_data longtext,
			error_message text,
			error_code varchar(50),
			plugin_version varchar(20),
			checksum varchar(64),
			is_full_table tinyint(1) DEFAULT 0,
			PRIMARY KEY (id),
			KEY idx_created_at (created_at),
			KEY idx_action_type (action_type)
		) $charset_collate;";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		$this->migrateDropUnusedColumns();
		$this->migrateAddNewColumns();
	}

	public function migrateDropUnusedColumns() {
		global $wpdb;
		$table_name = $this->_activity_log;
		$columns = $wpdb->get_col( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
			DB_NAME,
			$table_name
		));
		if (!$columns) { return; }
		if (in_array('ip_address', $columns, true)) {
			$wpdb->query("ALTER TABLE $table_name DROP COLUMN ip_address");
		}
		if (in_array('wp_version', $columns, true)) {
			$wpdb->query("ALTER TABLE $table_name DROP COLUMN wp_version");
		}
	}

	/**
	 * Thêm các cột mới cho full table snapshot
	 */
	public function migrateAddNewColumns() {
		global $wpdb;
		$table_name = $this->_activity_log;
		$columns = $wpdb->get_col( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
			DB_NAME,
			$table_name
		));
		if (!$columns) { return; }
		
		// Thêm cột checksum nếu chưa có
		if (!in_array('checksum', $columns, true)) {
			$wpdb->query("ALTER TABLE $table_name ADD COLUMN checksum varchar(64) NULL AFTER plugin_version");
		}
		
		// Thêm cột is_full_table nếu chưa có
		if (!in_array('is_full_table', $columns, true)) {
			$wpdb->query("ALTER TABLE $table_name ADD COLUMN is_full_table tinyint(1) DEFAULT 0 AFTER checksum");
		}
	}

	/**
	 * Compress và encode data để lưu vào database
	 * @param array|string $data - Data cần compress
	 * @return array - ['data' => compressed_base64, 'checksum' => md5]
	 */
	private function compressData($data) {
		if (is_array($data)) {
			$json_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} else {
			$json_data = $data;
		}
		
		if (empty($json_data)) {
			return ['data' => null, 'checksum' => null];
		}
		
		// Compress với gzip để giảm dung lượng
		$compressed = gzcompress($json_data, 9); // Level 9 = max compression
		$compressed_base64 = base64_encode($compressed);
		
		// Tính checksum để verify integrity
		$checksum = md5($json_data);
		
		return ['data' => $compressed_base64, 'checksum' => $checksum];
	}

	/**
	 * Decompress data từ database
	 * @param string $compressed_base64 - Compressed base64 string
	 * @return array|false - Decoded data hoặc false nếu lỗi
	 */
	public function decompressData($compressed_base64) {
		if (empty($compressed_base64)) {
			return null;
		}
		
		try {
			$compressed = base64_decode($compressed_base64);
			$json_data = gzuncompress($compressed);
			if ($json_data === false) {
				return false;
			}
			$data = json_decode($json_data, true);
			return $data;
		} catch (Exception $e) {
			error_log("MAC Menu: Failed to decompress data - " . $e->getMessage());
			return false;
		}
	}


	public function log($action_type, $description, $table = null, $records = 0, $error = null, $old_data = null, $new_data = null, $is_full_table = false) {
		global $wpdb;
		$this->createTable();
		$this->migrateAddNewColumns();
		$table_name = $this->_activity_log;
		$current_user = wp_get_current_user();
		$user_id = $current_user->ID;
		$user_name = $current_user->display_name;
		if ( ! function_exists('get_plugin_data') ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_version = '';
		$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/mac-menu/mac-menu.php', false, false);
		if ( ! empty($plugin_data['Version']) ) { $plugin_version = $plugin_data['Version']; }
		$hanoi_timezone = new DateTimeZone('Asia/Ho_Chi_Minh');
		$hanoi_datetime = new DateTime('now', $hanoi_timezone);
		$hanoi_timestamp = $hanoi_datetime->format('Y-m-d H:i:s');
		
		// Nếu is_full_table = true, compress old_data và new_data
		$old_data_compressed = null;
		$new_data_compressed = null;
		$checksum = null;
		
		if ($is_full_table) {
			if (!empty($old_data)) {
				$old_compressed = $this->compressData($old_data);
				$old_data_compressed = $old_compressed['data'];
			}
			if (!empty($new_data)) {
				$new_compressed = $this->compressData($new_data);
				$new_data_compressed = $new_compressed['data'];
				// Dùng checksum của new_data (hoặc old_data nếu new_data rỗng)
				$checksum = $new_compressed['checksum'] ?: ($old_compressed['checksum'] ?? null);
			} elseif (!empty($old_data)) {
				$checksum = $old_compressed['checksum'] ?? null;
			}
		} else {
			// Không compress, giữ nguyên format cũ (backward compatibility)
			$old_data_compressed = $old_data;
			$new_data_compressed = $new_data;
		}
		
		$data = array(
			'action_type' => $action_type,
			'action_description' => $description,
			'old_data' => $old_data_compressed,
			'new_data' => $new_data_compressed,
			'affected_table' => $table,
			'affected_records' => $records,
			'user_id' => $user_id,
			'user_name' => $user_name,
			'error_message' => $error,
			'plugin_version' => $plugin_version,
			'created_at' => $hanoi_timestamp,
			'checksum' => $checksum,
			'is_full_table' => $is_full_table ? 1 : 0
		);
		$wpdb->insert($table_name, $data);
		$this->cleanup();
	}

	public function cleanup() {
		global $wpdb;
		$table_name = $this->_activity_log;
		$hanoi_timezone = new DateTimeZone('Asia/Ho_Chi_Minh');
		$hanoi_datetime = new DateTime('now', $hanoi_timezone);
		// Xóa logs cũ hơn 30 ngày tính từ ngày được lưu
		$hanoi_datetime->sub(new DateInterval('P30D'));
		$cutoff_date = $hanoi_datetime->format('Y-m-d H:i:s');
		$wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE created_at < %s", $cutoff_date));
	}

	public function getLogs($limit = 50, $offset = 0, $action_type = null, $date_filter = null, $user_filter = null) {
		global $wpdb;
		$table_name = $this->_activity_log;
		$where_conditions = array();
		$where_values = array();
		
		if ($action_type) {
			$where_conditions[] = "action_type = %s";
			$where_values[] = $action_type;
		}
		
		if ($date_filter) {
			// Filter theo ngày: format Y-m-d
			$where_conditions[] = "DATE(created_at) = %s";
			$where_values[] = $date_filter;
		}
		
		if ($user_filter) {
			// Filter theo user_id hoặc user_name
			if (is_numeric($user_filter)) {
				$where_conditions[] = "user_id = %d";
				$where_values[] = intval($user_filter);
			} else {
				$where_conditions[] = "user_name = %s";
				$where_values[] = $user_filter;
			}
		}
		
		$where_clause = '';
		if (!empty($where_conditions)) {
			$where_clause = " WHERE " . implode(" AND ", $where_conditions);
		}
		
		$sql = "SELECT * FROM $table_name" . $where_clause . " ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$where_values[] = $limit;
		$where_values[] = $offset;
		// Use call_user_func_array to pass array to prepare
		$sql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $where_values));
		return $wpdb->get_results($sql);
	}

	public function getLogCount($action_type = null, $date_filter = null, $user_filter = null) {
		global $wpdb;
		$table_name = $this->_activity_log;
		$where_conditions = array();
		$where_values = array();
		
		if ($action_type) {
			$where_conditions[] = "action_type = %s";
			$where_values[] = $action_type;
		}
		
		if ($date_filter) {
			// Filter theo ngày: format Y-m-d
			$where_conditions[] = "DATE(created_at) = %s";
			$where_values[] = $date_filter;
		}
		
		if ($user_filter) {
			// Filter theo user_id hoặc user_name
			if (is_numeric($user_filter)) {
				$where_conditions[] = "user_id = %d";
				$where_values[] = intval($user_filter);
			} else {
				$where_conditions[] = "user_name = %s";
				$where_values[] = $user_filter;
			}
		}
		
		$where_clause = '';
		if (!empty($where_conditions)) {
			$where_clause = " WHERE " . implode(" AND ", $where_conditions);
		}
		
		$sql = "SELECT COUNT(*) FROM $table_name" . $where_clause;
		if (!empty($where_values)) {
			// Use call_user_func_array to pass array to prepare
			$sql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $where_values));
		}
		return (int) $wpdb->get_var($sql);
	}

	// Static helper wrappers for backward compatibility
	public static function createTableStatic() { (new self())->createTable(); }
	public static function forceCreateTableStatic() { (new self())->forceCreateTable(); }
	public static function migrateDropUnusedColumnsStatic() { (new self())->migrateDropUnusedColumns(); }
	public static function logStatic($action_type, $description, $table = null, $records = 0, $error = null, $old_data = null, $new_data = null) {
		(new self())->log($action_type, $description, $table, $records, $error, $old_data, $new_data);
	}
	public static function cleanupStatic() { (new self())->cleanup(); }
	public static function getLogsStatic($limit = 50, $offset = 0, $action_type = null) { return (new self())->getLogs($limit, $offset, $action_type); }
	public static function getLogCountStatic($action_type = null) { return (new self())->getLogCount($action_type); }
}

/**
 * Class MacMenuTableSnapshot
 * Quản lý snapshot toàn bộ table mac_cat_menu
 * Tương tự MacMenuActivityLog nhưng lưu toàn bộ table thay vì từng record
 */
class MacMenuTableSnapshot {
	private $_snapshot_table = '';
	private $_cat_menu_table = '';

	public function __construct() {
		global $wpdb;
		$this->_snapshot_table = $wpdb->prefix . 'mac_menu_table_snapshots';
		$this->_cat_menu_table = $wpdb->prefix . 'mac_cat_menu';
	}

	/**
	 * Tạo snapshot table nếu chưa có
	 */
	public function createTable() {
		global $wpdb;
		$table_name = $this->_snapshot_table;
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
		if ($table_exists) { return; }
		
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE $table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			action_type varchar(50) NOT NULL,
			action_description text,
			snapshot_data longtext NOT NULL,
			record_count int(11) NOT NULL DEFAULT 0,
			checksum varchar(64) NOT NULL,
			affected_table varchar(100),
			user_id int(11),
			user_name varchar(100),
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			plugin_version varchar(20),
			PRIMARY KEY (id),
			KEY idx_created_at (created_at),
			KEY idx_action_type (action_type)
		) $charset_collate;";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	/**
	 * Force create table (dùng khi cần update schema)
	 */
	public function forceCreateTable() {
		global $wpdb;
		$table_name = $this->_snapshot_table;
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			action_type varchar(50) NOT NULL,
			action_description text,
			snapshot_data longtext NOT NULL,
			record_count int(11) NOT NULL DEFAULT 0,
			checksum varchar(64) NOT NULL,
			affected_table varchar(100),
			user_id int(11),
			user_name varchar(100),
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			plugin_version varchar(20),
			PRIMARY KEY (id),
			KEY idx_created_at (created_at),
			KEY idx_action_type (action_type)
		) $charset_collate;";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	/**
	 * Tạo snapshot toàn bộ table
	 * @param string $action_type - 'table_snapshot', 'before_import', 'before_delete', etc.
	 * @param string $description - Mô tả snapshot
	 * @return int|false - Snapshot ID hoặc false nếu lỗi
	 */
	public function createSnapshot($action_type = 'table_snapshot', $description = '') {
		global $wpdb;
		$this->createTable();
		
		// Lấy toàn bộ data từ table
		$all_data = $wpdb->get_results("SELECT * FROM {$this->_cat_menu_table}", ARRAY_A);
		
		if (empty($all_data)) {
			return false;
		}
		
		// Convert to JSON
		$json_data = json_encode($all_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		
		// Compress với gzip để giảm dung lượng
		$compressed = gzcompress($json_data, 9); // Level 9 = max compression
		$compressed_base64 = base64_encode($compressed);
		
		// Tính checksum để verify integrity
		$checksum = md5($json_data);
		
		// Lấy thông tin user
		$current_user = wp_get_current_user();
		$user_id = $current_user->ID;
		$user_name = $current_user->display_name;
		
		// Lấy plugin version
		$plugin_version = '';
		if (!function_exists('get_plugin_data')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/mac-menu/mac-menu.php', false, false);
		if (!empty($plugin_data['Version'])) {
			$plugin_version = $plugin_data['Version'];
		}
		
		// Lấy thời gian Hanoi
		$hanoi_timezone = new DateTimeZone('Asia/Ho_Chi_Minh');
		$hanoi_datetime = new DateTime('now', $hanoi_timezone);
		$hanoi_timestamp = $hanoi_datetime->format('Y-m-d H:i:s');
		
		// Tạo description nếu chưa có
		if (empty($description)) {
			$description = "Table snapshot created - {$action_type}";
		}
		
		// Lưu vào database
		$data = array(
			'action_type' => $action_type,
			'action_description' => $description,
			'snapshot_data' => $compressed_base64,
			'record_count' => count($all_data),
			'checksum' => $checksum,
			'affected_table' => 'mac_cat_menu',
			'user_id' => $user_id,
			'user_name' => $user_name,
			'plugin_version' => $plugin_version,
			'created_at' => $hanoi_timestamp
		);
		
		$result = $wpdb->insert($this->_snapshot_table, $data);
		
		if ($result) {
			$snapshot_id = $wpdb->insert_id;
			
			// Cleanup old snapshots (giữ tối đa 30 snapshots)
			$this->cleanup(30);
			
			return $snapshot_id;
		}
		
		return false;
	}

	/**
	 * Lấy snapshot theo ID
	 * @param int $snapshot_id
	 * @return array|false - Decoded data hoặc false nếu lỗi
	 */
	public function getSnapshot($snapshot_id) {
		global $wpdb;
		
		$snapshot = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$this->_snapshot_table} WHERE id = %d",
			$snapshot_id
		), ARRAY_A);
		
		if (!$snapshot) {
			return false;
		}
		
		// Decompress data
		$compressed = base64_decode($snapshot['snapshot_data']);
		$json_data = gzuncompress($compressed);
		$data = json_decode($json_data, true);
		
		if (!$data) {
			return false;
		}
		
		// Verify checksum
		$current_checksum = md5($json_data);
		if ($current_checksum !== $snapshot['checksum']) {
			return false; // Data corrupted
		}
		
		return array(
			'id' => $snapshot['id'],
			'action_type' => $snapshot['action_type'],
			'action_description' => $snapshot['action_description'],
			'data' => $data,
			'record_count' => $snapshot['record_count'],
			'created_at' => $snapshot['created_at'],
			'user_name' => $snapshot['user_name']
		);
	}

	/**
	 * Lấy danh sách snapshots
	 * @param int $limit
	 * @param int $offset
	 * @return array
	 */
	public function getSnapshots($limit = 50, $offset = 0) {
		global $wpdb;
		$table_name = $this->_snapshot_table;
		$sql = "SELECT id, action_type, action_description, record_count, created_at, user_name 
				FROM $table_name 
				ORDER BY created_at DESC 
				LIMIT %d OFFSET %d";
		$sql = $wpdb->prepare($sql, $limit, $offset);
		return $wpdb->get_results($sql);
	}

	/**
	 * So sánh 2 snapshots và trả về chỉ những thay đổi
	 * @param int $old_snapshot_id
	 * @param int $new_snapshot_id
	 * @return array - Chỉ những records có thay đổi
	 */
	public function compareSnapshots($old_snapshot_id, $new_snapshot_id) {
		$old_snapshot = $this->getSnapshot($old_snapshot_id);
		$new_snapshot = $this->getSnapshot($new_snapshot_id);
		
		if (!$old_snapshot || !$new_snapshot) {
			return false;
		}
		
		$old_data = $old_snapshot['data'];
		$new_data = $new_snapshot['data'];
		
		// Tạo map theo ID để so sánh nhanh
		$old_map = array();
		foreach ($old_data as $record) {
			$old_map[$record['id']] = $record;
		}
		
		$new_map = array();
		foreach ($new_data as $record) {
			$new_map[$record['id']] = $record;
		}
		
		$changes = array();
		
		// So sánh từng record
		$all_ids = array_unique(array_merge(array_keys($old_map), array_keys($new_map)));
		
		foreach ($all_ids as $id) {
			$old_record = isset($old_map[$id]) ? $old_map[$id] : null;
			$new_record = isset($new_map[$id]) ? $new_map[$id] : null;
			
			// Record mới
			if (!$old_record && $new_record) {
				$changes[] = array(
					'id' => $id,
					'action' => 'created',
					'old_data' => null,
					'new_data' => $new_record
				);
			}
			// Record bị xóa
			elseif ($old_record && !$new_record) {
				$changes[] = array(
					'id' => $id,
					'action' => 'deleted',
					'old_data' => $old_record,
					'new_data' => null
				);
			}
			// Record bị thay đổi
			elseif ($old_record && $new_record) {
				$record_changes = $this->compareRecords($old_record, $new_record);
				if (!empty($record_changes)) {
					$changes[] = array(
						'id' => $id,
						'action' => 'updated',
						'old_data' => $old_record,
						'new_data' => $new_record,
						'changed_fields' => $record_changes
					);
				}
			}
		}
		
		return $changes;
	}

	/**
	 * So sánh 2 records và trả về chỉ những field thay đổi
	 * @param array $old_record
	 * @param array $new_record
	 * @return array - Chỉ những field có thay đổi
	 */
	private function compareRecords($old_record, $new_record) {
		$changes = array();
		
		$all_fields = array_unique(array_merge(array_keys($old_record), array_keys($new_record)));
		
		foreach ($all_fields as $field) {
			$old_value = isset($old_record[$field]) ? $old_record[$field] : null;
			$new_value = isset($new_record[$field]) ? $new_record[$field] : null;
			
			// Normalize values for comparison
			if (is_array($old_value)) {
				$old_value = json_encode($old_value, JSON_UNESCAPED_UNICODE);
			}
			if (is_array($new_value)) {
				$new_value = json_encode($new_value, JSON_UNESCAPED_UNICODE);
			}
			
			// So sánh
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
	 * Cleanup snapshots cũ (giữ tối đa N snapshots)
	 * @param int $keep_count - Số lượng snapshots giữ lại
	 */
	public function cleanup($keep_count = 30) {
		global $wpdb;
		$table_name = $this->_snapshot_table;
		
		// Lấy danh sách snapshots, sắp xếp theo created_at DESC
		$snapshots = $wpdb->get_col(
			"SELECT id FROM $table_name ORDER BY created_at DESC"
		);
		
		if (count($snapshots) > $keep_count) {
			$to_delete = array_slice($snapshots, $keep_count);
			$ids = implode(',', array_map('intval', $to_delete));
			$wpdb->query("DELETE FROM $table_name WHERE id IN ($ids)");
		}
	}

	/**
	 * Restore toàn bộ table từ snapshot
	 * @param int $snapshot_id - ID của snapshot cần restore
	 * @return bool - true nếu thành công, false nếu lỗi
	 */
	public function restoreSnapshot($snapshot_id) {
		global $wpdb;
		
		// Lấy snapshot
		$snapshot = $this->getSnapshot($snapshot_id);
		if (!$snapshot || !isset($snapshot['data'])) {
			return false;
		}
		
		// Backup table hiện tại trước khi restore (tạo snapshot)
		$this->createSnapshot('before_restore', 'Backup before restore from snapshot #' . $snapshot_id);
		
		// Begin transaction để đảm bảo atomic
		$wpdb->query('START TRANSACTION');
		
		try {
			// Truncate table (xóa toàn bộ data hiện tại)
			$wpdb->query("TRUNCATE TABLE {$this->_cat_menu_table}");
			
			// Insert lại data từ snapshot
			$data = $snapshot['data'];
			if (!empty($data) && is_array($data)) {
				foreach ($data as $record) {
					// Insert từng record
					$wpdb->insert($this->_cat_menu_table, $record);
				}
			}
			
			// Commit transaction
			$wpdb->query('COMMIT');
			
			return true;
		} catch (Exception $e) {
			// Rollback nếu có lỗi
			$wpdb->query('ROLLBACK');
			return false;
		}
	}

	// Static helper wrappers for backward compatibility
	public static function createTableStatic() { (new self())->createTable(); }
	public static function forceCreateTableStatic() { (new self())->forceCreateTable(); }
	public static function createSnapshotStatic($action_type = 'table_snapshot', $description = '') {
		return (new self())->createSnapshot($action_type, $description);
	}
	public static function getSnapshotStatic($snapshot_id) {
		return (new self())->getSnapshot($snapshot_id);
	}
	public static function getSnapshotsStatic($limit = 50, $offset = 0) {
		return (new self())->getSnapshots($limit, $offset);
	}
	public static function compareSnapshotsStatic($old_snapshot_id, $new_snapshot_id) {
		return (new self())->compareSnapshots($old_snapshot_id, $new_snapshot_id);
	}
	public static function cleanupStatic($keep_count = 30) { (new self())->cleanup($keep_count); }
}