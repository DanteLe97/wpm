<?php                           
    $keyDomain = !empty(get_option('mac_domain_valid_key')) ? get_option('mac_domain_valid_key') : "0" ;
    
    $statusDomain = !empty(get_option('mac_domain_valid_status')) ? get_option('mac_domain_valid_status') : "0" ;
    
    // Kiểm tra key - thống nhất flow cho cả 2 trường hợp
    $has_valid_key = !empty($keyDomain) && $keyDomain !== "0";
    
    // Nếu không có key, chặn page và redirect
    if (!$has_valid_key) {
        ?>
        <div class="wrap">
            <div class="notice notice-warning">
                <h3>⚠️ License Key Required</h3>
                <p>You need to add a valid license key to access the MAC Menu dashboard.</p>
                <?php if (mac_menu_is_core_available()): ?>
                    <p><a href="<?php echo admin_url('admin.php?page=mac-core'); ?>" class="button button-primary">Go to MAC Core Settings</a></p>
                <?php else: ?>
                    <p><strong>Please install and activate MAC Core plugin to manage your license.</strong></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return; // Dừng thực thi code tiếp theo
    }
    
    // Kiểm tra management capabilities (chỉ khi có key)
    $mac_core_available = mac_menu_is_core_available();
    $has_valid_status = !empty($statusDomain) && ($statusDomain =='activate' || $statusDomain =='deactivate');
    $can_manage = $mac_core_available && $has_valid_status;
    if ( is_admin() ) {
        if( ! function_exists('get_plugin_data') ){
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        $plugin_file = WP_PLUGIN_DIR . '/mac-menu/mac-menu.php';
        $plugin_data = get_plugin_data( $plugin_file );
    }
    if(isset($plugin_data['Version'])){
        $current_version = $plugin_data['Version'];
    }else{
        $current_version = '1.0.1';
    }
    
?>
<div id="post-body" style="margin-top: 50px;">
    <?php 
        // Display success message for CSV import
        if (isset($_GET['csv_imported']) && $_GET['csv_imported'] === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p>CSV processed successfully via CRM! Menu has been updated.</p></div>';
        }
        
        global $wpdb;
        // Tên bảng
        $cattablename = $wpdb->prefix . 'mac_cat_menu';
        // Kiểm tra sự tồn tại của bảng
        $table_exists_query = $wpdb->prepare("SHOW TABLES LIKE %s", $cattablename);
        $table_exists = $wpdb->get_var($table_exists_query);
        if(current_user_can('edit_dashboard')):
        ?>
        <div class="page-settings-wrap">
            <div class="content">
                <div class="wrap mac-dashboard">
                    <h1 class="wp-heading-inline"></h1>
                    <?php if( $can_manage ){ ?>
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2>IMPORT MENU <?php if (class_exists('MAC_Core\CRM_API_Manager') && MAC_Core\CRM_API_Manager::get_instance()->is_license_valid()): ?><span style="color: #0073aa; font-size: 14px;">(via CRM)</span><?php endif; ?></h2>
                        </div>
                        
                        <div class="inside is-primary-category"> 
                           
                            <select class="mac-selection-data mac-selection-import-mode">
                                <option value="" selected>Please select an import mode</option>
                                <option value="replace">Replace existing data (delete and re-import)</option>
                                <option value="append">Append to existing data (add new items only)</option>
                            </select>
                            <div class="mac-data-replace">
                                <?php
                                if ($table_exists === $cattablename):
                                    ?>
                                        <div class="form-add-cat-menu">
                                            <form action="<?php echo admin_url('admin.php?page=mac-menu'); ?>" method="post" id="posts-filter" enctype="multipart/form-data">
                                                <input id="input-delete-data" type="text" data-table="<?= $cattablename ?>" name="delete_data" value="" readonly style="border:none;padding:0; opacity: 0; width: 0;">
                                                <input type="file" name="csv_file_cat" accept=".csv" required>
                                                <input type="button" name="submit-cat" value="submit" class="btn-delete-menu">
                                            </form>
                                        </div>
                                        <!-- -->
                                        <div class="overlay" id="overlay"></div>
                                        <div class="confirm-dialog" id="confirmDialog" >
                                            <p>Are you sure you want to delete existing data and import new CSV?</p>
                                            <div class="btn-wrap">
                                                <div id="confirmOk">OK</div>
                                                <div id="confirmCancel">Cancel</div>
                                            </div>
                                        </div>
                                    <?php
                                else:
                                    ?>
                                        <div class="form-add-cat-menu">
                                            <form action="" method="post" enctype="multipart/form-data">
                                                <input type="file" name="csv_file_cat" accept=".csv" required>
                                                <input type="submit" name="submit-cat" value="submit">
                                            </form>
                                        </div>
                                    <?php
                                endif;
                                ?>
                            </div>
                            <div class="mac-data-append">
                                <div class="form-append-cat-menu" > <!--style="display:none"-->
                                    <form action="" method="post" enctype="multipart/form-data">
                                        <input type="text" name="name_shop" placeholder="Shop Name" required>
                                        <input type="file" name="csv_file_cat_append" accept=".csv" required>
                                        <input type="submit" name="submit-cat" value="submit">
                                    </form>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2>EXPORT MENU</h2>
                        </div>
                        <div class="inside is-primary-category"> 
                            <?php
                            if ($table_exists === $cattablename):
                                ?>
                                    <a href="<?php echo admin_url('admin-post.php?action=export_csv&export_table_data=1'); ?>">Export CSV</a>
                            <?php
                            endif;
                            ?>
                        </div>
                    </div>

            <?php } ?>
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2>Setting Custom Meta Box Page</h2>
                        </div>
                        <div class="inside"> 
                            <div class="mac-custom-meta-box-page-wrap" >
                                <form id="mac-custom-meta-box-page-form" method="post" enctype="multipart/form-data">
                                    <label>Enter Name Meta Box Page:</label>
                                    <?php $ValueMetaBoxPage = !empty(get_option('mac_custom_meta_box_page')) ? get_option('mac_custom_meta_box_page') : "" ; ?>
                                    <input type="text" name="mac-custom-meta-box-page" value="<?=$ValueMetaBoxPage;?>" required>
                                    <button type="submit">Save</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php if( $can_manage ){ ?>
                    <div class="form-add-settings-menu">
                        <form id="formCategorySettingsMain" method="post" enctype="multipart/form-data">
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2>SETTINGS</h2>
                                </div>
                                <div class="inside is-primary-category" <?php if($statusDomain =='pending' || $statusDomain =='0' ){ echo 'style="opacity: 0; visibility: hidden; height: 0;"';} ?>> 
                                    <table class="form-settings-page">
                                        <tr>
                                            <td>On/Off Description WP Editor </td>
                                            <td>
                                                <?php $editorTextValue = !empty(get_option('mac_menu_text_editor')) ? get_option('mac_menu_text_editor') : "0" ; ?>
                                                <div class="mac-switcher-wrap mac-switcher-btn <?php if($editorTextValue == "1") echo 'active'; ?>">
                                                    <span class="mac-switcher-true">On</span>
                                                    <span class="mac-switcher-false">Off</span>
                                                    <input type="text" name="mac-menu-text-editor" value="<?= $editorTextValue ?>" readonly/>
                                                </div>
                                            </td>
                                        </tr>  
                                        <tr>
                                            <td><h2 style="font-size: 18px; font-weight:700;">Menu</h2></td>
                                        </tr>

                                        <tr>
                                            <td><h2 style="font-size: 18px; font-weight:500;">Content</h2></td>
                                        </tr>

                                        <tr>
                                            <td>On/Off Element Category</td>
                                            <td>
                                                <?php $elementCatValue = !empty(get_option('mac_menu_element_category')) ? get_option('mac_menu_element_category') : "0" ; ?>
                                                <div class="mac-switcher-wrap mac-switcher-btn <?php if($elementCatValue == "1") echo 'active'; ?>">
                                                    <span class="mac-switcher-true">On</span>
                                                    <span class="mac-switcher-false">Off</span>
                                                    <input type="text" name="mac-menu-element-category" value="<?= $elementCatValue ?>" readonly/>
                                                </div>
                                            </td>
                                        </tr> 
                                        <tr>
                                            <td>On/Off Element Category Table</td>
                                            <td>
                                                <?php $elementCatTableValue = !empty(get_option('mac_menu_element_category_table')) ? get_option('mac_menu_element_category_table') : "0" ; ?>
                                                <div class="mac-switcher-wrap mac-switcher-btn <?php if($elementCatTableValue == "1") echo 'active'; ?>">
                                                    <span class="mac-switcher-true">On</span>
                                                    <span class="mac-switcher-false">Off</span>
                                                    <input type="text" name="mac-menu-element-category-table" value="<?= $elementCatTableValue ?>" readonly/>
                                                </div>
                                            </td>
                                        </tr> 

                                        <tr>
                                            <td><h2 style="font-size: 18px; font-weight:500;">Style</h2></td>
                                        </tr>

                                        <tr>
                                            <td>On/Off Background</td>
                                            <td>
                                                <?php $bgValue = !empty(get_option('mac_menu_background')) ? get_option('mac_menu_background') : "0" ; ?>
                                                <div class="mac-switcher-wrap mac-switcher-btn <?php if($bgValue == "1") echo 'active'; ?>">
                                                    <span class="mac-switcher-true">On</span>
                                                    <span class="mac-switcher-false">Off</span>
                                                    <input type="text" name="mac-menu-background" value="<?= $bgValue ?>" readonly/>
                                                </div>
                                            </td>
                                        </tr> 
                                        <tr>
                                            <td>On/Off Spacing</td>
                                            <td>
                                                <?php $spacingValue = !empty(get_option('mac_menu_spacing')) ? get_option('mac_menu_spacing') : "0" ; ?>
                                                <div class="mac-switcher-wrap mac-switcher-btn <?php if($spacingValue == "1") echo 'active'; ?>">
                                                    <span class="mac-switcher-true">On</span>
                                                    <span class="mac-switcher-false">Off</span>
                                                    <input type="text" name="mac-menu-spacing" value="<?= $spacingValue ?>" readonly/>
                                                </div>
                                            </td>
                                        </tr> 
                                        <tr>
                                            <td>On/Off Image</td>
                                            <td>
                                                <?php $imgValue = !empty(get_option('mac_menu_img')) ? get_option('mac_menu_img') : "0" ; ?>
                                                <div class="mac-switcher-wrap mac-switcher-btn <?php if($imgValue == "1") echo 'active'; ?>">
                                                    <span class="mac-switcher-true">On</span>
                                                    <span class="mac-switcher-false">Off</span>
                                                    <input type="text" name="mac-menu-img" value="<?= $imgValue ?>" readonly/>
                                                </div>
                                            </td>
                                        </tr> 
                                        <tr>
                                            <td>On/Off Item Image</td>
                                            <td>
                                                <?php $itemImgValue = !empty(get_option('mac_menu_item_img')) ? get_option('mac_menu_item_img') : "0" ; ?>
                                                <div class="mac-switcher-wrap mac-switcher-btn <?php if($itemImgValue == "1") echo 'active'; ?>">
                                                    <span class="mac-switcher-true">On</span>
                                                    <span class="mac-switcher-false">Off</span>
                                                    <input type="text" name="mac-menu-item-img" value="<?= $itemImgValue ?>" readonly/>
                                                </div>
                                            </td>
                                        </tr> 
                                        <tr>
                                            <td><h2 style="font-size: 18px; font-weight:500;">Typography</h2></td>
                                        </tr>
                                        <tr>
                                            <td>Rank</td>
                                            <td>
                                                <?php $rankNumber = !empty(get_option('mac_menu_rank')) ? get_option('mac_menu_rank') : "1" ; ?>
                                                <select class="mac-is-selection" name="mac-menu-rank">
                                                    <option value="1" <?= ($rankNumber == 1) ? "selected" : ""  ?>>1</option>
                                                    <option value="2" <?= ($rankNumber == 2) ? "selected" : ""  ?>>2</option>
                                                    <option value="3" <?= ($rankNumber == 3) ? "selected" : ""  ?>>3</option>
                                                    <option value="4" <?= ($rankNumber == 4) ? "selected" : ""  ?>>4</option>
                                                    <option value="5" <?= ($rankNumber == 5) ? "selected" : ""  ?>>5</option>
                                                </select>
                                            </td>
                                        </tr> 
                                        <tr>
                                            <td>Javascript Link Menu</td>
                                            <td>
                                                <?php $rankNumber = !empty(get_option('mac_menu_js_link')) ? get_option('mac_menu_js_link') : "0" ; ?>
                                                <select class="mac-is-selection" name="mac-menu-js-link">
                                                    <option value="0" <?= ($rankNumber == 0) ? "selected" : ""  ?>>Default</option>
                                                    <option value="1" <?= ($rankNumber == 1) ? "selected" : ""  ?>>Link Menu</option>
                                                </select>
                                            </td>
                                        </tr>   
                                        <?php do_action('mac_menu_add_on_additional_settings_row_dual_price'); ?>
                                        <tr>
                                            <td><h2 style="font-size: 18px; font-weight:500;">QR Code</h2></td>
                                        </tr>
                                        <tr>
                                            <td>On/Off QR Code</td>
                                            <td class="mac-qr-code">
                                                <?php $macQR = !empty(get_option('mac_qr_code')) ? get_option('mac_qr_code') : "0" ; ?>
                                                <div class="mac-switcher-wrap mac-switcher-btn <?php if($macQR == "1") echo 'active'; ?>">
                                                    <span class="mac-switcher-true">On</span>
                                                    <span class="mac-switcher-false">Off</span>
                                                    <input type="text" name="mac-menu-qr" value="<?= $macQR ?>" readonly/>
                                                </div>
                                                <?php $macHeading = !empty(get_option('mac_qr_title')) ? get_option('mac_qr_title') : "" ; ?>
                                                <label>Heading Title:</label>
                                                <input type="text" name="mac-qr-title" value="<?=$macHeading;?>">
                                            </td>
                                        </tr> 

                                        <tr>
                                            <td><h2 style="font-size: 18px; font-weight:500;">HTML New</h2></td>
                                        </tr>
                                        <tr>
                                            <td>On/Off HTML</td>
                                            <td class="mac-html">
                                                <?php $macHTMLOld = !empty(get_option('mac_html_old')) ? get_option('mac_html_old') : "0" ; ?>
                                                <div class="mac-switcher-wrap mac-switcher-btn <?php if($macHTMLOld == "1") echo 'active'; ?>">
                                                    <span class="mac-switcher-true">On</span>
                                                    <span class="mac-switcher-false">Off</span>
                                                    <input type="text" name="mac-html-old" value="<?= $macHTMLOld ?>" readonly/>
                                                </div>
                                            </td>
                                        </tr> 
                                    </table>
                                </div>
                              
                            </div>
                            <input type="text" name="action" value="menu-settings" readonly style="display:none;">
                            <input type="submit" name="submit-settings" class="mac-btn-save-chages" value="Save Changes">
                        </form>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
            <?php
        else:
            wp_die('You do not have sufficient permissions to access this page.');
        endif;
    ?>
</div>
<?php
if (false === get_option('mac_custom_meta_box_page')) {
    add_option('mac_custom_meta_box_page', '0');
}
if (false === get_option('mac_menu_element_category')) {
    add_option('mac_menu_element_category', '0');
}
if (false === get_option('mac_menu_element_category_table')) {
    add_option('mac_menu_element_category_table', '0');
}
if (false === get_option('mac_menu_background')) {
    add_option('mac_menu_background', '0');
}
if (false === get_option('mac_menu_spacing')) {
    add_option('mac_menu_spacing', '0');
}
if (false === get_option('mac_menu_rank')) {
    add_option('mac_menu_rank', '1');
}
if (false === get_option('mac_menu_text_editor')) {
    add_option('mac_menu_text_editor', '0');
}
if (false === get_option('mac_menu_img')) {
    add_option('mac_menu_img', '0');
}
if (false === get_option('mac_menu_item_img')) {
    add_option('mac_menu_item_img', '0');
}
if (false === get_option('mac_menu_js_link')) {
    add_option('mac_menu_js_link', '0');
}
if (false === get_option('mac_qr_title')) {
    add_option('mac_qr_title', '');
}

if (false === get_option('mac_html_old')) {
    add_option('mac_html_old', '1');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_REQUEST['mac-custom-meta-box-page']) && !empty($_REQUEST['mac-custom-meta-box-page']) ) {
        update_option('mac_custom_meta_box_page', $_REQUEST['mac-custom-meta-box-page']);
        mac_redirect('admin.php?page=mac-menu');
    }
    
}

