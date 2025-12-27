<?php
/*
Plugin Name:  MAC Menu
Description:  Menu Services
Version:      1.6.6.0
Author:       MAC USA One
Author URI:   https://macusaone.com
License:      GPL v1
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  mac-plugin
Domain Path:  /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Check if mac-core is active - Move to plugins_loaded hook
add_action('plugins_loaded', function() {
    if (!class_exists('MAC_Core\CRM_API_Manager')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p><strong>MAC Menu</strong> requires <strong>MAC Core</strong> plugin to be installed and activated.</p></div>';
        });
        return;
    }
}, 20); // Priority 20 to run after mac-core loads

if (!defined('MAC_PATH')) {
    define('MAC_PATH', plugin_dir_path( __FILE__ ) );
}
if (!defined('MAC_URI')) {
    define('MAC_URI', plugin_dir_url( __FILE__ ) );
}


/* xây lớp bảo mật */
/**  Bảo vệ tệp wp-config.php */
function protect_wp_config() {
    $htaccess_file = ABSPATH . '.htaccess';
    if (file_exists($htaccess_file) && is_writable($htaccess_file)) {
        $rules = "\n<files wp-config.php>\norder allow,deny\ndenY from all\n</files>\n";
        file_put_contents($htaccess_file, $rules, FILE_APPEND);
    }
}
register_activation_hook(__FILE__, 'protect_wp_config');

/**  Chặn XML-RPC */
add_filter('xmlrpc_enabled', '__return_false');

add_action('admin_menu','mac_admin_menu');
function mac_admin_menu(){
    // Thêm menu cha
        add_menu_page(
            'MAC Menu',
            'MAC Menu',
            'edit_published_pages',
            'mac-cat-menu',//menu_slug
            'mac_menu_admin_page_cat_menu',
            'dashicons-admin-page',
            26
        );
        add_submenu_page(
            'mac-cat-menu',
            'New Menu',
            'New Menu',
            'edit_published_pages',
            'mac-new-cat-menu',
            'mac_menu_admin_page_news_cat_menu',
            26
        );
        add_submenu_page(
            'mac-cat-menu',
            'Settings/Import',
            'Settings/Import',
            'edit_published_pages',
            'mac-menu',
            'mac_menu_admin_page_dashboard',
            26
        );
        add_submenu_page(
            'mac-cat-menu',
            'Activity Log',
            'Activity Log',
            'edit_published_pages',
            'mac-menu-activity-log',
            'mac_menu_admin_page_activity_log',
            27
        );
        add_submenu_page(
            'mac-cat-menu',
            'Bulk Edit',
            'Bulk Edit',
            'edit_published_pages',
            'mac-menu-bulk-edit',
            'mac_menu_admin_page_bulk_edit',
            28
        );
}

function change_media_label(){
    global $submenu;
    if(isset($submenu['mac-cat-menu'])):
        $submenu['mac-cat-menu'][0][0] = 'All Menu';
    endif;
}
add_action( 'admin_menu', 'change_media_label' );

// Auto-check domain URL when no key exists or key is invalid
// DISABLED: Now using manual check via button
// add_action('admin_init', 'mac_menu_auto_check_domain_url');

function mac_menu_auto_check_domain_url() {
    error_log('=== MAC Menu: mac_menu_auto_check_domain_url() CALLED ===');
    error_log('MAC Menu: mac_menu_auto_check_domain_url - Timestamp: ' . date('Y-m-d H:i:s'));
    
    // Only check if we're in admin and not doing AJAX
    if (!is_admin() || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
        error_log('MAC Menu: mac_menu_auto_check_domain_url - Not in admin or doing AJAX, skipping');
        error_log('MAC Menu: mac_menu_auto_check_domain_url() ENDING - Skipped');
        return;
    }
    
    // Check if we have a valid key
    $current_key = get_option('mac_domain_valid_key', '');
    $current_status = get_option('mac_domain_valid_status', '');
    
    error_log('MAC Menu: mac_menu_auto_check_domain_url - Current key: ' . ($current_key ?: 'empty'));
    error_log('MAC Menu: mac_menu_auto_check_domain_url - Current status: ' . ($current_status ?: 'empty'));
    
    // If no key or invalid status, check with CRM
    if (empty($current_key) || $current_status !== 'activate') {
        error_log('MAC Menu: mac_menu_auto_check_domain_url - No valid key or invalid status detected');
        
        // Check last sync time to avoid too frequent requests
        $last_sync = get_option('mac_domain_last_sync', 0);
        $current_time = time();
        
        // Ensure $last_sync is an integer
        $last_sync = intval($last_sync);
        
        $time_diff = $current_time - $last_sync;
        
        error_log('MAC Menu: mac_menu_auto_check_domain_url - Last sync: ' . date('Y-m-d H:i:s', $last_sync));
        error_log('MAC Menu: mac_menu_auto_check_domain_url - Current time: ' . date('Y-m-d H:i:s', $current_time));
        error_log('MAC Menu: mac_menu_auto_check_domain_url - Time difference: ' . $time_diff . ' seconds');
        
        // Only check if it's been more than 1 hour since last check
        if (($current_time - $last_sync) > 3600) {
                    // Function kvp_handle_check_request_url() đã được chuyển sang MAC Core
        // error_log('MAC Menu: mac_menu_auto_check_domain_url - More than 1 hour passed, calling kvp_handle_check_request_url()');
        // kvp_handle_check_request_url();
            error_log('MAC Menu: mac_menu_auto_check_domain_url() ENDING - URL check called');
        } else {
            error_log('MAC Menu: mac_menu_auto_check_domain_url - Less than 1 hour passed, skipping check');
            error_log('MAC Menu: mac_menu_auto_check_domain_url() ENDING - Skipped (time limit)');
        }
    } else {
        error_log('MAC Menu: mac_menu_auto_check_domain_url - Valid key and status found, no need to check');
        error_log('MAC Menu: mac_menu_auto_check_domain_url() ENDING - No action needed');
    }
}

function mac_menu_admin_page_dashboard(){
    include_once MAC_PATH.'includes/admin_pages/dashboard.php';
}

function mac_menu_admin_page_cat_menu(){
    include_once MAC_PATH.'includes/admin_pages/cat.php';
}

function mac_menu_admin_page_news_cat_menu(){
    mac_redirect('admin.php?page=mac-cat-menu&id=new');
}

function mac_menu_admin_page_activity_log(){
    include_once MAC_PATH.'includes/admin_pages/activity-log.php';
}

function mac_menu_admin_page_bulk_edit(){
    include_once MAC_PATH.'includes/admin_pages/bulk-edit.php';
}

if( !function_exists('mac_redirect') ){
    function mac_redirect($url){
        // Use wp_redirect instead of echo to avoid unexpected output
        if (!headers_sent()) {
            wp_redirect(admin_url($url));
            exit;
        } else {
            // Fallback for when headers are already sent
            echo("<script>location.href = '".admin_url($url)."'</script>");
        }
    }
}

// Làm việc với CSDL trong wordpress
include_once MAC_PATH.'includes/classes/macMenu.php';

