<?php
/**
 * Scanner Class
 * Handles all scanning logic
 */

namespace MAC_UIS;

if (!defined('ABSPATH')) {
    exit;
}

class Scanner {
    
    /**
     * Get all image attachments
     */
    public function get_all_image_attachments() {
        global $wpdb;
        error_log('[MAC-UIS] Collecting attachments...');
        
        $attachments = $wpdb->get_results("
            SELECT ID, guid, post_mime_type, post_status
            FROM {$wpdb->posts}
            WHERE post_type='attachment'
              AND post_status NOT IN ('trash','auto-draft')
        ");
        
        $filtered = [];
        foreach ($attachments as $a) {
            $fn = strtolower(basename($a->guid));
            if (preg_match('/\.(jpg|jpeg|png|gif|webp|avif|svg)$/', $fn)) {
                $filtered[] = $a;
            }
        }
        
        error_log('[MAC-UIS] Found ' . count($filtered) . ' image attachments after filtering.');
        return $filtered;
    }
    
    /**
     * Check if attachment is being used
     */
    public function is_attachment_used($id, $guid) {
        global $wpdb;
        $id = (int)$id;
        $file = strtolower(basename($guid));
        
        if (!preg_match('/\.(jpg|jpeg|png|gif|webp|avif|svg)$/', $file)) {
            return false;
        }
        
        // 1. Featured image
        $found_featured = $wpdb->get_var($wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = '_thumbnail_id' AND meta_value = %d
            LIMIT 1
        ", $id));
        if ($found_featured) {
            error_log("[MAC-UIS] ID=$id found as featured image in post_id=$found_featured");
            return true;
        }
        
        // 2. WooCommerce gallery
        $found_gallery = $wpdb->get_var($wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = '_product_image_gallery'
              AND (meta_value = %s OR meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s)
            LIMIT 1
        ",
            $id,
            $wpdb->esc_like($id).',%',
            '%,' . $wpdb->esc_like($id),
            '%,' . $wpdb->esc_like($id) . ',%'
        ));
        if ($found_gallery) {
            error_log("[MAC-UIS] ID=$id found in product gallery of post_id=$found_gallery");
            return true;
        }
        
        // 3. postmeta (Elementor, ACF, builder)
        $meta_hit = $wpdb->get_var($wpdb->prepare("
            SELECT pm.post_id
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_status NOT IN ('trash','auto-draft','inherit')
              AND pm.post_id <> %d
              AND (pm.meta_value LIKE %s OR pm.meta_value LIKE %s)
            LIMIT 1
        ", $id, '%\"id\":'.$id.'%', '%'.$wpdb->esc_like($file).'%'));
        if ($meta_hit) {
            error_log("[MAC-UIS] ID=$id found in postmeta of post_id=$meta_hit");
            return true;
        }
        
        // 4. post_content
        $content_hit = $wpdb->get_var($wpdb->prepare("
            SELECT ID FROM {$wpdb->posts}
            WHERE post_status IN ('publish','draft','private')
              AND post_type NOT IN ('revision','attachment')
              AND (post_content LIKE %s OR post_content LIKE %s)
            LIMIT 1
        ", '%\"id\":'.$id.'%', '%'.$wpdb->esc_like($file).'%'));
        if ($content_hit) {
            error_log("[MAC-UIS] ID=$id found in post_content of post_id=$content_hit");
            return true;
        }
        
        // 5. Kiểm tra các option cụ thể thường lưu ảnh
        $known_options = [
            'web-info',
            'design-template',
            'site_logo',
            'custom_logo',
            'theme_mods_' . get_option('stylesheet'),
            'elementor_pro_theme_builder_conditions'
        ];
        
        foreach ($known_options as $opt_name) {
            $opt_value = get_option($opt_name, null);
            if (empty($opt_value)) continue;
            
            // Nếu là ID đơn giản
            if (is_numeric($opt_value) && (int)$opt_value === $id) {
                error_log("[MAC-UIS] ID=$id found in option $opt_name (simple id)");
                return true;
            }
            
            // Chuẩn hóa option value
            $data = $this->normalize_option_value($opt_value);
            if (!$data || !is_array($data)) continue;
            
            // Xử lý web-info
            if ($opt_name === 'web-info') {
                if ($this->check_web_info_option($data, $id, $guid)) {
                    return true;
                }
            }
            
            // Xử lý design-template
            if ($opt_name === 'design-template') {
                $gallery_ids = $this->extract_gallery_ids_recursive($data);
                if (in_array($id, $gallery_ids, true)) {
                    error_log("[MAC-UIS] ID=$id found in design-template (recursive gallery)");
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Normalize option value (handle serialized strings)
     */
    private function normalize_option_value($opt_value) {
        if (is_array($opt_value)) {
            return $opt_value;
        }
        if (is_string($opt_value) && is_serialized($opt_value)) {
            $data = @unserialize($opt_value);
            if ($data && is_array($data)) {
                return $data;
            }
        }
        if (is_string($opt_value)) {
            $data = @unserialize($opt_value);
            if ($data && is_array($data)) {
                return $data;
            }
        }
        return null;
    }
    
    /**
     * Check web-info option for image usage
     */
    private function check_web_info_option($data, $id, $guid) {
        $image_keys = ['logo', 'gallery', 'gallery_gift_card', 'combination_logo'];
        foreach ($image_keys as $key) {
            if (!empty($data[$key])) {
                $value = $data[$key];
                if (is_string($value)) {
                    $ids_or_urls = array_map('trim', explode(',', $value));
                    foreach ($ids_or_urls as $val) {
                        if (ctype_digit($val) && (int)$val === $id) {
                            error_log("[MAC-UIS] ID=$id found in web-info[$key] (ID: $val)");
                            return true;
                        }
                        $filename = basename($guid);
                        if (stripos($val, $filename) !== false) {
                            error_log("[MAC-UIS] ID=$id found in web-info[$key] (URL match: $val)");
                            return true;
                        }
                    }
                } elseif (is_numeric($value) && (int)$value === $id) {
                    error_log("[MAC-UIS] ID=$id found in web-info[$key] (numeric: $value)");
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Extract gallery IDs recursively from array
     */
    private function extract_gallery_ids_recursive($arr) {
        $ids = [];
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $ids = array_merge($ids, $this->extract_gallery_ids_recursive($v));
            } elseif ($k === 'gallery' && !empty($v) && is_string($v)) {
                $ids = array_merge($ids, array_map('intval', explode(',', $v)));
            }
        }
        return $ids;
    }
    
    /**
     * Run scan job
     */
    public function run_scan() {
        global $wpdb;
        error_log('[MAC-UIS] Background job started at ' . date('H:i:s'));
        
        $attachments = $this->get_all_image_attachments();
        $total = count($attachments);
        $processed = 0;
        $unused = [];
        
        update_option('mac_uis_scan_progress', [
            'processed' => 0,
            'total'     => $total,
            'percent'   => 0
        ]);
        
        if ($total === 0) {
            update_option('mac_uis_scan_status', 'done');
            return;
        }
        
        foreach ($attachments as $att) {
            $processed++;
            $id = (int)$att->ID;
            
            $is_used = $this->is_attachment_used($id, $att->guid);
            if (!$is_used) {
                $unused[$id] = $att->guid;
            }
            
            $percent = (int) round(($processed / max(1, $total)) * 100);
            if ($processed % 50 === 0 || $processed === $total) {
                update_option('mac_uis_scan_progress', [
                    'processed' => $processed,
                    'total'     => $total,
                    'percent'   => $percent
                ]);
                error_log("[MAC-UIS] Progress: $processed/$total ($percent%)");
            }
            
            if ($processed % 200 === 0) {
                sleep(1);
            }
        }
        
        update_option('mac_uis_scan_result', $unused);
        update_option('mac_uis_scan_status', 'done');
        error_log('[MAC-UIS] Finished. Found ' . count($unused) . ' unused of ' . $total);
    }
    
    /**
     * Start scan (schedule WP-Cron)
     */
    public function start_scan() {
        update_option('mac_uis_scan_status', 'running');
        update_option('mac_uis_scan_result', []);
        update_option('mac_uis_scan_progress', ['processed' => 0, 'total' => 0, 'percent' => 0]);
        
        wp_schedule_single_event(time() + 5, 'mac_uis_run_scan_event');
        error_log('[MAC-UIS] WP-Cron job scheduled at ' . date('H:i:s'));
    }
    
    /**
     * Delete attachment and WebP version
     */
    public function delete_attachment($id) {
        $path = get_attached_file($id);
        $deleted = false;
        
        if ($path && file_exists($path)) {
            if (wp_delete_attachment($id, true)) {
                $deleted = true;
                error_log("[MAC-UIS] Deleted attachment ID=$id path=$path");
            }
        }
        
        // Xóa WebP tương ứng (WebP Express)
        if ($path) {
            $upload_dir = wp_get_upload_dir();
            $relative_path = str_replace(trailingslashit($upload_dir['basedir']), '', $path);
            $webp_path = WP_CONTENT_DIR . '/webp-express/webp-images/uploads/' . $relative_path . '.webp';
            if (file_exists($webp_path)) {
                unlink($webp_path);
                error_log("[MAC-UIS] Deleted WebP version: $webp_path");
            }
        }
        
        return $deleted;
    }
}

