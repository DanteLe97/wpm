<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Elementor_Dynamic_Tag_Mac_Menu_Item_Img extends \Elementor\Core\DynamicTags\Data_Tag {

    public function get_name() {
        return 'mac-menu-item-img';
    }

    public function get_title() {
        return esc_html__( 'Item Img', 'mac-plugin' );
    }

    public function get_group() {
        return [ 'request-mac-item-menu' ];
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
            'current_category_item',
            [
                'label' => __( 'Current Item', 'mac-plugin' ),
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
                'options' => $newArray,
                'condition' => [
                    'current_category_item!' => 'on',
                ],
            ]
        );
        $this->add_control(
			'cat_menu_item_index',
			[
				'type' => \Elementor\Controls_Manager::NUMBER,
				'label' => esc_html__( 'Index Item (ex: 1 ) ', 'mac-plugin' ),
                'condition' => [
                    'current_category_item!' => 'on',
                ],
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
        $index = !empty($this->get_settings( 'cat_menu_item_index' )) ? $this->get_settings( 'cat_menu_item_index' ) : "";

        $current_category_item = !empty($this->get_settings( 'current_category_item' )) ? $this->get_settings( 'current_category_item' ) :"";

        $customID = get_custom_array();
		if( $current_category_item != '' && isset($customID['id'])):
			$id = $customID['id'];
		endif;

		$customIndex = get_custom_index();
		if( ($current_category_item != '') && isset($customIndex)):
			$index = $customIndex;
		endif;

        if ( ! $id || $id == '' ) {
            $id = 1;
            $index = 1;
			//return [];
        }

        $objmacMenu = new macMenu();
        $Cat = $objmacMenu->find_cat_menu($id);
		
        $Array = !empty( $Cat[0]->group_repeater) ? json_decode($Cat[0]->group_repeater, true) : [];
		if($index !=''):
			$i = 1;
			foreach($Array as $key){
				if($index == $i ):
                    if(isset($key['featured_img']) && !empty($key['featured_img']) ):
                        $attachment_id = attachment_url_to_postid($key['featured_img']);
                        return [
                            'id' => $attachment_id,
                            'url' => $key['featured_img']
                        ];

                    endif;
				endif;
				$i++;
			}
		endif;
    }
}
