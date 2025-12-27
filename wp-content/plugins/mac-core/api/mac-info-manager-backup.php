<?php
/**
 * MAC Info Manager - Xử lý API lấy thông tin từ WordPress
 */
if (!defined('ABSPATH')) exit;

class Mac_Info_Manager {
    /**
     * In-memory HTML cache để tránh fetch trùng lặp trong cùng 1 request
     */
    private $html_cache = [];
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        // Debug log
    }

    /**
     * Đăng ký REST API routes
     */
    public function register_rest_routes() {
        // Debug log
        
        // (Removed test endpoint)
        
        // Unified get-infor endpoint with data array support
        register_rest_route('v1', '/get-infor', [
            'methods' => ['POST'],
            'callback' => [$this, 'handle_get_infor'],
            'permission_callback' => '__return_true',
            'args' => [
                'auth_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                // New structure: data array
                'data' => [
                    'required' => false,
                    'type' => 'array',
                ],
                // Backward compatibility: info/action for web-infor and smtp
                'info' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'action' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'fields' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        // Site Health endpoint
        register_rest_route('v1', '/site-health', [
            'methods' => ['POST'],
            'callback' => [$this, 'handle_site_health'],
            'permission_callback' => '__return_true',
            'args' => [
                'auth_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        // (Removed legacy routes: /get-infor/web-infor and /get-infor/smtp)
    }

    /**
     * Xử lý API POST: /wp-json/v1/get-infor
     */
    public function handle_get_infor($request) {
        // Verify auth_key first
        $auth_key = $request->get_param('auth_key');
        $auth_result = $this->verify_auth_key($auth_key);
        if ($auth_result !== true) {
            return $auth_result; // Return error response
        }

        // Check if using new structure with 'data' array
        $data = $request->get_param('data');
        
        if (!empty($data) && is_array($data)) {
            // New structure: process data array
            return $this->handle_data_array($data);
        }
        
        // Backward compatibility: check for info/action parameter
        $selector = $request->get_param('info');
        if (empty($selector)) {
            $selector = $request->get_param('action');
        }
        
        if (!empty($selector)) {
            $action = strtolower((string) $selector);
            
            // Route to legacy handlers
            switch ($action) {
                case 'web-infor':
                    return $this->handle_get_web_info($request);
                case 'smtp':
                    return $this->handle_get_smtp_info($request);
                default:
                    return new WP_REST_Response([
                        'success' => false,
                        'message' => 'Tham số info không hợp lệ. hãy cập cập mac-core mới nhất'
                    ], 400);
            }
        }
        
        // No valid parameters provided
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Vui lòng cung cấp data array hoặc info/action parameter.'
        ], 400);
    }

    /**
     * Handle Site Health API: /wp-json/v1/site-health
     */
    public function handle_site_health($request) {
        // Verify auth_key first
        $auth_key = $request->get_param('auth_key');
        $auth_result = $this->verify_auth_key($auth_key);
        if ($auth_result !== true) {
            return $auth_result; // Return error response
        }

        try {
            // Get site URL
            $site_url = get_site_url();
            
            // Get HTML content
            $html = $this->get_cached_html($site_url);
            
            if (empty($html)) {
                return new WP_REST_Response([
                    'success' => false,
                    'result' => false,
                    'message' => 'Không thể lấy nội dung website'
                ], 200);
            }
            
            // Check for hack by comparing HTML vs Database
            $hack_check = $this->detect_hack_by_comparison($html);
            
            if ($hack_check['is_hacked']) {
                return new WP_REST_Response([
                    'success' => true,
                    'result' => false,
                    'message' => $hack_check['message']
                ], 200);
            } else {
                return new WP_REST_Response([
                    'success' => true,
                    'result' => true,
                    'message' => 'Web an toàn'
                ], 200);
            }
            
        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'result' => false,
                'message' => 'Lỗi kiểm tra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detect hack by comparing HTML vs Database
     */
    private function detect_hack_by_comparison($html) {
        $details = [];
        $is_hacked = false;
        $message = 'Web an toàn';
        
        // 1. Extract title from HTML
        $html_title = $this->extract_title($html);
        $details['html_title'] = $html_title;
        
        // 2. Get title from database
        $db_title = get_option('blogname', '');
        $details['db_title'] = $db_title;
        
        // 3. Compare titles
        if ($html_title !== $db_title) {
            $is_hacked = true;
            $message = 'Title trong HTML khác với Database - Có thể bị hack';
            $details['title_mismatch'] = true;
            $details['title_difference'] = [
                'html' => $html_title,
                'database' => $db_title
            ];
        } else {
            $details['title_mismatch'] = false;
        }
        
        // 4. Check for Japanese characters in HTML title
        if ($this->has_japanese_characters($html_title)) {
            $is_hacked = true;
            $message = 'Title chứa ký tự tiếng Nhật - Web bị hack tiếng Nhật';
            $details['japanese_in_title'] = true;
        } else {
            $details['japanese_in_title'] = false;
        }
        
        // 5. Check for suspicious keywords in HTML content
        $suspicious_keywords = $this->find_suspicious_keywords($html);
        if (!empty($suspicious_keywords)) {
            $is_hacked = true;
            $message = 'Tìm thấy từ khóa đáng ngờ trong nội dung - Web bị hack';
            $details['suspicious_keywords'] = $suspicious_keywords;
        } else {
            $details['suspicious_keywords'] = [];
        }
        
        // 6. Check meta description
        $html_description = $this->extract_meta_description($html);
        $db_description = get_option('blogdescription', '');
        $details['html_description'] = $html_description;
        $details['db_description'] = $db_description;
        
        if ($html_description !== $db_description) {
            $details['description_mismatch'] = true;
            if (!$is_hacked) {
                $is_hacked = true;
                $message = 'Meta description khác với Database - Có thể bị hack';
            }
        } else {
            $details['description_mismatch'] = false;
        }
        
        // 7. Check for hidden content
        $hidden_content = $this->find_hidden_content($html);
        if (!empty($hidden_content)) {
            $is_hacked = true;
            $message = 'Tìm thấy nội dung ẩn đáng ngờ - Web bị hack';
            $details['hidden_content'] = $hidden_content;
        } else {
            $details['hidden_content'] = [];
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
    private function has_japanese_characters($text) {
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
    private function extract_meta_description($html) {
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    /**
     * Find hidden content in HTML
     */
    private function find_hidden_content($html) {
        $hidden_patterns = [
            '/<div[^>]*style="[^"]*display\s*:\s*none[^"]*"[^>]*>(.*?)<\/div>/is',
            '/<div[^>]*style="[^"]*visibility\s*:\s*hidden[^"]*"[^>]*>(.*?)<\/div>/is',
            '/<div[^>]*style="[^"]*position\s*:\s*absolute[^"]*left\s*:\s*-9999px[^"]*"[^>]*>(.*?)<\/div>/is',
            '/<div[^>]*style="[^"]*position\s*:\s*absolute[^"]*top\s*:\s*-9999px[^"]*"[^>]*>(.*?)<\/div>/is',
        ];
        
        $hidden_content = [];
        foreach ($hidden_patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $content) {
                    $clean_content = trim(strip_tags($content));
                    if (!empty($clean_content)) {
                        $hidden_content[] = $clean_content;
                    }
                }
            }
        }
        
        return $hidden_content;
    }

    /**
     * Simulate hacked content for testing
     */
    private function simulate_hacked_content() {
        // Simulate various types of Japanese hack patterns
        $hack_patterns = [
            // Title hack
            '<title>無料動画 - エロ動画サイト | アダルト動画無料視聴</title>',
            
            // Content hack
            '<div class="hacked-content" style="display:none;">
                無料動画、エロ動画、アダルト動画、AV女優
                オンラインカジノ、スロット、ボーナス
                出会い系、メル友、無料登録、無料視聴
            </div>',
            
            // Meta description hack
            '<meta name="description" content="無料動画サイト - エロ動画無料視聴">',
            
            // Hidden text hack
            '<div style="position:absolute;left:-9999px;top:-9999px;">
                無料動画 エロ動画 アダルト動画 AV女優 オンラインカジノ
            </div>'
        ];
        
        // Create a realistic HTML structure with hacked content
        $html = '<!DOCTYPE html>
<html>
<head>
    ' . $hack_patterns[0] . '
    ' . $hack_patterns[2] . '
    <meta charset="UTF-8">
</head>
<body>
    <h1>Welcome to Our Site</h1>
    <p>This is normal content</p>
    ' . $hack_patterns[1] . '
    ' . $hack_patterns[3] . '
</body>
</html>';
        
        return $html;
    }

    /**
     * Verify auth key
     */
    private function verify_auth_key($auth_key) {
        $shared_secret = get_option('mac_domain_valid_key', '');

        if (empty($shared_secret)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'CRM key chưa được đăng ký.'
            ], 403);
        }

        if ($auth_key !== $shared_secret) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Auth key không hợp lệ.'
            ], 403);
        }

        return true;
    }

    /**
     * Handle data array - New structure
     */
    private function handle_data_array($data) {
        $results = [];
        
        foreach ($data as $index => $item) {
            try {
                // Validate item structure
                if (!isset($item['type'])) {
                    $results[] = [
                        'success' => false,
                        'index' => $index,
                        'message' => 'Missing "type" parameter'
                    ];
                    continue;
                }
                
                $type = strtolower(sanitize_text_field($item['type']));
                $name = isset($item['name']) ? sanitize_text_field($item['name']) : null;
                $value = isset($item['value']) ? $item['value'] : null;
                
                // Route to appropriate handler
                switch ($type) {
                    case 'option':
                        $results[] = $this->handle_option($name, $value);
                        break;
                    case 'plugin':
                        $results[] = $this->handle_plugin($item);
                        break;
                    case 'post':
                        $results[] = $this->handle_post($item);
                        break;
                    case 'user':
                        $results[] = $this->handle_user($name);
                        break;
                    case 'sitecheck':
                        $sitecheck_result = $this->handle_sitecheck($item);
                        
                        // If batch sitecheck returns array, merge into results
                        if (is_array($sitecheck_result) && isset($sitecheck_result[0])) {
                            // Batch result - merge individual results
                            foreach ($sitecheck_result as $single_result) {
                                $results[] = $single_result;
                            }
                        } else {
                            // Single result - add normally
                            $results[] = $sitecheck_result;
                        }
                        break;
                    case 'updown':
                        $results[] = $this->handle_updown($item);
                        break;
                    default:
                        $results[] = [
                            'success' => false,
                            'type' => $type,
                            'message' => 'Loại không hợp lệ. Hỗ trợ: option, plugin, post, user, sitecheck, updown'
                        ];
                }
            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'index' => $index,
                    'message' => 'Lỗi xử lý: ' . $e->getMessage()
                ];
            }
        }
        
        return new WP_REST_Response([
            'success' => true,
            'total' => count($data),
            'results' => $results
        ], 200);
    }

    /**
     * Get nested value from array/object using dot notation
     * Example: get_nested_value($data, 'mail.mailer') returns $data['mail']['mailer']
     */
    private function get_nested_value($data, $path) {
        $keys = explode('.', $path);
        $current = $data;
        
        foreach ($keys as $key) {
            if (is_array($current) && isset($current[$key])) {
                $current = $current[$key];
            } elseif (is_object($current) && isset($current->$key)) {
                $current = $current->$key;
            } else {
                return null; // Path doesn't exist
            }
        }
        
        return $current;
    }

    /**
     * Handle OPTION type
     * - No value: GET option value
     * - value = string: CHECK if option value equals (==)
     * - value = object with path: Access nested field
     */
    private function handle_option($name, $value = null) {
        if (empty($name)) {
            return [
                'success' => false,
                'type' => 'option',
                'message' => 'Tham số "name" là bắt buộc cho type option'
            ];
        }

        try {
            $option_value = get_option($name, null);
            
            // Check if option exists
            if ($option_value === null) {
                return [
                    'success' => false,
                    'type' => 'option',
                    'name' => $name,
                    'message' => 'Option không tồn tại'
                ];
            }
            
            // Case 1: value is object with flexible selector (auto-detect)
            if (is_array($value) && count($value) > 0) {
                // Reserved keywords that are NOT selectors
                $reserved_keys = ['check', 'operator', 'value', 'type', 'compare'];
                
                // Find selector key (first non-reserved key)
                $selector_key = null;
                $selector_value = null;
                
                foreach ($value as $key => $val) {
                    if (!in_array($key, $reserved_keys)) {
                        $selector_key = $key;
                        $selector_value = $val;
                        break;
                    }
                }
                
                // If selector found, access nested field
                if ($selector_key && $selector_value) {
                    $nested_value = $this->get_nested_value($option_value, $selector_value);
                    
                    // If check parameter provided, validate the nested value
                    if (isset($value['check'])) {
                        $check = $value['check'];
                        $operator = isset($value['operator']) ? strtolower($value['operator']) : 'equals';
                        $result = false;
                        $message = '';
                        
                        switch (strtolower($check)) {
                            case 'exists':
                                $result = ($nested_value !== null);
                                $message = $result ? 'Value tồn tại' : 'Value không tồn tại';
                                break;
                                
                            case 'empty':
                                // For 'empty' check: null/not exists = empty = true
                                $result = empty($nested_value);
                                $message = $result ? 'Value empty hoặc không tồn tại' : 'Value không empty';
                                break;
                                
                            case 'not_empty':
                                $result = !empty($nested_value);
                                $message = $result ? 'Value tồn tại và không empty' : 'Value empty hoặc không tồn tại';
                                break;
                                
                            case 'not_null':
                                $result = ($nested_value !== null);
                                $message = $result ? 'Value không null' : 'Value là null';
                                break;
                                
                            default:
                                // For other checks, if path doesn't exist, return error
                                if ($nested_value === null) {
                                    return [
                                        'success' => false,
                                        'type' => 'option',
                                        'name' => $name,
                                        'selector' => $selector_key,
                                        $selector_key => $selector_value,
                                        'message' => ucfirst($selector_key) . ' không tồn tại trong option'
                                    ];
                                }
                                
                                // Check with operator
                                $comparison_result = false;
                                $comparison_method = '';
                                
                                // Multiple comparison methods for better compatibility
                                if ($nested_value == $check) {
                                    $comparison_result = true;
                                    $comparison_method = 'loose (==)';
                                } elseif ((string)$nested_value === (string)$check) {
                                    $comparison_result = true;
                                    $comparison_method = 'string comparison';
                                } elseif ((int)$nested_value === (int)$check) {
                                    $comparison_result = true;
                                    $comparison_method = 'integer comparison';
                                } elseif ((bool)$nested_value === (bool)$check) {
                                    $comparison_result = true;
                                    $comparison_method = 'boolean comparison';
                                }
                                
                                // Apply operator
                                switch ($operator) {
                                    case 'not_equals':
                                    case 'not_equal':
                                    case '!=':
                                    case '<>':
                                        $result = !$comparison_result;
                                        $message = $result ? 'Page found but does not match criteria' : 'Page found and matches criteria';
                                        break;
                                        
                                    case 'greater_than':
                                    case 'gt':
                                    case '>':
                                        $result = ($nested_value > $check);
                                        $message = $result ? 'Page found but does not match criteria' : 'Page found and matches criteria';
                                        break;
                                        
                                    case 'less_than':
                                    case 'lt':
                                    case '<':
                                        $result = ($nested_value < $check);
                                        $message = $result ? 'Page found but does not match criteria' : 'Page found and matches criteria';
                                        break;
                                        
                                    case 'greater_equal':
                                    case 'gte':
                                    case '>=':
                                        $result = ($nested_value >= $check);
                                        $message = $result ? 'Page found but does not match criteria' : 'Page found and matches criteria';
                                        break;
                                        
                                    case 'less_equal':
                                    case 'lte':
                                    case '<=':
                                        $result = ($nested_value <= $check);
                                        $message = $result ? 'Page found but does not match criteria' : 'Page found and matches criteria';
                                        break;
                                        
                                    case 'equals':
                                    case 'equal':
                                    case '==':
                                    case '=':
                                    default:
                                        $result = $comparison_result;
                                        $message = $result ? 'Page found and matches criteria' : 'Page found but does not match criteria';
                                        break;
                                }
                                
                                return [
                                    'success' => true,
                                    'type' => 'option',
                                    'name' => $name,
                                    'selector' => $selector_key,
                                    $selector_key => $selector_value,
                                    'result' => $result,
                                    'current_value' => $nested_value,
                                    'expected_value' => $check,
                                    'operator' => $operator,
                                    'comparison_method' => $comparison_method,
                                    'message' => $message
                                ];
                        }
                        
                        return [
                            'success' => true,
                            'type' => 'option',
                            'name' => $name,
                            'selector' => $selector_key,
                            $selector_key => $selector_value,
                            'result' => $result,
                            'current_value' => $nested_value,
                            'message' => $message
                        ];
                    }
                    
                    // No check parameter, return nested value
                    return [
                        'success' => true,
                        'type' => 'option',
                        'name' => $name,
                        'selector' => $selector_key,
                        $selector_key => $selector_value,
                        'data' => $nested_value
                    ];
                }
            }
            
            // Case 2: value is simple string/number (check equality)
            // Support for operator in simple value: {"value": "1", "operator": "not_equals"}
            if ($value !== null && $value !== '') {
                $operator = 'equals'; // Default
                $check_value = $value;
                
                // Check if value is an object with operator
                if (is_array($value) && isset($value['value']) && isset($value['operator'])) {
                    $check_value = $value['value'];
                    $operator = strtolower($value['operator']);
                }
                
                // Handle array values for contains_all, contains_any, etc.
                if (is_array($check_value) && in_array($operator, ['contains_all', 'contains_any', 'contains_all_with_any'])) {
                    return $this->handle_array_comparison($name, $option_value, $check_value, $operator);
                }
                
                // Special handling for page_on_front - compare with page title
                if ($name === 'page_on_front' && is_numeric($option_value)) {
                    $page_id = (int)$option_value;
                    $page = get_post($page_id);
                    if ($page) {
                        $actual_title = $page->post_title;
                        $option_value = $actual_title; // Use title for comparison
                    }
                }
                // Multiple comparison methods for better compatibility
                $comparison_result = false;
                $comparison_method = '';
                
                // Prioritize string comparison for text values
                if (is_string($option_value) && is_string($check_value)) {
                    // Method 1: String comparison (exact)
                    if ((string)$option_value === (string)$check_value) {
                        $comparison_result = true;
                        $comparison_method = 'string comparison (exact)';
                    }
                    // Method 2: String comparison (case-insensitive)
                    elseif (strtolower((string)$option_value) === strtolower((string)$check_value)) {
                        $comparison_result = true;
                        $comparison_method = 'string comparison (case-insensitive)';
                    }
                } else {
                    // For page_on_front, prioritize string comparison
                    if ($name === 'page_on_front') {
                        // Method 1: String comparison (case-insensitive)
                        if (strtolower((string)$option_value) === strtolower((string)$check_value)) {
                            $comparison_result = true;
                            $comparison_method = 'string comparison (case-insensitive)';
                        }
                        // Method 2: String comparison (exact)
                        elseif ((string)$option_value === (string)$check_value) {
                            $comparison_result = true;
                            $comparison_method = 'string comparison (exact)';
                        }
                    } else {
                // Method 1: Loose comparison (==)
                if ($option_value == $check_value) {
                    $comparison_result = true;
                    $comparison_method = 'loose (==)';
                }
                // Method 2: String comparison
                elseif ((string)$option_value === (string)$check_value) {
                    $comparison_result = true;
                    $comparison_method = 'string comparison';
                }
                // Method 3: Integer comparison
                elseif ((int)$option_value === (int)$check_value) {
                    $comparison_result = true;
                    $comparison_method = 'integer comparison';
                }
                // Method 4: Boolean comparison
                elseif ((bool)$option_value === (bool)$check_value) {
                    $comparison_result = true;
                    $comparison_method = 'boolean comparison';
                        }
                    }
                }
                
                // Apply operator
                $result = false;
                $message = '';
                
                switch ($operator) {
                    case 'not_equals':
                    case 'not_equal':
                    case '!=':
                    case '<>':
                        $result = !$comparison_result;
                        $message = $result ? 'Value khác expected' : 'Value giống expected';
                        break;
                        
                    case 'greater_than':
                    case 'gt':
                    case '>':
                        $result = ($option_value > $check_value);
                        $message = $result ? 'Value lớn hơn expected' : 'Value không lớn hơn expected';
                        break;
                        
                    case 'less_than':
                    case 'lt':
                    case '<':
                        $result = ($option_value < $check_value);
                        $message = $result ? 'Value nhỏ hơn expected' : 'Value không nhỏ hơn expected';
                        break;
                        
                    case 'greater_equal':
                    case 'gte':
                    case '>=':
                        $result = ($option_value >= $check_value);
                        $message = $result ? 'Value lớn hơn hoặc bằng expected' : 'Value nhỏ hơn expected';
                        break;
                        
                    case 'less_equal':
                    case 'lte':
                    case '<=':
                        $result = ($option_value <= $check_value);
                        $message = $result ? 'Value nhỏ hơn hoặc bằng expected' : 'Value lớn hơn expected';
                        break;
                        
                    case 'equals':
                    case 'equal':
                    case '==':
                    case '=':
                    default:
                        $result = $comparison_result;
                        $message = $result ? 'Value khớp' : 'Value không khớp';
                        break;
                }
                
                $response = [
                    'success' => true,
                    'type' => 'option',
                    'name' => $name,
                    'result' => $result,
                    'current_value' => $option_value,
                    'expected_value' => $check_value,
                    'operator' => $operator,
                    'comparison_method' => $comparison_method,
                    'message' => $message
                ];
                
                // Add debug info for page_on_front
                if ($name === 'page_on_front') {
                    $response['debug'] = [
                        'original_option_value' => get_option($name),
                        'is_numeric' => is_numeric(get_option($name)),
                        'special_handling_applied' => ($name === 'page_on_front' && is_numeric(get_option($name)))
                    ];
                }
                
                return $response;
            }
            
            // Case 3: No value provided, return full option value
            return [
                'success' => true,
                'type' => 'option',
                'name' => $name,
                'data' => $option_value
            ];
            
        } catch (Exception $e) {
            error_log('MAC Info Manager - Option Error: ' . $e->getMessage());
            return [
                'success' => false,
                'type' => 'option',
                'name' => $name,
                'message' => 'Lỗi khi lấy option: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle array comparison for contains_all, contains_any, etc.
     */
    private function handle_array_comparison($name, $option_value, $check_array, $operator) {
        // For post type, we need to get all posts and check their titles
        if ($name === 'any') {
            // This should be handled in handle_post function
            return $this->handle_post_array_comparison($check_array, $operator);
        }
        
        // For other types, convert to string and check
        $option_string = (string)$option_value;
        $found_items = [];
        $missing_items = [];
        
        foreach ($check_array as $item) {
            if (stripos($option_string, $item) !== false) {
                $found_items[] = $item;
            } else {
                $missing_items[] = $item;
            }
        }
        
        $result = false;
        $message = '';
        
        switch ($operator) {
            case 'contains_all':
                $result = empty($missing_items);
                $message = $result ? 'Chứa tất cả items' : 'Thiếu items: ' . implode(', ', $missing_items);
                break;
                
            case 'contains_any':
                $result = !empty($found_items);
                $message = $result ? 'Chứa ít nhất 1 item: ' . implode(', ', $found_items) : 'Không chứa item nào';
                break;
                
            case 'contains_all_with_any':
                // This is a complex case - would need specific implementation
                $result = empty($missing_items);
                $message = $result ? 'Chứa tất cả items' : 'Thiếu items: ' . implode(', ', $missing_items);
                break;
        }
        
        return [
            'success' => true,
            'type' => 'option',
            'name' => $name,
            'result' => $result,
            'operator' => $operator,
            'found_items' => $found_items,
            'missing_items' => $missing_items,
            'total_required' => count($check_array),
            'total_found' => count($found_items),
            'message' => $message
        ];
    }
    
    /**
     * Handle array comparison for post type
     */
    private function handle_post_array_comparison($check_array, $operator) {
        // This will be implemented in handle_post function
        return [
            'success' => true,
            'type' => 'post',
            'name' => 'any',
            'result' => false,
            'operator' => $operator,
            'message' => 'Array comparison for posts not implemented yet'
        ];
    }

    /**
     * Handle PLUGIN type
     * - name = "any": GET all plugins
     * - No value: GET plugin info
     * - With value: CHECK if plugin version equals (==)
     * - With activate/active: CHECK if plugin is active
     */
    private function handle_plugin($item) {
        $name = isset($item['name']) ? sanitize_text_field($item['name']) : null;
        $value = isset($item['value']) ? $item['value'] : null;
        $activate = isset($item['activate']) ? $item['activate'] : null;
        if ($activate === null) {
            $activate = isset($item['active']) ? $item['active'] : null;
        }
        
        if (empty($name)) {
            return [
                'success' => false,
                'type' => 'plugin',
                'message' => 'Tham số "name" là bắt buộc cho type plugin'
            ];
        }

        try {
            // Require plugin.php functions
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            $all_plugins = get_plugins();
            
            // Case 1: Get all plugins
            if (strtolower($name) === 'any') {
                $plugins_data = [];
                foreach ($all_plugins as $plugin_path => $plugin_data) {
                    $plugins_data[] = [
                        'name' => $plugin_data['Name'],
                        'version' => $plugin_data['Version'],
                        'active' => is_plugin_active($plugin_path),
                        'path' => $plugin_path
                    ];
                }
                
                return [
                    'success' => true,
                    'type' => 'plugin',
                    'name' => 'any',
                    'count' => count($plugins_data),
                    'data' => $plugins_data
                ];
            }
            
            // Case 2: Find specific plugin
            $plugin_found = null;
            $plugin_path_found = null;
            
            foreach ($all_plugins as $plugin_path => $plugin_data) {
                // Match by folder name or plugin name
                if (stripos($plugin_path, $name) !== false || stripos($plugin_data['Name'], $name) !== false) {
                    $plugin_found = $plugin_data;
                    $plugin_path_found = $plugin_path;
                    break;
                }
            }
            
            if (!$plugin_found) {
                return [
                    'success' => false,
                    'type' => 'plugin',
                    'name' => $name,
                    'message' => 'Plugin không tồn tại'
                ];
            }
            
            // Case 3: Check activate/active status if provided
            if ($activate !== null) {
                $is_active = is_plugin_active($plugin_path_found);
                $expected_active = (bool) $activate;
                $result = ($is_active === $expected_active);
                
                return [
                    'success' => true,
                    'type' => 'plugin',
                    'name' => $name,
                    'result' => $result,
                    'is_active' => $is_active,
                    'expected_active' => $expected_active,
                    'message' => $result ? 
                        ($is_active ? 'Plugin đang active' : 'Plugin không active (đúng như mong đợi)') : 
                        ($is_active ? 'Plugin đang active (không mong đợi)' : 'Plugin không active')
                ];
            }
            
            // Case 4: Check version if value provided
            if ($value !== null && $value !== '') {
                $result = ($plugin_found['Version'] == $value);
                return [
                    'success' => true,
                    'type' => 'plugin',
                    'name' => $name,
                    'result' => $result,
                    'current_version' => $plugin_found['Version'],
                    'expected_version' => $value,
                    'message' => $result ? 'Plugin version khớp' : 'Plugin version không khớp'
                ];
            }
            
            // Case 5: Return plugin info
            return [
                'success' => true,
                'type' => 'plugin',
                'name' => $name,
                'data' => [
                    'name' => $plugin_found['Name'],
                    'version' => $plugin_found['Version'],
                    'active' => is_plugin_active($plugin_path_found),
                    'path' => $plugin_path_found,
                    'author' => $plugin_found['Author'] ?? '',
                    'description' => $plugin_found['Description'] ?? ''
                ]
            ];
            
        } catch (Exception $e) {
            error_log('MAC Info Manager - Plugin Error: ' . $e->getMessage());
            return [
                'success' => false,
                'type' => 'plugin',
                'name' => $name,
                'message' => 'Lỗi khi lấy thông tin plugin: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle POST type
     * - name = "any": GET all posts
     * - No value: GET post info
     * - value = array: CHECK if post content contains values (auto include_data)
     * - value = object: CHECK with include_data and exclude_data
     */
    private function handle_post($item) {
        $name = isset($item['name']) ? sanitize_text_field($item['name']) : null;
        $value = isset($item['value']) ? $item['value'] : null;
        $post_type = isset($item['post_type']) ? sanitize_text_field($item['post_type']) : 'any';
        
        
        if (empty($name)) {
            return [
                'success' => true,
                'type' => 'post',
                'name' => $name,
                'result' => false,
                'is_found' => false,
                'expected_found' => true,
                'message' => 'Tham số "name" là bắt buộc cho type post'
            ];
        }

        try {
            // Case 1: Get all posts
            if (strtolower($name) === 'any') {
                // Handle special post types with meta filtering
                $special_post_types = [
                    'jet_templates' => [
                        'post_types' => ['jet-theme-core', 'jet-theme-template'],
                        'meta_filter' => null
                    ],
                    'jet_footer' => [
                        'post_types' => ['jet-theme-core', 'jet-theme-template'],
                        'meta_filter' => [
                            'key' => '_elementor_template_type',
                            'value' => 'jet_footer'
                        ]
                    ],
                    'jet_header' => [
                        'post_types' => ['jet-theme-core', 'jet-theme-template'],
                        'meta_filter' => [
                            'key' => '_elementor_template_type',
                            'value' => 'jet_header'
                        ]
                    ]
                ];
                
                if (isset($special_post_types[$post_type])) {
                    $config = $special_post_types[$post_type];
                    
                    
                    if ($config['meta_filter']) {
                        // Use custom query for meta filtering
                        global $wpdb;
                        $meta_key = $config['meta_filter']['key'];
                        $meta_value = $config['meta_filter']['value'];
                        
                        $post_ids = $wpdb->get_col($wpdb->prepare("
                            SELECT p.ID
                            FROM {$wpdb->posts} p
                            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                            WHERE p.post_type IN ('" . implode("','", $config['post_types']) . "')
                            AND p.post_status = 'publish'
                            AND pm.meta_key = %s
                            AND pm.meta_value = %s
                        ", $meta_key, $meta_value));
                        
                        if (empty($post_ids)) {
                            return [
                                'success' => true,
                                'type' => 'post',
                                'name' => 'any',
                                'post_type' => $post_type,
                                'count' => 0,
                                'data' => []
                            ];
                        }
                        
                        $args = [
                            'post_type' => $config['post_types'],
                            'post__in' => $post_ids,
                            'post_status' => 'publish',
                            'posts_per_page' => -1,
                            'orderby' => 'title',
                            'order' => 'ASC'
                        ];
                    } else {
                        // Standard query for post types without meta filtering
                        $args = [
                            'post_type' => $config['post_types'],
                            'post_status' => 'publish',
                            'posts_per_page' => -1,
                            'orderby' => 'title',
                            'order' => 'ASC'
                        ];
                    }
                } else {
                    // Standard WordPress post types
                $args = [
                    'post_type' => $post_type === 'any' ? ['post', 'page'] : $post_type,
                    'post_status' => 'any',
                    'posts_per_page' => -1,
                    'orderby' => 'date',
                    'order' => 'DESC'
                ];
                }
                
                $posts = get_posts($args);
                $posts_data = [];
                
                
                foreach ($posts as $post) {
                    $post_data = [
                        'ID' => $post->ID,
                        'post_title' => $post->post_title,
                        'post_name' => $post->post_name,
                        'post_type' => $post->post_type,
                        'post_status' => $post->post_status,
                        'post_date' => $post->post_date,
                        'post_url' => get_permalink($post->ID)
                    ];
                    
                    // Add post type specific info based on configuration
                    $post_type_configs = [
                        'jet-theme-core' => [
                            'template_type' => '_elementor_template_type',
                            'conditions' => '_elementor_conditions',
                            'elementor_data' => '_elementor_data',
                            'special_fields' => ['template_type', 'has_conditions', 'has_elementor_data']
                        ],
                        'jet-theme-template' => [
                            'template_type' => '_elementor_template_type',
                            'conditions' => '_elementor_conditions',
                            'elementor_data' => '_elementor_data',
                            'special_fields' => ['template_type', 'has_conditions', 'has_elementor_data']
                        ],
                        'wpcf7_contact_form' => [
                            'mail_settings' => '_mail',
                            'mail_2_settings' => '_mail_2',
                            'form_content' => '_form',
                            'special_fields' => ['mail_settings', 'mail_2_settings', 'form_content', 'has_mail_setup', 'has_mail_2_setup', 'mail_recipient', 'mail_subject', 'mail_sender']
                        ]
                    ];
                    
                    if (isset($post_type_configs[$post->post_type])) {
                        $config = $post_type_configs[$post->post_type];
                        $meta_data = [];
                        
                        // Get all meta fields
                        foreach ($config as $field => $meta_key) {
                            if ($field !== 'special_fields') {
                                $meta_data[$field] = get_post_meta($post->ID, $meta_key, true);
                            }
                        }
                        
                        // Add post info to meta_data for debugging
                        $meta_data['ID'] = $post->ID;
                        $meta_data['post_type'] = $post->post_type;
                        $meta_data['post_title'] = $post->post_title;
                        
                        // Add meta data to post_data
                        foreach ($meta_data as $field => $value) {
                            $post_data[$field] = $value ?: (is_array($value) ? [] : '');
                        }
                        
                        // Add computed fields
                        if (in_array('template_type', $config['special_fields'])) {
                            $post_data['template_type'] = $meta_data['template_type'] ?: 'unknown';
                        }
                        
                        if (in_array('has_conditions', $config['special_fields'])) {
                            $post_data['has_conditions'] = !empty($meta_data['conditions']);
                        }
                        
                        if (in_array('has_elementor_data', $config['special_fields'])) {
                            $post_data['has_elementor_data'] = !empty($meta_data['elementor_data']);
                        }
                        
                        if (in_array('has_mail_setup', $config['special_fields'])) {
                            // Check if mail settings exist and recipient is valid (not placeholder)
                            $has_mail_settings = !empty($meta_data['mail_settings']) && isset($meta_data['mail_settings']['recipient']);
                            $recipient_raw = isset($meta_data['mail_settings']['recipient']) ? $meta_data['mail_settings']['recipient'] : '';
                            $recipient = trim((string) $recipient_raw);
                            
                            // Treat common placeholders and any [token] as invalid recipients
                            $placeholder_tokens = ['[_site_admin_email]', '[your-email]', '[_site_title]'];
                            $lower_placeholder_tokens = array_map('strtolower', $placeholder_tokens);
                            $is_placeholder = in_array(strtolower($recipient), $lower_placeholder_tokens, true) || (preg_match('/^\s*\[[^\]]+\]\s*$/', $recipient) === 1);
                            
                            $is_valid_recipient = !empty($recipient) && !$is_placeholder && (filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false);
                            
                            $post_data['has_mail_setup'] = $has_mail_settings && $is_valid_recipient;
                        }
                        
                        if (in_array('has_mail_2_setup', $config['special_fields'])) {
                            $post_data['has_mail_2_setup'] = !empty($meta_data['mail_2_settings']) && isset($meta_data['mail_2_settings']['mail']);
                        }
                        
                        if (in_array('mail_recipient', $config['special_fields'])) {
                            $post_data['mail_recipient'] = isset($meta_data['mail_settings']['recipient']) ? $meta_data['mail_settings']['recipient'] : '';
                        }
                        
                        if (in_array('mail_subject', $config['special_fields'])) {
                            $post_data['mail_subject'] = isset($meta_data['mail_settings']['subject']) ? $meta_data['mail_settings']['subject'] : '';
                        }
                        
                        if (in_array('mail_sender', $config['special_fields'])) {
                            $post_data['mail_sender'] = isset($meta_data['mail_settings']['sender']) ? $meta_data['mail_settings']['sender'] : '';
                        }
                        
                        // Special checks
                        if ($post->post_type === 'jet-theme-core' || $post->post_type === 'jet-theme-template') {
                            $template_type = $meta_data['template_type'];
                            $elementor_data = $meta_data['elementor_data'];
                            
                            if ($template_type === 'jet_footer' && !empty($elementor_data)) {
                                $post_data['has_privacy_link'] = $this->check_privacy_policy_link($elementor_data);
                            }
                            
                            // Check for mac-menu QR settings in Elementor data
                            if (!empty($elementor_data)) {
                                $post_data['has_mac_menu_qr'] = $this->check_mac_menu_qr_settings($elementor_data);
                            }
                        }
                        
                        // Check for custom search patterns if value is provided
                        if (isset($item['value']) && !empty($item['value'])) {
                            
                            // Handle different value formats - check for new format first
                            if ((is_array($item['value']) || is_object($item['value'])) && isset($item['value']['value']) && isset($item['value']['operator'])) {
                                $search_value = $item['value']['value'];
                                $operator = strtolower($item['value']['operator']);
                                
                                // Handle array comparison for post titles
                                if (is_array($search_value) && in_array($operator, ['contains_all', 'contains_any', 'contains_all_with_any', 'not_equals'])) {
                                    $found_items = [];
                                    $missing_items = [];
                                    
                                    // Check if we need to check a specific field instead of post title
                                    $check_field = isset($item['value']['check_field']) ? $item['value']['check_field'] : null;
                                    
                                    if ($check_field === 'mac_qr_code') {
                                        // Check mac_qr_code for each page
                                        $expected_value = isset($item['value']['expected_value']) ? $item['value']['expected_value'] : 'on';
                                        
                                        foreach ($search_value as $title) {
                                            // Case-insensitive comparison for page title
                                            if (stripos($post_data['post_title'], $title) !== false) {
                                                // Check if this page has mac_qr_code with specific value
                                                $has_value = $this->check_mac_qr_code_value($meta_data, $expected_value);
                                                
                                                if ($operator === 'not_equals') {
                                                    // For not_equals, we want pages that DON'T have the value
                                                    if (!$has_value) {
                                                        $found_items[] = $title;
                                                    } else {
                                                        $missing_items[] = $title;
                                                    }
                                                } else {
                                                    // For other operators, we want pages that DO have the value
                                                    if ($has_value) {
                                                        $found_items[] = $title;
                                                    } else {
                                                        $missing_items[] = $title;
                                                    }
                                                }
                                            } else {
                                                $missing_items[] = $title;
                                            }
                                        }
                                    } else {
                                        // Default behavior - check post titles or other fields
                                        foreach ($search_value as $title) {
                                            // Case-insensitive comparison for page title
                                            if (stripos($post_data['post_title'], $title) !== false) {
                                                // If check_field is specified, check that field instead of just title match
                                                if ($check_field) {
                                                    $expected_value = isset($item['value']['expected_value']) ? $item['value']['expected_value'] : 'on';
                                                    $has_value = $this->check_field_value($meta_data, $check_field, $expected_value);
                                                    
                                                    if ($operator === 'not_equals') {
                                                        // For not_equals, we want pages that DON'T have the value
                                                        if (!$has_value) {
                                                            $found_items[] = $title;
                                                        } else {
                                                            $missing_items[] = $title;
                                                        }
                                                    } else {
                                                        // For other operators, we want pages that DO have the value
                                                        if ($has_value) {
                                                            $found_items[] = $title;
                                                        } else {
                                                            $missing_items[] = $title;
                                                        }
                                                    }
                                                } else {
                                                    // No check_field specified, just check title match
                                                    $found_items[] = $title;
                                                }
                                            } else {
                                                $missing_items[] = $title;
                                            }
                                        }
                                    }
                                    
                                    $result = false;
                                    $message = '';
                                    
                                    switch ($operator) {
                                        case 'contains_all':
                                            $result = empty($missing_items);
                                            $message = $result ? 'Chứa tất cả page titles' : 'Thiếu page titles: ' . implode(', ', $missing_items);
                                            break;
                                            
                                        case 'contains_any':
                                            $result = !empty($found_items);
                                            $message = $result ? 'Chứa ít nhất 1 page title: ' . implode(', ', $found_items) : 'Không chứa page title nào';
                                            break;
                                            
                                        case 'not_equals':
                                            $result = !empty($found_items);
                                            $message = $result ? 'Có pages KHÔNG có ' . $check_field . ' = ' . $expected_value . ': ' . implode(', ', $found_items) : 'Tất cả pages đều có ' . $check_field . ' = ' . $expected_value;
                                            break;
                                            
                                        case 'contains_all_with_any':
                                            // For this case, we need to check if all required pages exist AND at least one of the optional pages
                                            $all_pages = $search_value;
                                            $optional_pages = isset($item['value']['optional_pages']) ? $item['value']['optional_pages'] : [];
                                            $required_pages = array_diff($all_pages, $optional_pages);
                                            
                                            $required_found = [];
                                            $required_missing = [];
                                            $optional_found = [];
                                            
                                            foreach ($required_pages as $title) {
                                                // Check both title and slug (case-insensitive)
                                                $title_match = stripos($post_data['post_title'], $title) !== false;
                                                $slug_match = stripos($post_data['post_name'], sanitize_title($title)) !== false;
                                                
                                                if ($title_match || $slug_match) {
                                                    $required_found[] = $title;
                                                } else {
                                                    $required_missing[] = $title;
                                                }
                                            }
                                            
                                            foreach ($optional_pages as $title) {
                                                // Check both title and slug (case-insensitive)
                                                $title_match = stripos($post_data['post_title'], $title) !== false;
                                                $slug_match = stripos($post_data['post_name'], sanitize_title($title)) !== false;
                                                
                                                if ($title_match || $slug_match) {
                                                    $optional_found[] = $title;
                                                }
                                            }
                                            
                                            $result = empty($required_missing) && !empty($optional_found);
                                            $message = $result ? 'Có đủ required pages và ít nhất 1 optional page' : 'Thiếu required pages hoặc không có optional page nào';
                                            
                                            $post_data['required_found'] = $required_found;
                                            $post_data['required_missing'] = $required_missing;
                                            $post_data['optional_found'] = $optional_found;
                                            break;
                                    }
                                    
                                    $post_data['search_value'] = $search_value;
                                    $post_data['operator'] = $operator;
                                    $post_data['search_result'] = $result;
                                    $post_data['found_items'] = $found_items;
                                    $post_data['missing_items'] = $missing_items;
                                    $post_data['message'] = $message;
                                } else {
                                    // Single value comparison
                                    $check_field = isset($item['value']['check_field']) ? $item['value']['check_field'] : null;
                                    $expected_value = isset($item['value']['expected_value']) ? $item['value']['expected_value'] : null;
                                    
                                    
                                    if ($check_field && $expected_value) {
                                        // Check specific field value
                                        // Check if HTML check is requested
                                        $html_check = isset($item['value']['html']) && $item['value']['html'] === 'true';
                                        
                                        
                                        if ($html_check) {
                                            // HTML check with flexible selector
                                            $found = $this->check_html_for_selector($meta_data, $check_field, $expected_value);
                                        } else {
                                            // Regular field check
                                            $found = $this->check_field_value($meta_data, $check_field, $expected_value);
                                        }
                                    } else {
                                        // Check content pattern
                                        $found = $this->check_content_for_pattern($meta_data, $search_value);
                                    }
                                    
                                    // Apply operator logic
                                    $result = false;
                                    switch ($operator) {
                                        case 'equals':
                                        case 'contains':
                                            $result = $found;
                                            break;
                                        case 'not_equals':
                                        case 'not_contains':
                                            $result = !$found;
                                            break;
                                        case 'exists':
                                            $result = $found;
                                            break;
                                        case 'not_exists':
                                            $result = !$found;
                                            break;
                                        default:
                                            $result = $found;
                                    }
                                    
                                    $post_data['search_value'] = $search_value;
                                    $post_data['operator'] = $operator;
                                    $post_data['search_result'] = $result;
                                    $post_data['found'] = $found;
                                    
                                    if ($check_field) {
                                        $post_data['check_field'] = $check_field;
                                        $post_data['expected_value'] = $expected_value;
                                    }
                    
                    // Add debug info for jet_footer
                    if (isset($GLOBALS['jet_footer_debug'])) {
                        $post_data['debug_info'] = $GLOBALS['jet_footer_debug'];
                        unset($GLOBALS['jet_footer_debug']); // Clear after use
                    }
                                }
                            } else {
                                // Legacy format: array of patterns or single pattern
                                $search_patterns = is_array($item['value']) ? $item['value'] : [$item['value']];
                                $found_patterns = [];
                                $not_found_patterns = [];
                                
                                foreach ($search_patterns as $pattern) {
                                    if ($this->check_content_for_pattern($meta_data, $pattern)) {
                                        $found_patterns[] = $pattern;
                                    } else {
                                        $not_found_patterns[] = $pattern;
                                    }
                                }
                                
                                $post_data['search_patterns'] = $search_patterns;
                                $post_data['found_patterns'] = $found_patterns;
                                $post_data['not_found_patterns'] = $not_found_patterns;
                                $post_data['has_any_pattern'] = !empty($found_patterns);
                            }
                        }
                    }
                    
                    $posts_data[] = $post_data;
                }
                
                        // Handle array comparison for all posts
                        if (isset($item['value']) && is_array($item['value']) && isset($item['value']['value']) && isset($item['value']['operator'])) {
                            $search_value = $item['value']['value'];
                            $operator = strtolower($item['value']['operator']);
                            
                            if (is_array($search_value) && in_array($operator, ['contains_all', 'contains_any', 'contains_all_with_any', 'not_equals'])) {
                        $all_titles = array_column($posts_data, 'post_title');
                        $found_items = [];
                        $missing_items = [];
                        
                        // Check if we need to check a specific field instead of post title
                        $check_field = isset($item['value']['check_field']) ? $item['value']['check_field'] : null;
                        
                        foreach ($search_value as $title) {
                            $found = false;
                            
                            if ($check_field === 'mac_qr_code') {
                                // Check mac_qr_code for each page
                                $expected_value = isset($item['value']['expected_value']) ? $item['value']['expected_value'] : 'on';
                                
                                foreach ($posts_data as $post_data) {
                                    // Case-insensitive comparison for page title
                                    if (stripos($post_data['post_title'], $title) !== false) {
                                        // Check if this page has mac_qr_code with specific value
                                        $has_value = isset($post_data['has_mac_menu_qr']) && $post_data['has_mac_menu_qr'];
                                        
                                        if ($operator === 'not_equals') {
                                            // For not_equals, we want pages that DON'T have the value
                                            if (!$has_value) {
                                                $found = true;
                                                break;
                                            }
                                        } else {
                                            // For other operators, we want pages that DO have the value
                                            if ($has_value) {
                                                $found = true;
                                                break;
                                            }
                                        }
                                    }
                                }
                            } else {
                                // Default behavior - check post titles
                                foreach ($all_titles as $post_title) {
                                    // Case-insensitive comparison
                                    if (stripos($post_title, $title) !== false) {
                                        $found = true;
                                        break;
                                    }
                                }
                            }
                            
                            if ($found) {
                                $found_items[] = $title;
                            } else {
                                $missing_items[] = $title;
                            }
                        }
                        
                        $result = false;
                        $message = '';
                        
                        switch ($operator) {
                            case 'contains_all':
                                $result = empty($missing_items);
                                $message = $result ? 'Có tất cả required pages' : 'Thiếu pages: ' . implode(', ', $missing_items);
                                break;
                                
                            case 'contains_any':
                                $result = !empty($found_items);
                                $message = $result ? 'Có ít nhất 1 page: ' . implode(', ', $found_items) : 'Không có page nào';
                                break;
                                
                            case 'not_equals':
                                $result = !empty($found_items);
                                $message = $result ? 'Có pages KHÔNG có ' . $check_field . ' = ' . $expected_value . ': ' . implode(', ', $found_items) : 'Tất cả pages đều có ' . $check_field . ' = ' . $expected_value;
                                break;
                                
                            case 'contains_all_with_any':
                                $all_pages = $search_value;
                                $optional_pages = isset($item['value']['optional_pages']) ? $item['value']['optional_pages'] : [];
                                $required_pages = array_diff($all_pages, $optional_pages);
                                
                                $required_found = [];
                                $required_missing = [];
                                $optional_found = [];
                                
                                foreach ($required_pages as $title) {
                                    $found = false;
                                    foreach ($posts_data as $post_data) {
                                        // Check both title and slug (case-insensitive)
                                        $title_match = stripos($post_data['post_title'], $title) !== false;
                                        $slug_match = stripos($post_data['post_name'], sanitize_title($title)) !== false;
                                        
                                        if ($title_match || $slug_match) {
                                            $found = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($found) {
                                        $required_found[] = $title;
                                    } else {
                                        $required_missing[] = $title;
                                    }
                                }
                                
                                foreach ($optional_pages as $title) {
                                    $found = false;
                                    foreach ($posts_data as $post_data) {
                                        // Check both title and slug (case-insensitive)
                                        $title_match = stripos($post_data['post_title'], $title) !== false;
                                        $slug_match = stripos($post_data['post_name'], sanitize_title($title)) !== false;
                                        
                                        if ($title_match || $slug_match) {
                                            $found = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($found) {
                                        $optional_found[] = $title;
                                    }
                                }
                                
                                $result = empty($required_missing) && !empty($optional_found);
                                $message = $result ? 'Có đủ required pages và ít nhất 1 optional page' : 'Thiếu required pages hoặc không có optional page nào';
                
                return [
                    'success' => true,
                                    'total' => count($posts_data),
                    'type' => 'post',
                                    'result' => $result,
                                    'operator' => $operator,
                                    'required_found' => $required_found,
                                    'required_missing' => $required_missing,
                                    'optional_found' => $optional_found,
                                    'message' => $message,
                                    'results' => $posts_data
                                ];
                        }
                        
                        return [
                            'success' => true,
                            'total' => count($posts_data),
                            'type' => 'post',
                            'result' => $result,
                            'operator' => $operator,
                            'found_items' => $found_items,
                            'missing_items' => $missing_items,
                            'total_required' => count($search_value),
                            'total_found' => count($found_items),
                            'message' => $message,
                            'results' => $posts_data
                        ];
                    }
                }
                
                // If posts have HTML check result, return standard format for each post
                if (count($posts_data) > 0 && isset($posts_data[0]['check_field'])) {
                    $results = [];
                    foreach ($posts_data as $post_data) {
                        $results[] = [
                            'success' => true,
                            'type' => 'post',
                            'name' => $post_data['post_title'],
                            'result' => $post_data['search_result'],
                            'is_found' => $post_data['found'],
                            'expected_found' => $post_data['expected_value'] === 'on',
                            'message' => $post_data['message']
                        ];
                    }
                    
                    // Special handling for jet_footer to include post_type
                    if ($post_type === 'jet_footer') {
                        return [
                            'success' => true,
                            'total' => count($results),
                            'post_type' => 'jet_footer',
                            'type' => 'post',
                            'results' => $results
                        ];
                    }
                    
                    return [
                        'success' => true,
                        'total' => count($results),
                        'results' => $results
                    ];
                }
                
                // Special handling for Contact Form 7 and Jet Footer with mail settings check
                if (($post_type === 'wpcf7_contact_form' || $post_type === 'jet_footer') && isset($item['value']) && is_array($item['value'])) {
                    $results = [];
                    foreach ($posts_data as $post_data) {
                        // Check if this is a mail settings check (supports both list and keyed formats)
                        $valueArray = $item['value'];
                        $is_mail_check = (
                            (isset($valueArray['path']) && strtolower((string)$valueArray['path']) === 'recipient') ||
                            (isset($valueArray['check']) && in_array(strtolower((string)$valueArray['check']), ['empty','not_empty','exists','not_null'], true)) ||
                            in_array('recipient', $valueArray, true) ||
                            in_array('mail', $valueArray, true) ||
                            in_array('to:', $valueArray, true)
                        );
                        
                        // Check if this is a jet_footer check
                        $is_jet_footer_check = $post_type === 'jet_footer';
                        
                        if ($is_mail_check && $post_type === 'wpcf7_contact_form') {
                            // Check if form has valid mail settings
                            $has_valid_mail = isset($post_data['has_mail_setup']) && $post_data['has_mail_setup'];
                            $results[] = [
                                'ID' => $post_data['ID'],
                                'success' => true,
                                'result' => $has_valid_mail,
                                'post_title' => $post_data['post_title'],
                                'mail_recipient' => $post_data['mail_recipient'] ?? '',
                                'mail_settings' => $post_data['mail_settings'] ?? [],
                                'mail_2_settings' => $post_data['mail_2_settings'] ?? []
                            ];
                        } elseif ($is_jet_footer_check) {
                            // For jet_footer checks (like privacy policy link)
                            // Use the actual HTML check result instead of has_any_pattern
                            $check_field = $item['value']['check_field'] ?? '';
                            $expected_value = $item['value']['expected_value'] ?? 'on';
                            
                            // Create meta_data for HTML check
                            $meta_data = [
                                'ID' => $post_data['ID'],
                                'post_title' => $post_data['post_title'],
                                'post_type' => $post_data['post_type']
                            ];
                            
                            // Perform HTML check
                            $has_pattern = $this->check_html_for_selector($meta_data, $check_field, $expected_value);
                            
                            $results[] = [
                                'success' => true,
                                'type' => 'post',
                                'name' => $post_data['post_title'],
                                'result' => $has_pattern,
                                'is_found' => $has_pattern,
                                'expected_found' => true,
                                'message' => $has_pattern ? 'Pattern found' : 'Pattern not found'
                            ];
                        } else {
                            // For other checks (like privacy policy in Contact Form 7), use existing logic
                            $has_pattern = isset($post_data['has_any_pattern']) ? $post_data['has_any_pattern'] : false;
                            $results[] = [
                                'ID' => $post_data['ID'],
                                'success' => true,
                                'result' => $has_pattern,
                                'post_title' => $post_data['post_title'],
                                'found_patterns' => $post_data['found_patterns'] ?? [],
                                'not_found_patterns' => $post_data['not_found_patterns'] ?? [],
                                'form_content' => $post_data['form_content'] ?? '',
                                'mail_settings' => $post_data['mail_settings'] ?? [],
                                'mail_2_settings' => $post_data['mail_2_settings'] ?? []
                            ];
                        }
                    }
                    
                    return [
                        'success' => true,
                        'total' => count($results),
                        'post_type' => $post_type,
                        'type' => 'post',
                        'results' => $results
                    ];
                }
                
                return [
                    'success' => true,
                    'total' => count($posts_data),
                    'type' => 'post',
                    'results' => $posts_data
                ];
            }
            
            // Convert name to slug (e.g., "Privacy Policy" -> "privacy-policy")
            $slug = sanitize_title($name);
            
            // Find post by slug
            $args = [
                'name' => $slug,
                'post_type' => $post_type === 'any' ? ['post', 'page'] : $post_type,
                'post_status' => 'any',
                'posts_per_page' => 1
            ];
            
            $posts = get_posts($args);
            
            if (empty($posts)) {
                return [
                    'success' => true,
                    'type' => 'post',
                    'name' => $name,
                    'result' => false,
                    'is_found' => false,
                    'expected_found' => true,
                    'message' => 'Post/Page không tồn tại'
                ];
            }
            
            $post = $posts[0];
            
            // Case 2: No value, return post info
            if ($value === null || $value === '') {
                return [
                    'success' => true,
                    'type' => 'post',
                    'name' => $name,
                    'result' => true,
                    'is_found' => true,
                    'expected_found' => true,
                    'message' => 'Post/Page được tìm thấy',
                    'data' => [
                        'ID' => $post->ID,
                        'post_title' => $post->post_title,
                        'post_name' => $post->post_name,
                        'post_type' => $post->post_type,
                        'post_status' => $post->post_status,
                        'post_content' => $post->post_content,
                        'post_date' => $post->post_date,
                        'post_url' => get_permalink($post->ID)
                    ]
                ];
            }
            
            // Case 3: Check content with value
            // Check for include_data/exclude_data FIRST (before simple array check)
            if (is_array($value) && (isset($value['include_data']) || isset($value['exclude_data']))) {
                // Object/Array with include_data and exclude_data
                $include_data = isset($value['include_data']) ? $value['include_data'] : [];
                $exclude_data = isset($value['exclude_data']) ? $value['exclude_data'] : [];
            } elseif (is_array($value)) {
                // Check if this is the new format with operator and html
                if (isset($value['operator']) && isset($value['html']) && $value['html'] === 'true') {
                    // Use the new HTML check logic
                    $check_field = isset($value['check_field']) ? $value['check_field'] : null;
                    $expected_value = isset($value['expected_value']) ? $value['expected_value'] : 'on';
                    $operator = strtolower($value['operator']);
                    
                    // Get post meta data
                    $meta_data = [
                        'ID' => $post->ID,
                        'post_title' => $post->post_title,
                        'post_name' => $post->post_name,
                        'post_type' => $post->post_type,
                        'post_status' => $post->post_status,
                        'post_content' => $post->post_content,
                        'post_date' => $post->post_date,
                        'post_url' => get_permalink($post->ID)
                    ];
                    
                    // Check HTML for selector
                    $found = $this->check_html_for_selector($meta_data, $check_field, $expected_value);
                    
                    // Check if this is a count request
                    $is_count_request = strpos($check_field, 'count_') === 0;
                    $count_value = 0;
                    
                    if ($is_count_request) {
                        // For count requests, get the actual count
                        $count_value = $this->check_html_for_selector($meta_data, $check_field, 'count');
                    }
                    
                    // Apply operator logic
                    $result = false;
                    switch ($operator) {
                        case 'equals':
                        case 'contains':
                            $result = $found;
                            break;
                        case 'not_equals':
                        case 'not_contains':
                            $result = !$found;
                            break;
                        default:
                            $result = $found;
                    }
                    
                    $response = [
                        'success' => true,
                        'type' => 'post',
                        'name' => $name,
                        'result' => $result,
                        'is_found' => $found,
                        'expected_found' => $expected_value === 'on',
                        'message' => $result ? 'Page ' . $post->post_title . ' có ' . $check_field . ' trong HTML' : 'Page ' . $post->post_title . ' không có ' . $check_field . ' trong HTML'
                    ];
                    
                    // Add count information if this is a count request
                    if ($is_count_request) {
                        $response['count'] = $count_value;
                        $response['message'] = 'Page ' . $post->post_title . ' có ' . $count_value . ' thẻ ' . substr($check_field, 6) . ' trong HTML';
                    }
                    
                    return $response;
                } else {
                // Simple array - auto convert to include_data
                $include_data = [
                    ['column' => 'post_content', 'value' => $value]
                ];
                $exclude_data = [];
                }
            } else {
                return [
                    'success' => true,
                    'type' => 'post',
                    'name' => $name,
                    'result' => false,
                    'is_found' => false,
                    'expected_found' => true,
                    'message' => 'Format value không hợp lệ. Cần array hoặc object với include_data/exclude_data'
                ];
            }
            
            // Check include_data
            $include_matched = [];
            $include_not_matched = [];
            $include_result = true;
            
            foreach ($include_data as $condition) {
                $column = $condition['column'];
                $values = $condition['value'];
                
                // Get column value from post
                $post_value = '';
                if (property_exists($post, $column)) {
                    $post_value = $post->$column;
                } elseif ($column === 'content') {
                    $post_value = $post->post_content;
                }
                
                $condition_matched = [];
                $condition_not_matched = [];
                
                foreach ($values as $search_value) {
                    if (stripos($post_value, $search_value) !== false) {
                        $condition_matched[] = $search_value;
                    } else {
                        $condition_not_matched[] = $search_value;
                    }
                }
                
                $include_matched[$column] = $condition_matched;
                $include_not_matched[$column] = $condition_not_matched;
                
                // If any value not matched, include fails
                if (!empty($condition_not_matched)) {
                    $include_result = false;
                }
            }
            
            // Check exclude_data
            $exclude_matched = [];
            $exclude_result = true;
            
            foreach ($exclude_data as $condition) {
                $column = $condition['column'];
                $values = $condition['value'];
                
                // Get column value from post
                $post_value = '';
                if (property_exists($post, $column)) {
                    $post_value = $post->$column;
                } elseif ($column === 'content') {
                    $post_value = $post->post_content;
                }
                
                $condition_matched = [];
                
                foreach ($values as $search_value) {
                    if (stripos($post_value, $search_value) !== false) {
                        $condition_matched[] = $search_value;
                    }
                }
                
                $exclude_matched[$column] = $condition_matched;
                
                // If any value matched, exclude fails
                if (!empty($condition_matched)) {
                    $exclude_result = false;
                }
            }
            
            $final_result = $include_result && $exclude_result;
            
            return [
                'success' => true,
                'type' => 'post',
                'name' => $name,
                'post_title' => $post->post_title,
                'result' => $final_result,
                'is_found' => $final_result,
                'expected_found' => true,
                'include_matched' => $include_matched,
                'include_not_matched' => $include_not_matched,
                'exclude_matched' => $exclude_matched,
                'message' => $final_result ? 'Post thỏa mãn tất cả điều kiện' : 'Post không thỏa mãn điều kiện'
            ];
            
        } catch (Exception $e) {
            error_log('MAC Info Manager - Post Error: ' . $e->getMessage());
            return [
                'success' => true,
                'type' => 'post',
                'name' => $name,
                'result' => false,
                'is_found' => false,
                'expected_found' => true,
                'message' => 'Lỗi khi xử lý post: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle USER type
     * - name = "any": GET all users
     * - name: GET user info (by username or email)
     */
    private function handle_user($name) {
        if (empty($name)) {
            return [
                'success' => false,
                'type' => 'user',
                'message' => 'Tham số "name" là bắt buộc cho type user'
            ];
        }

        try {
            // Case 1: Get all users
            if (strtolower($name) === 'any') {
                $users = get_users();
                $users_data = [];
                
                foreach ($users as $user) {
                    $users_data[] = [
                        'ID' => $user->ID,
                        'user_login' => $user->user_login,
                        'user_email' => $user->user_email,
                        'user_nicename' => $user->user_nicename,
                        'display_name' => $user->display_name,
                        'roles' => $user->roles
                    ];
                }
                
                return [
                    'success' => true,
                    'type' => 'user',
                    'name' => 'any',
                    'count' => count($users_data),
                    'data' => $users_data
                ];
            }
            
            // Case 2: Get specific user
            // Try by username first
            $user = get_user_by('login', $name);
            
            // Try by email if not found
            if (!$user) {
                $user = get_user_by('email', $name);
            }
            
            if (!$user) {
                return [
                    'success' => false,
                    'type' => 'user',
                    'name' => $name,
                    'message' => 'User không tồn tại'
                ];
            }
            
            return [
                'success' => true,
                'type' => 'user',
                'name' => $name,
                'data' => [
                    'ID' => $user->ID,
                    'user_login' => $user->user_login,
                    'user_email' => $user->user_email,
                    'user_nicename' => $user->user_nicename,
                    'display_name' => $user->display_name,
                    'roles' => $user->roles,
                    'user_registered' => $user->user_registered
                ]
            ];
            
        } catch (Exception $e) {
            error_log('MAC Info Manager - User Error: ' . $e->getMessage());
            return [
                'success' => false,
                'type' => 'user',
                'name' => $name,
                'message' => 'Lỗi khi lấy thông tin user: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle UPDOWN type - Check website status
     */
    private function handle_updown($item) {
        try {
            $name = isset($item['name']) ? sanitize_text_field($item['name']) : null;
            $value = isset($item['value']) ? $item['value'] : null;
            
            // Get site URL
            $site_url = get_site_url();
            
            // Initialize results
            $details = [
                'is_up' => false,
                'current_title' => '',
                'redirects' => false,
                'http_status' => 0,
                'response_time' => 0,
                'title_hijacked' => false,
                'suspicious_redirects' => false,
                'content_anomaly' => false
            ];
            
            // Fetch website
            $start_time = microtime(true);
            $response = wp_remote_get($site_url, [
                'timeout' => 30,
                'redirection' => 0, // Don't follow redirects automatically
                'user-agent' => 'MAC-Info-Manager/1.0'
            ]);
            $end_time = microtime(true);
            $details['response_time'] = round(($end_time - $start_time) * 1000, 2); // ms
            
            if (is_wp_error($response)) {
                $details['is_up'] = false;
                $details['error_message'] = $response->get_error_message();
                
                return [
                    'success' => true,
                    'type' => 'updown',
                    'name' => $name ?: 'website_updown',
                    'result' => false,
                    'message' => 'Website không thể truy cập: ' . $response->get_error_message(),
                    'details' => $details
                ];
            }
            
            // Get HTTP status
            $http_status = wp_remote_retrieve_response_code($response);
            $details['http_status'] = $http_status;
            $details['is_up'] = ($http_status === 200);
            
            // Get HTML content
            $html = wp_remote_retrieve_body($response);
            
            // Extract title
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
                $details['current_title'] = trim(strip_tags($matches[1]));
            }
            
            // Check for redirects
            $redirect_url = wp_remote_retrieve_header($response, 'location');
            if (!empty($redirect_url)) {
                $details['redirects'] = true;
                $details['redirect_to'] = $redirect_url;
                
                // Check if suspicious redirect (to external domain)
                $site_domain = parse_url($site_url, PHP_URL_HOST);
                $redirect_domain = parse_url($redirect_url, PHP_URL_HOST);
                if ($redirect_domain && $redirect_domain !== $site_domain) {
                    $details['suspicious_redirects'] = true;
                }
            }
            
            // Check for title hijacked (Japanese characters or suspicious keywords)
            $title = $details['current_title'];
            $japanese_patterns = [
                '/[\x{3040}-\x{309F}]/u', // Hiragana
                '/[\x{30A0}-\x{30FF}]/u', // Katakana  
                '/[\x{4E00}-\x{9FAF}]/u', // Kanji
            ];
            
            foreach ($japanese_patterns as $pattern) {
                if (preg_match($pattern, $title)) {
                    $details['title_hijacked'] = true;
                    break;
                }
            }
            
            // Check for suspicious keywords
            $suspicious_keywords = [
                '無料動画', 'エロ動画', 'アダルト動画', 'AV女優',
                'オンラインカジノ', 'スロット', 'ボーナス',
                '出会い系', 'メル友', '無料登録', '無料視聴'
            ];
            
            foreach ($suspicious_keywords as $keyword) {
                if (strpos($html, $keyword) !== false) {
                    $details['title_hijacked'] = true;
                    break;
                }
            }
            
            // Check for content anomaly
            // Suspicious iframes
            if (preg_match('/<iframe[^>]*src=["\'](?!https?:\/\/(www\.)?(youtube|vimeo|google)\.com)/i', $html)) {
                $details['content_anomaly'] = true;
            }
            
            // Suspicious scripts
            if (preg_match('/<script[^>]*src=["\'](?!https?:\/\/(www\.)?(google|facebook|twitter)\.com)/i', $html)) {
                $details['content_anomaly'] = true;
            }
            
            // Calculate overall result
            $result = $details['is_up'] && 
                     !$details['title_hijacked'] && 
                     !$details['suspicious_redirects'] && 
                     !$details['content_anomaly'];
            
            $message = 'Get information web successful';
            if (!$details['is_up']) {
                $message = 'Website đang down (HTTP ' . $http_status . ')';
            } elseif ($details['title_hijacked']) {
                $message = 'Website bị hack - Title có ký tự Nhật hoặc nội dung đáng ngờ';
            } elseif ($details['suspicious_redirects']) {
                $message = 'Website có redirect đáng ngờ';
            } elseif ($details['content_anomaly']) {
                $message = 'Website có nội dung bất thường';
            }
            
            return [
                'success' => true,
                'type' => 'updown',
                'name' => $name ?: 'website_updown',
                'result' => $result,
                'message' => $message,
                'details' => $details
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'type' => 'updown',
                'name' => $name,
                'message' => 'Lỗi khi kiểm tra website: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Xử lý API POST: /wp-json/v1/get-infor/web-infor
     */
    public function handle_get_web_info($request) {
        $auth_key = $request->get_param('auth_key');
        $fields = $request->get_param('fields');
        $shared_secret = get_option('mac_domain_valid_key', '');

        // Kiểm tra auth_key
        if (empty($shared_secret)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'CRM key chưa được đăng ký.'
            ], 403);
        }

        if ($auth_key !== $shared_secret) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Auth key không hợp lệ.'
            ], 403);
        }

        try {
            // Lấy dữ liệu từ options
            $web_info = get_option('web-info', []);
            
            if (empty($web_info)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Không tìm thấy thông tin web-info.'
                ], 404);
            }

            // Nếu có fields cụ thể, chỉ trả về những field đó
            if (!empty($fields)) {
                $requested_fields = explode(',', $fields);
                $filtered_info = [];
                
                foreach ($requested_fields as $field) {
                    $field = trim($field);
                    if (isset($web_info[$field])) {
                        $filtered_info[$field] = $web_info[$field];
                    }
                }
                
                $web_info = $filtered_info;
            }

            return new WP_REST_Response([
                'success' => true,
                'data' => $web_info
            ], 200);

        } catch (Exception $e) {
            error_log('MAC Info Manager: Error getting web-info - ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Lỗi khi lấy thông tin web-info: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xử lý API POST: /wp-json/v1/get-infor/smtp
     */
    public function handle_get_smtp_info($request) {
        $auth_key = $request->get_param('auth_key');
        $shared_secret = get_option('mac_domain_valid_key', '');

        // Kiểm tra auth_key
        if (empty($shared_secret)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'CRM key chưa được đăng ký.'
            ], 403);
        }

        if ($auth_key !== $shared_secret) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Auth key không hợp lệ.'
            ], 403);
        }

        try {
            // Helper: che dấu secret, chỉ hiển thị vài ký tự đầu/cuối
            $mask = function($value) {
                $str = (string) $value;
                if ($str === '') return '';
                $len = strlen($str);
                if ($len <= 6) return '****';
                return substr($str, 0, 2) . '****' . substr($str, -2);
            };
            // Lấy dữ liệu SMTP từ WP Mail SMTP
            $smtp_settings = get_option('wp_mail_smtp', []);
            
            if (empty($smtp_settings)) {
                return new WP_REST_Response([
                    'success' => true,
                    'data' => [
                        'has_smtp' => false,
                        'message' => 'Không có cấu hình SMTP'
                    ]
                ], 200);
            }

            // Xử lý dữ liệu SMTP - trả về thông tin cơ bản cho CRM
            $mailer = $smtp_settings['mail']['mailer'] ?? '';
            $smtp_info = [
                'has_smtp' => true,
                'mailer' => $mailer,
                'from_email' => $smtp_settings['mail']['from_email'] ?? '',
                'from_name' => $smtp_settings['mail']['from_name'] ?? '',
            ];

            // Hiển thị thông tin cấu hình theo mailer đang sử dụng
            switch ($mailer) {
                case 'sendgrid':
                    $smtp_info['sendgrid'] = [
                        'api_key' => $mask($smtp_settings['sendgrid']['api_key'] ?? ''),
                        'domain' => $smtp_settings['sendgrid']['domain'] ?? '',
                    ];
                    break;
                    
                case 'sendlayer':
                    $smtp_info['sendlayer'] = [
                        'api_key' => $mask($smtp_settings['sendlayer']['api_key'] ?? ''),
                    ];
                    break;
                    
                case 'smtp':
                    $smtp_info['smtp'] = [
                        'host' => $smtp_settings['smtp']['host'] ?? '',
                        'port' => $smtp_settings['smtp']['port'] ?? '',
                        'encryption' => $smtp_settings['smtp']['encryption'] ?? '',
                        'auth' => $smtp_settings['smtp']['auth'] ?? false,
                        'autotls' => $smtp_settings['smtp']['autotls'] ?? '',
                        'user' => $mask($smtp_settings['smtp']['user'] ?? ''),
                        'pass' => $mask($smtp_settings['smtp']['pass'] ?? ''),
                    ];
                    break;
                    
                case 'mailgun':
                    $smtp_info['mailgun'] = [
                        'api_key' => $mask($smtp_settings['mailgun']['api_key'] ?? ''),
                        'domain' => $smtp_settings['mailgun']['domain'] ?? '',
                        'region' => $smtp_settings['mailgun']['region'] ?? '',
                    ];
                    break;
                    
                case 'gmail':
                    $smtp_info['gmail'] = [
                        'client_id' => $smtp_settings['gmail']['client_id'] ?? '',
                        'client_secret' => $mask($smtp_settings['gmail']['client_secret'] ?? ''),
                    ];
                    break;
                    
                default:
                    // Không hiển thị thông tin cấu hình cho mailer khác
                    break;
            }

            return new WP_REST_Response([
                'success' => true,
                'data' => $smtp_info
            ], 200);

        } catch (Exception $e) {
            error_log('MAC Info Manager: Error getting SMTP info - ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Lỗi khi lấy thông tin SMTP: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Check if Elementor data contains mac-menu QR code settings
     */
    private function check_mac_menu_qr_settings($elementor_data) {
        try {
            if (empty($elementor_data)) {
                return false;
            }
            
            // Decode JSON if it's a string
            $data = $elementor_data;
            if (is_string($elementor_data)) {
                $data = json_decode($elementor_data, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return false;
                }
            }
            
            // Recursively search through Elementor data
            $result = $this->search_elementor_for_mac_menu_qr($data);
            
            // Also check if QR code is globally enabled
            $qr_code_enabled = get_option('mac_qr_code', '0');
            if ($qr_code_enabled === '1' || $qr_code_enabled === 1) {
                return true; // QR code is globally enabled
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log('MAC Info Manager - QR Settings Check Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Recursively search Elementor data for mac-menu QR settings
     */
    private function search_elementor_for_mac_menu_qr($data) {
        if (!is_array($data)) {
            return false;
        }
        
        foreach ($data as $key => $value) {
            // Check if this is a widget with mac-menu settings
            // Support both 'mac-menu' and 'module_mac_menu' widget types
            if ($key === 'widgetType' && in_array($value, ['mac-menu', 'module_mac_menu'])) {
                return true; // Found mac-menu widget
            }
            
            // Check settings for QR code related fields
            if ($key === 'settings' && is_array($value)) {
                // Check for mac-menu QR code settings
                if (isset($value['mac_qr_code']) && $value['mac_qr_code'] === 'on') {
                    return true; // QR code is enabled
                }
                if (isset($value['mac_qr_code_title']) && !empty($value['mac_qr_code_title'])) {
                    return true; // QR code title is set
                }
                
                // Check other QR code related fields (if any)
                // Note: Based on mac-menu.php, only mac_qr_code and mac_qr_code_title are the main QR fields
            }
            
            // Recursively search nested arrays
            if (is_array($value)) {
                if ($this->search_elementor_for_mac_menu_qr($value)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Check if content contains privacy policy link
     */
    private function check_privacy_policy_link($content) {
        try {
            if (empty($content)) {
                return false;
            }
            
            // Decode JSON if it's Elementor data
            $decoded_content = $content;
            if (is_string($content) && (strpos($content, '[') === 0 || strpos($content, '{') === 0)) {
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $decoded_content = json_encode($decoded);
                }
            }
            
            // Common privacy policy link patterns
            $privacy_patterns = [
                // HTML link patterns
                'href="/privacy-policy"',
                'href=\'/privacy-policy\'',
                'href="/privacy-policy/"',
                'href=\'/privacy-policy/\'',
                'href="privacy-policy"',
                'href=\'privacy-policy\'',
                'href="privacy_policy"',
                'href=\'privacy_policy\'',
                'href="privacy policy"',
                'href=\'privacy policy\'',
                
                // Text patterns
                '/privacy-policy',
                'privacy-policy',
                'privacy_policy',
                'privacy policy',
                'privacy-policy/',
                'privacy-policy.html',
                'privacy-policy.php',
                
                // Shortcode patterns
                '[privacy_policy]',
                '[privacy-policy]',
                '[privacy policy]'
            ];
            
            foreach ($privacy_patterns as $pattern) {
                if (stripos($decoded_content, $pattern) !== false) {
                    return true;
                }
            }
            
            // Additional check for HTML anchor tags with privacy in href
            if (preg_match('/<a[^>]*href=["\'][^"\']*privacy[^"\']*["\'][^>]*>/i', $decoded_content)) {
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log('MAC Info Manager - Privacy Link Check Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if content contains specific pattern
     */
    private function check_content_for_pattern($meta_data, $pattern) {
        try {
            // Special handling for mac_qr_code pattern
            if ($pattern === 'mac_qr_code') {
                return $this->check_mac_qr_code_enabled($meta_data);
            }
            
            // Get all possible content fields to search
            $content_fields = [
                'elementor_data',
                'form_content',
                'conditions',
                'template_type'
            ];
            
            foreach ($content_fields as $field) {
                if (isset($meta_data[$field]) && !empty($meta_data[$field])) {
                    $content = $meta_data[$field];
                    
                    // Decode JSON if it's Elementor data
                    if (is_string($content) && (strpos($content, '[') === 0 || strpos($content, '{') === 0)) {
                        $decoded = json_decode($content, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $content = json_encode($decoded);
                        }
                    }
                    
                    // Check for pattern in content
                    if (stripos($content, $pattern) !== false) {
                        return true;
                    }
                    
                    // Check for HTML link patterns if pattern looks like a URL/path
                    if (preg_match('/^\/?[a-zA-Z0-9\-_\/]+$/', $pattern)) {
                        $html_patterns = [
                            'href="' . $pattern . '"',
                            'href=\'' . $pattern . '\'',
                            'href="' . $pattern . '/"',
                            'href=\'' . $pattern . '/\'',
                            'href="' . ltrim($pattern, '/') . '"',
                            'href=\'' . ltrim($pattern, '/') . '\''
                        ];
                        
                        foreach ($html_patterns as $html_pattern) {
                            if (stripos($content, $html_pattern) !== false) {
                                return true;
                            }
                        }
                        
                        // Regex check for any anchor tag with the pattern
                        $regex_pattern = '/<a[^>]*href=["\'][^"\']*' . preg_quote($pattern, '/') . '[^"\']*["\'][^>]*>/i';
                        if (preg_match($regex_pattern, $content)) {
                            return true;
                        }
                    }
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log('MAC Info Manager - Pattern Check Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if mac_qr_code is enabled (equals "on")
     */
    private function check_mac_qr_code_enabled($meta_data) {
        try {
            // Check in elementor_data for mac_qr_code: "on"
            if (isset($meta_data['elementor_data']) && !empty($meta_data['elementor_data'])) {
                $elementor_data = $meta_data['elementor_data'];
                
                // Decode JSON if it's a string
                if (is_string($elementor_data)) {
                    $decoded = json_decode($elementor_data, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $elementor_data = $decoded;
                    }
                }
                
                // Search for mac_qr_code: "on" in elementor_data
                if (is_array($elementor_data)) {
                    return $this->search_elementor_for_mac_qr_enabled($elementor_data);
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log('MAC Info Manager - Mac QR Check Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if mac_qr_code has specific value
     */
    private function check_mac_qr_code_value($meta_data, $expected_value = 'on') {
        try {
            // Method 1: Check Elementor data
            if (isset($meta_data['elementor_data']) && !empty($meta_data['elementor_data'])) {
                $elementor_data = $meta_data['elementor_data'];
                
                // Decode JSON if it's a string
                if (is_string($elementor_data)) {
                    $decoded = json_decode($elementor_data, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $elementor_data = $decoded;
                    }
                }
                
                // Search for mac_qr_code with specific value in elementor_data
                if (is_array($elementor_data)) {
                    $found = $this->search_elementor_for_mac_qr_value($elementor_data, $expected_value);
                    if ($found) {
                        return true;
                    }
                }
            }
            
            // Method 2: Check global option
            $global_qr_code = get_option('mac_qr_code', '0');
            if ($global_qr_code === $expected_value || $global_qr_code == 1) {
                return true;
            }
            
            // Method 3: Check rendered HTML content
            if (isset($meta_data['post_content']) && !empty($meta_data['post_content'])) {
                $found = $this->check_html_for_qr_code($meta_data['post_content'], $expected_value);
                if ($found) {
                    return true;
                }
            }
            
            // Method 4: Check page HTML from URL (NEW METHOD)
            if (isset($meta_data['ID'])) {
                $found = $this->check_page_html_for_qr($meta_data['ID'], $expected_value);
                if ($found) {
                    return true;
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log('MAC Info Manager - Mac QR Value Check Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check page HTML for QR code by making HTTP request
     */
    private function check_page_html_for_qr($page_id, $expected_value = 'on') {
        try {
            // Get page URL
            $page_url = get_permalink($page_id);
            if (!$page_url) {
                return false;
            }
        
            
            // Make HTTP request to get HTML
            $response = wp_remote_get($page_url, [
                'timeout' => 30,
                'user-agent' => 'MAC-Info-Manager/1.0'
            ]);
            
            if (is_wp_error($response)) {
                error_log('MAC Info Manager - HTTP request error: ' . $response->get_error_message());
                return false;
            }
            
            $html = wp_remote_retrieve_body($response);
            if (empty($html)) {
                return false;
            }
            
            // Check for QR code in HTML
            $found = $this->check_html_for_qr_code($html, $expected_value);
            
            return $found;
            
        } catch (Exception $e) {
            error_log('MAC Info Manager - Page HTML Check Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check HTML content for QR code indicators
     */
    private function check_html_for_qr_code($html_content, $expected_value) {
        try {
            if (empty($html_content)) {
                return false;
            }
            
            // QR code indicators in HTML - focusing on your specific ID
            $qr_indicators = [
                'id="mac-qr"',           // Your specific ID
                'id="mac-qr-code"',
                'id="qr-code"',
                'class="mac-qr"',
                'class="mac-qr-code"',
                'class="qr-code"',
                'data-qr="true"',
                'data-mac-qr="on"',
                'mac-menu-qr',
                'module_mac_menu'
            ];
            
            // Check if any QR indicators exist
            foreach ($qr_indicators as $indicator) {
                if (stripos($html_content, $indicator) !== false) {
                    return $expected_value === 'on';
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log('MAC Info Manager - HTML QR Check Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check HTML content for flexible selector
     * Supports:
     * - #name = ID selector (id="name")
     * - .name = Class selector (class="name")
     * - name = HTML tag (<name>)
     */
    private function check_html_for_selector($meta_data, $selector, $expected_value = 'on') {
        try {
            if (!isset($meta_data['ID'])) {
                return false;
            }
            
            $page_id = $meta_data['ID'];
            $html = '';
            
            // Check if this is a jet_footer post - search directly in JSON data
            $post = get_post($page_id);
            if ($post && ($post->post_type === 'jet-theme-core' || $post->post_type === 'jet-theme-template')) {
                // Check if this is a jet_footer template
                $template_type = get_post_meta($page_id, '_elementor_template_type', true);
                if ($template_type === 'jet_footer') {
                    // For jet_footer, search directly in _elementor_data JSON
                    $elementor_data = get_post_meta($page_id, '_elementor_data', true);
                    if (!empty($elementor_data)) {
                        // Use raw JSON data for searching
                        $html = $elementor_data;
                    }
                } else {
                    // For other jet templates, fetch from URL
                    $page_url = get_permalink($page_id);
                    if ($page_url) {
                        $response = wp_remote_get($page_url, [
                            'timeout' => 30,
                            'user-agent' => 'MAC-Info-Manager/1.0'
                        ]);
                        
                        if (!is_wp_error($response)) {
                            $html = wp_remote_retrieve_body($response);
                        }
                    }
                }
            } else {
                // For other posts, fetch from URL
            $page_url = get_permalink($page_id);
            
            if (!$page_url) {
                return false;
            }
            
            $response = wp_remote_get($page_url, [
                'timeout' => 30,
                'user-agent' => 'MAC-Info-Manager/1.0'
            ]);
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $html = wp_remote_retrieve_body($response);
            }
            
            if (empty($html)) {
                return false;
            }
            
            // Determine selector type and create search pattern
            $search_patterns = [];
            $selector_type = '';
            
            if (strpos($selector, '#') === 0) {
                // ID selector: #name -> id="name"
                $id_name = substr($selector, 1);
                $search_patterns = [
                    'id="' . $id_name . '"',
                    "id='" . $id_name . "'",
                    'id=' . $id_name . ' ',
                    'id=' . $id_name . '>'
                ];
                $selector_type = 'ID';
            } elseif (strpos($selector, '.') === 0) {
                // Class selector: .name -> class="name"
                $class_name = substr($selector, 1);
                $search_patterns = [
                    'class="' . $class_name . '"',
                    "class='" . $class_name . "'",
                    'class="' . $class_name . ' ',
                    'class=" ' . $class_name . '"',
                    'class="' . $class_name . '">',
                    'class="' . $class_name . '">'
                ];
                $selector_type = 'Class';
            } elseif (strpos($selector, 'href=') === 0) {
                // Href selector: href="/privacy-policy" -> href="/privacy-policy"
                $href_value = substr($selector, 5); // Remove "href="
                
                // Create comprehensive search patterns including JSON escaped versions
                $search_patterns = [
                    // Exact matches with quotes
                    'href="' . $href_value . '"',  // href="/privacy-policy"
                    "href='" . $href_value . "'",  // href='/privacy-policy'
                    
                    // JSON escaped versions (common in Elementor data)
                    'href=\"' . str_replace('/', '\/', $href_value) . '\"',  // href=\"\/privacy-policy\"
                    'href=\\\"' . str_replace('/', '\/', $href_value) . '\\\"',  // href=\"\/privacy-policy\"
                    
                    // Matches without quotes (common in Elementor)
                    'href=' . $href_value . ' ',   // href=/privacy-policy 
                    'href=' . $href_value . '>',   // href=/privacy-policy>
                    'href=' . $href_value . '"',   // href=/privacy-policy"
                    'href=' . $href_value . "'",   // href=/privacy-policy'
                    
                    // Additional variations
                    'href=' . $href_value . '&',   // href=/privacy-policy&
                    'href=' . $href_value . ';',   // href=/privacy-policy;
                    'href=' . $href_value . ')',   // href=/privacy-policy)
                    'href=' . $href_value . ',',   // href=/privacy-policy,
                    
                    // Case variations
                    'HREF="' . $href_value . '"',  // HREF="/privacy-policy"
                    'HREF=' . $href_value . ' ',   // HREF=/privacy-policy 
                ];
                $selector_type = 'Href';
            } elseif (strpos($selector, '/') === 0) {
                // Path selector: "/privacy-policy" -> search for this path in JSON
                $path_value = $selector; // Keep the leading slash
                
                // Simple search patterns for JSON data
                $search_patterns = [
                    $path_value,  // /privacy-policy
                    str_replace('/', '\/', $path_value),  // \/privacy-policy (JSON escaped)
                    'href=\"' . str_replace('/', '\/', $path_value) . '\"',  // href=\"\/privacy-policy\"
                    'href="' . $path_value . '"',  // href="/privacy-policy"
                ];
                $selector_type = 'Path';
            } else {
                // HTML tag: name -> <name>
                $tag_name = $selector;
                $search_patterns = [
                    '<' . $tag_name . '>',
                    '<' . $tag_name . ' ',
                    '<' . $tag_name . '/>',
                    '<' . $tag_name . '>',
                    '</' . $tag_name . '>'
                ];
                $selector_type = 'Tag';
            }
            
            // Check if this is a count request (e.g., count h1 tags)
            if (strpos($selector, 'count_') === 0) {
                $tag_name = substr($selector, 6); // Remove "count_" prefix
                $count = $this->count_html_tags($html, $tag_name);
                
                // Compare count with expected value
                if (is_numeric($expected_value)) {
                    return $count == $expected_value;
                } else {
                    // Return the count for further processing
                    return $count;
                }
            }
            
            // Search for any of the patterns
            $found = false;
            $matched_pattern = '';
            
            foreach ($search_patterns as $pattern) {
                if (stripos($html, $pattern) !== false) {
                    $found = true;
                    $matched_pattern = $pattern;
                    break;
                }
            }
            
            
            
            // Return result based on expected_value
            if ($found && $expected_value === 'on') {
                return true;
            } elseif (!$found && $expected_value === 'off') {
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Convert Elementor data to HTML for checking
     */
    private function convert_elementor_data_to_html($elementor_data) {
        try {
            // If elementor_data is already HTML string, return it
            if (is_string($elementor_data)) {
                // Check if it's HTML (contains < and >) - but not JSON
                if (strpos($elementor_data, '<') !== false && strpos($elementor_data, '>') !== false && strpos($elementor_data, '[') !== 0) {
                    return $elementor_data;
                }
                
                // Try to decode as JSON
                $decoded = json_decode($elementor_data, true);
                if ($decoded && is_array($decoded)) {
                    $elementor_data = $decoded;
                } else {
                    // If it's not JSON, return as is (might be plain text)
                    return $elementor_data;
                }
            }
            
            // If it's an array, convert to HTML
            if (is_array($elementor_data)) {
                $html = '';
                foreach ($elementor_data as $element) {
                    $element_html = $this->elementor_element_to_html($element);
                    if (!empty($element_html)) {
                        $html .= $element_html;
                    }
                }
                return $html;
            }
            
            // If it's not string or array, return empty
            return '';
        } catch (Exception $e) {
            return '';
        }
    }
    
    /**
     * Convert single Elementor element to HTML
     */
    private function elementor_element_to_html($element) {
        if (!is_array($element)) {
            return '';
        }
        
        $html = '';
        
        // Get element type and settings
        $element_type = $element['elType'] ?? '';
        $widget_type = $element['widgetType'] ?? '';
        $settings = $element['settings'] ?? [];
        
        // Handle different element types
        if ($element_type === 'widget') {
            // Handle specific widgets
            if ($widget_type === 'html') {
                $html_content = $settings['html'] ?? '';
                $html .= $html_content;
            } elseif ($widget_type === 'text-editor') {
                $editor_content = $settings['editor'] ?? '';
                // Process text-editor content to extract links
                $processed_content = $this->process_text_editor_content($editor_content);
                $html .= $processed_content;
            } elseif ($widget_type === 'image') {
                // Handle image widget - might have links
                $image_html = $this->process_image_widget($settings);
                $html .= $image_html;
            } elseif ($widget_type === 'heading') {
                $title = $settings['title'] ?? '';
                $size = $settings['size'] ?? 'h2';
                $html .= "<{$size}>{$title}</{$size}>";
            } elseif ($widget_type === 'button') {
                $text = $settings['text'] ?? '';
                $link = $settings['link']['url'] ?? '#';
                $html .= "<a href=\"{$link}\">{$text}</a>";
            } elseif ($widget_type === 'text-path') {
                // Handle text-path widget (often used for links)
                $text = $settings['text'] ?? '';
                $link = $settings['link']['url'] ?? '';
                if ($link) {
                    $html .= "<a href=\"{$link}\">{$text}</a>";
                } else {
                    $html .= $text;
                }
            } elseif ($widget_type === 'icon-list') {
                // Handle icon-list widget
                $icon_html = $this->process_icon_list_widget($settings);
                $html .= $icon_html;
            } elseif ($widget_type === 'social-icons') {
                // Handle social-icons widget - might contain privacy policy links
                $social_html = $this->process_social_icons_widget($settings);
                $html .= $social_html;
            } elseif ($widget_type === 'jet-headline') {
                // Handle jet-headline widget - might contain links
                $headline_html = $this->process_jet_headline_widget($settings);
                $html .= $headline_html;
            }
        } elseif ($element_type === 'section' || $element_type === 'column' || $element_type === 'container') {
            // Handle sections, columns, and containers
            if (isset($element['elements']) && is_array($element['elements'])) {
                foreach ($element['elements'] as $child_element) {
                    $html .= $this->elementor_element_to_html($child_element);
                }
            }
        }
        
        return $html;
    }
    
    /**
     * Process text-editor content to extract links and other HTML
     */
    private function process_text_editor_content($content) {
        if (empty($content)) {
            return '';
        }
        
        // If content already contains HTML tags, return as is
        if (strpos($content, '<') !== false && strpos($content, '>') !== false) {
            return $content;
        }
        
        // If content is plain text, wrap in paragraph
        return '<p>' . $content . '</p>';
    }
    
    /**
     * Process icon-list widget to extract links
     */
    private function process_icon_list_widget($settings) {
        $html = '';
        
        if (isset($settings['icon_list']) && is_array($settings['icon_list'])) {
            $html .= '<ul>';
            foreach ($settings['icon_list'] as $item) {
                $text = $item['text'] ?? '';
                $link = $item['link']['url'] ?? '';
                
                if ($link) {
                    $html .= '<li><a href="' . $link . '">' . $text . '</a></li>';
                } else {
                    $html .= '<li>' . $text . '</li>';
                }
            }
            $html .= '</ul>';
        }
        
        return $html;
    }
    
    /**
     * Process image widget to extract links
     */
    private function process_image_widget($settings) {
        $html = '';
        
        $image_url = $settings['image']['url'] ?? '';
        $link = $settings['link']['url'] ?? '';
        $alt = $settings['image']['alt'] ?? '';
        
        if ($image_url) {
            if ($link) {
                $html .= '<a href="' . $link . '"><img src="' . $image_url . '" alt="' . $alt . '"></a>';
            } else {
                $html .= '<img src="' . $image_url . '" alt="' . $alt . '">';
            }
        }
        
        return $html;
    }
    
    /**
     * Process social-icons widget to extract links
     */
    private function process_social_icons_widget($settings) {
        $html = '';
        
        // Social icons might contain privacy policy links
        if (isset($settings['social_icon_list']) && is_array($settings['social_icon_list'])) {
            $html .= '<ul>';
            foreach ($settings['social_icon_list'] as $item) {
                $text = $item['text'] ?? '';
                $link = $item['link']['url'] ?? '';
                
                if ($link) {
                    $html .= '<li><a href="' . $link . '">' . $text . '</a></li>';
                } else {
                    $html .= '<li>' . $text . '</li>';
                }
            }
            $html .= '</ul>';
        }
        
        return $html;
    }
    
    /**
     * Process jet-headline widget to extract links
     */
    private function process_jet_headline_widget($settings) {
        $html = '';
        
        $text = $settings['headline_text'] ?? '';
        $link = $settings['headline_link']['url'] ?? '';
        
        if ($text) {
            if ($link) {
                $html .= '<a href="' . $link . '">' . $text . '</a>';
            } else {
                $html .= $text;
            }
        }
        
        return $html;
    }
    
    /**
     * Count HTML tags in content
     */
    private function count_html_tags($html, $tag_name) {
        try {
            // Use regex to count opening tags
            $pattern = '/<' . preg_quote($tag_name, '/') . '(?:\s[^>]*)?>/i';
            preg_match_all($pattern, $html, $matches);
            return count($matches[0]);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Check if mac-menu widget is properly configured for QR code
     */
    private function check_mac_menu_widget_config($meta_data) {
        try {
            // Method 1: Check if mac-menu widget exists in Elementor data
            if (isset($meta_data['elementor_data']) && !empty($meta_data['elementor_data'])) {
                $elementor_data = $meta_data['elementor_data'];
                
                if (is_string($elementor_data)) {
                    $decoded = json_decode($elementor_data, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $elementor_data = $decoded;
                    }
                }
                
                if (is_array($elementor_data)) {
                    return $this->has_mac_menu_widget($elementor_data);
                }
            }
            
            // Method 2: Check if mac-menu plugin is active
            if (is_plugin_active('mac-menu/mac-menu.php')) {
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log('MAC Info Manager - Mac Menu Widget Config Check Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if Elementor data contains mac-menu widget
     */
    private function has_mac_menu_widget($data) {
        if (!is_array($data)) {
            return false;
        }
        
        foreach ($data as $key => $value) {
            // Check if this is a mac-menu widget
            if ($key === 'widgetType' && in_array($value, ['mac-menu', 'module_mac_menu'])) {
                return true;
            }
            
            // Recursively search nested arrays
            if (is_array($value)) {
                if ($this->has_mac_menu_widget($value)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if a specific field has expected value
     */
    private function check_field_value($meta_data, $field_name, $expected_value) {
        try {
            // Special handling for specific fields
            switch ($field_name) {
                case 'mac_qr_code':
                    // Multi-method approach for QR code detection
                    $qr_found = $this->check_mac_qr_code_value($meta_data, $expected_value);
                    
                    // If not found in data, check if mac-menu widget exists
                    if (!$qr_found) {
                        $widget_exists = $this->check_mac_menu_widget_config($meta_data);
                        if ($widget_exists) {
                            // Widget exists but QR not configured, return false for 'on'
                            return $expected_value !== 'on';
                        }
                    }
                    
                    return $qr_found;
                    
                case 'mac_qr_code_title':
                    return $this->check_mac_qr_code_title($meta_data, $expected_value);
                    
                case 'has_privacy_link':
                    return $this->check_privacy_policy_link($meta_data['elementor_data'] ?? '');
                    
                case 'has_elementor_data':
                    return !empty($meta_data['elementor_data']);
                    
                case 'has_conditions':
                    return !empty($meta_data['conditions']);
                    
                case 'template_type':
                    return isset($meta_data['template_type']) && $meta_data['template_type'] === $expected_value;
                    
                case 'has_mac_menu_widget':
                    return $this->check_mac_menu_widget_config($meta_data);
                    
                default:
                    // Generic field check
                    if (isset($meta_data[$field_name])) {
                        return $meta_data[$field_name] === $expected_value;
                    }
                    return false;
            }
        } catch (Exception $e) {
            error_log('MAC Info Manager - Field Value Check Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if mac_qr_code_title has specific value
     */
    private function check_mac_qr_code_title($meta_data, $expected_value) {
        try {
            // Check in elementor_data for mac_qr_code_title with specific value
            if (isset($meta_data['elementor_data']) && !empty($meta_data['elementor_data'])) {
                $elementor_data = $meta_data['elementor_data'];
                
                // Decode JSON if it's a string
                if (is_string($elementor_data)) {
                    $decoded = json_decode($elementor_data, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $elementor_data = $decoded;
                    }
                }
                
                // Search for mac_qr_code_title with specific value in elementor_data
                if (is_array($elementor_data)) {
                    return $this->search_elementor_for_field_value($elementor_data, 'mac_qr_code_title', $expected_value);
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log('MAC Info Manager - Mac QR Title Check Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Recursively search Elementor data for mac_qr_code: "on"
     */
    private function search_elementor_for_mac_qr_enabled($data) {
        if (!is_array($data)) {
            return false;
        }
        
        foreach ($data as $key => $value) {
            // Check if this is a widget with mac-menu settings
            if ($key === 'widgetType' && $value === 'mac-menu') {
                // This is a mac-menu widget, check if it has QR enabled
                return true; // Found mac-menu widget
            }
            
            // Check settings for mac_qr_code: "on"
            if ($key === 'settings' && is_array($value)) {
                if (isset($value['mac_qr_code']) && $value['mac_qr_code'] === 'on') {
                    return true; // QR code is enabled
                }
            }
            
            // Recursively search nested arrays
            if (is_array($value)) {
                if ($this->search_elementor_for_mac_qr_enabled($value)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Recursively search Elementor data for mac_qr_code with specific value
     */
    private function search_elementor_for_mac_qr_value($data, $expected_value) {
        if (!is_array($data)) {
            return false;
        }
        
        foreach ($data as $key => $value) {
            // Check if this is a widget with mac-menu settings
            if ($key === 'widgetType' && $value === 'mac-menu') {
                // This is a mac-menu widget, check if it has QR with specific value
                return true; // Found mac-menu widget
            }
            
            // Check settings for mac_qr_code with specific value
            if ($key === 'settings' && is_array($value)) {
                if (isset($value['mac_qr_code']) && $value['mac_qr_code'] === $expected_value) {
                    return true; // QR code has the expected value
                }
            }
            
            // Recursively search nested arrays
            if (is_array($value)) {
                if ($this->search_elementor_for_mac_qr_value($value, $expected_value)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Recursively search Elementor data for any field with specific value
     */
    private function search_elementor_for_field_value($data, $field_name, $expected_value) {
        if (!is_array($data)) {
            return false;
        }
        
        foreach ($data as $key => $value) {
            // Check if this is a widget with the field we're looking for
            // Support both 'mac-menu' and 'module_mac_menu' widget types
            if ($key === 'widgetType' && in_array($value, ['mac-menu', 'module_mac_menu'])) {
                // This is a mac-menu widget, check if it has the field with expected value
                if (isset($data['settings']) && is_array($data['settings'])) {
                    if (isset($data['settings'][$field_name]) && $data['settings'][$field_name] === $expected_value) {
                        return true; // Field has the expected value
                    }
                }
            }
            
            // Check settings for the field with specific value
            if ($key === 'settings' && is_array($value)) {
                if (isset($value[$field_name]) && $value[$field_name] === $expected_value) {
                    return true; // Field has the expected value
                }
            }
            
            // Recursively search nested arrays
            if (is_array($value)) {
                if ($this->search_elementor_for_field_value($value, $field_name, $expected_value)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Test endpoint
     */
    public function test_endpoint($request) {
        return new WP_REST_Response([
            'success' => true,
            'message' => 'MAC Info Manager API is working!',
            'timestamp' => current_time('c')
        ], 200);
    }
    
    /**
     * Helper function to get actual page name from value parameter
     */
    private function get_actual_page($value) {
        $actual_page = 'home';
        if (isset($value['page']) && !empty($value['page'])) {
            $page_slug = sanitize_text_field($value['page']);
            if (!empty($page_slug) && $page_slug !== '/') {
                $actual_page = $page_slug;
            }
        }
        return $actual_page;
    }
    
    /**
     * Get cached HTML or fetch fresh if not cached
     * Giảm redundant HTTP requests khi check nhiều items trên cùng 1 page
     */
    private function get_cached_html($site_url) {
        // Check if HTML already cached
        if (isset($this->html_cache[$site_url])) {
            return $this->html_cache[$site_url];
        }
        
        // Fetch fresh HTML

        $response = wp_remote_get($site_url, [
            'timeout' => 30,
            'user-agent' => 'MAC-Info-Manager/1.0'
        ]);
        
        if (is_wp_error($response)) {
            error_log('MAC Info Manager: Error fetching HTML - ' . $response->get_error_message());
            return false;
        }
        
        $html = wp_remote_retrieve_body($response);
        
        if (empty($html)) {
            return false;
        }
        
        // Cache the HTML
        $this->html_cache[$site_url] = $html;
        
        return $html;
    }
    
    /**
     * Handle batch sitecheck requests
     * Processes multiple check types in a single request
     */
    private function handle_batch_sitecheck($value, $item) {
        try {
            // Extract check types and page from value
            $check_types = [];
            $page = 'home';
            
            if (is_array($value) && isset($value['checks']) && is_array($value['checks'])) {
                // Format: {"value": {"checks": ["logo_links", "book_now_button"], "page": "home"}}
                $check_types = $value['checks'];
                if (isset($value['page']) && !empty($value['page'])) {
                    $page = sanitize_text_field($value['page']);
                }
            } elseif (is_array($value) && isset($value[0]) && is_string($value[0])) {
                // Format: {"value": ["logo_links", "book_now_button"]}
                $check_types = $value;
                if (isset($item['page']) && !empty($item['page'])) {
                    $page = sanitize_text_field($item['page']);
                }
            }
            
            // Get site URL
            $site_url = get_site_url();
            if ($page !== 'home') {
                $site_url = rtrim($site_url, '/') . '/' . $page;
            } else {
                $site_url = rtrim($site_url, '/') . '/home';
            }
            
            // Fetch website content (with caching)
            $html = $this->get_cached_html($site_url);
            
            if ($html === false) {
                return [
                    'success' => false,
                    'type' => 'sitecheck',
                    'message' => 'Không thể truy cập website hoặc website trả về nội dung rỗng'
                ];
            }
            
            $results = [];
            $counter = 1;
            
            // Process each check type
            foreach ($check_types as $check_type) {
                $check_type = sanitize_text_field($check_type);
                $check_result = $this->perform_single_sitecheck($check_type, $html, $site_url, $page);
                
                // Add name and counter
                $check_result['name'] = "check_{$counter}_{$check_type}_{$page}";
                $results[] = $check_result;
                $counter++;
            }
            
            // Return flat structure like individual requests
            return $results;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'type' => 'sitecheck',
                'message' => 'Lỗi xử lý batch sitecheck: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Perform a single sitecheck based on check type
     */
    private function perform_single_sitecheck($check_type, $html, $site_url, $page) {
        $value = ['check_type' => $check_type, 'page' => $page];
        
        // Get raw results from existing check methods
        $raw_results = null;
        
        if ($check_type === 'logo_links') {
            $raw_results = $this->perform_logo_links_check($html, $site_url, $value);
        } elseif ($check_type === 'book_now_button') {
            $raw_results = $this->perform_book_now_button_check($html, $site_url, $value);
        } elseif ($check_type === 'button_links') {
            $raw_results = $this->perform_button_links_check($html, $site_url, $value);
        } elseif ($check_type === 'social_links') {
            $raw_results = $this->perform_social_links_check($html, $site_url, $value);
        } elseif ($check_type === 'business_hours') {
            $raw_results = $this->perform_business_hours_check($html, $site_url, $value);
        } elseif ($check_type === 'h1_count') {
            $raw_results = $this->perform_h1_count_check($html, $site_url, $value);
        } elseif ($check_type === 'contrast') {
            $raw_results = $this->perform_contrast_check($html, $site_url, $value);
        } elseif ($check_type === 'image_size') {
            $raw_results = $this->perform_image_size_check($html, $site_url, $value);
        } elseif ($check_type === 'font_size') {
            $raw_results = $this->perform_font_size_check($html, $site_url, $value);
        } elseif ($check_type === 'duplicate_id') {
            $raw_results = $this->perform_duplicate_id_check($html, $site_url, $value);
        } elseif ($check_type === 'header_footer') {
            $raw_results = $this->perform_header_footer_check($html, $site_url, $value);
        } elseif ($check_type === 'h1_position') {
            $raw_results = $this->perform_h1_position_check($html, $site_url, $value);
        } elseif ($check_type === 'tel_whitespace') {
            $raw_results = $this->perform_tel_whitespace_check($html, $site_url, $value);
        } elseif ($check_type === 'other_buttons_target') {
            $raw_results = $this->perform_other_buttons_target_check($html, $site_url, $value);
        } elseif ($check_type === 'icon_list_inline') {
            $raw_results = $this->perform_icon_list_inline_check($html, $site_url, $value);
        } elseif ($check_type === 'copyright_year') {
            $raw_results = $this->perform_copyright_year_check($html, $site_url, $value);
        } elseif ($check_type === 'mac_marketing_link') {
            $raw_results = $this->perform_mac_marketing_link_check($html, $site_url, $value);
        } elseif ($check_type === 'privacy_link') {
            $raw_results = $this->perform_privacy_link_check($html, $site_url, $value);
        } elseif ($check_type === 'privacy_page_content') {
            $raw_results = $this->perform_privacy_page_content_check($html, $site_url, $value);
        } elseif ($check_type === 'url_hash') {
            $raw_results = $this->perform_url_hash_check($html, $site_url, $value);
        } elseif ($check_type === 'review_links') {
            $raw_results = $this->perform_review_links_check($html, $site_url, $value);
        } else {
            return [
                'success' => false,
                'type' => 'sitecheck',
                'message' => "Check type '{$check_type}' không được hỗ trợ hãy cập nhập MAC Core mới nhất để được hỗ trợ",
                'site_url' => $site_url
            ];
        }
        
        // If raw_results already has proper format, return it
        if (is_array($raw_results) && isset($raw_results['success']) && isset($raw_results['type'])) {
            return $raw_results;
        }
        
        // Otherwise, wrap raw results with proper format
        return $this->wrap_sitecheck_result($raw_results, $check_type, $page, $site_url);
    }
    
    /**
     * Wrap raw sitecheck results with proper format
     */
    private function wrap_sitecheck_result($raw_results, $check_type, $page, $site_url) {
        if (!is_array($raw_results)) {
            return [
                'success' => false,
                'type' => 'sitecheck',
                'message' => 'Lỗi xử lý check: ' . $check_type
            ];
        }
        
        // Determine result and message based on check type
        $result = false;
        $message = '';
        
        switch ($check_type) {
            case 'logo_links':
                $result = isset($raw_results['has_correct_logo']) ? $raw_results['has_correct_logo'] : false;
                $logo_found = $raw_results['logo_found'] ?? 0;
                $correct_links = $raw_results['correct_links'] ?? 0;
                $incorrect_links = $raw_results['incorrect_links'] ?? 0;
                
                if ($result) {
                    $message = "Tất cả logo có link đúng ({$correct_links}/{$logo_found}) tại page {$page}";
                } else {
                    $message = "Có logo không có link hoặc link không đúng ({$incorrect_links}/{$logo_found}) tại page {$page}";
                }
                break;
            case 'book_now_button':
                $result = isset($raw_results['has_correct_button']) ? $raw_results['has_correct_button'] : false;
                $button_found = $raw_results['button_found'] ?? 0;
                
                if ($result) {
                    $message = "Tất cả button Book Now có link đúng ({$button_found}/{$button_found}) tại page {$page}";
                } else {
                    if ($button_found === 0) {
                        $message = "Không tìm thấy button Book Now tại page {$page}";
                    } else {
                        $message = "Có button Book Now không có link hoặc link không đúng tại page {$page}";
                    }
                }
                break;
                
            case 'button_links':
                $result = isset($raw_results['has_valid_links']) ? $raw_results['has_valid_links'] : false;
                $total_buttons = $raw_results['total_buttons'] ?? 0;
                $invalid_count = $raw_results['invalid_count'] ?? 0;
                
                if ($result) {
                    $message = "Tất cả button/link có href hợp lệ tại page {$page}";
                } else {
                    $message = "Có {$invalid_count} button/link không có href hoặc href là # tại page {$page}";
                }
                break;
                
            case 'social_links':
                $result = isset($raw_results['has_valid_social_links']) ? $raw_results['has_valid_social_links'] : false;
                $invalid_count = $raw_results['invalid_count'] ?? 0;
                
                if ($result) {
                    $message = "Tất cả link mạng xã hội hợp lệ tại page {$page}";
                } else {
                    $message = "Có {$invalid_count} link mạng xã hội không hợp lệ tại page {$page}";
                }
                break;
                
            case 'business_hours':
                $result = isset($raw_results['has_business_hours']) ? $raw_results['has_business_hours'] : false;
                $message = $result ? 
                    "Có cụm từ \"Business Hours\" tại page {$page}" : 
                    "Không tìm thấy cụm từ \"Business Hours\" tại page {$page}";
                break;
                
            case 'h1_count':
                $result = isset($raw_results['has_correct_h1_count']) ? $raw_results['has_correct_h1_count'] : false;
                $h1_count = $raw_results['h1_count'] ?? 0;
                $message = "Số lượng thẻ H1: {$h1_count} tại page {$page}";
                break;
                
            case 'contrast':
                $result = isset($raw_results['has_contrast_issue']) ? !$raw_results['has_contrast_issue'] : false;
                $message = $result ? 
                    "Không có text trắng trên nền trắng tại page {$page}" : 
                    "Có text trắng trên nền trắng tại page {$page}";
                break;
                
            case 'image_size':
                $result = isset($raw_results['has_valid_image_sizes']) ? $raw_results['has_valid_image_sizes'] : false;
                $too_big_count = $raw_results['too_big_images_count'] ?? 0;
                
                if ($result) {
                    $message = "Không có hình ảnh vượt quá 1MB tại page {$page}";
                } else {
                    $message = "Có {$too_big_count} hình ảnh vượt quá 1MB tại page {$page}";
                }
                break;
                
            case 'font_size':
                $result = isset($raw_results['has_valid_font_sizes']) ? $raw_results['has_valid_font_sizes'] : false;
                $small_font_count = $raw_results['small_font_count'] ?? 0;
                
                if ($result) {
                    $message = "Tất cả font-size inline >= 16px tại page {$page}";
                } else {
                    $message = "Có {$small_font_count} font-size inline < 16px tại page {$page}";
                }
                break;
                
            case 'duplicate_id':
                $result = isset($raw_results['has_no_duplicate_ids']) ? $raw_results['has_no_duplicate_ids'] : false;
                $duplicate_count = $raw_results['duplicate_ids_count'] ?? 0;
                
                if ($result) {
                    $message = "Không có ID bị trùng tại page {$page}";
                } else {
                    $message = "Có {$duplicate_count} ID bị trùng tại page {$page}";
                }
                break;
                
            case 'header_footer':
                $result = isset($raw_results['has_one_header']) && isset($raw_results['has_one_footer']) ? 
                    ($raw_results['has_one_header'] && $raw_results['has_one_footer']) : false;
                $header_count = $raw_results['header_count'] ?? 0;
                $footer_count = $raw_results['footer_count'] ?? 0;
                $message = "Có {$header_count} header và {$footer_count} footer tại page {$page}";
                break;
                
            case 'h1_position':
                $result = isset($raw_results['h1_is_first']) ? $raw_results['h1_is_first'] : false;
                $first_heading = $raw_results['first_heading'] ?? 'unknown';
                $message = "Thẻ {$first_heading} nằm trên cùng tại page {$page}";
                break;
                
            case 'tel_whitespace':
                $result = isset($raw_results['has_no_tel_whitespace']) ? $raw_results['has_no_tel_whitespace'] : false;
                $tel_href_errors = $raw_results['tel_href_errors_count'] ?? 0;
                $tel_text_errors = $raw_results['tel_text_errors_count'] ?? 0;
                
                if ($result) {
                    $message = "Không có khoảng trắng trong tel: và phone number tại page {$page}";
                } else {
                    $message = "Có {$tel_href_errors} lỗi tel: href và {$tel_text_errors} lỗi phone number tại page {$page}";
                }
                break;
                
            case 'other_buttons_target':
                $result = isset($raw_results['has_valid_targets']) ? $raw_results['has_valid_targets'] : false;
                $total_links = $raw_results['total_links_with_target'] ?? 0;
                $invalid_count = $raw_results['invalid_count'] ?? 0;
                
                if ($result) {
                    $message = "Tất cả button thường không open new tab tại page {$page}";
                } else {
                    $message = "Có {$invalid_count} button thường open new tab tại page {$page}";
                }
                break;
                
            case 'icon_list_inline':
                $result = isset($raw_results['has_inline_setting']) ? $raw_results['has_inline_setting'] : false;
                $total_lists = $raw_results['total_icon_lists'] ?? 0;
                $invalid_count = $raw_results['invalid_count'] ?? 0;
                
                if ($result) {
                    $message = "Tất cả icon list đã set inline tại page {$page}";
                } else {
                    $message = "Có {$invalid_count} icon list chưa set inline tại page {$page}";
                }
                break;
                
            case 'copyright_year':
                $result = isset($raw_results['has_correct_year']) ? $raw_results['has_correct_year'] : false;
                $current_year = $raw_results['current_year'] ?? date('Y');
                
                if ($result) {
                    $message = "Footer có copyright năm {$current_year} tại page {$page}";
                } else {
                    $message = "Footer không có copyright năm {$current_year} tại page {$page}";
                }
                break;
                
            case 'mac_marketing_link':
                $result = isset($raw_results['link_correct']) ? $raw_results['link_correct'] : false;
                $has_link = isset($raw_results['has_mac_link']) ? $raw_results['has_mac_link'] : false;
                
                if ($result) {
                    $message = "Footer có link \"by Mac Marketing\" đúng tại page {$page}";
                } else {
                    $message = $has_link ? 
                        "Footer có link \"by Mac Marketing\" nhưng không đúng tại page {$page}" : 
                        "Footer không có link \"by Mac Marketing\" tại page {$page}";
                }
                break;
                
            case 'privacy_link':
                $result = isset($raw_results['has_privacy_link']) ? $raw_results['has_privacy_link'] : false;
                $message = $result ? 
                    "Footer có link Privacy Policy tại page {$page}" : 
                    "Footer không có link Privacy Policy tại page {$page}";
                break;
                
            case 'privacy_page_content':
                $result = isset($raw_results['has_mac_usa_one']) ? !$raw_results['has_mac_usa_one'] : true;
                $errors = $raw_results['errors'] ?? [];
                
                if ($result) {
                    $message = "Privacy Policy page không chứa \"Mac USA One\" tại page {$page}";
                } else {
                    $message = "Privacy Policy page chứa \"Mac USA One\" tại page {$page}";
                }
                break;
                
            case 'url_hash':
                $result = isset($raw_results['has_invalid_hash']) ? !$raw_results['has_invalid_hash'] : true;
                $invalid_hashes = $raw_results['invalid_hashes'] ?? [];
                
                if ($result) {
                    $message = "URL không chứa #happy hoặc #unhappy tại page {$page}";
                } else {
                    $message = "URL chứa hash không hợp lệ tại page {$page}";
                }
                break;
                
            case 'review_links':
                $result = isset($raw_results['has_valid_review_links']) ? $raw_results['has_valid_review_links'] : false;
                $invalid_count = $raw_results['invalid_count'] ?? 0;
                
                if ($result) {
                    $message = "Tất cả review links đúng format tại page {$page}";
                } else {
                    $message = "Có {$invalid_count} review links không đúng format tại page {$page}";
                }
                break;
                
            default:
                $result = false;
                $message = "Check type không hỗ trợ: {$check_type}";
        }
        
        return [
            'success' => true,
            'type' => 'sitecheck',
            'result' => $result,
            'message' => $message,
            'site_url' => $site_url,
            'details' => $raw_results
        ];
    }
    
    /**
     * Handle sitecheck requests
     * Supports various website checks like HTML tag counting, content validation, etc.
     */
    private function handle_sitecheck($item) {
        try {
            $name = isset($item['name']) ? sanitize_text_field($item['name']) : null;
            $value = isset($item['value']) ? $item['value'] : null;
            
            // Check if this is a batch request (value is array of check types or has checks key)
            if ((is_array($value) && isset($value[0]) && is_string($value[0])) || 
                (is_array($value) && isset($value['checks']) && is_array($value['checks']))) {
                return $this->handle_batch_sitecheck($value, $item);
            }
            
            // Get site URL - check if custom page provided
            $site_url = get_site_url();
            if (isset($value['page']) && !empty($value['page'])) {
                $page_slug = sanitize_text_field($value['page']);
                // If page is empty or "/", default to "home"
                if (empty($page_slug) || $page_slug === '/') {
                    $page_slug = 'home';
                }
                $site_url = rtrim($site_url, '/') . '/' . $page_slug;
            } else {
                // Default to home page if no page specified
                $site_url = rtrim($site_url, '/') . '/home';
            }
            
            if (empty($site_url)) {
                return [
                    'success' => false,
                    'type' => 'sitecheck',
                    'message' => 'Không thể lấy URL website'
                ];
            }
            
            // Fetch website content (with caching)
            $html = $this->get_cached_html($site_url);
            
            if ($html === false) {
                return [
                    'success' => false,
                    'type' => 'sitecheck',
                    'message' => 'Không thể truy cập website hoặc website trả về nội dung rỗng'
                ];
            }
            
            // Process different types of checks
            $results = [];
            
            // Check if this is a security check
            if (isset($value['check_type']) && $value['check_type'] === 'security_status') {
                $results = $this->perform_security_checksite_checks($html, $site_url);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'logo_links') {
                // Use perform_single_sitecheck for logo_links to avoid double processing
                $page = isset($value['page']) ? $value['page'] : 'home';
                $result = $this->perform_single_sitecheck('logo_links', $html, $site_url, $page);
                $result['name'] = $name ?: 'website_check';
                return $result;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'book_now_button') {
                $results = $this->perform_book_now_button_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'button_links') {
                $results = $this->perform_button_links_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'social_links') {
                $results = $this->perform_social_links_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'business_hours') {
                $results = $this->perform_business_hours_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'h1_count') {
                $results = $this->perform_h1_count_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'contrast') {
                $results = $this->perform_contrast_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'image_size') {
                $results = $this->perform_image_size_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'font_size') {
                $results = $this->perform_font_size_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'duplicate_id') {
                $results = $this->perform_duplicate_id_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'header_footer') {
                $results = $this->perform_header_footer_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'h1_position') {
                $results = $this->perform_h1_position_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'tel_whitespace') {
                $results = $this->perform_tel_whitespace_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'other_buttons_target') {
                $results = $this->perform_other_buttons_target_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'icon_list_inline') {
                $results = $this->perform_icon_list_inline_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'copyright_year') {
                $results = $this->perform_copyright_year_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'mac_marketing_link') {
                $results = $this->perform_mac_marketing_link_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'privacy_link') {
                $results = $this->perform_privacy_link_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'privacy_page_content') {
                $results = $this->perform_privacy_page_content_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'url_hash') {
                $results = $this->perform_url_hash_check($html, $site_url, $value);
            } elseif (isset($value['check_type']) && $value['check_type'] === 'review_links') {
                $results = $this->perform_review_links_check($html, $site_url, $value);
            } elseif (empty($value)) {
                // Default checks if no specific value provided
                $results = $this->perform_default_checksite_checks($html, $site_url);
            } else {
                // Custom checks based on value parameter - but skip if already handled
                if (!in_array($value['check_type'] ?? '', ['logo_links', 'book_now_button', 'button_links', 'social_links', 'business_hours', 'h1_count', 'contrast', 'image_size', 'font_size', 'duplicate_id', 'header_footer', 'h1_position', 'tel_whitespace', 'other_buttons_target', 'icon_list_inline', 'copyright_year', 'mac_marketing_link', 'privacy_link', 'privacy_page_content', 'url_hash', 'review_links'])) {
                    $results = $this->perform_custom_checksite_checks($html, $site_url, $value);
                } else {
                    $results = [];
                }
            }
            
            // Process different types of checks and generate appropriate messages
            $overall_result = true;
            $overall_message = 'Website check completed';
            
            if (isset($value['check_type']) && $value['check_type'] === 'security_status') {
                // Check for any security issues - if any found, result = false
                $security_issues = [];
                
                if (isset($results['website_live']) && !$results['website_live']) {
                    $security_issues[] = 'Website down';
                }
                
                if (isset($results['title_hijacked']) && $results['title_hijacked']) {
                    $security_issues[] = 'Title hijacked';
                }
                
                if (isset($results['suspicious_redirects']) && $results['suspicious_redirects']) {
                    $security_issues[] = 'Suspicious redirects';
                }
                
                if (isset($results['content_anomaly']) && $results['content_anomaly']) {
                    $security_issues[] = 'Content anomaly';
                }
                
                $overall_result = empty($security_issues);
                
                if ($overall_result) {
                    $overall_message = 'Website an toàn';
                } else {
                    $overall_message = 'Website có vấn đề bảo mật: ' . implode(', ', $security_issues);
                }
            } elseif (isset($value['check_type']) && $value['check_type'] === 'logo_links') {
                // logo_links is already handled in perform_single_sitecheck, skip processing here
                $overall_result = false;
                $overall_message = 'Logo links check handled elsewhere';
            } elseif (isset($value['check_type']) && $value['check_type'] === 'book_now_button') {
                $overall_result = isset($results['has_correct_button']) ? $results['has_correct_button'] : false;
                
                // Determine the actual page being checked
                $actual_page = 'home';
                if (isset($value['page']) && !empty($value['page'])) {
                    $page_slug = sanitize_text_field($value['page']);
                    if (!empty($page_slug) && $page_slug !== '/') {
                        $actual_page = $page_slug;
                    }
                }
                
                if ($overall_result) {
                    $overall_message = 'Tất cả button Book Now có link đúng (' . ($results['correct_links'] ?? 0) . '/' . ($results['button_found'] ?? 0) . ') tại page ' . $actual_page;
                } else {
                    if (($results['button_found'] ?? 0) === 0) {
                        $overall_message = 'Không tìm thấy button Book Now tại page ' . $actual_page;
                    } else {
                        $overall_message = 'Có button Book Now không có link hoặc link không đúng (' . ($results['incorrect_links'] ?? 0) . '/' . ($results['button_found'] ?? 0) . ') tại page ' . $actual_page;
                    }
                }
            } elseif (isset($value['check_type']) && $value['check_type'] === 'button_links') {
                $overall_result = isset($results['has_valid_links']) ? $results['has_valid_links'] : false;
                $actual_page = $this->get_actual_page($value);
                $overall_message = $overall_result ? 
                    'Tất cả button/link có href hợp lệ tại page ' . $actual_page : 
                    'Có ' . ($results['invalid_count'] ?? 0) . ' button/link không có href hoặc href là # tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'social_links') {
                $overall_result = isset($results['has_valid_social_links']) ? $results['has_valid_social_links'] : false;
                $actual_page = $this->get_actual_page($value);
                $overall_message = $overall_result ? 
                    'Tất cả link mạng xã hội hợp lệ tại page ' . $actual_page : 
                    'Có ' . ($results['invalid_count'] ?? 0) . ' link mạng xã hội không hợp lệ tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'business_hours') {
                $overall_result = isset($results['has_business_hours']) ? $results['has_business_hours'] : false;
                $actual_page = $this->get_actual_page($value);
                $overall_message = $overall_result ? 
                    'Có cụm từ "Business Hours" tại page ' . $actual_page : 
                    'Không tìm thấy cụm từ "Business Hours" tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'h1_count') {
                $overall_result = isset($results['has_correct_h1_count']) ? $results['has_correct_h1_count'] : false;
                $actual_page = $this->get_actual_page($value);
                $h1_count = $results['h1_count'] ?? 0;
                $overall_message = $overall_result ? 
                    'Có đúng 1 thẻ H1 tại page ' . $actual_page : 
                    'Số lượng thẻ H1: ' . $h1_count . ' tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'contrast') {
                $overall_result = !isset($results['has_contrast_issue']) || !$results['has_contrast_issue'];
                $actual_page = $this->get_actual_page($value);
                $overall_message = $overall_result ? 
                    'Không có text trắng trên nền trắng tại page ' . $actual_page : 
                    'Có text trắng trên nền trắng tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'image_size') {
                $overall_result = isset($results['has_valid_image_sizes']) ? $results['has_valid_image_sizes'] : false;
                $actual_page = $this->get_actual_page($value);
                $count = $results['too_big_images_count'] ?? 0;
                $overall_message = $overall_result ? 
                    'Không có hình ảnh vượt quá 1MB tại page ' . $actual_page : 
                    'Có ' . $count . ' hình ảnh vượt quá 1MB tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'font_size') {
                $overall_result = isset($results['has_valid_font_sizes']) ? $results['has_valid_font_sizes'] : false;
                $actual_page = $this->get_actual_page($value);
                $count = $results['small_font_count'] ?? 0;
                $overall_message = $overall_result ? 
                    'Tất cả font-size inline >= 16px tại page ' . $actual_page : 
                    'Có ' . $count . ' thẻ có font-size < 16px tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'duplicate_id') {
                $overall_result = isset($results['has_no_duplicate_ids']) ? $results['has_no_duplicate_ids'] : false;
                $actual_page = $this->get_actual_page($value);
                $count = $results['duplicate_ids_count'] ?? 0;
                $overall_message = $overall_result ? 
                    'Không có ID bị trùng tại page ' . $actual_page : 
                    'Có ' . $count . ' ID bị trùng tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'header_footer') {
                $has_one_header = isset($results['has_one_header']) ? $results['has_one_header'] : false;
                $has_one_footer = isset($results['has_one_footer']) ? $results['has_one_footer'] : false;
                $overall_result = $has_one_header && $has_one_footer;
                $actual_page = $this->get_actual_page($value);
                
                if ($overall_result) {
                    $overall_message = 'Có đúng 1 header và 1 footer tại page ' . $actual_page;
                } else {
                    $header_count = $results['header_count'] ?? 0;
                    $footer_count = $results['footer_count'] ?? 0;
                    $overall_message = 'Header: ' . $header_count . ', Footer: ' . $footer_count . ' tại page ' . $actual_page;
                }
            } elseif (isset($value['check_type']) && $value['check_type'] === 'h1_position') {
                $overall_result = isset($results['h1_is_first']) ? $results['h1_is_first'] : false;
                $actual_page = $this->get_actual_page($value);
                $first_heading = $results['first_heading'] ?? 'không có';
                $overall_message = $overall_result ? 
                    'Thẻ H1 nằm trên cùng tại page ' . $actual_page : 
                    'Thẻ heading đầu tiên là: ' . $first_heading . ' tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'tel_whitespace') {
                $overall_result = isset($results['has_no_tel_whitespace']) ? $results['has_no_tel_whitespace'] : false;
                $actual_page = $this->get_actual_page($value);
                $href_errors = $results['tel_href_errors_count'] ?? 0;
                $text_errors = $results['tel_text_errors_count'] ?? 0;
                
                if ($overall_result) {
                    $overall_message = 'Không có khoảng trắng trong tel: và phone number tại page ' . $actual_page;
                } else {
                    $overall_message = 'Có ' . $href_errors . ' href tel: và ' . $text_errors . ' phone text có khoảng trắng tại page ' . $actual_page;
                }
            } elseif (isset($value['check_type']) && $value['check_type'] === 'other_buttons_target') {
                $overall_result = isset($results['has_valid_targets']) ? $results['has_valid_targets'] : false;
                $actual_page = $this->get_actual_page($value);
                $count = $results['invalid_count'] ?? 0;
                $overall_message = $overall_result ? 
                    'Tất cả button thường không open new tab tại page ' . $actual_page : 
                    'Có ' . $count . ' button thường đang open new tab tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'icon_list_inline') {
                $overall_result = isset($results['has_inline_setting']) ? $results['has_inline_setting'] : false;
                $actual_page = $this->get_actual_page($value);
                $count = $results['invalid_count'] ?? 0;
                $overall_message = $overall_result ? 
                    'Tất cả icon list đã set inline tại page ' . $actual_page : 
                    'Có ' . $count . ' icon list chưa set inline tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'copyright_year') {
                $overall_result = isset($results['has_correct_year']) ? $results['has_correct_year'] : false;
                $actual_page = $this->get_actual_page($value);
                $year = $results['current_year'] ?? date('Y');
                $overall_message = $overall_result ? 
                    'Footer có copyright năm ' . $year . ' tại page ' . $actual_page : 
                    'Footer không có copyright năm ' . $year . ' tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'mac_marketing_link') {
                $overall_result = isset($results['link_correct']) ? $results['link_correct'] : false;
                $actual_page = $this->get_actual_page($value);
                $has_link = $results['has_mac_link'] ?? false;
                if ($overall_result) {
                    $overall_message = 'Footer có link "by Mac Marketing" đúng tại page ' . $actual_page;
                } elseif ($has_link) {
                    $overall_message = 'Footer có link "Mac Marketing" nhưng href không đúng tại page ' . $actual_page;
                } else {
                    $overall_message = 'Footer thiếu link "by Mac Marketing" tại page ' . $actual_page;
                }
            } elseif (isset($value['check_type']) && $value['check_type'] === 'privacy_link') {
                $overall_result = isset($results['has_privacy_link']) ? $results['has_privacy_link'] : false;
                $actual_page = $this->get_actual_page($value);
                $overall_message = $overall_result ? 
                    'Footer có link Privacy Policy tại page ' . $actual_page : 
                    'Footer thiếu link Privacy Policy tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'privacy_page_content') {
                $overall_result = !isset($results['has_mac_usa_one']) || !$results['has_mac_usa_one'];
                $actual_page = $this->get_actual_page($value);
                $errors = $results['errors'] ?? [];
                $overall_message = $overall_result ? 
                    'Privacy Policy page không chứa "Mac USA One" tại page ' . $actual_page : 
                    'Privacy Policy page chứa "Mac USA One": ' . implode(', ', $errors) . ' tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'url_hash') {
                $overall_result = !isset($results['has_invalid_hash']) || !$results['has_invalid_hash'];
                $actual_page = $this->get_actual_page($value);
                $hashes = $results['invalid_hashes'] ?? [];
                $overall_message = $overall_result ? 
                    'URL không chứa #happy hoặc #unhappy tại page ' . $actual_page : 
                    'URL chứa hash không hợp lệ: ' . implode(', ', $hashes) . ' tại page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'review_links') {
                $overall_result = isset($results['has_valid_review_links']) ? $results['has_valid_review_links'] : false;
                $actual_page = $this->get_actual_page($value);
                $count = $results['invalid_count'] ?? 0;
                $overall_message = $overall_result ? 
                    'Tất cả review links đúng format tại page ' . $actual_page : 
                    'Có ' . $count . ' review link không đúng format tại page ' . $actual_page;
            }
            
            return [
                'success' => true,
                'type' => 'sitecheck',
                'name' => $name ?: 'website_check',
                'result' => $overall_result,
                'message' => $overall_message,
                'site_url' => $site_url,
                'details' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'type' => 'sitecheck',
                'message' => 'Lỗi khi kiểm tra website: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Perform logo links check
     */
    private function perform_logo_links_check($html, $site_url, $value) {
        $results = [];
        $logo_elements = [];
        $correct_links = 0;
        $incorrect_links = 0;
        // Removed duplicate tracking - show all logos even if same image
        
        // Step 1: Look for class "img-logo" (highest priority) - Elementor structure
        $logo_class_pattern = '/<[^>]*class="[^"]*elementor-element[^"]*img-logo[^"]*"[^>]*>.*?<\/[^>]*>/is';
        preg_match_all($logo_class_pattern, $html, $class_matches);
        
        if (!empty($class_matches[0])) {
            // Found elements with img-logo class (Elementor widgets)
            foreach ($class_matches[0] as $element) {
                // Extract img tag from within the elementor element
                $img_pattern = '/<img[^>]*>/i';
                preg_match_all($img_pattern, $element, $img_matches);
                
                foreach ($img_matches[0] as $img_tag) {
                    $logo_info = $this->extract_logo_info_from_elementor($element, $img_tag, $site_url);
                    if ($logo_info) {
                        $logo_elements[] = $logo_info;
                        if ($logo_info['correct']) {
                            $correct_links++;
                        } else {
                            $incorrect_links++;
                        }
                    }
                }
            }
        } else {
            // Step 2: Look for any Elementor elements with img-logo class (fallback)
            $logo_class_pattern_fallback = '/<[^>]*class="[^"]*elementor-element[^"]*img-logo[^"]*"[^>]*>.*?<\/[^>]*>/is';
            preg_match_all($logo_class_pattern_fallback, $html, $class_matches_fallback);
            
            if (!empty($class_matches_fallback[0])) {
                // Found elements with img-logo class
                foreach ($class_matches_fallback[0] as $element) {
                    // Check if it's an Elementor element
                    if (strpos($element, 'elementor-element') !== false) {
                        // Extract img tag from within the elementor element
                        $img_pattern = '/<img[^>]*>/i';
                        preg_match_all($img_pattern, $element, $img_matches);
                        
                        foreach ($img_matches[0] as $img_tag) {
                            $logo_info = $this->extract_logo_info_from_elementor($element, $img_tag, $site_url);
                            if ($logo_info) {
                                $logo_elements[] = $logo_info;
                                if ($logo_info['correct']) {
                                    $correct_links++;
                                } else {
                                    $incorrect_links++;
                                }
                            }
                        }
                    } else {
                        // Regular element with img-logo class
                        $img_pattern = '/<img[^>]*>/i';
                        preg_match_all($img_pattern, $element, $img_matches);
                        
                        foreach ($img_matches[0] as $img_tag) {
                            $logo_info = $this->extract_logo_info($img_tag, $site_url);
                            if ($logo_info) {
                                $logo_elements[] = $logo_info;
                                if ($logo_info['correct']) {
                                    $correct_links++;
                                } else {
                                    $incorrect_links++;
                                }
                            }
                        }
                    }
                }
            } else {
                // Step 3: Look in header and footer for img tags, then find parent Elementor element
                $header_footer_pattern = '/<(header|footer)[^>]*>.*?<\/(header|footer)>/is';
                preg_match_all($header_footer_pattern, $html, $header_footer_matches);
                
                foreach ($header_footer_matches[0] as $section) {
                    $img_pattern = '/<img[^>]*>/i';
                    preg_match_all($img_pattern, $section, $img_matches);
                    
                    foreach ($img_matches[0] as $img_tag) {
                        // Find parent Elementor element containing this img
                        $parent_elementor = $this->find_parent_elementor_element($html, $img_tag);
                        
                        if ($parent_elementor) {
                            $logo_info = $this->extract_logo_info_from_elementor($parent_elementor, $img_tag, $site_url);
                        } else {
                            $logo_info = $this->extract_logo_info_with_context($section, $img_tag, $site_url);
                        }
                        
                        if ($logo_info) {
                            // Additional filter: check if it looks like a logo
                            if ($this->is_likely_logo($logo_info, $img_tag)) {
                                $logo_elements[] = $logo_info;
                                if ($logo_info['correct']) {
                                    $correct_links++;
                                } else {
                                    $incorrect_links++;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        $logo_found = count($logo_elements);
        $has_correct_logo = $incorrect_links === 0;
        
        // Determine page name
        $page_name = isset($value['page']) && !empty($value['page']) ? $value['page'] : 'home';
        
        // Create message
        if ($has_correct_logo) {
            $message = "Tất cả logo có link đúng ({$correct_links}/{$logo_found}) tại page {$page_name}";
        } else {
            $message = "Có logo không có link hoặc link không đúng ({$incorrect_links}/{$logo_found}) tại page {$page_name}";
        }
        
        // Build details
        $details = [
            'logo_found' => $logo_found,
            'correct_links' => $correct_links,
            'incorrect_links' => $incorrect_links,
            'has_correct_logo' => $has_correct_logo
        ];
        
        // Add logo elements if there are errors
        if ($incorrect_links > 0) {
            $details['logo_elements'] = $logo_elements;
        }
        
        return $details;
    }
    
    /**
     * Check 2: Link button "Book Now" - kiểm tra link bắt đầu tel: hoặc https://lk.macmarketing.us/
     */
    private function perform_book_now_button_check($html, $site_url, $value) {
        $results = [];
        $button_elements = [];
        $correct_links = 0;
        $incorrect_links = 0;
        
        // Sử dụng DOMDocument và XPath như trong analyzer.php
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // Bước 1: Tìm class "btn-booking" (ưu tiên cao nhất) - CHỈ ELEMENTOR
        $btn_booking_elements = $xpath->query('//*[contains(@class, "btn-booking")]');
        
        if ($btn_booking_elements->length > 0) {
            // Xử lý btn-booking elements
            foreach ($btn_booking_elements as $element) {
                $button_info = $this->extract_book_now_button_info_from_dom($element, $site_url);
                if ($button_info) {
                    $button_elements[] = $button_info;
                    if ($button_info['correct']) {
                        $correct_links++;
                    } else {
                        $incorrect_links++;
                    }
                }
            }
        } else {
            // Bước 2: Tìm thẻ <a> có text "Book Now" hoặc "Book appointment" - CHỈ THẺ A
            $book_buttons = $xpath->query("//a[descendant::text()[contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'book now') or contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'book appointment')]]");
            
            // Xử lý book buttons
            foreach ($book_buttons as $btn) {
                $button_info = $this->extract_book_now_button_info_from_dom($btn, $site_url);
                if ($button_info) {
                    $button_elements[] = $button_info;
                    if ($button_info['correct']) {
                        $correct_links++;
                    } else {
                        $incorrect_links++;
                    }
                }
            }
        }
        
        // Chỉ trả về kết quả có/không, không cần chi tiết
        $results['button_found'] = count($button_elements);
        $results['has_correct_button'] = $correct_links > 0; // Chỉ cần có ít nhất 1 button đúng
        
        
        return $results;
    }
    
    /**
     * Check 3: Link các button khác (không được rỗng hoặc #)
     * Loại trừ: toggle links, zipcode links
     */
    private function perform_button_links_check($html, $site_url, $value) {
        $results = [];
        $invalid_elements = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // Tìm tất cả thẻ <a> và <button>
        $buttons = $xpath->query('//a | //button');
        
        foreach ($buttons as $btn) {
            $href = $btn->getAttribute('href') ?: '';
            $class = $btn->getAttribute('class') ?: '';
            
            // Bỏ qua nếu có class 'elementor-toggle-title'
            if (strpos($class, 'elementor-toggle-title') !== false) {
                continue;
            }
            
            // Bỏ qua nếu có class 'zipcode' (nail salon zipcode)
            if (strpos($class, 'zipcode') !== false) {
                continue;
            }
            
            // Check if href is empty or #
            if ($href === '' || $href === '#') {
                $invalid_elements[] = [
                    'html' => $doc->saveHTML($btn),
                    'href' => $href,
                    'text' => trim($btn->textContent)
                ];
            }
        }
        
        $results['total_buttons'] = $buttons->length;
        $results['invalid_count'] = count($invalid_elements);
        $results['has_valid_links'] = count($invalid_elements) === 0;
        
        // Chỉ trả invalid_elements khi có lỗi
        if (count($invalid_elements) > 0) {
            $results['invalid_elements'] = $invalid_elements;
        }
        
        return $results;
    }
    
    /**
     * Check 21: Các button khác (không phải Book Now) không được open new tab
     */
    private function perform_other_buttons_target_check($html, $site_url, $value) {
        $results = [];
        $invalid_buttons = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // Tìm tất cả thẻ <a> có target="_blank"
        $links_with_target = $xpath->query('//a[@target="_blank"]');
        
        foreach ($links_with_target as $link) {
            $text = trim($link->textContent);
            $href = $link->getAttribute('href');
            $class = $link->getAttribute('class');
            
            // Bỏ qua Book Now buttons (class btn-booking hoặc text chứa "book")
            if (strpos($class, 'btn-booking') !== false || 
                stripos($text, 'book now') !== false || 
                stripos($text, 'book appointment') !== false) {
                continue; // Skip Book Now buttons
            }
            
            // Bỏ qua social links (được phép open new tab)
            $socials = ['facebook.com', 'instagram.com', 'twitter.com', 'linkedin.com', 'yelp.com', 'youtube.com', 'tiktok.com'];
            $is_social = false;
            foreach ($socials as $social) {
                if (strpos($href, $social) !== false) {
                    $is_social = true;
                    break;
                }
            }
            if ($is_social) {
                continue; // Skip social links
            }
            
            // Bỏ qua external links (macmarketing.us, google maps, etc)
            $allowed_external = ['macmarketing.us', 'maps.google.com', 'g.page'];
            $is_allowed_external = false;
            foreach ($allowed_external as $domain) {
                if (strpos($href, $domain) !== false) {
                    $is_allowed_external = true;
                    break;
                }
            }
            if ($is_allowed_external) {
                continue; // Skip allowed external links
            }
            
            // Nếu không thuộc các trường hợp trên → Error
            $invalid_buttons[] = [
                'html' => $doc->saveHTML($link),
                'text' => $text,
                'href' => $href,
                'target' => '_blank'
            ];
        }
        
        $results['total_links_with_target'] = $links_with_target->length;
        $results['invalid_count'] = count($invalid_buttons);
        $results['has_valid_targets'] = count($invalid_buttons) === 0;
        
        // Chỉ trả invalid_buttons khi có lỗi
        if (count($invalid_buttons) > 0) {
            $results['invalid_buttons'] = $invalid_buttons;
        }
        
        return $results;
    }
    
    /**
     * Check 26: Icon list phải set inline (elementor-list-item-link-inline)
     */
    private function perform_icon_list_inline_check($html, $site_url, $value) {
        $results = [];
        $invalid_icon_lists = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // Tìm tất cả icon list widgets
        $icon_lists = $xpath->query('//div[contains(@class, "elementor-widget-icon-list")]');
        
        foreach ($icon_lists as $list) {
            $classes = $list->getAttribute('class');
            
            // Check if có class "elementor-list-item-link-inline"
            if (strpos($classes, 'elementor-list-item-link-inline') === false) {
                // Không có inline class → Error
                $data_id = '';
                if (preg_match('/elementor-element-([a-z0-9]+)/', $classes, $matches)) {
                    $data_id = $matches[1];
                }
                
                $invalid_icon_lists[] = [
                    'html' => $doc->saveHTML($list),
                    'data_id' => $data_id,
                    'classes' => $classes
                ];
            }
        }
        
        $results['total_icon_lists'] = $icon_lists->length;
        $results['invalid_count'] = count($invalid_icon_lists);
        $results['has_inline_setting'] = count($invalid_icon_lists) === 0;
        
        // Chỉ trả invalid_icon_lists khi có lỗi
        if (count($invalid_icon_lists) > 0) {
            $results['invalid_icon_lists'] = $invalid_icon_lists;
        }
        
        return $results;
    }
    
    /**
     * Check 27: Copyright năm đúng (2025)
     */
    private function perform_copyright_year_check($html, $site_url, $value) {
        $results = [];
        $current_year = date('Y'); // 2025
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // Tìm footer
        $footers = $xpath->query('//footer');
        $has_correct_year = false;
        
        if ($footers->length > 0) {
            $footer_text = $footers[0]->textContent;
            if (strpos($footer_text, $current_year) !== false) {
                $has_correct_year = true;
            }
        }
        
        $results['current_year'] = $current_year;
        $results['has_correct_year'] = $has_correct_year;
        
        return $results;
    }
    
    /**
     * Check 28: Footer có "by Mac Marketing" link
     */
    private function perform_mac_marketing_link_check($html, $site_url, $value) {
        $results = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // Tìm footer
        $footers = $xpath->query('//footer');
        $has_mac_link = false;
        $link_correct = false;
        
        if ($footers->length > 0) {
            // Tìm link chứa "Mac Marketing" hoặc "macmarketing"
            $mac_links = $xpath->query('.//a[contains(translate(., "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "mac") and contains(translate(., "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "marketing")]', $footers[0]);
            
            if ($mac_links->length > 0) {
                $has_mac_link = true;
                $href = $mac_links[0]->getAttribute('href');
                if (strpos($href, 'macmarketing.us') !== false) {
                    $link_correct = true;
                }
            }
        }
        
        $results['has_mac_link'] = $has_mac_link;
        $results['link_correct'] = $link_correct;
        
        return $results;
    }
    
    /**
     * Check 29: Privacy Policy link ở footer
     */
    private function perform_privacy_link_check($html, $site_url, $value) {
        $results = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // Tìm footer
        $footers = $xpath->query('//footer');
        $has_privacy_link = false;
        
        if ($footers->length > 0) {
            // Tìm link chứa "/privacy-policy"
            $privacy_links = $xpath->query('.//a[contains(@href, "/privacy-policy")]', $footers[0]);
            if ($privacy_links->length > 0) {
                $has_privacy_link = true;
            }
        }
        
        $results['has_privacy_link'] = $has_privacy_link;
        
        return $results;
    }
    
    /**
     * Check 33: Privacy Policy page - không chứa "Mac USA One"
     */
    private function perform_privacy_page_content_check($html, $site_url, $value) {
        $results = [];
        $errors = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // Check H1
        $h1_tags = $xpath->query('//h1');
        if ($h1_tags->length > 0) {
            $h1_text = $h1_tags[0]->textContent;
            if (stripos($h1_text, 'Mac USA One') !== false) {
                $errors[] = 'H1 chứa "Mac USA One"';
            }
        }
        
        // Check toàn bộ content
        if (stripos($html, 'Mac USA One') !== false) {
            $errors[] = 'Content chứa "Mac USA One"';
        }
        
        $results['has_mac_usa_one'] = count($errors) > 0;
        $results['errors'] = $errors;
        
        return $results;
    }
    
    /**
     * Check 36: URL không chứa #happy hoặc #unhappy
     */
    private function perform_url_hash_check($html, $site_url, $value) {
        $results = [];
        
        // Check current URL
        $has_invalid_hash = false;
        $invalid_hashes = [];
        
        if (strpos($site_url, '#happy') !== false) {
            $has_invalid_hash = true;
            $invalid_hashes[] = '#happy';
        }
        
        if (strpos($site_url, '#unhappy') !== false) {
            $has_invalid_hash = true;
            $invalid_hashes[] = '#unhappy';
        }
        
        $results['has_invalid_hash'] = $has_invalid_hash;
        $results['invalid_hashes'] = $invalid_hashes;
        
        return $results;
    }
    
    /**
     * Check 37: Review links validation (Google + Yelp)
     */
    private function perform_review_links_check($html, $site_url, $value) {
        $results = [];
        $invalid_review_links = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // Tìm Google review links
        $google_links = $xpath->query('//a[contains(@href, "google.com")]');
        foreach ($google_links as $link) {
            $href = $link->getAttribute('href');
            
            // Check if it's a review link
            if (strpos($href, 'review') !== false || strpos($href, 'writereview') !== false) {
                // Validate format
                $valid = (strpos($href, 'g.page/r') !== false || 
                          strpos($href, 'search.google.com/local/writereview') !== false);
                
                if (!$valid) {
                    $invalid_review_links[] = [
                        'html' => $doc->saveHTML($link),
                        'href' => $href,
                        'type' => 'Google Review',
                        'error' => 'Link không đúng format (phải là g.page/r... hoặc search.google.com/local/writereview?)'
                    ];
                }
            }
        }
        
        // Tìm Yelp review links
        $yelp_links = $xpath->query('//a[contains(@href, "yelp.com")]');
        foreach ($yelp_links as $link) {
            $href = $link->getAttribute('href');
            
            // Check if it's a review link
            if (strpos($href, 'review') !== false || strpos($href, 'writereview') !== false) {
                // Validate format
                $valid = (strpos($href, 'writeareview/biz') !== false);
                
                if (!$valid) {
                    $invalid_review_links[] = [
                        'html' => $doc->saveHTML($link),
                        'href' => $href,
                        'type' => 'Yelp Review',
                        'error' => 'Link không đúng format (phải là yelp.com/writeareview/biz)'
                    ];
                }
            }
        }
        
        $results['invalid_count'] = count($invalid_review_links);
        $results['has_valid_review_links'] = count($invalid_review_links) === 0;
        
        // Chỉ trả invalid_review_links khi có lỗi
        if (count($invalid_review_links) > 0) {
            $results['invalid_review_links'] = $invalid_review_links;
        }
        
        return $results;
    }
    
    /**
     * Check 4: Link social
     */
    private function perform_social_links_check($html, $site_url, $value) {
        $results = [];
        $invalid_social_links = [];
        $socials = ['facebook.com', 'instagram.com', 'twitter.com', 'linkedin.com', 'yelp.com'];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        $links = $xpath->query('//a');
        foreach ($links as $a) {
            $href = $a->getAttribute('href');
            foreach ($socials as $social) {
                if (strpos($href, $social) !== false) {
                    if (empty($href) || $href === '#' || strpos($href, 'https://') !== 0) {
                        $invalid_social_links[] = [
                            'html' => $doc->saveHTML($a),
                            'href' => $href,
                            'social_type' => str_replace('.com', '', $social)
                        ];
                    }
                }
            }
        }
        
        $results['invalid_count'] = count($invalid_social_links);
        $results['has_valid_social_links'] = count($invalid_social_links) === 0;
        
        // Chỉ trả invalid_elements khi có lỗi
        if (count($invalid_social_links) > 0) {
            $results['invalid_elements'] = $invalid_social_links;
        }
        
        return $results;
    }
    
    /**
     * Check 5: Business Hours
     */
    private function perform_business_hours_check($html, $site_url, $value) {
        $results = [];
        $has_business_hours = stripos($html, 'business hours') !== false;
        
        $results['has_business_hours'] = $has_business_hours;
        
        return $results;
    }
    
    /**
     * Check 6: Thẻ H1
     */
    private function perform_h1_count_check($html, $site_url, $value) {
        $results = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        
        $h1_count = $doc->getElementsByTagName('h1')->length;
        
        $results['h1_count'] = $h1_count;
        $results['has_correct_h1_count'] = $h1_count === 1;
        
        return $results;
    }
    
    /**
     * Check 7: Contrast thấp
     */
    private function perform_contrast_check($html, $site_url, $value) {
        $results = [];
        $contrast_issue = false;
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        $spans = $xpath->query('//*[contains(@style, "color") and contains(@style, "background")]');
        foreach ($spans as $el) {
            $style = $el->getAttribute('style');
            if (preg_match('/color:\s*#fff/i', $style) && preg_match('/background(-color)?:\s*#fff/i', $style)) {
                $contrast_issue = true;
                break;
            }
        }
        
        $results['has_contrast_issue'] = $contrast_issue;
        
        return $results;
    }
    
    /**
     * Check 8: Hình ảnh lớn hơn 1MB
     */
    private function perform_image_size_check($html, $site_url, $value) {
        $results = [];
        $too_big_imgs = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        foreach ($xpath->query('//img') as $img) {
            $src = $img->getAttribute('src');
            if (!$src) continue;
            
            if (!preg_match('/^https?:\/\//', $src)) {
                $parsed_url = parse_url($site_url);
                $src = rtrim($parsed_url['scheme'] . '://' . $parsed_url['host'], '/') . '/' . ltrim($src, '/');
            }
            
            $headers = @get_headers($src, 1);
            if ($headers && isset($headers['Content-Length'])) {
                $length = is_array($headers['Content-Length']) ? end($headers['Content-Length']) : $headers['Content-Length'];
                if ((int)$length > 1024 * 1024) {
                    $size_mb = round((int)$length / (1024 * 1024), 2);
                    $too_big_imgs[] = [
                        'src' => $src,
                        'size_mb' => $size_mb,
                        'size_bytes' => (int)$length,
                        'html' => $doc->saveHTML($img)
                    ];
                }
            }
        }
        
        $results['too_big_images_count'] = count($too_big_imgs);
        $results['has_valid_image_sizes'] = count($too_big_imgs) === 0;
        
        // Chỉ trả too_big_images khi có lỗi
        if (count($too_big_imgs) > 0) {
            $results['too_big_images'] = $too_big_imgs;
        }
        
        return $results;
    }
    
    /**
     * Check 9: Font size inline nhỏ hơn 16px
     */
    private function perform_font_size_check($html, $site_url, $value) {
        $results = [];
        $small_font_elements = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        $texts = $xpath->query('//*[(self::p or self::h1 or self::h2 or self::h3 or self::span) and contains(@style, "font-size")]');
        foreach ($texts as $el) {
            // Bỏ qua nếu thẻ nằm trong SVG
            $parent = $el->parentNode;
            while ($parent) {
                if ($parent->nodeName === 'svg') {
                    continue 2;
                }
                $parent = $parent->parentNode;
            }
            
            if (preg_match('/font-size:\s*(\d+)px/i', $el->getAttribute('style'), $m)) {
                if ((int)$m[1] < 16) {
                    $small_font_elements[] = [
                        'html' => $doc->saveHTML($el),
                        'font_size' => $m[1] . 'px',
                        'text' => mb_substr(trim($el->textContent), 0, 100) . '...',
                        'tag' => $el->nodeName
                    ];
                }
            }
        }
        
        $results['small_font_count'] = count($small_font_elements);
        $results['has_valid_font_sizes'] = count($small_font_elements) === 0;
        
        // Chỉ trả small_font_elements khi có lỗi
        if (count($small_font_elements) > 0) {
            $results['small_font_elements'] = $small_font_elements;
        }
        
        return $results;
    }
    
    /**
     * Check 10: Trùng ID (loại trừ IDs trong mac-menu và SVG)
     */
    private function perform_duplicate_id_check($html, $site_url, $value) {
        $results = [];
        $ids = [];
        $duplicate_ids = [];
        $duplicate_elements = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        foreach ($xpath->query('//*[@id]') as $el) {
            $id = $el->getAttribute('id');
            
            // Loại trừ IDs trong SVG
            $parent = $el->parentNode;
            $is_in_svg = false;
            while ($parent) {
                if ($parent->nodeName === 'svg') {
                    $is_in_svg = true;
                    break;
                }
                $parent = $parent->parentNode;
            }
            if ($is_in_svg) {
                continue; // Skip IDs trong SVG
            }
            
            // Loại trừ IDs trong mac-menu widget
            $classes = $el->getAttribute('class');
            if (strpos($classes, 'mac-menu') !== false || 
                strpos($classes, 'module_mac_menu') !== false) {
                continue; // Skip IDs trong mac-menu
            }
            
            // Check duplicate
            if (isset($ids[$id])) {
                if (!in_array($id, $duplicate_ids)) {
                    $duplicate_ids[] = $id;
                }
                $duplicate_elements[] = [
                    'id' => $id,
                    'tag' => $el->nodeName,
                    'html' => $doc->saveHTML($el)
                ];
            } else {
                $ids[$id] = $el;
            }
        }
        
        // Add the first occurrence of duplicate IDs
        foreach ($duplicate_ids as $dup_id) {
            if (isset($ids[$dup_id])) {
                array_unshift($duplicate_elements, [
                    'id' => $dup_id,
                    'tag' => $ids[$dup_id]->nodeName,
                    'html' => $doc->saveHTML($ids[$dup_id])
                ]);
            }
        }
        
        $results['duplicate_ids_count'] = count($duplicate_ids);
        $results['has_no_duplicate_ids'] = count($duplicate_ids) === 0;
        
        // Chỉ trả duplicate_ids và duplicate_elements khi có lỗi
        if (count($duplicate_ids) > 0) {
            $results['duplicate_ids'] = $duplicate_ids;
            $results['duplicate_elements'] = $duplicate_elements;
        }
        
        return $results;
    }
    
    /**
     * Check 11: Thẻ header/footer chỉ có 1
     */
    private function perform_header_footer_check($html, $site_url, $value) {
        $results = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        
        $header_count = $doc->getElementsByTagName('header')->length;
        $footer_count = $doc->getElementsByTagName('footer')->length;
        
        $results['header_count'] = $header_count;
        $results['footer_count'] = $footer_count;
        $results['has_one_header'] = $header_count === 1;
        $results['has_one_footer'] = $footer_count === 1;
        
        return $results;
    }
    
    /**
     * Check 12: Kiểm tra thẻ h1 có nằm trên cùng không
     */
    private function perform_h1_position_check($html, $site_url, $value) {
        $results = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        $headings = [];
        foreach ($xpath->query('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]') as $el) {
            $headings[] = strtolower($el->nodeName);
        }
        
        $h1_first = (isset($headings[0]) && $headings[0] === 'h1');
        
        $results['h1_is_first'] = $h1_first;
        $results['first_heading'] = isset($headings[0]) ? $headings[0] : null;
        
        return $results;
    }
    
    /**
     * Check 13: Kiểm tra khoảng trắng đầu/cuối ở tel: và elementor-icon-list-text
     */
    private function perform_tel_whitespace_check($html, $site_url, $value) {
        $results = [];
        $tel_href_errors = [];
        $tel_text_errors = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // 13.1 Kiểm tra href tel:
        foreach ($xpath->query('//*[@href]') as $el) {
            $href = $el->getAttribute('href');
            if (stripos($href, 'tel:') === 0) {
                $tel_value = substr($href, 4);
                if (preg_match('/^(\s|%20)|((\s|%20)+)$/', $tel_value)) {
                    $tel_href_errors[] = [
                        'href' => $href,
                        'html' => $doc->saveHTML($el),
                        'error_type' => 'href_whitespace'
                    ];
                }
            }
        }
        
        // 13.2 Kiểm tra class elementor-icon-list-text
        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " elementor-icon-list-text ")]') as $el) {
            $text = trim($el->textContent, "\r\n");
            if (preg_match('/\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/', $text)) {
                if (preg_match('/^\s|\s$/', $el->textContent)) {
                    $tel_text_errors[] = [
                        'text' => $el->textContent,
                        'html' => $doc->saveHTML($el),
                        'error_type' => 'text_whitespace'
                    ];
                }
            }
        }
        
        $results['tel_href_errors_count'] = count($tel_href_errors);
        $results['tel_text_errors_count'] = count($tel_text_errors);
        $results['has_no_tel_whitespace'] = count($tel_href_errors) === 0 && count($tel_text_errors) === 0;
        
        // Chỉ trả tel_href_errors và tel_text_errors khi có lỗi
        if (count($tel_href_errors) > 0) {
            $results['tel_href_errors'] = $tel_href_errors;
        }
        if (count($tel_text_errors) > 0) {
            $results['tel_text_errors'] = $tel_text_errors;
        }
        
        return $results;
    }
    
    /**
     * Extract book now button information from DOM element
     */
    private function extract_book_now_button_info_from_dom($element, $site_url) {
        $button_info = [
            'html' => $element->ownerDocument->saveHTML($element),
            'text' => '',
            'link' => '',
            'target' => '',
            'link_status' => '',
            'correct' => false
        ];
        
        // Extract text content
        $button_info['text'] = trim($element->textContent);
        
        // Extract link (href attribute) - chỉ cho thẻ <a>
        if ($element->nodeName === 'a') {
            $href = $element->getAttribute('href');
            $target = $element->getAttribute('target');
            $button_info['target'] = $target;
            
            if (!empty($href)) {
                $button_info['link'] = $href;
                
                // Check if link starts with tel: or https://lk.macmarketing.us/
                $link_valid = (strpos($button_info['link'], 'tel:') === 0 || 
                               strpos($button_info['link'], 'https://lk.macmarketing.us/') === 0);
                
                // Check if target="_blank"
                $target_valid = ($target === '_blank');
                
                if ($link_valid && $target_valid) {
                    $button_info['link_status'] = 'Link đúng và open new tab';
                    $button_info['correct'] = true;
                } elseif ($link_valid && !$target_valid) {
                    $button_info['link_status'] = 'Link đúng nhưng thiếu target="_blank"';
                    $button_info['correct'] = false;
                } elseif (!$link_valid && $target_valid) {
                    $button_info['link_status'] = 'Link không đúng format (có target="_blank")';
                    $button_info['correct'] = false;
                } else {
                    $button_info['link_status'] = 'Link không đúng format và thiếu target="_blank"';
                    $button_info['correct'] = false;
                }
            } else {
                $button_info['link_status'] = 'Không có link';
                $button_info['correct'] = false;
            }
        } else {
            // Nếu không phải thẻ <a>, tìm thẻ <a> con
            $link_element = $element->getElementsByTagName('a')->item(0);
            if ($link_element) {
                $href = $link_element->getAttribute('href');
                $target = $link_element->getAttribute('target');
                $button_info['target'] = $target;
                
                if (!empty($href)) {
                    $button_info['link'] = $href;
                    
                    // Check if link starts with tel: or https://lk.macmarketing.us/
                    $link_valid = (strpos($button_info['link'], 'tel:') === 0 || 
                                   strpos($button_info['link'], 'https://lk.macmarketing.us/') === 0);
                    
                    // Check if target="_blank"
                    $target_valid = ($target === '_blank');
                    
                    if ($link_valid && $target_valid) {
                        $button_info['link_status'] = 'Link đúng và open new tab';
                        $button_info['correct'] = true;
                    } elseif ($link_valid && !$target_valid) {
                        $button_info['link_status'] = 'Link đúng nhưng thiếu target="_blank"';
                        $button_info['correct'] = false;
                    } elseif (!$link_valid && $target_valid) {
                        $button_info['link_status'] = 'Link không đúng format (có target="_blank")';
                        $button_info['correct'] = false;
                    } else {
                        $button_info['link_status'] = 'Link không đúng format và thiếu target="_blank"';
                        $button_info['correct'] = false;
                    }
                } else {
                    $button_info['link_status'] = 'Không có link';
                    $button_info['correct'] = false;
                }
            } else {
                $button_info['link_status'] = 'Không có thẻ <a>';
                $button_info['correct'] = false;
            }
        }
        
        return $button_info;
    }
    
    /**
     * Extract book now button information (legacy function)
     */
    private function extract_book_now_button_info($button_element, $site_url) {
        $button_info = [
            'html' => trim($button_element),
            'text' => '',
            'link' => '',
            'link_status' => '',
            'correct' => false
        ];
        
        // Extract text content
        $text_pattern = '/>([^<]+)</';
        preg_match($text_pattern, $button_element, $text_matches);
        if (!empty($text_matches[1])) {
            $button_info['text'] = trim(strip_tags($text_matches[1]));
        }
        
        // Kiểm tra xem element có chứa text "Book Now" hoặc "Book appointment" không
        $has_book_text = (stripos($button_element, 'Book Now') !== false || 
                         stripos($button_element, 'Book appointment') !== false);
        
        if (!$has_book_text) {
            // Nếu không có text "Book Now" hoặc "Book appointment", bỏ qua
            return null;
        }
        
        // Extract link (href attribute) - chỉ tìm trong element chứa text Book Now/Book appointment
        $link_pattern = '/href=["\']([^"\']*)["\']/i';
        preg_match($link_pattern, $button_element, $link_matches);
        if (!empty($link_matches[1])) {
            $button_info['link'] = $link_matches[1];
            
            // Check if link starts with tel: or https://lk.macmarketing.us/
            if (strpos($button_info['link'], 'tel:') === 0 || 
                strpos($button_info['link'], 'https://lk.macmarketing.us/') === 0) {
                $button_info['link_status'] = 'Link đúng';
                $button_info['correct'] = true;
            } else {
                $button_info['link_status'] = 'Link không đúng format';
                $button_info['correct'] = false;
            }
        } else {
            $button_info['link_status'] = 'Không có link';
            $button_info['correct'] = false;
        }
        
        return $button_info;
    }
    
    /**
     * Find parent Elementor element containing an img tag
     */
    private function find_parent_elementor_element($html, $img_tag) {
        // Get the position of the img tag in the HTML
        $img_position = strpos($html, $img_tag);
        if ($img_position === false) {
            return null;
        }
        
        // Find the closest opening div with elementor-element class before this img
        $before_img = substr($html, 0, $img_position);
        
        // Look for the most recent opening div with elementor-element class
        $elementor_pattern = '/<div[^>]*class="[^"]*elementor-element[^"]*"[^>]*>/i';
        preg_match_all($elementor_pattern, $before_img, $matches, PREG_OFFSET_CAPTURE);
        
        if (empty($matches[0])) {
            return null;
        }
        
        // Get the last (most recent) match
        $last_match = end($matches[0]);
        $elementor_start = $last_match[1];
        $elementor_tag = $last_match[0];
        
        // Extract data-id to find the closing tag
        preg_match('/data-id="([^"]*)"/', $elementor_tag, $data_id_matches);
        if (empty($data_id_matches[1])) {
            return null;
        }
        
        $data_id = $data_id_matches[1];
        
        // Find the closing div for this elementor element
        $after_img = substr($html, $img_position);
        $closing_pattern = '/<\/div>/i';
        $div_count = 1; // Start with 1 for the opening div
        $closing_position = 0;
        
        preg_match_all($closing_pattern, $after_img, $closing_matches, PREG_OFFSET_CAPTURE);
        
        foreach ($closing_matches[0] as $match) {
            $div_count--;
            if ($div_count === 0) {
                $closing_position = $match[1] + strlen($match[0]);
                break;
            }
        }
        
        if ($closing_position > 0) {
            $full_element = substr($html, $elementor_start, $img_position + $closing_position - $elementor_start);
            return $full_element;
        }
        
        return null;
    }

    /**
     * Check if an image is likely to be a logo
     */
    private function is_likely_logo($logo_info, $img_tag) {
        // Check for logo-related classes
        $logo_classes = ['logo', 'brand', 'site-logo', 'header-logo', 'footer-logo'];
        foreach ($logo_classes as $class) {
            if (stripos($img_tag, 'class="' . $class . '"') !== false || 
                stripos($img_tag, 'class="' . $class . ' ') !== false) {
                return true;
            }
        }
        
        // Check for logo-related alt text
        $alt = strtolower($logo_info['alt']);
        $logo_alt_keywords = ['logo', 'brand', 'site', 'company'];
        foreach ($logo_alt_keywords as $keyword) {
            if (strpos($alt, $keyword) !== false) {
                return true;
            }
        }
        
        // Check image dimensions (logos are usually smaller than content images)
        preg_match('/width=["\']?(\d+)["\']?/', $img_tag, $width_matches);
        preg_match('/height=["\']?(\d+)["\']?/', $img_tag, $height_matches);
        
        if (isset($width_matches[1]) && isset($height_matches[1])) {
            $width = intval($width_matches[1]);
            $height = intval($height_matches[1]);
            
            // Logo is usually smaller than 300px in either dimension
            if ($width < 300 || $height < 300) {
                return true;
            }
        }
        
        // If no specific indicators, assume it's a logo if it's in header/footer
        return true;
    }
    
    /**
     * Extract logo information from Elementor element
     */
    private function extract_logo_info_from_elementor($elementor_element, $img_tag, $site_url) {
        // Extract img src
        preg_match('/src=["\']([^"\']*)["\']/', $img_tag, $src_matches);
        $src = isset($src_matches[1]) ? $src_matches[1] : '';
        
        // Extract alt text
        preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_matches);
        $alt = isset($alt_matches[1]) ? $alt_matches[1] : '';
        
        // Extract data-id from elementor element
        preg_match('/data-id=["\']([^"\']*)["\']/', $elementor_element, $data_id_matches);
        $data_id = isset($data_id_matches[1]) ? $data_id_matches[1] : '';
        
        // Look for link within the elementor element (could be around img or around the whole widget)
        $link_pattern = '/<a[^>]*href=["\']([^"\']*)["\'][^>]*>.*?<\/a>/is';
        preg_match($link_pattern, $elementor_element, $link_matches);
        $link = isset($link_matches[1]) ? $link_matches[1] : '';
        
        // Check if link is correct (should be "/" or site_url)
        $correct = false;
        $link_status = '';
        
        if (empty($link)) {
            $link_status = 'Không có link';
        } elseif ($link === '/' || $link === $site_url || $link === rtrim($site_url, '/')) {
            $correct = true;
            $link_status = 'Link đúng';
        } else {
            $link_status = 'Link sai: ' . $link;
        }
        
        return [
            'html' => trim($elementor_element), // Return the full elementor element
            'img_html' => trim($img_tag), // Also include the img tag for reference
            'data_id' => $data_id, // Elementor data-id
            'src' => $src,
            'alt' => $alt,
            'link' => $link,
            'link_status' => $link_status,
            'correct' => $correct
        ];
    }

    /**
     * Extract logo information with parent context
     */
    private function extract_logo_info_with_context($parent_section, $img_tag, $site_url) {
        // Extract img src
        preg_match('/src=["\']([^"\']*)["\']/', $img_tag, $src_matches);
        $src = isset($src_matches[1]) ? $src_matches[1] : '';
        
        // Extract alt text
        preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_matches);
        $alt = isset($alt_matches[1]) ? $alt_matches[1] : '';
        
        // Look for link in parent context (header/footer section)
        $link_pattern = '/<a[^>]*href=["\']([^"\']*)["\'][^>]*>.*?<\/a>/is';
        preg_match($link_pattern, $parent_section, $link_matches);
        $link = isset($link_matches[1]) ? $link_matches[1] : '';
        
        // Check if link is correct (should be "/" or site_url)
        $correct = false;
        $link_status = '';
        
        if (empty($link)) {
            $link_status = 'Không có link';
        } elseif ($link === '/' || $link === $site_url || $link === rtrim($site_url, '/')) {
            $correct = true;
            $link_status = 'Link đúng';
        } else {
            $link_status = 'Link sai: ' . $link;
        }
        
        return [
            'html' => trim($parent_section), // Return the parent section
            'img_html' => trim($img_tag), // Also include the img tag
            'src' => $src,
            'alt' => $alt,
            'link' => $link,
            'link_status' => $link_status,
            'correct' => $correct
        ];
    }

    /**
     * Extract logo information from HTML element
     */
    private function extract_logo_info($img_tag, $site_url) {
        // Extract img src
        preg_match('/src=["\']([^"\']*)["\']/', $img_tag, $src_matches);
        $src = isset($src_matches[1]) ? $src_matches[1] : '';
        
        // Extract alt text
        preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_matches);
        $alt = isset($alt_matches[1]) ? $alt_matches[1] : '';
        
        // Check if it's wrapped in a link (look in parent context)
        $link_pattern = '/<a[^>]*href=["\']([^"\']*)["\'][^>]*>.*?<\/a>/is';
        preg_match($link_pattern, $img_tag, $link_matches);
        $link = isset($link_matches[1]) ? $link_matches[1] : '';
        
        // Check if link is correct (should be "/" or site_url)
        $correct = false;
        $link_status = '';
        
        if (empty($link)) {
            $link_status = 'Không có link';
        } elseif ($link === '/' || $link === $site_url || $link === rtrim($site_url, '/')) {
            $correct = true;
            $link_status = 'Link đúng';
        } else {
            $link_status = 'Link sai: ' . $link;
        }
        
        return [
            'html' => trim($img_tag), // Return the img tag
            'img_html' => trim($img_tag), // Same as html for consistency
            'src' => $src,
            'alt' => $alt,
            'link' => $link,
            'link_status' => $link_status,
            'correct' => $correct
        ];
    }

    /**
     * Perform default website checks
     */
    private function perform_default_checksite_checks($html, $site_url) {
        $checks = [];
        
        // Count HTML tags
        $checks['h1_count'] = $this->count_html_tags($html, 'h1');
        $checks['h2_count'] = $this->count_html_tags($html, 'h2');
        $checks['h3_count'] = $this->count_html_tags($html, 'h3');
        $checks['p_count'] = $this->count_html_tags($html, 'p');
        $checks['img_count'] = $this->count_html_tags($html, 'img');
        $checks['a_count'] = $this->count_html_tags($html, 'a');
        
        // Check for important elements
        $checks['has_title'] = $this->check_html_element($html, 'title');
        $checks['has_meta_description'] = $this->check_html_element($html, 'meta', 'name="description"');
        $checks['has_meta_keywords'] = $this->check_html_element($html, 'meta', 'name="keywords"');
        $checks['has_favicon'] = $this->check_html_element($html, 'link', 'rel="icon"');
        
        // Check for common issues
        $checks['has_privacy_policy'] = $this->check_html_element($html, 'a', 'href*="privacy-policy"');
        $checks['has_contact_form'] = $this->check_html_element($html, 'form') || $this->check_html_element($html, 'div', 'class*="contact"');
        $checks['has_social_links'] = $this->check_html_element($html, 'a', 'href*="facebook"') || 
                                     $this->check_html_element($html, 'a', 'href*="twitter"') ||
                                     $this->check_html_element($html, 'a', 'href*="instagram"');
        
        return $checks;
    }
    
    /**
     * Perform security website checks
     */
    private function perform_security_checksite_checks($html, $site_url) {
        $checks = [];
        $security_score = 100;
        
        // 1. Website Live Check
        $response = wp_remote_get($site_url, [
            'timeout' => 10,
            'user-agent' => 'MAC-Info-Manager/1.0'
        ]);
        
        if (is_wp_error($response)) {
            $checks['website_live'] = false;
            $checks['http_status'] = 0;
            $checks['error_message'] = $response->get_error_message();
            $security_score -= 50;
        } else {
            $http_status = wp_remote_retrieve_response_code($response);
            $checks['website_live'] = ($http_status === 200);
            $checks['http_status'] = $http_status;
            
            if ($http_status !== 200) {
                $security_score -= 50;
            }
            
            // Check response time
            $response_time = wp_remote_retrieve_header($response, 'x-response-time');
            if (empty($response_time)) {
                $response_time = 'N/A';
            }
            $checks['response_time'] = $response_time;
            
            // Check for redirects
            $redirect_url = wp_remote_retrieve_header($response, 'location');
            if (!empty($redirect_url)) {
                $checks['suspicious_redirects'] = true;
                $checks['redirect_to'] = $redirect_url;
                $security_score -= 25;
            } else {
                $checks['suspicious_redirects'] = false;
            }
        }
        
        // 2. Title Hijacked Check (Japanese characters)
        $title_hijacked = $this->detect_japanese_hack($html);
        $checks['title_hijacked'] = $title_hijacked;
        
        if ($title_hijacked) {
            $security_score -= 30;
            $checks['current_title'] = $this->extract_title($html);
            $checks['japanese_characters_found'] = true;
            $checks['suspicious_keywords'] = $this->find_suspicious_keywords($html);
        } else {
            $checks['current_title'] = $this->extract_title($html);
            $checks['japanese_characters_found'] = false;
        }
        
        // 3. Content Anomaly Check
        $content_anomaly = $this->detect_content_anomaly($html);
        $checks['content_anomaly'] = $content_anomaly;
        
        if ($content_anomaly) {
            $security_score -= 20;
        }
        
        // 4. Security Score
        $checks['security_score'] = max(0, $security_score);
        
        return $checks;
    }
    
    /**
     * Detect Japanese hack in content
     */
    private function detect_japanese_hack($html) {
        // Extract title
        $title = $this->extract_title($html);
        
        // Check for Japanese characters
        $japanese_patterns = [
            '/[\x{3040}-\x{309F}]/u', // Hiragana
            '/[\x{30A0}-\x{30FF}]/u', // Katakana  
            '/[\x{4E00}-\x{9FAF}]/u', // Kanji
        ];
        
        foreach ($japanese_patterns as $pattern) {
            if (preg_match($pattern, $title)) {
                return true;
            }
        }
        
        // Check for suspicious keywords
        $suspicious_keywords = [
            '無料動画', 'エロ動画', 'アダルト動画', 'AV女優',
            'オンラインカジノ', 'スロット', 'ボーナス',
            '出会い系', 'メル友', '無料登録', '無料視聴'
        ];
        
        foreach ($suspicious_keywords as $keyword) {
            if (strpos($html, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract title from HTML
     */
    private function extract_title($html) {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        return '';
    }
    
    /**
     * Find suspicious keywords in content
     */
    private function find_suspicious_keywords($html) {
        $suspicious_keywords = [
            '無料動画', 'エロ動画', 'アダルト動画', 'AV女優',
            'オンラインカジノ', 'スロット', 'ボーナス',
            '出会い系', 'メル友', '無料登録', '無料視聴'
        ];
        
        $found_keywords = [];
        foreach ($suspicious_keywords as $keyword) {
            if (strpos($html, $keyword) !== false) {
                $found_keywords[] = $keyword;
            }
        }
        
        return $found_keywords;
    }
    
    /**
     * Detect content anomaly
     */
    private function detect_content_anomaly($html) {
        // Check for suspicious iframes
        if (preg_match('/<iframe[^>]*src=["\'](?!https?:\/\/(www\.)?(youtube|vimeo|google)\.com)/i', $html)) {
            return true;
        }
        
        // Check for suspicious scripts
        if (preg_match('/<script[^>]*src=["\'](?!https?:\/\/(www\.)?(google|facebook|twitter)\.com)/i', $html)) {
            return true;
        }
        
        // Check for suspicious meta tags
        if (preg_match('/<meta[^>]*name=["\'](keywords|description)["\'][^>]*content=["\'][^"\']*(無料|エロ|アダルト|カジノ|出会い)/i', $html)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get security message based on score
     */
    private function get_security_message($score) {
        if ($score >= 90) {
            return 'Website an toàn';
        } elseif ($score >= 70) {
            return 'Website có một số vấn đề nhỏ';
        } elseif ($score >= 50) {
            return 'Website có vấn đề cần chú ý';
        } else {
            return 'Website có nguy cơ bảo mật cao';
        }
    }
    
    /**
     * Perform custom website checks based on value parameter
     */
    private function perform_custom_checksite_checks($html, $site_url, $value) {
        $checks = [];
        
        if (is_array($value)) {
            foreach ($value as $check_type => $check_value) {
                switch ($check_type) {
                    case 'count_tags':
                        if (is_array($check_value)) {
                            foreach ($check_value as $tag) {
                                $checks['count_' . $tag] = $this->count_html_tags($html, $tag);
                            }
                        }
                        break;
                        
                    case 'check_elements':
                        if (is_array($check_value)) {
                            foreach ($check_value as $element) {
                                $checks['has_' . $element] = $this->check_html_element($html, $element);
                            }
                        }
                        break;
                        
                    case 'check_links':
                        if (is_array($check_value)) {
                            foreach ($check_value as $link_pattern) {
                                $checks['has_link_' . str_replace(['*', '/', '?', '=', '&'], '_', $link_pattern)] = 
                                    $this->check_html_element($html, 'a', 'href*="' . $link_pattern . '"');
                            }
                        }
                        break;
                        
                    default:
                        $checks[$check_type] = $this->check_html_element($html, $check_type);
                }
            }
        } else {
            // Single check
            $checks['custom_check'] = $this->check_html_element($html, $value);
        }
        
        return $checks;
    }
    
    /**
     * Check if HTML element exists
     */
    private function check_html_element($html, $tag, $attribute = '') {
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
}

new Mac_Info_Manager();
