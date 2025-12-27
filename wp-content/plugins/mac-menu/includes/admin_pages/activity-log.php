<?php
// Activity Log Page for MAC Menu Plugin
if (!defined('ABSPATH')) {
    exit;
}

// Include restore functionality
include_once 'cat-restore.php';

// Ensure user has permission
if (!current_user_can('edit_dashboard')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

// Handle restore form submission - Restore entire table from old_data in activity_log
if (isset($_POST['action']) && $_POST['action'] === 'restore_category') {
    if (!wp_verify_nonce($_POST['nonce'], 'restore_category_' . $_POST['log_id'])) {
        wp_die('Security check failed');
    }
    
    $log_id = intval($_POST['log_id']);
    
    // Get the log entry
    global $wpdb;
    $log_table = $wpdb->prefix . 'mac_menu_activity_log';
    $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM $log_table WHERE id = %d", $log_id));
    
    if (!$log) {
        echo '<div class="notice notice-error"><p>Log entry not found.</p></div>';
        return;
    }
    
    // Check if log contains full table data (is_full_table = 1)
    if (empty($log->is_full_table) || empty($log->old_data)) {
        echo '<div class="notice notice-error"><p>Log entry does not contain full table data for restore.</p></div>';
        return;
    }
    
    // Decompress old_data from activity_log
    $activity_log_manager = new MacMenuActivityLog();
    $old_table_data = $activity_log_manager->decompressData($log->old_data);
    
    if (!$old_table_data || !is_array($old_table_data)) {
        echo '<div class="notice notice-error"><p>Failed to decompress old_data from log entry.</p></div>';
        return;
    }
    
    // Backup current table before restore (save to activity_log)
    $cat_menu_table = $wpdb->prefix . 'mac_cat_menu';
    $current_table_data = $wpdb->get_results("SELECT * FROM {$cat_menu_table}", ARRAY_A);
    $current_table_data = $current_table_data ?: array();
    
    // Begin transaction to ensure atomicity
    $wpdb->query('START TRANSACTION');
    
    try {
        // Truncate table (delete all current data)
        $wpdb->query("TRUNCATE TABLE {$cat_menu_table}");
        
        // Insert data from old_data
        if (!empty($old_table_data) && is_array($old_table_data)) {
            foreach ($old_table_data as $record) {
                // Insert each record
                $wpdb->insert($cat_menu_table, $record);
            }
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        // Log the restore action
        $hanoi_timezone = new DateTimeZone('Asia/Ho_Chi_Minh');
        $hanoi_datetime = new DateTime('now', $hanoi_timezone);
        $hanoi_timestamp = $hanoi_datetime->format('Y-m-d H:i:s');
        
        $record_count = count($old_table_data);
        $action_description = "Restored entire table from log #{$log_id} (before {$log->action_type})";
        
        // Save restore action to activity_log with old_data (current) and new_data (restored)
        log_activity('category_restore', $action_description, 'mac_cat_menu', $record_count, null, $current_table_data, $old_table_data, true);
        
        // Set success flag for JavaScript
        echo '<script>document.getElementById("restore-message").style.display = "block";</script>';
    } catch (Exception $e) {
        // Rollback if error occurs
        $wpdb->query('ROLLBACK');
        echo '<div class="notice notice-error"><p>Failed to restore table: ' . esc_html($e->getMessage()) . '</p></div>';
    }
}


// Ensure activity log table exists
force_create_activity_log_table();

/**
 * Check if log has full table data (can be restored)
 * Log with is_full_table = 1 and non-empty old_data can be restored
 */
function log_has_snapshot($log_id, $log_created_at) {
    global $wpdb;
    
    $log_table = $wpdb->prefix . 'mac_menu_activity_log';
    
    // Check if log has is_full_table = 1 and non-empty old_data
    $log = $wpdb->get_row($wpdb->prepare(
        "SELECT is_full_table, old_data FROM $log_table WHERE id = %d",
        $log_id
    ));
    
    if (!$log) {
        return false;
    }
    
    // If log has is_full_table = 1 and non-empty old_data, can restore
    if (!empty($log->is_full_table) && !empty($log->old_data)) {
        return true;
    }
    
    // Fallback: Check snapshot table (backward compatibility with old logs)
    if (!class_exists('MacMenuTableSnapshot')) {
        return false;
    }
    
    $snapshot_table = $wpdb->prefix . 'mac_menu_table_snapshots';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$snapshot_table'");
    if (!$table_exists) {
        return false;
    }
    
    // Find snapshot before action (created_at <= log->created_at)
    $old_snapshot = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $snapshot_table WHERE created_at <= %s ORDER BY created_at DESC LIMIT 1",
        $log_created_at
    ));
    
    // Find snapshot after action (created_at > log->created_at)
    $new_snapshot = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $snapshot_table WHERE created_at > %s ORDER BY created_at ASC LIMIT 1",
        $log_created_at
    ));
    
    // Only consider as having snapshot if BOTH snapshots exist (old and new) for comparison
    return ($old_snapshot && $new_snapshot);
}

