<?php
/**
 * Frontend Class
 * 
 * Handles frontend widget loading and rendering
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Tutorial_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_admin'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_frontend'));
        add_action('admin_footer', array($this, 'render_widget_container'));
        add_action('wp_footer', array($this, 'render_widget_container'));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts_admin($hook) {
        // Enqueue on all admin pages if there's an active tutorial
        $this->maybe_enqueue_scripts();
    }
    
    public function enqueue_scripts_frontend() {
        if (!is_singular('post') && !is_singular('page')) {
            return;
        }
        $this->maybe_enqueue_scripts();
    }
    
    private function maybe_enqueue_scripts() {
        $active_tutorial_data = $this->get_active_tutorial_data();
        if (!$active_tutorial_data) {
            return;
        }
        
        wp_enqueue_script(
            'mac-tutorial-widget',
            MAC_TUTORIALS_PLUGIN_URL . 'frontend/assets/js/widget.js',
            array('jquery'),
            MAC_TUTORIALS_VERSION,
            true
        );
        
        wp_enqueue_style(
            'mac-tutorial-widget',
            MAC_TUTORIALS_PLUGIN_URL . 'frontend/assets/css/widget.css',
            array(),
            MAC_TUTORIALS_VERSION
        );
        
        wp_localize_script('mac-tutorial-widget', 'MacTutorialData', $active_tutorial_data);
    }
    
    /**
     * Get active tutorial data
     * Now checks user state first, then falls back to current post
     */
    private function get_active_tutorial_data() {
        $state_manager = new MAC_Tutorial_State_Manager();
        
        // First, check if user has an active tutorial from state (status must be 'in-progress')
        $active_tutorial = $state_manager->get_active_tutorial();
        if ($active_tutorial && isset($active_tutorial['tutorial_id'])) {
            $tutorial_id = $active_tutorial['tutorial_id'];
            $post = get_post($tutorial_id);
            
            $allowed_types = array('post', 'page', 'mac_tutorial');
            if ($post && in_array($post->post_type, $allowed_types, true)) {
                $enabled = get_post_meta($tutorial_id, '_mac_tutorial_enabled', true);
                if (!empty($enabled)) {
                    $steps = get_post_meta($tutorial_id, '_mac_tutorial_steps', true);
                    $steps = !empty($steps) && is_array($steps) ? $steps : array();
                    if (!empty($steps)) {
                        $current_step = isset($active_tutorial['current_step']) ? intval($active_tutorial['current_step']) : 0;
                        $current_step = max(0, min($current_step, count($steps) - 1));
                        $status = $active_tutorial['status'] ?? 'in-progress';
                        
                        // Only return if status is 'in-progress'
                        if ($status === 'in-progress') {
                            return array(
                                'tutorial' => array(
                                    'id' => $tutorial_id,
                                    'title' => $post->post_title,
                                    'content' => $post->post_content,
                                    'steps' => $steps,
                                ),
                                'current_step' => $current_step,
                                'status' => $status,
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'nonce' => $state_manager->get_nonce(),
                            );
                        }
                    }
                }
            }
        }
        
        // Fallback: check current post (for edit page or frontend)
        // Only show if there's an active tutorial state with 'in-progress' status
        global $post;
        $allowed_types = array('post', 'page', 'mac_tutorial');
        if (!$post || !in_array($post->post_type, $allowed_types, true)) {
            return false;
        }
        
        $enabled = get_post_meta($post->ID, '_mac_tutorial_enabled', true);
        if (empty($enabled)) {
            return false;
        }
        
        $steps = get_post_meta($post->ID, '_mac_tutorial_steps', true);
        $steps = !empty($steps) && is_array($steps) ? $steps : array();
        if (empty($steps)) {
            return false;
        }
        
        $user_state = $state_manager->get_user_state();
        $current_state = $user_state[$post->ID] ?? array();
        $status = $current_state['status'] ?? '';
        
        // Only return if status is 'in-progress' (not 'closed', 'pause', or empty)
        if ($status !== 'in-progress') {
            return false;
        }
        
        $current_step = isset($current_state['current_step']) ? intval($current_state['current_step']) : 0;
        $current_step = max(0, min($current_step, count($steps) - 1));
        
        return array(
            'tutorial' => array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'steps' => $steps,
            ),
            'current_step' => $current_step,
            'status' => $status,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => $state_manager->get_nonce(),
        );
    }
    
    /**
     * Render widget container
     */
    public function render_widget_container() {
        // Only render if there's an active tutorial
        $active_data = $this->get_active_tutorial_data();
        if (!$active_data) {
            return;
        }
        ?>
        <div id="mac-tutorial-widget-container"></div>
        <?php
    }
}

