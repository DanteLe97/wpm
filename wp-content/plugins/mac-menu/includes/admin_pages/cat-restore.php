<?php
// Category Restore functionality for MAC Menu Plugin
if (!defined('ABSPATH')) {
    exit;
}

class Mac_Cat_Restore {
    
    public function __construct() {
        // Constructor
    }
    
    /**
     * Register AJAX actions
     */
    public static function register_ajax_actions() {
        add_action('wp_ajax_mac_get_backup_data', [__CLASS__, 'handle_get_backup_data']);
        add_action('wp_ajax_mac_restore_category', [__CLASS__, 'handle_restore_request']);
        
        // Test AJAX handler
        add_action('wp_ajax_mac_test_ajax', [__CLASS__, 'handle_test_ajax']);
    }
    
    /**
     * Test AJAX handler
     */
    public static function handle_test_ajax() {
        wp_send_json_success(['message' => 'Test AJAX working']);
    }
    
    /**
     * Handle get backup data AJAX request
     */
    public static function handle_get_backup_data() {
        // Skip nonce check for now to debug
        if (false && (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_restore_nonce'))) {
            wp_send_json_error('Security check failed - Invalid nonce');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $category_id = intval($_POST['category_id']);
        
        if (empty($category_id)) {
            wp_send_json_error('Invalid category ID');
            return;
        }
        
        $objmacMenu = new macMenu();
        $backup_data = $objmacMenu->get_cat_backup($category_id);
        
        if (empty($backup_data)) {
            wp_send_json_error('No backup data found for this category');
            return;
        }
        
        wp_send_json_success($backup_data);
    }
    
    /**
     * Handle restore request AJAX
     */
    public static function handle_restore_request() {
        if (!wp_verify_nonce($_POST['nonce'], 'mac_restore_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $category_id = intval($_POST['category_id']);
        $backup_timestamp = sanitize_text_field($_POST['backup_timestamp']);
        
        if (empty($category_id)) {
            wp_send_json_error('Invalid category ID');
            return;
        }
        
        $objmacMenu = new macMenu();
        $backup_data = $objmacMenu->get_cat_backup($category_id);
        
        if (empty($backup_data)) {
            wp_send_json_error('No backup data found for this category');
            return;
        }
        
        if (!empty($backup_timestamp) && $backup_data['backup_timestamp'] !== $backup_timestamp) {
            wp_send_json_error('Backup timestamp mismatch');
            return;
        }
        
        $restore_instance = new self();
        $result = $restore_instance->restore_category($category_id, $backup_data);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => 'Category restored successfully',
                'category_name' => $backup_data['category_name'],
                'restored_fields' => $result['restored_fields']
            ]);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Restore category from backup data
     */
    public function restore_category($category_id, $backup_data) {
        try {
            $objmacMenu = new macMenu();
            
            // Prepare restore data (exclude backup_data field to preserve it)
            $restore_data = $backup_data;
            unset($restore_data['backup_timestamp']); // Remove timestamp from restore data
            
            // Get current data for comparison
            $current_data = $objmacMenu->get_cat_by_id($category_id);
            if (!$current_data) {
                return [
                    'success' => false,
                    'message' => 'Category not found'
                ];
            }
            
            // Track which fields are being restored
            $restored_fields = [];
            $current = $current_data[0];
            
            foreach ($restore_data as $field => $value) {
                if ($field !== 'id' && isset($current->$field) && $current->$field !== $value) {
                    $restored_fields[] = $field;
                }
            }
            
            // Perform the restore
            $result = $objmacMenu->update_cat($category_id, $restore_data);
            
            if ($result) {
                // Log the restore action
                $this->log_restore_action($category_id, $backup_data, $restored_fields);
                
                return [
                    'success' => true,
                    'restored_fields' => $restored_fields
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to restore category data'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error restoring category: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if backup data exists for category
     */
    public function has_backup_data($category_id) {
        $objmacMenu = new macMenu();
        $backup_data = $objmacMenu->get_cat_backup($category_id);
        return !empty($backup_data);
    }
    
    /**
     * Render restore button HTML
     */
    public static function render_restore_button($category_id, $backup_timestamp = '') {
        return sprintf(
            '<button type="button" class="button mac-restore-btn" data-category-id="%d" data-backup-time="%s" title="Restore from backup">
                <span class="dashicons dashicons-undo"></span> Restore
            </button>',
            $category_id,
            esc_attr($backup_timestamp)
        );
    }
    
    /**
     * Log restore action to activity log
     */
    private function log_restore_action($category_id, $backup_data, $restored_fields) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mac_menu_activity_log';
        
        $log_data = [
            'action_type' => 'category_restore',
            'action_description' => sprintf(
                'Category restored from backup. Restored fields: %s',
                implode(', ', $restored_fields)
            ),
            'affected_table' => 'mac_cat_menu',
            'affected_records' => $category_id,
            'old_data' => json_encode($backup_data),
            'new_data' => null,
            'user_name' => wp_get_current_user()->display_name,
            'user_id' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'error_message' => null,
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert($table_name, $log_data);
    }
}