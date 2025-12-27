<?php
/**
 * Misc Utils - Các utility functions chung
 */
if (!defined('ABSPATH')) exit;

/**
 * Get nested value from array using dot notation
 */
function mac_get_nested_value($data, $path) {
    $keys = explode('.', $path);
    $current = $data;
    
    foreach ($keys as $key) {
        if (is_array($current) && isset($current[$key])) {
            $current = $current[$key];
        } else {
            return null;
        }
    }
    
    return $current;
}

/**
 * Handle array comparison
 */
function mac_handle_array_comparison($name, $option_value, $check_array, $operator) {
    if (!is_array($option_value) || !is_array($check_array)) {
        return [
            'success' => false,
            'type' => 'option',
            'name' => $name,
            'message' => 'Invalid data types for array comparison'
        ];
    }
    
    $result = false;
    $message = '';
    
    switch ($operator) {
        case 'contains':
            $result = !empty(array_intersect($check_array, $option_value));
            $message = $result ? 'Array contains expected values' : 'Array does not contain expected values';
            break;
        case 'not_contains':
            $result = empty(array_intersect($check_array, $option_value));
            $message = $result ? 'Array does not contain expected values' : 'Array contains expected values';
            break;
        case 'equals':
            $result = ($option_value === $check_array);
            $message = $result ? 'Arrays are equal' : 'Arrays are not equal';
            break;
        case 'not_equals':
            $result = ($option_value !== $check_array);
            $message = $result ? 'Arrays are not equal' : 'Arrays are equal';
            break;
        default:
            $result = false;
            $message = 'Unknown operator: ' . $operator;
    }
    
    return [
        'success' => true,
        'type' => 'option',
        'name' => $name,
        'result' => $result,
        'message' => $message
    ];
}

/**
 * Handle post array comparison
 */
function mac_handle_post_array_comparison($check_array, $operator) {
    if (!is_array($check_array)) {
        return [
            'success' => false,
            'message' => 'Invalid check array for post comparison'
        ];
    }
    
    // This function would be implemented based on specific post comparison logic
    // For now, return a placeholder
    return [
        'success' => true,
        'result' => false,
        'message' => 'Post array comparison not implemented'
    ];
}

/**
 * Get actual page from value
 */
function mac_get_actual_page($value) {
    if (isset($value['page']) && !empty($value['page'])) {
        $page = sanitize_text_field($value['page']);
        if (empty($page) || $page === '/') {
            return 'home';
        }
        return $page;
    }
    return 'home';
}

/**
 * Sanitize text field
 */
function mac_sanitize_text_field($text) {
    return sanitize_text_field($text);
}

/**
 * Sanitize textarea field
 */
function mac_sanitize_textarea_field($text) {
    return sanitize_textarea_field($text);
}

/**
 * Sanitize email field
 */
function mac_sanitize_email($email) {
    return sanitize_email($email);
}

/**
 * Sanitize URL field
 */
function mac_sanitize_url($url) {
    return esc_url_raw($url);
}

/**
 * Validate required fields
 */
function mac_validate_required_fields($data, $required_fields) {
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    return $missing_fields;
}

/**
 * Format error message
 */
function mac_format_error_message($message, $context = []) {
    if (!empty($context)) {
        $message .= ' Context: ' . json_encode($context);
    }
    
    return $message;
}

/**
 * Log error
 */
function mac_log_error($message, $context = []) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('MAC Info Manager: ' . mac_format_error_message($message, $context));
    }
}

/**
 * Log info
 */
function mac_log_info($message, $context = []) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('MAC Info Manager Info: ' . mac_format_error_message($message, $context));
    }
}

/**
 * Check if function exists
 */
function mac_function_exists($function_name) {
    return function_exists($function_name);
}

/**
 * Check if class exists
 */
function mac_class_exists($class_name) {
    return class_exists($class_name);
}

/**
 * Get WordPress version
 */
function mac_get_wp_version() {
    global $wp_version;
    return $wp_version;
}

