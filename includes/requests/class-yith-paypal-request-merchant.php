<?php
/**
 * Merchant Request handler class
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Request_Merchant
 */
class YITH_PayPal_Request_Merchant extends YITH_PayPal_Request {

	/**
	 * Get an access token
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 *
	 * @param array  $token_data Token data.
	 * @param string $auth Auth code.
	 *
	 * @return array
	 * @throws Exception Throws Exception.
	 */
	public function refresh_access_token( $token_data, $auth ) {

		$args = array(
			'headers' => array(
				'Authorization' => "Basic $auth",
				'Content-Type'  => ' application/x-www-form-urlencoded',
			),
			'body'    => array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $token_data['refresh_token'],
				'code_verifier' => yith_ppwc_get_nonce(),
			),
		);

		$response = $this->do_api_request( '/v1/identity/openidconnect/tokenservice', $args );

		if ( ! isset( $response['access_token'] ) || ! isset( $response['expires_in'] ) ) {
			throw new Exception( 'Error: An error occurred refreshing merchant token.' . print_r( $response, true ) ); // phpcs:ignore
		}

		return $response;
	}

	/**
	 * Get the merchant status
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 *
	 * @param string $merchant_id Merchant id.
	 * @param string $token Token.
	 * @return array
	 * @throws Exception Throws Exception.
	 */
	public function get_status( $merchant_id, $token ) {
		$args = array(
			'headers' => array(
				'Authorization' => "Bearer $token",
				'Content-Type'  => ' application/json',
			),
		);

		return $this->do_api_request( '/v1/customer/partners/%%partner_id%%/merchant-integrations/' . $merchant_id, $args );
	}


}
