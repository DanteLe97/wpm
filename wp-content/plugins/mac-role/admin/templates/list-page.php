<?php
/**
 * Admin list page template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap rud-admin-wrap">
	<h1 class="rud-page-title">
		<?php _e( 'Role URL Links', 'role-url-dashboard' ); ?>
		<a href="<?php echo admin_url( 'admin.php?page=role-links-add' ); ?>" class="page-title-action">
			<?php _e( 'Add New', 'role-url-dashboard' ); ?>
		</a>
	</h1>
	
	<?php if ( isset( $_GET['added'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php _e( 'Link added successfully!', 'role-url-dashboard' ); ?></p>
		</div>
	<?php endif; ?>
	
	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php _e( 'Link updated successfully!', 'role-url-dashboard' ); ?></p>
		</div>
	<?php endif; ?>
	
	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php _e( 'Link deleted successfully!', 'role-url-dashboard' ); ?></p>
		</div>
	<?php endif; ?>
	
	<div class="rud-filters">
		<form method="get" action="">
			<input type="hidden" name="page" value="role-links">
			
			<select name="entity_type" style="margin-right: 10px;">
				<option value=""><?php _e( 'All Types', 'role-url-dashboard' ); ?></option>
				<option value="role" <?php selected( $entity_type, 'role' ); ?>><?php _e( 'Role', 'role-url-dashboard' ); ?></option>
				<option value="user" <?php selected( $entity_type, 'user' ); ?>><?php _e( 'User', 'role-url-dashboard' ); ?></option>
			</select>
			
			<input type="text" name="s" placeholder="<?php esc_attr_e( 'Search...', 'role-url-dashboard' ); ?>" value="<?php echo esc_attr( $search ); ?>" style="margin-right: 10px;">
			
			<?php submit_button( __( 'Filter', 'role-url-dashboard' ), 'secondary', '', false ); ?>
		</form>
	</div>
	
	<form method="post" action="" id="rud-bulk-form">
		<?php wp_nonce_field( 'rud-bulk-action' ); ?>
		
		<div class="rud-bulk-actions" style="margin: 20px 0;">
			<select name="action" id="bulk-action-selector">
				<option value="-1"><?php _e( 'Bulk Actions', 'role-url-dashboard' ); ?></option>
				<option value="activate"><?php _e( 'Activate', 'role-url-dashboard' ); ?></option>
				<option value="deactivate"><?php _e( 'Deactivate', 'role-url-dashboard' ); ?></option>
				<option value="delete"><?php _e( 'Delete', 'role-url-dashboard' ); ?></option>
			</select>
			<?php submit_button( __( 'Apply', 'role-url-dashboard' ), 'action', '', false ); ?>
		</div>
		
		<div class="rud-mappings-grid">
			<?php if ( empty( $mappings ) ) : ?>
				<p><?php _e( 'No links found.', 'role-url-dashboard' ); ?></p>
			<?php else : ?>
				<?php foreach ( $mappings as $mapping ) : 
					$is_default = isset( $mapping['is_default'] ) && $mapping['is_default'];
					$default_link_id = isset( $mapping['default_link_id'] ) ? $mapping['default_link_id'] : '';
					$default_link_enabled = isset( $mapping['default_link_enabled'] ) ? $mapping['default_link_enabled'] : true;
					
					// Get default link data for edit
					$default_link_data = null;
					if ( $is_default && $default_link_id ) {
						$default_links_all = RUD_Default_Links::get_default_links_with_status();
						foreach ( $default_links_all as $dl ) {
							if ( $dl['id'] === $default_link_id ) {
								$default_link_data = $dl;
								break;
							}
						}
					}
				?>
					<div class="rud-mapping-card <?php echo $mapping['active'] ? '' : 'inactive'; ?> <?php echo $is_default ? 'rud-default-link-card' : ''; ?>" 
					     <?php if ( $is_default ) : ?>data-link-id="<?php echo esc_attr( $default_link_id ); ?>"<?php endif; ?>>
						<input type="checkbox" name="bulk_ids[]" value="<?php echo esc_attr( $mapping['id'] ); ?>" class="rud-bulk-checkbox">
						
						<?php if ( $is_default ) : ?>
							<span class="rud-default-badge" style="position: absolute; top: 10px; right: 35px; background: #ff5c02; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">
								<?php _e( 'Default', 'role-url-dashboard' ); ?>
							</span>
						<?php endif; ?>
						
						<div class="rud-card-content">
							<?php if ( ! empty( $mapping['icon'] ) ) : ?>
							<div class="rud-card-icon">
								<?php echo esc_html( $mapping['icon'] ); ?>
							</div>
							<?php endif; ?>
							
							<h3 class="rud-card-title"><?php echo esc_html( $mapping['label'] ); ?></h3>
							
							<div class="rud-card-meta">
								<span class="rud-entity-type">
									<?php if ( $is_default && isset( $mapping['default_roles'] ) && count( $mapping['default_roles'] ) > 1 ) : ?>
										<?php echo esc_html( ucfirst( $mapping['entity_type'] ) ); ?>: <?php echo esc_html( implode( ', ', $mapping['default_roles'] ) ); ?>
									<?php else : ?>
										<?php echo esc_html( ucfirst( $mapping['entity_type'] ) ); ?>: <?php echo esc_html( $mapping['entity'] ); ?>
									<?php endif; ?>
								</span>
								<div style="display: flex; align-items: center; gap: 10px;">
									<span class="rud-status <?php echo $mapping['active'] ? 'active' : 'inactive'; ?>">
										<?php echo $mapping['active'] ? __( 'Active', 'role-url-dashboard' ) : __( 'Inactive', 'role-url-dashboard' ); ?>
									</span>
									<?php if ( $is_default ) : ?>
										<label class="rud-toggle-switch" style="position: relative; display: inline-block; width: 40px; height: 20px;">
											<input type="checkbox" class="rud-default-link-toggle" 
											       data-link-id="<?php echo esc_attr( $default_link_id ); ?>"
											       <?php checked( $default_link_enabled, true ); ?>>
											<span class="rud-toggle-slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 20px;"></span>
										</label>
									<?php endif; ?>
								</div>
							</div>
							
							<div class="rud-card-url">
								<code><?php echo esc_html( $mapping['url'] ); ?></code>
							</div>
							
							<?php if ( ! empty( $mapping['description'] ) ) : ?>
								<p class="rud-card-description"><?php echo esc_html( $mapping['description'] ); ?></p>
							<?php endif; ?>
							
							<div class="rud-card-actions">
								<?php if ( $is_default && $default_link_data ) : ?>
									<button type="button" class="button button-small rud-edit-default-link" 
									        data-link-id="<?php echo esc_attr( $default_link_id ); ?>"
									        data-label="<?php echo esc_attr( $default_link_data['label'] ); ?>"
									        data-icon="<?php echo esc_attr( $default_link_data['icon'] ); ?>"
									        data-url="<?php echo esc_attr( $default_link_data['url'] ); ?>"
									        data-description="<?php echo esc_attr( $default_link_data['description'] ); ?>"
									        data-priority="<?php echo esc_attr( $default_link_data['priority'] ); ?>">
										<?php _e( 'Edit', 'role-url-dashboard' ); ?>
									</button>
								<?php else : ?>
									<a href="<?php echo admin_url( 'admin.php?page=role-links-add&id=' . $mapping['id'] ); ?>" class="button button-small">
										<?php _e( 'Edit', 'role-url-dashboard' ); ?>
									</a>
								<?php endif; ?>
								
								<?php if ( ! $is_default ) : ?>
									<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=role-links&action=delete&id=' . $mapping['id'] ), 'rud-delete-' . $mapping['id'] ); ?>" 
									   class="button button-small button-link-delete rud-delete-link"
									   onclick="return confirm('<?php esc_attr_e( 'Are you sure?', 'role-url-dashboard' ); ?>');">
										<?php _e( 'Delete', 'role-url-dashboard' ); ?>
									</a>
								<?php else : ?>
									<span class="button button-small" style="opacity: 0.5; cursor: not-allowed;" title="<?php esc_attr_e( 'Default links cannot be deleted', 'role-url-dashboard' ); ?>">
										<?php _e( 'Delete', 'role-url-dashboard' ); ?>
									</span>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</form>
</div>

