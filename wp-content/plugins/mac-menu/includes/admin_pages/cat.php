<?php
    global $wpdb;
    // Tên bảng
    $cattablename = $wpdb->prefix . 'mac_cat_menu';
    // Kiểm tra sự tồn tại
    $table_exists_query = $wpdb->prepare("SHOW TABLES LIKE %s", $cattablename);
    $table_exists = $wpdb->get_var($table_exists_query);

    $statusDomain = !empty(get_option('mac_domain_valid_status')) ? get_option('mac_domain_valid_status') : "0" ;


    if ( empty($statusDomain) || ($statusDomain != 'activate' && $statusDomain != 'deactivate' )):
        mac_redirect('admin.php?page=mac-menu');
    else:
        if ($table_exists != $cattablename) {
            create_table_cat();
        }
        $entriesList = $wpdb->get_results("SELECT  * FROM ".$cattablename."");
        $objmacMenu = new macMenu();
        $result = $objmacMenu->paginate_cat(100);
        extract($result);
        
        if( isset( $_GET['id'] ) && $_GET['id'] != '' ){
            require_once 'cat-detail.php';
        }elseif( isset( $_GET['id'] ) && $_GET['id'] == 'new' ) {
            require_once 'cat-detail.php';
        }
        else{
            if(!empty($entriesList)) {
                require_once 'cat-list.php';
            }else{
                mac_redirect('admin.php?page=mac-menu');
            }
            
            
        }
        

    endif;
?>