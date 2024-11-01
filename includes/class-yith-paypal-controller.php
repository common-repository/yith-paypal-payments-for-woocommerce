<?php
/**
 * Controller class
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Controller
 */
class YITH_PayPal_Controller {

	/**
	 * Contains an instance of the request class that we are working with.
	 *
	 * @var YITH_PayPal_Controller
	 */
	private $instance = null;

	/**
	 * The object type this store works with.
	 *
	 * @var string
	 */
	private $request_type = '';

	/**
	 * Contains an array of request handled by the plugin.
	 *
	 * @var array
	 */
	private $requests = array(
		'login'       => 'YITH_PayPal_Request_Login',
		'merchant'    => 'YITH_PayPal_Request_Merchant',
		'webhook'     => 'YITH_PayPal_Request_Webhook',
		'transaction' => 'YITH_PayPal_Request_Transaction',
	);

	/**
	 * Tells YITH_PayPal_Controller which request we want to work with.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param string $request_type Name of request.
	 * @throws Exception When validation fails.
	 */
	public function __construct( $request_type ) {
		$this->request_type = $request_type;

		if ( array_key_exists( $this->request_type, $this->requests ) ) {
			$request        = $this->requests[ $this->request_type ];
			$this->instance = new $request();
		} else {
			throw new Exception( 'Invalid request.' );
		}
	}

	/**
	 * Call request method
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param string $method Method.
	 * @param mixed  $parameters Parameters.
	 * @return mixed
	 */
	public function __call( $method, $parameters ) {
		if ( is_callable( array( $this->instance, $method ) ) ) {
			$object     = array_shift( $parameters );
			$parameters = array_merge( array( &$object ), $parameters );

			return $this->instance->$method( ...$parameters );
		}
	}

	/**
	 * Loads a request handler.
	 *
	 * @since 3.0.0
	 * @param string $request_type Name of the request.
	 * @return YITH_Paypal_Controller
	 * @throws Exception When validation fails.
	 */
	public static function load( $request_type ) {
		return new YITH_PayPal_Controller( $request_type );
	}
}
