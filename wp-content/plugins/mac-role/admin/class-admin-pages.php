<?php
/**
 * Admin pages for Role URL Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RUD_Admin_Pages {
	
	/**
	 * Instance
	 */
	private static $instance = null;
	
	/**
	 * Get instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_init', array( $this, 'process_actions' ) );
		
		// AJAX handlers
		add_action( 'wp_ajax_rud_get_submenus', array( $this, 'ajax_get_submenus' ) );
		add_action( 'wp_ajax_rud_toggle_default_link', array( $this, 'ajax_toggle_default_link' ) );
		add_action( 'wp_ajax_rud_update_default_link', array( $this, 'ajax_update_default_link' ) );
	}
	
	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		// Main menu
		add_menu_page(
			__( 'Role URL Dashboard', 'role-url-dashboard' ),
			__( 'Role Links', 'role-url-dashboard' ),
			'manage_role_dashboards',
			'role-links',
			array( $this, 'render_list_page' ),
			'dashicons-admin-links',
			30
		);
		
		// Submenu: All Links
		add_submenu_page(
			'role-links',
			__( 'All Links', 'role-url-dashboard' ),
			__( 'All Links', 'role-url-dashboard' ),
			'manage_role_dashboards',
			'role-links',
			array( $this, 'render_list_page' )
		);
		
		// Submenu: Add New
		add_submenu_page(
			'role-links',
			__( 'Add New Link', 'role-url-dashboard' ),
			__( 'Add New', 'role-url-dashboard' ),
			'manage_role_dashboards',
			'role-links-add',
			array( $this, 'render_edit_page' )
		);
		
		// Submenu: Settings
		add_submenu_page(
			'role-links',
			__( 'Settings', 'role-url-dashboard' ),
			__( 'Settings', 'role-url-dashboard' ),
			'manage_role_dashboards',
			'role-links-settings',
			array( $this, 'render_settings_page' )
		);
	}
	
	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'role-links' ) === false ) {
			return;
		}
		
		wp_enqueue_style(
			'rud-admin',
			RUD_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			RUD_VERSION
		);
		
		// Enqueue jQuery UI for autocomplete
		wp_enqueue_script( 'jquery-ui-autocomplete' );
		// Use core jQuery UI theme path to avoid 404
		wp_enqueue_style(
			'jquery-ui-autocomplete',
			includes_url( 'js/jquery/ui/themes/base/jquery-ui.min.css' ),
			array(),
			RUD_VERSION
		);
		
		wp_enqueue_script(
			'rud-admin',
			RUD_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-autocomplete' ),
			RUD_VERSION,
			true
		);
		
		// Get admin menu items for URL selector
		$menu_items = RUD_Helpers::get_admin_menu_items();
		
		wp_localize_script( 'rud-admin', 'rudAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'rud-admin-nonce' ),
			'menuItems' => $menu_items,
			'strings' => array(
				'confirmDelete' => __( 'Are you sure you want to delete this link?', 'role-url-dashboard' ),
				'saving' => __( 'Saving...', 'role-url-dashboard' ),
				'saved' => __( 'Saved!', 'role-url-dashboard' ),
				'error' => __( 'An error occurred.', 'role-url-dashboard' ),
				'selectOrType' => __( 'Chọn từ menu hoặc nhập URL thủ công', 'role-url-dashboard' ),
			),
		) );
	}
	
	/**
	 * Process form actions
	 */
	public function process_actions() {
		if ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'role-links' ) === false ) {
			return;
		}
		
		if ( ! current_user_can( 'manage_role_dashboards' ) ) {
			return;
		}
		
		// Handle delete
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
			check_admin_referer( 'rud-delete-' . $_GET['id'] );
			$id = absint( $_GET['id'] );
			if ( $id ) {
				RUD_DB::delete_mapping( $id );
				wp_redirect( admin_url( 'admin.php?page=role-links&deleted=1' ) );
				exit;
			}
		}
		
		// Handle bulk actions
		if ( isset( $_POST['action'] ) && $_POST['action'] !== '-1' && isset( $_POST['bulk_ids'] ) ) {
			check_admin_referer( 'rud-bulk-action' );
			$ids = array_map( 'absint', $_POST['bulk_ids'] );
			
			if ( $_POST['action'] === 'delete' ) {
				foreach ( $ids as $id ) {
					RUD_DB::delete_mapping( $id );
				}
				wp_redirect( admin_url( 'admin.php?page=role-links&bulk_deleted=' . count( $ids ) ) );
				exit;
			} elseif ( $_POST['action'] === 'activate' ) {
				foreach ( $ids as $id ) {
					RUD_DB::update_mapping( $id, array( 'active' => 1 ) );
				}
				wp_redirect( admin_url( 'admin.php?page=role-links&bulk_activated=' . count( $ids ) ) );
				exit;
			} elseif ( $_POST['action'] === 'deactivate' ) {
				foreach ( $ids as $id ) {
					RUD_DB::update_mapping( $id, array( 'active' => 0 ) );
				}
				wp_redirect( admin_url( 'admin.php?page=role-links&bulk_deactivated=' . count( $ids ) ) );
				exit;
			}
		}
		
		// Handle save
		if ( isset( $_POST['rud_save_mapping'] ) ) {
			check_admin_referer( 'rud-save-mapping' );
			
			$entity_type = isset( $_POST['entity_type'] ) ? sanitize_text_field( $_POST['entity_type'] ) : 'role';
			
			// Get entities (multiple)
			$entities = array();
			$multiple_entities = array();
			
			if ( $entity_type === 'role' ) {
				$entity_roles = isset( $_POST['entity_role'] ) ? $_POST['entity_role'] : array();
				if ( ! is_array( $entity_roles ) ) {
					$entity_roles = array( $entity_roles );
				}
				$entities = array_map( 'sanitize_key', array_filter( $entity_roles ) );
				$multiple_entities['roles'] = $entities;
				// Use first entity as primary for backward compatibility
				$entity = ! empty( $entities ) ? $entities[0] : '';
			} elseif ( $entity_type === 'user' ) {
				$entity_user_input = isset( $_POST['entity_user'] ) ? $_POST['entity_user'] : '';
				// Parse user IDs from textarea (one per line or comma-separated)
				$user_ids = preg_split( '/[\r\n,]+/', $entity_user_input );
				$entities = array_map( 'absint', array_filter( array_map( 'trim', $user_ids ) ) );
				$entities = array_filter( $entities, function( $id ) { return $id > 0; } );
				$entities = array_map( 'strval', $entities );
				$multiple_entities['users'] = $entities;
				// Use first entity as primary
				$entity = ! empty( $entities ) ? $entities[0] : '';
			}
			
			// Validate entities
			if ( empty( $entities ) ) {
				wp_die( __( 'Please select at least one role or enter at least one user ID.', 'role-url-dashboard' ) );
			}
			
			// Get additional URLs
			$additional_urls = array();
			if ( isset( $_POST['additional_urls'] ) && is_array( $_POST['additional_urls'] ) ) {
				$additional_urls = array_map( 'sanitize_text_field', array_filter( $_POST['additional_urls'] ) );
			}
			
			// Prepare meta data
			$meta = array(
				'additional_urls' => $additional_urls,
				'multiple_entities' => $multiple_entities,
			);
			
			$data = array(
				'entity_type' => $entity_type,
				'entity' => $entity, // Primary entity for backward compatibility
				'url' => isset( $_POST['url'] ) ? sanitize_text_field( $_POST['url'] ) : '',
				'label' => isset( $_POST['label'] ) ? sanitize_text_field( $_POST['label'] ) : '',
				'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '',
				'icon' => isset( $_POST['icon'] ) ? sanitize_text_field( $_POST['icon'] ) : '',
				'open_behavior' => isset( $_POST['open_behavior'] ) ? sanitize_text_field( $_POST['open_behavior'] ) : 'same',
				'priority' => isset( $_POST['priority'] ) ? intval( $_POST['priority'] ) : 10,
				'active' => isset( $_POST['active'] ) ? 1 : 0,
				'meta' => $meta,
			);
			
			// Validate URL
			if ( ! RUD_Helpers::validate_url( $data['url'] ) ) {
				wp_die( __( 'Invalid URL. Please enter a valid admin URL.', 'role-url-dashboard' ) );
			}
			
			$id = isset( $_POST['mapping_id'] ) ? absint( $_POST['mapping_id'] ) : 0;
			
			// Save mapping (single mapping with multiple entities in meta)
			if ( $id ) {
				$result = RUD_DB::update_mapping( $id, $data );
				if ( $result ) {
					wp_redirect( admin_url( 'admin.php?page=role-links&updated=1' ) );
					exit;
				}
			} else {
				$result = RUD_DB::insert_mapping( $data );
				if ( $result ) {
					wp_redirect( admin_url( 'admin.php?page=role-links&added=1' ) );
					exit;
				}
			}
		}
	}
	
	/**
	 * Render list page
	 */
	public function render_list_page() {
		if ( ! current_user_can( 'manage_role_dashboards' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'role-url-dashboard' ) );
		}
		
		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$entity_type = isset( $_GET['entity_type'] ) ? sanitize_text_field( $_GET['entity_type'] ) : '';
		$entity = isset( $_GET['entity'] ) ? sanitize_text_field( $_GET['entity'] ) : '';
		$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$show_default = isset( $_GET['show_default'] ) ? (bool) $_GET['show_default'] : true;
		
		$args = array(
			'search' => $search,
			'entity_type' => $entity_type,
			'entity' => $entity,
			'page' => $paged,
			'per_page' => 20,
		);
		
		$results = RUD_DB::search_mappings( $args );
		$all_mappings = $results['items'];
		$total = $results['total'];
		
		// Get default links data
		$default_links_data = RUD_Default_Links::get_default_links_with_status();
		$default_links_map = array();
		foreach ( $default_links_data as $dl ) {
			$default_links_map[ $dl['id'] ] = $dl;
		}
		
		// Group default links by default_link_id to avoid duplicates
		$default_links_grouped = array();
		$regular_mappings = array();
		
		foreach ( $all_mappings as $mapping ) {
			$meta = isset( $mapping['meta'] ) ? $mapping['meta'] : array();
			if ( isset( $meta['is_default'] ) && $meta['is_default'] && 
			     isset( $meta['default_link_id'] ) ) {
				$default_link_id = $meta['default_link_id'];
				
				// Group by default_link_id - only keep first one
				if ( ! isset( $default_links_grouped[ $default_link_id ] ) ) {
					$mapping['is_default'] = true;
					$mapping['default_link_id'] = $default_link_id;
					$mapping['default_link_enabled'] = isset( $default_links_map[ $default_link_id ] ) ? $default_links_map[ $default_link_id ]['enabled'] : true;
					$mapping['default_roles'] = array( $mapping['entity'] ); // Start with first role
					$default_links_grouped[ $default_link_id ] = $mapping;
				} else {
					// Add role to existing default link
					if ( ! in_array( $mapping['entity'], $default_links_grouped[ $default_link_id ]['default_roles'] ) ) {
						$default_links_grouped[ $default_link_id ]['default_roles'][] = $mapping['entity'];
					}
				}
			} else {
				$mapping['is_default'] = false;
				$regular_mappings[] = $mapping;
			}
		}
		
		// Merge: default links first, then regular mappings
		$mappings = array_merge( array_values( $default_links_grouped ), $regular_mappings );
		
		include RUD_PLUGIN_DIR . 'admin/templates/list-page.php';
	}
	
	/**
	 * Render edit page
	 */
	public function render_edit_page() {
		if ( ! current_user_can( 'manage_role_dashboards' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'role-url-dashboard' ) );
		}
		
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$mapping = $id ? RUD_DB::get_mapping( $id ) : null;
		
		include RUD_PLUGIN_DIR . 'admin/templates/edit-page.php';
	}
	
	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_role_dashboards' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'role-url-dashboard' ) );
		}
		
		// Handle settings save
		if ( isset( $_POST['rud_save_settings'] ) ) {
			check_admin_referer( 'rud-save-settings' );
			
			$settings = array(
				'dashboard_location' => isset( $_POST['dashboard_location'] ) ? sanitize_text_field( $_POST['dashboard_location'] ) : 'menu',
				'allow_iframe' => isset( $_POST['allow_iframe'] ) ? 1 : 0,
				'cache_ttl' => isset( $_POST['cache_ttl'] ) ? intval( $_POST['cache_ttl'] ) : 3600,
			);
			
			update_option( 'rud_settings', $settings );
			echo '<div class="notice notice-success"><p>' . __( 'Settings saved!', 'role-url-dashboard' ) . '</p></div>';
		}
		
		$settings = get_option( 'rud_settings', array(
			'dashboard_location' => 'index',
			'allow_iframe' => 0,
			'cache_ttl' => 3600,
		) );
		
		// Ensure dashboard_location is always 'index' (default)
		if ( ! isset( $settings['dashboard_location'] ) || $settings['dashboard_location'] !== 'index' ) {
			$settings['dashboard_location'] = 'index';
			update_option( 'rud_settings', $settings );
		}
		
		include RUD_PLUGIN_DIR . 'admin/templates/settings-page.php';
	}
	
	/**
	 * AJAX handler: Get submenus for a given menu slug
	 */
	public function ajax_get_submenus() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'rud-admin-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'role-url-dashboard' ) ) );
		}
		
		// Check capability
		if ( ! current_user_can( 'manage_role_dashboards' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'role-url-dashboard' ) ) );
		}
		
		// Get menu slug from request
		$menu_slug = isset( $_POST['menu_slug'] ) ? sanitize_text_field( $_POST['menu_slug'] ) : '';
		if ( empty( $menu_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Menu slug is required.', 'role-url-dashboard' ) ) );
		}
		
		// Get submenus - use global $submenu directly
		global $submenu;
		
		$submenu_items = array();
		
		try {
			// In AJAX context, admin_menu might not have fired yet
			// Trigger it to ensure all plugins register their menus
			if ( ! did_action( 'admin_menu' ) ) {
				do_action( 'admin_menu' );
			}
			
			// Get submenu items directly from global $submenu
			if ( isset( $submenu[ $menu_slug ] ) && is_array( $submenu[ $menu_slug ] ) ) {
				foreach ( $submenu[ $menu_slug ] as $submenu_item ) {
					if ( ! isset( $submenu_item[2] ) || empty( $submenu_item[2] ) ) {
						continue;
					}
					
					$submenu_slug = $submenu_item[2];
					$submenu_title = isset( $submenu_item[0] ) ? strip_tags( $submenu_item[0] ) : '';
					
					// Build submenu URL
					$submenu_url = RUD_Helpers::normalize_url( $submenu_slug );
					
					// If it's not a standard admin file, assume it's admin.php?page=
					if ( strpos( $submenu_url, '.php' ) === false && strpos( $submenu_url, 'admin.php?page=' ) === false ) {
						$submenu_url = 'admin.php?page=' . $submenu_slug;
					}
					
					$submenu_items[] = array(
						'url' => $submenu_url,
						'label' => $submenu_title,
						'slug' => $submenu_slug,
					);
				}
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array(
				'message' => __( 'Error loading submenus.', 'role-url-dashboard' ),
				'error' => $e->getMessage(),
			) );
		} catch ( Error $e ) {
			wp_send_json_error( array(
				'message' => __( 'Fatal error loading submenus.', 'role-url-dashboard' ),
				'error' => $e->getMessage(),
			) );
		}
		
		wp_send_json_success( array(
			'menu_slug' => $menu_slug,
			'submenus' => $submenu_items,
			'count' => count( $submenu_items ),
		) );
	}
	
	/**
	 * AJAX handler: Toggle default link enabled status
	 */
	public function ajax_toggle_default_link() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'rud-admin-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'role-url-dashboard' ) ) );
		}
		
		// Check capability
		if ( ! current_user_can( 'manage_role_dashboards' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'role-url-dashboard' ) ) );
		}
		
		$link_id = isset( $_POST['link_id'] ) ? sanitize_text_field( $_POST['link_id'] ) : '';
		$enabled = isset( $_POST['enabled'] ) ? (bool) $_POST['enabled'] : false;
		
		if ( empty( $link_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Link ID is required.', 'role-url-dashboard' ) ) );
		}
		
		RUD_Default_Links::update_enabled_status( $link_id, $enabled );
		
		wp_send_json_success( array(
			'message' => $enabled ? __( 'Link enabled.', 'role-url-dashboard' ) : __( 'Link disabled.', 'role-url-dashboard' ),
		) );
	}
	
	/**
	 * AJAX handler: Update default link custom data
	 */
	public function ajax_update_default_link() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'rud-admin-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'role-url-dashboard' ) ) );
		}
		
		// Check capability
		if ( ! current_user_can( 'manage_role_dashboards' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'role-url-dashboard' ) ) );
		}
		
		$link_id = isset( $_POST['link_id'] ) ? sanitize_text_field( $_POST['link_id'] ) : '';
		
		if ( empty( $link_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Link ID is required.', 'role-url-dashboard' ) ) );
		}
		
		$data = array();
		if ( isset( $_POST['label'] ) ) {
			$data['label'] = sanitize_text_field( $_POST['label'] );
		}
		if ( isset( $_POST['icon'] ) ) {
			$data['icon'] = sanitize_text_field( $_POST['icon'] );
		}
		if ( isset( $_POST['description'] ) ) {
			$data['description'] = sanitize_textarea_field( $_POST['description'] );
		}
		if ( isset( $_POST['priority'] ) ) {
			$data['priority'] = absint( $_POST['priority'] );
		}
		if ( isset( $_POST['url'] ) ) {
			$data['url'] = sanitize_text_field( $_POST['url'] );
		}
		
		RUD_Default_Links::update_custom_data( $link_id, $data );
		
		wp_send_json_success( array(
			'message' => __( 'Link updated.', 'role-url-dashboard' ),
		) );
	}
}

