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
class Elementor_Dynamic_Tag_Mac_Menu_List_Item extends \Elementor\Core\DynamicTags\Tag {

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
		return 'mac-menu-list-item';
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
		return esc_html__( 'List Item', 'mac-plugin' );
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
		if( $current_category != '' && isset($customArray['id'])):
			$id = $customArray['id'];
		endif;
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

		if(!isset($id) || $id == '' ):
			$id = 1;
			//return;
		endif;
		$headingArray = [];
		$block_str = '';
		$this->renderListItemHtml($id,
									$limit_item,
									$menu_item_is_img,
									$menu_item_is_description,
									$menu_item_is_price,
									$table_is_heading,
									$table_menu_item_is_img,
									$table_menu_item_is_description,
									$table_menu_item_is_price
								);
	}

	public function renderListItemHtml($id,
										$limit_item = '',
										$menu_item_is_img = '',
										$menu_item_is_description = '',
										$menu_item_is_price = '',
										$table_is_heading = '',
										$table_menu_item_is_img = '',
										$table_menu_item_is_description = '',
										$table_menu_item_is_price = ''
										) {
		$objmacMenu = new macMenu();
		$isCat = $objmacMenu->find_cat_menu($id);
		?>
		<?php $json = isset($isCat[0]->group_repeater) ? $isCat[0]->group_repeater : ""; 
			if( ($isCat[0]->group_repeater) != "[]"):
		?>
		<?php if($isCat[0]->is_table == 0){ ?>
				<div class="module-category__list-item" data-limit="<?= $limit_item ?>">
					<?php 
						$data = json_decode($json, true);
						if (is_array($data)) {
							$index = 0;
							$catIndex = 0;
							foreach ($data as $item) {
								if($limit_item != '' && $limit_item != '0' ):
									if( $index < $limit_item ):
										$index++;
									else:
										break;
									endif;
								endif;
								
								if (isset($item['fullwidth']) && !empty($item['fullwidth'])) {
									echo '<div class="module-category-item module-category-item-index-'.$catIndex.'">';
								}
								else{
									echo '<div class="module-category-item module-category-item-index-'.$catIndex.' item-not-fw">';
								}
								$catIndex++;
								/** custom layout / layout default */
									if (isset($item['featured_img']) && !empty($item['featured_img']) && $menu_item_is_img != 'off') {
										echo '<div class="module-category-item__img">';
										echo '<img src="'.$item['featured_img'].'" alt="image">';
										echo '</div>';
									}
									echo '<div class="module-category-item__text">';
									echo '<div class="module-category-item__head">';
										if (isset($item['name']) && !empty($item['name'])) {
											echo '<span class="module-category-item__name">'.$item['name'].'</span>';
										}
										if(isset($item['price-list']) && $menu_item_is_price != 'off'  ):
											foreach( $item['price-list'] as $itemPrice ){
												if( !empty($itemPrice['price']) ):
												echo '<div class="module-category-item__price">'.$itemPrice['price'].'</div>';
												endif;
											}
										endif;
									echo '</div>';
									if (isset($item['description']) && !empty($item['description']) && ($menu_item_is_description != 'off')  ) {
										echo '<div class="module-category-item__description">'.$item['description'].'</div>';
									}
									echo '</div>';
								echo '</div><!-- cat-menu-item -->';
								
							}
						}

					?>
				</div><!-- cat-menu-list-item -->
		<?php }else { ?>
				<div class="module-category-table-wrap" style="width:100%">
					<table  class="module-category-table">
						<tbody>
						<tr class="module-category__heading">
							<td></td>
							<?php
								$jsonHeading = isset($isCat[0]->data_table) ? $isCat[0]->data_table : [];
								$dataHeading = json_decode($jsonHeading, true);
								if (is_array($dataHeading) && $table_is_heading != 'off' ) {
									
									foreach ($dataHeading as $item) {
										echo '<td>';
										if (isset($item)) {
											echo ''. $item;
										}
										echo '</td>';
									}
								}
							?>
						</tr>
						
						<?php 
							$json = isset($isCat[0]->group_repeater) ? $isCat[0]->group_repeater : [];
							$data = json_decode($json, true);
							
							$tocalPrice = 0;
							if (is_array($data)) {
								$index = 0;
								$catIndex = 0;
								foreach ($data as $item) {

									if($limit_item != '' && $limit_item != '0' ):
										if( $index < $limit_item ):
											$index++;
										else:
											break;
										endif;
									endif;
									$catIndex++;
									echo '<tr class="module-category-item">';
										echo '<td class="module-category-item__content">';
										if (isset($item['featured_img']) && !empty($item['featured_img']) && $table_menu_item_is_img != 'off' ) {
											echo '<div class="module-category-item__img"><img src="'.$item['featured_img'].'" alt="image"></div>';
										}
										echo '<div class="module-category-item__text">';
											if (isset($item['name']) && !empty($item['name'])) {
												echo '<span class="module-category-item__name">'.$item['name'].'</span>';
											}
											if (isset($item['description']) && !empty($item['description']) && $table_menu_item_is_description != 'off' ) {
												echo '<div class="module-category-item__description">'.$item['description'].'</div>';
											}
										echo '</div><!-- .module-category__text -->';
									echo '</td><!-- .module-category-item__content -->';

									if(isset($item['price-list']) && $table_menu_item_is_price != 'off'):
										$indexPrice = 0;
										foreach( $item['price-list'] as $itemPrice ){
											echo '<td class="module-category-item__price">'.$itemPrice['price'].'</td>';
											$indexPrice++;
											if($indexPrice > $tocalPrice ):
												$tocalPrice++;
											endif;
											
										}
										if(count($dataHeading) >= $tocalPrice ):
											if(count($dataHeading) > 1 ):
												for( $i = 1; $i <= (count($dataHeading) - 1) ; $i++ ):
													if($i >= count($item['price-list']) ):
														echo '<td class="module-category-item__price"></td>';
													endif;
												endfor;
											endif;
										else:
											if($tocalPrice > 1):
												for( $i = 0; $i <= $tocalPrice ; $i++ ):
													if($i > count($item['price-list']) ):
														echo '<td class="module-category-item__price"></td>';
													endif;
												endfor;
											endif;
										endif;
										
									endif;
									echo '</tr><!-- .module-category-item -->';
									
								}
							}

						?>
						
						</tbody>
					</table>
				</div>
		<?php } ?>
		<?php endif; ?>
		<?php
	}

}