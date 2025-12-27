<?php
/**
 * Mac Dynamic Section Preview - Core Functions
 * 
 * @package Mac Rule ID
 * @version 2.0
 * @author MacUsaOne
 */

// Ngăn truy cập trực tiếp
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ===== REWRITE RULES CHO DYNAMIC SECTION =====
 */

// Thêm rewrite rule
add_action('init', 'mac_preview_add_rewrite_rules');
function mac_preview_add_rewrite_rules() {
    add_rewrite_rule('^page-mac-dynamic-section/?', 'index.php?mac_dynamic_section=1', 'top');
}

// Thêm query var
add_filter('query_vars', 'mac_preview_add_query_vars');
function mac_preview_add_query_vars($vars) {
    $vars[] = 'mac_dynamic_section';
    return $vars;
}

// Template redirect
add_action('template_redirect', 'mac_preview_template_redirect');
function mac_preview_template_redirect() {
    if (get_query_var('mac_dynamic_section')) {
        $template = get_stylesheet_directory() . '/page-mac-dynamic-section.php';
        if (file_exists($template)) {
            include $template;
            exit;
        } else {
            mac_preview_fallback_template();
            exit;
        }
    }
}

// Fallback template nếu không tìm thấy file trong theme
function mac_preview_fallback_template() {
    $id = sanitize_text_field($_GET['id'] ?? '');
    
    if (empty($id)) {
        wp_die('No ID provided');
    }
    
    $json_data = mac_preview_export_json($id);
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="mac-containers-' . date('Y-m-d-H-i-s') . '.json"');
    echo $json_data;
    exit;
}

/**
 * ===== EXPORT JSON LOGIC =====
 */

