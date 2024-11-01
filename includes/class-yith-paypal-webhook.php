<?php
/**
 * Webhook class
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Webhook
 */
class YITH_PayPal_Webhook {

	/**
	 * Single instance of the class
	 *
	 * @since 1.0.0
	 * @var class YITH_PayPal_Webhook
	 */
	protected static $instance;

	/**
	 * Page name
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $pagename;

	/**
	 * Returns single instance of the class
	 *
	 * @since 1.0.0
	 * @return YITH_PayPal_Webhook
	 */
	public static function get_webhook() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	private function __construct() {
		$this->get_webhook_url();
		$this->pagename = apply_filters( 'yith_ppcc_webhook_pagename', 'yith_ppwc' );

		add_action( 'woocommerce_api_' . $this->pagename, array( $this, 'handle_webhooks' ) );
	}

	/**
	 * Handle the webhook
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	public function handle_webhooks() {

		$headers     = getallheaders();
		$body        = @file_get_contents( 'php://input' ); //phpcs:ignore
		$environment = YITH_PayPal::get_instance()->get_gateway()->get_environment();

		if ( $body ) {

			$body   = json_decode( $body, true );
			$webook = YITH_PayPal_Controller::load( 'webhook' );
			try {
				if ( 'production' === $environment && ! $webook->verify_webhook_signature( $headers, $body ) ) {
					// print_r used on log.
					YITH_PayPal_Logger::log( 'Webhook failed signature ' . print_r( $body, 1 ) ); //phpcs:ignore
					return;
				}

				$event_type = $body['event_type'];
				$resource   = $body['resource'];

				switch ( $event_type ) {
					case 'PAYMENT.AUTHORIZATION.CREATED':
						$this->handle_capture( $resource, 'register_authorization' );
						break;
					case 'PAYMENT.AUTHORIZATION.VOIDED':
						$this->handle_capture( $resource, 'void_authorization' );
						break;
					case 'PAYMENT.CAPTURE.COMPLETED':
						$this->handle_capture( $resource, 'process_payment_complete' );
						break;

					case 'PAYMENT.CAPTURE.REFUNDED':
						$this->handle_capture( $resource, 'process_refund' );
						break;
					case 'PAYMENT.CAPTURE.REVERSED':
						$this->handle_capture( $resource, 'process_reversed' );
						break;
					case 'PAYMENT.CAPTURE.DENIED':
						$this->handle_capture( $resource, 'process_failed_payment' );
						break;
					case 'PAYMENT.CAPTURE.PENDING':
						$this->handle_capture( $resource, 'process_pending_payment' );
						break;
					case 'CHECKOUT.ORDER.COMPLETED':
					case 'CHECKOUT.ORDER.APPROVED':
						return;
					default:
						YITH_PayPal_Logger::log( 'Event type not found ' . $event_type );
				}
			} catch ( Exception $e ) {
				// APPEND REQUEST DATA TO LOG.
				$message = $e->getMessage() . ' ' . print_r( $body, true ); //phpcs:ignore
				YITH_PayPal_Logger::log( $message );
			}
		}

	}


	/**
	 * Handle authorization
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param array $posted Webhook content.
	 */
	public function handle_authorization( $posted ) {
		$event_type = $posted['event_type'];
	}


	/**
	 * Handle capture
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param array  $resource Webhook content.
	 * @param string $callback Method to call after checked the webhook resource.
	 */
	public function handle_capture( $resource, $callback ) {

		if ( empty( $resource['invoice_id'] ) ) {
			YITH_PayPal_Logger::log( 'The invoice id is not set inside the capture webhook resource.' );

			return;
		}

		$order_id = str_replace( YITH_PayPal::get_instance()->get_gateway()->get_prefix(), '', $resource['invoice_id'] );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			YITH_PayPal_Logger::log( 'The order with id ' . $order_id . ' is not found.' );

			return;
		}

