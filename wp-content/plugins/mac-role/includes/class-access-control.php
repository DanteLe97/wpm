<?php
/**
 * Access Control for Role URL Dashboard
 * Handles menu hiding, redirects, and access restrictions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RUD_Access_Control {
	
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
		// Hide admin menu for non-admin users using admin_init (works better in modern WordPress)
		add_action( 'admin_init', array( $this, 'hide_admin_menu' ), 1 );
		
		// Add body class to identify non-admin users
		add_filter( 'admin_body_class', array( $this, 'add_non_admin_body_class' ) );
		
		// CSS ẩn sớm: Hide admin bar/menu with CSS in head (runs early, before render)
		add_action( 'admin_head', array( $this, 'hide_admin_elements_early_css' ), 1 );
		
		// JavaScript xóa sau: Remove admin menu container completely from DOM
		add_action( 'admin_footer', array( $this, 'remove_admin_menu_container' ), 1 );
		
		// JavaScript xóa sau: Remove wpadminbar for non-admin users
		add_action( 'admin_footer', array( $this, 'remove_wpadminbar' ), 1 );
		
		// Add breadcrumb for non-admin users
		add_action( 'admin_notices', array( $this, 'add_breadcrumb' ), 1 );
		
		// Hide WordPress notices for non-admin users on all admin pages
		add_action( 'admin_head', array( $this, 'hide_wordpress_notices' ), 999 );
		
		// Redirect non-admin users after login
		add_filter( 'login_redirect', array( $this, 'redirect_after_login' ), 10, 3 );
		
		// Grant capability for allowed pages (must be early)
		add_filter( 'user_has_cap', array( $this, 'grant_capability_for_allowed_pages' ), 10, 4 );
		
		// Check access on admin pages
		add_action( 'admin_init', array( $this, 'check_admin_access' ), 1 );
		
		// Add back to dashboard button on allowed pages
		add_action( 'admin_notices', array( $this, 'add_back_to_dashboard_button' ) );
	}
	
	/**
	 * Check if user is administrator
	 */
	private function is_administrator() {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_role_dashboards' );
	}
	
	/**
	 * Hide admin menu for non-admin users using PHP remove_menu_page()
	 * This is more reliable than CSS hiding in modern WordPress
	 */
	public function hide_admin_menu() {
		// Check if user is admin - use direct check to avoid recursion
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return;
		}
		
		// Check if user is admin by checking capabilities directly
		$is_admin = false;
		if ( isset( $user->caps['administrator'] ) || 
		     ( isset( $user->allcaps['manage_options'] ) && $user->allcaps['manage_options'] ) ||
		     ( isset( $user->allcaps['manage_role_dashboards'] ) && $user->allcaps['manage_role_dashboards'] ) ) {
			$is_admin = true;
		}
		
		if ( $is_admin ) {
			return;
		}
		
		// Get allowed pages for current user
		$user_id = $user->ID;
		$mappings = RUD_DB::get_mappings_for_user( $user_id, true );
		$allowed_pages = array( 'role-links-dashboard' );
		
		// Extract allowed page slugs from mappings
		foreach ( $mappings as $mapping ) {
			if ( empty( $mapping['url'] ) || ! $mapping['active'] ) {
				continue;
			}
			
			// Parse primary URL
			$url = $mapping['url'];
			if ( strpos( $url, 'admin.php?page=' ) !== false ) {
				parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
				if ( isset( $params['page'] ) ) {
					$allowed_pages[] = $params['page'];
				}
			}
			
			// Also check additional URLs from meta
			$meta = ! empty( $mapping['meta'] ) ? json_decode( $mapping['meta'], true ) : array();
			if ( isset( $meta['additional_urls'] ) && is_array( $meta['additional_urls'] ) ) {
				foreach ( $meta['additional_urls'] as $add_url ) {
					if ( strpos( $add_url, 'admin.php?page=' ) !== false ) {
						parse_str( parse_url( $add_url, PHP_URL_QUERY ), $params );
						if ( isset( $params['page'] ) ) {
							$allowed_pages[] = $params['page'];
						}
					}
				}
			}
		}
		
		// Remove duplicates
		$allowed_pages = array_unique( $allowed_pages );
		
		// Get all registered menu pages
		global $menu, $submenu;
		
		// Remove all menu pages except allowed ones
		if ( isset( $menu ) && is_array( $menu ) ) {
			foreach ( $menu as $key => $item ) {
				if ( ! isset( $item[2] ) ) {
					continue;
				}
				
				$page_slug = $item[2];
				
				// Skip separators
				if ( empty( $page_slug ) ) {
					continue;
				}
				
				// Check if this page is allowed
				$is_allowed = false;
				foreach ( $allowed_pages as $allowed ) {
					// Exact match
					if ( $page_slug === $allowed ) {
						$is_allowed = true;
						break;
					}
					// Check if page slug contains allowed page (for admin.php?page=xxx)
					if ( strpos( $page_slug, 'admin.php?page=' ) !== false ) {
						parse_str( parse_url( $page_slug, PHP_URL_QUERY ), $params );
						if ( isset( $params['page'] ) && $params['page'] === $allowed ) {
							$is_allowed = true;
							break;
						}
					}
				}
				
				// Remove if not allowed
				if ( ! $is_allowed ) {
					remove_menu_page( $page_slug );
				}
			}
		}
		
		// Remove all submenu pages except allowed parent pages
		if ( isset( $submenu ) && is_array( $submenu ) ) {
			foreach ( $submenu as $parent => $items ) {
				if ( ! is_array( $items ) ) {
					continue;
				}
				
				// Check if parent is allowed
				$is_parent_allowed = false;
				foreach ( $allowed_pages as $allowed ) {
					if ( $parent === $allowed ) {
						$is_parent_allowed = true;
						break;
					}
					// Check if parent contains allowed page
					if ( strpos( $parent, 'admin.php?page=' ) !== false ) {
						parse_str( parse_url( $parent, PHP_URL_QUERY ), $params );
						if ( isset( $params['page'] ) && $params['page'] === $allowed ) {
							$is_parent_allowed = true;
							break;
						}
					}
				}
				
				// Remove all submenus if parent is not allowed
				if ( ! $is_parent_allowed ) {
					foreach ( $items as $sub_item ) {
						if ( isset( $sub_item[2] ) ) {
							remove_submenu_page( $parent, $sub_item[2] );
						}
					}
				}
			}
		}
	}
	
	/**
	 * Grant capability for allowed pages
	 * This bypasses WordPress default capability check for pages in our mapping
	 */
	public function grant_capability_for_allowed_pages( $allcaps, $caps, $args, $user ) {
		// Prevent infinite loop - use static flag
		static $processing = false;
		if ( $processing ) {
			return $allcaps;
		}
		$processing = true;
		
		try {
			// Only for non-admin users
			if ( isset( $user->ID ) ) {
				// Check capabilities directly from user object to avoid recursion
				if ( isset( $user->caps['administrator'] ) || isset( $user->caps['manage_role_dashboards'] ) ) {
					$processing = false;
					return $allcaps;
				}
			} elseif ( current_user_can( 'manage_options' ) ) {
				$processing = false;
				return $allcaps;
			}
			
			// Get current page if in admin
			$current_page = '';
			if ( is_admin() && isset( $_GET['page'] ) ) {
				$current_page = sanitize_text_field( $_GET['page'] );
			}
			
			// If no page parameter, return early
			if ( empty( $current_page ) ) {
				$processing = false;
				return $allcaps;
			}
			
			// Get user ID
			$user_id = isset( $user->ID ) ? $user->ID : get_current_user_id();
			if ( ! $user_id ) {
				$processing = false;
				return $allcaps;
			}
			
			// Get user's allowed mappings (with cache to avoid repeated queries)
			static $user_mappings_cache = array();
			if ( ! isset( $user_mappings_cache[ $user_id ] ) ) {
				$user_mappings_cache[ $user_id ] = RUD_DB::get_mappings_for_user( $user_id, true );
			}
			$mappings = $user_mappings_cache[ $user_id ];
			
			// Check if current page is in allowed mappings
			$is_allowed_page = false;
			foreach ( $mappings as $mapping ) {
				if ( empty( $mapping['url'] ) || ! $mapping['active'] ) {
					continue;
				}
				
				// Check primary URL
				if ( strpos( $mapping['url'], 'admin.php?page=' ) !== false ) {
					parse_str( parse_url( $mapping['url'], PHP_URL_QUERY ), $params );
					if ( isset( $params['page'] ) && $params['page'] === $current_page ) {
						$is_allowed_page = true;
						break;
					}
				}
				
				// Check additional URLs
				$meta = ! empty( $mapping['meta'] ) ? json_decode( $mapping['meta'], true ) : array();
				if ( isset( $meta['additional_urls'] ) && is_array( $meta['additional_urls'] ) ) {
					foreach ( $meta['additional_urls'] as $add_url ) {
						if ( strpos( $add_url, 'admin.php?page=' ) !== false ) {
							parse_str( parse_url( $add_url, PHP_URL_QUERY ), $params );
							if ( isset( $params['page'] ) && $params['page'] === $current_page ) {
								$is_allowed_page = true;
								break 2;
							}
						}
					}
				}
			}
			
			// If page is allowed, grant all requested capabilities
			if ( $is_allowed_page ) {
				foreach ( $caps as $cap ) {
					$allcaps[ $cap ] = true;
				}
			}
		} catch ( Exception $e ) {
			// If any error, just return original capabilities
		}
		
		$processing = false;
		return $allcaps;
	}
	
	/**
	 * Redirect non-admin users after login
	 */
	public function redirect_after_login( $redirect_to, $requested_redirect_to, $user ) {
		// Check if user is administrator
		if ( is_a( $user, 'WP_User' ) ) {
			if ( $user->has_cap( 'manage_options' ) || $user->has_cap( 'manage_role_dashboards' ) ) {
				return $redirect_to;
			}
		} elseif ( $this->is_administrator() ) {
			return $redirect_to;
		}
		
		// Redirect non-admin users to index.php (dashboard)
		return admin_url( 'index.php' );
	}
	
	/**
	 * Check admin access and block unauthorized pages
	 */
	public function check_admin_access() {
		// Allow administrators full access
		if ( $this->is_administrator() ) {
			return;
		}
		
		// Get current page
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		$current_file = isset( $_SERVER['PHP_SELF'] ) ? basename( $_SERVER['PHP_SELF'] ) : '';
		
		// Allow dashboard page
		if ( $current_page === 'role-links-dashboard' ) {
			return;
		}
		
		// Allow index.php (dashboard) for non-admin users
		if ( $current_file === 'index.php' ) {
			return;
		}
		
		// Get user's allowed mappings
		$user_id = get_current_user_id();
		$mappings = RUD_DB::get_mappings_for_user( $user_id );
		
		// Build current URL for comparison
		$current_url = '';
		
		// Get current file (admin.php, edit.php, etc.)
		$current_file = isset( $_SERVER['PHP_SELF'] ) ? basename( $_SERVER['PHP_SELF'] ) : '';
		
		// Build URL from file and query string
		if ( ! empty( $current_file ) ) {
			$current_url = $current_file;
			if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
				$current_url .= '?' . $_SERVER['QUERY_STRING'];
			}
		} else {
			// Fallback to REQUEST_URI parsing
			if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
				$parsed = parse_url( $_SERVER['REQUEST_URI'] );
				$current_url = isset( $parsed['path'] ) ? $parsed['path'] : '';
				if ( isset( $parsed['query'] ) ) {
					$current_url .= '?' . $parsed['query'];
				}
			}
			
			// Remove wp-admin prefix
			$current_url = str_replace( '/wp-admin/', '', $current_url );
			$current_url = ltrim( $current_url, '/' );
		}
		
		// Collect all allowed URLs (including additional URLs and related URLs)
		$allowed_urls = array();
		
		foreach ( $mappings as $mapping ) {
			if ( empty( $mapping['url'] ) || ! $mapping['active'] ) {
				continue;
			}
			
			// Add primary URL
			$allowed_urls[] = $mapping['url'];
			
			// Add additional URLs from meta
			$meta = ! empty( $mapping['meta'] ) ? json_decode( $mapping['meta'], true ) : array();
			if ( isset( $meta['additional_urls'] ) && is_array( $meta['additional_urls'] ) ) {
				foreach ( $meta['additional_urls'] as $add_url ) {
					if ( ! empty( $add_url ) ) {
						$allowed_urls[] = $add_url;
					}
				}
			}
		}
		
		// Check if current URL is allowed (including related URLs)
		$is_allowed = RUD_URL_Matcher::is_url_allowed( $current_url, $allowed_urls );
		
		// Also check by page parameter directly (for admin.php?page= URLs)
		if ( ! $is_allowed && ! empty( $current_page ) ) {
			foreach ( $mappings as $mapping ) {
				if ( empty( $mapping['url'] ) || ! $mapping['active'] ) {
					continue;
				}
				
				// Check if page matches
				if ( strpos( $mapping['url'], 'admin.php?page=' ) !== false ) {
					parse_str( parse_url( $mapping['url'], PHP_URL_QUERY ), $params );
					if ( isset( $params['page'] ) && $params['page'] === $current_page ) {
						$is_allowed = true;
						break;
					}
				}
				
				// Check additional URLs
				$meta = ! empty( $mapping['meta'] ) ? json_decode( $mapping['meta'], true ) : array();
				if ( isset( $meta['additional_urls'] ) && is_array( $meta['additional_urls'] ) ) {
					foreach ( $meta['additional_urls'] as $add_url ) {
						if ( strpos( $add_url, 'admin.php?page=' ) !== false ) {
							parse_str( parse_url( $add_url, PHP_URL_QUERY ), $params );
							if ( isset( $params['page'] ) && $params['page'] === $current_page ) {
								$is_allowed = true;
								break 2;
							}
						}
					}
				}
			}
		}
		
		// Block access if not allowed
		if ( ! $is_allowed ) {
			// Only block if we're actually in admin area and not on dashboard
			if ( is_admin() && $current_page !== 'role-links-dashboard' ) {
				// Redirect to dashboard instead of showing error
				wp_safe_redirect( admin_url( 'index.php' ) );
				exit;
			}
		}
	}
	
	/**
	 * Add body class to identify non-admin users
	 * This allows CSS/JS to easily target non-admin users
	 */
	public function add_non_admin_body_class( $classes ) {
		// Check if user is admin - use direct check to avoid recursion
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return $classes;
		}
		
		// Check if user is admin by checking capabilities directly
		$is_admin = false;
		if ( isset( $user->caps['administrator'] ) || 
		     ( isset( $user->allcaps['manage_options'] ) && $user->allcaps['manage_options'] ) ||
		     ( isset( $user->allcaps['manage_role_dashboards'] ) && $user->allcaps['manage_role_dashboards'] ) ) {
			$is_admin = true;
		}
		
		// Add class for non-admin users
		if ( ! $is_admin ) {
			$classes .= ' rud-non-admin rud-restricted-user';
			
			// Also add role-specific class if available
			if ( ! empty( $user->roles ) && is_array( $user->roles ) ) {
				foreach ( $user->roles as $role ) {
					$classes .= ' rud-role-' . sanitize_html_class( $role );
				}
			}
		}
		
		return $classes;
	}
	
	/**
	 * CSS ẩn sớm: Hide admin bar and menu with CSS in head (runs before render)
	 * This prevents FOUC (Flash of Unstyled Content)
	 */
	public function hide_admin_elements_early_css() {
		// Check if user is admin - use direct check to avoid recursion
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return;
		}
		
		// Check if user is admin by checking capabilities directly
		$is_admin = false;
		if ( isset( $user->caps['administrator'] ) || 
		     ( isset( $user->allcaps['manage_options'] ) && $user->allcaps['manage_options'] ) ||
		     ( isset( $user->allcaps['manage_role_dashboards'] ) && $user->allcaps['manage_role_dashboards'] ) ) {
			$is_admin = true;
		}
		
		if ( $is_admin ) {
			return;
		}
		
		// CSS inline trong head để ẩn ngay từ đầu (trước khi render)
		?>
		<style id="rud-hide-admin-early">
			/* Hide admin menu sidebar - ẩn ngay từ đầu */
			#adminmenumain,
			#adminmenuwrap,
			#adminmenuback,
			#adminmenu {
				display: none !important;
				visibility: hidden !important;
				width: 0 !important;
				position: absolute !important;
				left: -9999px !important;
				height: 0 !important;
				overflow: hidden !important;
				opacity: 0 !important;
			}
			
			/* Hide admin bar - ẩn ngay từ đầu */
			#wpadminbar {
				display: none !important;
				visibility: hidden !important;
				height: 0 !important;
				overflow: hidden !important;
				opacity: 0 !important;
			}
			
			/* Remove admin-bar class effect */
			body.admin-bar {
				padding-top: 0 !important;
			}
			
			/* Adjust content area - ngay từ đầu */
			#wpcontent,
			#wpbody-content {
				margin-left: 0 !important;
				padding-left: 20px !important;
			}
			
			.folded #wpcontent,
			.folded #wpbody-content {
				margin-left: 0 !important;
				padding-left: 20px !important;
			}
			
			#wpbody {
				margin-left: 0 !important;
			}
		</style>
		<?php
	}
	
	/**
	 * Remove wpadminbar for non-admin users (JavaScript xóa sau)
	 */
	public function remove_wpadminbar() {
		// Check if user is admin - use direct check to avoid recursion
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return;
		}
		
		// Check if user is admin by checking capabilities directly
		$is_admin = false;
		if ( isset( $user->caps['administrator'] ) || 
		     ( isset( $user->allcaps['manage_options'] ) && $user->allcaps['manage_options'] ) ||
		     ( isset( $user->allcaps['manage_role_dashboards'] ) && $user->allcaps['manage_role_dashboards'] ) ) {
			$is_admin = true;
		}
		
		if ( $is_admin ) {
			return;
		}
		
		// Use JavaScript to completely remove wpadminbar from DOM
		?>
		<script>
		(function() {
			function removeAdminBar() {
				var wpadminbar = document.getElementById('wpadminbar');
				if (wpadminbar && wpadminbar.parentNode) {
					wpadminbar.parentNode.removeChild(wpadminbar);
				}
				
				// Remove admin-bar class from body
				document.body.classList.remove('admin-bar');
				
				// Adjust body padding if needed
				if (document.body.style.paddingTop) {
					document.body.style.paddingTop = '0';
				}
			}
			
			// Run immediately
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', removeAdminBar);
			} else {
				removeAdminBar();
			}
			
			// Run after delays to catch late-loading elements
			setTimeout(removeAdminBar, 10);
			setTimeout(removeAdminBar, 50);
			setTimeout(removeAdminBar, 100);
		})();
		</script>
		<?php
	}
	
	/**
	 * Hide WordPress notices for non-admin users on all admin pages
	 */
	public function hide_wordpress_notices() {
		// Check if user is admin - use direct check to avoid recursion
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return;
		}
		
		// Check if user is admin by checking capabilities directly
		$is_admin = false;
		if ( isset( $user->caps['administrator'] ) || 
		     ( isset( $user->allcaps['manage_options'] ) && $user->allcaps['manage_options'] ) ||
		     ( isset( $user->allcaps['manage_role_dashboards'] ) && $user->allcaps['manage_role_dashboards'] ) ) {
			$is_admin = true;
		}
		
		if ( $is_admin ) {
			return;
		}
		
		?>
		<style>
			/* Hide all WordPress notices and update messages for non-admin users */
			.notice,
			.notice-warning,
			.notice-info,
			.notice-success,
			.notice-error,
			.update-nag,
			.updated,
			.error,
			.wrap > .notice,
			.wrap > .update-nag,
			.wrap > .updated,
			.wrap > .error,
			#wpbody-content > .notice,
			#wpbody-content > .update-nag,
			#wpbody-content > .updated,
			#wpbody-content > .error,
			.notice.notice-warning.update-nag,
			.notice.notice-warning.update-nag.inline {
				display: none !important;
				visibility: hidden !important;
				height: 0 !important;
				overflow: hidden !important;
				margin: 0 !important;
				padding: 0 !important;
			}
		</style>
		<script>
		(function() {
			function hideNotices() {
				// Hide all notices using JavaScript as backup
				var notices = document.querySelectorAll('.notice, .update-nag, .updated, .error, .notice-warning, .notice-info, .notice-success, .notice-error');
				for (var i = 0; i < notices.length; i++) {
					notices[i].style.display = 'none';
					notices[i].style.visibility = 'hidden';
					notices[i].style.height = '0';
					notices[i].style.overflow = 'hidden';
					notices[i].style.margin = '0';
					notices[i].style.padding = '0';
				}
			}
			
			// Run immediately
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', hideNotices);
			} else {
				hideNotices();
			}
			
			// Run after delays to catch late-loading notices
			setTimeout(hideNotices, 10);
			setTimeout(hideNotices, 50);
			setTimeout(hideNotices, 100);
			setTimeout(hideNotices, 300);
		})();
		</script>
		<?php
	}
	
	/**
	 * Add breadcrumb for non-admin users
	 */
	public function add_breadcrumb() {
		// Check if user is admin - use direct check to avoid recursion
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return;
		}
		
		// Check if user is admin by checking capabilities directly
		$is_admin = false;
		if ( isset( $user->caps['administrator'] ) || 
		     ( isset( $user->allcaps['manage_options'] ) && $user->allcaps['manage_options'] ) ||
		     ( isset( $user->allcaps['manage_role_dashboards'] ) && $user->allcaps['manage_role_dashboards'] ) ) {
			$is_admin = true;
		}
		
		if ( $is_admin ) {
			return;
		}
		
		global $pagenow;
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		$is_dashboard = ( $pagenow === 'index.php' ) || ( $current_page === 'role-links-dashboard' );
		
		// Don't show breadcrumb on dashboard page
		if ( $is_dashboard ) {
			return;
		}
		
		// Get current page title
		$page_title = '';
		global $menu, $submenu;
		
		if ( ! empty( $current_page ) ) {
			// Try to find in main menu
			foreach ( $menu as $menu_item ) {
				if ( isset( $menu_item[2] ) && $menu_item[2] === $current_page ) {
					$page_title = strip_tags( $menu_item[0] );
					break;
				}
			}
			
			// Try to find in submenu
			if ( empty( $page_title ) && isset( $submenu ) ) {
				foreach ( $submenu as $parent => $submenu_items ) {
					foreach ( $submenu_items as $submenu_item ) {
						if ( isset( $submenu_item[2] ) && $submenu_item[2] === $current_page ) {
							$page_title = strip_tags( $submenu_item[0] );
							break 2;
						}
					}
				}
			}
		}
		
		// Fallback: use page parameter or page filename
		if ( empty( $page_title ) ) {
			if ( ! empty( $current_page ) ) {
				$page_title = ucwords( str_replace( array( '-', '_' ), ' ', $current_page ) );
			} else {
				$page_title = ucwords( str_replace( array( '.php', '-', '_' ), ' ', basename( $pagenow ) ) );
			}
		}
		
		?>
		<div class="rud-breadcrumb" style="margin: -10px 0 20px 0; padding: 15px 20px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7; font-size: 14px;">
			<a href="<?php echo admin_url( 'index.php' ); ?>" 
			   style="text-decoration: none; color: #ff5c02; font-weight: 500;">
				<?php _e( 'Dashboard', 'role-url-dashboard' ); ?>
			</a>
			<span style="margin: 0 10px; color: #8c8f94;">/</span>
			<span style="color: #1d2327; font-weight: 500;">
				<?php echo esc_html( $page_title ); ?>
			</span>
		</div>
		<?php
	}
	
	/**
	 * Remove admin menu container completely using JavaScript (no CSS)
	 * This removes the HTML completely from DOM, not just hiding with CSS
	 */
	public function remove_admin_menu_container() {
		// Check if user is admin - use direct check to avoid recursion
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return;
		}
		
		// Check if user is admin by checking capabilities directly
		$is_admin = false;
		if ( isset( $user->caps['administrator'] ) || 
		     ( isset( $user->allcaps['manage_options'] ) && $user->allcaps['manage_options'] ) ||
		     ( isset( $user->allcaps['manage_role_dashboards'] ) && $user->allcaps['manage_role_dashboards'] ) ) {
			$is_admin = true;
		}
		
		if ( $is_admin ) {
			return;
		}
		
		// Use JavaScript to completely remove the container from DOM (PHP can't modify already rendered HTML)
		// This is the only way to remove it after WordPress has rendered it
		?>
		<script>
		(function() {
			function removeMenuContainer() {
				var adminMenuMain = document.getElementById('adminmenumain');
				var adminMenuWrap = document.getElementById('adminmenuwrap');
				var adminMenuBack = document.getElementById('adminmenuback');
				var adminMenu = document.getElementById('adminmenu');
				
				// Remove elements completely from DOM (not CSS display:none)
				if (adminMenuMain && adminMenuMain.parentNode) {
					adminMenuMain.parentNode.removeChild(adminMenuMain);
				}
				if (adminMenuWrap && adminMenuWrap.parentNode) {
					adminMenuWrap.parentNode.removeChild(adminMenuWrap);
				}
				if (adminMenuBack && adminMenuBack.parentNode) {
					adminMenuBack.parentNode.removeChild(adminMenuBack);
				}
				if (adminMenu && adminMenu.parentNode) {
					adminMenu.parentNode.removeChild(adminMenu);
				}
				
				// Adjust content area
				var wpcontent = document.getElementById('wpcontent');
				var wpbodyContent = document.getElementById('wpbody-content');
				if (wpcontent) {
					wpcontent.style.marginLeft = '0';
					wpcontent.style.paddingLeft = '20px';
				}
				if (wpbodyContent) {
					wpbodyContent.style.marginLeft = '0';
					wpbodyContent.style.paddingLeft = '20px';
				}
			}
			
			// Run immediately
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', removeMenuContainer);
			} else {
				removeMenuContainer();
			}
			
			// Run after delays to catch late-loading elements
			setTimeout(removeMenuContainer, 10);
			setTimeout(removeMenuContainer, 50);
			setTimeout(removeMenuContainer, 100);
		})();
		</script>
		<?php
	}
	
	/**
	 * @deprecated - No longer used, replaced by remove_admin_menu_container()
	 */
	public function add_inline_css_direct() {
		// Check directly - avoid recursion
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return;
		}
		
		// Check if user is admin by checking capabilities directly
		$is_admin = false;
		if ( isset( $user->caps['administrator'] ) || 
		     ( isset( $user->allcaps['manage_options'] ) && $user->allcaps['manage_options'] ) ||
		     ( isset( $user->allcaps['manage_role_dashboards'] ) && $user->allcaps['manage_role_dashboards'] ) ) {
			$is_admin = true;
		}
		
		if ( $is_admin ) {
			return;
		}
		
		// Add CSS directly to head to ensure it's always loaded
		// Use very specific selectors with highest priority
		echo '<style id="rud-hide-menu-direct">';
		echo 'body.wp-admin #adminmenuwrap, body.wp-admin #adminmenuback, body.wp-admin #adminmenu { display: none !important; visibility: hidden !important; width: 0 !important; position: absolute !important; left: -9999px !important; height: 0 !important; overflow: hidden !important; opacity: 0 !important; }';
		echo 'body.wp-admin #wpcontent, body.wp-admin #wpbody-content { margin-left: 0 !important; padding-left: 20px !important; }';
		echo 'body.wp-admin #adminmenu li, body.wp-admin #adminmenu * { display: none !important; visibility: hidden !important; }';
		echo '</style>';
		
		// Also add immediate JavaScript (runs before DOM ready)
		echo '<script>';
		echo '(function(){';
		echo 'function h(){var e=document.getElementById("adminmenuwrap");if(e)e.style.cssText="display:none!important;visibility:hidden!important;width:0!important;position:absolute!important;left:-9999px!important;height:0!important;overflow:hidden!important;opacity:0!important;";';
		echo 'var e2=document.getElementById("adminmenuback");if(e2)e2.style.cssText="display:none!important;visibility:hidden!important;width:0!important;position:absolute!important;left:-9999px!important;height:0!important;overflow:hidden!important;opacity:0!important;";';
		echo 'var e3=document.getElementById("adminmenu");if(e3)e3.style.cssText="display:none!important;visibility:hidden!important;";';
		echo 'var w=document.getElementById("wpcontent");if(w){w.style.marginLeft="0";w.style.paddingLeft="20px";}';
		echo 'var w2=document.getElementById("wpbody-content");if(w2){w2.style.marginLeft="0";w2.style.paddingLeft="20px";}';
		echo '}';
		echo 'h();';
		echo 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",h);}else{h();}';
		echo 'setTimeout(h,10);setTimeout(h,50);setTimeout(h,100);';
		echo '})();';
		echo '</script>';
	}
	
	/**
	 * Hide admin elements (admin bar, etc.)
	 * This runs on ALL admin pages via admin_head hook
	 */
	public function hide_admin_elements() {
		// Always check - don't rely on is_administrator() which might have issues
		// Check user capabilities directly to avoid recursion
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return;
		}
		
		// Check if user is admin by checking capabilities directly
		$is_admin = false;
		if ( isset( $user->caps['administrator'] ) || 
		     ( isset( $user->allcaps['manage_options'] ) && $user->allcaps['manage_options'] ) ||
		     ( isset( $user->allcaps['manage_role_dashboards'] ) && $user->allcaps['manage_role_dashboards'] ) ) {
			$is_admin = true;
		}
		
		if ( $is_admin ) {
			return;
		}
		
		?>
		<style id="rud-hide-admin-menu">
			/* Hide admin menu sidebar - CRITICAL: must be on all pages */
			#adminmenuwrap,
			#adminmenuback {
				display: none !important;
				width: 0 !important;
				visibility: hidden !important;
				position: absolute !important;
				left: -9999px !important;
				height: 0 !important;
				overflow: hidden !important;
				opacity: 0 !important;
			}
			
			/* Hide admin menu completely */
			#adminmenu,
			#adminmenu li,
			#adminmenu .wp-menu-arrow,
			#adminmenu * {
				display: none !important;
				visibility: hidden !important;
			}
			
			/* Adjust content area */
			#wpcontent,
			#wpbody-content {
				margin-left: 0 !important;
				padding-left: 20px !important;
			}
			
			.folded #wpcontent,
			.folded #wpbody-content {
				margin-left: 0 !important;
				padding-left: 20px !important;
			}
			
			#wpbody {
				margin-left: 0 !important;
			}
			
			/* Hide any remaining menu elements */
			.wp-menu-separator,
			#adminmenu-wrap {
				display: none !important;
			}
		</style>
		<script>
		// Force hide with vanilla JS (no jQuery dependency)
		(function() {
			function forceHideMenu() {
				var elements = ['adminmenuwrap', 'adminmenuback', 'adminmenu'];
				elements.forEach(function(id) {
					var el = document.getElementById(id);
					if (el) {
						el.style.cssText = 'display: none !important; visibility: hidden !important; width: 0 !important; position: absolute !important; left: -9999px !important; height: 0 !important; overflow: hidden !important; opacity: 0 !important;';
					}
				});
				
				// Also hide by class
				var menuItems = document.querySelectorAll('#adminmenu li, #adminmenu *');
				for (var i = 0; i < menuItems.length; i++) {
					menuItems[i].style.cssText = 'display: none !important; visibility: hidden !important;';
				}
				
				// Adjust content area
				var wpcontent = document.getElementById('wpcontent');
				var wpbodyContent = document.getElementById('wpbody-content');
				if (wpcontent) {
					wpcontent.style.marginLeft = '0';
					wpcontent.style.paddingLeft = '20px';
				}
				if (wpbodyContent) {
					wpbodyContent.style.marginLeft = '0';
					wpbodyContent.style.paddingLeft = '20px';
				}
			}
			
			// Run immediately
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', forceHideMenu);
			} else {
				forceHideMenu();
			}
			
			// Run multiple times to catch late-loading elements
			setTimeout(forceHideMenu, 10);
			setTimeout(forceHideMenu, 50);
			setTimeout(forceHideMenu, 100);
			setTimeout(forceHideMenu, 200);
			setTimeout(forceHideMenu, 300);
			setTimeout(forceHideMenu, 500);
			setTimeout(forceHideMenu, 1000);
			setTimeout(forceHideMenu, 2000);
			
			// Also use MutationObserver to catch dynamically added elements
			if (window.MutationObserver) {
				var observer = new MutationObserver(function(mutations) {
					forceHideMenu();
				});
				if (document.body) {
					observer.observe(document.body, {
						childList: true,
						subtree: true,
						attributes: true,
						attributeFilter: ['style', 'class']
					});
				}
			}
			
			// Also listen for window load
			window.addEventListener('load', forceHideMenu);
		})();
		</script>
		<?php
	}
	
	/**
	 * Enqueue access control CSS file
	 * This MUST run on ALL admin pages
	 */
	public function enqueue_access_control_css( $hook ) {
		// Check directly without is_administrator() to avoid recursion
		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_role_dashboards' ) ) {
			return;
		}
		
		// Load on ALL admin pages for non-admin users (no hook check)
		wp_enqueue_style(
			'rud-access-control',
			RUD_PLUGIN_URL . 'assets/css/access-control.css',
			array(),
			RUD_VERSION
		);
		
		// Ensure jQuery is loaded for JavaScript
		wp_enqueue_script( 'jquery' );
	}
	
	/**
	 * Enqueue access control styles (inline fallback)
	 * This MUST run on ALL admin pages
	 */
	public function enqueue_access_control_styles( $hook ) {
		// Check directly without is_administrator() to avoid recursion
		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_role_dashboards' ) ) {
			return;
		}
		// Add inline style as fallback - ensure it works on all pages
		$css = '
				/* Hide admin menu sidebar - must be on all pages */
				#adminmenuwrap,
				#adminmenuback {
					display: none !important;
					width: 0 !important;
					visibility: hidden !important;
					position: absolute !important;
					left: -9999px !important;
					height: 0 !important;
					overflow: hidden !important;
				}
				
				/* Hide admin menu items */
				#adminmenu,
				#adminmenu li,
				#adminmenu .wp-menu-arrow,
				#adminmenu * {
					display: none !important;
					visibility: hidden !important;
				}
				
				/* Adjust content area */
				#wpcontent,
				#wpbody-content {
					margin-left: 0 !important;
					padding-left: 20px !important;
				}
				
				.folded #wpcontent,
				.folded #wpbody-content {
					margin-left: 0 !important;
					padding-left: 20px !important;
				}
				
				#wpbody {
					margin-left: 0 !important;
				}
			';
		
		// Always add inline style to ensure it works on all pages
		if ( wp_style_is( 'rud-access-control', 'enqueued' ) ) {
			wp_add_inline_style( 'rud-access-control', $css );
		} else {
			// If CSS file not loaded, add to common or directly
			if ( wp_style_is( 'common', 'enqueued' ) ) {
				wp_add_inline_style( 'common', $css );
			} else {
				// Last resort: add directly in head
				add_action( 'admin_head', function() use ( $css ) {
					echo '<style>' . $css . '</style>';
				}, 9999 );
			}
		}
	}
	
	/**
	 * Hide admin menu in footer (backup)
	 * This MUST run on ALL admin pages
	 */
	public function hide_admin_menu_footer() {
		// Check directly - avoid recursion
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return;
		}
		
		// Check if user is admin by checking capabilities directly
		$is_admin = false;
		if ( isset( $user->caps['administrator'] ) || 
		     ( isset( $user->allcaps['manage_options'] ) && $user->allcaps['manage_options'] ) ||
		     ( isset( $user->allcaps['manage_role_dashboards'] ) && $user->allcaps['manage_role_dashboards'] ) ) {
			$is_admin = true;
		}
		
		if ( $is_admin ) {
			return;
		}
		?>
		<script>
			// Force hide admin menu - run in footer as final backup
			(function() {
				function forceHideMenu() {
					var menuWrap = document.getElementById('adminmenuwrap');
					var menuBack = document.getElementById('adminmenuback');
					var adminMenu = document.getElementById('adminmenu');
					
					if (menuWrap) {
						menuWrap.style.cssText = 'display: none !important; visibility: hidden !important; width: 0 !important; position: absolute !important; left: -9999px !important; height: 0 !important; overflow: hidden !important;';
					}
					if (menuBack) {
						menuBack.style.cssText = 'display: none !important; visibility: hidden !important; width: 0 !important; position: absolute !important; left: -9999px !important; height: 0 !important; overflow: hidden !important;';
					}
					if (adminMenu) {
						adminMenu.style.cssText = 'display: none !important; visibility: hidden !important;';
					}
					
					// Adjust content
					var wpcontent = document.getElementById('wpcontent');
					var wpbodyContent = document.getElementById('wpbody-content');
					if (wpcontent) {
						wpcontent.style.marginLeft = '0';
						wpcontent.style.paddingLeft = '20px';
					}
					if (wpbodyContent) {
						wpbodyContent.style.marginLeft = '0';
						wpbodyContent.style.paddingLeft = '20px';
					}
				}
				
				// Run immediately
				forceHideMenu();
				
				// Run on DOM ready
				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', forceHideMenu);
				} else {
					forceHideMenu();
				}
				
				// Run after delays
				setTimeout(forceHideMenu, 100);
				setTimeout(forceHideMenu, 500);
				setTimeout(forceHideMenu, 1000);
			})();
		</script>
		<?php
	}
	
	
	/**
	 * Add back to dashboard button on allowed pages
	 */
	public function add_back_to_dashboard_button() {
		if ( $this->is_administrator() ) {
			return;
		}
		
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		
		// Only show on pages other than dashboard
		if ( $current_page !== 'role-links-dashboard' && ! empty( $current_page ) ) {
			?>
			<div class="notice rud-back-dashboard-notice" style="margin: 15px 0; padding: 10px 15px; background: #fff; border-left: 4px solid #ff5c02;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=role-links-dashboard' ) ); ?>" 
				   class="button button-primary" 
				   style="font-size: 16px; padding: 8px 16px; text-decoration: none;">
					<?php _e( '← Quay về Dashboard', 'role-url-dashboard' ); ?>
				</a>
			</div>
			<?php
		}
	}
}