// Export JSON function
function mac_preview_export_json($id_string) {
    // Parse ID string (format: page:123-tem:abc123,page:456-tem:def456)
    $ids = explode(',', $id_string);
    $containers = [];
    
    foreach ($ids as $id_pair) {
        $id_pair = trim($id_pair);
        if (preg_match('/^page:(\d+)-tem:([a-zA-Z0-9_-]+)$/', $id_pair, $matches)) {
            $page_id = intval($matches[1]);
            $container_id = sanitize_text_field($matches[2]);
            
            $container_data = mac_preview_get_container_data($page_id, $container_id);
            if ($container_data) {
                $containers[] = $container_data;
            }
        }
    }
    
    return json_encode($containers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Get container data from Elementor
function mac_preview_get_container_data($page_id, $container_id) {
    // Validate page exists
    if (!get_post($page_id)) {
        return null;
    }
    
    // Get Elementor data
    $elementor_data = get_post_meta($page_id, '_elementor_data', true);
    
    if (empty($elementor_data)) {
        return null;
    }
    
    // Decode if it's JSON string
    if (is_string($elementor_data)) {
        $elementor_data = json_decode($elementor_data, true);
    }
    
    // Find container in data
    $container = mac_preview_find_container_recursive($elementor_data, $container_id);
    
    if (!$container) {
        return null;
    }
    
    return [
        'page_id' => $page_id,
        'container_id' => $container_id,
        'data' => $container,
        'exported_at' => current_time('mysql'),
        'site_url' => home_url()
    ];
}

// Recursive function to find container
function mac_preview_find_container_recursive($elements, $target_id) {
    if (!is_array($elements)) {
        return null;
    }
    
    foreach ($elements as $element) {
        if (isset($element['id']) && $element['id'] === $target_id) {
            return $element;
        }
        
        if (isset($element['elements']) && is_array($element['elements'])) {
            $found = mac_preview_find_container_recursive($element['elements'], $target_id);
            if ($found) {
                return $found;
            }
        }
    }
    
    return null;
}

/**
 * ===== FRONTEND ASSETS =====
 */

// Enqueue frontend assets
add_action('wp_enqueue_scripts', 'mac_preview_enqueue_frontend_assets', 20);
function mac_preview_enqueue_frontend_assets() {
    // Only load for admin users
    if (!current_user_can('edit_posts')) {
        return;
    }
    
    // Only load on Elementor pages
    if (!mac_preview_is_elementor_page()) {
        return;
    }
    
    // Enqueue CSS variables first (from main plugin) - only if not already enqueued
    if (!wp_style_is('coloris-css', 'enqueued')) {
        wp_enqueue_style('coloris-css', 'https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.css', array(), '1.0.0');
    }
    if (!wp_style_is('custom-colors-fonts-css', 'enqueued')) {
        wp_enqueue_style('custom-colors-fonts-css', MAC_RULEID_URI . 'css/custom-colors-fonts.css', array('coloris-css'), '1.0.0');
    }
    
    // Enqueue module CSS - depend on custom-colors-fonts for CSS variables
    wp_enqueue_style(
        'mac-preview-frontend',
        MAC_RULEID_URI . 'modules/mac-preview/assets/css/mac-preview-frontend.css',
        ['custom-colors-fonts-css'], // Dependency on main plugin CSS
        '2.0'
    );
    
    // Enqueue JS
    wp_enqueue_script(
        'mac-preview-frontend',
        MAC_RULEID_URI . 'modules/mac-preview/assets/js/mac-preview-frontend.js',
        ['jquery'],
        '2.0',
        true
    );
    
    // Localize script
    wp_localize_script('mac-preview-frontend', 'macPreviewData', [
        'homeUrl' => home_url(),
        'isAdmin' => current_user_can('manage_options'),
        'nonce' => wp_create_nonce('mac_preview_nonce'),
        'ajaxUrl' => admin_url('admin-ajax.php')
    ]);
}

// Check if current page is Elementor page
function mac_preview_is_elementor_page() {
    global $post;
    
    if (!$post) {
        return false;
    }
    
    // Check if Elementor is active
    if (!class_exists('\Elementor\Plugin')) {
        return false;
    }
    
    // Check if current page was built with Elementor
    return \Elementor\Plugin::$instance->documents->get($post->ID)->is_built_with_elementor();
}

/**
 * ===== UTILITY FUNCTIONS =====
 */

// Validate container ID format
function mac_preview_validate_container_id($container_id) {
    return preg_match('/^[a-zA-Z0-9_-]+$/', $container_id);
}

// Sanitize container data
function mac_preview_sanitize_container_data($data) {
    if (!is_array($data)) {
        return [];
    }
    
    // Basic sanitization - can be expanded
    return array_map('sanitize_text_field', $data);
}

/**
 * ===== ACTIVATION/DEACTIVATION HOOKS =====
 */

// Activation hook
function mac_preview_activate() {
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Check if Elementor is active
    if (!class_exists('\Elementor\Plugin')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Mac Preview requires Elementor to be installed and activated.');
    }
    
    // Schedule cleanup cron job
    if (!wp_next_scheduled('mac_preview_cleanup_temp_posts')) {
        wp_schedule_event(time(), 'hourly', 'mac_preview_cleanup_temp_posts');
    }
}

// Deactivation hook
function mac_preview_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Clear scheduled cron job
    wp_clear_scheduled_hook('mac_preview_cleanup_temp_posts');
}

/**
 * ===== AUTO CLEANUP TEMP POSTS =====
 */

// Hook cho cron job cleanup
add_action('mac_preview_cleanup_temp_posts', 'mac_preview_cleanup_temp_posts_cron');

function mac_preview_cleanup_temp_posts_cron() {
    // Tìm tất cả post có title "Mac Preview Temp Preview" và status "draft"
    $temp_posts = get_posts(array(
        'post_type' => 'page',
        'post_status' => 'draft',
        'title' => 'Mac Preview Temp Preview',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_elementor_edit_mode',
                'value' => 'builder',
                'compare' => '='
            )
        )
    ));
    
    if (!empty($temp_posts)) {
        foreach ($temp_posts as $post) {
            // Kiểm tra thời gian tạo post (xóa sau 1 giờ)
            $post_time = strtotime($post->post_date);
            $current_time = current_time('timestamp');
            
            if (($current_time - $post_time) > 3600) { // 1 giờ = 3600 giây
                wp_trash_post($post->ID);
            }
        }
    }
}

// Hook để cleanup ngay khi tạo post tạm thời (sau 5 phút)
add_action('wp_insert_post', 'mac_preview_schedule_temp_post_cleanup', 10, 2);

function mac_preview_schedule_temp_post_cleanup($post_id, $post) {
    // Chỉ xử lý cho post tạm thời của Mac Preview
    if ($post->post_title === 'Mac Preview Temp Preview' && 
        $post->post_status === 'draft' && 
        $post->post_type === 'page') {
        
        // Schedule cleanup sau 10 giây (để test)
        wp_schedule_single_event(time() + 10, 'mac_preview_cleanup_single_temp_post', array($post_id));
    }
}

