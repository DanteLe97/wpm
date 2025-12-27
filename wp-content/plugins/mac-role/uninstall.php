<?php
/**
 * Uninstall script for Role URL Dashboard
 * 
 * This file is executed when the plugin is uninstalled.
 */

// Exit if uninstall not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Include necessary files
require_once plugin_dir_path( __FILE__ ) . 'db/class-db.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-helpers.php';

// Remove capability from all roles
RUD_DB::remove_capability();

// Optionally drop table (uncomment if you want to remove data on uninstall)
// global $wpdb;
// $table_name = RUD_DB::get_table_name();
// $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Clear all transients
RUD_Helpers::clear_user_cache();

// Remove settings
delete_option( 'rud_settings' );

