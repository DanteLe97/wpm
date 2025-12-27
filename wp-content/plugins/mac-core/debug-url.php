<?php
// Debug URL generation
if (!defined('ABSPATH')) {
    $wp_load = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die('WordPress not found');
    }
}

echo "<h2>URL Debug</h2>";
echo "<p><strong>content_url('plugins/mac-core/'):</strong> " . content_url('plugins/mac-core/') . "</p>";
echo "<p><strong>Full update URL:</strong> " . content_url('plugins/mac-core/') . "update-mac-core.php</p>";

$nonce = wp_create_nonce('update_mac_core');
echo "<p><strong>Test update URL:</strong> <a href='" . content_url('plugins/mac-core/') . "update-mac-core.php?update_mac=mac-core&_wpnonce=" . $nonce . "'>Click here to test update</a></p>";
?>

