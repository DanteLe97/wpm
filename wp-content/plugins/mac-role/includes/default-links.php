<?php
/**
 * Default Role URL Links
 * 
 * This file contains the default links that are available in the plugin.
 * Users can enable/disable and edit these links.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RUD_Default_Links {
	
	/**
	 * Get default links configuration
	 */
	public static function get_default_links() {
		return array(
			array(
				'id' => 'web-info',
				'url' => 'admin.php?page=web-info',
				'label' => 'Web Info',
				'icon' => '',
				'description' => '',
				'priority' => 10,
			),
			array(
				'id' => 'mac-menu',
				'url' => 'admin.php?page=mac-cat-menu',
				'label' => 'MAC Menu',
				'icon' => '',
				'description' => '',
				'priority' => 10,
			),
			array(
				'id' => 'pages',
				'url' => 'edit.php?post_type=page',
				'label' => 'Pages',
				'icon' => '',
				'description' => '',
				'priority' => 10,
			),
		);
	}
	
	/**
	 * Get default links with their enabled status
	 */
	public static function get_default_links_with_status() {
		$default_links = self::get_default_links();
		$enabled_status = get_option( 'rud_default_links_enabled', array() );
		$custom_data = get_option( 'rud_default_links_custom', array() );
		
		foreach ( $default_links as &$link ) {
			$link_id = $link['id'];
			
			// Check if enabled (default: enabled)
			$link['enabled'] = isset( $enabled_status[ $link_id ] ) ? (bool) $enabled_status[ $link_id ] : true;
			
			// Merge custom data (user edits)
			if ( isset( $custom_data[ $link_id ] ) ) {
				$link = array_merge( $link, $custom_data[ $link_id ] );
			}
		}
		
		return $default_links;
	}
	
	/**
	 * Get enabled default links
	 */
	public static function get_enabled_default_links() {
		$all_links = self::get_default_links_with_status();
		return array_filter( $all_links, function( $link ) {
			return ! empty( $link['enabled'] );
		} );
	}
	
	/**
	 * Update enabled status for a default link
	 */
	public static function update_enabled_status( $link_id, $enabled ) {
		$enabled_status = get_option( 'rud_default_links_enabled', array() );
		$enabled_status[ $link_id ] = $enabled;
		update_option( 'rud_default_links_enabled', $enabled_status );
		
		// If enabling, create mappings for admin and editor
		if ( $enabled ) {
			self::create_mappings_for_link( $link_id );
		} else {
			// If disabling, deactivate mappings (don't delete)
			self::deactivate_mappings_for_link( $link_id );
		}
	}
	
	/**
	 * Update custom data for a default link
	 */
	public static function update_custom_data( $link_id, $data ) {
		$custom_data = get_option( 'rud_default_links_custom', array() );
		if ( ! isset( $custom_data[ $link_id ] ) ) {
			$custom_data[ $link_id ] = array();
		}
		$custom_data[ $link_id ] = array_merge( $custom_data[ $link_id ], $data );
		update_option( 'rud_default_links_custom', $custom_data );
		
		// Update existing mappings
		self::update_mappings_for_link( $link_id, $data );
	}
	
	/**
	 * Create mappings for admin and editor roles (single mapping with multiple roles)
	 */
	public static function create_mappings_for_link( $link_id ) {
		$default_links = self::get_default_links_with_status();
		$link = null;
		
		foreach ( $default_links as $l ) {
			if ( $l['id'] === $link_id ) {
				$link = $l;
				break;
			}
		}
		
		if ( ! $link ) {
			return;
		}
		
		// Normalize URL for comparison
		$normalized_url = RUD_Helpers::normalize_url( $link['url'] );
		
		// Check if default mapping already exists for this link_id
		$existing = RUD_DB::search_mappings( array(
			'url' => $normalized_url,
			'entity_type' => 'role',
		) );
		
		$default_mapping_exists = false;
		$existing_mapping_id = null;
		
		foreach ( $existing['items'] as $item ) {
			$item_meta = isset( $item['meta'] ) ? $item['meta'] : array();
			$item_normalized_url = RUD_Helpers::normalize_url( $item['url'] );
			
			// Check if this is a default mapping for this link_id
			if ( $item_normalized_url === $normalized_url &&
			     isset( $item_meta['is_default'] ) && 
			     $item_meta['is_default'] &&
			     isset( $item_meta['default_link_id'] ) && 
			     $item_meta['default_link_id'] === $link_id ) {
				$default_mapping_exists = true;
				$existing_mapping_id = $item['id'];
				break;
			}
		}
		
		// Roles for default links: administrator and editor
		$default_roles = array( 'administrator', 'editor' );
		
		if ( $default_mapping_exists ) {
			// Find the existing mapping to get its current meta
			$existing_mapping = null;
			foreach ( $existing['items'] as $item ) {
				if ( $item['id'] == $existing_mapping_id ) {
					$existing_mapping = $item;
					break;
				}
			}
			
			// Update existing mapping - ensure it has both roles
			$existing_meta = isset( $existing_mapping['meta'] ) ? $existing_mapping['meta'] : array();
			$existing_multiple_entities = isset( $existing_meta['multiple_entities'] ) ? $existing_meta['multiple_entities'] : array();
			$existing_roles = isset( $existing_multiple_entities['roles'] ) ? $existing_multiple_entities['roles'] : array();
			
			// Merge roles (ensure both admin and editor are included)
			$merged_roles = array_unique( array_merge( $existing_roles, $default_roles ) );
			
			// Preserve existing meta (like additional_urls)
			$updated_meta = $existing_meta;
			$updated_meta['is_default'] = true;
			$updated_meta['default_link_id'] = $link_id;
			$updated_meta['multiple_entities'] = array( 'roles' => $merged_roles );
			
			RUD_DB::update_mapping( $existing_mapping_id, array(
				'active' => 1,
				'label' => $link['label'],
				'description' => $link['description'],
				'icon' => $link['icon'],
				'priority' => $link['priority'],
				'meta' => $updated_meta,
			) );
		} else {
			// Create new mapping with both roles
			RUD_DB::insert_mapping( array(
				'entity_type' => 'role',
				'entity' => 'administrator', // Primary entity for backward compatibility
				'url' => $normalized_url,
				'label' => $link['label'],
				'description' => $link['description'],
				'icon' => $link['icon'],
				'priority' => $link['priority'],
				'active' => 1,
				'meta' => array(
					'is_default' => true,
					'default_link_id' => $link_id,
					'multiple_entities' => array( 'roles' => $default_roles ),
				),
			) );
		}
	}
	
	/**
	 * Deactivate mappings for a default link
	 */
	private static function deactivate_mappings_for_link( $link_id ) {
		$default_links = self::get_default_links_with_status();
		$link = null;
		
		foreach ( $default_links as $l ) {
			if ( $l['id'] === $link_id ) {
				$link = $l;
				break;
			}
		}
		
		if ( ! $link ) {
			return;
		}
		
		// Normalize URL for comparison
		$normalized_url = RUD_Helpers::normalize_url( $link['url'] );
		
		// Find and deactivate the default mapping for this link_id
		$mappings = RUD_DB::search_mappings( array(
			'url' => $normalized_url,
		) );
		
		foreach ( $mappings['items'] as $mapping ) {
			$meta = isset( $mapping['meta'] ) ? $mapping['meta'] : array();
			if ( isset( $meta['is_default'] ) && $meta['is_default'] && 
			     isset( $meta['default_link_id'] ) && $meta['default_link_id'] === $link_id ) {
				RUD_DB::update_mapping( $mapping['id'], array( 'active' => 0 ) );
				break; // Only one mapping per default link now
			}
		}
	}
	
	/**
	 * Update existing mappings when default link is edited
	 */
	private static function update_mappings_for_link( $link_id, $data ) {
		$default_links = self::get_default_links_with_status();
		$link = null;
		
		foreach ( $default_links as $l ) {
			if ( $l['id'] === $link_id ) {
				$link = $l;
				break;
			}
		}
		
		if ( ! $link ) {
			return;
		}
		
		// Normalize URL for comparison
		$normalized_url = RUD_Helpers::normalize_url( $link['url'] );
		
		// Find and update the default mapping for this link_id
		$mappings = RUD_DB::search_mappings( array(
			'url' => $normalized_url,
		) );
		
		$update_data = array();
		if ( isset( $data['label'] ) ) {
			$update_data['label'] = $data['label'];
		}
		if ( isset( $data['icon'] ) ) {
			$update_data['icon'] = $data['icon'];
		}
		if ( isset( $data['description'] ) ) {
			$update_data['description'] = $data['description'];
		}
		if ( isset( $data['priority'] ) ) {
			$update_data['priority'] = $data['priority'];
		}
		if ( isset( $data['url'] ) ) {
			$update_data['url'] = RUD_Helpers::normalize_url( $data['url'] );
		}
		
		foreach ( $mappings['items'] as $mapping ) {
			$meta = isset( $mapping['meta'] ) ? $mapping['meta'] : array();
			if ( isset( $meta['is_default'] ) && $meta['is_default'] && 
			     isset( $meta['default_link_id'] ) && $meta['default_link_id'] === $link_id ) {
				RUD_DB::update_mapping( $mapping['id'], $update_data );
				break; // Only one mapping per default link now
			}
		}
	}
}

