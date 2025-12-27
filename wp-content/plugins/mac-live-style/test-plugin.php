<?php
/**
 * Test file để kiểm tra plugin MAC LiveStyle
 */

// Kiểm tra xem plugin có load được không
if (class_exists('ElementorLiveColorChanger')) {
    echo "Plugin đã được load thành công!\n";
} else {
    echo "Plugin chưa được load!\n";
}

// Kiểm tra các functions WordPress
if (function_exists('add_action')) {
    echo "WordPress functions đã sẵn sàng!\n";
} else {
    echo "WordPress functions chưa sẵn sàng!\n";
}

// Kiểm tra ABSPATH
if (defined('ABSPATH')) {
    echo "ABSPATH đã được định nghĩa: " . ABSPATH . "\n";
} else {
    echo "ABSPATH chưa được định nghĩa!\n";
}
?> 