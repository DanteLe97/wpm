<?php
/**
 * Security Utils - Xử lý bảo mật và phát hiện hack
 */
if (!defined('ABSPATH')) exit;

/**
 * Verify authentication key
 */
function mac_verify_auth_key($auth_key) {
    $shared_secret = get_option('mac_domain_valid_key', '');
    
    if (empty($shared_secret)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Server not configured properly'
        ], 500);
    }
    
    if ($auth_key !== $shared_secret) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid authentication key'
        ], 401);
    }
    
    return true;
}

/**
 * Detect hack by comparing HTML vs Database
 */
function mac_detect_hack_by_comparison($html) {
    $details = [];
    $is_hacked = false;
    $message = 'Web an toàn';
    
    // Parse DOM một lần
    $dom = mac_parse_html_dom($html);
    $xpath = new DOMXPath($dom);
    
    // 1. Extract title from HTML using XPath
    $title_nodes = $xpath->query('//title');
    $html_title = $title_nodes->length > 0 ? trim($title_nodes[0]->textContent) : '';
    $details['html_title'] = $html_title;
    
    // 2. Check for Japanese characters in HTML title (main check)
    if (mac_has_japanese_characters($html_title)) {
        $is_hacked = true;
        $message = 'Title chứa ký tự tiếng Nhật - Web bị hack tiếng Nhật';
        $details['japanese_in_title'] = true;
    } else {
        $details['japanese_in_title'] = false;
    }
    
    // 3. Check for Japanese characters in meta description
    $html_description = mac_extract_meta_description($html);
    $details['html_description'] = $html_description;
    
    if (mac_has_japanese_characters($html_description)) {
        $is_hacked = true;
        $message = 'Meta description chứa ký tự tiếng Nhật - Web bị hack tiếng Nhật';
        $details['japanese_in_description'] = true;
    } else {
        $details['japanese_in_description'] = false;
    }
    
    return [
        'is_hacked' => $is_hacked,
        'message' => $message,
        'details' => $details
    ];
}

/**
 * Check if text contains Japanese characters
 */
function mac_has_japanese_characters($text) {
    $japanese_patterns = [
        '/[\x{3040}-\x{309F}]/u', // Hiragana
        '/[\x{30A0}-\x{30FF}]/u', // Katakana  
        '/[\x{4E00}-\x{9FAF}]/u', // Kanji
    ];
    
    foreach ($japanese_patterns as $pattern) {
        if (preg_match($pattern, $text)) {
            return true;
        }
    }
    return false;
}

/**
 * Extract meta description from HTML
 */
function mac_extract_meta_description($html) {
    if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

/**
 * Find hidden content in HTML
 */
function mac_find_hidden_content($html) {
    $hidden_patterns = [
        '/<div[^>]*style="[^"]*display\s*:\s*none[^"]*"[^>]*>(.*?)<\/div>/is',
        '/<div[^>]*style="[^"]*visibility\s*:\s*hidden[^"]*"[^>]*>(.*?)<\/div>/is',
        '/<div[^>]*style="[^"]*position\s*:\s*absolute[^"]*left\s*:\s*-9999px[^"]*"[^>]*>(.*?)<\/div>/is',
        '/<div[^>]*style="[^"]*position\s*:\s*absolute[^"]*top\s*:\s*-9999px[^"]*"[^>]*>(.*?)<\/div>/is',
    ];
    
    $hidden_content = [];
    foreach ($hidden_patterns as $pattern) {
        if (preg_match_all($pattern, $html, $matches)) {
            foreach ($matches[1] as $match) {
                $content = trim(strip_tags($match));
                if (!empty($content)) {
                    $hidden_content[] = $content;
                }
            }
        }
    }
    
    return $hidden_content;
}

/**
 * Find suspicious keywords in HTML
 */
function mac_find_suspicious_keywords($html) {
    $suspicious_keywords = [
        'viagra', 'cialis', 'casino', 'poker', 'lottery', 'loan', 'credit',
        'pharmacy', 'pills', 'medication', 'prescription', 'buy now',
        'click here', 'free money', 'earn money', 'work from home',
        'weight loss', 'diet pills', 'muscle building', 'supplements'
    ];
    
    $found_keywords = [];
    foreach ($suspicious_keywords as $keyword) {
        if (stripos($html, $keyword) !== false) {
            $found_keywords[] = $keyword;
        }
    }
    
    return $found_keywords;
}

/**
 * Simulate hacked content for testing
 */
function mac_simulate_hacked_content() {
    return [
        'title' => 'Hacked Site - ハッキングされたサイト',
        'description' => 'This site has been hacked by Japanese hackers',
        'hidden_content' => ['viagra', 'casino', 'free money'],
        'suspicious_keywords' => ['viagra', 'casino', 'lottery']
    ];
}
