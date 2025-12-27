<?php
/**
 * Cart Link
 */
$this->add_render_attribute( 'cart-link', 'href', esc_url( wc_get_cart_url() ) );
$this->add_render_attribute( 'cart-link', 'class', 'jet-blocks-cart__heading-link' );
$this->add_render_attribute( 'cart-link', 'data-e-disable-page-transition', 'true' );
$this->add_render_attribute( 'cart-link', 'title', esc_attr__( 'View your shopping cart', 'jet-blocks' ) );

?>
<a <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $this->get_render_attribute_string( 'cart-link' ); ?>><?php

	$this->__icon( 'cart_icon', '<span class="jet-blocks-cart__icon jet-blocks-icon">%s</span>' );
	$this->__html( 'cart_label', '<span class="jet-blocks-cart__label">%s</span>' );

	if ( 'yes' === $settings['show_count'] ) {
		?>
        <span class="jet-blocks-cart__count">
			<?php
            $count_format = isset( $settings['count_format'] ) ? wp_kses_post( $settings['count_format'] ) : '%s';

            ob_start();
            include $this->__get_global_template( 'cart-count' );
            $count_inner_html = ob_get_clean();

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            printf( $count_format, $count_inner_html );
            ?>
		</span>
		<?php
	}

	if ( 'yes' === $settings['show_total'] ) {
		?>
        <span class="jet-blocks-cart__total">
			<?php
            $total_format = isset( $settings['total_format'] ) ? wp_kses_post( $settings['total_format'] ) : '%s';

            ob_start();
            include $this->__get_global_template( 'cart-totals' );
            $total_inner_html = ob_get_clean();

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            printf( $total_format, $total_inner_html );
            ?>
		</span>
		<?php
	}

?></a>