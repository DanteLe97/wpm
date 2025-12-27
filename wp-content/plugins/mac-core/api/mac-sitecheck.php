<?php
/**
 * MAC Sitecheck - Handles all types of sitecheck
 */
if (!defined('ABSPATH')) exit;

class Mac_Sitecheck {
    private $html_cache = [];
    private static $instance = null;
    public function __construct() {
        // Private constructor to prevent direct instantiation
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function handle_sitecheck($item) {
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
                    $page_slug = '';
                }
                $site_url = rtrim($site_url, '/') . '/' . $page_slug;
            } else {
                // Default to home page if no page specified
                $site_url = rtrim($site_url, '/') . '/';
            }
            
            if (empty($site_url)) {
                return [
                    'success' => false,
                    'type' => 'sitecheck',
                    'message' => 'Unable to get website URL'
                ];
            }
            
            // Fetch website content (with caching)
            $html = $this->get_cached_html($site_url);
            
            if ($html === false) {
                return [
                    'success' => false,
                    'type' => 'sitecheck',
                    'message' => 'Unable to access website or website returned empty content'
                ];
            }
            
            // Process different types of checks
            $results = [];
            
            // Check if this is a security check
            if (isset($value['check_type']) && $value['check_type'] === 'security_status') {
                $results = [];
            } elseif (isset($value['check_type']) && $value['check_type'] === 'logo_links') {
                $results = $this->perform_logo_links_check($html, $site_url, $value);
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
                $results = [];
            } else {
                // Custom checks based on value parameter - but skip if already handled
                if (!in_array($value['check_type'] ?? '', ['logo_links', 'book_now_button', 'button_links', 'social_links', 'business_hours', 'h1_count', 'contrast', 'image_size', 'font_size', 'duplicate_id', 'header_footer', 'h1_position', 'tel_whitespace', 'other_buttons_target', 'icon_list_inline', 'copyright_year', 'mac_marketing_link', 'privacy_link', 'privacy_page_content', 'url_hash', 'review_links'])) {
                    $results = [];
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
                    $overall_message = 'Website is safe';
                } else {
                    $overall_message = 'Website has security issues: ' . implode(', ', $security_issues);
                }
            } elseif (isset($value['check_type']) && $value['check_type'] === 'logo_links') {
                // logo_links is already handled in perform_single_sitecheck, skip processing here
                $overall_result = false;
                $overall_message = 'Logo links check handled elsewhere';
            } elseif (isset($value['check_type']) && $value['check_type'] === 'book_now_button') {
                $overall_result = isset($results['has_correct_button']) ? $results['has_correct_button'] : false;
                
                // Determine the actual page being checked
                $actual_page = '';
                if (isset($value['page']) && !empty($value['page'])) {
                    $page_slug = sanitize_text_field($value['page']);
                    if (!empty($page_slug) && $page_slug !== '/') {
                        $actual_page = $page_slug;
                    }
                }
                
                if ($overall_result) {
                    $overall_message = 'All Book Now buttons have correct links (' . ($results['correct_links'] ?? 0) . '/' . ($results['button_found'] ?? 0) . ') on page ' . $actual_page;
                } else {
                    if (($results['button_found'] ?? 0) === 0) {
                        $overall_message = 'Book Now button not found on page ' . $actual_page;
                    } else {
                        $overall_message = 'Book Now buttons have missing or incorrect links (' . ($results['incorrect_links'] ?? 0) . '/' . ($results['button_found'] ?? 0) . ') on page ' . $actual_page;
                    }
                }
            } elseif (isset($value['check_type']) && $value['check_type'] === 'button_links') {
                $overall_result = isset($results['has_valid_links']) ? $results['has_valid_links'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $overall_message = $overall_result ? 
                    'All buttons/links have valid href on page ' . $actual_page : 
                    'There are ' . ($results['invalid_count'] ?? 0) . ' buttons/links without href or href is # on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'social_links') {
                $overall_result = isset($results['has_valid_social_links']) ? $results['has_valid_social_links'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : 'home';
                $overall_message = $overall_result ? 
                    'All social media links are valid on page ' . $actual_page : 
                    'There are ' . ($results['invalid_count'] ?? 0) . ' invalid social media links on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'business_hours') {
                $overall_result = isset($results['has_business_hours']) ? $results['has_business_hours'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $overall_message = $overall_result ? 
                    'Found "Business Hours" phrase on page ' . $actual_page : 
                    'Business Hours phrase not found on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'h1_count') {
                $overall_result = isset($results['has_correct_h1_count']) ? $results['has_correct_h1_count'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $h1_count = $results['h1_count'] ?? 0;
                $overall_message = $overall_result ? 
                    'Has exactly 1 H1 tag on page ' . $actual_page : 
                    'H1 tag count: ' . $h1_count . ' on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'contrast') {
                $overall_result = !isset($results['has_contrast_issue']) || !$results['has_contrast_issue'];
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $overall_message = $overall_result ? 
                    'No white text on white background on page ' . $actual_page : 
                    'Has white text on white background on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'image_size') {
                $overall_result = isset($results['has_valid_image_sizes']) ? $results['has_valid_image_sizes'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $count = $results['too_big_images_count'] ?? 0;
                $overall_message = $overall_result ? 
                    'No images exceeding 1MB on page ' . $actual_page : 
                    'There are ' . $count . ' images exceeding 1MB on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'font_size') {
                $overall_result = isset($results['has_valid_font_sizes']) ? $results['has_valid_font_sizes'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $count = $results['small_font_count'] ?? 0;
                $overall_message = $overall_result ? 
                    'All inline font-size >= 16px on page ' . $actual_page : 
                    'There are ' . $count . ' elements with font-size < 16px on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'duplicate_id') {
                $overall_result = isset($results['has_no_duplicate_ids']) ? $results['has_no_duplicate_ids'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $count = $results['duplicate_ids_count'] ?? 0;
                $overall_message = $overall_result ? 
                    'No duplicate IDs on page ' . $actual_page : 
                    'There are ' . $count . ' duplicate IDs on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'header_footer') {
                $has_one_header = isset($results['has_one_header']) ? $results['has_one_header'] : false;
                $has_one_footer = isset($results['has_one_footer']) ? $results['has_one_footer'] : false;
                $overall_result = $has_one_header && $has_one_footer;
                $actual_page = isset($value['page']) ? $value['page'] : '';
                
                if ($overall_result) {
                    $overall_message = 'Has exactly 1 header and 1 footer on page ' . $actual_page;
                } else {
                    $header_count = $results['header_count'] ?? 0;
                    $footer_count = $results['footer_count'] ?? 0;
                    $overall_message = 'Header: ' . $header_count . ', Footer: ' . $footer_count . ' on page ' . $actual_page;
                }
            } elseif (isset($value['check_type']) && $value['check_type'] === 'h1_position') {
                $overall_result = isset($results['h1_is_first']) ? $results['h1_is_first'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $first_heading = $results['first_heading'] ?? 'none';
                $overall_message = $overall_result ? 
                    'H1 tag is at the top on page ' . $actual_page : 
                    'First heading tag is: ' . $first_heading . ' on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'tel_whitespace') {
                $overall_result = isset($results['has_no_tel_whitespace']) ? $results['has_no_tel_whitespace'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $href_errors = $results['tel_href_errors_count'] ?? 0;
                $text_errors = $results['tel_text_errors_count'] ?? 0;
                
                if ($overall_result) {
                    $overall_message = 'No whitespace in tel: and phone number on page ' . $actual_page;
                } else {
                    $overall_message = 'There are ' . $href_errors . ' tel: href and ' . $text_errors . ' phone text with whitespace on page ' . $actual_page;
                }
            } elseif (isset($value['check_type']) && $value['check_type'] === 'other_buttons_target') {
                $overall_result = isset($results['has_valid_targets']) ? $results['has_valid_targets'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $count = $results['invalid_count'] ?? 0;
                $overall_message = $overall_result ? 
                    'All regular buttons do not open new tab on page ' . $actual_page : 
                    'There are ' . $count . ' regular buttons opening new tab on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'icon_list_inline') {
                $overall_result = isset($results['has_inline_setting']) ? $results['has_inline_setting'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $count = $results['invalid_count'] ?? 0;
                $overall_message = $overall_result ? 
                    'All icon lists are set inline on page ' . $actual_page : 
                    'There are ' . $count . ' icon lists not set inline on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'copyright_year') {
                $overall_result = isset($results['has_correct_year']) ? $results['has_correct_year'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $year = $results['current_year'] ?? date('Y');
                $overall_message = $overall_result ? 
                    'Footer has copyright year ' . $year . ' on page ' . $actual_page : 
                    'Footer missing copyright year ' . $year . ' on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'mac_marketing_link') {
                $overall_result = isset($results['link_correct']) ? $results['link_correct'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : 'home';
                $has_link = $results['has_mac_link'] ?? false;
                if ($overall_result) {
                    $overall_message = 'Footer has correct "by Mac Marketing" link on page ' . $actual_page;
                } elseif ($has_link) {
                    $overall_message = 'Footer has "Mac Marketing" link but href is incorrect on page ' . $actual_page;
                } else {
                    $overall_message = 'Footer missing "by Mac Marketing" link on page ' . $actual_page;
                }
            } elseif (isset($value['check_type']) && $value['check_type'] === 'privacy_link') {
                $overall_result = isset($results['has_privacy_link']) ? $results['has_privacy_link'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $overall_message = $overall_result ? 
                    'Footer has Privacy Policy link on page ' . $actual_page : 
                    'Footer missing Privacy Policy link on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'privacy_page_content') {
                $overall_result = !isset($results['has_mac_usa_one']) || !$results['has_mac_usa_one'];
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $errors = $results['errors'] ?? [];
                $overall_message = $overall_result ? 
                    'Privacy Policy page does not contain "Mac USA One" on page ' . $actual_page : 
                    'Privacy Policy page contains "Mac USA One": ' . implode(', ', $errors) . ' on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'url_hash') {
                $overall_result = !isset($results['has_invalid_hash']) || !$results['has_invalid_hash'];
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $hashes = $results['invalid_hashes'] ?? [];
                $overall_message = $overall_result ? 
                    'URL does not contain #happy or #unhappy on page ' . $actual_page : 
                    'URL contains invalid hash: ' . implode(', ', $hashes) . ' on page ' . $actual_page;
            } elseif (isset($value['check_type']) && $value['check_type'] === 'review_links') {
                $overall_result = isset($results['has_valid_review_links']) ? $results['has_valid_review_links'] : false;
                $actual_page = isset($value['page']) ? $value['page'] : '';
                $count = $results['invalid_count'] ?? 0;
                $overall_message = $overall_result ? 
                    'All review links have correct format on page ' . $actual_page : 
                    'There are ' . $count . ' review links with incorrect format on page ' . $actual_page;
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
                'message' => 'Error checking website: ' . $e->getMessage()
            ];
        }
    }
    /**
     * Handle individual sitecheck request
     */
    public function handle_individual_sitecheck($value, $name) {
        $check_type = $value['check_type'];
        $page = isset($value['page']) ? $value['page'] : '';
        
        // Get site URL
        $site_url = get_site_url();
        if (isset($value['page']) && !empty($value['page'])) {
            $page_slug = sanitize_text_field($value['page']);
            if (empty($page_slug) || $page_slug === '/') {
                $page_slug = 'home';
            }
            $site_url = rtrim($site_url, '/') . '/' . $page_slug;
        } else {
            $site_url = rtrim($site_url, '/') . '/';
        }
        
        if (empty($site_url)) {
            return [
                'success' => false,
                'type' => 'sitecheck',
                'message' => 'Unable to get website URL'
            ];
        }
        
        // Fetch website content
        $html = $this->get_cached_html($site_url);
        
        if ($html === false) {
            return [
                'success' => false,
                'type' => 'sitecheck',
                'message' => 'Unable to access website or website returned empty content'
            ];
        }
        
        // Perform the check
        $raw_results = $this->perform_check($check_type, $html, $site_url, $value);
        
        // Wrap result
        return $this->wrap_sitecheck_result($raw_results, $check_type, $page, $site_url, $name);
    }
    
    /**
     * Handle batch sitecheck request
     */
    private function handle_batch_sitecheck($value, $item) {
        $checks = [];
        $page = '';
        
        if (isset($value['checks']) && is_array($value['checks'])) {
            $checks = $value['checks'];
            $page = isset($value['page']) ? $value['page'] : '';
        } elseif (is_array($value) && isset($value[0]) && is_string($value[0])) {
            $checks = $value;
        }
        
        if (empty($checks)) {
            return [
                'success' => false,
                'type' => 'sitecheck',
                'message' => 'No checks provided'
            ];
        }
        
        // Get site URL
        $site_url = get_site_url();
        if (isset($value['page']) && !empty($value['page'])) {
            $page_slug = sanitize_text_field($value['page']);
            if (empty($page_slug) || $page_slug === '/') {
                $page_slug = '';
            }
            $site_url = rtrim($site_url, '/') . '/' . $page_slug;
        } else {
            $site_url = rtrim($site_url, '/') . '/';
        }
        
        if (empty($site_url)) {
            return [
                'success' => false,
                'type' => 'sitecheck',
                'message' => 'Unable to get website URL'
            ];
        }
        
        // Fetch website content
        $html = $this->get_cached_html($site_url);
        
        if ($html === false) {
            return [
                'success' => false,
                'type' => 'sitecheck',
                'message' => 'Unable to access website or website returned empty content'
            ];
        }
        
        // Perform all checks
        $results = [];
        foreach ($checks as $index => $check_type) {
            $raw_results = $this->perform_check($check_type, $html, $site_url, ['check_type' => $check_type, 'page' => $page]);
            $wrapped_result = $this->wrap_sitecheck_result($raw_results, $check_type, $page, $site_url, "check_" . ($index + 1) . "_{$check_type}_{$page}");
            $results[] = $wrapped_result;
        }
        
        return [
            'success' => true,
            'type' => 'sitecheck',
            'results' => $results,
            'total' => count($results),
            'site_url' => $site_url
        ];
    }
    
    /**
     * Perform specific check based on type
     */
    private function perform_check($check_type, $html, $site_url, $value) {
        switch ($check_type) {
            case 'logo_links':
                return $this->perform_logo_links_check($html, $site_url, $value);
            case 'book_now_button':
                return $this->perform_book_now_button_check($html, $site_url, $value);
            case 'button_links':
                return $this->perform_button_links_check($html, $site_url, $value);
            case 'social_links':
                return $this->perform_social_links_check($html, $site_url, $value);
            case 'business_hours':
                return $this->perform_business_hours_check($html, $site_url, $value);
            case 'h1_count':
                return $this->perform_h1_count_check($html, $site_url, $value);
            case 'contrast':
                return $this->perform_contrast_check($html, $site_url, $value);
            case 'image_size':
                return $this->perform_image_size_check($html, $site_url, $value);
            case 'font_size':
                return $this->perform_font_size_check($html, $site_url, $value);
            case 'duplicate_id':
                return $this->perform_duplicate_id_check($html, $site_url, $value);
            case 'header_footer':
                return $this->perform_header_footer_check($html, $site_url, $value);
            case 'h1_position':
                return $this->perform_h1_position_check($html, $site_url, $value);
            case 'tel_whitespace':
                return $this->perform_tel_whitespace_check($html, $site_url, $value);
            case 'other_buttons_target':
                return $this->perform_other_buttons_target_check($html, $site_url, $value);
            case 'icon_list_inline':
                return $this->perform_icon_list_inline_check($html, $site_url, $value);
            case 'copyright_year':
                return $this->perform_copyright_year_check($html, $site_url, $value);
            case 'mac_marketing_link':
                return $this->perform_mac_marketing_link_check($html, $site_url, $value);
            case 'privacy_link':
                return $this->perform_privacy_link_check($html, $site_url, $value);
            case 'privacy_page_content':
                return $this->perform_privacy_page_content_check($html, $site_url, $value);
            case 'url_hash':
                return $this->perform_url_hash_check($html, $site_url, $value);
            case 'review_links':
                return $this->perform_review_links_check($html, $site_url, $value);
            default:
                return [];
        }
    }
    
    /**
     * Wrap raw results into standard response format
     */
    private function wrap_sitecheck_result($raw_results, $check_type, $page, $site_url, $name = null) {
        if (!is_array($raw_results)) {
            return [
                'success' => true,
                'type' => 'sitecheck',
                'name' => $name ?: 'website_check',
                'result' => false,
                'message' => "Check type not supported: {$check_type}",
                'site_url' => $site_url,
                'details' => []
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
                    $message = "All logos have correct links ({$correct_links}/{$logo_found}) on page {$page}";
                } else {
                    $message = "Logos have missing or incorrect links ({$incorrect_links}/{$logo_found}) on page {$page}";
                }
                break;
            case 'book_now_button':
                $result = isset($raw_results['has_correct_button']) ? $raw_results['has_correct_button'] : false;
                $button_found = $raw_results['button_found'] ?? 0;
                
                if ($result) {
                    $message = "All Book Now buttons have correct links ({$button_found}/{$button_found}) on page {$page}";
                } else {
                    if ($button_found === 0) {
                        $message = "Book Now button not found on page {$page}";
                    } else {
                        $message = "Book Now buttons have missing or incorrect links on page {$page}";
                    }
                }
                break;
            case 'button_links':
                $result = isset($raw_results['has_valid_links']) ? $raw_results['has_valid_links'] : false;
                $total_buttons = $raw_results['total_buttons'] ?? 0;
                $invalid_count = $raw_results['invalid_count'] ?? 0;
                
                if ($result) {
                    $message = "All buttons/links have valid href on page {$page}";
                } else {
                    $message = "There are {$invalid_count} invalid buttons/links out of {$total_buttons} total on page {$page}";
                }
                break;
            case 'social_links':
                $result = isset($raw_results['has_valid_social_links']) ? $raw_results['has_valid_social_links'] : false;
                $invalid_count = $raw_results['invalid_count'] ?? 0;
                
                if ($result) {
                    $message = "All social media links are valid on page {$page}";
                } else {
                    $message = "There are {$invalid_count} invalid social media links on page {$page}";
                }
                break;
            case 'business_hours':
                $result = isset($raw_results['has_business_hours']) ? $raw_results['has_business_hours'] : false;
                
                if ($result) {
                    $message = "Found \"Business Hours\" phrase on page {$page}";
                } else {
                    $message = "Business Hours phrase not found on page {$page}";
                }
                break;
            case 'h1_count':
                $result = isset($raw_results['has_correct_h1_count']) ? $raw_results['has_correct_h1_count'] : false;
                $h1_count = $raw_results['h1_count'] ?? 0;
                
                if ($result) {
                    $message = "H1 tag count is correct (1) on page {$page}";
                } else {
                    $message = "H1 tag count: {$h1_count} on page {$page}";
                }
                break;
            case 'contrast':
                $result = isset($raw_results['has_contrast_issue']) ? !$raw_results['has_contrast_issue'] : false;
                
                if ($result) {
                    $message = "No white text on white background on page {$page}";
                } else {
                    $message = "Has white text on white background on page {$page}";
                }
                break;
            case 'image_size':
                $result = isset($raw_results['has_valid_image_sizes']) ? $raw_results['has_valid_image_sizes'] : false;
                $too_big_count = $raw_results['too_big_images_count'] ?? 0;
                
                if ($result) {
                    $message = "All images have valid size on page {$page}";
                } else {
                    $message = "There are {$too_big_count} images exceeding 1MB on page {$page}";
                }
                break;
            case 'font_size':
                $result = isset($raw_results['has_valid_font_sizes']) ? $raw_results['has_valid_font_sizes'] : false;
                $small_font_count = $raw_results['small_font_count'] ?? 0;
                
                if ($result) {
                    $message = "All inline font-size >= 16px on page {$page}";
                } else {
                    $message = "There are {$small_font_count} inline font-size < 16px on page {$page}";
                }
                break;
            case 'duplicate_id':
                $result = isset($raw_results['has_no_duplicate_ids']) ? $raw_results['has_no_duplicate_ids'] : false;
                $duplicate_count = $raw_results['duplicate_ids_count'] ?? 0;
                
                if ($result) {
                    $message = "No duplicate IDs on page {$page}";
                } else {
                    $message = "There are {$duplicate_count} duplicate IDs on page {$page}";
                }
                break;
            case 'header_footer':
                $result = (isset($raw_results['has_one_header']) && $raw_results['has_one_header']) && 
                         (isset($raw_results['has_one_footer']) && $raw_results['has_one_footer']);
                $header_count = $raw_results['header_count'] ?? 0;
                $footer_count = $raw_results['footer_count'] ?? 0;
                
                if ($result) {
                    $message = "Has exactly 1 header and 1 footer on page {$page}";
                } else {
                    $message = "Has {$header_count} headers and {$footer_count} footers on page {$page}";
                }
                break;
            case 'h1_position':
                $result = isset($raw_results['h1_is_first']) ? $raw_results['h1_is_first'] : false;
                $first_heading = $raw_results['first_heading'] ?? 'unknown';
                
                if ($result) {
                    $message = "H1 tag is at the top on page {$page}";
                } else {
                    $message = "First tag is {$first_heading} instead of H1 on page {$page}";
                }
                break;
            case 'tel_whitespace':
                $result = isset($raw_results['has_no_tel_whitespace']) ? $raw_results['has_no_tel_whitespace'] : false;
                $tel_href_errors = $raw_results['tel_href_errors_count'] ?? 0;
                $tel_text_errors = $raw_results['tel_text_errors_count'] ?? 0;
                
                if ($result) {
                    $message = "No whitespace in tel: and phone number on page {$page}";
                } else {
                    $message = "There are {$tel_href_errors} href errors and {$tel_text_errors} text errors in tel: on page {$page}";
                }
                break;
            case 'other_buttons_target':
                $result = isset($raw_results['has_valid_targets']) ? $raw_results['has_valid_targets'] : false;
                $total_links = $raw_results['total_links_with_target'] ?? 0;
                $invalid_count = $raw_results['invalid_count'] ?? 0;
                
                if ($result) {
                    $message = "All regular buttons do not open new tab on page {$page}";
                } else {
                    $message = "There are {$invalid_count} regular buttons opening new tab out of {$total_links} total on page {$page}";
                }
                break;
            case 'icon_list_inline':
                $result = isset($raw_results['has_inline_setting']) ? $raw_results['has_inline_setting'] : false;
                $total_icon_lists = $raw_results['total_icon_lists'] ?? 0;
                $invalid_count = $raw_results['invalid_count'] ?? 0;
                
                if ($result) {
                    $message = "All icon lists are set inline on page {$page}";
                } else {
                    $message = "There are {$invalid_count} icon lists not set inline out of {$total_icon_lists} total on page {$page}";
                }
                break;
            case 'copyright_year':
                $result = isset($raw_results['has_correct_year']) ? $raw_results['has_correct_year'] : false;
                $current_year = $raw_results['current_year'] ?? date('Y');
                
                if ($result) {
                    $message = "Footer has copyright year {$current_year} on page {$page}";
                } else {
                    $message = "Footer missing copyright year {$current_year} on page {$page}";
                }
                break;
            case 'mac_marketing_link':
                $result = isset($raw_results['has_mac_link']) && isset($raw_results['link_correct']) ? 
                         ($raw_results['has_mac_link'] && $raw_results['link_correct']) : false;
                
                if ($result) {
                    $message = "Footer has correct \"by Mac Marketing\" link on page {$page}";
                } else {
                    $message = "Footer missing \"by Mac Marketing\" link or link is incorrect on page {$page}";
                }
                break;
            case 'privacy_link':
                $result = isset($raw_results['has_privacy_link']) ? $raw_results['has_privacy_link'] : false;
                
                if ($result) {
                    $message = "Footer has Privacy Policy link on page {$page}";
                } else {
                    $message = "Footer missing Privacy Policy link on page {$page}";
                }
                break;
            case 'privacy_page_content':
                $result = isset($raw_results['has_mac_usa_one']) ? !$raw_results['has_mac_usa_one'] : false;
                
                if ($result) {
                    $message = "Privacy Policy page does not contain \"Mac USA One\" on page {$page}";
                } else {
                    $message = "Privacy Policy page contains \"Mac USA One\" on page {$page}";
                }
                break;
            case 'url_hash':
                $result = isset($raw_results['has_invalid_hash']) ? !$raw_results['has_invalid_hash'] : false;
                
                if ($result) {
                    $message = "URL does not contain #happy or #unhappy on page {$page}";
                } else {
                    $message = "URL contains #happy or #unhappy on page {$page}";
                }
                break;
            case 'review_links':
                $result = isset($raw_results['has_valid_review_links']) ? $raw_results['has_valid_review_links'] : false;
                $invalid_count = $raw_results['invalid_count'] ?? 0;
                
                if ($result) {
                    $message = "All review links have correct format on page {$page}";
                } else {
                    $message = "There are {$invalid_count} review links with incorrect format on page {$page}";
                }
                break;
            default:
                $result = false;
                $message = "Check type not supported: {$check_type}";
        }
        
        return [
            'success' => true,
            'type' => 'sitecheck',
            'name' => $name ?: 'website_check',
            'result' => $result,
            'message' => $message,
            'site_url' => $site_url,
            'details' => $raw_results
        ];
    }
    
    /**
     * Get cached HTML content
     */
    private function get_cached_html($site_url) {
        // Check if HTML already cached
        if (isset($this->html_cache[$site_url])) {
            // Store in global for CSS variable resolution
            $GLOBALS['mac_sitecheck_html_content'] = $this->html_cache[$site_url];
            $GLOBALS['mac_sitecheck_current_url'] = $site_url;
            return $this->html_cache[$site_url];
        }
        
        // Fetch HTML content
        $response = wp_remote_get($site_url, [
            'timeout' => 30,
            'user-agent' => 'MAC-Info-Manager/1.0'
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $html = wp_remote_retrieve_body($response);
        
        if (empty($html)) {
            return false;
        }
        
        // Cache the HTML
        $this->html_cache[$site_url] = $html;
        
        // Store in global for CSS variable resolution
        $GLOBALS['mac_sitecheck_html_content'] = $html;
        $GLOBALS['mac_sitecheck_current_url'] = $site_url;
        
        return $html;
    }
    
    // Include all the perform_*_check methods here
    // (I'll add them in the next step)
    
      /**
     * Perform logo links check
     */
    private function perform_logo_links_check($html, $site_url, $value) {
        $results = [];
        $logo_elements = [];
        $correct_links = 0;
        $incorrect_links = 0;
    
        preg_match_all('/<img[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE);
    
        foreach ($matches[0] as [$img_tag, $img_pos]) {
            // Determine header/footer area
            $before = substr($html, 0, $img_pos);
            $is_in_header = substr_count($before, '<header') > substr_count($before, '</header>');
            $is_in_footer = substr_count($before, '<footer') > substr_count($before, '</footer>');
            if (!$is_in_header && !$is_in_footer) continue;
    
            $has_parent_link = false;
            $href = '';
    
            if (preg_match_all('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>/', $before, $a_matches, PREG_OFFSET_CAPTURE)) {
                foreach (array_reverse($a_matches[0], true) as $i => [$a_tag, $a_start]) {
                    $html_after_a = substr($html, $a_start + strlen($a_tag), $img_pos - ($a_start + strlen($a_tag)));
                    if (substr_count($html_after_a, '</a>') == 0) {
                        $has_parent_link = true;
                        $href = $a_matches[1][$i][0];
                        break;
                    }
                }
            }
    
            $is_correct = false;
            if ($has_parent_link) {
                $is_correct = (
                    $href === '/' ||
                    $href === $site_url ||
                    $href === rtrim($site_url, '/') . '/' ||
                    strpos($href, $site_url . '/') === 0
                );
            }
    
            $logo_elements[] = [
                'img_tag' => $img_tag,
                'href' => $href,
                'correct' => $is_correct,
                'has_link' => $has_parent_link
            ];
    
            if ($is_correct) $correct_links++;
            else $incorrect_links++;
        }
    
        $logo_found = count($logo_elements);
        $has_correct_logo = $incorrect_links === 0;
        $page_name = $value['page'] ?? 'home';
    
        $message = $has_correct_logo
            ? "All logos have correct links ({$correct_links}/{$logo_found}) on page {$page_name}"
            : "Logos have missing or incorrect links ({$incorrect_links}/{$logo_found}) on page {$page_name}";
    
        return [
            'logo_found' => $logo_found,
            'correct_links' => $correct_links,
            'incorrect_links' => $incorrect_links,
            'has_correct_logo' => $has_correct_logo,
            'logo_elements' => $logo_elements,
            'message' => $message,
        ];
    }
    
    
    /**
     * Check 2: Link button "Book Now"
     * - Priority: class btn-booking
     * - If not found, search for text "Book Now" or "Book Appointment"
     * - Valid link if starts with tel: or https://lk.macmarketing.us/
     */
    private function perform_book_now_button_check($html, $site_url, $value) {
        $results = [];
        $button_elements = [];
        $correct_links = 0;
        $incorrect_links = 0;

        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);

        // 1️⃣ Priority: find class btn-booking
        $btn_booking_elements = $xpath->query('//*[contains(@class, "btn-booking")]');

        if ($btn_booking_elements->length > 0) {
            foreach ($btn_booking_elements as $element) {
                $button_info = $this->extract_book_now_button_info_from_dom($element, $site_url);
                if ($button_info) {
                    $button_elements[] = $button_info;
                    if ($button_info['correct']) $correct_links++;
                    else $incorrect_links++;
                }
            }
        } else {
            // 2️⃣ If no btn-booking, find <a> containing text "Book Now" or "Book Appointment"
            // Search both direct text and text in child elements
            $book_buttons = $xpath->query(
                "//a[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'book now') 
                or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'book appointment')
                or .//*[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'book now')]
                or .//*[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'book appointment')]]"
            );

            foreach ($book_buttons as $btn) {
                $button_info = $this->extract_book_now_button_info_from_dom($btn, $site_url);
                if ($button_info) {
                    $button_elements[] = $button_info;
                    if ($button_info['correct']) $correct_links++;
                    else $incorrect_links++;
                }
            }
        }

        $page_name = $value['page'] ?? 'home';

        $results['button_found'] = count($button_elements);
        $results['has_correct_button'] = $correct_links > 0;
        $results['page'] = $page_name;

        if (count($button_elements) === 0) {
            $results['message'] = "❌ Book Now button not found on page {$page_name}.";
        } elseif ($correct_links > 0) {
            $results['message'] = "✅ At least one valid Book Now button found on page {$page_name}.";
        } else {
            $results['message'] = "⚠️ Book Now button found but link is incorrect on page {$page_name}.";
        }

        // Add button elements to details for debugging
        if (count($button_elements) > 0) {
            $results['invalid_elements'] = $button_elements;
        }

        return $results;
    }

    
    /**
     * Check 3: Links for other buttons (must not be empty or #)
     * Exclude: toggle links, zipcode links
     */
    private function perform_button_links_check($html, $site_url, $value) {
        $results = [];
        $invalid_elements = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // Find all <a> and <button> tags
        $buttons = $xpath->query('//a | //button');
        
        foreach ($buttons as $btn) {
            $href = $btn->getAttribute('href') ?: '';
            $class = $btn->getAttribute('class') ?: '';
            
            // Skip if has class 'elementor-toggle-title'
            if (strpos($class, 'elementor-toggle-title') !== false) {
                continue;
            }
            
            // Skip if has class 'zipcode' (nail salon zipcode)
            if (strpos($class, 'zipcode') !== false) {
                continue;
            }
            
            // Check if href is empty or #
            if ($href === '' || $href === '#') {
                
                //'html' => $doc->saveHTML($btn),
                $invalid_elements[] = [
                    'href' => $href,
                    'text' => trim($btn->textContent)
                ];
            }
        }
        
        $results['total_buttons'] = $buttons->length;
        $results['invalid_count'] = count($invalid_elements);
        $results['has_valid_links'] = count($invalid_elements) === 0;
        
        // Only return invalid_elements when there are errors
        if (count($invalid_elements) > 0) {
            $results['invalid_elements'] = $invalid_elements;
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
            $class = $a->getAttribute('class');
            $text = trim($a->textContent);
            
            // Check if this is a social link by class or text content
            $is_social = false;
            $social_type = '';
            
            // Check by class (elementor social icons)
            if (strpos($class, 'elementor-social-icon') !== false) {
                if (strpos($class, 'facebook') !== false) {
                    $is_social = true;
                    $social_type = 'facebook';
                } elseif (strpos($class, 'instagram') !== false) {
                    $is_social = true;
                    $social_type = 'instagram';
                } elseif (strpos($class, 'twitter') !== false) {
                    $is_social = true;
                    $social_type = 'twitter';
                } elseif (strpos($class, 'linkedin') !== false) {
                    $is_social = true;
                    $social_type = 'linkedin';
                } elseif (strpos($class, 'yelp') !== false) {
                    $is_social = true;
                    $social_type = 'yelp';
                }
            }
            
            // Check by href containing social domains
            if (!$is_social) {
                foreach ($socials as $social) {
                    if (strpos($href, $social) !== false) {
                        $is_social = true;
                        $social_type = str_replace('.com', '', $social);
                        break;
                    }
                }
            }
            
            // Check by text content (fallback)
            if (!$is_social) {
                $text_lower = strtolower($text);
                if (in_array($text_lower, ['facebook', 'instagram', 'twitter', 'linkedin', 'yelp'])) {
                    $is_social = true;
                    $social_type = $text_lower;
                }
            }
            
            if ($is_social) {
                // Check if href is valid
                if (empty($href) || $href === '#' || strpos($href, 'https://') !== 0) {
                    $invalid_social_links[] = [
                        'html' => $doc->saveHTML($a),
                        'href' => $href,
                        'social_type' => $social_type,
                        'text' => $text,
                        'class' => $class
                    ];
                }
            }
        }
        
        $results['invalid_count'] = count($invalid_social_links);
        $results['has_valid_social_links'] = count($invalid_social_links) === 0;
        
        // Only return invalid_elements when there are errors
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
     * Check 6: H1 tag
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
     * Check 7: Low contrast
     */
    private function perform_contrast_check($html, $site_url, $value) {
        $results = [];
        $contrast_issues = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // Find all elements with style
        $elements = $xpath->query('//*[@style]');
        
        foreach ($elements as $el) {
            $style = $el->getAttribute('style');
            $text = trim($el->textContent);
            
            // Skip empty elements
            if (empty($text)) continue;
            
            // Extract color and background-color from style
            $color = '';
            $background = '';
            
            if (preg_match('/color:\s*([^;]+)/i', $style, $matches)) {
                $color = trim($matches[1]);
            }
            if (preg_match('/background(-color)?:\s*([^;]+)/i', $style, $matches)) {
                $background = trim($matches[2]);
            }
            
            // Check for contrast issues
            if (!empty($color) && !empty($background)) {
                $contrast_ratio = $this->calculate_contrast_ratio($color, $background);
                
                // WCAG guidelines: 4.5:1 for normal text, 3:1 for large text
                $min_ratio = 4.5;
                
                if ($contrast_ratio < $min_ratio) {
                    $issue_type = $contrast_ratio < 1.5 ? 'Very poor contrast' : 'Poor contrast';
                    
                    $contrast_issues[] = [
                        'html' => $doc->saveHTML($el),
                        'text' => $text,
                        'color' => $color,
                        'background' => $background,
                        'contrast_ratio' => round($contrast_ratio, 2),
                        'min_required' => $min_ratio,
                        'issue_type' => $issue_type
                    ];
                }
            }
        }
        
        $results['has_contrast_issue'] = count($contrast_issues) > 0;
        $results['contrast_issues_count'] = count($contrast_issues);
        
        if (count($contrast_issues) > 0) {
            $results['contrast_issues'] = $contrast_issues;
        }
        
        return $results;
    }
    
    /**
     * Calculate contrast ratio between two colors
     * Returns ratio between 1 (no contrast) and 21 (maximum contrast)
     */
    private function calculate_contrast_ratio($color1, $color2) {
        $rgb1 = $this->hex_to_rgb($color1);
        $rgb2 = $this->hex_to_rgb($color2);
        
        if (!$rgb1 || !$rgb2) {
            return 1; // Assume poor contrast if can't parse
        }
        
        $luminance1 = $this->get_luminance($rgb1);
        $luminance2 = $this->get_luminance($rgb2);
        
        $lighter = max($luminance1, $luminance2);
        $darker = min($luminance1, $luminance2);
        
        if ($darker == 0) return 21; // Maximum contrast
        
        return ($lighter + 0.05) / ($darker + 0.05);
    }
    
    /**
     * Convert hex color to RGB array
     */
    private function hex_to_rgb($hex) {
        // Remove # if present
        $hex = ltrim($hex, '#');
        
        // Handle 3-digit hex
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        // Handle named colors
        $named_colors = [
            'white' => 'ffffff',
            'black' => '000000',
            'red' => 'ff0000',
            'green' => '008000',
            'blue' => '0000ff',
            'yellow' => 'ffff00',
            'cyan' => '00ffff',
            'magenta' => 'ff00ff',
            'gray' => '808080',
            'grey' => '808080'
        ];
        
        if (isset($named_colors[strtolower($hex)])) {
            $hex = $named_colors[strtolower($hex)];
        }
        
        if (strlen($hex) != 6) return null;
        
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
    
    /**
     * Calculate relative luminance of RGB color
     */
    private function get_luminance($rgb) {
        $r = $rgb['r'] / 255;
        $g = $rgb['g'] / 255;
        $b = $rgb['b'] / 255;
        
        // Apply gamma correction
        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
        
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
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
        
        // Only return too_big_images when there are errors
        if (count($too_big_imgs) > 0) {
            $results['too_big_images'] = $too_big_imgs;
        }
        
        return $results;
    }
    
    /**
     * Check 9: Font size smaller than 16px
     * - Check inline styles
     * - Fetch and parse CSS files (Elementor, theme, etc.)
     */
    private function perform_font_size_check($html, $site_url, $value) {
        $results = [];
        $small_font_elements = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // ============================================================
        // 1. CHECK INLINE FONT-SIZE from HTML elements
        // ============================================================
        $text_elements = $xpath->query('//*[(self::p or self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6 or self::span or self::div or self::a or self::li) and @style]');
        
        foreach ($text_elements as $el) {
            // Skip if element is inside SVG
            $parent = $el->parentNode;
            while ($parent) {
                if ($parent->nodeName === 'svg') {
                    continue 2;
                }
                $parent = $parent->parentNode;
            }
            
            $style = $el->getAttribute('style');
            if (preg_match('/font-size\s*:\s*(\d+)px/i', $style, $matches)) {
                $font_size = (int)$matches[1];
                if ($font_size < 16) {
                $small_font_elements[] = [
                    'html' => $doc->saveHTML($el),
                    'font_size' => $font_size . 'px',
                        'text' => trim($el->textContent),
                    'tag' => $el->nodeName,
                    'class' => $el->getAttribute('class'),
                        'source' => 'inline'
                    ];
                }
            }
        }
        
        // ============================================================
        // 2. FETCH ONLY ELEMENTOR CSS FILE (post-{ID}.css)
        // ============================================================
        $css_links = [];
        
        // Try to get page ID from value or extract from URL
        $page_id = null;
        if (isset($value['page_id']) && is_numeric($value['page_id'])) {
            $page_id = $value['page_id'];
        } else {
            // Try to extract ID from page slug
            if (isset($value['page']) && !empty($value['page'])) {
                $page_slug = sanitize_title($value['page']);
                $post = get_page_by_path($page_slug);
                if ($post) {
                    $page_id = $post->ID;
                }
            }
        }
        
        // ONLY fetch Elementor CSS file by page ID
        if ($page_id) {
            $wp_upload_dir = wp_upload_dir();
            $elementor_css_path = "{$wp_upload_dir['baseurl']}/elementor/css/post-{$page_id}.css";
            $css_links[] = $elementor_css_path;
        }
        
        $all_css = '';
        foreach ($css_links as $css_url) {
            $css_response = wp_remote_get($css_url, [
                'timeout' => 10,
                'sslverify' => false
            ]);
            if (!is_wp_error($css_response)) {
                $css_body = wp_remote_retrieve_body($css_response);
                if (!empty($css_body)) {
                    $all_css .= "\n\n/* --- $css_url --- */\n" . $css_body;
                }
            }
        }
        
        // ============================================================
        // 3. PARSE CSS FONT-SIZE AND FIND ELEMENTS IN HTML
        // ============================================================
        if (!empty($all_css)) {
            // SKIP @media queries - remove all @media blocks
            $css_without_media = '';
            $lines = explode("\n", $all_css);
            $in_media = false;
            $brace_count = 0;
            
            foreach ($lines as $line) {
                if (preg_match('/@media/', $line)) {
                    $in_media = true;
                    $brace_count = 0;
                }
                
                if ($in_media) {
                    $brace_count += substr_count($line, '{');
                    $brace_count -= substr_count($line, '}');
                    
                    if ($brace_count <= 0 && strpos($line, '}') !== false) {
                        $in_media = false;
                    }
                } else {
                    $css_without_media .= $line . "\n";
                }
            }
            
            // Find all CSS rules with font-size < 16px
            preg_match_all('/([^\{\}]+)\s*\{[^\}]*font-size\s*:\s*([^;]+);/i', $css_without_media, $css_matches, PREG_SET_ORDER);
            
            foreach ($css_matches as $match) {
                $selector = trim($match[1]);
                $font_size = trim($match[2]);
                
                // Only check px values
                if (preg_match('/(\d+)px/i', $font_size, $px_match)) {
                    $size = (int)$px_match[1];
                    if ($size < 16) {
                        // Extract tag and class from CSS selector for better display
                        $tag = '';
                        $class = '';
                        
                        // Try to extract tag name (look for last tag in selector)
                        if (preg_match('/\b(h1|h2|h3|h4|h5|h6|p|span|div|a|li)\b/i', $selector, $tag_match)) {
                            $tag = $tag_match[1];
                        }
                        
                        // CONVERT CSS SELECTOR TO XPATH QUERY
                        // Example: ".elementor-38 .elementor-element.elementor-element-96dbd5e .elementor-heading-title"
                        // → "//*[contains(@class, 'elementor-38')]//*[contains(@class, 'elementor-heading-title')]"
                        $xpath_query = $this->css_selector_to_xpath($selector);
                        
                        // Query elements using XPath
                        $found_elements = [];
                        if (!empty($xpath_query)) {
                            $elements = $xpath->query($xpath_query);
                            
                            if ($elements->length > 0) {
                                foreach ($elements as $el) {
                                    if (!empty(trim($el->textContent))) {
                                        $found_elements[] = $el;
                                    }
                                }
                            }
                        }
                        
                        // Extract class for display (last class in selector)
                        $class = '';
                        if (preg_match_all('/\.([a-z0-9_-]+)(?=\s*\{|$)/i', $selector, $class_matches)) {
                            $class = end($class_matches[1]);
                        }
                        
                        // If we found elements in HTML, use real data (only first element)
                        if (!empty($found_elements)) {
                            $el = $found_elements[0];
                $small_font_elements[] = [
                    'html' => $doc->saveHTML($el),
                                'font_size' => $font_size,
                                'text' => mb_substr(trim($el->textContent), 0, 100),
                    'tag' => $el->nodeName,
                    'class' => $el->getAttribute('class'),
                                'source' => 'css'
                            ];
                        } else {
                            // No matching element found, use placeholder
                            $small_font_elements[] = [
                                'html' => $tag ? '<' . $tag . ($class ? ' class="' . $class . '"' : '') . '>...</' . $tag . '>' : '',
                                'font_size' => $font_size,
                                'text' => '...',
                                'tag' => $tag,
                                'class' => $class,
                                'source' => 'css'
                            ];
                        }
                    }
                }
            }
        }
        
        // ============================================================
        // 4. RETURN RESULTS
        // ============================================================
        $results['small_font_count'] = count($small_font_elements);
        $results['has_valid_font_sizes'] = count($small_font_elements) === 0;
        
        if (count($small_font_elements) > 0) {
            $results['small_font_elements'] = array_slice($small_font_elements, 0, 10); // Limit to first 10
        }
        
        // Debug info
        $results['debug'] = [
            'page_id' => $page_id,
            'css_files_fetched' => count($css_links),
            'css_files_urls' => $css_links,
            'css_content_length' => strlen($all_css)
        ];
        
        return $results;
    }
    
    /**
     * Get font size of an element from various sources
     */
    private function get_element_font_size($element) {
        // ONLY check inline style on the element itself - most reliable
        $style = $element->getAttribute('style');
        
        // Check direct font-size in inline style
        if ($style && preg_match('/font-size:\s*(\d+(?:\.\d+)?)px/i', $style, $matches)) {
            return (float)$matches[1];
        }
        
        // Check CSS variables in inline style
        if ($style && preg_match('/font-size:\s*var\(--([^\)]+)\)/i', $style, $matches)) {
            $css_var = trim($matches[1]);
            $font_size = $this->resolve_css_variable($css_var, $element);
            if ($font_size !== null) {
                return $font_size;
            }
        }
        
        // Check parent inline styles (CSS inheritance)
        $parent = $element->parentNode;
        while ($parent && $parent->nodeType === XML_ELEMENT_NODE && $parent->nodeName !== 'body') {
            $parent_style = $parent->getAttribute('style');
            
            if ($parent_style && preg_match('/font-size:\s*(\d+(?:\.\d+)?)px/i', $parent_style, $matches)) {
                return (float)$matches[1];
            }
            
            if ($parent_style && preg_match('/font-size:\s*var\(--([^\)]+)\)/i', $parent_style, $matches)) {
                $css_var = trim($matches[1]);
                $font_size = $this->resolve_css_variable($css_var, $parent);
                if ($font_size !== null) {
                    return $font_size;
                }
            }
            
            $parent = $parent->parentNode;
        }
        
        // DON'T try to parse CSS from external files - it's not reliable
        // CSS inject by JavaScript is not available in HTML
        return null;
    }
    
    /**
     * Find font size from Elementor heading CSS
     */
    private function find_elementor_heading_font_size($element) {
        $html = isset($GLOBALS['mac_sitecheck_html_content']) ? $GLOBALS['mac_sitecheck_html_content'] : '';
        
        if (empty($html)) {
            return null;
        }
        
        // Get element classes and tag
        $class = $element->getAttribute('class');
        $tag = strtolower($element->nodeName);
        
        // Search for any CSS variable related to this heading level
        $h_tag_map = [
            'h1' => 'h1',
            'h2' => 'h2', 
            'h3' => 'h3',
            'h4' => 'h4',
            'h5' => 'h5',
            'h6' => 'h6'
        ];
        
        if (isset($h_tag_map[$tag])) {
            $h_tag = $h_tag_map[$tag];
            
            // Look for various CSS variable patterns in HTML
            $patterns = [
                '--e-global-typography-' . $h_tag . '-font-size\s*:\s*(\d+(?:\.\d+)?)\s*px',
                '--e-global-typography-primary-font-size\s*:\s*(\d+(?:\.\d+)?)\s*px',
                'font-size:\s*var\(--e-global-typography-' . $h_tag . '-font-size\)',
                '\.elementor-kit-\d+\s*\{[^}]*--e-global-typography-' . $h_tag . '-font-size\s*:\s*(\d+(?:\.\d+)?)\s*px',
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match('/' . $pattern . '/i', $html, $matches)) {
                    // Extract font-size if we have a number
                    if (isset($matches[1]) && is_numeric($matches[1])) {
                        return (float)$matches[1];
                    }
                    // If it's a CSS variable reference, try to resolve it
                    // For now, return a conservative estimate
                    if (strpos($matches[0], 'var') !== false) {
                        // This is a CSS variable reference - try to find the actual value
                        $css_var = preg_replace('/.*--/', '--', $matches[0]);
                        if (preg_match('/' . preg_quote($css_var, '/') . '\s*:\s*(\d+(?:\.\d+)?)\s*px/i', $html, $var_matches)) {
                            return (float)$var_matches[1];
                        }
                    }
                }
            }
        }
        
        // Search for elementor-kit CSS in HTML more broadly
        if (preg_match('/\.elementor-kit-(\d+)\s*\{([^}]+)\}/is', $html, $kit_matches)) {
            $kit_css = $kit_matches[2];
            // Look for font-size in this kit
            if (preg_match('/font-size:\s*(\d+(?:\.\d+)?)\s*px/i', $kit_css, $font_matches)) {
                return (float)$font_matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Fetch Elementor CSS files from HTML
     */
    private function fetch_elementor_css_files($html, $site_url) {
        $css_content = '';
        $urls = [];
        
        // Find all CSS links that might be Elementor-related
        if (preg_match_all('/<link[^>]*rel=["\']stylesheet["\'][^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $link_matches, PREG_SET_ORDER)) {
            foreach ($link_matches as $match) {
                $full_tag = $match[0];
                $css_url = $match[1];
                
                // Check if this might be Elementor CSS
                if (strpos($css_url, 'elementor') !== false || 
                    strpos($css_url, 'post-') !== false || 
                    strpos($css_url, 'global') !== false) {
                    
                    // Convert to absolute URL
                    $absolute_url = $this->make_absolute_url($css_url, $html, $site_url);
                    
                    // Fetch CSS file
                    $css_file_content = $this->fetch_css_file($absolute_url);
                    if (!empty($css_file_content)) {
                        $css_content .= $css_file_content . "\n";
                        $urls[] = $absolute_url;
                    }
                }
            }
        }
        
        return [
            'content' => $css_content,
            'urls' => $urls
        ];
    }
    
    /**
     * Parse CSS from HTML and apply to element to get font-size
     */
    private function parse_css_for_element($element, $html, $site_url = '') {
        // Get element classes and other attributes
        $class = $element->getAttribute('class');
        $tag = strtolower($element->nodeName);
        $id = $element->getAttribute('id');
        
        if (empty($class)) {
            return null;
        }
        
        // Extract ALL CSS from HTML
        $css_content = '';
        
        // 1. Get CSS from <style> tags
        if (preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $style_matches)) {
            foreach ($style_matches[1] as $style_content) {
                $css_content .= $style_content . "\n";
            }
        }
        
        // 2. Fetch Elementor CSS files if this is an Elementor element
        if (strpos($class, 'elementor') !== false) {
            $elementor_css = $this->fetch_elementor_css($html, $site_url);
            if (!empty($elementor_css)) {
                $css_content .= $elementor_css . "\n";
            }
        }
        
        if (empty($css_content)) {
            return null;
        }
        
        // Strategy: Parse ALL CSS rules and find ones that might apply to this element
        // Then check if that rule has font-size < 16px
        
        $classes = explode(' ', $class);
        $classes = array_filter(array_map('trim', $classes)); // Clean up
        
        // For each of the element's classes, find CSS rules that match
        foreach ($classes as $single_class) {
            if (empty($single_class)) continue;
            
            // Look for this class in CSS selectors
            // Example: .elementor-heading-title { font-size: 10px; }
            // Or: .elementor-38 .elementor-heading-title { font-size: 10px; }
            
            // Pattern 1: Class appears in selector (anywhere before {)
            $pattern = '/' . preg_quote($single_class, '/') . '[^\{]*\{[^}]*font-size:\s*(\d+(?:\.\d+)?)\s*px/i';
            
            if (preg_match_all($pattern, $css_content, $matches)) {
                // Get all font-size values for this class
                foreach ($matches[1] as $font_size_value) {
                    return (float)$font_size_value;
                }
            }
            
            // Pattern 2: More specific - class is in a multi-class selector
            // Example: .elementor-38 .elementor-heading-title
            // Check if any of element's classes appear in the same CSS rule
            $pattern2 = '/\.' . preg_quote($single_class, '/') . '(\s|\.)[^\{]*\{[^}]*font-size:\s*(\d+(?:\.\d+)?)\s*px/i';
            if (preg_match_all($pattern2, $css_content, $matches)) {
                // Check if other classes from this element also appear in the same rule
                $rule_index = 0;
                foreach ($matches[0] as $full_match) {
                    // Count how many of our classes appear in this rule
                    $matched_classes = 0;
                    foreach ($classes as $check_class) {
                        if (strpos($full_match, $check_class) !== false) {
                            $matched_classes++;
                        }
                    }
                    
                    // If at least one class matches, apply the font-size
                    if ($matched_classes > 0) {
                        return (float)$matches[2][$rule_index];
                    }
                    $rule_index++;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Fetch Elementor CSS files from HTML
     */
    private function fetch_elementor_css($html, $site_url = '') {
        $all_css = '';
        
        // 1. Find all Elementor CSS file links
        // Pattern: <link rel="stylesheet" href="...elementor...css">
        if (preg_match_all('/<link[^>]*href=["\']([^"\']*elementor[^"\']*\.css)["\'][^>]*>/i', $html, $css_links)) {
            foreach ($css_links[1] as $css_url) {
                // Make absolute URL if needed
                $absolute_url = $this->make_absolute_url($css_url, $html, $site_url);
                
                // Fetch CSS content
                $css_content = $this->fetch_css_file($absolute_url);
                if (!empty($css_content)) {
                    $all_css .= $css_content . "\n";
                }
            }
        }
        
        return $all_css;
    }
    
    /**
     * Convert relative URL to absolute URL
     */
    private function make_absolute_url($url, $html, $base_url = '') {
        // If already absolute, return as is
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        }
        
        // Use provided base_url or extract from HTML
        if (empty($base_url)) {
            if (preg_match('/<base[^>]*href=["\']([^"\']+)["\']/i', $html, $matches)) {
                $base_url = $matches[1];
            }
        }
        
        if (empty($base_url)) {
            return $url; // Can't make absolute, return as is
        }
        
        // Combine base URL with relative URL
        $base_url = rtrim($base_url, '/');
        $url = ltrim($url, '/');
        
        return $base_url . '/' . $url;
    }
    
    /**
     * Fetch CSS file content
     */
    private function fetch_css_file($url) {
        // Use wp_remote_get to fetch CSS
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'user-agent' => 'MAC-Info-Manager/1.0'
        ]);
        
        if (is_wp_error($response)) {
            return '';
        }
        
        $css_content = wp_remote_retrieve_body($response);
        
        // Return if we got valid CSS (starts with common CSS patterns)
        if (!empty($css_content) && preg_match('/^\s*[@\w\s\.#\-\{\}]/', $css_content)) {
            return $css_content;
        }
        
        return '';
    }
    
    /**
     * Resolve CSS variable to actual value
     */
    /**
     * Convert CSS selector to XPath query
     * Example: ".elementor-38 .elementor-element.elementor-element-96dbd5e .elementor-heading-title"
     * → "//*[contains(@class, 'elementor-38')]//*[contains(@class, 'elementor-element-96dbd5e')]//*[contains(@class, 'elementor-heading-title')]"
     */
    private function css_selector_to_xpath($css_selector) {
        // Remove { and everything after it
        $selector = trim(preg_replace('/\{.*/', '', $css_selector));
        
        // Split by space to get each level
        $parts = preg_split('/\s+/', $selector);
        
        if (empty($parts)) {
            return '';
        }
        
        $xpath_parts = [];
        
        foreach ($parts as $part) {
            // Get all classes from part (may have multiple classes together like: .elementor-element.elementor-element-96dbd5e)
            if (preg_match_all('/\.([a-z0-9_-]+)/i', $part, $matches)) {
                $classes = $matches[1];
                // If there are multiple classes, get the last class (or could get all for more accurate matching)
                // But for simplicity, we get the last class of each part
                $class_name = end($classes);
                $xpath_parts[] = "//*[contains(@class, '{$class_name}')]";
            }
        }
        
        // Combine all XPath parts
        if (!empty($xpath_parts)) {
            return implode('', $xpath_parts);
        }
        
        return '';
    }
    
    private function resolve_css_variable($css_var, $element) {
        // Get the cached HTML
        $html = isset($GLOBALS['mac_sitecheck_html_content']) ? $GLOBALS['mac_sitecheck_html_content'] : '';
        
        if (empty($html)) {
            return null;
        }
        
        // Find the closest parent with elementor-kit class
        $parent = $element->parentNode;
        $kit_class = '';
        
        while ($parent && $parent->nodeType === XML_ELEMENT_NODE) {
            $parent_class = $parent->getAttribute('class');
            if (preg_match('/(elementor-kit-\d+)/', $parent_class, $matches)) {
                $kit_class = $matches[1];
                break;
            }
            $parent = $parent->parentNode;
        }
        
        // Look for CSS variable definition with -- prefix
        $full_css_var = '--' . $css_var;
        $css_var_pattern = '/' . preg_quote($full_css_var, '/') . '\s*:\s*(\d+(?:\.\d+)?)\s*px/i';
        
        // Check in <style> tags
        if (preg_match_all('/<style[^>]*>.*?<\/style>/is', $html, $style_matches)) {
            foreach ($style_matches[0] as $style_tag) {
                if (preg_match($css_var_pattern, $style_tag, $matches)) {
                    return (float)$matches[1];
                }
            }
        }
        
        // Check inline styles in HTML (search for pattern like: --e-global-typography-primary-font-size: 56px;)
        if (preg_match($css_var_pattern, $html, $matches)) {
            return (float)$matches[1];
        }
        
        // Check in inline styles for elementor-kit classes
        if (!empty($kit_class)) {
            // Look for .elementor-kit-X { --css-var: XXpx; }
            $kit_pattern = '/\.' . preg_quote($kit_class, '/') . '[^\{]*\{[^}]*' . preg_quote($full_css_var, '/') . '\s*:\s*(\d+(?:\.\d+)?)\s*px[^}]*\}/is';
            if (preg_match($kit_pattern, $html, $matches)) {
                return (float)$matches[1];
            }
        }
        
        // Common Elementor CSS variables and their defaults
        $common_vars = [
            'e-global-typography-primary-font-size' => 56,
            'e-global-typography-secondary-font-size' => 24,
            'e-global-typography-text-font-size' => 18,
            'e-global-typography-accent-font-size' => 16,
            'e-global-typography-h1-font-size' => 32,
            'e-global-typography-h2-font-size' => 24,
            'e-global-typography-h3-font-size' => 20,
            'e-global-typography-h4-font-size' => 18,
        ];
        
        if (isset($common_vars[$css_var])) {
            return $common_vars[$css_var];
        }
        
        return null;
    }
    
    /**
     * Get source of font size for debugging
     */
    private function get_font_size_source($element) {
        $style = $element->getAttribute('style');
        if (preg_match('/font-size:/', $style)) {
            return 'inline-style';
        }
        
        $class = $element->getAttribute('class');
        if (!empty($class)) {
            if (preg_match('/elementor-size-|text-|small|tiny/', $class)) {
                return 'css-class';
            }
        }
        
        $parent = $element->parentNode;
        while ($parent && $parent->nodeType === XML_ELEMENT_NODE) {
            $parent_style = $parent->getAttribute('style');
            if (preg_match('/font-size:/', $parent_style)) {
                return 'parent-style';
            }
            $parent = $parent->parentNode;
        }
        
        return 'default';
    }
    
    /**
     * Get all possible font size sources for debugging
     */
    private function get_all_font_size_sources($element) {
        $debug_info = [];
        
        // Check inline style
        $style = $element->getAttribute('style');
        $debug_info['has_inline_style'] = !empty($style);
        if ($style && preg_match('/font-size:\s*(\d+(?:\.\d+)?)px/i', $style, $matches)) {
            $debug_info['inline_font_size'] = $matches[1];
        }
        
        // Check class
        $class = $element->getAttribute('class');
        $debug_info['classes'] = $class;
        
        // Check if elementor-size-default in class
        if (strpos($class, 'elementor-size-default') !== false) {
            $debug_info['has_elementor_size_default'] = true;
            // This class doesn't specify size, defaults to Elementor typography
        }
        
        // Check parent style
        $parent = $element->parentNode;
        $debug_info['parent_style'] = null;
        if ($parent && $parent->nodeType === XML_ELEMENT_NODE) {
            $parent_style = $parent->getAttribute('style');
            $debug_info['parent_has_style'] = !empty($parent_style);
        }
        
        return $debug_info;
    }
    
    /**
     * Get debug info for font size source
     */
    private function get_font_size_debug($element, $font_size) {
        $html = isset($GLOBALS['mac_sitecheck_html_content']) ? $GLOBALS['mac_sitecheck_html_content'] : '';
        $class = $element->getAttribute('class');
        
        $debug = [];
        
        if (!empty($class)) {
            $debug['element_classes'] = $class;
            
            // Check if there's CSS for this class in HTML
            if (strpos($html, $class) !== false) {
                $debug['found_in_html'] = true;
                
                // Try to find CSS rule for this class
                if (preg_match('/elementor-heading-title[^\{]*\{[^}]*font-size:\s*(\d+(?:\.\d+)?)\s*px/i', $html, $matches)) {
                    $debug['css_font_size_in_html'] = $matches[1];
                }
            } else {
                $debug['found_in_html'] = false;
            }
        }
        
        return $debug;
    }
    
    /**
     * Check 10: Duplicate IDs (exclude IDs in mac-menu and SVG)
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
            
            // Exclude IDs in SVG
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
                continue; // Skip IDs in SVG
            }
            
            // Loại trừ IDs trong mac-menu widget
            $classes = $el->getAttribute('class');
            if (strpos($classes, 'mac-menu') !== false || 
                strpos($classes, 'module_mac_menu') !== false) {
                continue; // Skip IDs in mac-menu
            }
            
            // Check duplicate
            if (isset($ids[$id])) {
                if (!in_array($id, $duplicate_ids)) {
                    $duplicate_ids[] = $id;
                }
                //'html' => $doc->saveHTML($el)
                $duplicate_elements[] = [
                    'id' => $id,
                    'tag' => $el->nodeName
                ];
            } else {
                $ids[$id] = $el;
            }
        }
        
        // Add the first occurrence of duplicate IDs
        foreach ($duplicate_ids as $dup_id) {
            if (isset($ids[$dup_id])) {
                //'html' => $doc->saveHTML($ids[$dup_id])
                array_unshift($duplicate_elements, [
                    'id' => $dup_id,
                    'tag' => $ids[$dup_id]->nodeName
                    
                ]);
            }
        }
        
        $results['duplicate_ids_count'] = count($duplicate_ids);
        $results['has_no_duplicate_ids'] = count($duplicate_ids) === 0;
        
        // Only return duplicate_ids and duplicate_elements when there are errors
        if (count($duplicate_ids) > 0) {
            $results['duplicate_ids'] = $duplicate_ids;
            $results['duplicate_elements'] = $duplicate_elements;
        }
        
        return $results;
    }
    
    /**
     * Check 11: Header/footer tags should only have 1
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
     * Check 12: Check if h1 tag is at the top
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
     * Check 13: Check for leading/trailing whitespace in tel: and elementor-icon-list-text
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
                        'text' => trim($el->textContent),
                        'error_type' => 'href_whitespace'
                    ];
                }
            }
        }
        
        // 13.2 Check elementor-icon-list-text class
        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " elementor-icon-list-text ")]') as $el) {
            $text = trim($el->textContent, "\r\n");
            if (preg_match('/\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/', $text)) {
                if (preg_match('/^\s|\s$/', $el->textContent)) {
                    $tel_text_errors[] = [
                        'text' => $el->textContent,
                        'error_type' => 'text_whitespace'
                    ];
                }
            }
        }
        
        $results['tel_href_errors_count'] = count($tel_href_errors);
        $results['tel_text_errors_count'] = count($tel_text_errors);
        $results['has_no_tel_whitespace'] = count($tel_href_errors) === 0 && count($tel_text_errors) === 0;
        
        // Merge all errors into invalid_elements
        $all_errors = array_merge($tel_href_errors, $tel_text_errors);
        if (count($all_errors) > 0) {
            $results['invalid_elements'] = $all_errors;
        }
        
        return $results;
    }

    /**
     * Check 21: Other buttons (not Book Now) should not open new tab
     */
    private function perform_other_buttons_target_check($html, $site_url, $value) {
        $results = [];
        $invalid_buttons = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // Find all <a> tags with target="_blank"
        $links_with_target = $xpath->query('//a[@target="_blank"]');
        
        foreach ($links_with_target as $link) {
            $text = trim($link->textContent);
            $href = $link->getAttribute('href');
            $class = $link->getAttribute('class');
            
            // SKIP if inside <form>
            $parent = $link->parentNode;
            $is_in_form = false;
            while ($parent && $parent->nodeType === XML_ELEMENT_NODE) {
                if ($parent->nodeName === 'form') {
                    $is_in_form = true;
                    break;
                }
                $parent = $parent->parentNode;
            }
            if ($is_in_form) {
                continue; // Skip buttons in forms
            }
            
            // Skip Book Now buttons (class btn-booking or text contains "book")
            if (strpos($class, 'btn-booking') !== false || 
                stripos($text, 'book now') !== false || 
                stripos($text, 'book appointment') !== false) {
                continue; // Skip Book Now buttons
            }
            
            // Skip social links (allowed to open new tab)
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
            
            // Skip external links (macmarketing.us, google maps, etc)
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
            
            // If not in the above cases → Error
            //'html' => $doc->saveHTML($link),
            $invalid_buttons[] = [
                
                'text' => $text,
                'href' => $href,
                'target' => '_blank'
            ];
        }
        
        $results['total_links_with_target'] = $links_with_target->length;
        $results['invalid_count'] = count($invalid_buttons);
        $results['has_valid_targets'] = count($invalid_buttons) === 0;
        
        // Only return invalid_buttons when there are errors
        if (count($invalid_buttons) > 0) {
            $results['invalid_buttons'] = $invalid_buttons;
        }
        
        return $results;
    }
    
    /**
     * Check 26: Icon list must be set inline (elementor-list-item-link-inline)
     */
    private function perform_icon_list_inline_check($html, $site_url, $value) {
        $results = [];
        $invalid_icon_lists = [];
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        // Find all icon list widgets
        $icon_lists = $xpath->query('//div[contains(@class, "elementor-widget-icon-list")]');
        
        foreach ($icon_lists as $list) {
            $classes = $list->getAttribute('class');
            
            // Check if has class "elementor-list-item-link-inline"
            if (strpos($classes, 'elementor-list-item-link-inline') === false) {
                // No inline class → Error
                $data_id = '';
                if (preg_match('/elementor-element-([a-z0-9]+)/', $classes, $matches)) {
                    $data_id = $matches[1];
                }
                //'html' => $doc->saveHTML($list),
                $invalid_icon_lists[] = [
                    
                    'data_id' => $data_id,
                    'classes' => $classes
                ];
            }
        }
        
        $results['total_icon_lists'] = $icon_lists->length;
        $results['invalid_count'] = count($invalid_icon_lists);
        $results['has_inline_setting'] = count($invalid_icon_lists) === 0;
        
        // Only return invalid_icon_lists when there are errors
        if (count($invalid_icon_lists) > 0) {
            $results['invalid_icon_lists'] = $invalid_icon_lists;
        }
        
        return $results;
    }
    
    /**
     * Check 27: Correct copyright year (2025)
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
     * Check 28: Footer has "by Mac Marketing" link
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
            // Find link containing "Mac Marketing" or "macmarketing"
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
     * Check 29: Privacy Policy link in footer
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
            // Find link containing "/privacy-policy"
            $privacy_links = $xpath->query('.//a[contains(@href, "/privacy-policy")]', $footers[0]);
            if ($privacy_links->length > 0) {
                $has_privacy_link = true;
            }
        }
        
        $results['has_privacy_link'] = $has_privacy_link;
        
        return $results;
    }
    
    /**
     * Check 33: Privacy Policy page - should not contain "Mac USA One"
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
                $errors[] = 'H1 contains "Mac USA One"';
            }
        }
        
        // Check entire content
        if (stripos($html, 'Mac USA One') !== false) {
            $errors[] = 'Content contains "Mac USA One"';
        }
        
        $results['has_mac_usa_one'] = count($errors) > 0;
        $results['errors'] = $errors;
        
        return $results;
    }
    
    /**
     * Check 36: URL should not contain #happy or #unhappy
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
        
        // Find Google review links
        $google_links = $xpath->query('//a[contains(@href, "google.com")]');
        foreach ($google_links as $link) {
            $href = $link->getAttribute('href');
            
            // Check if it's a review link
            if (strpos($href, 'review') !== false || strpos($href, 'writereview') !== false) {
                // Validate format
                $valid = (strpos($href, 'g.page/r') !== false || 
                          strpos($href, 'search.google.com/local/writereview') !== false);
                
                if (!$valid) {
                    // 'html' => $doc->saveHTML($link),
                    $invalid_review_links[] = [
                       
                        'href' => $href,
                        'type' => 'Google Review',
                        'error' => 'Link format incorrect (must be g.page/r... or search.google.com/local/writereview?)'
                    ];
                }
            }
        }
        
        // Find Yelp review links
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
                        'error' => 'Link format incorrect (must be yelp.com/writeareview/biz)'
                    ];
                }
            }
        }
        
        $results['invalid_count'] = count($invalid_review_links);
        $results['has_valid_review_links'] = count($invalid_review_links) === 0;
        
        // Only return invalid_review_links when there are errors
        if (count($invalid_review_links) > 0) {
            $results['invalid_review_links'] = $invalid_review_links;
        }
        
        return $results;
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
            $link_status = 'No link';
        } elseif ($link === '/' || $link === $site_url || $link === rtrim($site_url, '/')) {
            $correct = true;
            $link_status = 'Link correct';
        } else {
            $link_status = 'Link incorrect: ' . $link;
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
            $link_status = 'No link';
        } elseif ($link === '/' || $link === $site_url || $link === rtrim($site_url, '/')) {
            $correct = true;
            $link_status = 'Link correct';
        } else {
            $link_status = 'Link incorrect: ' . $link;
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
     * Check if image is likely a logo
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
        
        // Check image dimensions (logos can be various sizes)
        preg_match('/width=["\']?(\d+)["\']?/', $img_tag, $width_matches);
        preg_match('/height=["\']?(\d+)["\']?/', $img_tag, $height_matches);
        
        if (isset($width_matches[1]) && isset($height_matches[1])) {
            $width = intval($width_matches[1]);
            $height = intval($height_matches[1]);
            
            // Check if it's in header/footer area (more likely to be logo)
            $is_in_header_footer = stripos($img_tag, 'header') !== false || 
                                  stripos($img_tag, 'footer') !== false ||
                                  stripos($img_tag, 'site-header') !== false ||
                                  stripos($img_tag, 'site-footer') !== false;
            
            // Logo can be various sizes, but if it's in header/footer, it's likely a logo
            if ($is_in_header_footer) {
                return true;
            }
            
            // Also check for reasonable logo dimensions (not too small, not too large)
            if (($width >= 50 && $width <= 1000) && ($height >= 30 && $height <= 800)) {
                return true;
            }
        }
        
        return false;
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
            $link_status = 'No link';
        } elseif ($link === '/' || $link === $site_url || $link === rtrim($site_url, '/')) {
            $correct = true;
            $link_status = 'Link correct';
        } else {
            $link_status = 'Link incorrect: ' . $link;
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
     * Extract book now button info from DOM element
     */
    private function extract_book_now_button_info_from_dom($element, $site_url) {
        $button_info = [
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
                    $button_info['link_status'] = 'Link correct and opens new tab';
                    $button_info['correct'] = true;
                } elseif ($link_valid && !$target_valid) {
                    $button_info['link_status'] = 'Link correct but missing target="_blank"';
                    $button_info['correct'] = false;
                } elseif (!$link_valid && $target_valid) {
                    $button_info['link_status'] = 'Link format incorrect (has target="_blank")';
                    $button_info['correct'] = false;
                } else {
                    $button_info['link_status'] = 'Link format incorrect and missing target="_blank"';
                    $button_info['correct'] = false;
                }
            } else {
                $button_info['link_status'] = 'No link';
                $button_info['correct'] = false;
            }
        } else {
            // If not an <a> tag, find child <a> tag
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
                        $button_info['link_status'] = 'Link correct and opens new tab';
                        $button_info['correct'] = true;
                    } elseif ($link_valid && !$target_valid) {
                        $button_info['link_status'] = 'Link correct but missing target="_blank"';
                        $button_info['correct'] = false;
                    } elseif (!$link_valid && $target_valid) {
                        $button_info['link_status'] = 'Link format incorrect (has target="_blank")';
                        $button_info['correct'] = false;
                    } else {
                        $button_info['link_status'] = 'Link format incorrect and missing target="_blank"';
                        $button_info['correct'] = false;
                    }
                } else {
                    $button_info['link_status'] = 'No link';
                    $button_info['correct'] = false;
                }
            } else {
                $button_info['link_status'] = 'No <a> tag found';
                $button_info['correct'] = false;
            }
        }
        
        return $button_info;
    }
}

// Initialize the class
new Mac_Sitecheck();