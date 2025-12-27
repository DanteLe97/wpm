<?php
/**
 * Database Class
 * 
 * Handles custom table creation for tutorial sites (source site only)
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Tutorial_Database {
    
    /**
     * Table name
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mac_tutorial_sites';
    }
    
    /**
     * Get table name
     */
    public function get_table_name() {
        return $this->table_name;
    }
    
    /**
     * Create table
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_url varchar(255) NOT NULL,
            api_key varchar(12) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY site_url (site_url),
            UNIQUE KEY api_key (api_key),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Drop table
     */
    public function drop_table() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
    }
    
    /**
     * Insert site
     */
    public function insert_site($site_url) {
        global $wpdb;
        
        // Check if site already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE site_url = %s",
            $site_url
        ), ARRAY_A);
        
        if ($existing) {
            return array(
                'success' => false,
                'message' => 'Site already exists',
                'data' => $existing
            );
        }
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'site_url' => sanitize_text_field($site_url),
                'status' => 'pending'
            ),
            array('%s', '%s')
        );
        
        if ($result) {
            return array(
                'success' => true,
                'id' => $wpdb->insert_id,
                'data' => $this->get_site_by_id($wpdb->insert_id)
            );
        }
        
        return array(
            'success' => false,
            'message' => $wpdb->last_error
        );
    }
    
    /**
     * Get site by ID
     */
    public function get_site_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ), ARRAY_A);
    }
    
    /**
     * Get site by URL
     */
    public function get_site_by_url($site_url) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE site_url = %s",
            $site_url
        ), ARRAY_A);
    }
    
    /**
     * Get site by API key
     */
    public function get_site_by_api_key($api_key) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE api_key = %s",
            $api_key
        ), ARRAY_A);
    }
    
    /**
     * Get all sites
     */
    public function get_all_sites($status = null) {
        global $wpdb;
        
        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE status = %s ORDER BY created_at DESC",
                $status
            ), ARRAY_A);
        }
        
        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC",
            ARRAY_A
        );
    }
    
    /**
     * Update site status
     */
    public function update_site_status($id, $status, $api_key = null) {
        global $wpdb;
        
        $data = array('status' => $status);
        $format = array('%s');
        
        if ($api_key !== null) {
            $data['api_key'] = $api_key;
            $format[] = '%s';
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $id),
            $format,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete site
     */
    public function delete_site($id) {
        global $wpdb;
        return $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Generate API key (12 characters)
     */
    public function generate_api_key() {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $api_key = '';
        
        do {
            $api_key = '';
            for ($i = 0; $i < 12; $i++) {
                $api_key .= $characters[rand(0, strlen($characters) - 1)];
            }
            
            // Check if key already exists
            $existing = $this->get_site_by_api_key($api_key);
        } while ($existing);
        
        return $api_key;
    }
}

