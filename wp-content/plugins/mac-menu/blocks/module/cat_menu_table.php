<?php
if (!class_exists('Mac_Category_Menu_Table')) {
    class Mac_Category_Menu_Table {
        // Properties
        private $macMenu;
        private $catMenuAttr;
        private $mac_menu_dp;
        private $mac_menu_dp_value;
        private $mac_menu_dp_sw;

        // Constructor
        public function __construct() {
            $this->macMenu = new macMenu();
            $this->initializeDualPriceSettings();
        }

        /**
         * Initialize dual price settings
         */
        private function initializeDualPriceSettings() {
            $this->mac_menu_dp = get_option('mac_menu_dp') ?: 0;
            $this->mac_menu_dp_value = get_option('mac_menu_dp_value');
            $this->mac_menu_dp_sw = get_option('mac_menu_dp_sw') ?: 0;

            if (empty($this->mac_menu_dp_sw)) {
                $this->mac_menu_dp_sw = 0;
                $this->mac_menu_dp_value = 1 + $this->mac_menu_dp_value / 100;
            }
        }

        /**
         * Render category menu table
         * 
         * @param array $catMenuAttr Menu attributes
         * @return string HTML output
         */
        public function render($catMenuAttr) {
            $this->catMenuAttr = $catMenuAttr;

            // Get menu attributes with defaults
            $id = $catMenuAttr['id'];
            $class = $catMenuAttr['class'] ?? "";
            $limit_item = $catMenuAttr['limit_item'] ?? "";

            // Table display options
            $table_is_img = $catMenuAttr['cat_menu_table_is_img'] ?? "";
            $table_is_description = $catMenuAttr['cat_menu_table_is_description'] ?? "";
            $table_is_price = $catMenuAttr['cat_menu_table_is_price'] ?? "";
            $table_is_heading = $catMenuAttr['cat_menu_table_is_heading'] ?? "";

            // Table menu item display options
            $table_menu_item_is_img = $catMenuAttr['cat_menu_table_item_is_img'] ?? "";
            $table_menu_item_is_description = $catMenuAttr['cat_menu_table_item_is_description'] ?? "";
            $table_menu_item_is_price = $catMenuAttr['cat_menu_table_item_is_price'] ?? "";

            // Layout options
            $custom_layout = $catMenuAttr['cat_menu_is_custom_layout'] ?? '';
            $render_items = $catMenuAttr['cat_menu_render_items'] ?? '';
            $section_category = $catMenuAttr['id_section_category'] ?? '';
            $section_category_item = $catMenuAttr['id_section_category_item'] ?? '';

            // Menu structure options
            $is_child = $catMenuAttr['is_child'] ?? 0;
            $is_content = $catMenuAttr['is_content'] ?? 0;
            $is_parents_0 = $catMenuAttr['is_parents_0'] ?? 0;

            // Get category data
            $isCat = $this->macMenu->find_cat_menu($id);

            // Apply filters for dual price
            $this->mac_menu_dp_value = apply_filters('custom_dp_value', $this->mac_menu_dp_value, $id);
            $this->mac_menu_dp_sw = apply_filters('custom_dp_sw', $this->mac_menu_dp_sw, $id);

            // Start output buffering
            ob_start();

            // Render custom layout if enabled
            if ($this->shouldRenderCustomLayout($custom_layout, $section_category)) {
                $this->renderCustomLayout($id, $class, $isCat, $section_category, $limit_item, $table_is_heading, $table_menu_item_is_img, $table_menu_item_is_description, $table_menu_item_is_price, $render_items);
            } else {
                $this->renderDefaultLayout($id, $class, $isCat, $table_is_img, $table_is_description, $table_is_price, $table_is_heading, $is_content, $is_parents_0, $limit_item, $table_menu_item_is_img, $table_menu_item_is_description, $table_menu_item_is_price);
            }

            return ob_get_clean();
        }

        /**
         * Check if should render custom layout
         */
        private function shouldRenderCustomLayout($custom_layout, $section_category) {
            return $custom_layout == 'on' && $section_category != 'default';
        }

        /**
         * Render custom layout using Elementor
         */
        private function renderCustomLayout($id, $class, $isCat, $template_id, $limit_item, $table_is_heading, $table_menu_item_is_img, $table_menu_item_is_description, $table_menu_item_is_price, $render_items = '') {
            ?>
            <div id="<?= $isCat[0]->slug_category ?? '' ?>" class="module-category__content<?= $class; ?>">
            <?php 
            if (!empty($template_id) && class_exists('\Elementor\Plugin')) {
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
            </div>
            <?php
        }

        /**
         * Render default layout
         */
        private function renderDefaultLayout($id, $class, $isCat, $table_is_img, $table_is_description, $table_is_price, $table_is_heading, $is_content, $is_parents_0, $limit_item, $table_menu_item_is_img, $table_menu_item_is_description, $table_menu_item_is_price) {
            if ($is_content == 1 && $is_parents_0 == 1) {
                return;
            }
            ?>
            <div id="<?= $isCat[0]->slug_category ?? '' ?>" class="module-category__content module-category-table-style__content<?= $class; ?>">
                <?php $this->renderCategoryHeader($isCat, $table_is_price, $table_is_description, $table_is_img); ?>
                <?php $this->renderMenuTable($isCat, $limit_item, $table_is_heading, $table_menu_item_is_img, $table_menu_item_is_description, $table_menu_item_is_price); ?>
            </div>
            <?php
        }

        /**
         * Render category header
         */
        private function renderCategoryHeader($isCat, $table_is_price, $table_is_description, $table_is_img) {
            ?>
            <div class="module-category__text">
                <div class="module-category__head">
                    <?php if (!empty($isCat[0]->category_name)): ?>
                        <span class="module-category__name"><?= $isCat[0]->category_name ?></span>
                    <?php endif; ?>
                    <?php if (!empty($isCat[0]->price) && $table_is_price != 'off' && $this->mac_menu_dp_sw == 1): ?>
                        <div class="module-category__price"><?= $isCat[0]->price ?></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($isCat[0]->category_description) && $table_is_description != 'off'): ?>
                    <div class="module-category__description">
                        <?php if (!empty($isCat[0]->price) && $table_is_price != 'off' && $this->mac_menu_dp_sw == 0): 
                            $arrayCatPrice = $this->DuralPrice($this->mac_menu_dp_value, $this->mac_menu_dp_sw, $isCat[0]->price, $isCat[0]->price);
                        ?>
                            <div class="dualprice-category">cash: <?= $arrayCatPrice['cashs'] ?> /card: <?= $arrayCatPrice['cards'] ?></div>
                        <?php endif; ?>
                        <?= $isCat[0]->category_description ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($isCat[0]->featured_img) && $table_is_img != 'off'): ?>
                <div class="module-category__img">
                    <?= getGalleryFromIds($isCat[0]->featured_img, 'url') ?>
                </div>
            <?php endif; ?>
            <?php
        }

        /**
         * Render menu table
         */
        private function renderMenuTable($isCat, $limit_item, $table_is_heading, $table_menu_item_is_img, $table_menu_item_is_description, $table_menu_item_is_price) {
            $json = $isCat[0]->group_repeater ?? "[]";
            $data = json_decode($json, true);
            
            if (empty($data) || !is_array($data[0] ?? null)) {
                return;
            }
            $dataTable = isset($isCat[0]->data_table) ? json_decode($isCat[0]->data_table, true) : [];
            $countHeading = is_array($dataTable) ? count($dataTable) : 0;
            ?>
            <div class="module-category-table-wrap" style="width:100%">
                <table class="module-category-table">
                    <tbody>
                        <?php 
                        $this->renderTableHeadings($isCat, $table_is_heading);
                        $this->renderTableRows($data, $limit_item, $table_menu_item_is_img, $table_menu_item_is_description, $table_menu_item_is_price,$countHeading);
                        ?>
                    </tbody>
                </table>
            </div>
            <?php
        }

        /**
         * Render table headings
         */
        private function renderTableHeadings($isCat, $table_is_heading) {
            $groupRepeater = json_decode($isCat[0]->group_repeater, true);
            $hasPrice = array_reduce($groupRepeater, function ($carry, $item) {
                foreach ($item['price-list'] as $p) {
                    if (!empty($p['price'])) return true;
                }
                return $carry;
            }, false);

            $jsonHeading = $isCat[0]->data_table ?? "[]";
            $dataHeading = json_decode($jsonHeading, true);
            $countHeading = count($dataHeading);

            if (!empty($this->mac_menu_dp)) {
                $this->renderDualPriceHeadings($dataHeading, $table_is_heading, $hasPrice);
            } else {
                $this->renderSinglePriceHeadings($dataHeading, $table_is_heading);
            }
        }

        /**
         * Render dual price headings
         */
        private function renderDualPriceHeadings($dataHeading, $table_is_heading, $hasPrice) {
            if (!is_array($dataHeading) || $table_is_heading == 'off' || !$hasPrice) {
                return;
            }

            $countHeading = count($dataHeading);
            echo '<tr class="module-category__heading">';
          
            if ($countHeading > 1 || ($countHeading === 1 && $dataHeading[0] !== '')) {
                echo '<td rowspan="2"></td>';
                foreach ($dataHeading as $item) {
                    echo '<td colspan="2">' . ($item ?? '') . '</td><td style="display: none;"></td>';
                }
                echo '</tr><tr class="module-category__heading"><td style="display: none;"></td>';
            } else {
                echo '<td></td>';
            }
            if(empty($countHeading)){
                $countHeading = 1;
            }
            for ($i = 0; $i <= $countHeading; $i++) {
                echo '<td><span>Cash</span></td>';
                echo '<td><span>Card</span></td>';
            }
            echo '</tr>';
        }

        /**
         * Render single price headings
         */
        private function renderSinglePriceHeadings($dataHeading, $table_is_heading) {
            if (!is_array($dataHeading) || $table_is_heading == 'off') {
                return;
            }

            echo '<tr class="module-category__heading"><td></td>';
            foreach ($dataHeading as $item) {
                echo '<td>' . ($item ?? '') . '</td>';
            }
            echo '</tr>';
        }

        /**
         * Render table rows
         */
        private function renderTableRows($data, $limit_item, $table_menu_item_is_img, $table_menu_item_is_description, $table_menu_item_is_price,$countHeading) {
            $index = 0;
            $catIndex = 0;
            $totalPrice = 0;

            foreach ($data as $item) {
                if ($limit_item != '' && $limit_item != '0' && $index >= $limit_item) {
                    break;
                }
                $index++;
                $catIndex++;

                $this->renderTableRow($item, $catIndex, $table_menu_item_is_img, $table_menu_item_is_description, $table_menu_item_is_price, $totalPrice,$countHeading);
            }
        }

        /**
         * Render single table row
         */
        private function renderTableRow($item, $catIndex, $table_menu_item_is_img, $table_menu_item_is_description, $table_menu_item_is_price, &$totalPrice,$countHeading) {
            echo '<tr class="module-category-item">';
            
            // Render item content
            echo '<td class="module-category-item__content">';
            if (!empty($item['featured_img']) && $table_menu_item_is_img != 'off') {
                echo '<div class="module-category-item__img"><img src="' . $item['featured_img'] . '" alt="image"></div>';
            }
            echo '<div class="module-category-item__text">';
            if (!empty($item['name'])) {
                echo '<span class="module-category-item__name">' . $item['name'] . '</span>';
            }
            if (!empty($item['description']) && $table_menu_item_is_description != 'off') {
                echo '<div class="module-category-item__description">' . $item['description'] . '</div>';
            }
            echo '</div>';
            echo '</td>';

            // Render prices
            if (!empty($this->mac_menu_dp)) {
                $this->renderDualPrices($item, $table_menu_item_is_price,$countHeading);
            } else {
                $this->renderSinglePrices($item, $table_menu_item_is_price, $totalPrice,$countHeading);
            }

            echo '</tr>';
        }

        /**
         * Render dual prices
         */
        private function renderDualPrices($item, $table_menu_item_is_price,$countHeading) {
            if (empty($item['price-list']) || $table_menu_item_is_price == 'off') {
                return;
            }
            $totalPrice = 0;
            $indexPrice = 0;
            foreach ($item['price-list'] as $itemPrice) {
                $arrayPrice = $this->DuralPrice($this->mac_menu_dp_value, $this->mac_menu_dp_sw, $itemPrice['price'], $itemPrice['price']);
                echo '<td class="module-category-item__price">' . $arrayPrice['cashs'] . '</td>';
                echo '<td class="module-category-item__price">' . $arrayPrice['cards'] . '</td>';
                $indexPrice++;
                    if ($indexPrice > $totalPrice) {
                        $totalPrice++;
                    }
            }
            $this->fillEmptyPriceCells($totalPrice, count($item['price-list']),$countHeading,1);
        }

        /**
         * Render single prices
         */
        private function renderSinglePrices($item, $table_menu_item_is_price, &$totalPrice,$countHeading) {
            if (empty($item['price-list']) || $table_menu_item_is_price == 'off') {
                return;
            }

            $indexPrice = 0;
            foreach ($item['price-list'] as $itemPrice) {
                echo '<td class="module-category-item__price">' . $itemPrice['price'] . '</td>';
                $indexPrice++;
                if ($indexPrice > $totalPrice) {
                    $totalPrice++;
                }
            }
            // Fill empty cells if needed
            $this->fillEmptyPriceCells($totalPrice, count($item['price-list']),$countHeading);
        }

        /**
         * Fill empty price cells
         */
        private function fillEmptyPriceCells($totalPrice, $priceListCount,$countHeading,$isDp = 0) {

            if ($countHeading >= $totalPrice) {
                if ($countHeading > 1) {
                    for ($i = 1; $i <= ($countHeading - 1); $i++) {
                        if ($i >= $priceListCount) {
                            echo '<td class="module-category-item__price"></td>';
                            if($isDp == 1){
                                echo '<td class="module-category-item__price"></td>';
                            }
                        }
                    }
                }
            } else {
                if ($totalPrice > 1) {
                    for ($i = 0; $i <= $totalPrice; $i++) {
                        if ($i > $priceListCount) {
                            echo '<td class="module-category-item__price"></td>';
                            if($isDp == 1){
                                echo '<td class="module-category-item__price"></td>';
                            }
                        }
                    }
                }
            }

            // if ($totalPrice > 1) {
            //     for ($i = 0; $i <= $totalPrice; $i++) {
            //         if ($i > $priceListCount) {
            //             echo '<td class="module-category-item__price"></td>';
            //         }
            //     }
            // }
        }

        /**
         * Calculate dual price
         */
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
    }
}
