<?php
/**
 * Frontend class
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Frontend
 */
class YITH_PayPal_Frontend {

	/**
	 * Is button visible?
	 *
	 * @var boolean
	 */
	public $button_visible = false;

	/**
	 * What is the button label?
	 *
	 * @var boolean
	 */
	public static $button_label = 'pay';

	/**
	 * The gateway
	 *
	 * @var YITH_PayPal_Gateway
	 */
	protected $gateway = false;


	/**
	 * Check if the checkout is the confirmed page
	 *
	 * @var bool
	 */
	protected $confirmed_page = false;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ), 15 );

		add_action( 'woocommerce_thankyou_' . YITH_Paypal::GATEWAY_ID, array( $this, 'add_custom_thank_you_message' ), 10, 1 );
		add_action( 'woocommerce_thankyou_' . YITH_Paypal::GATEWAY_ID . '_custom_card', array( $this, 'add_custom_thank_you_message' ), 10, 1 );
	}

	/**
	 * Add a custom message inside the thankyou page
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param integer $order_id The order ID.
	 * @return void
	 */
	public function add_custom_thank_you_message( $order_id ) {
		$order          = wc_get_order( $order_id );
		$is_autorized   = $order->get_meta( '_yith_ppwc_paypal_authorize_info' );
		$payment_method = $order->get_payment_method_title();
		if ( ! empty( $is_autorized ) ) {
			/* translators: %s stands for the gateway title. */
			$message = sprintf( esc_html_x( 'Your payment has been authorized with %s.', 'Message in thank you page placeholder is the title of the gateway', 'yith-paypal-payments-for-woocommerce' ), $payment_method );
		} else {
			/* translators: %s stands for the gateway title. */
			$message = sprintf( esc_html_x( 'Your payment has been captured with %s.', 'Message in thank you page placeholder is the title of the gateway', 'yith-paypal-payments-for-woocommerce' ), $payment_method );
		}

		echo apply_filters( 'yith_ppwc_thankyou_page_message', sprintf( '<div class="yith-ppwc-ty-message">%s</div>', $message ), $order_id ); //phpcs:ignore
	}

	/**
	 * Init function
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function init() {
		// Check if current gateway is available.
		$this->gateway = YITH_PayPal::get_instance()->get_gateway();

		if ( $this->gateway->is_available() ) {

			add_action( 'template_redirect', array( $this, 'init_paypal_button' ), 0 );
			add_action( 'woocommerce_checkout_update_order_review', array( $this, 'init_paypal_button' ), 0 );

			add_action( 'wp_loaded', array( $this, 'init_confirm_page' ), 1 );

			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'hide_payment_gateway' ), 10, 1 );
			add_action( 'woocommerce_cart_emptied', array( $this, 'empty_paypal_session' ), 10 );
			add_action( 'woocommerce_cart_item_removed', array( $this, 'empty_paypal_session' ), 10 );

			add_action( 'wp_loaded', array( $this, 'maybe_handle_single_add_to_cart' ), 5 );

		}
	}

	/**
	 * Conditionally show PayPal button
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return void
	 */
	public function init_paypal_button() {

		if ( ! $this->is_button_visible() ) {
			return;
		}

		if ( is_product() ) {

			global $post;

			if ( ! empty( $post ) ) {
				$product = wc_get_product( $post->ID );
			}

			// Show button for simple and grouped products. Show it also for variable product that has available variations.
			if ( ! empty( $product ) && ( $product->is_purchasable() && ! $product->is_type( 'external' ) && ( ! $product->is_type( 'variable' ) || ! empty( $product->get_available_variations() ) ) ) ) {
				self::$button_label = 'checkout';
				add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'show_button' ) );
			}
		} elseif ( is_cart() ) {
			self::$button_label = 'checkout';
			add_action( 'woocommerce_proceed_to_checkout', array( __CLASS__, 'show_button' ) );
		} elseif ( is_checkout() ) {
			self::$button_label = 'checkout';
			add_action( 'woocommerce_review_order_after_submit', array( __CLASS__, 'show_button_checkout' ) );
			add_action( 'woocommerce_pay_order_after_submit', array( __CLASS__, 'show_button_checkout' ) );
		}
	}

	/**
	 * Add the hook on checkout page to transform the page in confirm page.
	 *
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function init_confirm_page() {
		if ( isset( WC()->session ) && WC()->session->get( 'checkout_as_confirm_page', false ) ) {
			$this->confirmed_page = true;
			add_filter( 'woocommerce_checkout_get_value', array( $this, 'override_checkout_field' ), 10, 2 );
			add_filter( 'woocommerce_ship_to_different_address_checked', array( $this, 'enable_ship_to_different_address' ), 10, 2 );
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'manage_gateways_on_checkout_page' ), 100, 1 );
			add_filter( 'woocommerce_order_button_text', array( $this, 'set_order_button_text' ), 100, 1 );
		}
	}

	/**
	 * Hide payment gateway from the list of available gateways if visibility is disabled
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param array $gateways The array of available gateways.
	 * @return array
	 */
	public function hide_payment_gateway( $gateways ) {

		if ( ! self::is_button_visible() ) {
			unset( $gateways[ $this->gateway->id ] );
		}

		return $gateways;
	}

	/**
	 * Hide payment gateways from the list inside the confirmation page
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param array $gateways The array of available gateways.
	 * @return array
	 */
	public function manage_gateways_on_checkout_page( $gateways ) {

		if ( isset( WC()->session, $gateways[ $this->gateway->id ] ) ) {

			if ( WC()->session->get( 'paypal_order_id', false ) ) {
				$id = $this->gateway->id;

				$gateways = array( $id => $gateways[ $id ] );
			}
		}

		if ( ! self::is_button_visible() ) {
			unset( $gateways[ $this->gateway->id ] );
		}

		return $gateways;
	}

	/**
	 * Show button
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public static function show_button() {
		// Capture location and add as button data.
		$location = is_checkout() ? 'checkout' : ( is_product() ? 'product' : '' );
		$order    = '';

		if ( isset( $_REQUEST['key'], $_REQUEST['pay_for_order'] ) ) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$location = 'pay_order';
			$key      = sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			$order    = wc_get_order_id_by_order_key( $key );
		}
		printf( '<div class="yith-ppwc-button" data-label="%s" data-location="%s" data-order="%s"></div>', esc_html( self::$button_label ), esc_attr( $location ), esc_attr( $order ) );
	}

	/**
	 * Show button at checkout
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public static function show_button_checkout() {

		$paypal_order_id          = isset( WC()->session ) ? WC()->session->get( 'paypal_order_id' ) : '';
		$checkout_as_confirm_page = isset( WC()->session ) ? WC()->session->get( 'checkout_as_confirm_page', false ) : false;

		if ( isset( $_GET['pay_for_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$paypal_order_id          = '';
			$checkout_as_confirm_page = false;
		}

		if ( ! $checkout_as_confirm_page || empty( $paypal_order_id ) ) {
			YITH_PayPal_Frontend::show_button();
		} else {
			$fields = array(
				'paypal-order-id' => '<input id="' . esc_attr( YITH_Paypal::GATEWAY_ID ) . '-order-id" class="input-text" type="hidden" value="' . $paypal_order_id . '"/>',
			);

			if ( $checkout_as_confirm_page ) {
				$fields['checkout_as_confirm_page'] = '<input id="checkout_as_confirm_page" class="input-text" type="hidden" value="' . $checkout_as_confirm_page . '"/>';
			}
			?>

			<fieldset id="wc-<?php echo esc_attr( YITH_Paypal::GATEWAY_ID ); ?>-form">
				<?php
				foreach ( $fields as $field ) {
					echo $field; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
				}
				?>
				<div class="clear"></div>
			</fieldset>
			<?php
		}
	}

	/**
	 * Is PayPal button visible
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return boolean
	 */
	public static function is_button_visible() {

		// Read button visibility options.
		$button_on = get_option( 'yith_ppwc_button_on', array( 'cart', 'checkout' ) );

		$is_visible = false;

		if ( is_product() && in_array( 'product', $button_on, true ) ) {
			$is_visible = true;
		} elseif ( is_cart() && in_array( 'cart', $button_on, true ) ) {
			$is_visible = true;
		} elseif ( is_checkout() && in_array( 'checkout', $button_on, true ) ) {
			$is_visible = true;
		}

		return $is_visible;
	}

	/**
	 * Override checkout fields.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param mixed  $value Checkout field value.
	 * @param string $field Checkout field name.
	 * @return mixed
	 */
	public function override_checkout_field( $value, $field ) {
		if ( $this->confirmed_page ) {
			$paypal_shipping = WC()->session->get( 'paypal_shipping_address', false );
			$paypal_billing  = WC()->session->get( 'paypal_billing_address', false );

			$field_raw = str_replace( 'billing_', '', $field );
			if ( empty( $value ) && isset( $paypal_billing[ $field_raw ] ) ) {
				return $paypal_billing[ $field_raw ];
			}

			$field_raw = str_replace( 'shipping_', '', $field );

			if ( isset( $paypal_shipping[ $field_raw ] ) ) {
				return $paypal_shipping[ $field_raw ];
			}
		}

		return $value;
	}

	/**
	 * Enable the shipping on checkout if PayPal shipping address is set.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param bool $value Check if the shipping address is enabled.
	 * @return bool
	 */
	public function enable_ship_to_different_address( $value ) {
		if ( $this->confirmed_page ) {
			$paypal_shipping = WC()->session->get( 'paypal_shipping_address', false );
			$value           = ! ! $paypal_shipping;
		}

		return $value;
	}

	/**
	 * Override the place holder label button
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $label Place order label.
	 * @return string
	 */
	public function set_order_button_text( $label ) {
		$label = esc_html_x( 'Proceed to PayPal', 'Checkout button label', 'yith-paypal-payments-for-woocommerce' );

		if ( isset( WC()->session ) && WC()->session->get( 'paypal_order_id', false ) ) {
			$label = esc_html_x( 'Continue', 'Checkout button label', 'yith-paypal-payments-for-woocommerce' );
		}

		return $label;
	}


	/**
	 * Remove the PayPal settings from the session.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	public function empty_paypal_session() {
		if ( isset( WC()->cart ) && WC()->cart->is_empty() ) {
			unset( WC()->session->paypal_order_id );
			unset( WC()->session->paypal_shipping_address );
			unset( WC()->session->paypal_billing_address );
			unset( WC()->session->checkout_as_confirm_page );
		}
	}

	/**
	 * Maybe handle single add to cart on product page
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @return void
	 */
	public function maybe_handle_single_add_to_cart() {

		if ( empty( $_REQUEST['is-yith-ppwc-action'] ) || 'yes' !== $_REQUEST['is-yith-ppwc-action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			return;
		}

		// Save old cart on session if any.
		if ( ! WC()->cart->is_empty() ) {
			$cart = WC()->session->get( 'cart' );
			WC()->session->set( 'old_cart', $cart );

			// empty cart.
			WC()->cart->empty_cart( true );
		}

		// Avoid add to cart process redirect.
		add_filter( 'woocommerce_add_to_cart_redirect', '__return_false', 999 );
		add_filter( 'pre_option_woocommerce_cart_redirect_after_add', array( $this, 'prevent_cart_redirect' ), 99, 2 );
	}

	/**
	 * Prevent add to cart redirect option
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param string $value Value.
	 * @param string $option Option.
	 * @return string
	 */
	public function prevent_cart_redirect( $value, $option ) {
		return 'no';
	}
}

new YITH_PayPal_Frontend();
