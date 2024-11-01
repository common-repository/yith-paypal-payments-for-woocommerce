<?php
/**
 * AJAX handler class
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Ajax
 */
class YITH_PayPal_Ajax {

	/**
	 * The AJAX action key
	 *
	 * @var string
	 */
	const AJAX_ACTION = 'yith_ppwc_ajax_request';

	/**
	 * The array of accepted request
	 *
	 * @var array
	 */
	public $requests = array();

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function __construct() {

		$this->requests = $this->load_requests();

		// Handle WC AJAX requests.
		add_action( 'wc_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
		// Handle ADMIN AJAX requests.
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
	}

	/**
	 * An array of valid request
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return array
	 */
	protected function load_requests() {
		return array(
			'validate_checkout',
			'validate_product_cart',
			'create_order',
			'approve_order',
			'partial_payment',
			'partial_payment_refund',
			'void_payment_authorization',
		);
	}

	/**
	 * Handle AJAX request
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @throws Exception Throws Exception.
	 */
	public function handle_ajax() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		try {
			// Check if request if valid.
			$request = isset( $_REQUEST['request'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['request'] ) ) : '';

			if ( ! $request || ! in_array( $request, $this->requests, true ) || ! is_callable( array( $this, 'handle_' . $request ) ) ) {
				throw new Exception( 'Error: Invalid request!' );
			}

			$res = call_user_func( array( $this, 'handle_' . $request ) );
			if ( $res ) {
				$this->handle_ajax_request_success( $res );
			} else {
				throw new Exception( 'An error occurred processing the request!' );
			}
		} catch ( Exception $e ) {
			$this->handle_ajax_request_failure( array(), $e->getMessage() );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Handle a partial payment request
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return mixed
	 * @throws Exception Throws Exception.
	 */
	public function handle_partial_payment() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( - 1 );
		}

		$order_id       = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;
		$payment_amount = isset( $_REQUEST['payment_amount'] ) ? wc_format_decimal( wp_unslash( $_REQUEST['payment_amount'] ), wc_get_price_decimals() ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$payed_amount   = isset( $_REQUEST['payed_amount'] ) ? wc_format_decimal( wp_unslash( $_REQUEST['payed_amount'] ), wc_get_price_decimals() ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$order       = wc_get_order( $order_id );
		$total_payed = YITH_PayPal_Order_Helper::get_total_partial_payed( $order );
		$max_payment = YITH_PayPal_Order_Helper::get_max_partial_payment( $order );

		if ( ! $payment_amount || $max_payment < $payment_amount || 0 > $payment_amount ) {
			throw new Exception( __( 'Invalid partial payment amount', 'yith-paypal-payments-for-woocommerce' ) );
		}

		if ( wc_format_decimal( $total_payed, wc_get_price_decimals() ) !== $payed_amount ) {
			throw new Exception( __( 'Error processing partial payment. Please try again.', 'yith-paypal-payments-for-woocommerce' ) );
		}

		// Create the refund object.
		$payment = YITH_PayPal_Order_Helper::create_partial_payment(
			array(
				'amount'   => $payment_amount,
				'order_id' => $order_id,
			)
		);

		if ( is_wp_error( $payment ) ) {
			throw new Exception( $payment->get_error_message() );
		}

		return true;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Handle a partial payment refund request
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return mixed
	 * @throws Exception Throws Exception.
	 */
	public function handle_partial_payment_refund() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( - 1 );
		}

		$order_id        = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;
		$refund_amount   = isset( $_REQUEST['refund_amount'] ) ? wc_format_decimal( wp_unslash( $_REQUEST['refund_amount'] ), wc_get_price_decimals() ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$refunded_amount = isset( $_REQUEST['refunded_amount'] ) ? wc_format_decimal( wp_unslash( $_REQUEST['refunded_amount'] ), wc_get_price_decimals() ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$refund_reason   = isset( $_REQUEST['refund_reason'] ) ? wp_unslash( $_REQUEST['refund_reason'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$partial_items   = isset( $_REQUEST['partial_items'] ) ? json_decode( wp_unslash( $_REQUEST['partial_items'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$partial_items   = is_array( $partial_items ) ? array_filter( $partial_items ) : array();
		$api_refund      = isset( $_REQUEST['api_refund'] ) && 'true' === $_REQUEST['api_refund'];

		$order      = wc_get_order( $order_id );
		$max_refund = $order ? wc_format_decimal( YITH_PayPal_Order_Helper::get_total_partial_payed( $order ) - $order->get_total_refunded(), wc_get_price_decimals() ) : 0;

		if ( ! $refund_amount || $max_refund < $refund_amount || 0 > $refund_amount || empty( $partial_items ) ) {
			throw new Exception( __( 'Invalid refund amount', 'yith-paypal-payments-for-woocommerce' ) );
		}

		if ( wc_format_decimal( $order->get_total_refunded(), wc_get_price_decimals() ) !== $refunded_amount ) {
			throw new Exception( __( 'Error processing refund. Please try again.', 'yith-paypal-payments-for-woocommerce' ) );
		}

		// Double check for partials amount and refund.
		foreach ( $partial_items as $partial_id => $refund_partial_amount ) {
			$partial = wc_get_order( $partial_id );
			if ( ! $partial ) {
				throw new Exception( __( 'Error processing refund. Please try again.', 'yith-paypal-payments-for-woocommerce' ) );
			}

			if ( $partial->get_remaining_refund_amount() < $refund_partial_amount ) {
				// translators: 1 - Order id, 2 - Transaction id.
				throw new Exception( sprintf( __( 'Invalid refund amount for partial #%1$s (Transaction ID: %2$s)', 'yith-paypal-payments-for-woocommerce' ), $partial->get_id(), $partial->get_transaction_id() ) );
			}
		}

		foreach ( $partial_items as $partial_id => $refund_partial_amount ) {
			// Create the refund object.
			$refund = YITH_PayPal_Order_Helper::create_partial_payment_refund(
				array(
					'amount'         => $refund_partial_amount,
					'reason'         => $refund_reason,
					'order_id'       => $order_id,
					'partial_id'     => $partial_id,
					'refund_payment' => $api_refund,
				)
			);

			if ( is_wp_error( $refund ) ) {
				throw new Exception( $refund->get_error_message() );
			}

			// Trigger notification emails.
			if ( ( $order->get_remaining_refund_amount() - $refund_partial_amount ) > 0 ) {
				do_action( 'woocommerce_order_partially_refunded', $order->get_id(), $refund->get_id() );
			} else {
				do_action( 'woocommerce_order_fully_refunded', $order->get_id(), $refund->get_id() );

				$parent_status = apply_filters( 'woocommerce_order_fully_refunded_status', 'refunded', $order->get_id(), $refund->get_id() );
				if ( $parent_status ) {
					$order->update_status( $parent_status );
				}

				// order is fully refunded, no more refund required.
				break;
			}
		}

		return true;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Void a payment authorization
	 *
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return mixed
	 * @throws Exception Throws Exception.
	 */
	public function handle_void_payment_authorization() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( - 1 );
		}

		$order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;
		$order    = wc_get_order( $order_id );
		$gateway  = YITH_PayPal::get_instance()->get_gateway();
		if ( ! $order || empty( $gateway ) ) {
			throw new Exception( __( 'Error processing void authorization. Please try again.', 'yith-paypal-payments-for-woocommerce' ) );
		}

		$res = $gateway->maybe_void_authorized_payment( $order->get_id() );
		if ( is_wp_error( $res ) ) {
			throw new Exception( $res->get_error_message() );
		}

		return true;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Validate form checkout before send request to PayPal
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return mixed
	 * @throws Exception Throws Exception.
	 */
	public function handle_validate_checkout() {
		try {

			// Make sure there are no WC notices.
			wc_clear_notices();

			$checkout = new YITH_PayPal_Checkout_Helper();
			$checkout->process_checkout();

			if ( wc_notice_count( 'error' ) ) {
				throw new Exception( 'Error processing checkout form' );
			}

			// Make sure there are no WC notices.
			wc_clear_notices();
			return true;

		} catch ( Exception $e ) {
			$has_error_notice = ! wc_notice_count( 'error' );
			$this->handle_ajax_request_failure(
				array(
					'messages' => wc_print_notices( true ),
				),
				$e->getMessage(),
				$has_error_notice
			);
		}

		return false;
	}

	/**
	 * Validate product cart form submit. The form is completely handled by WooCommerce, we just need to check if there are errors.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return mixed
	 * @throws Exception Throws Exception.
	 */
	public function handle_validate_product_cart() {
		try {
			if ( wc_notice_count( 'error' ) ) {
				$errors = wp_list_pluck( wc_get_notices( 'error' ), 'notice' );
				throw new Exception( 'Error processing product cart form. Error details: ' . print_r( $errors, true ) ); // phpcs:ignore
			}

			wc_clear_notices();

			return true;

		} catch ( Exception $e ) {
			$this->handle_ajax_request_failure(
				array(
					'reload' => true,
				),
				$e->getMessage(),
				0 === wc_notice_count( 'error' )
			);
		}

		return false;
	}

	/**
	 * Handle create order
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return mixed
	 * @throws Exception Throws Exception.
	 */
	public function handle_create_order() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		try {

			$checkout_request = isset( $_REQUEST['checkoutRequest'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['checkoutRequest'] ) ) : '';

			if ( 'checkout' === $checkout_request ) {
				wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
			}

			$gateway  = YITH_PayPal::get_instance()->get_gateway();
			$handler  = YITH_PayPal_Controller::load( 'transaction' );
			$flow     = isset( $_REQUEST['flow'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['flow'] ) ) : '';
			$order_id = isset( $_REQUEST['orderID'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderID'] ) ) : '';

			if ( $order_id && 'pay_order' === $checkout_request ) {
				return $handler->create_order_to_pay_order( $flow, $order_id );
			}

			if ( ! in_array( $checkout_request, array( 'checkout', 'pay_order' ), true ) && $gateway->is_fast_checkout_enabled() && ! WC()->cart->needs_shipping() ) {
				$flow = 'ecs';
			}

			return $handler->create_order( $flow );

		} catch ( Exception $e ) {
			$this->handle_ajax_request_failure( array(), $e->getMessage(), true );
		}

		return false;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Handle approve order request
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @throws Exception Throws Exception.
	 */
	public function handle_approve_order() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		try {

			if ( empty( $_REQUEST['orderID'] ) ) {
				throw new Exception( 'The PayPal order ID cannot be empty' );
			}

			$checkout_request = isset( $_REQUEST['checkoutRequest'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['checkoutRequest'] ) ) : '';
			$order_id         = sanitize_text_field( wp_unslash( $_REQUEST['orderID'] ) );
			$handler          = YITH_PayPal_Controller::load( 'transaction' );

			if ( in_array( $checkout_request, array( 'checkout', 'pay_order' ), true ) ) {
				$handler->approve_order_checkout( $order_id );

				return true;
			} else {
				$handler->approve_order( $order_id );

				$gateway = YITH_PayPal::get_instance()->get_gateway();
				if ( $gateway->is_fast_checkout_enabled() && ! WC()->cart->needs_shipping() ) {
					$checkout = new YITH_PayPal_Checkout_Helper();
					$checkout->process_fast_checkout();
				}

				return array(
					'redirect' => wc_get_checkout_url(),
				);
			}
		} catch ( Exception $e ) {
			$this->handle_ajax_request_failure( array(), $e->getMessage(), true );
		}

		return false;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Handle approve order request on checkout page
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @throws Exception Throws Exception.
	 */
	public function handle_approve_order_checkout() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		try {

			if ( empty( $_REQUEST['orderID'] ) ) {
				throw new Exception( 'The PayPal order ID cannot be empty' );
			}

			$order_id = sanitize_text_field( wp_unslash( $_REQUEST['orderID'] ) );
			$handler  = YITH_PayPal_Controller::load( 'transaction' );
			$handler->approve_order_checkout( $order_id );

			// If no error process checkout.
			wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
			WC()->checkout()->process_checkout();
		} catch ( Exception $e ) {
			$this->log_error( $e->getMessage() );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Handle an AJAX request response success
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param mixed  $data The data to send as json response.
	 * @param string $message The error message to log.
	 * @return void
	 */
	protected function handle_ajax_request_success( $data, $message = '' ) {
		// Send success.
		wp_send_json( $this->format_response_data( $data, 'success' ) );
	}

	/**
	 * Handle an AJAX request response error
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param mixed   $data The data to send as json response.
	 * @param string  $message The error message to log.
	 * @param boolean $wc_notice If add or not a wc error notice. Useful for all WooCommerce sections.
	 * @return void
	 */
	protected function handle_ajax_request_failure( $data = array(), $message = '', $wc_notice = false ) {
		// Add generic error notice if $wc_notice is true.
		$wc_notice && wc_add_notice( __( 'An error occurred processing the request!', 'yith-paypal-payments-for-woocommerce' ), 'error' );
		// Log the error.
		$this->log_error( $message );
		// Add message to data if error is not present.
		if ( ! isset( $data['error'] ) ) {
			$data['error'] = $message;
		}

		// Send error.
		wp_send_json( $this->format_response_data( $data, 'failure' ) );
	}

	/**
	 * Format the response
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param mixed  $data The response data.
	 * @param string $result Result.
	 * @return array
	 */
	protected function format_response_data( $data, $result = 'success' ) {
		$data = array_filter( (array) $data );

		return array_merge( array( 'result' => $result ), $data );
	}

	/**
	 * Log request error
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $message Message text.
	 */
	protected function log_error( $message ) {
		if ( $message ) {
			YITH_PayPal_Logger::log( $message );
		}
	}
}

new YITH_PayPal_Ajax();
