<?php
/**
 * User dashboard template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap rud-dashboard-wrap">
	<?php 
	// Only show breadcrumb if NOT on dashboard page
	global $pagenow;
	$current_page = isset( $_GET['page'] ) ? $_GET['page'] : '';
	$is_dashboard = ( $pagenow === 'index.php' ) || ( $current_page === 'role-links-dashboard' );
	
	if ( ! $is_dashboard ) : 
	?>
	<!-- Breadcrumb -->
	<div class="rud-breadcrumb" style="margin-bottom: 20px; padding: 10px 0; border-bottom: 1px solid #ddd;">
		<a href="<?php echo admin_url( 'index.php' ); ?>" 
		   style="text-decoration: none; color: #ff5c02; font-size: 14px;">
			<?php _e( 'Dashboard', 'role-url-dashboard' ); ?>
		</a>
		<span style="margin: 0 8px; color: #666;">/</span>
		<span style="color: #666; font-size: 14px;">
			<?php 
			// Get current page title from WordPress admin menu
			global $submenu, $menu;
			$page_title = '';
			if ( isset( $_GET['page'] ) ) {
				$page_slug = sanitize_text_field( $_GET['page'] );
				foreach ( $menu as $menu_item ) {
					if ( isset( $menu_item[2] ) && $menu_item[2] === $page_slug ) {
						$page_title = strip_tags( $menu_item[0] );
						break;
					}
				}
				if ( empty( $page_title ) && isset( $submenu ) ) {
					foreach ( $submenu as $parent => $submenu_items ) {
						foreach ( $submenu_items as $submenu_item ) {
							if ( isset( $submenu_item[2] ) && $submenu_item[2] === $page_slug ) {
								$page_title = strip_tags( $submenu_item[0] );
								break 2;
							}
						}
					}
				}
			}
			echo ! empty( $page_title ) ? esc_html( $page_title ) : __( 'Current Page', 'role-url-dashboard' );
			?>
		</span>
	</div>
	<?php endif; ?>
	
	<div class="rud-dashboard-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
		<h1 class="rud-dashboard-title" style="font-size: 28px; margin: 0;">
			<?php _e( 'My Dashboard', 'role-url-dashboard' ); ?>
		</h1>
		<?php 
		// Only show back button if NOT on dashboard page (index.php or role-links-dashboard)
		global $pagenow;
		$current_page = isset( $_GET['page'] ) ? $_GET['page'] : '';
		$is_dashboard = ( $pagenow === 'index.php' ) || ( $current_page === 'role-links-dashboard' );
		
		if ( ! $is_dashboard ) : 
		?>
			<a href="<?php echo admin_url( 'index.php' ); ?>" 
			   class="button button-primary button-large rud-back-dashboard" 
			   style="font-size: 16px; padding: 12px 24px; text-decoration: none; white-space: nowrap;">
				<?php _e( '← Quay về Dashboard', 'role-url-dashboard' ); ?>
			</a>
		<?php endif; ?>
	</div>
	
	<?php 
	// Ensure variables are arrays
	$standalone_mappings = isset( $standalone_mappings ) && is_array( $standalone_mappings ) ? $standalone_mappings : array();
	$grouped_mappings = isset( $grouped_mappings ) && is_array( $grouped_mappings ) ? $grouped_mappings : array();
	
	$total_items = count( $standalone_mappings ) + count( $grouped_mappings );
	if ( $total_items === 0 ) : 
	?>
		<div class="rud-empty-state">
			<p style="font-size: 18px; color: #666;">
				<?php _e( 'No links available. Please contact your administrator.', 'role-url-dashboard' ); ?>
			</p>
		</div>
	<?php else : ?>
		<div class="rud-tiles-grid">
			<?php 
			// Display grouped mappings (with submenu)
			foreach ( $grouped_mappings as $prefix => $group_items ) : 
				// First item is the main link
				$main_mapping = $group_items[0];
				$submenu_items = array_slice( $group_items, 1 );
				
				$admin_url = RUD_Helpers::get_admin_url( $main_mapping['url'] );
				$icon = ! empty( $main_mapping['icon'] ) ? $main_mapping['icon'] : '';
				$data_behavior = $main_mapping['open_behavior'];
			?>
				<div class="rud-tile rud-tile-grouped" 
				     data-url="<?php echo esc_attr( $admin_url ); ?>"
				     data-behavior="<?php echo esc_attr( $data_behavior ); ?>"
				     role="button"
				     tabindex="0"
				     aria-label="<?php echo esc_attr( $main_mapping['label'] ); ?>">
					
					<?php if ( ! empty( $icon ) ) : ?>
					<div class="rud-tile-icon" style="font-size: 48px; margin-bottom: 15px;">
						<?php echo esc_html( $icon ); ?>
					</div>
					<?php endif; ?>
					
					<h3 class="rud-tile-title" style="font-size: 20px; font-weight: bold; margin: 0 0 10px 0;">
						<?php echo esc_html( $main_mapping['label'] ); ?>
					</h3>
					
					<?php if ( ! empty( $main_mapping['description'] ) ) : ?>
						<p class="rud-tile-description" style="font-size: 16px; color: #666; margin: 0 0 15px 0;">
							<?php echo esc_html( $main_mapping['description'] ); ?>
						</p>
					<?php endif; ?>
					
					<?php if ( ! empty( $submenu_items ) ) : ?>
						<div class="rud-tile-submenu" style="margin: 15px 0; padding-top: 15px; border-top: 1px solid #e0e0e0; width: 100%;">
							<div style="font-size: 14px; font-weight: bold; color: #666; margin-bottom: 10px; text-align: left;">
								<?php _e( 'Submenu:', 'role-url-dashboard' ); ?>
							</div>
							<div class="rud-submenu-links" style="display: flex; flex-direction: column; gap: 8px; text-align: left;">
								<?php foreach ( $submenu_items as $submenu_item ) : 
									$submenu_url = RUD_Helpers::get_admin_url( $submenu_item['url'] );
									$submenu_behavior = $submenu_item['open_behavior'];
								?>
									<a href="<?php echo esc_url( $submenu_url ); ?>" 
									   class="rud-submenu-link" 
									   data-url="<?php echo esc_attr( $submenu_url ); ?>"
									   data-behavior="<?php echo esc_attr( $submenu_behavior ); ?>"
									   style="display: block; padding: 8px 12px; background: #f5f5f5; border-radius: 4px; text-decoration: none; color: #ff5c02; font-size: 14px; transition: background 0.2s;">
										<?php echo esc_html( $submenu_item['label'] ); ?>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
					
					<div class="rud-tile-action" style="margin-top: 15px;">
						<button class="rud-open-button" style="font-size: 16px; padding: 12px 24px; background: #ff5c02; color: white; border: none; border-radius: 4px; cursor: pointer;">
							<?php _e( 'Open', 'role-url-dashboard' ); ?>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
			
			<?php 
			// Display standalone mappings (no submenu)
			foreach ( $standalone_mappings as $mapping ) : 
				$admin_url = RUD_Helpers::get_admin_url( $mapping['url'] );
				$icon = ! empty( $mapping['icon'] ) ? $mapping['icon'] : '';
				$target = $mapping['open_behavior'] === 'new' ? '_blank' : '_self';
				$data_behavior = $mapping['open_behavior'];
				$meta = ! empty( $mapping['meta'] ) ? json_decode( $mapping['meta'], true ) : array();
				$additional_urls = isset( $meta['additional_urls'] ) && is_array( $meta['additional_urls'] ) ? $meta['additional_urls'] : array();
			?>
				<div class="rud-tile" 
				     data-url="<?php echo esc_attr( $admin_url ); ?>"
				     data-behavior="<?php echo esc_attr( $data_behavior ); ?>"
				     role="button"
				     tabindex="0"
				     aria-label="<?php echo esc_attr( $mapping['label'] ); ?>">
					
					<?php if ( ! empty( $icon ) ) : ?>
					<div class="rud-tile-icon" style="font-size: 48px; margin-bottom: 15px;">
						<?php echo esc_html( $icon ); ?>
					</div>
					<?php endif; ?>
					
					<h3 class="rud-tile-title" style="font-size: 20px; font-weight: bold; margin: 0 0 10px 0;">
						<?php echo esc_html( $mapping['label'] ); ?>
					</h3>
					
					<?php if ( ! empty( $mapping['description'] ) ) : ?>
						<p class="rud-tile-description" style="font-size: 16px; color: #666; margin: 0;">
							<?php echo esc_html( $mapping['description'] ); ?>
						</p>
					<?php endif; ?>
					
					<?php if ( ! empty( $additional_urls ) ) : ?>
						<div class="rud-tile-submenu" style="margin: 15px 0; padding-top: 15px; border-top: 1px solid #e0e0e0; width: 100%;">
							<div style="font-size: 14px; font-weight: bold; color: #666; margin-bottom: 10px; text-align: left;">
								<?php _e( 'Submenu:', 'role-url-dashboard' ); ?>
							</div>
							<div class="rud-submenu-links" style="display: flex; flex-direction: column; gap: 8px; text-align: left;">
								<?php foreach ( $additional_urls as $add_url ) : 
									$submenu_url = RUD_Helpers::get_admin_url( $add_url );
									// Get label from WordPress admin menu
									$submenu_label = RUD_Helpers::get_menu_label_from_url( $add_url );
									// Fallback to URL if no label found
									$submenu_label = ! empty( $submenu_label ) ? $submenu_label : $add_url;
								?>
									<a href="<?php echo esc_url( $submenu_url ); ?>" 
									   class="rud-submenu-link" 
									   data-url="<?php echo esc_attr( $submenu_url ); ?>"
									   data-behavior="<?php echo esc_attr( $data_behavior ); ?>"
									   style="display: block; padding: 8px 12px; background: #f5f5f5; border-radius: 4px; text-decoration: none; color: #ff5c02; font-size: 14px; transition: background 0.2s;">
										<?php echo esc_html( $submenu_label ); ?>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
					
					<div class="rud-tile-action" style="margin-top: 15px;">
						<button class="rud-open-button" style="font-size: 16px; padding: 12px 24px; background: #ff5c02; color: white; border: none; border-radius: 4px; cursor: pointer;">
							<?php _e( 'Open', 'role-url-dashboard' ); ?>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<?php if ( ! empty( $mappings ) ) : ?>
	<div id="rud-iframe-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 999999;">
		<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 1200px; height: 90%; background: white; border-radius: 8px; padding: 20px;">
			<button id="rud-close-iframe" style="float: right; font-size: 24px; background: none; border: none; cursor: pointer; padding: 5px 15px;">&times;</button>
			<iframe id="rud-iframe-content" src="" style="width: 100%; height: calc(100% - 40px); border: none; margin-top: 20px;"></iframe>
		</div>
	</div>
<?php endif; ?>

