/**
 * PayPal API Login Handler
 */

function onboardedCallback( authCode, sharedId ) {
    "use strict";

    var cname   = 'yith_ppwc_login',
        cvalue  = JSON.stringify( {
            authCode: authCode,
            sharedId: sharedId
        } );

    // make sure old cookie is deleted if any
    document.cookie = cname + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC";
    document.cookie = cname + "=" + cvalue + "";
}
