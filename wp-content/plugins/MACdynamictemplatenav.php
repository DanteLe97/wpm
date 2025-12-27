<?php
/*
Plugin Name: MAC Dynamic Template Nav
Description: Hiển thị menu với dropdown biến thể đúng parent (frontend + Elementor editor). Có tính năng đổi root theo biến thể hiện tại.
Version: 2.3.0
*/
if (!defined('ABSPATH')) exit;


function mac_nav_base_slug($slug)
{
    $slug = sanitize_title($slug);
    // Xóa pattern -s04, -s05, etc.
    $slug = preg_replace('/-s\d+$/', '', $slug);
    // Xóa pattern -02, -03, etc.
    $slug = preg_replace('/-\d+$/', '', $slug);
    return $slug;
}

function mac_nav_variant_suffix($slug)
{
    // Tìm pattern -s04, -s05, etc.
    if (preg_match('/-s(\d+)$/', $slug, $m)) {
        return $m[1];
    }
    // Tìm pattern -02, -03, etc.
    if (preg_match('/-(\d+)$/', $slug, $m)) {
        return $m[1];
    }
    return '';
}

function mac_nav_template_parent($post_id)
{
    $p = get_post($post_id);
    if (!$p) return 0;
    while ($p->post_parent) {
        $p = get_post($p->post_parent);
    }
    return $p ? $p->ID : 0;
}

