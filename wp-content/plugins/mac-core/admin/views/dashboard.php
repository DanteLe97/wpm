<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Classes are already loaded in mac_core_init()

// License Manager removed - functionality moved to CRM_API_Manager
// $licenses = array(); // No license management needed
// $plugins = array(); // Plugin Manager not implemented yet

// Get MAC Core information
$mac_core_version = defined('MAC_CORE_VERSION') ? MAC_CORE_VERSION : 'Unknown';
$mac_core_path = defined('MAC_CORE_PATH') ? MAC_CORE_PATH : 'Unknown';

// Get CRM status
$domain_status = get_option('mac_domain_valid_status', '');
$domain_key = get_option('mac_domain_valid_key', '');
$domain_url = get_option('mac_domain_valid_url', '');

// Get MAC Menu version if installed
$mac_menu_version = '-';
if (file_exists(WP_PLUGIN_DIR . '/mac-menu/mac-menu.php')) {
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $mac_menu_plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/mac-menu/mac-menu.php');
    if (!empty($mac_menu_plugin_data['Version'])) {
        $mac_menu_version = $mac_menu_plugin_data['Version'];
    }
}

// Clean up invalid values
if ($domain_status === '0' || $domain_status === null || $domain_status === '') {
    $domain_status = 'inactive';
}
if ($domain_key === '0' || $domain_key === null) {
    $domain_key = '';
}

// Create last_sync option if it doesn't exist
if (false === get_option('mac_domain_last_sync')) {
    add_option('mac_domain_last_sync', '');
}

// Get MAC Core plugin data
$mac_core_plugin_data = get_plugin_data(MAC_CORE_PATH . 'mac-core.php');
$mac_core_description = isset($mac_core_plugin_data['Description']) ? $mac_core_plugin_data['Description'] : 'MAC Core Plugin';

// Use MAC_CORE_VERSION constant for current version, fallback to get_plugin_data
$mac_core_current_version = defined('MAC_CORE_VERSION') ? MAC_CORE_VERSION : ($mac_core_plugin_data['Version'] ?? '1.0.0');

// Ensure kvp_params is available for the form
?>
<script>
    var kvp_params = {
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>'
    };
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

    // MAC Core admin variables are defined in class-admin.php
