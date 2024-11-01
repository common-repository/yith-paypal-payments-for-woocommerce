<?php
/**
 * Class YITH_PayPal_Order_Partial_Data_Store_CPT file.
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Order Refund Data Store: Stored in CPT.
 *
 * @version  3.0.0
 */
class YITH_PayPal_Order_Partial_Data_Store_CPT extends Abstract_WC_Order_Data_Store_CPT implements WC_Object_Data_Store_Interface, WC_Order_Refund_Data_Store_Interface {

	/**
	 * Data stored in meta keys, but not considered "meta" for an order.
	 *
	 * @since 3.0.0
	 * @var array
	 */
	protected $internal_meta_keys = array(
		'_order_currency',
		'_cart_discount',
		'_partial_amount',
		'_cart_discount_tax',
		'_order_shipping',
		'_order_shipping_tax',
		'_order_tax',
		'_order_total',
		'_order_version',
		'_prices_include_tax',
		'_transaction_id',
		'_date_paid',
	);

	/**
	 * Delete a refund - no trash is supported.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $args Array of args to pass to the delete method.
	 */
	public function delete( &$order, $args = array() ) {
		$id = $order->get_id();

		if ( ! $id ) {
			return;
		}

		wp_delete_post( $id );
		$order->set_id( 0 );
		do_action( 'yith_paypal_payments_delete_order_partial', $id );
	}

	/**
	 * Read refund data. Can be overridden by child classes to load other props.
	 *
	 * @param YITH_PayPal_Order_Partial $partial Refund object.
	 * @param object                    $post_object Post object.
	 * @since 3.0.0
	 */
	protected function read_order_data( &$partial, $post_object ) {
		parent::read_order_data( $partial, $post_object );
		$id = $partial->get_id();
		$partial->set_props(
			array(
				'amount'         => get_post_meta( $id, '_partial_amount', true ),
				'date_paid'      => get_post_meta( $id, '_date_paid', true ),
				'transaction_id' => get_post_meta( $id, '_transaction_id', true ),
			)
		);
	}

	/**
	 * Helper method that updates all the post meta for an order based on it's settings in the WC_Order class.
	 *
	 * @param YITH_PayPal_Order_Partial $partial Refund object.
	 * @since 3.0.0
	 */
	protected function update_post_meta( &$partial ) {

		$updated_props     = array();
		$meta_key_to_props = array(
			'_partial_amount' => 'amount',
			'_transaction_id' => 'transaction_id',
			'_date_paid'      => 'date_paid',
		);

		$props_to_update = $this->get_props_to_update( $partial, $meta_key_to_props );
		foreach ( $props_to_update as $meta_key => $prop ) {

			$value = $partial->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;
			switch ( $prop ) {
				case 'date_paid':
				case 'date_completed':
					$value = ! is_null( $value ) ? $value->getTimestamp() : '';
					break;
			}

			$updated = $this->update_or_delete_post_meta( $partial, $meta_key, $value );

			if ( $updated ) {
				$updated_props[] = $prop;
			}
		}

		parent::update_post_meta( $partial );

		do_action( 'yith_paypal_payments_order_partial_object_updated_props', $partial, $updated_props );
	}

	/**
	 * Get a title for the new post type.
	 *
	 * @return string
	 */
	protected function get_post_title() {
		return sprintf(
			/* translators: %s: Order date */
			__( 'Partial Payment &ndash; %s', 'yith-paypal-payments-for-woocommerce' ),
			strftime( _x( '%b %d, %Y @ %I:%M %p', 'Order date parsed by strftime', 'yith-paypal-payments-for-woocommerce' ) ) // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText
		);
	}
}
