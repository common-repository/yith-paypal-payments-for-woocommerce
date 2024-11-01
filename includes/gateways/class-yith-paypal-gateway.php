<?php
/**
 * Gateway class
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Gateway
 */
class YITH_PayPal_Gateway extends WC_Payment_Gateway_CC {

	/**
	 * The partner ID
	 *
	 * @var string
	 */
	const PARTNER_ID      = '4W7PT248NATQQ';
	const PARTNER_ID_LIVE = 'FVENL8FXSNQ8U';

	/**
	 * The partner Client ID
	 *
	 * @var string
	 */
	const PARTNER_CLIENT_ID      = 'AQzsRhvy0N0zjOuxvJaWVleXCsqOujcbEP5aJE_wp46yO_oqE2oUcs-GmKiUBtdXoQu-jJYdOOLWUiUR';
	const PARTNER_CLIENT_ID_LIVE = 'AVVVyplPWwBMpvvRtiTf4qi0QuocVBTeaEYSE8gDgqACLFfj3kZ-Zo5W1gbM1jXLQ3hAeBw16gMm3Vzl';

	/**
	 * Gateway environment
	 *
	 * @var string
	 */
	protected $environment = '';

	/**
	 * Constructor for the gateway.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function __construct() {

		// Setup general properties.
		$this->setup_properties();
		// Load the settings.
		$this->init_settings();

		$this->supports = array(
			'products',
			'refunds',
		);

		// Get settings.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->environment = $this->get_option( 'environment', 'production' );

		if ( 'capture' !== $this->get_intent() ) {
			// for authorized payment.
			add_action( 'woocommerce_order_status_payment-auth_to_processing', array( $this, 'request_authorized_payment' ) );
			add_action( 'woocommerce_order_status_payment-auth_to_completed', array( $this, 'request_authorized_payment' ) );
			add_action( 'woocommerce_order_status_cancelled', array( $this, 'maybe_void_authorized_payment' ) );
		}
	}

	/**
	 * Setup general properties for the gateway.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	protected function setup_properties() {
		$this->id                 = YITH_Paypal::GATEWAY_ID;
		$this->icon               = '';
		$this->method_title       = __( 'PayPal Payments', 'yith-paypal-payments-for-woocommerce' );
		$this->method_description = __( 'PayPal Advanced Payments Gateway.', 'yith-paypal-payments-for-woocommerce' );
		$this->has_fields         = false;
	}

	/**
	 * Get option key for this gateway.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function get_option_key() {
		return 'yith_ppwc_gateway_options';
	}

	/**
	 * Init settings for gateways.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function init_settings() {
		parent::init_settings();
		$this->enabled = ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
	}

	/**
	 * Show payment fields on checkout page
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function payment_fields() {
		$description = $this->get_description();

		if ( $description ) {
			echo wpautop( wptexturize( $description ) ); // @codingStandardsIgnoreLine.
		}

		if ( $this->supports( 'tokenization' ) && is_checkout() ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->form();
			$this->save_payment_method_checkbox();
		} else {
			$this->form();
		}
	}

	/**
	 * Init settings for gateways.
	 *
	 * @return boolean
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @since 1.0.0
	 */
	public function is_enabled() {
		return 'yes' === $this->enabled;
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$is_available = $this->is_enabled();
		$merchant     = YITH_PayPal_Merchant::get_merchant();

		if ( ! $merchant->is_valid() ) {
			$is_available = false;
		}

		return $is_available;
	}

	/**
	 * Check if fast checkout is enabled
	 *
	 * @return boolean
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @since 1.0.0
	 */
	public function is_fast_checkout_enabled() {
		return 'yes' === $this->get_option( 'fast_checkout', 'no' );
	}


	/**
	 * Get the gateway environment
	 *
	 * @return string
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @since 1.0.0
	 */
	public function get_environment() {
		return $this->environment;
	}

	/**
	 * Get the gateway partner ID
	 *
	 * @return string
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @since 1.0.0
	 */
	public function get_partner_id() {
		return 'production' === $this->environment ? self::PARTNER_ID_LIVE : self::PARTNER_ID;
	}

