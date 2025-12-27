<?php
/**
 * MAC Core Update Script - Runs independently
 * Similar to old user code but uses CRM API
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Find wp-load.php by going up directories (support when running from runner in wp-content)
    $dir = __DIR__;
    $found = false;
    for ($i = 0; $i < 10; $i++) {
        $candidate = $dir . '/wp-load.php';
        if (file_exists($candidate)) {
            require_once $candidate;
            $found = true;
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) { break; }
        $dir = $parent;
    }
    if (!$found) {
        die('WordPress not found');
    }
}

// Check if user can update plugins
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
}

// Check nonce for security
if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'update_mac_core')) {
    wp_die('Security check failed');
}

// ====== CRM Configuration ======
$domain_status = get_option('mac_domain_valid_status', '');
$domain_key = get_option('mac_domain_valid_key', '');

// Get current MAC Core version automatically
$current_version = '1.0.0'; // fallback
if (defined('MAC_CORE_VERSION')) {
    $current_version = MAC_CORE_VERSION;
} else {
    // Fallback: get from plugin header
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/mac-core/mac-core.php');
    $current_version = $plugin_data['Version'] ?? '1.0.0';
}

// Check if CRM connection is active
if ($domain_status !== 'activate' || empty($domain_key)) {
    wp_die('CRM connection must be active to update MAC Core');
}

// ====== Safe CRM API Call Function ======
if (!function_exists('crm_wp_get')) {
    function crm_wp_get($url, $token = '')
{
    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'User-Agent'    => 'WordPress Plugin',
            'Accept'        => 'application/json',
        ),
        'timeout' => 60, // Longer timeout for downloads
    );
    
    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        error_log('CRM request error: ' . $response->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($code !== 200) {
        error_log("CRM HTTP {$code} for {$url}. Body: {$body}");
        return false;
    }

    return $body;
}
}

// ====== Download file from CRM ======
if (!function_exists('download_file_from_crm')) {
    function download_file_from_crm($url, $token, $dest_file)
{
    // Ensure destination directory exists
    $dest_dir = dirname($dest_file);
    if (!is_dir($dest_dir)) {
        if (!wp_mkdir_p($dest_dir)) {
            error_log('CRM download error: Failed to create directory ' . $dest_dir);
            return array('success' => false, 'message' => 'Failed to prepare temp directory');
        }
    }

    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'User-Agent'    => 'WordPress Plugin',
        ),
        'timeout' => 180, // Longer timeout for large files
        'stream' => true,  // Stream download to file
        'filename' => $dest_file,
    );
    
    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        error_log('CRM download error: ' . $response->get_error_message());
        return array('success' => false, 'message' => $response->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        $body_preview = substr(wp_remote_retrieve_body($response), 0, 200);
        error_log("CRM download HTTP {$code} for {$url} - Body: {$body_preview}");
        return array('success' => false, 'message' => 'Download HTTP ' . $code);
    }

    if (!file_exists($dest_file) || filesize($dest_file) === 0) {
        return array('success' => false, 'message' => 'Downloaded file missing or empty');
    }

    return array('success' => true, 'file' => $dest_file);
}
}

// ====== Delete directory ======
if (!function_exists('delete_directory')) {
    function delete_directory($dir)
    {
        if (!is_dir($dir)) {
            return true;
        }

        $objects = array_diff(scandir($dir), array('.', '..'));
        foreach ($objects as $object) {
            $file = $dir . '/' . $object;
            if (is_dir($file)) {
                if (!delete_directory($file)) {
                    return false;
                }
            } else {
                if (!@unlink($file)) {
                    return false;
                }
            }
        }

        return @rmdir($dir);
    }
}

// ====== Copy directory ======
if (!function_exists('copy_directory')) {
    function copy_directory($source, $destination)
    {
        if (!is_dir($source)) {
            return false;
        }
        
        if (!wp_mkdir_p($destination)) {
            return false;
        }
        
        $objects = array_diff(scandir($source), array('.', '..'));
        foreach ($objects as $object) {
            $source_path = $source . '/' . $object;
            $dest_path = $destination . '/' . $object;
            
            if (is_dir($source_path)) {
                if (!copy_directory($source_path, $dest_path)) {
                    return false;
                }
            } else {
                if (!copy($source_path, $dest_path)) {
                    return false;
                }
            }
        }
        
        return true;
    }
}

// ====== Download and replace MAC Core ======
if (!function_exists('download_and_replace_mac_core')) {
    function download_and_replace_mac_core()
{
    global $domain_key;
    
    try {
        // 1) Get download URL from CRM
        if (!class_exists('MAC_Core\\CRM_API_Manager')) {
            throw new Exception('CRM API Manager not loaded');
        }
        $crm = \MAC_Core\CRM_API_Manager::get_instance();
        $req = $crm->download_plugin('mac-core');
        if (!$req || empty($req['success']) || empty($req['data']['download_url'])) {
            $msg = isset($req['message']) ? $req['message'] : 'CRM did not return download_url for mac-core';
            throw new Exception('Failed to get plugin info from CRM: ' . $msg);
        }
        $download_url = $req['data']['download_url'];

        // 2) Download minimal ZIP and extract directly to WP_PLUGIN_DIR
        if (!function_exists('download_url') || !function_exists('unzip_file')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
        $tmp_zip = download_url($download_url);
        if (is_wp_error($tmp_zip)) {
            throw new Exception('Download failed: ' . $tmp_zip->get_error_message());
        }

        // Initialize Filesystem in direct mode for unzip_file to work
        if (!defined('FS_METHOD')) {
            define('FS_METHOD', 'direct');
        }
        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        $unzipped = unzip_file($tmp_zip, WP_PLUGIN_DIR);
        if (is_wp_error($unzipped)) {
            // Fallback to ZipArchive if WP_Filesystem is not accessible
            $zipRes = false;
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                $res = $zip->open($tmp_zip);
                if ($res === TRUE) {
                    $zipRes = $zip->extractTo(WP_PLUGIN_DIR);
                    $zip->close();
                }
            }
            @unlink($tmp_zip);
            if (!$zipRes) {
                throw new Exception('Unzip failed: ' . $unzipped->get_error_message());
            } else {
                return array('success' => true, 'message' => 'Unzipped via ZipArchive to plugins directory successfully.');
            }
        }
        @unlink($tmp_zip);

        return array('success' => true, 'message' => 'Unzipped to plugins directory successfully.');
        
    } catch (Exception $e) {
        if (isset($tmp_zip) && file_exists($tmp_zip)) { @unlink($tmp_zip); }
        return array('success' => false, 'message' => 'Update failed: ' . $e->getMessage());
    }
}
}

// ====== Trigger update ======
if (isset($_GET['update_mac']) && $_GET['update_mac'] === 'mac-core') {
    // If running inside the plugin directory (Windows locks), relaunch from wp-content runner
    $target_plugin_dir = str_replace('\\', '/', WP_PLUGIN_DIR . '/mac-core');
    $this_file_path   = str_replace('\\', '/', __FILE__);
    $is_inside_target = strpos($this_file_path, rtrim($target_plugin_dir, '/')) === 0;
    if ($is_inside_target && !isset($_GET['runner'])) {
        $runner_path = WP_CONTENT_DIR . '/update-mac-core-runner.php';
        @copy(__FILE__, $runner_path);
        $query = $_GET; $query['runner'] = '1';
        $runner_url = content_url('update-mac-core-runner.php') . '?' . http_build_query($query);
        wp_safe_redirect($runner_url);
        exit();
    }
    // Show update progress
    echo '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';
    echo '<h2>🔄 Updating MAC Core...</h2>';
    echo '<p>Please wait while we update MAC Core plugin...</p>';
    
    // Perform update
    $result = download_and_replace_mac_core();
    
    if ($result['success']) {
        echo '<div style="color: green; padding: 15px; background: #f0f8f0; border: 1px solid #4caf50; border-radius: 3px; margin: 20px 0;">';
        echo '<h3>✅ Update Successful!</h3>';
        echo '<p>' . esc_html($result['message']) . '</p>';
        echo '<p><strong>Current Version:</strong> ' . esc_html($current_version) . '</p>';
        echo '</div>';
        echo '<p><a href="' . admin_url('admin.php?page=mac-core') . '" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;">Go to MAC Core Dashboard</a></p>';
    } else {
        echo '<div style="color: red; padding: 15px; background: #fff0f0; border: 1px solid #f44336; border-radius: 3px; margin: 20px 0;">';
        echo '<h3>❌ Update Failed!</h3>';
        echo '<p>' . esc_html($result['message']) . '</p>';
        echo '<p><strong>Current Version:</strong> ' . esc_html($current_version) . '</p>';
        echo '</div>';
        echo '<p><a href="' . admin_url('admin.php?page=mac-core') . '" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;">Back to MAC Core Dashboard</a></p>';
    }
    
    echo '</div>';
    
    // Clean up runner if we are running as the runner
    if (isset($_GET['runner'])) {
        $runner_self = WP_CONTENT_DIR . '/update-mac-core-runner.php';
        if (is_file($runner_self)) { @unlink($runner_self); }
    }

    // Auto redirect after 5 seconds
    echo '<script>setTimeout(function(){ window.location.href = "' . admin_url('admin.php?page=mac-core') . '"; }, 5000);</script>';
    exit();
}

// ====== Show update link ======
if (isset($_GET['show_update_link'])) {
    $nonce = wp_create_nonce('update_mac_core');
    $update_url = plugins_url('update-mac-core.php', __FILE__) . '?update_mac=mac-core&_wpnonce=' . $nonce;
    
    echo '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';
    echo '<h2>🔗 MAC Core Update Link</h2>';
    echo '<p><strong>Current Version:</strong> ' . esc_html($current_version) . '</p>';
    echo '<p>Click the link below to update MAC Core:</p>';
    echo '<p><a href="' . esc_url($update_url) . '" style="background: #0073aa; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px;">Update MAC Core Now</a></p>';
    echo '<p><strong>Note:</strong> This will update MAC Core plugin. Make sure to backup your data first.</p>';
    echo '</div>';
    exit();
}
?>
