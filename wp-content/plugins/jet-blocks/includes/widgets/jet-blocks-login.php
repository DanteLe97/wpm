<?php
/**
 * Class: Jet_Blocks_Login
 * Name: Login Form
 * Slug: jet-login
 */

namespace Elementor;

use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Utils;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Jet_Blocks_Login extends Jet_Blocks_Base {

	public function get_name() {
		return 'jet-login';
	}

	public function get_title() {
		return esc_html__( 'Login Form', 'jet-blocks' );
	}

	public function get_icon() {
		return 'jet-blocks-icon-login';
	}

	public function get_jet_help_url() {
		return 'https://crocoblock.com/knowledge-base/articles/how-to-add-a-login-form-to-the-header-built-with-elementor/';
	}

	public function get_categories() {
		return array( 'jet-blocks' );
	}

	protected function register_controls() {

		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'Content', 'jet-blocks' ),
			)
		);

		$this->add_control(
			'label_username',
			array(
				'label'   => esc_html__( 'Username Label', 'jet-blocks' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Username', 'jet-blocks' ),
			)
		);

		$this->add_control(
			'placeholder_username',
			array(
				'label'   => esc_html__( 'Username Placeholder', 'jet-blocks' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->add_control(
			'label_password',
			array(
				'label'   => esc_html__( 'Password Label', 'jet-blocks' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Password', 'jet-blocks' ),
			)
		);

		$this->add_control(
			'placeholder_password',
			array(
				'label'   => esc_html__( 'Password Placeholder', 'jet-blocks' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->add_control(
			'label_remember',
			array(
				'label'   => esc_html__( 'Remember Me Label', 'jet-blocks' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Remember Me', 'jet-blocks' ),
			)
		);

		$this->add_control(
			'label_log_in',
			array(
				'label'   => esc_html__( 'Log In Button Label', 'jet-blocks' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Log In', 'jet-blocks' ),
			)
		);

		$this->add_control(
			'login_redirect',
			array(
				'type'       => 'select',
				'label'      => esc_html__( 'Redirect After Login', 'jet-blocks' ),
				'default'    => 'home',
				'options'    => array(
					'home'   => esc_html__( 'Home page', 'jet-blocks' ),
					'left'   => esc_html__( 'Stay on the current page', 'jet-blocks' ),
					'custom' => esc_html__( 'Custom URL', 'jet-blocks' ),
				),
			)
		);

		$this->add_control(
			'login_redirect_url',
			array(
				'label'     => esc_html__( 'Redirect URL', 'jet-blocks' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => '',
				'dynamic'   => array(
					'active' => true,
				),
				'condition' => array(
					'login_redirect' => 'custom',
				),
			)
		);

		$this->add_control(
			'label_logged_in',
			array(
				'label'   => esc_html__( 'Logged in message', 'jet-blocks' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'You already logged in', 'jet-blocks' ),
			)
		);

		$this->add_control(
			'lost_password_link',
			array(
				'label'   => esc_html__( 'Lost Password link', 'jet-blocks' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => '',
			)
		);

		$this->add_control(
			'lost_password_link_text',
			array(
				'label'     => esc_html__( 'Lost Password link text', 'jet-blocks' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Lost your password?', 'jet-blocks' ),
				'condition' => array(
					'lost_password_link' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		$this->__start_controls_section(
			'login_fields_style',
			array(
				'label'      => esc_html__( 'Fields', 'jet-blocks' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'show_label' => false,
			)
		);

		$this->__add_control(
			'input_bg_color',
			array(
				'label'  => esc_html__( 'Background Color', 'jet-blocks' ),
				'type'   => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jet-login input.input' => 'background-color: {{VALUE}}',
				),
			),
			25
		);

		$this->__add_control(
			'input_color',
			array(
				'label'  => esc_html__( 'Text Color', 'jet-blocks' ),
				'type'   => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jet-login input.input' => 'color: {{VALUE}}',
					'{{WRAPPER}} .jet-login input::placeholder'=> 'color: {{VALUE}}',
				),
			),
			25
		);

		$this->__add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'input_typography',
				'selector' => '{{WRAPPER}} .jet-login input.input',
			),
			50
		);

		$this->__add_responsive_control(
			'input_padding',
			array(
				'label'      => esc_html__( 'Padding', 'jet-blocks' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-login input.input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			),
			25
		);

		$this->__add_responsive_control(
			'input_margin',
			array(
				'label'      => esc_html__( 'Margin', 'jet-blocks' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-login input.input' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			),
			25
		);

		$this->__add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'input_border',
				'label'          => esc_html__( 'Border', 'jet-blocks' ),
				'placeholder'    => '1px',
				'selector'       => '{{WRAPPER}} .jet-login input.input',
			),
			50
		);

		$this->__add_responsive_control(
			'input_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'jet-blocks' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-login input.input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			),
			50
		);

		$this->__add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'input_box_shadow',
				'selector' => '{{WRAPPER}} .jet-login input.input',
			),
			100
		);

		$this->__end_controls_section();

		$this->__start_controls_section(
			'login_labels_style',
			array(
				'label'      => esc_html__( 'Labels', 'jet-blocks' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'show_label' => false,
			)
		);

		$this->__add_control(
			'labels_bg_color',
			array(
				'label'  => esc_html__( 'Background Color', 'jet-blocks' ),
				'type'   => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jet-login label' => 'background-color: {{VALUE}}',
				),
			),
			50
		);

		$this->__add_control(
			'labels_color',
			array(
				'label'  => esc_html__( 'Text Color', 'jet-blocks' ),
				'type'   => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jet-login label' => 'color: {{VALUE}}',
				),
			),
			25
		);

		$this->__add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'labels_typography',
				'selector' => '{{WRAPPER}} .jet-login label',
			),
			50
		);

		$this->__add_responsive_control(
			'labels_padding',
			array(
				'label'      => esc_html__( 'Padding', 'jet-blocks' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-login label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			),
			50
		);

		$this->__add_responsive_control(
			'labels_margin',
			array(
				'label'      => esc_html__( 'Margin', 'jet-blocks' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-login label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			),
			25
		);

		$this->__add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'labels_border',
				'label'          => esc_html__( 'Border', 'jet-blocks' ),
				'placeholder'    => '1px',
				'selector'       => '{{WRAPPER}} .jet-login label',
			),
			75
		);

		$this->__add_responsive_control(
			'labels_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'jet-blocks' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-login label' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			),
			75
		);

		$this->__add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'labels_box_shadow',
				'selector' => '{{WRAPPER}} .jet-login label',
			),
			100
		);

		$this->__end_controls_section();

		$this->__start_controls_section(
			'login_submit_style',
			array(
				'label'      => esc_html__( 'Submit', 'jet-blocks' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'show_label' => false,
			)
		);

		$this->__start_controls_tabs( 'tabs_form_submit_style' );

		$this->__start_controls_tab(
			'login_form_submit_normal',
			array(
				'label' => esc_html__( 'Normal', 'jet-blocks' ),
			)
		);

		$this->__add_control(
			'login_submit_bg_color',
			array(
				'label'  => esc_html__( 'Background Color', 'jet-blocks' ),
				'type'   => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} input[type="submit"]' => 'background-color: {{VALUE}}',
				),
			),
			25
		);

		$this->__add_control(
			'login_submit_color',
			array(
				'label'  => esc_html__( 'Text Color', 'jet-blocks' ),
				'type'   => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} input[type="submit"]' => 'color: {{VALUE}}',
				),
			),
			25
		);

		$this->__end_controls_tab();

		$this->__start_controls_tab(
			'login_form_submit_hover',
			array(
				'label' => esc_html__( 'Hover', 'jet-blocks' ),
			)
		);

		$this->__add_control(
			'login_submit_bg_color_hover',
			array(
				'label'  => esc_html__( 'Background Color', 'jet-blocks' ),
				'type'   => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} input[type="submit"]:hover' => 'background-color: {{VALUE}}',
				),
			),
			25
		);

		$this->__add_control(
			'login_submit_color_hover',
			array(
				'label'  => esc_html__( 'Text Color', 'jet-blocks' ),
				'type'   => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} input[type="submit"]:hover' => 'color: {{VALUE}}',
				),
			),
			25
		);

		$this->__add_control(
			'login_submit_hover_border_color',
			array(
				'label' => esc_html__( 'Border Color', 'jet-blocks' ),
				'type' => Controls_Manager::COLOR,
				'condition' => array(
					'login_submit_border_border!' => '',
				),
				'selectors' => array(
					'{{WRAPPER}} input[type="submit"]:hover' => 'border-color: {{VALUE}};',
				),
			),
			75
		);

		$this->__end_controls_tab();

		$this->__end_controls_tabs();

		$this->__add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'login_submit_typography',
				'selector'  => '{{WRAPPER}} input[type="submit"]',
				'fields_options' => array(
					'typography' => array(
						'separator' => 'before',
					),
				),
			),
			50
		);

		$this->__add_responsive_control(
			'login_submit_padding',
			array(
				'label'      => esc_html__( 'Padding', 'jet-blocks' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} input[type="submit"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator' => 'before',
			),
			25
		);

		$this->__add_responsive_control(
			'login_submit_margin',
			array(
				'label'      => esc_html__( 'Margin', 'jet-blocks' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} input[type="submit"]' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			),
			25
		);

		$this->__add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'login_submit_border',
				'label'          => esc_html__( 'Border', 'jet-blocks' ),
				'placeholder'    => '1px',
				'selector'       => '{{WRAPPER}} input[type="submit"]',
			),
			75
		);

		$this->__add_responsive_control(
			'login_submit_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'jet-blocks' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} input[type="submit"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			),
			75
		);

		$this->__add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'login_submit_box_shadow',
				'selector' => '{{WRAPPER}} input[type="submit"]',
			),
			100
		);

		$this->__add_responsive_control(
			'login_submit_alignment',
			array(
				'label'   => esc_html__( 'Alignment', 'jet-blocks' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'left',
				'options' => array(
					'left'    => array(
						'title' => esc_html__( 'Left', 'jet-blocks' ),
						'icon'  => ! is_rtl() ? 'eicon-h-align-left' : 'eicon-h-align-right',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'jet-blocks' ),
						'icon'  => 'eicon-h-align-center',
					),
					'right' => array(
						'title' => esc_html__( 'Right', 'jet-blocks' ),
						'icon'  => ! is_rtl() ? 'eicon-h-align-right' : 'eicon-h-align-left',
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .login-submit' => 'text-align: {{VALUE}};',
				),
				'classes' => 'jet-blocks-text-align-control',
			),
			50
		);

		$this->__end_controls_section();

		$this->__start_controls_section(
			'lost_password_link_style',
			array(
				'label'      => esc_html__( 'Lost Password Link', 'jet-blocks' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'show_label' => false,
				'condition'  => array(
					'lost_password_link' => 'yes',
				),
			)
		);

		$this->__add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'lost_password_link_typography',
				'selector' => '{{WRAPPER}} .jet-login-lost-password-link',
			),
			50
		);

		$this->__add_control(
			'lost_password_link_color',
			array(
				'label'     => esc_html__( 'Color', 'jet-blocks' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jet-login-lost-password-link' => 'color: {{VALUE}}',
				),
			),
			25
		);

		$this->__add_control(
			'lost_password_link_hover_color',
			array(
				'label'     => esc_html__( 'Hover Color', 'jet-blocks' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jet-login-lost-password-link:hover' => 'color: {{VALUE}}',
				),
			),
			25
		);

		$this->__add_responsive_control(
			'lost_password_link_margin',
			array(
				'label'      => esc_html__( 'Margin', 'jet-blocks' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-login-lost-password-link' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			),
			25
		);

		$this->__end_controls_section();

		$this->__start_controls_section(
			'login_errors_style',
			array(
				'label'      => esc_html__( 'Errors', 'jet-blocks' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'show_label' => false,
			)
		);

		$this->__add_control(
			'errors_bg_color',
			array(
				'label'  => esc_html__( 'Background Color', 'jet-blocks' ),
				'type'   => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jet-login-message' => 'background-color: {{VALUE}}',
				),
			),
			50
		);

		$this->__add_control(
			'errors_color',
			array(
				'label'  => esc_html__( 'Text Color', 'jet-blocks' ),
				'type'   => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jet-login-message' => 'color: {{VALUE}}',
				),
			),
			25
		);

		$this->__add_control(
			'errors_link_color',
			array(
				'label'  => esc_html__( 'Link Color', 'jet-blocks' ),
				'type'   => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jet-login-message a' => 'color: {{VALUE}}',
				),
			),
			25
		);

		$this->__add_control(
			'errors_link_hover_color',
			array(
				'label'  => esc_html__( 'Link Hover Color', 'jet-blocks' ),
				'type'   => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jet-login-message a:hover' => 'color: {{VALUE}}',
				),
			),
			25
		);

		$this->__add_responsive_control(
			'errors_padding',
			array(
				'label'      => esc_html__( 'Padding', 'jet-blocks' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-login-message' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			),
			50
		);

		$this->__add_responsive_control(
			'errors_margin',
			array(
				'label'      => esc_html__( 'Margin', 'jet-blocks' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-login-message' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			),
			25
		);

		$this->__add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'errors_border',
				'label'          => esc_html__( 'Border', 'jet-blocks' ),
				'placeholder'    => '1px',
				'selector'       => '{{WRAPPER}} .jet-login-message',
			),
			75
		);

		$this->__add_responsive_control(
			'errors_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'jet-blocks' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-login-message' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			),
			75
		);

		$this->__add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'errors_box_shadow',
				'selector' => '{{WRAPPER}} .jet-login-message',
			),
			100
		);

		$this->__end_controls_section();

	}

	protected function render() {

		$this->__context = 'render';

		$settings = $this->get_settings_for_display();

		if ( is_user_logged_in() && ! jet_blocks_integration()->in_elementor() ) {

			$this->__open_wrap();
			echo wp_kses_post( $settings['label_logged_in'] );
			$this->__close_wrap();

			return;
		}

		$this->__open_wrap();

        global $wp;
        $base_url     = home_url( $wp->request );
        $redirect_url = esc_url_raw( add_query_arg( wp_unslash( $_GET ), $base_url ) ); // phpcs:ignore

		switch ( $settings['login_redirect'] ) {

			case 'home':
                $redirect_url = esc_url( home_url( '/' ) ); // phpcs:ignore
				break;

			case 'custom':
                $redirect_url = esc_url( do_shortcode( $settings['login_redirect_url'] ) ); // phpcs:ignore
				break;
		}

		add_filter( 'login_form_bottom', array( $this, 'add_login_fields' ) );

		$login_form = wp_login_form( array(
			'echo'           => false,
			'redirect'       => $redirect_url,
			'label_username' => $settings['label_username'],
			'label_password' => $settings['label_password'],
			'label_remember' => $settings['label_remember'],
			'label_log_in'   => $settings['label_log_in'],
		) );

		remove_filter( 'login_form_bottom', array( $this, 'add_login_fields' ) );

		$username_placeholder = ! empty( $settings['placeholder_username'] ) ? $settings['placeholder_username'] : '';
		$password_placeholder = ! empty( $settings['placeholder_password'] ) ? $settings['placeholder_password'] : '';

		$login_form = preg_replace( '/action=[\'\"].*?[\'\"]/', '', $login_form );
		$login_form = str_replace( 'id="user_login"', 'id="user_login" placeholder="' . esc_attr( $username_placeholder ) . '"', $login_form );
		$login_form = preg_replace( '/(<input[^>]*id="user_pass"[^>]*>)/', '<p class="login-password__wrapper">$1' . $this->get_eye_icon_svg() . '</p>', $login_form );
		$login_form = str_replace( 'id="user_pass"', 'id="user_pass" placeholder="' . esc_attr( $password_placeholder ) . '"', $login_form );

		echo '<div class="jet-login">';
		echo $login_form; // phpcs:ignore
		include $this->__get_global_template( 'lost-password-link' );
		include $this->__get_global_template( 'messages' );
		echo '</div>';

		$this->__close_wrap();
	}

	/**
	 * Generates the SVG icons for password visibility toggle.
	 *
	 * @return string SVG code for the eye icons used in the password visibility toggle.
	 */
	protected function get_eye_icon_svg() {
		return '
			<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="e-font-icon-svg e-far-eye password-visibility__icon password-visibility__icon--show show" viewBox="0 0 576 512">
				<path d="M288 144a110.94 110.94 0 0 0-31.24 5 55.4 55.4 0 0 1 7.24 27 56 56 0 0 1-56 56 55.4 55.4 0 0 1-27-7.24A111.71 111.71 0 1 0 288 144zm284.52 97.4C518.29 135.59 410.93 64 288 64S57.68 135.64 3.48 241.41a32.35 32.35 0 0 0 0 29.19C57.71 376.41 165.07 448 288 448s230.32-71.64 284.52-177.41a32.35 32.35 0 0 0 0-29.19zM288 400c-98.65 0-189.09-55-237.93-144C98.91 167 189.34 112 288 112s189.09 55 237.93 144C477.1 345 386.66 400 288 400z"></path>
			</svg>
			<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="e-font-icon-svg e-far-eye-slash password-visibility__icon password-visibility__icon--hide" viewBox="0 0 640 512">
				<path d="M634 471L36 3.51A16 16 0 0 0 13.51 6l-10 12.49A16 16 0 0 0 6 41l598 467.49a16 16 0 0 0 22.49-2.49l10-12.49A16 16 0 0 0 634 471zM296.79 146.47l134.79 105.38C429.36 191.91 380.48 144 320 144a112.26 112.26 0 0 0-23.21 2.47zm46.42 219.07L208.42 260.16C210.65 320.09 259.53 368 320 368a113 113 0 0 0 23.21-2.46zM320 112c98.65 0 189.09 55 237.93 144a285.53 285.53 0 0 1-44 60.2l37.74 29.5a333.7 333.7 0 0 0 52.9-75.11 32.35 32.35 0 0 0 0-29.19C550.29 135.59 442.93 64 320 64c-36.7 0-71.71 7-104.63 18.81l46.41 36.29c18.94-4.3 38.34-7.1 58.22-7.1zm0 288c-98.65 0-189.08-55-237.93-144a285.47 285.47 0 0 1 44.05-60.19l-37.74-29.5a333.6 333.6 0 0 0-52.89 75.1 32.35 32.35 0 0 0 0 29.19C89.72 376.41 197.08 448 320 448c36.7 0 71.71-7.05 104.63-18.81l-46.41-36.28C359.28 397.2 339.89 400 320 400z"></path>
			</svg>';
	}


	/**
	 * Add form fields
	 *
	 * @param  string $content
	 * @return string
	 */
	public function add_login_fields( $content ) {
		$content .= '<input type="hidden" name="jet_login" value="1">';
		return $content;
	}

}
