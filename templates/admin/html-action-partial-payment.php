<?php
/**
 * Partial Payment template
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 * @var WC_Order $order
 * @var float $partial_payed
 */

defined( 'ABSPATH' ) || exit;

?>

<script type="text/template" id="tmpl-yith-ppwc-partial-payment">
	<div class="wc-order-data-row wc-order-partial-payment wc-order-data-row-toggle">
		<table class="wc-order-totals">
			<tr>
				<td class="label"><?php esc_html_e( 'Amount already paid', 'yith-paypal-payments-for-woocommerce' ); ?>:</td>
				<td class="total"><?php echo wc_price( $partial_payed, array( 'currency' => $order->get_currency() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
			<tr>
				<td class="label"><?php esc_html_e( 'Total available to pay', 'yith-paypal-payments-for-woocommerce' ); ?>:</td>
				<td class="total"><?php echo wc_price( $order->get_total() - $partial_payed, array( 'currency' => $order->get_currency() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
			<tr>
				<td class="label">
					<label for="partial_payment_amount">
						<?php esc_html_e( 'Partial payment amount', 'yith-paypal-payments-for-woocommerce' ); ?>:
					</label>
				</td>
				<td class="total">
					<input type="text" id="partial_payment_amount" name="partial_payment_amount" class="wc_input_price" />
					<div class="clear"></div>
				</td>
			</tr>
		</table>
		<div class="clear"></div>
		<div class="partial-payment-actions" style="margin-top:5px;padding-top:12px;border-top:1px solid #dfdfdf;">
			<?php
			$amount = '<span class="wc-order-partial-amount">' . wc_price( 0, array( 'currency' => $order->get_currency() ) ) . '</span>';
			?>
			<?php /* translators: refund amount  */ ?>
			<button type="button" class="button button-primary do-partial-payment"><?php printf( esc_html__( 'Pay %s via PayPal Payments', 'yith-paypal-payments-for-woocommerce' ), wp_kses_post( $amount ) ); ?></button>
			<button type="button" class="button cancel" style="float:left;"><?php esc_html_e( 'Cancel', 'yith-paypal-payments-for-woocommerce' ); ?></button>
			<input type="hidden" id="partial_payed_amount" name="partial_payed_amount" value="<?php echo esc_attr( $partial_payed ); ?>" />
			<div class="clear"></div>
		</div>
	</div>
</script>
