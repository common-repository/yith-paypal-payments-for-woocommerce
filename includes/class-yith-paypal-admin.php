<?php
/**
 * Admin class
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YITH_PayPal_Admin
 */
class YITH_PayPal_Admin {

	/**
	 * Plugin panel page
	 *
	 * @const string
	 */
	const PANEL_PAGE = 'yith_paypal_payments';

	/**
	 * Plugin panel object
	 *
	 * @var YITH_PayPal_Admin
	 */
	protected $panel = null;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function __construct() {
		// Plugin Panel.
		add_action( 'admin_menu', array( $this, 'register_panel' ), 5 );
		// Panel custom type.
		add_action( 'woocommerce_admin_field_yith_ppwc_login_button', array( $this, 'paypal_login_button' ), 10, 1 );

		// Make sure gateway correctly load options after a save or reset action.
		// TODO check for a better solution.
		add_action( 'yit_panel_wc_after_update', array( $this, 'sync_environment_change' ), 5 );
		add_action( 'yit_panel_wc_after_update', array( $this, 'reload_after_save' ) );
		add_action( 'reload_after_save', array( $this, 'reload_after_save' ) );

		// Listen the query string to catch the merchant data.
		add_action( 'admin_init', array( $this, 'login_merchant_from_query' ), 0 );
		// Handle merchant admin action.
		add_action( 'admin_init', array( $this, 'logout_merchant' ) );
		add_action( 'admin_init', array( $this, 'refresh_merchant' ) );

		add_action( 'admin_init', array( $this, 'redirect_panel_page' ) );

		// Add action links.
		add_filter( 'plugin_action_links_' . plugin_basename( YITH_PAYPAL_PAYMENTS_PATH . '/' . basename( YITH_PAYPAL_PAYMENTS_FILE ) ), array( $this, 'action_links' ) );
		add_filter( 'yith_show_plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 5 );

	}

	/**
	 * The login PayPal API credentials button
	 *
	 * @param array $options Options array.
	 * @return void
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	public function paypal_login_button( $options ) {

		// check if merchant is currently logged in.
		$merchant = YITH_PayPal_Merchant::get_merchant();

		if ( ! $merchant->is_valid() ) { // needs login.
			// then get the login url.
			$login_url = YITH_PayPal::get_instance()->get_gateway()->get_login_url();
		} else {
			// Logout url action.
			$logout_url = add_query_arg(
				array(
					'page'   => self::PANEL_PAGE,
					'action' => 'logout_merchant',
					'nonce'  => wp_create_nonce( 'logout_merchant' ),
				),
				admin_url( 'admin.php' )
			);
			// Refresh url action.
			$refresh_url = add_query_arg(
				array(
					'page'   => self::PANEL_PAGE,
					'action' => 'refresh_merchant',
					'nonce'  => wp_create_nonce( 'refresh_merchant' ),
				),
				admin_url( 'admin.php' )
			);
		}

		include YITH_PAYPAL_PAYMENTS_PATH . 'templates/admin/login-button.php';
	}

	/**
	 * Add a panel under YITH Plugins tab
	 *
	 * @since 1.0.0
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function register_panel() {

		if ( ! empty( $this->panel ) ) {
			return;
		}

		$admin_tabs = array(
			'general' => __( 'Settings', 'yith-paypal-payments-for-woocommerce' ),
			'button'  => __( 'Button options', 'yith-paypal-payments-for-woocommerce' ),
		);

		$merchant = YITH_PayPal_Merchant::get_merchant();
		if ( 'yes' === wc_bool_to_string( $merchant->is_enabled_to_custom_card_fields() ) ) {
			$admin_tabs['credit-card'] = __( 'Custom Credit Card', 'yith-paypal-payments-for-woocommerce' );
		}

		$args = array(
			'create_menu_page' => true,
			'parent_slug'      => '',
			'page_title'       => 'YITH PayPal Payments for WooCommerce',
			'menu_title'       => 'PayPal Payments for WooCommerce',
			'capability'       => 'manage_woocommerce',
			'parent'           => '',
			'parent_page'      => 'yith_plugin_panel',
			'page'             => self::PANEL_PAGE,
			'admin-tabs'       => $admin_tabs,
			'options-path'     => YITH_PAYPAL_PAYMENTS_PATH . '/plugin-options',
			'class'            => yith_set_wrapper_class(),
		);

		/* === Fixed: not updated theme  === */
		if ( ! class_exists( 'YIT_Plugin_Panel_WooCommerce' ) ) {
			require_once YITH_PAYPAL_PAYMENTS_PATH . '/plugin-fw/lib/yit-plugin-panel-wc.php';
		}

		$this->panel = new YIT_Plugin_Panel_WooCommerce( $args );
	}

