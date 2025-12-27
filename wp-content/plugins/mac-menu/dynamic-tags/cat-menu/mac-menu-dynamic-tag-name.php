<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor Dynamic Tag - Server Variable
 *
 * Elementor dynamic tag that returns a server variable.
 *
 * @since 1.0.0
 */
class Elementor_Dynamic_Tag_Mac_Menu_Name extends \Elementor\Core\DynamicTags\Tag {

	/**
	 * Get dynamic tag name.
	 *
	 * Retrieve the name of the server variable tag.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Dynamic tag name.
	 */
	public function get_name() {
		return 'mac-menu-name';
	}

	/**
	 * Get dynamic tag title.
	 *
	 * Returns the title of the server variable tag.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Dynamic tag title.
	 */
	public function get_title() {
		return esc_html__( 'Name', 'mac-plugin' );
	}

	/**
	 * Get dynamic tag groups.
	 *
	 * Retrieve the list of groups the server variable tag belongs to.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array Dynamic tag groups.
	 */
	public function get_group() {
		return [ 'request-mac-menu' ];
	}

	/**
	 * Get dynamic tag categories.
	 *
	 * Retrieve the list of categories the server variable tag belongs to.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array Dynamic tag categories.
	 */
	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	/**
	 * Register dynamic tag controls.
	 *
	 * Add input fields to allow the user to customize the server variable tag settings.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @return void
	 */
	protected function register_controls() {
		$objmacMenu = new macMenu();
        $results = $objmacMenu->all_cat();
        $newArray = array();
        foreach($results as $item ){
            $newArray[$item->id] = $item->category_name;
        }
        $this->add_control(
            'current_category',
            [
                'label' => __( 'Current Category', 'mac-plugin' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'on',
                'default' => 'off',
            ]
        );
		$this->add_control(
            'price_in_name_category',
            [
                'label' => __( 'Show Price in Name', 'mac-plugin' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_enable' => __( 'On', 'mac-plugin' ),
                'label_disable' => __( 'Off', 'mac-plugin' ),
                'return_value' => 'on',
                'default' => 'off',
				// 'condition' => [
                //     'current_category!' => 'on',
                // ],
            ]
        );
		$this->add_control(
			'user_selected_cat_menu',
			[
				'type' => \Elementor\Controls_Manager::SELECT,
				'label' => esc_html__( 'Menu', 'mac-plugin' ),
				'condition' => [
                    'current_category!' => 'on',
                ],
				'options' => $newArray,
			]
		);
	}

	/**
	 * Render tag output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function render() {
		$id = !empty($this->get_settings( 'user_selected_cat_menu' )) ? $this->get_settings( 'user_selected_cat_menu' ) :"";
		$current_category = !empty($this->get_settings( 'current_category' )) ? $this->get_settings( 'current_category' ) :"";
		$price_in_name_category = !empty($this->get_settings( 'price_in_name_category' )) ? $this->get_settings( 'price_in_name_category' ) :"";
		$customID = get_custom_array();
		
		// Nếu current_category được bật, thử lấy từ JetEngine current object trước
		if( $current_category != '' ) {
			// Thử lấy từ JetEngine listings data (nếu có)
			if ( function_exists( 'jet_engine' ) && isset( jet_engine()->listings ) && isset( jet_engine()->listings->data ) ) {
				$current_object = jet_engine()->listings->data->get_current_object();
				if ( $current_object && ( is_object( $current_object ) || is_array( $current_object ) ) ) {
					$jet_id = is_object( $current_object ) ? ( $current_object->id ?? null ) : ( $current_object['id'] ?? null );
					if ( $jet_id ) {
						$id = $jet_id;
					}
				}
			}
			
			// Fallback to custom_array nếu JetEngine không có
			if ( ! $id && isset($customID['id']) ) {
				$id = $customID['id'];
			}
		}
		if(!isset($id) || $id == '' ):
			$id = 1;
			//return;
		endif;
		$objmacMenu = new macMenu();
		$Cat = $objmacMenu->find_cat_menu($id);
		echo wp_kses_post( $Cat[0]->category_name );
		if(!empty($price_in_name_category)){
			echo '<span class="price-in-name">';
			echo wp_kses_post( $Cat[0]->price );
			echo '</span>';
			
		}
		
	}

}