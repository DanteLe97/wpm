<?php
/**
 * Test script for fixed image import functionality
 */

// Load WordPress
require_once('../../../wp-load.php');

// Test data với Jet Portfolio images và background images
$test_data = array(
    'auth_key' => 'test_key',
    'data' => array(
        'page' => array(
            'name' => 'Test Image Import Fixed',
            'content' => array(
                array(
                    'id' => 'test_element',
                    'elType' => 'widget',
                    'widgetType' => 'image',
                    'settings' => array(
                        'image' => array(
                            'id' => '',
                            'url' => 'https://temply.macusaone.com/wp-content/uploads/nails/n37_pink_short.jpg',
                            'alt' => 'Test Image',
                            'source' => 'library',
                            'size' => ''
                        )
                    ),
                    'elements' => array()
                ),
                array(
                    'id' => 'test_element_2',
                    'elType' => 'widget',
                    'widgetType' => 'html',
                    'settings' => array(
                        'html' => '<div style="background-image: url(https://temply.macusaone.com/wp-content/uploads/nails/n15_pink_long.jpg);">Test Background</div>'
                    ),
                    'elements' => array()
                ),
                array(
                    'id' => 'test_element_3',
                    'elType' => 'widget',
                    'widgetType' => 'html',
                    'settings' => array(
                        'html' => '<a href="https://temply.macusaone.com/wp-content/uploads/nails/n120_white_short.jpg"><img src="" alt="Test Image"></a>'
                    ),
                    'elements' => array()
                ),
                array(
                    'id' => 'jet_portfolio',
                    'elType' => 'widget',
                    'widgetType' => 'jet-portfolio',
                    'settings' => array(
                        'html' => '<div class="jet-portfolio__item">
                            <a class="jet-portfolio__link" href="https://temply.macusaone.com/wp-content/uploads/nails/n119_white_short.jpg">
                                <div class="jet-portfolio__image">
                                    <img class="jet-portfolio__image-instance" src="" alt="">
                                </div>
                            </a>
                        </div>'
                    ),
                    'elements' => array()
                )
            )
        )
    )
);

echo "Testing fixed image import functionality...\n";

// Test import bằng cách gọi trực tiếp function
$result = MAC_Importer_Import_API::import_page_from_data($test_data['data']['page']);

if (is_wp_error($result)) {
    echo "Import failed: " . $result->get_error_message() . "\n";
} else {
    echo "Import successful! Page ID: " . $result . "\n";
    
    // Get elementor data
    $elementor_data = get_post_meta($result, '_elementor_data', true);
    if ($elementor_data) {
        echo "Elementor data found:\n";
        echo json_encode($elementor_data, JSON_PRETTY_PRINT) . "\n";
    }
    
    // Check media library for imported images
    $attachments = get_posts(array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'numberposts' => -1,
        'meta_query' => array(
            array(
                'key' => '_original_image_url',
                'value' => 'https://temply.macusaone.com',
                'compare' => 'LIKE'
            )
        )
    ));
    
    echo "\nImported images found: " . count($attachments) . "\n";
    foreach ($attachments as $attachment) {
        $original_url = get_post_meta($attachment->ID, '_original_image_url', true);
        $current_url = wp_get_attachment_url($attachment->ID);
        echo "- Original: $original_url\n";
        echo "  Current: $current_url\n";
    }
}

