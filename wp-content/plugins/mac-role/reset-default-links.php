<?php
/**
 * Reset Default Links - Helper script to test activation
 * 
 * Usage: Access this file directly in browser or via WP-CLI
 * Example: http://yoursite.com/wp-content/plugins/mac-role/reset-default-links.php
 * 
 * WARNING: This will delete all default links settings and mappings!
 */

// Load WordPress
require_once( '../../../wp-load.php' );

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
	die( 'Access denied. You must be an administrator.' );
}

// Delete default links options
delete_option( 'rud_default_links_enabled' );
delete_option( 'rud_default_links_custom' );

// Delete all default link mappings from database
global $wpdb;
$table_name = $wpdb->prefix . 'role_url_map';

$default_mappings = $wpdb->get_results( "
	SELECT id, meta 
	FROM {$table_name} 
	WHERE meta LIKE '%\"is_default\":true%'
" );

$deleted_count = 0;
foreach ( $default_mappings as $mapping ) {
	$meta = maybe_unserialize( $mapping->meta );
	if ( isset( $meta['is_default'] ) && $meta['is_default'] ) {
		$wpdb->delete( $table_name, array( 'id' => $mapping->id ), array( '%d' ) );
		$deleted_count++;
	}
}

echo '<h1>Default Links Reset Complete</h1>';
echo '<p>Deleted options:</p>';
echo '<ul>';
echo '<li>rud_default_links_enabled</li>';
echo '<li>rud_default_links_custom</li>';
echo '</ul>';
echo '<p>Deleted ' . $deleted_count . ' default link mappings from database.</p>';
echo '<p><strong>Now deactivate and reactivate the plugin to test activation hook.</strong></p>';
echo '<p><a href="' . admin_url( 'plugins.php' ) . '">Go to Plugins page</a></p>';

