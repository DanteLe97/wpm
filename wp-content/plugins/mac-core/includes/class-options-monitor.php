<?php
/**
 * MAC Core Options Monitor
 * 
 * Monitors all option changes related to licenses and domains
 */

namespace MAC_Core;

class Options_Monitor {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook into WordPress option update functions
        add_action('update_option', array($this, 'log_option_update'), 10, 3);
        add_action('add_option', array($this, 'log_option_add'), 10, 2);
        add_action('delete_option', array($this, 'log_option_delete'), 10, 1);
        
        // Monitor specific options
        $this->monitor_specific_options();
    }
    
    /**
     * Monitor specific options that we care about
     */
    private function monitor_specific_options() {
        $critical_options = array(
            'mac_domain_valid_key',
            'mac_domain_valid_status',
            'mac_menu_github_key',
            'mac_license_key',
            'mac_core_api_key'
        );
        
        foreach ($critical_options as $option) {
            add_action("update_option_{$option}", array($this, 'log_critical_option_update'), 10, 3);
        }
    }
    
    /**
     * Log all option updates
     */
    public function log_option_update($option, $old_value, $new_value) {
        // Only log critical options
        $critical_options = array(
            'mac_domain_valid_key',
            'mac_domain_valid_status', 
            'mac_menu_github_key',
            'mac_license_key',
            'mac_core_api_key'
        );
        
        // if (in_array($option, $critical_options)) {
        //     // // error_log(...MAC...);
        //     error_log("MAC Core Options Monitor: Timestamp: " . date('Y-m-d H:i:s'));
        //     error_log("MAC Core Options Monitor: Old value: " . $this->format_value($old_value));
        //     error_log("MAC Core Options Monitor: New value: " . $this->format_value($new_value));
        //     error_log("MAC Core Options Monitor: Backtrace: " . $this->get_backtrace());
        //     // error_log(...MAC...);
        //     // error_log(...MAC...);
        // }
    }
    
    /**
     * Log critical option updates with more detail
     */
    public function log_critical_option_update($old_value, $new_value, $option) {
        $sensitive_options = array(
            'mac_domain_valid_key',
            'mac_menu_github_key',
            'mac_license_key',
            'mac_core_api_key'
        );

        $old_to_log = in_array($option, $sensitive_options, true)
            ? $this->mask_secret($old_value)
            : $this->format_value($old_value);
        $new_to_log = in_array($option, $sensitive_options, true)
            ? $this->mask_secret($new_value)
            : $this->format_value($new_value);

        // error_log(...MAC...);
        error_log("Option: {$option}");
        error_log("Timestamp: " . date('Y-m-d H:i:s'));
        error_log("Old value: " . $old_to_log);
        error_log("New value: " . $new_to_log);
        error_log("Backtrace: " . $this->get_backtrace());
        error_log("Request URI: " . $_SERVER['REQUEST_URI']);
        error_log("User agent: " . $_SERVER['HTTP_USER_AGENT']);
        error_log("User ID: " . get_current_user_id());
        error_log("=== End Critical Option Update ===");
    }
    
    /**
     * Log option additions
     */
    public function log_option_add($option, $value) {
        $critical_options = array(
            'mac_domain_valid_key',
            'mac_domain_valid_status',
            'mac_menu_github_key',
            'mac_license_key',
            'mac_core_api_key'
        );
        
    //     if (in_array($option, $critical_options)) {
    //         // // error_log(...MAC...);
    //         error_log("MAC Core Options Monitor: Timestamp: " . date('Y-m-d H:i:s'));
    //         error_log("MAC Core Options Monitor: Value: " . $this->format_value($value));
    //         error_log("MAC Core Options Monitor: Backtrace: " . $this->get_backtrace());
    //     }
    }
    
    /**
     * Log option deletions
     */
    public function log_option_delete($option) {
        $critical_options = array(
            'mac_domain_valid_key',
            'mac_domain_valid_status',
            'mac_menu_github_key',
            'mac_license_key',
            'mac_core_api_key'
        );
        
        // if (in_array($option, $critical_options)) {
        //     // // error_log(...MAC...);
        //     error_log("MAC Core Options Monitor: Timestamp: " . date('Y-m-d H:i:s'));
        //     error_log("MAC Core Options Monitor: Backtrace: " . $this->get_backtrace());
        // }
    }
    
    /**
     * Format value for logging (hide sensitive data)
     */
    private function format_value($value) {
        if (is_null($value)) {
            return 'NULL';
        }
        
        if (is_string($value)) {
            if (strlen($value) > 50) {
                return substr($value, 0, 10) . '...' . substr($value, -10) . ' (length: ' . strlen($value) . ')';
            }
            return $value;
        }
        
        if (is_array($value)) {
            return 'Array: ' . json_encode($value);
        }
        
        return (string) $value;
    }
    
    /**
     * Get backtrace for debugging
     */
    private function get_backtrace() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        $trace_string = '';
        foreach ($backtrace as $i => $trace) {
            if ($i === 0) continue; // Skip current function
            $trace_string .= "\n" . ($i) . ". " . 
                (isset($trace['class']) ? $trace['class'] . '::' : '') . 
                $trace['function'] . '() in ' . 
                (isset($trace['file']) ? basename($trace['file']) : 'unknown') . 
                ':' . (isset($trace['line']) ? $trace['line'] : 'unknown');
        }
        return $trace_string;
    }
    
    /**
     * Get current status of all critical options
     */
    public function get_critical_options_status() {
        $options = array(
            'mac_domain_valid_key' => get_option('mac_domain_valid_key'),
            'mac_domain_valid_status' => get_option('mac_domain_valid_status'),
            'mac_menu_github_key' => get_option('mac_menu_github_key'),
            'mac_license_key' => get_option('mac_license_key'),
            'mac_core_api_key' => get_option('mac_core_api_key')
        );
        
        $status = array();
        foreach ($options as $option => $value) {
            $status[$option] = array(
                'exists' => $value !== false,
                'value' => $this->format_value($value),
                'length' => is_string($value) ? strlen($value) : 0
            );
        }
        
        return $status;
    }

    private function mask_secret($value) {
        if (!is_string($value) || $value === '') {
            return '(hidden)';
        }
        $len = strlen($value);
        if ($len <= 6) {
            return str_repeat('*', $len);
        }
        return substr($value, 0, 2) . str_repeat('*', max(0, $len - 4)) . substr($value, -2);
    }
}

