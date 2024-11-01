<?php
/**
 * Scripts class. This class handle all scripts and style resource
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Scripts
 */
class YITH_PayPal_Scripts {

	/**
	 * Constructor for the gateway.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	public function __construct() {

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts_style' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_style' ), 10 );

		add_filter( 'script_loader_src', array( $this, 'sdk_remove_ver_css_js' ), 9999, 2 );
		add_filter( 'script_loader_tag', array( $this, 'add_attribute_to_script_tag' ), 10, 2 );
	}

	/**
	 * Get an assets url
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $asset Asset url.
	 * @return string
	 */
	protected function asset_url( $asset ) {
		$debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		$asset = $debug ? str_replace( '.min.js', '.js', $asset ) : $asset;

		return YITH_PAYPAL_PAYMENTS_URL . $asset;
	}

	/**
	 * Load styles
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return array
	 */
	protected function get_styles() {
		return array(
			'yith-ppwc-backend'  => array(
				'src'   => $this->asset_url( 'assets/css/backend.css' ),
				'scope' => 'admin',
			),
			'yith-ppwc-frontend' => array(
				'src'          => $this->asset_url( 'assets/css/frontend.css' ),
				'inline_style' => true,
				'scope'        => 'frontend',
			),
		);
	}

	/**
	 * Load scripts
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return array
	 */
	protected function get_scripts() {

		$gateway = YITH_PayPal::get_instance()->get_gateway();

		return array(
			'yith-ppwc-backend'         => array(
				'src'           => $this->asset_url( 'assets/js/backend.min.js' ),
				'scope'         => 'admin',
				'deps'          => array( 'jquery', 'jquery-ui-dialog' ),
				'localized_var' => 'yith_ppwc_admin',
			),
			'yith-ppwc-login'           => array(
				'src'   => $gateway->get_login_asset_url(),
				'deps'  => false,
				'scope' => 'admin',
			),
			'yith-ppwc-login-handler'   => array(
				'src'   => $this->asset_url( 'assets/js/login.min.js' ),
				'deps'  => array( 'jquery', 'yith-ppwc-login' ),
				'scope' => 'admin',
			),
			'yith-ppwc-partial-payment' => array(
				'src'           => $this->asset_url( 'assets/js/partial-payment.min.js' ),
				'scope'         => 'admin-order',
				'deps'          => array( 'jquery' ),
				'localized_var' => 'yith_ppwc_partial_payment',
			),
			'yith-ppwc-sdk'             => array(
				'src'       => $gateway->get_sdk_url(),
				'deps'      => false,
				'scope'     => 'frontend',
				'in_footer' => false,
			),
			'yith-ppwc-frontend'        => array(
				'src'           => $this->asset_url( 'assets/js/frontend.min.js' ),
				'deps'          => array( 'jquery', 'yith-ppwc-sdk' ),
				'scope'         => 'frontend',
				'localized_var' => 'yith_ppwc_frontend',
			),
		);
	}

	/**
	 * Get localized data for script
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $handle The script handle.
	 * @return array
	 */
	protected function get_localized_script_data( $handle ) {

		$data    = array();
		$default = array(
			'ajaxAction' => YITH_PayPal_Ajax::AJAX_ACTION,
			'ajaxNonce'  => wp_create_nonce( YITH_PayPal_Ajax::AJAX_ACTION ),
			'ajaxLoader' => YITH_PAYPAL_PAYMENTS_URL . 'assets/images/ajax-loader.gif',
		);

		switch ( $handle ) {
			case 'yith-ppwc-frontend':
				$enabled_funding_sources = get_option( 'yith_ppwc_button_funding_sources', array() );

				ob_start();
				wc_print_notice( __( 'An error occurred processing the request!', 'yith-paypal-payments-for-woocommerce' ), 'error' );
				$error_message = ob_get_clean();

				$data = array(
					'ajaxUrl'             => WC_AJAX::get_endpoint( YITH_PayPal_Ajax::AJAX_ACTION ),
					'ajaxNonce'           => wp_create_nonce( YITH_PayPal_Ajax::AJAX_ACTION ),
					'customCardPaymentID' => YITH_Paypal::GATEWAY_ID . '_custom_card',
					'buttonShape'         => get_option( 'yith_ppwc_button_shape', 'rect' ),
					'buttonColor'         => get_option( 'yith_ppwc_button_color', 'gold' ),
					'layout'              => ( empty( $enabled_funding_sources ) || is_product() ) ? 'horizontal' : 'vertical',
					'secure_3d_unknown'   => esc_html_x( 'An error occurred during the payment authorization. Please, try again.', 'Error message on checkout page during the 3D Secure', 'yith-paypal-payments-for-woocommerce' ),
					'secure_3d_no'        => esc_html_x( 'There were some issues with the payment authorization. It is not possible to complete this transaction.', 'Error message on checkout page during the 3D Secure', 'yith-paypal-payments-for-woocommerce' ),
					'errorMessage'        => $error_message,
				);
				break;
			case 'yith-ppwc-partial-payment':
				$data = array(
					'do_payment_confirm' => __( 'Are you sure you wish to proceed with this partial payment? This action cannot be undone.', 'yith-paypal-payments-for-woocommerce' ),
					'void_confirm'       => __( 'Are you sure you wish to proceed to void this payment authorization? You will not be able to ask payments anymore.', 'yith-paypal-payments-for-woocommerce' ),
				);
				break;
			case 'yith-ppwc-backend':
				$data = array(
					'continue' => esc_html_x( 'Continue', 'Label button of a dialog popup', 'yith-paypal-payments-for-woocommerce' ),
					'cancel'   => esc_html_x( 'Cancel', 'Label button of a dialog popup', 'yith-paypal-payments-for-woocommerce' ),
				);
				break;
			default:
				break;
		}

		return array_merge( $default, $data );
	}


