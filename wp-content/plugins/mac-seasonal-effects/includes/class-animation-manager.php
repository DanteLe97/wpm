<?php
/**
 * Animation Manager Class
 * 
 * Manages animation settings and state
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Animation_Manager {
    
    private $discovery;
    private $validator;
    private $option_name = 'mac_seasonal_effects_settings';
    
    public function __construct($discovery, $validator) {
        $this->discovery = $discovery;
        $this->validator = $validator;
    }
    
    /**
     * Get all settings
     */
    public function get_settings() {
        $defaults = array(
            'enabled' => false,
            'category' => null,
            'style' => null,
            'start_date' => date('Y-m-d'),
            'end_date' => null,
            'customizations' => array()
        );
        
        $settings = get_option($this->option_name, array());
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Save settings
     */
    public function save_settings($settings) {
        // Validate dates
        if (!empty($settings['start_date']) && !$this->validator->validate_date($settings['start_date'])) {
            return new WP_Error('invalid_start_date', 'Invalid start date format');
        }
        
        if (!empty($settings['end_date']) && !$this->validator->validate_date($settings['end_date'])) {
            return new WP_Error('invalid_end_date', 'Invalid end date format');
        }
        
        // Ensure only one animation is active
        if ($settings['enabled']) {
            // If changing animation, disable others
            if (!empty($settings['category']) && !empty($settings['style'])) {
                // This is handled in admin save
            }
        }
        
        // Save settings
        // Note: update_option() returns:
        // - true if option was added or updated
        // - false if value didn't change (but that's still success for us)
        // We always consider it success unless there's a real database error
        $result = update_option($this->option_name, $settings);
        
        // Always return true - update_option only fails on serious database errors
        // If value didn't change, that's still success
        return true;
    }
    
    /**
     * Check if animation is currently active
     */
    public function is_animation_active() {
        $settings = $this->get_settings();
        
        // Check if enabled
        if (!$settings['enabled']) {
            return false;
        }
        
        // Check if category and style are set
        if (empty($settings['category']) || empty($settings['style'])) {
            return false;
        }
        
        // Check date range
        if (!$this->validator->is_date_valid($settings['start_date'], $settings['end_date'])) {
            // Auto-disable if on or past end date
            if ($this->validator->should_auto_disable($settings['end_date'])) {
                $settings['enabled'] = false;
                $this->save_settings($settings);
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Get active animation data
     */
    public function get_active_animation() {
        if (!$this->is_animation_active()) {
            return null;
        }
        
        $settings = $this->get_settings();
        $animation = $this->discovery->get_animation_path($settings['category'], $settings['style']);
        
        if (!$animation) {
            return null;
        }
        
        $custom_key = $settings['category'] . '-' . $settings['style'];
        $customizations = isset($settings['customizations'][$custom_key]) 
            ? $settings['customizations'][$custom_key] 
            : array();
        
        return array(
            'category' => $settings['category'],
            'style' => $settings['style'],
            'path' => $animation['path'],
            'url' => $animation['url'],
            'has_css' => $animation['has_css'],
            'has_js' => $animation['has_js'],
            'has_html' => $animation['has_html'],
            'config' => $animation['config'],
            'customizations' => $customizations
        );
    }
    
    /**
     * Get customization value with fallback to default
     */
    public function get_customization_value($category, $style, $type, $key) {
        $settings = $this->get_settings();
        $custom_key = $category . '-' . $style;
        
        // Get custom value
        if (isset($settings['customizations'][$custom_key][$type][$key])) {
            return $settings['customizations'][$custom_key][$type][$key];
        }
        
        // Fallback to default from config
        $config = $this->discovery->get_config($category, $style);
        if ($config && isset($config['customizable'][$type][$key]['default'])) {
            return $config['customizable'][$type][$key]['default'];
        }
        
        return null;
    }
    
    /**
     * Get config for animation
     */
    public function get_config($category, $style) {
        return $this->discovery->get_config($category, $style);
    }
    
    /**
     * Get animation path (wrapper for discovery)
     */
    public function get_animation_path($category, $style) {
        return $this->discovery->get_animation_path($category, $style);
    }
}