	/**
	 * Login and set merchant data from query string.
	 * The url is the one returned by PayPal login window
	 *
	 * @return void
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	public function login_merchant_from_query() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended

		if ( ! isset( $_GET['page'] ) || ! isset( $_GET['merchantIdInPayPal'] ) || self::PANEL_PAGE !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		// Check for cookie value.
		$login_data = isset( $_COOKIE['yith_ppwc_login'] ) ? json_decode( wp_unslash( $_COOKIE['yith_ppwc_login'] ), true ) : false; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! empty( $login_data ) ) {

			$merchant = YITH_PayPal_Merchant::get_merchant();
			if ( ! $merchant->is_valid() ) { // if current merchant is not valid attempt to login it.

				if ( $merchant->login( $login_data ) ) {
					// Set the merchant ID.
					$merchant->set( 'merchant_id', sanitize_text_field( wp_unslash( $_GET['merchantIdInPayPal'] ) ) );
					// Additional fields.
					isset( $_GET['permissionsGranted'] ) && $merchant->set( 'permissions_granted', sanitize_text_field( wp_unslash( $_GET['permissionsGranted'] ) ) );
					isset( $_GET['accountStatus'] ) && $merchant->set( 'account_status', sanitize_text_field( wp_unslash( $_GET['accountStatus'] ) ) );
					isset( $_GET['consentStatus'] ) && $merchant->set( 'consent_status', sanitize_text_field( wp_unslash( $_GET['consentStatus'] ) ) );

					// Save.
					$merchant->save();
				} else {
					// If merchant is not valid reset and force delete saved data.
					$merchant->logout();
				}
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PANEL_PAGE ) );
		exit;

		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Manage a logout merchant request
	 *
	 * @return void
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	public function logout_merchant() {
		if ( ! isset( $_GET['action'] ) || ! isset( $_GET['nonce'] )
			|| 'logout_merchant' !== sanitize_text_field( wp_unslash( $_GET['action'] ) ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'logout_merchant' ) ) {
			return;
		}

		$merchant = YITH_PayPal_Merchant::get_merchant();
		$merchant->logout();

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PANEL_PAGE ) );
		exit;
	}

	/**
	 * Manage a logout merchant request
	 *
	 * @return void
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	public function refresh_merchant() {
		if ( ! isset( $_GET['action'] ) || ! isset( $_GET['nonce'] )
			|| 'refresh_merchant' !== sanitize_text_field( wp_unslash( $_GET['action'] ) ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'refresh_merchant' ) ) {
			return;
		}

		$merchant = YITH_PayPal_Merchant::get_merchant();
		$merchant->check_status( true );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PANEL_PAGE ) );
		exit;
	}

	/**
	 * Reload admin section to make sure gateway is correctly initialized
	 *
	 * @return void
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	public function reload_after_save() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_REQUEST['page'] ) || self::PANEL_PAGE !== sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) ) {
			return;
		}

		$tab = isset( $_REQUEST['tab'] ) ? '&tab=' . sanitize_text_field( wp_unslash( $_REQUEST['tab'] ) ) : '';
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PANEL_PAGE . $tab ) );
		exit;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Redirect the wc-setting page to the YITH panel
	 *
	 * @return void
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 */
	public function redirect_panel_page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$redirect_to = '';
		if ( isset( $_GET['page'], $_GET['tab'], $_GET['section'] ) && 'wc-settings' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) && 'checkout' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
			switch ( $_GET['section'] ) {
				case 'yith_paypal_payments':
					$redirect_to = add_query_arg( array( 'page' => self::PANEL_PAGE ), admin_url( 'admin.php' ) );
					break;
				case 'yith_paypal_payments_custom_card':
					$redirect_to = add_query_arg(
						array(
							'page' => self::PANEL_PAGE,
							'tab'  => 'credit-card',
						),
						admin_url( 'admin.php' )
					);
					break;
				default:
			}
		}

		! empty( $redirect_to ) && wp_safe_redirect( $redirect_to );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Action Links
	 * Add the action links to plugin admin page
	 *
	 * @param string $links | links plugin array.
	 * @return   mixed
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 * @since 1.0.0
	 * @use plugin_action_links_{$plugin_file_name}
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 */
	public function action_links( $links ) {
		$links = yith_add_action_links( $links, self::PANEL_PAGE, false );

		return $links;
	}

	/**
	 * Plugin row_meta
	 * Add the action links to plugin admin page.
	 *
	 * @param array    $new_row_meta_args An array of plugin row meta.
	 * @param string[] $plugin_meta       An array of the plugin's metadata,
	 *                                    including the version, author,
	 *                                    author URI, and plugin URI.
	 * @param string   $plugin_file       Path to the plugin file relative to the plugins directory.
	 * @param array    $plugin_data       An array of plugin data.
	 * @param string   $status            Status of the plugin. Defaults are 'All', 'Active',
	 *                                    'Inactive', 'Recently Activated', 'Upgrade', 'Must-Use',
	 *                                    'Drop-ins', 'Search', 'Paused'.
	 * @return   array
	 * @since 1.0.0
	 * @use plugin_row_meta
	 * @author Francesco Licandro <francesco.licandro@yithemes.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	public function plugin_row_meta( $new_row_meta_args, $plugin_meta, $plugin_file, $plugin_data, $status ) {

		if ( defined( 'YITH_PAYPAL_PAYMENTS_INIT' ) && YITH_PAYPAL_PAYMENTS_INIT === $plugin_file ) {

			foreach ( $new_row_meta_args['to_show'] as $key => $value ) {
				if ( in_array( $value, array( 'support', 'live_demo', 'premium_version' ), true ) ) {
					unset( $new_row_meta_args['to_show'][ $key ] );
				}
			}

			$new_row_meta_args['slug']       = YITH_PAYPAL_PAYMENTS_SLUG;
			$new_row_meta_args['is_premium'] = false;
		}

		return $new_row_meta_args;
	}

	/**
	 * Sync options environment change
	 *
	 * @since 1.3.1
	 * @author Francesco Licandro
	 * @return void
	 */
	public function sync_environment_change() {

		if ( ! isset( $_POST['yith_ppwc_gateway_options'] ) ) {
			return;
		}

		$pp_options = get_option( 'yith_ppwc_gateway_options', array() );
		if ( ! empty( $pp_options['environment'] ) ) {
			$cc_options = get_option( 'yith_ppwc_cc_gateway_options', array() );
			if ( ! empty( $cc_options ) ) {
				$cc_options['environment'] = $pp_options['environment'];
				update_option( 'yith_ppwc_cc_gateway_options', $cc_options );
			}
		}

	}
}

new YITH_PayPal_Admin();
