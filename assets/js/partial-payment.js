/**
 * PayPal API Partial Payment Handler
 */


(function ($) {

	if( typeof woocommerce_admin_meta_boxes == 'undefined' ) {
		return;
	}

	var partial_payment_order = {
		init: function () {

			var items = $( '#woocommerce-order-items' ),
				partials = items.find( 'tr.partial-payment' );

			items
				.on( 'click', 'button.void-authorization', this.void_authorization )
				.on( 'click', '.button.partial-payment', this.show_section )
				.on( 'change keyup', '#partial_payment_amount', this.amount_changed )
				.on( 'click', 'button.do-partial-payment', this.do_payment )
				.on( 'click', 'button.cancel', this.cancel );

			// Custom refund handler for partial payments.
			if ( partials.length ) {
				items
					.on( 'click', 'button.refund-items', this.refund_partials )
					.on( 'change', '.refund input.refund_partial', this.refund_input_changed )
					.on( 'click', 'button.do-api-refund, button.do-manual-refund', this.do_refund );
			}
		},

		block: function () {
			$( '#woocommerce-order-items' ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );
		},

		unblock: function () {
			$( '#woocommerce-order-items' ).unblock();
		},

		do_request: function (data) {
			$.ajax( {
				url: woocommerce_admin_meta_boxes.ajax_url,
				data: data,
				type: 'POST',
				success: function (response) {
					if ( 'success' === response.result ) {
						// Redirect to same page for show the refunded status
						window.location.reload();
					} else {
						window.alert( response.error );
						partial_payment_order.unblock();
					}
				},
			} );
		},

		void_authorization: function() {
			partial_payment_order.block();

			if ( window.confirm( yith_ppwc_partial_payment.void_confirm ) ) {
				partial_payment_order.do_request( {
					action: yith_ppwc_partial_payment.ajaxAction,
					security: yith_ppwc_partial_payment.ajaxNonce,
					request: 'void_payment_authorization',
					order_id: woocommerce_admin_meta_boxes.post_id,
				} );
			} else {
				partial_payment_order.unblock();
			}
		},

		show_section: function () {

			if ( !$( 'div.wc-order-partial-payment' ).length ) {
				var wrap = $( this ).closest( '.wc-order-data-row' ),
					template = wp.template( 'yith-ppwc-partial-payment' );

				wrap.after( template( {} ) ).hide();
			}

			$( 'div.wc-order-partial-payment' ).slideDown();
			$( 'div.wc-order-data-row-toggle' ).not( 'div.wc-order-partial-payment' ).slideUp();
			$( 'div.wc-order-totals-items' ).slideUp();
			$( '.wc-order-edit-line-item .wc-order-edit-line-item-actions' ).hide();

			return false;
		},

		amount_changed: function () {
			var total = accounting.unformat( $( this ).val(), woocommerce_admin.mon_decimal_point );

			$( 'button .wc-order-partial-amount .amount' ).text( accounting.formatMoney( total, {
				symbol: woocommerce_admin_meta_boxes.currency_format_symbol,
				decimal: woocommerce_admin_meta_boxes.currency_format_decimal_sep,
				thousand: woocommerce_admin_meta_boxes.currency_format_thousand_sep,
				precision: woocommerce_admin_meta_boxes.currency_format_num_decimals,
				format: woocommerce_admin_meta_boxes.currency_format
			} ) );
		},

		do_payment: function () {
			partial_payment_order.block();

			if ( window.confirm( yith_ppwc_partial_payment.do_payment_confirm ) ) {
				var amount = $( 'input#partial_payment_amount' ).val(),
					payed = $( 'input#partial_payed_amount' ).val();

				partial_payment_order.do_request( {
					action: yith_ppwc_partial_payment.ajaxAction,
					security: yith_ppwc_partial_payment.ajaxNonce,
					request: 'partial_payment',
					order_id: woocommerce_admin_meta_boxes.post_id,
					payment_amount: amount,
					payed_amount: payed
				} );
			} else {
				partial_payment_order.unblock();
			}
		},

		cancel: function () {

			$( 'div.wc-order-partial-payment' ).slideUp();
			$( 'div.wc-order-totals-items, div.wc-order-bulk-actions' ).slideDown();
			$( '.wc-order-edit-line-item .wc-order-edit-line-item-actions' ).show();

			return false;
		},

		refund_partials: function (ev) {
			ev.stopImmediatePropagation();

			$( 'div.wc-order-refund-items' ).slideDown();
			$( 'div.wc-order-data-row-toggle' ).not( 'div.wc-order-refund-items' ).slideUp();
			$( 'div.wc-order-totals-items' ).slideUp();
			$( '#woocommerce-order-items' ).find( 'tr.partial-payment div.refund' ).show();
			$( '.wc-order-edit-line-item .wc-order-edit-line-item-actions' ).hide();

			// Hide unavailable elements
			$( 'label[for="restock_refunded_items"]' ).closest( 'tr' ).hide();
			// Make sure main refund amount input id readonly
			$( 'input#refund_amount' ).attr( 'readonly', 'readonly' );

			var total_refund_available = 0;
			$( 'input[name="order_partial_amount_remaining"]' ).each( function (i, elem) {
				total_refund_available += parseFloat( accounting.formatNumber(
					elem.value,
					woocommerce_admin_meta_boxes.currency_format_num_decimals,
					'',
					woocommerce_admin.mon_decimal_point
				) );
			} );

			$( 'label[for="refund_amount"]' ).closest( 'tr' ).prev().find( 'span.amount' ).text(
				accounting.formatMoney( total_refund_available, {
					symbol: woocommerce_admin_meta_boxes.currency_format_symbol,
					decimal: woocommerce_admin_meta_boxes.currency_format_decimal_sep,
					thousand: woocommerce_admin_meta_boxes.currency_format_thousand_sep,
					precision: woocommerce_admin_meta_boxes.currency_format_num_decimals,
					format: woocommerce_admin_meta_boxes.currency_format
				} )
			);
		},

		refund_input_changed: function () {
			var refund_amount = 0;
			var $items = $( '.woocommerce_order_items' ).find( 'tr.partial-payment' );

			$items.each( function () {
				var $row = $( this );
				var refund_cost_fields = $row.find( '.refund input' );

				refund_cost_fields.each( function (index, el) {
					refund_amount += parseFloat( accounting.unformat( $( el ).val() || 0, woocommerce_admin.mon_decimal_point ) );
				} );
			} );

			$( '#refund_amount' )
				.val( accounting.formatNumber(
					refund_amount,
					woocommerce_admin_meta_boxes.currency_format_num_decimals,
					'',
					woocommerce_admin.mon_decimal_point
				) )
				.change();
		},

		do_refund: function (ev) {
			ev.stopImmediatePropagation();

			partial_payment_order.block();

			if ( window.confirm( woocommerce_admin_meta_boxes.i18n_do_refund ) ) {
				var refund_amount = $( 'input#refund_amount' ).val(),
					refund_reason = $( 'input#refund_reason' ).val(),
					refunded_amount = $( 'input#refunded_amount' ).val(),
					partial_items = {};

				$( '.refund input.refund_partial' ).each( function (index, item) {
					if ( $( item ).closest( 'tr' ).data( 'order_partial_id' ) ) {
						partial_items[$( item ).closest( 'tr' ).data( 'order_partial_id' )] = accounting.unformat(
							item.value,
							woocommerce_admin.mon_decimal_point
						);
					}
				} );

				partial_payment_order.do_request( {
					action: yith_ppwc_partial_payment.ajaxAction,
					security: yith_ppwc_partial_payment.ajaxNonce,
					request: 'partial_payment_refund',
					order_id: woocommerce_admin_meta_boxes.post_id,
					refund_amount: refund_amount,
					refunded_amount: refunded_amount,
					refund_reason: refund_reason,
					partial_items: JSON.stringify( partial_items, null, '' ),
					api_refund: $( this ).is( '.do-api-refund' ),
				} );

			} else {
				partial_payment_order.unblock();
			}
		},
	};

	partial_payment_order.init();

})( jQuery );