<?php
/**
 * This class is useful to validate a checkout request using WC Checkout methods avoiding order create
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Checkout_Helper
 */
class YITH_PayPal_Checkout_Helper extends WC_Checkout {

	/**
	 * Process the checkout after the confirm order button is pressed.
	 *
	 * @throws Exception When validation fails.
	 */
	public function process_checkout() {
		try {
			$nonce_value = wc_get_var( $_REQUEST['woocommerce-process-checkout-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // @codingStandardsIgnoreLine.

			if ( empty( $nonce_value ) || ! wp_verify_nonce( $nonce_value, 'woocommerce-process_checkout' ) ) {
				WC()->session->set( 'refresh_totals', true );
				throw new Exception( __( 'We were unable to process your order, please try again.', 'woocommerce' ) );
			}

			wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
			wc_set_time_limit( 0 );

			do_action( 'woocommerce_before_checkout_process' );

			if ( WC()->cart->is_empty() ) {
				/* translators: %s: shop cart url */
				throw new Exception( sprintf( __( 'Sorry, your session has expired. <a href="%s" class="wc-backward">Return to shop</a>', 'woocommerce' ), esc_url( wc_get_page_permalink( 'shop' ) ) ) );
			}

			do_action( 'woocommerce_checkout_process' );

			$errors      = new WP_Error();
			$posted_data = $this->get_posted_data();

			// Update session for customer and totals.
			$this->update_session( $posted_data );

			// Validate posted data and cart items before proceeding.
			$this->validate_checkout( $posted_data, $errors );

			foreach ( $errors->errors as $code => $messages ) {
				$data = $errors->get_error_data( $code );
				foreach ( $messages as $message ) {
					wc_add_notice( $message, 'error', $data );
				}
			}
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
		}
	}

	/**
	 * Process fast checkout for digital goods.
	 *
	 * @throws Exception When validation fails.
	 */
	public function process_fast_checkout() {

		wc_clear_notices(); // Clear all old notice.

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
		wc_set_time_limit( 0 );

		try {

			do_action( 'woocommerce_before_checkout_process' );

			// Double check cart and that cart doesn't need shipping.
			if ( WC()->cart->is_empty() || WC()->cart->needs_shipping() ) {
				/* translators: %s: shop cart url */
				throw new Exception( sprintf( __( 'There was an error processing your request. <a href="%s" class="wc-backward">Return to shop</a>', 'yith-paypal-payments-for-woocommerce' ), esc_url( wc_get_page_permalink( 'shop' ) ) ) );
			}

			do_action( 'woocommerce_checkout_process' );

			// Create posted data array.
			$posted_data = $this->create_fast_checkout_posted_data();
			// Update cart totals now we have customer address.
			WC()->cart->calculate_totals();

			// Check for customer, if requested create the account.
			$this->process_fast_checkout_customer( $posted_data );

			$order_id = $this->create_order( $posted_data );

			if ( is_wp_error( $order_id ) ) {
				throw new Exception( $order_id->get_error_message() );
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				throw new Exception( __( 'Unable to create order.', 'woocommerce' ) );
			}

			do_action( 'woocommerce_checkout_order_processed', $order_id, $posted_data, $order );

			if ( WC()->cart->needs_payment() ) {
				$this->process_order_payment( $order_id, $posted_data['payment_method'] );
			} else {
				$this->process_order_without_payment( $order_id );
			}

			return true;
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );

			return false;
		}
	}

	/**
	 * Create and return an array of posted date used on fast checkout
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return array
	 * @throws Exception When billing address is not valid.
	 */
	protected function create_fast_checkout_posted_data() {
		$posted = array();
		// First merge with billing address.
		$billing_address = WC()->session->get( 'paypal_billing_address' );
		if ( empty( $billing_address ) || empty( $billing_address['email'] ) || ! is_email( $billing_address['email'] ) ) {
			throw new Exception( __( 'No valid billing address provided.', 'yith-paypal-payments-for-woocommerce' ) );
		}

		foreach ( $billing_address as $field => $value ) {
			$posted[ 'billing_' . $field ] = $value;
		}

		$posted['payment_method'] = YITH_Paypal::GATEWAY_ID;

		return $posted;
	}

	/**
	 * Process fast checkout customer.
	 * If the user is not logged in try to find and user with same billing email and log-in. If not create it if guest checkout is not available.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param array $posted Array of posted data.
	 * @return void
	 * @throws Exception When create customer fails.
	 */
	protected function process_fast_checkout_customer( $posted ) {
		if ( ! is_user_logged_in() ) {
			$email       = $posted['billing_email'];
			$customer    = get_user_by( 'email', $email );
			$customer_id = $customer ? $customer->ID : false;

			if ( ! $customer_id || $this->is_registration_required() ) {

				$customer_args = array(
					'first_name' => ! empty( $data['billing_first_name'] ) ? $data['billing_first_name'] : '',
					'last_name'  => ! empty( $data['billing_last_name'] ) ? $data['billing_last_name'] : '',
				);

				// Force WC to create username and password, no matter what is the plugin settings.
				add_filter( 'pre_option_woocommerce_registration_generate_username', 'yith_ppwc_return_yes' );
				add_filter( 'pre_option_woocommerce_registration_generate_password', 'yith_ppwc_return_yes' );

				$customer_id = wc_create_new_customer( $email, '', '', $customer_args );
				if ( is_wp_error( $customer_id ) ) {
					throw new Exception( $customer_id->get_error_message() );
				}
			}

			wc_set_customer_auth_cookie( $customer_id );
			// On multisite, ensure user exists on current site, if not add them before allowing login.
			if ( $customer_id && is_multisite() && is_user_logged_in() && ! is_user_member_of_blog() ) {
				add_user_to_blog( get_current_blog_id(), $customer_id, 'customer' );
			}
		}
	}
}
