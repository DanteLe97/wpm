<?php
if (!class_exists('Mac_Category_Menu_Table')) {
    class Mac_Category_Menu_Table
    {
        
        private function DuralPrice($mac_menu_dp_value = 0, $mac_menu_dp_sw = 0, $cashs = '', $cards = '')
        {
						
					
            // Callback xử lý thay thế chỉ số có ký tự "$" ở trước
            $cards = preg_replace_callback('/\$(\d+(?:\.\d+)?)/', function($matches) use ($mac_menu_dp_value, $mac_menu_dp_sw) {
                $original = (float)$matches[1]; // Lấy số sau dấu $

                // Tính toán lại giá trị card
                if (empty($mac_menu_dp_sw)) {
                    $card = round($original * $mac_menu_dp_value, 2);
                } else {
                    $card = round($original + $mac_menu_dp_value, 2);
                }
// 				echo '<pre>';
// 					print_r($card);
// 					echo '</pre>';
                // Trả về "$" + số sau khi tính
                return '$' . number_format($card, 2, '.', '');
            }, $cards);

            return [
                'cashs' => $cashs,
                'cards' => $cards,
            ];
        }

        function render($catMenuAttr)
        {
            $id = $catMenuAttr['id'];
            $class = isset($catMenuAttr['class']) ? $catMenuAttr['class'] : "";
            $limit_item = isset($catMenuAttr['limit_item']) ? $catMenuAttr['limit_item'] : "";
            $table_is_img = isset($catMenuAttr['cat_menu_table_is_img']) ? $catMenuAttr['cat_menu_table_is_img'] : "";
            $table_is_description = isset($catMenuAttr['cat_menu_table_is_description']) ? $catMenuAttr['cat_menu_table_is_description'] : "";
            $table_is_price = isset($catMenuAttr['cat_menu_table_is_price']) ? $catMenuAttr['cat_menu_table_is_price'] : "";
            $table_is_heading = isset($catMenuAttr['cat_menu_table_is_heading']) ? $catMenuAttr['cat_menu_table_is_heading'] : "";

            $table_menu_item_is_img = isset($catMenuAttr['cat_menu_table_item_is_img']) ? $catMenuAttr['cat_menu_table_item_is_img'] : "";
            $table_menu_item_is_description = isset($catMenuAttr['cat_menu_table_item_is_description']) ? $catMenuAttr['cat_menu_table_item_is_description'] : "";
            $table_menu_item_is_price = isset($catMenuAttr['cat_menu_table_item_is_price']) ? $catMenuAttr['cat_menu_table_item_is_price'] : "";

            $custom_layout = isset($catMenuAttr['cat_menu_is_custom_layout']) ? $catMenuAttr['cat_menu_is_custom_layout'] : '';
            $render_items = isset($catMenuAttr['cat_menu_render_items']) ? $catMenuAttr['cat_menu_render_items'] : '';
            $section_category = isset($catMenuAttr['id_section_category']) ? $catMenuAttr['id_section_category'] : '';
            $section_category_item = isset($catMenuAttr['id_section_category_item']) ? $catMenuAttr['id_section_category_item'] : '';

            $is_child = isset($catMenuAttr['is_child']) ? $catMenuAttr['is_child'] : 0;
            $is_content = isset($catMenuAttr['is_content']) ? $catMenuAttr['is_content'] : 0;
            $is_parents_0 = isset($catMenuAttr['is_parents_0']) ? $catMenuAttr['is_parents_0'] : 0;

            $objmacMenu = new macMenu();
            $isCat = $objmacMenu->find_cat_menu($id);

            $mac_menu_dp = get_option('mac_menu_dp');
            if (empty($mac_menu_dp)) {
                $mac_menu_dp = 0;
            }
            
            $mac_menu_dp_value = get_option('mac_menu_dp_value');
            $mac_menu_dp_value = apply_filters('custom_dp_value', $mac_menu_dp_value, $catMenuAttr['id']);

            if (empty($mac_menu_dp_value)) {
                $mac_menu_dp_value = 0;
            }
            $mac_menu_dp_sw = get_option('mac_menu_dp_sw');
            $mac_menu_dp_sw = apply_filters('custom_dp_sw', $mac_menu_dp_sw, $catMenuAttr['id']);
            if (empty($mac_menu_dp_sw)) {
                $mac_menu_dp_sw = 0;
                $mac_menu_dp_value = 1 + $mac_menu_dp_value / 100;
            }
            ob_start();
            ?>
            <?php
            if ($custom_layout == 'on' && $section_category != 'default') {
                ?>
                <div id="<?= isset($isCat[0]->slug_category) ? $isCat[0]->slug_category : '' ?>"
                    class="module-category__content<?= $class; ?>">
                    <?php
                    $template_id = $section_category;
                    if (!empty($template_id)) {
                        $custom_array = [
                            'id' => $id,
                            'limit_item' => $limit_item,
                            'table_is_heading' => $table_is_heading,
                            'table_is_img' => $table_menu_item_is_img,
                            'table_is_description' => $table_menu_item_is_description,
                            'table_is_price' => $table_menu_item_is_price
                        ];
                        set_custom_array($custom_array);
                        
                        // Reset custom_index để render category info
                        set_custom_index(null);
                        
                        // Lấy group_repeater từ category để loop qua items
                        $json = $isCat[0]->group_repeater ?? "[]";
                        $data = json_decode($json, true);
                        
                        // Nếu switch render_items = 'on' VÀ có items → render category 1 lần, items nhiều lần
                        if ($render_items == 'on' && is_array($data) && !empty($data)) {
                            // Render category info 1 lần trước khi loop items
                            //echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($template_id);
                            
                            // Loop qua từng item và render template cho mỗi item
                            $item_index = 0;
                            foreach ($data as $item) {
                                // Kiểm tra limit_item nếu có
                                if ($limit_item != '' && $limit_item != '0' && $item_index >= intval($limit_item)) {
                                    break;
                                }
                                
                                // Set custom_index cho item hiện tại (bắt đầu từ 1)
                                set_custom_index($item_index + 1);
                                
                                // Render template cho item này (chỉ render item info, không render category info)
                                echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($template_id);
                                
                                $item_index++;
                            }
                        } else {
                            // Switch render_items = 'off' HOẶC không có items → chỉ render 1 lần (cho category)
                            echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($template_id);
                        }
                    }
                    ?>
                </div> <!-- .module-category__content -->
                <?php
            } else {
                
                if (!($is_content == 1 && $is_parents_0 == 1)) { ?>
                    <div id="<?= isset($isCat[0]->slug_category) ? $isCat[0]->slug_category : '' ?>"
                        class="module-category__content module-category-table-style__content<?= $class; ?>">

                        <div class="module-category__text">
                            <div class="module-category__head">
                                <?php if (isset($isCat[0]->category_name) && !empty($isCat[0]->category_name)) { ?>
                                    <span class="module-category__name"><?= $isCat[0]->category_name ?></span>
                                <?php } ?>
                                <?php if (isset($isCat[0]->price) && !empty($isCat[0]->price) && $table_is_price != 'off' && $mac_menu_dp_sw == 1) { ?>
                                    <div class="module-category__price"><?= $isCat[0]->price ?></div>
                                <?php } ?>
                            </div><!-- .module-category__head -->
                            <?php if (isset($isCat[0]->category_description) && !empty($isCat[0]->category_description) && $table_is_description != 'off') { ?>
                                <div class="module-category__description">
                                <?php if (isset($isCat[0]->price) && !empty($isCat[0]->price) && $table_is_price != 'off' && $mac_menu_dp_sw == 0) {
                                    $arrayCatPrice = $this->DuralPrice($mac_menu_dp_value, $mac_menu_dp_sw, $isCat[0]->price, $isCat[0]->price);
                                    echo '<div class="dualprice-category">cash: '.$arrayCatPrice['cashs'].' /card: '.$arrayCatPrice['cards'].'</div>';
                                } ?>
                                <?= $isCat[0]->category_description ?>
                                </div><!-- .module-category__description -->
                            <?php } ?>
                        </div><!-- .module-category__text -->
                        <?php if (isset($isCat[0]->featured_img) && !empty($isCat[0]->featured_img) && $table_is_img != 'off') { ?>
                            <div class="module-category__img">
                                <?php
                                echo getGalleryFromIds($isCat[0]->featured_img, 'url');
                                ?>
                            </div><!-- .module-category__img -->
                        <?php }
                }
                $json = isset($isCat[0]->group_repeater) ? $isCat[0]->group_repeater : [];
                $data = json_decode($json, true);
                
                // Lấy danh sách category child
                $catItemChild = $objmacMenu->getAllIdCatChildInsideHTML($id);
                $macCatHtml = new Render_Module();
                $category_child_wrap = isset($catMenuAttr['index_child_wrap']) ? $catMenuAttr['index_child_wrap'] : 0;

                
                // Hiển thị heading của table
                $groupRepeater = json_decode($isCat[0]->group_repeater, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($groupRepeater)) {
                    $groupRepeater = array();
                }

                // Kiểm tra an toàn có price trong price-list
                $hasPrice = false;
                foreach ($groupRepeater as $item) {
                    if (is_array($item) && isset($item['price-list']) && is_array($item['price-list'])) {
                        foreach ($item['price-list'] as $p) {
                            if (is_array($p) && !empty($p['price'])) { $hasPrice = true; break 2; }
                            if (!is_array($p) && !empty($p)) { $hasPrice = true; break 2; }
                        }
                    }
                }
                $jsonHeading = isset($isCat[0]->data_table) ? $isCat[0]->data_table : [];
                $dataHeading = json_decode($jsonHeading, true);

                $countHeading = count($dataHeading);
               
                if (empty($data) && (!isset($data[0]) || !is_array($data[0]))) {

                    if($catMenuAttr['is_child'] == 0){
                        foreach($catItemChild as $catChild => $value ) {
                            $childCat = $objmacMenu->find_cat_menu($value);
                            echo $macCatHtml->htmlModuleMenu($childCat, $catMenuAttr, $category_child_wrap, $category_child_wrap, null, 1);
                            // Xử lý các category con của category child
                            $results = $objmacMenu->find_cat_menu_all_child_cats(array($value));
                            $branch = $macCatHtml->buildTree($results, $value);
                            echo $macCatHtml->htmlModuleMenu($branch, $catMenuAttr, $category_child_wrap, $category_child_wrap + 1, null, 1);
                        }
                    }
                    if (!($is_content == 1 && $is_parents_0 == 1)) {
                        echo '</div><!-- module-category-child-table-style -->';
                    }
                    return ob_get_clean();
                }
                
                // Mở table chính
                echo '<div class="module-category-table-wrap" style="width:100%">
                        <table class="module-category-table">
                            <tbody>';

                
                       
                if (!empty($mac_menu_dp)) {
                  
                    if (is_array($dataHeading) && $table_is_heading != 'off' && $hasPrice) {

                        echo '<tr class="module-category__heading">';
                        if ($countHeading > 1 || ($countHeading === 1 && $dataHeading[0] !== '')) {
                            echo '<td rowspan="2"></td>';
                            foreach ($dataHeading as $item) {
                                echo '<td colspan="2">';
                                if (isset($item)) {
                                    echo '' . $item;
                                }
                                echo '</td><td style="display: none;"></td>';
                            }
                            echo '</tr><tr class="module-category__heading"><td style="display: none;"></td>';
                        } else {
                            echo '<td></td>';
                        }
                        if(empty($countHeading)){
                            $countHeading = 1;
                        }
                        
                        for ($i = 0; $i < $countHeading; $i++) {
                            echo '<td><span>Cash</span></td>';
                            echo '<td><span>Card</span></td>';
                        }
                        echo '</tr>';
                    }
                } else {
                    if (is_array($dataHeading) && $table_is_heading != 'off') {
                        echo '<tr class="module-category__heading">
                    <td></td>';
                        foreach ($dataHeading as $item) {
                            echo '<td>';
                            if (isset($item)) {
                                echo '' . $item;
                            }
                            echo '</td>';
                        }
                        echo '</tr>';
                    }
                }
                $tocalPrice = 0;
                $tocalItem = count($catItemChild) + count($data);
                for ($catItem = 0; $catItem <= $tocalItem; $catItem++){
                    $position = 1;
                    
                  
                    foreach($catItemChild as $catChild => $value ) {
                        $childCat = $objmacMenu->find_cat_menu($value);
                        if(!isset($childCat[0]->category_inside_order)){
                            $childCat[0]->category_inside_order = $position;
                        }
                        if ($catItem == $childCat[0]->category_inside_order || $childCat[0]->category_inside_order == 0) {
                           
                            echo '</tbody></table></div><!-- .module-category-table-wrap -->';

                            echo $macCatHtml->htmlModuleMenu($childCat, $catMenuAttr, $category_child_wrap, $category_child_wrap, null, 1);

                            // Xử lý các category con của category child
                            $results = $objmacMenu->find_cat_menu_all_child_cats(array($value));
                            $branch = $macCatHtml->buildTree($results, $value);
                            echo $macCatHtml->htmlModuleMenu($branch, $catMenuAttr, $category_child_wrap, $category_child_wrap + 1, null, 1);
                            // Hiển thị lại heading
                            echo '<div class="module-category-table-wrap" style="width:100%">
                                    <table class="module-category-table">
                                        <tbody>';
                                       
                            if (!empty($mac_menu_dp)) {
                                // Mở lại table mới
                                
                                if (is_array($dataHeading) && $table_is_heading != 'off' && $hasPrice && !empty($groupRepeater)) {
                                    echo '<tr class="module-category__heading">';
                                    if ($countHeading > 1 || ($countHeading === 1 && $dataHeading[0] !== '')) {
                                        echo '<td rowspan="2"></td>';
                                        foreach ($dataHeading as $headingItem) {
                                            echo '<td colspan="2">';
                                            if (isset($headingItem)) {
                                                echo '' . $headingItem;
                                            }
                                            echo '</td><td style="display: none;"></td>';
                                        }
                                        echo '</tr><tr class="module-category__heading"><td style="display: none;"></td>';
                                    } else {
                                        echo '<td></td>';
                                    }
                                    for ($i = 0; $i < $countHeading; $i++) {
                                        echo '<td><span>Cash</span></td>';
                                        echo '<td><span>Card</span></td>';
                                    }
                                    echo '</tr>';
                                }
                            } else {
                                if (is_array($dataHeading) && $table_is_heading != 'off') {
                                    echo '<tr class="module-category__heading">
                                <td></td>';
                                    foreach ($dataHeading as $headingItem) {
                                        echo '<td>';
                                        if (isset($headingItem)) {
                                            echo '' . $headingItem;
                                        }
                                        echo '</td>';
                                    }
                                    echo '</tr>';
                                }
                            }
                            unset($catItemChild[$catChild]);
                            break;
                        }
                        $position +=1;
                    }
                    $positionItem = 1;
                    $index = 0;
                    foreach ($data as $item) {
						if ($limit_item != '' && $limit_item != '0') {
                            if ($index >= $limit_item) {
                                break;
                            }
                            $index++;
                        }
						if(!isset($item['position']) || empty($item['position'])){
                            $item['position'] = $positionItem;
                        }
                 
                    
                        if (isset($item['position']) && $item['position'] == $catItem || $item['position'] == '0') {
                            
                            echo '<tr class="module-category-item">';
                            echo '<td class="module-category-item__content">';
                            if (isset($item['featured_img']) && !empty($item['featured_img']) && $table_menu_item_is_img != 'off') {
                                echo '<div class="module-category-item__img"><img src="' . $item['featured_img'] . '" alt="image"></div>';
                            }
                            echo '<div class="module-category-item__text">';
                            if (isset($item['name']) && !empty($item['name'])) {
                                echo '<span class="module-category-item__name">' . $item['name'] . '</span>';
                            }
                            if (isset($item['description']) && !empty($item['description']) && $table_menu_item_is_description != 'off') {
                                echo '<div class="module-category-item__description">' . $item['description'] . '</div>';
                            }
                            echo '</div><!-- .module-category-item__text -->';
                            echo '</td><!-- .module-category-item__content -->';
        
                            if (!empty($mac_menu_dp)) {
                                
                                if (isset($item['price-list']) && $table_menu_item_is_price != 'off' && $hasPrice) {
                                    $indexPrice = 0;
                                    foreach ($item['price-list'] as $itemPrice) {
                                        
                                        $arrayPrice = $this->DuralPrice($mac_menu_dp_value, $mac_menu_dp_sw, $itemPrice['price'], $itemPrice['price']);
                                        
                                        echo '<td class="module-category-item__price">' . $arrayPrice['cashs'] . '</td>';
                                        echo '<td class="module-category-item__price">' . $arrayPrice['cards'] . '</td>';
                                        $indexPrice++;
                                        if ($indexPrice > $tocalPrice) {
                                            $tocalPrice++;
                                        }
                                    }
                                    if (count($dataHeading) >= $tocalPrice) {
                                        if (count($dataHeading) > 1) {
                                            for ($i = 1; $i <= (count($dataHeading) - 1); $i++) {
                                                if ($i >= count($item['price-list'])) {
                                                    echo '<td class="module-category-item__price"></td>';
                                                    echo '<td class="module-category-item__price"></td>';
                                                }
                                            }
                                        }
                                    } else {
                                        if ($tocalPrice > 1) {
                                            for ($i = 0; $i <= $tocalPrice; $i++) {
                                                if ($i > count($item['price-list'])) {
                                                    echo '<td class="module-category-item__price"></td>';
                                                    echo '<td class="module-category-item__price"></td>';
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {
                                if (isset($item['price-list']) && $table_menu_item_is_price != 'off') {
                                    $indexPrice = 0;
                                    foreach ($item['price-list'] as $itemPrice) {
                                        echo '<td class="module-category-item__price">' . $itemPrice['price'] . '</td>';
                                        $indexPrice++;
                                        if ($indexPrice > $tocalPrice) {
                                            $tocalPrice++;
                                        }
                                    }
                                    if (count($dataHeading) >= $tocalPrice) {
                                        if (count($dataHeading) > 1) {
                                            for ($i = 1; $i <= (count($dataHeading) - 1); $i++) {
                                                if ($i >= count($item['price-list'])) {
                                                    echo '<td class="module-category-item__price"></td>';
                                                }
                                            }
                                        }
                                    } else {
                                        if ($tocalPrice > 1) {
                                            for ($i = 0; $i <= $tocalPrice; $i++) {
                                                if ($i > count($item['price-list'])) {
                                                    echo '<td class="module-category-item__price"></td>';
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            echo '</tr><!-- .modul-category-item -->';
                            break; // Tìm thấy là dừng
                        }
						$positionItem += 1;
                    }
                }
                // print_r($catItemChild);
                if($catItemChild){
                    $indexChildCat = 0;
                    foreach($catItemChild as $catChild => $value ) {
                        if ($limit_item != '' && $limit_item != '0') {
                            if ($indexChildCat >= $limit_item) {
                                break;
                            }
                            $indexChildCat++;
                        }
                        $childCat = $objmacMenu->find_cat_menu($value);
                        echo '</tbody></table></div><!-- .module-category-table-wrap -->';
                        echo $macCatHtml->htmlModuleMenu($childCat, $catMenuAttr, $category_child_wrap, $category_child_wrap, null, 1);
                        // Xử lý các category con của category child
                        $results = $objmacMenu->find_cat_menu_all_child_cats(array($value));
                        $branch = $macCatHtml->buildTree($results, $value);
                        echo $macCatHtml->htmlModuleMenu($branch, $catMenuAttr, $category_child_wrap, $category_child_wrap + 1, null, 1);
                        // Hiển thị lại heading
                        if (!empty($mac_menu_dp)) {
                            // Mở lại table mới
                            echo '<div class="module-category-table-wrap" style="width:100%">
                                <table class="module-category-table">
                                    <tbody>';
                            if (is_array($dataHeading) && $table_is_heading != 'off' && $hasPrice) {
                                echo '<tr class="module-category__heading">';
                                if ($countHeading > 1 || ($countHeading === 1 && $dataHeading[0] !== '')) {
                                    echo '<td rowspan="2"></td>';
                                    foreach ($dataHeading as $headingItem) {
                                        echo '<td colspan="2">';
                                        if (isset($headingItem)) {
                                            echo '' . $headingItem;
                                        }
                                        echo '</td><td style="display: none;"></td>';
                                    }
                                    echo '</tr><tr class="module-category__heading"><td style="display: none;"></td>';
                                } else {
                                    echo '<td></td>';
                                }
                                for ($i = 0; $i < $countHeading; $i++) {
                                    echo '<td><span>Cash</span></td>';
                                    echo '<td><span>Card</span></td>';
                                }
                                echo '</tr>';
                            }
                        } else {
                            // Mở lại table wrap nếu $mac_menu_dp rỗng
                            echo '<div class="module-category-table-wrap" style="width:100%">
                                    <table class="module-category-table">
                                        <tbody>';
                            if (is_array($dataHeading) && $table_is_heading != 'off') {
                                echo '<tr class="module-category__heading">
                            <td></td>';
                                foreach ($dataHeading as $headingItem) {
                                    echo '<td>';
                                    if (isset($headingItem)) {
                                        echo '' . $headingItem;
                                    }
                                    echo '</td>';
                                }
                                echo '</tr>';
                            }
                        }
                    }
                        
                }
                
                echo '</tbody>
                </table>
                </div><!-- cat-menu-table -->';
               
                ?>

                <?php if (!($is_content == 1 && $is_parents_0 == 1)) {
                    echo '</div>';
                }
            }
            return ob_get_clean();
        }
    }
}