function mac_nav_inject_variants($items, $args)
{
    // Chặn không cho chạy khi đang trong trình chỉnh sửa Elementor
    if (is_admin() || (isset($_GET['action']) && $_GET['action'] === 'elementor') || (isset($_GET['elementor-preview']))) {
        return $items;
    }
    
    // Kiểm tra nếu menu có class 'mac-exclude-nav' thì bỏ qua
    if (isset($args->menu_class) && strpos($args->menu_class, 'mac-exclude-nav') !== false) {
        return $items;
    }
    
    // Kiểm tra nếu có items và item đầu tiên có class 'mac-exclude-nav'
    if (!empty($items) && isset($items[0]->classes) && in_array('mac-exclude-nav', $items[0]->classes)) {
        return $items;
    }
    
    $q = get_queried_object();
    $template_parent_id = (isset($q->ID)) ? mac_nav_template_parent($q->ID) : 0;

    if (!$template_parent_id && is_admin() && defined('ELEMENTOR_VERSION')) {
        $first_page = get_pages(['number' => 1, 'post_type' => 'page']);
        if ($first_page) {
            $template_parent_id = mac_nav_template_parent($first_page[0]->ID);
        }
    }
    if (!$template_parent_id) return $items;

    $pages = get_pages([
        'parent'      => $template_parent_id,
        'post_type'   => 'page',
        'post_status' => 'publish',
        'sort_column' => 'menu_order,post_title',
        'sort_order'  => 'asc',
    ]);
    if (!$pages) return $items;

    // Group theo base slug
    $groups = [];
    foreach ($pages as $p) {
        $base = mac_nav_base_slug($p->post_name);
        if (!isset($groups[$base])) $groups[$base] = [];
        $groups[$base][] = $p;
    }

    // Lấy variant từ URL hiện tại
    $current_variant = isset($q->post_name) ? mac_nav_variant_suffix($q->post_name) : '';
    
    // Nếu đang ở trang con (không phải home), lấy variant từ home page
    if ($current_variant && isset($q->post_name)) {
        $current_base = mac_nav_base_slug($q->post_name);
        if ($current_base !== 'home' && $template_parent_id) {
            // Tìm trang home để lấy variant
            $home_pages = get_pages([
                'parent' => $template_parent_id,
                'post_type' => 'page',
                'post_status' => 'publish'
            ]);
            foreach ($home_pages as $page) {
                if (mac_nav_base_slug($page->post_name) === 'home') {
                    $home_variant = mac_nav_variant_suffix($page->post_name);
                    // Chỉ áp dụng variant nếu home page thực sự có variant
                    if ($home_variant) {
                        $current_variant = $home_variant;
                    }
                    break;
                }
            }
        }
    }
    
    // Lấy URL hiện tại để tạo pattern cho các items khác
    $current_url = '';
    if (isset($q->ID)) {
        $current_url = get_permalink($q->ID);
    }
    
    // Lấy slug hiện tại để gắn current-menu-item
    $current_slug = isset($q->post_name) ? $q->post_name : '';
    $current_base_slug = mac_nav_base_slug($current_slug);

    // Map root items - giữ nguyên title gốc
    $root_menu = [];
    foreach ($items as $item) {
        if ($item->menu_item_parent == 0) {
            if ($item->object === 'page') {
                $page = get_post($item->object_id);
                if ($page) {
                    $base = mac_nav_base_slug($page->post_name);
                    $root_menu[$base] = $item;
                    
                    // Gắn current-menu-item nếu base slug khớp
                    if ($base === $current_base_slug) {
                        if (!in_array('current-menu-item', $item->classes)) {
                            $item->classes[] = 'current-menu-item';
                        }
                    }
                    
                    // Giữ nguyên title gốc của menu item, không đổi thành page title
                }
            } else {
                $maybe_id = url_to_postid($item->url);
                if ($maybe_id) {
                    $page = get_post($maybe_id);
                } else {
                    $page = get_page_by_title($item->title);
                }
                if ($page) {
                    $item->object    = 'page';
                    $item->object_id = $page->ID;
                    $item->type      = 'post_type';
                    // Giữ nguyên title gốc của menu item

                    $base = mac_nav_base_slug($page->post_name);
                    $root_menu[$base] = $item;
                }
            }
        }
    }

    // Xóa con cũ
    $items = array_filter($items, fn($it) => $it->menu_item_parent == 0);

    // Xử lý menu items để đổi link theo pattern từ URL hiện tại (trừ home)
    if ($current_url && isset($q->post_name)) {
        $current_base = mac_nav_base_slug($q->post_name);
        
        foreach ($items as $item) {
            if ($item->menu_item_parent == 0) {
                // Tìm variant tương ứng cho item này
                if ($item->object === 'page') {
                    $page = get_post($item->object_id);
                    if ($page) {
                        $base = mac_nav_base_slug($page->post_name);
                        
                        // Tìm variant thực tế của item này
                        if (isset($groups[$base])) {
                            $current_variant = mac_nav_variant_suffix($q->post_name);
                            
                            foreach ($groups[$base] as $variant) {
                                $variant_suffix = mac_nav_variant_suffix($variant->post_name);
                                if ($variant_suffix === $current_variant) {
                                    $item->url = get_permalink($variant->ID);
                                    break;
                                }
                            }
                        } else {
                            // Fallback: thay thế base trong URL hiện tại hoặc đặt "#"
                            if ($base !== 'home') {
                                $new_url = str_replace('/' . $current_base . '/', '/' . $base . '/', $current_url);
                                $item->url = $new_url;
                            } else {
                                $item->url = '#';
                            }
                        }
                    }
                } else {
                    // Xử lý custom links
                    $maybe_id = url_to_postid($item->url);
                    if ($maybe_id) {
                        $page = get_post($maybe_id);
                        if ($page) {
                            $base = mac_nav_base_slug($page->post_name);
                            
                            // Tìm variant thực tế của item này
                            if (isset($groups[$base])) {
                                $current_variant = mac_nav_variant_suffix($q->post_name);
                                
                                foreach ($groups[$base] as $variant) {
                                    $variant_suffix = mac_nav_variant_suffix($variant->post_name);
                                    if ($variant_suffix === $current_variant) {
                                        $item->url = get_permalink($variant->ID);
                                        break;
                                    }
                                }
                            } else {
                                // Fallback: thay thế base trong URL hiện tại hoặc đặt "#"
                                if ($base !== 'home') {
                                    $new_url = str_replace('/' . $current_base . '/', '/' . $base . '/', $current_url);
                                    $item->url = $new_url;
                                } else {
                                    $item->url = '#';
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // Bơm biến thể - cho tất cả items có variants
    $maxid = 7000000;
    foreach ($root_menu as $base => $root_item) {
        if (empty($groups[$base])) continue;

        $variants = $groups[$base];
        if (count($variants) < 2) continue;

        // Xác định root mới dựa trên variant hiện tại
        $new_root = null;
        if ($current_variant) {
            // Tìm variant tương ứng với current_variant
            foreach ($variants as $p) {
                if (mac_nav_variant_suffix($p->post_name) === $current_variant) {
                    $new_root = $p;
                    break;
                }
            }
        }
        if (!$new_root) {
            // fallback: root là gốc (không số)
            foreach ($variants as $p) {
                if (!preg_match('/-\d+$/', $p->post_name)) {
                    $new_root = $p;
                    break;
                }
            }
        }
        if (!$new_root) $new_root = $variants[0];

        // Cập nhật root item - chỉ đổi URL, giữ nguyên title
        $root_item->url = get_permalink($new_root->ID);
        // Giữ nguyên title gốc: $root_item->title không đổi

        if (!in_array('menu-item-has-children', $root_item->classes)) {
            $root_item->classes[] = 'menu-item-has-children';
            $root_item->classes[] = 'jet-menu-item-has-children';
        }

        // Dropdown = tất cả variants với title gốc + số thứ tự
        $index = 1;
        foreach ($variants as $p) {
            $fake = $maxid++;
            $display_title = $root_item->title; // Dùng title gốc của menu item
            
            // Thêm số thứ tự cho tất cả items (format: "Home 01", "Home 02", "About Us 01", "About Us 02")
            $display_title .= ' ' . str_pad($index, 2, '0', STR_PAD_LEFT);
            $index++;

            // Kiểm tra xem page có tồn tại không
            $page_url = get_permalink($p->ID);
            if (!$page_url || $page_url === '#') {
                $page_url = '#';
            }

            // Kiểm tra xem có phải current page không
            $is_current = ($p->post_name === $current_slug);
            $child_classes = ['menu-item', 'menu-item-type-post_type', 'menu-item-object-page'];
            if ($is_current) {
                $child_classes[] = 'current-menu-item';
            }

            $child = (object)[
                'ID'               => $fake,
                'db_id'            => $fake,
                'menu_item_parent' => $root_item->ID,
                'object_id'        => $p->ID,
                'object'           => 'page',
                'type'             => 'post_type',
                'post_type'        => 'nav_menu_item',
                'type_label'       => 'Page',
                'title'            => $display_title,
                'post_title'       => $display_title,
                'url'              => $page_url,
                'target'           => '',
                'attr_title'       => '',
                'description'      => '',
                'xfn'              => '',
                'status'           => 'publish',
                'menu_order'       => $root_item->menu_order + 0.01,
                'classes'          => $child_classes,
            ];

            $child = wp_setup_nav_menu_item($child);
            if (empty($child->title)) $child->title = $display_title;
            if (empty($child->post_title)) $child->post_title = $display_title;

            $items[] = $child;
        }
    }

    usort($items, function ($a, $b) {
        if ($a->menu_item_parent == $b->menu_item_parent) {
            return ($a->menu_order <=> $b->menu_order) ?: ($a->ID <=> $b->ID);
        }
        return $a->menu_item_parent <=> $b->menu_item_parent;
    });

    return array_values($items);
}

// Frontend
add_filter('wp_nav_menu_objects', 'mac_nav_inject_variants', 100, 2);

// Elementor Editor
add_action('elementor/frontend/widget/before_render', function ($widget) {
    if ($widget->get_name() === 'jet-nav-menu') {
        add_filter('wp_nav_menu_objects', 'mac_nav_inject_variants', 100, 2);
    }
});

