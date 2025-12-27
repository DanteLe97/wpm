<?php
/**
 * Plugin Name: MAC Social Feed
 * Plugin URI: https://macusaone.com
 * Description: Tổng hợp feed từ Instagram (Basic Display), Facebook Page và Google My Business (Places). Kèm trang cấu hình, nút kiểm tra kết nối, cron refresh token, and shortcode/widget.
 * Version: 1.0.0
 * Author: MAC USA One
 * Author URI: https://macusaone.com
 * Text Domain: mac-social-feed
 */


if (!defined('ABSPATH')) exit;

class SFA {
    const VERSION = '0.2';
    const OPT_KEY = 'sfa_settings';
    const FEEDS_OPT_KEY = 'sfa_feeds_data'; // Lưu feeds dưới dạng JSON

    public static function init(){
        // Không cần register_post_type nữa vì lưu vào options
        // add_action('init', [__CLASS__, 'register_post_type']);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'frontend_assets']);
        add_shortcode('social_feed', [__CLASS__, 'shortcode_social_feed']);
        add_action('wp_ajax_sfa_check_connection', [__CLASS__, 'ajax_check_connection']);
        add_action('wp_ajax_sfa_manual_fetch', [__CLASS__, 'ajax_manual_fetch']);
        add_action('admin_init', [__CLASS__, 'handle_oauth_callback']);
        add_action('sfa_daily_tasks', [__CLASS__, 'daily_tasks']);

        // ensure daily schedule exists
        add_filter('cron_schedules', function($s){ if(!isset($s['every_five_minutes'])) $s['every_five_minutes']=['interval'=>300,'display'=>'Every 5 Minutes']; return $s; });
        if(!wp_next_scheduled('sfa_daily_tasks')) wp_schedule_event(time()+60, 'daily', 'sfa_daily_tasks');
    }

    /* ------------------ Storage helpers ------------------ */
    public static function get_settings(){ return get_option(self::OPT_KEY, []); }
    public static function update_setting($key, $val){ $all = self::get_settings(); $all[$key]=$val; update_option(self::OPT_KEY,$all); }
    
    /* ------------------ Feeds storage (JSON in options) ------------------ */
    public static function get_feeds(){ 
        $feeds = get_option(self::FEEDS_OPT_KEY, '[]'); 
        $decoded = json_decode($feeds, true); 
        return is_array($decoded) ? $decoded : []; 
    }
    public static function save_feeds($feeds, $max_items = 500){ 
        // Giới hạn số lượng feeds để tránh options quá lớn
        if(count($feeds) > $max_items){
            // Sắp xếp theo thời gian (mới nhất trước) và chỉ giữ $max_items items
            usort($feeds, function($a, $b){
                $time_a = isset($a['created_time']) ? strtotime($a['created_time']) : 0;
                $time_b = isset($b['created_time']) ? strtotime($b['created_time']) : 0;
                return $time_b - $time_a;
            });
            $feeds = array_slice($feeds, 0, $max_items);
        }
        update_option(self::FEEDS_OPT_KEY, wp_json_encode($feeds)); 
    }

    /* ------------------ Admin ------------------ */
    public static function admin_menu(){ add_menu_page('Social Feed', 'Social Feed', 'manage_options', 'sfa_settings', [__CLASS__,'render_settings'], 'dashicons-share', 58); }

    public static function admin_assets($hook){ 
        if(strpos($hook,'sfa_settings')===false) return; 
        wp_enqueue_style('sfa-admin', plugin_dir_url(__FILE__).'assets/admin.css', [], self::VERSION); 
        wp_enqueue_script('sfa-admin-js', plugin_dir_url(__FILE__).'assets/admin.js', ['jquery'], self::VERSION, true); 
        $settings = self::get_settings();
        $instagram_url = '';
        $facebook_url = '';
        if(!empty($settings['instagram']['instagram_app_id'])){
            $inst = new SFA_Instagram($settings);
            $instagram_url = $inst->get_connect_url();
        }
        if(!empty($settings['facebook']['facebook_app_id'])){
            $fb = new SFA_Facebook($settings);
            $facebook_url = $fb->get_connect_url();
        }
        wp_localize_script('sfa-admin-js','sfa_ajax',[
            'ajax_url'=>admin_url('admin-ajax.php'),
            'nonce'=>wp_create_nonce('sfa_nonce'),
            'instagram_url'=>$instagram_url,
            'facebook_url'=>$facebook_url
        ]); 
    }
    
