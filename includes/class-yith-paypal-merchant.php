<?php
/**
 * Merchant class
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Merchant
 */
class YITH_PayPal_Merchant {

	/**
	 * Current merchant environment
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $environment = '';

	/**
	 * The merchant token
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $token = '';

	/**
	 * An array of merchant data
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $data = array();

	/**
	 * A flag to check if data changed and need to be saved
	 *
	 * @since 1.0.0
	 * @var boolean
	 */
	protected $changed = false;

	/**
	 * Single instance of the class
	 *
	 * @since 1.0.0
	 * @var YITH_PayPal_Merchant
	 */
	protected static $instance;

	/**
	 * Returns single instance of the class
	 *
	 * @since 1.0.0
	 * @return YITH_PayPal_Merchant
	 */
	public static function get_merchant() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 2.1
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, 'Cloning is forbidden.', '1.0.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 2.1
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, 'Unserializing instances of this class is forbidden.', '1.0.0' );
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	private function __construct() {
		$this->load_environment();
		$this->load_data();

		// force save on shutdown.
		add_action( 'shutdown', array( $this, 'save' ) );
	}

	/**
	 * Load merchant data based on gateway environment
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return void
	 */
	protected function load_environment() {
		$gateway = YITH_PayPal::get_instance()->get_gateway();
		if ( $gateway ) {
			$this->environment = $gateway->get_environment();
		}
	}

	/**
	 * Load merchant data based on gateway environment
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return void
	 */
	protected function load_data() {
		$this->data = get_option( 'yith_ppwc_merchant_data_' . $this->environment, array() );
		// add token.
		$this->token = $this->get_token();
	}

	/**
	 * Reset merchant data
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return void
	 */
	protected function reset() {
		$this->data    = array();
		$this->token   = '';
		$this->changed = array();
	}

	/**
	 * Get the merchant token. Refresh it if false
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return string
	 */
	protected function get_token() {
		$token = get_transient( 'yith_ppwc_merchant_token_' . $this->environment );
		if ( false === $token || '' === $token ) {
			$token = $this->refresh_token();
		}

		return $token;
	}

	/**
	 * Set the merchant token.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param mixed $token_data THe token data to set.
	 * @return mixed
	 */
	protected function set_token( $token_data ) {
		$this->set_token_transient( $token_data['access_token'], $token_data['expires_in'] );

		return $token_data['access_token'];
	}

	/**
	 * Set the merchant token transient.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param string $token The token to set.
	 * @param string $expire The transient expire date.
	 * @return mixed
	 */
	protected function set_token_transient( $token, $expire ) {
		return set_transient( 'yith_ppwc_merchant_token_' . $this->environment, $token, $expire );
	}

	/**
	 * Refresh the merchant token. Refresh it if false
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return string
	 */
	protected function refresh_token() {

		if ( ! $this->is_valid() ) {
			return '';
		}

		try {

			$login = YITH_PayPal_Controller::load( 'login' );

			$token_data = $login->get_access_token( $this->get_client_id(), $this->get_client_secret() );

			$this->set_token_transient( $token_data['access_token'], $token_data['expires_in'] );

			return $token_data['access_token'];
		} catch ( Exception $e ) {
			YITH_PayPal_Logger::log( $e->getMessage() );

			return '';
		}
	}

	/**
	 * Refresh the merchant token. Refresh it if false
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return boolean
	 */
	protected function delete_token() {
		delete_option( 'yith_ppwc_merchant_token_info_' . $this->environment );
		delete_transient( 'yith_ppwc_merchant_token_' . $this->environment );

		return true;
	}

	/*****************
	 * PUBLIC METHODS
	 *****************/

	/**
	 * Process a merchant login
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param array $data Data.
	 * @return boolean
	 */
	public function login( $data ) {
		$handler = YITH_PayPal_Controller::load( 'login' );

		return $handler->process_request( $data );
	}

	/**
	 * Process a merchant logout
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return boolean
	 */
	public function logout() {
		delete_option( 'yith_ppwc_merchant_data_' . $this->environment );
		delete_option( 'yith_ppwc_merchant_token_info_' . $this->environment );
		delete_option( 'yith_ppwc_gateway_enabled_to_manage_custom_card' );
		delete_transient( 'yith_ppwc_merchant_token_' . $this->environment );

		$this->reset();

		return true;
	}

	/**
	 * Check if merchant is valid
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return boolean
	 */
	public function is_valid() {
		return ! empty( $this->data['client_id'] ) && ! empty( $this->data['client_secret'] ) && ! empty( $this->data['merchant_id'] );
	}

	/**
	 * Check the merchant status
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param boolean $force If force or not get status for merchant.
	 * @return boolean
	 */
	public function check_status( $force = false ) {

		$merchant_id = $this->get( 'merchant_id' );
		if ( ! $merchant_id || ! $this->token ) {
			return false;
		}

		try {

			// Avoid multiple auto request for merchant status within one hour.
			$transient = 'yith_ppwc_check_status_' . $merchant_id;
			if ( false !== get_transient( $transient ) && ! $force ) {
				return false;
			}

			$merchant = YITH_PayPal_Controller::load( 'merchant' );
			$status   = $merchant->get_status( $merchant_id, $this->token );

			isset( $status['payments_receivable'] ) && $this->set( 'payments_receivable', $status['payments_receivable'] );
			isset( $status['primary_email_confirmed'] ) && $this->set( 'primary_email_confirmed', $status['primary_email_confirmed'] );
			isset( $status['products'] ) && $this->set( 'products', $status['products'] );
			isset( $status['capabilities'] ) && $this->set( 'capabilities', $status['capabilities'] );

			$webhook = $this->get( 'webhook' );

			if ( ! $webhook ) {
				$webook          = YITH_PayPal_Controller::load( 'webhook' );
				$webook_response = $webook->register_webhook( $this->token );

				$this->set( 'webhook', $webook_response );
			}

			set_transient( $transient, 'yes', HOUR_IN_SECONDS );

			return true;
		} catch ( Exception $e ) {
			YITH_PayPal_Logger::log( $e->getMessage() );

			return false;
		}
	}

	/**
	 * Check if merchant is enabled for Custom Card Fields
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return boolean
	 */
	public function are_payments_receivable() {

		if ( ! isset( $this->data['payments_receivable'] ) ) {
			$this->check_status();
		}

		return ! empty( $this->data['payments_receivable'] );
	}

	/**
	 * Check if merchant is valid
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return boolean
	 */
	public function is_primary_email_confirmed() {
		$this->check_status();
		return ! empty( $this->data['primary_email_confirmed'] );
	}


	/**
	 * Check if merchant is enabled to manage the custom card fields.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return boolean
	 */
	public function is_enabled_to_custom_card_fields() {

		if ( ! isset( $this->data['products'] ) || ! isset( $this->data['capabilities'] ) ) {
			$this->check_status();
		}

		if ( ! isset( $this->data['products'] ) || ! isset( $this->data['capabilities'] ) ) {
			return false;
		}

		$is_enabled    = false;
		$base_location = wc_get_base_location();

		if ( isset( $base_location['country'] ) && in_array( $base_location['country'], array( 'US', 'GB', 'FR', 'IT', 'ES' ) ) ) { //phpcs:ignore
			$has_product_custom = array_search( 'PPCP_CUSTOM', array_column( $this->data['products'], 'name' ), true );

			if ( false !== $has_product_custom && isset( $this->data['products'][ $has_product_custom ]['vetting_status'] ) && 'SUBSCRIBED' === $this->data['products'][ $has_product_custom ]['vetting_status'] ) {
				$has_capability = array_search( 'CUSTOM_CARD_PROCESSING', array_column( $this->data['capabilities'], 'name' ), true );

				if ( false !== $has_capability && isset( $this->data['capabilities'][ $has_capability ]['status'] ) && 'ACTIVE' === $this->data['capabilities'][ $has_capability ]['status'] ) {
					$is_enabled = true;
				}
			}
		}
		update_option( 'yith_ppwc_gateway_enabled_to_manage_custom_card', wc_bool_to_string( $is_enabled ) );

		return $is_enabled;
	}

	/**
	 * Get a merchant data by key
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $key The key of the data to get.
	 * @return mixed
	 */
	public function get( $key ) {
		if ( 'token' === $key ) {
			return $this->token;
		} elseif ( isset( $this->data[ $key ] ) ) {
			return $this->data[ $key ];
		}

		return false;
	}

	/**
	 * Get auth code for merchant
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return mixed
	 */
	public function get_auth() {
		// base64 encode is requested by PayPal API.
		return $this->is_valid() ? base64_encode( $this->data['client_id'] . ':' . $this->data['client_secret'] ) : ''; //phpcs:ignore 
	}


	/**
	 * Get client id for merchant
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return mixed
	 */
	public function get_client_id() {
		return $this->is_valid() ? $this->data['client_id'] : '';
	}

	/**
	 * Get client secret for merchant
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return mixed
	 */
	public function get_client_secret() {
		return $this->is_valid() ? $this->data['client_secret'] : '';
	}


	/**
	 * Check if the customer is active or not
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return string
	 */
	public function is_active() {
		return $this->is_valid() ? 'active' : 'not-active';
	}

	/**
	 * Set a merchant data
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param string $key The key of the data to set.
	 * @param mixed  $value The value of the data to set.
	 * @return boolean
	 */
	public function set( $key, $value ) {

		if ( null === $value ) {
			return $this->delete( $key );
		} elseif ( 'token' === $key ) {
			return $this->set_token( $value );
		}

		$this->data[ $key ] = $value;
		$this->changed      = true;

		return true;
	}

	/**
	 * Delete a merchant data
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $key The key of the data to set.
	 * @return boolean
	 */
	public function delete( $key ) {

		if ( 'token' === $key ) {
			return $this->delete_token();
		}

		if ( ! isset( $this->data[ $key ] ) ) {
			return false;
		}

		unset( $this->data[ $key ] );
		$this->changed = true;

		return true;
	}

	/**
	 * Delete a merchant data
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return boolean
	 */
	public function save() {

		if ( $this->changed ) {
			update_option( 'yith_ppwc_merchant_data_' . $this->environment, $this->data );
			$this->changed = false;

			return true;
		}

		return false;
	}
}
