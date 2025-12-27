<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
class Mac_Module_Widget_QR extends Widget_Base {

    public function get_name() {
        return 'module_mac_qr';
    }

    public function get_title() {
        return __( 'MAC QR', 'mac-plugin' );
    }

    public function get_icon() {
        return 'eicon-ehp-hero';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    protected function _register_controls() {
        $this->render_option_content_mac_qr();
    }

    protected function render() {
        $settings = $this->get_settings();
        echo mac_qr_elementor_render( $settings );
    }

    protected function render_option_content_mac_qr() {
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
                'mac_qr_code_module_title',
                [
                    'label' => __( 'Heading', 'mac-plugin' ),
                    'type' => Controls_Manager::TEXT,
                    'default' => '',
                ]
            );
            $this->end_controls_section(); 
            // Style 
            $this->start_controls_section(
                'section_style',
                [
                    'label' => __( 'Typography', 'mac-plugin' ),
                    'tab' => Controls_Manager::TAB_STYLE,
                ]
            );
             // Heading Title QR Code
            // Heading Control
            $this->add_control(
                'qr_heading_control',
                [
                    'label' => __('QR Heading', 'mac-plugin'),
                    'type' => Controls_Manager::HEADING,
                    'separator' => 'before',
                    'classes' => 'elementor-control-separator-before'
                ]
            );

            // Heading Title Setting
                
            $this->add_responsive_control(
                'qr_heading_color',
                [
                    'label' => __('Title Color', 'mac-plugin'),
                    'type' => Controls_Manager::COLOR,
                    'devices' => [ 'desktop', 'tablet', 'mobile' ],
                    'selectors' => [
                        '{{WRAPPER}} .mac-qrcode__heading' => 'color: {{VALUE}};',
                    ]
                    
                ]
            );
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                [
                    'name' => 'qr_heading_typography',
                    'label' => __('Title Typography', 'mac-plugin'),
                    'devices' => [ 'desktop', 'tablet', 'mobile' ],
                    'selector' => '{{WRAPPER}} .mac-qrcode__heading',
                ]
            );
            $this->end_controls_section();
        }
    }
}

// Hàm toàn cục bên ngoài class
if ( ! function_exists( 'mac_qr_elementor_render' ) ) {
    function mac_qr_elementor_render( $settings ) {
        $block_str = '';
        $macQRTitle  = get_option( 'mac_qr_title' ) ?: '';
        $moduleQRTitle = $settings['mac_qr_code_module_title'] ?? '';
        if ( ! empty( $moduleQRTitle ) ) {
            $macQRTitle = $moduleQRTitle;
        }
        $block_str .= '<div id="mac-module-qr" class="mac-qrcode-wrap">';
        if ( ! empty( $macQRTitle ) ) {
            $block_str .= '<div class="mac-qrcode__head">';
            $block_str .= '<h3 class="mac-qrcode__heading">' . esc_html( $macQRTitle ) . '</h3>';
            $block_str .= '</div>';
        }
        $block_str .= '<div class="mac-qrcode__Content">';
        $block_str .= '<div class="mac-qrcode__shortcode">' . do_shortcode( '[page_qr_code]' ) . '</div>';
        $block_str .= '<div class="mac-dowpdf__shortcode">' . do_shortcode( '[elementor_pdf_button]' ) . '</div>';
        $block_str .= '</div></div>';
        
        return $block_str;
    }
}