$htmlNew = get_option('mac_html_old',1);
if(!empty($htmlNew)) {
    include_once MAC_PATH.'/blocks/new_html/render/render-module.php';
    include_once MAC_PATH.'/blocks/new_html/module/cat_menu_basic.php';
    include_once MAC_PATH.'/blocks/new_html/module/cat_menu_table.php';
}else{
    include_once MAC_PATH.'/blocks/render/render-module.php';
    include_once MAC_PATH.'/blocks/module/cat_menu_basic.php';
    include_once MAC_PATH.'/blocks/module/cat_menu_table.php';
}

include_once MAC_PATH.'/includes/admin_pages/table-list-menu.php';

// Activity Log System - 30 days retention
function create_activity_log_table() {
    if (!class_exists('MacMenuActivityLog')) { return; }
    (new MacMenuActivityLog())->createTable();
}

// Force create table function for immediate use
function force_create_activity_log_table() {
    if (!class_exists('MacMenuActivityLog')) { return; }
    (new MacMenuActivityLog())->forceCreateTable();
}

function migrate_activity_log_drop_unused_columns() {
    if (!class_exists('MacMenuActivityLog')) { return; }
    (new MacMenuActivityLog())->migrateDropUnusedColumns();
}

function log_activity($action_type, $description, $table = null, $records = 0, $error = null, $old_data = null, $new_data = null, $is_full_table = false) {
    // Debug: Log to file for troubleshooting - DISABLED: Data already saved to database
    // if ($action_type === 'category_update') {
    //     $debug_log = WP_CONTENT_DIR . '/plugins/mac-menu/debug-activity.log';
    //     $timestamp = date('Y-m-d H:i:s');
    //     $debug_message = "[{$timestamp}] {$action_type}: {$description}\n";
    //     file_put_contents($debug_log, $debug_message, FILE_APPEND | LOCK_EX);
    // }
    if (!class_exists('MacMenuActivityLog')) { return; }
    (new MacMenuActivityLog())->log($action_type, $description, $table, $records, $error, $old_data, $new_data, $is_full_table);
}

function cleanup_old_logs() {
    if (!class_exists('MacMenuActivityLog')) { return; }
    (new MacMenuActivityLog())->cleanup();
}

function get_activity_logs($limit = 50, $offset = 0, $action_type = null, $date_filter = null, $user_filter = null) {
    if (!class_exists('MacMenuActivityLog')) { return []; }
    return (new MacMenuActivityLog())->getLogs($limit, $offset, $action_type, $date_filter, $user_filter);
}

function get_activity_log_count($action_type = null, $date_filter = null, $user_filter = null) {
    if (!class_exists('MacMenuActivityLog')) { return 0; }
    return (new MacMenuActivityLog())->getLogCount($action_type, $date_filter, $user_filter);
}

// DISABLED: Table Snapshot helper functions - Không dùng snapshot table nữa, dùng activity_log thay thế
function create_table_snapshot($action_type = 'table_snapshot', $description = '') {
    // DISABLED: Không tạo snapshot nữa, dùng activity_log thay thế
    return false;
    
    /* OLD CODE - DISABLED
    if (!class_exists('MacMenuTableSnapshot')) { return false; }
    return (new MacMenuTableSnapshot())->createSnapshot($action_type, $description);
    */
}

function get_table_snapshot($snapshot_id) {
    if (!class_exists('MacMenuTableSnapshot')) { return false; }
    return (new MacMenuTableSnapshot())->getSnapshot($snapshot_id);
}

function compare_table_snapshots($old_snapshot_id, $new_snapshot_id) {
    if (!class_exists('MacMenuTableSnapshot')) { return false; }
    return (new MacMenuTableSnapshot())->compareSnapshots($old_snapshot_id, $new_snapshot_id);
}

// AJAX handler để compare data từ activity_log - Ưu tiên dùng old_data và new_data từ activity_log
add_action('wp_ajax_mac_compare_snapshots', 'mac_compare_snapshots_ajax');
function mac_compare_snapshots_ajax() {
    // Đảm bảo class được load
    if (!class_exists('MacMenuActivityLog')) {
        // Load class nếu chưa có
        if (!defined('MAC_PATH')) {
            define('MAC_PATH', plugin_dir_path(__FILE__));
        }
        $mac_menu_file = MAC_PATH . 'includes/classes/macMenu.php';
        if (file_exists($mac_menu_file)) {
            require_once $mac_menu_file;
        } else {
            wp_send_json_error('macMenu.php file not found at: ' . $mac_menu_file);
        }
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_activity_log_nonce')) {
        wp_send_json_error('Security check failed - Invalid nonce');
    }
    
    if (!current_user_can('edit_dashboard')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
    
    if (!$log_id) {
        wp_send_json_error('Invalid log ID');
    }
    
    // Lấy log entry
    global $wpdb;
    $log_table = $wpdb->prefix . 'mac_menu_activity_log';
    $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM $log_table WHERE id = %d", $log_id));
    
    if (!$log) {
        wp_send_json_error('Log entry not found');
    }
    
    $activity_log_manager = new MacMenuActivityLog();
    $old_data = null;
    $new_data = null;
    
    // Ưu tiên: Dùng old_data và new_data từ activity_log nếu có is_full_table = 1
    if (!empty($log->is_full_table) && !empty($log->old_data)) {
        // Decompress old_data và new_data từ activity_log
        $old_data = $activity_log_manager->decompressData($log->old_data);
        if (!empty($log->new_data)) {
            $new_data = $activity_log_manager->decompressData($log->new_data);
        }
        
        if ($old_data === false || ($new_data === false && !empty($log->new_data))) {
            wp_send_json_error('Failed to decompress data from log entry');
        }
    } else {
        // Fallback: Dùng snapshot table (backward compatibility với logs cũ)
        if (!class_exists('MacMenuTableSnapshot')) {
            wp_send_json_error('No full table data found in log entry and snapshot system not available.');
        }
        
        $snapshot_manager = new MacMenuTableSnapshot();
        $snapshot_table = $wpdb->prefix . 'mac_menu_table_snapshots';
        
        // Tìm snapshot trước action (created_at <= log->created_at)
        $old_snapshot = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $snapshot_table WHERE created_at <= %s ORDER BY created_at DESC LIMIT 1",
            $log->created_at
        ));
        
        // Tìm snapshot sau action (created_at > log->created_at)
        $new_snapshot = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $snapshot_table WHERE created_at > %s ORDER BY created_at ASC LIMIT 1",
            $log->created_at
        ));
        
        if (!$old_snapshot || !$new_snapshot) {
            wp_send_json_error('Snapshots not found for comparison. Please create snapshots first.');
        }
        
        $old_snapshot_data = $snapshot_manager->getSnapshot($old_snapshot->id);
        $new_snapshot_data = $snapshot_manager->getSnapshot($new_snapshot->id);
        
        if (!$old_snapshot_data || !$new_snapshot_data) {
            wp_send_json_error('Failed to get snapshot data');
        }
        
        $old_data = $old_snapshot_data['data'];
        $new_data = $new_snapshot_data['data'];
    }
    
    // So sánh old_data và new_data
    try {
        $changes = mac_compare_table_data($old_data, $new_data);
        
        if ($changes === false) {
            wp_send_json_error('Failed to compare data - Invalid data');
        }
    } catch (Exception $e) {
        wp_send_json_error('Error comparing data: ' . $e->getMessage());
    }
    
    // Format changes để hiển thị
    $formatted_changes = array();
    foreach ($changes as $change) {
        $formatted_changes[] = array(
            'id' => $change['id'],
            'action' => $change['action'],
            'category_name' => isset($change['old_data']['category_name']) ? $change['old_data']['category_name'] : 
                              (isset($change['new_data']['category_name']) ? $change['new_data']['category_name'] : ''),
            'changed_fields' => isset($change['changed_fields']) ? $change['changed_fields'] : array(),
            'old_data' => $change['old_data'],
            'new_data' => $change['new_data']
        );
    }
    
    wp_send_json_success(array(
        'changes' => $formatted_changes,
        'total_changes' => count($formatted_changes),
        'log_id' => $log_id
    ));
}

