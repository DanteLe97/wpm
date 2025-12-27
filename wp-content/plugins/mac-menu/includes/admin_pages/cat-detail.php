<?php
$statusDomain = !empty(get_option('mac_domain_valid_status')) ? get_option('mac_domain_valid_status') : "0" ;

if ( empty($statusDomain) || ($statusDomain != 'activate' && $statusDomain != 'deactivate' )):
    mac_redirect('admin.php?page=mac-menu');
endif;
$id_category = isset( $_GET['id'] ) ? $_GET['id'] : "new";
$objmacMenu = new macMenu();
if( isset($id_category) && $id_category != 'new' ){
    $itemCatMenu = $objmacMenu->find_cat_menu($id_category);
}else{
    $itemCatMenu = array();
}  
$lastId = $wpdb->insert_id;

$idDelete = isset( $_POST['formIdDeledte'] ) ? $_POST['formIdDeledte'] : '';

if($idDelete != '') {
    $objmacMenu->destroy_Cat($idDelete);
    mac_redirect('admin.php?page=mac-cat-menu&id='.$id_category);
    exit();
}else {
    if( isset( $_POST['submit-form'] )) {
        // print_r( wp_unslash($_POST) );
        // die();
        check_admin_referer( 'mac-update_id_item');
        // Người dùng đang lưu
        $arrayForm_cat_id = [
            'id' => [
                '0' => "new"
            ]
        ];
        // FIX: Properly handle form_cat for child categories
        if (isset($_REQUEST['form_cat']) && is_array($_REQUEST['form_cat']) && isset($_REQUEST['form_cat']['id'])) {
            $form_cat_id = $_REQUEST['form_cat'];
        } else {
            // For child categories, we need to get the actual ID from URL
            $current_id = isset($_GET['id']) ? $_GET['id'] : 'new';
            if ($current_id !== 'new') {
                $form_cat_id = [
                    'id' => [
                        '0' => $current_id
                    ]
                ];
            } else {
                $form_cat_id = $arrayForm_cat_id;
            }
        }
        
        // Lấy mac-html-old option để kiểm tra
        $mac_html_old = !empty(get_option('mac_html_old')) ? get_option('mac_html_old') : "0";
        $current_id = isset($_GET['id']) ? $_GET['id'] : 'new';
        
        foreach ($form_cat_id['id'] as $item_id ){
            if($item_id == ''):
                $item_id = 'new';
            endif;
            
            // Kiểm tra xem có phải child category không (không phải parent đang edit)
            $is_child_category = ($item_id !== 'new' && $item_id !== $current_id);
            
            // Lấy dữ liệu hiện tại từ database nếu là child category
            $current_category_data = null;
            $category_inside_from_db = '0';
            
            if ($is_child_category) {
                $objmacMenu = new macMenu();
                $current_category_data = $objmacMenu->find_cat_menu($item_id);
                if (!empty($current_category_data) && isset($current_category_data[0])) {
                    $current_category_data = $current_category_data[0];
                    $category_inside_from_db = isset($current_category_data->category_inside) ? $current_category_data->category_inside : '0';
                }
            }
            
            // Kiểm tra điều kiện: mac-html-old === 1 VÀ category_inside === 1
            // Nếu cả 2 đều === 1 và là child category, chỉ update category_inside_order
            $category_inside = isset($_REQUEST['form_'.$item_id.'_category_inside']) ? $_REQUEST['form_'.$item_id.'_category_inside'] : ($current_category_data ? $category_inside_from_db : '1');
            $should_only_update_position = ($mac_html_old === "1" || $mac_html_old === 1) && 
                                          ($category_inside === "1" || $category_inside === 1) && 
                                          $is_child_category;
            
            error_log('item_id: ' . $item_id . ' - is_child: ' . ($is_child_category ? 'YES' : 'NO') . ' - should_only_update_position: ' . ($should_only_update_position ? 'YES' : 'NO'));
            
            if ($should_only_update_position) {
                // CHỈ update category_inside_order, lấy tất cả các trường khác từ database
                $category_inside_order = isset($_REQUEST['form_'.$item_id.'_category_inside_order']) ? $_REQUEST['form_'.$item_id.'_category_inside_order'] : ($current_category_data ? $current_category_data->category_inside_order : 'new');
                
                // Lấy tất cả dữ liệu từ database
                $category_name = $current_category_data ? $current_category_data->category_name : '';
                $slug_category = $current_category_data ? $current_category_data->slug_category : '';
                $category_description = $current_category_data ? $current_category_data->category_description : '';
                $price = $current_category_data ? $current_category_data->price : '';
                $featured_img = $current_category_data ? $current_category_data->featured_img : '';
                $parents_category = $current_category_data ? $current_category_data->parents_category : '0';
                $order = $current_category_data ? $current_category_data->order : '';
                $group_repeater = $current_category_data ? $current_category_data->group_repeater : '[]';
                $is_hidden = $current_category_data ? $current_category_data->is_hidden : '0';
                $is_table = $current_category_data ? $current_category_data->is_table : '0';
                $table_col = $current_category_data ? $current_category_data->data_table : '[]';
                
                // Giữ nguyên category_inside từ database
                $category_inside = $category_inside_from_db;
                
                // table_col và group_repeater đã là JSON string từ database, không cần xử lý thêm
            } else {
                // Update tất cả các trường từ form (logic bình thường)
                $category_name = isset($_REQUEST['form_'.$item_id.'_category_name']) ? stripslashes($_REQUEST['form_'.$item_id.'_category_name']) : '';
                $slug_category = isset($_REQUEST['form_'.$item_id.'_slug_category']) ? $_REQUEST['form_'.$item_id.'_slug_category'] : '';
                $category_description = isset($_REQUEST['form_'.$item_id.'_category_description']) ? stripslashes($_REQUEST['form_'.$item_id.'_category_description']) : '';
                $price = isset($_REQUEST['form_'.$item_id.'_price']) ? stripslashes($_REQUEST['form_'.$item_id.'_price']) : '';
                $featured_img = isset($_REQUEST['form_'.$item_id.'_featured_img']) ? $_REQUEST['form_'.$item_id.'_featured_img'] : '';
                $parents_category  = isset($_REQUEST['form_'.$item_id.'_parents_category']) ? $_REQUEST['form_'.$item_id.'_parents_category'] : '0';
                $order  = isset($_REQUEST['form_'.$item_id.'_order']) ? $_REQUEST['form_'.$item_id.'_order'] : '';
                $group_repeater = isset($_REQUEST['form_'.$item_id.'_group-repeater']) ? $_REQUEST['form_'.$item_id.'_group-repeater'] : [];
                /**/
                array_walk_recursive($group_repeater, 'remove_slashes_from_array');
                $group_repeater = json_encode($group_repeater);
                $is_hidden = isset($_REQUEST['form_'.$item_id.'_is_hidden']) ? $_REQUEST['form_'.$item_id.'_is_hidden'] : '0';
                $is_table = isset($_REQUEST['form_'.$item_id.'_is_table']) ? $_REQUEST['form_'.$item_id.'_is_table'] : '0';
                $table_col = isset($_REQUEST['form_'.$item_id.'_table_col']) ? $_REQUEST['form_'.$item_id.'_table_col'] : [];
                $category_inside = isset($_REQUEST['form_'.$item_id.'_category_inside']) ? $_REQUEST['form_'.$item_id.'_category_inside'] : '1';
                $category_inside_order = isset($_REQUEST['form_'.$item_id.'_category_inside_order']) ? $_REQUEST['form_'.$item_id.'_category_inside_order'] : 'new';
                
                // Xử lý table_col cho trường hợp update tất cả
                array_walk_recursive($table_col, 'remove_slashes_from_array');
                $table_col = json_encode($table_col);

            }

            // Chỉ xử lý slug nếu không phải trường hợp chỉ update position
            if (!$should_only_update_position && empty($slug_category)):
                $slug_category = create_slug($category_name);
            endif;
            include_once 'cat-configs.php';
            $catConfigSettings = array();
            /** chuyền dữ liệu sang cat-config */
            $catConfigSettings['id_category'] = $item_id;
            $catConfigSettings['category_name'] = $category_name;
            $catConfigSettings['slug_category'] = $slug_category;
            $catConfigSettings['category_description'] = $category_description;
            $catConfigSettings['price'] = $price;
            $catConfigSettings['featured_img'] = $featured_img;
            $catConfigSettings['parents_category'] = $parents_category;
            $catConfigSettings['order'] = $order;
            $catConfigSettings['group_repeater'] = $group_repeater;
            $catConfigSettings['is_hidden'] = $is_hidden;
            $catConfigSettings['is_table'] = $is_table;
            $catConfigSettings['table_col'] = $table_col;
            $catConfigSettings['category_inside'] = $category_inside;
            $catConfigSettings['category_inside_order'] = $category_inside_order;
            $renderCatConfigSettings = new Mac_Cat_Config_Settings;
            $renderCatConfigSettings->render($catConfigSettings);
        }
        mac_redirect('admin.php?page=mac-cat-menu&id='.$id_category);
        exit();
    }
}
include_once 'catHTML.php';

