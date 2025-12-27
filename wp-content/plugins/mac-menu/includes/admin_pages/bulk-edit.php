<?php
// Kiểm tra quyền truy cập
if (!defined('ABSPATH')) {
    exit;
}

$keyDomain = !empty(get_option('mac_domain_valid_key')) ? get_option('mac_domain_valid_key') : "0";
$statusDomain = !empty(get_option('mac_domain_valid_status')) ? get_option('mac_domain_valid_status') : "0";

// Kiểm tra key
$has_valid_key = !empty($keyDomain) && $keyDomain !== "0";

if (!$has_valid_key) {
    ?>
    <div class="wrap">
        <div class="notice notice-warning">
            <h3>⚠️ License Key Required</h3>
            <p>You need to add a valid license key to access the MAC Menu Bulk Edit.</p>
            <?php if (mac_menu_is_core_available()): ?>
                <p><a href="<?php echo admin_url('admin.php?page=mac-core'); ?>" class="button button-primary">Go to MAC Core Settings</a></p>
            <?php else: ?>
                <p><strong>Please install and activate MAC Core plugin to manage your license.</strong></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return;
}

// Kiểm tra management capabilities
$mac_core_available = mac_menu_is_core_available();
$has_valid_status = !empty($statusDomain) && ($statusDomain == 'activate' || $statusDomain == 'deactivate');
$can_manage = $mac_core_available && $has_valid_status;

if (!$can_manage) {
    ?>
    <div class="wrap">
        <div class="notice notice-error">
            <h3>⚠️ Access Denied</h3>
            <p>You don't have permission to access Bulk Edit. Please check your license status.</p>
        </div>
    </div>
    <?php
    return;
}

// Định nghĩa hàm export và lưu vào session (phải định nghĩa trước khi sử dụng)
if (!function_exists('mac_bulk_edit_export_and_save')) {
    function mac_bulk_edit_export_and_save() {
        global $wpdb;
        
        // Đảm bảo bảng tồn tại - tự động tạo nếu chưa có
        $table_name = $wpdb->prefix . "mac_cat_menu";
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if (!$table_exists) {
            // Tạo bảng nếu chưa có
            if (function_exists('create_table_cat')) {
                create_table_cat();
            } else {
                // Load file chứa hàm create_table_cat
                include_once MAC_PATH . 'includes/admin_pages/table-list-menu.php';
                if (function_exists('create_table_cat')) {
                    create_table_cat();
                } else {
                    return false; // Không thể tạo bảng
                }
            }
        }
        
        // Lấy dữ liệu từ bảng (có thể rỗng)
        $data = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
        
        // Nếu data rỗng, vẫn cho phép bulk edit với mảng rỗng
        if (empty($data)) {
            // Trả về mảng rỗng thay vì false
            $flattened_data = array();
            
            // Lưu vào transient
            $user_id = get_current_user_id();
            $transient_key = 'mac_bulk_edit_data_' . $user_id;
            set_transient($transient_key, $flattened_data, HOUR_IN_SECONDS);
            set_transient($transient_key . '_timestamp', time(), HOUR_IN_SECONDS);
            
            return true; // Cho phép vào editor với data trống
        }
        
        // Flatten data giống như export CSV
        $flattened_data = array();
        
        foreach ($data as $row) {
            // Tạo dòng category chính
            $rowData = array(
                'id' => $row['id'],
                'category_name' => $row['category_name'],
                'category_description' => $row['category_description'],
                'price' => '',
                'featured_img' => $row['featured_img'],
                'parents_category' => $row['parents_category'],
                'is_hidden' => $row['is_hidden'],
                'is_table' => $row['is_table'],
                'table_heading' => '',
                'item_list_name' => '',
                'item_list_price' => '',
                'item_list_description' => '',
                'item_list_fw' => '',
                'item_list_img' => '',
                'item_list_position' => '',
                'category_inside' => $row['category_inside'],
                'category_inside_order' => $row['category_inside_order']
            );
            
            // Xử lý price - giữ nguyên format array hoặc chuyển về pipe-separated
            if (!empty($row['price'])) {
                $price = $row['price'];
                if (is_string($price) && (strpos($price, '[') === 0)) {
                    $decoded = json_decode($price, true);
                    if (is_array($decoded)) {
                        // Giữ nguyên tất cả giá trị, kể cả rỗng, và implode với pipe
                        $rowData['price'] = implode('|', array_map(function($item) {
                            return $item === null || $item === '' ? '' : $item;
                        }, $decoded));
                    } else {
                        $rowData['price'] = $price;
                    }
                } else {
                    $rowData['price'] = $price;
                }
            }
            
            // Xử lý data_table - giữ nguyên format array hoặc chuyển về pipe-separated
            if (!empty($row['data_table'])) {
                $data_table = $row['data_table'];
                if (is_string($data_table) && (strpos($data_table, '[') === 0)) {
                    $decoded = json_decode($data_table, true);
                    if (is_array($decoded)) {
                        // Giữ nguyên tất cả giá trị, kể cả rỗng, và implode với pipe
                        $rowData['table_heading'] = implode('|', array_map(function($item) {
                            return $item === null || $item === '' ? '' : $item;
                        }, $decoded));
                    } else {
                        $rowData['table_heading'] = $data_table;
                    }
                } else {
                    $rowData['table_heading'] = $data_table;
                }
            }
            
            // Xử lý group_repeater
            if (!empty($row['group_repeater'])) {
                $arrayGroupRepeater = json_decode($row['group_repeater'], true);
                if (!empty($arrayGroupRepeater) && is_array($arrayGroupRepeater)) {
                    $index = 0;
                    $position = 1; // Bắt đầu từ 1 cho mỗi category
                    foreach ($arrayGroupRepeater as $item) {
                        if (!empty($item['name'])) {
                            $rowData1 = $rowData;
                            
                            // Cập nhật dữ liệu từ group_repeater
                            $rowData1['item_list_name'] = $item['name'];
                            
                            if (isset($item['price-list']) && is_array($item['price-list'])) {
                                $prices = array_column($item['price-list'], 'price');
                                $rowData1['item_list_price'] = implode('|', $prices);
                            } else {
                                $rowData1['item_list_price'] = '';
                            }
                            
                            $rowData1['item_list_description'] = isset($item['description']) ? $item['description'] : '';
                            $rowData1['item_list_fw'] = isset($item['fullwidth']) ? $item['fullwidth'] : '';
                            $rowData1['item_list_img'] = isset($item['featured_img']) ? $item['featured_img'] : '';
                            // Tự động điền số thứ tự bắt đầu từ 1 cho mỗi category
                            $rowData1['item_list_position'] = (string)$position;
                            
                            // Nếu không phải dòng đầu tiên, để trống các cột category
                            if ($index > 0) {
                                $rowData1['id'] = '';
                                $rowData1['category_name'] = '';
                                $rowData1['category_description'] = '';
                                $rowData1['price'] = '';
                                $rowData1['featured_img'] = '';
                                $rowData1['parents_category'] = '';
                                $rowData1['is_hidden'] = '';
                                $rowData1['is_table'] = '';
                                $rowData1['table_heading'] = '';
                                $rowData1['category_inside'] = '';
                                $rowData1['category_inside_order'] = '';
                            }
                            
                            $flattened_data[] = $rowData1;
                            $index++;
                            $position++; // Tăng position cho item tiếp theo
                        }
                    }
                } else {
                    // Nếu không có group_repeater, thêm dòng category
                    $flattened_data[] = $rowData;
                }
            } else {
                // Nếu không có group_repeater, thêm dòng category
                $flattened_data[] = $rowData;
            }
        }
        
        // Lưu vào WordPress transient (thay vì session để đảm bảo persist)
        $user_id = get_current_user_id();
        $transient_key = 'mac_bulk_edit_data_' . $user_id;
        
        // Lưu data với thời gian expire 1 giờ
        set_transient($transient_key, $flattened_data, HOUR_IN_SECONDS);
        set_transient($transient_key . '_timestamp', time(), HOUR_IN_SECONDS);
        
        return true;
    }
}

