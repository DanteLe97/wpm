<?php
/**
 * Loop item template
 */
$is_linked = $this->__is_linked();
$settings  = $this->get_settings();
?>
<div class="<?php echo esc_attr( $this->__get_logo_classes() ); ?>">
<?php
if ( $is_linked ) {
	printf( '<a href="%1$s" class="jet-logo__link">', esc_url( home_url( '/' ) ) );
} else {
	echo '<div class="jet-logo__link">';
}

$logo_image = (string) $this->__get_logo_image();
$logo_text  = (string) $this->__get_logo_text();

echo wp_kses_post( $logo_image );
echo wp_kses_post( $logo_text );

if ( $is_linked ) {
	echo '</a>';
} else {
	echo '</div>';
}
?>
</div>