<?
add_action('wp_enqueue_scripts', function () {
    // Only affect checkout page
    if (!is_checkout()) {
        return;
    }

    /**
     * Attach inline JS AFTER wc-checkout so we can intercept its behavior.
     * Priority 20 ensures checkout.js is already registered.
     */
    wp_add_inline_script(
        'wc-checkout',
        <<<JS
(function ($) {

    /**
     * When false:
     * - No checkout recalculations
     * - No spinner
     * - No AJAX
     * - No Sovos calls
     */
    let allowCheckoutUpdate = false;

    /**
     * HARD BLOCK all update_checkout events unless explicitly allowed.
     * This stops WooCommerce BEFORE it:
     * - Blocks the UI
     * - Fires update_order_review
     * - Calls Sovos
     */
    $(document.body).on('update_checkout', function (e) {
        if (!allowCheckoutUpdate) {
            e.stopImmediatePropagation();
            return false;
        }
    });

    /**
     * Allow EXACTLY ONE checkout recalculation
     */
    function runCheckoutUpdate() {
        allowCheckoutUpdate = true;
        $(document.body).trigger('update_checkout');
        allowCheckoutUpdate = false;
    }

    $(function () {

        alert('hello world it works');

        /**
         * Explicit user intent triggers
         *
         * Default:
         * - #toggle-billing-details (your Save Address button)
         *
         * Extendable via:
         *   window.sovosDeferUpdateTriggers = ['#my-custom-btn'];
         */
	const triggerSelectors = [
            '#toggle-billing-details',
            ...(window.sovosDeferUpdateTriggers || [])
        ].join(', ');

        /**
         * Only THESE actions are allowed to recalc checkout
         */
	$(document.body).on('click', triggerSelectors, function () {
            runCheckoutUpdate();
        });

	/**
         * Shipping address toggle should still sync totals once
         */
	$('#ship-to-different-address-checkbox').on('change', function () {
            runCheckoutUpdate();
        });

    });

})(jQuery);
JS
    );
}, 20);
