<?php
// Include các file cần thiết cho Mac Menu
if (file_exists(MAC_PATH . 'includes/classes/macMenu.php')) {
    include_once MAC_PATH . 'includes/classes/macMenu.php';
}

if (file_exists(MAC_PATH . 'blocks/render/mac-menu-render.php')) {
    include_once MAC_PATH . 'blocks/render/mac-menu-render.php';
}

// Include render-module.php và các module cần thiết
$htmlNew = get_option('mac_html_old', 1);
if (!empty($htmlNew)) {
    if (file_exists(MAC_PATH . 'blocks/new_html/render/render-module.php')) {
        include_once MAC_PATH . 'blocks/new_html/render/render-module.php';
    }
    if (file_exists(MAC_PATH . 'blocks/new_html/module/cat_menu_basic.php')) {
        include_once MAC_PATH . 'blocks/new_html/module/cat_menu_basic.php';
    }
    if (file_exists(MAC_PATH . 'blocks/new_html/module/cat_menu_table.php')) {
        include_once MAC_PATH . 'blocks/new_html/module/cat_menu_table.php';
    }
} else {
    if (file_exists(MAC_PATH . 'blocks/render/render-module.php')) {
        include_once MAC_PATH . 'blocks/render/render-module.php';
    }
    if (file_exists(MAC_PATH . 'blocks/module/cat_menu_basic.php')) {
        include_once MAC_PATH . 'blocks/module/cat_menu_basic.php';
    }
    if (file_exists(MAC_PATH . 'blocks/module/cat_menu_table.php')) {
        include_once MAC_PATH . 'blocks/module/cat_menu_table.php';
    }
}

// Hook để thêm cột QR vào danh sách Pages
add_filter('manage_pages_columns', 'qrcode_add_column');
if (!function_exists('qrcode_add_column')) {
    function qrcode_add_column($columns) {
        $columns['qrcode'] = 'QR Code';
        return $columns;
    }
}

// Hàm mã hóa ID
if (!function_exists('qrcode_encrypt_id')) {
    function qrcode_encrypt_id($id) {
        $salt = 6008;
        $encrypted = ($id + $salt) * 2;
        return strrev($encrypted);
    }
}

// Hàm giải mã ID
if (!function_exists('qrcode_decrypt_id')) {
    function qrcode_decrypt_id($encrypted) {
        // Kiểm tra xem $encrypted có phải là chuỗi hợp lệ không
        if (!is_string($encrypted) || empty($encrypted)) {
            return 0;
        }
        
        // Kiểm tra xem $encrypted có chứa ký tự không phải số không
        if (!preg_match('/^[0-9]+$/', $encrypted)) {
            return 0;
        }
        
        $salt = 6008;
        $reversed = strrev($encrypted);
        
        // Đảm bảo $reversed là số trước khi thực hiện phép chia
        $reversed = intval($reversed);
        
        // Kiểm tra xem $reversed có hợp lệ không
        if ($reversed <= 0) {
            return 0;
        }
        
        // Thực hiện phép tính an toàn
        $result = ($reversed / 2) - $salt;
        
        // Kiểm tra kết quả cuối cùng
        if ($result <= 0) {
            return 0;
        }
        
        return $result;
    }
}