$catConfigs = array();
$catConfigs['id_category'] = $id_category;
$renderCatHTML = new Mac_Cat_HTML;

?>
<div class="wrap mac-dashboard">
    <h1 class="wp-heading-inline"></h1>
    <form id="posts-filter" class="mac-form-menu" method="post">
        <?php wp_nonce_field( 'mac-update_id_item');?>
        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2" style="display: flex;">
                <!-- Left columns -->
                <div id="post-body-content">
                    <!-- Detail -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle ui-sortable-handle"> <?php if($id_category != 'new'){ echo 'Detail Category'; } else{ echo 'New Category'; } ?></h2>
                        </div>
                        <div class="inside is-primary-category"> <?php echo $renderCatHTML->render($catConfigs); ?></div>
                    </div>
                    <?php if($id_category != 'new'):  ?>
                    <!-- list item in category -->
                     
                    <div class="postbox sub-category-list">
                        <div class="postbox-header">
                            <h2 class="hndle">List Category Child</h2>
                        </div>
                        <div class="inside is-child-category">
                            <?php 
                            echo $renderCatHTML->list_cat_child_in_cat($id_category);
                            ?>
                            <div class="add-cat-child">
                                <a href="admin.php?page=mac-cat-menu&id=new&id_child=<?= isset($itemCatMenu[0]->id) ? $itemCatMenu[0]->id : "" ; ?>" >Add Category Child</a>
                            </div>
                        </div><!-- inside -->
                    </div>
                    <!-- list item in category -->
                     <?php endif; ?>
                </div>
                <!-- Right columns -->
                <div id="postbox-container-1">
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle">Action</h2>
                        </div>
                        <div class="inside">
                            <input type="submit" name="submit-form" id="doaction" class="button action mac-btn-save-chages" value="Save Changes">
                            
                            
                            <?php if (isset($itemCatMenu[0]->parents_category) && $itemCatMenu[0]->parents_category != 0 ): ?>
                            <div class="backto-parents-category">
                                <h3></h3>
                                <div class="backto-parents-category__inner">
                                    <a href="admin.php?page=mac-cat-menu&id=<?= isset($itemCatMenu[0]->parents_category) ? $itemCatMenu[0]->parents_category : "" ; ?>" >Back to Parents Category</a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div><!-- .postbox -->
                </div>

            </div>
        </div>
    </form>
</div>
<?php 
    
?>