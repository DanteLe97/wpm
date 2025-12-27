<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Elementor_Dynamic_Tag_Mac_Menu_Img extends \Elementor\Core\DynamicTags\Data_Tag {

    public function get_name() {
        return 'mac-menu-img';
    }

    public function get_title() {
        return esc_html__( 'Image', 'mac-plugin' );
    }

    public function get_group() {
        return [ 'request-mac-menu' ];
    }

    public function get_categories() {
        return  [  \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY  ];
    }

    protected function register_controls() {
        $objmacMenu = new macMenu();
        $results = $objmacMenu->all_cat();
        $newArray = array();
        foreach($results as $item) {
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

	public function render() {
        $images = $this->get_value();
        if ( empty( $images ) ) {
            echo 'No images found';
            return;
        }
        echo '<img src="' . esc_url( $image['url'] ) . '" alt="" />';
    }

    public function get_value( array $options = [] ) {
        $id = $this->get_settings( 'user_selected_cat_menu' );
        $current_category = !empty($this->get_settings( 'current_category' )) ? $this->get_settings( 'current_category' ) :"";
		$customID = get_custom_array();
		if( $current_category != '' && isset($customID['id'])):
			$id = $customID['id'];
		endif;
        if ( ! $id || $id == '' ) {
            $id = 1;
			//return [];
        }

        $objmacMenu = new macMenu();
        $Cat = $objmacMenu->find_cat_menu($id);
		$ids = $Cat[0]->featured_img;
		
		$image_ids_array = explode('|', $ids);
        $images = [];
		if (!empty($image_ids_array)) {
			foreach ($image_ids_array as $image_id) {
				if(!empty($image_id)):
					$image_url = wp_get_attachment_image_src($image_id, 'full');
					return [
						'id' => $image_id,
						'url' => $image_url[0]
					];
				endif;
			}
		}
    }
}
