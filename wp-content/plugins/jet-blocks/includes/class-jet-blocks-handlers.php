<?php
/**
 * Class description
 *
 * @package   package_name
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Jet_Blocks_Handlers' ) ) {

	/**
	 * Define Jet_Blocks_Handlers class
	 */
	class Jet_Blocks_Handlers {

		/**
		 * A reference to an instance of this class.
		 *
		 * @since 1.0.0
		 * @var   object
		 */
		private static $instance = null;

		/**
		 * Constructor for the class
		 */
		public function init() {

			add_action( 'init', array( $this, 'register_handler' ) );
			add_action( 'init', array( $this, 'login_handler' ) );
			add_action( 'init', array( $this, 'reset_handler' ) );

			if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.0.0', '>=' ) ) {
				add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_link_fragments' ) );
			} else {
				add_filter( 'add_to_cart_fragments', array( $this, 'cart_link_fragments' ) );
			}
		}

		/**
		 * Cart link fragments
		 *
		 * @return array
		 */
		public function cart_link_fragments( $fragments ) {

			global $woocommerce;

			$jet_fragments = apply_filters( 'jet-blocks/handlers/cart-fragments', array(
				'.jet-blocks-cart__total-val' => 'jet-blocks-cart/global/cart-totals.php',
				'.jet-blocks-cart__count-val' => 'jet-blocks-cart/global/cart-count.php',
			) );

			foreach ( $jet_fragments as $selector => $template ) {
				ob_start();
				include jet_blocks()->get_template( $template );
				$fragments[ $selector ] = ob_get_clean();
			}

			return $fragments;

		}

		/**
		 * Login form handler.
		 *
		 * @return void
		 */
		public function login_handler() {

			if ( ! isset( $_POST['jet_login'] ) ) { // phpcs:ignore
				return;
			}

			$recaptcha_token    = isset( $_POST['token'] ) ? $_POST['token'] : ''; // phpcs:ignore
			$recaptcha_settings = jet_blocks_settings()->get( 'captcha' );

			if ( $recaptcha_settings ) {

				if ( 'true' === $recaptcha_settings['enable']) {

					$recaptcha_instance = jet_blocks()->integration_manager->get_integration_module_instance( 'recaptcha' );

					if ( '' != $recaptcha_token ) {
						$recaptcha_verify = $recaptcha_instance->maybe_verify( $recaptcha_token );

						if( true != $recaptcha_verify ) {
							return;
						}
					}
				}
			}

			try {

				if ( empty( $_POST['log'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing

					$error = sprintf(
						'<strong>%1$s</strong>: %2$s',
						__( 'ERROR', 'jet-blocks' ),
						__( 'The username field is empty.', 'jet-blocks' )
					);

					throw new Exception( $error );

				}

				$signon = wp_signon();

				if ( is_wp_error( $signon ) ) {
					throw new Exception( $signon->get_error_message() );
				}

				$raw_redirect = isset( $_POST['redirect_to'] ) // phpcs:ignore
					? wp_unslash( $_POST['redirect_to'] ) // phpcs:ignore
					: home_url( '/' );

				$redirect = wp_validate_redirect( $raw_redirect, home_url( '/' ) );
				wp_safe_redirect( $redirect );
				exit;

			} catch ( Exception $e ) {
				wp_cache_set( 'jet-login-messages', $e->getMessage() );
			}

		}

		/**
		 * Registration handler
		 *
		 * @return void
		 */
		public function register_handler() {

			if ( ! isset( $_POST['jet-register-nonce'] ) ) {
				return;
			}

			if ( ! wp_verify_nonce( $_POST['jet-register-nonce'], 'jet-register' ) ) { // phpcs:ignore
				return;
			}

			$recaptcha_token    = isset( $_POST['token'] ) ? $_POST['token'] : ''; // phpcs:ignore
			$recaptcha_settings = jet_blocks_settings()->get( 'captcha' );

			if ( $recaptcha_settings ) {

				if ( 'true' === $recaptcha_settings['enable']) {

					$recaptcha_instance = jet_blocks()->integration_manager->get_integration_module_instance( 'recaptcha' );

					if ( '' != $recaptcha_token ) {
						$recaptcha_verify = $recaptcha_instance->maybe_verify( $recaptcha_token );

						if( true != $recaptcha_verify ) {
							return;
						}
					}
				}

			}
			
			try {

				$username           = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
				$password           = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$email              = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
				$confirm_password   = isset( $_POST['jet_confirm_password'] ) ? filter_var( wp_unslash( $_POST['jet_confirm_password'] ), FILTER_VALIDATE_BOOLEAN ) : false;
				$confirmed_password = isset( $_POST['password-confirm'] ) ? (string) wp_unslash( $_POST['password-confirm'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

				if ( $confirm_password && $password !== $confirmed_password ) {
					throw new Exception( esc_html__( 'Entered passwords don\'t match', 'jet-blocks' ) );
				}

				$validation_error = new WP_Error();

				$user = $this->create_user( $username, sanitize_email( $email ), $password );

				if ( is_wp_error( $user ) ) {
					throw new Exception( $user->get_error_message() );
				}

				global $current_user;
				$current_user = get_user_by( 'id', $user );
				wp_set_auth_cookie( $user, true );

				if ( ! empty( $_POST['jet_redirect'] ) ) { // phpcs:ignore
					$redirect = wp_sanitize_redirect( $_POST['jet_redirect'] ); // phpcs:ignore
				} else { // phpcs:ignore
					$redirect = $_POST['_wp_http_referer']; // phpcs:ignore
				} // phpcs:ignore
				wp_redirect( $redirect ); // phpcs:ignore
				exit; // phpcs:ignore

			} catch ( Exception $e ) {
				wp_cache_set( 'jet-register-messages', $e->getMessage() );
			}

		}

		/**
		 * Reset form handler.
		 *
		 * @return void
		 */
		public function reset_handler() {
			$action = isset( $_REQUEST['jet_reset_action'] ) ? $_REQUEST['jet_reset_action'] : ''; // phpcs:ignore

			if ( 'jet_reset_pass_reset' !== $action ) {
				return;
			}

			if ( ! ( isset( $_REQUEST['jet_reset_nonce'] )
				&& wp_verify_nonce( wp_unslash( $_REQUEST['jet_reset_nonce'] ), 'jet_reset_pass_reset' ) ) ) { // phpcs:ignore
				$args       = array();
				$error      = new \WP_Error( 'jet_reset_error', '<strong>ERROR</strong>: ' . esc_html__( 'something went wrong with that!', 'jet-blocks' ) );
				$site_title = get_bloginfo( 'name', 'display' );
				wp_die( $error, $site_title . ' - ' . esc_html__( 'Error', 'jet-blocks' ), $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			$recaptcha_token    = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
			$recaptcha_settings = jet_blocks_settings()->get( 'captcha' ); // phpcs:ignore

			if ( $recaptcha_settings ) {

				if ( 'true' === $recaptcha_settings['enable']) {

					$recaptcha_instance = jet_blocks()->integration_manager->get_integration_module_instance( 'recaptcha' );

					if ( '' != $recaptcha_token ) {
						$recaptcha_verify = $recaptcha_instance->maybe_verify( $recaptcha_token );

						if( true != $recaptcha_verify ) {
							return;
						}
					}
				}

			}

			$errors           = array();
			$user_pass        = isset( $_POST['jet_reset_new_user_pass'] )
				? trim( (string) wp_unslash( $_POST['jet_reset_new_user_pass'] ) ) // phpcs:ignore
				: ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$user_pass_repeat = isset( $_POST['jet_reset_new_user_pass_again'] )
				? trim( (string) wp_unslash( $_POST['jet_reset_new_user_pass_again'] ) ) // phpcs:ignore
				: ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( empty( $user_pass ) || empty( $user_pass_repeat ) ) {
				$errors['no_password'] = esc_html__( 'Please enter a new password.', 'jet-blocks' );
				$_REQUEST['errors']    = $errors;
				return;
			} elseif ( $user_pass !== $user_pass_repeat ) {
				$errors['password_mismatch'] = esc_html__( 'The passwords don\'t match.', 'jet-blocks' );
				$_REQUEST['errors']          = $errors;
				return;
			}

			$key     = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
			$user_id = isset( $_GET['uid'] ) ? sanitize_text_field( wp_unslash( $_GET['uid'] ) ) : '';

			if ( empty( $key ) || empty( $user_id ) ) {
				$errors['key_login'] = esc_html__( 'The reset link is not valid.', 'jet-blocks' );
				$_REQUEST['errors']  = $errors;
				wp_safe_redirect( get_permalink() );
				exit;
			}

			$userdata = get_userdata( absint( $user_id ) );
			$login    = $userdata ? $userdata->user_login : '';
			$user     = check_password_reset_key( $key, $login );

			if ( is_wp_error( $user ) ) {

				if ( $user->get_error_code() === 'expired_key' ) {

					$errors['expired_key'] = esc_html__( 'Sorry, that key has expired. Please reset your password again.', 'jet-blocks' );

				} else {

					$errors['invalid_key'] = esc_html__( 'Sorry, that key does not appear to be valid. Please reset your password again.', 'jet-blocks' );

				}

			}

			if ( ! empty( $errors ) ) {
				$_REQUEST['errors'] = $errors;
				return;
			}


			do_action( 'validate_password_reset', new \WP_Error(), $user );

			reset_password( $user, $user_pass );

			$raw_success = isset( $_POST['jet-reset-success-redirect'] )
				? wp_unslash( $_POST['jet-reset-success-redirect'] ) // phpcs:ignore
				: '';

			$redirect_page_url = wp_validate_redirect( $raw_success, get_permalink( 0 ) );
			wp_safe_redirect( $redirect_page_url );
			exit;

		}

		/**
		 * Create new user function
		 *
		 * @param  [type] $username [description]
		 * @param  [type] $email    [description]
		 * @param  [type] $password [description]
		 * @return [type]           [description]
		 */
		public function create_user( $username, $email, $password ) {

			// Check username
			if ( empty( $username ) || ! validate_username( $username ) ) {
				return new WP_Error(
					'registration-error-invalid-username',
					__( 'Please enter a valid account username.', 'jet-blocks' )
				);
			}

			if ( username_exists( $username ) ) {
				return new WP_Error(
					'registration-error-username-exists',
					__( 'An account is already registered with that username. Please choose another.', 'jet-blocks' )
				);
			}

			// Check the email address.
			if ( empty( $email ) || ! is_email( $email ) ) {
				return new WP_Error(
					'registration-error-invalid-email',
					__( 'Please provide a valid email address.', 'jet-blocks' )
				);
			}

			if ( email_exists( $email ) ) {
				return new WP_Error(
					'registration-error-email-exists',
					__( 'An account is already registered with your email address. Please log in.', 'jet-blocks' )
				);
			}

			// Check password
			if ( empty( $password ) ) {
				return new WP_Error(
					'registration-error-missing-password',
					__( 'Please enter an account password.', 'jet-blocks' )
				);
			}

			$custom_error = apply_filters( 'jet_register_form_custom_error', null );

			if ( is_wp_error( $custom_error ) ){
				return $custom_error;
			}

			$new_user_data = array(
				'user_login' => $username,
				'user_pass'  => $password,
				'user_email' => $email,
			);

			$user_id = wp_insert_user( $new_user_data );

			if ( is_wp_error( $user_id ) ) {
				return new WP_Error(
					'registration-error',
					'<strong>' . __( 'Error:', 'jet-blocks' ) . '</strong> ' . __( 'Couldn&#8217;t register you&hellip; please contact us if you continue to have problems.', 'jet-blocks' )
				);
			}

			return $user_id;

		}

		/**
		 * Returns the instance.
		 *
		 * @since  1.0.0
		 * @return object
		 */
		public static function get_instance() {

			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}
			return self::$instance;
		}
	}

}

/**
 * Returns instance of Jet_Blocks_Handlers
 *
 * @return object
 */
function jet_blocks_handlers() {
	return Jet_Blocks_Handlers::get_instance();
}