// Hook để hiển thị QR trong cột
add_action('manage_pages_custom_column', 'qrcode_column_content', 10, 2);
if (!function_exists('qrcode_column_content')) {
    function qrcode_column_content($column_name, $post_id) {
        if ($column_name == 'qrcode') {
            if (!extension_loaded('gd')) {
                echo '<span style="color:red">PHP GD library không được bật. Vui lòng bật extension này trong php.ini</span>';
                return;
            }

            $qr_dir = MAC_PATH . 'adds-on/qr-code-pages/qr-images/';
            $qr_url = MAC_URI . 'adds-on/qr-code-pages/qr-images/';

            if (!file_exists($qr_dir)) {
                if (!wp_mkdir_p($qr_dir)) {
                    echo '<span style="color:red">Không thể tạo thư mục QR code. Vui lòng kiểm tra quyền truy cập thư mục.</span>';
                    return;
                }
            }

            if (!is_writable($qr_dir)) {
                echo '<span style="color:red">Thư mục QR code không có quyền ghi. Vui lòng cấp quyền ghi cho thư mục ' . esc_html($qr_dir) . '</span>';
                return;
            }

            if (!file_exists(MAC_PATH . 'adds-on/qr-code-pages/assets/phpqrcode/phpqrcode.php')) {
                echo '<span style="color:red">Không tìm thấy file thư viện QR code</span>';
                return;
            }
            require_once MAC_PATH . 'adds-on/qr-code-pages/assets/phpqrcode/phpqrcode.php';

            $file_name = 'qr-' . $post_id . '.png';
            $file_path = $qr_dir . $file_name;

            try {
                if (!file_exists($file_path)) {
                    $site_url = get_site_url();
                    $encrypted_id = qrcode_encrypt_id($post_id);
                    $qr_data = $site_url . '/qr-redirect/?id=' . $encrypted_id;
                    QRcode::png($qr_data, $file_path, QR_ECLEVEL_L, 3);

                    if (!file_exists($file_path)) {
                        echo '<span style="color:red">Không thể tạo QR code. Vui lòng kiểm tra quyền ghi file.</span>';
                        return;
                    }
                }

                echo '<img src="' . esc_url($qr_url . $file_name) . '" width="80" height="80" alt="QR Code for ' . esc_attr(get_the_title($post_id)) . '" />';
            } catch (Exception $e) {
                echo '<span style="color:red">Lỗi khi tạo QR code: ' . esc_html($e->getMessage()) . '</span>';
            }
        }
    }
}

// Hook để xử lý redirect khi quét QR code
add_action('init', 'handle_qr_redirect');
if (!function_exists('handle_qr_redirect')) {
    function handle_qr_redirect() {
        if (isset($_GET['id']) && $_GET['id'] != 'new') {
            $encrypted_id = sanitize_text_field($_GET['id']);

            try {
                $page_id = qrcode_decrypt_id($encrypted_id);

                if ($page_id > 0) {
                    $page_url = get_permalink($page_id);
                    if ($page_url) {
                        wp_redirect($page_url);
                        exit;
                    }
                }
            } catch (Exception $e) {
                // Không làm gì khi lỗi
            }
        }
    }
}

// Thêm rewrite rule cho endpoint riêng
add_action('init', 'add_qr_redirect_endpoint');
if (!function_exists('add_qr_redirect_endpoint')) {
    function add_qr_redirect_endpoint() {
        add_rewrite_rule('^qr-redirect/?$', 'index.php', 'top');
    }
}

// Đăng ký query var
add_filter('query_vars', 'register_qr_query_var');
if (!function_exists('register_qr_query_var')) {
    function register_qr_query_var($vars) {
        $vars[] = 'id';
        return $vars;
    }
}

