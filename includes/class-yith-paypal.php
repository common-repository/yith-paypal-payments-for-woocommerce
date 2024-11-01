<?php
/**
 * Main class
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal
 */
final class YITH_PayPal {

	/**
	 * The gateway ID
	 *
	 * @var string
	 */
	const GATEWAY_ID = 'yith_paypal_payments';

	/**
	 * Single instance of the class
	 *
	 * @var YITH_Paypal
	 * @since 1.0.0
	 */
	protected static $instance;

	/**
	 * The gateway class
	 *
	 * @var YITH_PayPal_Gateway
	 * @since 1.0.0
	 */
	protected $gateway = null;

	/**
	 * Returns single instance of the class
	 *
	 * @return YITH_Paypal
	 * @since 1.0.0
	 */
	public static function get_instance() {
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
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	private function __construct() {
		// plugin autoloader.
		include_once YITH_PAYPAL_PAYMENTS_PATH . 'includes/class-yith-paypal-autoloader.php';
		include_once YITH_PAYPAL_PAYMENTS_PATH . 'includes/yith-paypal-functions.php';
		include_once YITH_PAYPAL_PAYMENTS_PATH . 'includes/order/class-yith-paypal-order-helper.php';

		// Load plugin framework.
		add_action( 'plugins_loaded', array( $this, 'plugin_fw_loader' ), 15 );
		// Register the gateway on the list of available WooCommerce gateway.
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ), 10, 1 );

		add_action( 'init', array( $this, 'init' ), 10 );

		// Compatibility issue with WPC Product Bundles for WooCommerce.
		add_filter( 'yith_ppwc_build_request_details', array( $this, 'skip_details_for_wpc_bundles' ), 10, 2 );
	}


	/**
	 * Init plugin
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	public function init() {
		try {
			$this->load_gateway();
			$this->includes();

		} catch ( Exception $e ) {
			$message = '[Error] There was an error on booting plugin process: ' . $e->getMessage();
			YITH_PayPal_Logger::log( $message );
		}
	}


	/**
	 * Include plugin required class and file
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	protected function includes() {
		include_once YITH_PAYPAL_PAYMENTS_PATH . 'includes/class-yith-paypal-scripts.php';
		include_once YITH_PAYPAL_PAYMENTS_PATH . 'includes/class-yith-paypal-ajax.php';

		if ( $this->is_admin() ) {
			// include admin classes.
			include_once YITH_PAYPAL_PAYMENTS_PATH . 'includes/class-yith-paypal-admin.php';
		} else {
			include_once YITH_PAYPAL_PAYMENTS_PATH . 'includes/class-yith-paypal-frontend.php';
		}

		YITH_PayPal_Webhook::get_webhook();
	}
	/**
	 * Check if is admin or not and load the correct class
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @return boolean
	 */
	public function is_admin() {
		$check_ajax    = defined( 'DOING_AJAX' ) && DOING_AJAX;
		$check_context = isset( $_REQUEST['context'] ) && 'frontend' === sanitize_text_field( wp_unslash( $_REQUEST['context'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return is_admin() && ! ( $check_ajax && $check_context );
	}

	/**
	 * Register the gateway to WooCommerce gateways list
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @param array $gateways An array of gateways.
	 * @return array
	 */
	public function register_gateway( $gateways ) {
		$gateways[] = 'YITH_PayPal_Gateway';
		if ( 'yes' === get_option( 'yith_ppwc_gateway_enabled_to_manage_custom_card', 'no' ) ) {
			$gateways[] = 'YITH_PayPal_Custom_Card_Gateway';
		}

		return $gateways;
	}


	/**
	 * Load the gateway instance and set the class variable
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @return void
	 * @throws Exception Throws Exception.
	 */
	protected function load_gateway() {
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( empty( $gateways[ self::GATEWAY_ID ] ) ) {
			throw new Exception( 'PayPal gateway not found.' );
		}

		$this->gateway = $gateways[ self::GATEWAY_ID ];
	}

	/**
	 * Get the gateway instance
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @return YITH_PayPal_Gateway|null
	 */
	public function get_gateway() {
		return $this->gateway;
	}

	/**
	 * Load Plugin Framework
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @return void
	 */
	public function plugin_fw_loader() {
		if ( ! defined( 'YIT_CORE_PLUGIN' ) ) {
			global $plugin_fw_data;
			if ( ! empty( $plugin_fw_data ) ) {
				$plugin_fw_file = array_shift( $plugin_fw_data );
				include_once $plugin_fw_file;
			}
		}
	}

	/**
	 * Skip include order/cart details when WPC Product Bundles for WooCommerce is active.
	 * This plugin wrong set item meta value to negative value.
	 *
	 * @since 1.2.4
	 * @author Francesco Licandro
	 * @param boolean $include True to include details, false otherwise.
	 * @param string  $section Optional. Current request section (cart|order).
	 * @return boolean
	 */
	public function skip_details_for_wpc_bundles( $include, $section = 'cart' ) {
		return $include && ! defined( 'WOOSB_FILE' );
	}
}
