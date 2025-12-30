<?php
/**
 * Import API for MAC Importer Demo
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Importer_Import_API {
    
    // Option để skip download images khi import
    const SKIP_DOWNLOAD_IMAGES = true; // true = skip download, false = download ngay
    
    /**
     * Register import API endpoints
     */
    public static function register_endpoints() {
        // Import page API
        register_rest_route('ltp/v1', '/elementor/import-page', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'import_pages_from_domain'),
            'permission_callback' => '__return_true'
        ));
        
        // Diagnostic endpoint để kiểm tra route import có hoạt động không
        register_rest_route('ltp/v1', '/elementor/import-page/ping', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array(__CLASS__, 'import_ping')
        ));
        
        // Temporary self-test endpoint to verify Elementor import pipeline end-to-end
        register_rest_route('ltp/v1', '/elementor/test-import', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array(__CLASS__, 'test_import')
        ));
        
        // Test image processing endpoint
        register_rest_route('ltp/v1', '/elementor/test-images', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array(__CLASS__, 'test_image_processing')
        ));
        
        // Test background-image processing endpoint
        register_rest_route('ltp/v1', '/elementor/test-background-images', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array(__CLASS__, 'test_background_image_processing')
        ));
        
        // Test custom_colors import endpoint
        register_rest_route('ltp/v1', '/elementor/test-custom-colors-import', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array(__CLASS__, 'test_custom_colors_import')
        ));
        
        // Test simple settings import endpoint
        register_rest_route('ltp/v1', '/elementor/test-simple-settings-import', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array(__CLASS__, 'test_simple_settings_import')
        ));
        
        // Test basic functionality endpoint
        register_rest_route('ltp/v1', '/elementor/test-basic', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array(__CLASS__, 'test_basic_functionality')
        ));
        
        // Test real import endpoint
        register_rest_route('ltp/v1', '/elementor/test-real-import', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array(__CLASS__, 'test_real_import')
        ));
        
        // Test override primary color endpoint
        register_rest_route('ltp/v1', '/elementor/test-override-primary', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array(__CLASS__, 'test_override_primary_color')
        ));
        
        // Test debug import settings endpoint
        register_rest_route('ltp/v1', '/elementor/test-debug-import', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array(__CLASS__, 'test_debug_import_settings')
        ));
        
        // Download all external images API
        register_rest_route('ltp/v1', '/elementor/download-images', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'download_images_endpoint'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Import pages từ domain khác
     */
    public static function import_pages_from_domain($request) {
        // Log chẩn đoán ngay khi endpoint được gọi
        $raw_body = @file_get_contents('php://input');
        $raw_preview = is_string($raw_body) ? substr($raw_body, 0, 1000) : '';
        
        // Lấy parameters từ request
        $auth_key = $request->get_param('auth_key');
        $site_settings = $request->get_param('site_settings');
        // Chỉ hỗ trợ single page
        $page = $request->get_param('page');
        
        // Tự động lấy domain từ request URL (bỏ requirement domain parameter)
        $domain = self::get_domain_from_request($request);
        
        // Kiểm tra nếu nhận được data từ API export (có data field)
        $export_data = $request->get_param('data');
        if (!empty($export_data)) {
            $site_settings = isset($export_data['site_settings']) ? $export_data['site_settings'] : $site_settings;
            // Chỉ xử lý single page
            if (isset($export_data['page']) && !empty($export_data['page'])) {
                // Nếu page có name (cấu trúc đầy đủ), lấy luôn
                if (isset($export_data['page']['name'])) {
                    $page = $export_data['page'];
                } 
                // Nếu page chỉ có content (cấu trúc đơn giản), tạo page object
                elseif (isset($export_data['page']['content'])) {
                    // Lấy title từ page object nếu có
                    $page_title = 'Imported Page';
                    if (isset($export_data['page']['title'])) {
                        $page_title = $export_data['page']['title'];
                    }
                    
                    $page = array(
                        'name' => $page_title,
                        'page_title' => $page_title,
                        'data' => array(
                            'content' => $export_data['page']['content']
                        )
                    );
                }
            }
            // Lấy page từ data.page nếu có (format mới với data.page)
            elseif (isset($export_data['page']) && is_array($export_data['page'])) {
                $page = $export_data['page'];
            }
        }
        
        // Kiểm tra nếu nhận được toàn bộ response từ API export
        $export_response = $request->get_param('export_response');
        if (!empty($export_response) && isset($export_response['data'])) {
            // Lấy page từ pages array (lấy page đầu tiên)
            if (isset($export_response['data']['pages']) && is_array($export_response['data']['pages']) && !empty($export_response['data']['pages'])) {
                $page = $export_response['data']['pages'][0]; // Lấy page đầu tiên
            }
            // Fallback: nếu có page trực tiếp (format cũ)
            elseif (isset($export_response['data']['page'])) {
                $page = $export_response['data']['page'];
            }
            
            // Lấy site_settings từ page nếu có
            if (isset($page['site_settings']) && !empty($page['site_settings'])) {
                $site_settings = $page['site_settings'];
            }
        }
        
        // Lấy templates từ request (ưu tiên theo thứ tự)
        $templates = array();
        // 1. Từ export_response (toàn bộ response từ export API) - data.templates[]
        if (!empty($export_response) && isset($export_response['data']['templates']) && is_array($export_response['data']['templates'])) {
            $templates = $export_response['data']['templates'];
        }
        // 2. Từ export_data (nested trong data)
        if (empty($templates) && isset($export_data['templates']) && is_array($export_data['templates'])) {
            $templates = $export_data['templates'];
        }
        // 3. Từ parameter trực tiếp
        if (empty($templates)) {
            $templates_param = $request->get_param('templates');
            if (is_array($templates_param)) {
                $templates = $templates_param;
            }
        }
        // Validate
        if (empty($templates) || !is_array($templates)) {
            $templates = array();
        }
        
        // Validate input
        if (empty($auth_key)) {
            return MAC_Importer_API_Base::create_error_response(400, 'Missing required parameter: auth_key');
        }
        
        
        // Đánh dấu có page hay không (single only); nếu trống sẽ bỏ qua không báo lỗi
        $has_single_page = is_array($page) && !empty($page);
        
        // Tăng timeout và memory limit
        set_time_limit(600); // 10 phút
        ini_set('memory_limit', '1024M'); // 1GB
        
        $results = array();
        $errors = array();
        $success_count = 0;
        
        try {
            // Theo yêu cầu mới: KHÔNG auto-fetch site_settings khi thiếu → bỏ qua nếu trống

            // Tắt Content Sanitizer trong suốt quá trình import để tránh cảnh báo widgetType trên hosting
            $ltp_disable_sanitizer_cb = function() { return false; };
            add_filter('elementor/content_sanitizer/enabled', $ltp_disable_sanitizer_cb, 10, 0);
            // Đảm bảo container được bật trong session editor khi lưu
            $ltp_enable_container_cb = function($settings){
                if (is_array($settings)) {
                    if (!isset($settings['features'])) { $settings['features'] = array(); }
                    $settings['features']['container'] = true;
                }
                return $settings;
            };
            add_filter('elementor/editor/localize_settings', $ltp_enable_container_cb, 10, 1);
            
            // Import site settings nếu có (bỏ qua nếu trống)
            if (is_array($site_settings) && !empty($site_settings)) {
               
                $site_result = self::import_site_settings_from_data($site_settings);
                if (is_wp_error($site_result)) {
                   
                    $errors[] = 'Site Settings: ' . $site_result->get_error_message();
                } else {
                   
                    $success_count++;
                    $results[] = 'Site settings imported successfully';
                }
            }
            
            // Import templates trước (nếu có) để tạo mapping
            $template_id_mapping = array();
            if (!empty($templates) && is_array($templates)) {
                foreach ($templates as $template_data) {
                    $template_result = self::import_template_from_data($template_data);
                    if (!is_wp_error($template_result)) {
                        $old_template_id = isset($template_data['template_id']) ? $template_data['template_id'] : null;
                        if ($old_template_id) {
                            $template_id_mapping[$old_template_id] = $template_result;
                            $success_count++;
                            $template_name = isset($template_data['template_name']) ? $template_data['template_name'] : 'Template';
                            $results[] = 'Template "' . $template_name . '" imported successfully (ID: ' . $template_result . ')';
                        }
                    } else {
                        $template_name = isset($template_data['template_name']) ? $template_data['template_name'] : 'Template';
                        $errors[] = 'Template "' . $template_name . '": ' . $template_result->get_error_message();
                    }
                }
            }
            
            // Import single page nếu có (bỏ qua nếu trống)
            if ($has_single_page) {
                $page_data = $page;
                $page_name = isset($page_data['name']) ? $page_data['name'] : 'page';
                
                // Replace template IDs trong page content nếu có templates
                if (!empty($template_id_mapping) && isset($page_data['data']['content'])) {
                    $page_data['data']['content'] = self::replace_template_ids_in_content(
                        $page_data['data']['content'],
                        $template_id_mapping
                    );
                }
                
                $page_result = self::import_page_from_data($page_data);
                if (is_wp_error($page_result)) {
                    $errors[] = $page_name . ': ' . $page_result->get_error_message();
                } else {
                    $success_count++;
                    $results[] = $page_name . ' imported successfully (ID: ' . $page_result . ', Slug: ' . get_post_field('post_name', $page_result) . ')';
                }
            }
            
            // Tạo response message dạng text
            $message = '';
            if ($success_count > 0) {
                $message .= "Import completed successfully! {$success_count} items imported.";
            }
            
            if (!empty($results)) {
                $message .= " -- Success Details: ";
                $message .= implode(' -- ', $results);
            }
            
            if (!empty($errors)) {
                $message .= " -- Errors: ";
                $message .= implode(' -- ', $errors);
            }
            
            // Trả về kết quả
            if (empty($errors)) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => $message,
                    'imported_count' => $success_count
                ), 200);
            } else {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => $message,
                    'imported_count' => $success_count,
                    'error_count' => count($errors)
                ), 200);
            }
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'code' => 500,
                'message' => 'Import failed: ' . $e->getMessage()
            ), 200);
        } finally {
            // Gỡ filter để không ảnh hưởng phiên khác
            if (isset($ltp_disable_sanitizer_cb)) {
                remove_filter('elementor/content_sanitizer/enabled', $ltp_disable_sanitizer_cb, 10);
            }
            if (isset($ltp_enable_container_cb)) {
                remove_filter('elementor/editor/localize_settings', $ltp_enable_container_cb, 10);
            }
        }
    }
    
    /**
     * Tạo slug unique cho page (tránh trùng lặp)
     */
    public static function get_unique_page_slug($base_slug) {
        $original_slug = $base_slug;
        $counter = 1;
        
        // Kiểm tra slug gốc có trùng không
        $existing_page = get_page_by_path($base_slug, OBJECT, 'page');
        if (!$existing_page) {
            return $base_slug; // Slug gốc không trùng, dùng luôn
        }
        
        // Tìm slug unique bằng cách thêm số
        do {
            $new_slug = $original_slug . '-' . $counter;
            $existing_page = get_page_by_path($new_slug, OBJECT, 'page');
            $counter++;
        } while ($existing_page);
        
        return $new_slug;
    }
    
    /**
     * Tự động lấy domain từ request URL
     */
    public static function get_domain_from_request($request) {
        // Lấy từ HTTP_HOST và REQUEST_SCHEME
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https';
        
        if (empty($host)) {
            // Fallback: lấy từ HTTP_REFERER hoặc SERVER_NAME
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            if (!empty($referer)) {
                $parsed = wp_parse_url($referer);
                if ($parsed && isset($parsed['host'])) {
                    $host = $parsed['host'];
                    $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : 'https';
                }
            }
        }
        
        if (empty($host)) {
            // Fallback cuối cùng: lấy từ SERVER_NAME
            $host = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
        }
        
        if (empty($host)) {
            return '';
        }
        
        return $scheme . '://' . $host;
    }
    
    /**
     * Gọi API domain khác để lấy data
     */
    public static function fetch_data_from_domain($domain, $auth_key, $pages_request) {
        $api_url = rtrim($domain, '/') . '/wp-json/ltp/v1/elementor/export-page';
        
        $request_data = array(
            'action' => 'get_data',
            'auth_key' => $auth_key,
            'pages' => $pages_request // Send the full pages array to the remote export API
        );
        
        $response = wp_remote_post($api_url, array(
            'method' => 'POST',
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $auth_key
            ),
            'body' => json_encode($request_data)
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('remote_request_failed', 'Failed to connect to remote domain: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('remote_request_failed', 'Remote domain returned error code: ' . $response_code);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_failed', 'Failed to decode response from remote domain');
        }
        
        if (!isset($data['success']) || !$data['success']) {
            return new WP_Error('remote_api_error', 'Remote API returned error: ' . (isset($data['message']) ? $data['message'] : 'Unknown error'));
        }
        
        return $data;
    }
    
    /**
     * Import site settings từ data
     */
    public static function import_site_settings_from_data($site_settings) {
        if (!class_exists('Elementor\Plugin')) {
            return new WP_Error('elementor_not_active', 'Elementor is not active.');
        }
        
        try {
            // Lấy active kit
            $kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
            
            if (!$kit_id) {
                // Tạo kit mới nếu chưa có
                $kit = new \Elementor\Core\Kits\Documents\Kit();
                $kit_id = $kit->save(array());
            }
            
            if (!$kit_id) {
                return new WP_Error('kit_creation_failed', 'Failed to create or get active kit.');
            }
            
            // Lấy settings từ structure (site_settings có thể là object hoặc array)
            if (isset($site_settings['settings'])) {
                $settings = $site_settings['settings'];
            } else {
                $settings = $site_settings;
            }
            
            
            // Cập nhật site settings (giống plugin cũ)
            update_post_meta($kit_id, '_elementor_page_settings', $settings);
            
            // Cập nhật các settings riêng biệt (giống plugin cũ)
            if (isset($settings['system_colors'])) {
                // Lưu system_colors - giữ nguyên màu 8 ký tự hex (có alpha) nếu có
                // Không dùng sanitize_hex_color() vì nó chỉ hỗ trợ 6 ký tự
                update_post_meta($kit_id, '_elementor_system_colors', $settings['system_colors']);
            }
            
            // Define default custom colors that should always exist
            $default_custom_colors = array(
                array(
                    '_id' => '575bd41',
                    'title' => 'Black',
                    'color' => '#000000'
                ),
                array(
                    '_id' => '041be46',
                    'title' => 'White',
                    'color' => '#FFFFFF'
                ),
                array(
                    '_id' => '54f3520',
                    'title' => 'Transparent',
                    'color' => '#00000000' // 8 ký tự hex với alpha = 00 (transparent)
                )
            );
            
            if (isset($settings['custom_colors']) && is_array($settings['custom_colors']) && !empty($settings['custom_colors'])) {
                // Merge with default colors: keep JSON colors, add missing default colors
                $json_colors = $settings['custom_colors'];
                $merged_colors = $json_colors;
                
                // Get existing _id values from JSON colors
                $existing_ids = array();
                foreach ($json_colors as $color) {
                    if (isset($color['_id'])) {
                        $existing_ids[] = $color['_id'];
                    }
                }
                
                // Add default colors that are missing
                foreach ($default_custom_colors as $default_color) {
                    if (!in_array($default_color['_id'], $existing_ids)) {
                        $merged_colors[] = $default_color;
                    }
                }
                
                // Lưu custom_colors - giữ nguyên màu 8 ký tự hex (có alpha) nếu có
                // Không dùng sanitize_hex_color() vì nó chỉ hỗ trợ 6 ký tự
                update_post_meta($kit_id, '_elementor_custom_colors', $merged_colors);
            } else {
                // Fallback: Use default custom colors if not provided in settings
                update_post_meta($kit_id, '_elementor_custom_colors', $default_custom_colors);
            }
            
            if (isset($settings['system_typography'])) {
                update_post_meta($kit_id, '_elementor_system_typography', $settings['system_typography']);
               
            }
            
            if (isset($settings['custom_typography'])) {
                update_post_meta($kit_id, '_elementor_custom_typography', $settings['custom_typography']);
               
            }
            
            // Build typography mapping from system_typography
            $typography_map = array();
            if (isset($settings['system_typography']) && is_array($settings['system_typography'])) {
                foreach ($settings['system_typography'] as $typography) {
                    if (isset($typography['_id'])) {
                        $typography_map[$typography['_id']] = $typography;
                    }
                }
            }
            
            // Mapping: which typography ID to use for each field type
            $field_to_typography_map = array(
                'h1' => 'primary',
                'h2' => 'secondary',
                'h3' => 'secondary',
                'h4' => 'secondary',
                'h5' => 'secondary',
                'h6' => 'secondary',
                'body' => 'text',
                'link_normal' => 'text'
            );
            
            // Default values (fallback if not found in system_typography or settings)
            $default_font_family = 'Poppins';
            $default_font_sizes = array(
                'h1' => array('size' => 36, 'tablet' => 32, 'mobile' => 28),
                'h2' => array('size' => 28, 'tablet' => 26, 'mobile' => 24),
                'h3' => array('size' => 24, 'tablet' => 22, 'mobile' => 20),
                'h4' => array('size' => 20, 'tablet' => 18, 'mobile' => 18),
                'h5' => array('size' => 18, 'tablet' => 17, 'mobile' => 16),
                'h6' => array('size' => 16, 'tablet' => 15, 'mobile' => 14),
                'body' => array('size' => 18, 'tablet' => 17, 'mobile' => 16),
                'link_normal' => array('size' => 18, 'tablet' => 17, 'mobile' => 16)
            );
            
            // Auto-fill missing font_family fields
            foreach ($field_to_typography_map as $field_type => $typography_id) {
                $field_name = $field_type . '_typography_font_family';
                
                if (!isset($settings[$field_name]) || empty($settings[$field_name])) {
                    // Priority 1: Get from system_typography mapping
                    if (isset($typography_map[$typography_id]) && isset($typography_map[$typography_id]['typography_font_family'])) {
                        $settings[$field_name] = $typography_map[$typography_id]['typography_font_family'];
                    }
                    // Priority 2: Use default
                    elseif ($default_font_family) {
                        $settings[$field_name] = $default_font_family;
                    }
                }
            }
            
            // Auto-fill missing font_size fields
            foreach ($field_to_typography_map as $field_type => $typography_id) {
                // Desktop size
                $field_name = $field_type . '_typography_font_size';
                if (!isset($settings[$field_name]) || empty($settings[$field_name])) {
                    // Priority 1: Use default for this specific field type
                    if (isset($default_font_sizes[$field_type])) {
                        $settings[$field_name] = array(
                            'unit' => 'px',
                            'size' => $default_font_sizes[$field_type]['size'],
                            'sizes' => array()
                        );
                    }
                    // Priority 2: Fallback to system_typography mapping if default not available
                    elseif (isset($typography_map[$typography_id]) && isset($typography_map[$typography_id]['typography_font_size'])) {
                        $settings[$field_name] = $typography_map[$typography_id]['typography_font_size'];
                    }
                }
                
                // Tablet size
                $field_name_tablet = $field_type . '_typography_font_size_tablet';
                if (!isset($settings[$field_name_tablet]) || empty($settings[$field_name_tablet])) {
                    // Priority 1: Use default for this specific field type
                    if (isset($default_font_sizes[$field_type])) {
                        $settings[$field_name_tablet] = array(
                            'unit' => 'px',
                            'size' => $default_font_sizes[$field_type]['tablet'],
                            'sizes' => array()
                        );
                    }
                    // Priority 2: Fallback to system_typography mapping if default not available
                    elseif (isset($typography_map[$typography_id]) && isset($typography_map[$typography_id]['typography_font_size_tablet'])) {
                        $settings[$field_name_tablet] = $typography_map[$typography_id]['typography_font_size_tablet'];
                    }
                }
                
                // Mobile size
                $field_name_mobile = $field_type . '_typography_font_size_mobile';
                if (!isset($settings[$field_name_mobile]) || empty($settings[$field_name_mobile])) {
                    // Priority 1: Use default for this specific field type
                    if (isset($default_font_sizes[$field_type])) {
                        $settings[$field_name_mobile] = array(
                            'unit' => 'px',
                            'size' => $default_font_sizes[$field_type]['mobile'],
                            'sizes' => array()
                        );
                    }
                    // Priority 2: Fallback to system_typography mapping if default not available
                    elseif (isset($typography_map[$typography_id]) && isset($typography_map[$typography_id]['typography_font_size_mobile'])) {
                        $settings[$field_name_mobile] = $typography_map[$typography_id]['typography_font_size_mobile'];
                    }
                }
            }
            
            // Update settings again after auto-filling fonts
            update_post_meta($kit_id, '_elementor_page_settings', $settings);
            
            // Enqueue fonts từ typography settings
            self::enqueue_fonts_from_settings($settings);
            
            // Clear cache (giống plugin cũ)
            \Elementor\Plugin::$instance->files_manager->clear_cache();
            delete_transient('elementor_kit_' . $kit_id);
            
            // Bật Flexbox Containers (giống plugin cũ)
            update_option('elementor_experiment-container', 'active');
            
            return true;
            
        } catch (Exception $e) {
            return new WP_Error('import_error', 'Error importing site settings: ' . $e->getMessage());
        }
    }
    
    /**
     * Import page từ data
     */
    public static function import_page_from_data($page_data) {
        if (!class_exists('Elementor\Plugin')) {
            return new WP_Error('elementor_not_active', 'Elementor is not active.');
        }
        
        try {
            // Lấy tiêu đề trang (ưu tiên page_title, fallback sang name, cuối cùng là title)
            $page_title = 'Page';
            if (isset($page_data['page_title']) && $page_data['page_title'] !== '') {
                $page_title = $page_data['page_title'];
            } elseif (isset($page_data['name']) && $page_data['name'] !== '') {
                $page_title = $page_data['name'];
            } elseif (isset($page_data['title']) && $page_data['title'] !== '') {
                $page_title = $page_data['title'];
            }

            // Tạo slug từ tiêu đề và tìm slug unique
            $base_slug = sanitize_title($page_title);
            $page_slug = self::get_unique_page_slug($base_slug);
            
            // Tạo page mới (luôn tạo mới, không update existing)
            $post_data = array(
                'post_title' => $page_title,
                'post_name' => $page_slug,
                'post_status' => 'publish',
                'post_type' => 'page',
            );
            
            $page_id = wp_insert_post($post_data);
            
            if (is_wp_error($page_id)) {
                return $page_id;
            }
            
            // Log thông tin slug được tạo
            
            // Tìm elementor data từ các cấu trúc khác nhau
            $elementor_data = null;
            
            
            // Cấu trúc 1: page_data['data']['content'] (từ export API)
            if (isset($page_data['data']) && isset($page_data['data']['content'])) {
                $elementor_data = $page_data['data']['content'];
            }
            // Cấu trúc 1.5: page_data['content'] (từ pages object trực tiếp)
            elseif (isset($page_data['content'])) {
                $elementor_data = $page_data['content'];
            }
            // Cấu trúc 2: page_data['elementor_data'] (từ import cũ)
            elseif (isset($page_data['elementor_data'])) {
                $elementor_data = $page_data['elementor_data'];
            }
            // Cấu trúc 3: page_data['content'] (template object trực tiếp)
            elseif (isset($page_data['content'])) {
                $elementor_data = $page_data['content'];
            }
            // Cấu trúc 4: page_data chính là array elements
            elseif (is_array($page_data) && isset($page_data[0]['elType'])) {
                $elementor_data = $page_data;
            }
            // Cấu trúc 5: Kiểm tra các key khác có thể chứa elementor data
            elseif (isset($page_data['elements'])) {
                $elementor_data = $page_data['elements'];
            }
            // Cấu trúc 6: Kiểm tra trong data nhưng không có content
            elseif (isset($page_data['data']) && is_array($page_data['data'])) {
                // Tìm trong data xem có elementor content không
                foreach ($page_data['data'] as $key => $value) {
                    if (is_array($value) && isset($value[0]['elType'])) {
                        $elementor_data = $value;
                        break;
                    }
                }
            }
            
            // Debug logging
            if (isset($page_data['data'])) {
            }
            
            if (is_null($elementor_data)) {
                
                // Thử fallback: tìm kiếm trong toàn bộ cấu trúc
                $elementor_data = self::find_elementor_data_recursive($page_data);
                if (is_null($elementor_data)) {
                    return new WP_Error('json_error', 'Invalid elementor data format - no content found');
                }
            }
            
            // Parse nếu là string
            if (is_string($elementor_data)) {
                $original_data = $elementor_data; // Lưu lại dữ liệu gốc
                $elementor_data = json_decode($elementor_data, true);
                
                // Nếu JSON decode thất bại, thử sửa lỗi JSON
                if (is_null($elementor_data)) {
                    $fixed_json = self::fix_elementor_json($original_data);
                    if ($fixed_json) {
                        $elementor_data = json_decode($fixed_json, true);
                    }
                }
            }
            
            if (is_null($elementor_data)) {
                return new WP_Error('json_error', 'Invalid elementor data format - JSON parse failed');
            }
            
            // Debug: Log elementor data structure
            if (is_array($elementor_data)) {
                if (!empty($elementor_data) && isset($elementor_data[0])) {
                }
            }
            
            // Chuẩn hóa dữ liệu
            $normalized_data = self::normalize_elementor_template_data($elementor_data);
            if (is_wp_error($normalized_data)) {
                return $normalized_data;
            }
            
            // Áp dụng template vào page
            $result = self::apply_elementor_template_to_page($normalized_data, $page_id);
            if (is_wp_error($result)) {
                return $result;
            }
            
            // Cập nhật page settings nếu có
            $page_settings = null;
            if (isset($page_data['elementor_page_settings'])) {
                $page_settings = $page_data['elementor_page_settings'];
            } elseif (isset($page_data['data']['page_settings'])) {
                $page_settings = $page_data['data']['page_settings'];
            }
            
            if ($page_settings) {
                update_post_meta($page_id, '_elementor_page_settings', $page_settings);
            }
            
            return $page_id;
            
        } catch (Exception $e) {
            return new WP_Error('import_error', 'Error importing page: ' . $e->getMessage());
        }
    }
    
    /**
     * Tìm kiếm elementor data một cách đệ quy trong cấu trúc dữ liệu
     */
    public static function find_elementor_data_recursive($data, $depth = 0) {
        // Giới hạn độ sâu để tránh vòng lặp vô hạn
        if ($depth > 5) {
            return null;
        }
        
        if (is_array($data)) {
            // Kiểm tra xem có phải array của elements không
            if (isset($data[0]['elType']) || isset($data[0]['id'])) {
                return $data;
            }
            
            // Tìm kiếm trong các key phổ biến
            $common_keys = ['content', 'elements', 'elementor_data', 'data'];
            foreach ($common_keys as $key) {
                if (isset($data[$key]) && is_array($data[$key])) {
                    $result = self::find_elementor_data_recursive($data[$key], $depth + 1);
                    if (!is_null($result)) {
                        return $result;
                    }
                }
            }
            
            // Tìm kiếm trong tất cả các phần tử của array
            foreach ($data as $value) {
                if (is_array($value)) {
                    $result = self::find_elementor_data_recursive($value, $depth + 1);
                    if (!is_null($result)) {
                        return $result;
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Sửa lỗi JSON từ Elementor export
     */
    public static function fix_elementor_json($json_content) {
        if (empty($json_content)) {
            return false;
        }
        
        // Sửa các lỗi JSON phổ biến
        $fixed = $json_content;
        
        // Sửa lỗi trailing comma
        $fixed = preg_replace('/,(\s*[}\]])/', '$1', $fixed);
        
        // Sửa lỗi single quotes
        $fixed = str_replace("'", '"', $fixed);
        
        // Sửa lỗi unescaped quotes trong strings
        $fixed = preg_replace('/(?<!\\\\)"(?=.*":)/', '\\"', $fixed);
        
        return $fixed;
    }
    
    /**
     * Normalize elementor template data
     */
    public static function normalize_elementor_template_data($data) {
        if (empty($data) || !is_array($data)) {
            return new WP_Error('invalid_data', 'Invalid elementor data format');
        }
        
        // Đảm bảo data có cấu trúc đúng
        if (!isset($data[0]['elType'])) {
            return new WP_Error('invalid_structure', 'Elementor data must be an array of elements');
        }
        
        // Chuẩn hóa data
        $normalized = array();
        foreach ($data as $element) {
            if (isset($element['elType'])) {
                $normalized[] = $element;
            }
        }
        
        return $normalized;
    }
    
    /**
     * Apply elementor template to page
     */
    public static function apply_elementor_template_to_page($data, $page_id) {
        if (!class_exists('Elementor\Plugin')) {
            return new WP_Error('elementor_not_active', 'Elementor is not active.');
        }
        
        try {
            
            // Process external images trước khi lưu data
            $processed_data = self::process_external_images($data, $page_id);
            
            // Process CSS background images
            $processed_data = self::process_css_external_images($processed_data, $page_id);
            
            // Process HTML content và Jet Portfolio images
            $processed_data = self::process_html_external_images($processed_data, $page_id);
            
            
            // Lưu elementor data vào page - dùng wp_slash() như Elementor làm
            if (is_array($processed_data)) {
                $json_data = wp_json_encode($processed_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $slashed_data = wp_slash($json_data);
            } else {
                $slashed_data = wp_slash($processed_data);
            }
            
            $result = update_post_meta($page_id, '_elementor_data', $slashed_data);
            
            if (!$result) {
                return new WP_Error('meta_update_failed', 'Failed to save elementor data to page');
            }
            
            // Đánh dấu page là elementor page
            update_post_meta($page_id, '_elementor_edit_mode', 'builder');
            update_post_meta($page_id, '_elementor_template_type', 'page');
            update_post_meta($page_id, '_elementor_version', ELEMENTOR_VERSION);
            
            // Chỉ cập nhật pro version nếu có
            if (defined('ELEMENTOR_PRO_VERSION')) {
                update_post_meta($page_id, '_elementor_pro_version', constant('ELEMENTOR_PRO_VERSION'));
            }
            
            // Cập nhật CSS cache
            \Elementor\Plugin::$instance->files_manager->clear_cache();
            
            // Cập nhật page status
            wp_update_post(array(
                'ID' => $page_id,
                'post_status' => 'publish'
            ));
            
            return true;
            
        } catch (Exception $e) {
            return new WP_Error('apply_error', 'Error applying elementor template: ' . $e->getMessage());
        }
    }
    
    
    /**
     * Clear Elementor cache mạnh hơn
     */
    public static function clear_elementor_cache($kit_id) {
        if (!class_exists('Elementor\Plugin')) {
            return;
        }
        
        try {
            // Clear Elementor files cache
            \Elementor\Plugin::$instance->files_manager->clear_cache();
            
            // Clear kit cache
            delete_transient('elementor_kit_' . $kit_id);
            
            // Clear all Elementor transients
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_elementor_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_elementor_%'");
            
            // Clear Elementor CSS cache
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_elementor_css_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_elementor_css_%'");
            
            // Clear Elementor Kit cache
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_elementor_kit_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_elementor_kit_%'");
            
            // Force regenerate CSS
            if (method_exists(\Elementor\Plugin::$instance->files_manager, 'clear_cache')) {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            }
            
            
        } catch (Exception $e) {
        }
    }
    
    /**
     * Enqueue fonts từ typography settings
     */
    public static function enqueue_fonts_from_settings($settings) {
        $fonts = array();
        
        // Lấy fonts từ system_typography
        if (isset($settings['system_typography']) && is_array($settings['system_typography'])) {
            foreach ($settings['system_typography'] as $index => $typography) {
                if (isset($typography['typography_font_family']) && !empty($typography['typography_font_family'])) {
                    $font = $typography['typography_font_family'];
                    $fonts[] = $font;
                }
            }
        } else {
        }
        
        // Lấy fonts từ custom_typography
        if (isset($settings['custom_typography']) && is_array($settings['custom_typography'])) {
            foreach ($settings['custom_typography'] as $typography) {
                if (isset($typography['typography_font_family']) && !empty($typography['typography_font_family'])) {
                    $fonts[] = $typography['typography_font_family'];
                }
            }
        }
        
        // Lấy fonts từ body typography
        if (isset($settings['body_typography_font_family']) && !empty($settings['body_typography_font_family'])) {
            $font = $settings['body_typography_font_family'];
            $fonts[] = $font;
        }
        
        // Lấy fonts từ heading typography
        $heading_font_keys = array(
            'h1_typography_font_family', 'h2_typography_font_family', 'h3_typography_font_family',
            'h4_typography_font_family', 'h5_typography_font_family', 'h6_typography_font_family',
            'link_normal_typography_font_family'
        );
        
        foreach ($heading_font_keys as $key) {
            if (isset($settings[$key]) && !empty($settings[$key])) {
                $font = $settings[$key];
                $fonts[] = $font;
            }
        }
        
        // Loại bỏ fonts trùng lặp và fonts mặc định
        $fonts = array_unique($fonts);
        $default_fonts = array('Arial', 'Helvetica', 'Times New Roman', 'Georgia', 'Verdana', 'Tahoma');
        $fonts = array_diff($fonts, $default_fonts);
        
        
        if (!empty($fonts)) {
            // Lưu fonts vào Elementor custom fonts option
            $elementor_fonts = get_option('elementor_fonts', array());
            
            foreach ($fonts as $font) {
                if (!in_array($font, $elementor_fonts)) {
                    $elementor_fonts[] = $font;
                } else {
                }
            }
            
            $result = update_option('elementor_fonts', $elementor_fonts);
            
            // Enqueue fonts qua Elementor trong request hiện tại
            if (class_exists('Elementor\Plugin')) {
                foreach ($fonts as $font) {
                    \Elementor\Plugin::$instance->frontend->enqueue_font($font);
                }
            } else {
            }
            
        } else {
        }
    }
    
    /**
     * Import ping endpoint
     */
    public static function import_ping($request) {
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'import-page route is reachable'
        ), 200);
    }
    
    /**
     * Test import endpoint
     */
    public static function test_import($request) {
        $name = $request->get_param('name');
        if (empty($name)) {
            $name = 'Test Page';
        }

        $page_data = array(
            'name' => $name,
            // Demo: có thể thêm 'data' nếu cần test trực tiếp
        );

        $result = self::import_page_from_data($page_data);
        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), 200);
        }
        return new WP_REST_Response(array(
            'success' => true,
            'page_id' => $result,
            'message' => 'Test page created successfully'
        ), 200);
    }
    
    /**
     * Test image processing endpoint
     */
    public static function test_image_processing($request) {
        $test_css = 'background-image: url("https://example.com/test.jpg"); background: url(https://example.com/test2.png);';
        $test_html = '<img src="https://example.com/test3.jpg" data-src="https://example.com/test4.jpg" class="jet-portfolio-item">';
        
        $processed_css = self::process_css_background_image_urls($test_css, 0);
        
        $processed_html = self::process_html_images($test_html, 0);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Image processing test completed. Check logs for details.',
            'css_input' => $test_css,
            'css_output' => $processed_css,
            'html_input' => $test_html,
            'html_output' => $processed_html
        ), 200);
    }
    
    /**
     * Test background-image processing endpoint
     */
    public static function test_background_image_processing($request) {
        // Test data với background-image
        $test_data = array(
            array(
                'id' => 'test1',
                'elType' => 'container',
                'settings' => array(
                    'background_background' => 'classic',
                    'background_image' => array(
                        'url' => 'https://temply.macusaone.com/wp-content/uploads/2025/09/test-bg.jpg',
                        'id' => '',
                        'size' => ''
                    ),
                    'background_overlay_image' => array(
                        'url' => 'https://temply.macusaone.com/wp-content/uploads/2025/09/test-overlay.jpg',
                        'id' => '',
                        'size' => ''
                    ),
                    'custom_css' => 'background-image: url("https://temply.macusaone.com/wp-content/uploads/2025/09/test-css-bg.jpg");'
                ),
                'elements' => array()
            )
        );
        
        $page_id = 1; // Test page ID
        
        $processed_data = self::process_external_images($test_data, $page_id);
        
        return new WP_REST_Response(array(
            'success' => true,
            'original' => $test_data,
            'processed' => $processed_data,
            'message' => 'Background image processing test completed. Check logs for details.'
        ), 200);
    }
    
    
    /**
     * Tìm attachment đã tồn tại bằng URL gốc
     */
    private static function find_existing_attachment_by_url($original_url) {
        global $wpdb;
        
        // Tìm theo hash (mới)
        $url_hash = md5($original_url);
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_mac_img_hash' 
             AND meta_value = %s",
            $url_hash
        ));
        
        if ($attachment_id && get_post($attachment_id)) {
            return $attachment_id;
        }
        
        // Fallback: tìm theo URL cũ (cho các ảnh đã import trước đó)
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_original_image_url' 
             AND meta_value = %s",
            $original_url
        ));
        
        if ($attachment_id && get_post($attachment_id)) {
            return $attachment_id;
        }
        
        return false;
    }
    
    /**
     * Lấy attachment ID từ URL - thử nhiều cách
     */
    private static function get_attachment_id_from_url($url) {
        if (empty($url)) {
            return 0;
        }
        
        // Cách 1: Dùng attachment_url_to_postid (WordPress native) - nhanh nhất
        $attachment_id = attachment_url_to_postid($url);
        if ($attachment_id) {
            return intval($attachment_id);
        }
        
        // Cách 2: Query database trực tiếp theo guid
        global $wpdb;
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment'",
            $url
        ));
        if ($attachment_id) {
            return intval($attachment_id);
        }
        
        // Cách 3: Tìm theo filename trong _wp_attached_file (cho các URL đã được rewrite)
        $filename = basename(parse_url($url, PHP_URL_PATH));
        if ($filename) {
            // Tìm chính xác theo filename
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_wp_attached_file' 
                 AND meta_value LIKE %s
                 ORDER BY post_id DESC 
                 LIMIT 1",
                '%' . $wpdb->esc_like($filename)
            ));
            if ($attachment_id) {
                return intval($attachment_id);
            }
        }
        
        // Cách 4: Tìm theo guid LIKE (cho các URL có thể bị modify)
        if ($filename) {
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                 WHERE guid LIKE %s AND post_type = 'attachment'
                 ORDER BY ID DESC 
                 LIMIT 1",
                '%' . $wpdb->esc_like($filename)
            ));
            if ($attachment_id) {
                return intval($attachment_id);
            }
        }
        
        return 0;
    }
    
    /**
     * Xử lý tất cả external images trong Elementor data
     */
    private static function process_external_images($data, $page_id) {
        if (!is_array($data)) {
            return $data;
        }
        
        foreach ($data as &$element) {
            // Xử lý images trong element settings
            if (isset($element['settings']) && is_array($element['settings'])) {
                // Xử lý tất cả background_image fields
                $element_id = isset($element['id']) ? $element['id'] : '';
                $element['settings'] = self::process_background_image_fields($element['settings'], $page_id, $element_id);
                // Xử lý image field
                if (isset($element['settings']['image']) && is_array($element['settings']['image'])) {
                    $image = &$element['settings']['image'];
                    if (!empty($image['url']) && self::is_external_url($image['url'])) {
                        if (!self::SKIP_DOWNLOAD_IMAGES) {
                        $new_url = self::download_and_attach_image($image['url'], $page_id);
                        if ($new_url) {
                            $image['url'] = $new_url;
                            }
                        }
                    }
                }
                
                
                // Xử lý các string values có thể chứa image URLs
                foreach ($element['settings'] as $key => &$value) {
                    if (is_string($value) && self::is_image_url($value) && self::is_external_url($value)) {
                        if (!self::SKIP_DOWNLOAD_IMAGES) {
                        $new_url = self::download_and_attach_image($value, $page_id);
                        if ($new_url) {
                            $value = $new_url;
                            }
                        }
                    }
                }
            }
            
            // Xử lý images trong nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                $element['elements'] = self::process_external_images($element['elements'], $page_id);
            }
        }
        
        return $data;
    }
    
    /**
     * Kiểm tra xem string có phải là image URL không
     */
    private static function is_image_url($url) {
        if (empty($url) || !is_string($url)) {
            return false;
        }
        
        // Kiểm tra URL hợp lệ
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Kiểm tra extension ảnh
        $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp');
        $path_info = pathinfo(parse_url($url, PHP_URL_PATH));
        $extension = isset($path_info['extension']) ? strtolower($path_info['extension']) : '';
        
        return in_array($extension, $image_extensions);
    }
    
    /**
     * Kiểm tra xem URL có phải là external URL không
     */
    private static function is_external_url($url) {
        if (empty($url) || !is_string($url)) {
            return false;
        }
        
        // Kiểm tra URL hợp lệ
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Kiểm tra xem có phải external URL không (không phải local)
        $home_url = home_url();
        $parsed_url = parse_url($url);
        $parsed_home = parse_url($home_url);
        
        // Nếu không có domain hoặc domain khác với home URL
        if (!isset($parsed_url['host']) || $parsed_url['host'] !== $parsed_home['host']) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Xử lý external images trong CSS content
     */
    private static function process_css_external_images($data, $page_id) {
        if (!is_array($data)) {
            return $data;
        }
        
        foreach ($data as $index => &$element) {
            if (isset($element['settings']) && is_array($element['settings'])) {
                // Xử lý tất cả settings có thể chứa CSS
                foreach ($element['settings'] as $key => &$value) {
                    if (is_string($value)) {
                        // Xử lý CSS background images
                        if (strpos($value, 'background-image:') !== false || strpos($value, 'background:') !== false) {
                        $value = self::process_css_background_image_urls($value, $page_id);
                        }
                    }
                }
            }
            
            // Xử lý inline styles (style_inline)
            if (isset($element['style_inline']) && is_string($element['style_inline'])) {
                $element['style_inline'] = self::process_css_background_image_urls($element['style_inline'], $page_id);
            }
            
            // Xử lý custom_css
            if (isset($element['custom_css']) && is_string($element['custom_css'])) {
                $element['custom_css'] = self::process_css_background_image_urls($element['custom_css'], $page_id);
            }
            
            // Xử lý background images (tôn trọng SKIP_DOWNLOAD_IMAGES)
            if (isset($element['settings']) && is_array($element['settings'])) {
                $element_id = isset($element['id']) ? $element['id'] : '';
                $element['settings'] = self::process_background_image_fields($element['settings'], $page_id, $element_id);
            }
            
            // Recursively process nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                $element['elements'] = self::process_css_external_images($element['elements'], $page_id);
            }
        }
        
        return $data;
    }
    
    /**
     * Xử lý background-image URLs trong CSS string
     */
    private static function process_css_background_image_urls($css_content, $page_id) {
        if (empty($css_content) || !is_string($css_content)) {
            return $css_content;
        }
        
        
        
        // Tìm tất cả background-image: url(...) trong CSS
        $patterns = array(
            '/background-image:\s*url\(["\']([^"\')\s]+)["\']\)/i',
            '/background-image:\s*url\(([^"\')\s]+)\)/i',
            '/background:\s*url\(["\']([^"\')\s]+)["\']\)/i',
            '/background:\s*url\(([^"\')\s]+)\)/i'
        );
        
        foreach ($patterns as $pattern) {
            $css_content = preg_replace_callback($pattern, function($matches) use ($page_id) {
                $image_url = $matches[1];
                $image_url = trim($image_url, '"\'');
                
                if (self::is_image_url($image_url) && self::is_external_url($image_url)) {
                    if (self::SKIP_DOWNLOAD_IMAGES) {
                        // Giữ nguyên URL nếu SKIP_DOWNLOAD_IMAGES = true
                        error_log("SKIP: CSS background image found: " . $image_url);
                        return $matches[0]; // Giữ nguyên
                    } else {
                        // Download và replace URL nếu SKIP_DOWNLOAD_IMAGES = false
                        $new_url = self::download_and_attach_image($image_url, $page_id);
                        if ($new_url) {
                            return str_replace($image_url, $new_url, $matches[0]);
                        }
                    }
                }
                return $matches[0]; // Giữ nguyên nếu không phải external image
            }, $css_content);
        }
        
        return $css_content;
    }
    
    /**
     * Xử lý external images trong HTML content
     */
    private static function process_html_external_images($data, $page_id) {
        if (!is_array($data)) {
            return $data;
        }
        
        foreach ($data as &$element) {
            // Xử lý tất cả string values trong element
            $element = self::process_element_strings($element, $page_id);
            
            // Recursively process nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                $element['elements'] = self::process_html_external_images($element['elements'], $page_id);
            }
        }
        
        return $data;
    }
    
    /**
     * Xử lý tất cả string values trong element để tìm images
     */
    private static function process_element_strings($element, $page_id) {
        if (!is_array($element)) {
            return $element;
        }
        
        foreach ($element as $key => &$value) {
            if (is_string($value)) {
                // Xử lý HTML content với images (bao gồm Jet Portfolio)
                if (strpos($value, '<img') !== false || 
                    strpos($value, 'jet-portfolio') !== false || 
                    strpos($value, 'jetengine') !== false ||
                    strpos($value, 'jetelements') !== false) {
                    $value = self::process_html_images($value, $page_id);
                }
                // Xử lý CSS content với background images
                elseif (strpos($value, 'background-image:') !== false || 
                        strpos($value, 'background:') !== false) {
                    $value = self::process_css_background_image_urls($value, $page_id);
                }
                // Xử lý các string có thể chứa image URLs
                elseif (self::is_image_url($value) && self::is_external_url($value)) {
                    if (!self::SKIP_DOWNLOAD_IMAGES) {
                    $new_url = self::download_and_attach_image($value, $page_id);
                    if ($new_url) {
                        $value = $new_url;
                        }
                    }
                }
            } elseif (is_array($value)) {
                $value = self::process_element_strings($value, $page_id);
            }
        }
        
        return $element;
    }
    
    /**
     * Xử lý images trong HTML string
     */
    private static function process_html_images($html_content, $page_id) {
        if (empty($html_content) || !is_string($html_content)) {
            return $html_content;
        }
        
        // Tìm tất cả img tags với src trống hoặc external URLs
        $pattern = '/<img([^>]*?)(?:src\s*=\s*["\']([^"\']*)["\'])?([^>]*?)>/i';
        
        $html_content = preg_replace_callback($pattern, function($matches) use ($page_id, $html_content) {
            $full_match = $matches[0];
            $before_src = $matches[1];
            $src_value = isset($matches[2]) ? $matches[2] : '';
            $after_src = $matches[3];
            
            // Nếu src trống, tìm href từ parent link
            if (empty($src_value)) {
                $parent_href = self::find_parent_href($html_content, $full_match);
                if ($parent_href && self::is_image_url($parent_href) && self::is_external_url($parent_href)) {
                    if (!self::SKIP_DOWNLOAD_IMAGES) {
                    $new_url = self::download_and_attach_image($parent_href, $page_id);
                    if ($new_url) {
                        return '<img' . $before_src . 'src="' . $new_url . '"' . $after_src . '>';
                        }
                    }
                }
            }
            // Nếu src có external URL, download và thay thế
            elseif (self::is_image_url($src_value) && self::is_external_url($src_value)) {
                if (!self::SKIP_DOWNLOAD_IMAGES) {
                $new_url = self::download_and_attach_image($src_value, $page_id);
                if ($new_url) {
                    return '<img' . $before_src . 'src="' . $new_url . '"' . $after_src . '>';
                    }
                }
            }
            
            return $full_match;
        }, $html_content);
        
        // Xử lý thêm cho Jet Portfolio - tìm data-src, data-lazy-src, etc.
        $jet_patterns = array(
            // data-src
            '/data-src\s*=\s*["\']([^"\']+)["\']/i',
            // data-lazy-src
            '/data-lazy-src\s*=\s*["\']([^"\']+)["\']/i',
            // data-original
            '/data-original\s*=\s*["\']([^"\']+)["\']/i',
            // data-bg
            '/data-bg\s*=\s*["\']([^"\']+)["\']/i'
        );
        
        foreach ($jet_patterns as $pattern) {
            $html_content = preg_replace_callback($pattern, function($matches) use ($page_id) {
                $image_url = $matches[1];
                if (self::is_image_url($image_url) && self::is_external_url($image_url)) {
                    if (!self::SKIP_DOWNLOAD_IMAGES) {
                    $new_url = self::download_and_attach_image($image_url, $page_id);
                    if ($new_url) {
                        return str_replace($image_url, $new_url, $matches[0]);
                        }
                    }
                }
                return $matches[0];
            }, $html_content);
        }
        
        return $html_content;
    }
    
    /**
     * Tìm href từ parent <a> tag
     */
    private static function find_parent_href($html_content, $img_tag) {
        // Tìm vị trí của img tag trong HTML
        $img_pos = strpos($html_content, $img_tag);
        if ($img_pos === false) {
            return false;
        }
        
        // Tìm <a> tag trước img tag và kiểm tra nó chưa đóng
        $before_img = substr($html_content, 0, $img_pos);
        
        // Tìm tất cả <a> tags và </a> tags
        preg_match_all('/<a[^>]*?href\s*=\s*["\']([^"\']*)["\'][^>]*?>/i', $before_img, $a_matches, PREG_OFFSET_CAPTURE);
        preg_match_all('/<\/a>/i', $before_img, $close_matches, PREG_OFFSET_CAPTURE);
        
        // Đếm số <a> và </a> để tìm <a> chưa đóng
        $open_count = count($a_matches[0]);
        $close_count = count($close_matches[0]);
        
        if ($open_count > $close_count) {
            // Có <a> tag chưa đóng, lấy href của nó
            $last_open_index = $open_count - 1;
            $href = $a_matches[1][$last_open_index][0];
            return $href;
        }
        
        return false;
    }
    
    /**
     * Tạo title từ filename (loại bỏ extension và format đẹp)
     */
    private static function generate_image_title_from_filename($filename) {
        if (empty($filename)) {
            return '';
        }
        
        // Loại bỏ extension
        $title = pathinfo($filename, PATHINFO_FILENAME);
        
        if (empty($title)) {
            return '';
        }
        
        // Thay thế các ký tự đặc biệt bằng khoảng trắng
        $title = str_replace(array('_', '-', '%20', '+'), ' ', $title);
        
        // Loại bỏ số thứ tự ở đầu (ví dụ: "123_image.jpg" -> "image")
        $title = preg_replace('/^\d+\s*/', '', $title);
        
        // Loại bỏ khoảng trắng thừa
        $title = trim($title);
        $title = preg_replace('/\s+/', ' ', $title);
        
        // Capitalize first letter của mỗi từ
        $title = ucwords(strtolower($title));
        
        // Nếu title vẫn rỗng sau khi xử lý, dùng filename gốc (không có extension)
        if (empty($title)) {
            $title = pathinfo($filename, PATHINFO_FILENAME);
        }
        
        return $title;
    }
    
    /**
     * Download ảnh từ URL external và attach vào WordPress media library
     */
    /**
     * Kiểm tra xem URL có phải là placeholder image không
     * Chỉ check domain patterns, KHÔNG bao gồm unsplash (vì dùng để làm demo)
     */
    private static function is_placeholder_image($url) {
        if (empty($url) || !is_string($url)) {
            return false;
        }
        
        // Parse URL để lấy host
        $parsed_url = parse_url($url);
        if (!isset($parsed_url['host'])) {
            return false;
        }
        
        $host = strtolower($parsed_url['host']);
        $path = isset($parsed_url['path']) ? strtolower($parsed_url['path']) : '';
        
        // Danh sách placeholder domains (KHÔNG bao gồm unsplash)
        $placeholder_domains = array(
            'via.placeholder.com',
            'placehold.it',
            'placeholder.com',
            'dummyimage.com',
            'loremflickr.com',
            'placeimg.com',
            'fakeimg.pl',
            'placekitten.com',
            'placebear.com',
            'picsum.photos', // Lorem Picsum - placeholder service
            'placehold.co',
            'holder.js',
            'place-hold.it',
            'placehold.jp',
            'placehold.net',
            'placeholdr.com',
            'placeholder.pics',
            'placeholderimage.com',
        );
        
        // Check domain
        foreach ($placeholder_domains as $domain) {
            if (strpos($host, $domain) !== false) {
                return true;
            }
        }
        
        // Check URL path patterns (chứa từ khóa placeholder)
        $placeholder_keywords = array('placeholder', 'dummy', 'default-image', 'sample-image', 'test-image');
        foreach ($placeholder_keywords as $keyword) {
            if (stripos($path, $keyword) !== false || stripos($url, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private static function download_and_attach_image($image_url, $page_id) {
        if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Skip placeholder images
        if (self::is_placeholder_image($image_url)) {
            error_log("SKIP: Placeholder image detected and skipped: {$image_url}");
            return false;
        }
        
        // Kiểm tra xem ảnh đã được download chưa (tránh duplicate)
        $existing_attachment = self::find_existing_attachment_by_url($image_url);
        if ($existing_attachment) {
            return wp_get_attachment_url($existing_attachment);
        }
        
        // Download ảnh từ URL external
        $temp_file = download_url($image_url);
        if (is_wp_error($temp_file)) {
            return false;
        }
        
        // Tạo filename từ URL
        $filename = basename(parse_url($image_url, PHP_URL_PATH));
        if (empty($filename) || strpos($filename, '.') === false) {
            $filename = 'imported-image-' . time() . '.jpg';
        }
        
        // Attach vào WordPress media library
        $file_array = array(
            'name' => $filename,
            'tmp_name' => $temp_file
        );
        
        // Không để lại dấu vết - description rỗng
        $attachment_id = media_handle_sideload($file_array, $page_id, '');
        
        // Cleanup temp file
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        if (is_wp_error($attachment_id)) {
            return false;
        }
        
        // Set title từ tên file (loại bỏ extension và format đẹp)
        $title = self::generate_image_title_from_filename($filename);
        if ($title) {
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_title' => $title
            ));
        }
        
        // Lưu hash của original URL để tránh duplicate (không lưu URL gốc)
        update_post_meta($attachment_id, '_mac_img_hash', md5($image_url));
        
        $new_url = wp_get_attachment_url($attachment_id);
        
        return $new_url;
    }
    
    /**
     * Xử lý tất cả background_image fields trong settings
     */
    private static function process_background_image_fields(&$settings, $page_id, $element_id = '') {
        if (!is_array($settings)) {
            return $settings;
        }
        
        // Danh sách tất cả background_image fields trong Elementor
        $bg_image_fields = array(
            'background_image',
            'background_overlay_image',
            'background_hover_image',
            'background_overlay_hover_image',
            'ep_background_overlay_image',
            'ep_background_overlay_hover_image',
            'element_pack_widget_tooltip_background_image',
            'element_pack_widget_tooltip_background_image_tablet',
            'element_pack_widget_tooltip_background_image_mobile',
            'ep_background_overlay_video_fallback',
            'ep_background_overlay_hover_video_fallback'
        );
        
        foreach ($bg_image_fields as $field) {
            if (isset($settings[$field]) && is_array($settings[$field])) {
                $bg_image = &$settings[$field];
                if (!empty($bg_image['url']) && self::is_external_url($bg_image['url'])) {
                    if (self::SKIP_DOWNLOAD_IMAGES) {
                        // Chỉ log background image mà không download
                        error_log("SKIP: Background image field '{$field}' found: " . $bg_image['url']);
                        
                        // Inject CSS trực tiếp vào page để force render external URL
                        $url = $bg_image['url'];
                        $css_selector = ".elementor-element-{$element_id}";
                        $css_rule = "background-image: url('{$url}') !important;";
                        
                        // Inject vào custom_css của element
                        if (isset($settings['custom_css']) && is_string($settings['custom_css'])) {
                            $settings['custom_css'] .= "\n{$css_selector} { {$css_rule} }";
                        } else {
                            $settings['custom_css'] = "{$css_selector} { {$css_rule} }";
                        }
                        
                        // Cũng inject vào style_inline
                        if (isset($settings['style_inline']) && is_string($settings['style_inline'])) {
                            $settings['style_inline'] .= "\n{$css_rule}";
                        } else {
                            $settings['style_inline'] = $css_rule;
                        }
                        
                        error_log("INFO: Injected CSS for {$field}: {$css_selector} { {$css_rule} }");
                    } else {
                    $new_url = self::download_and_attach_image($bg_image['url'], $page_id);
                    if ($new_url) {
                        $attachment_id = self::get_attachment_id_from_url($new_url);
                        if ($attachment_id) {
                            $bg_image['id'] = intval($attachment_id);
                            $bg_image['url'] = $new_url;
                        } else {
                            // Set ID = 0 để tránh lỗi Elementor/Jet Elements
                            $bg_image['id'] = 0;
                            $bg_image['url'] = $new_url;
                            }
                        }
                    }
                }
            }
        }
        
        return $settings;
    }
    
    
    /**
     * Hook để giữ nguyên _id gốc của custom_colors
     */
    public static function preserve_custom_colors_ids($settings, $kit_id) {
        // Lấy custom_colors gốc từ post meta
        $original_custom_colors = get_post_meta($kit_id, '_elementor_custom_colors', true);
        
        if (is_array($original_custom_colors) && !empty($original_custom_colors)) {
            // Giữ nguyên _id gốc
            $settings['custom_colors'] = $original_custom_colors;
        }
        
        return $settings;
    }
    
    /**
     * Test custom_colors import
     */
    public static function test_custom_colors_import($request) {
        // Test data với _id gốc
        $test_site_settings = array(
            'settings' => array(
                'custom_colors' => array(
                    array(
                        '_id' => '041be46',
                        'title' => 'White',
                        'color' => '#ffffff'
                    ),
                    array(
                        '_id' => '575bd41',
                        'title' => 'Black',
                        'color' => '#000000'
                    ),
                    array(
                        '_id' => '54f3520',
                        'title' => 'Transparent',
                        'color' => '#00000000'
                    )
                )
            )
        );
        
        // Import site settings
        $result = self::import_site_settings_from_data($test_site_settings);
        
        // Kiểm tra kết quả
        $kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
        $custom_colors_meta = get_post_meta($kit_id, '_elementor_custom_colors', true);
        
        return new WP_REST_Response(array(
            'success' => true,
            'test_data' => $test_site_settings,
            'import_result' => $result,
            'kit_id' => $kit_id,
            'custom_colors_meta' => $custom_colors_meta,
            'message' => 'Custom colors import test completed'
        ), 200);
    }
    
    /**
     * Test simple settings import
     */
    public static function test_simple_settings_import($request) {
        try {
            // Test data đơn giản
            $test_site_settings = array(
                'settings' => array(
                    'system_colors' => array(
                        array(
                            '_id' => 'primary',
                            'title' => 'Primary',
                            'color' => '#ff0000'
                        )
                    )
                )
            );
            
            // Import site settings
            $result = self::import_site_settings_from_data($test_site_settings);
            
            // Kiểm tra kết quả
            $kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
            $kit_settings = get_post_meta($kit_id, '_elementor_page_settings', true);
            
            return new WP_REST_Response(array(
                'success' => true,
                'test_data' => $test_site_settings,
                'import_result' => $result,
                'kit_id' => $kit_id,
                'kit_settings' => $kit_settings,
                'message' => 'Simple settings import test completed'
            ), 200);
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Simple settings import test failed'
            ), 500);
        }
    }
    
    /**
     * Test basic functionality
     */
    public static function test_basic_functionality($request) {
        try {
            // Kiểm tra Elementor có active không
            $elementor_active = class_exists('Elementor\Plugin');
            
            // Kiểm tra kit ID
            $kit_id = null;
            if ($elementor_active) {
                $kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
            }
            
            // Kiểm tra kit document
            $kit_document = null;
            if ($kit_id) {
                $kit_document = \Elementor\Plugin::$instance->documents->get($kit_id);
            }
            
            // Kiểm tra settings
            $current_settings = null;
            if ($kit_document) {
                $current_settings = $kit_document->get_settings();
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'elementor_active' => $elementor_active,
                'kit_id' => $kit_id,
                'kit_document_exists' => !is_null($kit_document),
                'current_settings_count' => is_array($current_settings) ? count($current_settings) : 0,
                'message' => 'Basic functionality test completed'
            ), 200);
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Basic functionality test failed'
            ), 500);
        }
    }
    
    /**
     * Test import settings với data thực tế
     */
    public static function test_real_import($request) {
        try {
            // Test data thực tế từ export API
            $test_site_settings = array(
                'settings' => array(
                    'system_colors' => array(
                        array(
                            '_id' => 'primary',
                            'title' => 'Primary',
                            'color' => '#F2849E'
                        ),
                        array(
                            '_id' => 'secondary',
                            'title' => 'Secondary', 
                            'color' => '#ffeaef'
                        )
                    ),
                    'custom_colors' => array(
                        array(
                            '_id' => '041be46',
                            'title' => 'White',
                            'color' => '#ffffff'
                        ),
                        array(
                            '_id' => '575bd41',
                            'title' => 'Black',
                            'color' => '#000000'
                        )
                    )
                )
            );
            
            // Import site settings
            $result = self::import_site_settings_from_data($test_site_settings);
            
            // Kiểm tra kết quả
            $kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
            $custom_colors_meta = get_post_meta($kit_id, '_elementor_custom_colors', true);
            $system_colors_meta = get_post_meta($kit_id, '_elementor_system_colors', true);
            
            return new WP_REST_Response(array(
                'success' => true,
                'test_data' => $test_site_settings,
                'import_result' => $result,
                'kit_id' => $kit_id,
                'custom_colors_meta' => $custom_colors_meta,
                'system_colors_meta' => $system_colors_meta,
                'message' => 'Real import test completed'
            ), 200);
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Real import test failed'
            ), 500);
        }
    }
    
    /**
     * Test override primary color
     */
    public static function test_override_primary_color($request) {
        try {
            // Lấy settings hiện tại trước khi import
            $kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
            $kit = \Elementor\Plugin::$instance->documents->get($kit_id);
            $current_settings = $kit->get_settings();
            
            // Test data với Primary color khác
            $test_site_settings = array(
                'settings' => array(
                    'system_colors' => array(
                        array(
                            '_id' => 'primary',
                            'title' => 'Primary',
                            'color' => '#00ff00' // Màu xanh lá để test đè lên
                        )
                    )
                )
            );
            
            // Import site settings
            $result = self::import_site_settings_from_data($test_site_settings);
            
            // Lấy settings sau khi import
            $kit_after = \Elementor\Plugin::$instance->documents->get($kit_id);
            $settings_after = $kit_after->get_settings();
            
            return new WP_REST_Response(array(
                'success' => true,
                'before_import' => array(
                    'system_colors' => isset($current_settings['system_colors']) ? $current_settings['system_colors'] : 'Not found'
                ),
                'test_data' => $test_site_settings,
                'import_result' => $result,
                'after_import' => array(
                    'system_colors' => isset($settings_after['system_colors']) ? $settings_after['system_colors'] : 'Not found'
                ),
                'message' => 'Override primary color test completed'
            ), 200);
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Override primary color test failed'
            ), 500);
        }
    }
    
    
    /**
     * Download tất cả external images trong tất cả pages
     */
    public static function download_all_external_images_all_pages() {
        // Lấy tất cả pages có Elementor data
        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => '_elementor_data',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        if (empty($pages)) {
            return array(
                'success' => true,
                'message' => 'Không tìm thấy pages nào có Elementor data',
                'pages_processed' => 0,
                'total_downloaded' => 0,
                'total_failed' => 0,
                'results' => array()
            );
        }
        
        $total_downloaded = 0;
        $total_failed = 0;
        $total_skipped_placeholder = 0;
        $pages_processed = 0;
        $results = array();
        $downloaded_urls = array(); // Track URLs đã download để skip duplicate
        
        foreach ($pages as $page) {
            $page_id = $page->ID;
            $page_title = $page->post_title;
            
            error_log("PROCESSING: Page {$page_id} - {$page_title}");
            
            try {
                // Download images cho page này
                $page_result = self::download_all_external_images_single($page_id, $downloaded_urls);
                
                $results[] = array(
                    'page_id' => $page_id,
                    'page_title' => $page_title,
                    'success' => $page_result['success'],
                    'downloaded' => $page_result['downloaded'],
                    'failed' => $page_result['failed'],
                    'skipped_placeholder' => isset($page_result['skipped_placeholder']) ? $page_result['skipped_placeholder'] : 0,
                    'total' => $page_result['total'],
                    'message' => $page_result['message']
                );
                
                $total_downloaded += $page_result['downloaded'];
                $total_failed += $page_result['failed'];
                $total_skipped_placeholder += isset($page_result['skipped_placeholder']) ? $page_result['skipped_placeholder'] : 0;
                $pages_processed++;
                
                // Update downloaded_urls với URLs mới
                if (isset($page_result['downloaded_urls'])) {
                    $downloaded_urls = array_merge($downloaded_urls, $page_result['downloaded_urls']);
                }
                
            } catch (Exception $e) {
                error_log("ERROR: Failed to process page {$page_id}: " . $e->getMessage());
                
                $results[] = array(
                    'page_id' => $page_id,
                    'page_title' => $page_title,
                    'success' => false,
                    'downloaded' => 0,
                    'failed' => 0,
                    'total' => 0,
                    'message' => 'Error: ' . $e->getMessage()
                );
                
                $total_failed++;
                $pages_processed++;
            }
        }
        
        $message = "Hoàn tất xử lý {$pages_processed} pages: {$total_downloaded} images downloaded, {$total_failed} failed";
        if ($total_skipped_placeholder > 0) {
            $message .= ", {$total_skipped_placeholder} placeholder images đã bỏ qua";
        }
        
        return array(
            'success' => true,
            'message' => $message,
            'pages_processed' => $pages_processed,
            'total_downloaded' => $total_downloaded,
            'total_failed' => $total_failed,
            'total_skipped_placeholder' => $total_skipped_placeholder,
            'results' => $results
        );
    }
    
    /**
     * Download tất cả external images trong page (single page version)
     */
    public static function download_all_external_images_single($page_id, $downloaded_urls = array()) {
        if (!$page_id) {
            return array(
                'success' => false,
                'message' => 'Page ID không hợp lệ',
                'downloaded' => 0,
                'failed' => 0,
                'total' => 0,
                'downloaded_urls' => array()
            );
        }
        
        // Lấy elementor_data
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        
        if (empty($elementor_data)) {
            return array(
                'success' => true,
                'message' => 'Không tìm thấy Elementor data',
                'downloaded' => 0,
                'failed' => 0,
                'total' => 0,
                'downloaded_urls' => array()
            );
        }
        
        // Xử lý elementor_data - có thể là string hoặc array
        if (is_array($elementor_data)) {
            $elements = $elementor_data;
        } else {
            // Decode JSON
            $elements = json_decode($elementor_data, true);
            if (!$elements) {
                return array(
                    'success' => false,
                    'message' => 'Elementor data không hợp lệ',
                    'downloaded' => 0,
                    'failed' => 0,
                    'total' => 0,
                    'downloaded_urls' => array()
                );
            }
        }
        
        $downloaded_count = 0;
        $failed_count = 0;
        $skipped_placeholder_count = 0;
        $total_external = 0;
        $new_downloaded_urls = array();
        
        // Tìm tất cả external URLs (đã được filter placeholder trong find_all_external_image_urls)
        $external_urls = self::find_all_external_image_urls($elements);
        $total_external = count($external_urls);
        
        if ($total_external == 0) {
            return array(
                'success' => true,
                'message' => 'Không có external images nào cần download',
                'downloaded' => 0,
                'failed' => 0,
                'skipped_placeholder' => 0,
                'total' => 0,
                'downloaded_urls' => array()
            );
        }
        
        // Download từng URL và replace trong elements array
        foreach ($external_urls as $url) {
            // Double check placeholder (phòng trường hợp có URL lọt qua filter)
            if (self::is_placeholder_image($url)) {
                $skipped_placeholder_count++;
                error_log("SKIP: Placeholder image skipped during download: {$url}");
                continue;
            }
            
            // Skip nếu URL đã được download trước đó
            if (in_array($url, $downloaded_urls)) {
                error_log("SKIP: URL already downloaded: {$url}");
                continue;
            }
            
            $new_url = self::download_and_attach_image($url, $page_id);
            if ($new_url) {
                // Replace URL trong elements array (bao gồm background_image fields và CSS)
                $elements = self::replace_url_in_elements_complete($elements, $url, $new_url, $page_id);
                $downloaded_count++;
                $new_downloaded_urls[] = $url;
            } else {
                $failed_count++;
            }
        }
        
        // Update elementor_data nếu có thay đổi
        if ($downloaded_count > 0) {
            // Lưu theo cách Elementor làm để đảm bảo compatible
            $save_result = self::save_elementor_data($page_id, $elements);
            
            if (is_wp_error($save_result)) {
                error_log("ERROR: save_elementor_data failed for page {$page_id}. Error: " . $save_result->get_error_message());
                return array(
                    'success' => false,
                    'message' => 'Lỗi khi lưu Elementor data: ' . $save_result->get_error_message(),
                    'downloaded' => $downloaded_count,
                    'failed' => $failed_count,
                    'total' => $total_external,
                    'downloaded_urls' => $new_downloaded_urls
                );
            }
        }
        
        $message = "Download hoàn tất: {$downloaded_count} thành công, {$failed_count} thất bại";
        if ($skipped_placeholder_count > 0) {
            $message .= ", {$skipped_placeholder_count} placeholder images đã bỏ qua";
        }
        
        return array(
            'success' => true,
            'message' => $message,
            'downloaded' => $downloaded_count,
            'failed' => $failed_count,
            'skipped_placeholder' => $skipped_placeholder_count,
            'total' => $total_external,
            'downloaded_urls' => $new_downloaded_urls
        );
    }
    
    /**
     * Download tất cả external images trong page (original function)
     */
    public static function download_all_external_images($page_id) {
        if (!$page_id) {
            return array(
                'success' => false,
                'message' => 'Page ID không hợp lệ'
            );
        }
        
        // Lấy elementor_data
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        
        if (empty($elementor_data)) {
            return array(
                'success' => false,
                'message' => 'Không tìm thấy Elementor data'
            );
        }
        
        // Xử lý elementor_data - có thể là string hoặc array
        if (is_array($elementor_data)) {
            $elements = $elementor_data;
        } else {
            // Decode JSON
            $elements = json_decode($elementor_data, true);
            if (!$elements) {
                return array(
                    'success' => false,
                    'message' => 'Elementor data không hợp lệ'
                );
            }
        }
        
        $downloaded_count = 0;
        $failed_count = 0;
        $skipped_placeholder_count = 0;
        $total_external = 0;
        
        // Tìm tất cả external URLs (đã được filter placeholder trong find_all_external_image_urls)
        $external_urls = self::find_all_external_image_urls($elements);
        $total_external = count($external_urls);
        
        if ($total_external == 0) {
            return array(
                'success' => true,
                'message' => 'Không có external images nào cần download',
                'downloaded' => 0,
                'failed' => 0,
                'skipped_placeholder' => 0,
                'total' => 0
            );
        }
        
        // Download từng URL và replace trong elements array
        foreach ($external_urls as $url) {
            // Double check placeholder (phòng trường hợp có URL lọt qua filter)
            if (self::is_placeholder_image($url)) {
                $skipped_placeholder_count++;
                error_log("SKIP: Placeholder image skipped during download: {$url}");
                continue;
            }
            
            $new_url = self::download_and_attach_image($url, $page_id);
            if ($new_url) {
                // Replace URL trong elements array (bao gồm background_image fields và CSS)
                $elements = self::replace_url_in_elements_complete($elements, $url, $new_url, $page_id);
                $downloaded_count++;
            } else {
                $failed_count++;
            }
        }
        
        // Update elementor_data nếu có thay đổi
        if ($downloaded_count > 0) {
            // Lưu theo cách Elementor làm để đảm bảo compatible
            $save_result = self::save_elementor_data($page_id, $elements);
            
            if (is_wp_error($save_result)) {
                error_log("ERROR: save_elementor_data failed for page {$page_id}. Error: " . $save_result->get_error_message());
                return array(
                    'success' => false,
                    'message' => 'Lỗi khi lưu Elementor data: ' . $save_result->get_error_message(),
                    'downloaded' => $downloaded_count,
                    'failed' => $failed_count,
                    'total' => $total_external
                );
            }
        }
        
        $message = "Download hoàn tất: {$downloaded_count} thành công, {$failed_count} thất bại";
        if ($skipped_placeholder_count > 0) {
            $message .= ", {$skipped_placeholder_count} placeholder images đã bỏ qua";
        }
        
        return array(
            'success' => true,
            'message' => $message,
            'downloaded' => $downloaded_count,
            'failed' => $failed_count,
            'skipped_placeholder' => $skipped_placeholder_count,
            'total' => $total_external
        );
    }
    
    /**
     * Lưu Elementor data đúng cách như Elementor làm
     * QUAN TRỌNG: Phải dùng wp_slash() để WordPress không strip slashes khi lưu
     */
    private static function save_elementor_data($page_id, $elements) {
        if (!$page_id || empty($elements)) {
            return new WP_Error('invalid_data', 'Page ID hoặc elements không hợp lệ');
        }
        
        // Encode JSON với flags phù hợp
        $json_data = wp_json_encode($elements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json_data === false) {
            return new WP_Error('json_encode_failed', 'Không thể encode JSON: ' . json_last_error_msg());
        }
        
        // QUAN TRỌNG: Dùng wp_slash() như Elementor làm
        // Elementor expect data được lưu với slashes, WordPress sẽ stripslashes khi đọc
        $slashed_data = wp_slash($json_data);
        
        // Lưu vào database
        $result = update_post_meta($page_id, '_elementor_data', $slashed_data);
        
        if ($result === false) {
            // Kiểm tra xem data có thực sự thay đổi không
            $current_data = get_post_meta($page_id, '_elementor_data', true);
            if ($current_data === $json_data) {
                // Data giống nhau, không cần update
                $result = true;
            } else {
                return new WP_Error('update_failed', 'Không thể lưu Elementor data vào database');
            }
        }
        
        // Clear Elementor cache
        $kit_id = get_option('elementor_active_kit', 0);
        self::clear_elementor_cache($kit_id);
        
        // Regenerate CSS cho page
        if (class_exists('Elementor\Plugin')) {
            try {
                // Clear CSS cache cho page cụ thể
                $post_css = \Elementor\Core\Files\CSS\Post::create($page_id);
                $post_css->delete();
                
                // Force regenerate
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            } catch (Exception $e) {
                error_log("Warning: Could not clear Elementor CSS cache: " . $e->getMessage());
            }
        }
        
        return true;
    }
    
    /**
     * Tìm tất cả external image URLs trong elements
     */
    private static function find_all_external_image_urls($elements) {
        $urls = array();
        
        if (!is_array($elements)) {
            return $urls;
        }
        
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }
            
            // Tìm trong settings
            if (isset($element['settings']) && is_array($element['settings'])) {
                $urls = array_merge($urls, self::find_urls_in_settings($element['settings']));
            }
            
            // Tìm trong inline styles (style_inline)
            if (isset($element['style_inline']) && is_string($element['style_inline'])) {
                $css_urls = self::extract_urls_from_css($element['style_inline']);
                $urls = array_merge($urls, $css_urls);
            }
            
            // Tìm trong custom_css
            if (isset($element['custom_css']) && is_string($element['custom_css'])) {
                $css_urls = self::extract_urls_from_css($element['custom_css']);
                $urls = array_merge($urls, $css_urls);
            }
            
            // Tìm trong nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                $urls = array_merge($urls, self::find_all_external_image_urls($element['elements']));
            }
        }
        
        return array_unique($urls);
    }
    
    /**
     * Tìm URLs trong settings
     */
    private static function find_urls_in_settings($settings) {
        $urls = array();
        
        foreach ($settings as $key => $value) {
            if (is_string($value)) {
                // Check if it's a direct image URL
                if (self::is_image_url($value) && self::is_external_url($value)) {
                    $urls[] = $value;
                }
                // Check if it contains CSS with background images
                elseif (strpos($value, 'background') !== false) {
                    $css_urls = self::extract_urls_from_css($value);
                    $urls = array_merge($urls, $css_urls);
                }
            } elseif (is_array($value)) {
                if (isset($value['url']) && self::is_image_url($value['url']) && self::is_external_url($value['url'])) {
                    $urls[] = $value['url'];
                }
                // Recursive search trong nested arrays
                $urls = array_merge($urls, self::find_urls_in_settings($value));
            }
        }
        
        return $urls;
    }
    
    /**
     * Extract URLs from CSS string
     */
    private static function extract_urls_from_css($css_content) {
        $urls = array();
        
        if (empty($css_content) || !is_string($css_content)) {
            return $urls;
        }
        
        // Patterns để tìm URLs trong CSS
        $patterns = array(
            '/background-image:\s*url\(["\']([^"\')\s]+)["\']\)/i',
            '/background-image:\s*url\(([^"\')\s]+)\)/i',
            '/background:\s*url\(["\']([^"\')\s]+)["\']\)/i',
            '/background:\s*url\(([^"\')\s]+)\)/i'
        );
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $css_content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $url) {
                    $url = trim($url, '"\''); // Remove quotes
                    if (self::is_image_url($url) && self::is_external_url($url)) {
                        $urls[] = $url;
                    }
                }
            }
        }
        
        return $urls;
    }
    
    /**
     * Replace URL trong elements array
     */
    private static function replace_url_in_elements($elements, $old_url, $new_url) {
        if (!is_array($elements)) {
            return $elements;
        }
        
        foreach ($elements as $key => &$element) {
            if (is_array($element)) {
                // Recursive replace trong nested elements
                $element = self::replace_url_in_elements($element, $old_url, $new_url);
            } elseif (is_string($element)) {
                // Replace trong string values
                $element = str_replace($old_url, $new_url, $element);
            }
        }
        
        return $elements;
    }
    
    /**
     * Replace URL trong CSS string (cho inline styles)
     */
    private static function replace_url_in_css($css_content, $old_url, $new_url) {
        if (empty($css_content) || !is_string($css_content)) {
            return $css_content;
        }
        
        // Replace trong tất cả các pattern CSS
        $patterns = array(
            // Pattern 1: background-image: url("old_url")
            '/background-image:\s*url\(["\']?' . preg_quote($old_url, '/') . '["\']?\)/i',
            // Pattern 2: background: url("old_url")
            '/background:\s*url\(["\']?' . preg_quote($old_url, '/') . '["\']?\)/i'
        );
        
        foreach ($patterns as $pattern) {
            $css_content = preg_replace($pattern, str_replace($old_url, $new_url, '$0'), $css_content);
        }
        
        return $css_content;
    }
    
    /**
     * Replace URL trong elements array (bao gồm background_image fields và CSS)
     */
    private static function replace_url_in_elements_complete($elements, $old_url, $new_url, $page_id) {
        if (!is_array($elements)) {
            return $elements;
        }
        
        foreach ($elements as $key => &$element) {
            if (is_array($element)) {
                // Xử lý settings (bao gồm background_image fields)
                if (isset($element['settings']) && is_array($element['settings'])) {
                    $element['settings'] = self::replace_url_in_settings_complete($element['settings'], $old_url, $new_url, $page_id);
                }
                
                // Xử lý inline styles
                if (isset($element['style_inline']) && is_string($element['style_inline'])) {
                    $element['style_inline'] = self::replace_url_in_css($element['style_inline'], $old_url, $new_url);
                }
                
                // Xử lý custom_css
                if (isset($element['custom_css']) && is_string($element['custom_css'])) {
                    $element['custom_css'] = self::replace_url_in_css($element['custom_css'], $old_url, $new_url);
                }
                
                // Recursive replace trong nested elements
                $element = self::replace_url_in_elements_complete($element, $old_url, $new_url, $page_id);
            } elseif (is_string($element)) {
                // Replace trong string values
                $element = str_replace($old_url, $new_url, $element);
            }
        }
        
        return $elements;
    }
    
    /**
     * Replace URL trong settings (bao gồm TẤT CẢ image fields)
     */
    private static function replace_url_in_settings_complete($settings, $old_url, $new_url, $page_id) {
        if (!is_array($settings)) {
            return $settings;
        }
        
        // Danh sách TẤT CẢ image-related fields trong Elementor
        $image_fields = array(
            // Background images
            'background_image',
            'background_overlay_image',
            'background_hover_image',
            'background_overlay_hover_image',
            'ep_background_overlay_image',
            'ep_background_overlay_hover_image',
            'element_pack_widget_tooltip_background_image',
            'element_pack_widget_tooltip_background_image_tablet',
            'element_pack_widget_tooltip_background_image_mobile',
            'ep_background_overlay_video_fallback',
            'ep_background_overlay_hover_video_fallback',
            // Widget images (Image widget, etc.)
            'image',
            'image_hover',
            'before_image',
            'after_image',
            'fallback_image',
            'logo',
            'logo_image',
            'author_avatar',
            'default_image',
            'thumbnail',
            'featured_image',
            // Responsive variants
            'image_tablet',
            'image_mobile',
            'background_image_tablet',
            'background_image_mobile',
        );
        
        // Xử lý các image fields đã biết
        foreach ($image_fields as $field) {
            if (isset($settings[$field]) && is_array($settings[$field])) {
                $settings[$field] = self::update_image_object($settings[$field], $old_url, $new_url);
            }
        }
        
        // Xử lý gallery (array của image objects)
        $gallery_fields = array('gallery', 'wp_gallery', 'carousel', 'slides', 'images', 'portfolio_images');
        foreach ($gallery_fields as $gallery_field) {
            if (isset($settings[$gallery_field]) && is_array($settings[$gallery_field])) {
                foreach ($settings[$gallery_field] as $idx => &$gallery_item) {
                    if (is_array($gallery_item)) {
                        // Gallery item có thể là image object trực tiếp
                        if (isset($gallery_item['url'])) {
                            $gallery_item = self::update_image_object($gallery_item, $old_url, $new_url);
                        }
                        // Hoặc có thể có nested image object (như trong slides)
                        elseif (isset($gallery_item['image']) && is_array($gallery_item['image'])) {
                            $gallery_item['image'] = self::update_image_object($gallery_item['image'], $old_url, $new_url);
                        }
                        // Recursive cho các nested structures khác
                        else {
                            $gallery_item = self::replace_url_in_settings_complete($gallery_item, $old_url, $new_url, $page_id);
                        }
                    }
                }
                unset($gallery_item);
            }
        }
        
        // Xử lý recursive cho các nested arrays có thể chứa image objects
        foreach ($settings as $key => &$value) {
            // Skip các fields đã xử lý
            if (in_array($key, $image_fields) || in_array($key, $gallery_fields)) {
                continue;
            }
            
            if (is_array($value)) {
                // Kiểm tra nếu đây là image object (có 'url' key)
                if (isset($value['url']) && is_string($value['url']) && $value['url'] === $old_url) {
                    $value = self::update_image_object($value, $old_url, $new_url);
                }
                // Recursive tìm kiếm trong nested arrays
                else {
                    $value = self::replace_url_in_settings_complete($value, $old_url, $new_url, $page_id);
                }
            }
            // Replace trong CSS strings
            elseif (is_string($value) && strpos($value, $old_url) !== false) {
                $settings[$key] = self::replace_url_in_css($value, $old_url, $new_url);
            }
        }
        unset($value);
        
        return $settings;
    }
    
    /**
     * Update image object với URL mới và ID mới
     */
    private static function update_image_object($image_obj, $old_url, $new_url) {
        if (!is_array($image_obj)) {
            return $image_obj;
        }
        
        // Kiểm tra và replace URL
        if (isset($image_obj['url']) && $image_obj['url'] === $old_url) {
            $image_obj['url'] = $new_url;
            
            // Get attachment ID - thử nhiều cách
            $attachment_id = self::get_attachment_id_from_url($new_url);
            
            if ($attachment_id) {
                $image_obj['id'] = intval($attachment_id);
            } else {
                // Set ID = 0 thay vì unset hoặc empty string
                // - Empty string '' gây lỗi: wp_get_attachment_image_src('') trả về false
                // - unset() có thể gây lỗi cho một số plugin expect key 'id' tồn tại
                // - 0 (integer) an toàn hơn vì hầu hết plugins check if($id) hoặc if(!empty($id))
                $image_obj['id'] = 0;
            }
        }
        
        return $image_obj;
    }
    
    /**
     * Replace URL trong elements array (bao gồm inline styles) - OLD VERSION
     */
    private static function replace_url_in_elements_with_css($elements, $old_url, $new_url) {
        if (!is_array($elements)) {
            return $elements;
        }
        
        foreach ($elements as $key => &$element) {
            if (is_array($element)) {
                // Xử lý inline styles
                if (isset($element['style_inline']) && is_string($element['style_inline'])) {
                    $element['style_inline'] = self::replace_url_in_css($element['style_inline'], $old_url, $new_url);
                }
                
                // Xử lý custom_css
                if (isset($element['custom_css']) && is_string($element['custom_css'])) {
                    $element['custom_css'] = self::replace_url_in_css($element['custom_css'], $old_url, $new_url);
                }
                
                // Recursive replace trong nested elements
                $element = self::replace_url_in_elements_with_css($element, $old_url, $new_url);
            } elseif (is_string($element)) {
                // Replace trong string values
                $element = str_replace($old_url, $new_url, $element);
            }
        }
        
        return $elements;
    }
    
    
    /**
     * Import template từ template data
     * Tạo template post mới và lưu Elementor data
     * 
     * @param array $template_data Template data từ export
     * @return int|WP_Error Template ID mới hoặc WP_Error nếu lỗi
     */
    public static function import_template_from_data($template_data) {
        if (!class_exists('Elementor\Plugin')) {
            return new WP_Error('elementor_not_active', 'Elementor is not active.');
        }
        
        try {
            // Validate template data
            if (empty($template_data['content']) || !is_array($template_data['content'])) {
                return new WP_Error('invalid_template_data', 'Template content is missing or invalid.');
            }
            
            // Tạo template post mới
            $template_name = isset($template_data['template_name']) ? $template_data['template_name'] : 'Imported Template';
            $template_slug = isset($template_data['template_slug']) ? $template_data['template_slug'] : sanitize_title($template_name);
            
            // Tạo slug unique nếu cần
            $original_slug = $template_slug;
            $counter = 1;
            while (get_page_by_path($template_slug, OBJECT, 'elementor_library')) {
                $template_slug = $original_slug . '-' . $counter;
                $counter++;
            }
            
            $new_template_post = array(
                'post_title' => $template_name,
                'post_name' => $template_slug,
                'post_type' => 'elementor_library',
                'post_status' => 'publish'
            );
            
            $new_template_id = wp_insert_post($new_template_post);
            
            if (is_wp_error($new_template_id)) {
                return $new_template_id;
            }
            
            // Process external images trong template content
            $processed_content = self::process_external_images($template_data['content'], $new_template_id);
            
            // Lưu Elementor data vào template - dùng wp_slash() như Elementor làm
            if (is_array($processed_content)) {
                $json_data = wp_json_encode($processed_content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $slashed_data = wp_slash($json_data);
            } else {
                $slashed_data = wp_slash($processed_content);
            }
            
            $result = update_post_meta($new_template_id, '_elementor_data', $slashed_data);
            
            if (!$result) {
                wp_delete_post($new_template_id, true);
                return new WP_Error('meta_update_failed', 'Failed to save elementor data to template');
            }
            
            // Lưu page settings
            $page_settings = isset($template_data['page_settings']) ? $template_data['page_settings'] : array();
            update_post_meta($new_template_id, '_elementor_page_settings', $page_settings);
            
            // Đánh dấu template là elementor template
            update_post_meta($new_template_id, '_elementor_edit_mode', 'builder');
            $template_type = isset($template_data['type']) ? $template_data['type'] : 'section';
            update_post_meta($new_template_id, '_elementor_template_type', $template_type);
            update_post_meta($new_template_id, '_elementor_version', ELEMENTOR_VERSION);
            
            // Chỉ cập nhật pro version nếu có
            if (defined('ELEMENTOR_PRO_VERSION')) {
                update_post_meta($new_template_id, '_elementor_pro_version', constant('ELEMENTOR_PRO_VERSION'));
            }
            
            // Cập nhật CSS cache
            \Elementor\Plugin::$instance->files_manager->clear_cache();
            
            return $new_template_id;
            
        } catch (Exception $e) {
            return new WP_Error('import_error', 'Error importing template: ' . $e->getMessage());
        }
    }
    
    /**
     * Replace template IDs trong Elementor content
     * Recursively tìm và replace id_section_category trong mac-menu widgets
     * 
     * @param array $content Elementor content
     * @param array $template_id_mapping Mapping: old_id => new_id
     * @return array Content đã được replace
     */
    public static function replace_template_ids_in_content($content, $template_id_mapping) {
        if (!is_array($content)) {
            return $content;
        }
        
        foreach ($content as $key => &$value) {
            // Tìm mac-menu widget
            if (isset($value['widgetType']) && $value['widgetType'] === 'module_mac_menu') {
                if (isset($value['settings']['id_section_category'])) {
                    $old_id = $value['settings']['id_section_category'];
                    
                    // Convert sang int để so sánh
                    $old_id_int = intval($old_id);
                    
                    // Replace nếu có trong mapping
                    if (isset($template_id_mapping[$old_id_int])) {
                        $value['settings']['id_section_category'] = (string)$template_id_mapping[$old_id_int];
                    }
                }
            }
            
            // Recursively check child elements
            if (is_array($value)) {
                $value = self::replace_template_ids_in_content($value, $template_id_mapping);
            }
        }
        
        return $content;
    }
    
    /**
     * Public wrapper để tìm external image URLs
     */
    public static function get_external_image_urls($page_id) {
        try {
            $elementor_data = get_post_meta($page_id, '_elementor_data', true);
            if (empty($elementor_data)) {
                return array();
            }
            
            // Xử lý elementor_data - có thể là string hoặc array
            if (is_array($elementor_data)) {
                $elements = $elementor_data;
            } else {
                $elements = json_decode($elementor_data, true);
                if (!$elements) {
                    return array();
                }
            }
            
            $urls = self::find_all_external_image_urls($elements);
            
            return $urls;
        } catch (Exception $e) {
            return array();
        } catch (Error $e) {
            return array();
        }
    }
    
    /**
     * REST API endpoint để download images
     */
    public static function download_images_endpoint($request) {
        $page_id = $request->get_param('page_id');
        
        if (!$page_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Page ID không được cung cấp'
            ), 400);
        }
        
        $result = self::download_all_external_images($page_id);
        
        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }
    
}
