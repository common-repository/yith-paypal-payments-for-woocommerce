<?php
/**
 * Logger class
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Logger
 */
class YITH_PayPal_Logger {

	/**
	 * WC logger instance
	 *
	 * @var WC_Logger
	 */
	public static $logger = null;

	/**
	 * Init the WC Logger
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return void
	 */
	public static function init() {
		if ( is_null( self::$logger ) && class_exists( 'WC_Logger' ) ) {
			self::$logger = new WC_Logger();
		}
	}

	/**
	 * Log a message
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param string $message The message to log.
	 * @param string $type The message type.
	 * @return void
	 */
	public static function log( $message, $type = 'error' ) {
		self::init();

		if ( ! is_null( self::$logger ) ) {
			self::$logger->log(
				$type,
				$message,
				array(
					'source' => 'yith-paypal-log',
				)
			);
		}
	}
}
