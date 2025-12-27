<?php
/**
 * Main cart template
 */

$widget_settings = [
	'triggerType'  => $settings['trigger_type'],
	'openMiniCartOnAdd'  => $settings['open_minicart_on_add'],
	'closeOnClickOutside'  => $settings['close_on_click_outside'],
	'showCartList'  => $settings['show_cart_list'],
];

$classes = [
    'jet-blocks-cart',
    'jet-blocks-cart--' . $settings['layout_type'] . '-layout',
];

// Check if the cart should be hidden when empty
if (
	'yes' === $settings['hide_cart_when_empty']
	&& class_exists('WooCommerce')
	&& WC()->cart
	&& WC()->cart->is_empty()
) {
	$classes[] = 'jet-blocks-cart--empty';
}
$class_string = implode( ' ', $classes );

?><div class="<?php echo esc_attr( $class_string ); ?>" data-settings="<?php echo htmlspecialchars( json_encode( $widget_settings ) ); // phpcs:ignore ?>">
	<div class="jet-blocks-cart__heading"><?php
		include $this->__get_global_template( 'cart-link' );
	?></div>

	<?php if ( 'yes' === $settings['show_cart_list'] ) {
		include $this->__get_global_template( 'cart-list' );
	} ?>

	<?php if ( 'slide-out' === $settings['layout_type'] && 'yes' === $settings['show_overlay_color'] ) { ?>
		<div class="jet-blocks-cart__overlay"></div>
	<?php } ?>
</div>