    public static function frontend_assets(){
        wp_enqueue_style('sfa-frontend', plugin_dir_url(__FILE__).'assets/frontend.css', [], self::VERSION);
    }

    public static function register_settings(){ register_setting('sfa_group', self::OPT_KEY);
        add_settings_section('sfa_section_instagram','Instagram Settings',[__CLASS__,'section_instagram_desc'],'sfa_instagram');
        add_settings_field('sfa_instagram_app_id','Instagram App ID', [__CLASS__,'field_text'], 'sfa_instagram','sfa_section_instagram',['label_for'=>'instagram_app_id','option_key'=>'instagram']);
        add_settings_field('sfa_instagram_app_secret','Instagram App Secret', [__CLASS__,'field_text_secret'], 'sfa_instagram','sfa_section_instagram',['label_for'=>'instagram_app_secret','option_key'=>'instagram']);
        add_settings_field('sfa_instagram_token','Instagram Access Token (long-lived)', [__CLASS__,'field_text'], 'sfa_instagram','sfa_section_instagram',['label_for'=>'instagram_access_token','option_key'=>'instagram']);

        add_settings_section('sfa_section_facebook','Facebook Settings',[__CLASS__,'section_facebook_desc'],'sfa_facebook');
        add_settings_field('sfa_facebook_app_id','Facebook App ID', [__CLASS__,'field_text'], 'sfa_facebook','sfa_section_facebook',['label_for'=>'facebook_app_id','option_key'=>'facebook']);
        add_settings_field('sfa_facebook_app_secret','Facebook App Secret', [__CLASS__,'field_text_secret'], 'sfa_facebook','sfa_section_facebook',['label_for'=>'facebook_app_secret','option_key'=>'facebook']);
        add_settings_field('sfa_facebook_page_id','Facebook Page ID', [__CLASS__,'field_text'], 'sfa_facebook','sfa_section_facebook',['label_for'=>'facebook_page_id','option_key'=>'facebook']);
        add_settings_field('sfa_facebook_page_token','Facebook Page Access Token', [__CLASS__,'field_text'], 'sfa_facebook','sfa_section_facebook',['label_for'=>'facebook_page_token','option_key'=>'facebook']);

        add_settings_section('sfa_section_gmb','Google My Business / Places',[__CLASS__,'section_gmb_desc'],'sfa_gmb');
        add_settings_field('sfa_gmb_api_key','Google API Key', [__CLASS__,'field_text'], 'sfa_gmb','sfa_section_gmb',['label_for'=>'gmb_api_key','option_key'=>'gmb']);
        add_settings_field('sfa_gmb_place_id','Place ID / Location ID', [__CLASS__,'field_text'], 'sfa_gmb','sfa_section_gmb',['label_for'=>'gmb_place_id','option_key'=>'gmb']);

        add_settings_section('sfa_section_general','General',null,'sfa_general');
        add_settings_field('sfa_general_item_limit','Items to fetch per source (default 20)', [__CLASS__,'field_text'], 'sfa_general','sfa_section_general',['label_for'=>'general_limit','option_key'=>'general']);
    }

    public static function section_instagram_desc(){
        echo '<div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">';
        echo '<h3 style="margin-top: 0;">📱 Hướng dẫn lấy Instagram App ID & Secret:</h3>';
        echo '<ol style="margin: 10px 0; padding-left: 20px;">';
        echo '<li>Truy cập <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a> và đăng nhập</li>';
        echo '<li>Vào <strong>My Apps</strong> → <strong>Create App</strong> (hoặc chọn app có sẵn)</li>';
        echo '<li>Chọn loại app: <strong>Consumer</strong> hoặc <strong>Business</strong></li>';
        echo '<li>Thêm sản phẩm <strong>Instagram Basic Display</strong></li>';
        echo '<li>Vào <strong>Settings</strong> → <strong>Basic</strong> để xem <strong>App ID</strong> và <strong>App Secret</strong></li>';
        echo '<li>Thêm <strong>Valid OAuth Redirect URIs</strong>: <code>'.admin_url('admin.php?page=sfa_settings').'</code></li>';
        echo '<li><strong>Cách 1 (Khuyến nghị):</strong> Điền App ID & Secret, sau đó click button <strong>"Connect Instagram"</strong> bên dưới để tự động lấy token</li>';
        echo '<li><strong>Cách 2:</strong> Lấy token thủ công từ <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a> và điền vào ô "Access Token"</li>';
        echo '</ol>';
        echo '</div>';
    }

