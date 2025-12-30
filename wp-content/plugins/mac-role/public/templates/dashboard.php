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
	<div class="rud-breadcrumb">
		<a href="<?php echo admin_url( 'index.php' ); ?>">
			<?php _e( 'Dashboard', 'role-url-dashboard' ); ?>
		</a>
		<span class="rud-breadcrumb-separator">/</span>
		<span class="rud-breadcrumb-current">
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
	
	<div class="rud-dashboard-header">
		<h1 class="rud-dashboard-title">
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
			   class="button button-primary button-large rud-back-dashboard">
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
			<p>
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
					<div class="rud-tile-icon">
						<?php echo esc_html( $icon ); ?>
					</div>
					<?php endif; ?>
					
					<h3 class="rud-tile-title">
						<?php echo esc_html( $main_mapping['label'] ); ?>
					</h3>
					
					<?php if ( ! empty( $main_mapping['description'] ) ) : ?>
						<p class="rud-tile-description">
							<?php echo esc_html( $main_mapping['description'] ); ?>
						</p>
					<?php endif; ?>
					
					<?php if ( ! empty( $submenu_items ) ) : ?>
						<div class="rud-tile-submenu">
							<div class="rud-submenu-title">
								<?php _e( 'Submenu:', 'role-url-dashboard' ); ?>
							</div>
							<div class="rud-submenu-links">
								<?php foreach ( $submenu_items as $submenu_item ) : 
									$submenu_url = RUD_Helpers::get_admin_url( $submenu_item['url'] );
									$submenu_behavior = $submenu_item['open_behavior'];
								?>
									<a href="<?php echo esc_url( $submenu_url ); ?>" 
									   class="rud-submenu-link" 
									   data-url="<?php echo esc_attr( $submenu_url ); ?>"
									   data-behavior="<?php echo esc_attr( $submenu_behavior ); ?>">
										<?php echo esc_html( $submenu_item['label'] ); ?>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
					
					<div class="rud-tile-action">
						<button class="rud-open-button">
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
					<div class="rud-tile-icon">
						<?php echo esc_html( $icon ); ?>
					</div>
					<?php endif; ?>
					
					<h3 class="rud-tile-title">
						<?php echo esc_html( $mapping['label'] ); ?>
					</h3>
					
					<?php if ( ! empty( $mapping['description'] ) ) : ?>
						<p class="rud-tile-description">
							<?php echo esc_html( $mapping['description'] ); ?>
						</p>
					<?php endif; ?>
					
					<?php if ( ! empty( $additional_urls ) ) : ?>
						<div class="rud-tile-submenu">
							<div class="rud-submenu-title">
								<?php _e( 'Submenu:', 'role-url-dashboard' ); ?>
							</div>
							<div class="rud-submenu-links">
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
									   data-behavior="<?php echo esc_attr( $data_behavior ); ?>">
										<?php echo esc_html( $submenu_label ); ?>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
					
					<div class="rud-tile-action">
						<button class="rud-open-button">
							<?php _e( 'Open', 'role-url-dashboard' ); ?>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<?php if ( ! empty( $mappings ) ) : ?>
	<div id="rud-iframe-modal">
		<div class="rud-iframe-modal-content">
			<button id="rud-close-iframe">&times;</button>
			<iframe id="rud-iframe-content" src=""></iframe>
		</div>
	</div>
<?php endif; ?>