// Xử lý action
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';

// Nếu là action edit, export CSV và lưu vào transient
if ($action === 'edit') {
    // Export CSV và lưu data vào transient
    $result = mac_bulk_edit_export_and_save();
    
    if (!$result) {
        ?>
        <div class="wrap">
            <div class="notice notice-error">
                <p>Failed to load data. Please try again.</p>
                <p><a href="<?php echo admin_url('admin.php?page=mac-menu-bulk-edit'); ?>" class="button">Go Back</a></p>
            </div>
        </div>
        <?php
        return;
    }
    
    // Redirect đến trang spreadsheet editor
    ?>
    <div class="wrap">
        <div class="notice notice-info">
            <p><strong>Preparing data...</strong></p>
            <p>Please wait while we prepare your data.</p>
        </div>
    </div>
    <script>
    // Redirect đến trang spreadsheet editor
    setTimeout(function() {
        window.location.href = '<?php echo admin_url('admin.php?page=mac-menu-bulk-edit&action=spreadsheet'); ?>';
    }, 500);
    </script>
    <?php
    return;
}

// Nếu là trang spreadsheet editor
if ($action === 'spreadsheet') {
    include_once MAC_PATH . 'includes/admin_pages/bulk-edit-spreadsheet.php';
    return;
}
// Enqueue custom CSS
wp_enqueue_style('mac-bulk-edit-spreadsheet-css', MAC_URI . 'admin/css/bulk-edit-spreadsheet.css', array(), '1.0.0');
// Trang chính - hiển thị nút Edit
?>
<div class="wrap mac-bulk-edit-spreadsheet">
    <h1 class="wp-heading-inline">Bulk Edit Menu</h1>
    <hr class="wp-header-end">
    
    <div class="postbox" style="margin-top: 20px;">
        <div class="postbox-header">
            <h2>Bulk Edit Menu Data</h2>
        </div>
        <div class="inside">
            <p>Use this tool to bulk edit your menu data in a spreadsheet format.</p>
            <p><strong>Note:</strong> All changes will replace existing data. Make sure to backup your data before editing.</p>
            
            <form method="get" action="<?php echo admin_url('admin.php'); ?>">
                <input type="hidden" name="page" value="mac-menu-bulk-edit">
                <input type="hidden" name="action" value="edit">
                <p>
                    <button type="submit" class="button button-primary button-large">
                        <span class="dashicons dashicons-edit" style="vertical-align: middle; margin-right: 5px;"></span>
                        Start Bulk Edit
                    </button>
                </p>
            </form>
            
            <div class="notice notice-info" style="margin-top: 20px;">
                <p><strong>How it works:</strong></p>
                <ol>
                    <li>Click "Start Bulk Edit" to open the spreadsheet editor</li>
                    <li>Edit your data in the spreadsheet</li>
                    <li>Click "Save" to replace all existing data with your changes</li>
                    <li>All changes are logged in Activity Log for tracking</li>
                </ol>
            </div>
        </div>
    </div>
</div>

