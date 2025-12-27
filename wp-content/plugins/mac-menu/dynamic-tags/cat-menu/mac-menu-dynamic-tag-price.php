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
class Elementor_Dynamic_Tag_Mac_Menu_Price extends \Elementor\Core\DynamicTags\Tag {

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
		return 'mac-menu-price';
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
		return esc_html__( 'Price', 'mac-plugin' );
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
		$customID = get_custom_array();
		if( $current_category != '' && isset($customID['id'])):
			$id = $customID['id'];
		endif;
		if(!isset($id) || $id == '' ):
			$id = 1;
			//return;
		endif;
		$objmacMenu = new macMenu();
		$Cat = $objmacMenu->find_cat_menu($id);
		echo wp_kses_post( $Cat[0]->price );
	}

}