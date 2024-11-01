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
 * Class YITH_PayPal_Autoloader
 */
class YITH_PayPal_Autoloader {

	/**
	 * Path to the includes directory.
	 *
	 * @var string
	 */
	private $include_path = '';

	/**
	 * The Constructor.
	 */
	public function __construct() {
		if ( function_exists( '__autoload' ) ) {
			spl_autoload_register( '__autoload' );
		}

		spl_autoload_register( array( $this, 'autoload' ) );

		$this->include_path = untrailingslashit( YITH_PAYPAL_PAYMENTS_PATH ) . '/includes/';
	}

	/**
	 * Take a class name and turn it into a file name.
	 *
	 * @param string $class Class name.
	 *
	 * @return string
	 */
	private function get_file_name_from_class( $class ) {
		return 'class-' . str_replace( '_', '-', $class ) . '.php';
	}

	/**
	 * Include a class file.
	 *
	 * @param string $file File name.
	 * @param string $path File path.
	 *
	 * @return bool Successful or not.
	 */
	private function load_file( $file, $path = '' ) {

		// build file path/name.
		$file = $path ? $path . $file : $this->include_path . $file;

		if ( is_readable( $file ) ) {
			include_once $file;

			return true;
		}

		return false;
	}

	/**
	 * Auto-load WC classes on demand to reduce memory consumption.
	 *
	 * @param string $class Class name.
	 */
	public function autoload( $class ) {
		$class = strtolower( $class );

		if ( 0 !== strpos( $class, 'yith_paypal' ) ) {
			return;
		}

		// check for a different path.
		$path = $this->include_path;
		if ( 0 === strpos( $class, 'yith_paypal_request' ) ) {
			$path .= 'requests/';
		} elseif ( false !== strpos( $class, 'order_partial' ) ) {
			$path .= 'order/';
		} elseif ( false !== strpos( $class, 'gateway' ) ) {
			$path .= 'gateways/';
		}

		$file = $this->get_file_name_from_class( $class );
		$this->load_file( $file, $path );
	}
}

new YITH_PayPal_Autoloader();