    public static function section_facebook_desc(){
        echo '<div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #1877f2; margin-bottom: 20px;">';
        echo '<h3 style="margin-top: 0;">📘 Hướng dẫn lấy Facebook App ID, Secret & Page Token:</h3>';
        echo '<ol style="margin: 10px 0; padding-left: 20px;">';
        echo '<li>Truy cập <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a> và đăng nhập</li>';
        echo '<li>Vào <strong>My Apps</strong> → <strong>Create App</strong> (hoặc chọn app có sẵn)</li>';
        echo '<li>Chọn loại app: <strong>Business</strong></li>';
        echo '<li>Thêm sản phẩm <strong>Facebook Login</strong> hoặc <strong>Pages</strong></li>';
        echo '<li>Vào <strong>Settings</strong> → <strong>Basic</strong> để xem <strong>App ID</strong> và <strong>App Secret</strong></li>';
        echo '<li>Thêm <strong>Valid OAuth Redirect URIs</strong>: <code>'.admin_url('admin.php?page=sfa_settings').'</code></li>';
        echo '<li><strong>Lấy Page ID:</strong> Vào trang Facebook của bạn → <strong>About</strong> → Copy <strong>Page ID</strong> (hoặc dùng <a href="https://www.facebook.com/help/1503421039731588" target="_blank">công cụ này</a>)</li>';
        echo '<li><strong>Cách 1 (Khuyến nghị):</strong> Điền App ID & Secret, sau đó click button <strong>"Connect Facebook Page"</strong> bên dưới để tự động lấy Page Token</li>';
        echo '<li><strong>Cách 2:</strong> Lấy Page Token thủ công từ <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a></li>';
        echo '</ol>';
        echo '</div>';
    }

    public static function section_gmb_desc(){
        echo '<div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #34a853; margin-bottom: 20px;">';
        echo '<h3 style="margin-top: 0;">🗺️ Hướng dẫn lấy Google API Key & Place ID:</h3>';
        echo '<ol style="margin: 10px 0; padding-left: 20px;">';
        echo '<li><strong>Lấy Google API Key:</strong></li>';
        echo '<ul style="margin: 5px 0; padding-left: 20px;">';
        echo '<li>Truy cập <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>';
        echo '<li>Tạo project mới hoặc chọn project có sẵn</li>';
        echo '<li>Vào <strong>APIs & Services</strong> → <strong>Library</strong></li>';
        echo '<li>Tìm và bật <strong>Places API</strong> (hoặc <strong>Places API (New)</strong>)</li>';
        echo '<li>Vào <strong>APIs & Services</strong> → <strong>Credentials</strong></li>';
        echo '<li>Click <strong>Create Credentials</strong> → <strong>API Key</strong></li>';
        echo '<li>Copy API Key và dán vào ô bên dưới</li>';
        echo '</ul>';
        echo '<li><strong>Lấy Place ID:</strong></li>';
        echo '<ul style="margin: 5px 0; padding-left: 20px;">';
        echo '<li>Truy cập <a href="https://www.google.com/maps" target="_blank">Google Maps</a></li>';
        echo '<li>Tìm địa điểm của bạn (tên doanh nghiệp, địa chỉ)</li>';
        echo '<li>Click vào địa điểm trên bản đồ</li>';
        echo '<li>Trong URL, bạn sẽ thấy <code>!1s...</code> hoặc <code>place_id=...</code></li>';
        echo '<li>Hoặc dùng <a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank">Place ID Finder</a></li>';
        echo '<li>Copy Place ID và dán vào ô bên dưới</li>';
        echo '</ul>';
        echo '</ol>';
        echo '</div>';
    }

    public static function field_text($args){ $opt = self::get_settings(); $group=$args['option_key']; $name = $args['label_for']; $val = isset($opt[$group][$name])?$opt[$group][$name]:''; printf('<input type="text" id="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text"/>', esc_attr($name), esc_attr(self::OPT_KEY), esc_attr($group.'['.$name.']'), esc_attr($val)); }
    public static function field_text_secret($args){ $opt=self::get_settings(); $group=$args['option_key']; $name=$args['label_for']; $val = isset($opt[$group][$name])?$opt[$group][$name]:''; printf('<input type="password" id="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text" autocomplete="new-password"/>', esc_attr($name), esc_attr(self::OPT_KEY), esc_attr($group.'['.$name.']'), esc_attr($val)); }

