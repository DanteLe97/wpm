<?php
/**
 * Helper functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RUD_Helpers {
	
	/**
	 * Normalize URL - strip domain, ensure relative admin path
	 */
	public static function normalize_url( $url ) {
		if ( empty( $url ) ) {
			return '';
		}
		
		// Remove scheme and domain
		$url = preg_replace( '#^https?://[^/]+#', '', $url );
		
		// Remove leading slash
		$url = ltrim( $url, '/' );
		
		// Remove wp-admin/ prefix if present
		$url = preg_replace( '#^wp-admin/?#', '', $url );
		
		// Remove nonce from query string
		$url = remove_query_arg( '_wpnonce', $url );
		
		// Ensure it's an admin path
		$allowed_prefixes = array( 'index.php', 'edit.php', 'post.php', 'admin.php', 'upload.php', 'users.php', 'tools.php', 'options-general.php' );
		$is_allowed = false;
		
		foreach ( $allowed_prefixes as $prefix ) {
			if ( strpos( $url, $prefix ) === 0 ) {
				$is_allowed = true;
				break;
			}
		}
		
		// Check for plugin admin pages (admin.php?page=...)
		if ( strpos( $url, 'admin.php?page=' ) === 0 ) {
			$is_allowed = true;
		}
		
		if ( ! $is_allowed && ! empty( $url ) ) {
			// If not matching allowed patterns, prepend admin.php?page=
			if ( strpos( $url, '?' ) === false && strpos( $url, '.php' ) === false ) {
				$url = 'admin.php?page=' . $url;
			}
		}
		
		return $url;
	}
	
	/**
	 * Validate URL - check if it's a valid admin URL
	 */
	public static function validate_url( $url ) {
		$normalized = self::normalize_url( $url );
		
		if ( empty( $normalized ) ) {
			return false;
		}
		
		// Reject absolute external URLs
		if ( preg_match( '#^https?://#', $url ) ) {
			return false;
		}
		
		// Must be relative admin path
		return true;
	}
	
	/**
	 * Get admin URL from normalized path
	 */
	public static function get_admin_url( $path ) {
		if ( empty( $path ) ) {
			return admin_url();
		}
		
		// If path doesn't start with admin.php, prepend it
		if ( strpos( $path, 'admin.php' ) !== 0 && strpos( $path, 'index.php' ) !== 0 && 
			 strpos( $path, 'edit.php' ) !== 0 && strpos( $path, 'post.php' ) !== 0 ) {
			$path = 'admin.php?page=' . $path;
		}
		
		return admin_url( $path );
	}
	
	/**
	 * Get user roles
	 */
	public static function get_user_roles( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}
		
		return $user->roles;
	}
	
	/**
	 * Get all roles
	 */
	public static function get_all_roles() {
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}
		return $wp_roles->get_names();
	}
	
	/**
	 * Sanitize entity (role slug or user ID)
	 */
	public static function sanitize_entity( $entity, $entity_type ) {
		if ( $entity_type === 'role' ) {
			return sanitize_key( $entity );
		} elseif ( $entity_type === 'user' ) {
			return absint( $entity );
		}
		return '';
	}
	
	/**
	 * Get cache key for user mappings
	 */
	public static function get_cache_key( $user_id ) {
		return 'rud_user_' . $user_id . '_mappings';
	}
	
	/**
	 * Clear user cache
	 */
	public static function clear_user_cache( $user_id = null ) {
		if ( $user_id ) {
			delete_transient( self::get_cache_key( $user_id ) );
		} else {
			// Clear all user caches (expensive, use sparingly)
			global $wpdb;
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rud_user_%' OR option_name LIKE '_transient_timeout_rud_user_%'" );
		}
	}
	
	/**
	 * Get default icon
	 */
	public static function get_default_icon( $url ) {
		// Simple icon mapping based on URL patterns
		if ( strpos( $url, 'edit.php' ) !== false ) {
			return '📝';
		} elseif ( strpos( $url, 'users.php' ) !== false ) {
			return '👥';
		} elseif ( strpos( $url, 'upload.php' ) !== false ) {
			return '📁';
		} elseif ( strpos( $url, 'tools.php' ) !== false ) {
			return '🔧';
		} elseif ( strpos( $url, 'options-general.php' ) !== false ) {
			return '⚙️';
		}
		return '🔗';
	}
	
	/**
	 * Extract URL prefix for grouping (e.g., admin.php?page=mac-menu-*)
	 */
	public static function get_url_prefix( $url ) {
		if ( empty( $url ) ) {
			return '';
		}
		
		// For admin.php?page= URLs, extract the page prefix
		if ( strpos( $url, 'admin.php?page=' ) !== false ) {
			$query = parse_url( $url, PHP_URL_QUERY );
			if ( $query ) {
				parse_str( $query, $params );
				if ( isset( $params['page'] ) ) {
					$page = $params['page'];
					// Extract prefix before last dash (e.g., mac-menu from mac-menu-bulk-edit)
					$parts = explode( '-', $page );
					if ( count( $parts ) > 1 ) {
						// Remove last part and join back
						array_pop( $parts );
						$prefix = implode( '-', $parts );
						return 'admin.php?page=' . $prefix;
					}
					return 'admin.php?page=' . $page;
				}
			}
		}
		
		// For other URLs, return the base path
		$normalized = self::normalize_url( $url );
		$parts = explode( '?', $normalized );
		return $parts[0];
	}
	
	/**
	 * Get menu label from URL by checking WordPress admin menu
	 */
	public static function get_menu_label_from_url( $url ) {
		global $menu, $submenu;
		
		if ( empty( $url ) ) {
			return '';
		}
		
		// Ensure menu is loaded
		if ( ! isset( $menu ) || ! is_array( $menu ) ) {
			require_once( ABSPATH . 'wp-admin/includes/menu.php' );
			require_once( ABSPATH . 'wp-admin/menu.php' );
		}
		
		$normalized = self::normalize_url( $url );
		
		// Extract page parameter for admin.php?page= URLs
		if ( strpos( $normalized, 'admin.php?page=' ) !== false ) {
			$query = parse_url( $normalized, PHP_URL_QUERY );
			if ( $query ) {
				parse_str( $query, $params );
				$page_slug = isset( $params['page'] ) ? $params['page'] : '';
				
				if ( ! empty( $page_slug ) ) {
					// Check in submenu first (more specific)
					if ( isset( $submenu ) && is_array( $submenu ) ) {
						foreach ( $submenu as $parent => $items ) {
							foreach ( $items as $item ) {
								if ( isset( $item[2] ) && $item[2] === $page_slug ) {
									return strip_tags( $item[0] );
								}
							}
						}
					}
					
					// Check in main menu
					if ( isset( $menu ) && is_array( $menu ) ) {
						foreach ( $menu as $menu_item ) {
							if ( isset( $menu_item[2] ) && $menu_item[2] === $page_slug ) {
								return strip_tags( $menu_item[0] );
							}
						}
					}
				}
			}
		}
		
		// For other URLs, try to extract from path
		$path = parse_url( $normalized, PHP_URL_PATH );
		if ( $path ) {
			$path = basename( $path );
			// Try to find in menu by file name
			if ( isset( $menu ) && is_array( $menu ) ) {
				foreach ( $menu as $menu_item ) {
					if ( isset( $menu_item[2] ) && strpos( $menu_item[2], $path ) !== false ) {
						return strip_tags( $menu_item[0] );
					}
				}
			}
		}
		
		return '';
	}
	
	/**
	 * Group mappings by URL prefix
	 */
	public static function group_mappings_by_prefix( $mappings ) {
		$grouped = array();
		$standalone = array();
		
		foreach ( $mappings as $mapping ) {
			$prefix = self::get_url_prefix( $mapping['url'] );
			
			// Check if there are other mappings with same prefix
			$has_group = false;
			foreach ( $mappings as $other ) {
				if ( $other['url'] !== $mapping['url'] ) {
					$other_prefix = self::get_url_prefix( $other['url'] );
					if ( $prefix === $other_prefix && ! empty( $prefix ) ) {
						$has_group = true;
						break;
					}
				}
			}
			
			if ( $has_group && ! empty( $prefix ) ) {
				if ( ! isset( $grouped[ $prefix ] ) ) {
					$grouped[ $prefix ] = array();
				}
				$grouped[ $prefix ][] = $mapping;
			} else {
				$standalone[] = $mapping;
			}
		}
		
		// Sort each group by priority
		foreach ( $grouped as $prefix => $items ) {
			usort( $grouped[ $prefix ], function( $a, $b ) {
				$prio_a = isset( $a['priority'] ) ? intval( $a['priority'] ) : 10;
				$prio_b = isset( $b['priority'] ) ? intval( $b['priority'] ) : 10;
				return $prio_a - $prio_b;
			} );
		}
		
		return array(
			'grouped' => $grouped,
			'standalone' => $standalone,
		);
	}
	
	/**
	 * Get all admin menu items
	 * Returns array of menu items with their URLs and labels
	 */
	public static function get_admin_menu_items() {
		global $menu, $submenu;
		
		$menu_items = array();
		
		// Ensure menu is loaded
		if ( ! isset( $menu ) || ! is_array( $menu ) ) {
			require_once( ABSPATH . 'wp-admin/includes/menu.php' );
			require_once( ABSPATH . 'wp-admin/menu.php' );
		}
		
		// Get main menu items
		if ( isset( $menu ) && is_array( $menu ) ) {
			foreach ( $menu as $menu_item ) {
				if ( ! isset( $menu_item[2] ) || empty( $menu_item[2] ) ) {
					continue;
				}
				
				$menu_slug = $menu_item[2];
				$menu_title = isset( $menu_item[0] ) ? strip_tags( $menu_item[0] ) : '';
				$menu_icon = isset( $menu_item[6] ) ? $menu_item[6] : '';
				
				// Skip separators
				if ( strpos( $menu_slug, 'separator' ) !== false ) {
					continue;
				}
				
				// Build URL
				$menu_url = self::normalize_url( $menu_slug );
				
				// If it's not a standard admin file, assume it's admin.php?page=
				if ( strpos( $menu_url, '.php' ) === false && strpos( $menu_url, 'admin.php?page=' ) === false ) {
					$menu_url = 'admin.php?page=' . $menu_slug;
				}
				
				$menu_items[] = array(
					'url' => $menu_url,
					'label' => $menu_title,
					'type' => 'main',
					'parent' => '',
				);
				
				// Get submenu items
				if ( isset( $submenu[ $menu_slug ] ) && is_array( $submenu[ $menu_slug ] ) ) {
					foreach ( $submenu[ $menu_slug ] as $submenu_item ) {
						if ( ! isset( $submenu_item[2] ) || empty( $submenu_item[2] ) ) {
							continue;
						}
						
						$submenu_slug = $submenu_item[2];
						$submenu_title = isset( $submenu_item[0] ) ? strip_tags( $submenu_item[0] ) : '';
						
						// Build submenu URL
						$submenu_url = self::normalize_url( $submenu_slug );
						
						// If it's not a standard admin file, assume it's admin.php?page=
						if ( strpos( $submenu_url, '.php' ) === false && strpos( $submenu_url, 'admin.php?page=' ) === false ) {
							$submenu_url = 'admin.php?page=' . $submenu_slug;
						}
						
						// Skip if same as parent
						if ( $submenu_url === $menu_url ) {
							continue;
						}
						
						$menu_items[] = array(
							'url' => $submenu_url,
							'label' => $menu_title . ' → ' . $submenu_title,
							'type' => 'submenu',
							'parent' => $menu_title,
						);
					}
				}
			}
		}
		
		// Add common admin pages that might not be in menu
		$common_pages = array(
			array( 'url' => 'edit.php', 'label' => 'Posts', 'type' => 'common' ),
			array( 'url' => 'edit.php?post_type=page', 'label' => 'Pages', 'type' => 'common' ),
			array( 'url' => 'upload.php', 'label' => 'Media Library', 'type' => 'common' ),
			array( 'url' => 'users.php', 'label' => 'Users', 'type' => 'common' ),
			array( 'url' => 'tools.php', 'label' => 'Tools', 'type' => 'common' ),
			array( 'url' => 'options-general.php', 'label' => 'Settings', 'type' => 'common' ),
			array( 'url' => 'post-new.php', 'label' => 'Add New Post', 'type' => 'common' ),
			array( 'url' => 'post-new.php?post_type=page', 'label' => 'Add New Page', 'type' => 'common' ),
		);
		
		// Merge and remove duplicates
		$all_items = array_merge( $menu_items, $common_pages );
		$unique_items = array();
		$seen_urls = array();
		
		foreach ( $all_items as $item ) {
			$url_key = $item['url'];
			if ( ! isset( $seen_urls[ $url_key ] ) ) {
				$unique_items[] = $item;
				$seen_urls[ $url_key ] = true;
			}
		}
		
		// Sort by label
		usort( $unique_items, function( $a, $b ) {
			return strcmp( $a['label'], $b['label'] );
		} );
		
		return $unique_items;
	}
}

