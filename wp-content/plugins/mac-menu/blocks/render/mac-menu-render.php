<?php
if (!function_exists('mac_menu_elementor_render')) {
    function mac_menu_elementor_render($settings) {
        /**/
        $currentCategory = isset($settings['is_current_category']) ? $settings['is_current_category'] : '';
		$idPage = get_the_ID();
		$namesCategoryInPage = get_post_meta($idPage, '_custom_meta_key', true);
		
		if( $currentCategory === 'on' && $namesCategoryInPage=='null' ) {
			return;
		}
		/**/
        $jsValue = isset($settings['html_js']) ? $settings['html_js'] : '';
        $cssValue = isset($settings['html_css']) ? $settings['html_css'] : '';
        $cssHTML = '<style>';
        $cssHTML .= $cssValue;
        $cssHTML .= '</style>';

        $jsHTML = '<script>';
        $jsHTML .= $jsValue;
        $jsHTML .= '</script>';
        $block_str = '';
        if($cssValue != ''):
        $block_str .= $cssHTML;
        endif;
        
        $categoryNameAlignment = isset($settings['category_head_text_align']) ? $settings['category_head_text_align'] : '';
        if($categoryNameAlignment != '' ){
            $classCategoryNameAlignment = ' category-heading-alignment-'.$categoryNameAlignment;
        }
        $jsLinkMenu = !empty(get_option('mac_menu_js_link')) ? get_option('mac_menu_js_link') : "0";
        $jsLinkMenuClass = '';
        if(!empty($jsLinkMenu)){
            $jsLinkMenuClass = ' mac-menu-services-js-default';
        }
        
        $block_str .= '<div class="mac-menu'.$classCategoryNameAlignment.$jsLinkMenuClass.'">';
        $renderHTML = new Render_Module;
        $block_str .= $renderHTML->render($settings);  
        $block_str .= '</div><!-- mac-menu -->';
        
        $macQRTitle = !empty(get_option('mac_qr_title')) ? get_option('mac_qr_title') : '';
        $macQRCode = !empty(get_option('mac_qr_code')) ? get_option('mac_qr_code') : 0;


        $moduleQRTitle  = isset($settings['mac_qr_code_title']) ? $settings['mac_qr_code_title'] : '';
        $moduleQRCode = isset($settings['mac_qr_code']) && $settings['mac_qr_code']  === 'on' ? 'on' : 'off';

        if(!empty($moduleQRTitle)){
            $macQRTitle = $moduleQRTitle;
        }
        if($moduleQRCode){
            $macQRCode = $moduleQRCode;
        }
        //mac_qr_code
        if($macQRCode == 1 || $macQRCode == 'on'){
            $block_str .= '<div id="mac-qr" class="mac-qrcode-wrap">';
            if( $macQRTitle != '' ){
                $block_str .= '<div class="mac-qrcode__head">';
                $block_str .= '<h3 class="mac-qrcode__heading">'.$macQRTitle.'</h3>';
                $block_str .= '</div><!-- qr-code__head -->';
            }
            $shortcode = '[page_qr_code]';
            if (!empty($settings['pdf_post_id'])) {
                $shortcode = '[page_qr_code post_id="' . intval($settings['pdf_post_id']) . '"]';
            }
            $block_str .= '<div class="mac-qrcode__Content">';
            $block_str .= '<div class="mac-qrcode__shortcode">'.do_shortcode($shortcode).'</div>';
            $block_str .= '<div class="mac-dowpdf__shortcode">'.do_shortcode('[elementor_pdf_button]').'</div>';
            $block_str .= '</div><!-- qr-code__content -->';
            $block_str .= '</div><!-- qr-code -->';
        }
        if($jsValue != ''):
        $block_str .= $jsHTML;
        endif;

        return $block_str;    
    }
    
}  
