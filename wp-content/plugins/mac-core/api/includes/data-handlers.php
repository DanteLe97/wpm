<?php
/**
 * Data Handlers - Handles different types of data (option, plugin, post, user, updown)
 */
if (!defined('ABSPATH')) exit;

/**
 * Handle option requests with cache
 */
function mac_handle_option($name, $value = null) {
    static $option_cache = []; // Static cache trong function
    
    if (empty($name)) {
        return [
            'success' => false,
            'type' => 'option',
            'message' => 'Option name is required'
        ];
    }

    try {
        if (!isset($option_cache[$name])) {
            $option_cache[$name] = get_option($name, null);
        }
        $option_value = $option_cache[$name];
        
        if ($value === null) {
            // Just return the option value
            return [
                'success' => true,
                'type' => 'option',
                'name' => $name,
                'result' => true,
                'value' => $option_value,
                'message' => 'Option value retrieved successfully'
            ];
        }
        
        // Handle different check types
        if (is_string($value) || is_numeric($value)) {
            // Simple value check: check if option value equals the provided value
            $result = ($option_value == $value);
            return [
                'success' => true,
                'type' => 'option',
                'name' => $name,
                'result' => $result,
                'expected_value' => $value,
                'actual_value' => $option_value,
                'message' => $result ? 'Value equals expected' : 'Value does not equal expected'
            ];
        } elseif (is_array($value)) {
            $check_type = $value['check'] ?? '';
            $operator = $value['operator'] ?? '';
            $path = $value['path'] ?? '';
            $expected_value = $value['value'] ?? $value['expected'] ?? '';
            
            if (!empty($path)) {
                $nested_value = mac_get_nested_value($option_value, $path);
            } else {
                $nested_value = $option_value;
            }
            
            // Handle special cases for specific options
            // Auto-convert ID to Name/Title for options that store Post/Page IDs
            if (is_numeric($nested_value)) {
                $should_convert = false;
                $post_type = null;
                
                // Check if user explicitly requests ID to title conversion
                // Support 2 formats:
                // 1. "name_page": true or "name_post": true (recommended - most clear)
                // 2. "convert": "page" or "convert": "post" (backward compatible)
                
                $convert_flag = null;
                $post_type = null;
                
                // Check for name_page or name_post flags (most explicit and recommended)
                if (!empty($value['name_page'])) {
                    $should_convert = true;
                    $post_type = 'page';
                } elseif (!empty($value['name_post'])) {
                    $should_convert = true;
                    $post_type = 'post';
                }
                // Check for convert flag (backward compatible)
                elseif (!empty($value['convert'])) {
                    $convert_flag = $value['convert'];
                    $should_convert = true;
                    
                    // Parse convert value to determine post_type
                    if (is_string($convert_flag)) {
                        // Support "page", "post", or any custom post type
                        $post_type = $convert_flag;
                    } elseif ($convert_flag === true && isset($value['post_type'])) {
                        // convert: true with separate post_type parameter
                        $post_type = $value['post_type'];
                    }
                } else {
                    // Fallback: predefined options for backward compatibility
                    $auto_convert_options = [
                        'page_on_front' => 'page',
                        'page_for_posts' => 'page',
                    ];
                    
                    if (isset($auto_convert_options[$name])) {
                        $should_convert = true;
                        $post_type = $auto_convert_options[$name];
                    }
                }
                
                // Convert ID to post_title if needed
                if ($should_convert && $nested_value > 0) {
                    $post = get_post($nested_value);
                    
                    // Verify post type if specified
                    if ($post && (!$post_type || $post->post_type === $post_type)) {
                        $nested_value = $post->post_title;
                    }
                }
            }
            
            // Handle boolean check first
            if (is_bool($value['check'])) {
                $result = ($nested_value == $value['check']);
                $message = $result ? 'Value matches expected boolean' : 'Value does not match expected boolean';
            } else {
                switch ($check_type) {
                    case 'empty':
                        $result = empty($nested_value);
                        $message = $result ? 'Value is empty or does not exist' : 'Value is not empty';
                        break;
                    case 'not_empty':
                        $result = !empty($nested_value);
                        $message = $result ? 'Value is not empty' : 'Value is empty';
                        break;
                    case 'equals':
                        $result = $nested_value === $expected_value;
                        $message = $result ? 'Value equals expected' : 'Value does not equal expected';
                        break;
                    case 'contains':
                        $search = $value['search'] ?? $expected_value;
                        $result = is_string($nested_value) && strpos($nested_value, $search) !== false;
                        $message = $result ? 'Value contains search term' : 'Value does not contain search term';
                        break;
                    case 'sendgrid':
                        $result = ($nested_value === 'sendgrid');
                        $message = $result ? 'Mailer is SendGrid' : 'Mailer is not SendGrid';
                        break;
                    case 'gmail':
                        $result = ($nested_value === 'gmail');
                        $message = $result ? 'Mailer is Gmail' : 'Mailer is not Gmail';
                        break;
                    case 'mailgun':
                        $result = ($nested_value === 'mailgun');
                        $message = $result ? 'Mailer is Mailgun' : 'Mailer is not Mailgun';
                        break;
                    case 'smtp':
                        $result = ($nested_value === 'smtp');
                        $message = $result ? 'Mailer is SMTP' : 'Mailer is not SMTP';
                        break;
                    default:
                        // Handle operator-based checks
                        if ($operator === 'equals') {
                            $result = $nested_value == $expected_value;
                            $message = $result ? 'Value equals expected' : 'Value does not equal expected';
                        } elseif ($operator === 'not_equals') {
                            $result = $nested_value != $expected_value;
                            $message = $result ? 'Value does not equal expected' : 'Value equals expected';
                        } else {
                            $result = false;
                            $message = 'Check type is not supported: ' . ($check_type ?: $operator);
                        }
                }
            }
            
            return [
                'success' => true,
                'type' => 'option',
                'name' => $name,
                'selector' => $path ? 'path' : 'direct',
                'path' => $path,
                'result' => $result,
                'message' => $message
            ];
        }
        
        return [
            'success' => true,
            'type' => 'option',
            'name' => $name,
            'result' => true,
            'value' => $option_value,
            'message' => 'Option value retrieved successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'type' => 'option',
            'name' => $name,
            'message' => 'Error processing option: ' . $e->getMessage()
        ];
    }
}

