<?php
/**
 * Webhook Request handler class
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Request_Webhook
 */
class YITH_PayPal_Request_Webhook extends YITH_PayPal_Request {

	/**
	 * Return the list of events to subscribe
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return array
	 */
	private function get_webook_events_to_subscribe() {
		$events = array(
			'PAYMENT.AUTHORIZATION.CREATED',
			'PAYMENT.AUTHORIZATION.VOIDED',
			'PAYMENT.CAPTURE.COMPLETED',
			'PAYMENT.CAPTURE.DENIED',
			'PAYMENT.CAPTURE.PENDING',
			'PAYMENT.CAPTURE.REFUNDED',
			'PAYMENT.CAPTURE.REVERSED',
			'CHECKOUT.ORDER.COMPLETED',
			'CHECKOUT.ORDER.APPROVED',
		);

		$event_object = array();
		foreach ( $events as $event ) {
			$event_object[] = array( 'name' => $event );
		}

		return $event_object;
	}


	/**
	 * Subscribe the webhook
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param string $token Token.
	 * @throws Exception Throws Exception.
	 */
	public function register_webhook( $token ) {

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => "Bearer $token",
				'Content-Type'  => ' application/json',
			),
			'body'    => wp_json_encode(
				array(
					'url'         => YITH_PayPal_Webhook::get_webhook()->get_webhook_url(),
					'event_types' => $this->get_webook_events_to_subscribe(),
				)
			),
		);

		try {
			$response = $this->do_api_request( '/v1/notifications/webhooks', $args );
		} catch ( Exception $e ) {
			$response = $this->refresh_webhook( $token );
		}

		return $response;

	}

	/**
	 * Refresh webhook
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param string $token Token.
	 * @throws Exception Throws Exception.
	 */
	protected function refresh_webhook( $token ) {
		$args = array(
			'method'  => 'GET',
			'headers' => array(
				'Authorization' => "Bearer $token",
				'Content-Type'  => ' application/json',
			),
		);

		$response = $this->do_api_request( '/v1/notifications/webhooks', $args );

		if ( ! isset( $response['webhooks'] ) ) {
			throw new Exception( 'Error: No webhooks registered have been found for this site.' );
		}

		if ( isset( $response['webhooks'] ) && is_array( $response['webhooks'] ) ) {
			foreach ( $response['webhooks'] as $key => $webhook ) {
				if ( isset( $webhook['url'] ) && YITH_PayPal_Webhook::get_webhook()->get_webhook_url() !== $webhook['url'] ) {
					unset( $response['webhooks'][ $key ] );
				}
			}
		}

		if ( ! isset( $response['webhooks'] ) ) {
			throw new Exception( 'Error: No webhooks registered have been found for this site.' );
		}

		return array_values( $response );

	}

	/**
	 * Verify the webhook signature
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param array $headers Header sent by PayPal.
	 * @param array $body Body arguments sent by PayPal.
	 * @return bool;
	 * @throws Exception Throws Exception.
	 */
	public function verify_webhook_signature( $headers, $body ) {

		$token   = YITH_PayPal_Merchant::get_merchant()->get( 'token' );
		$webhook = $this->refresh_webhook( $token );

		if ( empty( $webhook[0][0]['id'] ) ) {
			return false;
		}

		$transmission_id   = isset( $headers['PAYPAL-TRANSMISSION-ID'] ) ? $headers['PAYPAL-TRANSMISSION-ID'] : ( isset( $headers['Paypal-Transmission-Id'] ) ? $headers['Paypal-Transmission-Id'] : '' );
		$transmission_time = isset( $headers['PAYPAL-TRANSMISSION-TIME'] ) ? $headers['PAYPAL-TRANSMISSION-TIME'] : ( isset( $headers['Paypal-Transmission-Time'] ) ? $headers['Paypal-Transmission-Time'] : '' );
		$transmission_sig  = isset( $headers['PAYPAL-TRANSMISSION-SIG'] ) ? $headers['PAYPAL-TRANSMISSION-SIG'] : ( isset( $headers['Paypal-Transmission-Sig'] ) ? $headers['Paypal-Transmission-Sig'] : '' );
		$cert_url          = isset( $headers['PAYPAL-CERT-URL'] ) ? $headers['PAYPAL-CERT-URL'] : ( isset( $headers['Paypal-Cert-Url'] ) ? $headers['Paypal-Cert-Url'] : '' );
		$auth_algo         = isset( $headers['PAYPAL-AUTH-ALGO'] ) ? $headers['PAYPAL-AUTH-ALGO'] : ( isset( $headers['Paypal-Auth-Algo'] ) ? $headers['Paypal-Auth-Algo'] : '' );

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => "Bearer $token",
				'Content-Type'  => ' application/json',
			),
			'body'    => wp_json_encode(
				array(
					'transmission_id'   => $transmission_id,
					'transmission_time' => $transmission_time,
					'transmission_sig'  => $transmission_sig,
					'cert_url'          => $cert_url,
					'auth_algo'         => $auth_algo,
					'webhook_id'        => $webhook[0][0]['id'],
					'webhook_event'     => $body,
				)
			),
		);

		$response = $this->do_api_request( '/v1/notifications/verify-webhook-signature', $args );

		if ( 'SUCCESS' !== $response['verification_status'] ) {
			return false;
		}

		return true;
	}
}
