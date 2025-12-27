<?php
/**
 * Test Activation Hook - Debug script
 * 
 * Usage: Access this file directly in browser
 * Example: http://localhost/wpm/wp-content/plugins/mac-role/test-activation.php
 * 
 * This will simulate activation and show what happens
 */

// Load WordPress
require_once( '../../../wp-load.php' );

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
	die( 'Access denied. You must be an administrator.' );
}

echo '<h1>Test Activation Hook</h1>';
echo '<pre>';

// Check if class exists
if ( ! class_exists( 'RUD_Default_Links' ) ) {
	echo "ERROR: RUD_Default_Links class not found!\n";
	echo "Make sure plugin is activated.\n";
	exit;
}

// Get default links
$default_links = RUD_Default_Links::get_default_links();
echo "Found " . count( $default_links ) . " default links:\n";
foreach ( $default_links as $link ) {
	echo "  - {$link['id']}: {$link['label']} ({$link['url']})\n";
}

echo "\n";

// Check current enabled status
$enabled_status = get_option( 'rud_default_links_enabled', array() );
echo "Current enabled status:\n";
print_r( $enabled_status );

echo "\n";

// Check existing mappings
global $wpdb;
$table_name = $wpdb->prefix . 'role_url_map';
$existing_mappings = $wpdb->get_results( "SELECT * FROM {$table_name} WHERE meta LIKE '%is_default%'", ARRAY_A );
echo "Existing default mappings in database: " . count( $existing_mappings ) . "\n";
foreach ( $existing_mappings as $mapping ) {
	$meta = maybe_unserialize( $mapping['meta'] );
	$default_link_id = isset( $meta['default_link_id'] ) ? $meta['default_link_id'] : 'N/A';
	echo "  - ID: {$mapping['id']}, Entity: {$mapping['entity']}, URL: {$mapping['url']}, Default Link ID: {$default_link_id}\n";
}

echo "\n";

// Test creating mappings
echo "Testing create_mappings_for_link...\n";
foreach ( $default_links as $link ) {
	$link_id = $link['id'];
	echo "Creating mappings for: {$link_id}...\n";
	try {
		RUD_Default_Links::create_mappings_for_link( $link_id );
		echo "  ✓ Success\n";
	} catch ( Exception $e ) {
		echo "  ✗ Error: " . $e->getMessage() . "\n";
	}
}

echo "\n";

// Check mappings after creation
$existing_mappings_after = $wpdb->get_results( "SELECT * FROM {$table_name} WHERE meta LIKE '%is_default%'", ARRAY_A );
echo "Mappings after creation: " . count( $existing_mappings_after ) . "\n";
foreach ( $existing_mappings_after as $mapping ) {
	$meta = maybe_unserialize( $mapping['meta'] );
	$default_link_id = isset( $meta['default_link_id'] ) ? $meta['default_link_id'] : 'N/A';
	echo "  - ID: {$mapping['id']}, Entity: {$mapping['entity']}, URL: {$mapping['url']}, Default Link ID: {$default_link_id}\n";
}

echo "\n";
echo "Done!\n";
echo '</pre>';

echo '<p><a href="' . admin_url( 'admin.php?page=role-links' ) . '">Go to Role Links page</a></p>';

