<?php
	$settings   = $this->get_settings_for_display();
	$form_title = wp_kses_post( $settings['form_title'] );

	if ( 'yes' === $settings['login_link'] ) {
		$login_url  = $this->get_login_url( $settings );
		$login_text = isset( $settings['form_login_button_text'] ) ? wp_kses_post( $settings['form_login_button_text'] ) : '';
		$login_link = '<a class="jet-reset__login-link" href="' . $login_url . '">' . $login_text . '</a>';
	} else {
		$login_link = '';
	}
?>

<div class="jet-reset">

	<div class="jet-reset__success-message">
		<?php if ( ! empty( $form_title ) ):?>

			<legend class="jet-reset__form-title"><?php echo wp_kses_post( $form_title ); ?></legend>

		<?php endif;?>

		<p>
            <?php
            printf(
                '%s %s',
                esc_html__( 'Your password has been reset.', 'jet-blocks' ),
                wp_kses_post( $login_link )
            );
            ?>
		</p>
	</div>

</div>