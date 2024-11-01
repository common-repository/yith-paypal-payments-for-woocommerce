<?php
/**
 * Login Request handler class
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Request_Login
 */
class YITH_PayPal_Request_Login extends YITH_PayPal_Request {

	/**
	 * Handle a request
	 *
	 * @param mixed $data Data.
	 *
	 * @return boolean true on success, false otherwise
	 * @throws Exception Throws Exception.
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function process_request( $data ) {

		try {
			// validate data.
			$data = $this->validate_required( $data, array( 'sharedId', 'authCode' ) );

			$merchant    = YITH_PayPal_Merchant::get_merchant();
			$token_data  = $this->get_access_token( $data['sharedId'], '', $data['authCode'] );
			$credentials = $this->get_client_credentials( $token_data['access_token'] );
			// store credentials.
			$merchant->set( 'client_id', $credentials['client_id'] );
			$merchant->set( 'client_secret', $credentials['client_secret'] );

			$token_data = $this->get_access_token( $credentials['client_id'], $credentials['client_secret'] );

			// store token.
			$token = $merchant->set( 'token', $token_data );

			// Register webhook.
			$webook          = YITH_PayPal_Controller::load( 'webhook' );
			$webook_response = $webook->register_webhook( $token );

			$merchant->set( 'webhook', $webook_response );

			return true;
		} catch ( Exception $e ) {
			YITH_PayPal_Logger::log( $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get client credentials.
	 *
	 * @param string $token Auth code.
	 * @return array
	 * @throws Exception Throws Exception.
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	protected function get_client_credentials( $token ) {

		$args = array(
			'timeout' => 25,
			'headers' => array(
				'Authorization' => "Bearer $token",
				'Content-Type'  => 'application/json',
			),
		);

		$response = $this->do_api_request( '/v1/customer/partners/%%partner_id%%/merchant-integrations/credentials/', $args );

		if ( ! isset( $response['client_id'] ) || ! isset( $response['client_secret'] ) ) {
			throw new Exception( 'Error: An error occurred getting merchant credentials.' . print_r( $response, true ) ); //phpcs:ignore
		}

		return $response;
	}

	/**
	 * Get an access token
	 *
	 * @param string $client_id Client id.
	 * @param string $secret_id Shared id.
	 * @param string $auth_code Auth code.
	 *
	 * @return array
	 * @throws Exception Throws Exception.
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 *
	 * @since 1.0.0
	 */
	public function get_access_token( $client_id, $secret_id, $auth_code = '' ) {
		// base64 requested by PayPal API.
		$auth = base64_encode( $client_id . ':' . $secret_id ); //phpcs:ignore
		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => "Basic $auth",
				'Content-Type'  => ' application/x-www-form-urlencoded',
			),
			'body'    => array(
				'grant_type' => 'client_credentials',
			),
		);

		if ( ! empty( $auth_code ) ) {
			$args['body'] = array(
				'grant_type'    => 'authorization_code',
				'code'          => $auth_code,
				'code_verifier' => yith_ppwc_get_nonce(),
			);
		}

		$response = $this->do_api_request( '/v1/oauth2/token', $args );

		if ( ! isset( $response['access_token'] ) || ! isset( $response['expires_in'] ) ) {
			throw new Exception( 'Error: An error occurred getting merchant token.' . print_r( $response, true ) ); //phpcs:ignore
		}

		return $response;
	}


	/**
	 * Get a client token needed for credit card payment.
	 *
	 * @return array
	 * @throws Exception Throws Exception.
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 *
	 * @since 1.0.0
	 */
	public function get_client_token() {

		$token = YITH_PayPal_Merchant::get_merchant()->get( 'token' );
		$args  = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => "Bearer $token",
				'Content-Type'  => 'application/json',
			),
		);

		$response = $this->do_api_request( '/v1/identity/generate-token', $args );

		if ( ! isset( $response['client_token'] ) || ! isset( $response['expires_in'] ) ) {
			throw new Exception( 'Error: An error occurred getting client token.' . print_r( $response, true ) ); //phpcs:ignore
		}

		return $response;
	}
}
