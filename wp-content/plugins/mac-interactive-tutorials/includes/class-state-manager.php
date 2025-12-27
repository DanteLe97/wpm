<?php
/**
 * State Manager Class
 * 
 * Handles tutorial state management for users
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Tutorial_State_Manager {
    
    /**
     * Nonce key for AJAX
     */
    private $nonce_key = 'mac_tutorial_state';
    
    /**
     * User meta key
     */
    private $meta_key = 'mac_tutorial_state';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_mac_tutorial_state', array($this, 'handle_ajax'));
    }
    
    /**
     * Get user state
     */
    public function get_user_state($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return array();
        }
        
        $state = get_user_meta($user_id, $this->meta_key, true);
        return !empty($state) && is_array($state) ? $state : array();
    }
    
    /**
     * Get active tutorial for current user
     * Only returns tutorials with status 'in-progress' (not 'pause' or 'closed')
     */
    public function get_active_tutorial($user_id = null) {
        $state = $this->get_user_state($user_id);
        
        if (empty($state)) {
            return false;
        }
        
        // Find only in-progress tutorial (not pause or closed)
        foreach ($state as $tutorial_id => $tutorial_state) {
            if (isset($tutorial_state['status']) && $tutorial_state['status'] === 'in-progress') {
                return array_merge(
                    array('tutorial_id' => $tutorial_id),
                    $tutorial_state
                );
            }
        }
        
        return false;
    }
    
    /**
     * Get paused or closed tutorial (for resume button)
     */
    public function get_paused_tutorial($tutorial_id, $user_id = null) {
        $state = $this->get_user_state($user_id);
        
        if (empty($state) || !isset($state[$tutorial_id])) {
            return false;
        }
        
        $tutorial_state = $state[$tutorial_id];
        if (isset($tutorial_state['status']) && 
            in_array($tutorial_state['status'], array('pause', 'closed'))) {
            return array_merge(
                array('tutorial_id' => $tutorial_id),
                $tutorial_state
            );
        }
        
        return false;
    }
    
    /**
     * Update tutorial state
     */
    public function update_state($tutorial_id, $step_index, $status = 'in-progress') {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        // Validate and sanitize step_index
        $step_index = intval($step_index);
        $step_index = max(0, $step_index); // Ensure non-negative
        
        $state = $this->get_user_state($user_id);
        
        $state[$tutorial_id] = array(
            'tutorial_id' => intval($tutorial_id),
            'current_step' => $step_index,
            'status' => sanitize_text_field($status),
            'updated_at' => current_time('mysql'),
        );
        
        // Add started_at if new
        if (!isset($state[$tutorial_id]['started_at'])) {
            $state[$tutorial_id]['started_at'] = current_time('mysql');
        }
        
        update_user_meta($user_id, $this->meta_key, $state);
        
        return $state[$tutorial_id];
    }
    
    /**
     * Pause tutorial
     */
    public function pause_tutorial($tutorial_id) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        $state = $this->get_user_state($user_id);
        if (isset($state[$tutorial_id])) {
            $current_step = $state[$tutorial_id]['current_step'] ?? 0;
            return $this->update_state($tutorial_id, $current_step, 'pause');
        }
        
        return false;
    }
    
    /**
     * Resume tutorial
     */
    public function resume_tutorial($tutorial_id) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        $state = $this->get_user_state($user_id);
        if (isset($state[$tutorial_id])) {
            $current_step = $state[$tutorial_id]['current_step'] ?? 0;
            return $this->update_state($tutorial_id, $current_step, 'in-progress');
        }
        
        return false;
    }
    
    /**
     * Complete tutorial
     */
    public function complete_tutorial($tutorial_id) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        $state = $this->get_user_state($user_id);
        if (isset($state[$tutorial_id])) {
            $state[$tutorial_id]['status'] = 'complete';
            $state[$tutorial_id]['completed_at'] = current_time('mysql');
            $state[$tutorial_id]['updated_at'] = current_time('mysql');
            
            update_user_meta($user_id, $this->meta_key, $state);
            return $state[$tutorial_id];
        }
        
        return false;
    }
    
    /**
     * Close tutorial (hide widget but keep state)
     */
    public function close_tutorial($tutorial_id) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        $state = $this->get_user_state($user_id);
        if (isset($state[$tutorial_id])) {
            $current_step = $state[$tutorial_id]['current_step'] ?? 0;
            $state[$tutorial_id]['status'] = 'closed';
            $state[$tutorial_id]['closed_at'] = current_time('mysql');
            $state[$tutorial_id]['updated_at'] = current_time('mysql');
            
            update_user_meta($user_id, $this->meta_key, $state);
            return $state[$tutorial_id];
        }
        
        return false;
    }
    
    /**
     * Handle AJAX requests
     */
    public function handle_ajax() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->nonce_key)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'mac-interactive-tutorials')));
        }
        
        // Check permissions
        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Unauthorized.', 'mac-interactive-tutorials')));
        }
        
        $action = sanitize_text_field($_POST['action_type'] ?? '');
        $tutorial_id = absint($_POST['tutorial_id'] ?? 0);
        $step_index = isset($_POST['step_index']) ? intval($_POST['step_index']) : 0;
        $step_index = max(0, $step_index); // Ensure non-negative
        
        if (!$tutorial_id) {
            wp_send_json_error(array('message' => __('Tutorial ID is required.', 'mac-interactive-tutorials')));
        }
        
        // Validate step_index against actual steps count
        $steps = get_post_meta($tutorial_id, '_mac_tutorial_steps', true);
        $steps_count = !empty($steps) && is_array($steps) ? count($steps) : 0;
        if ($steps_count > 0) {
            $step_index = min($step_index, $steps_count - 1); // Clamp to valid range
        }
        
        switch ($action) {
            case 'start':
                // Pause all other tutorials
                $this->pause_all_tutorials();
                $result = $this->update_state($tutorial_id, 0, 'in-progress');
                wp_send_json_success(array('state' => $result));
                break;
                
            case 'update_step':
                $result = $this->update_state($tutorial_id, $step_index, 'in-progress');
                wp_send_json_success(array('state' => $result));
                break;
                
            case 'pause':
                $result = $this->pause_tutorial($tutorial_id);
                wp_send_json_success(array('state' => $result));
                break;
                
            case 'resume':
            case 'open':
                // Pause all other tutorials
                $this->pause_all_tutorials();
                $result = $this->resume_tutorial($tutorial_id);
                wp_send_json_success(array('state' => $result));
                break;
                
            case 'close':
                $result = $this->close_tutorial($tutorial_id);
                wp_send_json_success(array('state' => $result));
                break;
                
            case 'complete':
                $result = $this->complete_tutorial($tutorial_id);
                wp_send_json_success(array('state' => $result));
                break;
                
            default:
                wp_send_json_error(array('message' => __('Invalid action.', 'mac-interactive-tutorials')));
        }
    }
    
    /**
     * Pause all active tutorials
     */
    private function pause_all_tutorials() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        
        $state = $this->get_user_state($user_id);
        foreach ($state as $tutorial_id => $tutorial_state) {
            if (isset($tutorial_state['status']) && $tutorial_state['status'] === 'in-progress') {
                $current_step = $tutorial_state['current_step'] ?? 0;
                $this->update_state($tutorial_id, $current_step, 'pause');
            }
        }
    }
    
    /**
     * Get nonce
     */
    public function get_nonce() {
        return wp_create_nonce($this->nonce_key);
    }
}

