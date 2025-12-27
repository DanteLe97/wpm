<?php
/**
 * Public render for User Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RUD_Render {
	
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
		add_action( 'admin_menu', array( $this, 'add_user_dashboard_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		// Hide admin bar for non-admin users
		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar_for_non_admin' ) );
		
		// Replace dashboard index if setting is enabled - use early hook
		add_action( 'admin_init', array( $this, 'maybe_replace_dashboard_index' ), 1 );
		add_action( 'load-index.php', array( $this, 'maybe_replace_dashboard_index' ) );
	}
	
	/**
	 * Add user dashboard menu
	 */
	public function add_user_dashboard_menu() {
		$settings = get_option( 'rud_settings', array( 'dashboard_location' => 'index' ) );
		$location = isset( $settings['dashboard_location'] ) ? $settings['dashboard_location'] : 'index';
		
		// Always add menu page so dashboard is accessible via URL
		// If location is 'index', we'll redirect index.php to this page
		add_menu_page(
			__( 'My Dashboard', 'role-url-dashboard' ),
			__( 'My Dashboard', 'role-url-dashboard' ),
			'read',
			'role-links-dashboard',
			array( $this, 'render_user_dashboard' ),
			'dashicons-dashboard',
			$location === 'index' ? 2 : 2 // Position 2 (right after Dashboard)
		);
	}
	
	/**
	 * Enqueue scripts for user dashboard
	 */
	public function enqueue_scripts( $hook ) {
		// Hide admin bar CSS for all admin pages (non-admin users)
		if ( ! current_user_can( 'administrator' ) ) {
			wp_add_inline_style( 'wp-admin', '#wpadminbar { display: none !important; } body.admin-bar { padding-top: 0 !important; }' );
		}
		
		// Load dashboard styles on dashboard page
		if ( $hook === 'toplevel_page_role-links-dashboard' ) {
			wp_enqueue_style(
				'rud-dashboard',
				RUD_PLUGIN_URL . 'assets/css/dashboard.css',
				array(),
				RUD_VERSION
			);
			
			wp_enqueue_script(
				'rud-dashboard',
				RUD_PLUGIN_URL . 'assets/js/dashboard.js',
				array( 'jquery' ),
				RUD_VERSION,
				true
			);
			
			$settings = get_option( 'rud_settings', array( 'allow_iframe' => 0 ) );
			
			wp_localize_script( 'rud-dashboard', 'rudDashboard', array(
				'allowIframe' => isset( $settings['allow_iframe'] ) ? $settings['allow_iframe'] : 0,
			) );
		}
		
		// Load dashboard styles on index.php for non-admin users
		if ( $hook === 'index.php' && ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_role_dashboards' ) ) {
			wp_enqueue_style(
				'rud-dashboard',
				RUD_PLUGIN_URL . 'assets/css/dashboard.css',
				array(),
				RUD_VERSION
			);
			
			wp_enqueue_script(
				'rud-dashboard',
				RUD_PLUGIN_URL . 'assets/js/dashboard.js',
				array( 'jquery' ),
				RUD_VERSION,
				true
			);
			
			$settings = get_option( 'rud_settings', array( 'allow_iframe' => 0 ) );
			
			wp_localize_script( 'rud-dashboard', 'rudDashboard', array(
				'allowIframe' => isset( $settings['allow_iframe'] ) ? $settings['allow_iframe'] : 0,
			) );
		}
	}
	
	/**
	 * Hide admin bar for non-admin users
	 */
	public function hide_admin_bar_for_non_admin( $show ) {
		if ( ! is_user_logged_in() ) {
			return $show;
		}
		
		// Show admin bar only for administrators
		if ( current_user_can( 'administrator' ) ) {
			return $show;
		}
		
		// Hide for all other users
		return false;
	}
	
	/**
	 * Replace dashboard index if setting is enabled
	 */
	public function maybe_replace_dashboard_index() {
		// Only run on index.php page
		global $pagenow;
		if ( $pagenow !== 'index.php' ) {
			return;
		}
		
		// Only replace for non-admin users
		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_role_dashboards' ) ) {
			return; // Let admins see default dashboard
		}
		
		// Remove default dashboard widgets and welcome panel
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
		
		// Hide WordPress notices using CSS and JS (more reliable)
		add_action( 'admin_head', array( $this, 'hide_wordpress_elements_css' ), 999 );
		
		// Replace dashboard content
		add_action( 'admin_footer', array( $this, 'replace_dashboard_content' ), 999 );
		add_filter( 'screen_options_show_screen', '__return_false', 999 );
		add_filter( 'contextual_help', '__return_empty_string', 999 );
	}
	
	/**
	 * Hide WordPress elements with CSS
	 */
	public function hide_wordpress_elements_css() {
		global $pagenow;
		if ( $pagenow !== 'index.php' ) {
			return;
		}
		
		// Only for non-admin users
		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_role_dashboards' ) ) {
			return;
		}
		
		?>
		<style>
			/* Hide WordPress dashboard title */
			.wrap > h1:first-child,
			.wrap > h1.wp-heading-inline {
				display: none !important;
			}
			
			/* Hide all WordPress notices */
			.wrap > .notice,
			.wrap > .updated,
			.wrap > .error,
			.wrap > .update-nag,
			.notice,
			.updated,
			.error,
			.update-nag {
				display: none !important;
			}
			
			/* Hide welcome panel */
			#welcome-panel {
				display: none !important;
			}
			
			/* Hide screen options and help */
			#screen-options-link-wrap,
			#contextual-help-link-wrap {
				display: none !important;
			}
			
			/* Hide dashboard widgets */
			#dashboard-widgets-wrap {
				display: none !important;
			}
		</style>
		<?php
	}
	
	/**
	 * Replace dashboard content with our custom dashboard
	 */
	public function replace_dashboard_content() {
		global $pagenow;
		if ( $pagenow !== 'index.php' ) {
			return;
		}
		
		// Only for non-admin users
		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_role_dashboards' ) ) {
			return;
		}
		
		// Get dashboard content
		ob_start();
		$this->render_dashboard_content();
		$dashboard_html = ob_get_clean();
		
		?>
		<script>
		jQuery(document).ready(function($) {
			// Hide WordPress default elements
			$('#dashboard-widgets-wrap').hide();
			$('#welcome-panel').hide();
			$('.wrap h1').first().hide(); // Hide WordPress title
			$('.notice, .update-nag, .error, .updated').hide(); // Hide all WordPress notices
			$('#wpbody-content .wrap > .notice').hide(); // Hide notices in content area
			$('#wpbody-content .wrap > .updated').hide();
			$('#wpbody-content .wrap > .error').hide();
			$('#wpbody-content .wrap > .update-nag').hide();
			
			// Hide screen options and help tabs
			$('#screen-options-link-wrap').hide();
			$('#contextual-help-link-wrap').hide();
			
			// Get dashboard content
			var dashboardContent = <?php echo json_encode( $dashboard_html ); ?>;
			
			// Replace dashboard widgets with our content
			$('#dashboard-widgets-wrap').before('<div class="rud-dashboard-container">' + dashboardContent + '</div>').remove();
		});
		</script>
		<style>
			/* Hide WordPress notices and messages */
			.wrap > .notice,
			.wrap > .updated,
			.wrap > .error,
			.wrap > .update-nag {
				display: none !important;
			}
			
			/* Hide WordPress dashboard title */
			.wrap > h1:first-child {
				display: none !important;
			}
			
			/* Hide welcome panel */
			#welcome-panel {
				display: none !important;
			}
			
			/* Hide screen options and help */
			#screen-options-link-wrap,
			#contextual-help-link-wrap {
				display: none !important;
			}
		</style>
		<?php
	}
	
	/**
	 * AJAX handler to get dashboard content
	 */
	public function ajax_get_dashboard_content() {
		check_ajax_referer( 'rud-dashboard-ajax', 'nonce' );
		
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'role-url-dashboard' ) ) );
		}
		
		ob_start();
		$this->render_dashboard_content();
		$content = ob_get_clean();
		
		wp_send_json_success( array( 'html' => $content ) );
	}
	
	/**
	 * Render dashboard content (used by both page and AJAX)
	 */
	private function render_dashboard_content() {
		$user_id = get_current_user_id();
		$mappings = RUD_DB::get_mappings_for_user( $user_id );
		
		// Filter out inactive mappings
		$mappings = array_filter( $mappings, function( $m ) {
			return isset( $m['active'] ) && $m['active'] == 1;
		} );
		
		// Group mappings by prefix if helper function exists
		if ( method_exists( 'RUD_Helpers', 'group_mappings_by_prefix' ) ) {
			$grouped_data = RUD_Helpers::group_mappings_by_prefix( $mappings );
			$grouped_mappings = isset( $grouped_data['grouped'] ) ? $grouped_data['grouped'] : array();
			$standalone_mappings = isset( $grouped_data['standalone'] ) ? $grouped_data['standalone'] : array();
		} else {
			// Fallback: treat all as standalone
			$grouped_mappings = array();
			$standalone_mappings = $mappings;
		}
		
		// Check if we're on index.php (dashboard) or admin.php?page=role-links-dashboard
		global $pagenow;
		$is_dashboard_page = ( $pagenow === 'index.php' ) || ( isset( $_GET['page'] ) && $_GET['page'] === 'role-links-dashboard' );
		
		include RUD_PLUGIN_DIR . 'public/templates/dashboard.php';
	}
	
	/**
	 * Render user dashboard
	 */
	public function render_user_dashboard() {
		if ( ! is_user_logged_in() ) {
			wp_die( __( 'You must be logged in to view this page.', 'role-url-dashboard' ) );
		}
		
		$this->render_dashboard_content();
	}
}

