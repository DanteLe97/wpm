<?php
/**
 * Animation Loader Class
 * 
 * Loads animation assets (CSS, JS, HTML) on frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Animation_Loader {
    
    private $manager;
    
    public function __construct($manager) {
        $this->manager = $manager;
    }
    
    /**
     * Initialize loader
     */
    public function init() {
        // Check if preview mode
        $is_preview = isset($_GET['animation_preview']) && current_user_can('manage_options');
        
        if ($is_preview) {
            // Preview mode - load specific animation
            $preview = sanitize_text_field($_GET['animation_preview']);
            list($category, $style) = explode('-', $preview, 2);
            $this->load_animation($category, $style, true);
        } else {
            // Normal mode - load active animation
            if ($this->manager->is_animation_active()) {
                $animation = $this->manager->get_active_animation();
                if ($animation) {
                    $this->load_animation($animation['category'], $animation['style'], false, $animation['customizations']);
                }
            }
        }
    }
    
    /**
     * Load animation assets
     */
    private function load_animation($category, $style, $is_preview = false, $customizations = array()) {
        $animation = $this->manager->get_animation_path($category, $style);
        
        if (!$animation) {
            return;
        }
        
        // Load CSS
        if ($animation['has_css']) {
            add_action('wp_enqueue_scripts', function() use ($animation, $category, $style) {
                wp_enqueue_style(
                    'mac-animation-' . $category . '-' . $style,
                    $animation['url'] . 'style.css',
                    array(),
                    file_exists($animation['path'] . '/style.css') ? filemtime($animation['path'] . '/style.css') : MAC_SEASONAL_EFFECTS_VERSION
                );
            });
        }
        
        // Load JS
        if ($animation['has_js']) {
            add_action('wp_enqueue_scripts', function() use ($animation, $category, $style) {
                wp_enqueue_script(
                    'mac-animation-' . $category . '-' . $style,
                    $animation['url'] . 'script.js',
                    array('jquery'),
                    file_exists($animation['path'] . '/script.js') ? filemtime($animation['path'] . '/script.js') : MAC_SEASONAL_EFFECTS_VERSION,
                    true
                );
            });
        }
        
        // Inject custom CSS (colors)
        if (!empty($customizations['colors'])) {
            add_action('wp_head', function() use ($customizations) {
                $this->inject_custom_colors($customizations['colors']);
            }, 999);
        }
        
        // Inject custom images CSS (only if needed for background-image)
        if (!empty($customizations['images'])) {
            add_action('wp_head', function() use ($customizations, $animation) {
                $this->inject_custom_images($customizations['images'], $animation['path']);
            }, 999);
        }
        
        // Inject HTML template
        // Use priority 5 to ensure container is injected before scripts run (scripts are enqueued with default priority 10)
        if ($animation['has_html']) {
            add_action('wp_footer', function() use ($animation, $category, $style, $customizations) {
                $this->inject_html_template($animation['path'], $category, $style, $customizations);
            }, 5);
        }
    }
    
    /**
     * Inject custom colors as CSS variables
     */
    private function inject_custom_colors($colors) {
        if (empty($colors)) {
            return;
        }
        
        $css = '<style id="mac-animation-custom-colors">';
        $css .= '.mac-animation-container {';
        foreach ($colors as $key => $value) {
            $css .= "--anim-{$key}-color: {$value};";
        }
        $css .= '}';
        $css .= '</style>';
        
        echo $css;
    }
    
    /**
     * Inject custom images as CSS (for background images)
     * Note: For <img src=""> tags, replacement is done in inject_html_template()
     * Only inject CSS if template doesn't use <img> tags for these images
     */
    private function inject_custom_images($images, $template_path = null) {
        if (empty($images)) {
            return;
        }
        
        // Check if template uses <img> tags (if template path provided)
        // Only inject CSS for images that are NOT used in <img> tags
        $images_to_inject = array();
        
        if ($template_path) {
            $html_path = $template_path . '/template.html';
            if (file_exists($html_path)) {
                $html_content = file_get_contents($html_path);
                
                // Check each image - only inject CSS if NOT used in <img> tags
                foreach ($images as $key => $url) {
                    $used_in_img = false;
                    
                    // Check if used in <img> tags via class
                    if (preg_match('/<img[^>]*class="[^"]*' . preg_quote($key, '/') . '[^"]*"/i', $html_content)) {
                        $used_in_img = true;
                    }
                    // Check if used in <img> tags via alt
                    elseif (preg_match('/<img[^>]*alt="[^"]*' . preg_quote($key, '/') . '[^"]*"/i', $html_content)) {
                        $used_in_img = true;
                    }
                    // Check if used in placeholder {{{key}}}
                    elseif (strpos($html_content, '{{{' . $key . '}}}') !== false) {
                        $used_in_img = true;
                    }
                    
                    // Only add to inject list if NOT used in <img> tags (for background-image support)
                    if (!$used_in_img) {
                        $images_to_inject[$key] = $url;
                    }
                }
            } else {
                // No template file - inject all (for background-image support)
                $images_to_inject = $images;
            }
        } else {
            // No template path - inject all (for background-image support)
            $images_to_inject = $images;
        }
        
        // Only inject CSS if there are images that need background-image
        if (empty($images_to_inject)) {
            return; // All images are handled via <img src=""> replacement, no need for CSS
        }
        
        $css = '<style id="mac-animation-custom-images">';
        foreach ($images_to_inject as $key => $url) {
            // Escape URL for CSS
            $escaped_url = esc_url($url);
            // For elements that use background-image
            $css .= ".anim-{$key}, .halloween-{$key} { background-image: url('{$escaped_url}'); }";
        }
        $css .= '</style>';
        
        echo $css;
    }
    
    /**
     * Inject HTML template with customizations
     */
    private function inject_html_template($template_path, $category, $style, $customizations) {
        $html_path = $template_path . '/template.html';
        
        if (!file_exists($html_path)) {
            return;
        }
        
        $html = file_get_contents($html_path);
        
        // Replace image placeholders - merge customizations with defaults
        // Get config for defaults
        $config_for_images = $this->manager->get_config($category, $style);
        if ($config_for_images && isset($config_for_images['customizable']['images'])) {
            foreach ($config_for_images['customizable']['images'] as $key => $image_config) {
                // Check if there's a customization for this image
                if (!empty($customizations['images']) && isset($customizations['images'][$key])) {
                    // Use custom URL
                    $url = $customizations['images'][$key];
                } else {
                    // Use default from config
                    $url = $image_config['default'];
                }
                
                // Convert relative path to full URL if needed
                if (!empty($url) && !preg_match('/^https?:\/\//', $url)) {
                    if (preg_match('/^\/wp-content\//', $url)) {
                        // Absolute path from WordPress root
                        $url = home_url($url);
                    } else {
                        // Relative path - convert to full URL
                        $url = MAC_SEASONAL_EFFECTS_PLUGIN_URL . ltrim($url, '/');
                    }
                }
                
                $escaped_url = esc_url($url);
                
                // Replace {{{key}}} placeholders (priority 1)
                $html = str_replace('{{{' . $key . '}}}', $escaped_url, $html);
                
                // Replace in class-based img tags (e.g., class="halloween-ghost" -> replace src)
                // Pattern: <img ... class="halloween-{key}" ... src="...">
                $html = preg_replace(
                    '/(<img[^>]*class="[^"]*halloween-' . preg_quote($key, '/') . '[^"]*"[^>]*src=")[^"]*(")/i',
                    '$1' . $escaped_url . '$2',
                    $html
                );
                
                // Replace in alt-based img tags (e.g., alt="ghost" -> replace src)
                $html = preg_replace(
                    '/(<img[^>]*alt="[^"]*' . preg_quote($key, '/') . '[^"]*"[^>]*src=")[^"]*(")/i',
                    '$1' . $escaped_url . '$2',
                    $html
                );
            }
        }
        
        // Get customizable settings
        $settings_data = '';
        
        // Get config for defaults
        $config = $this->manager->get_config($category, $style);
        
        // Merge customizations with defaults from config
        $merged_settings = array();
        if ($config && isset($config['customizable']['settings'])) {
            foreach ($config['customizable']['settings'] as $key => $setting_config) {
                // Use customization if exists, otherwise use default
                $merged_settings[$key] = isset($customizations['settings'][$key]) 
                    ? $customizations['settings'][$key] 
                    : $setting_config['default'];
            }
        }
        
        // Also include any customizations not in config
        if (!empty($customizations['settings'])) {
            foreach ($customizations['settings'] as $key => $value) {
                if (!isset($merged_settings[$key])) {
                    $merged_settings[$key] = $value;
                }
            }
        }
        
        // Build data attributes for settings
        foreach ($merged_settings as $key => $value) {
            $data_key = str_replace('_', '-', $key);
            // Convert boolean to string for data attribute
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $settings_data .= ' data-' . esc_attr($data_key) . '="' . esc_attr($value) . '"';
        }
        
        // Build data attributes for images
        // Helper function to convert relative path to full URL
        $convert_image_url = function($url) {
            if (empty($url)) {
                return $url;
            }
            
            // Already absolute URL (http/https)
            if (preg_match('/^https?:\/\//', $url)) {
                return $url;
            }
            
            // Already absolute path from WordPress root (/wp-content/...)
            if (preg_match('/^\/wp-content\//', $url)) {
                return home_url($url);
            }
            
            // Relative path from plugin - convert to full URL
            // Remove leading slash if present
            $url = ltrim($url, '/');
            return MAC_SEASONAL_EFFECTS_PLUGIN_URL . $url;
        };
        
        // Merge customizations with defaults - always inject all images
        // Use customization if exists, otherwise use default from config
        if ($config && isset($config['customizable']['images'])) {
            foreach ($config['customizable']['images'] as $key => $image_config) {
                // Check if there's a customization for this image
                if (!empty($customizations['images']) && isset($customizations['images'][$key])) {
                    // Use custom URL
                    $url = $customizations['images'][$key];
                } else {
                    // Use default from config
                    $url = $image_config['default'];
                }
                
                // Convert to full URL
                $url = $convert_image_url($url);
                
                // Inject as data attribute
                $data_key = str_replace('_', '-', $key);
                $escaped_url = esc_url($url);
                $settings_data .= ' data-' . esc_attr($data_key) . '="' . esc_attr($escaped_url) . '"';
            }
        }
        
        
        // Add plugin URL to data attributes for JavaScript
        $settings_data .= ' data-plugin-url="' . esc_attr(MAC_SEASONAL_EFFECTS_PLUGIN_URL) . '"';
        
        // Wrap in container for CSS variables
        echo '<div class="mac-animation-container mac-animation-' . esc_attr($category) . ' mac-animation-' . esc_attr($category) . '-' . esc_attr($style) . '"' . $settings_data . '>';
        echo $html;
        echo '</div>';
    }
}

