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
class Elementor_Dynamic_Tag_Mac_Menu_Content extends \Elementor\Core\DynamicTags\Tag {

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
		return 'mac-menu-content';
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
		return esc_html__( 'Content', 'mac-plugin' );
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
		$customArray = get_custom_array();
		
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
			if ( ! $id && isset($customArray['id']) ) {
				$id = $customArray['id'];
			}
		}

		$limit_item = '';
		$menu_item_is_img = '';
		$menu_item_is_description = '';
		$menu_item_is_price = '';

		$table_is_heading = '';
		$table_menu_item_is_img = '';
		$table_menu_item_is_description = '';
		$table_menu_item_is_price = '';
		
		if( isset($customArray['limit_item'])):
			$limit_item = $customArray['limit_item'];
		endif;

		if( isset($customArray['is_img'])):
			$menu_item_is_img = $customArray['is_img'];
		endif;
		if( isset($customArray['is_description'])):
			$menu_item_is_description = $customArray['is_description'];
		endif;
		if( isset($customArray['is_price'])):
			$menu_item_is_price = $customArray['is_price'];
		endif;

		if( isset($customArray['table_is_heading'])):
			$table_is_heading = $customArray['table_is_heading'];
		endif;

		if( isset($customArray['table_is_img'])):
			$table_menu_item_is_img = $customArray['table_is_img'];
		endif;
		if( isset($customArray['table_is_description'])):
			$table_menu_item_is_description = $customArray['table_is_description'];
		endif;
		if( isset($customArray['table_is_price'])):
			$table_menu_item_is_price = $customArray['table_is_price'];
		endif;

		$moduleInfo = [];

		$moduleInfo = array (
			'id_category' => $id,

			'limit_list_item' => $limit_item,
			'cat_menu_item_is_img' => $menu_item_is_img,
			'cat_menu_item_is_description' => $menu_item_is_description,
			'cat_menu_item_is_price' => $menu_item_is_price,

			'cat_menu_table_is_heading' => $table_is_heading,
			'cat_menu_table_item_is_img' => $table_menu_item_is_img,
			'cat_menu_table_item_is_description' => $table_menu_item_is_description,
			'cat_menu_table_item_is_price' => $table_menu_item_is_price,
		);

		if(!isset($id) || $id == '' ):
			$id = 1;
			//return;
		endif;
		$headingArray = [];
		$block_str = '';

		$objmacMenu = new macMenu();
		//$isCat = $objmacMenu->find_cat_menu($id);
		$newArrayCategoryIds = [];
		$newArrayCategoryIds[] = $id;	
		$render_modules = '';
		$results = $objmacMenu->find_cat_menu_all_child_cats($newArrayCategoryIds);
				$tree = $this->buildTree($results);
				$render_modules .= $this->htmlModuleMenu($tree,$moduleInfo,0,0);
				$childCategorySelectIndex = 1;
				foreach($newArrayCategoryIds as $itemCat ) {
					$resultsItemcat = $objmacMenu->find_cat_menu($itemCat);
					if($resultsItemcat[0]->parents_category != 0 ):
						$render_modules .= $this->htmlModuleMenu($resultsItemcat,$moduleInfo,0,0,$childCategorySelectIndex);
						$childCategorySelectIndex++;
					endif;
				}
				
				echo $render_modules;
	}

	private function htmlModuleMenu($tree,$moduleInfo,$parents_category = 0,$indexChildWrap = 0,$childCategorySelectIndex = null ) {
		$objmacMenu = new macMenu();
		$MenuHTML = new Mac_Category_Menu;
		$MenuTableHTML = new Mac_Category_Menu_Table;   
		$id_category = isset($moduleInfo['id_category']) ? $moduleInfo['id_category'] : array('all');
		$limit_item = isset($moduleInfo['limit_list_item']) ? $moduleInfo['limit_list_item'] : '';
		
		/** layout custom */
		$custom_layout = isset($moduleInfo['cat_menu_is_custom_layout']) ? $moduleInfo['cat_menu_is_custom_layout'] : '';
		$section_category = isset($moduleInfo['id_section_category']) ? $moduleInfo['id_section_category'] : '';
		$section_category_item = isset($moduleInfo['id_section_category_item']) ? $moduleInfo['id_section_category_item'] : '';

		/** Cat Menu Basic */
		$is_img = isset($moduleInfo['cat_menu_is_img']) ? $moduleInfo['cat_menu_is_img'] : '';
		$is_description = isset($moduleInfo['cat_menu_is_description']) ? $moduleInfo['cat_menu_is_description'] : '';
		$is_price = isset($moduleInfo['cat_menu_is_price']) ? $moduleInfo['cat_menu_is_price'] : '';
		$menu_item_is_img = isset($moduleInfo['cat_menu_item_is_img']) ? $moduleInfo['cat_menu_item_is_img'] : '';
		$menu_item_is_description = isset($moduleInfo['cat_menu_item_is_description']) ? $moduleInfo['cat_menu_item_is_description'] : '';
		$menu_item_is_price = isset($moduleInfo['cat_menu_item_is_price']) ? $moduleInfo['cat_menu_item_is_price'] : '';
		/** Cat Menu Table */
		$table_is_img = isset($moduleInfo['cat_menu_table_is_img']) ? $moduleInfo['cat_menu_table_is_img'] : '';
		$table_is_description = isset($moduleInfo['cat_menu_table_is_description']) ? $moduleInfo['cat_menu_table_is_description'] : '';
		$table_is_price = isset($moduleInfo['cat_menu_table_is_price']) ? $moduleInfo['cat_menu_table_is_price'] : '';
		$table_menu_is_heading = isset($moduleInfo['cat_menu_table_is_heading']) ? $moduleInfo['cat_menu_table_is_heading'] : '';
		$table_menu_item_is_img = isset($moduleInfo['cat_menu_table_item_is_img']) ? $moduleInfo['cat_menu_table_item_is_img'] : '';
		$table_menu_item_is_description = isset($moduleInfo['cat_menu_table_item_is_description']) ? $moduleInfo['cat_menu_table_item_is_description'] : '';
		$table_menu_item_is_price = isset($moduleInfo['cat_menu_table_item_is_price']) ? $moduleInfo['cat_menu_table_item_is_price'] : '';

		$html = '';
		$index_cat_child_wrap = 0;
		$catIndex = 0;
		if(isset($childCategorySelectIndex) && $childCategorySelectIndex != null):
			$catIndex = 'select-child-'.$childCategorySelectIndex;
		endif;
		$mac_menu_dp = get_option('mac_menu_dp');
		if (empty($mac_menu_dp)) {
			$mac_menu_dp = 0;
		}
		foreach ($tree as $branch) {
			
			$MenuAttr = array (
				'id' => $branch->id,
				'limit_item' => $limit_item,
				'cat_menu_is_custom_layout' => $custom_layout,
				'id_section_category' => $section_category,
				'id_section_category_item' => $section_category_item,
				'cat_menu_is_img' => $is_img,
				'cat_menu_is_description' => $is_description,
				'cat_menu_is_price' => $is_price,
				'cat_menu_item_is_img' => $menu_item_is_img,
				'cat_menu_item_is_description' => $menu_item_is_description,
				'cat_menu_item_is_price' => $menu_item_is_price,
				'is_child' => 0,
				'is_content' => 1,
				'is_parents_0' => 0,
			);  
			$MenuTableAttr = array (
				'id' => $branch->id,
				'limit_item' => $limit_item,
				'cat_menu_is_custom_layout' => $custom_layout,
				'id_section_category' => $section_category,
				'id_section_category_item' => $section_category_item,
				'cat_menu_table_is_img' => $table_is_img,
				'cat_menu_table_is_description' => $table_is_description,
				'cat_menu_table_is_price' => $table_is_price,
				'cat_menu_table_is_heading' => $table_menu_is_heading,
				'cat_menu_table_item_is_img' => $table_menu_item_is_img,
				'cat_menu_table_item_is_description' => $table_menu_item_is_description,
				'cat_menu_table_item_is_price' => $table_menu_item_is_price,
				'is_child' => 0,
				'is_content' => 1,
				'is_parents_0' => 0,
			);
			if ($mac_menu_dp == 1 && $branch->is_table == 0) {
				$MenuTableAttr['class'] = ' show-card-price';
			}
			$index_cat_child = 0;
			if($parents_category == 0){
				if($branch->is_table == 0):
					$MenuAttr['is_parents_0'] = 1;
					if ($mac_menu_dp == 0) {
						$html .= $MenuHTML->render($MenuAttr);
					} else {
						$MenuTableAttr['is_parents_0'] = 1;
						$html .= $MenuTableHTML->render($MenuTableAttr);
					}
				else:
					$MenuTableAttr['is_parents_0'] = 1;
					$html .= $MenuTableHTML->render($MenuTableAttr);
				endif;

				if (isset($branch->children) && (empty($custom_layout) || $custom_layout == 'off' )  ) {
					$html .= $this->htmlModuleMenu($branch->children,$moduleInfo,$branch->id,$indexChildWrap+1 );     
				}
				
			}else{
				$html .='<div class="module-category-child-wrap module-category-child-'.$indexChildWrap.'-wrap">';


				$childMenuAttr = $MenuAttr;
				$childMenuAttr['id'] = $branch->id;
				$childMenuAttr['class'] = ' module-category-child module-category-child-index-'.$index_cat_child;
				$childMenuAttr['is_child'] = 1;
				$childMenuAttr['is_parents_0'] = 0;

				$childMenuTableAttr = $MenuTableAttr;
				$childMenuTableAttr['id'] = $branch->id;
				$childMenuTableAttr['class'] = ' module-category-child module-category-child-table-style module-category-child-index-'.$index_cat_child;
				$childMenuTableAttr['is_child'] = 1;
				$childMenuTableAttr['is_parents_0'] = 0;

				if($branch->is_table == 0):
					if ($mac_menu_dp == 0) {
						$html .= $MenuHTML->render($childMenuAttr);
					} else {
						$childMenuTableAttr['class'] .=' show-card-price';
						//$childMenuTableAttr['is_parents_0'] = 1;
						$html .= $MenuTableHTML->render($childMenuTableAttr);
					}
				else:
					$html .= $MenuTableHTML->render($childMenuTableAttr);
				endif;
				if (isset($branch->children)  ) {
					$html .='<div class="module-category-child-wrap module-category-child-'.($indexChildWrap+1).'-wrap">';
					$numberChildWrap = $indexChildWrap+1;
					foreach($branch->children as $item){

						$childMenuAttr = $MenuAttr;
						$childMenuAttr['id'] = $item->id;
						$childMenuAttr['class'] = ' module-category-child module-category-child-index-'.$index_cat_child;
						$childMenuAttr['is_child'] = 1;
						$childMenuAttr['is_parents_0'] = 0;

						$childMenuTableAttr = $MenuTableAttr;
						$childMenuTableAttr['id'] = $item->id;
						$childMenuTableAttr['class'] = ' module-category-child module-category-child-index-'.$index_cat_child;
						$childMenuTableAttr['is_child'] = 1;
						$childMenuTableAttr['is_parents_0'] = 0;

						if($item->is_table == 0):
							$html .= $MenuHTML->render($childMenuAttr);
						else:
							//$childMenuTableAttr['is_parents_0'] = 1;
							$html .= $MenuTableHTML->render($childMenuTableAttr);
						endif;
						$index_cat_child++;
						if( !empty($item->children) ):
						$html .= $this->htmlModuleMenu($item->children,$moduleInfo,$item->id,$numberChildWrap+1 ); 
						endif;
					}
					if($parents_category == 0):
						$html .= '</div><!-- module-category-child-wrap -->';
					else:
						$html .= '</div><!-- child-'.($indexChildWrap+1).' -->';
					endif;
				}
				$html .= '</div><!-- child-'.$indexChildWrap.' -->';
			}
			$catIndex++;
				
		}
		return $html;
	}
	private function htmlModuleMenu_new_html($tree,$moduleInfo,$parents_category = 0,$indexChildWrap = 0,$childCategorySelectIndex = null , $category_inside = 0) {
		$objmacMenu = new macMenu();
		$MenuHTML = new Mac_Category_Menu;
		$MenuTableHTML = new Mac_Category_Menu_Table;   
		$id_category = isset($moduleInfo['id_category']) ? $moduleInfo['id_category'] : array('all');
		$limit_item = isset($moduleInfo['limit_list_item']) ? $moduleInfo['limit_list_item'] : '';
		
		/** layout custom */
		$custom_layout = isset($moduleInfo['cat_menu_is_custom_layout']) ? $moduleInfo['cat_menu_is_custom_layout'] : '';
		$section_category = isset($moduleInfo['id_section_category']) ? $moduleInfo['id_section_category'] : '';
		$section_category_item = isset($moduleInfo['id_section_category_item']) ? $moduleInfo['id_section_category_item'] : '';

		/** Cat Menu Basic */
		$is_img = isset($moduleInfo['cat_menu_is_img']) ? $moduleInfo['cat_menu_is_img'] : '';
		$is_description = isset($moduleInfo['cat_menu_is_description']) ? $moduleInfo['cat_menu_is_description'] : '';
		$is_price = isset($moduleInfo['cat_menu_is_price']) ? $moduleInfo['cat_menu_is_price'] : '';
		$menu_item_is_img = isset($moduleInfo['cat_menu_item_is_img']) ? $moduleInfo['cat_menu_item_is_img'] : '';
		$menu_item_is_description = isset($moduleInfo['cat_menu_item_is_description']) ? $moduleInfo['cat_menu_item_is_description'] : '';
		$menu_item_is_price = isset($moduleInfo['cat_menu_item_is_price']) ? $moduleInfo['cat_menu_item_is_price'] : '';
		/** Cat Menu Table */
		$table_is_img = isset($moduleInfo['cat_menu_table_is_img']) ? $moduleInfo['cat_menu_table_is_img'] : '';
		$table_is_description = isset($moduleInfo['cat_menu_table_is_description']) ? $moduleInfo['cat_menu_table_is_description'] : '';
		$table_is_price = isset($moduleInfo['cat_menu_table_is_price']) ? $moduleInfo['cat_menu_table_is_price'] : '';
		$table_menu_is_heading = isset($moduleInfo['cat_menu_table_is_heading']) ? $moduleInfo['cat_menu_table_is_heading'] : '';
		$table_menu_item_is_img = isset($moduleInfo['cat_menu_table_item_is_img']) ? $moduleInfo['cat_menu_table_item_is_img'] : '';
		$table_menu_item_is_description = isset($moduleInfo['cat_menu_table_item_is_description']) ? $moduleInfo['cat_menu_table_item_is_description'] : '';
		$table_menu_item_is_price = isset($moduleInfo['cat_menu_table_item_is_price']) ? $moduleInfo['cat_menu_table_item_is_price'] : '';

		$html = '';
		$index_cat_child_wrap = 0;
		$catIndex = 0;
		if(isset($childCategorySelectIndex) && $childCategorySelectIndex != null):
			$catIndex = 'select-child-'.$childCategorySelectIndex;
		endif;
		$mac_menu_dp = get_option('mac_menu_dp');
		if (empty($mac_menu_dp)) {
			$mac_menu_dp = 0;
		}
		foreach ($tree as $branch) {
			
			$MenuAttr = array (
				'id' => $branch->id,
				'limit_item' => $limit_item,
				'cat_menu_is_custom_layout' => $custom_layout,
				'id_section_category' => $section_category,
				'id_section_category_item' => $section_category_item,
				'cat_menu_is_img' => $is_img,
				'cat_menu_is_description' => $is_description,
				'cat_menu_is_price' => $is_price,
				'cat_menu_item_is_img' => $menu_item_is_img,
				'cat_menu_item_is_description' => $menu_item_is_description,
				'cat_menu_item_is_price' => $menu_item_is_price,
				'is_child' => 0,
				'is_content' => 1,
				'is_parents_0' => 0,
			);  
			$MenuTableAttr = array (
				'id' => $branch->id,
				'limit_item' => $limit_item,
				'cat_menu_is_custom_layout' => $custom_layout,
				'id_section_category' => $section_category,
				'id_section_category_item' => $section_category_item,
				'cat_menu_table_is_img' => $table_is_img,
				'cat_menu_table_is_description' => $table_is_description,
				'cat_menu_table_is_price' => $table_is_price,
				'cat_menu_table_is_heading' => $table_menu_is_heading,
				'cat_menu_table_item_is_img' => $table_menu_item_is_img,
				'cat_menu_table_item_is_description' => $table_menu_item_is_description,
				'cat_menu_table_item_is_price' => $table_menu_item_is_price,
				'is_child' => 0,
				'is_content' => 1,
				'is_parents_0' => 0,
			);
			if ($mac_menu_dp == 1 && $branch->is_table == 0) {
				$MenuTableAttr['class'] = ' show-card-price';
			}
			$index_cat_child = 0;
			if($parents_category == 0){
				if($branch->is_table == 0):
					$MenuAttr['is_parents_0'] = 1;
					if ($mac_menu_dp == 0) {
						$html .= $MenuHTML->render($MenuAttr);
					} else {
						$MenuTableAttr['is_parents_0'] = 1;
						$html .= $MenuTableHTML->render($MenuTableAttr);
					}
				else:
					$MenuTableAttr['is_parents_0'] = 1;
					$html .= $MenuTableHTML->render($MenuTableAttr);
				endif;

				if (isset($branch->children) && (empty($custom_layout) || $custom_layout == 'off' )  ) {
					$html .= $this->htmlModuleMenu($branch->children,$moduleInfo,$branch->id,$indexChildWrap+1 );     
				}
				
			}else{
				
				if($branch->category_inside == 1 && $category_inside == 0){
					continue;
				}
				$html .='<div class="module-category-child-wrap module-category-child-'.$indexChildWrap.'-wrap">';


				$childMenuAttr = $MenuAttr;
				$childMenuAttr['id'] = $branch->id;
				$childMenuAttr['class'] = ' module-category-child module-category-child-index-'.$index_cat_child;
				$childMenuAttr['is_child'] = 1;
				$childMenuAttr['is_parents_0'] = 0;

				$childMenuTableAttr = $MenuTableAttr;
				$childMenuTableAttr['id'] = $branch->id;
				$childMenuTableAttr['class'] = ' module-category-child module-category-child-table-style module-category-child-index-'.$index_cat_child;
				$childMenuTableAttr['is_child'] = 1;
				$childMenuTableAttr['is_parents_0'] = 0;

				if($branch->is_table == 0):
					if ($mac_menu_dp == 0) {
						$html .= $MenuHTML->render($childMenuAttr);
					} else {
						$childMenuTableAttr['class'] .=' show-card-price';
						//$childMenuTableAttr['is_parents_0'] = 1;
						$html .= $MenuTableHTML->render($childMenuTableAttr);
					}
				else:
					$html .= $MenuTableHTML->render($childMenuTableAttr);
				endif;
				if (isset($branch->children)  ) {
					$html .='<div class="module-category-child-wrap module-category-child-'.($indexChildWrap+1).'-wrap">';
					$numberChildWrap = $indexChildWrap+1;
					foreach($branch->children as $item){

						$childMenuAttr = $MenuAttr;
						$childMenuAttr['id'] = $item->id;
						$childMenuAttr['class'] = ' module-category-child module-category-child-index-'.$index_cat_child;
						$childMenuAttr['is_child'] = 1;
						$childMenuAttr['is_parents_0'] = 0;

						$childMenuTableAttr = $MenuTableAttr;
						$childMenuTableAttr['id'] = $item->id;
						$childMenuTableAttr['class'] = ' module-category-child module-category-child-index-'.$index_cat_child;
						$childMenuTableAttr['is_child'] = 1;
						$childMenuTableAttr['is_parents_0'] = 0;

						if($item->is_table == 0):
							$html .= $MenuHTML->render($childMenuAttr);
						else:
							//$childMenuTableAttr['is_parents_0'] = 1;
							$html .= $MenuTableHTML->render($childMenuTableAttr);
						endif;
						$index_cat_child++;
						if( !empty($item->children) ):
						$html .= $this->htmlModuleMenu($item->children,$moduleInfo,$item->id,$numberChildWrap+1 ); 
						endif;
					}
					if($parents_category == 0):
						$html .= '</div><!-- module-category-child-wrap -->';
					else:
						$html .= '</div><!-- child-'.($indexChildWrap+1).' -->';
					endif;
				}
				$html .= '</div><!-- child-'.$indexChildWrap.' -->';
			}
			$catIndex++;
				
		}
		return $html;
	}
	private function buildTree(array $elements, $parentId = 0) {
		$branch = array();
		
		foreach ($elements as $element) {
			if ($element->parents_category == $parentId) {
				$children = $this->buildTree($elements, $element->id);
				if ($children) {
					$element->children = $children;
					foreach($children as $item){
						$element->childrenID[] = $item->id;
						$element->childrenParents_category[] = $item->parents_category;
						$element->childrenTable[] = $item->is_table;
					}
				}
				$branch[] = $element;
			}
		}
		return $branch;
	}

}