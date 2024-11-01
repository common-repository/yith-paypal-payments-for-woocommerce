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
 * Class YITH_PayPal_Custom_Card_Gateway
 */
class YITH_PayPal_Custom_Card_Gateway extends YITH_PayPal_Gateway {

	/**
	 * List of credit cards
	 *
	 * @var array List cards
	 */
	public $cards = array(
		'visa'       => 'Visa',
		'mastercard' => 'MasterCard',
		'discover'   => 'Discover',
		'amex'       => 'American Express',
		'diners'     => 'Diners Club',
		'jcb'        => 'JCB',
		'maestro'    => 'Maestro',
	);

	/**
	 * Constructor for the gateway.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function __construct() {

		parent::__construct();

		// Get settings.
		$this->title       = $this->get_option( 'title', __( 'Credit Card', 'yith-paypal-payments-for-woocommerce' ) );
		$this->description = $this->get_option( 'description', __( 'Pay with your Credit Card', 'yith-paypal-payments-for-woocommerce' ) );
		$this->setup_properties();

		if ( 'production' !== $this->environment ) {
			$this->description .= '<br>' . __(
				'SANDBOX ENABLED. Pay securely using your credit card.
Test credit card numbers: 4111111111111111. Complete Expiration and CVV fields as needed.',
				'yith-paypal-payments-for-woocommerce'
			);
			$this->description  = trim( $this->description );
		}

	}

	/**
	 * Initialise Settings.
	 * Store all settings in a single database entry
	 * and make sure the $settings array is either the default
	 * or the settings stored in the database.
	 *
	 * @since 1.0.0
	 * @uses get_option(), add_option()
	 */
	public function init_settings() {

		// merge with parent settings.
		$parent_settings = get_option( parent::get_option_key(), array() );
		$settings        = get_option( $this->get_option_key(), array() );

		$this->settings = array_merge( $parent_settings, $settings );

		// If there are no settings defined, use defaults.
		if ( empty( $this->settings ) ) {
			$form_fields    = $this->get_form_fields();
			$this->settings = array_merge( array_fill_keys( array_keys( $form_fields ), '' ), wp_list_pluck( $form_fields, 'default' ) );
		}
	}


	/**
	 * Setup general properties for the gateway.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	protected function setup_properties() {
		$this->id                 = YITH_Paypal::GATEWAY_ID . '_' . 'custom_card'; //phpcs:ignore
		$this->icon               = '';
		$this->method_title       = __( 'PayPal Payments Custom Card', 'yith-paypal-payments-for-woocommerce' );
		$this->method_description = __( 'PayPal Custom Credit Card Payments Gateway.', 'yith-paypal-payments-for-woocommerce' );
		$this->has_fields         = true;
		$this->enabled            = ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
	}

	/**
	 * Get option key for this gateway.
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function get_option_key() {
		return 'yith_ppwc_cc_gateway_options';
	}


	/**
	 * Pay the order.
	 *
	 * @param int $order_id Order id.
	 *
	 * @return array
	 * @throws Exception Throw Exception.
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function process_payment( $order_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['yith_paypal_cc_payments-order-id'] ) && isset( WC()->session ) ) {
			WC()->session->set( 'paypal_order_id', sanitize_text_field( wp_unslash( $_REQUEST['yith_paypal_cc_payments-order-id'] ) ) );
			return parent::process_payment( $order_id );
		} else {
			throw new Exception( 'Error: An error occurred during the payment.' );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Add the button on checkout page
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	public function form() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$order = '';
		if ( isset( $_REQUEST['key'], $_REQUEST['pay_for_order'] ) ) {
			$location = 'pay_order';
			$key      = sanitize_text_field( wp_unslash( $_REQUEST['key'] ) );
			$order    = wc_get_order_id_by_order_key( $key );
		}
		?>
		<div class='card_container'>
			<div id='yith-ppwc-cc-form'>
				<input id="yith_paypal_cc_payments-order-id" name="yith_paypal_cc_payments-order-id" class="input-text"
					type="hidden" value="" data-order-id="<?php echo esc_attr( $order ); ?>"/>
				<div class="yith-ppwc-cardinfo-card-number">
					<label for='card-number'><?php esc_html_e( 'Card Number', 'yith-paypal-payments-for-woocommerce' ); ?></label>
					<div id='yith-ppwc-cc-card-number' class='card_field'></div>
					<div id="card-image"></div>
				</div>

				<div>
					<label for='expiration-date'><?php esc_html_e( 'Expiration Date', 'yith-paypal-payments-for-woocommerce' ); ?></label>
					<div id='yith-ppwc-cc-expiration-date' class='card_field'></div>
				</div>
				<div>
					<label for='cvv'><?php esc_html_e( 'CVV', 'yith-paypal-payments-for-woocommerce' ); ?></label>
					<div id='yith-ppwc-cc-cvv' class='card_field'></div>
				</div>

				<div class="error"></div>
			</div>
			<?php
			if ( 'yes' === $this->get_option( '3d_secure_setting', 'no' ) ) :
				$liability = (array) $this->get_option( '3d_secure_liability_shift', array( 'possible', 'unknown' ) );
				?>
				<div id="payments-sdk__contingency-lightbox"
					data-liability="<?php echo esc_attr( implode( ',', $liability ) ); ?>"></div>
			<?php endif; ?>
		</div>
		<?php
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Get_icon function.
	 *
	 * @access public
	 * @return string
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	public function get_icon() {
		switch ( WC()->countries->get_base_country() ) {

			case 'US':
				$allowed = apply_filters( 'yith_ppwc_us_icons', array( 'visa', 'mastercard', 'amex', 'discover', 'diners', 'jcb' ) );
				break;

			default:
				$allowed = apply_filters( 'yith_ppwc_default_icons', array( 'visa', 'mastercard', 'amex' ) );
				break;
		}

		$icon = '';
		foreach ( $allowed as $name ) {
			$icon .= apply_filters( 'yith_ppwc_icon', '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/' . $name . '.png' ) . '" alt="' . $this->cards[ $name ] . '" style="width:40px;" />', $name, $this->cards[ $name ], $allowed );
		}

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

}
