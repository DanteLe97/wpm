<?php
/**
 * Class: Jet_Blocks_Logo
 * Name: Site Logo
 * Slug: jet-logo
 */

namespace Elementor;

use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Utils;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Jet_Blocks_Logo extends Jet_Blocks_Base {

	public function get_name() {
		return 'jet-logo';
	}

	public function get_title() {
		return esc_html__( 'Site Logo', 'jet-blocks' );
	}

	public function get_icon() {
		return 'jet-blocks-icon-logo';
	}

	public function get_jet_help_url() {
		return 'https://crocoblock.com/knowledge-base/articles/how-to-display-a-website-logo-in-the-header-built-with-elementor/';
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
			'logo_type',
			array(
				'type'    => 'select',
				'label'   => esc_html__( 'Logo Type', 'jet-blocks' ),
				'default' => 'text',
				'options' => array(
					'text'  => esc_html__( 'Text', 'jet-blocks' ),
					'image' => esc_html__( 'Image', 'jet-blocks' ),
					'both'  => esc_html__( 'Both Text and Image', 'jet-blocks' ),
				),
			)
		);

        $this->add_control(
            'logo_image_from',
            array(
                'label'   => esc_html__( 'Logo Image From', 'jet-blocks' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'custom',
                'options' => array(
                    'custom'         => esc_html__( 'Custom', 'jet-blocks' ),
                    'from_site_logo' => esc_html__( 'From Site Logo', 'jet-blocks' ),
                ),
                'condition' => array(
                    'logo_type!' => 'text',
                ),
            )
        );

		$this->add_control(
			'logo_image_from_notice',
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => esc_html__(
					'You can set a global site logo in Elementor: Site Settings → Site Identity.',
					'jet-blocks'
				),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition' => [
					'logo_image_from' => 'from_site_logo',
				],
			]
		);

        $this->add_control(
            'logo_image',
            array(
                'label'     => esc_html__( 'Logo Image', 'jet-blocks' ),
                'type'      => Controls_Manager::MEDIA,
                'condition' => array(
                    'logo_type!' => 'text',
                    'logo_image_from' => 'custom',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Image_Size::get_type(),
            [
                'name' => 'logo_image',
                'exclude' => [],
                'include' => [],
                'condition' => [
                    'logo_type!' => 'text',
                    'logo_image_from' => 'custom',
                ],
                'default' => 'full',
            ]
        );

		$this->add_control(
			'logo_image_2x',
			array(
				'label'     => esc_html__( 'Retina Logo Image', 'jet-blocks' ),
				'type'      => Controls_Manager::MEDIA,
				'condition' => array(
					'logo_type!' => 'text',
                    'logo_image_from' => 'custom',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			[
				'name'      => 'logo_image_2x',
				'exclude'   => [],
				'include'   => [],
				'condition' => [
					'logo_type!' => 'text',
                    'logo_image_from' => 'custom',
				],
				'default'   => 'full',
			]
		);

		$this->add_control(
			'logo_text_from',
			array(
				'type'       => 'select',
				'label'      => esc_html__( 'Logo Text From', 'jet-blocks' ),
				'default'    => 'site_name',
				'options'    => array(
					'site_name' => esc_html__( 'Site Name', 'jet-blocks' ),
					'custom'    => esc_html__( 'Custom', 'jet-blocks' ),
				),
				'condition' => array(
					'logo_type!' => 'image',
				),
			)
		);

		$this->add_control(
			'logo_text',
			array(
				'label'     => esc_html__( 'Custom Logo Text', 'jet-blocks' ),
				'type'      => Controls_Manager::TEXT,
				'condition' => array(
					'logo_text_from' => 'custom',
					'logo_type!'     => 'image',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_settings',
			array(
				'label' => esc_html__( 'Settings', 'jet-blocks' ),
			)
		);

		$this->add_control(
			'linked_logo',
			array(
				'label'        => esc_html__( 'Linked Logo', 'jet-blocks' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'jet-blocks' ),
				'label_off'    => esc_html__( 'No', 'jet-blocks' ),
				'return_value' => 'true',
				'default'      => 'true',
			)
		);

		$this->add_control(
			'remove_link_on_front',
			array(
				'label'        => esc_html__( 'Remove Link on Front Page', 'jet-blocks' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'jet-blocks' ),
				'label_off'    => esc_html__( 'No', 'jet-blocks' ),
				'return_value' => 'true',
				'default'      => '',
			)
		);

		$this->add_control(
			'logo_display',
			array(
				'type'        => 'select',
				'label'       => esc_html__( 'Display Logo Image and Text', 'jet-blocks' ),
				'label_block' => true,
				'default'     => 'block',
				'options'     => array(
					'inline' => esc_html__( 'Inline', 'jet-blocks' ),
					'block'  => esc_html__( 'Text Below Image', 'jet-blocks' ),
				),
				'condition' => array(
					'logo_type' => 'both',
				),
			)
		);

		$this->end_controls_section();

		$this->__start_controls_section(
			'logo_style',
			array(
				'label'      => esc_html__( 'Logo', 'jet-blocks' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'show_label' => false,
			)
		);

		$this->__add_responsive_control(
			'logo_alignment',
			array(
				'label'   => esc_html__( 'Logo Alignment', 'jet-blocks' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'flex-start',
				'options' => array(
					'flex-start' => array(
						'title' => esc_html__( 'Start', 'jet-blocks' ),
						'icon'  => ! is_rtl() ? 'eicon-h-align-left' : 'eicon-h-align-right',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'jet-blocks' ),
						'icon'  => 'eicon-h-align-center',
					),
					'flex-end' => array(
						'title' => esc_html__( 'End', 'jet-blocks' ),
						'icon'  => ! is_rtl() ? 'eicon-h-align-right' : 'eicon-h-align-left',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .jet-logo' => 'justify-content: {{VALUE}}',
				),
			),
			25
		);

		$this->__add_control(
			'vertical_logo_alignment',
			array(
				'label'       => esc_html__( 'Image and Text Vertical Alignment', 'jet-blocks' ),
				'type'        => Controls_Manager::CHOOSE,
				'default'     => 'center',
				'label_block' => true,
				'options' => array(
					'flex-start' => array(
						'title' => esc_html__( 'Top', 'jet-blocks' ),
						'icon' => 'eicon-v-align-top',
					),
					'center' => array(
						'title' => esc_html__( 'Middle', 'jet-blocks' ),
						'icon' => 'eicon-v-align-middle',
					),
					'flex-end' => array(
						'title' => esc_html__( 'Bottom', 'jet-blocks' ),
						'icon' => 'eicon-v-align-bottom',
					),
					'baseline' => array(
						'title' => esc_html__( 'Baseline', 'jet-blocks' ),
						'icon' => 'eicon-v-align-bottom',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .jet-logo__link' => 'align-items: {{VALUE}}',
				),
				'condition' => array(
					'logo_type'    => 'both',
					'logo_display' => 'inline',
				),
			),
			25
		);

		$this->__end_controls_section();

		$this->__start_controls_section(
			'text_logo_style',
			array(
				'label'      => esc_html__( 'Text', 'jet-blocks' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'show_label' => false,
				'condition' => array(
					'logo_type' => array ( 'both', 'text'),
				),
			)
		);

		$this->__add_control(
			'text_logo_color',
			array(
				'label'     => esc_html__( 'Color', 'jet-blocks' ),
				'type'      => Controls_Manager::COLOR,
				'global' => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'selectors' => array(
					'{{WRAPPER}} .jet-logo__text' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'logo_type' => array ( 'both', 'text'),
				),
			),
			25
		);

		$this->__add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'text_logo_typography',
				'selector' => '{{WRAPPER}} .jet-logo__text',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'condition' => array(
					'logo_type' => array ( 'both', 'text'),
				),
			),
			50
		);

		$this->__add_control(
			'text_logo_gap',
			array(
				'label'      => esc_html__( 'Gap', 'jet-blocks' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'default'    => array(
					'size' => 5,
				),
				'range'      => array(
					'px' => array(
						'min' => 10,
						'max' => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .jet-logo-display-block .jet-logo__img'  => 'margin-bottom: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .jet-logo-display-inline .jet-logo__img' => 'margin-right: {{SIZE}}{{UNIT}}',
					'body.rtl {{WRAPPER}} .jet-logo-display-inline .jet-logo__img' => 'margin-left: {{SIZE}}{{UNIT}}; margin-right: 0;',
				),
				'condition'  => array(
					'logo_type' => 'both',
				),
			),
			25
		);

		$this->__add_responsive_control(
			'text_logo_alignment',
			array(
				'label'   => esc_html__( 'Alignment', 'jet-blocks' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => array(
					'left' => array(
						'title' => esc_html__( 'Left', 'jet-blocks' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'jet-blocks' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right' => array(
						'title' => esc_html__( 'Right', 'jet-blocks' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .jet-logo__text' => 'text-align: {{VALUE}}',
				),
				'condition' => array(
					'logo_type'    => 'both',
					'logo_display' => 'block',
				),
			),
			50
		);

		$this->__end_controls_section();

	}

	protected function render() {

		$this->__context = 'render';

		$this->__open_wrap();
		include $this->__get_global_template( 'index' );
		$this->__close_wrap();
	}

	/**
	 * Check if logo is linked
	 * @return [type] [description]
	 */
	public function __is_linked() {

		$settings = $this->get_settings();

		if ( empty( $settings['linked_logo'] ) ) {
			return false;
		}

		if ( 'true' === $settings['remove_link_on_front'] && is_front_page() ) {
			return false;
		}

		return true;

	}

	/**
	 * Returns logo text
	 *
	 * @return string Text logo HTML markup.
	 */
	public function __get_logo_text() {

		$settings    = $this->get_settings();
		$type        = isset( $settings['logo_type'] ) ? esc_attr( $settings['logo_type'] ) : 'text';
		$text_from   = isset( $settings['logo_text_from'] ) ? esc_attr( $settings['logo_text_from'] ) : 'site_name';
		$custom_text = isset( $settings['logo_text'] ) ? wp_kses_post( $settings['logo_text'] ) : '';

		if ( 'image' === $type ) {
			return;
		}

		if ( 'site_name' === $text_from ) {
			$text = wp_kses_post( get_bloginfo( 'name' ) );
		} else {
			$text = wp_kses_post( $custom_text );
		}

		$format = apply_filters(
			'jet-blocks/widgets/logo/text-foramt',
			'<div class="jet-logo__text">%s</div>'
		);

		return sprintf( $format, $text );
	}

	/**
	 * Returns logo classes string
	 *
	 * @return string
	 */
	public function __get_logo_classes() {

		$settings = $this->get_settings();

		$type = isset( $settings['logo_type'] ) ? esc_attr( $settings['logo_type'] ) : 'text';
		$display = isset( $settings['logo_display'] ) ? esc_attr( $settings['logo_display'] ) : 'block';

		$classes = array(
			'jet-logo',
			'jet-logo-type-' . $type,
			'jet-logo-display-' . $display,
		);

		return implode( ' ', $classes );
	}

	/**
	 * Returns logo image
	 *
	 * @return string Image logo HTML markup.
	 */
	public function __get_logo_image() {
		$settings = $this->get_settings_for_display();
		$type     = isset( $settings['logo_type'] ) ? esc_attr( $settings['logo_type'] ) : 'text';

		if ( 'text' === $type ) {
			return '';
		}

		$image_src = '';
		$alt = get_bloginfo( 'name' );
		$retina_src = '';

		// Site Logo
		if ( isset( $settings['logo_image_from'] ) && 'from_site_logo' === $settings['logo_image_from'] ) {
			$logo_id = get_theme_mod( 'custom_logo' );
			if ( $logo_id ) {
                $image_src = wp_get_attachment_image_url( $logo_id, 'full' );
                $retina_src = '';
			} elseif ( function_exists( 'get_custom_logo' ) ) {
				$custom_logo_html = get_custom_logo();
				if ( preg_match( '/<svg.*<\/svg>/is', $custom_logo_html, $m ) ) {
					return $m[0];
				}
				if ( preg_match( '/src=["\']([^"\']+)["\']/', $custom_logo_html, $m ) ) {
					$image_src = $m[1];
				}
			}
		} else {
			$logo_id = isset( $settings['logo_image']['id'] ) ? $settings['logo_image']['id'] : false;
			if ( $logo_id ) {
				$image_src = Group_Control_Image_Size::get_attachment_image_src( $logo_id, 'logo_image', $settings );
				if ( ! empty( $settings['logo_image_2x']['id'] ) ) {
					$retina_src = Group_Control_Image_Size::get_attachment_image_src( $settings['logo_image_2x']['id'], 'logo_image_2x', $settings );
				}
				$alt = get_post_meta( $logo_id, '_wp_attachment_image_alt', true ) ?: $alt;
			} elseif ( ! empty( $settings['logo_image']['url'] ) ) {
				$image_src = $settings['logo_image']['url'];
			}
		}

		if ( empty( $image_src ) ) {
			return '';
		}

		if ( preg_match( '/\.svg(\?.*)?$/i', $image_src ) ) {
			return sprintf(
				'<img src="%1$s" class="jet-logo__img" alt="%2$s" />',
				esc_url( $image_src ),
				esc_attr( $alt )
			);
		}

		$width  = ! empty( $settings['logo_image_custom_dimension']['width'] ) ? intval( $settings['logo_image_custom_dimension']['width'] ) : '';
		$height = ! empty( $settings['logo_image_custom_dimension']['height'] ) ? intval( $settings['logo_image_custom_dimension']['height'] ) : '';

		$attrs = '';
		if ( $width ) {
			$attrs .= ' width="' . absint( $width ) . '"';
		}
		if ( $height ) {
			$attrs .= ' height="' . absint( $height ) . '"';
		}

		if ( $retina_src ) {
			return sprintf(
				'<img src="%1$s" srcset="%1$s 1x, %2$s 2x" class="jet-logo__img" alt="%3$s"%4$s />',
				esc_url( $image_src ),
				esc_url( $retina_src ),
				esc_attr( $alt ),
				$attrs
			);
		} else {
			return sprintf(
				'<img src="%1$s" class="jet-logo__img" alt="%2$s"%3$s />',
				esc_url( $image_src ),
				esc_attr( $alt ),
				$attrs
			);
		}
	}

	public function __get_logo_image_src( $args = array() ) {

		if ( ! empty( $args['id'] ) ) {
			$img_data = wp_get_attachment_image_src( $args['id'], 'full' );

			return ! empty( $img_data[0] ) ? $img_data[0] : false;
		}

		if ( ! empty( $args['url'] ) ) {
			return $args['url'];
		}

		return false;
	}

}
