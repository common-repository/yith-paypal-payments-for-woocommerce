<?php
/**
 * Login PayPal button template
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 * @var YITH_PayPal_Merchant $merchant Current merchant.
 * @var string               $login_url Login url.
 */

defined( 'ABSPATH' ) || exit;

?>

<tr valign="top">
	<th scope="row" class="titledesc"><?php echo esc_html( $options['title'] ); ?></th>
	<td class="forminp yith_ppwc_login_button">
		<div class="yith_ppwc_login_button_wrapper">

			<div
					class="onboarding-status">
				<?php
				// translators:Admin option, 1 and 2 the placeholder are tags, 2 is the current status of login.
				printf( esc_html_x( 'On-boarding status: %1$s%2$s%3$s', 'Admin option, 1 and 2 the placeholder are tags, 2 is the current status of login', 'yith-paypal-payments-for-woocommerce' ), '<span class="' . esc_attr( $merchant->is_active() ) . '">', esc_html( yith_ppwc_status_label( $merchant->is_active() ) ), '</span>' );
				?>
			</div>
			<?php if ( ! $merchant->is_valid() ) : ?>
				<div class="yith-ppwc-button-wrapper">
					<a href="<?php echo esc_url( $login_url ); ?>" target="_blank"
							data-paypal-onboard-complete="onboardedCallback" data-paypal-button="PPLtBlue">
						<?php esc_html_e( 'Connect with PayPal', 'yith-paypal-payments-for-woocommerce' ); ?>
					</a>
				</div>

				<span class="description"><?php printf( esc_html_x( 'If issues occur during the onboarding with regard to connecting your account, please contact %1$s to determine the account eligibility issues. %2$sFor technical plugin support, please use the %3$s.', 'placeholder: 1. PayPal support link, 2. html tag, 3. WordPress Forum link', 'yith-paypal-payments-for-woocommerce' ), yith_ppwc_get_pp_support_link(), '<br>', yith_ppwc_get_wp_support_link() ); ?></span>
				<?php
			else :
				$permission_granted = $merchant->get( 'permissions_granted' );
				$consent_status     = $merchant->get( 'consent_status' );
				?>

				<div class="onboarding-status-info">

					<?php if ( $merchant->get( 'merchant_id' ) ) : ?>
						<div class="info">
							<span class="info-label"><?php echo esc_html_x( 'PayPal ID', 'admin login PayPal info', 'yith - paypal - payments - for - woocommerce' ); ?></span>
							<span class="info-value"><?php echo esc_html( $merchant->get( 'merchant_id' ) ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( $permission_granted ) : ?>
						<div class="info">
							<span class="info-label"><?php echo esc_html_x( 'Permission granted', 'admin login PayPal info', 'yith - paypal - payments - for - woocommerce' ); ?></span>
							<span class="info-value"><?php echo( 'true' === $permission_granted ? esc_html( 'Yes', 'yith - paypal - payments - for - woocommerce' ) : esc_html( 'No', 'yith - paypal - payments - for - woocommerce' ) ); //phpcs:ignore ?></span>
						</div>
					<?php endif; ?>


					<?php if ( $consent_status ) : ?>
						<div class="info">
							<span class="info-label"><?php echo esc_html_x( 'Consent status', 'admin login PayPal info', 'yith - paypal - payments - for - woocommerce' ); ?></span>
							<span class="info-value"><?php echo( 'true' === $consent_status ? esc_html( 'Yes', 'yith - paypal - payments - for - woocommerce' ) : esc_html( 'No', 'yith - paypal - payments - for - woocommerce' ) ); //phpcs:ignore  ?></span>
						</div>
					<?php endif; ?>

					<div class="info">
						<span class="info-label"><?php echo esc_html_x( 'Confirmed e - mail', 'admin login PayPal info', 'yith - paypal - payments - for - woocommerce' ); ?></span>
						<span class="info-value"><?php echo $merchant->is_primary_email_confirmed() ? esc_html( 'Yes', 'yith - paypal - payments - for - woocommerce' ) : esc_html( 'No', 'yith - paypal - payments - for - woocommerce' ); //phpcs:ignore
						?>
							</span>
					</div>
					<div class="info">
						<span class="info-label"><?php echo esc_html_x( 'Payment receivable', 'admin login PayPal info', 'yith - paypal - payments - for - woocommerce' ); ?></span>
						<span class="info-value"><?php echo $merchant->are_payments_receivable() ? esc_html( 'Yes', 'yith - paypal - payments - for - woocommerce' ) : esc_html( 'No', 'yith - paypal - payments - for - woocommerce' ); //phpcs:ignore
						?>
							</span>
					</div>
				</div>
				<?php if ( ! $merchant->are_payments_receivable() || ! $merchant->is_primary_email_confirmed() ) : ?>
				<div class="onboarding-status-note">
					<p>
						<strong><?php echo esc_html_x( 'Notice:', 'admin login PayPal note label', 'yith - paypal - payments - for - woocommerce' ); ?></strong>
					</p>
					<p><?php echo esc_html_x( 'To start to accept payments, login to PayPal and finish signing up . ', 'admin login PayPal note', 'yith - paypal - payments - for - woocommerce' ); ?></p>
				</div>
			<?php endif; ?>
				<div class="onboarding-action-buttons">
					<a href="<?php echo esc_url( $refresh_url ); ?>" class="button button-primary refresh">
						<?php echo esc_html_x( 'Refresh PayPal Details', 'admin login PayPal button label', 'yith - paypal - payments - for - woocommerce' ); ?>
					</a>
					<a href="<?php echo esc_url( $logout_url ); ?>" class="button button-red logout">
						<?php echo esc_html_x( 'Revoke', 'admin login PayPal button label', 'yith - paypal - payments - for - woocommerce' ); ?>
					</a>
					<div id="yith-ppwc-logout-confirm" title="<?php esc_html_e( 'Disconnect your PayPal account . ', 'yith - paypal - payments - for - woocommerce' ); ?>" style="display:none;">
						<p><?php esc_html_e( 'Disconnecting your PayPal account will prevent you from offering PayPal services and products on your website . do you with to continue ? ', 'yith - paypal - payments - for - woocommerce' ); ?></p>
					</div>
				</div>

			<?php endif; ?>
		</div>
	</td>
</tr>
