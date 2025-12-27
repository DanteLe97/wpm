<?php
/**
 * HTML Utils - Xử lý HTML parsing và các checks
 */
if (!defined('ABSPATH')) exit;

/**
 * Parse HTML DOM (shared function)
 */
function mac_parse_html_dom($html) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    return $dom;
}

/**
 * Extract title from HTML
 */
function mac_extract_title($html) {
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
        return trim(strip_tags($matches[1]));
    }
    return '';
}

/**
 * Count HTML tags
 */
function mac_count_html_tags($html, $tag_name) {
    $pattern = '/<' . preg_quote($tag_name, '/') . '(?:\s[^>]*)?>/i';
    preg_match_all($pattern, $html, $matches);
    return count($matches[0]);
}

/**
 * Check HTML element
 */
function mac_check_html_element($html, $tag, $attribute = '') {
    try {
        if (empty($attribute)) {
            // Simple tag check
            $pattern = '/<' . preg_quote($tag, '/') . '(?:\s[^>]*)?>/i';
        } else {
            // Tag with attribute check
            $pattern = '/<' . preg_quote($tag, '/') . '(?:\s[^>]*)?' . preg_quote($attribute, '/') . '(?:\s[^>]*)?>/i';
        }
        
        return preg_match($pattern, $html) ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check content for pattern
 */
function mac_check_content_for_pattern($meta_data, $pattern) {
    if (empty($meta_data) || !is_array($meta_data)) {
        return false;
    }
    
    foreach ($meta_data as $key => $value) {
        if (is_array($value) && isset($value[0])) {
            $value = $value[0];
        }
        
        if (is_string($value) && preg_match($pattern, $value)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check MAC Menu QR settings
 */
function mac_check_mac_menu_qr_settings($elementor_data) {
    if (empty($elementor_data)) {
        return false;
    }
    
    $data = json_decode($elementor_data, true);
    if (!$data || !is_array($data)) {
        return false;
    }
    
    return mac_search_elementor_for_mac_menu_qr($data);
}

/**
 * Search Elementor for MAC Menu QR
 */
function mac_search_elementor_for_mac_menu_qr($data) {
    if (!is_array($data)) {
        return false;
    }
    
    foreach ($data as $item) {
        if (isset($item['widgetType']) && $item['widgetType'] === 'mac-menu') {
            if (isset($item['settings']['mac_qr']) && $item['settings']['mac_qr'] === 'yes') {
                return true;
            }
        }
        
        if (isset($item['elements']) && is_array($item['elements'])) {
            if (mac_search_elementor_for_mac_menu_qr($item['elements'])) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Check privacy policy link
 */
function mac_check_privacy_policy_link($content) {
    $patterns = [
        '/privacy[^>]*policy/i',
        '/privacy-policy/i',
        '/privacy_policy/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check MAC QR code enabled
 */
function mac_check_mac_qr_code_enabled($meta_data) {
    if (empty($meta_data) || !is_array($meta_data)) {
        return false;
    }
    
    foreach ($meta_data as $key => $value) {
        if (is_array($value) && isset($value[0])) {
            $value = $value[0];
        }
        
        if (is_string($value) && strpos($value, 'mac_qr') !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check MAC QR code value
 */
function mac_check_mac_qr_code_value($meta_data, $expected_value = 'on') {
    if (empty($meta_data) || !is_array($meta_data)) {
        return false;
    }
    
    foreach ($meta_data as $key => $value) {
        if (is_array($value) && isset($value[0])) {
            $value = $value[0];
        }
        
        if (is_string($value) && strpos($value, 'mac_qr') !== false) {
            if (strpos($value, $expected_value) !== false) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Check page HTML for QR
 */
function mac_check_page_html_for_qr($page_id, $expected_value = 'on') {
    $html_content = get_post_field('post_content', $page_id);
    if (empty($html_content)) {
        return false;
    }
    
    return mac_check_html_for_qr_code($html_content, $expected_value);
}

/**
 * Check HTML for QR code
 */
function mac_check_html_for_qr_code($html_content, $expected_value) {
    $patterns = [
        '/mac_qr[^>]*' . preg_quote($expected_value, '/') . '/i',
        '/mac-qr[^>]*' . preg_quote($expected_value, '/') . '/i',
        '/macQr[^>]*' . preg_quote($expected_value, '/') . '/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html_content)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check HTML for selector
 */
function mac_check_html_for_selector($meta_data, $selector, $expected_value = 'on') {
    if (empty($meta_data) || !is_array($meta_data)) {
        return false;
    }
    
    foreach ($meta_data as $key => $value) {
        if (is_array($value) && isset($value[0])) {
            $value = $value[0];
        }
        
        if (is_string($value) && strpos($value, $selector) !== false) {
            if (strpos($value, $expected_value) !== false) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Convert Elementor data to HTML
 */
function mac_convert_elementor_data_to_html($elementor_data) {
    if (empty($elementor_data)) {
        return '';
    }
    
    $data = json_decode($elementor_data, true);
    if (!$data || !is_array($data)) {
        return '';
    }
    
    $html = '';
    foreach ($data as $element) {
        $html .= mac_elementor_element_to_html($element);
    }
    
    return $html;
}

/**
 * Convert Elementor element to HTML
 */
function mac_elementor_element_to_html($element) {
    if (!isset($element['elType']) || !isset($element['widgetType'])) {
        return '';
    }
    
    $html = '';
    
    switch ($element['widgetType']) {
        case 'text-editor':
            $html .= mac_process_text_editor_content($element['settings']['editor'] ?? '');
            break;
        case 'icon-list':
            $html .= mac_process_icon_list_widget($element['settings']);
            break;
        case 'image':
            $html .= mac_process_image_widget($element['settings']);
            break;
        case 'social-icons':
            $html .= mac_process_social_icons_widget($element['settings']);
            break;
        case 'jet-headline':
            $html .= mac_process_jet_headline_widget($element['settings']);
            break;
    }
    
    return $html;
}

/**
 * Process text editor content
 */
function mac_process_text_editor_content($content) {
    if (empty($content)) {
        return '';
    }
    
    return '<div class="text-editor">' . $content . '</div>';
}

/**
 * Process icon list widget
 */
function mac_process_icon_list_widget($settings) {
    if (empty($settings['icon_list'])) {
        return '';
    }
    
    $html = '<ul class="icon-list">';
    foreach ($settings['icon_list'] as $item) {
        $html .= '<li>' . ($item['text'] ?? '') . '</li>';
    }
    $html .= '</ul>';
    
    return $html;
}

/**
 * Process image widget
 */
function mac_process_image_widget($settings) {
    if (empty($settings['image']['url'])) {
        return '';
    }
    
    $src = $settings['image']['url'];
    $alt = $settings['image']['alt'] ?? '';
    
    return '<img src="' . esc_url($src) . '" alt="' . esc_attr($alt) . '">';
}

/**
 * Process social icons widget
 */
function mac_process_social_icons_widget($settings) {
    if (empty($settings['social_icon_list'])) {
        return '';
    }
    
    $html = '<div class="social-icons">';
    foreach ($settings['social_icon_list'] as $item) {
        $html .= '<a href="' . esc_url($item['social'] ?? '#') . '">' . ($item['text'] ?? '') . '</a>';
    }
    $html .= '</div>';
    
    return $html;
}

/**
 * Process jet headline widget
 */
function mac_process_jet_headline_widget($settings) {
    if (empty($settings['headline_text'])) {
        return '';
    }
    
    $tag = $settings['headline_tag'] ?? 'h2';
    $text = $settings['headline_text'];
    
    return '<' . $tag . ' class="jet-headline">' . $text . '</' . $tag . '>';
}