    public static function render_settings(){ 
        if(!current_user_can('manage_options')) wp_die('Not allowed'); 
        // Check for OAuth success/error messages
        $oauth_message = isset($_GET['sfa_oauth']) ? sanitize_text_field($_GET['sfa_oauth']) : '';
        ?>
        <div class="wrap">
            <h1>Social Feed Aggregator</h1>
            <?php if($oauth_message === 'success'): ?>
                <div class="notice notice-success is-dismissible"><p>OAuth connection successful! Token saved.</p></div>
            <?php elseif($oauth_message === 'error'): ?>
                <div class="notice notice-error is-dismissible"><p>OAuth connection failed. Please try again.</p></div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('sfa_group'); 
                do_settings_sections('sfa_instagram'); 
                do_settings_sections('sfa_facebook'); 
                do_settings_sections('sfa_gmb'); 
                do_settings_sections('sfa_general'); 
                submit_button(); ?>
            </form>
            
            <hr>
            <h2>Quick Checks</h2>
            <p>Use the buttons to verify each platform is connected correctly.</p>
            <button class="button button-primary sfa-check" data-source="instagram">Check Instagram</button> 
            <span id="sfa-status-instagram" class="sfa-status"></span><br><br>
            <button class="button button-primary sfa-check" data-source="facebook">Check Facebook</button> 
            <span id="sfa-status-facebook" class="sfa-status"></span><br><br>
            <button class="button button-primary sfa-check" data-source="gmb">Check Google My Business</button> 
            <span id="sfa-status-gmb" class="sfa-status"></span>
            
            <hr>
            <h2>OAuth Connection (Instagram / Facebook)</h2>
            <p>Click the buttons below to connect your accounts via OAuth. Make sure you have filled in App ID and App Secret first.</p>
            <button id="sfa-instagram-connect" class="button button-secondary">Connect Instagram</button>
            <button id="sfa-facebook-connect" class="button button-secondary">Connect Facebook Page</button>
            
            <hr>
            <h2>Auto Fetch & Manual Fetch</h2>
            <p><strong>Auto Fetch:</strong> Plugin tự động fetch posts từ tất cả nguồn đã kết nối mỗi ngày (qua WordPress Cron).</p>
            <p><strong>Manual Fetch:</strong> Click button bên dưới để fetch ngay lập tức mà không cần đợi cron job.</p>
            <button id="sfa-manual-fetch" class="button button-secondary">Fetch Now (Manual)</button>
            <span id="sfa-fetch-status" class="sfa-status"></span>
        </div>
    <?php }
    
    public static function handle_oauth_callback(){
        if(!isset($_GET['page']) || $_GET['page'] !== 'sfa_settings') return;
        if(!current_user_can('manage_options')) return;
        
        // Handle Instagram OAuth callback
        if(isset($_GET['code']) && isset($_GET['sfa_source']) && $_GET['sfa_source'] === 'instagram'){
            $code = sanitize_text_field($_GET['code']);
            $settings = self::get_settings();
            $inst = new SFA_Instagram($settings);
            $result = $inst->exchange_code_for_token($code);
            if($result['ok']){
                wp_redirect(admin_url('admin.php?page=sfa_settings&sfa_oauth=success'));
            } else {
                wp_redirect(admin_url('admin.php?page=sfa_settings&sfa_oauth=error'));
            }
            exit;
        }
        
        // Handle Facebook OAuth callback
        if(isset($_GET['code']) && isset($_GET['sfa_source']) && $_GET['sfa_source'] === 'facebook'){
            $code = sanitize_text_field($_GET['code']);
            $settings = self::get_settings();
            $fb = new SFA_Facebook($settings);
            $result = $fb->exchange_code_for_token($code);
            if($result['ok']){
                wp_redirect(admin_url('admin.php?page=sfa_settings&sfa_oauth=success'));
            } else {
                wp_redirect(admin_url('admin.php?page=sfa_settings&sfa_oauth=error'));
            }
            exit;
        }
    }

