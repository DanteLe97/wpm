<?php
if (!class_exists('Mac_Category_Menu')) {
    class Mac_Category_Menu {
        // Properties
        private $macMenu;
        private $catMenuAttr;

        // Constructor
        public function __construct() {
            $this->macMenu = new macMenu();
        }

        /**
         * Render category menu
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
            
            // Category display options
            $is_img = $catMenuAttr['cat_menu_is_img'] ?? "";
            $is_description = $catMenuAttr['cat_menu_is_description'] ?? "";
            $is_price = $catMenuAttr['cat_menu_is_price'] ?? "";

            // Menu item display options
            $menu_item_is_img = $catMenuAttr['cat_menu_item_is_img'] ?? "";
            $menu_item_is_description = $catMenuAttr['cat_menu_item_is_description'] ?? "";
            $menu_item_is_price = $catMenuAttr['cat_menu_item_is_price'] ?? "";

            // Layout options
            $custom_layout = $catMenuAttr['cat_menu_is_custom_layout'] ?? '';
            $render_items = $catMenuAttr['cat_menu_render_items'] ?? '';
            $section_category = $catMenuAttr['id_section_category'] ?? '';

            // Menu structure options
            $is_child = $catMenuAttr['is_child'] ?? 0;
            $is_content = $catMenuAttr['is_content'] ?? 0;
            $is_parents_0 = $catMenuAttr['is_parents_0'] ?? 0;

            // Get category data
            $isCat = $this->macMenu->find_cat_menu($id);

            // Start output buffering
            ob_start();

            // Render custom layout if enabled
            if ($this->shouldRenderCustomLayout($custom_layout, $section_category)) {
                $this->renderCustomLayout($id, $class, $isCat, $section_category, $limit_item, $menu_item_is_img, $menu_item_is_description, $menu_item_is_price);
            } else {
                $this->renderDefaultLayout($id, $class, $isCat, $is_img, $is_description, $is_price, $is_content, $is_parents_0, $limit_item, $menu_item_is_img, $menu_item_is_description, $menu_item_is_price);
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
        private function renderCustomLayout($id, $class, $isCat, $template_id, $limit_item, $menu_item_is_img, $menu_item_is_description, $menu_item_is_price, $render_items = '') {
            ?>
            <div id="<?= $isCat[0]->slug_category ?? '' ?>" class="module-category__content<?= $class; ?>">
            <?php 
            if (!empty($template_id) && class_exists('\Elementor\Plugin')) {
                $custom_array = [
                    'id' => $id,
                    'limit_item' => $limit_item,
                    'is_img' => $menu_item_is_img,
                    'is_description' => $menu_item_is_description,
                    'is_price' => $menu_item_is_price
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
        private function renderDefaultLayout($id, $class, $isCat, $is_img, $is_description, $is_price, $is_content, $is_parents_0, $limit_item, $menu_item_is_img, $menu_item_is_description, $menu_item_is_price) {
            // if ($is_content == 1 && $is_parents_0 == 1) {
            //     return;
            // }
            ?>
            <div id="<?= $isCat[0]->slug_category ?? '' ?>" class="module-category__content<?= $class; ?>">
                <?php if($is_content !=1 || ($is_content !=1 && $is_parents_0 == 0) || ($is_content == 1 && $is_parents_0 == 0) ){ $this->renderCategoryHeader($isCat, $is_price, $is_description, $is_img);} ?>
                <?php $this->renderMenuItems($isCat, $limit_item, $menu_item_is_img, $menu_item_is_description, $menu_item_is_price); ?>
            </div>
            <?php
        }

        /**
         * Render category header
         */
        private function renderCategoryHeader($isCat, $is_price, $is_description, $is_img) {
            ?>
            <div class="module-category__text">
                <div class="module-category__head">
                    <?php if (!empty($isCat[0]->category_name)): ?>
                        <span class="module-category__name"><?= $isCat[0]->category_name ?></span>
                    <?php endif; ?>
                    <?php if (!empty($isCat[0]->price) && $is_price != 'off'): ?>
                        <div class="module-category__price"><?= $isCat[0]->price ?></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($isCat[0]->category_description) && $is_description != 'off'): ?>
                    <div class="module-category__description"><?= $isCat[0]->category_description ?></div>
                <?php endif; ?>
            </div>
            <?php if (!empty($isCat[0]->featured_img) && $is_img != 'off'): ?>
                <div class="module-category__img">
                    <?= getGalleryFromIds($isCat[0]->featured_img, 'url') ?>
                </div>
            <?php endif; ?>
            <?php
        }

        /**
         * Render menu items
         */
        private function renderMenuItems($isCat, $limit_item, $menu_item_is_img, $menu_item_is_description, $menu_item_is_price) {
            $json = $isCat[0]->group_repeater ?? "[]";
            if ($json == "[]") {
                return;
            }
            ?>
            <div class="module-category__list-item" data-limit="<?= $limit_item ?>">
                <?php
                $data = json_decode($json, true);
                if (is_array($data)) {
                    $this->renderMenuItemsList($data, $limit_item, $menu_item_is_img, $menu_item_is_description, $menu_item_is_price);
                }
                ?>
            </div>
            <?php
        }

        /**
         * Render menu items list
         */
        private function renderMenuItemsList($data, $limit_item, $menu_item_is_img, $menu_item_is_description, $menu_item_is_price) {
            $index = 0;
            $catIndex = 0;

            foreach ($data as $item) {
                if ($limit_item != '' && $limit_item != '0' && $index >= $limit_item) {
                    break;
                }
                $index++;

                $this->renderMenuItem($item, $catIndex, $menu_item_is_img, $menu_item_is_description, $menu_item_is_price);
                $catIndex++;
            }
        }

        /**
         * Render single menu item
         */
        private function renderMenuItem($item, $catIndex, $menu_item_is_img, $menu_item_is_description, $menu_item_is_price) {
            $fullwidthClass = isset($item['fullwidth']) && !empty($item['fullwidth']) ? '' : 'item-not-fw';
            ?>
            <div class="module-category-item module-category-item-index-<?= $catIndex ?> <?= $fullwidthClass ?>">
                <?php if (!empty($item['featured_img']) && $menu_item_is_img != 'off'): ?>
                    <div class="module-category-item__img">
                        <img src="<?= $item['featured_img'] ?>" alt="image">
                    </div>
                <?php endif; ?>
                <div class="module-category-item__text">
                    <div class="module-category-item__head">
                        <?php if (!empty($item['name'])): ?>
                            <span class="module-category-item__name"><?= $item['name'] ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['price-list']) && $menu_item_is_price != 'off'): ?>
                            <?php foreach ($item['price-list'] as $itemPrice): ?>
                                <?php if (!empty($itemPrice['price'])): ?>
                                    <div class="module-category-item__price"><?= $itemPrice['price'] ?></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($item['description']) && $menu_item_is_description != 'off'): ?>
                        <div class="module-category-item__description"><?= $item['description'] ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
    }
}
?>