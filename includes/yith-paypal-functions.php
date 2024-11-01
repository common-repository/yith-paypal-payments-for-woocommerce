<?php
/**
 * Utility Functions
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'yith_ppwc_credit_cards_list' ) ) {
	/**
	 * Return the PayPal credit card list.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return array
	 */
	function yith_ppwc_credit_cards_list() {
		return array(
			'visa'       => esc_html_x( 'Visa', 'Admin option, credit card name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'mastercard' => esc_html_x( 'Mastercard', 'Admin option, credit card name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'amex'       => esc_html_x( 'American Express', 'Admin option, credit card name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'discover'   => esc_html_x( 'Discover', 'Admin option, credit card name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'jcb'        => esc_html_x( 'JCB', 'Admin option, credit card name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'elo'        => esc_html_x( 'ELO', 'Admin option, credit card name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'hiper'      => esc_html_x( 'Hiper', 'Admin option, credit card name do not translate', 'yith-paypal-payments-for-woocommerce' ),
		);
	}
}

if ( ! function_exists( 'yith_ppwc_funding_sources_list' ) ) {
	/**
	 * Return the PayPal funding sources list.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return array
	 */
	function yith_ppwc_funding_sources_list() {
		return array(
			'card'       => esc_html_x( 'Credit or debit cards', 'Admin option', 'yith-paypal-payments-for-woocommerce' ),
			'credit'     => esc_html_x( 'PayPal Credit', 'Admin option, payment method name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'bancontact' => esc_html_x( 'Bancontact', 'Admin option, payment method name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'blik'       => esc_html_x( 'BLIK', 'Admin option, payment method name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'eps'        => esc_html_x( 'eps', 'Admin option, payment method name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'giropay'    => esc_html_x( 'giropay', 'Admin option, payment method name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'ideal'      => esc_html_x( 'iDEAL', 'Admin option, payment method name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'mybank'     => esc_html_x( 'MyBank', 'Admin option, payment method name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'p24'        => esc_html_x( 'Przelewy24', 'Admin option, payment method name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'sepa'       => esc_html_x( 'SEPA-Lastschrift', 'Admin option, payment method name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'sofort'     => esc_html_x( 'Sofort', 'Admin option, payment method name do not translate', 'yith-paypal-payments-for-woocommerce' ),
			'venmo'      => esc_html_x( 'Venmo', 'Admin option, payment method name do not translate', 'yith-paypal-payments-for-woocommerce' ),
		);
	}
}

if ( ! function_exists( 'yith_ppwc_get_tracking_id' ) ) {
	/**
	 * Return the a tracking id to tracking the login.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return string
	 */
	function yith_ppwc_get_tracking_id() {
		return apply_filters( 'yith_ppwc_tracking_id', substr( bin2hex( md5( get_site_url() . '-' . get_current_user_id() ) ), 0, 20 ) );
	}
}

if ( ! function_exists( 'yith_ppwc_get_nonce' ) ) {
	/**
	 * Get a request nonce
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return string
	 */
	function yith_ppwc_get_nonce() {
		$user = wp_get_current_user();
		$str  = sha1( $user->user_email );
		$salt = md5( $user->user_email );

		return hash( 'gost', $str . $salt );
	}
}


if ( ! function_exists( 'yith_ppwc_get_disabled_funding' ) ) {
	/**
	 * Return the disabled funding sources
	 *
	 * @param bool $implode Specific what type of result.
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return array|string
	 */
	function yith_ppwc_get_disabled_funding_sources( $implode = true ) {
		$funding_sources          = array_keys( yith_ppwc_funding_sources_list() );
		$enabled_funding_sources  = get_option( 'yith_ppwc_button_funding_sources', array() );
		$disabled_funding_sources = array_diff( $funding_sources, $enabled_funding_sources );

		return $implode ? implode( ',', $disabled_funding_sources ) : $disabled_funding_sources;
	}
}

if ( ! function_exists( 'yith_ppwc_are_credit_cards_disabled' ) ) {
	/**
	 * Return if the credit cards are enabled or not.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return string
	 */
	function yith_ppwc_are_credit_cards_enabled() {
		$enabled_funding_sources = get_option( 'yith_ppwc_button_funding_sources', array() );

		return in_array( 'card', $enabled_funding_sources ); //phpcs:ignore
	}
}

if ( ! function_exists( 'yith_ppwc_is_custom_credit_card_enabled' ) ) {
	/**
	 * Return if the credit cards are enabled or not.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return string
	 */
	function yith_ppwc_is_custom_credit_card_enabled() {
		$opt     = get_option( 'yith_ppwc_cc_gateway_options' );
		$enabled = isset( $opt['enabled'] ) ? $opt['enabled'] : 'no';

		return 'yes' === $enabled;
	}
}

if ( ! function_exists( 'yith_ppwc_get_disabled_cards' ) ) {
	/**
	 * Return the disabled cards
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return string
	 */
	function yith_ppwc_get_disabled_cards() {
		$cards          = array_keys( yith_ppwc_credit_cards_list() );
		$enabled_cards  = get_option( 'yith_ppwc_button_credit_cards', array() );
		$disabled_cards = array_diff( $cards, $enabled_cards );

		return implode( ',', $disabled_cards );
	}
}

if ( ! function_exists( 'yith_ppwc_status_label' ) ) {
	/**
	 * Return merchant status label.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param string $status The status merchant key.
	 * @return array
	 */
	function yith_ppwc_status_label( $status ) {
		$status_labels = array(
			'active'     => esc_html_x( 'Active', 'Merchant login status in admin options', 'yith-paypal-payments-for-woocommerce' ),
			'not-active' => esc_html_x( 'Not Active', 'Merchant login status in admin options', 'yith-paypal-payments-for-woocommerce' ),
		);

		return isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
	}
}

if ( ! function_exists( 'yith_ppwc_get_order_item_description' ) ) {
	/**
	 * Return the description of an order item
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param WC_Order_Item $order_item Order item.
	 * @return string
	 */
	function yith_ppwc_get_order_item_description( $order_item ) {
		$item_desc = array();
		foreach ( $order_item->get_formatted_meta_data() as $meta ) {
			$item_desc[] = sprintf( '%s: %s', $meta->display_key, $meta->display_value );
		}
		$item_desc = implode( ',', (array) $item_desc );

		return apply_filters( 'yith_ppwc_get_order_item_description', yith_ppwc_format_string( $item_desc ), $order_item );
	}
}

if ( ! function_exists( 'yith_ppwc_format_string' ) ) {
	/**
	 * Limit length of an string.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param string  $string The string to format.
	 * @param integer $limit The maximum string length value.
	 * @return string
	 */
	function yith_ppwc_format_string( $string, $limit = 127 ) {
		if ( strlen( $string ) > $limit ) {
			$string = substr( $string, 0, $limit - 3 ) . '...';
		}

		return wp_strip_all_tags( $string );
	}
}

if ( ! function_exists( 'yith_ppwc_round_amount' ) ) {
	/**
	 * Return the amount rounded with 2 decimals as PayPal require.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param float $amount Value to round.
	 * @return float
	 */
	function yith_ppwc_round_amount( $amount ) {
		return (float) wc_format_decimal( (float) $amount, 2, false );
	}
}

if ( ! function_exists( 'yith_ppwc_format_amount' ) ) {
	/**
	 * Return the amount formatted as PayPal require.
	 *
	 * @see https://developer.paypal.com/docs/api/orders/v1/?mark=purchase_units#definition-amount.
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param float  $amount The amount to round.
	 * @param string $currency The currency to use.
	 * @return array
	 */
	function yith_ppwc_format_amount( $amount, $currency = '' ) {
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}

		return array(
			'currency_code' => $currency,
			'value'         => (string) yith_ppwc_round_amount( $amount ),
		);
	}
}

if ( ! function_exists( 'yith_ppwc_return_yes' ) ) {
	/**
	 * Util function that return string 'yes'. Useful for filter WooCommerce settings
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return string
	 */
	function yith_ppwc_return_yes() {
		return 'yes';
	}
}

if ( ! function_exists( 'yith_ppwc_check_gateway' ) ) {
	/**
	 * Util function that check if the gateway is right
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param string $gateway Gateway to check.
	 * @return bool
	 */
	function yith_ppwc_check_gateway( $gateway ) {
		return in_array( $gateway, array( YITH_Paypal::GATEWAY_ID, YITH_Paypal::GATEWAY_ID . '_custom_card' ) ); //phpcs:ignore
	}
}


if ( ! function_exists( 'yith_ppwc_get_pp_support_link' ) ) {
	/**
	 * Return the html link to PayPal Support
	 *
	 * @since 1.2.3
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return string
	 */
	function yith_ppwc_get_pp_support_link() {
		return sprintf( '<a href="https://www.paypal.com/smarthelp/contact-us">%s</a>', esc_html_x( 'PayPal support', 'this is a label of the link to PayPal support', 'yith-paypal-payments-for-woocommerce' ) );
	}
}

if ( ! function_exists( 'yith_ppwc_get_wp_support_link' ) ) {
	/**
	 * Return the html link to WordPress Forum Support
	 *
	 * @since 1.2.3
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return string
	 */
	function yith_ppwc_get_wp_support_link() {
		return sprintf( '<a href="https://wordpress.org/support/plugin/yith-paypal-payments-for-woocommerce/">%s</a>', esc_html_x( 'wp.org forum', 'this is a label of the link to WordPress forum', 'yith-paypal-payments-for-woocommerce' ) );
	}
}