// Handle pagination
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Handle filters
$action_type_filter = isset($_GET['action_type']) ? sanitize_text_field($_GET['action_type']) : '';
$date_filter = isset($_GET['date_filter']) ? sanitize_text_field($_GET['date_filter']) : '';
$user_filter = isset($_GET['user_filter']) ? sanitize_text_field($_GET['user_filter']) : '';

// Get logs
$logs = get_activity_logs($per_page, $offset, $action_type_filter, $date_filter, $user_filter);
$total_logs = get_activity_log_count($action_type_filter, $date_filter, $user_filter);
$total_pages = ceil($total_logs / $per_page);

// Get unique action types for filter
global $wpdb;
$table_name = $wpdb->prefix . 'mac_menu_activity_log';
$action_types = $wpdb->get_col("SELECT DISTINCT action_type FROM $table_name ORDER BY action_type");

// Get unique users for filter
$users = $wpdb->get_results("SELECT DISTINCT user_id, user_name FROM $table_name WHERE user_name IS NOT NULL AND user_name != '' ORDER BY user_name ASC");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">MAC Menu Activity Log</h1>
    
    
    <!-- Restore Success Message -->
    <div id="restore-message" class="notice notice-success" style="display: none; margin: 15px 0;">
        <p><strong>Restore successful!</strong> The entire table has been restored to the state before the change from snapshot.</p>
    </div>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" action="">
                <input type="hidden" name="page" value="mac-menu-activity-log">
                
                <select name="action_type" style="margin-right: 5px;">
                    <option value="">All Actions</option>
                    <?php foreach ($action_types as $type): ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($action_type_filter, $type); ?>>
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $type))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="date" name="date_filter" value="<?php echo esc_attr($date_filter); ?>" placeholder="Filter by date" style="margin-right: 5px; padding: 4px 8px;" title="Filter by date (YYYY-MM-DD)">
                
                <select name="user_filter" style="margin-right: 5px;">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo esc_attr($user->user_id); ?>" <?php selected($user_filter, $user->user_id); ?>>
                            <?php echo esc_html($user->user_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="submit" class="button" value="Filter">
                <?php if ($action_type_filter || $date_filter || $user_filter): ?>
                    <a href="<?php echo admin_url('admin.php?page=mac-menu-activity-log'); ?>" class="button" style="margin-left: 5px;">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="tablenav-pages">
            <?php if ($total_pages > 1): ?>
                <span class="displaying-num"><?php printf('%d items', $total_logs); ?></span>
                <span class="pagination-links">
                    <?php
                    $page_links = paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $page
                    ));
                    echo $page_links;
                    ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    
    <table class="wp-list-table widefat fixed striped mac-activity-log-table">
         <thead>
             <tr>
                 <th scope="col" class="manage-column column-date">Date/Time</th>
                 <th scope="col" class="manage-column column-action">Action</th>
                 <th scope="col" class="manage-column column-description">Description</th>
                 <th scope="col" class="manage-column column-records">Records</th>
                 <th scope="col" class="manage-column column-user">User</th>
                <th scope="col" class="manage-column column-actions">Actions</th>
             </tr>
         </thead>
        
        <tbody>
             <?php if (empty($logs)): ?>
                 <tr>
                     <td colspan="8">No activity logs found.</td>
                 </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="column-date" data-label="Date/Time">
                            <?php 
                            // Display Hanoi time (already stored in Hanoi timezone)
                            echo esc_html($log->created_at);
                            ?>
                        </td>
                        <td class="column-action" data-label="Action">
                            <?php 
                            $action_type = $log->action_type;
                            $action_display = ucfirst(str_replace('_', ' ', $action_type));
                            
                            // Define colors for each action type
                            $action_colors = [
                                'category_update' => 'background: linear-gradient(135deg, #ffc107, #ff8c00); color: #856404;',
                                'category_position_update' => 'background: linear-gradient(135deg, #ffc107, #ff8c00); color: #856404;',
                                'category_restore' => 'background: linear-gradient(135deg, #17a2b8, #138496); color: #ffffff; border: 2px solid #0c5460;',
                                'category_create' => 'background: linear-gradient(135deg, #28a745, #1e7e34); color: #ffffff;',
                                'category_delete' => 'background: linear-gradient(135deg, #dc3545, #c82333); color: #ffffff;',
                                'bulk_edit_replace' => 'background: linear-gradient(135deg, #6f42c1, #5a32a3); color: #ffffff;',
                            ];
                            
                            $style = isset($action_colors[$action_type]) ? $action_colors[$action_type] : 'background: #6c757d; color: #ffffff;';
                            ?>
                            <span class="action-type" style="<?php echo $style; ?> padding: 4px 5px; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; min-width: 80px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <?php echo esc_html($action_display); ?>
                            </span>
                        </td>
                         <td class="column-description" data-label="Description">
                             <div class="desc-wrap">
                                 <div class="desc-content is-collapsed action-description" 
                                      data-action="<?php echo esc_attr($log->action_type); ?>"
                                      data-log-id="<?php echo esc_attr($log->id); ?>">
                                    <?php 
                                    // Decode old_data and new_data once
                                    $old_data = $log->old_data ? json_decode($log->old_data, true) : null;
                                    $new_data = $log->new_data ? json_decode($log->new_data, true) : null;
                                    
                                    // Extract category name and ID from old_data or new_data
                                    $category_id = '';
                                    $category_name = '';
                                    
                                    if ($old_data) {
                                        if (isset($old_data['id'])) {
                                            $category_id = $old_data['id'];
                                        }
                                        if (isset($old_data['category_name']) && !empty($old_data['category_name'])) {
                                            $category_name = $old_data['category_name'];
                                        }
                                    }
                                    
                                    if (empty($category_name) && $new_data) {
                                        if (isset($new_data['category_name']) && !empty($new_data['category_name'])) {
                                            $category_name = $new_data['category_name'];
                                        }
                                        if (isset($new_data['id']) && empty($category_id)) {
                                            $category_id = $new_data['id'];
                                        }
                                    }
                                    
                                    // Fallback: get from affected_records if available
                                    if (empty($category_id) && !empty($log->affected_records)) {
                                        $category_id = $log->affected_records;
                                    }
                                    
                                    // Build category info display
                                    $category_info = '';
                                    if (!empty($category_id) || !empty($category_name)) {
                                        $category_info = '<div style="margin-bottom: 10px; padding: 8px; background: #f0f0f0; border-left: 3px solid #0073aa; border-radius: 3px;">';
                                        $category_info .= '<strong>Category Info:</strong><br>';
                                        if (!empty($category_id)) {
                                            $category_info .= 'ID: <strong>' . esc_html($category_id) . '</strong>';
                                        }
                                        if (!empty($category_name)) {
                                            if (!empty($category_id)) {
                                                $category_info .= ' | ';
                                            }
                                            $category_info .= 'Name: <strong>' . esc_html($category_name) . '</strong>';
                                        }
                                        $category_info .= '</div>';
                                    }
                                    
                                    // Check if log has snapshot
                                    $has_snapshot = log_has_snapshot($log->id, $log->created_at);
                                        
                                    // Display Category Info
                                            echo $category_info;
                                    
                                    // If has snapshot: only show Category Info, don't show old action_description
                                    // If no snapshot: show Category Info + action_description as before
                                    if (!$has_snapshot) {
                                        // No snapshot: show action_description as before
                                            if ($log->action_type === 'category_restore') {
                                                echo "<div class='restore-indicator'>";
                                                echo "<strong>Category has been restored!</strong><br>";
                                                echo "<small>Restored to previous state from activity log</small>";
                                                echo "</div>";
                                            } else {
                                                echo wp_kses_post($log->action_description);
                                        }
                                    } else {
                                        // Has snapshot: only show brief message
                                        if ($log->action_type === 'category_restore') {
                                            echo "<div class='restore-indicator'>";
                                            echo "<strong>Category has been restored!</strong><br>";
                                            echo "<small>Restored to previous state from activity log</small>";
                                            echo "</div>";
                                        } else {
                                            // Show brief message for logs with snapshot
                                            echo "<div style='color: #666; font-style: italic; margin-top: 8px;'>";
                                            echo "Change details will be displayed when clicking 'Show all'";
                                            echo "</div>";
                                        }
                                    }
                                    
                                    // Snapshot comparison will be loaded via AJAX when clicking "Show all"
                                    ?>
                                </div>
                                <button type="button" class="button-link toggle-desc" aria-expanded="false">Show all</button>
                            </div>
                        </td>
                        <td class="column-records" data-label="Records">
                            <?php echo esc_html($log->affected_records ?: '-'); ?>
                        </td>
                        <td class="column-user" data-label="User">
                            <?php echo esc_html($log->user_name ?: 'System'); ?>
                        </td>
                         <td class="column-actions" data-label="Actions">
                             <?php 
                             // Only show restore button if log has snapshot (can restore entire table)
                             $has_snapshot = log_has_snapshot($log->id, $log->created_at);
                             
                             // List of action types that can be restored (must have is_full_table = 1 and old_data)
                             $restorable_actions = [
                                 'category_create', 
                                 'category_update', 
                                 'category_delete', 
                                 'category_position_update',
                                 'bulk_edit_replace'
                             ];
                             
                             if ($has_snapshot && in_array($log->action_type, $restorable_actions)) {
                                 // Show restore form - restore entire table from snapshot
                                     echo '<form method="post" style="display:inline;">';
                                     echo '<input type="hidden" name="action" value="restore_category">';
                                     echo '<input type="hidden" name="log_id" value="' . esc_attr($log->id) . '">';
                                     echo '<input type="hidden" name="nonce" value="' . wp_create_nonce('restore_category_' . $log->id) . '">';
                                 
                                 // Custom confirm message for bulk_edit_replace
                                 $confirm_message = ($log->action_type === 'bulk_edit_replace') 
                                     ? 'Are you sure you want to restore the entire table to the state before this bulk edit? This will overwrite all current data.'
                                     : 'Are you sure you want to restore the entire table to the state before this change? This will overwrite all current data.';
                                 
                                 echo '<button type="submit" class="restore-button" onclick="return confirm(\'' . esc_js($confirm_message) . '\')">Restore</button>';
                                     echo '</form>';
                             } else {
                                 echo '<span class="no-restore">N/A</span>';
                             }
                             ?>
                         </td>
                     </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="tablenav bottom">
        <div class="alignleft actions">
            <form method="get" action="">
                <input type="hidden" name="page" value="mac-menu-activity-log">
                
                <select name="action_type" style="margin-right: 5px;">
                    <option value="">All Actions</option>
                    <?php foreach ($action_types as $type): ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($action_type_filter, $type); ?>>
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $type))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="date" name="date_filter" value="<?php echo esc_attr($date_filter); ?>" placeholder="Filter by date" style="margin-right: 5px; padding: 4px 8px;" title="Filter by date (YYYY-MM-DD)">
                
                <select name="user_filter" style="margin-right: 5px;">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo esc_attr($user->user_id); ?>" <?php selected($user_filter, $user->user_id); ?>>
                            <?php echo esc_html($user->user_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="submit" class="button" value="Filter">
                <?php if ($action_type_filter || $date_filter || $user_filter): ?>
                    <a href="<?php echo admin_url('admin.php?page=mac-menu-activity-log'); ?>" class="button" style="margin-left: 5px;">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="tablenav-pages">
            <?php if ($total_pages > 1): ?>
                <span class="displaying-num"><?php printf('%d items', $total_logs); ?></span>
                <span class="pagination-links">
                    <?php echo $page_links; ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Enqueue CSS and JS for Activity Log
wp_enqueue_style('mac-activity-log-css', plugin_dir_url(__FILE__) . '../../admin/css/activity-log.css', array(), '1.0.0');
wp_enqueue_script('mac-activity-log-js', plugin_dir_url(__FILE__) . '../../admin/js/activity-log.js', array('jquery'), '1.0.0', true);

// Localize script with nonce and ajaxurl
wp_localize_script('mac-activity-log-js', 'macActivityLog', array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('mac_activity_log_nonce')
));
?>

