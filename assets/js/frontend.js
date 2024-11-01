/**
 * Frontend JS
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 *
 * @var yith_ppwc_frontend
 */

(function( $, document ) {
	"use strict";

	var isValid = true,
		buttonRendered = false,
		cardFormRendered = false,
		formRequest,
		formRequestType = '',
		currentFlow = '';

	function in_array( needle, haystack ) {
		for (var i in haystack) {
			if ( haystack[i] === needle ) {
				return true;
			}
		}
		return false;
	}

	// Check if the Paypal Request needs a form to be processed. Return the form itself.
	function checkRequestForm() {

		var button = $( document ).find( '.yith-ppwc-button' );
		// If button data location is set get it, otherwise try to catch the location from page form.
		formRequestType = button.attr( 'data-location' );
		// Get the for request based on location
		if ( 'checkout' === formRequestType ) {
			formRequest = button.closest( 'form[name="checkout"]' );
		} else if ( 'pay_order' === formRequestType ) {
			formRequest = button.closest( '#order_review' );
		} else if ( 'product' === formRequestType ) {
			formRequest = button.closest( '.product.type-product' ).find( 'form.cart' );
		}
	}

	// Serialize form input.
	function serializeForm() {

		var data = formRequest.serializeArray();

		// additional check form product form
		if ( 'product' === formRequestType ) {
			var addToCart = formRequest.find( 'button[name="add-to-cart"]' );

			data.push( {name: 'is-yith-ppwc-action', value: 'yes'} );
			if ( addToCart.length ) {
				data.push( {name: 'add-to-cart', value: addToCart.val()} );
			}
		}

		return data;
	}

	// Format the request body.
	function formatRequestBody( body ) {
		var formatted = [];

		// add security nonce
		body.push(
			{name: 'security', value: yith_ppwc_frontend.ajaxNonce},
			{name: 'flow', value: currentFlow}
		);

		jQuery.each( body, function( index, item ) {
			formatted.push( item.name + '=' + item.value );
		} );

		return formatted.join( '&' );
	}

	// Handle custom request error.
	function handleRequestError( data ) {
		if ( data && data.reload ) {
			window.location.reload();
		} else if ( data && data.redirect ) {
			window.location.href = data.redirect;
		} else {

			var error_messages 	= ( data && data.messages.length ) ? data.messages : yith_ppwc_frontend.errorMessage;

			if ( 'checkout' === formRequestType ) {
				$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
				$( 'form.checkout' ).prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_messages + '</div>' );

				$( document.body ).trigger( 'checkout_error', [ error_messages ] );
				formRequest.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
			} else {
				$( '.woocommerce-notices-wrapper' ).first().html( error_messages );
			}

			if ( typeof $.scroll_to_notices != 'undefined' ) {
				$.scroll_to_notices( ( 'checkout' === formRequestType ? $('.woocommerce-NoticeGroup-checkout') : $( '.woocommerce-notices-wrapper' ).first() ) );
			}

			unblockFormRequest();
		}

		return false;
	}

	function blockFormRequest() {
		if ( typeof formRequest != 'undefined' && ! formRequest.hasClass( 'processing' ) ) {
			formRequest
				.addClass( 'processing' )
				.block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				} );
		}
	}

	function unblockFormRequest() {
		if ( typeof formRequest != 'undefined' && formRequest.hasClass( 'processing' ) ) {
			formRequest.removeClass( 'processing' ).unblock();
		}
	}

	function togglePlaceOrder( enable ) {

		var isPayPalChecked 	= $( '#payment_method_yith_paypal_payments' ).is( ':checked' ),
			isCustomCardChecked	= $( '#payment_method_yith_paypal_payments_custom_card' ).is( ':checked' ),
			placeOrder			= $( '#place_order' ),
			PayPalbutton		= $( '.yith-ppwc-button' );

		// Reset to start condition. Place order button visible and PayPal buttons hidden
		PayPalbutton.hide();
		placeOrder.attr( 'disabled', false ).css( {'opacity': '1', 'cursor': 'pointer'} ).show();

		if ( ! enable && ( isPayPalChecked || isCustomCardChecked ) ) {
			placeOrder.attr( 'disabled', true );

			if ( isPayPalChecked ) {
				placeOrder.hide();
				PayPalbutton.show();
			}
			else {
				placeOrder.css( {'opacity': '0.5', 'cursor': 'default'} );
			}
		}
	}

	// Render the PayPal button
	function showButton() {

		var button = $( document ).find( '.yith-ppwc-button' );

		if ( ! isValid ) {
			button.hide();
		} else {
			button.show();
		}

		if ( buttonRendered ) {
			return;
		}
		buttonRendered = true;

		paypal.Buttons( {
			style: {
				layout: yith_ppwc_frontend.layout,
				color: yith_ppwc_frontend.buttonColor,
				shape: yith_ppwc_frontend.buttonShape,
				label: button.data( 'label' )
			},
			onClick: function( data, actions ) {

				var body = [];

				currentFlow = (data && data.fundingSource) ? data.fundingSource : '';
				checkRequestForm();
				if ( typeof formRequest == 'undefined' || ! formRequest.length ) {
					return actions.resolve(); // Continue if no form must be validated.
				}

				body = serializeForm();
				if ( 'checkout' === formRequestType ) {
					body.push( {name: 'request', value: 'validate_checkout'} );
					blockFormRequest();
				} else {
					body.push( {name: 'request', value: 'validate_product_cart'} );
				}

				return fetch( yith_ppwc_frontend.ajaxUrl, {
					method: 'POST',
					headers: {
						'content-type': 'application/x-www-form-urlencoded'
					},
					body: formatRequestBody( body )
				} ).then( function( res ) {
					return res.json();
				} ).then( function( data ) {

					if ( data && 'failure' === data.result ) {
						handleRequestError( data );
						return actions.reject();
					}

					return actions.resolve(); // No error, continue.
				} ).catch( function( error ) {
					console.log( '--- PayPal button onClick error: ' + error + ' ---' );
					handleRequestError( null );
					return actions.reject();
				} );
			},
			createOrder: function( data, actions ) {
				return fetch( yith_ppwc_frontend.ajaxUrl, {
					method: 'POST',
					headers: {
						'content-type': 'application/x-www-form-urlencoded'
					},
					body: formatRequestBody( [
						{name: 'request', value: 'create_order'},
						{name: 'checkoutRequest', value: formRequestType},
						{name: 'orderID', value: button.data( 'order' )}
					] )
				} )
					.then( function( res ) {
						return res.json();
					} )
					.then( function( data ) {
						unblockFormRequest();
						return data.id; // Use the same key name for order ID on the client and server
					} );
			},
			onError: function( err ) {
				window.location.reload();
			},
			onApprove: function( data, actions ) {
				if ( data && data.orderID ) {

					if ( 'checkout' === formRequestType ) {
						blockFormRequest();
					}

					fetch( yith_ppwc_frontend.ajaxUrl, {
						method: 'POST',
						headers: {
							'content-type': 'application/x-www-form-urlencoded'
						},
						body: formatRequestBody( [
							{name: 'request', value: 'approve_order'},
							{name: 'orderID', value: data.orderID},
							{name: 'checkoutRequest', value: formRequestType}
						] ),
					} )
						.then( function( res ) {
							return res.json();
						} )
						.then( function( json ) {
							if ( json ) {
								if ( json.redirect ) {
									window.location.href = json.redirect;
								}
								else if ( json.result && 'failure' === json.result ) {
									window.location.reload();
								}
							}

							// if request id checkout submit form checkout and let WC handle it
							if ( 'checkout' === formRequestType || 'pay_order' === formRequestType ) {
								unblockFormRequest();
								formRequest.submit();
							}
						} );
				}
			}
		} )
			.render( '.yith-ppwc-button' )
			.catch( function( error ) {
				console.log( error );
			} );
	}

	function showCreditCard() {
		if ( typeof paypal.HostedFields == "undefined" || cardFormRendered ) {
			return;
		}

		// Eligibility check for advanced credit and debit card payments
		if ( paypal.HostedFields.isEligible() ) {

			var cardContainer = $( '#yith-ppwc-cc-form' ),
				errorWrapper = cardContainer.find( '.error' );

			checkRequestForm();
			currentFlow = 'unbranded_card';
			cardFormRendered = true;

			paypal.HostedFields.render( {
				createOrder: function( data, actions ) {
					return fetch( yith_ppwc_frontend.ajaxUrl, {
						method: 'POST',
						headers: {
							'content-type': 'application/x-www-form-urlencoded'
						},
						body: formatRequestBody( [
							{name: 'request', value: 'create_order'},
							{name: 'checkoutRequest', value: formRequestType},
							{name: 'orderID', value: $( '#yith_paypal_cc_payments-order-id' ).data( 'order-id' )},
						] )
					} )
						.then( function( res ) {
							return res.json();
						} )
						.then( function( data ) {
							return data.id; // Use the same key name for order ID on the client and server
						} );
				}, // replace order-ID with the order ID
				styles: {
					'input': {
						'font-size': '13px',
						'font-family': 'helvetica, tahoma, calibri, sans-serif',
						'color': '#3a3a3a',
						'padding': '0'
					},
					':focus': {
						'color': 'black'
					},
					'input.invalid': {
						'color': '#e53a40'
					},

				},
				fields: {
					number: {
						selector: '#yith-ppwc-cc-card-number',
						placeholder: '1111 1111 1111 1111'
					},
					cvv: {
						selector: '#yith-ppwc-cc-cvv',
						placeholder: '123'
					},
					expirationDate: {
						selector: '#yith-ppwc-cc-expiration-date',
						placeholder: '10/24'
					}
				}
			} )
				.then( function( hf ) {

					var validityCheck = function() {
						var state = hf.getState();
						errorWrapper.html( '' );
						// Check if all fields are valid, then show submit button
						var formValid = Object.keys( state.fields ).every( function( key ) {
							return state.fields[key].isValid;
						} );

						togglePlaceOrder( formValid );
					};

					hf.on( 'validityChange', validityCheck );
					validityCheck();

					hf.on( 'empty', function( event ) {
						errorWrapper.html( '' );
						$( '#card-image' ).removeClass();
					} );

					hf.on( 'cardTypeChange', function( event ) {
						errorWrapper.html( '' );
						// Change card bg depending on card type
						if ( event.cards.length === 1 ) {

							$( '#card-image' ).removeClass().addClass( event.cards[0].type );


							// Change the CVV length for AmericanExpress cards
							if ( event.cards[0].code.size === 4 ) {
								hf.setAttribute( {
									field: 'cvv',
									attribute: 'placeholder',
									value: '1234'
								} );

							}
						} else {
							hf.setAttribute( {
								field: 'cvv',
								attribute: 'placeholder',
								value: '123'
							} );

						}
					} );

					$( '#place_order' ).on( 'click', function( event ) {
						// double check payment methods
						if ( yith_ppwc_frontend.customCardPaymentID != $( 'input[name="payment_method"]:checked' ).val() ) {
							return;
						}

						event.preventDefault();
						errorWrapper.html( '' );

						var args = {},
							secure3D = $( document ).find( '#payments-sdk__contingency-lightbox' ).length > 0;
						if ( secure3D ) {
							args = {
								contingencies: ['3D_SECURE']
							};
						}

						blockFormRequest();
						hf.submit( args ).then( function( payload ) {

							try {

								var paypal_order_id = $( '#yith_paypal_cc_payments-order-id' );

								if ( secure3D ) {
									var liability = $( document ).find( '#payments-sdk__contingency-lightbox' ).data( 'liability' ).split( "," );

									// Needed only when 3D Secure contingency applied
									if ( typeof payload.liabilityShift !== "undefined" && ! in_array( payload.liabilityShift.toLowerCase(), liability ) ) {

										if ( payload.liabilityShift.toLowerCase() === 'unknown' ) {
											errorWrapper.html( yith_ppwc_frontend.secure_3d_unknown );
											throw yith_ppwc_frontend.secure_3d_unknown;
										} else if ( payload.liabilityShift.toLowerCase() === 'no' ) {
											errorWrapper.html( yith_ppwc_frontend.secure_3d_no );
											throw yith_ppwc_frontend.secure_3d_no;
										}
									}
								}

								if ( typeof payload.orderId === 'undefined' || ! payload.orderId ) {
									throw 'PayPal order ID missing';
								}

								paypal_order_id.val( payload.orderId );

								// unblock form and re-submit. Unblock is mandatory for WC script
								unblockFormRequest();
								formRequest.submit();
								return true;
							} catch (error) {
								unblockFormRequest();
								console.log( error );
								return false;
							}

						} ).catch( function( error ) {
							if ( typeof error.details !== "undefined" && error.details.length > 0 ) {
								var errorMessage = '';
								error.details.forEach( function( element ) {
									errorMessage += element.description;
								} );
								errorWrapper.html( errorMessage );
								unblockFormRequest();
							}
						} );
					} );

				} )
				.catch( function( error ) {
					console.log( error );
				} );
		} else {
			$( '#yith-ppwc-cc-form' ).hide();  // hides the advanced credit and debit card payments fields if merchant isn't eligible
		}
	}

	// Handle checkout page.
	if ( $( document ).find( '#checkout_as_confirm_page' ).length ) {
		$( document.body ).on( 'updated_checkout', function() {
			$( document ).find( 'ul.wc_payment_methods' ).hide();
		} );
	} else if ( $( document ).find( '.yith-ppwc-button' ).length ) { // if is standard checkout page.

		var initPlugin = function() {
			// make sure button is emptied
			$( document ).find( '.yith-ppwc-button' ).html( '' );
			// reset render flags
			buttonRendered = false;
			cardFormRendered = false;

			showButton();
			showCreditCard();
		};

		$( document ).ready( function() {
			checkRequestForm();

			// listen payment method change if form request is checkout or pay_order
			if ( 'checkout' === formRequestType || 'pay_order' === formRequestType ) {
				$( document.body ).on( 'change', 'input[name="payment_method"]', function() {
					togglePlaceOrder( null );
				} );
			}

			// for checkout initialize plugin on updated_checkout trigger
			if ( 'checkout' === formRequestType ) {
				$( document.body ).on( 'updated_checkout', function() {
					togglePlaceOrder( null );
					initPlugin();
				} );
			} else {
				initPlugin();
			}

		} );

		// let's listen a custom trigger to re-init plugin
		$( document ).on( 'yith_ppwc_initialize', initPlugin );
	}

	// Handle single product page.
	$( 'form.variations_form' ).on( 'show_variation', function( ev, variation, purchasable ) {
		isValid = purchasable;
		showButton();
	} );
	$( 'form.variations_form' ).on( 'hide_variation', function( ev, variation, purchasable ) {
		isValid = false;
		showButton();
	} );

}( jQuery, document ));