    /* ------------------ Shortcode ------------------ */
    public static function shortcode_social_feed($atts){ 
        $atts = shortcode_atts(['source'=>'all','limit'=>5,'layout'=>'grid'], $atts, 'social_feed'); 
        $feeds = self::get_feeds(); 
        $limit = intval($atts['limit']); 
        $source = sanitize_text_field($atts['source']); 
        
        // Filter by source if needed
        if($source !== 'all'){
            $feeds = array_filter($feeds, function($feed) use ($source){
                return isset($feed['origin']) && $feed['origin'] === $source;
            });
        }
        
        // Sort by created_time (newest first)
        usort($feeds, function($a, $b){
            $time_a = isset($a['created_time']) ? strtotime($a['created_time']) : 0;
            $time_b = isset($b['created_time']) ? strtotime($b['created_time']) : 0;
            return $time_b - $time_a;
        });
        
        // Limit
        $feeds = array_slice($feeds, 0, $limit);
        
        ob_start(); 
        echo '<div class="sfa-grid">'; 
        foreach($feeds as $feed){
            $media = isset($feed['media']) && is_array($feed['media']) ? $feed['media'] : [];
            $permalink = isset($feed['permalink']) ? $feed['permalink'] : '#';
            $title = isset($feed['message']) ? wp_trim_words($feed['message'], 12, '...') : 'No title';
            $excerpt = isset($feed['message']) ? wp_trim_words($feed['message'], 20, '...') : '';
            ?>
            <article class="sfa-item">
                <a href="<?php echo esc_url($permalink); ?>" target="_blank">
                    <?php if(!empty($media[0])): ?>
                        <img src="<?php echo esc_url($media[0]); ?>" alt="" style="max-width:100%"/>
                    <?php endif; ?>
                    <h4><?php echo esc_html($title); ?></h4>
                    <div class="sfa-excerpt"><?php echo esc_html($excerpt); ?></div>
                </a>
            </article>
            <?php
        }
        echo '</div>'; 
        return ob_get_clean(); 
    }

    /* ------------------ AJAX Check ------------------ */
    public static function ajax_check_connection(){ 
        check_ajax_referer('sfa_nonce'); 
        if(!current_user_can('manage_options')) wp_send_json_error('no_permission'); 
        $src = isset($_POST['source'])?sanitize_text_field($_POST['source']):''; 
        $settings=self::get_settings(); 
        switch($src){ 
            case 'instagram': 
                $inst=new SFA_Instagram($settings); 
                $res=$inst->check_connection(); 
                break; 
            case 'facebook': 
                $fb=new SFA_Facebook($settings); 
                $res=$fb->check_connection(); 
                break; 
            case 'gmb': 
                $gmb=new SFA_GMB($settings); 
                $res=$gmb->check_connection(); 
                break; 
            default: 
                wp_send_json_error(['message'=>'unknown_source']); 
        }
        if(isset($res['ok']) && $res['ok']) wp_send_json_success($res); 
        wp_send_json_error($res);
    }
    
    public static function ajax_manual_fetch(){
        check_ajax_referer('sfa_nonce');
        if(!current_user_can('manage_options')) wp_send_json_error('no_permission');
        $settings = self::get_settings();
        $fetcher = new SFA_Fetcher($settings);
        $fetcher->run_all();
        wp_send_json_success(['message'=>'Fetch completed']);
    }

    /* ------------------ Daily Tasks ------------------ */
    public static function daily_tasks(){ $settings=self::get_settings(); // refresh instagram
        if(!empty($settings['instagram']['instagram_access_token']) && !empty($settings['instagram']['instagram_expires_at'])){
            if(time() > ($settings['instagram']['instagram_expires_at'] - DAY_IN_SECONDS*7)){
                $inst = new SFA_Instagram($settings);
                $inst->refresh_long_lived_token();
            }
        }
        // validate facebook token
        if(!empty($settings['facebook']['facebook_page_token'])){
            $fb = new SFA_Facebook($settings);
            $fb->validate_page_token();
        }
        // fetch posts
        $fetcher = new SFA_Fetcher($settings);
        $fetcher->run_all();
    }
}

