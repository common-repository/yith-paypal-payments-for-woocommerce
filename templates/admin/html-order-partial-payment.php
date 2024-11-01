<?php
/**
 * Shows a shipping line
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 * @var YITH_PayPal_Order_Partial $partial
 * @var float $partial_payed
 */

defined( 'ABSPATH' ) || exit;

?>
<tr class="partial-payment" data-order_partial_id="<?php echo esc_attr( $partial->get_id() ); ?>">
	<td class="thumb"><div></div></td>

	<td class="name">
		<?php
		printf(
			/* translators: 1: refund id 2: refund date */
			esc_html__( 'Partial payment #%1$s - %2$s (Transaction ID: %3$s)', 'yith-paypal-payments-for-woocommerce' ),
			esc_html( $partial->get_id() ),
			esc_html( wc_format_datetime( $partial->get_date_paid(), get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) ) ),
			esc_html( $partial->get_transaction_id() )
		);
		?>
	</td>

	<td class="item_cost" width="1%">&nbsp;</td>
	<td class="quantity" width="1%">&nbsp;</td>

	<td class="line_cost" width="1%">
		<div class="view">
			<?php
			echo wp_kses_post( wc_price( $partial->get_amount(), array( 'currency' => $partial->get_currency() ) ) );
			$refunded = $partial->get_total_refunded();
			if ( $refunded ) {
				echo '<small class="refunded">-' . wc_price( $refunded, array( 'currency' => $partial->get_currency() ) ) . '</small>'; //phpcs:ignore
			}
			?>
		</div>
		<div class="refund" style="display: none;">
			<input type="text" name="refund_partial[<?php echo absint( $partial->get_id() ); ?>]" placeholder="<?php echo esc_attr( wc_format_localized_price( 0 ) ); ?>" class="refund_partial wc_input_price" />
		</div>
		<input type="hidden" name="order_partial_amount_remaining" value="<?php echo esc_attr( $partial->get_amount() - $partial->get_total_refunded() ); ?>">
	</td>

	<td class="line_tax" width="1%">&nbsp;</td>
	<td class="wc-order-edit-line-item">&nbsp;</td>
</tr>
