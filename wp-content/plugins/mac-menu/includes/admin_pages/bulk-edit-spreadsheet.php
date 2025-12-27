<?php
// Kiểm tra quyền truy cập
if (!defined('ABSPATH')) {
    exit;
}

// Lấy data từ WordPress transient (thay vì session)
$user_id = get_current_user_id();
$transient_key = 'mac_bulk_edit_data_' . $user_id;
$bulk_edit_data = get_transient($transient_key);

if ($bulk_edit_data === false) {
    // Nếu không có transient, có thể là lần đầu hoặc đã hết hạn
    // Cho phép vào editor với data trống
    $bulk_edit_data = array();
}

// Cho phép hiển thị editor ngay cả khi data trống (không cần check empty)

// Định nghĩa columns
$columns = array(
    'id', 'category_name', 'category_description', 'price', 'featured_img',
    'parents_category', 'is_hidden', 'is_table', 'table_heading',
    'item_list_name', 'item_list_price', 'item_list_description',
    'item_list_fw', 'item_list_img', 'item_list_position',
    'category_inside', 'category_inside_order'
);

$column_headers = array(
    'ID', 'Category Name', 'Category Description', 'Price', 'Featured Image',
    'Parents Category', 'Is Hidden', 'Is Table', 'Table Heading',
    'Item Name', 'Item Price', 'Item Description',
    'Item Full Width', 'Item Image', 'Item Position',
    'Category Inside', 'Category Inside Order'
);

// Chuyển đổi data thành format cho Handsontable
$hot_data = array();
if (!empty($bulk_edit_data)) {
    foreach ($bulk_edit_data as $row) {
        $hot_row = array();
        foreach ($columns as $col) {
            $hot_row[] = isset($row[$col]) ? $row[$col] : '';
        }
        $hot_data[] = $hot_row;
    }
}
// Nếu data trống, $hot_data sẽ là mảng rỗng - Handsontable sẽ hiển thị bảng trống

// Encode data để truyền vào JavaScript
$hot_data_json = json_encode($hot_data);
$column_headers_json = json_encode($column_headers);
$nonce = wp_create_nonce('mac_bulk_edit_save');

// Enqueue Handsontable CSS
$handsontable_css_url = MAC_URI . 'admin/css/handsontable.full.min.css';
wp_enqueue_style('handsontable-css', $handsontable_css_url, array(), '1.0.0');

// Enqueue custom CSS
wp_enqueue_style('mac-bulk-edit-spreadsheet-css', MAC_URI . 'admin/css/bulk-edit-spreadsheet.css', array(), '1.0.0');

// Enqueue Handsontable JS
$handsontable_js_url = MAC_URI . 'admin/js/handsontable.full.min.js';
wp_enqueue_script('handsontable-js', $handsontable_js_url, array(), '1.0.0', false);

// Enqueue custom JS với dependencies
wp_enqueue_script('mac-bulk-edit-spreadsheet-js', MAC_URI . 'admin/js/bulk-edit-spreadsheet.js', array('jquery', 'handsontable-js'), '1.0.0', true);

// Localize script để truyền các biến PHP vào JavaScript
wp_localize_script('mac-bulk-edit-spreadsheet-js', 'macBulkEdit', array(
    'hotData' => $hot_data,
    'columnHeaders' => $column_headers,
    'columns' => $columns,
    'columnsCount' => count($columns),
    'nonce' => $nonce,
    'redirectUrl' => admin_url('admin.php?page=mac-cat-menu')
));
?>

<div class="wrap mac-bulk-edit-spreadsheet">
    <h1 class="wp-heading-inline">Bulk Edit Menu - Spreadsheet Editor</h1>
    <hr class="wp-header-end">
    
    <div class="postbox" style="margin-top: 20px;">
        <div class="postbox-header">
            <h2>Edit Menu Data</h2>
        </div>
        <div class="inside">
            <?php if (empty($bulk_edit_data)): ?>
                <div class="notice notice-info" style="margin-bottom: 20px;">
                    <p><strong>Empty Table:</strong> No data found. You can add new data by clicking on the cells below and entering your information.</p>
                </div>
            <?php endif; ?>
            
            <div style="margin-bottom: 20px;">
                <button id="mac-bulk-edit-save" class="button button-primary button-large">
                    <span class="dashicons dashicons-yes" style="vertical-align: middle; margin-right: 5px;"></span>
                    Save Changes
                </button>
                <button id="mac-bulk-edit-add-row" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                    Add Row
                </button>
                <a href="<?php echo admin_url('admin.php?page=mac-menu-bulk-edit'); ?>" class="button button-secondary">
                    Cancel
                </a>
                <span id="mac-bulk-edit-status" style="margin-left: 20px;"></span>
            </div>
            
            <div id="mac-bulk-edit-spreadsheet" style="overflow: auto; max-width: 100%;"></div>
        </div>
    </div>
</div>