/* ======================== Instagram helper ======================== */
class SFA_Instagram {
    private $settings;
    public function __construct($settings=[]){ $this->settings = isset($settings['instagram']) ? $settings['instagram'] : []; }
    public function get_connect_url(){ 
        $app_id = $this->settings['instagram_app_id'] ?? ''; 
        $redirect = admin_url('admin.php?page=sfa_settings&sfa_source=instagram'); 
        $url = "https://api.instagram.com/oauth/authorize?client_id=".urlencode($app_id) ."&redirect_uri=".urlencode($redirect) ."&scope=user_profile,user_media&response_type=code"; 
        return $url; 
    }
    public function exchange_code_for_token($code){ // exchange code to short-lived token
        $app_id = $this->settings['instagram_app_id'] ?? ''; $app_secret = $this->settings['instagram_app_secret'] ?? ''; $redirect = admin_url('admin.php?page=sfa_settings'); $url = 'https://api.instagram.com/oauth/access_token'; $body = ['client_id'=>$app_id,'client_secret'=>$app_secret,'grant_type'=>'authorization_code','redirect_uri'=>$redirect,'code'=>$code]; $r = wp_remote_post($url,['body'=>$body,'timeout'=>20]); if(is_wp_error($r)) return ['ok'=>false,'error'=>$r->get_message()]; $b=json_decode(wp_remote_retrieve_body($r),true); if(isset($b['access_token'])){
            // exchange short token to long
            $short = $b['access_token']; $ex = wp_remote_get('https://graph.instagram.com/access_token?grant_type=ig_exchange_token&client_secret='.urlencode($app_secret).'&access_token='.urlencode($short)); if(is_wp_error($ex)) return ['ok'=>false,'error'=>$ex->get_message()]; $bb=json_decode(wp_remote_retrieve_body($ex),true); if(isset($bb['access_token'])){ $inst = SFA::get_settings(); $inst['instagram']['instagram_access_token']=$bb['access_token']; $inst['instagram']['instagram_expires_at']=time()+intval($bb['expires_in']); update_option(SFA::OPT_KEY,$inst); return ['ok'=>true]; }
        }
        return ['ok'=>false,'body'=>$b]; }
    public function refresh_long_lived_token(){ $settings = SFA::get_settings(); $token = $settings['instagram']['instagram_access_token'] ?? ''; if(!$token) return ['ok'=>false,'message'=>'no_token']; $url = 'https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token='.urlencode($token); $r = wp_remote_get($url,['timeout'=>20]); if(is_wp_error($r)) return ['ok'=>false,'error'=>$r->get_message()]; $b=json_decode(wp_remote_retrieve_body($r),true); if(isset($b['access_token'])){ $settings = SFA::get_settings(); $settings['instagram']['instagram_access_token']=$b['access_token']; $settings['instagram']['instagram_expires_at']=time()+intval($b['expires_in']); update_option(SFA::OPT_KEY,$settings); return ['ok'=>true]; } return ['ok'=>false,'body'=>$b]; }
    public function check_connection(){ $settings = SFA::get_settings(); $token = $settings['instagram']['instagram_access_token'] ?? ''; if(!$token) return ['ok'=>false,'message'=>'no_token']; $url = 'https://graph.instagram.com/me?fields=id,username&access_token='.urlencode($token); $r=wp_remote_get($url,['timeout'=>10]); if(is_wp_error($r)) return ['ok'=>false,'error'=>$r->get_message()]; $b=json_decode(wp_remote_retrieve_body($r),true); if(isset($b['id'])) return ['ok'=>true,'id'=>$b['id'],'username'=>$b['username']]; return ['ok'=>false,'body'=>$b]; }
}

/* ======================== Facebook helper ======================== */
class SFA_Facebook {
    private $settings;
    public function __construct($settings=[]){ $this->settings = isset($settings['facebook']) ? $settings['facebook'] : []; }
    public function get_connect_url(){ 
        $app_id = $this->settings['facebook_app_id'] ?? ''; 
        $redirect = admin_url('admin.php?page=sfa_settings&sfa_source=facebook'); 
        $scopes='pages_read_engagement,pages_read_user_content'; 
        $url = 'https://www.facebook.com/v17.0/dialog/oauth?client_id='.urlencode($app_id).'&redirect_uri='.urlencode($redirect).'&scope='.$scopes.'&response_type=code'; 
        return $url; 
    }
    
