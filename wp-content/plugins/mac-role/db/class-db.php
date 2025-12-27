<?php
/**
 * Database layer for Role URL Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RUD_DB {
	
	/**
	 * Get table name
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'role_url_map';
	}
	
	/**
	 * Create table on activation
	 */
	public static function create_table() {
		global $wpdb;
		
		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			entity_type ENUM('role','user') NOT NULL,
			entity VARCHAR(191) NOT NULL,
			url VARCHAR(1024) NOT NULL,
			label VARCHAR(191) NOT NULL,
			open_behavior ENUM('same','new','iframe') NOT NULL DEFAULT 'same',
			icon VARCHAR(255) DEFAULT NULL,
			description TEXT DEFAULT NULL,
			priority INT DEFAULT 10,
			active TINYINT(1) DEFAULT 1,
			meta JSON DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			INDEX idx_entity (entity_type, entity),
			INDEX idx_url (url(191)),
			INDEX idx_active (active)
		) {$charset_collate};";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	
	/**
	 * Add capability to administrator role
	 */
	public static function add_capability_to_admin() {
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( 'manage_role_dashboards' );
		}
	}
	
	/**
	 * Remove capability from all roles (on uninstall)
	 */
	public static function remove_capability() {
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}
		
		foreach ( $wp_roles->roles as $role_name => $role_info ) {
			$role = get_role( $role_name );
			if ( $role && $role->has_cap( 'manage_role_dashboards' ) ) {
				$role->remove_cap( 'manage_role_dashboards' );
			}
		}
	}
	
	/**
	 * Get mappings by entity (including multiple entities in meta)
	 */
	public static function get_mappings_by_entity( $entity_type, $entity, $active_only = true ) {
		global $wpdb;
		$table_name = self::get_table_name();
		
		$where = $wpdb->prepare( 'entity_type = %s AND (entity = %s', $entity_type, $entity );
		
		// Also check in meta for multiple_entities
		$where .= $wpdb->prepare( ' OR meta LIKE %s', '%"' . $entity . '"%' );
		$where .= ')';
		
		if ( $active_only ) {
			$where .= ' AND active = 1';
		}
		
		$results = $wpdb->get_results(
			"SELECT * FROM {$table_name} WHERE {$where} ORDER BY priority ASC, id ASC",
			ARRAY_A
		);
		
		// Filter results to ensure entity is actually in the mapping
		if ( $results ) {
			$filtered = array();
			foreach ( $results as $mapping ) {
				// Check primary entity
				if ( $mapping['entity'] === $entity ) {
					$filtered[] = $mapping;
					continue;
				}
				
				// Check multiple_entities in meta
				$meta = ! empty( $mapping['meta'] ) ? json_decode( $mapping['meta'], true ) : array();
				if ( isset( $meta['multiple_entities'] ) ) {
					$multiple_roles = isset( $meta['multiple_entities']['roles'] ) ? $meta['multiple_entities']['roles'] : array();
					$multiple_users = isset( $meta['multiple_entities']['users'] ) ? $meta['multiple_entities']['users'] : array();
					
					if ( $entity_type === 'role' && in_array( $entity, $multiple_roles ) ) {
						$filtered[] = $mapping;
					} elseif ( $entity_type === 'user' && in_array( $entity, $multiple_users ) ) {
						$filtered[] = $mapping;
					}
				}
			}
			return $filtered;
		}
		
		return array();
	}
	
	/**
	 * Get mappings for user (union of role mappings + user-specific)
	 */
	public static function get_mappings_for_user( $user_id, $use_cache = true ) {
		// Check cache
		if ( $use_cache ) {
			$cache_key = RUD_Helpers::get_cache_key( $user_id );
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				return $cached;
			}
		}
		
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}
		
		$mappings = array();
		$seen_urls = array(); // For deduplication
		
		// Get role-based mappings
		foreach ( $user->roles as $role ) {
			$role_mappings = self::get_mappings_by_entity( 'role', $role, true );
			foreach ( $role_mappings as $mapping ) {
				// Check if this mapping includes this role in multiple_entities
				$meta = ! empty( $mapping['meta'] ) ? json_decode( $mapping['meta'], true ) : array();
				$multiple_entities = isset( $meta['multiple_entities']['roles'] ) ? $meta['multiple_entities']['roles'] : array();
				
				// Include if role matches primary entity or is in multiple_entities
				if ( $mapping['entity'] === $role || in_array( $role, $multiple_entities ) ) {
					$url_key = $mapping['url'];
					if ( ! isset( $seen_urls[ $url_key ] ) ) {
						$mappings[] = $mapping;
						$seen_urls[ $url_key ] = true;
					}
				}
			}
		}
		
		// Get user-specific mappings (override role mappings)
		$user_mappings = self::get_mappings_by_entity( 'user', (string) $user_id, true );
		foreach ( $user_mappings as $mapping ) {
			// Check if this mapping includes this user in multiple_entities
			$meta = ! empty( $mapping['meta'] ) ? json_decode( $mapping['meta'], true ) : array();
			$multiple_entities = isset( $meta['multiple_entities']['users'] ) ? $meta['multiple_entities']['users'] : array();
			
			// Include if user ID matches primary entity or is in multiple_entities
			if ( $mapping['entity'] === (string) $user_id || in_array( (string) $user_id, $multiple_entities ) ) {
				$url_key = $mapping['url'];
				// Remove role mapping with same URL if exists
				$mappings = array_filter( $mappings, function( $m ) use ( $url_key ) {
					return $m['url'] !== $url_key;
				} );
				$mappings[] = $mapping;
			}
		}
		
		// Sort by priority
		usort( $mappings, function( $a, $b ) {
			$prio_a = isset( $a['priority'] ) ? intval( $a['priority'] ) : 10;
			$prio_b = isset( $b['priority'] ) ? intval( $b['priority'] ) : 10;
			if ( $prio_a === $prio_b ) {
				return 0;
			}
			return ( $prio_a < $prio_b ) ? -1 : 1;
		} );
		
		// Cache for 1 hour
		if ( $use_cache ) {
			set_transient( $cache_key, $mappings, HOUR_IN_SECONDS );
		}
		
		return $mappings;
	}
	
	/**
	 * Insert mapping
	 */
	public static function insert_mapping( $data ) {
		global $wpdb;
		$table_name = self::get_table_name();
		
		$defaults = array(
			'entity_type' => 'role',
			'entity' => '',
			'url' => '',
			'label' => '',
			'open_behavior' => 'same',
			'icon' => null,
			'description' => null,
			'priority' => 10,
			'active' => 1,
			'meta' => null,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);
		
		$data = wp_parse_args( $data, $defaults );
		
		// Normalize URL
		$data['url'] = RUD_Helpers::normalize_url( $data['url'] );
		
		// Sanitize
		$data['entity_type'] = in_array( $data['entity_type'], array( 'role', 'user' ) ) ? $data['entity_type'] : 'role';
		$data['entity'] = RUD_Helpers::sanitize_entity( $data['entity'], $data['entity_type'] );
		$data['label'] = sanitize_text_field( $data['label'] );
		$data['open_behavior'] = in_array( $data['open_behavior'], array( 'same', 'new', 'iframe' ) ) ? $data['open_behavior'] : 'same';
		$data['icon'] = ! empty( $data['icon'] ) ? sanitize_text_field( $data['icon'] ) : null;
		$data['description'] = ! empty( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null;
		$data['priority'] = intval( $data['priority'] );
		$data['active'] = intval( $data['active'] );
		
		if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
			$data['meta'] = json_encode( $data['meta'] );
		}
		
		$result = $wpdb->insert( $table_name, $data );
		
		if ( $result ) {
			// Clear caches
			RUD_Helpers::clear_user_cache();
			return $wpdb->insert_id;
		}
		
		return false;
	}
	
	/**
	 * Update mapping
	 */
	public static function update_mapping( $id, $data ) {
		global $wpdb;
		$table_name = self::get_table_name();
		
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		
		$update_data = array();
		$allowed_fields = array( 'entity_type', 'entity', 'url', 'label', 'open_behavior', 'icon', 'description', 'priority', 'active', 'meta' );
		
		foreach ( $allowed_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				if ( $field === 'url' ) {
					$update_data[ $field ] = RUD_Helpers::normalize_url( $data[ $field ] );
				} elseif ( $field === 'entity' ) {
					$entity_type = isset( $data['entity_type'] ) ? $data['entity_type'] : 'role';
					$update_data[ $field ] = RUD_Helpers::sanitize_entity( $data[ $field ], $entity_type );
				} elseif ( $field === 'label' ) {
					$update_data[ $field ] = sanitize_text_field( $data[ $field ] );
				} elseif ( $field === 'description' ) {
					$update_data[ $field ] = sanitize_textarea_field( $data[ $field ] );
				} elseif ( $field === 'icon' ) {
					$update_data[ $field ] = ! empty( $data[ $field ] ) ? sanitize_text_field( $data[ $field ] ) : null;
				} elseif ( $field === 'open_behavior' ) {
					$update_data[ $field ] = in_array( $data[ $field ], array( 'same', 'new', 'iframe' ) ) ? $data[ $field ] : 'same';
				} elseif ( $field === 'priority' ) {
					$update_data[ $field ] = intval( $data[ $field ] );
				} elseif ( $field === 'active' ) {
					$update_data[ $field ] = intval( $data[ $field ] );
				} elseif ( $field === 'meta' && is_array( $data[ $field ] ) ) {
					$update_data[ $field ] = json_encode( $data[ $field ] );
				} else {
					$update_data[ $field ] = $data[ $field ];
				}
			}
		}
		
		if ( empty( $update_data ) ) {
			return false;
		}
		
		$update_data['updated_at'] = current_time( 'mysql' );
		
		$result = $wpdb->update(
			$table_name,
			$update_data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
		
		if ( $result !== false ) {
			// Clear caches
			RUD_Helpers::clear_user_cache();
			return true;
		}
		
		return false;
	}
	
	/**
	 * Delete mapping
	 */
	public static function delete_mapping( $id ) {
		global $wpdb;
		$table_name = self::get_table_name();
		
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		
		$result = $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );
		
		if ( $result ) {
			// Clear caches
			RUD_Helpers::clear_user_cache();
			return true;
		}
		
		return false;
	}
	
	/**
	 * Search mappings (for admin list)
	 */
	public static function search_mappings( $args = array() ) {
		global $wpdb;
		$table_name = self::get_table_name();
		
		$defaults = array(
			'entity_type' => '',
			'entity' => '',
			'search' => '',
			'active' => null,
			'per_page' => 20,
			'page' => 1,
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		$where = array( '1=1' );
		$where_values = array();
		
		if ( ! empty( $args['entity_type'] ) ) {
			$where[] = 'entity_type = %s';
			$where_values[] = $args['entity_type'];
		}
		
		if ( ! empty( $args['entity'] ) ) {
			$where[] = 'entity = %s';
			$where_values[] = $args['entity'];
		}
		
		if ( $args['active'] !== null ) {
			$where[] = 'active = %d';
			$where_values[] = intval( $args['active'] );
		}
		
		if ( ! empty( $args['search'] ) ) {
			$where[] = '(label LIKE %s OR url LIKE %s OR description LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where_values[] = $search_term;
			$where_values[] = $search_term;
			$where_values[] = $search_term;
		}
		
		$where_clause = implode( ' AND ', $where );
		
		if ( ! empty( $where_values ) ) {
			$where_clause = $wpdb->prepare( $where_clause, $where_values );
		}
		
		$offset = ( $args['page'] - 1 ) * $args['per_page'];
		$limit = intval( $args['per_page'] );
		
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY priority ASC, id DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);
		
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}" );
		
		return array(
			'items' => $results ? $results : array(),
			'total' => intval( $total ),
		);
	}
	
	/**
	 * Get mapping by ID
	 */
	public static function get_mapping( $id ) {
		global $wpdb;
		$table_name = self::get_table_name();
		
		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}
		
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $id ),
			ARRAY_A
		);
		
		return $result;
	}
}

