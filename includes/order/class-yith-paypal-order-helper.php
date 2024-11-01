<?php
/**
 * Helper class form partial payments order
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Order_Helper
 */
class YITH_PayPal_Order_Helper {

	/**
	 * Constructor
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	public function __construct() {
		// Register post type.
		add_action( 'init', array( $this, 'register_post_types' ), 5 );
		// Add custom data store.
		add_filter( 'woocommerce_data_stores', array( $this, 'add_data_store' ), 10, 1 );
		// Register custom order status.
		add_filter( 'woocommerce_register_shop_order_post_statuses', array( $this, 'register_order_status' ), 10, 1 );
		add_filter( 'wc_order_statuses', array( $this, 'add_order_status' ), 10, 1 );
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'add_order_status_to_valid_order_status_for_payment_complete' ), 10, 1 );

		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'add_partial_button' ), 10, 1 );
		add_action( 'woocommerce_admin_order_items_after_shipping', array( $this, 'add_partial_payment_items' ), 10, 1 );
	}

	/**
	 * Register custom order partial post types.
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	public function register_post_types() {

		if ( ! is_blog_installed() || post_type_exists( 'shop_order_partial' ) || ! function_exists( 'wc_register_order_type' ) ) {
			return;
		}

		wc_register_order_type(
			'shop_order_partial',
			apply_filters(
				'yith_paypal_payments_register_post_type_shop_order_partial',
				array(
					'label'                            => __( 'Partial Payment', 'yith-paypal-payments-for-woocommerce' ),
					'capability_type'                  => 'shop_order',
					'public'                           => false,
					'hierarchical'                     => false,
					'supports'                         => false,
					'exclude_from_orders_screen'       => true,
					'add_order_meta_boxes'             => false,
					'exclude_from_order_count'         => true,
					'exclude_from_order_views'         => true,
					'exclude_from_order_reports'       => true,
					'exclude_from_order_sales_reports' => true,
					'class_name'                       => 'YITH_PayPal_Order_Partial',
					'rewrite'                          => false,
				)
			)
		);

		do_action( 'yith_paypal_payments_after_register_post_type' );
	}

	/**
	 * Register custom order status. Used for order with authorized payments
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param array $statuses Array of status.
	 * @return array
	 */
	public function register_order_status( $statuses ) {

		$statuses['wc-payment-auth'] = array(
			'label'                     => _x( 'PayPal Payment authorized', 'Order status', 'yith-paypal-payments-for-woocommerce' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders */
			'label_count'               => _n_noop( 'PayPal Payment authorized <span class="count">(%s)</span>', 'PayPal Payment authorized <span class="count">(%s)</span>', 'yith-paypal-payments-for-woocommerce' ),
		);

		return $statuses;
	}

	/**
	 * Add custom order status to the list of available order statuses
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @param array $statuses Array of status.
	 * @return array
	 */
	public function add_order_status( $statuses ) {
		$statuses['wc-payment-auth'] = _x( 'PayPal Payment authorized', 'Order status', 'yith-paypal-payments-for-woocommerce' );
		return $statuses;
	}

	/**
	 * Add custom data store
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @param array $stores An array of data stores.
	 * @return array
	 */
	public function add_data_store( $stores ) {
		$stores['order-partial'] = 'YITH_PayPal_Order_Partial_Data_Store_CPT';
		return $stores;
	}

	/**
	 * Check if given order can have partial payments
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @param WC_Order $order WC order.
	 * @return boolean
	 */
	public static function can_order_have_partial( $order ) {
		$voided = 'voided' === $order->get_meta( '_captured' );
		return ! $voided && yith_ppwc_check_gateway( $order->get_payment_method() ) && $order->get_meta( '_yith_ppwc_paypal_authorize_info', true ) && 'payment-auth' === $order->get_status();
	}

	/**
	 * Add partial payment button on order metabox
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public function add_partial_button( $order ) {
		if ( self::can_order_have_partial( $order ) ) {
			echo '<button type="button" class="button partial-payment">' . esc_html__( 'Partial Payment', 'yith-paypal-payments-for-woocommerce' ) . '</button>';
			echo '<button type="button" class="button void-authorization">' . esc_html__( 'Void Payment Authorization', 'yith-paypal-payments-for-woocommerce' ) . '</button>';
			// add also partial payment template.
			$partial_payed = self::get_total_partial_payed( $order );
			include YITH_PAYPAL_PAYMENTS_PATH . 'templates/admin/html-action-partial-payment.php';
		}
	}

	/**
	 * Add partial payment items after shipping items
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @param integer $order_id Order id.
	 * @return void
	 */
	public function add_partial_payment_items( $order_id ) {
		$partials = self::get_partials( $order_id );
		if ( ! empty( $partials ) ) {
			// Print line partial.
			foreach ( $partials as $partial ) {
				include YITH_PAYPAL_PAYMENTS_PATH . 'templates/admin/html-order-partial-payment.php';
			}
		}
	}

	/**
	 * Get order partial payment.
	 *
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @param string|integer $order_id Order id.
	 * @return array of YITH_PayPal_Order_Partial objects
	 */
	public static function get_partials( $order_id ) {
		$cache_key   = WC_Cache_Helper::get_cache_prefix( 'orders' ) . 'partial' . $order_id;
		$cached_data = wp_cache_get( $cache_key, 'orders' );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$partials = wc_get_orders(
			array(
				'type'   => 'shop_order_partial',
				'parent' => $order_id,
				'limit'  => -1,
			)
		);

		wp_cache_set( $cache_key, $partials, 'orders' );

		return $partials;
	}

	/**
	 * Get partial amount already payed for an order.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param WC_Order $order The order object.
	 * @return float
	 */
	public static function get_total_partial_payed( $order ) {

		global $wpdb;

		// Use the WC cache system.
		$cache_key   = WC_Cache_Helper::get_cache_prefix( 'orders' ) . 'partial_payed' . $order->get_id();
		$cached_data = wp_cache_get( $cache_key, 'orders' );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM( postmeta.meta_value )
				FROM $wpdb->postmeta AS postmeta
				INNER JOIN $wpdb->posts AS posts ON ( posts.post_type = 'shop_order_partial' AND posts.post_parent = %d )
				WHERE postmeta.meta_key = '_partial_amount'
				AND postmeta.post_id = posts.ID",
				$order->get_id()
			)
		);