// Hook để cleanup post đơn lẻ
add_action('mac_preview_cleanup_single_temp_post', 'mac_preview_cleanup_single_temp_post_cron');

function mac_preview_cleanup_single_temp_post_cron($post_id) {
    $post = get_post($post_id);
    
    if ($post && 
        $post->post_title === 'Mac Preview Temp Preview' && 
        $post->post_status === 'draft') {
        
        wp_trash_post($post_id);
    }
}

/**
 * ===== MANUAL CLEANUP FUNCTION =====
 */

// Function để cleanup thủ công (có thể gọi từ admin)
function mac_preview_manual_cleanup_temp_posts() {
    $temp_posts = get_posts(array(
        'post_type' => 'page',
        'post_status' => 'draft',
        'title' => 'Mac Preview Temp Preview',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_elementor_edit_mode',
                'value' => 'builder',
                'compare' => '='
            )
        )
    ));
    
    $deleted_count = 0;
    
    if (!empty($temp_posts)) {
        foreach ($temp_posts as $post) {
            wp_trash_post($post->ID);
            $deleted_count++;
        }
    }
    
    return $deleted_count;
}

// AJAX handler để cleanup thủ công từ admin
add_action('wp_ajax_mac_preview_manual_cleanup', 'mac_preview_manual_cleanup_ajax');

function mac_preview_manual_cleanup_ajax() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_preview_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    $deleted_count = mac_preview_manual_cleanup_temp_posts();
    
    wp_send_json_success(array(
        'message' => "Đã xóa {$deleted_count} post tạm thời",
        'deleted_count' => $deleted_count
    ));
}

/**
 * ===== AJAX HANDLERS =====
 */

// AJAX handler for validating container ID
add_action('wp_ajax_mac_preview_validate_container', 'mac_preview_validate_container_ajax');
function mac_preview_validate_container_ajax() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_preview_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce', 'should_reload' => false));
    }

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions', 'should_reload' => false));
    }

    if (!isset($_POST['container_id'])) {
        wp_send_json_error(array('message' => 'Vui lòng nhập Container ID', 'should_reload' => false));
    }

    $container_id_string = sanitize_text_field($_POST['container_id']);
    
    // Parse container ID format: page:123-tem:abc123
    if (!preg_match('/^page:(\d+)-tem:([a-zA-Z0-9_-]+)$/', $container_id_string, $matches)) {
        wp_send_json_error(array(
            'message' => 'Format không hợp lệ! Đúng: page:PAGE_ID-tem:CONTAINER_ID',
            'should_reload' => false
        ));
    }

    $page_id = intval($matches[1]);
    $element_id = $matches[2];

    // Check if page exists
    $post = get_post($page_id);
    if (!$post) {
        wp_send_json_error(array(
            'message' => "Không tìm thấy page với ID: {$page_id}",
            'should_reload' => false
        ));
    }

    // Check if page has Elementor data
    $elementor_data = get_post_meta($page_id, '_elementor_data', true);
    if (empty($elementor_data)) {
        wp_send_json_error(array(
            'message' => "Page {$page_id} không có dữ liệu Elementor",
            'should_reload' => false
        ));
    }

    // Parse Elementor data and find container
    $elementor_data_array = json_decode($elementor_data, true);
    if (!is_array($elementor_data_array)) {
        wp_send_json_error(array(
            'message' => "Dữ liệu Elementor không hợp lệ cho page {$page_id}",
            'should_reload' => false
        ));
    }

    // Search for container in Elementor data
    $container_found = mac_preview_find_container_recursive($elementor_data_array, $element_id);
    
    if (!$container_found) {
        wp_send_json_error(array(
            'message' => "Không tìm thấy container '{$element_id}' trong page {$page_id}",
            'should_reload' => false
        ));
    }

    // Check if it's actually a container element
    if (!isset($container_found['elType']) || $container_found['elType'] !== 'container') {
        wp_send_json_error(array(
            'message' => "Element '{$element_id}' không phải là container",
            'should_reload' => false
        ));
    }

    // All checks passed
    wp_send_json_success(array(
        'message' => 'Container ID hợp lệ',
        'page_id' => $page_id,
        'element_id' => $element_id,
        'page_title' => $post->post_title,
        'container_data' => $container_found
    ));
} 