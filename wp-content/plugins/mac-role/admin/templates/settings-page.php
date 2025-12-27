<?php
/**
 * Settings page template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap rud-admin-wrap">
	<h1 class="rud-page-title"><?php _e( 'Role URL Dashboard Settings', 'role-url-dashboard' ); ?></h1>
	
	<form method="post" action="">
		<?php wp_nonce_field( 'rud-save-settings' ); ?>
		
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="dashboard_location"><?php _e( 'Dashboard Location', 'role-url-dashboard' ); ?></label>
					</th>
					<td>
						<select name="dashboard_location" id="dashboard_location" style="font-size: 16px; padding: 8px;" disabled>
							<option value="menu" <?php selected( $settings['dashboard_location'], 'menu' ); ?>>
								<?php _e( 'Show in Admin Menu', 'role-url-dashboard' ); ?>
							</option>
							<option value="index" <?php selected( $settings['dashboard_location'], 'index' ); ?>>
								<?php _e( 'Replace Dashboard Index', 'role-url-dashboard' ); ?>
							</option>
						</select>
						<input type="hidden" name="dashboard_location" value="index">
						<p class="description">
							<?php _e( 'Dashboard is set to replace the default WordPress dashboard index page.', 'role-url-dashboard' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="allow_iframe"><?php _e( 'Allow Iframe', 'role-url-dashboard' ); ?></label>
					</th>
					<td>
						<label style="font-size: 16px;">
							<input type="checkbox" name="allow_iframe" id="allow_iframe" value="1" 
							       <?php checked( $settings['allow_iframe'], 1 ); ?>>
							<?php _e( 'Allow links to open in iframe (not recommended for WP core pages)', 'role-url-dashboard' ); ?>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="cache_ttl"><?php _e( 'Cache TTL (seconds)', 'role-url-dashboard' ); ?></label>
					</th>
					<td>
						<input type="number" name="cache_ttl" id="cache_ttl" 
						       value="<?php echo esc_attr( $settings['cache_ttl'] ); ?>"
						       min="0" style="font-size: 16px; padding: 8px; width: 150px;">
						<p class="description">
							<?php _e( 'How long to cache user mappings (default: 3600 = 1 hour)', 'role-url-dashboard' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		
		<p class="submit">
			<button type="submit" name="rud_save_settings" class="button button-primary button-large" style="font-size: 16px; padding: 12px 24px;">
				<?php _e( 'Save Settings', 'role-url-dashboard' ); ?>
			</button>
		</p>
	</form>
</div>