// Enqueue style và script cho export PDF
add_action('wp_enqueue_scripts', 'mac_add_on_qr_code_enqueue');
if (!function_exists('mac_add_on_qr_code_enqueue')) {
    function mac_add_on_qr_code_enqueue() {
        wp_enqueue_style('mac-qr-style', MAC_URI . 'adds-on/qr-code-pages/assets/css/style.css');
        
        // Enqueue html2pdf trước
        wp_enqueue_script('epe-pdf-export-html2pdf', MAC_URI . 'adds-on/qr-code-pages/assets/js/html2pdf.bundle.min.js', array(), '', true);
        
        // Enqueue script chính với dependency html2pdf
        wp_enqueue_script('epe-pdf-export', MAC_URI . 'adds-on/qr-code-pages/assets/js/mac-pdf-export.js', array('epe-pdf-export-html2pdf'), '', true);
        
        // Localize script để truyền WordPress site URL và current post ID
        wp_localize_script('epe-pdf-export', 'macPdfAjax', array(
            'siteUrl' => home_url(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mac_pdf_nonce'),
            'currentPostId' => get_the_ID()
        ));
    }
}

















////////////////////
/// *** PDF Template System
////////////////////////

add_action('init', function() {
    add_rewrite_rule(
        '^mac-pdf-default/?$',
        'index.php?mac_pdf_default=1',
        'top'
    );
    add_rewrite_tag('%mac_pdf_default%', '1');
});

add_action('template_redirect', function() {
    if (get_query_var('mac_pdf_default') == 1) {
        
        // Chặn cache / headers
        nocache_headers();
        
        // Lấy post ID từ URL parameter
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        
          
		$lang_attr = get_language_attributes();
		$charset   = get_bloginfo( 'charset' );
		$body_cls  = implode( ' ', get_body_class() );
        // Kiểm tra Mac Menu Widget - kiểm tra nhiều cách
        if (class_exists('Mac_Module_Widget') || class_exists('\Mac_Module_Widget') || function_exists('mac_menu_elementor_render')) {
            // Set custom array cho dynamic tags
            set_custom_array([
                'id' => 1, // ID mặc định
                'limit_item' => '',
                'is_img' => 'on',
                'is_description' => 'on',
                'is_price' => 'on'
            ]);
            
                         // Dữ liệu Mac Menu Widget - đầy đủ settings để tránh lỗi
             $widget_settings = [
                 'id_category' => ['all'],
                 'limit_list_item' => '',
                 'cat_menu_is_img' => 'on',
                 'cat_menu_is_description' => 'on',
                 'cat_menu_is_price' => 'on',
                 'cat_menu_item_is_img' => 'on',
                 'cat_menu_item_is_description' => 'on',
                 'cat_menu_item_is_price' => 'on',
                 'category_head_text_align' => '', // Thêm để tránh lỗi undefined variable
                 'html_js' => '',
                 'html_css' => '',
                 'mac_qr_code_title' => 'Scan to Explore Our Services!',
                 'mac_qr_code' => 'on',
                 'is_child' => 0,
                 'is_content' => 1,
                 'is_parents_0' => 0,
                 'pdf_post_id' => $post_id // Truyền post ID vào settings
             ];
            
          
             // Inline CSS
             echo '<style>
             body {
                 font-family: "Roboto", Arial, sans-serif;
                 margin: 0;
                 padding: 10px;
                 background: #fff;
                 color: #333;
             }
             .mac-menu {
                 --e-global-color-text: #333;
             }
             .pdf-container {
                 max-width: 1140px;
                 margin: 0 auto;
                 background: #fff;
             }
             .pdf-header {
                 text-align: center;
                 margin-bottom: 20px;
                 padding-bottom: 10px;
                 border-bottom: 2px solid #ecd9d3;
             }
             .pdf-title {
                 font-size: 36px;
                 font-weight: 700;
                 text-transform: uppercase;
                 color: #333;
                 margin: 0 0 10px 0;
             }
             .pdf-subtitle {
                 font-size: 14px;
                 color: #666;
                 margin: 0;
             }
             .pdf-content {
                 line-height: 1.6;
                 font-size: 14px;
             }
             .pdf-footer {
                 margin-top: 30px;
                 padding-top: 20px;
                 border-top: 1px solid #ddd;
                 text-align: center;
                 font-size: 12px;
                 color: #666;
             }
             .pdf-loading {
                 position: fixed;
                 top: 50%;
                 left: 50%;
                 transform: translate(-50%, -50%);
                 background: rgba(0,0,0,0.8);
                 color: white;
                 padding: 20px;
                 border-radius: 5px;
                 z-index: 9999;
             }

             /* CSS cho Mac Menu trong PDF */
             .module-category .module-category-child {
                margin-top: 40px;
            }
             .module-category__text:not(:last-child) {
                 margin-bottom: 10px;
             }
             .module-category__description {
                 font-size: 14px;
                 color: #666;
                 margin: 0;
             }
                 
             .module-category__heading > td {
                 color: #8A4C4C !important;
                 text-transform: uppercase;
             }

             table td, table th {
                 border-color: #8A4C4C80 !important;
             }

             .module-category-item__name {
                 color: #8A4C4C !important;
                 text-transform: uppercase;
             }

             .module-category-item__price {
                 color: #8A4C4C !important;
             }

             table td , table th {
                 border-bottom-width: 0;
             }
             table tr.module-category-item:last-child td {
                 border-bottom-width: 1px; 
             }

             table td:not(:last-child) {
                 border-right-width: 0;
             }
             table .module-category__heading td[colspan] ~ td[colspan]  {
                 border-right: 1px solid #8A4C4C80;
             }
             .module-category__heading td{
                 text-align: center;
                 border-bottom-width: 0px;
             }
             
             td{
                 background-color: transparent !important;
                 border-width: 0;
             }
             td:not(:first-child){
                 text-align: center;
             }
             td:nth-child(even){
                 border-bottom: 0px solid #8a4c4c80;
             }

             table{
                 border: 0px solid #8a4c4c80;
             }
             .module-category-parents-0 > .module-category__content>.module-category__text>  .module-category__head {
                 display: flex;
                 justify-content: center;
             }
             .module-category-parents-0 > .module-category__content:not(.module-category-child)>.module-category__text> .module-category__head > .module-category__name{
                 text-align: center;
                 border-bottom: 3px solid #8A4D4C;
                 margin-bottom: 20px;
             }
            .module-category-parents-0 > .module-category__content:not(.module-category-child)>.module-category__text> .module-category__description {
                 text-align: center;
             }
            .module-category__content .module-category__name {
                 font-size: 20px;
                 font-weight: 700;
             }
             .module-category-parents-0 > .module-category__content .module-category__name {
                 font-size: 20px;
                 text-transform: uppercase;
                 font-weight: 700;
             }
            
             .module-category__heading,
             .module-category-item__price,
             .module-category-item__name {
                 font-size: 16px;
                 font-weight: 600;

             }
             .module-category__content[id$="-hide"]{
                 margin-top: 0!important;
             }

             .module-category__content[id$="-hide"] .module-category__text{
                 display: none;
             }

             .module-category-table-wrap{
                 overflow-x: auto;
             }

             .module-category-item .module-category-item__description ul {
               margin-top: 0px;
               --columns: 2;
               -webkit-column-count: var(--columns);
               -moz-column-count: var(--columns);
               column-count: var(--columns)
             }
             .module-category-item .module-category-item__description ul {
               margin-bottom: 15px;
             }
             @media (max-width: 1024px) {
               .module-category-item .module-category-item__description ul {
                   --columns: 1
               }
             }
             .module-category-item ul {
               margin-top: 0px;
             }
             .module-category-item__text ol {
               margin-top: 0px;
             }
             .module-category-item .module-category-item__description ul li {
               margin-right: 20px;
               /* -webkit-column-break-inside: avoid; */
               -moz-column-break-inside: avoid;
               /* break-inside: avoid-column; */
             }

             .module-category-item li {
               margin-right: 20px;
               /* -webkit-column-break-inside: avoid; */
               -moz-column-break-inside: avoid;
               /* break-inside: avoid-column; */
             }
             .module-category__content[id$="-hide"]{
                 margin-top: 0!important;
             }

             .show-card-price tr:not(:first-child):not(:last-child){
                 border-bottom: 1px solid #8a4c4c38;
             }
             .show-card-price table{
                 border-spacing: 10px; 
             }

             .show-card-price td, .show-card-price table{
                 border: none !important;
                 padding: 5px;
             }
             .show-card-price .module-category__heading td:not(:first-child) span{
                 padding: 5px 15px;
                 background-color: #ecd9d3
             }

             .module-category__content:not(.show-card-price) .module-category__heading:first-child td{
                 background-color: #ecd9d3 !important;
             }

             .module-category__img {
                 display: flex;
                 justify-content: center;
                 gap: 10px;
                 max-width: 100%;
             }

             @media (max-width:767px){
                 .module-category-table-wrap td{
                     padding:5px;
                 }
             }

             /**/
             .module-category-table-wrap:has(.table-scroll-active):before, .module-category-table-wrap:has(.table-scroll-active):after {
                 display: none !important;
             }
             .module-category__heading:first-child td:first-child {
                 display: block !important;
             }
             .module-category__heading:first-child td[colspan="2"] {
                 width:300px;
             }
             .module-category__heading:not(:first-child) td {
                 display: block !important;
             }
             table th,
             table td {
                 padding: 7px;
             }
             .module-category-table-wrap {
                 width: 100%;
                 overflow-x: auto;
             }
             table tr {
                 display: flex;
                 flex-wrap: nowrap;
             }
             table td {
             /* 	min-width: 150px; */
                 width: 150px;
             }
             table td:first-child {
             /* 	min-width: 150px; */
                 width: 150px;
                 flex: 1;
             }
             .mac-pdf-button:hover {
                 font-weight: 600;
             }
             @media (max-width: 767px) {
                 .module-category__heading:first-child td[colspan="2"] {
                     width:160px;
                 }
                 table td {
                     width:80px;
             /* 		min-width: 80px; */
                 }
             }
             /**/
                              .mac-pdf-exporting .not-print {
                  display: none;
              }
              
              /* ?n loading indicator khi xu?t PDF */
              
              .mac-pdf-exporting .pdf-loading {
                  display: none !important;
              }
              
              /* body:not(:has(a[href$="privacy-policy"])) .has-policy {
                  display: none !important;
              } */

              /**/
             
         </style>';
            

		echo '<!DOCTYPE html>
		<html ' . $lang_attr . '>
		<head>
			<meta charset="' . esc_attr( $charset ) . '">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">';
			wp_head();
		echo '</head>
		<body class="' . esc_attr( $body_cls ) . '">';
            
            // Loading indicator
            echo '<div id="pdf-loading" class="pdf-loading">
                <div>Creating PDF...</div>
                <div style="font-size: 12px; margin-top: 10px;">Please wait for a moment</div>
            </div>';
            
            // Main container
            echo '<div class="pdf-container">';
            
            // Header
            echo '<div class="pdf-header">';
            echo '<h1 class="pdf-title">' . get_bloginfo('name') . '</h1>';
            echo '</div>';
            
            // Content
            echo '<div class="pdf-content">';
            
            // Mac Menu Widget
            echo '<div class="mac-menu-section">';
            
            // Render Mac Menu sử dụng hàm render có sẵn
            if (function_exists('mac_menu_elementor_render')) {
                echo mac_menu_elementor_render($widget_settings);
            } else {
                // Fallback: render trực tiếp bằng Render_Module
                $render_module = new Render_Module();
                echo '<div class="mac-menu">';
                echo $render_module->render($widget_settings);
                echo '</div>';
            }
            
            echo '</div>';
            
            
            echo '</div>'; // .pdf-content
        
            
            echo '</div>'; // .pdf-container
            
            // JavaScript cho PDF generation
            // JavaScript cho PDF generation
            echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        setTimeout(function() {
                            generatePDF();
                        }, 1500);
                    });

                    function generatePDF() {
                        let rawTitle = document.title;
                        let decodedTitle = decodeHtmlEntities(rawTitle);
                        let slug = slugifyKeepCase(decodedTitle);
                        
                        // Thêm class vào body khi bắt đầu xuất PDF
                        document.body.classList.add("mac-pdf-exporting");
                        
                        let scale = 2;
                        if (document.documentElement.offsetWidth < 767) { scale = 1; }
                        const H2C_SCALE = Math.max(1, Math.ceil(window.devicePixelRatio * 1.5));
                        
                        const opt = {
                            margin: [10, 10],
                            filename: slug + ".pdf",
                            image: { type: "jpeg" },
                            html2canvas: {
                                scale: H2C_SCALE,
                                useCORS: true,
                                scrollX: 0,
                                scrollY: 0,
                                windowWidth: document.documentElement.clientWidth,
                                windowHeight: document.documentElement.scrollHeight
                            },
                            jsPDF: {
                                unit: "mm",
                                format: "letter",
                                orientation: "portrait"
                            },
                            pagebreak: {
                                mode: ["avoid-all", "css", "legacy"],
                                avoid: ["img", ".no-break"],
                                after: "#mac-qr"
                            }
                        };

                        // Tạo bản sao của body
                        var clonedBody = document.body.cloneNode(true);

                        // Reset CSS để không bị lệch/crop

                        clonedBody.style.margin = "0 auto";
                        clonedBody.style.transform = "scale(0.65)";
                        clonedBody.style.transformOrigin = "top left"; // thu nhỏ từ góc trái
                        clonedBody.style.padding = "0";
                        clonedBody.style.width = "1140px";
                        clonedBody.style.maxWidth = "1140px";
                        clonedBody.style.boxSizing = "border-box";
                        // const rect = clonedBody.getBoundingClientRect();
                        // clonedBody.style.height = rect.height * 0.65 + "px";

                        // clonedBody.style.margin = "0";
                        // clonedBody.style.padding = "0";
                        // clonedBody.style.width = "100%";
                        // clonedBody.style.maxWidth = "100%";
                        // clonedBody.style.boxSizing = "border-box";

                        // Ẩn phần không cần in
                        clonedBody.querySelectorAll("script, style, link, meta, noscript, .no-print, #jet-theme-core-header, #jet-theme-core-footer, header, footer, #wpadminbar")
                            .forEach(el => el.style.display = "none");

                        // Tìm phần tử QR và căn chỉnh
                        // ["#mac-qr", "#mac-module-qr"].forEach(selector => {
                        //     const qrElement = clonedBody.querySelector(selector);

                            
                        //     if (qrElement) {
                        //         qrElement.style.pageBreakBefore = "always";
                        //         qrElement.style.breakBefore = "always";
                        //         qrElement.style.height = "279.4mm";
                        //         qrElement.style.display = "flex";
                        //         qrElement.style.justifyContent = "center";
                        //         qrElement.style.alignItems = "center";
                                
                        //         qrElement.style.flexDirection = "column";
                        //         qrElement.style.textAlign = "center";
                        //     }
                        // });
                        
                        // Tìm QR gốc và clone riêng, append sau clonedBody
                    const qr = document.querySelector("#mac-qr, #mac-module-qr");

                    clonedBody.querySelectorAll("#mac-qr, #mac-module-qr").forEach((el) => {
                        el.style.setProperty("opacity", "0");
                    });
                        
                    if (qr) {
                        const qrClone = qr.cloneNode(true);
                        qrClone.style.pageBreakBefore = "always";
                        qrClone.style.display = "flex";
                        qrClone.style.justifyContent = "center";
                        qrClone.style.alignItems = "center";
                        // qrClone.style.height = "279.4mm"; // đúng 1 trang A4
                        qrClone.style.width = "210mm";    // full ngang A4
                        qrClone.style.height = (279.4 / 0.65) + "mm"; // scale ngược lại
                        qrClone.style.transform = "scale(" + (1 / 0.65) + ")";
                        qrClone.style.boxSizing = "border-box";
                        qrClone.querySelectorAll(".no-print").forEach(el => el.style.display = "none");
                        if (qrClone.classList.contains("no-print")) qrClone.style.display = "none";
                        clonedBody.appendChild(qrClone);
                    }

                        // Tạo PDF
                        html2pdf().set(opt).from(clonedBody).toPdf().get("pdf").then((pdf) => {
                        const totalPages = pdf.internal.getNumberOfPages();
                        const totalPagesNeeded = Math.ceil(totalPages * 65 / 100);

                        // Nếu PDF tạo nhiều hơn số cần → xóa trang thừa
                        for (let i = totalPages; i > totalPagesNeeded-1; i--) {
                            pdf.deletePage(i);
                        }


                            // Lấy số trang
                            // const totalPages = pdf.internal.getNumberOfPages();
                            // // Xóa trang cuối nếu có nhiều hơn 1 trang
                            // if (totalPages > 1) {
                            //     pdf.deletePage(totalPages);
                            // }

                            // Lưu PDF
                            pdf.save(slug + ".pdf");
                            // Xóa class khi đã tải xong file PDF
                            document.body.classList.remove("mac-pdf-exporting");
                            // Ẩn loading và đóng cửa sổ
                            let loading = document.getElementById("pdf-loading");
                            if (loading) loading.style.display = "none";
                            
                            // Đóng cửa sổ sau khi download xong
                            setTimeout(function() {
                                try {
                                    window.close();
                                } catch (e) {
                                    // Nếu không đóng được, thử redirect về trang chủ
                                    window.location.href = \'/\';
                                }
                            }, 1500);
                        }).catch(err => {
                            console.error("Lỗi khi xuất PDF:", err);
                        });
                    }


                    function slugifyKeepCase(text) {
                        return text
                        .normalize("NFD")
                        .replace(/[\u0300-\u036f]/g, "")       // Bỏ dấu tiếng Việt
                        .replace(/[^a-zA-Z0-9\s-]/g, "")       // Loại bỏ ký tự đặc biệt (giữ chữ in hoa)
                        .trim()
                        .replace(/\s+/g, "-")                  // khoảng trắng -> dấu -
                        .replace(/-+/g, "-");                  // gộp nhiều dấu -
                    }

                    function decodeHtmlEntities(html) {
                        const txt = document.createElement("textarea");
                        txt.innerHTML = html;
                        return txt.value;
                    }

                    if (typeof html2pdf === "undefined") {
                        console.error("html2pdf chưa được load!");
                    }
                    </script>';

            

            
            wp_footer();
            exit;
            
        } else {
            // Debug: hiển thị thông tin chi tiết
            echo '<h2>Debug Information:</h2>';
            echo '<p>Mac_Module_Widget class exists: ' . (class_exists('Mac_Module_Widget') ? 'YES' : 'NO') . '</p>';
            echo '<p>Mac_Module_Widget class exists (with namespace): ' . (class_exists('\Mac_Module_Widget') ? 'YES' : 'NO') . '</p>';
            echo '<p>mac_menu_elementor_render function exists: ' . (function_exists('mac_menu_elementor_render') ? 'YES' : 'NO') . '</p>';
            echo '<p>Render_Module class exists: ' . (class_exists('Render_Module') ? 'YES' : 'NO') . '</p>';
            
            // Thử render trực tiếp
            echo '<h3>Thử render trực tiếp:</h3>';
            if (function_exists('mac_menu_elementor_render')) {
                echo '<p>✅ Hàm mac_menu_elementor_render tồn tại!</p>';
                $widget_settings = [
                    'id_category' => ['all'],
                    'limit_list_item' => '',
                    'cat_menu_is_img' => 'on',
                    'cat_menu_is_description' => 'on',
                    'cat_menu_is_price' => 'on',
                    'cat_menu_item_is_img' => 'on',
                    'cat_menu_item_is_description' => 'on',
                    'cat_menu_item_is_price' => 'on'
                ];
                echo mac_menu_elementor_render($widget_settings);
            } else {
                echo '<p>❌ Hàm mac_menu_elementor_render không tồn tại</p>';
            }
            
            wp_die('Mac Menu Widget chưa được kích hoạt hoặc có lỗi.');
        }
    }
});