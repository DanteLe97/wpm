<?php
/**
 * View hiển thị danh sách logs
 */

if (!defined('ABSPATH')) {
    exit;
}

// Format action badge
function get_action_badge($action) {
    $badges = array(
        'insert' => array('label' => 'Created', 'class' => 'success'),
        'update' => array('label' => 'Updated', 'class' => 'info'),
        'delete' => array('label' => 'Deleted', 'class' => 'danger'),
        'point_update' => array('label' => 'Point Updated', 'class' => 'warning')
    );
    
    if (isset($badges[$action])) {
        return $badges[$action];
    }
    
    return array('label' => ucfirst($action), 'class' => 'default');
}

// Format log data for display
function format_log_data($log) {
    $formatted = $log;
    
    // Decode JSON data
    if (!empty($log['old_data'])) {
        $formatted['old_data'] = json_decode($log['old_data'], true);
    }
    
    if (!empty($log['new_data'])) {
        $formatted['new_data'] = json_decode($log['new_data'], true);
    }
    
    if (!empty($log['changes'])) {
        $formatted['changes'] = json_decode($log['changes'], true);
    }
    
    return $formatted;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Logs</h1>
    <hr class="wp-header-end">
    
    <!-- Search and Filter Form -->
    <div class="mac-referral-filters">
        <form method="get" action="" class="search-form">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            
            <p class="search-box">
                <label class="screen-reader-text" for="log-search-input">Search Logs:</label>
                <input type="search" 
                       id="log-search-input" 
                       name="s" 
                       value="<?php echo esc_attr($search); ?>" 
                       placeholder="Search by Referral ID, User, or Data...">
                <input type="submit" class="button" value="Search Logs">
            </p>
            
            <div class="alignleft actions">
                <label for="action-filter" class="screen-reader-text">Filter by action</label>
                <select name="action_filter" id="action-filter">
                    <option value="">All Actions</option>
                    <option value="insert" <?php selected($action_filter, 'insert'); ?>>Created</option>
                    <option value="update" <?php selected($action_filter, 'update'); ?>>Updated</option>
                    <option value="delete" <?php selected($action_filter, 'delete'); ?>>Deleted</option>
                    <option value="point_update" <?php selected($action_filter, 'point_update'); ?>>Point Updated</option>
                </select>
                <input type="submit" class="button" value="Filter">
                
                <?php if (!empty($search) || !empty($action_filter)): ?>
                    <a href="<?php echo admin_url('admin.php?page=' . esc_attr($_GET['page'])); ?>" class="button">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Pagination Form -->
    <form method="get" action="" id="per-page-form-logs" class="per-page-form">
        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
        <?php if (!empty($search)): ?>
            <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
        <?php endif; ?>
        <?php if (!empty($action_filter)): ?>
            <input type="hidden" name="action_filter" value="<?php echo esc_attr($action_filter); ?>">
        <?php endif; ?>
        <label for="per-page-selector-logs">Items per page:</label>
        <select name="per_page" id="per-page-selector-logs" onchange="document.getElementById('per-page-form-logs').submit();">
            <option value="25" <?php selected($per_page, 25); ?>>25</option>
            <option value="50" <?php selected($per_page, 50); ?>>50</option>
            <option value="100" <?php selected($per_page, 100); ?>>100</option>
            <option value="200" <?php selected($per_page, 200); ?>>200</option>
        </select>
    </form>
    
    <!-- Logs Table -->
    <div class="mac-referral-table-wrapper">
        <table class="wp-list-table widefat fixed striped table-view-list tb-actions-sticky">
        <thead>
            <tr>
                <th scope="col" class="column-id">ID</th>
                <th scope="col" class="column-referral-id">Referral ID</th>
                <th scope="col" class="column-action">Action</th>
                <th scope="col" class="column-user">User</th>
                <th scope="col" class="column-date">Date</th>
                <th scope="col" class="column-details column-actions">Details</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="6" class="no-items">No logs found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <?php 
                    $formatted_log = format_log_data($log);
                    $action_badge = get_action_badge($log['action']);
                    $log_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['log_date']));
                    ?>
                    <tr>
                        <td class="column-id"><?php echo esc_html($log['id']); ?></td>
                        <td class="column-referral-id">
                            <a href="<?php echo admin_url('admin.php?page=mac-referral'); ?>" title="View Referral">
                                <?php echo esc_html($log['referral_id']); ?>
                            </a>
                        </td>
                        <td class="column-action">
                            <span class="action-badge action-badge-<?php echo esc_attr($action_badge['class']); ?>">
                                <?php echo esc_html($action_badge['label']); ?>
                            </span>
                        </td>
                        <td class="column-user">
                            <?php echo esc_html($log['user_name'] ? $log['user_name'] : 'N/A'); ?>
                        </td>
                        <td class="column-date"><?php echo esc_html($log_date); ?></td>
                        <td class="column-details column-actions">
                            <?php if ($log['action'] === 'point_update'): ?>
                                <?php if (isset($log['old_point']) && isset($log['new_point'])): ?>
                                    Points: <?php echo esc_html($log['old_point']); ?> → <?php echo esc_html($log['new_point']); ?>
                                    <?php if (isset($log['point_change'])): ?>
                                        (<?php echo $log['point_change'] > 0 ? '+' : ''; ?><?php echo esc_html($log['point_change']); ?>)
                                    <?php endif; ?>
                                    <br>
                                    <a href="#" class="view-log-details-link" data-log-id="<?php echo esc_attr($log['id']); ?>" style="font-size: 12px; color: #2271b1; text-decoration: underline;">View Details</a>
                                <?php endif; ?>
                            <?php elseif ($log['action'] === 'update' && !empty($formatted_log['changes'])): ?>
                                <?php 
                                $changes = $formatted_log['changes'];
                                $change_count = count($changes);
                                ?>
                                <a href="#" class="view-log-details-link" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                    <?php echo sprintf('%d field(s) changed', $change_count); ?>
                                </a>
                            <?php elseif ($log['action'] === 'insert' || $log['action'] === 'delete'): ?>
                                <a href="#" class="view-log-details-link" data-log-id="<?php echo esc_attr($log['id']); ?>" style="font-size: 12px; color: #2271b1; text-decoration: underline;">View Details</a>
                            <?php else: ?>
                                <a href="#" class="view-log-details-link" data-log-id="<?php echo esc_attr($log['id']); ?>" style="font-size: 12px; color: #2271b1; text-decoration: underline;">View Details</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $base_url = admin_url('admin.php?page=' . esc_attr($_GET['page']));
                $query_params = array();
                if (!empty($search)) {
                    $query_params['s'] = $search;
                }
                if (!empty($action_filter)) {
                    $query_params['action_filter'] = $action_filter;
                }
                if ($per_page != 50) {
                    $query_params['per_page'] = $per_page;
                }
                
                $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
                
                // Previous page
                if ($current_page > 1): 
                    $prev_url = $base_url . '&paged=' . ($current_page - 1) . $query_string;
                ?>
                    <a class="prev-page button" href="<?php echo esc_url($prev_url); ?>">
                        <span class="screen-reader-text">Previous page</span>
                        <span aria-hidden="true">‹</span>
                    </a>
                <?php endif; ?>
                
                <!-- Page numbers -->
                <span class="paging-input">
                    <label for="current-page-selector-logs" class="screen-reader-text">Current Page</label>
                    <span class="tablenav-paging-text">
                        <span class="current-page"><?php echo esc_html($current_page); ?></span>
                        of
                        <span class="total-pages"><?php echo esc_html($total_pages); ?></span>
                    </span>
                </span>
                
                <!-- Next page -->
                <?php if ($current_page < $total_pages): 
                    $next_url = $base_url . '&paged=' . ($current_page + 1) . $query_string;
                ?>
                    <a class="next-page button" href="<?php echo esc_url($next_url); ?>">
                        <span class="screen-reader-text">Next page</span>
                        <span aria-hidden="true">›</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Log Details Modal -->
    <div id="mac-referral-log-modal" class="mac-referral-modal" style="display: none;">
        <div class="mac-referral-modal-content">
            <div class="mac-referral-modal-header">
                <h2>Log Details</h2>
                <button type="button" class="mac-referral-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="mac-referral-modal-body" id="log-details-content">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