</script>
<div class="wrap">
    <h1><?php echo esc_html__('MAC Core Dashboard', 'mac-core'); ?></h1>

    <?php
    // Display notification when redirected from MAC Menu due to missing key
    if (isset($_GET['message']) && $_GET['message'] === 'no_key') {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>⚠️ MAC Menu Dashboard Access Restricted:</strong> You need to add a valid license key to access the MAC Menu dashboard. Please add your license key below to continue.</p>';
        echo '</div>';
    }
    ?>
    <div class="mac-core-dashboard">
        <!-- Top Row Grid -->
        <div class="mac-core-grid-row">
            <div class="mac-core-dashboard-section-wrap">
                <div class="mac-core-dashboard-section-left">
                    <!-- Add-ons Section (moved from plugins.php) -->
                    <div class="mac-core-dashboard-section mac-core-grid-item mac-core-full-width">
                        <h2><?php echo esc_html__('Add-ons', 'mac-core'); ?></h2>

                        <?php
                        if (!function_exists('is_plugin_active')) {
                            require_once ABSPATH . 'wp-admin/includes/plugin.php';
                        }

                        // Define add-ons list
                        $mac_core_addons = array(
                            'mac-menu' => array(
                                'name' => 'MAC Menu',
                                'description' => 'Create beautiful menu tables for your restaurant website.',
                                'slug' => 'mac-menu',
                                'file' => 'mac-menu/mac-menu.php',
                                // GitHub functionality removed - using CRM only
                            ),
                            'mac-importer-demo' => array(
                                'name' => 'MAC Importer Demo',
                                'description' => 'Demo importer for MAC design templates and Elementor pages.',
                                'slug' => 'mac-importer-demo',
                                'file' => 'mac-importer-demo/mac-importer-demo.php',
                                // GitHub functionality removed - using CRM only
                            ),
                            'mac-log-viewer' => array(
                                'name' => 'MAC Log Viewer',
                                'description' => 'View and manage PHP error logs with syntax highlighting.',
                                'slug' => 'mac-log-viewer',
                                'file' => 'mac-log-viewer/mac-log-viewer.php',
                                // GitHub functionality removed - using CRM only
                            ),
                            'mac-seasonal-effects' => array(
                                'name' => 'MAC Seasonal Effects',
                                'description' => 'Plugin that allows you to select and customize seasonal effects (animations) that run on your website based on events (Halloween, Thanksgiving, etc.)',
                                'slug' => 'mac-seasonal-effects',
                                'file' => 'mac-seasonal-effects/mac-seasonal-effects.php',
                                // GitHub functionality removed - using CRM only
                            ),
                            'mac-interactive-tutorials' => array(
                                'name' => 'MAC Interactive Tutorials',
                                'description' => 'Tạo tutorials trực tiếp trong WordPress admin',
                                'slug' => 'mac-interactive-tutorials',
                                'file' => 'mac-interactive-tutorials/mac-interactive-tutorials.php',
                                // GitHub functionality removed - using CRM only
                            ),
                            'mac-role' => array(
                                'name' => 'MAC Role URL Dashboard',
                                'description' => 'Quản lý URL admin được phép truy cập theo Role/User với UI đơn giản.',
                                'slug' => 'mac-role',
                                'file' => 'mac-role/mac-role.php',
                                // GitHub functionality removed - using CRM only
                            ),
                            'mac-unused-images-scanner' => array(
                                'name' => 'MAC Unused Images Scanner',
                                'description' => 'Quét và xóa ảnh không sử dụng trong WordPress với WP-Cron, có tiến trình và bulk delete. Hỗ trợ WebP cleanup.',
                                'slug' => 'mac-unused-images-scanner',
                                'file' => 'mac-unused-images-scanner/mac-unused-images-scanner.php',
                                // GitHub functionality removed - using CRM only
                            )
                        );

                        // Generic function for addon buttons
                        if (!function_exists('mac_core_render_addon_buttons')) {
                            function mac_core_render_addon_buttons($addon_slug, $addon_data, $domain_status, $domain_key) {
                                $name = $addon_data['name'] ?? $addon_slug;
                                $current_version = $addon_data['version'] ?? '1.0.0';
                                $is_active = !empty($addon_data['is_active']);
                                $is_installed = isset($addon_data['version']) && $addon_data['version'] !== 'Not Installed';
                                
                                ob_start();
                                ?>
                                <div class="mac-core-addon-actions">
                                    <?php if ($is_active) : ?>
                                        <?php if (!empty($addon_data['update_info']) && $domain_status === 'activate' && !empty($domain_key)) : ?>
                                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=mac-core&action=update&addon=' . $addon_slug), 'mac_core_plugin_action')); ?>" class="button button-primary">
                                                <?php echo esc_html__('Update Plugin', 'mac-core'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=mac-core&action=deactivate&addon=' . $addon_slug), 'mac_core_plugin_action')); ?>" class="button" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to deactivate this add-on?', 'mac-core')); ?>');">
                                            <?php echo esc_html__('Deactivate Plugin', 'mac-core'); ?>
                                        </a>
                                    <?php elseif ($is_installed) : ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=mac-core&action=activate&addon=' . $addon_slug), 'mac_core_plugin_action')); ?>" class="button button-primary">
                                            <?php echo esc_html__('Activate Plugin', 'mac-core'); ?>
                                        </a>
                                    <?php else : ?>
                                        <button class="button button-primary mac-core-install-plugin" data-plugin-slug="<?php echo esc_attr($addon_slug); ?>">
                                            <?php echo esc_html__('Install Now', 'mac-core'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($domain_status === 'activate' && !empty($domain_key)) : ?>
                                        <button type="button" class="button mac-core-check-update" 
                                                data-plugin-slug="<?php echo esc_attr($addon_slug); ?>"
                                                data-current-version="<?php echo esc_attr($current_version); ?>">
                                            <?php echo esc_html__('Check Update', 'mac-core'); ?>
                                        </button>
                                        <button type="button" class="button mac-core-update-plugin" 
                                                data-plugin-slug="<?php echo esc_attr($addon_slug); ?>"
                                                style="display: none;">
                                            <?php echo esc_html__('Update ', 'mac-core') . esc_html($name); ?>
                                        </button>
                                        <div class="mac-core-status-result" 
                                             data-plugin-slug="<?php echo esc_attr($addon_slug); ?>" 
                                             style="display: none;"></div>
                                    <?php endif; ?>
                                </div>
                                <?php
                                return ob_get_clean();
                            }
                        }

                        // Helper
                        if (!function_exists('mac_core_get_plugin_description')) {
                            function mac_core_get_plugin_description($plugin_file)
                            {
                                if (!function_exists('get_plugin_data')) {
                                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                                }
                                $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
                                return isset($plugin_data['Description']) ? $plugin_data['Description'] : '';
                            }
                        }

                        $update_manager = \MAC_Core\Update_Manager::get_instance();
                        $installed_addons = array();
                        $available_addons = array();
                        $domain_license_active_local = $update_manager->is_domain_license_active();

                        foreach ($mac_core_addons as $addon_slug => $addon_data) {
                            $plugin_file = $addon_data['file'];
                            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
                            if (file_exists($plugin_path)) {
                                $real_version = $update_manager->get_plugin_version($addon_slug);
                                $real_description = mac_core_get_plugin_description($plugin_file);
                                $is_active = is_plugin_active($plugin_file);
                                $update_info = false;
                                if ($domain_license_active_local) {
                                    // GitHub functionality removed - using CRM only
                                    $update_info = false;
                                }
                                $addon_data['version'] = $real_version;
                                $addon_data['description'] = !empty($real_description) ? $real_description : $addon_data['description'];
                                $addon_data['is_active'] = $is_active;
                                $addon_data['file'] = $plugin_file;
                                $addon_data['update_info'] = $update_info;
                                // GitHub functionality removed - using CRM only
                                $addon_data['github_user'] = false;
                                if ($is_active) {
                                    $installed_addons[$addon_slug] = $addon_data;
                                } else {
                                    $available_addons[$addon_slug] = $addon_data;
                                }
                            } else {
                                $addon_data['version'] = 'Not Installed';
                                $addon_data['is_active'] = false;
                                $addon_data['update_info'] = false;
                                // GitHub functionality removed - using CRM only
                                $addon_data['github_user'] = false;
                                $available_addons[$addon_slug] = $addon_data;
                            }
                        }

                        // Merge + sort
                        $all_addons = array();
                        foreach ($installed_addons as $slug => $data) {
                            $data['installed'] = true;
                            $all_addons[] = $data;
                        }
                        foreach ($available_addons as $slug => $data) {
                            $data['installed'] = ($data['version'] !== 'Not Installed');
                            $all_addons[] = $data;
                        }
                        usort($all_addons, function ($a, $b) {
                            $rank = function ($x) {
                                if (!empty($x['slug']) && $x['slug'] === 'mac-menu') return 0;
                                if (!empty($x['is_active']) && $x['is_active']) return 1;
                                if (!empty($x['installed'])) return 2;
                                return 3;
                            };
                            $ra = $rank($a);
                            $rb = $rank($b);
                            if ($ra === $rb) {
                                return strcasecmp($a['name'], $b['name']);
                            }
                            return ($ra < $rb) ? -1 : 1;
                        });
                        ?>

                        <div class="mac-core-addons-wrap">
                            <div class="mac-core-addon-cards">
                                <?php foreach ($all_addons as $addon_data) : $addon_slug = $addon_data['slug']; ?>
                                    <div class="mac-core-addon-card horizontal" style="position: relative;">
                                        <div class="mac-core-addon-logo">MAC</div>
                                        <div class="mac-core-addon-body">
                                            <h3 class="mac-core-addon-title"><?php echo esc_html($addon_data['name']); ?></h3>
                                            <?php
                                            $allowed = array(
                                                'cite' => array(),
                                                'a' => array('href' => array(), 'title' => array(), 'target' => array(), 'rel' => array()),
                                            );
                                            ?>
                                            <p class="mac-core-addon-desc"><?php echo wp_kses((string) $addon_data['description'], $allowed); ?></p>
                                            <div class="mac-core-addon-meta">
                                                <?php if (!empty($addon_data['is_active'])) : ?>
                                                    <?php if (!empty($addon_data['update_info']) && $domain_license_active_local) : ?>
                                                        <span class="badge-version has-update" title="<?php echo esc_attr__('Update available', 'mac-core'); ?>"><?php echo esc_html($addon_data['version'] . ' → ' . $addon_data['update_info']['latest_version']); ?></span>
                                                    <?php else : ?>
                                                        <span class="badge-version is-latest"><?php echo esc_html($addon_data['version']); ?></span>
                                                    <?php endif; ?>
                                                <?php elseif (isset($addon_data['version']) && $addon_data['version'] !== 'Not Installed') : ?>
                                                    <span class="badge-version is-latest"><?php echo esc_html($addon_data['version']); ?></span>
                                                <?php else : ?>
                                                    <span class="badge-version is-missing"><?php echo esc_html__('Not Installed', 'mac-core'); ?></span>
                                                <?php endif; ?>
                                            </div>

                                            <?php
                                            // Status line like: "MAC Menu Status: Installed & Active"
                                            $status_label = sprintf('%s', __('Status:', 'mac-core'));
                                            if (!empty($addon_data['is_active'])) {
                                                $status_text = __('Installed & Active', 'mac-core');
                                                $status_class = 'mac-core-status-active';
                                            } elseif (isset($addon_data['version']) && $addon_data['version'] !== 'Not Installed') {
                                                $status_text = __('Installed but Inactive', 'mac-core');
                                                $status_class = 'mac-core-status-inactive';
                                            } else {
                                                $status_text = __('Not Installed', 'mac-core');
                                                $status_class = 'mac-core-status-missing';
                                            }
                                            ?>
                                            <div class="mac-core-addon-status-line">
                                                <span class="mac-core-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span>
                                            </div>
                                        </div>
                                        <div class="mac-core-addon-actions-wrap">
                                            <?php echo mac_core_render_addon_buttons($addon_slug, $addon_data, $domain_status, $domain_key); ?>
                                        </div>

                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                    <!-- Bottom Row - System Information Section -->
                    <div class="mac-core-grid-row">
                        <div class="mac-core-dashboard-section mac-core-grid-item mac-core-full-width">
                            <h2><?php echo esc_html__('System Information', 'mac-core'); ?></h2>
                            <table class="widefat">
                                <tbody>
                                    <tr>
                                        <td><strong><?php echo esc_html__('WordPress Version', 'mac-core'); ?></strong></td>
                                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo esc_html__('PHP Version', 'mac-core'); ?></strong></td>
                                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo esc_html__('MySQL Version', 'mac-core'); ?></strong></td>
                                        <td><?php echo esc_html($wpdb->get_var('SELECT VERSION()')); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo esc_html__('MAC Core Version', 'mac-core'); ?></strong></td>
                                        <td><?php echo esc_html(MAC_CORE_VERSION); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="mac-core-dashboard-section-right">
                    <!-- MAC Core Information Section -->
                    <div class="mac-core-dashboard-section mac-core-grid-item">
                        <h2><?php echo esc_html__('MAC Core Information', 'mac-core'); ?></h2>

                        <div class="mac-core-info-grid">
                            <div class="mac-core-info-item">
                                <strong><?php echo esc_html__('Version:', 'mac-core'); ?></strong>
                                <span class="mac-core-version"><?php echo esc_html($mac_core_current_version); ?></span>
                            </div>
                        </div>
                        <div class="mac-core-crm-status">
                            <div class="mac-core-status-item">
                                <strong><?php echo esc_html__('Status:', 'mac-core'); ?></strong>
                                <span class="mac-core-status mac-core-status-<?php echo $domain_status === 'activate' ? 'active' : 'inactive'; ?>">
                                    <?php echo $domain_status === 'activate' ? esc_html__('Active', 'mac-core') : esc_html__('Inactive', 'mac-core'); ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($domain_status !== 'activate' || empty($domain_key)) : ?>
                            <div class="mac-core-notice mac-core-notice-warning">
                                <p><?php echo esc_html__('⚠️ CRM connection is not active. Please add a valid license key below to activate the connection.', 'mac-core'); ?></p>
                            </div>
                        <?php else : ?>
                            <div class="mac-core-notice mac-core-notice-success">
                                <p><?php echo esc_html__('✅ CRM connection is active and working properly.', 'mac-core'); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="mac-core-actions">
                            <button type="button" class="button mac-core-test-validate-url">
                                <?php echo esc_html__('Test Validate URL', 'mac-core'); ?>
                            </button>

                            <?php if ($domain_status === 'activate' && !empty($domain_key)) : ?>

                                <button type="button" class="button mac-core-check-update-mac-core" data-current-version="<?php echo esc_attr($mac_core_current_version); ?>"><?php echo esc_html__('Check update MAC Core', 'mac-core'); ?></button>
                                <?php
                                $update_nonce = wp_create_nonce('update_mac_core');
                                $direct_update_url = content_url('plugins/mac-core/update-mac-core.php') . '?update_mac=mac-core&_wpnonce=' . $update_nonce;
                                ?>
                                <button type="button" class="button mac-core-update-mac-core" data-update-url="<?php echo esc_url($direct_update_url); ?>" style="display:none;">
                                    <?php echo esc_html__('Update MAC Core', 'mac-core'); ?>
                                </button>
                                <div id="mac-core-self-update-status" class="mac-core-status-result" style="display:none;"></div>
                            <?php endif; ?>

                        </div>
                        <!-- License Management Section -->
                        <h2><?php echo esc_html__('License Management', 'mac-core'); ?></h2>
                        <!-- Add License Form -->
                        <div class="mac-core-form-container">
                            <h3><?php echo esc_html__('Add License', 'mac-core'); ?></h3>
                            <?php $has_key = !empty($domain_key); ?>
                            <?php if ($has_key) : ?>
                                <p><?php echo esc_html__('A license key already exists for this domain.', 'mac-core'); ?></p>
                                <button type="button" class="button" id="toggle-license-form"><?php echo esc_html__('Add/Change License Key', 'mac-core'); ?></button>
                            <?php endif; ?>
                            <div id="kvp-container" class="key-domain-wrap" style="<?php echo $has_key ? 'display:none;' : ''; ?>">
                                <form id="kvp-form">
                                    <?php wp_nonce_field('mac_core_add_license', 'mac_core_license_nonce'); ?>
                                    <label for="kvp-key-input"><?php echo esc_html__('Enter your key:', 'mac-core'); ?></label>
                                    <input type="text" id="kvp-key-input" name="key" value="MAC Menu" required>
                                    <button type="submit"><?php echo esc_html__('Validate', 'mac-core'); ?></button>
                                </form>
                                <div id="kvp-result"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
