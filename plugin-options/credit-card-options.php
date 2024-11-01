<?php
/**
 * The plugin general options array
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

return array(
	'credit-card' => array(
		array(
			'title' => esc_html_x( 'Custom Credit Card Options', 'Admin option section title', 'yith-paypal-payments-for-woocommerce' ),
			'type'  => 'title',
			'desc'  => '',
			'id'    => 'yith_ppwc_credit_card_options',
		),

		array(
			'id'        => 'yith_ppwc_cc_gateway_options[enabled]',
			'title'     => esc_html_x( 'Enable YITH PayPal Payments', 'Admin title option', 'yith-paypal-payments-for-woocommerce' ),
			'desc'      => esc_html_x( 'Choose to enable the custom "white label" credit card option in your checkout as payment options.', 'Admin description option', 'yith-paypal-payments-for-woocommerce' ),
			'type'      => 'yith-field',
			'yith-type' => 'onoff',
			'default'   => 'yes',
		),

		array(
			'id'        => 'yith_ppwc_cc_gateway_options[title]',
			'title'     => esc_html_x( 'Title', 'Admin option title of payment method', 'yith-paypal-payments-for-woocommerce' ),
			'desc'      => esc_html_x( 'Enter a title to identify this payment method during checkout.', 'Admin option description', 'yith-paypal-payments-for-woocommerce' ),
			'type'      => 'yith-field',
			'yith-type' => 'text',
			'default'   => esc_html_x( 'Pay with Credit Card or Debit Card', 'Default value of a the payment method title', 'yith-paypal-payments-for-woocommerce' ),
		),

		array(
			'id'        => 'yith_ppwc_cc_gateway_options[description]',
			'title'     => esc_html_x( 'Description', 'Admin title option of payment method description', 'yith-paypal-payments-for-woocommerce' ),
			'desc'      => esc_html_x( 'Enter an optional description for this payment method.', 'Admin description option of payment method description.', 'yith-paypal-payments-for-woocommerce' ),
			'type'      => 'yith-field',
			'yith-type' => 'text',
			'default'   => esc_html_x( 'Pay safe with Credit Card', 'Default value of a the payment method description', 'yith-paypal-payments-for-woocommerce' ),
		),

		array(
			'id'        => 'yith_ppwc_cc_gateway_options[3d_secure_setting]',
			'title'     => esc_html_x( 'Enable 3D Secure', 'Admin title option', 'yith-paypal-payments-for-woocommerce' ),
			'desc'      => esc_html_x( '3D Secure enables you to authenticate card holders through card issuers. It reduces the likelihood of fraud when you use supported cards and improves transaction performance. A successful 3D Secure authentication can shift liability for chargebacks due to fraud from you to the card issuer.', 'Admin description option', 'yith-paypal-payments-for-woocommerce' ),
			'type'      => 'yith-field',
			'yith-type' => 'onoff',
			'default'   => 'yes',
		),

		array(
			'id'        => 'yith_ppwc_cc_gateway_options[3d_secure_liability_shift]',
			'title'     => esc_html_x( '3D Secure Liability Shift', 'Admin title option', 'yith-paypal-payments-for-woocommerce' ),
			'desc'      => esc_html_x( '3D Secure authentication is performed only if the card is enrolled for the service. In scenarios where the 3D Secure authentication hasn\'t been successful, you have the option to complete the payment at your own risk, meaning that you, as the merchant, will be liable in case of a chargeback.', 'Admin description option', 'yith-paypal-payments-for-woocommerce' ),
			'type'      => 'yith-field',
			'yith-type' => 'checkbox-array',
			'default'   => array( 'possible', 'unknown' ),
			'options'   => array(
				'possible' => sprintf( esc_html_x( 'Liability might shift to the card issuer.', 'Admin option', 'yith-paypal-payments-for-woocommerce' ) ),
				'no'       => sprintf( esc_html_x( 'Liability is with the merchant.', 'Admin option', 'yith-paypal-payments-for-woocommerce' ) ),
				'unknown'  => sprintf( esc_html_x( 'The authentication system is not available.', 'Admin option', 'yith-paypal-payments-for-woocommerce' ) ),
			),
			'deps'      => array(
				'target-id' => 'yith_ppwc_cc_gateway_options\\[3d_secure_liability_shift\\]',
				'id'        => 'yith_ppwc_cc_gateway_options\\[3d_secure_setting\\]',
				'value'     => 'yes',
			),
		),

		array(
			'type' => 'sectionend',
			'id'   => 'yith_ppwc_end_credit_card',
		),
	),
);
