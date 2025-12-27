<?php
// Shortcode hiển thị QR code của trang hiện tại
if (!function_exists('display_page_qr_code')) {
    function display_page_qr_code($atts) {
        global $post;
        $atts = shortcode_atts(array(
            'post_id' => 0,
        ), $atts, 'page_qr_code');

        $macQRCode = !empty(get_option('mac_qr_code')) ? get_option('mac_qr_code') : 0;
        if (empty($macQRCode)) {
            return '';
        }

        // Xác định post ID: ưu tiên tham số shortcode, fallback global $post
        $target_post_id = intval($atts['post_id']);
        if ($target_post_id <= 0 && $post && isset($post->ID)) {
            $target_post_id = intval($post->ID);
        }
        if ($target_post_id <= 0) {
            return '';
        }

        $qr_dir = MAC_PATH . 'adds-on/qr-code-pages/qr-images/';
        $qr_url = MAC_URI . 'adds-on/qr-code-pages/qr-images/';
        $file_name = 'qr-' . $target_post_id . '.png';
        $file_path = $qr_dir . $file_name;

        // Tạo thư mục nếu chưa có
        if (!file_exists($qr_dir)) {
            if (!wp_mkdir_p($qr_dir)) {
                return '<span style="color:red">Không thể tạo thư mục QR code.</span>';
            }
        }

        // Load thư viện QR nếu chưa tồn tại QR
        if (!file_exists($file_path)) {
            if (!file_exists(MAC_PATH . 'adds-on/qr-code-pages/assets/phpqrcode/phpqrcode.php')) {
                return '<span style="color:red">Thiếu thư viện QR code.</span>';
            }
            require_once MAC_PATH . 'adds-on/qr-code-pages/assets/phpqrcode/phpqrcode.php';

            $site_url = get_site_url();
            $encrypted_id = qrcode_encrypt_id($target_post_id);
            $qr_data = $site_url . '/qr-redirect/?id=' . $encrypted_id;

            QRcode::png($qr_data, $file_path, QR_ECLEVEL_L, 3);
        }

        $alt_title = get_the_title($target_post_id);
        $ver = file_exists($file_path) ? filemtime($file_path) : time();
        return '<img src="' . esc_url($qr_url . $file_name . '?v=' . $ver) . '" width="150" height="150" alt="QR Code for ' . esc_attr($alt_title) . '" />';
    }
}
add_shortcode('page_qr_code', 'display_page_qr_code');


// Shortcode tạo nút PDF cho Elementor
if (!function_exists('mac_elementor_pdf_button')) {
    function mac_elementor_pdf_button() {
        $macQRCode = !empty(get_option('mac_qr_code')) ? get_option('mac_qr_code') : 0;
        if (empty($macQRCode)) {
            return '';
        }

        return '<a id="download-pdf-1" class="no-print mac-pdf-button-1">Download PDF</a>';
    }
}
add_shortcode('elementor_pdf_button', 'mac_elementor_pdf_button');
