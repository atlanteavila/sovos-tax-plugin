( function ( $ ) {
    const data = window.sovosCheckoutIntent || {};
    const nonce = data.nonce;
    const intentFieldName = '_sovos_checkout_nonce';

    if ( ! nonce ) {
        return;
    }

    const ensureIntentField = function () {
        let field = $( 'form.checkout input[name="' + intentFieldName + '"]' );

        if ( ! field.length ) {
            field = $( '<input>', { type: 'hidden', name: intentFieldName } );
            $( 'form.checkout' ).append( field );
        }

        return field;
    };

    const clearIntent = function () {
        const field = $( 'form.checkout input[name="' + intentFieldName + '"]' );
        if ( field.length ) {
            field.val( '' );
        }
    };

    const markIntent = function () {
        ensureIntentField().val( nonce );
    };

    $( document.body ).on(
        'click',
        'form.checkout button[name="update_order_review"], form.checkout input[name="update_order_review"], form.checkout button[name="woocommerce_checkout_update_totals"], form.checkout input[name="woocommerce_checkout_update_totals"], form.checkout :submit',
        function () {
            markIntent();
        }
    );

    $( 'form.checkout' ).on( 'checkout_place_order', function () {
        markIntent();
    } );

    $( document.body ).on( 'updated_checkout checkout_error', function () {
        clearIntent();
    } );
} )( jQuery );
