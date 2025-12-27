<?php
	/*
	Plugin Name: MAC Importer Demo
	Plugin URI: https://macusaone.com
	Description: Import Elementor pages and site settings between domains
	Author: MAC USA One
	*/
define( 'BK_HOMEPAGE', 'Home Demo' );
define( 'BK_AD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BK_AD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once (BK_AD_PLUGIN_DIR.'templates/welcome_template.php');
require_once (BK_AD_PLUGIN_DIR.'library/demo_import.php');

// Include API classes
require_once (BK_AD_PLUGIN_DIR.'api/class-api-base.php');
require_once (BK_AD_PLUGIN_DIR.'api/class-export-api.php');
require_once (BK_AD_PLUGIN_DIR.'api/class-import-api.php');
require_once (BK_AD_PLUGIN_DIR.'api/class-utility-apis.php');
require_once (BK_AD_PLUGIN_DIR.'api/class-ajax-apis.php');


// Enqueue imported fonts
add_action('wp_enqueue_scripts', 'mac_importer_enqueue_fonts', 20);
function mac_importer_enqueue_fonts() {
    $fonts = get_option('elementor_fonts', array());
    if (!empty($fonts) && is_array($fonts)) {
        foreach ($fonts as $font) {
            // Enqueue Google Fonts
            $font_url = 'https://fonts.googleapis.com/css2?family=' . str_replace(' ', '+', $font) . ':wght@300;400;500;600;700&display=swap';
            wp_enqueue_style('google-font-' . sanitize_title($font), $font_url, array(), null);
        }
    }
}

// Đảm bảo có hàm wp_tempnam khi chạy qua REST (một số môi trường không load file.php)
if ( ! function_exists('wp_tempnam') ) {
    if ( defined('ABSPATH') ) {
        @require_once ABSPATH . 'wp-admin/includes/file.php';
    }
}

if ( ! function_exists( 'bk_admin_panel_scripts_method' ) ) {
    function bk_admin_panel_scripts_method() {
        wp_enqueue_style( 'bkadstyle', BK_AD_PLUGIN_URL . 'assets/css/style.css', array(), '' );
        wp_enqueue_script('bkadscript', BK_AD_PLUGIN_URL . 'assets/js/main.js', array('jquery'),false, true);
    }
}
add_action('admin_enqueue_scripts', 'bk_admin_panel_scripts_method');

/**-------------------------------------------------------------------------------------------------------------------------
 * register ajax
 */
if ( ! function_exists( 'bk_admin_enqueue_ajax_url' ) ) {
	function bk_admin_enqueue_ajax_url() {
        echo '<script type="application/javascript">var ajaxurl = "' . esc_url(admin_url( 'admin-ajax.php' )) . '"</script>';
	}
	add_action( 'admin_enqueue_scripts', 'bk_admin_enqueue_ajax_url' );
}

function bk_theme_welcome() {
    add_menu_page(
        'MAC Importer Demo', 
        'MAC Importer Demo',
        'edit_theme_options', 
        'bk-theme-welcome', 
        'bk_welcome_template', 
        'dashicons-admin-site', 
        4 
    );
}
add_action('admin_menu', 'bk_theme_welcome');

// Register AJAX APIs
MAC_Importer_AJAX_APIs::register_endpoints();

// Register all API endpoints
add_action('rest_api_init', function() {
    MAC_Importer_Export_API::register_endpoints();
    MAC_Importer_Import_API::register_endpoints();
    MAC_Importer_Utility_APIs::register_endpoints();
});