/**
 * Handle plugin requests
 */
function mac_handle_plugin($item) {
    $name = isset($item['name']) ? sanitize_text_field($item['name']) : null;
    $value = isset($item['value']) ? $item['value'] : null;
    
    if (empty($name)) {
        return [
            'success' => false,
            'type' => 'plugin',
            'message' => 'Plugin name is required'
        ];
    }
    
    try {
        // Cache get_plugins() if called multiple times
        static $all_plugins = null;
        if ($all_plugins === null) {
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $all_plugins = get_plugins();
        }
        
        $plugin_found = null;
        $plugin_path_found = null;
        
        // Find plugin
        foreach ($all_plugins as $plugin_path => $plugin_data) {
            if (strpos($plugin_path, $name . '/') === 0) {
                $plugin_found = $plugin_data;
                $plugin_path_found = $plugin_path;
                break;
            }
        }
        
        if (!$plugin_found) {
            return [
                'success' => true,
                'type' => 'plugin',
                'name' => $name,
                'result' => false,
                'is_found' => false,
                'expected_found' => true,
                'message' => 'Plugin does not exist'
            ];
        }
        
        $is_active = is_plugin_active($plugin_path_found);
        
        // Handle different check types
        if ($value !== null && $value !== '') {
            if (is_bool($value)) {
                // Check active status
                $result = ($is_active === $value);
                return [
                    'success' => true,
                    'type' => 'plugin',
                    'name' => $name,
                    'result' => $result,
                    'is_active' => $is_active,
                    'expected_active' => $value,
                    'message' => $result ? 
                        ($is_active ? 'Plugin is active' : 'Plugin is not active (as expected)') : 
                        ($is_active ? 'Plugin is active (not expected)' : 'Plugin is not active')
                ];
            } else {
                // Check version
                $result = ($plugin_found['Version'] == $value);
                return [
                    'success' => true,
                    'type' => 'plugin',
                    'name' => $name,
                    'result' => $result,
                    'current_version' => $plugin_found['Version'],
                    'expected_version' => $value,
                    'message' => $result ? 'Plugin version matches' : 'Plugin version does not match'
                ];
            }
        }
        
        // Return plugin info
        return [
            'success' => true,
            'type' => 'plugin',
            'name' => $name,
            'result' => $is_active, // Return actual active status
            'message' => 'Plugin found and retrieved successfully',
            'data' => [
                'name' => $plugin_found['Name'],
                'version' => $plugin_found['Version'],
                'active' => $is_active,
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'type' => 'plugin',
            'name' => $name,
            'message' => 'Error retrieving plugin information: ' . $e->getMessage()
        ];
    }
}

/**
 * Handle post requests with optimized WP_Query
 */
function mac_handle_post($item) {
    $name = isset($item['name']) ? sanitize_text_field($item['name']) : null;
    $value = isset($item['value']) ? $item['value'] : null;
    $post_type = isset($item['post_type']) ? sanitize_text_field($item['post_type']) : 'any';
    
    // Get post_title parameter (can use post_title or name, default null)
    $filter_post_title = isset($item['post_title']) ? sanitize_text_field($item['post_title']) : null;
    
    // Read operator and pages from value array for contains_all_with_any operator
    $operator = isset($value['operator']) ? $value['operator'] : null;
    $required_pages = isset($value['required_pages']) ? $value['required_pages'] : [];
    $optional_pages = isset($value['optional_pages']) ? $value['optional_pages'] : [];
    
    if (empty($name)) {
        return [
            'success' => true,
            'type' => 'post',
            'name' => $name,
            'result' => false,
            'is_found' => false,
            'expected_found' => true,
            'message' => 'Parameter "name" is required for type post'
        ];
    }

    try {
        // Special handling for jet_footer
        if ($post_type === 'jet_footer') {
            $args = [
                'post_type' => ['jet-theme-core', 'jet-theme-template'],
                'post_status' => ['publish', 'draft'], // Include both publish and draft posts
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_elementor_template_type',
                        'value' => 'jet_footer',
                        'compare' => '='
                    ]
                ]
            ];
        } else {
            // Use WP_Query with fields='ids' to save memory
            $args = [
                'post_type' => $post_type,
                'post_status' => ['publish', 'draft'], // Include both publish and draft posts
                'posts_per_page' => -1,
                'fields' => 'ids'
            ];
            
            // Skip filtering by name if using contains_all_with_any operator
            $use_name_filter = true;
            if ($post_type === 'page' && $operator === 'contains_all_with_any') {
                $use_name_filter = false;
            }
            
            if ($use_name_filter) {
                // Support new structure: name = check name, post_slug = slug to find
                $post_slug = null;
                if (is_array($value) && isset($value['post_slug'])) {
                    $post_slug = sanitize_text_field($value['post_slug']);
                } else {
                    // Backward compatible: name = slug (old structure)
                    $post_slug = $name;
                }
                
                if ($post_slug && $post_slug !== 'any') {
                    $args['name'] = $post_slug;
                }
            }
        }
        
        $query = new WP_Query($args);
        $post_ids = $query->posts;
        
        if (empty($post_ids)) {
            // Return individual result format when no posts found
            return [
                'name' => $name,
                'success' => true,
                'result' => false,
                'message' => 'Post not found',
                'type' => 'post',
                'post_type' => $post_type
            ];
        }
        
        $results = [];
        
        // Handle different post types with specific logic
        if ($post_type === 'wpcf7_contact_form') {
            // Contact Form 7 - return nested structure
            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                
                // Filter by post_title if specified (case-insensitive)
                if ($filter_post_title !== null) {
                    if (stripos($post->post_title, $filter_post_title) === false) {
                        continue; // Skip if title doesn't match
                    }
                }
                
                $results[] = mac_handle_contact_form_post($post, $value);
            }
            
            // If no results found after filtering
            if (empty($results)) {
                return [
                    'success' => true,
                    'type' => 'post',
                    'name' => $name,
                    'post_type' => $post_type,
                    'result' => false,
                    'total' => 0,
                    'message' => $filter_post_title ? 'No contact form found with title matching: ' . $filter_post_title : 'No contact form found',
                    'results' => []
                ];
            }
            
            $overall_result = true;
        } elseif ($post_type === 'jet_footer') {
            // Jet Footer - return nested structure
            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                
                // Filter by post_title if specified (case-insensitive)
                if ($filter_post_title !== null) {
                    if (stripos($post->post_title, $filter_post_title) === false) {
                        continue; // Skip if title doesn't match
                    }
                }
                
                $results[] = mac_handle_jet_footer_post($post, $value);
            }
            
            // If no results found after filtering
            if (empty($results)) {
                return [
                    'success' => true,
                    'type' => 'post',
                    'name' => $name,
                    'post_type' => $post_type,
                    'result' => false,
                    'total' => 0,
                    'message' => $filter_post_title ? 'No jet footer found with title matching: ' . $filter_post_title : 'No jet footer found',
                    'results' => []
                ];
            }
            
            $overall_result = true;
        } elseif ($post_type === 'page' && $operator === 'contains_all_with_any') {
            // Page type with contains_all_with_any - return new format with summary
            
            // Get all page titles
            $found_pages = [];
            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                $found_pages[] = $post->post_title;
            }
            
            // Check which required pages were found
            $required_found = [];
            $required_missing = [];
            foreach ($required_pages as $required_page) {
                $found = false;
                foreach ($found_pages as $found_page) {
                    if (stripos($found_page, $required_page) !== false) {
                        $required_found[] = $required_page;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $required_missing[] = $required_page;
                }
            }
            
            // Check which optional pages were found
            $optional_found = [];
            foreach ($optional_pages as $optional_page) {
                foreach ($found_pages as $found_page) {
                    if (stripos($found_page, $optional_page) !== false) {
                        $optional_found[] = $optional_page;
                        break;
                    }
                }
            }
            
            $all_required_found = count($required_found) === count($required_pages);
            $has_optional = count($optional_found) > 0;
            
            // Determine overall result
            $overall_result = $all_required_found && $has_optional;
            
            // Build results with full page info
            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                
                $results[] = [
                    'ID' => $post->ID,
                    'post_title' => $post->post_title,
                    'post_name' => $post->post_name,
                    'post_type' => $post->post_type,
                    'post_status' => $post->post_status,
                    'post_date' => $post->post_date
                ];
            }
            
            // Create message
            $message = '';
            if ($all_required_found && $has_optional) {
                $message = 'All required pages found and optional pages check completed';
            } elseif ($all_required_found && !$has_optional) {
                $message = 'All required pages found but no optional pages found';
            } else {
                $message = 'Missing required pages or no optional pages found';
            }
        } else {
            // Default post handling - return individual results
            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                
                // Check if HTML validation is requested
                $result = true;
                $message = 'Post retrieved successfully';
                
                if ($value && isset($value['html']) && $value['html'] === 'true') {
                    $check_field = $value['check_field'] ?? '';
                    $expected_value = $value['expected_value'] ?? '';
                    $operator = $value['operator'] ?? 'equals';
                    
                    if (!empty($check_field)) {
                        // Get rendered HTML content for checking
                        $search_content = '';
                        
                        // Check if Elementor is active and this is an Elementor page
                        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
                        $is_elementor = get_post_meta($post_id, '_elementor_edit_mode', true) === 'builder';
                        
                        if ($is_elementor && $elementor_data && class_exists('\Elementor\Plugin')) {
                            // Render Elementor template to get actual HTML
                            try {
                                $search_content = \Elementor\Plugin::$instance->frontend->get_builder_content($post_id);
                            } catch (Exception $e) {
                                // Fallback: use raw post content
                                $search_content = apply_filters('the_content', $post->post_content);
                            }
                        } else {
                            // For non-Elementor pages, get rendered content
                            $search_content = apply_filters('the_content', $post->post_content);
                        }
                        
                        // Also check in post meta fields (like ACF fields)
                        if (!$is_elementor) {
                            $elementor_data = get_post_meta($post_id, '_elementor_data', true);
                            if ($elementor_data) {
                                $search_content .= ' ' . $elementor_data;
                            }
                        }
                        
                        // Check if field exists in rendered content
                        // Support CSS selector format: #abc (ID), .abc (class), abc (text)
                        $field_found = false;
                        
                        if (strpos($check_field, '#') === 0) {
                            // ID selector: #abc → check for id="abc" or id='abc'
                            $id_name = substr($check_field, 1);
                            $field_found = (strpos($search_content, 'id="' . $id_name . '"') !== false) 
                                        || (strpos($search_content, "id='" . $id_name . "'") !== false);
                        } elseif (strpos($check_field, '.') === 0) {
                            // Class selector: .abc → check for class="abc" or class='abc' or class="... abc ..."
                            $class_name = substr($check_field, 1);
                            $field_found = (strpos($search_content, 'class="' . $class_name . '"') !== false)
                                        || (strpos($search_content, "class='" . $class_name . "'") !== false)
                                        || (strpos($search_content, 'class="') !== false && strpos($search_content, $class_name) !== false);
                        } else {
                            // Plain text search
                            $field_found = strpos($search_content, $check_field) !== false;
                        }
                        
                        if (!empty($expected_value)) {
                            // Check both field and expected value
                            $value_found = strpos($search_content, $expected_value) !== false;
                            
                            if ($operator === 'equals') {
                                $result = $field_found && $value_found;
                                $message = $result ? "Page {$post->post_title} has {$check_field} and {$expected_value} in HTML" : "Page {$post->post_title} does not have {$check_field} and {$expected_value} in HTML";
                            } elseif ($operator === 'not_equals') {
                                $result = $field_found && !$value_found;
                                $message = $result ? "Page {$post->post_title} has {$check_field} but does not have {$expected_value} in HTML" : "Page {$post->post_title} does not meet the conditions";
                            }
                        } else {
                            // Only check field existence
                            // Support operator: 'equals' (default) or 'not_equals'
                            if ($operator === 'not_equals') {
                                // If operator is not_equals and no expected_value, field should NOT exist
                                $result = !$field_found;
                                $message = $result ? "Page {$post->post_title} does not have {$check_field} in HTML (as expected)" : "Page {$post->post_title} has {$check_field} in HTML (not expected)";
                            } else {
                                // Default: check if field exists
                                $result = $field_found;
                                $message = $result ? "Page {$post->post_title} has {$check_field} in HTML" : "Page {$post->post_title} does not have {$check_field} in HTML";
                            }
                        }
                    }
                }
                
                $results[] = [
                    'ID' => $post->ID,
                    'name' => $name,
                    'success' => true,
                    'result' => $result,
                    'post_title' => $post->post_title,
                    'message' => $message
                ];
            }
            $overall_result = true;
        }
        
        // Return different formats based on post type
        if ($post_type === 'wpcf7_contact_form' || $post_type === 'jet_footer') {
            // Return nested structure for special post types (contact form and jet footer)
            return [
                'name' => $name,
                'success' => true,
                'total' => count($results),
                'post_type' => $post_type,
                'type' => 'post',
                'result' => $overall_result,
                'message' => $message,
                'results' => $results
            ];
        } elseif ($post_type === 'page' && $operator === 'contains_all_with_any') {
            // Return new format for pages check
            return [
                'success' => true,
                'total' => count($results),
                'type' => 'post',
                'result' => $overall_result,
                'name' => $name,
                'operator' => 'contains_all_with_any',
                'required_found' => $required_found,
                'required_missing' => $required_missing,
                'optional_found' => $optional_found,
                'message' => $message,
                'results' => $results
            ];
        } else {
            // Return individual results for regular post types
            // Each result should be a separate item in the main results array
            return array_map(function($result) use ($post_type) {
                return array_merge($result, [
                    'type' => 'post',
                    'post_type' => $post_type
                ]);
            }, $results);
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'type' => 'post',
            'name' => $name,
            'message' => 'Error processing post: ' . $e->getMessage()
        ];
    }
}

