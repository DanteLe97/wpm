<?php
/**
 * Admin Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render results table
 */
function mac_uis_render_results_table($result) {
    ?>
    <form method="post" id="mac-uis-bulk-delete-form" class="mac-uis-results-form">
        <?php wp_nonce_field('bulk_delete_unused_images'); ?>
        <input type="hidden" name="bulk_delete_action" value="1">
        
        <div class="mac-uis-bulk-actions">
            <select name="bulk_action" id="mac-uis-bulk-action" required>
                <option value="">-- <?php echo esc_html__('Hành động', 'mac-unused-images-scanner'); ?> --</option>
                <option value="delete"><?php echo esc_html__('Xóa ảnh được chọn', 'mac-unused-images-scanner'); ?></option>
            </select>
            <input type="submit" id="mac-uis-bulk-action-btn" class="button button-secondary" value="<?php echo esc_attr__('Thực hiện', 'mac-unused-images-scanner'); ?>">
        </div>
        
        <h2><?php echo sprintf(esc_html__('Tổng cộng: %d ảnh không sử dụng', 'mac-unused-images-scanner'), count($result)); ?></h2>
        
        <table class="widefat fixed striped mac-uis-results-table">
            <thead>
                <tr>
                    <th class="check-column">
                        <input type="checkbox" id="mac-uis-select-all">
                    </th>
                    <th><?php echo esc_html__('Ảnh', 'mac-unused-images-scanner'); ?></th>
                    <th><?php echo esc_html__('Tên File', 'mac-unused-images-scanner'); ?></th>
                    <th><?php echo esc_html__('Đường dẫn', 'mac-unused-images-scanner'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result as $id => $guid) : ?>
                    <?php $thumb = wp_get_attachment_image($id, [80, 80]); ?>
                    <tr>
                        <td class="check-column">
                            <input type="checkbox" name="delete_ids[]" value="<?php echo intval($id); ?>" class="mac-uis-item-checkbox">
                        </td>
                        <td class="mac-uis-thumbnail"><?php echo $thumb; ?></td>
                        <td class="mac-uis-filename"><?php echo esc_html(basename($guid)); ?></td>
                        <td class="mac-uis-url">
                            <a href="<?php echo esc_url($guid); ?>" target="_blank"><?php echo esc_html($guid); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
    <?php
}

$status = get_option('mac_uis_scan_status', 'idle');
$result = get_option('mac_uis_scan_result', []);
$manual_run_url = wp_nonce_url(
    admin_url('upload.php?page=mac-unused-images-scanner&run_scan_now=1'),
    'run_scan_now',
    'mac_uis_nonce'
);
?>

<div class="wrap mac-uis-admin-page">
    <h1><?php echo esc_html__('Tìm ảnh không sử dụng (WP-Cron)', 'mac-unused-images-scanner'); ?></h1>
    <p><?php echo esc_html__('Khi bấm "Bắt đầu quét", hệ thống sẽ chạy nền bằng WP-Cron. Không timeout, có tiến trình và log.', 'mac-unused-images-scanner'); ?></p>
    
    <div class="mac-uis-actions">
        <form method="post" class="mac-uis-scan-form">
            <?php wp_nonce_field('start_unused_scan'); ?>
            <input type="submit" name="start_scan" class="button button-primary" value="<?php echo esc_attr__('Bắt đầu quét nền', 'mac-unused-images-scanner'); ?>">
        </form>
        
        <p class="mac-uis-manual-run">
            <a href="<?php echo esc_url($manual_run_url); ?>" 
               class="button mac-uis-manual-btn" 
               onclick="return confirm('<?php echo esc_js(__('Chạy quét ngay lập tức (không qua WP-Cron)?', 'mac-unused-images-scanner')); ?>');">
                <?php echo esc_html__('Chạy quét ngay (thủ công)', 'mac-unused-images-scanner'); ?>
            </a>
            <span class="description"><?php echo esc_html__('(Chạy trực tiếp, có thể timeout nếu có quá nhiều ảnh)', 'mac-unused-images-scanner'); ?></span>
        </p>
    </div>
    
    <div id="mac-uis-scan-status" class="mac-uis-status-box"></div>
    
    <?php if ($status === 'done') : ?>
        <?php if (empty($result)) : ?>
            <div class="updated">
                <p><?php echo esc_html__('Không có ảnh nào bị bỏ trống.', 'mac-unused-images-scanner'); ?></p>
            </div>
        <?php else : ?>
            <?php mac_uis_render_results_table($result); ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
