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
	'button' => array(
		array(
			'title' => esc_html_x( 'Button Options', 'Title of setting tab.', 'yith-paypal-payments-for-woocommerce' ),
			'type'  => 'title',
			'desc'  => '',
			'id'    => 'yith_ppwc_button_options',
		),

		array(
			'id'        => 'yith_ppwc_button_on',
			'title'     => esc_html_x( 'Show PayPal button on', 'Admin title option', 'yith-paypal-payments-for-woocommerce' ),
			'desc'      => esc_html_x( 'Choose where to show the PayPal payment button.', 'Admin description option', 'yith-paypal-payments-for-woocommerce' ),
			'type'      => 'yith-field',
			'yith-type' => 'checkbox-array',
			'default'   => array( 'cart', 'checkout' ),
			'options'   => array(
				// translators:placeholders are html tags.
				'cart'     => sprintf( esc_html_x( 'Cart page %1$sShow PayPal button on cart page%2$s', 'Admin option, the placeholder are tags', 'yith-paypal-payments-for-woocommerce' ), '<small>', '</small>' ),
				// translators:placeholders are html tags.
				'checkout' => sprintf( esc_html_x( 'Checkout %1$sShow PayPal button on checkout page%2$s', 'Admin option, the placeholder are tags', 'yith-paypal-payments-for-woocommerce' ), '<small>', '</small>' ),
				// translators:placeholders are html tags.
				'product'  => sprintf( esc_html_x( 'Single product pages %1$sShow PayPal button in all product pages%2$s', 'Admin option, the placeholder are tags', 'yith-paypal-payments-for-woocommerce' ), '<small>', '</small>' ),
			),
		),

		array(
			'id'        => 'yith_ppwc_button_color',
			'title'     => esc_html_x( 'Button color', 'Admin title option', 'yith-paypal-payments-for-woocommerce' ),
			// translators:placeholders are html tags.
			'desc'      => sprintf( esc_html_x( 'Choose the PayPal button color. The recommended color is %1$sgold%2$s.', 'Admin option, the placeholder are tags', 'yith-paypal-payments-for-woocommerce' ), '<strong>', '</strong>' ),
			'type'      => 'yith-field',
			'yith-type' => 'select-images',
			'default'   => 'gold',
			'options'   => array(
				'gold'   => array(
					'label' => esc_html_x( 'Gold', 'Option: Button color', 'yith-paypal-payments-for-woocommerce' ),
					'image' => YITH_PAYPAL_PAYMENTS_URL . 'assets/images/button-gold.png',
				),
				'blue'   => array(
					'label' => esc_html_x( 'Blue', 'Option: Button color', 'yith-paypal-payments-for-woocommerce' ),
					'image' => YITH_PAYPAL_PAYMENTS_URL . 'assets/images/button-blue.png',
				),
				'silver' => array(
					'label' => esc_html_x( 'Silver', 'Option: Button color', 'yith-paypal-payments-for-woocommerce' ),
					'image' => YITH_PAYPAL_PAYMENTS_URL . 'assets/images/button-silver.png',
				),
				'white'  => array(
					'label' => esc_html_x( 'White', 'Option: Button color', 'yith-paypal-payments-for-woocommerce' ),
					'image' => YITH_PAYPAL_PAYMENTS_URL . 'assets/images/button-white.png',
				),
				'black'  => array(
					'label' => esc_html_x( 'Black', 'Option: Button color', 'yith-paypal-payments-for-woocommerce' ),
					'image' => YITH_PAYPAL_PAYMENTS_URL . 'assets/images/button-black.png',
				),
			),
		),

		array(
			'id'        => 'yith_ppwc_button_shape',
			'title'     => esc_html_x( 'Button shape', 'Admin title option', 'yith-paypal-payments-for-woocommerce' ),
			// translators:placeholders are html tags.
			'desc'      => sprintf( esc_html_x( 'Choose the shape style of PayPal button. The recommended shape is %1$srectangular%2$s.', 'Admin option, the placeholder are tags', 'yith-paypal-payments-for-woocommerce' ), '<strong>', '</strong>' ),
			'type'      => 'yith-field',
			'yith-type' => 'radio',
			'default'   => 'rect',
			'options'   => array(
				'rect' => esc_html_x( 'Rectangular', 'Admin option', 'yith-paypal-payments-for-woocommerce' ),
				'pill' => esc_html_x( 'Pill', 'Admin option', 'yith-paypal-payments-for-woocommerce' ),
			),
		),

		array(
			'id'           => 'yith_ppwc_button_size',
			'title'        => esc_html_x( 'Button container width', 'Admin title option', 'yith-paypal-payments-for-woocommerce' ),
			'desc'         => sprintf( esc_html_x( 'Use this value to edit the button size.', 'Admin option', 'yith-paypal-payments-for-woocommerce' ), '<strong>', '</strong>' ),
			'type'         => 'yith-field',
			'yith-type'    => 'dimensions',
			'allow_linked' => false,
			'dimensions'   => array(
				'width' => esc_html_x( 'Width', 'Admin option', 'yith-paypal-payments-for-woocommerce' ),
			),
			'default'      => array(
				'dimensions' => array(
					'width' => 100,
				),
				'unit'       => 'percentage',
			),
		),

		array(
			'type' => 'sectionend',
			'id'   => 'yith_ppwc_end_button_options',
		),

		array(
			'title' => esc_html_x( 'Funding Sources', 'Title of setting tab.', 'yith-paypal-payments-for-woocommerce' ),
			'type'  => 'title',
			'desc'  => sprintf( esc_html_x( 'Please note: the alternative payment methods available to your customers can\'t be controlled from any plugin as this is determined by the PayPal Commerce Platform. For more information and availability, contact %s.', 'placeholder is PayPal support link', 'yith-paypal-payments-for-woocommerce' ), yith_ppwc_get_pp_support_link() ),
			'id'    => 'yith_ppwc_funding_options',
		),

		array(
			'id'        => 'yith_ppwc_button_funding_sources',
			'title'     => esc_html_x( 'Funding sources for the PayPal transactions', 'Admin title option', 'yith-paypal-payments-for-woocommerce' ),
			'desc'      => sprintf( esc_html_x( 'Select the funding sources that will be available in the PayPal wallet by default.%s The funding sources eligible and visible to the user, depends on a variety of factors including location of the customer.', 'Admin option. Placeholder is an html tag.', 'yith-paypal-payments-for-woocommerce' ), '<br>' ),
			'type'      => 'yith-field',
			'yith-type' => 'checkbox-array',
			'default'   => array( 'card', 'venmo', 'credit', 'ideal' ),
			'options'   => yith_ppwc_funding_sources_list(),
		),

		array(
			'id'        => 'yith_ppwc_button_credit_cards',
			'title'     => esc_html_x( 'Visible Card Payment types', 'Admin title option', 'yith-paypal-payments-for-woocommerce' ),
			'desc'      => sprintf(
				esc_html_x(
					'Select the payment types that will displayed in the checkout credit card section.%s
			Card eligibility is determined based on a variety of factors including the location of the customer.',
					'Admin option. Placeholder is an html tag.',
					'yith-paypal-payments-for-woocommerce'
				),
				'<br>'
			),
			'type'      => 'yith-field',
			'yith-type' => 'checkbox-array',
			'default'   => array( 'visa', 'mastercard', 'amex' ),
			'options'   => yith_ppwc_credit_cards_list(),
		),

		array(
			'type' => 'sectionend',
			'id'   => 'yith_ppwc_end_funding_options',
		),
	),
);