	/**
	 * Get the gateway partner client ID
	 *
	 * @return string
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @since 1.0.0
	 */
	public function get_partner_client_id() {
		return 'production' === $this->environment ? self::PARTNER_CLIENT_ID_LIVE : self::PARTNER_CLIENT_ID;
	}

	/**
	 * Get the BN code to trace orders
	 *
	 * @param string $flow Flow.
	 * @return string
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	public function get_bn_code( $flow = '' ) {
		if ( ! $flow ) {
			$flow = WC()->session->get( 'paypal_current_flow' );
		}

		$bn = 'YITH_SI_PPCPPlugin';
		if ( 'unbranded_card' === $flow ) {
			$bn = 'YITH_SI_PPCPPlugin_Unbranded';
		} elseif ( 'credit' === $flow ) {
			$bn = 'YITH_SI_PPCPPlugin_Credit';
		} elseif ( 'ecs' === $flow ) {
			$bn = 'YITH_SI_PPCPPlugin_ECS';
		}

		return $bn;
	}

	/**
	 * Get the intent
	 *
	 * @return string
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	public function get_intent() {
		return 'capture' === $this->get_option( 'intent', 'capture' ) ? 'capture' : 'authorize';
	}

	/**
	 * Get the gateway base url
	 *
	 * @return string
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @since 1.0.0
	 */
	public function get_base_url() {
		return 'production' === $this->get_environment() ? 'https://www.paypal.com' : 'https://www.sandbox.paypal.com';
	}

	/**
	 * Get the API gateway url
	 *
	 * @return string
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @since 1.0.0
	 */
	public function get_api_url() {
		return 'production' === $this->get_environment() ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com';
	}

	/**
	 * Get the invoice id prefix
	 *
	 * @return string
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @since 1.0.0
	 */
	public function get_prefix() {
		return $this->get_option( 'prefix' );
	}

	/**
	 * Get the SDK url
	 *
	 * @return string
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @since 1.0.0
	 */
	public function get_sdk_url() {

		// Add needed data like merchant ID and options style.
		$merchant = YITH_PayPal_Merchant::get_merchant();

		$cc_enabled = yith_ppwc_is_custom_credit_card_enabled();

		$args = array(
			'components'  => ( is_checkout() && $cc_enabled ) ? 'hosted-fields,buttons' : 'buttons',
			'client-id'   => $merchant->get_client_id(),
			'merchant-id' => $merchant->get( 'merchant_id' ),
			'currency'    => get_woocommerce_currency(),
			'intent'      => $this->get_intent(),
			'commit'      => is_checkout() ? 'true' : 'false',
			'locale'      => get_locale(), // to get PayPal button text based on buyer locale instead of site locale.
			'debug'       => 'production' !== $this->environment ? 'true' : 'false',
		);

		$disabled_funding_sources = yith_ppwc_get_disabled_funding_sources( false );

		if ( $disabled_funding_sources ) {
			if ( $cc_enabled && is_checkout() && in_array( 'card', $disabled_funding_sources, true ) ) {
				$key = array_search( 'card', $disabled_funding_sources, true );
				unset( $disabled_funding_sources[ $key ] );
			}

			$args['disable-funding'] = implode( ',', $disabled_funding_sources );
		}

		$disabled_card = yith_ppwc_get_disabled_cards();
		if ( yith_ppwc_are_credit_cards_enabled() && ! empty( $disabled_card ) ) {
			$args['disable-card'] = yith_ppwc_get_disabled_cards();
		}

		return add_query_arg( apply_filters( 'yith_ppwc_sdk_parameters', $args ), 'https://www.paypal.com/sdk/js' );
	}

	/**
	 * Get the asset login url
	 *
	 * @return string
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @since 1.0.0
	 */
	public function get_login_asset_url() {
		return $this->get_base_url() . '/webapps/merchantboarding/js/lib/lightbox/partner.js';
	}

