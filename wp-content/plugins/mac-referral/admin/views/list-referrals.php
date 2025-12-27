<?php
/**
 * Trang danh sách referral
 */

if (!defined('ABSPATH')) {
    exit;
}

// Lấy dữ liệu phân trang và các biến cần thiết
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
// Đảm bảo per_page hợp lệ (chỉ cho phép các giá trị: 50, 100, 200)
$allowed_per_page = array(50, 100, 200);
if (!in_array($per_page, $allowed_per_page)) {
    $per_page = 50;
}
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

// Hiển thị thông báo (đã được xử lý trong class-admin.php)
// settings_errors('mac_referral_messages');

?>

<div class="wrap mac-referral-wrap">
    <h1 class="wp-heading-inline">Manage Referrals</h1>
    <button type="button" class="page-title-action" id="mac-referral-add-new">
        Add New
    </button>
    <hr class="wp-header-end">
    
    <!-- Add Phone Referral Quick Action -->
    <!-- <div class="mac-referral-quick-action">
        <button type="button" 
                class="button button-primary" 
                id="mac-referral-quick-add-btn">
            Add Phone Referral (+10 points)
        </button>
        <p class="description" style="margin: 10px 0 0 0;">
            Click to open popup for adding points to referrer
        </p>
    </div> -->
    
    <!-- Search Form -->
    <div class="mac-referral-search" style="margin: 20px 0;">
        <form method="get" action="" style="display: flex; align-items: center; gap: 10px;">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">
            <?php if (isset($_GET['per_page']) && $_GET['per_page'] != 50): ?>
                <input type="hidden" name="per_page" value="<?php echo esc_attr($per_page); ?>">
            <?php endif; ?>
            <input type="search" 
                   name="s" 
                   id="mac-referral-search-input"
                   value="<?php echo esc_attr($search); ?>" 
                   placeholder="Search by name, email or phone number..."
                   class="regular-text" 
                   style="width: 300px;">
            <button type="submit" class="button">Search</button>
            <?php if (!empty($search)): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mac-referral' . ($per_page != 50 ? '&per_page=' . $per_page : ''))); ?>" class="button">Clear Filter</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Bulk Actions Form -->
    <div class="tablenav top">
        <div class="alignleft actions bulkactions">
            <div style="display: inline-block;">
                <select id="bulk-action-selector" style="margin-right: 5px;">
                    <option value="">Select Action</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="button" class="button action" id="mac-referral-do-bulk-action">
                    Apply
                </button>
            </div>
        </div>
        
        <div class="alignleft actions">
            <label for="per-page-selector" class="screen-reader-text">Items per page</label>
            <form method="get" action="" id="per-page-form" style="display: inline-block;">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
                <?php endif; ?>
                <select name="per_page" id="per-page-selector" onchange="document.getElementById('per-page-form').submit();" style="margin-right: 10px;">
                    <option value="50" <?php selected($per_page, 50); ?>>50</option>
                    <option value="100" <?php selected($per_page, 100); ?>>100</option>
                    <option value="200" <?php selected($per_page, 200); ?>>200</option>
                </select>
            </form>
            <!-- <span class="displaying-num" style="margin-left: 10px;">
                <?php echo sprintf('%d item(s)', $total_items); ?>
            </span> -->
        </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php 
                    $start = ($current_page - 1) * $per_page + 1;
                    $end = min($current_page * $per_page, $total_items);
                    echo sprintf('%d-%d of %d', $start, $end, $total_items);
                    ?>
                </span>
                <span class="pagination-links">
                    <?php
                    $base_url = admin_url('admin.php?page=mac-referral');
                    if (!empty($search)) {
                        $base_url .= '&s=' . urlencode($search);
                    }
                    // Luôn thêm per_page vào URL để giữ nguyên khi chuyển trang
                    $base_url .= '&per_page=' . $per_page;
                    
                    // First page
                    if ($current_page > 1) {
                        echo '<a class="first-page" href="' . esc_url($base_url . '&paged=1') . '">
                            <span class="screen-reader-text">First page</span>
                            <span aria-hidden="true">&laquo;</span>
                        </a>';
                        echo '<a class="prev-page" href="' . esc_url($base_url . '&paged=' . ($current_page - 1)) . '">
                            <span class="screen-reader-text">Previous page</span>
                            <span aria-hidden="true">&lsaquo;</span>
                        </a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan" aria-hidden="true">&laquo;</span>';
                        echo '<span class="tablenav-pages-navspan" aria-hidden="true">&lsaquo;</span>';
                    }
                    
                    // Current page info
                    echo '<span class="paging-input">
                        <label for="current-page-selector" class="screen-reader-text">Current page</label>
                        <span class="current-page" id="current-page-selector">' . $current_page . '</span>
                        <span class="tablenav-paging-text"> / ' . $total_pages . '</span>
                    </span>';
                    
                    // Next/Last page
                    if ($current_page < $total_pages) {
                        echo '<a class="next-page" href="' . esc_url($base_url . '&paged=' . ($current_page + 1)) . '">
                            <span class="screen-reader-text">Next page</span>
                            <span aria-hidden="true">&rsaquo;</span>
                        </a>';
                        echo '<a class="last-page" href="' . esc_url($base_url . '&paged=' . $total_pages) . '">
                            <span class="screen-reader-text">Last page</span>
                            <span aria-hidden="true">&raquo;</span>
                        </a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan" aria-hidden="true">&rsaquo;</span>';
                        echo '<span class="tablenav-pages-navspan" aria-hidden="true">&raquo;</span>';
                    }
                    ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Bulk Actions Form - Chứa checkboxes -->
        <form method="post" action="" id="mac-referral-bulk-form">
            <?php wp_nonce_field('mac_referral_action', 'mac_referral_nonce'); ?>
            <input type="hidden" name="mac_referral_action" value="delete_referrals">
            <input type="hidden" name="bulk_action" id="bulk-action-hidden" value="">
            
            <div class="mac-referral-list">
                <div class="mac-referral-table-wrapper">
                    <table class="wp-list-table widefat fixed striped tb-actions-sticky">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="mac-referral-select-all" />
                            </td>
                            <th style="width: 50px;">ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone Number</th>
                            <th>Referrer Phone</th>
                            <th style="width: 250px;">Points</th>
                            <th style="width: 150px;">Created Date</th>
                            <th class="column-actions" style="width: 170px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($referrals)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 20px;">
                                <?php if (!empty($search)): ?>
                                    No results found for "<?php echo esc_html($search); ?>"
                                <?php else: ?>
                                    No data available. Please add your first referral.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($referrals as $referral): ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="referral_ids[]" value="<?php echo esc_attr($referral['id']); ?>" class="mac-referral-checkbox" />
                                </th>
                            <td><?php echo esc_html($referral['id']); ?></td>
                            <td><?php echo esc_html($referral['fullname']); ?></td>
                            <td><?php echo esc_html($referral['email']); ?></td>
                            <td>
                                <?php 
                                $phone = $referral['phone'];
                                // Format số điện thoại nếu đủ 10 số
                                if (strlen(preg_replace('/\D/', '', $phone)) === 10) {
                                    $phone_numbers = preg_replace('/\D/', '', $phone);
                                    $phone = '(' . substr($phone_numbers, 0, 3) . ') ' . substr($phone_numbers, 3, 3) . '-' . substr($phone_numbers, 6);
                                }
                                echo esc_html($phone); 
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($referral['phone_referral'])): ?>
                                    <?php 
                                    $phone_ref = $referral['phone_referral'];
                                    // Format số điện thoại nếu đủ 10 số
                                    if (strlen(preg_replace('/\D/', '', $phone_ref)) === 10) {
                                        $phone_ref_numbers = preg_replace('/\D/', '', $phone_ref);
                                        $phone_ref = '(' . substr($phone_ref_numbers, 0, 3) . ') ' . substr($phone_ref_numbers, 3, 3) . '-' . substr($phone_ref_numbers, 6);
                                    }
                                    ?>
                                    <a href="#" 
                                       class="mac-referral-referral-link" 
                                       data-phone="<?php echo esc_attr(preg_replace('/\D/', '', $referral['phone_referral'])); ?>"
                                       title="Click to view referrer information">
                                        <?php echo esc_html($phone_ref); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="point-display" data-id="<?php echo esc_attr($referral['id']); ?>">
                                    <?php echo esc_html($referral['point']); ?>
                                </span>
                                <div class="point-control" style="display: none;">
                                    <input type="number" 
                                           class="point-change-input" 
                                           data-id="<?php echo esc_attr($referral['id']); ?>"
                                           placeholder="Enter points" 
                                           min="0"
                                           step="1">
                                    <button type="button" 
                                            class="button button-small point-add-btn" 
                                            data-id="<?php echo esc_attr($referral['id']); ?>"
                                            title="Add points">
                                        +
                                    </button>
                                    <button type="button" 
                                            class="button button-small point-subtract-btn" 
                                            data-id="<?php echo esc_attr($referral['id']); ?>"
                                            title="Subtract points">
                                        -
                                    </button>
                                    <button type="button" 
                                            class="button button-small point-cancel-btn">
                                        X
                                    </button>
                                </div>
                            </td>
                            <td><?php echo esc_html(date('d/m/Y H:i', strtotime($referral['create_date']))); ?></td>
                            <td class="referral-actions column-actions">
                                <button type="button" 
                                        class="button button-small edit-referral-btn" 
                                        data-id="<?php echo esc_attr($referral['id']); ?>">
                                    Edit
                                </button>
                                <button type="button" 
                                        class="button button-small point-edit-btn" 
                                        data-id="<?php echo esc_attr($referral['id']); ?>">
                                    Edit Points
                                </button>
                                <button type="button" 
                                        class="button button-small history-btn" 
                                        data-id="<?php echo esc_attr($referral['id']); ?>"
                                        title="View History">
                                    History
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
                </div>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                // Tính lại start và end cho bottom pagination
                $start = ($current_page - 1) * $per_page + 1;
                $end = min($current_page * $per_page, $total_items);
                // Xây dựng base_url cho bottom pagination
                $base_url_bottom = admin_url('admin.php?page=mac-referral');
                if (!empty($search)) {
                    $base_url_bottom .= '&s=' . urlencode($search);
                }
                $base_url_bottom .= '&per_page=' . $per_page;
                
                echo '<span class="displaying-num">' . sprintf('%d-%d of %d', $start, $end, $total_items) . '</span>';
                echo '<span class="pagination-links">';
                if ($current_page > 1) {
                    echo '<a class="first-page" href="' . esc_url($base_url_bottom . '&paged=1') . '">
                        <span class="screen-reader-text">First page</span>
                        <span aria-hidden="true">&laquo;</span>
                    </a>';
                    echo '<a class="prev-page" href="' . esc_url($base_url_bottom . '&paged=' . ($current_page - 1)) . '">
                        <span class="screen-reader-text">Previous page</span>
                        <span aria-hidden="true">&lsaquo;</span>
                    </a>';
                } else {
                    echo '<span class="tablenav-pages-navspan" aria-hidden="true">&laquo;</span>';
                    echo '<span class="tablenav-pages-navspan" aria-hidden="true">&lsaquo;</span>';
                }
                echo '<span class="paging-input">
                    <span class="current-page">' . $current_page . '</span>
                    <span class="tablenav-paging-text"> / ' . $total_pages . '</span>
                </span>';
                if ($current_page < $total_pages) {
                    echo '<a class="next-page" href="' . esc_url($base_url_bottom . '&paged=' . ($current_page + 1)) . '">
                        <span class="screen-reader-text">Next page</span>
                        <span aria-hidden="true">&rsaquo;</span>
                    </a>';
                    echo '<a class="last-page" href="' . esc_url($base_url_bottom . '&paged=' . $total_pages) . '">
                        <span class="screen-reader-text">Last page</span>
                        <span aria-hidden="true">&raquo;</span>
                    </a>';
                } else {
                    echo '<span class="tablenav-pages-navspan" aria-hidden="true">&rsaquo;</span>';
                    echo '<span class="tablenav-pages-navspan" aria-hidden="true">&raquo;</span>';
                }
                echo '</span>';
                ?>
            </div>
        </div>
        <?php endif; ?>
            </div>
        </form>
