<?php
/**
 * Cleanup Duplicate Default Links - Merge 2 mappings into 1
 * 
 * Usage: Access this file directly in browser
 * Example: http://localhost/wpm/wp-content/plugins/mac-role/cleanup-duplicate-defaults.php
 * 
 * This will merge duplicate default link mappings (admin + editor) into single mappings
 */

// Load WordPress
require_once( '../../../wp-load.php' );

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
	die( 'Access denied. You must be an administrator.' );
}

echo '<h1>Cleanup Duplicate Default Links</h1>';
echo '<pre>';

// Get all default links
$default_links = RUD_Default_Links::get_default_links();
$default_links_map = array();
foreach ( $default_links as $link ) {
	$default_links_map[ $link['id'] ] = $link;
}

// Get all mappings
global $wpdb;
$table_name = $wpdb->prefix . 'role_url_map';
$all_mappings = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A );

echo "Total mappings in database: " . count( $all_mappings ) . "\n\n";

// Group default mappings by URL and default_link_id
$default_mappings_by_url = array();

foreach ( $all_mappings as $mapping ) {
	$meta = maybe_unserialize( $mapping['meta'] );
	if ( isset( $meta['is_default'] ) && $meta['is_default'] && 
	     isset( $meta['default_link_id'] ) ) {
		$url = $mapping['url'];
		$default_link_id = $meta['default_link_id'];
		
		if ( ! isset( $default_mappings_by_url[ $url ] ) ) {
			$default_mappings_by_url[ $url ] = array();
		}
		if ( ! isset( $default_mappings_by_url[ $url ][ $default_link_id ] ) ) {
			$default_mappings_by_url[ $url ][ $default_link_id ] = array();
		}
		
		$default_mappings_by_url[ $url ][ $default_link_id ][] = $mapping;
	}
}

$merged_count = 0;
$deleted_count = 0;

// Process each group
foreach ( $default_mappings_by_url as $url => $link_groups ) {
	foreach ( $link_groups as $link_id => $mappings ) {
		if ( count( $mappings ) <= 1 ) {
			// Already single mapping, skip
			continue;
		}
		
		echo "Processing: {$link_id} ({$url}) - Found " . count( $mappings ) . " mappings\n";
		
		// Keep first mapping, collect roles from all
		$keep_mapping = $mappings[0];
		$all_roles = array();
		
		foreach ( $mappings as $mapping ) {
			$all_roles[] = $mapping['entity'];
		}
		$all_roles = array_unique( $all_roles );
		
		echo "  Keeping mapping ID: {$keep_mapping['id']}, Roles: " . implode( ', ', $all_roles ) . "\n";
		
		// Update kept mapping with all roles
		$keep_meta = maybe_unserialize( $keep_mapping['meta'] );
		$keep_meta['multiple_entities'] = array( 'roles' => $all_roles );
		$keep_meta['is_default'] = true;
		$keep_meta['default_link_id'] = $link_id;
		
		$wpdb->update(
			$table_name,
			array(
				'meta' => maybe_serialize( $keep_meta ),
				'entity' => $all_roles[0], // Primary entity
			),
			array( 'id' => $keep_mapping['id'] ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		
		// Delete other mappings
		for ( $i = 1; $i < count( $mappings ); $i++ ) {
			$wpdb->delete( $table_name, array( 'id' => $mappings[ $i ]['id'] ), array( '%d' ) );
			$deleted_count++;
			echo "  Deleted mapping ID: {$mappings[$i]['id']}\n";
		}
		
		$merged_count++;
		echo "\n";
	}
}

echo "Summary:\n";
echo "  Merged groups: {$merged_count}\n";
echo "  Deleted mappings: {$deleted_count}\n";
echo '</pre>';

echo '<p><strong>Cleanup complete!</strong></p>';
echo '<p><a href="' . admin_url( 'admin.php?page=role-links' ) . '">Go to Role Links page</a></p>';