if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'menu-settings'){
    $updated_options = array();
    
    if(isset($_REQUEST['mac-menu-element-category']) ) {
        update_option('mac_menu_element_category', $_REQUEST['mac-menu-element-category']);
        $updated_options[] = 'Element Category';
    }
    if(isset($_REQUEST['mac-menu-element-category-table']) ) {
        update_option('mac_menu_element_category_table', $_REQUEST['mac-menu-element-category-table']);
        $updated_options[] = 'Element Category Table';
    }
    if(isset($_REQUEST['mac-menu-background'])) {
        update_option('mac_menu_background', $_REQUEST['mac-menu-background']);
        $updated_options[] = 'Background';
    }
    if(isset($_REQUEST['mac-menu-spacing'])) {
        update_option('mac_menu_spacing', $_REQUEST['mac-menu-spacing']);
        $updated_options[] = 'Spacing';
    }
    if(isset($_REQUEST['mac-menu-rank']) && !empty($_REQUEST['mac-menu-rank']) ) {
        update_option('mac_menu_rank', $_REQUEST['mac-menu-rank']);
        $updated_options[] = 'Rank';
    }
    if(isset($_REQUEST['mac-menu-text-editor']) ) {
        update_option('mac_menu_text_editor', $_REQUEST['mac-menu-text-editor']);
        $updated_options[] = 'Text Editor';
    }
    if(isset($_REQUEST['mac-menu-img']) ) {
        update_option('mac_menu_img', $_REQUEST['mac-menu-img']);
        $updated_options[] = 'Image';
    }
    if(isset($_REQUEST['mac-menu-item-img']) ) {
        update_option('mac_menu_item_img', $_REQUEST['mac-menu-item-img']);
        $updated_options[] = 'Item Image';
    }
    if(isset($_REQUEST['mac-menu-js-link']) ) {
        update_option('mac_menu_js_link', $_REQUEST['mac-menu-js-link']);
        $updated_options[] = 'JS Link';
    }
    
    if(isset($_REQUEST['mac-menu-dp']) ) {
        update_option('mac_menu_dp', $_REQUEST['mac-menu-dp']);
        $updated_options[] = 'Dual Price';
    }
    if(isset($_REQUEST['mac-menu-dp-sw']) ) {
        update_option('mac_menu_dp_sw', $_REQUEST['mac-menu-dp-sw']);
        $updated_options[] = 'Dual Price Switch';
    }
    if(isset($_REQUEST['mac-menu-dp-value']) ) {
        update_option('mac_menu_dp_value', $_REQUEST['mac-menu-dp-value']);
        $updated_options[] = 'Dual Price Value';
    }
    if(isset($_REQUEST['mac-menu-qr']) ) {
        update_option('mac_qr_code', $_REQUEST['mac-menu-qr']);
        $updated_options[] = 'QR Code';
    }
    if(isset($_REQUEST['mac-qr-title']) ) {
        update_option('mac_qr_title', $_REQUEST['mac-qr-title']);
        $updated_options[] = 'QR Title';
    }
    if(isset($_REQUEST['mac-html-old'])) {
        $mac_html_old_value = sanitize_text_field($_REQUEST['mac-html-old']);
        update_option('mac_html_old', $mac_html_old_value);
        $updated_options[] = 'HTML Old';
    }
    
    // Log settings update
    if (!empty($updated_options)) {
        log_activity(
            'settings_update', 
            'Updated plugin settings: ' . implode(', ', $updated_options), 
            'wp_options', 
            count($updated_options)
        );
    }
    
    mac_redirect('admin.php?page=mac-menu');
    exit();
}
function importCSV($fileCSV, $parentID = 0, $parentName = ''){
    // Initialize CSV import process
    
    if($fileCSV['error'] === UPLOAD_ERR_OK ) {
        // Check if CRM connection is active
        if (!class_exists('MAC_Core\CRM_API_Manager')) {
            log_activity('crm_error', 'CRM class not found', null, 0, 'MAC_Core\CRM_API_Manager class not available');
            return false;
        }
        
        $crm_instance = MAC_Core\CRM_API_Manager::get_instance();
        
        if (!$crm_instance->is_license_valid()) {
            log_activity('crm_error', 'CRM license not valid', null, 0, 'CRM license validation failed');
            return false;
        }
        
        $csv_file = $fileCSV;
        $tmp_name = $csv_file["tmp_name"];
        
        // Determine import mode based on parent info
        $import_mode = (!empty($parentID) && !empty($parentName)) ? 'append' : 'replace';
        
        // Process CSV through CRM
        $crm = MAC_Core\CRM_API_Manager::get_instance();

        $result = $crm->upload_csv_to_crm($tmp_name, $import_mode);
        
        
        if ($result['success']) {
            // Apply the processed data from CRM
            $applied = apply_crm_processed_data($result['data'], $parentID, $parentName);
            
            // Log successful import
            $import_mode = (!empty($parentID) && !empty($parentName)) ? 'append' : 'replace';
            $record_count = isset($result['data']['categories']) ? count($result['data']['categories']) : 0;
            log_activity(
                'import_' . $import_mode, 
                'CSV import via CRM - ' . $import_mode . ' mode', 
                'mac_cat_menu', 
                $record_count
            );
            
            return (bool) $applied;
        } else {
            // Log import error
            $error_msg = $result['message'] ?? 'Unknown error';
            log_activity(
                'import_error', 
                'CSV import failed via CRM', 
                'mac_cat_menu', 
                0, 
                $error_msg
            );
            return false;
        }
    } else {
        echo 'Upload Failed';
    }
}