	/**
	 * Get the login url
	 *
	 * @return string
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	public function get_login_url() {

		$base = $this->get_base_url() . '/bizsignup/partner/entry';

		return add_query_arg(
			array(
				'partnerId'          => $this->get_partner_id(),
				'product'            => 'ppcp',
				'integrationType'    => 'FO',
				'features'           => 'PAYMENT,REFUND',
				'partnerClientID'    => $this->get_partner_client_id(),
				'returnToPartnerUrl' => admin_url( 'admin.php?page=' . YITH_PayPal_Admin::PANEL_PAGE ),
				'partnerLogoUrl'     => '',
				'displayMode'        => 'minibrowser',
				'sellerNonce'        => yith_ppwc_get_nonce(),
				'tracking_id'        => yith_ppwc_get_tracking_id(),
			),
			$base
		);
	}

	/**
	 * Add the button on checkout page
	 *
	 * @return void
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	public function form() {}

	/**
	 * Pay the order.
	 *
	 * @param int $order_id Order id.
	 *
	 * @return array
	 * @throws Exception .
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	public function process_payment( $order_id ) {

		if ( ! isset( WC()->session ) || ! WC()->session->get( 'paypal_order_id', false ) ) {
			throw new Exception( 'Error: An error occurred during the payment.' );
		}

		$order           = wc_get_order( $order_id );
		$paypal_order_id = WC()->session->get( 'paypal_order_id' );
		$order->update_meta_data( '_paypal_order', $paypal_order_id );

		if ( $order->get_total() > 0 ) {
			$transaction = YITH_PayPal_Controller::load( 'transaction' );
			try {
				if ( $transaction->update_order( $order_id, $paypal_order_id ) ) {

					if ( 'authorize' === $this->get_intent() ) {
						$this->authorize_order( $order, $paypal_order_id );
					} else {
						$payment_info = $transaction->pay_order( $paypal_order_id );
						if ( 'COMPLETED' === $payment_info['status'] ) {
							$transaction_id = $payment_info['transaction_id'];

							isset( $payment_info['yith_ppwc_paypal_address'] ) && $order->update_meta_data( '_yith_ppwc_paypal_address', $payment_info['yith_ppwc_paypal_address'] );
							isset( $payment_info['yith_ppwc_payer_id'] ) && $order->update_meta_data( '_yith_ppwc_payer_id', $payment_info['yith_ppwc_payer_id'] );
							isset( $payment_info['yith_ppwc_invoice_id'] ) && $order->update_meta_data( '_yith_ppwc_invoice_id', $payment_info['yith_ppwc_invoice_id'] );

							$order->payment_complete( $transaction_id );
						}
					}
				}
			} catch ( Exception $e ) {

				wc_add_notice( $e->getMessage(), 'error' );
				$redirect = WC()->session->get( 'checkout_as_confirm_page' ) ? wc_get_cart_url() : wc_get_checkout_url();

				// Return to checkout redirect.
				return array(
					'result'   => 'success',
					'redirect' => $redirect,
				);
			}
		} else {
			$order->payment_complete();
		}

		$order->save_meta_data();

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);

	}

	/**
	 * Process the refund.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $paypal_order_id PayPal order id.
	 *
	 * @throws Exception Throws Exception.
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	protected function authorize_order( $order, $paypal_order_id ) {

		$transaction = YITH_PayPal_Controller::load( 'transaction' );

		$order_details = $transaction->maybe_request_authorize_code( $paypal_order_id );

		if ( empty( $order_details['purchase_units'][0]['payments']['authorizations'] ) ) {
			throw new Exception( 'No authorizations have been created for this order.' );
		}

		$authorize_info = $order_details['purchase_units'][0]['payments']['authorizations'][0];
		$order_note     = esc_html_x( 'PayPal Payment authorized. Change the order status to Processing or Complete to capture funds or do a partial payment.', 'Order note message', 'yith-paypal-payments-for-woocommerce' );

		if ( ! empty( $authorize_info['expiration_time'] ) ) {
			$date = new WC_DateTime( $authorize_info['expiration_time'] );
			// translators: the placeholder is a date.
			$order_note .= sprintf( esc_html_x( ' Expiration date %s.', 'Order note, the placeholder is the expiration date of the authorized payment', 'yith-paypal-payments-for-woocommerce' ), $date->date_i18n( wc_date_format() ) );
		}

		$order->has_status( 'payment-auth' ) ? $order->add_order_note( $order_note ) : $order->update_status( 'payment-auth', $order_note );

		$order->update_meta_data( '_captured', 'no' );
		$order->update_meta_data( '_yith_ppwc_paypal_authorize_info', $authorize_info );

		! empty( $order_details['payer']['email_address'] ) && $order->update_meta_data( '_yith_ppwc_paypal_address', $order_details['payer']['email_address'] );
		! empty( $order_details['payer']['payer_id'] ) && $order->update_meta_data( '_yith_ppwc_payer_id', $order_details['payer']['payer_id'] );
		! empty( $order_details['purchase_units'][0]['invoice_id'] ) && $order->update_meta_data( '_yith_ppwc_invoice_id', $order_details['purchase_units'][0]['invoice_id'] );
	}

	/**
	 * Charge an authorized payment.
	 *
	 * @param int $order_id Order id.
	 *
	 * @return void|WP_Error
	 * @throws Exception Exception message.
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function request_authorized_payment( $order_id ) {
		$order          = wc_get_order( $order_id );
		$captured       = $order->get_meta( '_captured' );
		$payment_method = $order->get_payment_method();
		$invoice_id     = $order->get_meta( '_yith_ppwc_invoice_id' );
		$invoice_id     = empty( $invoice_id ) ? $this->get_prefix() . $order_id : $invoice_id;

		if ( ! $captured || 'no' !== $captured || ! yith_ppwc_check_gateway( $payment_method ) ) {
			return;
		}

		// If there are partials, continue with that.
		$partials = YITH_PayPal_Order_Helper::get_partials( $order_id );
		if ( ! empty( $partials ) ) {
			YITH_PayPal_Order_Helper::create_partial_payment(
				array(
					'amount'   => YITH_PayPal_Order_Helper::get_remaining_order_amount( $order ),
					'order_id' => $order_id,
				)
			);

			return;
		}

		try {

			$auth_info = $order->get_meta( '_yith_ppwc_paypal_authorize_info' );

			if ( empty( $auth_info ) ) {
				throw new Exception( esc_html_x( 'Authorize information are missed.', 'Error message', 'yith-paypal-payments-for-woocommerce' ) );
			}

			$transaction    = YITH_PayPal_Controller::load( 'transaction' );
			$authorize_id   = $auth_info['id'];
			$transaction_id = $transaction->capture_authorization( $authorize_id, $invoice_id );

			if ( $order->payment_complete( $transaction_id ) ) {
				// translators: the placeholder is the transaction id.
				$order->add_order_note( sprintf( esc_html_x( 'PayPal payment approved (ID: %s)', 'Order note. Placeholder id of transaction', 'yith-paypal-payments-for-woocommerce' ), $transaction_id ) );
				$order->update_meta_data( '_captured', 'final' );
				$order->update_meta_data( '_transaction_id', $transaction_id );
				$order->save_meta_data();
			} else {
				throw new Exception( __( 'Something went wrong with the payment', 'yith-paypal-payments-for-woocommerce' ) );
			}
		} catch ( Exception $e ) {
			// translators: the placeholder is an error message.
			$message = sprintf( esc_html_x( 'There was an error while processing payment: %s', 'Error message. Placeholder is the error detail', 'yith-paypal-payments-for-woocommerce' ), esc_html( $e->get_message() ) );

			$order->add_order_note( $message );
			return new WP_Error( 'yith_ppwc_error', $message );
		}
	}

	/**
	 * Charge an authorized partial payment.
	 *
	 * @param int $partial_id Order id.
	 *
	 * @return void|WP_Error
	 * @throws Exception Exception message.
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function request_authorized_partial_payment( $partial_id ) {

		$partial = wc_get_order( $partial_id );
		if ( ! $partial || 'shop_order_partial' !== $partial->get_type() ) {
			return;
		}

		$order          = wc_get_order( $partial->get_parent_id() );
		$payment_method = $order ? $order->get_payment_method() : '';

		try {
			if ( 'no' !== $partial->get_meta( '_captured' ) || ! yith_ppwc_check_gateway( $payment_method ) ) {
				throw new Exception( esc_html_x( 'Invalid request.', 'Error message', 'yith-paypal-payments-for-woocommerce' ) );
			}

			$auth_info = $order ? $order->get_meta( '_yith_ppwc_paypal_authorize_info' ) : false;

			if ( empty( $auth_info ) || empty( $auth_info['id'] ) ) {
				throw new Exception( esc_html_x( 'Authorize information are missed.', 'Error message', 'yith-paypal-payments-for-woocommerce' ) );
			}

			$invoice_id = $partial->get_meta( '_yith_ppwc_invoice_id' );
			$invoice_id = empty( $invoice_id ) ? $this->get_prefix() . $order->get_id() : $invoice_id;

			$final_payment = abs( YITH_PayPal_Order_Helper::get_remaining_order_amount( $order ) ) < 0.01;

			$transaction    = YITH_PayPal_Controller::load( 'transaction' );
			$transaction_id = $transaction->capture_authorization(
				$auth_info['id'],
				$invoice_id,
				yith_ppwc_format_amount( $partial->get_amount(), $partial->get_currency() ),
				$final_payment
			);

			if ( $transaction_id ) {
				$this->save_transaction_id_on_partial_payment( $transaction_id, $order, $partial, $final_payment );
			} else {
				throw new Exception( __( 'Something went wrong with the payment', 'yith-paypal-payments-for-woocommerce' ) );
			}
		} catch ( Exception $e ) {
			// translators: the placeholder is an error message.
			$message = sprintf( esc_html_x( 'There was an error while processing partial payment: %s', 'Error message. Placeholder is the error detail', 'yith-paypal-payments-for-woocommerce' ), esc_html( $e->get_message() ) );

			$order->add_order_note( $message );
			return new WP_Error( 'yith_ppwc_error', $message );
		}
	}


	/**
	 * Register partial payment inside the order.
	 *
	 * @param string                    $transaction_id Transaction id.
	 * @param WC_Order                  $order Order.
	 * @param YITH_PayPal_Order_Partial $partial Partial order.
	 * @param bool                      $final_payment If true the payment is completed.
	 *
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function save_transaction_id_on_partial_payment( $transaction_id, $order, $partial, $final_payment ) {
		// translators: the placeholder is the transaction id.
		$order->add_order_note( sprintf( esc_html_x( 'PayPal partial payment approved (ID: %s)', 'Order note. Placeholder id of transaction', 'yith-paypal-payments-for-woocommerce' ), $transaction_id ) );

		$partial->update_meta_data( '_captured', 'true' );
		$partial->set_transaction_id( $transaction_id );
		if ( ! $partial->get_date_paid( 'edit' ) ) {
			$partial->set_date_paid( time() );
		}

		$partial->save();

		if ( $final_payment ) {
			$order->add_order_note( sprintf( esc_html_x( 'Order PayPal partial payment finalized. No more partial payment allowed.', 'Order note.', 'yith-paypal-payments-for-woocommerce' ) ) );
			$order->update_meta_data( '_captured', 'final' );
			$order->payment_complete();
		}
	}


	/**
	 * Check if the order has an authorized payment and do the void
	 *
	 * @param int    $order_id Order id.
	 * @param string $auth_code The auth code.
	 *
	 * @throws Exception Throws Exception.
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	public function maybe_void_authorized_payment( $order_id, $auth_code = '' ) {
		$order          = wc_get_order( $order_id );
		$captured       = $order->get_meta( '_captured' );
		$payment_method = $order->get_payment_method();
		$invoice_id     = $order->get_meta( '_yith_ppwc_invoice_id' );
		$invoice_id     = empty( $invoice_id ) ? $this->get_prefix() . $order_id : $invoice_id;

		if ( ! $captured || 'final' === $captured || ! yith_ppwc_check_gateway( $payment_method ) ) {
			return;
		}

		try {

			$auth_info = $order->get_meta( '_yith_ppwc_paypal_authorize_info' );

			if ( empty( $auth_info ) ) {
				throw new Exception( esc_html_x( 'Authorize information are missed.', 'Error message', 'yith-paypal-payments-for-woocommerce' ) );
			}

			$transaction  = YITH_PayPal_Controller::load( 'transaction' );
			$authorize_id = $auth_info['id'];
			if ( ! empty( $auth_code ) && $authorize_id !== $auth_code ) {
				$order->add_order_note( esc_html_x( 'Requested a Payment Authorization Void for this order from PayPal, but the Auth Code does not match with the registered code.', 'Order note.', 'yith-paypal-payments-for-woocommerce' ) );
				return;
			}

			empty( $auth_code ) && $transaction->void_authorization( $authorize_id, $invoice_id );

			$who = empty( $auth_code ) ? esc_html_x( 'Administrator', 'A piece of order note, to specify who made the action', 'yith-paypal-payments-for-woocommerce' ) : esc_html_x( 'PayPal', 'A piece of order note, to specify who made the action', 'yith-paypal-payments-for-woocommerce' );
			// translators: The placeholder is a string previously declared.
			$order->add_order_note( sprintf( esc_html_x( 'PayPal payment is voided by %s', 'Order note. Placeholder who requested the void', 'yith-paypal-payments-for-woocommerce' ), $who ) );
			$order->update_meta_data( '_captured', 'voided' );
			$order->save_meta_data();

		} catch ( Exception $e ) {
			// translators: The placeholder is the error returned by PayPal.
			return new WP_Error( 'yith_ppwc_error', sprintf( esc_html_x( 'Sorry, There was an error while processing payment %s', 'Error message. Placeholder is the error detail', 'yith-paypal-payments-for-woocommerce' ), esc_html( $e->getMessage() ) ) );
		}
	}

	/**
	 * Get the transaction URL.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function get_transaction_url( $order ) {

		$this->view_transaction_url = $this->get_base_url() . '/cgi-bin/webscr?cmd=_view-a-trans&id=%s';

		return parent::get_transaction_url( $order );
	}

	/**
	 * Process the refund.
	 *
	 * @param int        $order_id Order id.
	 * @param null|float $amount Amount.
	 * @param string     $reason Reason of Refund.
	 * @param string     $transaction_id The payment transaction ID. User for partial payments.
	 *
	 * @return bool|WP_Error
	 * @throws Exception Throws Exception.
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	public function process_refund( $order_id, $amount = null, $reason = '', $transaction_id = '' ) {

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'yith_ppwc_refund_error', __( 'No valid order to refund.' ) );
		}

		if ( empty( $transaction_id ) ) {
			$transaction_id = $order->get_transaction_id();
		}

		if ( ! $transaction_id ) {
			return new WP_Error( 'yith_ppwc_refund_error', __( 'No transaction id registered for this order.' ) );
		}

		$order_currency      = $order->get_currency();
		$payer_id            = $order->get_meta( '_yith_ppwc_payer_id' );
		$invoice_id          = $order->get_meta( '_yith_ppwc_invoice_id' );
		$refund_tracking_ids = (array) $order->get_meta( '_yith_ppwc_refund_tracking_ids' );

		try {
			$transaction = YITH_PayPal_Controller::load( 'transaction' );
			// ask refund to transaction.
			$refund_id = $transaction->refund_order( $amount, $order_currency, $reason, $transaction_id, $payer_id, $invoice_id );
			array_push( $refund_tracking_ids, $refund_id );
			$order->update_meta_data( '_yith_ppwc_refund_tracking_ids', $refund_tracking_ids );
			$order->save_meta_data();

		} catch ( Exception $e ) {
			return new WP_Error( 'yith_ppwc_refund_error', $e->getMessage() );
		}

		// translators: Placeholder is the refund id.
		$note  = is_null( $amount ) ? sprintf( esc_html_x( 'Fully Refund ID: %2$s', 'Order note message', 'yith-paypal-payments-for-woocommerce' ), $amount, $refund_id ) : sprintf( esc_html_x( 'Refunded %1$s - Refund ID: %2$s', 'Order note message', 'yith-paypal-payments-for-woocommerce' ), wc_price( $amount, $order_currency ), $refund_id );
		$note .= $reason ? esc_html_x( ' - Reason: ', 'Part of an order note message', 'yith-paypal-payments-for-woocommerce' ) . $reason : '';

		$order->add_order_note( $note );

		return true;
	}
}
