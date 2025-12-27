<?php
/**
 * Admin edit page template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit = ! empty( $mapping );
$roles = RUD_Helpers::get_all_roles();

// Parse meta for additional data
$meta = ! empty( $mapping['meta'] ) ? json_decode( $mapping['meta'], true ) : array();
$additional_urls = isset( $meta['additional_urls'] ) ? $meta['additional_urls'] : array();
$multiple_entities = isset( $meta['multiple_entities'] ) ? $meta['multiple_entities'] : array();

// Get current entities
$current_entities = array();
if ( $mapping ) {
	if ( $mapping['entity_type'] === 'role' ) {
		$current_entities = isset( $multiple_entities['roles'] ) ? $multiple_entities['roles'] : array( $mapping['entity'] );
	} else {
		$current_entities = isset( $multiple_entities['users'] ) ? $multiple_entities['users'] : array( $mapping['entity'] );
	}
}
?>

<div class="wrap rud-admin-wrap">
	<h1 class="rud-page-title">
		<?php echo $is_edit ? __( 'Edit Link', 'role-url-dashboard' ) : __( 'Add New Link', 'role-url-dashboard' ); ?>
	</h1>
	
	<form method="post" action="" class="rud-edit-form">
		<?php wp_nonce_field( 'rud-save-mapping' ); ?>
		
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="mapping_id" value="<?php echo esc_attr( $mapping['id'] ); ?>">
		<?php endif; ?>
		
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="entity_type"><?php _e( 'Type', 'role-url-dashboard' ); ?></label>
					</th>
					<td>
						<select name="entity_type" id="entity_type" required style="font-size: 16px; padding: 8px;">
							<option value="role" <?php selected( $mapping ? $mapping['entity_type'] : 'role', 'role' ); ?>>
								<?php _e( 'Role', 'role-url-dashboard' ); ?>
							</option>
							<option value="user" <?php selected( $mapping ? $mapping['entity_type'] : 'user', 'user' ); ?>>
								<?php _e( 'User', 'role-url-dashboard' ); ?>
							</option>
						</select>
					</td>
				</tr>
				
				<tr id="entity-role-row">
					<th scope="row">
						<label for="entity_role"><?php _e( 'Role(s)', 'role-url-dashboard' ); ?></label>
					</th>
					<td>
						<select name="entity_role[]" id="entity_role" multiple size="5" style="font-size: 16px; padding: 8px; min-width: 300px;">
							<?php foreach ( $roles as $role_slug => $role_name ) : ?>
								<option value="<?php echo esc_attr( $role_slug ); ?>" 
									<?php selected( in_array( $role_slug, $current_entities ), true ); ?>>
									<?php echo esc_html( $role_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php _e( 'Giữ Ctrl (Windows) hoặc Cmd (Mac) để chọn nhiều roles', 'role-url-dashboard' ); ?>
						</p>
					</td>
				</tr>
				
				<tr id="entity-user-row" style="display: none;">
					<th scope="row">
						<label for="entity_user"><?php _e( 'User ID(s)', 'role-url-dashboard' ); ?></label>
					</th>
					<td>
						<textarea name="entity_user" id="entity_user" rows="3" 
						          style="font-size: 16px; padding: 8px; min-width: 300px;"
						          placeholder="<?php esc_attr_e( 'Nhập User IDs, mỗi ID một dòng (ví dụ: 1, 2, 3)', 'role-url-dashboard' ); ?>"><?php 
							if ( $mapping && $mapping['entity_type'] === 'user' ) {
								if ( isset( $multiple_entities['users'] ) && is_array( $multiple_entities['users'] ) ) {
									echo esc_textarea( implode( "\n", $multiple_entities['users'] ) );
								} else {
									echo esc_textarea( $mapping['entity'] );
								}
							}
						?></textarea>
						<p class="description">
							<?php _e( 'Nhập User IDs, mỗi ID một dòng', 'role-url-dashboard' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="label"><?php _e( 'Label', 'role-url-dashboard' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="text" name="label" id="label" 
						       value="<?php echo $mapping ? esc_attr( $mapping['label'] ) : ''; ?>"
						       required style="font-size: 16px; padding: 8px; width: 100%; max-width: 500px;">
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="url"><?php _e( 'Admin URL', 'role-url-dashboard' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<div class="rud-combobox-wrapper" style="position: relative; max-width: 500px;">
							<input type="text" name="url" id="url" 
							       value="<?php echo $mapping ? esc_attr( $mapping['url'] ) : ''; ?>"
							       required style="font-size: 16px; padding: 8px 40px 8px 8px; width: 100%;"
							       placeholder="<?php esc_attr_e( 'Chọn từ menu hoặc gõ để tìm kiếm/ nhập URL', 'role-url-dashboard' ); ?>"
							       autocomplete="off">
							<button type="button" class="rud-dropdown-toggle" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 5px; font-size: 18px; color: #666;" title="<?php esc_attr_e( 'Xem danh sách menu', 'role-url-dashboard' ); ?>">
								▼
							</button>
							<div class="rud-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #8c8f94; border-radius: 4px; max-height: 300px; overflow-y: auto; z-index: 1000; margin-top: 2px; box-shadow: 0 2px 8px rgba(0,0,0,.15);">
								<div class="rud-dropdown-search" style="padding: 8px; border-bottom: 1px solid #f0f0f1;">
									<input type="text" class="rud-menu-search" placeholder="<?php esc_attr_e( 'Tìm kiếm...', 'role-url-dashboard' ); ?>" style="width: 100%; padding: 6px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 3px;">
								</div>
								<div class="rud-dropdown-list"></div>
							</div>
						</div>
						<p class="description">
							<?php _e( 'Click vào mũi tên để chọn từ menu, hoặc gõ để tìm kiếm/ nhập URL thủ công. Các URL liên quan sẽ tự động được cho phép (post-new.php, post.php, etc.)', 'role-url-dashboard' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="additional_urls"><?php _e( 'Additional URLs', 'role-url-dashboard' ); ?></label>
					</th>
					<td>
						<div id="additional-urls-container">
							<?php if ( ! empty( $additional_urls ) ) : ?>
								<?php foreach ( $additional_urls as $index => $add_url ) : ?>
									<div class="rud-url-field" style="margin-bottom: 10px;">
										<div class="rud-combobox-wrapper" style="position: relative; max-width: 450px; display: inline-block;">
											<input type="text" name="additional_urls[]" 
											       value="<?php echo esc_attr( $add_url ); ?>"
											       class="rud-additional-url-input"
											       style="font-size: 16px; padding: 8px 40px 8px 8px; width: 100%;"
											       placeholder="<?php esc_attr_e( 'Chọn từ menu hoặc gõ để tìm kiếm/ nhập URL', 'role-url-dashboard' ); ?>"
											       autocomplete="off">
											<button type="button" class="rud-dropdown-toggle" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 5px; font-size: 18px; color: #666;" title="<?php esc_attr_e( 'Xem danh sách menu', 'role-url-dashboard' ); ?>">
												▼
											</button>
											<div class="rud-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #8c8f94; border-radius: 4px; max-height: 300px; overflow-y: auto; z-index: 1000; margin-top: 2px; box-shadow: 0 2px 8px rgba(0,0,0,.15);">
												<div class="rud-dropdown-search" style="padding: 8px; border-bottom: 1px solid #f0f0f1;">
													<input type="text" class="rud-menu-search" placeholder="<?php esc_attr_e( 'Tìm kiếm...', 'role-url-dashboard' ); ?>" style="width: 100%; padding: 6px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 3px;">
												</div>
												<div class="rud-dropdown-list"></div>
											</div>
										</div>
										<button type="button" class="button rud-remove-url" style="margin-left: 5px; vertical-align: top; margin-top: 0;"><?php _e( 'Xóa', 'role-url-dashboard' ); ?></button>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
						<button type="button" id="rud-add-url" class="button" style="margin-top: 10px;">
							<?php _e( '+ Thêm URL', 'role-url-dashboard' ); ?>
						</button>
						<button type="button" id="rud-auto-submenu" class="button" style="margin-top: 10px; margin-left: 8px;">
							<?php _e( 'Tự động lấy submenu', 'role-url-dashboard' ); ?>
						</button>
						<p class="description">
							<?php _e( 'Thêm các URL bổ sung nếu cần. Click vào mũi tên để chọn từ menu, hoặc gõ để tìm kiếm/ nhập thủ công.', 'role-url-dashboard' ); ?>
							<br><?php _e( 'Nút "Tự động lấy submenu" sẽ thêm nhanh các trang con cùng prefix với Admin URL đã chọn.', 'role-url-dashboard' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="description"><?php _e( 'Description', 'role-url-dashboard' ); ?></label>
					</th>
					<td>
						<textarea name="description" id="description" rows="3" 
						          style="font-size: 16px; padding: 8px; width: 100%; max-width: 500px;"><?php echo $mapping ? esc_textarea( $mapping['description'] ) : ''; ?></textarea>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="icon"><?php _e( 'Icon', 'role-url-dashboard' ); ?></label>
					</th>
					<td>
						<input type="text" name="icon" id="icon" 
						       value="<?php echo $mapping ? esc_attr( $mapping['icon'] ) : ''; ?>"
						       style="font-size: 20px; padding: 8px; width: 100px;"
						       placeholder="🔗">
						<p class="description">
							<?php _e( 'Emoji icon (e.g., 📝, 👥, 📁)', 'role-url-dashboard' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="open_behavior"><?php _e( 'Open Behavior', 'role-url-dashboard' ); ?></label>
					</th>
					<td>
						<select name="open_behavior" id="open_behavior" style="font-size: 16px; padding: 8px;">
							<option value="same" <?php selected( $mapping ? $mapping['open_behavior'] : 'same', 'same' ); ?>>
								<?php _e( 'Same Tab', 'role-url-dashboard' ); ?>
							</option>
							<option value="new" <?php selected( $mapping ? $mapping['open_behavior'] : '', 'new' ); ?>>
								<?php _e( 'New Tab', 'role-url-dashboard' ); ?>
							</option>
							<option value="iframe" <?php selected( $mapping ? $mapping['open_behavior'] : '', 'iframe' ); ?>>
								<?php _e( 'Iframe', 'role-url-dashboard' ); ?>
							</option>
						</select>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="priority"><?php _e( 'Priority', 'role-url-dashboard' ); ?></label>
					</th>
					<td>
						<input type="number" name="priority" id="priority" 
						       value="<?php echo $mapping ? esc_attr( $mapping['priority'] ) : 10; ?>"
						       min="0" max="100" style="font-size: 16px; padding: 8px; width: 100px;">
						<p class="description">
							<?php _e( 'Lower numbers appear first (0-100)', 'role-url-dashboard' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="active"><?php _e( 'Status', 'role-url-dashboard' ); ?></label>
					</th>
					<td>
						<label style="font-size: 16px;">
							<input type="checkbox" name="active" id="active" value="1" 
							       <?php checked( $mapping ? $mapping['active'] : 1, 1 ); ?>>
							<?php _e( 'Active', 'role-url-dashboard' ); ?>
						</label>
					</td>
				</tr>
			</tbody>
		</table>
		
		<p class="submit">
			<button type="submit" name="rud_save_mapping" class="button button-primary button-large" style="font-size: 16px; padding: 12px 24px;">
				<?php echo $is_edit ? __( 'Update Link', 'role-url-dashboard' ) : __( 'Add Link', 'role-url-dashboard' ); ?>
			</button>
			<a href="<?php echo admin_url( 'admin.php?page=role-links' ); ?>" class="button button-large" style="font-size: 16px; padding: 12px 24px;">
				<?php _e( 'Cancel', 'role-url-dashboard' ); ?>
			</a>
		</p>
	</form>
</div>

<script>
jQuery(document).ready(function($) {
	function toggleEntityFields() {
		var entityType = $('#entity_type').val();
		if (entityType === 'role') {
			$('#entity-role-row').show();
			$('#entity-user-row').hide();
			$('#entity_user').removeAttr('required').val('').prop('disabled', true);
			$('#entity_role').prop('disabled', false);
		} else {
			$('#entity-role-row').hide();
			$('#entity-user-row').show();
			$('#entity_role').removeAttr('required').prop('disabled', true);
			$('#entity_user').attr('required', 'required').prop('disabled', false);
		}
	}
	
	$('#entity_type').on('change', toggleEntityFields);
	toggleEntityFields(); // Initialize on page load
	
	// Add URL field - moved to admin.js
	
	// Remove URL field
	$(document).on('click', '.rud-remove-url', function() {
		$(this).closest('.rud-url-field').remove();
	});
	
	// Disable hidden fields before submit
	$('.rud-edit-form').on('submit', function() {
		var entityType = $('#entity_type').val();
		if (entityType === 'role') {
			$('#entity_user').prop('disabled', true);
		} else {
			$('#entity_role').prop('disabled', true);
		}
	});
});
</script>

