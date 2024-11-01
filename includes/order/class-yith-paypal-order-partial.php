<?php
/**
 * Order partial. Partial are based on orders (essentially partial order payments made by paypal) and
 * contain much of the same data.
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order refund class.
 */
class YITH_PayPal_Order_Partial extends WC_Abstract_Order {

	/**
	 * Which data store to load.
	 *
	 * @var string
	 */
	protected $data_store_name = 'order-partial';

	/**
	 * This is the name of this object type.
	 *
	 * @var string
	 */
	protected $object_type = 'order_partial';

	/**
	 * Stores product data.
	 *
	 * @var array
	 */
	protected $extra_data = array(
		'amount'         => '',
		'transaction_id' => '',
		'date_paid'      => null,
	);

	/**
	 * Get internal type (post type.)
	 *
	 * @return string
	 */
	public function get_type() {
		return 'shop_order_partial';
	}

	/**
	 * Get status - always completed for refunds.
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string
	 */
	public function get_status( $context = 'view' ) {
		return 'completed';
	}

	/**
	 * Get a title for the new post type.
	 */
	public function get_post_title() {
		// @codingStandardsIgnoreStart
		return sprintf( __( 'Refund &ndash; %s', 'woocommerce' ), strftime( _x( '%b %d, %Y @ %I:%M %p', 'Order date parsed by strftime', 'woocommerce' ) ) );
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Get partial amount.
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return int|float
	 */
	public function get_amount( $context = 'view' ) {
		return $this->get_prop( 'amount', $context );
	}


	/**
	 * How much money is left to refund?
	 *
	 * @return string
	 */
	public function get_remaining_refund_amount() {
		return wc_format_decimal( $this->get_amount() - $this->get_total_refunded(), wc_get_price_decimals() );
	}

	/**
	 * Get amount already refunded.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_total_refunded() {

		global $wpdb;

		$cache_key   = WC_Cache_Helper::get_cache_prefix( 'orders' ) . 'total_refunded' . $this->get_id();
		$cached_data = wp_cache_get( $cache_key, $this->cache_group );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM( postmeta.meta_value )
				FROM $wpdb->postmeta AS postmeta
				INNER JOIN $wpdb->postmeta AS postmeta2 USING( post_id )
				INNER JOIN $wpdb->posts AS posts ON ( posts.post_type = 'shop_order_refund' AND posts.post_parent = %d )
				WHERE postmeta.meta_key = '_refund_amount'
				AND postmeta2.meta_key = '_partial_payment_id' AND postmeta2.meta_value = %d
				AND postmeta.post_id = posts.ID",
				$this->get_parent_id(),
				$this->get_id()
			)
		);

		$total_refunded = floatval( $total );

		wp_cache_set( $cache_key, $total_refunded, $this->cache_group );

		return $total_refunded;
	}

	/**
	 * Get formatted partial amount.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_formatted_amount() {
		return wc_price( $this->get_amount(), array( 'currency' => $this->get_currency() ) );
	}

	/**
	 * Get date paid.
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return WC_DateTime|NULL object if the date is set or null if there is no date.
	 */
	public function get_date_paid( $context = 'view' ) {
		return $this->get_prop( 'date_paid', $context );
	}

	/**
	 * Get transaction d.
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string
	 */
	public function get_transaction_id( $context = 'view' ) {
		return $this->get_prop( 'transaction_id', $context );
	}

	/**
	 * Set refunded amount.
	 *
	 * @param string $value Value to set.
	 * @throws WC_Data_Exception Exception if the amount is invalid.
	 */
	public function set_amount( $value ) {
		$this->set_prop( 'amount', wc_format_decimal( $value ) );
	}

	/**
	 * Set transaction id.
	 *
	 * @param string $value Transaction id.
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 */
	public function set_transaction_id( $value ) {
		$this->set_prop( 'transaction_id', $value );
	}

	/**
	 * Set date paid.
	 *
	 * @param  string|integer|null $date UTC timestamp, or ISO 8601 DateTime. If the DateTime string has no timezone or offset, WordPress site timezone will be assumed. Null if their is no date.
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 */
	public function set_date_paid( $date = null ) {
		$this->set_date_prop( 'date_paid', $date );
	}
}
