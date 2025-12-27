<div
	class="jet-blocks-settings-page jet-blocks-settings-page__integrations"
>
	<cx-vui-switcher
		name="captcha-enable"
		label="<?php _e( 'Enable reCAPTCHA v3', 'jet-blocks' ); // phpcs:ignore ?>"
		description="<?php _e( 'Use reCAPTCHA v3 form verification', 'jet-blocks' ); // phpcs:ignore ?>"
		:wrapper-css="[ 'equalwidth' ]"
		:return-true="'true'"
		:return-false="'false'"
		v-model="pageOptions.captcha.value.enable"
	>
	</cx-vui-switcher>

	<cx-vui-component-wrapper
		:wrapper-css="[ 'fullwidth-control' ]"
		:conditions="[
			{
				input: pageOptions.captcha.value.enable,
				compare: 'equal',
				value: 'true',
			}
		]"
	>
		<cx-vui-input
			name="captcha-site-key"
			label="<?php _e( 'Site Key:', 'jet-blocks' ); // phpcs:ignore ?>"
            :description="'<?php echo esc_js(
				sprintf(
				/* translators: %s: link to Google reCAPTCHA keys page */
					__( 'Register reCAPTCHA v3 keys %s.', 'jet-blocks' ),
					'<a href=\'https://www.google.com/recaptcha/admin/create\' target=\'_blank\' rel=\'noopener noreferrer\'>' .
					esc_html__( 'here', 'jet-blocks' ) .
					'</a>'
				)
			); ?>'"
			:wrapper-css="[ 'equalwidth' ]"
			size="fullwidth"
			v-model="pageOptions.captcha.value.site_key"
		>
		</cx-vui-input>

		<cx-vui-input
			name="captcha-secret-key"
			label="<?php _e( 'Secret Key:', 'jet-blocks' ); // phpcs:ignore ?>"
            :description="'<?php echo esc_js(
				sprintf(
					__( 'Register reCAPTCHA v3 keys %s.', 'jet-blocks' ),
					'<a href=\'https://www.google.com/recaptcha/admin/create\' target=\'_blank\' rel=\'noopener noreferrer\'>' .
					esc_html__( 'here', 'jet-blocks' ) .
					'</a>'
				)
			); ?>'"
			:wrapper-css="[ 'equalwidth' ]"
			size="fullwidth"
			v-model="pageOptions.captcha.value.secret_key"
		>
		</cx-vui-input>

	</cx-vui-component-wrapper>
</div>