/**
 * Get PHP version
 */
function mac_get_php_version() {
    return PHP_VERSION;
}

/**
 * Get server info
 */
function mac_get_server_info() {
    return $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
}

/**
 * Get memory limit
 */
function mac_get_memory_limit() {
    return ini_get('memory_limit');
}

/**
 * Get max execution time
 */
function mac_get_max_execution_time() {
    return ini_get('max_execution_time');
}

/**
 * Get upload max filesize
 */
function mac_get_upload_max_filesize() {
    return ini_get('upload_max_filesize');
}

/**
 * Get post max size
 */
function mac_get_post_max_size() {
    return ini_get('post_max_size');
}

/**
 * Check if site is multisite
 */
function mac_is_multisite() {
    return is_multisite();
}

/**
 * Get current site ID
 */
function mac_get_current_site_id() {
    return get_current_blog_id();
}

/**
 * Get site URL
 */
function mac_get_site_url() {
    return get_site_url();
}

/**
 * Get home URL
 */
function mac_get_home_url() {
    return get_home_url();
}

/**
 * Get admin URL
 */
function mac_get_admin_url() {
    return admin_url();
}

/**
 * Get current user ID
 */
function mac_get_current_user_id() {
    return get_current_user_id();
}

/**
 * Check if user is logged in
 */
function mac_is_user_logged_in() {
    return is_user_logged_in();
}

/**
 * Get current user
 */
function mac_get_current_user() {
    return wp_get_current_user();
}

/**
 * Check if user can manage options
 */
function mac_current_user_can_manage_options() {
    return current_user_can('manage_options');
}

/**
 * Check if user can edit posts
 */
function mac_current_user_can_edit_posts() {
    return current_user_can('edit_posts');
}

/**
 * Check if user can edit pages
 */
function mac_current_user_can_edit_pages() {
    return current_user_can('edit_pages');
}

/**
 * Check if user can edit users
 */
function mac_current_user_can_edit_users() {
    return current_user_can('edit_users');
}

/**
 * Check if user can install plugins
 */
function mac_current_user_can_install_plugins() {
    return current_user_can('install_plugins');
}

/**
 * Check if user can activate plugins
 */
function mac_current_user_can_activate_plugins() {
    return current_user_can('activate_plugins');
}

/**
 * Check if user can deactivate plugins
 */
function mac_current_user_can_deactivate_plugins() {
    return current_user_can('deactivate_plugins');
}

/**
 * Check if user can delete plugins
 */
function mac_current_user_can_delete_plugins() {
    return current_user_can('delete_plugins');
}

/**
 * Check if user can update plugins
 */
function mac_current_user_can_update_plugins() {
    return current_user_can('update_plugins');
}

/**
 * Check if user can install themes
 */
function mac_current_user_can_install_themes() {
    return current_user_can('install_themes');
}

/**
 * Check if user can switch themes
 */
function mac_current_user_can_switch_themes() {
    return current_user_can('switch_themes');
}

/**
 * Check if user can edit themes
 */
function mac_current_user_can_edit_themes() {
    return current_user_can('edit_themes');
}

/**
 * Check if user can delete themes
 */
function mac_current_user_can_delete_themes() {
    return current_user_can('delete_themes');
}

/**
 * Check if user can update themes
 */
function mac_current_user_can_update_themes() {
    return current_user_can('update_themes');
}

/**
 * Check if user can update core
 */
function mac_current_user_can_update_core() {
    return current_user_can('update_core');
}

/**
 * Check if user can update languages
 */
function mac_current_user_can_update_languages() {
    return current_user_can('update_languages');
}

/**
 * Check if user can update translations
 */
function mac_current_user_can_update_translations() {
    return current_user_can('update_translations');
}

/**
 * Check if user can update everything
 */
function mac_current_user_can_update_everything() {
    return current_user_can('update_core') && 
           current_user_can('update_plugins') && 
           current_user_can('update_themes') && 
           current_user_can('update_languages');
}
