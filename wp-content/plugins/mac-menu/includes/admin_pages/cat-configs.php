<?php
if (!class_exists('Mac_Cat_Config_Settings')) {
    class Mac_Cat_Config_Settings {
        public function get_table_columns($table_name) {
            global $wpdb; // nếu là WordPress
            $columns = $wpdb->get_col("DESC {$table_name}", 0);
            return $columns;
        }
        function render($catAttr) {
            // print_r($catAttr);
            // die();
			$macHTMLOld = !empty(get_option('mac_html_old')) ? get_option('mac_html_old') : "0" ;

            $id_category          = $catAttr['id_category'];
            $category_name        = $catAttr['category_name'];
            $slug_category        = $catAttr['slug_category'];
            $category_description = $catAttr['category_description'];
            $price                = $catAttr['price'];
            $featured_img         = $catAttr['featured_img'];
            $parents_category     = $catAttr['parents_category'];
            $order                = $catAttr['order'];
            $group_repeater       = $catAttr['group_repeater'];
            $is_hidden            = $catAttr['is_hidden'];
            $is_table             = $catAttr['is_table'];
            $table_col            = $catAttr['table_col'];
            $category_inside       = $catAttr['category_inside'];
            $category_inside_order = $catAttr['category_inside_order'];
            
            if($table_col) {
                $tableColData = json_decode($table_col, true);
                $resultTableCol = array();
                foreach ($tableColData as $item) {
                    $resultTableCol[] = $item;
                    
                }
                $resultTableColJson = json_encode($resultTableCol);
                $table_col = $resultTableColJson;
            }else {
                $table_col = [];
            }

            if($group_repeater) {
                $repeaterData = json_decode($group_repeater, true);
                $resultRepeate = array();
                foreach ($repeaterData as $item) {
                    if (!empty($item['name'])) {
                        $resultRepeate[] = $item;
                    }
                }
                $resultRepeateJson = json_encode($resultRepeate);
                $group_repeater = $resultRepeateJson;
            }else {
                $group_repeater = [];
            }

            $objmacMenu = new macMenu();
            if($id_category == 'new' || $id_category == '' ) {
                
                $id_category_parent = isset( $_GET['id_child'] ) ? $_GET['id_child'] : "0";
                $objmacMenu->save_cat([
                    'category_name'        => $category_name,
                    'slug_category'        => $slug_category,
                    'category_description' => $category_description,
                    'price'                => $price,
                    'featured_img'         => $featured_img,
                    'parents_category'     => $id_category_parent,
                    'order'                => $order,
                    'group_repeater'       => $group_repeater,
                    'is_hidden'             => $is_hidden,
                    'is_table'             => $is_table,
                    'data_table'           => $table_col,
                    'category_inside'      => $category_inside,
                    'category_inside_order'=> $category_inside_order
                    
                ]);
                if($id_category_parent != ''){
                    mac_redirect('admin.php?page=mac-cat-menu&id='.$id_category_parent);
                }else{
                    mac_redirect('admin.php?page=mac-cat-menu');
                }
                
            }else {
                if( isset($id_category) ){
                    // Lấy dữ liệu hiện tại từ database
                    $current_data = $objmacMenu->find_cat_menu($id_category);
                    
                    // Debug logging
                    error_log('MAC Menu Debug - ID: ' . $id_category);
                    error_log('MAC Menu Debug - Current data: ' . print_r($current_data[0] ?? 'NO DATA', true));
                    error_log('MAC Menu Debug - Form data - category_name: ' . $category_name);
                    error_log('MAC Menu Debug - Form data - parents_category: ' . $parents_category);
                    error_log('MAC Menu Debug - GET id: ' . ($_GET['id'] ?? 'NOT SET'));
                    
                    // CRITICAL: Validate that we have valid data before proceeding
                    if (empty($current_data) || !isset($current_data[0])) {
                        error_log('MAC Menu CRITICAL ERROR: No current data found for ID: ' . $id_category);
                        return; // Stop processing if no current data
                    }
                    
                    // PROTECTION: For child categories, only allow limited updates
                    $is_child_category = ($current_data[0]->parents_category != 0);
                    $is_editing_self = ($_GET['id'] == $id_category);
                    $is_parent_category = ($current_data[0]->parents_category == 0);
                    
                    // Chỉ update full data khi:
                    // 1. Là parent category (parents_category == 0)
                    // 2. Hoặc đang edit chính category đó (GET id == category id)
                    // 3. Hoặc là child category nhưng có category_inside = 0 (cho phép edit full)
                    $should_update_full = (
                        $is_parent_category ||  
                        $is_editing_self ||
                        (isset($current_data[0]->category_inside) && $current_data[0]->category_inside == 0)
                    );
                    
                    
                    // ADDITIONAL PROTECTION: If it's a child category and we're not editing it directly,
                    // and the form data is empty, skip the update entirely
                    if ($is_child_category && !$is_editing_self && empty($category_name)) {
                        error_log('MAC Menu PROTECTION: Skipping update for child category with empty data - ID: ' . $id_category);
                        return; // Skip update to prevent data loss
                    }
                    
                    if ($should_update_full)
                    {
                        $cattablename = $objmacMenu->get_cat_menu_name();
                        $valid_columns = $this->get_table_columns($cattablename);

                        $datanew = [
                            'category_name'        => $category_name,
                            'slug_category'        => $slug_category,
                            'category_description' => $category_description,
                            'price'                => $price,
                            'featured_img'         => $featured_img,
                            'parents_category'     => $parents_category,
                            'group_repeater'       => $group_repeater,
                            'order'                => $order,
                            'is_hidden'            => $is_hidden,
                            'is_table'             => $is_table,
                            'data_table'           => $table_col,
                            'category_inside'      => $category_inside,
                            'category_inside_order'=> $category_inside_order
                        ];

                        // Lọc ra những key nằm trong danh sách cột thực tế
                        $filtered_data = array_intersect_key($datanew, array_flip($valid_columns));

                        // Validation: Kiểm tra dữ liệu quan trọng không được rỗng
                        $is_valid_data = true;
                        if (empty($category_name)) {
                            $is_valid_data = false;
                            error_log('MAC Menu Error: category_name is empty for ID: ' . $id_category);
                        }
                        
                        // BACKUP: Create backup before update for child categories
                        if ($is_child_category && $is_valid_data) {
                            $backup_data = [
                                'id' => $id_category,
                                'category_name' => $current_data[0]->category_name,
                                'slug_category' => $current_data[0]->slug_category,
                                'category_description' => $current_data[0]->category_description,
                                'price' => $current_data[0]->price,
                                'featured_img' => $current_data[0]->featured_img,
                                'parents_category' => $current_data[0]->parents_category,
                                'group_repeater' => $current_data[0]->group_repeater,
                                'order' => $current_data[0]->order,
                                'is_hidden' => $current_data[0]->is_hidden,
                                'is_table' => $current_data[0]->is_table,
                                'data_table' => $current_data[0]->data_table,
                                'category_inside' => $current_data[0]->category_inside,
                                'category_inside_order' => $current_data[0]->category_inside_order,
                                'backup_timestamp' => current_time('mysql')
                            ];
                            
                            // Store backup in the same table with a backup flag
                            $backup_data_json = json_encode($backup_data);
                            $objmacMenu->update_cat_backup($id_category, $backup_data_json);
                            error_log('MAC Menu Backup created for ID: ' . $id_category);
                        }
                        
                        // Chỉ update nếu dữ liệu hợp lệ và có thay đổi thực sự
                        if ($is_valid_data) {
                            $objmacMenu->update_cat($id_category, $filtered_data);
                            error_log('MAC Menu Update successful for ID: ' . $id_category);
                        } else {
                            error_log('MAC Menu Error: Skipping update due to invalid data for ID: ' . $id_category);
                        }
                        
                    }else{
                        // Tạo mảng chứa các trường cần update
                        $update_data = [];
                        if($current_data[0]->category_inside_order !== $category_inside_order) {
                            $update_data['category_inside_order'] = $category_inside_order;
                        }
                        //Chỉ update nếu có dữ liệu thay đổi
                        if(!empty($update_data)) {
                            $objmacMenu->update_cat_inside($id_category, $update_data);
                        }
                    }
                    
                }
            }
        }
        
    }
}