/**
 * So sánh 2 mảng table data và trả về chỉ những thay đổi
 * @param array $old_data - Data cũ (toàn bộ table)
 * @param array $new_data - Data mới (toàn bộ table)
 * @return array - Chỉ những records có thay đổi
 */
function mac_compare_table_data($old_data, $new_data) {
    if (!is_array($old_data)) {
        $old_data = array();
    }
    if (!is_array($new_data)) {
        $new_data = array();
    }
    
    // Tạo map theo ID để dễ so sánh
    $old_map = array();
    foreach ($old_data as $record) {
        if (isset($record['id'])) {
            $old_map[$record['id']] = $record;
        }
    }
    
    $new_map = array();
    foreach ($new_data as $record) {
        if (isset($record['id'])) {
            $new_map[$record['id']] = $record;
        }
    }
    
    $changes = array();
    
    // Tìm records bị xóa (có trong old nhưng không có trong new)
    foreach ($old_map as $id => $old_record) {
        if (!isset($new_map[$id])) {
            $changes[] = array(
                'id' => $id,
                'action' => 'deleted',
                'old_data' => $old_record,
                'new_data' => null,
                'changed_fields' => array()
            );
        }
    }
    
    // Tìm records được thêm mới (có trong new nhưng không có trong old)
    foreach ($new_map as $id => $new_record) {
        if (!isset($old_map[$id])) {
            $changes[] = array(
                'id' => $id,
                'action' => 'created',
                'old_data' => null,
                'new_data' => $new_record,
                'changed_fields' => array()
            );
        }
    }
    
    // Tìm records bị thay đổi (có trong cả old và new nhưng có field khác nhau)
    foreach ($new_map as $id => $new_record) {
        if (isset($old_map[$id])) {
            $old_record = $old_map[$id];
            $changed_fields = array();
            
            // So sánh từng field
            foreach ($new_record as $field => $new_value) {
                $old_value = isset($old_record[$field]) ? $old_record[$field] : null;
                
                // Normalize values để so sánh
                if (is_array($new_value)) {
                    $new_value = json_encode($new_value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                if (is_array($old_value)) {
                    $old_value = json_encode($old_value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                
                $new_value = (string)$new_value;
                $old_value = (string)$old_value;
                
                if ($new_value !== $old_value) {
                    $changed_fields[$field] = array(
                        'old' => $old_value,
                        'new' => $new_value
                    );
                }
            }
            
            // Nếu có thay đổi, thêm vào changes
            if (!empty($changed_fields)) {
                $changes[] = array(
                    'id' => $id,
                    'action' => 'updated',
                    'old_data' => $old_record,
                    'new_data' => $new_record,
                    'changed_fields' => $changed_fields
                );
            }
        }
    }
    
    return $changes;
}

// create or update data
if (false === get_option('my_plugin_schema_version')) {
    add_option('my_plugin_schema_version', '2');
}
create_or_update_cat_table();

function add_media_button_shortcode() {
    ob_start();
    ?>
    <div class="add-media-button-container">
        <input type="text" id="custom_media_url" name="custom_media_url" size="25" readonly />
        <button type="button" id="add_media_button">Add Media</button>
        <img id="media_preview" class="featured_img" src="" style="max-width: 200px; display: none;" />
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('add_media_button', 'add_media_button_shortcode');

// Kết hợp với enqueue scripts và styles
function mac_enqueue_scripts() {
    wp_enqueue_style( 'mac-style', MAC_URI . 'public/css/mac-menu-style.css' );
    wp_enqueue_script( 'mac-script', MAC_URI . 'public/js/mac-menu-script.js', array( 'jquery' ), '', true );
}
add_action( 'wp_enqueue_scripts', 'mac_enqueue_scripts' );
function mac_admin_enqueue_scripts() {
    global $pagenow;
    wp_enqueue_script( 'jquery' );
    if ( isset( $_GET['page']) && 
            (   $_GET['page'] == 'mac-new-cat-menu' ||
                $_GET['page'] == 'mac-cat-menu' ||
                $_GET['page'] == 'mac-menu'
            )
            
        )  {
            wp_enqueue_media();
            /* jquery ui */
            wp_enqueue_style( 'jquery-ui', MAC_URI . 'admin/css/jquery-ui.css' );
            wp_enqueue_style( 'admin-style', MAC_URI . 'admin/css/admin-style.css' );
            wp_enqueue_script( 'jquery-ui', MAC_URI . 'admin/js/jquery-ui.js', array( 'jquery' ), '', true );
            wp_enqueue_script( 'jquery-repeater', MAC_URI . 'admin/js/jquery.repeater.js', array( 'jquery' ), '', true );
            wp_enqueue_script( 'admin-script', MAC_URI . 'admin/js/admin-script.js', array( 'jquery-repeater' ), '', true );
    }
}
add_action( 'admin_enqueue_scripts', 'mac_admin_enqueue_scripts' );

function create_slug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[áàảãạăắằẳẵặâấầẩẫậ]/u', 'a', $string);
    $string = preg_replace('/[éèẻẽẹêếềểễệ]/u', 'e', $string);
    $string = preg_replace('/[íìỉĩị]/u', 'i', $string);
    $string = preg_replace('/[óòỏõọôốồổỗộơớờởỡợ]/u', 'o', $string);
    $string = preg_replace('/[úùủũụưứừửữự]/u', 'u', $string);
    $string = preg_replace('/[ýỳỷỹỵ]/u', 'y', $string);
    $string = preg_replace('/[đ]/u', 'd', $string);
    
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    $string = trim($string, '-');
    
    return $string;
}
function create_array($string,$name=null) {
    $new_array = explode('|', $string);
    if(!empty($name)){
        $result = array();
        foreach ($new_array as $item) {
            $result[] = array($name => $item);
        }
        return $result;
    }else{
        return $new_array;
    }
}

function getGalleryFromIds($ids,$url = null) {
    $htmlGallery = '';
    $image_ids_array = explode('|', $ids);
    if (!empty($image_ids_array)) {
        foreach ($image_ids_array as $image_id) {
            $image_url = wp_get_attachment_image_src($image_id, 'full');
            if ($image_url) {
                if( isset($url) ):
                    $htmlGallery .= '<img src="' . esc_url($image_url[0]) . '" alt="image">';
                else:
                    $htmlGallery .= '<div class="image-preview" data-id="' . $image_id . '">';
                    $htmlGallery .= '<img src="' . esc_url($image_url[0]) . '" alt="image">';
                    $htmlGallery .= '<span class="remove-img-button" data-id="' . $image_id . '">x</span>';
                    $htmlGallery .= '</div>';
                endif;
            }
        }
    } else {
        echo 'No images found.';
    }
    return $htmlGallery;
}

add_action( 'elementor/widgets/register', 'mac_register_custom_widget' );
function mac_register_custom_widget( $widgets_manager ) {
    require_once( MAC_PATH. 'blocks/mac-menu.php' );
    
    $widgets_manager->register( new \Mac_Module_Widget() );
    /* QR code Module */
    include_once MAC_PATH.'adds-on/qr-module.php';
    $widgets_manager->register( new \Mac_Module_Widget_QR() );
}

// phân quyền admin
function add_custom_capabilities() {
    $role = get_role('editor');
    if ($role) {
        $role->add_cap('edit_published_pages');
    }
}
add_action('init', 'add_custom_capabilities');

function remove_custom_capabilities() {
    // Lấy vai trò người dùng
    $role = get_role('editor');
    // Xóa quyền cho vai trò
    if ($role) {
        $role->remove_cap('edit_published_pages');
    }
}
//add_action('init', 'remove_custom_capabilities');

// Hàm để loại bỏ ký tự escape từ chuỗi
function remove_slashes_from_array(&$item, $key) {
    if (is_array($item)) {
        array_walk_recursive($item, 'remove_slashes_from_array');
    } else {
        $item = stripslashes($item);
    }
}

function buildTree(array $elements, $parentId = 0) {
    $branch = array();
    
    foreach ($elements as $element) {
        if ($element->parents_category == $parentId) {
            $children = buildTree($elements, $element->id);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}
function buildOptions($tree, $prefix = '') {
    $html = '';
    foreach ($tree as $branch) {
        $html .= '<option value="' . $tree->id . '">' . $prefix .  $tree->category_name . '</option>';
        if (isset($branch['children'])) {
            $html .= buildOptions($branch['children'], $prefix . '--');
        }
    }
    return $html;
}

/* wp editor */
function custom_tinymce_config($init) {
    $init['wpautop'] = false;
    $init['apply_source_formatting'] = true;
    return $init;
}
add_filter('tiny_mce_before_init', 'custom_tinymce_config');

/** Dynamic */
function register_request_dynamic_tag_group( $dynamic_tags_manager ) {
    $dynamic_tags_manager->register_group(
        'request-mac-menu',
        [
            'title' => esc_html__( 'Mac Category', 'mac-plugin' )
        ]
    );
}
add_action( 'elementor/dynamic_tags/register', 'register_request_dynamic_tag_group' );

function register_request_dynamic_tag_item_menu_group( $dynamic_tags ) {
    $dynamic_tags->register_group(
        'request-mac-item-menu',
        [
            'title' => esc_html__( 'Mac Category List Item', 'mac-plugin' )
        ]
    );
}
add_action( 'elementor/dynamic_tags/register', 'register_request_dynamic_tag_item_menu_group' );

function register_dynamic_tag( $dynamic_tags_manager ) {
    require_once( __DIR__ . '/dynamic-tags/cat-menu/mac-menu-dynamic-tag-name.php' );
    require_once( __DIR__ . '/dynamic-tags/cat-menu/mac-menu-dynamic-tag-description.php' );
    require_once( __DIR__ . '/dynamic-tags/cat-menu/mac-menu-dynamic-tag-price.php' );
    require_once( __DIR__ . '/dynamic-tags/cat-menu/mac-menu-dynamic-tag-img.php' );
    require_once( __DIR__ . '/dynamic-tags/cat-menu/mac-menu-dynamic-tag-gallery.php' );
    require_once( __DIR__ . '/dynamic-tags/cat-menu/mac-menu-dynamic-tag-heading-col.php' );
    require_once( __DIR__ . '/dynamic-tags/cat-menu/mac-menu-dynamic-tag-list-item.php' );
    require_once( __DIR__ . '/dynamic-tags/cat-menu/mac-menu-dynamic-tag-content.php' );

    /** item menu */

    require_once( __DIR__ . '/dynamic-tags/item-menu/mac-menu-dynamic-tag-item-name.php' );
    require_once( __DIR__ . '/dynamic-tags/item-menu/mac-menu-dynamic-tag-item-description.php' );
    require_once( __DIR__ . '/dynamic-tags/item-menu/mac-menu-dynamic-tag-item-price.php' );
    require_once( __DIR__ . '/dynamic-tags/item-menu/mac-menu-dynamic-tag-item-img.php' );


    $dynamic_tags_manager->register( new \Elementor_Dynamic_Tag_Mac_Menu_Name );
    $dynamic_tags_manager->register( new \Elementor_Dynamic_Tag_Mac_Menu_Description );
    $dynamic_tags_manager->register( new \Elementor_Dynamic_Tag_Mac_Menu_Price );
    $dynamic_tags_manager->register( new \Elementor_Dynamic_Tag_Mac_Menu_Img );
    $dynamic_tags_manager->register( new \Elementor_Dynamic_Tag_Mac_Menu_Gallery );
    $dynamic_tags_manager->register( new \Elementor_Dynamic_Tag_Mac_Menu_Heading_Col );
    $dynamic_tags_manager->register( new \Elementor_Dynamic_Tag_Mac_Menu_List_Item );
    $dynamic_tags_manager->register( new \Elementor_Dynamic_Tag_Mac_Menu_Content );

    /** item menu */

    $dynamic_tags_manager->register( new \Elementor_Dynamic_Tag_Mac_Menu_Item_Name );
    $dynamic_tags_manager->register( new \Elementor_Dynamic_Tag_Mac_Menu_Item_Description );
    $dynamic_tags_manager->register( new \Elementor_Dynamic_Tag_Mac_Menu_Item_Price );
    $dynamic_tags_manager->register( new \Elementor_Dynamic_Tag_Mac_Menu_Item_Img );
}
add_action( 'elementor/dynamic_tags/register', 'register_dynamic_tag' );

/** add field set current menu */
function custom_meta_box() {
    $option_value = get_option('mac_custom_meta_box_page');
    $nameMetaBoxPage = !empty($option_value) ? $option_value : "page";
    add_meta_box(
        'custom_meta_box_id',
        'MAC Menu',
        'custom_meta_box_callback',
        $nameMetaBoxPage,
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'custom_meta_box');

function custom_meta_box_callback($post) {
    $value = get_post_meta($post->ID, '_custom_meta_key', true);
    echo '<label for="custom_meta_box_text">Category </label>';
    echo '<input type="text" id="custom_meta_box_text" name="custom_meta_box_text" value="' . esc_attr($value) . '" />';
}

function save_custom_meta_box_data($post_id) {
    if (array_key_exists('custom_meta_box_text', $_POST)) {
        update_post_meta(
            $post_id,
            '_custom_meta_key',
            $_POST['custom_meta_box_text']
        );
    }
}
add_action('save_post', 'save_custom_meta_box_data');

global $custom_id,$custom_index,$custom_array;
$custom_array = [];
$custom_id = null;
$custom_index = null;

function set_custom_array($array) {
    global $custom_array;
    $custom_array = $array;
}

function get_custom_array() {
    global $custom_array;
    return $custom_array;
}

function set_custom_index($index) {
    global $custom_index;
    $custom_index = $index;
}

function get_custom_index() {
    global $custom_index;
    return $custom_index;
}
/* register domain and update plugin */

if (false === get_option('mac_menu_github_key')) {
    add_option('mac_menu_github_key', '0');
}
if (false === get_option('mac_domain_valid_key')) {
    add_option('mac_domain_valid_key', '0');
}
if (false === get_option('mac_domain_valid_status')) {
    add_option('mac_domain_valid_status', '0');
}

// Domain registration functionality moved to MAC Core
// Function mac_register_domain_on_activation() removed - now handled by CRM_API_Manager

// Domain manager functionality moved to MAC Core to avoid conflicts
// require_once( __DIR__ . '/domain-manager.php');

// Load compatibility layer for fallback functions
require_once( __DIR__ . '/includes/compatibility.php');

require_once( __DIR__ . '/update-plugin.php' );

// Load CSV Processor
// CSV processing now integrated into dashboard.php

// export data menu
function export_data_to_csv() {
    if (isset($_REQUEST['export_table_data']) && $_REQUEST['export_table_data'] == '1') {
        global $wpdb;

        // Lấy dữ liệu từ bảng trong database
        $table_name = $wpdb->prefix . "mac_cat_menu"; // Thay đổi theo bảng của bạn
        $data = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

        // Nếu có dữ liệu
        if (!empty($data)) {

            // Xóa tất cả các buffer trước khi xuất
            //ob_clean();
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
            }
            // Xử lý headers để xuất file CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="exported_data.csv"');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Mở luồng ghi CSV
            $output = fopen('php://output', 'w');

            // Thêm dòng tiêu đề vào CSV
            fputcsv($output, array(
                'id', 
                'category_name', 
                'category_description', 
                'price', 
                'featured_img', 
                'parents_category', 
                'is_hidden', 
                'is_table', 
                'table_heading', 
                'item_list_name', 
                'item_list_price', 
                'item_list_description', 
                'item_list_fw', 
                'item_list_img',
                'item_list_position',
                'category_inside',
                'category_inside_order'
            ));
            
            // Duyệt qua các bản ghi và thêm vào CSV
            foreach ($data as $row) {
                // Tạo dữ liệu cho từng dòng
                $rowData = array(
                    $row['id'],
                    $row['category_name'],
                    $row['category_description'], 
                    $row['price'],
                    $row['featured_img'],
                    $row['parents_category'],
                    $row['is_hidden'],
                    $row['is_table'],
                    '', '', '', '', '', '','', $row['category_inside'], $row['category_inside_order']
                );

                // Kiểm tra và xử lý các trường 'data_table' và 'group_repeater'
                if (!empty($row['data_table'])) {
                    // Xử lý data_table từ array JSON thành string với dấu |
                    $data_table = $row['data_table'];
                    
                    // Kiểm tra nếu là array JSON
                    if (is_string($data_table) && (strpos($data_table, '[') === 0)) {
                        $decoded = json_decode($data_table, true);
                        
                        if (is_array($decoded)) {
                            // Lọc bỏ các phần tử rỗng hoặc chỉ chứa dấu ngoặc kép
                            $filtered = array_filter($decoded, function($item) {
                                return !empty($item) && $item !== '""' && $item !== '[""]';
                            });
                            
                            if (!empty($filtered)) {
                                $rowData[8] = implode('|', $filtered);
                            } else {
                                $rowData[8] = ''; // Array rỗng hoặc chỉ chứa dữ liệu rỗng
                            }
                        } else {
                            $rowData[8] = $data_table; // Không phải array, giữ nguyên
                        }
                    } else {
                        $rowData[8] = $data_table; // Không phải JSON, giữ nguyên
                    }
                } else {
                    $rowData[8] = ''; // Trống
                }
                $index = 0;
                

                if (!empty($row['group_repeater'])) {

                    $arrayGroupRepeater = json_decode($row['group_repeater'], true);
                    if(!empty($arrayGroupRepeater)) {
                        foreach ($arrayGroupRepeater as $item) {
                            $rowData1 = $rowData;
                            $rowData1['id']  = $row['id']++;
                            // Cập nhật dữ liệu từ 'group_repeater'
                            if(!empty($item['name'])){
                                // Giữ nguyên format Unicode cho name
                                $rowData1[9] = $item['name'];
                                if (isset($item['price-list']) && is_array($item['price-list'])) {
                                    $prices = array_column($item['price-list'], 'price');
                                } else {
                                    $prices = [];
                                }
                                // Giữ nguyên format Unicode cho prices
                                $stringPrices = implode('|', $prices);
                                $rowData1[10] = $stringPrices;
                                // Giữ nguyên format Unicode cho description
                                $rowData1[11] = $item['description'];
                                $rowData1[12] = $item['fullwidth'];
                                $rowData1[13] = $item['featured_img'];
                                $rowData1[14] = isset($item['position']) ? $item['position'] : '0';

                                $rowData[9] = $item['name'];
                                $rowData[10] = $stringPrices;
                                $rowData[11] = $item['description'];
                                $rowData[12] = $item['fullwidth'];
                                $rowData[13] = $item['featured_img'];
    
                                // Ghi dòng dữ liệu vào CSV
                                
                                if ($index > 0) {
                                    // Nếu không phải dòng đầu tiên, giữ lại dữ liệu của group_repeater mà không lặp lại các thông tin chung
                                    $rowData1[0] = ''; 
                                    $rowData1[1] = ''; 
                                    $rowData1[2] = '';
                                    $rowData1[3] = '';
                                    $rowData1[4] = '';
                                    $rowData1[5] = '';
                                    $rowData1[6] = '';
                                    $rowData1[7] = '';
                                    $rowData1[8] = '';
                                    $rowData1[15] = '';
                                    $rowData1[16] = '';
                                    $rowData1[17] = '';
                                }
    
                                // Ghi vào CSV
                                $index++;
                                fputcsv($output, $rowData1);
                            
                            }
                                
                        }
                    }else{
                        fputcsv($output, $rowData);
                    }
                    

                }else{
                    // Ghi dòng cuối cùng của bản ghi vào CSV
                    //fputcsv($output, $rowData);
                }

                
            }

            // Đóng file output
            //fputcsv($output, $rowData);
            
            fclose($output);
            
            // Dừng script và xuất file CSV
            exit();
        }

        exit();
    }
}
add_action('admin_post_export_csv', 'export_data_to_csv');  // Đăng ký hàm export với WordPress admin hook
add_action('admin_post_nopriv_export_csv', 'export_data_to_csv'); // Cho phép người dùng không đăng nhập sử dụng

// AJAX handler cho Bulk Edit Save
add_action('wp_ajax_mac_bulk_edit_save', 'mac_bulk_edit_save_ajax');
function mac_bulk_edit_save_ajax() {
    // Tắt output buffering và xóa mọi output trước đó
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Tắt error display và suppress warnings để tránh output không mong muốn
    @ini_set('display_errors', 0);
    error_reporting(0); // Tắt tất cả error reporting trong AJAX
    
    // Suppress database errors để không output HTML
    global $wpdb;
    $wpdb->suppress_errors = true;
    $wpdb->show_errors = false;
    
    // Load dashboard.php để có hàm insert_category (trước khi có output)
    if (!function_exists('insert_category')) {
        // Suppress any output from include
        ob_start();
        include_once MAC_PATH . 'includes/admin_pages/dashboard.php';
        ob_end_clean();
    }
    
    // Kiểm tra nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_bulk_edit_save')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    // Kiểm tra quyền
    if (!current_user_can('edit_published_pages')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action'));
        return;
    }
    
    // Lấy data
    if (!isset($_POST['data'])) {
        wp_send_json_error(array('message' => 'No data received'));
        return;
    }
    
    $data = json_decode(stripslashes($_POST['data']), true);
    if (!$data || !is_array($data)) {
        wp_send_json_error(array('message' => 'Invalid data format'));
        return;
    }
    
    // Unflatten data và save
    try {
        $result = mac_bulk_edit_unflatten_and_save($data);
        
        // Đảm bảo result có đúng format
        if (!isset($result['success'])) {
            $result['success'] = false;
        }
        
        if ($result['success']) {
            $response_data = array(
                'message' => !empty($result['message']) ? $result['message'] : 'Changes saved successfully!',
                'inserted_count' => isset($result['inserted_count']) ? intval($result['inserted_count']) : 0
            );
            wp_send_json_success($response_data);
        } else {
            $response_data = array(
                'message' => !empty($result['message']) ? $result['message'] : 'Failed to save changes',
                'errors' => isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : array()
            );
            wp_send_json_error($response_data);
        }
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => 'Error: ' . $e->getMessage(),
            'errors' => array($e->getMessage())
        ));
    }
    
    // wp_send_json_* đã tự động gọi wp_die(), không cần gọi lại
}

/**
 * Unflatten data và save vào database (replace mode)
 */
function mac_bulk_edit_unflatten_and_save($flattened_data) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'mac_cat_menu';
    $errors = array();
    $inserted_count = 0;
    
    try {
        // Đảm bảo hàm create_table_cat được load
        if (!function_exists('create_table_cat')) {
            include_once MAC_PATH . 'includes/admin_pages/table-list-menu.php';
        }
        
        // Kiểm tra bảng tồn tại
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        // Lấy toàn bộ table TRƯỚC khi replace (để log)
        $old_full_table = array();
        if ($table_exists) {
            $old_full_table = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
            if (!$old_full_table) {
                $old_full_table = array();
            }
        }
        
        // REPLACE MODE: Drop và recreate table (hoặc tạo mới nếu chưa có)
        if ($table_exists) {
            // Nếu bảng đã tồn tại, drop và recreate
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
        
        // Tạo bảng mới (hoặc recreate)
        if (function_exists('create_table_cat')) {
            create_table_cat();
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to create table: create_table_cat function not found',
                'errors' => array('create_table_cat function not found')
            );
        }
        
        // Đảm bảo bảng đã được tạo thành công
        $table_exists_after = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists_after) {
            return array(
                'success' => false,
                'message' => 'Failed to create table: Table was not created successfully',
                'errors' => array('Table creation failed')
            );
        }
        
        // Log table recreation
        if (function_exists('log_activity')) {
            log_activity(
                'table_recreate',
                'Dropped and recreated mac_cat_menu table for bulk edit replace mode',
                'mac_cat_menu',
                0
            );
        }
        
        // Unflatten data: Gộp các dòng có cùng category
        $categories = array();
        $current_category_id = null;
        $current_category = null;
        
        // Xử lý trường hợp data trống
        if (empty($flattened_data) || !is_array($flattened_data)) {
            // Nếu data trống, vẫn tạo bảng và return success (bảng trống)
            $new_full_table = array();
            
            // Log chi tiết
            if (function_exists('log_activity')) {
                $description = 'Bulk edit completed: 0 categories (empty data)';
                
                log_activity(
                    'bulk_edit_replace',
                    $description,
                    'mac_cat_menu',
                    0,
                    null,
                    $old_full_table,
                    $new_full_table,
                    true // compressed
                );
            }
            
            // Xóa transient data
            $user_id = get_current_user_id();
            $transient_key = 'mac_bulk_edit_data_' . $user_id;
            delete_transient($transient_key);
            delete_transient($transient_key . '_timestamp');
            
            return array(
                'success' => true,
                'message' => 'Table created successfully. No data to insert.',
                'inserted_count' => 0
            );
        }
        
        $auto_id_counter = 1; // Counter cho ID tự động nếu không có ID
        
        foreach ($flattened_data as $row) {
            // Nếu có category_name (không cần id - có thể là data mới), đây là dòng category chính
            if (!empty($row['category_name'])) {
                // Lưu category trước đó nếu có
                if ($current_category !== null) {
                    $categories[] = $current_category;
                }
                
                // Tạo category mới
                // Nếu không có ID hoặc ID rỗng, dùng AUTO_INCREMENT (không set id)
                $category_id = (!empty($row['id']) && is_numeric($row['id']) && intval($row['id']) > 0) ? intval($row['id']) : null;
                
                $current_category_id = $category_id;
                $current_category = array(
                    'id' => $category_id, // Có thể là null nếu data mới
                    'category_name' => $row['category_name'],
                    'category_description' => isset($row['category_description']) ? $row['category_description'] : '',
                    'price' => isset($row['price']) ? $row['price'] : '',
                    'featured_img' => isset($row['featured_img']) ? $row['featured_img'] : '',
                    'parents_category' => isset($row['parents_category']) && $row['parents_category'] !== '' ? $row['parents_category'] : '0',
                    'is_hidden' => isset($row['is_hidden']) && $row['is_hidden'] !== '' ? $row['is_hidden'] : 0,
                    'is_table' => isset($row['is_table']) && $row['is_table'] !== '' ? $row['is_table'] : 0,
                    'data_table' => isset($row['table_heading']) ? $row['table_heading'] : '',
                    'category_inside' => isset($row['category_inside']) && $row['category_inside'] !== '' ? $row['category_inside'] : 1,
                    'category_inside_order' => isset($row['category_inside_order']) && $row['category_inside_order'] !== '' ? $row['category_inside_order'] : 'new',
                    'group_repeater' => array()
                );
            }
            
            // Nếu có item_list_name, đây là item trong group_repeater
            if (!empty($row['item_list_name']) && $current_category !== null) {
                $item = array(
                    'name' => $row['item_list_name'],
                    'description' => isset($row['item_list_description']) ? $row['item_list_description'] : '',
                    'fullwidth' => isset($row['item_list_fw']) ? $row['item_list_fw'] : 0,
                    'featured_img' => isset($row['item_list_img']) ? $row['item_list_img'] : '',
                    'position' => isset($row['item_list_position']) ? $row['item_list_position'] : 0
                );
                
                // Xử lý price-list
                if (!empty($row['item_list_price'])) {
                    $prices = explode('|', $row['item_list_price']);
                    $price_list = array();
                    foreach ($prices as $price) {
                        if (!empty(trim($price))) {
                            $price_list[] = array('price' => trim($price));
                        }
                    }
                    $item['price-list'] = $price_list;
                } else {
                    $item['price-list'] = array();
                }
                
                $current_category['group_repeater'][] = $item;
            }
        }
        
        // Lưu category cuối cùng
        if ($current_category !== null) {
            $categories[] = $current_category;
        }
        
        // Insert categories vào database
        $order_index = 0;
        foreach ($categories as $category) {
            // Prepare data
            $slug_value = !empty($category['slug_category'])
                ? $category['slug_category']
                : (function_exists('create_slug') ? create_slug($category['category_name']) : $category['category_name']);
            
            $group_repeater_value = !empty($category['group_repeater'])
                ? json_encode($category['group_repeater'])
                : json_encode([]);
            
            // Xử lý data_table
            $data_table_value = '';
            if (!empty($category['data_table'])) {
                if (is_string($category['data_table']) && strpos($category['data_table'], '|') !== false) {
                    // Convert delimited string to array
                    $data_table_array = explode('|', $category['data_table']);
                    $data_table_value = json_encode($data_table_array);
                } else if (is_string($category['data_table']) && (strpos($category['data_table'], '[') === 0)) {
                    // Already JSON
                    $data_table_value = $category['data_table'];
                } else {
                    $data_table_value = json_encode(array($category['data_table']));
                }
            } else {
                $data_table_value = json_encode([]);
            }
            
            $category_data = array(
                'category_name' => $category['category_name'],
                'slug_category' => $slug_value,
                'category_description' => $category['category_description'],
                'price' => $category['price'],
                'featured_img' => $category['featured_img'],
                'parents_category' => $category['parents_category'],
                'group_repeater' => $group_repeater_value,
                'is_table' => $category['is_table'],
                'is_hidden' => $category['is_hidden'],
                'data_table' => $data_table_value,
                'order' => $order_index,
                'category_inside' => $category['category_inside'],
                'category_inside_order' => $category['category_inside_order']
            );
            
            // Đảm bảo hàm insert_category được load
            if (!function_exists('insert_category')) {
                include_once MAC_PATH . 'includes/admin_pages/dashboard.php';
            }
            
            // Xác định có dùng ID cụ thể hay không
            // Chỉ dùng ID cụ thể nếu ID là số và > 0 (có thể là ID từ data cũ)
            // Nếu ID là số nhỏ (1, 2, 3...) và là data mới, có thể dùng AUTO_INCREMENT
            $use_crm_id = false;
            if (!empty($category['id']) && is_numeric($category['id']) && intval($category['id']) > 0) {
                // Kiểm tra xem ID này có phải là ID thực từ database cũ không
                // Nếu là data mới (không có trong old_full_table), dùng AUTO_INCREMENT
                $id_exists_in_old = false;
                if (!empty($old_full_table)) {
                    foreach ($old_full_table as $old_row) {
                        if (isset($old_row['id']) && intval($old_row['id']) == intval($category['id'])) {
                            $id_exists_in_old = true;
                            break;
                        }
                    }
                }
                
                // Chỉ dùng ID cụ thể nếu ID tồn tại trong old data (restore) hoặc ID lớn (có thể là từ import)
                if ($id_exists_in_old || intval($category['id']) > 100) {
                    $use_crm_id = true;
                    $category_data['id'] = intval($category['id']);
                }
            }
            
            if (function_exists('insert_category')) {
                $insert_result = insert_category($category_data, $use_crm_id);
                
                if ($insert_result === false) {
                    $errors[] = 'Failed to insert category: ' . $category['category_name'] . ' - ' . $wpdb->last_error;
                } else {
                    $inserted_count++;
                }
            } else {
                // Fallback: dùng wpdb->insert trực tiếp
                if ($use_crm_id && isset($category_data['id'])) {
                    // Insert với ID cụ thể
                    $sql = "INSERT INTO $table_name SET ";
                    $sql_parts = [];
                    $sql_parts[] = "`id` = " . intval($category_data['id']);
                    unset($category_data['id']);
                    
                    foreach ($category_data as $key => $value) {
                        if ($value !== null && $value !== '') {
                            $escaped_key = "`" . $key . "`";
                            $sql_parts[] = "$escaped_key = " . $wpdb->prepare('%s', $value);
                        }
                    }
                    
                    $sql .= implode(', ', $sql_parts);
                    $insert_result = $wpdb->query($sql);
                } else {
                    // Dùng AUTO_INCREMENT
                    $insert_result = $wpdb->insert($table_name, $category_data);
                }
                
                if ($insert_result === false) {
                    $errors[] = 'Failed to insert category: ' . $category['category_name'] . ' - ' . $wpdb->last_error;
                } else {
                    $inserted_count++;
                }
            }
            
            $order_index++;
        }
        
        // Lấy toàn bộ table SAU khi replace (để log)
        $new_full_table = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
        
        // Log chi tiết (suppress errors nếu có)
        if (function_exists('log_activity')) {
            $description = sprintf(
                'Bulk edit completed: %d categories inserted',
                $inserted_count
            );
            
            if (!empty($errors)) {
                $description .= ' with ' . count($errors) . ' errors';
            }
            
            // Suppress errors khi log để tránh làm hỏng JSON response
            $old_suppress = $wpdb->suppress_errors;
            $old_show = $wpdb->show_errors;
            $wpdb->suppress_errors = true;
            $wpdb->show_errors = false;
            
            @log_activity(
                'bulk_edit_replace',
                $description,
                'mac_cat_menu',
                $inserted_count,
                !empty($errors) ? implode('; ', $errors) : null,
                $old_full_table,
                $new_full_table,
                true // compressed
            );
            
            // Restore error settings
            $wpdb->suppress_errors = $old_suppress;
            $wpdb->show_errors = $old_show;
        }
        
        // Xóa transient data
        $user_id = get_current_user_id();
        $transient_key = 'mac_bulk_edit_data_' . $user_id;
        delete_transient($transient_key);
        delete_transient($transient_key . '_timestamp');
        
        if (!empty($errors)) {
            return array(
                'success' => false,
                'message' => 'Saved with ' . count($errors) . ' errors. ' . $inserted_count . ' categories inserted.',
                'errors' => $errors,
                'inserted_count' => $inserted_count
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Successfully saved ' . $inserted_count . ' categories.',
            'inserted_count' => $inserted_count
        );
        
    } catch (Exception $e) {
        // Log error
        if (function_exists('log_activity')) {
            log_activity(
                'bulk_edit_error',
                'Bulk edit failed: ' . $e->getMessage(),
                'mac_cat_menu',
                0,
                $e->getMessage()
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'errors' => array($e->getMessage())
        );
    }
}


////////////////////
/// *** Privacy Policy Settings
////////////////////////

include_once(   __DIR__ .  '/mac-privacy-policy-settings.php' );

////////////////////
/// *** Adds on
////////////////////////

include_once MAC_PATH.'adds-on/dual-price.php';


if (false === get_option('mac_qr_code')) {
    add_option('mac_qr_code', '0');
}
$macQRCode = !empty(get_option('mac_qr_code')) ? get_option('mac_qr_code') : 0;
/* QR code pages */
if($macQRCode == 1) {
    include_once MAC_PATH.'adds-on/qr-code-pages/qr-code-pages.php';
}
include_once MAC_PATH.'adds-on/qr-code-pages/short-code.php';

include_once __DIR__ . '/adds-on/popup-tuner.php';

class My_Custom_CSS_Elementor {
    public function __construct() {
        add_action('elementor/element/after_section_end', [$this, 'add_custom_css_section'], 10, 2);
        add_action('elementor/element/parse_css', [$this, 'add_custom_css_to_elementor'], 10, 2);
    }

    public function add_custom_css_section($element, $section_id) {
        if ('section_advanced' !== $section_id) {
            return;
        }

        $element->start_controls_section(
            'mac_section_custom_css', // Thêm tiền tố 'mac'
            [
                'label' => esc_html__('Custom CSS', 'my-custom-css'),
                'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
            ]
        );

        $element->add_control(
            'mac_custom_css', // Thêm tiền tố 'mac'
            [
                'label' => esc_html__('Add your own custom CSS', 'my-custom-css'),
                'type' => \Elementor\Controls_Manager::CODE,
                'language' => 'css',
                'render_type' => 'ui',
            ]
        );

        $element->end_controls_section();
    }

    public function add_custom_css_to_elementor($post_css, $element) {
        $element_settings = $element->get_settings();
        if (empty($element_settings['mac_custom_css'])) { // Cập nhật tên control
            return;
        }

        $css = trim($element_settings['mac_custom_css']); // Cập nhật tên control
        if (empty($css)) {
            return;
        }

        $css = str_replace('selector', $post_css->get_element_unique_selector($element), $css);
        $post_css->get_stylesheet()->add_raw_css($css);
    }
}

// Khởi tạo plugin
add_action('plugins_loaded', function() {
    if (did_action('elementor/loaded')) {
        new My_Custom_CSS_Elementor();
    }
});


/** custom link auto editor */
include_once MAC_PATH.'adds-on/auto-login.php';

include_once MAC_PATH.'adds-on/mac-authorize-policy-and-terms-settings.php';

// Plugin Lifecycle Hooks for Activity Logging
register_activation_hook(__FILE__, 'mac_menu_activate');
register_deactivation_hook(__FILE__, 'mac_menu_deactivate');

// Ensure table exists on plugin load
add_action('init', 'force_create_activity_log_table');

// DISABLED: Không tạo snapshot table nữa, dùng activity_log thay thế
// Ensure snapshot table exists on plugin load
// add_action('init', function() {
//     if (class_exists('MacMenuTableSnapshot')) {
//         (new MacMenuTableSnapshot())->forceCreateTable();
//     }
// });

// Xóa bảng wp_mac_menu_table_snapshots nếu tồn tại (chạy 1 lần khi plugin load)
add_action('init', function() {
    global $wpdb;
    $snapshot_table = $wpdb->prefix . 'mac_menu_table_snapshots';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$snapshot_table'");
    
    if ($table_exists) {
        // Kiểm tra xem đã xóa chưa (tránh xóa nhiều lần)
        $deleted_option = get_option('mac_menu_snapshot_table_deleted', false);
        if (!$deleted_option) {
            // Xóa bảng
            $wpdb->query("DROP TABLE IF EXISTS $snapshot_table");
            // Đánh dấu đã xóa
            update_option('mac_menu_snapshot_table_deleted', true);
            error_log("MAC Menu: Deleted snapshot table $snapshot_table");
        }
    }
}, 999); // Priority cao để chạy sau các hook khác

function mac_menu_activate() {
    // Create activity log table if it doesn't exist
    create_activity_log_table();
    
    // Log activation
    log_activity('plugin_activate', 'Plugin MAC Menu activated');
}

function mac_menu_deactivate() {
    // Log deactivation
    log_activity('plugin_deactivate', 'Plugin MAC Menu deactivated');
}

// Hook for plugin updates
add_action('upgrader_process_complete', 'mac_menu_plugin_updated', 10, 2);

function mac_menu_plugin_updated($upgrader, $hook_extra) {
    // Check if this is a plugin operation
    if ($hook_extra['type'] !== 'plugin') {
        return;
    }
    
    // Helper function to check if plugin is MAC plugin
    $is_mac_plugin = function($plugin_path) {
        return strpos($plugin_path, 'mac-') === 0;
    };
    
    // Handle plugin installation (when plugins array is null)
    if (!isset($hook_extra['plugins']) || $hook_extra['plugins'] === null) {
        // This is likely a fresh installation
        if (isset($hook_extra['plugin']) && 
            $hook_extra['plugin'] === 'mac-menu/mac-menu.php' && 
            $is_mac_plugin('mac-menu')) {
            log_activity('plugin_install', 'Plugin MAC Menu installed');
        }
        return;
    }
    
    // Handle plugin updates (when plugins array exists)
    if (is_array($hook_extra['plugins'])) {
        foreach ($hook_extra['plugins'] as $plugin) {
            // Only process MAC plugins
            if ($is_mac_plugin($plugin)) {
                if ($plugin === 'mac-menu/mac-menu.php') {
                    log_activity('plugin_update', 'Plugin MAC Menu updated');
                }
                // Could add other MAC plugins here if needed
                // elseif ($plugin === 'mac-reservation/mac-reservation.php') {
                //     log_activity('plugin_update', 'Plugin MAC Reservation updated');
                // }
            }
        }
    }
}

// ============================================
// JetEngine Query Builder Integration
// ============================================

// Include debug page (luôn load để có thể debug)
require_once MAC_PATH . 'includes/jet-engine-debug.php';

// Include JetEngine integration - Load sớm và để integration file tự check
// Check xem JetEngine plugin có active không
function mac_menu_is_jetengine_active() {
    // Check plugin file exists
    $jetengine_file = WP_PLUGIN_DIR . '/jet-engine/jet-engine.php';
    if ( ! file_exists( $jetengine_file ) ) {
        return false;
    }
    
    // Check plugin active
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    return is_plugin_active( 'jet-engine/jet-engine.php' );
}

// Load integration file nếu JetEngine có thể active
if ( mac_menu_is_jetengine_active() ) {
    $integration_file = MAC_PATH . 'includes/jet-engine-integration.php';
    if ( file_exists( $integration_file ) ) {
        require_once $integration_file;
        error_log( 'Mac Menu: JetEngine integration file loaded (JetEngine plugin detected)' );
    }
}