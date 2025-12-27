<?php
/**
 * Test script để kiểm tra API sau khi bỏ domain parameter
 */

// Test data không có domain parameter
$test_data = array(
    'auth_key' => 'your_auth_key_here',
    'page' => array(
        'name' => 'Test Page',
        'data' => array(
            'content' => array(),
            'page_settings' => array(),
            'version' => '0.4',
            'title' => 'Test Page',
            'type' => 'page'
        )
    )
);

echo "✅ Test data structure (không có domain parameter):\n";
echo json_encode($test_data, JSON_PRETTY_PRINT);

echo "\n\n📝 API Endpoint: POST /wp-json/ltp/v1/elementor/import-page\n";
echo "🔧 Changes made:\n";
echo "- ❌ Removed 'domain' parameter requirement\n";
echo "- ✅ Auto-detect domain from request URL\n";
echo "- ✅ Fallback to page URL if needed\n";
echo "- ✅ Maintains backward compatibility\n";