	/**
	 * Get inline style for the styles
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $handle The style handle.
	 * @return string
	 */
	protected function get_inline_style( $handle ) {

		$style = '';

		switch ( $handle ) {
			case 'yith-ppwc-frontend':
				$dimensions = yith_plugin_fw_get_dimensions_by_option( 'yith_ppwc_button_size', true );
				$style      = '.yith-ppwc-button{ width:' . $dimensions['width'] . ';}';
				break;
			default:
				break;
		}

		return $style;
	}

	/**
	 * Enqueue plugin styles
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $scope Where embed the file.
	 * @return void
	 */
	protected function enqueue_styles( $scope = 'frontend' ) {

		$default = array(
			'deps'         => array(),
			'version'      => YITH_PAYPAL_PAYMENTS_VERSION,
			'inline_style' => false,
			'media'        => 'all',
		);

		foreach ( $this->get_styles() as $handle => $data ) {

			if ( isset( $data['scope'] ) && $scope !== $data['scope'] ) {
				continue;
			}

			// merge data with default.
			$data = array_merge( $default, $data );

			if ( wp_register_style( $handle, $data['src'], $data['deps'], $data['version'], $data['media'] ) ) {
				// Enqueue registered style.
				wp_enqueue_style( $handle );

				$data['inline_style'] && wp_add_inline_style( $handle, $this->get_inline_style( $handle ) );
			}
		}
	}

	/**
	 * Enqueue plugin scripts
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $scope Where embed the script.
	 * @return void
	 */
	protected function enqueue_scripts( $scope = 'frontend' ) {

		$default = array(
			'deps'          => array( 'jquery' ),
			'version'       => YITH_PAYPAL_PAYMENTS_VERSION,
			'in_footer'     => true,
			'localized_var' => '',
		);

		foreach ( $this->get_scripts() as $handle => $data ) {
			if ( isset( $data['scope'] ) && $scope !== $data['scope'] ) {
				continue;
			}

			// Merge data with default.
			$data = array_merge( $default, $data );

			if ( wp_register_script( $handle, $data['src'], $data['deps'], $data['version'], $data['in_footer'] ) ) {
				// Enqueue registered script.
				wp_enqueue_script( $handle );
				// Maybe localize data.
				if ( $data['localized_var'] ) {
					$localized = $this->get_localized_script_data( $handle );
					wp_localize_script( $handle, $data['localized_var'], $localized );
				}
			}
		}
	}

	/**
	 * Admin enqueue scripts and styles
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return void
	 */
	public function admin_enqueue_scripts_style() {

		global $current_screen;

		if ( isset( $_GET['page'] ) && YITH_PayPal_Admin::PANEL_PAGE === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			$this->enqueue_styles( 'admin' );
			$this->enqueue_scripts( 'admin' );
		}

		if ( ! is_null( $current_screen ) && 'post' === $current_screen->base && 'shop_order' === $current_screen->post_type ) {
			$this->enqueue_scripts( 'admin-order' );
			$this->enqueue_styles( 'admin' );
		}
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @return void
	 */
	public function enqueue_scripts_style() {
		if ( YITH_PayPal_Frontend::is_button_visible() || apply_filters( 'yith_ppwc_load_frontend_scripts', false ) ) {
			$this->enqueue_styles();
			$this->enqueue_scripts();
		}
	}

	/**
	 * Remove version from script url
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $src Script URL.
	 * @param string $handle The script handle.
	 * @return bool|string
	 */
	public function sdk_remove_ver_css_js( $src, $handle ) {
		if ( in_array( $handle, array( 'yith-ppwc-sdk', 'yith-ppwc-login' ) ) && strpos( $src, 'ver=' ) ) { //phpcs:ignore
			$src = remove_query_arg( 'ver', $src );
		}

		return $src;
	}

	/**
	 * Add attribute to script tags.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $tag Script URL.
	 * @param string $handle The script handle.
	 * @return string
	 */
	public function add_attribute_to_script_tag( $tag, $handle ) {

		if ( 'yith-ppwc-sdk' !== $handle || ! yith_ppwc_is_custom_credit_card_enabled() ) {
			return $tag;
		}

		try {
			$login                = YITH_PayPal_Controller::load( 'login' );
			$client_token_request = $login->get_client_token();
			$tag                  = str_replace( '<script', '<script data-client-token="' . $client_token_request['client_token'] . '"', $tag );

		} catch ( Exception $e ) {
			YITH_PayPal_Logger::log( 'Error triggered during the request of client token for custom credit card. ' . $e->getMessage() );
		}

		return $tag;
	}
}

new YITH_PayPal_Scripts();