		$total_payed = floatval( $total );

		wp_cache_set( $cache_key, $total_payed, 'orders' );

		return $total_payed;
	}

	/**
	 * Get the remaining order amount that can be payed
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param WC_Order $order The order object.
	 * @return float
	 */
	public static function get_remaining_order_amount( $order ) {
		return $order->get_total() - self::get_total_partial_payed( $order );
	}

	/**
	 * Get max partial payment amount available for an order.
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param WC_Order $order The order object.
	 * @return string
	 */
	public static function get_max_partial_payment( $order ) {
		return wc_format_decimal( self::get_remaining_order_amount( $order ), wc_get_price_decimals() );
	}

	/**
	 * Create a partial payment
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param array $args New refund arguments.
	 * @return YITH_PayPal_Order_Partial|WP_Error
	 * @throws Exception Exception message.
	 */
	public static function create_partial_payment( $args = array() ) {
		$default_args = array(
			'amount'     => 0,
			'order_id'   => 0,
			'partial_id' => 0,
			'capture'    => true,
		);

		try {

			$args  = wp_parse_args( $args, $default_args );
			$order = wc_get_order( $args['order_id'] );

			if ( ! $order ) {
				throw new Exception( __( 'Invalid order ID.', 'yith-paypal-payments-for-woocommerce' ) );
			}

			// Double check for payment method.
			$payment_method = $order->get_payment_method();
			if ( ! yith_ppwc_check_gateway( $payment_method ) ) {
				throw new Exception( __( 'Invalid request.', 'yith-paypal-payments-for-woocommerce' ) );
			}

			$max_payment_amount = self::get_max_partial_payment( $order );
			$partial            = new YITH_PayPal_Order_Partial( $args['partial_id'] );

			if ( 0 > $args['amount'] || $args['amount'] > $max_payment_amount ) {
				throw new Exception( __( 'Invalid partial payment amount.', 'yith-paypal-payments-for-woocommerce' ) );
			}

			$partial->set_currency( $order->get_currency() );
			$partial->set_amount( $args['amount'] );
			$partial->set_parent_id( absint( $args['order_id'] ) );
			$partial->set_prices_include_tax( $order->get_prices_include_tax() );

			$partial->add_meta_data( '_captured', 'no' );
			$partial->add_meta_data( '_yith_ppwc_invoice_id', $order->get_meta( '_yith_ppwc_invoice_id' ) );

			$partial->update_taxes();
			$partial->calculate_totals( false );
			$partial->set_total( $args['amount'] );

			do_action( 'yith_paypal_payments_create_partial', $partial, $args );

			if ( $partial->save() ) {

				if ( $args['capture'] ) {
					$result = YITH_PayPal::get_instance()->get_gateway()->request_authorized_partial_payment( $partial->get_id() );

					if ( is_wp_error( $result ) ) {
						$partial->delete();
						return $result;
					}
				} elseif ( isset( $args['transaction_id'], $args['final_capture'] ) ) {
					YITH_PayPal::get_instance()->get_gateway()->save_transaction_id_on_partial_payment( $args['transaction_id'], $order, $partial, $args['final_capture'] );
				}

				$partial->save();

			}

			do_action( 'yith_paypal_payments_partial_created', $partial->get_id(), $args );

		} catch ( Exception $e ) {
			if ( isset( $partial ) && is_a( $partial, 'YITH_PayPal_Order_Partial' ) ) {
				wp_delete_post( $partial->get_id(), true );
			}
			return new WP_Error( 'error', $e->getMessage() );
		}

		return $partial;
	}

	/**
	 * Create a partial payment refund
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @param array $args New refund arguments.
	 * @return WC_Order_Refund|WP_Error
	 * @throws Exception Exception message.
	 */
	public static function create_partial_payment_refund( $args ) {

		$default_args = array(
			'amount'         => 0,
			'reason'         => null,
			'order_id'       => 0,
			'partial_id'     => 0,
			'refund_payment' => false,
		);

		try {

			$args    = wp_parse_args( $args, $default_args );
			$partial = wc_get_order( $args['partial_id'] );
			if ( ! $partial ) {
				throw new Exception( __( 'Invalid partial order ID.', 'yith-paypal-payments-for-woocommerce' ) );
			}

			$order_id = $args['order_id'] ? $args['order_id'] : $partial->get_parent_id();
			$order    = wc_get_order( $order_id );
			if ( ! $order ) {
				throw new Exception( __( 'Invalid order ID.', 'woocommerce' ) );
			}

			// Double check for payment method.
			$payment_method = $order->get_payment_method();
			if ( ! yith_ppwc_check_gateway( $payment_method ) ) {
				throw new Exception( __( 'Invalid order gateway.', 'yith-paypal-payments-for-woocommerce' ) );
			}

			$remaining_refund_amount = $partial->get_remaining_refund_amount();
			$refund                  = new WC_Order_Refund( 0 );

			if ( 0 > $args['amount'] || $args['amount'] > $remaining_refund_amount ) {
				throw new Exception( __( 'Invalid refund amount.', 'yith-paypal-payments-for-woocommerce' ) );
			}

			$refund->set_currency( $order->get_currency() );
			$refund->set_amount( $args['amount'] );
			$refund->set_parent_id( absint( $args['order_id'] ) );
			$refund->set_refunded_by( get_current_user_id() ? get_current_user_id() : 1 );
			$refund->set_prices_include_tax( $order->get_prices_include_tax() );
			$refund->add_meta_data( '_partial_payment_id', $partial->get_id() );

			if ( ! is_null( $args['reason'] ) ) {
				$refund->set_reason( $args['reason'] );
			}

			$refund->update_taxes();
			$refund->calculate_totals( false );
			$refund->set_total( $args['amount'] * -1 );

			// this should remain after update_taxes(), as this will save the order, and write the current date to the db
			// so we must wait until the order is persisted to set the date.
			if ( isset( $args['date_created'] ) ) {
				$refund->set_date_created( $args['date_created'] );
			}

			/**
			 * Action hook to adjust refund before save.
			 *
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_create_refund', $refund, $args );

			if ( $refund->save() ) {
				if ( $args['refund_payment'] ) {

					$gateway = YITH_PayPal::get_instance()->get_gateway();

					if ( ! $gateway->supports( 'refunds' ) ) {
						throw new Exception( __( 'The payment gateway for this order does not support automatic refunds.', 'yith-paypal-payments-for-woocommerce' ) );
					}

					$result = $gateway->process_refund( $order->get_id(), $refund->get_amount(), $refund->get_reason(), $partial->get_transaction_id() );

					if ( ! $result ) {
						throw new Exception( __( 'An error occurred while attempting to create the refund using the payment gateway API.', 'yith-paypal-payments-for-woocommerce' ) );
					}

					if ( is_wp_error( $result ) ) {
						$refund->delete();
						return $result;
					}

					$refund->set_refunded_payment( true );
					$refund->save();
				}
			}

			do_action( 'woocommerce_refund_created', $refund->get_id(), $args );
			do_action( 'woocommerce_order_refunded', $order->get_id(), $refund->get_id() );

		} catch ( Exception $e ) {
			if ( isset( $refund ) && is_a( $refund, 'WC_Order_Refund' ) ) {
				wp_delete_post( $refund->get_id(), true );
			}
			return new WP_Error( 'error', $e->getMessage() );
		}

		return $refund;
	}

	/**
	 * Add the new status to the list of valid order status for payment complete.
	 *
	 * @param array $status Valid status order for payment complete.
	 * @return array
	 *
	 * @since 1.0.0
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	public function add_order_status_to_valid_order_status_for_payment_complete( $status ) {
		array_push( $status, 'payment-auth' );
		return $status;
	}

	/**
	 * Register payment sent by PayPal webhook.
	 *
	 * @param WC_Order $order Order to complete.
	 * @param array    $resource Webhook request.
	 *
	 * @throws Exception Throws Exception.
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @since 1.0.0
	 */
	public static function register_payment_from_webhook( $order, $resource ) {

		if ( self::can_order_have_partial( $order ) ) {
			$partials = self::get_partials( $order->get_id() );
			if ( empty( $partials ) && 1 === $resource['final_capture'] ) {
				$order->payment_complete( $resource['id'] );
			} else {
				self::create_partial_payment(
					array(
						'amount'         => $resource['amount']['value'],
						'order_id'       => $order->get_id(),
						'transaction_id' => $resource['id'],
						'final_capture'  => $resource['final_capture'],
						'capture'        => false,
					)
				);
			}
		} else {
			$order->payment_complete( $resource['id'] );
		}

	}
}

new YITH_PayPal_Order_Helper();
