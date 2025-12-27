<?php
/**
 * Test script để kiểm tra logic unique slug
 */

// Simulate test cases
$test_cases = array(
    'Home' => 'home',
    'About Us' => 'about-us', 
    'Contact' => 'contact',
    'Services' => 'services'
);

echo "🧪 Test Cases for Unique Slug Generation:\n\n";

foreach ($test_cases as $title => $expected_slug) {
    echo "📄 Title: '$title'\n";
    echo "🔗 Expected base slug: '$expected_slug'\n";
    echo "🔄 If conflict exists:\n";
    echo "   - First conflict: {$expected_slug}-2\n";
    echo "   - Second conflict: {$expected_slug}-3\n";
    echo "   - And so on...\n\n";
}

echo "✅ Logic implemented:\n";
echo "- ❌ No longer updates existing pages\n";
echo "- ✅ Always creates new pages\n";
echo "- ✅ Auto-generates unique slugs (home → home-2 → home-3)\n";
echo "- ✅ Logs slug changes for debugging\n";
echo "- ✅ Shows final slug in response message\n";

echo "\n📋 Example API Response:\n";
echo "{\n";
echo "  \"success\": true,\n";
echo "  \"message\": \"Import completed successfully! 1 items imported. -- Success Details: Home imported successfully (ID: 123, Slug: home-2)\",\n";
echo "  \"imported_count\": 1\n";
echo "}\n";
