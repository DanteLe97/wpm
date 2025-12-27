<?php
/**
 * Export API for MAC Importer Demo
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Importer_Export_API {
    
    /**
     * Register export API endpoints
     */
    public static function register_endpoints() {
        // Export page API
        register_rest_route('ltp/v1', '/elementor/export-page', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'export_elementor_data_by_url'),
            'permission_callback' => '__return_true'
        ));
        
        // Elementor data API (legacy)
        register_rest_route('ltp/v1', '/elementor-data', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_elementor_data_rest'),
            'permission_callback' => function() {
                return current_user_can('edit_theme_options');
            }
        ));
        
        // Test custom colors format endpoint
        register_rest_route('ltp/v1', '/elementor/test-custom-colors', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array(__CLASS__, 'test_custom_colors_format')
        ));
    }
    
    /**
     * Export Elementor data theo URL
     */
    public static function export_elementor_data_by_url($request) {
        $auth_key = MAC_Importer_API_Base::get_auth_key_from_request($request);
        $pages = $request->get_param('pages');
        $single_url = $request->get_param('url');
        
        // Auth check: dùng license từ mac-core, luôn trả 200 JSON
        $is_valid = MAC_Importer_API_Base::validate_license_key($auth_key);
        if (!$is_valid) {
            $debug = $request->get_param('debug');
            return MAC_Importer_API_Base::create_error_response(401, 'Unauthorized: invalid or missing auth_key', $debug);
        }
        
        // Validate input (không cần action)

        // Chuẩn hóa input: hỗ trợ param url đơn hoặc mảng pages
        if (!empty($single_url) && is_string($single_url)) {
            $name = $request->get_param('name');
            if (empty($name)) {
                $parsed = wp_parse_url($single_url);
                $name = isset($parsed['path']) ? trim(basename($parsed['path']), '/') : 'page';
            }
            $flag = $request->get_param('site_settings');
            $pages = array(array(
                'name' => $name,
                'url' => $single_url,
                'site_settings' => is_bool($flag) ? $flag : false,
            ));
        }

        if (empty($pages) || !is_array($pages)) {
            return MAC_Importer_API_Base::create_error_response(400, 'Parameter "pages" must be a non-empty array');
        }
        
        // Extract URLs from pages array
        $urls = array();
        foreach ($pages as $page) {
            if (isset($page['url'])) {
                $urls[] = $page['url'];
            }
        }
        
        if (empty($urls)) {
            return MAC_Importer_API_Base::create_error_response(400, 'No valid URLs found in pages array');
        }
        
        $exported_pages = array();
        $errors = array();
        
        // Parse URLs và tìm page IDs, giữ lại name và xử lý site_settings theo từng trang
        foreach ($pages as $index => $page_request) {
            $url = $page_request['url'];
            $name = isset($page_request['name']) ? $page_request['name'] : '';
            $want_site_settings = isset($page_request['site_settings']) && $page_request['site_settings'] === true;
            
            $page_data = self::parse_url_and_get_page_data($url);
            if ($page_data) {
                // Đảm bảo 'name' đứng đầu bằng cách tái tạo mảng theo thứ tự mong muốn
                unset($page_data['name']);
                $ordered = array_merge(array('name' => $name), $page_data);

                // Override page_title và title trong data bằng name từ request
                $ordered['page_title'] = $name;
                if (isset($ordered['data']['title'])) {
                    $ordered['data']['title'] = $name;
                }

                // Luôn trả site_settings là object; nếu flag true thì lấy theo trang hiện tại
                $ordered['site_settings'] = (object) array();
                if ($want_site_settings && isset($ordered['page_id'])) {
                    $ordered['site_settings'] = self::build_site_settings_from_mac_colors(intval($ordered['page_id']));
                }
                $exported_pages[] = $ordered;
            } else {
                $errors[] = array(
                    'url' => $url,
                    'name' => $name,
                    'error' => 'Page not found'
                );
            }
        }
        
        // Debug: Log số lượng URLs và pages
        error_log('LTP Debug: Total URLs: ' . count($urls));
        error_log('LTP Debug: URLs received: ' . print_r($urls, true));
        error_log('LTP Debug: Found pages: ' . count($exported_pages));
        error_log('LTP Debug: Errors: ' . count($errors));
        
        // Không còn site_settings toàn cục; đã nhúng theo từng trang tùy theo flag
        $site_settings_global = null;
        
        // Luôn trả về cùng một format: data.pages (mảng), kèm errors nếu có
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'pages' => $exported_pages,
                'errors' => $errors
            )
        ), 200);
    }
    
    /**
     * Parse URL và lấy page data
     */
    public static function parse_url_and_get_page_data($url) {
        // Ưu tiên dùng url_to_postid để resolve chính xác theo permalink
        $post_id = url_to_postid($url);
        $page = null;
        if ($post_id) {
            $page = get_post($post_id);
        }
        if (!$page) {
            $parsed_url = parse_url($url);
            if (!$parsed_url || !isset($parsed_url['path'])) {
                return false;
            }
            $path = trim($parsed_url['path'], '/');
            if (empty($path)) {
                return false;
            }
            // Tìm page bằng slug (fallback)
            $page = get_page_by_path($path);
        }
        if (!$page) {
            return false;
        }
        
        // Lấy elementor data
        $elementor_data = get_post_meta($page->ID, '_elementor_data', true);
        $elementor_page_settings = get_post_meta($page->ID, '_elementor_page_settings', true);

        // Chuẩn JSON export giống format yêu cầu: pages[*].data = {...}
        $decoded_content = is_string($elementor_data) ? json_decode($elementor_data, true) : $elementor_data;
        if (is_null($decoded_content)) {
            $decoded_content = array();
        }
        
        // Xử lý background-image URLs trong elementor data
        $decoded_content = self::process_background_images_in_export($decoded_content, $page->ID);
        $page_export_data = array(
            'content' => $decoded_content,
            'page_settings' => is_array($elementor_page_settings) ? $elementor_page_settings : array(),
            'version' => '0.4',
            'title' => $page->post_title, // Sẽ được override trong export_elementor_data_by_url
            'type' => 'page'
        );
        
        return array(
            'name' => '', // Sẽ được set trong export_elementor_data_by_url
            'page_id' => $page->ID,
            'page_title' => $page->post_title, // Sẽ được override trong export_elementor_data_by_url
            'page_url' => get_permalink($page->ID),
            // Dữ liệu export chuẩn để import: đặt tại key 'data'
            'data' => $page_export_data
        );
    }
    
    /**
     * Lấy Elementor Kit data (site settings)
     */
    public static function get_elementor_kit_data() {
        // Ưu tiên lấy trực tiếp từ Elementor Kit (post meta) để chính xác nhất
        if (class_exists('Elementor\Plugin')) {
            try {
                $kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
                if ($kit_id) {
                    $settings = get_post_meta($kit_id, '_elementor_page_settings', true);
                    if (!is_array($settings)) { $settings = array(); }
                    
                    error_log('Export API Debug: Kit ID: ' . $kit_id);
                    error_log('Export API Debug: Settings keys: ' . print_r(array_keys($settings), true));
                    error_log('Export API Debug: system_typography in settings: ' . (isset($settings['system_typography']) ? count($settings['system_typography']) . ' items' : 'NOT FOUND'));
                    
                    $system_colors = get_post_meta($kit_id, '_elementor_system_colors', true);
                    $custom_colors = get_post_meta($kit_id, '_elementor_custom_colors', true);
                    // Ưu tiên màu custom từ meta riêng mac_custom_colors nếu có
                    $mac_custom_colors = get_post_meta($kit_id, 'mac_custom_colors', true);
                    $system_typography = get_post_meta($kit_id, '_elementor_system_typography', true);
                    $custom_typography = get_post_meta($kit_id, '_elementor_custom_typography', true);

                    if (!isset($settings['system_colors'])) { $settings['system_colors'] = is_array($system_colors) ? $system_colors : array(); }
                    if (!isset($settings['custom_colors'])) {
                        if (is_array($mac_custom_colors) && !empty($mac_custom_colors)) {
                            // Xử lý mac_custom_colors để có format đúng
                            $settings['custom_colors'] = self::process_mac_custom_colors($mac_custom_colors);
                        } else {
                            $settings['custom_colors'] = is_array($custom_colors) ? $custom_colors : array();
                        }
                    } else {
                        // Nếu đã có custom_colors nhưng mac_custom_colors tồn tại, ghi đè để đúng màu đang dùng
                        if (is_array($mac_custom_colors) && !empty($mac_custom_colors)) {
                            $settings['custom_colors'] = self::process_mac_custom_colors($mac_custom_colors);
                        }
                    }
                    
                    // Tạo system_typography từ các font keys riêng biệt nếu chưa có
                    if (!isset($settings['system_typography']) || empty($settings['system_typography'])) {
                        error_log('Export API Debug: system_typography is empty, building from font keys');
                        $settings['system_typography'] = self::build_system_typography_from_font_keys($settings);
                        error_log('Export API Debug: Built system_typography: ' . count($settings['system_typography']) . ' items');
                    } else {
                        error_log('Export API Debug: system_typography already exists with ' . count($settings['system_typography']) . ' items');
                    }
                    
                    if (!isset($settings['custom_typography'])) { $settings['custom_typography'] = is_array($custom_typography) ? $custom_typography : array(); }

                    $kit_post = get_post($kit_id);
                    return array(
                        'content' => array(),
                        'settings' => $settings,
                        'metadata' => array(
                            'kit_id' => (string) $kit_id,
                            'kit_title' => $kit_post ? $kit_post->post_title : '',
                            'source' => 'kit_meta'
                        )
                    );
                }
            } catch (Exception $e) {
                // Fallback xuống logic cũ nếu có lỗi
            }
        }

        // Fallback: lấy từ options nếu không có Kit hoặc Elementor không hoạt động
        $system_colors = get_option('elementor_system_colors', array());
        $custom_colors = get_option('elementor_custom_colors', array());
        $system_typography = get_option('elementor_system_typography', array());
        $custom_typography = get_option('elementor_custom_typography', array());

        if (empty($system_colors)) {
            $system_colors = array(
                array('_id' => 'primary', 'title' => 'Primary', 'color' => '#F26212'),
                array('_id' => 'secondary', 'title' => 'Secondary', 'color' => '#6c757d'),
                array('_id' => 'text', 'title' => 'Text', 'color' => '#333333'),
                array('_id' => 'accent', 'title' => 'Accent', 'color' => '#28a745')
            );
        }
        if (empty($custom_colors)) {
            $custom_colors = array(
                array('_id' => '575bd41', 'title' => 'Black', 'color' => '#000000'),
                array('_id' => '041be46', 'title' => 'White', 'color' => '#FFFFFF'),
                array('_id' => '54f3520', 'title' => 'Transparent', 'color' => '#00000000'),
                array('_id' => 'a1b2c3d', 'title' => 'Background', 'color' => '#FFF4EE'),
                array('_id' => 'e4f5g6h', 'title' => 'Hover', 'color' => '#FF9256'),
                array('_id' => 'i7j8k9l', 'title' => 'Border', 'color' => '#F5F5F5'),
                array('_id' => 'm0n1o2p', 'title' => 'Success', 'color' => '#28A745'),
                array('_id' => 'q3r4s5t', 'title' => 'Warning', 'color' => '#FFC107')
            );
        }
        if (empty($system_typography)) {
            $system_typography = array(
                array(
                    '_id' => 'primary',
                    'title' => 'Primary',
                    'typography_typography' => 'custom',
                    'typography_font_family' => 'Clash Display',
                    'typography_font_weight' => '700',
                    'typography_font_size' => array('unit' => 'px', 'size' => 36, 'sizes' => array()),
                    'typography_font_size_tablet' => array('unit' => 'px', 'size' => 32, 'sizes' => array()),
                    'typography_font_size_mobile' => array('unit' => 'px', 'size' => 28, 'sizes' => array())
                ),
                array(
                    '_id' => 'secondary',
                    'title' => 'Secondary',
                    'typography_typography' => 'custom',
                    'typography_font_family' => 'Archivo',
                    'typography_font_weight' => '700',
                    'typography_font_size' => array('unit' => 'px', 'size' => 28, 'sizes' => array()),
                    'typography_font_size_tablet' => array('unit' => 'px', 'size' => 26, 'sizes' => array()),
                    'typography_font_size_mobile' => array('unit' => 'px', 'size' => 24, 'sizes' => array())
                )
            );
        }

        // Lấy body font từ settings hoặc mặc định
        $body_font = 'Archivo'; // Mặc định cho fallback

        return array(
            'content' => array(),
            'settings' => array(
                'template' => 'default',
                'viewport_md' => 768,
                'viewport_lg' => 1025,
                'colors_enable_styleguide_preview' => 'yes',
                'system_colors' => $system_colors,
                'custom_colors' => $custom_colors,
                'typography_enable_styleguide_preview' => 'yes',
                'system_typography' => $system_typography,
                'custom_typography' => $custom_typography,
                'default_generic_fonts' => 'Sans-serif',
                'page_title_selector' => 'h1.entry-title',
                'hello_footer_copyright_text' => 'All rights reserved',
                'activeItemIndex' => 1,
                '__globals__' => array(
                    'body_typography_typography' => '',
                    'button_typography_typography' => 'globals\/typography?id=text',
                    'button_background_color' => 'globals\/colors?id=primary',
                    'button_text_color' => 'globals\/colors?id=041be46',
                    'button_hover_background_color' => 'globals\/colors?id=68c5c02',
                    'button_hover_text_color' => 'globals\/colors?id=575bd41',
                    'link_hover_color' => 'globals\/colors?id=text',
                    'link_normal_color' => 'globals\/colors?id=text'
                ),
                'body_typography_font_size' => array(
                    'unit' => 'px',
                    'size' => 18,
                    'sizes' => array()
                ),
                'body_typography_font_size_mobile' => array(
                    'unit' => 'px',
                    'size' => 16,
                    'sizes' => array()
                ),
                'body_typography_font_weight' => '400',
                'h1_typography_typography' => 'custom',
                'h2_typography_typography' => 'custom',
                'h3_typography_typography' => 'custom',
                'body_typography_typography' => 'custom',
                'body_typography_font_family' => $body_font,
                'lightbox_enable_counter' => '',
                'lightbox_enable_zoom' => '',
                'lightbox_enable_share' => '',
                'lightbox_title_src' => '',
                'lightbox_description_src' => '',
                'link_normal_typography_typography' => 'custom',
                'h2_typography_font_family' => $body_font,
                'link_normal_typography_font_family' => $body_font,
                'h1_typography_font_family' => $body_font,
                'h2_typography_font_size_mobile' => array(
                    'unit' => 'px',
                    'size' => 22,
                    'sizes' => array()
                ),
                'h3_typography_font_family' => $body_font,
                'h3_typography_font_size_mobile' => array(
                    'unit' => 'px',
                    'size' => 20,
                    'sizes' => array()
                ),
                'h4_typography_typography' => 'custom',
                'h4_typography_font_family' => $body_font,
                'h4_typography_font_size_mobile' => array(
                    'unit' => 'px',
                    'size' => 18,
                    'sizes' => array()
                ),
                'h5_typography_typography' => 'custom',
                'h5_typography_font_family' => $body_font,
                'h6_typography_typography' => 'custom',
                'h6_typography_font_family' => $body_font
            ),
            'metadata' => array(
                'template_id' => '605',
                'template_name' => 'Imported Kit',
                'template_category' => 'Section',
                'source' => 'options_fallback'
            )
        );
    }
    
    /**
     * Xây site_settings theo đúng logic nút "export-site-settings" (mac-live-style)
     * Nguồn duy nhất: post meta 'mac_custom_colors' của 1 page cụ thể
     */
    public static function build_site_settings_from_mac_colors($page_id) {
        $mac_colors = get_post_meta($page_id, 'mac_custom_colors', true);
        if (is_string($mac_colors)) {
            $decoded = json_decode($mac_colors, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $mac_colors = $decoded;
            }
        }
        if (!is_array($mac_colors)) {
            $mac_colors = array();
        }
        
        // Debug: Log dữ liệu gốc
        error_log('MAC Export Debug: mac_custom_colors for page ' . $page_id . ': ' . print_r($mac_colors, true));

        // Xử lý mac_custom_colors - giữ nguyên cấu trúc gốc nếu có
        $system_colors = array();
        $custom_colors = array();
        
        // Tạo system_colors theo mapping chuẩn
        $color_mapping = array(
            'primary' => 'Primary',
            'secondary' => 'Secondary', 
            'text' => 'Text',
            'accent' => 'Accent',
        );
        
        // Tìm system colors trong mac_custom_colors
        foreach ($mac_colors as $item) {
            if (!isset($item['color'])) { continue; }
            
            $is_system_color = false;
            $key = '';
            
            // Kiểm tra qua field 'text' (--e-global-color-<key>)
            if (isset($item['text']) && is_string($item['text'])) {
                $key = str_replace('--e-global-color-', '', $item['text']);
                if (isset($color_mapping[$key])) {
                    $is_system_color = true;
                }
            }
            // Kiểm tra qua field 'name' 
            elseif (isset($item['name']) && is_string($item['name'])) {
                $name_key = sanitize_title($item['name']);
                if (isset($color_mapping[$name_key])) {
                    $key = $name_key;
                    $is_system_color = true;
                }
            }
            
            if ($is_system_color) {
                $system_colors[] = array(
                    '_id' => $key,
                    'title' => $color_mapping[$key],
                    'color' => $item['color']
                );
            } else {
                // Giữ nguyên cấu trúc gốc cho custom colors - sử dụng _id gốc hoặc mapping từ site-settings.json
                if (isset($item['_id'])) {
                    $custom_id = $item['_id'];
                } else {
                    // Mapping từ site-settings.json bạn đã gửi
                    $title = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : 'Custom');
                    $color = $item['color'];
                    
                    // Mapping dựa trên title và color
                    if (strtolower($title) === 'black' && $color === '#000000') {
                        $custom_id = '575bd41';
                    } elseif (strtolower($title) === 'white' && $color === '#ffffff') {
                        $custom_id = '041be46';
                    } elseif (strtolower($title) === 'transparent' && $color === '#00000000') {
                        $custom_id = '54f3520';
                    } else {
                        // Fallback: tạo từ title + color
                        $custom_id = substr(md5($title . $color), 0, 7);
                    }
                }
                
                $custom_colors[] = array(
                    '_id' => $custom_id,
                    'title' => isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : 'Custom Color'),
                    'color' => $item['color']
                );
            }
        }
        
        // Đảm bảo có đủ system colors (fallback nếu thiếu)
        foreach ($color_mapping as $k => $title) {
            $exists = false;
            foreach ($system_colors as $sc) {
                if ($sc['_id'] === $k) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $system_colors[] = array('_id' => $k, 'title' => $title, 'color' => '#000000');
            }
        }

        // Lấy fonts từ HTML elements (giống như button export)
        $current_fonts = self::extract_fonts_from_html($page_id);
        $system_typography = self::build_system_typography_from_fonts($current_fonts);
        $custom_typography = self::get_default_custom_typography();
        $body_font = isset($current_fonts['body']) ? $current_fonts['body'] : 'Archivo';

        return array(
            'content' => array(),
            'settings' => array(
                'template' => 'default',
                'viewport_md' => 768,
                'viewport_lg' => 1025,
                'colors_enable_styleguide_preview' => 'yes',
                'system_colors' => $system_colors,
                'custom_colors' => $custom_colors,
                'typography_enable_styleguide_preview' => 'yes',
                'system_typography' => $system_typography,
                'custom_typography' => $custom_typography,
                'default_generic_fonts' => 'Sans-serif',
                'page_title_selector' => 'h1.entry-title',
                'hello_footer_copyright_text' => 'All rights reserved',
                'activeItemIndex' => 1,
                '__globals__' => array(
                    'body_typography_typography' => '',
                    'button_typography_typography' => 'globals\/typography?id=text',
                    'button_background_color' => 'globals\/colors?id=primary',
                    'button_text_color' => 'globals\/colors?id=041be46',
                    'button_hover_background_color' => 'globals\/colors?id=68c5c02',
                    'button_hover_text_color' => 'globals\/colors?id=575bd41',
                    'link_hover_color' => 'globals\/colors?id=text',
                    'link_normal_color' => 'globals\/colors?id=text'
                ),
                'body_typography_font_size' => array(
                    'unit' => 'px',
                    'size' => 18,
                    'sizes' => array()
                ),
                'body_typography_font_size_mobile' => array(
                    'unit' => 'px',
                    'size' => 16,
                    'sizes' => array()
                ),
                'body_typography_font_weight' => '400',
                'h1_typography_typography' => 'custom',
                'h2_typography_typography' => 'custom',
                'h3_typography_typography' => 'custom',
                'body_typography_typography' => 'custom',
                'body_typography_font_family' => $body_font,
                'lightbox_enable_counter' => '',
                'lightbox_enable_zoom' => '',
                'lightbox_enable_share' => '',
                'lightbox_title_src' => '',
                'lightbox_description_src' => '',
                'link_normal_typography_typography' => 'custom',
                'h2_typography_font_family' => $body_font,
                'link_normal_typography_font_family' => $body_font,
                'h1_typography_font_family' => $body_font,
                'h2_typography_font_size_mobile' => array(
                    'unit' => 'px',
                    'size' => 22,
                    'sizes' => array()
                ),
                'h3_typography_font_family' => $body_font,
                'h3_typography_font_size_mobile' => array(
                    'unit' => 'px',
                    'size' => 20,
                    'sizes' => array()
                ),
                'h4_typography_typography' => 'custom',
                'h4_typography_font_family' => $body_font,
                'h4_typography_font_size_mobile' => array(
                    'unit' => 'px',
                    'size' => 18,
                    'sizes' => array()
                ),
                'h5_typography_typography' => 'custom',
                'h5_typography_font_family' => $body_font,
                'h6_typography_typography' => 'custom',
                'h6_typography_font_family' => $body_font
            ),
            'metadata' => array(
                'template_id' => '605',
                'template_name' => 'Imported Kit',
                'template_category' => 'Section',
                'source' => 'mac_custom_colors_export_site_settings_like'
            )
        );
    }
    
    /**
     * REST API endpoint để lấy elementor data (legacy)
     */
    public static function get_elementor_data_rest($request) {
        global $wpdb;
        
        // Lấy tất cả post có _elementor_data
        $results = $wpdb->get_results("
            SELECT p.ID, p.post_title, p.post_type, p.post_status, 
                   pm.meta_value as elementor_data
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE pm.meta_key = '_elementor_data'
            AND p.post_status = 'publish'
            ORDER BY p.post_date DESC
        ");
        
        $data = array();
        foreach ($results as $row) {
            $elementor_data = maybe_unserialize($row->elementor_data);
            if (!empty($elementor_data)) {
                $data[] = array(
                    'id' => $row->ID,
                    'title' => $row->post_title,
                    'type' => $row->post_type,
                    'elementor_data' => $elementor_data
                );
            }
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'count' => count($data),
            'data' => $data
        ), 200);
    }
    
    /**
     * Tạo system_typography từ các font keys riêng biệt
     */
    private static function build_system_typography_from_font_keys($settings) {
        $system_typography = array();
        
        // Lấy fonts từ các key riêng biệt
        $font_keys = array(
            'body_typography_font_family' => 'body',
            'h1_typography_font_family' => 'primary', 
            'h2_typography_font_family' => 'secondary',
            'h3_typography_font_family' => 'text',
            'h4_typography_font_family' => 'accent',
            'h5_typography_font_family' => 'h5',
            'h6_typography_font_family' => 'h6',
            'link_normal_typography_font_family' => 'link'
        );
        
        $used_fonts = array();
        
        foreach ($font_keys as $key => $typography_id) {
            if (isset($settings[$key]) && !empty($settings[$key])) {
                $font_family = $settings[$key];
                
                // Chỉ tạo typography nếu font chưa được sử dụng
                if (!in_array($font_family, $used_fonts)) {
                    $used_fonts[] = $font_family;
                    
                    $system_typography[] = array(
                        '_id' => $typography_id,
                        'title' => ucfirst($typography_id),
                        'typography_typography' => 'custom',
                        'typography_font_family' => $font_family,
                        'typography_font_weight' => isset($settings[$key . '_font_weight']) ? $settings[$key . '_font_weight'] : '400',
                        'typography_font_size' => isset($settings[$key . '_font_size']) ? $settings[$key . '_font_size'] : array(
                            'unit' => 'px',
                            'size' => 16,
                            'sizes' => array()
                        )
                    );
                }
            }
        }
        
        return $system_typography;
    }
    
    /**
     * Lấy fonts từ HTML elements (giống như colors)
     */
    private static function extract_fonts_from_html($page_id) {
        $current_fonts = array();
        
        // Lấy URL của page
        $page_url = get_permalink($page_id);
        if (!$page_url) {
            error_log('MAC Export Debug: Cannot get page URL for page ' . $page_id);
            return $current_fonts;
        }
        
        // Fetch HTML content
        $response = wp_remote_get($page_url);
        if (is_wp_error($response)) {
            error_log('MAC Export Debug: Failed to fetch HTML for page ' . $page_id . ': ' . $response->get_error_message());
            return $current_fonts;
        }
        
        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            error_log('MAC Export Debug: Empty HTML for page ' . $page_id);
            return $current_fonts;
        }
        
        // Parse HTML bằng regex để tìm font select elements
        $font_mapping = array(
            'primary-font' => 'primary',
            'secondary-font' => 'secondary', 
            'accent-font' => 'accent'
        );
        
        foreach ($font_mapping as $select_id => $typography_id) {
            // Tìm select element theo ID và option được selected
            $pattern = '/<select[^>]*id=["\']' . preg_quote($select_id, '/') . '["\'][^>]*>(.*?)<\/select>/s';
            if (preg_match($pattern, $html, $select_match)) {
                $select_content = $select_match[1];
                
                // Tìm option được selected
                $option_pattern = '/<option[^>]*selected[^>]*value=["\']([^"\']*)["\'][^>]*>/';
                if (preg_match($option_pattern, $select_content, $option_match)) {
                    $font_family = trim($option_match[1]);
                    if (!empty($font_family)) {
                        $current_fonts[$typography_id] = $font_family;
                        error_log("MAC Export Debug: Found font from HTML - $select_id: $font_family");
                    }
                }
            }
        }
        
        error_log('MAC Export Debug: Extracted ' . count($current_fonts) . ' fonts from HTML');
        return $current_fonts;
    }
    
    /**
     * Tạo system_typography từ current_fonts (giống như button export)
     */
    private static function build_system_typography_from_fonts($current_fonts) {
        $system_typography = array();
        $font_mapping = array(
            'primary' => 'Primary',
            'secondary' => 'Secondary',
            'text' => 'Text',
            'accent' => 'Accent'
        );

        foreach ($font_mapping as $font_key => $font_title) {
            $font_family = isset($current_fonts[$font_key]) ? $current_fonts[$font_key] : 'Arial';
            $system_typography[] = array(
                '_id' => $font_key,
                'title' => $font_title,
                'typography_typography' => 'custom',
                'typography_font_family' => $font_family,
                'typography_font_weight' => $font_key === 'primary' ? '700' : ($font_key === 'secondary' ? '700' : '400'),
                'typography_font_size' => array(
                    'unit' => 'px',
                    'size' => $font_key === 'primary' ? 36 : ($font_key === 'secondary' ? 28 : 18),
                    'sizes' => array()
                ),
                'typography_font_size_tablet' => array(
                    'unit' => 'px',
                    'size' => $font_key === 'primary' ? 32 : ($font_key === 'secondary' ? 26 : 17),
                    'sizes' => array()
                ),
                'typography_font_size_mobile' => array(
                    'unit' => 'px',
                    'size' => $font_key === 'primary' ? 28 : ($font_key === 'secondary' ? 24 : 16),
                    'sizes' => array()
                )
            );
        }
        
        return $system_typography;
    }
    
    /**
     * Lấy custom_typography mặc định (giống như button export)
     */
    private static function get_default_custom_typography() {
        return array(
            array(
                '_id' => '91b935f',
                'title' => 'Tertiary',
                'typography_typography' => 'custom',
                'typography_font_size' => array('unit' => 'px', 'size' => 20, 'sizes' => array()),
                'typography_font_size_tablet' => array('unit' => 'px', 'size' => 18, 'sizes' => array()),
                'typography_font_size_mobile' => array('unit' => 'px', 'size' => 17, 'sizes' => array()),
                'typography_font_weight' => '700'
            ),
            array(
                '_id' => '6c505cb',
                'title' => 'Large Heading',
                'typography_typography' => 'custom',
                'typography_font_size' => array('unit' => 'px', 'size' => 80, 'sizes' => array()),
                'typography_font_size_tablet' => array('unit' => 'px', 'size' => 60, 'sizes' => array()),
                'typography_font_size_mobile' => array('unit' => 'px', 'size' => 48, 'sizes' => array()),
                'typography_font_weight' => '700'
            ),
            array(
                '_id' => 'a48f8cb',
                'title' => 'Compact Heading',
                'typography_typography' => 'custom',
                'typography_font_size' => array('unit' => 'px', 'size' => 56, 'sizes' => array()),
                'typography_font_size_tablet' => array('unit' => 'px', 'size' => 42, 'sizes' => array()),
                'typography_font_size_mobile' => array('unit' => 'px', 'size' => 32, 'sizes' => array()),
                'typography_font_weight' => '700'
            ),
            array(
                '_id' => 'edd5d91',
                'title' => 'SEO',
                'typography_typography' => 'custom',
                'typography_font_size' => array('unit' => 'px', 'size' => 20, 'sizes' => array()),
                'typography_font_weight' => '600',
                'typography_line_height' => array('unit' => 'px', 'size' => 18, 'sizes' => array())
            )
        );
    }
    
    /**
     * Xử lý background-image URLs trong export data
     */
    private static function process_background_images_in_export($data, $page_id) {
        if (!is_array($data)) {
            return $data;
        }
        
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = self::process_background_images_in_export($value, $page_id);
            } elseif (is_string($value)) {
                // Xử lý background-image URLs trong CSS
                $value = self::process_css_background_image_urls_export($value, $page_id);
            }
        }
        
        return $data;
    }
    
    /**
     * Xử lý background-image URLs trong CSS string cho export
     */
    private static function process_css_background_image_urls_export($css_content, $page_id) {
        if (empty($css_content) || !is_string($css_content)) {
            return $css_content;
        }
        
        // Tìm tất cả background-image: url(...) trong CSS
        $patterns = array(
            '/background-image:\s*url\(["\']?([^"\']*?)["\']?\)/i',
            '/background:\s*url\(["\']?([^"\']*?)["\']?\)/i'
        );
        
        foreach ($patterns as $pattern) {
            $css_content = preg_replace_callback($pattern, function($matches) use ($page_id) {
                $image_url = $matches[1];
                if (self::is_image_url($image_url) && self::is_external_url($image_url)) {
                    error_log('Export API Debug: Found background-image URL: ' . $image_url);
                    // Trong export, chúng ta giữ nguyên URL để import API xử lý
                    return $matches[0];
                }
                return $matches[0];
            }, $css_content);
        }
        
        return $css_content;
    }
    
    /**
     * Kiểm tra xem có phải là image URL không
     */
    private static function is_image_url($url) {
        if (empty($url)) return false;
        
        $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico');
        $path_info = pathinfo(parse_url($url, PHP_URL_PATH));
        $extension = isset($path_info['extension']) ? strtolower($path_info['extension']) : '';
        
        return in_array($extension, $image_extensions);
    }
    
    /**
     * Kiểm tra xem có phải là external URL không
     */
    private static function is_external_url($url) {
        if (empty($url)) return false;
        
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) return false;
        
        $current_domain = parse_url(home_url(), PHP_URL_HOST);
        return $parsed['host'] !== $current_domain;
    }
    
    /**
     * Xử lý mac_custom_colors để có format đúng cho Elementor
     */
    private static function process_mac_custom_colors($mac_colors) {
        if (!is_array($mac_colors)) {
            return array();
        }
        
        $custom_colors = array();
        
        foreach ($mac_colors as $item) {
            if (!isset($item['color'])) { continue; }
            
            // Giữ nguyên _id gốc nếu có, nếu không thì tạo ID ngẫu nhiên như Elementor
            if (isset($item['_id'])) {
                $custom_id = $item['_id'];
            } else {
                // Tạo ID ngẫu nhiên 7 ký tự như Elementor thường làm
                $custom_id = substr(md5(uniqid() . $item['color']), 0, 7);
            }
            
            $custom_colors[] = array(
                '_id' => $custom_id,
                'title' => isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : 'Custom Color'),
                'color' => $item['color']
            );
        }
        
        return $custom_colors;
    }
    
    /**
     * Test custom colors format endpoint
     */
    public static function test_custom_colors_format($request) {
        // Test mac_custom_colors format với _id gốc và unique
        $test_mac_colors = array(
            array('_id' => '575bd41', 'title' => 'Black', 'color' => '#000000'),
            array('_id' => '041be46', 'title' => 'White', 'color' => '#ffffff'),
            array('title' => 'Red', 'color' => '#ff0000'), // Không có _id - sẽ tạo unique
            array('name' => 'Blue', 'color' => '#0000ff'), // Không có _id - sẽ tạo unique
            array('title' => 'Green', 'color' => '#00ff00') // Không có _id - sẽ tạo unique
        );
        
        $processed = self::process_mac_custom_colors($test_mac_colors);
        
        // Test Elementor Kit data
        $kit_data = self::get_elementor_kit_data();
        
        // Test fallback colors (khi không có mac_custom_colors)
        $fallback_colors = array(
            array('_id' => '575bd41', 'title' => 'Black', 'color' => '#000000'),
            array('_id' => '041be46', 'title' => 'White', 'color' => '#FFFFFF'),
            array('_id' => '54f3520', 'title' => 'Transparent', 'color' => '#00000000')
        );
        
        return new WP_REST_Response(array(
            'success' => true,
            'test_mac_colors' => $test_mac_colors,
            'processed_custom_colors' => $processed,
            'fallback_colors' => $fallback_colors,
            'kit_custom_colors' => isset($kit_data['settings']['custom_colors']) ? $kit_data['settings']['custom_colors'] : 'Not found',
            'message' => 'Custom colors format test completed - giữ nguyên _id gốc cả fallback'
        ), 200);
    }
}
