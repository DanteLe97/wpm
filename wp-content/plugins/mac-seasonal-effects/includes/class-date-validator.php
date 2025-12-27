<?php
/**
 * Date Validator Class
 * 
 * Validates date ranges and checks if animation should be active
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Date_Validator {
    
    /**
     * Check if current date is within animation date range
     */
    public function is_date_valid($start_date, $end_date = null) {
        $today = date('Y-m-d');
        
        // Check start date
        if ($today < $start_date) {
            return false;
        }
        
        // Check end date (if provided)
        // Disable on end date or after (today >= end_date)
        if (!empty($end_date) && trim($end_date) !== '') {
            // Use string comparison for Y-m-d format (works correctly)
            if ($today >= $end_date) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate date format
     */
    public function validate_date($date) {
        if (empty($date)) {
            return false;
        }
        
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Check if animation should be auto-disabled (on or past end date)
     */
    public function should_auto_disable($end_date) {
        if (empty($end_date) || trim($end_date) === '') {
            return false; // No end date, don't auto-disable
        }
        
        $today = date('Y-m-d');
        // Disable on end date or after (today >= end_date)
        return $today >= $end_date;
    }
}

