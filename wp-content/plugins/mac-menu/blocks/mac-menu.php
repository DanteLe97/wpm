<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;

class Mac_Module_Widget extends Widget_Base {

    public function get_name() {
        return 'module_mac_menu';
    }

    public function get_title() {
        return __( 'Mac Menu', 'mac-plugin' );
    }

    public function get_icon() {
        return 'eicon-menu-card';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    protected function _register_controls() {
        $this->render_option_content();
        $this->render_option_setting_category();
        $spacingCategory = !empty(get_option('mac_menu_spacing')) ? get_option('mac_menu_spacing') : "0";
        if($spacingCategory == 1):
        $this->render_option_spacing_category();
        endif;
        $this->render_option_setting_img();
        $this->render_option_setting_tabs();
    }
    public function get_select_cat_menu() {
        $objmacMenu = new macMenu();
        $results = $objmacMenu->all_cat();
        
        $newArray = array();
        foreach($results as $item ){
            $newArray[$item->id] = $item->category_name;
        }
        $newArray['all'] = esc_html__( 'All', 'mac-plugin');

        return $newArray;
    }
    public function get_select_template_section() {
        $newArray = array();
        $args = array(
            'post_type'      => 'elementor_library',
            'posts_per_page' => -1, // Lấy tất cả các post
            'post_status'    => 'publish', // Lấy các post có trạng thái là 'publish'
            'meta_query'     => array(
                array(
                    'key'   => '_elementor_template_type', // Key lưu loại template
                    'value' => 'section', // Giá trị cần tìm
                ),
            ),
        );
        
        // Lấy danh sách các template từ Elementor
        $elementor_templates = get_posts($args);
        foreach($elementor_templates as $template) {
            $newArray[$template->ID] = $template->post_title;
        }
        $newArray['default'] = esc_html__( 'Default', 'mac-plugin');

        return $newArray;
    }
    protected function render() {
        $settings = $this->get_settings_for_display();
       
        include MAC_PATH.'/blocks/render/mac-menu-render.php';
        
        if ( function_exists( 'mac_menu_elementor_render' ) ) {
            $settings    = $this->get_settings();
            echo \mac_menu_elementor_render( $settings );
        }

    }
    public function render_option_setting() {
        $rankNumber = !empty(get_option('mac_menu_rank')) ? get_option('mac_menu_rank') : "1";
        for ($i=0; $i < $rankNumber; $i++) { 
            $this->render_option_setting_menu_basic($i);
            $this->render_option_setting_menu_table($i);
        }
    }
    public function render_option_content() {

        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Content', 'mac-plugin' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );
        $this->add_control(
            'is_current_category',
            [
                'label' => __( 'On/Off Current Category in Page', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'on',
                'default' => 'off',
            ]
        );
        $this->add_control(
            'id_category',
            [
                'label' => __( 'Select Option', 'mac-plugin' ),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'default' => array('all'),
                'options' => $this->get_select_cat_menu(),
                'condition' => [
                    'is_current_category!' => 'on',
                ],
            ]
        );
        $this->add_control(
            'limit_list_item',
            [
                'label' => __( 'Limit List Item', 'mac-plugin' ),
                'type' => Controls_Manager::NUMBER
            ]
        );
        // Heading Control
        $this->add_control(
            'custom_layout_heading_control',
            [
                'label' => __('Layout Category', 'mac-plugin'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'classes' => 'elementor-control-separator-before'
            ]
        );
        $this->add_control(
            'cat_menu_is_custom_layout',
            [
                'label' => __( 'On/Off Custom', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'on',
                'default' => 'off',
            ]
        );
        
        $this->add_control(
            'cat_menu_render_items',
            [
                'label' => __( 'Render Items (Loop)', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'on',
                'default' => 'off',
                'description' => __( 'On: Render template nhiều lần cho items. Off: Chỉ render 1 lần cho category.', 'mac-plugin' ),
                'condition' => [
                    'cat_menu_is_custom_layout' => 'on',
                ],
            ]
        );
        
        $this->add_control(
            'id_section_category',
            [
                'label' => __( 'Select Template Section', 'mac-plugin' ),
                'type' => Controls_Manager::SELECT,
                'default' => array('default'),
                'options' => $this->get_select_template_section(),
                'condition' => [
                    'cat_menu_is_custom_layout' => 'on',
                ],
            ]
        );
        $this->end_controls_section();

        $elementCategory = !empty(get_option('mac_menu_element_category')) ? get_option('mac_menu_element_category') : "0";
        if($elementCategory == 1):
        $this->start_controls_section(
            'content_cat_menu_section',
            [
                'label' => __( 'Category', 'mac-plugin' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );
        $this->add_control(
            'cat_menu_is_img',
            [
                'label' => __( 'Image Hidden', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'off',
                'default' => 'on',
            ]
        );
        $this->add_control(
            'cat_menu_is_description',
            [
                'label' => __( 'Description Hidden', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'off',
                'default' => 'on',
            ]
        );
        $this->add_control(
            'cat_menu_is_price',
            [
                'label' => __( 'Price Hidden', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'off',
                'default' => 'on',
            ]
        );
        // Heading Control
        $this->add_control(
            'content_item_heading_control',
            [
                'label' => __('Item', 'mac-plugin'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'classes' => 'elementor-control-separator-before'
            ]
        );
        $this->add_control(
            'cat_menu_item_is_img',
            [
                'label' => __( 'Image Hidden', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'off',
                'default' => 'on',
            ]
        );
        $this->add_control(
            'cat_menu_item_is_description',
            [
                'label' => __( 'Description Hidden', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'off',
                'default' => 'on',
            ]
        );
        $this->add_control(
            'cat_menu_item_is_price',
            [
                'label' => __( 'Price Hidden', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'off',
                'default' => 'on',
            ]
        );
        $this->end_controls_section();
        endif;

        $elementCategoryTable = !empty(get_option('mac_menu_element_category_table')) ? get_option('mac_menu_element_category_table') : "0";
        if($elementCategoryTable == 1):
        $this->start_controls_section(
            'content_cat_menu_table_section',
            [
                'label' => __( 'Category Table', 'mac-plugin' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );
        $this->add_control(
            'cat_menu_table_is_img',
            [
                'label' => __( 'Image Hidden', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'off',
                'default' => 'on',
            ]
        );
        $this->add_control(
            'cat_menu_table_is_description',
            [
                'label' => __( 'Description Hidden', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'off',
                'default' => 'on',
            ]
        );
        $this->add_control(
            'cat_menu_table_is_price',
            [
                'label' => __( 'Price Hidden', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'off',
                'default' => 'on',
            ]
        );
        $this->add_control(
            'cat_menu_table_is_heading',
            [
                'label' => __( 'Heading Hidden', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'off',
                'default' => 'on',
            ]
        );
        // Heading Control
        $this->add_control(
            'content_table_item_heading_control',
            [
                'label' => __('Item', 'mac-plugin'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'classes' => 'elementor-control-separator-before'
            ]
        );
        $this->add_control(
            'cat_menu_table_item_is_img',
            [
                'label' => __( 'Image Hidden', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'off',
                'default' => 'on',
            ]
        );
        $this->add_control(
            'cat_menu_table_item_is_description',
            [
                'label' => __( 'Description Hidden', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'off',
                'default' => 'on',
            ]
        );
        $this->add_control(
            'cat_menu_table_item_is_price',
            [
                'label' => __( 'Price Hidden', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'off',
                'default' => 'on',
            ]
        );
        $this->end_controls_section();
        endif;

        $qrCode = !empty(get_option('mac_qr_code')) ? get_option('mac_qr_code') : "0";
        if($qrCode == 1){
            $this->start_controls_section(
                'content_qr_code_section',
                [
                    'label' => __( 'QR Code', 'mac-plugin' ),
                    'tab' => Controls_Manager::TAB_CONTENT,
                ]
            );
           
            $this->add_control(
                'mac_qr_code_title',
                [
                    'label' => __( 'Heading', 'mac-plugin' ),
                    'type' => Controls_Manager::TEXT,
                    'default' => '',
                ]
            );
            $this->add_control(
                'mac_qr_code',
                [
                    'label' => __( 'QR', 'mac-plugin' ),
                    'type' => Controls_Manager::SWITCHER,
                    'label_enable' => __( 'On', 'mac-plugin' ),
                    'label_disable' => __( 'Off', 'mac-plugin' ),
                    'return_value' => 'on',
                    'default' => 'on',
                ]
            );
            $this->end_controls_section();
        } 

        $this->start_controls_section(
            'content_html_section',
            [
                'label' => __( 'HTML', 'mac-plugin' ),
                'tab' => Controls_Manager::TAB_CONTENT, //TAB_CONTENT,
            ]
        );
        
        $this->add_control(
			'html_css',
			[
				'label' => esc_html__( 'CSS', 'mac-plugin' ),
				'type' => \Elementor\Controls_Manager::CODE,
                'language' => 'css',
                'default' => ''
			]
		);
        $this->add_control(
			'html_js',
			[
				'label' => esc_html__( 'JS', 'mac-plugin' ),
                'type' => \Elementor\Controls_Manager::CODE,
                'language' => 'javascript',
                'default' => ''
			]
		);
        $this->end_controls_section();


    }
    public function render_option_setting_img() {
        $imgCategory = !empty(get_option('mac_menu_img')) ? get_option('mac_menu_img') : "0";
        $imgItemCategory = !empty(get_option('mac_menu_item_img')) ? get_option('mac_menu_item_img') : "0";
        
        
        if($imgCategory != 0 || $imgItemCategory != 0 ):
        $this->start_controls_section(
            'style_setting_img',
            [
                'label' => __( 'Images', 'mac-plugin' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );
        
        if($imgCategory == 1):
        // Width control
        $this->add_responsive_control(
            'img_width',
            [
                'label' => __( 'Width', 'plugin-name' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px', '%', 'vw' ],
                'range' => [
                    'px' => [
                        'min' => 50,
                        'max' => 1000,
                    ],
                    '%' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                    'vw' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .module-category__img img,{{WRAPPER}} .custom-category__img img' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        // Height control
        $this->add_responsive_control(
            'img_height',
            [
                'label' => __( 'Height', 'plugin-name' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px', '%', 'vh' ],
                'range' => [
                    'px' => [
                        'min' => 50,
                        'max' => 1000,
                    ],
                    '%' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                    'vh' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .module-category__img img,{{WRAPPER}} .custom-category__img img' => 'height: {{SIZE}}{{UNIT}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );

        // Object fit control
        $this->add_control(
            'img_object_fit',
            [
                'label' => __( 'Object Fit', 'plugin-name' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'fill' => __( 'Fill', 'plugin-name' ),
                    'cover' => __( 'Cover', 'plugin-name' ),
                    'contain' => __( 'Contain', 'plugin-name' ),
                ],
                'default' => 'cover',
                'selectors' => [
                    '{{WRAPPER}} .module-category__img img,{{WRAPPER}} .custom-category__img img' => 'object-fit: {{VALUE}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );
        // Padding control
        $this->add_responsive_control(
            'img_padding',
            [
                'label' => __( 'Padding', 'mac-plugin' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em' ],
                'selectors' => [
                    '{{WRAPPER}} .module-category__img img,{{WRAPPER}} .custom-category__img img' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );

        // Margin control
        $this->add_responsive_control(
            'img_margin',
            [
                'label' => __( 'Margin', 'mac-plugin' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em' ],
                'selectors' => [
                    '{{WRAPPER}} .module-category__img img,{{WRAPPER}} .custom-category__img img' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );

        //Dorder radius control
        $this->add_responsive_control(
            'img_border_radius',
            [
                'label' => __( 'Border Radius', 'mac-plugin' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .module-category__img img,{{WRAPPER}} .custom-category__img img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );
        //Dorder radius control
        $this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'img_border',
				'selector' => '{{WRAPPER}} .module-category__img img,{{WRAPPER}} .custom-category__img img',
                'classes' => 'elementor-control-separator-before'
			]
		);
        endif;

        if($imgItemCategory == 1):
        // Heading Control
        $this->add_control(
            'item_img_heading_control',
            [
                'label' => __('Item', 'mac-plugin'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'classes' => 'elementor-control-separator-before'
            ]
        );

        // Width control
        $this->add_responsive_control(
            'item_img_width',
            [
                'label' => __( 'Width', 'plugin-name' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px', '%', 'vw' ],
                'range' => [
                    'px' => [
                        'min' => 50,
                        'max' => 1000,
                    ],
                    '%' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                    'vw' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .module-category-item__img img,{{WRAPPER}} .custom-category-item__img img' => 'width: {{SIZE}}{{UNIT}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );

        // Height control
        $this->add_responsive_control(
            'item_img_height',
            [
                'label' => __( 'Height', 'plugin-name' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px', '%', 'vh' ],
                'range' => [
                    'px' => [
                        'min' => 50,
                        'max' => 1000,
                    ],
                    '%' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                    'vh' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .module-category-item__img img,{{WRAPPER}} .custom-category-item__img img' => 'height: {{SIZE}}{{UNIT}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );

        // Object fit control
        $this->add_control(
            'item_img_object_fit',
            [
                'label' => __( 'Object Fit', 'plugin-name' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'fill' => __( 'Fill', 'plugin-name' ),
                    'cover' => __( 'Cover', 'plugin-name' ),
                    'contain' => __( 'Contain', 'plugin-name' ),
                ],
                'default' => 'cover',
                'selectors' => [
                    '{{WRAPPER}} .module-category-item__img img,{{WRAPPER}} .custom-category-item__img img' => 'object-fit: {{VALUE}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );
        // Padding control
        $this->add_responsive_control(
            'item_img_padding',
            [
                'label' => __( 'Padding', 'mac-plugin' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em' ],
                'selectors' => [
                    '{{WRAPPER}} .module-category-item__img img,{{WRAPPER}} .custom-category-item__img img' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );

        // Margin control
        $this->add_responsive_control(
            'item_img_margin',
            [
                'label' => __( 'Margin', 'mac-plugin' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em' ],
                'selectors' => [
                    '{{WRAPPER}} .module-category-item__img img,{{WRAPPER}} .custom-category-item__img img' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );

        //Dorder radius control
        $this->add_responsive_control(
            'item_img_border_radius',
            [
                'label' => __( 'Border Radius', 'mac-plugin' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .module-category-item__img img,{{WRAPPER}} .custom-category-item__img img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );
        //Dorder radius control
        $this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'item_img_border',
				'selector' => '{{WRAPPER}} .module-category-item__img img,{{WRAPPER}} .custom-category-item__img img',
                'classes' => 'elementor-control-separator-before'
			]
		);
        endif;

        $this->end_controls_section();
        endif;
    }
    public function render_option_setting_category() {
        $this->start_controls_section(
            'section_settings',
            [
                'label' => __( 'Styles', 'mac-plugin' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );
        $this->add_control(
            'is_category_head_text_align',
            [
                'label' => __( 'On/Off Name Alignment', 'mac-plugin' ),
                'type' => Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'on',
                'default' => 'off',
            ]
        );
        $this->add_responsive_control(
			'category_head_text_align',
			[
				'label' => esc_html__( 'Alignment', 'mac-plugin' ),
				'type' => \Elementor\Controls_Manager::CHOOSE,
                'description' => __( '*only use Category Name', 'plugin-name' ),
                'condition' => [
                    'is_category_head_text_align' => 'on',
                ],
				'options' => [
					'left' => [
						'title' => esc_html__( 'Left', 'mac-plugin' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Center', 'mac-plugin' ),
						'icon' => 'eicon-text-align-center',
					],
					'right' => [
						'title' => esc_html__( 'Right', 'mac-plugin' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'default' => 'center',
				'toggle' => true,
			]
		);

        $BackgroundCategory = !empty(get_option('mac_menu_background')) ? get_option('mac_menu_background') : "0";
        if($BackgroundCategory == 1):
        // Heading Control
        $this->add_control(
            'heading_bg_control',
            [
                'label' => __('Background', 'mac-plugin'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'classes' => 'elementor-control-separator-before'
            ]
        );
        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'background',
                'label' => __( 'Background', 'mac-plugin' ),
                'types' => [ 'classic', 'gradient' ],
                'selector' => '{{WRAPPER}} .module-category,{{WRAPPER}} .custom-category',
                
            ]
        );
        $this->add_control(
            'background_overlay_color',
            [
                'label' => __( 'Overlay Color', 'mac-plugin' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}  .module-category:before,{{WRAPPER}} .custom-category:before' => 'background-color: {{VALUE}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );

        $this->add_control(
            'background_overlay_opacity',
            [
                'label' => __( 'Overlay Opacity', 'mac-plugin' ),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 1,
                        'step' => 0.1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 0.5,
                ],
                'selectors' => [
                    '{{WRAPPER}}  .module-category:before,{{WRAPPER}} .custom-category:before' => 'opacity: {{SIZE}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );

        // Padding control
        $this->add_responsive_control(
            'padding',
            [
                'label' => __( 'Padding', 'mac-plugin' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em' ],
                'selectors' => [
                    '{{WRAPPER}} .module-category,{{WRAPPER}} .custom-category' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );

        // Margin control
        $this->add_responsive_control(
            'margin',
            [
                'label' => __( 'Margin', 'mac-plugin' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em' ],
                'selectors' => [
                    '{{WRAPPER}} .module-category,{{WRAPPER}} .custom-category' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );

        //Dorder radius control
        $this->add_responsive_control(
            'border_radius',
            [
                'label' => __( 'Border Radius', 'mac-plugin' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .module-category,{{WRAPPER}} .custom-category' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );
        //Dorder radius control
        $this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'border',
				'selector' => '{{WRAPPER}} .module-category,{{WRAPPER}} .custom-category',
                'classes' => 'elementor-control-separator-before'
			]
		);

        // Heading Control
        $this->add_control(
            'heading_bg_secondary_control',
            [
                'label' => __('Background Secondary (Background for Even)', 'mac-plugin'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'classes' => 'elementor-control-separator-before'
            ]
        );
        //BG item Secondary (Even)
        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'background_secondary',
                'label' => __( 'Background', 'mac-plugin' ),
                'types' => [ 'classic', 'gradient' ],
                'selector' => '{{WRAPPER}} .module-category:nth-child(even),{{WRAPPER}} .custom-category:nth-child(even)',
                
            ]
        );
        endif;

        $this->end_controls_section();
    }
    public function render_option_spacing_category() {
        $this->start_controls_section(
            'section_spacing',
            [
                'label' => __( 'Spacing', 'mac-plugin' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );
        // Heading Control
        $this->add_control(
            'spacing_heading_basic_control',
            [
                'label' => __('Basic', 'mac-plugin'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'classes' => 'elementor-control-separator-before'
            ]
        );
        $this->add_responsive_control(
            'spacing_category_head',
            [
                'label' => __( 'Head', 'mac-plugin' ),
                'type' => Controls_Manager::SLIDER,
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'size' => 0,
                ],
                'selectors' => [
                    '{{WRAPPER}} .module-category__content:not(.module-category-table-style__content) .module-category__head,{{WRAPPER}} .custom-category__content:not(.custom-category-table-style__content) .custom-category__head' => 'margin-bottom: {{SIZE}}px;',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );
        $this->add_responsive_control(
            'spacing_category_text',
            [
                'label' => __( 'Text', 'mac-plugin' ),
                'type' => Controls_Manager::SLIDER,
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .module-category__content:not(.module-category-table-style__content) .module-category__text:not(:last-child),{{WRAPPER}} .custom-category__content:not(.custom-category-table-style__content) .custom-category__text:not(:last-child)' => 'margin-bottom: {{SIZE}}px;',
                ],
            ]
        );
        $this->add_responsive_control(
            'spacing_category_content',
            [
                'label' => __( 'Content', 'mac-plugin' ),
                'type' => Controls_Manager::SLIDER,
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'size' => 0,
                ],
                'selectors' => [  
                    '{{WRAPPER}} .module-category .module-category-child-wrap .module-category-child-wrap ,{{WRAPPER}} .module-category .module-category-child,{{WRAPPER}} .custom-category .custom-category-child-wrap .custom-category-child-wrap ,{{WRAPPER}} .custom-category .custom-category-child' => 'margin-top: {{SIZE}}px;',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );
        $this->add_responsive_control(
            'spacing_category_list_item_row',
            [
                'label' => __( 'List Item Row', 'mac-plugin' ),
                'type' => Controls_Manager::SLIDER,
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .module-category,{{WRAPPER}} .custom-category' => '--gap-row: {{SIZE}}px;',
                ],
            ]
        );
        $this->add_responsive_control(
            'spacing_category_list_item_col',
            [
                'label' => __( 'List Item Column', 'mac-plugin' ),
                'type' => Controls_Manager::SLIDER,
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 200,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'size' => 40,
                ],
                'selectors' => [
                    '{{WRAPPER}} .module-category,{{WRAPPER}} .custom-category' => '--gap-col: {{SIZE}}px;',
                ],
            ]
        );
        $this->add_responsive_control(
            'spacing_category_list_item_head',
            [
                'label' => __( 'List Item Head', 'mac-plugin' ),
                'type' => Controls_Manager::SLIDER,
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'size' => 0,
                ],
                'selectors' => [
                    '{{WRAPPER}} .module-category__content:not(.module-category-table-style__content) .module-category-item__description,{{WRAPPER}} .custom-category__content:not(.custom-category-table-style__content) .custom-category-item__description' => 'margin-top: {{SIZE}}px;',
                ],
            ]
        );
        // Heading Control
        $this->add_control(
            'spacing_heading_table_control',
            [
                'label' => __('Table', 'mac-plugin'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'classes' => 'elementor-control-separator-before'
            ]
        );
        $this->add_responsive_control(
            'spacing_category_table_head',
            [
                'label' => __( 'Head', 'mac-plugin' ),
                'type' => Controls_Manager::SLIDER,
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'size' => 0,
                ],
                'selectors' => [
                    '{{WRAPPER}} .module-category__content.module-category-table-style__content .module-category__head,{{WRAPPER}} .custom-category__content.custom-category-table-style__content .custom-category__head' => 'margin-bottom: {{SIZE}}px;',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );
        $this->add_responsive_control(
            'spacing_category_table_text',
            [
                'label' => __( 'Text', 'mac-plugin' ),
                'type' => Controls_Manager::SLIDER,
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .module-category__content.module-category-table-style__content .module-category__text:not(:last-child),{{WRAPPER}} .custom-category__content.custom-category-table-style__content .custom-category__text:not(:last-child)' => 'margin-bottom: {{SIZE}}px;',
                ],
            ]
        );
        $this->add_responsive_control(
            'spacing_category_table_content',
            [
                'label' => __( 'Content', 'mac-plugin' ),
                'type' => Controls_Manager::SLIDER,
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'size' => 0,
                ],
                'selectors' => [
                    '{{WRAPPER}} .module-category.module-category-table-style .module-category-table-style-child-wrap,{{WRAPPER}} .custom-category.custom-category-table-style .custom-category-table-style-child-wrap' => 'margin-top: {{SIZE}}px;',
                ],
                'classes' => 'elementor-control-separator-before'
            ]
        );
        $this->add_responsive_control(
            'spacing_category_table_list_item_head',
            [
                'label' => __( 'List Item Head', 'mac-plugin' ),
                'type' => Controls_Manager::SLIDER,
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'size' => 0,
                ],
                'selectors' => [
                    '{{WRAPPER}} .module-category-table-style__content .module-category-item__description,{{WRAPPER}} .custom-category-table-style__content .custom-category-item__description' => 'margin-top: {{SIZE}}px;',
                ],
            ]
        );

        $this->end_controls_section();
    }
    public function render_option_setting_tabs() {
        $rankNumber = !empty(get_option('mac_menu_rank')) ? get_option('mac_menu_rank') : "1";
        $this->start_controls_section(
            'section_style',
            [
                'label' => __( 'Typography', 'mac-plugin' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->start_controls_tabs('button_tabs');
        for ($i=1; $i <= $rankNumber; $i++) { 
            if($i == 1):
                $this->render_option_tab($i);
            else:
                $this->render_option_tab_rank_secondary($i);
            endif;
            
        }
        $this->end_controls_tabs();
        $this->end_controls_section(); 
    }
    public function render_option_tab($i) {
        // Tab Style Cat Settings
        $numberRank = $i;
        $this->start_controls_tab(
            'category_tab_'.$numberRank,
            [
                'label' => __( 'Rank '.$numberRank, 'mac-plugin' ),
            ]
        );

        // Name Setting
            
        $this->add_responsive_control(
            'name_text_color_'.$i.'',
            [
                'label' => __('Name Color', 'mac-plugin'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .module-category__name,{{WRAPPER}} .custom-category__name' => 'color: {{VALUE}};',
                ],
                
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'name_typography_'.$i.'',
                'label' => __('Name Typography', 'mac-plugin'),
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'selector' => '{{WRAPPER}} .module-category__name,{{WRAPPER}} .custom-category__name',
            ]
        );
        // Price Setting
            
        $this->add_responsive_control(
            'price_text_color_'.$i.'',
            [
                'label' => __('Price Color', 'mac-plugin'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .module-category__price,{{WRAPPER}} .custom-category__price' => 'color: {{VALUE}};',
                ],
                'classes' => 'elementor-control-separator-before'
                
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'price_typography_'.$i.'',
                'label' => __('Price Typography', 'mac-plugin'),
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'selector' => '{{WRAPPER}} .module-category__price,{{WRAPPER}} .custom-category__price',
            ]
        );
        // Description Setting
        $this->add_responsive_control(
            'description_text_color_'.$i.'',
            [
                'label' => __('Description Color', 'mac-plugin'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .module-category__description,{{WRAPPER}} .custom-category__description' => 'color: {{VALUE}};',
                ],
                'classes' => 'elementor-control-separator-before'
                
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography_'.$i.'',
                'label' => __('Description Typography', 'mac-plugin'),
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'selector' => '{{WRAPPER}} .module-category__description,{{WRAPPER}} .custom-category__description',
            ]
        );
        $this->add_responsive_control(
            'spacing_ellipsis_color_'.$i.'',
            [
                'label' => __('Spacing Ellipsis Color', 'mac-plugin'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .module-category-item__head:has(.module-category-item__price) .module-category-item__name:after, 
                    {{WRAPPER}} .module-category__head:has(.module-category__price) .module-category__name:after,
                    {{WRAPPER}} .custom-category-item__head:has(.custom-category-item__price) .custom-category-item__name:after, 
                    {{WRAPPER}} .custom-category__head:has(.custom-category__price) .custom-category__name:after' => 'border-color: {{VALUE}};',
                ],
                'classes' => 'elementor-control-separator-before'
                
            ]
        );

        // Item Heding Name Setting
        $this->add_responsive_control(
            'table_heading_text_color_'.$i.'',
            [
                'label' => __('Heading Color', 'mac-plugin'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .module-category-table .module-category__heading > td,{{WRAPPER}} .custom-category-table .custom-category__heading > td' => 'color: {{VALUE}};',
                ],
                'classes' => 'elementor-control-separator-before'
                
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'table_heading_typography_'.$i.'',
                'label' => __('Heading Typography', 'mac-plugin'),
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'selector' => '{{WRAPPER}} .module-category-table .module-category__heading > td,{{WRAPPER}} .custom-category-table .custom-category__heading > td',
            ]
        );
        $this->add_responsive_control(
            'table_border_color_'.$i.'',
            [
                'label' => __('Border Color (Style Only Table)', 'mac-plugin'),
                'type' => Controls_Manager::COLOR,
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'selectors' => [
                    '{{WRAPPER}} table td, table th' => 'border-color: {{VALUE}};',
                ],
                'classes' => 'elementor-control-separator-before'
                
            ]
        );

        // Heading Control
        $this->add_control(
            'item_heading_control_'.$i.'',
            [
                'label' => __('Item', 'mac-plugin'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'classes' => 'elementor-control-separator-before'
            ]
        );

        // Item Name Setting
            
        $this->add_responsive_control(
            'item_name_text_color_'.$i.'',
            [
                'label' => __('Name Color', 'mac-plugin'),
                'type' => Controls_Manager::COLOR,
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'selectors' => [
                    '{{WRAPPER}} .module-category-item__name,
                    {{WRAPPER}} .module-category-item__price,
                    {{WRAPPER}} .custom-category-item__name,
                    {{WRAPPER}} .custom-category-item__price,
                    {{WRAPPER}} .module-category__heading > td,
                    {{WRAPPER}} .custom-category__heading > td' => 'color: {{VALUE}};',
                ]
                
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'item_name_typography_'.$i.'',
                'label' => __('Name Typography', 'mac-plugin'),
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'selector' => '{{WRAPPER}} .module-category-item__name,
                                {{WRAPPER}} .module-category-item__price,
                                {{WRAPPER}} .module-category__heading > td,
                                {{WRAPPER}} .custom-category-item__name,
                                {{WRAPPER}} .custom-category-item__price,
                                {{WRAPPER}} .custom-category__heading > td
                ',
            ]
        );
            // Item Price Setting
            
        $this->add_responsive_control(
            'item_price_text_color_'.$i.'',
            [
                'label' => __('Price Color', 'mac-plugin'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .module-category-item__price,{{WRAPPER}} .custom-category-item__price' => 'color: {{VALUE}};',
                ],
                'classes' => 'elementor-control-separator-before'
                
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'item_price_typography_'.$i.'',
                'label' => __('Price Typography', 'mac-plugin'),
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'selector' => '{{WRAPPER}} .module-category-item__price,{{WRAPPER}} .custom-category-item__price',
            ]
        );
            // Item Description Setting
        $this->add_responsive_control(
            'item_description_text_color_'.$i.'',
            [
                'label' => __('Description Color', 'mac-plugin'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .module-category-item__description,{{WRAPPER}} .custom-category-item__description' => 'color: {{VALUE}};',
                ],
                'classes' => 'elementor-control-separator-before'
                
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'item_description_typography_'.$i.'',
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'label' => __('Description Typography', 'mac-plugin'),
                'selector' => '{{WRAPPER}} .module-category-item__description,{{WRAPPER}} .custom-category-item__description',
            ]
        );
        // Heading Title QR Code
        // Heading Control
        $this->add_control(
            'qr_heading_control_'.$i.'',
            [
                'label' => __('QR Heading', 'mac-plugin'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'classes' => 'elementor-control-separator-before'
            ]
        );

        // Heading Title Setting
            
        $this->add_responsive_control(
            'qr_heading_color_'.$i.'',
            [
                'label' => __('Title Color', 'mac-plugin'),
                'type' => Controls_Manager::COLOR,
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'selectors' => [
                    '{{WRAPPER}} .mac-qrcode__heading,{{WRAPPER}} .custom-qrcode__heading' => 'color: {{VALUE}};',
                ]
                
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'qr_heading_typography_'.$i.'',
                'label' => __('Title Typography', 'mac-plugin'),
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'selector' => '{{WRAPPER}} .mac-qrcode__heading,{{WRAPPER}} .custom-qrcode__heading',
            ]
        );


        $this->end_controls_tab();
    }
    public function render_option_tab_rank_secondary($i) {
        // Tab Style Cat Settings
        $numberRank = $i;
        $numberRankChild = $i -1;
        $classChildWrap = '';
        //if ($i != 1):
            $classChildWrap = '.module-category-child-'.$numberRankChild.'-wrap > .module-category__content';
        //endif;
        $this->start_controls_tab(
            'category_tab_'.$numberRank,
            [
                'label' => __( 'Rank '.$numberRank, 'mac-plugin' ),
            ]
        );

        // Name Setting
            
        $this->add_responsive_control(
            'name_text_color_'.$i.'',
            [
                'label' => __('Name Color', 'mac-plugin'),
                'type' => Controls_Manager::COLOR,
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'selectors' => [
                    '{{WRAPPER}} '.$classChildWrap.' .module-category__name,{{WRAPPER}} '.$classChildWrap.' .custom-category__name' => 'color: {{VALUE}};',
                ],
                
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'name_typography_'.$i.'',
                'label' => __('Name Typography', 'mac-plugin'),
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'selector' => '{{WRAPPER}} '.$classChildWrap.' .module-category__name,{{WRAPPER}} '.$classChildWrap.' .custom-category__name',
            ]
        );
        // Price Setting
            
        $this->add_responsive_control(
            'price_text_color_'.$i.'',
            [
                'label' => __('Price Color', 'mac-plugin'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} '.$classChildWrap.' .module-category__price,{{WRAPPER}} '.$classChildWrap.' .custom-category__price' => 'color: {{VALUE}};',
                ],
                'classes' => 'elementor-control-separator-before'
                
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'price_typography_'.$i.'',
                'label' => __('Price Typography', 'mac-plugin'),
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'selector' => '{{WRAPPER}} '.$classChildWrap.' .module-category__price,{{WRAPPER}} '.$classChildWrap.' .custom-category__price',
            ]
        );
        // Description Setting
        $this->add_responsive_control(
            'description_text_color_'.$i.'',
            [
                'label' => __('Description Color', 'mac-plugin'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} '.$classChildWrap.' .module-category__description,{{WRAPPER}} '.$classChildWrap.' .custom-category__description' => 'color: {{VALUE}};',
                ],
                'classes' => 'elementor-control-separator-before'
                
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography_'.$i.'',
                'label' => __('Description Typography', 'mac-plugin'),
                'devices' => [ 'desktop', 'tablet', 'mobile' ],
                'selector' => '{{WRAPPER}} '.$classChildWrap.' .module-category__description,{{WRAPPER}} '.$classChildWrap.' .custom-category__description',
            ]
        );
        $this->add_responsive_control(
            'spacing_ellipsis_color_'.$i.'',
            [
                'label' => __('Spacing Ellipsis Color', 'mac-plugin'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} '.$classChildWrap.' .module-category-item__head:has(.module-category-item__price) .module-category-item__name:after, '.$classChildWrap.'  .module-category__head:has(.module-category__price) .module-category__name:after,
                    {{WRAPPER}} '.$classChildWrap.' .custom-category-item__head:has(.custom-category-item__price) .custom-category-item__name:after, '.$classChildWrap.'  .custom-category__head:has(.custom-category__price) .custom-category__name:after' => 'border-color: {{VALUE}};',
                ],
                'classes' => 'elementor-control-separator-before'
                
            ]
        );
        $this->end_controls_tab();
    }
}
