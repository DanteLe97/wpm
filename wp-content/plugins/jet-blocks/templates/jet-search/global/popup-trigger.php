<?php
/**
 * Popup trigger
 */
?>
<?php
$settings = $this->get_settings();
$popup_trigger_title = ! empty( $settings['search_placeholder'] )
	? esc_attr( $settings['search_placeholder'] )
	: esc_attr__( 'Open search', 'jet-blocks' );
?>
<div class="jet-search__popup-trigger-container">
	<button type="button" class="jet-search__popup-trigger" title="<?php echo esc_attr( $popup_trigger_title ); ?>"><?php
		$this->__icon( 'search_popup_trigger_icon', '<span class="jet-search__popup-trigger-icon jet-blocks-icon">%s</span>' )
	?></button>
</div>