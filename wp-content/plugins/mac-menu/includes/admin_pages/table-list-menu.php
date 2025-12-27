<?php
global $wpdb;
function create_table_cat(){
    global $wpdb;
    $objmacMenu = new macMenu();
    $cattablename = $objmacMenu->get_cat_menu_name();
    $charset_collate = $wpdb->get_charset_collate();
    $stringSQL = "
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `category_name` text COLLATE utf8_unicode_ci DEFAULT NULL,
        `slug_category` text COLLATE utf8_unicode_ci DEFAULT NULL,
        `category_description` text COLLATE utf8_unicode_ci DEFAULT NULL,
        `price` text COLLATE utf8_unicode_ci DEFAULT NULL,
        `featured_img` text COLLATE utf8_unicode_ci DEFAULT NULL,
        `parents_category` text COLLATE utf8_unicode_ci DEFAULT NULL,
        `order` int(11) DEFAULT NULL,
        `group_repeater` text COLLATE utf8_unicode_ci DEFAULT NULL,
        `is_table` int(11) DEFAULT NULL,
        `is_hidden` int(11) DEFAULT NULL,
        `data_table` text COLLATE utf8_unicode_ci DEFAULT NULL,
        `category_inside` int(11) DEFAULT 1,
        `category_inside_order` text COLLATE utf8_unicode_ci DEFAULT 'new',
        PRIMARY KEY (id)
    ";
    // Kiểm tra bảng đã tồn tại hay chưa
    $table_exists_query = $wpdb->prepare("SHOW TABLES LIKE %s", $cattablename);
    $table_exists = $wpdb->get_var($table_exists_query);

    // Nếu bảng tồn tại
    if ($table_exists === $cattablename) {
        return;
    }
    update_option('my_plugin_schema_version', 2);
    $sql = "CREATE TABLE `$cattablename` (
        ".$stringSQL."
      ) ".$charset_collate.";";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    register_activation_hook( __FILE__, 'plugin_cat_table' );
    create_or_update_cat_table();
}
function create_or_update_cat_table(){
    global $wpdb;
    $objmacMenu = new macMenu();
    $cattablename = $objmacMenu->get_cat_menu_name();

    // Tên option để lưu trạng thái
    $schema_version_key = 'my_plugin_schema_version';
    $schema_version = 3; // Tăng version để trigger update

    // Kiểm tra trạng thái schema
    $current_schema_version = get_option($schema_version_key, 0);

    //if ($current_schema_version < $schema_version) {
        // Thêm cột category_inside nếu chưa có
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `$cattablename` LIKE %s", 'category_inside'
        ));
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `$cattablename` ADD `category_inside` int(11) DEFAULT 1");
        } else {
            // Nếu cột đã tồn tại, đảm bảo nó có giá trị mặc định là 1
            $wpdb->query("ALTER TABLE `$cattablename` MODIFY `category_inside` int(11) DEFAULT 1");
        }

        // Thêm cột category_inside_order nếu chưa có
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `$cattablename` LIKE %s", 'category_inside_order'
        ));
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `$cattablename` ADD `category_inside_order` TEXT DEFAULT 'new'");
        } else {
            // Nếu cột đã tồn tại, thay đổi kiểu dữ liệu và giá trị mặc định
            $wpdb->query("ALTER TABLE `$cattablename` MODIFY `category_inside_order` TEXT DEFAULT 'new'");
        }

        // Cập nhật trạng thái schema
    //     update_option($schema_version_key, $schema_version);
    // }
}

function delete_table_cat($deleteData){
    global $wpdb;
    
    // DISABLED: Không tạo snapshot nữa, dùng activity_log thay thế
    $objmacMenu = new macMenu();
    $cattablename = $objmacMenu->get_cat_menu_name();
    
    if ($deleteData === $cattablename) {
        // DISABLED: Không tạo snapshot nữa
        // if (class_exists('MacMenuTableSnapshot')) {
        //     if (function_exists('create_table_snapshot')) {
        //         create_table_snapshot('before_delete', 'Auto snapshot - before delete table');
        //     }
        // }
        
        // Đếm số records trước khi xóa
        $record_count = $wpdb->get_var("SELECT COUNT(*) FROM $deleteData");
    } else {
        $record_count = 0;
    }
    
    // Xóa table
    $sql = "DROP TABLE IF EXISTS $deleteData";
    $result = $wpdb->query( $sql );
    
    // Log the table deletion
    if ($result !== false && function_exists('log_activity')) {
        $description = "Deleted table '$deleteData'";
        if ($record_count > 0) {
            $description .= " with $record_count records";
        }
        log_activity('table_delete', $description, $deleteData, $record_count);
    }
    
    return $result;
}
function plugin_cat_table($cat_csv_to_array,$parentID = 0,$parentName = ''){
    global $wpdb;
    $cattablename = $wpdb->prefix."mac_cat_menu";
    create_table_cat();
    $orderIndex = 0;
    foreach ($cat_csv_to_array as $item):
        $category_inside_order = isset($item['category_inside_order']) && $item['category_inside_order'] !== '0' && $item['category_inside_order'] !== 0 ? $item['category_inside_order'] : 'new';
        $wpdb->insert( $cattablename,
            array(
                'category_name' => $item['category_name'],
                'slug_category' => create_slug(
                    $parentID == 0
                        ? $item['category_name']
                        : $parentName . ' ' . $item['category_name']
                ),
                'category_description' => $item['category_description'],
                'price' => $item['price'],
                'featured_img' => $item['featured_img'],
                'order' => $orderIndex,
                'is_table' => $item['is_table'],
                'is_hidden' => $item['is_hidden'],
                'parents_category' => $item['parents_category'],
                'data_table' => json_encode(create_array($item['data_table'])),
                'group_repeater' => json_encode($item['group_repeater']),
                'category_inside' => $item['category_inside'],
                'category_inside_order' => $category_inside_order,
            )
        );
        $orderIndex++;
    endforeach;
    // Kiểm tra sự tồn tại của bảng
    $table_exists_query = $wpdb->prepare("SHOW TABLES LIKE %s", $cattablename);
    $table_exists = $wpdb->get_var($table_exists_query);
    if ($table_exists === $cattablename) {
        mac_redirect('admin.php?page=mac-cat-menu');
        exit();
    } else {
        echo "Bảng '$cattablename' không tồn tại.";
    }
}
