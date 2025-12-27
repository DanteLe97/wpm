<?php
/** update plugin */
// URL để lấy thông tin phiên bản
$github_token = !empty(get_option('mac_menu_github_key')) ? get_option('mac_menu_github_key') : "" ; 
if (!defined('UPDATE_CHECK_URL')) {
    define('UPDATE_CHECK_URL', 'https://api.github.com/repos/DanteLe97/MAC_MENU/contents/version.json');
}
if (!defined('GITHUB_TOKEN')) {
    define('GITHUB_TOKEN', $github_token);
}

$keyDomain = !empty(get_option('mac_domain_valid_key')) ? get_option('mac_domain_valid_key') : "0" ;
// Function kvp_handle_check_request() đã được chuyển sang MAC Core
// if(!empty($keyDomain)) {
//     kvp_handle_check_request($keyDomain);
// }
$response = wp_remote_get(UPDATE_CHECK_URL, array(
    'headers' => array(
        'Authorization' => 'token ' . GITHUB_TOKEN,
        'User-Agent' => 'MAC Menu',
    ),
));
global $latest_version ,$download_url,$response;
if (!is_wp_error($response)) {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
}
if (isset($data['content'])) {
    $version_data = base64_decode($data['content']);
    $version_info = json_decode($version_data, true);
    $latest_version = $version_info['version'];
    $download_url = $version_info['download_url'];
}
// Kiểm tra cập nhật
function check_update() {
    global $latest_version ,$download_url;
    if(empty($latest_version) || empty($download_url)){
        return;
    }
    if ( is_admin() ) {
        if( ! function_exists('get_plugin_data') ){
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        $plugin_file = WP_PLUGIN_DIR . '/mac-menu/mac-menu.php';
        $plugin_data = get_plugin_data( $plugin_file );
    }
    if(isset($plugin_data['Version'])){
        $current_version = $plugin_data['Version'];
    }else{
        $current_version = '1.3.1';
    }
    $required_version = '1.2.0';
    if (version_compare($latest_version, $required_version, '>=')) {
        
        if (version_compare($current_version, $latest_version, '<')) {
            return true;
        }
        else {
            return false;
        }
    }else {
        return false;
    }
}
function check_github_token() {
    $response = wp_remote_get('https://api.github.com/user', [
        'headers' => [
            'Authorization' => 'token ' . GITHUB_TOKEN,
            'User-Agent' => 'WordPress'
        ]
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);
    
    if (isset($data->login)) {
        return 'Người dùng hiện tại là ' . esc_html($data->login);
    } else {
        return 'Không thể lấy thông tin người dùng.';
    }
    return isset($data->login);
}
   
if(check_update() == true ) {
    add_filter('plugin_row_meta', 'my_plugin_row_meta', 10, 2);
}
function my_plugin_row_meta($links, $file) {
    if ($file == 'mac-menu/mac-menu.php') {
        global $latest_version, $download_url;
        $checkGT = check_github_token();
        if(empty($latest_version) || empty($download_url)){
            return;
        }
        $statusDomain = !empty(get_option('mac_domain_valid_status')) ? get_option('mac_domain_valid_status') : "0" ;
        if($statusDomain !='activate')
        { 
            $buttonUpdate = '';
        }else {
            $buttonUpdate = ' <a href="plugins.php?update_mac=mac-menu">Update Now</a>';
        }
        $update_notice = '<p class="update-message notice inline notice-warning notice-alt">There is a new version <strong>' .$latest_version. '</strong> for plugin. '.$buttonUpdate.'</p>';
        array_unshift($links, $update_notice);
    }
    return $links;
}

//download_and_replace_plugin_files();

function fetch_and_enqueue_github_style() {
    $github_repo_owner = 'DanteLe97';
    $github_repo_name = 'MAC_MENU';
    $file_path = 'mac-menu/admin/css/admin-style.css';
    $github_api_url = "https://api.github.com/repos/{$github_repo_owner}/{$github_repo_name}/contents/{$file_path}";
    $response = wp_remote_get($github_api_url, array(
        'headers' => array(
            'Authorization' => 'token ' . GITHUB_TOKEN,
            'User-Agent' => 'WordPress Plugin'
        )
    ));

    if (is_wp_error($response)) {
        return;
    }

    $data = wp_remote_retrieve_body($response);
    $file_info = json_decode($data, true);

    if (isset($file_info['content'])) {
        $css_content = base64_decode($file_info['content']);
        $upload_dir = wp_upload_dir();
        $css_file_path = $upload_dir['basedir'] . '/github-admin-style.css';
        file_put_contents($css_file_path, $css_content);
        $css_file_url = $upload_dir['baseurl'] . '/github-admin-style.css';
        wp_enqueue_style('github-admin-style', $css_file_url, array(), null);
    }
}
//add_action('admin_enqueue_scripts', 'fetch_and_enqueue_github_style');

function list_files_in_directory($directory) {
    $github_api_url = 'https://api.github.com/repos/DanteLe97/MAC_MENU/contents/' . $directory;
    $access_token = GITHUB_TOKEN;

    $response = wp_remote_get($github_api_url, array(
        'headers' => array(
            'Authorization' => 'token ' . $access_token,
            'User-Agent' => 'WordPress Plugin'
        )
    ));

    if (is_wp_error($response)) {
        return array();
    }

    $data = wp_remote_retrieve_body($response);
    return json_decode($data, true);
}
function download_file_content($file_url, $access_token) {
    $response = wp_remote_get($file_url, array(
        'headers' => array(
            'Authorization' => 'token ' . $access_token,
            'User-Agent' => 'WordPress Plugin'
        )
    ));

    if (is_wp_error($response)) {
        return '';
    }

    $data = wp_remote_retrieve_body($response);
    $file_info = json_decode($data, true);

    if (isset($file_info['content'])) {
        return base64_decode($file_info['content']);
    }

    return '';
}

function download_and_replace_plugin_files() {
    $directory = 'mac-menu';
    $access_token = GITHUB_TOKEN;
    $files = list_files_in_directory($directory);

    $upload_dir = wp_upload_dir();
    $plugin_path = WP_PLUGIN_DIR . '/' . $directory;
    if (file_exists($plugin_path)) {
        delete_directory($plugin_path);
    }
    if (!file_exists($plugin_path)) {
        mkdir($plugin_path, 0755, true);
    }
    foreach ($files as $file) {
        if ($file['type'] == 'file') {
            $file_content = download_file_content($file['url'], $access_token);
            $file_path = $plugin_path . '/' . $file['name'];
            file_put_contents($file_path, $file_content);
        } elseif ($file['type'] == 'dir') {
            download_sub_directory_files($directory . '/' . $file['name'], $access_token);
        }
    }
}

function delete_directory($dir) {
    if (is_dir($dir)) {
        $objects = array_diff(scandir($dir), array('.', '..'));
        foreach ($objects as $object) {
            $file = $dir . '/' . $object;
            (is_dir($file)) ? delete_directory($file) : unlink($file);
        }
        rmdir($dir);
    }
}

function download_sub_directory_files($sub_directory, $access_token) {
    $files = list_files_in_directory($sub_directory);
    $plugin_path = WP_PLUGIN_DIR . '/' . $sub_directory;
    if (!file_exists($plugin_path)) {
        mkdir($plugin_path, 0755, true);
    }

    foreach ($files as $file) {
        if ($file['type'] == 'file') {
            $file_content = download_file_content($file['url'], $access_token);
            $file_path = $plugin_path . '/' . $file['name'];
            file_put_contents($file_path, $file_content);
        } elseif ($file['type'] == 'dir') {
            download_sub_directory_files($sub_directory . '/' . $file['name'], $access_token);
        }
    }
}
//download_mac_menu_files();
if( isset( $_GET['update_mac'] ) && $_GET['update_mac'] == 'mac-menu' ){
    download_and_replace_plugin_files();
    mac_redirect('plugins.php');
    exit();
}