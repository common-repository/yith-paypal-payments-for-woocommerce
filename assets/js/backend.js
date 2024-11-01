/**
 * Admin JS
 *
 * @author YITH
 * @package YITH PayPal Payments for WooCommerce
 * @version 1.0.0
 */

( function( $ ){

    // on change environment, submit the form and reload the page
    $( document ).on( 'change', '#yith_ppwc_gateway_options\\[environment\\]', function() {
        if ( ! $( this ).closest( '.yith-plugin-fw-radio' ).hasClass( 'yith-plugin-fw-radio--initialized' ) ) {
            return false;
        }
        $(this).closest( 'form' ).submit();
    } );

    function ajaxRequest( data, item ) {

        data['action' ] = yith_ppwc_admin.ajaxAction;
        data['security' ] = yith_ppwc_admin.ajaxNonce;

        return $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: data,
            beforeSend: function() {
                item.block({
                    message: null,
                    overlayCSS: {
                        background: 'url(' + yith_ppwc_admin.ajaxLoader + ') #fff no-repeat center',
                        opacity: 0.6
                    }});
            },
            complete: function ( response ) {
                //item.unblock();
            }
        });
    }

    // Disconnect account
    $( document ).on( 'click', '.onboarding-action-buttons a.logout', function( ev ) {
        ev.preventDefault();

        var link    = $(this).attr('href'),
            confirm = $( '#yith-ppwc-logout-confirm' );


        var buttons = {};
        buttons[yith_ppwc_admin.continue] = function() {
            window.location.replace( link );
        };
        buttons[yith_ppwc_admin.cancel] = function() { confirm.dialog("close"); };

        // init dialog
        confirm.dialog({
            width: 350,
            modal: true,
            dialogClass: 'yith-ppwc-logout-confirm',
            buttons: buttons
        });
    });

    $('#yith_ppwc_button_funding_sources-card').on('change', function(){
        var $t = $(this);
        var $option = $(document).find('#yith_ppwc_button_credit_cards').closest('tr');

        if( $t.is( ':checked' )){
            $option.show();
        }else{
            $option.hide();
        }
    }).change();

} )( jQuery );