// Function to apply CRM processed data to local database
function apply_crm_processed_data($data, $parentID = 0, $parentName = '') {
    if (empty($data['categories'])) {
        return false;
    }
    
    // Apply categories data
    $result = apply_categories_data($data['categories'], $parentID, $parentName);
    
    return $result;
}

// Function to apply categories data to local database
function apply_categories_data($categories, $parentID = 0, $parentName = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mac_cat_menu';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    if (!$table_exists) {
        if (function_exists('create_table_cat')) {
            create_table_cat();
        }
    }
    
    // Determine if this is append mode (has parent info)
    $is_append_mode = !empty($parentID) && !empty($parentName);
    
    if ($is_append_mode) {
        // APPEND MODE: Insert categories under parent
        
        // Tìm ID lớn nhất hiện tại để tạo category parent
        $max_id = $wpdb->get_var("SELECT MAX(id) FROM $table_name");
        $category_parent_id = $max_id + 1;
        
        // Create category parent category
        $category_parent_data = array(
            'category_name' => $parentName,
            'slug_category' => function_exists('create_slug') ? create_slug($parentName) : $parentName,
            'category_description' => 'Shop parent category for imported data',
            'price' => '',
            'featured_img' => '',
            'parents_category' => 0,
            'group_repeater' => json_encode([]),
            'is_table' => 0,
            'is_hidden' => 0,
            'data_table' => json_encode([]),
            'order' => 0,
            'category_inside' => 1,
            'category_inside_order' => 'new'
        );
        
        $shop_insert_result = $wpdb->insert($table_name, $shop_parent_data);
        if ($shop_insert_result === false) {
            log_activity('database_error', 'Failed to create shop parent category', 'mac_cat_menu', 0, $wpdb->last_error);
            return false;
        }
        $category_parent_id = $wpdb->insert_id;
        
        // Log shop parent creation
        log_activity('shop_parent_created', 'Created shop parent category: ' . $parentName, 'mac_cat_menu', 1);
        
    } else {
        // REPLACE MODE: Clear existing data and reset IDs
        
        // Drop and recreate table to reset auto-increment ID
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        // Recreate table
        if (function_exists('create_table_cat')) {
            create_table_cat();
        } else {
            return false;
        }
        
        // Log table recreation
        log_activity('table_recreate', 'Dropped and recreated mac_cat_menu table for replace mode', 'mac_cat_menu', 0);
    }
    
    // Insert new categories data
    $inserted_count = 0;
    $error_count = 0;
    $order_index = 0;
    
    foreach ($categories as $index => $category) {
        
        // Prepare category data for database
        $slug_value = !empty($category['slug_category'])
            ? $category['slug_category']
            : (function_exists('create_slug') ? create_slug($category['category_name']) : $category['category_name']);
        
        $group_repeater_value = isset($category['group_repeater']) && is_array($category['group_repeater'])
            ? json_encode($category['group_repeater'])
            : (is_string($category['group_repeater']) ? $category['group_repeater'] : json_encode([]));
        
        $data_table_value = '';
        if (isset($category['data_table'])) {
            if (is_array($category['data_table'])) {
                $data_table_value = json_encode($category['data_table']);
            } else if (is_string($category['data_table'])) {
                // Convert delimited string to array then encode, to match plugin_cat_table behavior
                $data_table_value = json_encode(function_exists('create_array') ? create_array($category['data_table']) : $category['data_table']);
            }
        }
        
        // Handle parents_category based on mode
        $parents_category_value = $category['parents_category'];
        if ($is_append_mode) {
            // APPEND MODE: Tất cả categories đều con của shop parent
            $parents_category_value = $category_parent_id;
        }
        
        $category_data = array(
            'category_name' => $category['category_name'],
            'slug_category' => $slug_value,
            'category_description' => $category['category_description'],
            'price' => $category['price'],
            'featured_img' => $category['featured_img'],
            'parents_category' => $parents_category_value,
            'group_repeater' => $group_repeater_value,
            'is_table' => $category['is_table'],
            'is_hidden' => $category['is_hidden'],
            'data_table' => $data_table_value,
            'order' => $order_index,
            'category_inside' => $category['category_inside'],
            'category_inside_order' => $category['category_inside_order']
        );
        
        // Add ID from CRM if available
        if (isset($category['id'])) {
            $category_data['id'] = $category['id'];
        }
        
        // Insert based on mode
        if ($is_append_mode) {
            // APPEND MODE: Sử dụng AUTO_INCREMENT
            $insert_result = $wpdb->insert($table_name, $category_data);
        } else {
            // REPLACE MODE: Sử dụng ID từ CRM
            $insert_result = insert_category($category_data, true); // true = use CRM ID
        }
        
        if ($wpdb->last_error) {
            $error_count++;
        } else {
            $inserted_id = ($is_append_mode) ? $wpdb->insert_id : $insert_result;
            $inserted_count++;
        }
        $order_index++;
    }
    
    return ($error_count === 0);
}


    // Only process if this is a POST request with CSV file
    if($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_file_cat'])) {
        // NEW: Use CRM import for all CSV processing
        
        // Check if CRM connection is active
        if (!class_exists('MAC_Core\CRM_API_Manager') || !MAC_Core\CRM_API_Manager::get_instance()->is_license_valid()) {
            echo '<div class="notice notice-error"><p>CRM connection not available. Please check your CRM license and connection.</p></div>';
        } else {
            // Use CRM import for replace mode
            try {
                $ok = importCSV($_FILES['csv_file_cat'], 0, '');
                if ($ok) {
                    // Success → go to mac-cat-menu
                    echo '<script>window.location.href = "' . admin_url('admin.php?page=mac-cat-menu') . '";</script>';
                    echo '<div class="notice notice-success"><p>CSV processed successfully via CRM! Redirecting to Menu...</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>CRM import failed or no data applied. Please check logs.</p></div>';
                }
            } catch (Exception $e) {
                echo '<div class="notice notice-error"><p>CRM import failed: ' . esc_html($e->getMessage()) . '</p></div>';
            }
        }
    }elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_file_cat_append']) && !empty($_REQUEST['name_shop'])) {
    $shopName = trim($_REQUEST['name_shop']);
    
            // Check if CRM connection is active
        if (!class_exists('MAC_Core\CRM_API_Manager') || !MAC_Core\CRM_API_Manager::get_instance()->is_license_valid()) {
            echo '<div class="notice notice-error"><p>CRM connection not available. Please check your CRM license and connection.</p></div>';
        } else {
        // Use CRM import for append mode
        
        // Create parent category first (like old logic)
        $parentID = insert_category([
            'category_name' => $shopName,
            'slug_category' => create_slug($shopName),
            'category_description' => '',
            'price' => '',
            'featured_img' => '',
            'parents_category' => 0,
            'group_repeater' => [],
            'is_table' => 0,
            'is_hidden' => 0,
            'data_table' => '',
            'order' => '',
            'category_inside' => '',
            'category_inside_order' => NULL
        ], false); // false = use AUTO_INCREMENT for shop parent
        
        if ($parentID) {
            try {
                $ok = importCSV($_FILES['csv_file_cat_append'], $parentID, $shopName);
                if ($ok) {
                    echo '<script>window.location.href = "' . admin_url('admin.php?page=mac-cat-menu') . '";</script>';
                    echo '<div class="notice notice-success"><p>CSV append processed successfully via CRM! Redirecting to Menu...</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>CRM append failed or no data applied. Please check logs.</p></div>';
                }
            } catch (Exception $e) {
                echo '<div class="notice notice-error"><p>CRM append failed: ' . esc_html($e->getMessage()) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Failed to create parent category for append mode.</p></div>';
        }
    }
}
function insert_category($data, $use_crm_id = false) {
    global $wpdb;
    $cattablename = $wpdb->prefix . "mac_cat_menu";
    
    // Chuẩn bị dữ liệu cơ bản
    $category_data = [
        'category_name' => $data['category_name'],
        'slug_category' => $data['slug_category'],
        'category_description' => $data['category_description'],
        'price' => $data['price'],
        'featured_img' => $data['featured_img'],
        'parents_category' => $data['parents_category'],
        'group_repeater' => maybe_serialize($data['group_repeater']),
        'is_table' => $data['is_table'],
        'is_hidden' => $data['is_hidden'],
        'data_table' => $data['data_table'],
        'order' => $data['order'],
        'category_inside' => $data['category_inside'],
        'category_inside_order' => $data['category_inside_order']
    ];
    
    // Nếu sử dụng ID từ CRM và có ID
    if ($use_crm_id && isset($data['id'])) {
        // INSERT với ID cụ thể từ CRM
        $sql = "INSERT INTO $cattablename SET ";
        $sql_parts = [];
        $sql_parts[] = "`id` = " . intval($data['id']);
        
        foreach ($category_data as $key => $value) {
            if ($value !== null && $value !== '') {
                // Đặt backticks cho các từ khóa SQL
                $escaped_key = "`" . $key . "`";
                $sql_parts[] = "$escaped_key = " . $wpdb->prepare('%s', $value);
            }
        }
        
        $sql .= implode(', ', $sql_parts);
        $result = $wpdb->query($sql);
        
        if ($result !== false) {
            return $data['id']; // Trả về ID từ CRM
        } else {
            // Log database error
            log_activity('database_error', 'Failed to insert category with CRM ID', 'mac_cat_menu', 0, $wpdb->last_error);
            return false;
        }
    } else {
        // Sử dụng AUTO_INCREMENT như cũ
        $result = $wpdb->insert($cattablename, $category_data);
        
        if ($result !== false) {
            return $wpdb->insert_id;
        } else {
            // Log database error
            log_activity('database_error', 'Failed to insert category with auto-increment', 'mac_cat_menu', 0, $wpdb->last_error);
            return false;
        }
    }
}

