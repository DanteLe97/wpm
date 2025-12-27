









/**
 * Quét ảnh không sử dụng (WP-Cron + Bulk Delete + WebP cleanup + Xác nhận)
 * Phiên bản tối ưu và chính xác
 */

add_action('admin_menu', function() {
    add_submenu_page(
        'upload.php',
        'Unused Images (Cron)',
        'Unused Images (Cron)',
        'manage_options',
        'unused-images-cron',
        'wp_unused_images_progress_page'
    );
});

function wp_unused_images_progress_page() {
    echo '<div class="wrap">';
    echo '<h1>Tìm ảnh không sử dụng (WP-Cron)</h1>';
    echo '<p>Khi bấm "Bắt đầu quét", hệ thống sẽ chạy nền bằng WP-Cron. Không timeout, có tiến trình và log.</p>';

    /* ====== Bulk Delete Handler ====== */
    if (isset($_POST['bulk_delete_action']) && !empty($_POST['delete_ids'])) {
        check_admin_referer('bulk_delete_unused_images');
        $delete_ids = array_map('intval', $_POST['delete_ids']);
        $deleted = 0;

        // Lấy danh sách hiện tại
        $result = get_option('unused_image_scan_result', []);

        foreach ($delete_ids as $id) {
            $path = get_attached_file($id);
            if ($path && file_exists($path)) {
                // Xóa bản gốc qua wp_delete_attachment()
                if (wp_delete_attachment($id, true)) {
                    $deleted++;
                    error_log("[UNUSED-SCAN] Deleted attachment ID=$id path=$path");
                }
            }

            // Xóa WebP tương ứng (WebP Express)
            $upload_dir = wp_get_upload_dir();
            $relative_path = str_replace(trailingslashit($upload_dir['basedir']), '', $path);
            $webp_path = WP_CONTENT_DIR . '/webp-express/webp-images/uploads/' . $relative_path . '.webp';
            if (file_exists($webp_path)) {
                unlink($webp_path);
                error_log("[UNUSED-SCAN] Deleted WebP version: $webp_path");
            }

            // Gỡ ảnh vừa xóa khỏi danh sách result
            if (isset($result[$id])) {
                unset($result[$id]);
            }
        }

        // Cập nhật lại danh sách còn lại
        update_option('unused_image_scan_result', $result);

        echo '<div class="updated"><p>Đã xóa ' . $deleted . ' ảnh và cập nhật lại danh sách.</p></div>';
    }

    /* ====== Start Scan ====== */
    if (isset($_POST['start_scan'])) {
        check_admin_referer('start_unused_scan');
        wp_start_unused_image_scan_cron();
        echo '<div class="updated"><p>Đã lên lịch quét ảnh trong nền (chạy sau 5 giây). Tiến trình sẽ hiển thị bên dưới.</p></div>';
    }

    echo '<form method="post" style="margin-bottom:20px;">';
    wp_nonce_field('start_unused_scan');
    echo '<input type="submit" name="start_scan" class="button button-primary" value="Bắt đầu quét nền">';
    echo '</form>';

    echo '<div id="scan-status"></div>';

    $status = get_option('unused_image_scan_status');
    $result = get_option('unused_image_scan_result');

    if ($status === 'done') {
        if (empty($result)) {
            echo '<div class="updated"><p>Không có ảnh nào bị bỏ trống.</p></div>';
        } else {
            echo '<form method="post" id="bulk-delete-form">';
            wp_nonce_field('bulk_delete_unused_images');
            echo '<input type="hidden" name="bulk_delete_action" value="1">';
            echo '<div style="margin:10px 0;">
                    <select name="bulk_action" id="bulk_action" required>
                        <option value="">-- Hành động --</option>
                        <option value="delete">Xóa ảnh được chọn</option>
                    </select>
                    <input type="submit" id="bulk_action_btn" class="button button-secondary" value="Thực hiện">
                  </div>';

            echo '<h2>Tổng cộng: ' . count($result) . ' ảnh không sử dụng</h2>';
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th><input type="checkbox" id="select-all"></th><th>Ảnh</th><th>Tên File</th><th>Đường dẫn</th></tr></thead><tbody>';

            foreach ($result as $id => $guid) {
                $thumb = wp_get_attachment_image($id, [80, 80]);
                echo '<tr>';
                echo '<td><input type="checkbox" name="delete_ids[]" value="' . intval($id) . '"></td>';
                echo '<td>' . $thumb . '</td>';
                echo '<td>' . esc_html(basename($guid)) . '</td>';
                echo '<td><a href="' . esc_url($guid) . '" target="_blank">' . esc_html($guid) . '</a></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</form>';
            echo '<script>
                // Chọn tất cả
                document.getElementById("select-all").addEventListener("change", function(){
                    document.querySelectorAll("input[name=\'delete_ids[]\']").forEach(function(cb) { cb.checked = this.checked; }.bind(this));
                });

                // Xác nhận trước khi xóa
                document.getElementById("bulk-delete-form").addEventListener("submit", function(e){
                    const action = document.getElementById("bulk_action").value;
                    if (action === "delete") {
                        const checked = document.querySelectorAll("input[name=\'delete_ids[]\']:checked").length;
                        if (checked === 0) {
                            alert("Vui lòng chọn ít nhất một ảnh để xóa.");
                            e.preventDefault();
                            return;
                        }
                        const confirmed = confirm("Bạn có chắc chắn muốn xóa " + checked + " ảnh đã chọn không?\\nHành động này không thể hoàn tác.");
                        if (!confirmed) e.preventDefault();
                    }
                });
            </script>';
        }
    }

    echo '</div>';

    // Hiển thị tiến trình bằng AJAX
    echo '<script>
    const statusBox = document.getElementById("scan-status");
    function checkProgress() {
        fetch(ajaxurl + "?action=wp_check_scan_progress")
            .then(res => res.json())
            .then(data => {
                if (data.status === "running") {
                    statusBox.innerHTML = "<p><strong>Đang xử lý:</strong> " + data.processed + " / " + data.total + " ảnh (" + data.percent + "%)</p>";
                    setTimeout(checkProgress, 3000);
                } else if (data.status === "done") {
                    statusBox.innerHTML = "<p><strong>Hoàn tất!</strong> Vui lòng tải lại trang để xem kết quả.</p>";
                } else {
                    statusBox.innerHTML = "<p><em>Chưa bắt đầu.</em></p>";
                }
            })
            .catch(err => console.error(err));
    }
    checkProgress();
    </script>';
}

/* =====================================================
   1. Lên lịch WP-Cron job
===================================================== */
function wp_start_unused_image_scan_cron() {
    update_option('unused_image_scan_status', 'running');
    update_option('unused_image_scan_result', []);
    update_option('unused_image_scan_progress', ['processed' => 0, 'total' => 0, 'percent' => 0]);

    wp_schedule_single_event(time() + 5, 'wp_run_unused_image_scan_event');
    error_log('[UNUSED-SCAN] WP-Cron job scheduled at ' . date('H:i:s'));
}
add_action('wp_run_unused_image_scan_event', 'wp_run_unused_image_scan');

/* =====================================================
   2. Hàm lấy danh sách ảnh
===================================================== */
function wpui_get_all_image_attachments() {
    global $wpdb;
    error_log('[UNUSED-SCAN] Collecting attachments...');

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

    error_log('[UNUSED-SCAN] Found ' . count($filtered) . ' image attachments after filtering.');
    return $filtered;
}

/* =====================================================
   3. Hàm kiểm tra ảnh có đang được sử dụng không
===================================================== */
// function wpui_is_attachment_used($id, $guid) {
//     global $wpdb;
//     $id = (int)$id;
//     $file = strtolower(basename($guid));

//     if (!preg_match('/\.(jpg|jpeg|png|gif|webp|avif|svg)$/', $file)) return false;

//     $found_featured = $wpdb->get_var($wpdb->prepare("
//         SELECT post_id FROM {$wpdb->postmeta}
//         WHERE meta_key = '_thumbnail_id' AND meta_value = %d
//         LIMIT 1
//     ", $id));
//     if ($found_featured) return true;

//     $found_gallery = $wpdb->get_var($wpdb->prepare("
//         SELECT post_id FROM {$wpdb->postmeta}
//         WHERE meta_key = '_product_image_gallery'
//           AND (meta_value = %s OR meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s)
//         LIMIT 1
//     ",
//         $id,
//         $wpdb->esc_like($id).',%',
//         '%,' . $wpdb->esc_like($id),
//         '%,' . $wpdb->esc_like($id) . ',%'
//     ));
//     if ($found_gallery) return true;

//     $meta_hit = $wpdb->get_var($wpdb->prepare("
//         SELECT pm.post_id
//         FROM {$wpdb->postmeta} pm
//         JOIN {$wpdb->posts} p ON p.ID = pm.post_id
//         WHERE p.post_status NOT IN ('trash','auto-draft','inherit')
//           AND pm.post_id <> %d
//           AND (pm.meta_value LIKE %s OR pm.meta_value LIKE %s)
//         LIMIT 1
//     ", $id, '%\"id\":'.$id.'%', '%'.$wpdb->esc_like($file).'%'));
//     if ($meta_hit) return true;

//     $content_hit = $wpdb->get_var($wpdb->prepare("
//         SELECT ID FROM {$wpdb->posts}
//         WHERE post_status IN ('publish','draft','private')
//           AND post_type NOT IN ('revision','attachment')
//           AND (post_content LIKE %s OR post_content LIKE %s)
//         LIMIT 1
//     ", '%\"id\":'.$id.'%', '%'.$wpdb->esc_like($file).'%'));
//     if ($content_hit) return true;

//     $option_hit = $wpdb->get_var($wpdb->prepare("
//         SELECT option_id FROM {$wpdb->options}
//         WHERE option_value LIKE %s OR option_value LIKE %s
//         LIMIT 1
//     ", '%i:'.$id.';%', '%'.$wpdb->esc_like($file).'%'));
//     if ($option_hit) return true;

//     return false;
// }
function wpui_is_attachment_used($id, $guid) {
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
        error_log("[UNUSED-SCAN] ID=$id found as featured image in post_id=$found_featured");
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
        error_log("[UNUSED-SCAN] ID=$id found in product gallery of post_id=$found_gallery");
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
        error_log("[UNUSED-SCAN] ID=$id found in postmeta of post_id=$meta_hit");
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
        error_log("[UNUSED-SCAN] ID=$id found in post_content of post_id=$content_hit");
        return true;
    }

    // 4.5. Kiểm tra Elementor Templates và các templates khác
    // Elementor templates lưu trong post_type 'elementor_library' với meta '_elementor_data'
    $elementor_templates = $wpdb->get_results($wpdb->prepare("
        SELECT pm.post_id, pm.meta_value
        FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE p.post_status NOT IN ('trash','auto-draft')
          AND pm.meta_key = '_elementor_data'
          AND (pm.meta_value LIKE %s OR pm.meta_value LIKE %s)
        LIMIT 10
    ", '%\"id\":'.$id.'%', '%'.$wpdb->esc_like($file).'%'));
    
    if (!empty($elementor_templates)) {
        foreach ($elementor_templates as $template) {
            // Elementor data là JSON string, cần decode và tìm ID
            $elementor_data = json_decode($template->meta_value, true);
            if ($elementor_data && is_array($elementor_data)) {
                // Helper function để tìm ID trong Elementor data (đệ quy)
                $find_id_in_elementor = function($data, $search_id, $search_filename = '') use (&$find_id_in_elementor) {
                    if (is_array($data)) {
                        foreach ($data as $key => $value) {
                            // Kiểm tra trực tiếp nếu value là ID
                            if (is_numeric($value) && (int)$value === $search_id) {
                                return true;
                            }
                            
                            // Kiểm tra key 'id' với value là số
                            if ($key === 'id' && is_numeric($value) && (int)$value === $search_id) {
                                return true;
                            }
                            
                            // Kiểm tra các key image thường dùng
                            $image_keys = ['image', 'url', 'background_image', 'overlay_image', 'background_overlay_image', 
                                         'hover_image', 'mobile_image', 'tablet_image', 'desktop_image', 'icon_image'];
                            if (in_array($key, $image_keys)) {
                                // Nếu là số ID
                                if (is_numeric($value) && (int)$value === $search_id) {
                                    return true;
                                }
                                // Nếu là object có key 'id'
                                if (is_array($value) && isset($value['id']) && (int)$value['id'] === $search_id) {
                                    return true;
                                }
                                // Nếu là URL, kiểm tra filename
                                if (is_string($value) && !empty($search_filename) && stripos($value, $search_filename) !== false) {
                                    return true;
                                }
                            }
                            
                            // Kiểm tra trong settings (nơi Elementor thường lưu ảnh)
                            if ($key === 'settings' && is_array($value)) {
                                foreach ($value as $setting_key => $setting_value) {
                                    // Các key thường chứa ảnh trong settings
                                    if (in_array($setting_key, $image_keys) || 
                                        preg_match('/.*image.*/i', $setting_key) || 
                                        preg_match('/.*icon.*/i', $setting_key)) {
                                        // Nếu là số ID
                                        if (is_numeric($setting_value) && (int)$setting_value === $search_id) {
                                            return true;
                                        }
                                        // Nếu là object có key 'id'
                                        if (is_array($setting_value) && isset($setting_value['id']) && (int)$setting_value['id'] === $search_id) {
                                            return true;
                                        }
                                        // Nếu là array của objects
                                        if (is_array($setting_value)) {
                                            foreach ($setting_value as $item) {
                                                if (is_array($item) && isset($item['id']) && (int)$item['id'] === $search_id) {
                                                    return true;
                                                }
                                            }
                                        }
                                        // Nếu là URL
                                        if (is_string($setting_value) && !empty($search_filename) && stripos($setting_value, $search_filename) !== false) {
                                            return true;
                                        }
                                    }
                                }
                            }
                            
                            // Đệ quy vào elements và các array con
                            if ($key === 'elements' || is_array($value)) {
                                if ($find_id_in_elementor($value, $search_id, $search_filename)) {
                                    return true;
                                }
                            }
                        }
                    }
                    return false;
                };
                
                $filename = basename($guid);
                if ($find_id_in_elementor($elementor_data, $id, $filename)) {
                    error_log("[UNUSED-SCAN] ID=$id found in Elementor template post_id={$template->post_id}");
                    return true;
                }
            }
        }
    }

    // Kiểm tra các post_type template khác (page templates, custom templates)
    $template_types = ['elementor_library', 'page', 'wp_template', 'wp_template_part'];
    $template_types_escaped = array_map(function($type) use ($wpdb) {
        return "'" . esc_sql($type) . "'";
    }, $template_types);
    $template_types_in = implode(',', $template_types_escaped);
    
    $template_hit = $wpdb->get_var($wpdb->prepare("
        SELECT p.ID FROM {$wpdb->posts} p
        WHERE p.post_status NOT IN ('trash','auto-draft')
          AND p.post_type IN ($template_types_in)
          AND (p.post_content LIKE %s OR p.post_content LIKE %s)
        LIMIT 1
    ", '%\"id\":'.$id.'%', '%'.$wpdb->esc_like($file).'%'));
    if ($template_hit) {
        error_log("[UNUSED-SCAN] ID=$id found in template post_id=$template_hit");
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

    // Helper: hàm đệ quy tìm tất cả ID trong các key 'gallery'
    if (!function_exists('wpui_extract_gallery_ids_recursive')) {
        function wpui_extract_gallery_ids_recursive($arr) {
            $ids = [];
            foreach ($arr as $k => $v) {
                if (is_array($v)) {
                    $ids = array_merge($ids, wpui_extract_gallery_ids_recursive($v));
                } elseif ($k === 'gallery' && !empty($v) && is_string($v)) {
                    $ids = array_merge($ids, array_map('intval', explode(',', $v)));
                }
            }
            return $ids;
        }
    }

    // Helper: chuẩn hóa option value (xử lý cả array và serialized string)
    $normalize_option_value = function($opt_value) {
        // Nếu đã là array (WordPress đã unserialize)
        if (is_array($opt_value)) {
            return $opt_value;
        }
        // Nếu là string serialized
        if (is_string($opt_value) && is_serialized($opt_value)) {
            $data = @unserialize($opt_value);
            if ($data && is_array($data)) {
                return $data;
            }
        }
        // Nếu là string nhưng không phải serialized, thử unserialize anyway
        if (is_string($opt_value)) {
            $data = @unserialize($opt_value);
            if ($data && is_array($data)) {
                return $data;
            }
        }
        return null;
    };

    foreach ($known_options as $opt_name) {
        $opt_value = get_option($opt_name, null);
        if (empty($opt_value)) continue;

        // Nếu là ID đơn giản (ví dụ site_logo)
        if (is_numeric($opt_value) && (int)$opt_value === $id) {
            error_log("[UNUSED-SCAN] ID=$id found in option $opt_name (simple id)");
            return true;
        }

        // Chuẩn hóa option value (xử lý cả array và serialized)
        $data = $normalize_option_value($opt_value);
        if (!$data || !is_array($data)) continue;

        // Xử lý web-info
        if ($opt_name === 'web-info') {
            $image_keys = ['logo', 'gallery', 'gallery_gift_card', 'combination_logo'];
            foreach ($image_keys as $key) {
                if (!empty($data[$key])) {
                    $value = $data[$key];
                    // Nếu là string chứa ID ngăn cách bởi dấu phẩy
                    if (is_string($value)) {
                        $ids_or_urls = array_map('trim', explode(',', $value));
                        foreach ($ids_or_urls as $val) {
                            // Nếu là ID số
                            if (ctype_digit($val) && (int)$val === $id) {
                                error_log("[UNUSED-SCAN] ID=$id found in web-info[$key] (ID: $val)");
                                return true;
                            }
                            // Nếu là URL, kiểm tra filename
                            $filename = basename($guid);
                            if (stripos($val, $filename) !== false) {
                                error_log("[UNUSED-SCAN] ID=$id found in web-info[$key] (URL match: $val)");
                                return true;
                            }
                        }
                    }
                    // Nếu là số đơn giản
                    elseif (is_numeric($value) && (int)$value === $id) {
                        error_log("[UNUSED-SCAN] ID=$id found in web-info[$key] (numeric: $value)");
                        return true;
                    }
                }
            }
        }

        // Xử lý design-template (tìm gallery đệ quy)
        if ($opt_name === 'design-template') {
            $gallery_ids = wpui_extract_gallery_ids_recursive($data);
            if (in_array($id, $gallery_ids, true)) {
                error_log("[UNUSED-SCAN] ID=$id found in design-template (recursive gallery)");
                return true;
            }
        }
    }

    return false;
}

/* =====================================================
   4. Job quét ảnh
===================================================== */
function wp_run_unused_image_scan() {
    global $wpdb;
    error_log('[UNUSED-SCAN] Background job started at ' . date('H:i:s'));

    $attachments = wpui_get_all_image_attachments();
    $total = count($attachments);
    $processed = 0;
    $unused = [];

    update_option('unused_image_scan_progress', [
        'processed' => 0,
        'total'     => $total,
        'percent'   => 0
    ]);

    if ($total === 0) {
        update_option('unused_image_scan_status', 'done');
        return;
    }

    foreach ($attachments as $att) {
        $processed++;
        $id = (int)$att->ID;

        $is_used = wpui_is_attachment_used($id, $att->guid);
        if (!$is_used) {
            $unused[$id] = $att->guid;
        }

        $percent = (int) round(($processed / max(1, $total)) * 100);
        if ($processed % 50 === 0 || $processed === $total) {
            update_option('unused_image_scan_progress', [
                'processed' => $processed,
                'total'     => $total,
                'percent'   => $percent
            ]);
            error_log("[UNUSED-SCAN] Progress: $processed/$total ($percent%)");
        }

        if ($processed % 200 === 0) {
            sleep(1);
        }
    }

    update_option('unused_image_scan_result', $unused);
    update_option('unused_image_scan_status', 'done');
    error_log('[UNUSED-SCAN] Finished. Found ' . count($unused) . ' unused of ' . $total);
}

/* =====================================================
   5. AJAX kiểm tra tiến trình
===================================================== */
add_action('wp_ajax_wp_check_scan_progress', function() {
    $progress = get_option('unused_image_scan_progress', []);
    $status = get_option('unused_image_scan_status', 'idle');
    wp_send_json(array_merge($progress, ['status' => $status]));
});



//wp-admin/upload.php?page=unused-images-cron&run_scan_now=1
if (isset($_GET['run_scan_now']) && current_user_can('manage_options')) {
    wp_run_unused_image_scan();
    wp_die('Đã chạy job thủ công, xem log để kiểm tra.');
}