    public function exchange_code_for_token($code){
        $app_id = $this->settings['facebook_app_id'] ?? '';
        $app_secret = $this->settings['facebook_app_secret'] ?? '';
        $redirect = admin_url('admin.php?page=sfa_settings&sfa_source=facebook');
        
        // Exchange code for access token
        $url = 'https://graph.facebook.com/v17.0/oauth/access_token';
        $body = [
            'client_id' => $app_id,
            'client_secret' => $app_secret,
            'redirect_uri' => $redirect,
            'code' => $code
        ];
        
        $r = wp_remote_post($url, ['body' => $body, 'timeout' => 20]);
        if(is_wp_error($r)) return ['ok' => false, 'error' => $r->get_error_message()];
        
        $b = json_decode(wp_remote_retrieve_body($r), true);
        if(!isset($b['access_token'])) return ['ok' => false, 'body' => $b];
        
        $user_token = $b['access_token'];
        
        // Get user's pages
        $pages_url = 'https://graph.facebook.com/v17.0/me/accounts?access_token='.urlencode($user_token);
        $pages_r = wp_remote_get($pages_url, ['timeout' => 20]);
        if(is_wp_error($pages_r)) return ['ok' => false, 'error' => $pages_r->get_error_message()];
        
        $pages_b = json_decode(wp_remote_retrieve_body($pages_r), true);
        if(empty($pages_b['data'])) return ['ok' => false, 'message' => 'No pages found'];
        
        // Use first page (or you can let user select)
        $first_page = $pages_b['data'][0];
        $page_token = $first_page['access_token'] ?? '';
        $page_id = $first_page['id'] ?? '';
        
        if($page_token && $page_id){
            $settings = SFA::get_settings();
            $settings['facebook']['facebook_page_token'] = $page_token;
            $settings['facebook']['facebook_page_id'] = $page_id;
            update_option(SFA::OPT_KEY, $settings);
            return ['ok' => true, 'page_name' => $first_page['name'] ?? ''];
        }
        
        return ['ok' => false, 'message' => 'Failed to get page token'];
    }
    public function check_connection(){ $settings = SFA::get_settings(); $page_token = $settings['facebook']['facebook_page_token'] ?? ''; $page_id = $settings['facebook']['facebook_page_id'] ?? ''; if(!$page_token || !$page_id) return ['ok'=>false,'message'=>'no_token_or_pageid']; $url = 'https://graph.facebook.com/v17.0/'.urlencode($page_id).'?fields=name&access_token='.urlencode($page_token); $r=wp_remote_get($url,['timeout'=>10]); if(is_wp_error($r)) return ['ok'=>false,'error'=>$r->get_message()]; $b=json_decode(wp_remote_retrieve_body($r),true); if(isset($b['name'])) return ['ok'=>true,'name'=>$b['name']]; return ['ok'=>false,'body'=>$b]; }
    public function validate_page_token(){ // optional: call debug_token using app access token
        $settings = SFA::get_settings(); $token = $settings['facebook']['facebook_page_token'] ?? ''; if(!$token) return false; $app_id = $settings['facebook']['facebook_app_id'] ?? ''; $app_secret = $settings['facebook']['facebook_app_secret'] ?? ''; if(!$app_id || !$app_secret) return false; $app_token = $app_id . '|' . $app_secret; $url = 'https://graph.facebook.com/debug_token?input_token='.urlencode($token).'&access_token='.urlencode($app_token); $r=wp_remote_get($url,['timeout'=>10]); if(is_wp_error($r)) return false; $b=json_decode(wp_remote_retrieve_body($r),true); return $b; }
}

/* ======================== GMB / Places helper ======================== */
class SFA_GMB {
    private $settings;
    public function __construct($settings=[]){ $this->settings = isset($settings['gmb']) ? $settings['gmb'] : []; }
    public function check_connection(){ $settings = SFA::get_settings(); $key = $settings['gmb']['gmb_api_key'] ?? ''; $place = $settings['gmb']['gmb_place_id'] ?? ''; if(!$key || !$place) return ['ok'=>false,'message'=>'no_key_or_placeid']; $url = 'https://maps.googleapis.com/maps/api/place/details/json?place_id='.urlencode($place).'&key='.urlencode($key); $r=wp_remote_get($url,['timeout'=>10]); if(is_wp_error($r)) return ['ok'=>false,'error'=>$r->get_message()]; $b=json_decode(wp_remote_retrieve_body($r),true); if(isset($b['result'])) return ['ok'=>true,'name'=>$b['result']['name']]; return ['ok'=>false,'body'=>$b]; }
}

/* ======================== Fetcher ======================== */
class SFA_Fetcher {
    private $settings;
    public function __construct($settings=[]){ $this->settings = $settings ?: SFA::get_settings(); }
    public function run_all(){ $this->fetch_instagram(); $this->fetch_facebook(); $this->fetch_gmb(); }

