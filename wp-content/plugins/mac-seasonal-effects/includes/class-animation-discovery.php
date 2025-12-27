<?php
/**
 * Animation Discovery Class
 * 
 * Scans animations folder and discovers available animations
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Animation_Discovery {
    
    private $animations_cache = null;
    
    /**
     * Scan animations folder and return all available animations
     */
    public function scan_animations() {
        if ($this->animations_cache !== null) {
            return $this->animations_cache;
        }
        
        $animations = array();
        $base_path = MAC_SEASONAL_EFFECTS_PLUGIN_DIR . 'animations/';
        
        if (!is_dir($base_path)) {
            return $animations;
        }
        
        // Scan categories (folders in animations/)
        $categories = glob($base_path . '*', GLOB_ONLYDIR);
        
        foreach ($categories as $category_dir) {
            $category = basename($category_dir);
            $styles = array();
            
            // Scan styles (subfolders in category)
            $style_dirs = glob($category_dir . '/*', GLOB_ONLYDIR);
            
            foreach ($style_dirs as $style_dir) {
                $style = basename($style_dir);
                
                // Check if required files exist
                $config_path = $style_dir . '/config.json';
                $css_path = $style_dir . '/style.css';
                $js_path = $style_dir . '/script.js';
                $html_path = $style_dir . '/template.html';
                
                if (file_exists($css_path) || file_exists($js_path) || file_exists($html_path)) {
                    $style_data = array(
                        'name' => $style,
                        'path' => $style_dir,
                        'url' => MAC_SEASONAL_EFFECTS_PLUGIN_URL . 'animations/' . $category . '/' . $style . '/',
                        'has_css' => file_exists($css_path),
                        'has_js' => file_exists($js_path),
                        'has_html' => file_exists($html_path),
                        'config' => null
                    );
                    
                    // Load config.json if exists
                    if (file_exists($config_path)) {
                        $config_content = file_get_contents($config_path);
                        $style_data['config'] = json_decode($config_content, true);
                    }
                    
                    $styles[$style] = $style_data;
                }
            }
            
            if (!empty($styles)) {
                $animations[$category] = $styles;
            }
        }
        
        $this->animations_cache = $animations;
        return $animations;
    }
    
    /**
     * Get all categories
     */
    public function get_categories() {
        $animations = $this->scan_animations();
        return array_keys($animations);
    }
    
    /**
     * Get styles for a specific category
     */
    public function get_styles($category) {
        $animations = $this->scan_animations();
        if (!isset($animations[$category])) {
            return array();
        }
        return $animations[$category];
    }
    
    /**
     * Get animation path
     */
    public function get_animation_path($category, $style) {
        $animations = $this->scan_animations();
        if (!isset($animations[$category][$style])) {
            return null;
        }
        return $animations[$category][$style];
    }
    
    /**
     * Get config for animation
     */
    public function get_config($category, $style) {
        $animation = $this->get_animation_path($category, $style);
        if (!$animation || !$animation['config']) {
            return null;
        }
        return $animation['config'];
    }
    
    /**
     * Clear cache
     */
    public function clear_cache() {
        $this->animations_cache = null;
    }
}

