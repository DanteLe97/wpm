<?php
/**
 * Admin Class
 * 
 * Handles admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Animation_Admin {
    
    private $manager;
    private $discovery;
    private $settings_instance;
    
    public function __construct($manager, $discovery) {
        $this->manager = $manager;
        $this->discovery = $discovery;
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Register AJAX handlers early (not dependent on page render)
        $this->register_ajax_handlers();
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Create settings instance for AJAX handlers
        $settings = new MAC_Animation_Settings($this->manager, $this->discovery);
        
        // AJAX handlers are registered in MAC_Animation_Settings constructor
        // But we need to keep the instance alive, so store it
        $this->settings_instance = $settings;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('MAC Seasonal Effects', 'mac-seasonal-effects'),
            __('MAC Seasonal Effects', 'mac-seasonal-effects'),
            'manage_options',
            'mac-seasonal-effects',
            array($this, 'render_settings_page'),
            'dashicons-admin-generic',
            30
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_mac-seasonal-effects') {
            return;
        }
        
        wp_enqueue_style(
            'mac-seasonal-effects-admin',
            MAC_SEASONAL_EFFECTS_PLUGIN_URL . 'assets/admin.css',
            array(),
            MAC_SEASONAL_EFFECTS_VERSION
        );
        
        // Enqueue media uploader (required for wp.media)
        wp_enqueue_media();
        
        wp_enqueue_script(
            'mac-seasonal-effects-admin',
            MAC_SEASONAL_EFFECTS_PLUGIN_URL . 'assets/admin.js',
            array('jquery', 'wp-color-picker', 'media-upload', 'media-views'),
            MAC_SEASONAL_EFFECTS_VERSION,
            true
        );
        
        wp_enqueue_style('wp-color-picker');
        
        wp_localize_script('mac-seasonal-effects-admin', 'macSeasonalEffects', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mac_seasonal_effects_nonce'),
            'previewUrl' => home_url('?animation_preview=')
        ));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Use existing instance if available, otherwise create new one
        if (!$this->settings_instance) {
            $this->settings_instance = new MAC_Animation_Settings($this->manager, $this->discovery);
        }
        $this->settings_instance->render();
    }
}

