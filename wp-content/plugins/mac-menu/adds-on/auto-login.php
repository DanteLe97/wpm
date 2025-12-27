<?php
// ==========================
// Tạo Auto-login URL
// ==========================
class Auto_Login
{
    
    
    public function __construct()
    {
        add_action('init', [$this, 'walc_auto_login_admin']);
        add_action('admin_menu', function () {
            add_users_page(
                'Auto Login Generator',
                'Auto Login Links',
                'administrator', // chỉ admin mới thấy
                'walc-auto-login',
                [$this, 'walc_render_admin_page']
            );
        });
        

        
    }


    // ==========================
    // Đăng nhập khi có token
    // ==========================
    public function walc_auto_login_admin()
    {
        if (!isset($_GET['walc_token'])) return;

        $token = sanitize_text_field($_GET['walc_token']);
        $users = get_users([
            'meta_key'   => 'walc_token',
            'meta_value' => $token,
            'number'     => 1,
            'fields'     => 'all',
        ]);

        if (empty($users)) {
            $this->walc_add_log([
                'action' => 'token_invalid',
                'token' => $token,
                'time' => current_time('timestamp'),
                'note' => 'Token không hợp lệ hoặc không tồn tại trong hệ thống.'
            ]);
            wp_die('Token không hợp lệ hoặc không tồn tại trong hệ thống.');
        }

        $user = $users[0];
        $expires = intval(get_user_meta($user->ID, 'walc_expires', true));
        $usage_count = intval(get_user_meta($user->ID, 'walc_usage_count', true));
        $created_time = intval(get_user_meta($user->ID, 'walc_created_time', true));
        $username = $user->user_login;
        
        // Tính thời gian còn lại
        $time_left = $expires - time();
        $days_left = floor($time_left / DAY_IN_SECONDS);
        $hours_left = floor(($time_left % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
        
        // Kiểm tra hết hạn
        if (time() > $expires) {
            $expired_days = floor((time() - $expires) / DAY_IN_SECONDS);
            $expired_hours = floor(((time() - $expires) % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
            
            $error_message = "Token đã hết hạn.\n";
            $error_message .= "Thời gian tạo: " . date('d/m/Y h:i:s A', $created_time) . "\n";
            $error_message .= "Thời gian hết hạn: " . date('d/m/Y h:i:s A', $expires) . "\n";
            $error_message .= "Đã hết hạn: " . $expired_days . " ngày " . $expired_hours . " giờ trước\n";
            $error_message .= "Số lần đã sử dụng: " . $usage_count . "/5";

            // Ghi log xóa user vì hết hạn
            $this->walc_add_log([
                'action' => 'user_deleted',
                'user_id' => $user->ID,
                'username' => $username,
                'reason' => 'expired',
                'created_time' => $created_time,
                'expires' => $expires,
                'usage_count' => $usage_count,
                'deleted_time' => current_time('timestamp'),
                'note' => 'Xóa user vì token hết hạn',
            ]);
            // Xóa token và thông tin liên quan
            delete_user_meta($user->ID, 'walc_token');
            delete_user_meta($user->ID, 'walc_expires'); 
            delete_user_meta($user->ID, 'walc_usage_count');
            delete_user_meta($user->ID, 'walc_created_time');

            update_option('walc_auto_login_user_id', null);
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            wp_delete_user($user->ID);
            wp_die($error_message);
        }
        
        // Kiểm tra số lần sử dụng
        if ($usage_count > 50) {
            $error_message = "Token đã đạt giới hạn sử dụng.\n";
            $error_message .= "Thời gian tạo: " . date('d/m/Y h:i:s A', $created_time) . "\n";
            $error_message .= "Thời gian hết hạn: " . date('d/m/Y h:i:s A', $expires) . "\n";
            $error_message .= "Số lần đã sử dụng: " . $usage_count . "/5\n";
            $error_message .= "Thời gian còn lại: " . $days_left . " ngày " . $hours_left . " giờ";

            // Ghi log xóa user vì vượt số lần sử dụng
            $this->walc_add_log([
                'action' => 'user_deleted',
                'user_id' => $user->ID,
                'username' => $username,
                'reason' => 'usage_limit',
                'created_time' => $created_time,
                'expires' => $expires,
                'usage_count' => $usage_count,
                'deleted_time' => current_time('timestamp'),
                'note' => 'Xóa user vì vượt số lần sử dụng',
            ]);
            // Xóa token và thông tin liên quan
            delete_user_meta($user->ID, 'walc_token');
            delete_user_meta($user->ID, 'walc_expires'); 
            delete_user_meta($user->ID, 'walc_usage_count');
            delete_user_meta($user->ID, 'walc_created_time');

            update_option('walc_auto_login_user_id', null);
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            wp_delete_user($user->ID);
            wp_die($error_message);
        }

        // Đăng nhập thành công
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        // Kiểm tra thời gian giữa các lần tăng usage_count
        $last_usage_time = intval(get_user_meta($user->ID, 'walc_last_usage_time', true));
        $now = time();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        // Danh sách các từ khóa nhận diện trình duyệt phổ biến
        $browser_keywords = [
            'Chrome', 'Firefox', 'Safari', 'Edge', 'Opera', 'MSIE', 'Trident', 'Mozilla',
            'Mobile', 'Android', 'iPhone', 'iPad', 'Windows NT', 'Mac OS X', 'Linux'
        ];
        
        // Kiểm tra xem có phải trình duyệt thật không
        $is_browser = false;
        foreach ($browser_keywords as $kw) {
            if (stripos($user_agent, $kw) !== false) {
                $is_browser = true;
                break;
            }
        }
        
        // Nếu không phải trình duyệt thật thì coi là bot
        $is_bot = !$is_browser;

        if (!$is_bot && ($now - $last_usage_time > 10)) { // chỉ tăng nếu không phải bot và cách lần trước >10s
            update_user_meta($user->ID, 'walc_usage_count', $usage_count + 1);
            update_user_meta($user->ID, 'walc_last_usage_time', $now);
            $usage_count++;
            $this->walc_add_log([
                'action' => 'usage_count_increased',
                'user_id' => $user->ID,
                'username' => $username,
                'time' => $now,
                'user_agent' => $user_agent,
                'referer' => $referer,
                'note' => 'Increased',
            ]);
        } else {
            $this->walc_add_log([
                'action' => 'usage_count_skipped',
                'user_id' => $user->ID,
                'username' => $username,
                'time' => $now,
                'user_agent' => $user_agent,
                'referer' => $referer,
                'note' => $is_bot ? 'Skipped (Bot)' : 'Skipped',
            ]);
        }
        // Lưu thời gian đăng nhập cuối
        update_user_meta($user->ID, 'walc_last_login', $now);

        $this->walc_add_log([
            'action' => 'login',
            'user_id' => $user->ID,
            'username' => $username,
            'login_time' => current_time('timestamp'),
            'usage_count' => $usage_count,
            'expires' => $expires,
        ]);

        wp_redirect(admin_url());
        exit;
    }



    // ==========================
    // Tạo Auto-login URL
    // ==========================
    public function walc_generate_auto_login_link($user_id, $days = 7)
    {
        // Kiểm tra user có tồn tại không
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }

        // Kiểm tra quyền - cho phép editor và admin
        if (!user_can($user_id, 'editor') && !user_can($user_id, 'administrator')) {
            return false;
        }

        // Validate số ngày (từ 3-10 ngày)
        $days = intval($days);
        if ($days < 3 || $days > 10) {
            $days = 7; // Default 7 ngày nếu không hợp lệ
        }

        // Tạo token an toàn
        $token = wp_generate_password(32, false, false);
        $expires = time() + $days * DAY_IN_SECONDS;

        // Lưu thông tin vào database
        update_user_meta($user_id, 'walc_token', $token);
        update_user_meta($user_id, 'walc_expires', $expires);
        update_user_meta($user_id, 'walc_usage_count', 0); // Đếm số lần sử dụng
        update_user_meta($user_id, 'walc_created_time', time()); // Lưu thời gian tạo

        return site_url('?walc_token=' . $token);
    }
    

    function allow_editor_manage_options() {
        $role = get_role('editor');
        if ($role && !$role->has_cap('manage_options')) {
            $role->add_cap('manage_options');
        }
    }
    // ==========================
    // Giao diện trang admin
    // ==========================
    public function walc_render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Bạn không có quyền truy cập trang này.');
        }

        // Xử lý form nếu submit
        $login_url = '';
        $selected_days = isset($_POST['walc_days']) ? intval($_POST['walc_days']) : 7;

        if (isset($_POST['submit'])) {
            $user_id = get_option('walc_auto_login_user_id',null);
            $user = get_user_by('ID', $user_id);

            if ($user_id === null || !$user) {
                // Tạo username và password ngẫu nhiên
                $random_username = 'user_' . wp_generate_password(8, false);
                $random_password = wp_generate_password(12, true);
                
                // Tạo user mới với role editor
                $userdata = array(
                    'user_login' => $random_username,
                    'user_pass' => $random_password,
                    'role' => 'editor'
                );
                
                $user_id = wp_insert_user($userdata);
                
                if (!is_wp_error($user_id)) {
                    // Gán quyền chỉ cho user này
                    $user_obj = new WP_User($user_id);
                    $user_obj->add_cap('manage_options');
                    // Lấy dữ liệu hiện tại để không ghi đè
                    $current_role_manager = get_option('elementor_role-manager', array());
                    
                    // Đảm bảo $current_role_manager là array
                    if (!is_array($current_role_manager)) {
                        $current_role_manager = array();
                    }
                    // Thêm quyền cho role editor nếu chưa có
                    if (!isset($current_role_manager['editor']) || !is_array($current_role_manager['editor'])) {
                        $current_role_manager['editor'] = array();
                    }
                    // Thêm các quyền cần thiết nếu chưa có
                    if (!in_array('json-upload', $current_role_manager['editor'])) {
                        $current_role_manager['editor'][] = 'json-upload';
                    }
                    if (!in_array('custom-html', $current_role_manager['editor'])) {
                        $current_role_manager['editor'][] = 'custom-html';
                    }
                    update_option('elementor_role-manager', $current_role_manager);
                    // Lưu user_id vào option
                    update_option('walc_auto_login_user_id', $user_id);

                    $this->walc_add_log([
                        'action' => 'user_created',
                        'user_id' => $user_id,
                        'username' => $random_username,
                        'created_time' => current_time('timestamp'),
                    ]);
                } else {
                    $this->walc_add_log([
                        'action' => 'user_create_failed',
                        'username' => $random_username,
                        'time' => current_time('timestamp'),
                        'error' => $user_id->get_error_message(),
                    ]);
                }
            }
            
            $login_url = $this->walc_generate_auto_login_link($user_id, $selected_days);
        }

?>
        <div class="wrap">
            <h1>Auto-Login Link Generator</h1>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th><label for="walc_days">Thời gian hiệu lực:</label></th>
                        <td>
                            <select name="walc_days" id="walc_days" required>
                                <?php for ($i = 3; $i <= 10; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($selected_days, $i); ?>>
                                        <?php echo $i; ?> ngày
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <p class="description">Chọn số ngày mà link đăng nhập sẽ có hiệu lực (từ 3-10 ngày)</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Tạo Auto-login Link'); ?>
            </form>

            <?php 
            // Hiển thị thông tin debug luôn có sẵn (không phụ thuộc vào $login_url)
            $current_user_id = get_option('walc_auto_login_user_id', null);
            if ($current_user_id): 
                $current_user = get_user_by('ID', $current_user_id);
                if ($current_user): 
            ?>
                <h2>Thông tin Token hiện tại</h2>
                <button type="button" id="toggle-debug-info" class="button button-secondary" onclick="toggleDebugInfo()">
                    <span id="toggle-text">Ẩn thông tin</span>
                </button>
                
                <div id="debug-info" style="display: none;">
                    <?php
                    $token = get_user_meta($current_user_id, 'walc_token', true);
                    $expires = get_user_meta($current_user_id, 'walc_expires', true);
                    $usage_count = get_user_meta($current_user_id, 'walc_usage_count', true);
                    $created_time = get_user_meta($current_user_id, 'walc_created_time', true);
                    $last_login = get_user_meta($current_user_id, 'walc_last_login', true);
                    ?>
                    
                    <table class="form-table">
                        <tr><th>User ID:</th><td><?php echo $current_user_id; ?></td></tr>
                        <tr><th>Username:</th><td><?php echo $current_user->user_login; ?></td></tr>
                        <tr><th>Token:</th><td><?php echo $token; ?></td></tr>
                        <tr><th>Thời gian tạo:</th><td><?php echo ($created_time ? date('d/m/Y h:i:s A', $created_time) : 'N/A'); ?></td></tr>
                        <tr><th>Thời gian hết hạn:</th><td><?php echo ($expires ? date('d/m/Y h:i:s A', $expires) : 'N/A'); ?></td></tr>
                        <tr><th>Số lần đã sử dụng:</th><td><?php echo $usage_count . '/5'; ?></td></tr>
                        <tr><th>Lần đăng nhập cuối:</th><td><?php echo ($last_login ? date('d/m/Y h:i:s A', $last_login) : 'Chưa đăng nhập'); ?></td></tr>
                        
                        <?php if ($expires && time() < $expires): 
                            $time_left = $expires - time();
                            $days_left = floor($time_left / DAY_IN_SECONDS);
                            $hours_left = floor(($time_left % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
                        ?>
                            <tr><th>Thời gian còn lại:</th><td><?php echo $days_left . ' ngày ' . $hours_left . ' giờ'; ?></td></tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <script>
                function toggleDebugInfo() {
                    var debugInfo = document.getElementById('debug-info');
                    var toggleText = document.getElementById('toggle-text');
                    
                    if (debugInfo.style.display === 'none') {
                        debugInfo.style.display = 'block';
                        toggleText.textContent = 'Ẩn thông tin';
                    } else {
                        debugInfo.style.display = 'none';
                        toggleText.textContent = 'Hiển thị thông tin';
                    }
                }
                </script>
            <?php 
                endif;
            endif; 
            ?>
            
            <?php if ($login_url): ?>
                <h2>Kết quả:</h2>
                <p><strong>Link đăng nhập (có hiệu lực trong <?php echo $selected_days; ?> ngày):</strong></p>
                <input type="text" readonly value="<?php echo esc_url($login_url); ?>" style="width: 100%; font-size: 1.1em;">
            <?php endif; ?>

            <!-- Thêm nút Xem log -->
            <form method="post" style="display:inline-block;margin-left:10px;">
                <input type="hidden" name="show_log" value="1">
                <input type="submit" class="button button-secondary" value="Xem log">
            </form>

            <!-- Hiển thị log nếu có yêu cầu -->
            <?php if (isset($_POST['show_log'])): ?>
                <h2>Log hoạt động Auto-login</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Hành động</th>
                            <th>User</th>
                            <th>Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $logs = get_option('walc_auto_login_log', array());
                        if (!empty($logs)):
                            foreach ($logs as $log):
                        ?>
                            <tr>
                                <td><?php echo date('d/m/Y h:i:s A', $log['time'] ?? ($log['created_time'] ?? time())); ?></td>
                                <td><?php echo esc_html($log['action']); ?></td>
                                <td><?php echo esc_html($log['username'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    foreach ($log as $k => $v):
                                        if (in_array($k, ['action','username','time','created_time'])) continue;
                                        if (is_array($v)) $v = json_encode($v);
                                        echo esc_html($k) . ': ' . esc_html($v) . '<br>';
                                    endforeach;
                                    ?>
                                </td>
                            </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                            <tr>
                                <td colspan="4">Chưa có log nào.</td>
                            </tr>
                        <?php
                        endif;
                        ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
<?php
    }

    // Thêm hàm ghi log
    private function walc_add_log($data) {
        $logs = get_option('walc_auto_login_log', array());
        if (!is_array($logs)) $logs = array();
        array_unshift($logs, $data); // Thêm mới lên đầu
        update_option('walc_auto_login_log', $logs);
    }
}

new Auto_Login();

// Fix linter: Định nghĩa tạm các hàm WordPress nếu chưa có (chỉ để tránh lỗi khi check ngoài WP)
if (!function_exists('current_time')) {
    function current_time($type) { return time(); }
}
if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key, $single) { return null; }
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