/**
 * Handle user requests
 */
function mac_handle_user($name) {
    if (empty($name)) {
        return [
            'success' => false,
            'type' => 'user',
            'message' => 'User name is required'
        ];
    }
    
    try {
        $user = get_user_by('login', $name);
        if (!$user) {
            $user = get_user_by('email', $name);
        }
        
        if (!$user) {
            return [
                'success' => false,
                'type' => 'user',
                'message' => 'User not found: ' . $name
            ];
        }
        
        $user_data = [
            'ID' => $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'display_name' => $user->display_name,
            'user_registered' => $user->user_registered,
            'roles' => $user->roles,
            'capabilities' => $user->allcaps
        ];
        
        return [
            'success' => true,
            'type' => 'user',
            'result' => true,
            'message' => 'User found and retrieved successfully',
            'data' => $user_data
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'type' => 'user',
            'name' => $name,
            'message' => 'Error processing user: ' . $e->getMessage()
        ];
    }
}

/**
 * Handle updown requests
 */
function mac_handle_updown($item) {
    $name = isset($item['name']) ? sanitize_text_field($item['name']) : null;
    $value = isset($item['value']) ? $item['value'] : null;
    
    if (empty($name)) {
        return [
            'success' => false,
            'type' => 'updown',
            'message' => 'Name is required'
        ];
    }
    
    try {
        // Simple updown check - you can extend this logic
        $is_up = true; // Placeholder logic
        
        return [
            'success' => true,
            'type' => 'updown',
            'name' => $name,
            'result' => $is_up,
            'is_up' => $is_up,
            'message' => $is_up ? 'Service is up' : 'Service is down'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'type' => 'updown',
            'name' => $name,
            'message' => 'Error processing updown: ' . $e->getMessage()
        ];
    }
}