		$this->$callback( $order, $resource );

	}


	/**
	 * Complete the order.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param WC_Order $order Order to complete.
	 * @param array    $resource Webhook request.
	 * @throws Exception Throws Exception.
	 */
	private function process_payment_complete( $order, $resource ) {

		if ( $order->has_status( wc_get_is_paid_statuses() ) ) {
			YITH_PayPal_Logger::log( 'Aborting the order ' . $order->get_id() . ' the order is already completed.' );

			return;
		}

		$this->validate_currency( $order, $resource['amount']['currency_code'] );
		$this->save_paypal_meta_data( $order, $resource );

		if ( 'completed' === strtolower( $resource['status'] ) ) {

			if ( $order->has_status( 'cancelled' ) ) {
				YITH_PayPal_Logger::log( 'Payment for cancelled order ' . $order->get_id() . ' received' );
			}

			YITH_PayPal_Order_Helper::register_payment_from_webhook( $order, $resource );
		}
	}

	/**
	 * Process the refund of the order.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param WC_Order $order Order to refund.
	 * @param array    $resource Webhook request.
	 * @throws Exception Throws Exception.
	 */
	private function process_refund( $order, $resource ) {

		// check the refund status.
		if ( 'completed' !== strtolower( $resource['status'] ) ) {
			YITH_PayPal_Logger::log( 'The refund process for the order ' . $order->get_id() . ' is not completed.' );

			return;
		}

		$amount              = $resource['amount']['value'];
		$currency            = $resource['amount']['currency_code'];
		$refund_tracking_ids = (array) $order->get_meta( '_yith_ppwc_refund_tracking_ids' );

		if ( in_array( $resource['id'], $refund_tracking_ids ) ) { //phpcs:ignore
			YITH_PayPal_Logger::log( 'The refund process for the order ' . $order->get_id() . ' with reference ' . $resource['id'] . ' is already registered.' );

			return;
		}

		// handle full refunds.
		if ( $order->get_total() <= $amount ) {
			/* translators: %s: Placeholder is the number of transaction. */
			$order->update_status( 'refunded', sprintf( esc_html_x( 'Refunded this order via PayPal. Reference id: %s', 'Order note when an order is refunded: Placeholder is the number of transaction', 'yith-paypal-payments-for-woocommerce' ), $resource['id'] ) );
		} else {
			// refund partial order.
			$order_refund = wc_create_refund(
				array(
					'amount'   => $amount,
					/* translators: %s: Placeholder is the number of transaction. */
					'reason'   => sprintf( esc_html_x( 'Refunded via PayPal. Reference id: %s', 'Order note when an order is refunded: Placeholder is the number of transaction', 'yith-paypal-payments-for-woocommerce' ), $resource['id'] ),
					'order_id' => $order->get_id(),
				)
			);

			if ( $order_refund ) {
				$order_refund->set_refunded_by( 'PayPal' );
				$order_refund->save();
			}

			// translators: 1. Reference id, 2. Refund Amount.
			$order->add_order_note( sprintf( esc_html_x( 'Refunded partially via PayPal. Reference id: %1$s. Amount: %2$s', 'Order note when an order is refunded: Placeholder is the number of transaction', 'yith-paypal-payments-for-woocommerce' ), $resource['id'], wc_price( $amount, $currency ) ) );
		}

		$order->update_meta_data( '_yith_ppwc_refund_tracking_ids', $resource['id'] );
		$order->save_meta_data();
	}

	/**
	 * Process reversed order.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param WC_Order $order Order to reverse.
	 * @param array    $resource Webhook request.
	 */
	private function process_reversed( $order, $resource ) {
		/* translators: %s: Reference id of this reversed action. */
		$order->update_status( 'on-hold', sprintf( esc_html_x( 'Reversed via PayPal. Reference id: %s', 'Order note, %s is the reference id.', 'yith-paypal-payments-for-woocommerce' ), wc_clean( $resource['id'] ) ) );
	}

	/**
	 * Process failed payments when a payment is denied.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param WC_Order $order Order to register failed payment.
	 * @param array    $resource Webhook request.
	 */
	private function process_failed_payment( $order, $resource ) {
		/* translators: %s: Reference id of this denied action. */
		if ( ! $order->has_status( 'failed' ) ) {
			/* translators: %s: Reference id. */
			$order->update_status( 'failed', sprintf( esc_html_x( 'Denied payment via PayPal. Reference id: %s', 'Order note, %s is the reference id.', 'yith-paypal-payments-for-woocommerce' ), wc_clean( $resource['id'] ) ) );
		}
	}

	/**
	 * Process pending payments when a payment is denied.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param WC_Order $order Order to set in pending.
	 * @param array    $resource Webhook request.
	 */
	private function process_pending_payment( $order, $resource ) {

		if ( ! $order->has_status( 'pending' ) ) {
			/* translators: %s: Reference id of this denied action. */
			$order->update_status( 'pending', sprintf( esc_html_x( 'Pending payment via PayPal. Reference id: %s', 'Order note, %s is the reference id.', 'yith-paypal-payments-for-woocommerce' ), wc_clean( $resource['id'] ) ) );
		}
	}

	/**
	 * Register Authorization code inside an order if is necessary.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param WC_Order $order Order where register the authorization.
	 * @param array    $resource Webhook request.
	 * @throws Exception Throws Exception.
	 */
	private function register_authorization( $order, $resource ) {
		if ( 'VOIDED' === $resource['status'] ) {
			return;
		}

		$order_authorize_info = $order->get_meta( '_yith_ppwc_paypal_authorize_info' );

		$is_registered = ! empty( $order_authorize_info ) && $order_authorize_info['id'] === $resource['id'];

		if ( ! $is_registered ) {
			$order->update_meta_data( '_yith_ppwc_paypal_authorize_info', $resource );
		}

	}

	/**
	 * Void the authorization of an order.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param WC_Order $order Order.
	 * @param array    $resource Webhook request.
	 * @throws Exception Throws Exception.
	 */
	private function void_authorization( $order, $resource ) {
		YITH_PayPal::get_instance()->get_gateway()->maybe_void_authorized_payment( $order->get_id(), $resource['id'] );
		$new_status = apply_filters( 'yith_ppwc_order_status_after_void_authorization', 'cancelled', $order, $resource );
		$order->update_status( $new_status );
	}

	/**
	 * Check currency from Webhook matches the order.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param WC_Order $order Order object.
	 * @param string   $currency Currency code.
	 */
	protected function validate_currency( $order, $currency ) {
		if ( $order->get_currency() !== $currency ) {
			YITH_PayPal_Logger::log( 'Payment error: Currencies do not match (sent "' . $order->get_currency() . '" | returned "' . $currency . '")' );

			/* translators: %s: currency code. */
			$order->update_status( 'on-hold', sprintf( esc_html_x( 'Validation error: PayPal currencies do not match (code %s).', 'Order note, the placeholder is the currency code', 'yith-paypal-payments-for-woocommerce' ), $currency ) );
			exit;
		}
	}

	/**
	 * Check payment amount from Webhook matches the order.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param WC_Order $order Order object.
	 * @param int      $amount Amount to validate.
	 */
	protected function validate_amount( $order, $amount ) {
		if ( number_format( $order->get_total(), 2, '.', '' ) !== number_format( $amount, 2, '.', '' ) ) {
			YITH_PayPal_Logger::log( 'Payment error: Amounts do not match (gross ' . $amount . ')' );

			/* translators: %s: Amount. */
			$order->update_status( 'on-hold', sprintf( esc_html_x( 'Validation error: PayPal amounts do not match (gross %s).', 'Order note, the placeholder is the amount of the order.', 'yith-paypal-payments-for-woocommerce' ), $amount ) );
			exit;
		}
	}

	/**
	 * Save PayPal metadata inside the order.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param WC_Order $order Order object.
	 * @param integer  $resource Webhook content.
	 * @throws Exception Throws Exception.
	 */
	protected function save_paypal_meta_data( $order, $resource ) {

		$order->update_meta_data( '_yith_ppwc_invoice_id', $resource['invoice_id'] );

		if ( ! empty( $resource['links'] ) ) {
			$up_relation_key = array_search( 'up', array_column( $resource['links'], 'rel' ), true );
			if ( false !== $up_relation_key ) {
				$link = array_filter( explode( '/', $resource['links'][ $up_relation_key ]['href'] ) );
				if ( ! in_array( 'authorizations', $link ) ) { //phpcs:ignore
					$paypal_order_id = end( $link );
					$transaction     = YITH_PayPal_Controller::load( 'transaction' );
					try {
						$order_details = $transaction->get_order_details( $paypal_order_id );
						! empty( $order_details['payer']['email_address'] ) && $order->update_meta_data( '_yith_ppwc_paypal_address', $order_details['payer']['email_address'] );
						! empty( $order_details['payer']['payer_id'] ) && $order->update_meta_data( '_yith_ppwc_payer_id', $order_details['payer']['payer_id'] );
					} catch ( Exception $e ) {
						YITH_PayPal_Logger::log( 'There was an issue during the PayPal order details request and some payer info are not saved. ' . $paypal_order_id );
					}
				}
			}
		}

		$order->save_meta_data();
	}

	/**
	 * Get webhook URL
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	public function get_webhook_url() {
		return apply_filters( 'yith_ppcc_webhook_url', get_site_url( null, '', 'https' ) . '/?wc-api=' . $this->pagename );
	}
}
