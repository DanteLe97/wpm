<?php
/**
 * REST API endpoints for Role URL Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RUD_REST_API {
	
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}
	
	/**
	 * Register REST routes
	 */
	public function register_routes() {
		$namespace = 'role-url-dashboard/v1';
		
		// Get mappings for current user
		register_rest_route( $namespace, '/mappings', array(
			'methods' => 'GET',
			'callback' => array( $this, 'get_mappings' ),
			'permission_callback' => array( $this, 'check_read_permission' ),
		) );
		
		// Get mapping by ID
		register_rest_route( $namespace, '/mapping/(?P<id>\d+)', array(
			'methods' => 'GET',
			'callback' => array( $this, 'get_mapping' ),
			'permission_callback' => array( $this, 'check_manage_permission' ),
		) );
		
		// Create mapping
		register_rest_route( $namespace, '/mapping', array(
			'methods' => 'POST',
			'callback' => array( $this, 'create_mapping' ),
			'permission_callback' => array( $this, 'check_manage_permission' ),
		) );
		
		// Update mapping
		register_rest_route( $namespace, '/mapping/(?P<id>\d+)', array(
			'methods' => 'PUT',
			'callback' => array( $this, 'update_mapping' ),
			'permission_callback' => array( $this, 'check_manage_permission' ),
		) );
		
		// Delete mapping
		register_rest_route( $namespace, '/mapping/(?P<id>\d+)', array(
			'methods' => 'DELETE',
			'callback' => array( $this, 'delete_mapping' ),
			'permission_callback' => array( $this, 'check_manage_permission' ),
		) );
		
		// Test link
		register_rest_route( $namespace, '/test-link', array(
			'methods' => 'POST',
			'callback' => array( $this, 'test_link' ),
			'permission_callback' => array( $this, 'check_read_permission' ),
		) );
	}
	
	/**
	 * Check read permission
	 */
	public function check_read_permission() {
		return is_user_logged_in();
	}
	
	/**
	 * Check manage permission
	 */
	public function check_manage_permission() {
		return current_user_can( 'manage_role_dashboards' );
	}
	
	/**
	 * Get mappings
	 */
	public function get_mappings( $request ) {
		$entity = $request->get_param( 'entity' );
		$entity_type = $request->get_param( 'entity_type' );
		
		if ( $entity && $entity_type ) {
			$mappings = RUD_DB::get_mappings_by_entity( $entity_type, $entity, true );
		} else {
			// Return current user's mappings
			$user_id = get_current_user_id();
			$mappings = RUD_DB::get_mappings_for_user( $user_id );
		}
		
		return new WP_REST_Response( $mappings, 200 );
	}
	
	/**
	 * Get mapping by ID
	 */
	public function get_mapping( $request ) {
		$id = $request->get_param( 'id' );
		$mapping = RUD_DB::get_mapping( $id );
		
		if ( ! $mapping ) {
			return new WP_Error( 'not_found', __( 'Mapping not found.', 'role-url-dashboard' ), array( 'status' => 404 ) );
		}
		
		return new WP_REST_Response( $mapping, 200 );
	}
	
	/**
	 * Create mapping
	 */
	public function create_mapping( $request ) {
		$data = $request->get_json_params();
		
		if ( empty( $data['url'] ) || empty( $data['label'] ) ) {
			return new WP_Error( 'invalid_data', __( 'URL and label are required.', 'role-url-dashboard' ), array( 'status' => 400 ) );
		}
		
		if ( ! RUD_Helpers::validate_url( $data['url'] ) ) {
			return new WP_Error( 'invalid_url', __( 'Invalid URL.', 'role-url-dashboard' ), array( 'status' => 400 ) );
		}
		
		$id = RUD_DB::insert_mapping( $data );
		
		if ( ! $id ) {
			return new WP_Error( 'insert_failed', __( 'Failed to create mapping.', 'role-url-dashboard' ), array( 'status' => 500 ) );
		}
		
		$mapping = RUD_DB::get_mapping( $id );
		return new WP_REST_Response( $mapping, 201 );
	}
	
	/**
	 * Update mapping
	 */
	public function update_mapping( $request ) {
		$id = $request->get_param( 'id' );
		$data = $request->get_json_params();
		
		$mapping = RUD_DB::get_mapping( $id );
		if ( ! $mapping ) {
			return new WP_Error( 'not_found', __( 'Mapping not found.', 'role-url-dashboard' ), array( 'status' => 404 ) );
		}
		
		if ( isset( $data['url'] ) && ! RUD_Helpers::validate_url( $data['url'] ) ) {
			return new WP_Error( 'invalid_url', __( 'Invalid URL.', 'role-url-dashboard' ), array( 'status' => 400 ) );
		}
		
		$result = RUD_DB::update_mapping( $id, $data );
		
		if ( ! $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update mapping.', 'role-url-dashboard' ), array( 'status' => 500 ) );
		}
		
		$mapping = RUD_DB::get_mapping( $id );
		return new WP_REST_Response( $mapping, 200 );
	}
	
	/**
	 * Delete mapping
	 */
	public function delete_mapping( $request ) {
		$id = $request->get_param( 'id' );
		
		$mapping = RUD_DB::get_mapping( $id );
		if ( ! $mapping ) {
			return new WP_Error( 'not_found', __( 'Mapping not found.', 'role-url-dashboard' ), array( 'status' => 404 ) );
		}
		
		$result = RUD_DB::delete_mapping( $id );
		
		if ( ! $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete mapping.', 'role-url-dashboard' ), array( 'status' => 500 ) );
		}
		
		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}
	
	/**
	 * Test link
	 */
	public function test_link( $request ) {
		$data = $request->get_json_params();
		$url = isset( $data['url'] ) ? $data['url'] : '';
		
		if ( empty( $url ) ) {
			return new WP_Error( 'invalid_data', __( 'URL is required.', 'role-url-dashboard' ), array( 'status' => 400 ) );
		}
		
		$normalized = RUD_Helpers::normalize_url( $url );
		$admin_url = RUD_Helpers::get_admin_url( $normalized );
		
		return new WP_REST_Response( array(
			'normalized' => $normalized,
			'admin_url' => $admin_url,
			'valid' => RUD_Helpers::validate_url( $url ),
		), 200 );
	}
}