/**
 * Handle Contact Form 7 post processing
 */
function mac_handle_contact_form_post($post, $value) {
    $post_id = $post->ID;
    
    // Get Contact Form 7 meta data
    $form_data = get_post_meta($post_id, '_form', true);
    $mail_data = get_post_meta($post_id, '_mail', true);
    $mail_2_data = get_post_meta($post_id, '_mail_2', true);
    
    // Extract recipient from mail settings
    $mail_recipient = isset($mail_data['recipient']) ? $mail_data['recipient'] : '';
    
    // Process mail settings
    $mail_settings = [
        'active' => !empty($mail_data),
        'recipient' => $mail_recipient,
    ];
    
    // Process mail 2 settings
    $mail_2_settings = [
        'active' => !empty($mail_2_data),
        'recipient' => isset($mail_2_data['recipient']) ? $mail_2_data['recipient'] : ''
    ];
    
    // Check if value validation is requested
    $result = true;
    if ($value && isset($value['path']) && isset($value['check'])) {
        $path = $value['path'];
        $check = $value['check'];
        
        if ($path === 'recipient') {
            if ($check === 'not_empty') {
                $result = !empty($mail_recipient);
            } elseif ($check === 'empty') {
                $result = empty($mail_recipient);
            }
        }
    }
    
    return [
        'ID' => $post_id,
        'success' => true,
        'result' => $result,
        'post_title' => $post->post_title,
        'mail_recipient' => $mail_recipient,
        'message' => 'Contact form processed successfully'
    ];
}

