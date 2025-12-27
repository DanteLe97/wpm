<?php
if (!class_exists('Render_Module')) {
    class Render_Module
    {
        function render($moduleInfo)
        {
            $render_modules = '';
            $id_category = isset($moduleInfo['id_category']) ? $moduleInfo['id_category'] : array('all');
            $limit_item = isset($moduleInfo['limit_list_item']) ? $moduleInfo['limit_list_item'] : '';

            $currentCategory = isset($moduleInfo['is_current_category']) ? $moduleInfo['is_current_category'] : '';
            
            /**  call data menu */
            $idPage = get_the_ID();
            $objmacMenu = new macMenu();
            $namesCategoryInPage = get_post_meta($idPage, '_custom_meta_key', true);

            if ($currentCategory == 'on' && !empty($namesCategoryInPage)) {
                $arrayCategoryInPage = explode(',', $namesCategoryInPage);
                $arrayCategoryInPage = array_map('trim', $arrayCategoryInPage);
                $newArrayCategoryIds = [];
                foreach ($arrayCategoryInPage as $itemCat) {
                    $resultsItem = $objmacMenu->find_cat_menu_by_name($itemCat);
                    if (isset($resultsItem[0]->id)) {
                        $newArrayCategoryIds[] = $resultsItem[0]->id;
                    }
                }
                $results = '';
                if (isset($newArrayCategoryIds) && !empty($newArrayCategoryIds)) {
                    $results = $objmacMenu->find_cat_menu_all_child_cats($newArrayCategoryIds);
                }
                if (empty($results)) {
                    $results = array();
                }
                $tree = $this->buildTree($results);
                $render_modules .= $this->htmlModuleMenu($tree, $moduleInfo, 0, 0);
                $childCategorySelectIndex = 1;
                foreach ($arrayCategoryInPage as $itemCat) {
                    $resultsItemcat = $objmacMenu->find_cat_menu_by_name($itemCat);
                    if ($resultsItemcat[0]->parents_category != 0):
                        $render_modules .= $this->htmlModuleMenu($resultsItemcat, $moduleInfo, 0, 0, $childCategorySelectIndex);
                        $childCategorySelectIndex++;
                    endif;
                }
            } else {
                if (in_array('all', $id_category)) {
                    $results = $objmacMenu->all_cat();
                    $tree = $this->buildTree($results);
                    $render_modules .= $this->htmlModuleMenu($tree, $moduleInfo, 0, 0);
                } else {
                    $results = $objmacMenu->find_cat_menu_all_child_cats($id_category);
                    $tree = $this->buildTree($results);
                    $render_modules .= $this->htmlModuleMenu($tree, $moduleInfo, 0, 0);
                    $childCategorySelectIndex = 1;
                    foreach ($id_category as $itemCat) {
                        $resultsItemcat = $objmacMenu->find_cat_menu($itemCat);
                        if ($resultsItemcat[0]->parents_category != 0):
                            $render_modules .= $this->htmlModuleMenu($resultsItemcat, $moduleInfo, 0, 0, $childCategorySelectIndex);
                            $childCategorySelectIndex++;
                        endif;
                    }
                }
            }


            return $render_modules;
        }

        public function htmlModuleMenu($tree, $moduleInfo, $parents_category = 0, $indexChildWrap = 0, $childCategorySelectIndex = null, $category_inside = 0)
        {
            $objmacMenu = new macMenu();
            $MenuHTML = new Mac_Category_Menu;
            $MenuTableHTML = new Mac_Category_Menu_Table;
            $id_category = isset($moduleInfo['id_category']) ? $moduleInfo['id_category'] : array('all');
            $limit_item = isset($moduleInfo['limit_list_item']) ? $moduleInfo['limit_list_item'] : '';

            /** layout custom */
            $custom_layout = isset($moduleInfo['cat_menu_is_custom_layout']) ? $moduleInfo['cat_menu_is_custom_layout'] : '';
            $render_items = isset($moduleInfo['cat_menu_render_items']) ? $moduleInfo['cat_menu_render_items'] : '';
            $section_category = isset($moduleInfo['id_section_category']) ? $moduleInfo['id_section_category'] : '';
            $section_category_item = isset($moduleInfo['id_section_category_item']) ? $moduleInfo['id_section_category_item'] : '';

            /** Cat Menu Basic */
            $is_img = isset($moduleInfo['cat_menu_is_img']) ? $moduleInfo['cat_menu_is_img'] : '';
            $is_description = isset($moduleInfo['cat_menu_is_description']) ? $moduleInfo['cat_menu_is_description'] : '';
            $is_price = isset($moduleInfo['cat_menu_is_price']) ? $moduleInfo['cat_menu_is_price'] : '';
            $menu_item_is_img = isset($moduleInfo['cat_menu_item_is_img']) ? $moduleInfo['cat_menu_item_is_img'] : '';
            $menu_item_is_description = isset($moduleInfo['cat_menu_item_is_description']) ? $moduleInfo['cat_menu_item_is_description'] : '';
            $menu_item_is_price = isset($moduleInfo['cat_menu_item_is_price']) ? $moduleInfo['cat_menu_item_is_price'] : '';
            /** Cat Menu Table */
            $table_is_img = isset($moduleInfo['cat_menu_table_is_img']) ? $moduleInfo['cat_menu_table_is_img'] : '';
            $table_is_description = isset($moduleInfo['cat_menu_table_is_description']) ? $moduleInfo['cat_menu_table_is_description'] : '';
            $table_is_price = isset($moduleInfo['cat_menu_table_is_price']) ? $moduleInfo['cat_menu_table_is_price'] : '';
            $table_menu_is_heading = isset($moduleInfo['cat_menu_table_is_heading']) ? $moduleInfo['cat_menu_table_is_heading'] : '';
            $table_menu_item_is_img = isset($moduleInfo['cat_menu_table_item_is_img']) ? $moduleInfo['cat_menu_table_item_is_img'] : '';
            $table_menu_item_is_description = isset($moduleInfo['cat_menu_table_item_is_description']) ? $moduleInfo['cat_menu_table_item_is_description'] : '';
            $table_menu_item_is_price = isset($moduleInfo['cat_menu_table_item_is_price']) ? $moduleInfo['cat_menu_table_item_is_price'] : '';

            $html = '';
            $index_cat_child_wrap = 0;
            $catIndex = 0;
            if (isset($childCategorySelectIndex) && $childCategorySelectIndex != null):
                $catIndex = 'select-child-' . $childCategorySelectIndex;
            endif;

            $mac_menu_dp = get_option('mac_menu_dp');
            if (empty($mac_menu_dp)) {
                $mac_menu_dp = 0;
            }
            foreach ($tree as $branch) {

                $MenuAttr = array(
                    'id' => $branch->id,
                    'limit_item' => $limit_item,
                    'cat_menu_is_custom_layout' => $custom_layout,
                    'cat_menu_render_items' => $render_items,
                    'id_section_category' => $section_category,
                    'id_section_category_item' => $section_category_item,
                    'cat_menu_is_img' => $is_img,
                    'cat_menu_is_description' => $is_description,
                    'cat_menu_is_price' => $is_price,
                    'cat_menu_item_is_img' => $menu_item_is_img,
                    'cat_menu_item_is_description' => $menu_item_is_description,
                    'cat_menu_item_is_price' => $menu_item_is_price,
                    'is_child' => 0,
                    'is_parents_0' => 0,
                    'index_child_wrap' => $indexChildWrap + 1,
                );
                $MenuTableAttr = array(
                    'id' => $branch->id,
                    'limit_item' => $limit_item,
                    'cat_menu_is_custom_layout' => $custom_layout,
                    'cat_menu_render_items' => $render_items,
                    'id_section_category' => $section_category,
                    'id_section_category_item' => $section_category_item,
                    'cat_menu_table_is_img' => $table_is_img,
                    'cat_menu_table_is_description' => $table_is_description,
                    'cat_menu_table_is_price' => $table_is_price,
                    'cat_menu_table_is_heading' => $table_menu_is_heading,
                    'cat_menu_table_item_is_img' => $table_menu_item_is_img,
                    'cat_menu_table_item_is_description' => $table_menu_item_is_description,
                    'cat_menu_table_item_is_price' => $table_menu_item_is_price,
                    'is_child' => 0,
                    'is_parents_0' => 0,
                    'index_child_wrap' => $indexChildWrap + 1,
                );

                if ($mac_menu_dp == 1 && $branch->is_table == 0) {
                    $MenuTableAttr['class'] = ' show-card-price';
                }
                $index_cat_child = 0;
                if ($parents_category == 0) {
                    if ($branch->is_table == 0){
                        $html .= '<div class="module-category module-category-parents-' . $parents_category . ' module-category-index-' . $catIndex . '">';
                        $MenuAttr['is_parents_0'] = 1;
                        if ($mac_menu_dp == 0) {
                            $html .= $MenuHTML->render($MenuAttr);
                        } else {
                            $html .= $MenuTableHTML->render($MenuTableAttr);
                        }

                    }else{
                        $html .= '<div class="module-category module-category-parents-' . $parents_category . ' module-category-table-style module-category-index-' . $catIndex . '">';
                        $MenuTableAttr['is_parents_0'] = 1;
                        $html .= $MenuTableHTML->render($MenuTableAttr);
                    }

                    if (isset($branch->children) && (empty($custom_layout) || $custom_layout == 'off')) {
                        $html .= $this->htmlModuleMenu($branch->children, $moduleInfo, $branch->id, $indexChildWrap + 1);
                    }

                } elseif ($parents_category != 0 && (empty($custom_layout) || $custom_layout == 'off')) {
                    if($branch->category_inside == 1 && $category_inside == 0){
                        continue;
                    }
                    $html .= '<div class="module-category-child-wrap module-category-child-' . $indexChildWrap . '-wrap">';
                    $childMenuAttr = $MenuAttr;
                    $childMenuAttr['id'] = $branch->id;
                    $childMenuAttr['class'] = ' module-category-child module-category-child-index-' . $index_cat_child;
                    $childMenuAttr['is_child'] = 1;
                    $childMenuAttr['is_parents_0'] = 0;
                    //
                    $childMenuTableAttr = $MenuTableAttr;
                    $childMenuTableAttr['id'] = $branch->id;
                    $childMenuTableAttr['class'] = ' module-category-child module-category-child-table-style module-category-child-index-' . $index_cat_child;
                    $childMenuTableAttr['is_child'] = 1;
                    $childMenuTableAttr['is_parents_0'] = 0;

                    if ($branch->is_table == 0){
                        
                        if ($mac_menu_dp == 0) {
                            $html .= $MenuHTML->render($childMenuAttr);
                        } else {
                            $childMenuTableAttr['class'] .=' show-card-price';
                            $html .= $MenuTableHTML->render($childMenuTableAttr);
                        }
                    }else {
                        
                        $html .= $MenuTableHTML->render($childMenuTableAttr);
                    }

                    if (isset($branch->children) && (empty($custom_layout) || $custom_layout == 'off')) {
                        $html .= '<div class="module-category-child-wrap module-category-child-' . ($indexChildWrap + 1) . '-wrap">';
                        $numberChildWrap = $indexChildWrap + 1;
                        foreach ($branch->children as $item) {
                            
                            $childMenuAttr = $MenuAttr;
                            $childMenuAttr['id'] = $item->id;
                            $childMenuAttr['class'] = ' module-category-child module-category-child-index-' . $index_cat_child;
                            $childMenuAttr['is_child'] = 1;
                            $childMenuAttr['is_parents_0'] = 0;
                            //
                            
                            $childMenuTableAttr = $MenuTableAttr;
                            $childMenuTableAttr['id'] = $item->id;
                            $childMenuTableAttr['class'] = ' module-category-child module-category-child-index-' . $index_cat_child;
                            $childMenuTableAttr['is_child'] = 1;
                            $childMenuTableAttr['is_parents_0'] = 0;

                            if ($item->is_table == 0){
                                if ($mac_menu_dp == 0) {
                                    $html .= $MenuHTML->render($childMenuAttr);
                                } else {
                                    $html .= $MenuTableHTML->render($childMenuTableAttr);
                                }

                            }else{
                                $html .= $MenuTableHTML->render($childMenuTableAttr);
                            }
                            $index_cat_child++;
                            if (!empty($item->children)){
                                $html .= $this->htmlModuleMenu($item->children, $moduleInfo, $item->id, $numberChildWrap + 1);
                            }

                        }
                        $html .= '</div><!-- rank-' . ($indexChildWrap + 1) . ' -->';

                    }
                    $html .= '</div><!-- child-' . $indexChildWrap . ' -->';
                }
                $catIndex++;
                if ($parents_category == 0){
                    $html .= '</div><!-- module-category -->';
                }
            }
            return $html;
        }

        public function buildTree(array $elements, $parentId = 0)
        {
			
            $branch = array();
			if(empty($elements)) return $branch;
			if(empty($elements[0])) return $branch;
            foreach ($elements as $element) {
                if ($element->parents_category == $parentId) {
                    $children = $this->buildTree($elements, $element->id);
                    if ($children) {
                        $element->children = $children;
                        foreach ($children as $item) {
                            $element->childrenID[] = $item->id;
                            $element->childrenParents_category[] = $item->parents_category;
                            $element->childrenTable[] = $item->is_table;
                        }
                    }
                    $branch[] = $element;
                }
            }
            return $branch;
        }




    }
}