    private function fetch_instagram(){ $s = $this->settings['instagram'] ?? []; $token = $s['instagram_access_token'] ?? ''; if(!$token) return; $limit = intval($this->settings['general']['general_limit'] ?? 20); $url = 'https://graph.instagram.com/me/media?fields=id,caption,media_url,permalink,timestamp,media_type&access_token='.urlencode($token).'&limit='.intval($limit);
        $r=wp_remote_get($url,['timeout'=>20]); if(is_wp_error($r)) return; $b=json_decode(wp_remote_retrieve_body($r),true); if(empty($b['data'])) return; foreach($b['data'] as $p){ $item=['origin'=>'instagram','origin_id'=>$p['id'],'message'=>$p['caption'] ?? '','media'=> isset($p['media_url'])?[$p['media_url']]:[],'permalink'=>$p['permalink'] ?? '','created_time'=>$p['timestamp'] ?? current_time('mysql'),'raw'=>$p]; $this->upsert_item($item); } }

    private function fetch_facebook(){ $s=$this->settings['facebook'] ?? []; $token = $s['facebook_page_token'] ?? ''; $page = $s['facebook_page_id'] ?? ''; if(!$token || !$page) return; $limit = intval($this->settings['general']['general_limit'] ?? 20); $url = 'https://graph.facebook.com/v17.0/'.urlencode($page).'/posts?fields=message,full_picture,permalink_url,created_time,id&limit='.intval($limit).'&access_token='.urlencode($token);
        $r=wp_remote_get($url,['timeout'=>20]); if(is_wp_error($r)) return; $b=json_decode(wp_remote_retrieve_body($r),true); if(empty($b['data'])) return; foreach($b['data'] as $p){ $media = []; if(!empty($p['full_picture'])) $media[]=$p['full_picture']; $item=['origin'=>'facebook','origin_id'=>$p['id'],'message'=>$p['message'] ?? '','media'=>$media,'permalink'=>$p['permalink_url'] ?? '','created_time'=>$p['created_time'] ?? current_time('mysql'),'raw'=>$p]; $this->upsert_item($item); } }

    private function fetch_gmb(){ $s=$this->settings['gmb'] ?? []; $key = $s['gmb_api_key'] ?? ''; $place = $s['gmb']['gmb_place_id'] ?? ''; if(!$key || !$place) return; $url = 'https://maps.googleapis.com/maps/api/place/details/json?place_id='.urlencode($place).'&fields=name,review,user_ratings_total&key='.urlencode($key); $r=wp_remote_get($url,['timeout'=>20]); if(is_wp_error($r)) return; $b=json_decode(wp_remote_retrieve_body($r),true); if(empty($b['result'])) return; // create a single item capturing summary
        $res = $b['result']; $item=['origin'=>'gmb','origin_id'=>$place,'message'=>'Google Place: '.($res['name'] ?? '').' - Reviews: '.($res['user_ratings_total'] ?? 0),'media'=>[],'permalink'=>'','created_time'=>current_time('mysql'),'raw'=>$res]; $this->upsert_item($item); }

    private function upsert_item($item){ 
        // Lấy feeds hiện tại từ options
        $feeds = SFA::get_feeds();
        
        // Tìm item đã tồn tại (theo origin + origin_id)
        $found_index = false;
        foreach($feeds as $index => $existing_item){
            if(isset($existing_item['origin']) && $existing_item['origin'] === $item['origin'] && 
               isset($existing_item['origin_id']) && $existing_item['origin_id'] === $item['origin_id']){
                $found_index = $index;
                break;
            }
        }
        
        // Chuẩn hóa dữ liệu item
        $normalized_item = [
            'origin' => $item['origin'] ?? '',
            'origin_id' => $item['origin_id'] ?? '',
            'message' => $item['message'] ?? '',
            'media' => isset($item['media']) && is_array($item['media']) ? $item['media'] : [],
            'permalink' => $item['permalink'] ?? '',
            'created_time' => $item['created_time'] ?? current_time('mysql'),
            'raw' => $item['raw'] ?? []
        ];
        
        // Update hoặc insert
        if($found_index !== false){
            $feeds[$found_index] = $normalized_item;
        } else {
            $feeds[] = $normalized_item;
        }
        
        // Lưu lại vào options
        SFA::save_feeds($feeds);
    }
}

/* ======================== Boot ======================== */
SFA::init();