/**
 * Handle Jet Footer post processing
 */
function mac_handle_jet_footer_post($post, $value) {
    $post_id = $post->ID;
    
    // Get Jet Footer meta data
    $elementor_data = get_post_meta($post_id, '_elementor_data', true);
    
    // Check if value validation is requested
    $result = false;
    $is_found = false;
    $message = 'Pattern not found';
    
    if ($value && isset($value['check_field'])) {
        $check_field = $value['check_field'];
        $expected_value = $value['expected_value'] ?? '';
        $operator = isset($value['operator']) ? $value['operator'] : 'equals';
        
        // For jet_footer, convert Elementor JSON to HTML like old code
        $search_content = $post->post_content;
        if ($elementor_data) {
            // Convert Elementor JSON to HTML (simplified version)
            $elementor_html = mac_convert_elementor_data_to_html($elementor_data);
            $search_content .= ' ' . $elementor_html;
        }
        
        // Look for the pattern - only need to check if field exists
        $result = (strpos($search_content, $check_field) !== false);
        $message = $result ? "Page {$post->post_title} has {$check_field} in HTML" : "Page {$post->post_title} does not have {$check_field} in HTML";
    }
    
    return [
        'success' => true,
        'type' => 'post',
        'name' => $post->post_title,
        'result' => $result,
        'is_found' => $is_found,
        'expected_found' => true,
        'message' => $message
    ];
}

