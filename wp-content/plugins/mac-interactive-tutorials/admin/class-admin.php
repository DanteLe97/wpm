<?php
/**
 * Admin Class
 * 
 * Handles admin pages and UI
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Tutorial_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_init', array($this, 'handle_actions'));
    }
    
    /**
     * Add admin page
     */
    public function add_admin_page() {
        // No custom menu needed; using post/page screens
    }
    
    /**
     * Handle admin actions
     */
    public function handle_actions() {
        if (isset($_GET['mac_toggle_tutorial']) && isset($_GET['tutorial_id'])) {
            $this->toggle_tutorial();
        }
        
        if (isset($_GET['mac_start_tutorial']) && isset($_GET['tutorial_id'])) {
            $this->start_tutorial();
        }
        
        if (isset($_GET['mac_resume_tutorial']) && isset($_GET['tutorial_id'])) {
            $this->resume_tutorial();
        }
    }
    
    /**
     * Toggle tutorial (on/off widget)
     */
    private function toggle_tutorial() {
        if (!current_user_can('read')) {
            wp_die(__('You do not have permission to toggle tutorials.', 'mac-interactive-tutorials'));
        }
        
        $tutorial_id = absint($_GET['tutorial_id']);
        if (empty($tutorial_id)) {
            wp_die(__('Invalid tutorial ID.', 'mac-interactive-tutorials'));
        }
        
        $nonce = sanitize_text_field($_GET['_wpnonce'] ?? '');
        
        if (!wp_verify_nonce($nonce, 'toggle_tutorial_' . $tutorial_id)) {
            wp_die(__('Security check failed.', 'mac-interactive-tutorials'));
        }
        
        // Verify post exists
        $post = get_post($tutorial_id);
        if (!$post) {
            wp_die(__('Tutorial not found.', 'mac-interactive-tutorials'));
        }
        
        try {
            $state_manager = new MAC_Tutorial_State_Manager();
            $active_state = $state_manager->get_active_tutorial();
            $target_url = '';
            
            if ($active_state && $active_state['tutorial_id'] == $tutorial_id) {
                $state_manager->close_tutorial($tutorial_id);
            } else {
                $paused_state = $state_manager->get_paused_tutorial($tutorial_id);
                if ($paused_state) {
                    $current_step = $paused_state['current_step'] ?? 0;
                    $state_manager->update_state($tutorial_id, $current_step, 'in-progress');
                    $target_url = $this->get_step_url($tutorial_id, $current_step);
                } else {
                    $state_manager->update_state($tutorial_id, 0, 'in-progress');
                    $target_url = $this->get_step_url($tutorial_id, 0);
                }
            }
            
            // Determine redirect URL
            // Only redirect to target_url if it's set, otherwise go to list page
            $redirect = '';
            if (!empty($target_url)) {
                $redirect = $target_url;
            } else {
                // Redirect to list page if no target_url
                $post_type = get_post_type($tutorial_id);
                $redirect = admin_url('edit.php?post_type=' . $post_type);
            }
            
            // Redirect
            if (!headers_sent()) {
                wp_safe_redirect($redirect);
                exit;
            } else {
                // Headers already sent, use JavaScript redirect
                echo '<script>window.location.href = "' . esc_js($redirect) . '";</script>';
                exit;
            }
        } catch (Exception $e) {
            error_log('MAC Tutorial Toggle Error: ' . $e->getMessage());
            wp_die(__('An error occurred while toggling the tutorial.', 'mac-interactive-tutorials'));
        }
    }
    
    /**
     * Start tutorial (from beginning)
     */
    private function start_tutorial() {
        if (!current_user_can('read')) {
            wp_die(__('You do not have permission to start tutorials.', 'mac-interactive-tutorials'));
        }
        
        $tutorial_id = absint($_GET['tutorial_id']);
        if (empty($tutorial_id)) {
            wp_die(__('Invalid tutorial ID.', 'mac-interactive-tutorials'));
        }
        
        $nonce = sanitize_text_field($_GET['_wpnonce'] ?? '');
        
        if (!wp_verify_nonce($nonce, 'start_tutorial_' . $tutorial_id)) {
            wp_die(__('Security check failed.', 'mac-interactive-tutorials'));
        }
        
        // Verify post exists
        $post = get_post($tutorial_id);
        if (!$post) {
            wp_die(__('Tutorial not found.', 'mac-interactive-tutorials'));
        }
        
        try {
            $state_manager = new MAC_Tutorial_State_Manager();
            $state_manager->update_state($tutorial_id, 0, 'in-progress');
            
            $target_url = $this->get_step_url($tutorial_id, 0);
            // Only redirect to target_url if it's set, otherwise go to list page
            $redirect = '';
            if (!empty($target_url)) {
                $redirect = $target_url;
            } else {
                // Redirect to list page if no target_url
                $post_type = get_post_type($tutorial_id);
                $redirect = admin_url('edit.php?post_type=' . $post_type);
            }
            
            if (!headers_sent()) {
                wp_safe_redirect($redirect);
                exit;
            } else {
                echo '<script>window.location.href = "' . esc_js($redirect) . '";</script>';
                exit;
            }
        } catch (Exception $e) {
            error_log('MAC Tutorial Start Error: ' . $e->getMessage());
            wp_die(__('An error occurred while starting the tutorial.', 'mac-interactive-tutorials'));
        }
    }
    
    /**
     * Resume tutorial (from last step)
     */
    private function resume_tutorial() {
        if (!current_user_can('read')) {
            wp_die(__('You do not have permission to resume tutorials.', 'mac-interactive-tutorials'));
        }
        
        $tutorial_id = absint($_GET['tutorial_id']);
        if (empty($tutorial_id)) {
            wp_die(__('Invalid tutorial ID.', 'mac-interactive-tutorials'));
        }
        
        $nonce = sanitize_text_field($_GET['_wpnonce'] ?? '');
        
        if (!wp_verify_nonce($nonce, 'resume_tutorial_' . $tutorial_id)) {
            wp_die(__('Security check failed.', 'mac-interactive-tutorials'));
        }
        
        // Verify post exists
        $post = get_post($tutorial_id);
        if (!$post) {
            wp_die(__('Tutorial not found.', 'mac-interactive-tutorials'));
        }
        
        try {
            $state_manager = new MAC_Tutorial_State_Manager();
            $paused_state = $state_manager->get_paused_tutorial($tutorial_id);
            $target_url = '';
            
            if ($paused_state) {
                $current_step = $paused_state['current_step'] ?? 0;
                $state_manager->update_state($tutorial_id, $current_step, 'in-progress');
                $target_url = $this->get_step_url($tutorial_id, $current_step);
            } else {
                $state_manager->update_state($tutorial_id, 0, 'in-progress');
                $target_url = $this->get_step_url($tutorial_id, 0);
            }
            
            // Only redirect to target_url if it's set, otherwise go to list page
            $redirect = '';
            if (!empty($target_url)) {
                $redirect = $target_url;
            } else {
                // Redirect to list page if no target_url
                $post_type = get_post_type($tutorial_id);
                $redirect = admin_url('edit.php?post_type=' . $post_type);
            }
            
            if (!headers_sent()) {
                wp_safe_redirect($redirect);
                exit;
            } else {
                echo '<script>window.location.href = "' . esc_js($redirect) . '";</script>';
                exit;
            }
        } catch (Exception $e) {
            error_log('MAC Tutorial Resume Error: ' . $e->getMessage());
            wp_die(__('An error occurred while resuming the tutorial.', 'mac-interactive-tutorials'));
        }
    }

    /**
     * Helper: get step URL (normalized)
     */
    private function get_step_url($tutorial_id, $step_index) {
        $steps = get_post_meta($tutorial_id, '_mac_tutorial_steps', true);
        if (empty($steps) || !is_array($steps) || !isset($steps[$step_index]['target_url'])) {
            return '';
        }
        $target_url = trim($steps[$step_index]['target_url']);
        if (empty($target_url)) {
            return '';
        }
        if (!preg_match('/^https?:\\/\\//', $target_url)) {
            $target_url = admin_url($target_url);
        }
        return $target_url;
    }
    
    /**
     * Add custom columns to tutorials list
     */
    public function add_custom_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['steps_count'] = __('Steps', 'mac-interactive-tutorials');
                $new_columns['difficulty'] = __('Difficulty', 'mac-interactive-tutorials');
            }
        }
        return $new_columns;
    }
    
    /**
     * Render custom column content
     */
    public function render_custom_columns($column, $post_id) {
        if ($column === 'steps_count') {
            $steps = get_post_meta($post_id, '_mac_tutorial_steps', true);
            $count = !empty($steps) && is_array($steps) ? count($steps) : 0;
            echo $count;
        } elseif ($column === 'difficulty') {
            $settings = get_post_meta($post_id, '_mac_tutorial_settings', true);
            $difficulty = $settings['difficulty'] ?? 'beginner';
            echo ucfirst($difficulty);
        }
    }
}

