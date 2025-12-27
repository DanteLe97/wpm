<?php
/**
 * MacAPI Core Plugin - Xử lý auto-login độc lập cho API, không trùng với add-on/auto-login.php
 */
if (!defined('ABSPATH')) exit;

class MacAPI_Auto_Login {
    public function __construct() {
        add_action('init', [$this, 'macapi_auto_login_handler']);
        add_action('init', [$this, 'macapi_crm_login_handler']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    // CRM API endpoint: ?macapi_crm_login=1&auth_key=xxx&user=xxx
    public function macapi_crm_login_handler() {
        if (!isset($_GET['macapi_crm_login']) || $_GET['macapi_crm_login'] != 1) {
            return;
        }

        $auth_key = $_GET['auth_key'] ?? '';
        $user_login = sanitize_user($_GET['user'] ?? '');
        $shared_secret = get_option('mac_domain_valid_key', ''); // Lấy key từ CRM đã đăng ký

        header('Content-Type: application/json');

        if (empty($shared_secret)) {
            wp_send_json_error(['message' => 'CRM key chưa được đăng ký.'], 403);
        }

        if ($auth_key !== $shared_secret) {
            wp_send_json_error(['message' => 'Auth key không hợp lệ.'], 403);
        }

        if (empty($user_login)) {
            wp_send_json_error(['message' => 'Thiếu user.'], 400);
        }

        // Tìm user theo user_login
        $user = get_user_by('login', $user_login);
        if (!$user) {
            wp_send_json_error(['message' => 'User not found.'], 404);
        }

        // Delete old token before creating new token
        delete_user_meta($user->ID, 'macapi_token');

        // Tạo token mới (không có thời gian hết hạn)
        $token = wp_generate_password(32, false, false);
        update_user_meta($user->ID, 'macapi_token', $token);

        // Trả về JSON response
        wp_send_json_success([
            'login_url'   => site_url('?macapi_token=' . $token),
            'username'    => $user->user_login,
            'role'        => implode(',', $user->roles)
        ]);
    }

    // Add admin menu
    public function add_admin_menu() {
        // add_submenu_page(
        //     'mac-core', // Parent slug
        //     'Auto Login Manager', // Page title
        //     'Auto Login', // Menu title
        //     'manage_options', // Capability
        //     'mac-auto-login', // Menu slug
        //     [$this, 'admin_page'] // Callback
        // );
    }

    // Admin page content
    public function admin_page() {
        if (isset($_POST['create_link']) && isset($_POST['user_id']) && isset($_POST['days'])) {
            $user_id = intval($_POST['user_id']);
            $days = intval($_POST['days']);
            $login_url = $this->macapi_generate_auto_login_link($user_id, $days);
            echo '<div class="notice notice-success"><p>Auto-login link has been created!</p></div>';
        }
        
        $users = get_users(['role__in' => ['administrator', 'editor']]);
        $crm_key = get_option('mac_domain_valid_key', '');
        ?>
        <div class="wrap">
            <h1>Auto Login Manager</h1>
            
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th><label for="user_id">Select User:</label></th>
                        <td>
                            <select name="user_id" id="user_id" required>
                                <option value="">-- Select User --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user->ID; ?>">
                                        <?php echo esc_html($user->user_login) . ' (' . esc_html($user->user_email) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="days">Validity Period:</label></th>
                        <td>
                            <select name="days" id="days" required>
                                <option value="1">1 day</option>
                                <option value="3" selected>3 days</option>
                                <option value="7">7 days</option>
                                <option value="30">30 days</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Create Auto-Login Link', 'primary', 'create_link'); ?>
            </form>

            <?php if (isset($login_url)): ?>
                <div class="card" style="max-width: 600px; margin-top: 20px;">
                    <h2>Auto-Login Link</h2>
                    <p><strong>Link:</strong></p>
                    <input type="text" readonly value="<?php echo esc_url($login_url); ?>" style="width: 100%; font-size: 14px; padding: 8px;">
                    <p><small>Copy this link and send it to the user. The link will expire after <?php echo $days; ?> day(s).</small></p>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2>CRM API Endpoint</h2>
                <?php if (!empty($crm_key)): ?>
                    <p><strong>URL:</strong></p>
                    <input type="text" readonly value="<?php echo site_url('?macapi_crm_login=1&auth_key=' . $crm_key . '&user=USERNAME'); ?>" style="width: 100%; font-size: 14px; padding: 8px;">
                    <p><small>Replace USERNAME with the actual username. Auth key: <code><?php echo esc_html($crm_key); ?></code></small></p>
                    <p><strong>⚠️ Note:</strong> When calling this API, the user will be automatically logged in and redirected to the admin dashboard!</p>
                <?php else: ?>
                    <p><strong>⚠️ CRM key is not registered!</strong></p>
                    <p>Please register the domain with CRM first to use this API endpoint.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // Xử lý auto-login qua token riêng
    public function macapi_auto_login_handler() {
        if (!isset($_GET['macapi_token'])) {
            return;
        }
        
        $token = sanitize_text_field($_GET['macapi_token']);
        
        $users = get_users([
            'meta_key'   => 'macapi_token',
            'meta_value' => $token,
            'number'     => 1,
            'fields'     => 'all',
        ]);
        
        if (empty($users)) {
            wp_die('Token không hợp lệ.');
        }
        
        $user = $users[0];
        
        // Đăng nhập
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        
        // DEBUG: Kiểm tra headers đã gửi chưa
        if (!headers_sent()) {
            wp_redirect(admin_url());
            exit;
        } else {
            echo '<script>window.location="' . admin_url() . '";</script>';
            exit;
        }
    }

    // Tạo link auto-login riêng
    public function macapi_generate_auto_login_link($user_id, $days = 3) {
        $token = wp_generate_password(32, false, false);
        $expires = time() + $days * DAY_IN_SECONDS;
        update_user_meta($user_id, 'macapi_token', $token);
        update_user_meta($user_id, 'macapi_expires', $expires);
        return site_url('?macapi_token=' . $token);
    }
}
new MacAPI_Auto_Login();