</div>

<!-- Quick Add Phone Referral Modal -->
<div id="mac-referral-quick-modal" class="mac-referral-modal" style="display: none;">
    <div class="mac-referral-modal-content" style="max-width: 500px;">
        <div class="mac-referral-modal-header">
            <h2>Add Points to Referrer</h2>
            <span class="mac-referral-modal-close">&times;</span>
        </div>
        <div class="mac-referral-modal-body">
            <div id="mac-referral-quick-form">
                <table class="form-table">
                    <tr>
                        <td>
                            <label for="quick_phone_referral_input">Enter Phone Number</label>
                        </td>
                    </tr>
                    <tr>
                        <td class="mac-referral-quick-form-input flex-row">
                            <input type="text" 
                                   id="quick_phone_referral_input" 
                                   class="regular-text" 
                                   placeholder="(817) 849-9988 or 8178499988"
                                   maxlength="14">
                            <button type="button" 
                                    class="button" 
                                    id="mac-referral-search-phone-btn">
                                Search
                            </button>
                        </td>
                    </tr>
                </table>
                
                <div id="mac-referral-quick-result" style="display: none; margin-top: 20px; padding: 15px; background: #f0f0f1; border-radius: 4px;">
                    <h4 style="margin: 0 0 10px 0;">Referrer Information:</h4>
                    <table class="form-table" style="margin: 0;">
                        <tr>
                            <th scope="row">ID:</th>
                            <td id="quick-result-id">-</td>
                        </tr>
                        <tr>
                            <th scope="row">Full Name:</th>
                            <td id="quick-result-fullname">-</td>
                        </tr>
                        <tr>
                            <th scope="row">Email:</th>
                            <td id="quick-result-email">-</td>
                        </tr>
                        <tr>
                            <th scope="row">Phone Number:</th>
                            <td id="quick-result-phone">-</td>
                        </tr>
                        <tr>
                            <th scope="row">Current Points:</th>
                            <td id="quick-result-point">-</td>
                        </tr>
                        <tr>
                            <th scope="row">Points to Add:</th>
                            <td><strong style="color: #46b450;">+10 points</strong></td>
                        </tr>
                        <tr>
                            <th scope="row">Points After Addition:</th>
                            <td><strong id="quick-result-new-point">-</strong></td>
                        </tr>
                    </table>
                    
                    <input type="hidden" id="quick-result-referral-id" value="">
                    
                    <p class="submit" style="margin-top: 15px;">
                        <button type="button" 
                                class="button button-primary" 
                                id="mac-referral-confirm-add-btn">
                            Confirm and Add Points
                        </button>
                        <button type="button" 
                                class="button mac-referral-quick-cancel">
                            Cancel
                        </button>
                    </p>
                </div>
                
                <div id="mac-referral-quick-error" style="display: none; margin-top: 20px; padding: 10px; background: #fcf0f1; border-left: 4px solid #dc3232; color: #dc3232;">
                    <strong id="quick-error-message"></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div id="mac-referral-modal" class="mac-referral-modal" style="display: none;">
    <div class="mac-referral-modal-content">
        <div class="mac-referral-modal-header">
            <h2 id="mac-referral-modal-title">Add New Referral</h2>
            <span class="mac-referral-modal-close">&times;</span>
        </div>
        <div class="mac-referral-modal-body">
            <form id="mac-referral-form" method="post" action="" onsubmit="return false;">
                <?php wp_nonce_field('mac_referral_action', 'mac_referral_nonce'); ?>
                <input type="hidden" name="mac_referral_action" value="save_referral">
                <input type="hidden" name="referral_id" id="referral_id" value="0">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fullname">Full Name <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="fullname" 
                                   name="fullname" 
                                   class="regular-text" 
                                   required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="email">Email</label>
                        </th>
                        <td>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="regular-text">
                            <div id="email-message" style="margin-top: 5px; display: none;"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="phone">Phone Number <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="phone" 
                                   name="phone" 
                                   class="regular-text" 
                                   placeholder="(817) 849-9988"
                                   maxlength="14"
                                   required>
                            <p class="description">Format: (XXX) XXX-XXXX (10 digits)</p>
                            <div id="phone-message" style="margin-top: 5px; display: none;"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="phone_referral">Referrer Phone Number</label>
                        </th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="text" 
                                       id="phone_referral" 
                                       name="phone_referral" 
                                       class="regular-text"
                                       placeholder="(817) 849-9988"
                                       maxlength="14">
                                <button type="button" 
                                        id="mac-referral-add-phone-btn" 
                                        class="button">
                                    Verify
                                </button>
                            </div>
                            <div id="phone-referral-message" style="margin-top: 5px; display: none;"></div>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">Save</button>
                    <button type="button" class="button mac-referral-modal-cancel">Cancel</button>
                </p>
            </form>
        </div>
    </div>
</div>

<!-- History Modal -->
<div id="mac-referral-history-modal" class="mac-referral-modal" style="display: none;">
    <div class="mac-referral-modal-content" style="max-width: 800px;">
        <div class="mac-referral-modal-header">
            <h2>Referral History</h2>
            <button type="button" class="mac-referral-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="mac-referral-modal-body" id="history-content">
            <div style="text-align: center; padding: 20px;">
                <span class="spinner is-active"></span>
                <p>Loading history...</p>
            </div>
        </div>
    </div>
</div>

