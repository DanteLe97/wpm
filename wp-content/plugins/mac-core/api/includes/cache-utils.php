<?php
/**
 * Cache Utils - Xử lý caching để tối ưu performance
 */
if (!defined('ABSPATH')) exit;

/**
 * Cached HTML với transient
 */
function mac_get_cached_html($url) {
    $cache_key = 'mac_html_' . md5($url);
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    $response = wp_remote_get($url, [
        'timeout' => 30,
        'user-agent' => 'MAC-Core-Sitecheck/1.0'
    ]);
    
    if (is_wp_error($response)) {
        return '';
    }
    
    $html = wp_remote_retrieve_body($response);
    
    // Cache for 5 minutes
    set_transient($cache_key, $html, 300);
    
    return $html;
}

/**
 * Clear HTML cache
 */
function mac_clear_html_cache($url = null) {
    if ($url) {
        $cache_key = 'mac_html_' . md5($url);
        delete_transient($cache_key);
    } else {
        // Clear all MAC HTML caches
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mac_html_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_mac_html_%'");
    }
}

/**
 * Get cached option value
 */
function mac_get_cached_option($option_name, $default = false) {
    static $option_cache = [];
    
    if (!isset($option_cache[$option_name])) {
        $option_cache[$option_name] = get_option($option_name, $default);
    }
    
    return $option_cache[$option_name];
}

/**
 * Get cached post meta
 */
function mac_get_cached_post_meta($post_id, $meta_key = '', $single = false) {
    static $meta_cache = [];
    
    $cache_key = $post_id . '_' . $meta_key . '_' . ($single ? '1' : '0');
    
    if (!isset($meta_cache[$cache_key])) {
        $meta_cache[$cache_key] = get_post_meta($post_id, $meta_key, $single);
    }
    
    return $meta_cache[$cache_key];
}

/**
 * Get cached plugins list
 */
function mac_get_cached_plugins() {
    static $plugins_cache = null;
    
    if ($plugins_cache === null) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins_cache = get_plugins();
    }
    
    return $plugins_cache;
}

/**
 * Get cached users
 */
function mac_get_cached_users($args = []) {
    static $users_cache = [];
    
    $cache_key = md5(serialize($args));
    
    if (!isset($users_cache[$cache_key])) {
        $users_cache[$cache_key] = get_users($args);
    }
    
    return $users_cache[$cache_key];
}

/**
 * Cache query results
 */
function mac_cache_query_result($cache_key, $callback, $expiration = 300) {
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    $result = $callback();
    set_transient($cache_key, $result, $expiration);
    
    return $result;
}

/**
 * Clear all MAC caches
 */
function mac_clear_all_caches() {
    // Clear HTML caches
    mac_clear_html_cache();
    
    // Clear other caches if needed
    wp_cache_flush();
}

/**
 * Get cache statistics
 */
function mac_get_cache_stats() {
    global $wpdb;
    
    $html_caches = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_mac_html_%'
    ");
    
    $timeout_caches = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_timeout_mac_html_%'
    ");
    
    return [
        'html_caches' => $html_caches,
        'timeout_caches' => $timeout_caches,
        'total_caches' => $html_caches + $timeout_caches
    ];
}
