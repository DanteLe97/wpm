<?php
/**
 * Popup trigger
 */
$settings = $this->get_settings();
$close_label = ! empty( $settings['search_placeholder'] )
	? esc_attr( $settings['search_placeholder'] )
	: esc_attr__( 'Close search', 'jet-blocks' );
$this->__icon( 'search_close_icon',
	sprintf(
		'<button type="button" class="jet-search__popup-close" aria-label="%1$s"><span class="jet-search__popup-close-icon jet-blocks-icon">%%s</span></button>',
		$close_label
	)
);
