<?php
/**
 * AJAX APIs for MAC Importer Demo
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Importer_AJAX_APIs {
    
    /**
     * Register AJAX endpoints
     */
    public static function register_endpoints() {
        // AJAX handler for getting elementor data
        add_action('wp_ajax_ltp_get_elementor_data', array(__CLASS__, 'get_elementor_data_api'));
        add_action('wp_ajax_nopriv_ltp_get_elementor_data', array(__CLASS__, 'get_elementor_data_api'));
        
        // AJAX handlers for download images
        add_action('wp_ajax_check_external_images', array(__CLASS__, 'check_external_images_ajax'));
        add_action('wp_ajax_download_external_images', array(__CLASS__, 'download_external_images_ajax'));
    }
    
    /**
     * API endpoint để lấy _elementor_data
     */
    public static function get_elementor_data_api() {
        // Kiểm tra quyền truy cập
        if (!current_user_can('edit_theme_options')) {
            wp_die('Unauthorized access');
        }
        
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
        
        // Trả về JSON
        wp_send_json_success(array(
            'count' => count($data),
            'data' => $data
        ));
    }
    
    /**
     * AJAX handler để check external images
     */
    public static function check_external_images_ajax() {
        try {
            // Kiểm tra nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_ajax_nonce')) {
                wp_send_json_error(array('message' => 'Security check failed'));
                return;
            }
            
            // Kiểm tra quyền truy cập
            if (!current_user_can('edit_theme_options')) {
                wp_send_json_error(array('message' => 'Unauthorized access'));
                return;
            }
            
            $page_id = isset($_POST['page_id']) ? sanitize_text_field($_POST['page_id']) : '';
            
            if (empty($page_id)) {
                wp_send_json_error(array('message' => 'Page ID không được để trống'));
                return;
            }
            
            if ($page_id === 'all') {
                // Check all pages
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
                
                $total_external = 0;
                foreach ($pages as $page) {
                    $external_urls = MAC_Importer_Import_API::get_external_image_urls($page->ID);
                    $total_external += count($external_urls);
                }
                
                wp_send_json_success(array(
                    'total' => $total_external,
                    'message' => "Tìm thấy {$total_external} external images across all pages"
                ));
                return; // QUAN TRỌNG: return sau khi gửi response
            }
            
            // Single page
            $page_id = intval($page_id);
            if (!$page_id) {
                wp_send_json_error(array('message' => 'Page ID không hợp lệ'));
                return;
            }
            
            // Lấy external URLs
            $external_urls = MAC_Importer_Import_API::get_external_image_urls($page_id);
            $total = count($external_urls);
            
            wp_send_json_success(array(
                'total' => $total,
                'urls' => $external_urls,
                'message' => "Tìm thấy {$total} external images"
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Lỗi: ' . $e->getMessage()));
        } catch (Error $e) {
            wp_send_json_error(array('message' => 'Lỗi nghiêm trọng: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler để download external images
     */
    public static function download_external_images_ajax() {
        // Tăng thời gian thực thi cho việc download nhiều hình
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');
        
        try {
            // Kiểm tra nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_ajax_nonce')) {
                wp_send_json_error(array('message' => 'Security check failed'));
                return;
            }
            
            // Kiểm tra quyền truy cập
            if (!current_user_can('edit_theme_options')) {
                wp_send_json_error(array('message' => 'Unauthorized access'));
                return;
            }
            
            $page_id = isset($_POST['page_id']) ? sanitize_text_field($_POST['page_id']) : '';
            
            if (empty($page_id)) {
                wp_send_json_error(array('message' => 'Page ID không được để trống'));
                return;
            }
            
            if ($page_id === 'all') {
                // Xử lý tất cả pages
                $result = MAC_Importer_Import_API::download_all_external_images_all_pages();
            } else {
                // Xử lý single page
                $page_id = intval($page_id);
                if (!$page_id) {
                    wp_send_json_error(array('message' => 'Page ID không hợp lệ'));
                    return;
                }
                $result = MAC_Importer_Import_API::download_all_external_images($page_id);
            }
            
            if (isset($result['success']) && $result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Lỗi: ' . $e->getMessage()));
        } catch (Error $e) {
            wp_send_json_error(array('message' => 'Lỗi nghiêm trọng: ' . $e->getMessage()));
        }
    }
}
