<?php
/**
 * Transaction Request handler class
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Request_Transaction
 */
class YITH_PayPal_Request_Transaction extends YITH_PayPal_Request {

	/**
	 * Address map
	 *
	 * @var array
	 */
	protected $address_map = array();

	/**
	 * The class construct
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @throws Exception Throws Exception.
	 */
	public function __construct() {
		parent::__construct();
		$this->load_address_map();
	}

	/**
	 * Create order method
	 *
	 * @param string $flow Flow.
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @throws Exception Throws Exception.
	 */
	public function create_order( $flow = '' ) {

		if ( WC()->cart->is_empty() ) {
			throw new Exception( 'The cart is empty' );
		}

		// force recalculate total for cart.
		WC()->cart->calculate_totals();

		$token = YITH_PayPal_Merchant::get_merchant()->get( 'token' );
		// Store the flow for the current order. Used for different bn code.
		WC()->session->set( 'paypal_current_flow', $flow );

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'PayPal-Partner-Attribution-Id' => $this->gateway->get_bn_code(),
				'Authorization'                 => "Bearer $token",
				'Content-Type'                  => 'application/json',
			),
			'body'    => wp_json_encode( $this->build_request() ),
		);

		return $this->do_api_request( '/v2/checkout/orders', $args );
	}

	/**
	 * Create order method
	 *
	 * @param string $flow Flow.
	 * @param int    $order_id Order id.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @throws Exception Throws Exception.
	 */
	public function create_order_to_pay_order( $flow = '', $order_id = 0 ) {

		$token = YITH_PayPal_Merchant::get_merchant()->get( 'token' );
		// Store the flow for the current order. Used for different bn code.
		WC()->session->set( 'paypal_current_flow', $flow );

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'PayPal-Partner-Attribution-Id' => $this->gateway->get_bn_code(),
				'Authorization'                 => "Bearer $token",
				'Content-Type'                  => 'application/json',
			),
			'body'    => wp_json_encode( $this->build_request_from_order( $order_id ) ),
		);

		return $this->do_api_request( '/v2/checkout/orders', $args );
	}


	/**
	 * Update order method
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param int    $order_id WC Order id.
	 * @param string $paypal_order PayPal order id.
	 * @return void
	 * @throws Exception Throws Exception.
	 */
	public function update_order( $order_id, $paypal_order ) {

		$request_body = $this->build_update_request_from_order( $order_id );

		if ( empty( $request_body ) ) {
			return;
		}

		$replace_body = array(
			'op'    => 'replace',
			'path'  => "/purchase_units/@reference_id=='default'",
			'value' => $this->build_update_request_from_order( $order_id ),
		);

		$token = YITH_PayPal_Merchant::get_merchant()->get( 'token' );

		$args = array(
			'method'  => 'PATCH',
			'headers' => array(
				'PayPal-Partner-Attribution-Id' => $this->gateway->get_bn_code(),
				'Prefer'                        => 'return=representation',
				'Authorization'                 => "Bearer $token",
				'Content-Type'                  => 'application/json',
			),
			'body'    => wp_json_encode( array( $replace_body ) ),
		);

		$result = $this->do_api_request( '/v2/checkout/orders/' . $paypal_order, $args );

		return true;

	}

	/**
	 * Pay the order
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param string $paypal_order PauPal Order.
	 * @return array
	 * @throws Exception Throws Exception.
	 */
	public function pay_order( $paypal_order ) {

		$payment_details = array();
		$token           = YITH_PayPal_Merchant::get_merchant()->get( 'token' );

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'PayPal-Partner-Attribution-Id' => $this->gateway->get_bn_code(),
				'Authorization'                 => "Bearer $token",
				'Content-Type'                  => 'application/json',
			),
		);

		if ( $this->gateway->get_intent() === 'capture' ) {
			$payment_details = $this->do_api_request( '/v2/checkout/orders/' . $paypal_order . '/capture', $args );
		}

		if ( empty( $payment_details ) || empty( $payment_details['status'] ) || ( 'COMPLETED' !== $payment_details['status'] && 'PENDING' !== $payment_details['status'] ) ) {
			throw new Exception( 'The processing PayPal payment is not completed' );
		}

		if ( isset( $payment_details['purchase_units'][0]['payments']['captures'][0]['status'] ) && 'COMPLETED' !== $payment_details['purchase_units'][0]['payments']['captures'][0]['status'] ) {
			throw new Exception( 'The processing PayPal payment is not completed your payment has been ' . $payment_details['purchase_units'][0]['payments']['captures'][0]['status'] );
		}

		$args = array(
			'status'               => $payment_details['status'],
			'transaction_id'       => $payment_details['purchase_units'][0]['payments']['captures'][0]['id'],
			'yith_ppwc_invoice_id' => empty( $payment_details['purchase_units'][0]['payments']['captures'][0]['invoice_id'] ) ? '' : $payment_details['purchase_units'][0]['payments']['captures'][0]['invoice_id'],
		);

		if ( ! empty( $payment_details['payer'] ) ) {
			$args['yith_ppwc_paypal_address'] = isset( $payment_details['payer']['email_address'] ) ? $payment_details['payer']['email_address'] : '';
			$args['yith_ppwc_payer_id']       = isset( $payment_details['payer']['payer_id'] ) ? $payment_details['payer']['payer_id'] : '';
		}

		// Clear session flow.
		WC()->session->set( 'paypal_current_flow', '' );

		return $args;

	}

	/**
	 * Handle order approved
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $order_id The PayPal order ID created.
	 * @return bool
	 * @throws Exception Throws Exception.
	 */
	public function approve_order( $order_id ) {

		$order_details = $this->get_approved_order( $order_id );

		// Set checkout as confirm page.
		WC()->session->set( 'checkout_as_confirm_page', true );
		// Store PayPal order ID.
		WC()->session->set( 'paypal_order_id', sanitize_text_field( $order_details['id'] ) );
		// Get shipping address if any. Consider first purchase unit.
		$billing  = $this->get_customer_information( $order_details, 'billing' );
		$shipping = $this->get_customer_information( $order_details, 'shipping' );

		if ( wc_ship_to_billing_address_only() ) {
			WC()->session->set( 'paypal_billing_address', ! empty( $shipping ) ? $shipping : $billing );
		} else {
			WC()->session->set( 'paypal_billing_address', $billing );
			WC()->session->set( 'paypal_shipping_address', $shipping );
		}

		return true;
	}

	/**
	 * Handle order approved at checkout page
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $order_id The PayPal order ID created.
	 * @return bool
	 * @throws Exception Throws Exception.
	 */
	public function approve_order_checkout( $order_id ) {

		$order_details = $this->get_approved_order( $order_id );

		// Store PayPal order ID.
		WC()->session->set( 'paypal_order_id', sanitize_text_field( $order_details['id'] ) );

		return true;
	}

	/**
	 * Handle order approved
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $order_id The PayPal order ID created.
	 * @return array
	 * @throws Exception Throws Exception.
	 */
	protected function get_approved_order( $order_id ) {

		if ( WC()->cart->is_empty() ) {
			throw new Exception( 'The cart is empty' );
		}

		$order_details = $this->get_order_details( $order_id );

		if ( 'APPROVED' !== $order_details['status'] ) {
			throw new Exception( 'The processing PayPal order status is not approved.' );
		}

		return $order_details;
	}


	/**
	 * Request an authorize code for the order if is necessary.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $order_id The PayPal order ID created.
	 * @return array
	 * @throws Exception Throws Exception.
	 */
	public function maybe_request_authorize_code( $order_id ) {

		if ( $this->gateway->get_intent() !== 'authorize' ) {
			return;
		}

		$token = YITH_PayPal_Merchant::get_merchant()->get( 'token' );

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'PayPal-Partner-Attribution-Id' => $this->gateway->get_bn_code(),
				'Authorization'                 => "Bearer $token",
				'Content-Type'                  => 'application/json',
				'Prefer'                        => 'return=representation',
			),
		);

		$authorize = $this->do_api_request( '/v2/checkout/orders/' . $order_id . '/authorize', $args );

		if ( empty( $authorize['purchase_units'][0]['payments']['authorizations'] ) ) {
			throw new Exception( 'No authorizations have been created for this process.' );
		}

		return $authorize;
	}

	/**
	 * Capture authorization payment
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string  $auth_id Authorization id.
	 * @param string  $invoice_id Invoice id.
	 * @param array   $amount The amount data.
	 * @param boolean $force_final True to force set the final amount, false not.
	 * @return string
	 * @throws Exception Throws Exception.
	 */
	public function capture_authorization( $auth_id, $invoice_id, $amount = array(), $force_final = false ) {
		$token = YITH_PayPal_Merchant::get_merchant()->get( 'token' );

		// Start creating body.
		$body = array( 'invoice_id' => $invoice_id );
		// If the amount if empty or final is force, set the final capture.
		if ( empty( $amount ) || $force_final ) {
			$body['final_capture'] = 'true';
		}
		// Set the amount to capture if not empty.
		if ( ! empty( $amount ) ) {
			$body['amount'] = $amount;
		}

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'PayPal-Partner-Attribution-Id' => $this->gateway->get_bn_code(),
				'Authorization'                 => "Bearer $token",
				'Content-Type'                  => 'application/json',
				'Prefer'                        => 'return=representation',
			),
			'body'    => wp_json_encode( $body ),
		);

		$capture = $this->do_api_request( '/v2/payments/authorizations/' . $auth_id . '/capture', $args );

		if ( ! empty( $capture['status'] ) && 'COMPLETED' !== $capture['status'] ) {
			throw new Exception( 'Something went wrong with the payment. Transaction status ' . $capture['status'] . '.' );
		}

		return $capture['id'];
	}

	/**
	 * Void authorization payment
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>

	 * @param string $auth_id Authorization id.
	 * @return bool
	 * @throws Exception Throws an Exception.
	 */
	public function void_authorization( $auth_id ) {
		$token = YITH_PayPal_Merchant::get_merchant()->get( 'token' );

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => "Bearer $token",
				'Content-Type'  => 'application/json',
			),
		);

		$void = $this->do_api_request( '/v2/payments/authorizations/' . $auth_id . '/void', $args );

		return true;
	}


	/**
	 * Get the customer information
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param array  $data Response object.
	 * @param string $type Type of information.
	 * @return array
	 */
	private function get_customer_information( $data, $type ) {

		$customer_info = array();
		$info          = array();
		if ( 'shipping' === $type && isset( $data['purchase_units'] ) ) {
			$purchase_units = isset( $data['purchase_units'] ) ? array_shift( $data['purchase_units'] ) : false;
			$info           = isset( $purchase_units['shipping'] ) ? $purchase_units['shipping'] : array();
		} elseif ( 'billing' === $type && isset( $data['payer'] ) ) {
			$info = $data['payer'];
		}

		if ( ! empty( $info ) ) {
			if ( isset( $info['name'] ) ) {
				$name                        = $info['name'];
				$customer_info['first_name'] = isset( $name['given_name'] ) ? sanitize_text_field( $name['given_name'] ) : '';
				$customer_info['last_name']  = isset( $name['surname'] ) ? sanitize_text_field( $name['surname'] ) : '';

				if ( isset( $name['full_name'] ) ) {
					$first_name                  = explode( ' ', $name['full_name'] );
					$first_name                  = array_map( 'sanitize_text_field', $first_name );
					$customer_info['last_name']  = array_pop( $first_name );
					$customer_info['first_name'] = implode( ' ', $first_name );
				}
			}

			if ( isset( $info['email_address'] ) ) {
				$customer_info['email'] = sanitize_email( $info['email_address'] );
			}

			if ( isset( $info['address'] ) ) {
				$info_address = array_map( 'sanitize_text_field', $info['address'] );
				foreach ( $this->address_map as $internal_key => $paypal_key ) {
					$customer_info[ $internal_key ] = isset( $info_address[ $paypal_key ] ) ? $info_address[ $paypal_key ] : '';
				}
			}
		}

		return $customer_info;
	}

	/**
	 * Create the body request.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return array
	 */
	private function build_request() {

		$cart_total            = yith_ppwc_round_amount( WC()->cart->get_total( 'edit' ) );
		$cart_total_items      = yith_ppwc_round_amount( WC()->cart->get_cart_contents_total() + WC()->cart->get_discount_total() );
		$cart_total_tax        = yith_ppwc_round_amount( WC()->cart->get_cart_contents_tax() + WC()->cart->get_discount_tax() );
		$temp_cart_total_items = 0;
		$temp_cart_total_tax   = 0;
		$needs_shipping        = WC()->cart->needs_shipping();

		$body = $this->get_body_base( $cart_total, '', $needs_shipping );

		if ( apply_filters( 'yith_ppwc_build_request_details', true, 'cart' ) ) {

			foreach ( WC()->cart->get_cart_contents() as $cart_item_key => $cart_item ) {

				$product            = $cart_item['data'];
				$product_unit_price = wc_get_price_excluding_tax( $product );
				$product_unit_price = wc_add_number_precision( $product_unit_price );
				$product_item_tax   = 0;
				if ( $product->is_taxable() ) {
					$product_tax_rates = WC_Tax::get_rates( $product->get_tax_class(), WC()->cart->get_customer() );
					$product_item_tax  = WC_Tax::calc_tax( $product_unit_price, $product_tax_rates, false );
					$product_item_tax  = array_sum( $product_item_tax );
					$product_item_tax  = wc_round_tax_total( $product_item_tax, 0 );
				}

				$unit_amount                          = yith_ppwc_format_amount( wc_remove_number_precision( $product_unit_price ) );
				$tax                                  = yith_ppwc_format_amount( wc_remove_number_precision( $product_item_tax ) );
				$body['purchase_units'][0]['items'][] = array(
					'name'        => $product->get_name(),
					'description' => $this->check_request_field_maxlength( wc_get_formatted_cart_item_data( $cart_item, true ) ),
					'sku'         => $product->get_sku(),
					'unit_amount' => $unit_amount,
					'tax'         => $tax,
					'quantity'    => (string) $cart_item['quantity'],
				);

				$temp_cart_total_items += ( $unit_amount['value'] * $cart_item['quantity'] );
				$temp_cart_total_tax   += ( $tax['value'] * $cart_item['quantity'] );
			}

			$difference = abs( ( $temp_cart_total_items + $temp_cart_total_tax ) - ( $cart_total_items + $cart_total_tax ) );
			// If the sum of temp total and tax is equal to the WooCommerce values, go ahead and add breakdown values.
			if ( yith_ppwc_round_amount( $difference ) < 0.01 ) {
				// Set items total and tax.
				$body['purchase_units'][0]['amount']['breakdown']['item_total'] = yith_ppwc_format_amount( $temp_cart_total_items );
				$body['purchase_units'][0]['amount']['breakdown']['tax_total']  = yith_ppwc_format_amount( $temp_cart_total_tax );

				if ( $needs_shipping ) {
					$body['purchase_units'][0]['amount']['breakdown']['shipping'] = yith_ppwc_format_amount( WC()->cart->get_shipping_total() + WC()->cart->get_shipping_tax() );
				}

				// Manage discount amount.
				$discount_amount = WC()->cart->has_discount() ? WC()->cart->get_discount_total() + WC()->cart->get_discount_tax() : 0;

				// Add fee costs if any.
				$fees = WC()->cart->get_fees();
				if ( ! empty( $fees ) ) {
					$fees_amount = WC()->cart->get_fee_total() + WC()->cart->get_fee_tax();
					// Compatibility with Woo Discount Rules by FlyCart. It set some cart discount as fee.
					if ( defined( 'WDR_CORE' ) && $fees_amount < 0 ) {
						// Discount must be a positive value.
						$discount_amount += ( $fees_amount * -1 );
					} else {
						$body['purchase_units'][0]['amount']['breakdown']['handling'] = yith_ppwc_format_amount( $fees_amount );
					}
				}

				if ( defined( 'YITH_YWGC_INIT' ) && isset( WC()->cart->applied_gift_cards ) ) {
					foreach ( WC()->cart->applied_gift_cards as $code ) {
						$discount_amount += isset( WC()->cart->applied_gift_cards_amounts[ $code ] ) ? (float) WC()->cart->applied_gift_cards_amounts[ $code ] : 0;
					}
				}

				// Adds discount if any.
				if ( ! empty( $discount_amount ) ) {
					$body['purchase_units'][0]['amount']['breakdown']['discount'] = yith_ppwc_format_amount( $discount_amount );
				}
			} else {
				// Since WC totals cannot be equal to PayPal avoid send items.
				unset( $body['purchase_units'][0]['items'] );
			}
		}

		if ( $needs_shipping && defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			$this->set_shipping_address( $body, WC()->customer );
		}

		return $body;
	}

	/**
	 * Create the body request from order
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param number $order_id The id of the order that should be paid.
	 * @return array
	 */
	public function build_request_from_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array();
		}

		$order_total = yith_ppwc_round_amount( $order->get_total( 'edit' ) );

		// Add fee costs if any.
		$fees          = $order->get_fees();
		$total_tax_fee = 0;
		if ( $fees ) {
			foreach ( $fees as $id => $fee ) {
				$total_tax_fee += (float) $fee->get_total_tax();
			}
		}

		$order_total_item     = yith_ppwc_round_amount( $order->get_subtotal() );
		$order_total_item_tax = yith_ppwc_round_amount( $order->get_cart_tax() - $total_tax_fee );

		$currency                  = $order->get_currency();
		$needs_shipping            = ! empty( $order->get_shipping_method() );
		$temp_order_total_item     = 0;
		$temp_order_total_item_tax = 0;

		$body = $this->get_body_base( $order_total, $currency, $needs_shipping );

		if ( apply_filters( 'yith_ppwc_build_request_details', true, 'order' ) ) {

			$body['purchase_units'][0]['invoice_id'] = $this->gateway->get_prefix() . $order_id;

			foreach ( $order->get_items( 'line_item' ) as $order_item ) {

				$product = wc_get_product( $order_item['product_id'] );
				if ( ! $product ) {
					continue;
				}

				$item_subtotal = yith_ppwc_round_amount( $order->get_item_subtotal( $order_item, false, false ) );
				$item_tax      = yith_ppwc_round_amount( (float) $order->get_item_subtotal( $order_item, true, false ) - (float) $order->get_item_subtotal( $order_item, false, false ) );

				$body['purchase_units'][0]['items'][] = array(
					'name'        => $product->get_name(),
					'description' => $this->check_request_field_maxlength( yith_ppwc_get_order_item_description( $order_item ) ),
					'sku'         => $product->get_sku(),
					'unit_amount' => yith_ppwc_format_amount( $item_subtotal, $currency ),
					'tax'         => yith_ppwc_format_amount( $item_tax, $currency ),
					'quantity'    => (string) $order_item->get_quantity(),
				);

				$temp_order_total_item     += $order_item->get_quantity() * $item_subtotal;
				$temp_order_total_item_tax += $order_item->get_quantity() * $item_tax;
			}

			$difference  = abs( ( $temp_order_total_item + $temp_order_total_item_tax ) - ( $order_total_item + $order_total_item_tax ) );
			$difference2 = abs( $order_total - ( $order_total_item + $order_total_item_tax ) );
			// If the sum of temp total and tax is equal to the WooCommerce values, go ahead and add breakdown values.
			if ( yith_ppwc_round_amount( $difference ) < 0.01 && yith_ppwc_round_amount( $difference2 ) < 0.01 ) {
				// Set items total and tax.
				$body['purchase_units'][0]['amount']['breakdown']['item_total'] = yith_ppwc_format_amount( $temp_order_total_item );
				$body['purchase_units'][0]['amount']['breakdown']['tax_total']  = yith_ppwc_format_amount( $temp_order_total_item_tax );

				if ( $needs_shipping ) {
					$body['purchase_units'][0]['amount']['breakdown']['shipping'] = yith_ppwc_format_amount( (float) $order->get_shipping_total() + (float) $order->get_shipping_tax() );
				}

				// Get order discount amount.
				$discount_amount = $order->get_total_discount( false );
				// Add fee costs if any.
				if ( $fees ) {
					$fees_amount = $order->get_total_fees() + $total_tax_fee;
					// Compatibility with Woo Discount Rules by FlyCart. It set some cart discount as fee.
					if ( defined( 'WDR_CORE' ) && $fees_amount < 0 ) {
						// Discount must be a positive value.
						$discount_amount += ( $fees_amount * - 1 );
					} else {
						$body['purchase_units'][0]['amount']['breakdown']['handling'] = yith_ppwc_format_amount( $fees_amount );
					}
				}

				// YITH Gift Card support
				if ( defined( 'YITH_YWGC_INIT' ) ) {
					$gift_cards = $order->get_meta( '_ywgc_applied_gift_cards' );
					if ( ! empty( $gift_cards ) ) { // If there are gift card applied, sum the amount to discount
						$discount_amount += (float) $order->get_meta( '_ywgc_applied_gift_cards_totals' );
					}
				}

				// Adds discounts inside the request.
				if ( $discount_amount > 0 ) {
					$body['purchase_units'][0]['amount']['breakdown']['discount'] = yith_ppwc_format_amount( $discount_amount );
				}
			} else {
				// Since WC totals cannot be equal to PayPal avoid send items.
				unset( $body['purchase_units'][0]['items'] );
			}
		}

		if ( $needs_shipping ) {
			$this->set_shipping_address( $body, $order );
		}

		return $body;

	}

	/**
	 * Check if max length is valid for fields, otherwise cut it
	 *
	 * @since 1.1.2
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string  $string String.
	 * @param integer $maxlength By default is 127.
	 * @return string
	 */
	public function check_request_field_maxlength( $string, $maxlength = 125 ) {
		if ( empty( $string ) || strlen( $string ) < $maxlength ) {
			return $string;
		}

		$maxlength -= 3; // Save three chars for ...
		$words      = preg_split( '/\s/', $string );
		$output     = '';
		$i          = 0;
		while ( true ) {
			$length = strlen( $output ) + strlen( $words[ $i ] );
			if ( $length > $maxlength ) {
				break;
			} else {
				$output .= ' ' . $words[ $i ];
				++ $i;
			}
		}
		$output .= '...';

		return trim( $output );
	}

	/**
	 * Create the body request from order to update PayPal order
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param number $order_id The id of the order that should be paid.
	 * @return string
	 */
	public function build_update_request_from_order( $order_id ) {

		$request_from_order = $this->build_request_from_order( $order_id );

		if ( empty( $request_from_order ) || empty( $request_from_order['purchase_units'] ) ) {
			return '';
		}

		return $request_from_order['purchase_units'][0];
	}

	/**
	 * Get transaction request body base
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param float   $total The request total amount.
	 * @param string  $currency The currency, default is the WooCommerce one.
	 * @param boolean $needs_shipping If request needs shipping or not.
	 * @return array
	 */
	protected function get_body_base( $total, $currency = '', $needs_shipping = true ) {

		$body = array(
			'intent'              => strtoupper( $this->gateway->get_intent() ),
			'application_context' => array(
				'brand_name' => get_bloginfo( 'name' ),
			),
			'purchase_units'      => array(
				array(
					'amount' => yith_ppwc_format_amount( $total, $currency ),
				),
			),
		);

		// Set the shipping preference based on cart.
		if ( ! $needs_shipping ) {
			$body['application_context']['shipping_preference'] = 'NO_SHIPPING';
		}

		return $body;
	}

	/**
	 * Load the address map.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return void
	 */
	protected function load_address_map() {
		$this->address_map = array(
			'address_1' => 'address_line_1',
			'address_2' => 'address_line_2',
			'state'     => 'admin_area_1',
			'city'      => 'admin_area_2',
			'postcode'  => 'postal_code',
			'country'   => 'country_code',
		);
	}

	/**
	 * Set shipping address for the request body
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param array                $body Array body.
	 * @param WC_Customer|WC_Order $object Object.
	 * @return void
	 */
	protected function set_shipping_address( &$body, $object ) {
		$address = $this->get_valid_shipping_address( $object );
		if ( empty( $address ) ) {
			return;
		}

		// Set shipping preference.
		$body['application_context']['shipping_preference'] = 'SET_PROVIDED_ADDRESS';
		// Set address.
		$body['purchase_units'][0]['shipping']['address'] = $address;
		$body['purchase_units'][0]['shipping']['name']    = array(
			'full_name' => $object->get_shipping_first_name() . ' ' . $object->get_shipping_last_name(),
		);
	}


	/**
	 * Validate given shipping address for the request body
	 * Double validate address if WC check failed and to be sure that address sent to PayPal is always correct
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param WC_Customer|WC_Order $object Object.
	 * @return array
	 * @throws Exception Exception message.
	 */
	protected function get_valid_shipping_address( $object ) {

		$address = array();

		try {

			$country = $object->get_shipping_country();
			if ( empty( $country ) ) {
				throw new Exception( 'Shipping country missing' );
			}

			// avoid filtering.
			remove_all_filters( 'woocommerce_default_address_fields' );
			remove_all_filters( 'woocommerce_shipping_fields' );
			$fields = WC()->countries->get_address_fields( $country, 'shipping_' );

			foreach ( $this->address_map as $key => $pp_key ) {
				$key    = 'shipping_' . $key;
				$method = 'get_' . $key;
				$value  = $object->$method();

				// validate postcode.
				if ( 'shipping_postcode' === $key ) {
					$value = wc_format_postcode( $value, $country );
					if ( '' !== $value && ! WC_Validation::is_postcode( $value, $country ) ) {
						throw new Exception( 'Invalid postcode format' );
					}
				}

				// if field it is required and value is empty.
				if ( isset( $fields[ $key ] ) && ! empty( $fields[ $key ]['required'] ) && '' === $value ) {
					throw new Exception( 'Required field missing' );
				}

				$address[ $pp_key ] = $value;
			}

			return $address;

		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Refund and order.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param null|float $amount Amount to refund, if null the refund is total.
	 * @param string     $currency Order currency.
	 * @param string     $reason Refund reason.
	 * @param string     $transaction_id Transaction id of order.
	 * @param string     $payer_id Payer id.
	 * @param string     $invoice_id PayPal Invoice id.
	 *
	 * @throws Exception Throws Exception.
	 */
	public function refund_order( $amount, $currency, $reason, $transaction_id, $payer_id, $invoice_id ) {
		$token       = YITH_PayPal_Merchant::get_merchant()->get( 'token' );
		$merchant_id = YITH_PayPal_Merchant::get_merchant()->get_client_id();

		$body = array(
			'invoice_id' => $invoice_id,
		);

		if ( ! empty( $reason ) ) {
			$body['note_to_payer'] = yith_ppwc_format_string( $reason, 255 );
		}

		if ( ! is_null( $amount ) ) {
			$body['amount'] = yith_ppwc_format_amount( $amount, $currency );
		}

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'PayPal-Partner-Attribution-Id' => YITH_PayPal::get_instance()->get_gateway()->get_bn_code(),
				'Authorization'                 => "Bearer $token",
				'Content-Type'                  => ' application/json',
			),
			'body'    => wp_json_encode( $body ),
		);

		$refund_transaction = $this->do_api_request( '/v2/payments/captures/' . $transaction_id . '/refund', $args );

		if ( empty( $refund_transaction ) || 'COMPLETED' !== $refund_transaction['status'] || ! isset( $refund_transaction['id'] ) ) {
			throw new Exception( esc_html_x( 'The refund process is not completed', 'Admin error message', 'yith-paypal-payments-for-woocommerce' ) );
		}

		return $refund_transaction['id'];
	}

	/**
	 * Get order details
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $order_id The PayPal order ID created.
	 * @return array
	 * @throws Exception Throws Exception.
	 */
	public function get_order_details( $order_id ) {

		$token = YITH_PayPal_Merchant::get_merchant()->get( 'token' );

		$args = array(
			'method'  => 'GET',
			'headers' => array(
				'PayPal-Partner-Attribution-Id' => YITH_PayPal::get_instance()->get_gateway()->get_bn_code(),
				'Authorization'                 => "Bearer $token",
				'Content-Type'                  => 'application/json',
			),
		);

		return $this->do_api_request( '/v2/checkout/orders/' . $order_id, $args );
	}

}