// Add JavaScript for Import Menu Processing
add_action('admin_footer', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'mac-menu') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Import mode selection
            $('.mac-selection-import-mode').on('change', function() {
                var mode = $(this).val();
                if (mode === 'replace') {
                    $('.mac-data-replace').show();
                    $('.mac-data-append').hide();
                } else if (mode === 'append') {
                    $('.mac-data-replace').hide();
                    $('.mac-data-append').show();
                } else {
                    $('.mac-data-replace').hide();
                    $('.mac-data-append').hide();
                }
            });
            
            // Delete confirmation for replace mode
            $('.btn-delete-menu').on('click', function(e) {
                e.preventDefault();
                console.log('Submit button clicked');
                var form = $(this).closest('form');
                // Ensure hidden input has table value to trigger confirmation
                var $hidden = $('#input-delete-data');
                var tableName = $hidden.data('table');
                if ($hidden.val() === '' && typeof tableName !== 'undefined') {
                    $hidden.val(tableName);
                }
                var deleteData = $hidden.val();
                console.log('Delete data:', deleteData);
                
                if (deleteData) {
                    console.log('Showing confirmation dialog');
                    $('#overlay').show();
                    $('#confirmDialog').show();
                    
                    $('#confirmOk').off('click').on('click', function() {
                        console.log('OK clicked - submitting form');
                        $('#overlay').hide();
                        $('#confirmDialog').hide();
                        form.submit();
                    });
                    
                    $('#confirmCancel').off('click').on('click', function() {
                        console.log('Cancel clicked - hiding dialog');
                        $('#overlay').hide();
                        $('#confirmDialog').hide();
                    });
                } else {
                    console.log('No delete data - submitting form directly');
                    form.submit();
                }
            });
            
            // Show success/error messages
            <?php if (isset($_GET['csv_imported']) && $_GET['csv_imported'] === 'success'): ?>
            alert('CSV processed successfully via CRM! Menu has been updated.');
            <?php endif; ?>
        });
        </script>
        <?php
    }
});
?>