// Row actions on posts/pages/mac_tutorial with tutorial enabled
function mac_tutorial_add_row_actions($actions, $post) {
    // If running as source site: apply on post/page. If client site: only mac_tutorial.
    $allowed_types = array('post', 'page');
    if (!defined('MAC_TUTORIALS_IS_SOURCE') || MAC_TUTORIALS_IS_SOURCE !== true) {
        $allowed_types = array('mac_tutorial');
    }
    if (!in_array($post->post_type, $allowed_types, true)) {
        return $actions;
    }
    $enabled = get_post_meta($post->ID, '_mac_tutorial_enabled', true);
    if (empty($enabled)) {
        return $actions;
    }
    
    $state_manager = new MAC_Tutorial_State_Manager();
    $active_state = $state_manager->get_active_tutorial();
    $paused_state = $state_manager->get_paused_tutorial($post->ID);
    
    $tutorial_id = $post->ID;
    $toggle_nonce = wp_create_nonce('toggle_tutorial_' . $tutorial_id);
    $start_nonce = wp_create_nonce('start_tutorial_' . $tutorial_id);
    $resume_nonce = wp_create_nonce('resume_tutorial_' . $tutorial_id);
    
    $toggle_url = admin_url('admin.php?mac_toggle_tutorial=1&tutorial_id=' . $tutorial_id . '&_wpnonce=' . $toggle_nonce);
    $start_url = admin_url('admin.php?mac_start_tutorial=1&tutorial_id=' . $tutorial_id . '&_wpnonce=' . $start_nonce);
    $resume_url = admin_url('admin.php?mac_resume_tutorial=1&tutorial_id=' . $tutorial_id . '&_wpnonce=' . $resume_nonce);
    
    $is_active = $active_state && $active_state['tutorial_id'] == $tutorial_id;
    $user_state = $state_manager->get_user_state();
    $has_state = isset($user_state[$tutorial_id]);
    
    // WordPress sẽ tự động thêm View button nếu CPT có permalink (publicly_queryable => true)
    // Không cần thêm thủ công nữa
    
    if ($is_active) {
        $actions['mac_toggle'] = '<a href="' . esc_url($toggle_url) . '">' . __('Stop Tutorial', 'mac-interactive-tutorials') . '</a>';
    } else {
        $actions['mac_toggle'] = '<a href="' . esc_url($toggle_url) . '">' . __('Start Tutorial', 'mac-interactive-tutorials') . '</a>';
    }
    
    if ($has_state) {
        $actions['mac_start'] = '<a href="' . esc_url($start_url) . '">' . __('Restart Tutorial', 'mac-interactive-tutorials') . '</a>';
        if ($paused_state || !$is_active) {
            $actions['mac_resume'] = '<a href="' . esc_url($resume_url) . '">' . __('Resume Tutorial', 'mac-interactive-tutorials') . '</a>';
        }
    }
    return $actions;
}
// Attach filters depending on source/client mode
if (defined('MAC_TUTORIALS_IS_SOURCE') && MAC_TUTORIALS_IS_SOURCE === true) {
    // Source site: apply on post/page
    add_filter('post_row_actions', 'mac_tutorial_add_row_actions', 10, 2);
    add_filter('page_row_actions', 'mac_tutorial_add_row_actions', 10, 2);
} else {
    // Client site: apply on mac_tutorial CPT
    // WordPress uses {post_type}_row_actions filter for custom post types
    add_filter('mac_tutorial_row_actions', 'mac_tutorial_add_row_actions', 10, 2);
    // Also add to post_row_actions as fallback (WordPress may use this for CPTs too)
    add_filter('post_row_actions', 'mac_tutorial_add_row_actions', 10, 2);
}

