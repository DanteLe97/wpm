<?php
if (!class_exists('Mac_Cat_HTML')) {
    class Mac_Cat_HTML
    {
        function render($catAttr)
        {
            ob_start();
            $id = $catAttr['id_category'];

            $objmacMenu = new macMenu();
            if (isset($id) && $id != 'new') {
                $itemCatMenu = $objmacMenu->find_cat_menu($id);
            } else {
                $itemCatMenu = array();
            }
            // echo '<pre>';
            // print_r($itemCatMenu);
            // echo '</pre>';
            $DescriptionEditor = !empty(get_option('mac_menu_text_editor')) ? get_option('mac_menu_text_editor') : "0";
            $htmlNew = get_option('mac_html_old', 1);
            ?>
            <table class="form-table content">
                <tr style="display: none;">
                    <td>ID</td>
                    <td><input name="form_cat[id][]" class="large-text id-cat-menu" value="<?= isset($itemCatMenu[0]->id) ? $itemCatMenu[0]->id : ""; ?>" readonly></input></td>
                </tr>
                <tr>
                    <td>Name</td>
                    <td><input name="form_<?= $id; ?>_category_name" class="large-text" value="<?= isset($itemCatMenu[0]->category_name) ? esc_html($itemCatMenu[0]->category_name) : ""; ?>"> </input></td>
                </tr>
                <tr>
                    <td>Slug</td>
                    <td><input name="form_<?= $id; ?>_slug_category" class="large-text" value="<?= isset($itemCatMenu[0]->slug_category) ? esc_html($itemCatMenu[0]->slug_category) : ""; ?>"> </input></td>
                </tr>
                <tr>
                    <td>Description</td>
                    <?php if ($DescriptionEditor == 0): ?>
                        <td><textarea name="form_<?= $id; ?>_category_description" rows="5" class="large-text"><?= isset($itemCatMenu[0]->category_description) ? esc_html($itemCatMenu[0]->category_description) : ""; ?> </textarea></td>
                    <?php else: ?>
                        <td>
                            <?php
                            if (isset($itemCatMenu[0]->category_description) && !empty($itemCatMenu[0]->category_description)):
                                $contentCatDescription = $itemCatMenu[0]->category_description;
                            else:
                                $contentCatDescription = "";
                            endif;
                            $formCatDescription = "form_" . $id . "_category_description";
                            ?>
                            <?php wp_editor($contentCatDescription, $formCatDescription, array('textarea_name' => $formCatDescription)); ?>
                        </td>
                    <?php endif; ?>
                </tr>
                <tr>
                    <td>Price</td>
                    <td><input name="form_<?= $id; ?>_price" class="large-text" value="<?= isset($itemCatMenu[0]->price) ? esc_html($itemCatMenu[0]->price) : ""; ?>"> </input></td>
                </tr>
                <tr>
                    <td>Images</td>
                    <td class="mac-gallery-wrap">
                        <button class="upload-gallery-button">Upload Images</button>
                        <?php if (isset($itemCatMenu[0]->featured_img) && !empty($itemCatMenu[0]->featured_img)): ?>
                            <div class="gallery mac-gallery-list">
                                <?php echo '' . getGalleryFromIds($itemCatMenu[0]->featured_img); ?>
                            </div>
                            <input type="hidden" class="image-attachment-ids" name="form_<?= $id; ?>_featured_img" value="<?= $itemCatMenu[0]->featured_img; ?>">
                        <?php else: ?>
                            <div class="gallery mac-gallery-list"></div>
                            <input type="hidden" class="image-attachment-ids" name="form_<?= $id; ?>_featured_img" value="">
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Parents</td>
                    <?php
                    $id_category_parent = isset($_GET['id_child']) ? $_GET['id_child'] : "";
                    $parents_category = '';
                    if ($id_category_parent != "") {
                        $parents_category = $id_category_parent;
                    } else {
                        $parents_category = isset($itemCatMenu[0]->parents_category) ? $itemCatMenu[0]->parents_category : "0";
                    }
                    ?>
                    <?php $allCat = $objmacMenu->all_cat(); //all_cat_by_not_is_table 
                    ?>
                    <td>
                        <select class="mac-is-selection-parents" name="form_<?= $id; ?>_parents_category">
                            <option value="0" <?= ($parents_category == 0) ? "selected" : ""  ?>>Null</option>

                            <?php
                            $tree = $this->buildTree($allCat);
                            $options = $this->buildOptions($tree, '', $parents_category);
                            echo $options;
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>On / Off Category</td>
                    <td>
                        <div class="mac-switcher-wrap mac-switcher-btn<?= (isset($itemCatMenu[0]->is_hidden) && ($itemCatMenu[0]->is_hidden == '1')) ? ' active' : ''  ?>">
                            <span class="mac-switcher-true">On</span>
                            <span class="mac-switcher-false">Off</span>
                            <input type="text" name="form_<?= $id; ?>_is_hidden" value="<?= isset($itemCatMenu[0]->is_hidden) ? $itemCatMenu[0]->is_hidden : "0"  ?>" readonly />
                        </div>
                    </td>
                </tr>
                <?php $data_table = !empty($itemCatMenu[0]->data_table) ? json_decode($itemCatMenu[0]->data_table, true) : []; ?>
                <tr>
                    <td>On / Off Table Layout</td>
                    <td>
                        <div class="mac-is-table mac-switcher-wrap mac-switcher-btn<?= (isset($itemCatMenu[0]->is_table) && ($itemCatMenu[0]->is_table == '1')) ? ' active' : ''  ?>">
                            <span class="mac-switcher-true">On</span>
                            <span class="mac-switcher-false">Off</span>
                            <input type="text" name="form_<?= $id; ?>_is_table" value="<?= isset($itemCatMenu[0]->is_table) ? $itemCatMenu[0]->is_table : "0"  ?>" readonly />
                        </div>
                    </td>
                </tr>

                <?php if ($parents_category == 0): ?>
                    <?php do_action('mac_menu_additional_settings_dual_price_in_category'); ?>
                <?php endif; ?>
                <tr class="mac-table-total-col">
                    <td>Table Col </td>
                    <td>
                        <?php $totalColNumber = 1;
                        if (isset($data_table)): $totalColNumber = count($data_table);
                        endif; ?>
                        <select class="mac-is-selection">
                            <option value="1" <?= ($totalColNumber == 1) ? "selected" : ""  ?>>1</option>
                            <option value="2" <?= ($totalColNumber == 2) ? "selected" : ""  ?>>2</option>
                            <option value="3" <?= ($totalColNumber == 3) ? "selected" : ""  ?>>3</option>
                            <option value="4" <?= ($totalColNumber == 4) ? "selected" : ""  ?>>4</option>
                            <option value="5" <?= ($totalColNumber == 5) ? "selected" : ""  ?>>5</option>
                        </select>
                    </td>
                </tr>
                <tr class="mac-table-col-heading">
                    <td>Heading Col</td>
                    <td>
                        <table class="data_table">
                            <tr>
                                <?php if (isset($data_table) && !empty($data_table)): ?>
                                    <?php foreach ($data_table as $item => $value):
                                    ?>
                                        <td>
                                            <lable>Name</lable>
                                            <input name="form_<?= $id; ?>_table_col[]" class="large-text" value="<?= isset($value) ? esc_html($value) : ''; ?>"> </input>
                                        </td>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <td>
                                        <lable>Name</lable>
                                        <input name="form_<?= $id; ?>_table_col[]" class="large-text" value=""> </input>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td> List item</td>
                    <td>
                        <?php
                        $classFormRepeater  =  'form-repeater';
                        $classFormRepeaterChild  =  'form-repeater-child';
                        $classDataRepeater  =  'data-repeater-create';
                        $classDataRepeaterDuplicate  =  'data-repeater-duplicate';
                        $group_repeater = !empty($itemCatMenu[0]->group_repeater) ? json_decode($itemCatMenu[0]->group_repeater, true) : [];
                        // Ensure group_repeater is an array
                        if (!is_array($group_repeater)) {
                            $group_repeater = [];
                        } 
                        $htmlNew = get_option('mac_html_old', 1);
                        ?>
                        <div class="<?= $classFormRepeater; ?>" name="form_<?= $id; ?>_form-repeater">
                            <div data-repeater-list="form_<?= $id; ?>_group-repeater" class="repeater-list-item sortable">
                                <?php if (!empty($group_repeater)) { ?>
                                    <div class="mac-first-item-hidden repeater-item" data-repeater-item>
                                        <div class="repater-item-wrap">
                                            <input data-repeater-delete type="button" value="Delete" />
                                            <input <?= $classDataRepeaterDuplicate; ?> type="button" value="Duplicate" />
                                            <div class="mac-list-heading mac-collapsible mac-collapsible-btn">
                                                <h4 class="mac-heading-title"></h4>
                                                <div class="mac-heading-button "><span>+</span><span>-</span></div>
                                            </div>
                                            <div class="content">
                                                <label>Name: </label>
                                                <input type="text" class="repater-item__name" name="name" />
                                                <label>Image: </label>
                                                <div class="mac-add-media">
                                                    <input type="text" class="custom_media_url" name="featured_img" size="25" value="" readonly style="display:none" />
                                                    <button type="button" class="add_media_button">Add Media</button>
                                                    <button type="button" class="remove_media_button" style="display: none;">Remove img</button>
                                                    <img class="media_preview repater-item__img" src="" style="max-width: 200px; display:none;" alt="featured-img" />
                                                </div>
                                                <label>Description: </label>
                                                <?php if ($DescriptionEditor == 0) { ?>
                                                    <textarea type="textarea" class="repater-item__description" name="description" value="" rows="10" cols="20"></textarea>
                                                <?php
                                                } else {
                                                    $contentItemDescription = "";
                                                    $formItemDescription = "description";
                                                    echo $this->display_custom_editor($contentItemDescription, $formItemDescription);
                                                }
                                                ?>

                                                <label>FullWidth: </label>

                                                <div class="mac-switcher-wrap mac-switcher-btn repater-item__switcher-fw">
                                                    <span class="mac-switcher-true">On</span>
                                                    <span class="mac-switcher-false">Off</span>
                                                    <input type="text" name="fullwidth" value="0" readonly />
                                                </div>

                                                <div class="price-list">
                                                    <div class="<?= $classFormRepeaterChild; ?>">
                                                        <div data-repeater-list="price-list" class="repeater-list-item repater-item__repeater-list">
                                                            <div data-repeater-item>
                                                                <label>Price: </label>
                                                                <input type="text" name="price" value="" />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="position-item" style="opacity: 0; height:0;width: 0;">
                                                    <label>position: </label>
                                                    <input type="text" name="position" value="0" readonly/>
                                                </div>
                                            </div>
                                        </div>
                                    </div><!-- mac-first-item-hidden -->
                                    <?php

                                    $htmlItemRepeater = '';
                                    $catItemChild = $objmacMenu->getAllIdCatChildInside($id);
                                    
                                    // GIẢI PHÁP 2: Tạo mảng tất cả items cần render (bao gồm cả child categories)
                                    $allItems = array();
                                    
                                    // 1. Thêm child categories vào mảng với key là category_inside_order
                                    if ($htmlNew == 1 && !empty($catItemChild)) {
                                        foreach ($catItemChild as $catChild => $value) {
                                            $item = $objmacMenu->find_cat_menu($value);
                                            if (!empty($item) && isset($item[0])) {
                                                $order = isset($item[0]->category_inside_order) ? intval($item[0]->category_inside_order) : 0;
                                                if ($order == 0) {
                                                    // Nếu category_inside_order = 0, đặt vào cuối cùng
                                                    $order = 9999;
                                                }
                                                if (!isset($allItems[$order])) {
                                                    $allItems[$order] = array();
                                                }
                                                $allItems[$order][] = array(
                                                    'type' => 'child_category',
                                                    'id' => $value,
                                                    'order' => $order
                                                );
                                            }
                                        }
                                    }
                                    
                                    // 2. Thêm items vào mảng với key là position
                                    $position = 1;
                                    foreach ($group_repeater as $itemRepeater) {
                                        if (!isset($itemRepeater['position'])) {
                                            $itemRepeater['position'] = $position;
                                        }
                                        $itemPosition = isset($itemRepeater['position']) ? intval($itemRepeater['position']) : 0;
                                        if ($itemPosition == 0) {
                                            // Nếu position = 0, đặt vào cuối cùng
                                            $itemPosition = 9999;
                                        }
                                        if (!isset($allItems[$itemPosition])) {
                                            $allItems[$itemPosition] = array();
                                        }
                                        $allItems[$itemPosition][] = array(
                                            'type' => 'item',
                                            'data' => $itemRepeater
                                        );
                                        $position += 1;
                                    }
                                    
                                    // 3. Sắp xếp theo key (position/order) và render
                                    ksort($allItems);
                                    
                                    foreach ($allItems as $order => $items) {
                                        foreach ($items as $item) {
                                            if ($item['type'] === 'child_category') {
                                                // Render child category
                                                $htmlItemRepeater .= $this->item_cat_html($item['id']);
                                            } else {
                                                // Render item
                                                $itemRepeater = $item['data'];
                                                
                                                $checkedFW = '0';
                                                $checkedFWText = '';
                                                $htmlImg = '';
                                                if (!empty($itemRepeater['fullwidth'])) {
                                                    $checkedFW = '1';
                                                    $checkedFWText = ' active';
                                                }
                                                if (!empty($itemRepeater['featured_img'])) {
                                                    $htmlImg .= '<input type="text" class="custom_media_url" name="featured_img" size="25" value="' . $itemRepeater['featured_img'] . '" readonly style="display:none" />';
                                                    $htmlImg .= '<button type="button" class="add_media_button">Add Media</button>';
                                                    $htmlImg .= '<button type="button" class="remove_media_button" style="">Remove img</button>';
                                                    $htmlImg .= '<img class="media_preview" src="' . $itemRepeater['featured_img'] . '" style="max-width: 200px; " alt="featured-img" />';
                                                } else {
                                                    $htmlImg .= '<input type="text" class="custom_media_url" name="featured_img" size="25" value=""  readonly style="display:none"/>';
                                                    $htmlImg .= '<button type="button" class="add_media_button">Add Media</button>';
                                                    $htmlImg .= '<button type="button" class="remove_media_button" style="display:none;">Remove img</button>';
                                                    $htmlImg .= '<img class="media_preview" src="" style="max-width: 200px; display:none;" alt="featured-img" />';
                                                }

                                                $htmlItemRepeater .= '<div data-repeater-item class="repeater-item">';
                                                $htmlItemRepeater .= '<div class="repater-item-wrap">';

                                                $htmlItemRepeater .= '<input data-repeater-delete type="button" value="Delete"/>';
                                                $htmlItemRepeater .= '<input ' . $classDataRepeaterDuplicate . ' type="button" value="Duplicate"/>';
                                                $htmlItemRepeater .= '<div class="mac-list-heading mac-collapsible mac-collapsible-btn">';
                                                $htmlItemRepeater .= '<h4 class="mac-heading-title">' . esc_html($itemRepeater['name']) . '</h4>';
                                                $htmlItemRepeater .= '<div class="mac-heading-button"><span>+</span><span>-</span></div>';
                                                $htmlItemRepeater .= '</div>';
                                                $htmlItemRepeater .= '<div class="content">';
                                                $htmlItemRepeater .= '<label>Name: </label>';
                                                $htmlItemRepeater .= '<input type="text" name="name" value="' . esc_html($itemRepeater['name']) . '" />';

                                                $htmlItemRepeater .= '<label>Image: </label>';
                                                $htmlItemRepeater .= '<div class="mac-add-media">';

                                                $htmlItemRepeater .= $htmlImg;
                                                $htmlItemRepeater .= '</div>';
                                                $htmlItemRepeater .= '<label>Description: </label>';
                                                if ($DescriptionEditor == 0):

                                                    $htmlItemRepeater .= '<textarea type="textarea" name="description" value="" rows="10" cols="20">' . $itemRepeater['description'] . '</textarea>';
                                                else:
                                                    if ($itemRepeater['description'] != '') {
                                                        $contentItemDescription = $itemRepeater['description'];
                                                    } else {
                                                        $contentItemDescription = "";
                                                    }
                                                    $formItemDescription = "description";
                                                    $htmlItemRepeater .= $this->display_custom_editor($contentItemDescription, $formItemDescription);
                                                endif;

                                                $htmlItemRepeater .= '<label>FullWidth: </label>';

                                                $htmlItemRepeater .= '<div class="mac-switcher-wrap mac-switcher-btn' . $checkedFWText . '">';
                                                $htmlItemRepeater .= '<span class="mac-switcher-true">On</span>';
                                                $htmlItemRepeater .= '<span class="mac-switcher-false">Off</span>';
                                                $htmlItemRepeater .= '<input type="text" name="fullwidth" value="' . $checkedFW . '" readonly/>';
                                                $htmlItemRepeater .= '</div>';

                                                $htmlItemRepeater .= '<div class="price-list">';
                                                $htmlItemRepeater .= '<div class="' . $classFormRepeaterChild . '">';
                                                $htmlItemRepeater .= '<div data-repeater-list="price-list" class="repeater-list-item">';
                                                
                                                if (!empty($itemRepeater['price-list']) && is_array($itemRepeater['price-list'])) {
                                                    foreach ($itemRepeater['price-list'] as $itemPrice) {
                                                        $htmlItemRepeater .= '<div data-repeater-item>';
                                                        $htmlItemRepeater .= '<label>Price: </label>';
                                                        $htmlItemRepeater .= '<input type="text" name="price" value="' . esc_html($itemPrice['price']) . '"/>';
                                                        $htmlItemRepeater .= '</div>';
                                                    }
                                                    if(count($itemRepeater['price-list']) < $totalColNumber){
                                                        $htmlItemRepeater .= '<div data-repeater-item>';
                                                        $htmlItemRepeater .= '<label>Price: </label>';
                                                        $htmlItemRepeater .= '<input type="text" name="price" value=""/>';
                                                        $htmlItemRepeater .= '</div>';
                                                    }
                                                } else {
                                                    
                                                    for ($inumber = 1; $inumber <= $totalColNumber; $inumber++) {
                                                        $htmlItemRepeater .= '<div data-repeater-item>';
                                                        $htmlItemRepeater .= '<label>Price: </label>';
                                                        $htmlItemRepeater .= '<input type="text" name="price" value=""/>';
                                                        $htmlItemRepeater .= '</div>';
                                                    }
                                                }
                                                $htmlItemRepeater .= '</div>';
                                                $htmlItemRepeater .= '</div>';
                                                $htmlItemRepeater .= '</div> <!-- price-list -->';
                                                $itemPosition = isset($itemRepeater['position']) ? $itemRepeater['position'] : '0';
                                                $htmlItemRepeater .= '<div class="position-item" style="opacity: 0; height:0;width: 0;">
                                                                <label>position: </label>
                                                                <input type="text" name="position" readonly value="' . $itemPosition . '"/>
                                                            </div>';

                                                $htmlItemRepeater .= '</div>';
                                                $htmlItemRepeater .= '</div>';

                                                $htmlItemRepeater .= '</div><!-- htmlItemRepeater 1 -->';
                                            }
                                        }
                                    }
                                    echo $htmlItemRepeater;


                                    ?>
                                <?php } else {
                                    if($htmlNew == 1){
                                        echo $this->list_cat_child_in_cat($id, 1);
                                    }
                                    ?>
                                    <div data-repeater-item class="repeater-item">
                                        <div class="repater-item-wrap">
                                            <input data-repeater-delete type="button" value="Delete" />
                                            <input <?= $classDataRepeaterDuplicate; ?> type="button" value="Duplicate" />
                                            <div class="mac-list-heading mac-collapsible mac-collapsible-btn">
                                                <h4 class="mac-heading-title"></h4>
                                                <div class="mac-heading-button"><span>+</span><span>-</span></div>
                                            </div>
                                            <div class="content">
                                                <label>Name: </label>
                                                <input type="text" name="name" />

                                                <label>Image: </label>
                                                <div class="mac-add-media">
                                                    <input type="text" class="custom_media_url" name="featured_img" size="25" value="" readonly style="display:none" />
                                                    <button type="button" class="add_media_button">Add Media</button>
                                                    <button type="button" class="remove_media_button" style="display: none;">Remove img</button>
                                                    <img class="media_preview" src="" style="max-width: 200px; display:none;" alt="featured-img" />
                                                </div>

                                                <label>Description: </label>
                                                <?php if ($DescriptionEditor == 0): ?>
                                                    <textarea type="textarea" name="description" value="" rows="10" cols="20"></textarea>
                                                <?php
                                                else:
                                                    $contentItemDescription = "";
                                                    $formItemDescription = "description";
                                                    echo $this->display_custom_editor($contentItemDescription, $formItemDescription);
                                                endif;
                                                ?>
                                                <label>FullWidth: </label>
                                                <div class="mac-switcher-wrap mac-switcher-btn">
                                                    <span class="mac-switcher-true">On</span>
                                                    <span class="mac-switcher-false">Off</span>
                                                    <input type="text" name="fullwidth" value="0" readonly />
                                                </div>
                                                <div class="price-list">
                                                    <div class="form-repeater-child">
                                                        <div data-repeater-list="price-list" class="repeater-list-item">
                                                            <div data-repeater-item>
                                                                <label>Price: </label>
                                                                <input type="text" name="price" value="" />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="position-item" style="opacity: 0; height:0;width: 0;">
                                                    <label>position: </label>
                                                    <input type="text" name="position" value="0" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                <?php }  ?>
                            </div>
                            <input <?= $classDataRepeater; ?> type="button" value="Add" />
                        </div>
                    <td>
                </tr>
                <?php if (!empty($itemCatMenu[0]->parents_category)) { ?>
                    <tr style="border-bottom: 0px">
                        <td> Category inside </td>
                        <td>
                            <div class="mac-switcher-wrap mac-switcher-btn<?= (isset($itemCatMenu[0]->category_inside) && ($itemCatMenu[0]->category_inside == '1')) ? ' active' : ''  ?>">
                                <span class="mac-switcher-true">On</span>
                                <span class="mac-switcher-false">Off</span>
                                <input type="text" name="form_<?= $id; ?>_category_inside" value="<?= isset($itemCatMenu[0]->category_inside) ? $itemCatMenu[0]->category_inside : "1"  ?>" readonly />
                            </div>
                        </td>
                    </tr>
                    <tr style="opacity: 0; height:0;width: 0; display:none">
                        <td>Order Category Inside</td>
                        <td><input name="form_<?= $id; ?>_category_inside_order" class="large-text category-position" value="<?= isset($itemCatMenu[0]->category_inside_order)  ? $itemCatMenu[0]->category_inside_order : ''; ?>"> </input></td>
                    </tr>
                <?php } ?>
                <tr style="opacity: 0; height:0;width: 0; display:none">
                    <td>Order</td>
                    <td><input name="form_<?= $id; ?>_order" class="large-text position" readonly value="<?= isset($itemCatMenu[0]->order) ? $itemCatMenu[0]->order : ""; ?>"> </input></td>
                </tr>
            </table>
        <?php return ob_get_clean();
        }
        function renderChildCatInside($catAttr)
        {
            ob_start();
            $id = $catAttr['id_category'];

            $objmacMenu = new macMenu();
            if (isset($id) && $id != 'new') {
                $itemCatMenu = $objmacMenu->find_cat_menu($id);
            } else {
                $itemCatMenu = array();
            }
            ?>
            <table class="form-table content">
                <tr style="display: none;">
                    <td>ID</td>
                    <td><input name="form_cat[id][]" class="large-text id-cat-menu" value="<?= isset($itemCatMenu[0]->id) ? $itemCatMenu[0]->id : ""; ?>" readonly></input></td>
                </tr>
                
                <tr style="opacity: 0; height:0;width: 0; display:none">
                    <td>Order Category Inside</td>
                    <td><input name="form_<?= $id; ?>_category_inside_order" class="large-text category-position" value="<?= isset($itemCatMenu[0]->category_inside_order)  ? $itemCatMenu[0]->category_inside_order : ''; ?>"> </input></td>
                </tr>
            </table>
        <?php return ob_get_clean();
        }
        private function buildTree(array $elements, $parentId = 0)
        {
            $branch = array();
            foreach ($elements as $element) {
                if ($element->parents_category == $parentId) {
                    $children = $this->buildTree($elements, $element->id);
                    if ($children) {
                        $element->children = $children;
                    }
                    $branch[] = $element;
                }
            }
            return $branch;
        }
        private function buildOptions($tree, $prefix = '', $parents_category = 0)
        {
            $html = '';
            $parentsCatHTML = '';
            foreach ($tree as $branch) {
                if ($parents_category == $branch->id):
                    $parentsCatHTML = 'selected';
                else:
                    $parentsCatHTML = '';
                endif;
                $html .= '<option value="' . $branch->id . '" ' . $parentsCatHTML . ' >' . $prefix .  $branch->category_name . '</option>';
                if (isset($branch->children)) {
                    $html .= $this->buildOptions($branch->children, $prefix . '--', $parents_category);
                }
            }

            return $html;
        }
        private function display_custom_editor($contentItemDescription, $formItemDescription)
        {
            ob_start(); // Bắt đầu bộ đệm đầu ra
            $textareaID = uniqid($formItemDescription . '-');
        ?>
            <div class="mac-menu-custom-wp-editor">
                <?php
                wp_editor($contentItemDescription, $textareaID, array('textarea_name' => $formItemDescription));

                ?>
            </div>
<?php
            $editor_content = ob_get_clean();
            return $editor_content;
        }
        private function item_cat_html($id)
        {
            ob_start(); // Bắt đầu bộ đệm đầu ra
            $objmacMenu = new macMenu();
            $htmlNew = get_option('mac_html_old', 1);
            $item = $objmacMenu->find_cat_menu($id);
            $htmlCat = '';

            $htmlCat .= '<div class="list-item repater-item-wrap">';
            $catChildConfigs = array();
            $catChildConfigs['id_category'] = $id;
            $catChildConfigs['is_category_inside'] = $item[0]->category_inside;
            $htmlCat .= '<input class="btn-delete-cat-menu" type="button" value="Delete">';
            $htmlCat .= '<a class="btn-edit-cat-menu" href="admin.php?page=mac-cat-menu&id=' . $id . '">Edit</a>';
            $htmlCat .= '<div class="mac-list-heading mac-list-cat-child__heading mac-collapsible mac-collapsible-btn">';
            $htmlCat .= '<h4 class="mac-heading-title">' . $item[0]->category_name . '</h4>';
            if (empty($item[0]->category_inside) || $htmlNew == 0) {
                $htmlCat .= '<div class="mac-heading-button"><span>+</span><span>-</span></div>';
            }
            $htmlCat .= '</div>';
            //$htmlCat .= $this->render($catChildConfigs);
            if (empty($item[0]->category_inside) || $htmlNew == 0) {
                $htmlCat .= $this->render($catChildConfigs);
            }
            else{
                $htmlCat .= $this->renderChildCatInside($catChildConfigs);
            }
            $allChildMenu = $objmacMenu->all_cat_by_parent_cat_menu($id);
            if ($allChildMenu) {
                $htmlCat .= '<table class="form-table-child content">';
                $htmlCat .= '<tbody>';
                $htmlCat .= '<tr>';
                $htmlCat .= '<td>List Category Child Item</td>';
                $htmlCat .= '<td>';
                $htmlCat .= '<div class="mac-list-cat-child">';
                foreach ($allChildMenu as $itemCatChild):
                    $htmlCat .= '<div class="list-child-item">';
                    $htmlCat .= '<div class="mac-list-heading">';
                    $htmlCat .= '<h4 class="mac-heading-title"><a href="admin.php?page=mac-cat-menu&id=' . $itemCatChild->id . '">' . $itemCatChild->category_name . '</a></h4>';
                    $htmlCat .= '</div>';
                    $htmlCat .= '</div>';
                endforeach;
                $htmlCat .= '</div>';
                $htmlCat .= '</td>';
                $htmlCat .= '</tr>';
                $htmlCat .= '</tbody>';
                $htmlCat .= '</table>';
            }
            $htmlCat .= '</div>';
            return $htmlCat;
        }
        public function list_cat_child_in_cat($id_category, $inside = null)
        {
            $objmacMenu = new macMenu();
            $result = $objmacMenu->all_cat_by_parent_cat_menu($id_category);
            $htmlNew = get_option('mac_html_old', 1);
            if (empty($inside)) {
                echo '<div class="inside">
                    <div class="mac-list-cat-child sortable">';
                $inside = 0;
            }
            foreach ($result as $item) {
                if ($inside == $item->category_inside || empty($htmlNew)) {
                    echo $this->item_cat_html($item->id);
                }
            }
            if (empty($inside)) {
                echo '</div>
                    <div class="overlay" id="overlay"></div>
                    <div class="confirm-dialog" id="confirmDialog" >
                        <p>Are you sure you want to delete?</p>
                        <input name="formIdDeledte" value="" readonly style="opacity: 0; visibility: hidden;"/>
                        <div class="btn-wrap">
                            <div id="confirmOk">OK</div>
                            <div id="confirmCancel">Cancel</div>
                        </div>
                    </div>
                </div>';
            }
        }
    }
}
