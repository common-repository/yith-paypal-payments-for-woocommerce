<?php
/**
 * Abstract for plugin request
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Request
 */
abstract class YITH_PayPal_Request {

	/**
	 * THe gateway class
	 *
	 * @var YITH_PayPal_Gateway
	 */
	protected $gateway = null;

	/**
	 * The class construct
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @throws Exception Throws Exception.
	 */
	public function __construct() {
		$this->gateway = YITH_PayPal::get_instance()->get_gateway();
	}


	/**
	 * Validate request data
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @param array        $data An array of data to validate.
	 * @param array|string $expected An array of expected data.
	 * @return array
	 * @throws Exception Throws Exception.
	 */
	protected function validate_required( $data, $expected = array() ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			throw new Exception( 'Error: No data to validate or data is not an array.' );
		}
		! is_array( $expected ) && $expected = explode( '', $expected ); //phpcs:ignore
		$validated                           = array();

		foreach ( $data as $key => $value ) {
			if ( empty( $expected ) || in_array( $key, $expected, true ) ) {
				$validated[ $key ] = sanitize_text_field( $value );
			}
		}
		// check fir missing expected.
		$validated      = array_filter( $validated );
		$validated_keys = array_keys( $validated );
		$missing        = array_diff( $expected, $validated_keys );

		if ( ! empty( $missing ) ) {
			throw new Exception( 'Error: Missing expected data from request: ' . implode( ', ', $missing ) );
		}
		return $validated;
	}

	/**
	 * Do an API request
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @param string $endpoint The endpoint of the request.
	 * @param array  $args An array of request arguments.
	 * @return array
	 * @throws Exception Throws Exception.
	 */
	protected function do_api_request( $endpoint, $args = array() ) {

		$defaults = array(
			'method'  => 'GET',
			'timeout' => 25,
		);

		// Merge given args with default.
		$args    = array_merge( $defaults, $args );
		$gateway = YITH_PayPal::get_instance()->get_gateway();
		$url     = $gateway->get_api_url() . $endpoint;
		$url     = $this->search_replace_url( $url, $gateway ); // Replace placeholder.

		if ( '/v1/identity/generate-token' !== $endpoint ) {
			YITH_PayPal_Logger::log( 'Request to: ' . $url, 'info' );
            YITH_PayPal_Logger::log( 'Arguments: ' . print_r( $args, 1 ),'info' ); // phpcs:ignore
		}
		// Make the request.
		$response = wp_safe_remote_request( $url, $args );

		if ( is_wp_error( $response ) || ! in_array( absint( $response['response']['code'] ), array( 200, 201, 400, 204 ), true ) ) {
			YITH_PayPal_Logger::log( 'Error: Unable to process the PayPal API Request.' . print_r( $response, true ) ); // phpcs:ignore
			throw new Exception( esc_html_x( 'Error: Unable to process the PayPal API Request.', 'Checkout error message', 'yith-paypal-payments-for-woocommerce' ) );
		}

		if ( '/v1/identity/generate-token' !== $endpoint ) {
			YITH_PayPal_Logger::log( 'Response: ' . print_r( $response, true ), 'info' ); // phpcs:ignore
		}

		return json_decode( $response['body'], true );
	}

	/**
	 * Search and replace request placeholders
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @param string                   $url URL.
	 * @param YITH_PayPal_Gateway|null $gateway Gateway.
	 * @return string
	 */
	protected function search_replace_url( $url, $gateway = null ) {

		is_null( $gateway ) && YITH_PayPal::get_instance()->get_gateway();

		$placeholders = array(
			'%%partner_id%%'        => $gateway->get_partner_id(),
			'%%partner_client_id%%' => $gateway->get_partner_client_id(),
		);

		foreach ( $placeholders as $search => $replace ) {
			$url = str_replace( $search, $replace, $url );
		}

		return $url;
	}
}
