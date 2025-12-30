<?php
/**
 * Plugin Name: MAC Role URL Dashboard
 * Plugin URI: https://macusaone.com
 * Description: Quản lý URL admin được phép truy cập theo Role/User với UI đơn giản.
 * Version: 1.0.1.1
 * Author: MAC USA One
 * Author URI: https://macusaone.com
 * Text Domain: mac-role
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'RUD_VERSION', '1.0.0' );
define( 'RUD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RUD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RUD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'RUD_PLUGIN_FILE', __FILE__ );

// Include core files
require_once RUD_PLUGIN_DIR . 'includes/class-helpers.php';
require_once RUD_PLUGIN_DIR . 'includes/class-url-matcher.php';
require_once RUD_PLUGIN_DIR . 'db/class-db.php';
require_once RUD_PLUGIN_DIR . 'includes/class-access-control.php';
require_once RUD_PLUGIN_DIR . 'includes/default-links.php';
require_once RUD_PLUGIN_DIR . 'admin/class-admin-pages.php';
require_once RUD_PLUGIN_DIR . 'public/class-render.php';
require_once RUD_PLUGIN_DIR . 'api/rest.php';

/**
 * Main plugin class
 */
class Role_Url_Dashboard {
	
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
		$this->init_hooks();
	}
	
	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Activation/Deactivation
		register_activation_hook( RUD_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( RUD_PLUGIN_FILE, array( $this, 'deactivate' ) );
		
		// Load text domain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		
		// Initialize components
		add_action( 'init', array( $this, 'init' ) );
	}
	
	/**
	 * Activation hook
	 */
	public function activate() {
		RUD_DB::create_table();
		RUD_DB::add_capability_to_admin();
		
		// Enable default links on activation (only if not already set)
		$default_links = RUD_Default_Links::get_default_links();
		$enabled_status = get_option( 'rud_default_links_enabled', array() );
		$is_first_activation = empty( $enabled_status );
		
		// First, set all default links as enabled (only on first activation)
		if ( $is_first_activation ) {
			foreach ( $default_links as $link ) {
				$link_id = $link['id'];
				$enabled_status[ $link_id ] = true;
			}
			// Save enabled status first (needed for get_default_links_with_status)
			update_option( 'rud_default_links_enabled', $enabled_status );
			
			// Then create mappings for all enabled default links (only on first activation)
			foreach ( $default_links as $link ) {
				$link_id = $link['id'];
				RUD_Default_Links::create_mappings_for_link( $link_id );
			}
		}
		
		flush_rewrite_rules();
	}
	
	/**
	 * Deactivation hook
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}
	
	/**
	 * Load text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'role-url-dashboard', false, dirname( RUD_PLUGIN_BASENAME ) . '/languages' );
	}
	
	/**
	 * Initialize plugin
	 */
	public function init() {
		// Initialize access control (must be first)
		RUD_Access_Control::get_instance();
		
		// Initialize admin
		if ( is_admin() ) {
			RUD_Admin_Pages::get_instance();
		}
		
		// Initialize public render
		RUD_Render::get_instance();
		
		// Initialize REST API
		RUD_REST_API::get_instance();
	}
}

// Initialize plugin
Role_Url_Dashboard::get_instance();

