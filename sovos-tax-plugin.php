<?php
/**
 * Plugin Name:       Sovos Tax Plugin
 * Plugin URI:        https://example.com/sovos-tax-plugin
 * Description:       Boots the Sovos integration class and prepares it for use inside WooCommerce.
 * Version:           1.0.0
 * Author:            Sovos
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sovos-tax-plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Collect admin notices so they can be shown after plugin load.
 */
function sovos_tax_plugin_admin_notices(): void
{
    $messages = apply_filters('sovos_tax_plugin_admin_notices', []);

    if (empty($messages)) {
        return;
    }

    foreach ($messages as $message) {
        printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
    }
}
add_action('admin_notices', 'sovos_tax_plugin_admin_notices');

/**
 * Ensures the Sovos credentials are available as constants. Values can be sourced
 * from environment variables to avoid hard-coding secrets.
 */
function sovos_tax_plugin_define_credentials(): bool
{
    $credential_map = [
        'SOVOS_COMPANY' => 'SOVOS_COMPANY',
        'SOVOS_USERNAME' => 'SOVOS_USERNAME',
        'SOVOS_PASSWORD' => 'SOVOS_PASSWORD',
        'SOVOS_HMAC_KEY' => 'SOVOS_HMAC_KEY',
    ];

    foreach ($credential_map as $constant => $env_key) {
        if (!defined($constant)) {
            $value = getenv($env_key);
            if ($value === false || $value === '') {
                return false;
            }

            define($constant, $value);
        }
    }

    return true;
}

/**
 * Initialize the plugin once all other plugins are loaded.
 */
function sovos_tax_plugin_init(): void
{
    $messages = [];

    if (!class_exists('WooCommerce')) {
        $messages[] = __('WooCommerce must be active for the Sovos Tax Plugin to run.', 'sovos-tax-plugin');
    }

    if (!sovos_tax_plugin_define_credentials()) {
        $messages[] = __('SOVOS_* environment variables are missing. Define SOVOS_COMPANY, SOVOS_USERNAME, SOVOS_PASSWORD, and SOVOS_HMAC_KEY.', 'sovos-tax-plugin');
    }

    if (!empty($messages)) {
        add_filter('sovos_tax_plugin_admin_notices', function (array $existing) use ($messages) {
            return array_merge($existing, $messages);
        });
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'SovosIntegration.php';

    // Instantiate the integration so downstream code can grab it from a global.
    $debug = defined('WP_DEBUG') && WP_DEBUG;

    try {
        $GLOBALS['sovos_integration'] = new \Sovos\SovosIntegration(null, $debug);
    } catch (\Throwable $exception) {
        add_filter('sovos_tax_plugin_admin_notices', function (array $existing) use ($exception) {
            $existing[] = sprintf(
                /* translators: %s: error message */
                __('Sovos Tax Plugin failed to initialize: %s', 'sovos-tax-plugin'),
                $exception->getMessage()
            );
            return $existing;
        });
    }
}
add_action('plugins_loaded', 'sovos_tax_plugin_init');

/**
 * Defer checkout recalculation until billing/shipping information is complete.
 */
function sovos_tax_plugin_enqueue_checkout_script(): void
{
    if (!function_exists('is_checkout') || !is_checkout()) {
        return;
    }

    $checkout_handle = 'wc-checkout';

    if (!wp_script_is($checkout_handle, 'enqueued')) {
        return;
    }

    $inline_script = <<<'JS'
    (function ($) {
        if (!$.fn.trigger || !document.body) {
            return;
        }

        const originalTrigger = $.fn.trigger;

        if (originalTrigger.__sovosDeferredApplied) {
            return;
        }

        let allowImmediate = false;
        let queuedUpdate = null;

        const fieldFilled = (selector) => {
            const field = $(selector);

            if (!field.length) {
                return true;
            }

            const value = field.val();
            return typeof value === 'string' && value.trim() !== '';
        };

        const isBillingComplete = () => [
            '#billing_country',
            '#billing_address_1',
            '#billing_city',
            '#billing_state',
            '#billing_postcode'
        ].every(fieldFilled);

        const isShippingDifferent = () => $('#ship-to-different-address-checkbox').is(':checked');

        const isShippingComplete = () => {
            if (!isShippingDifferent()) {
                return isBillingComplete();
            }

            return [
                '#shipping_country',
                '#shipping_address_1',
                '#shipping_city',
                '#shipping_state',
                '#shipping_postcode'
            ].every(fieldFilled);
        };

        const canUpdateCheckout = () => isBillingComplete() && isShippingComplete();

        const flushPendingUpdate = () => {
            if (!queuedUpdate || !canUpdateCheckout()) {
                return;
            }

            const { context, args } = queuedUpdate;
            queuedUpdate = null;

            allowImmediate = true;
            try {
                originalTrigger.apply(context, args);
            } finally {
                allowImmediate = false;
            }
        };

        const maybeQueueUpdate = (context, args) => {
            if (allowImmediate || canUpdateCheckout()) {
                return false;
            }

            queuedUpdate = { context, args };
            return true;
        };

        const watchAddressChanges = () => {
            const selectors = [
                '#billing_country',
                '#billing_address_1',
                '#billing_city',
                '#billing_state',
                '#billing_postcode',
                '#shipping_country',
                '#shipping_address_1',
                '#shipping_city',
                '#shipping_state',
                '#shipping_postcode',
                '#ship-to-different-address-checkbox'
            ];

            selectors.forEach((selector) => {
                $(document.body).on('change keyup', selector, flushPendingUpdate);
            });
        };

        const registerManualTriggers = () => {
            const registry = window.sovosDeferUpdateTriggers || {};

            registry.isBillingComplete = isBillingComplete;
            registry.isShippingComplete = isShippingComplete;
            registry.hasPendingUpdate = () => !!queuedUpdate;
            registry.flushPendingUpdate = flushPendingUpdate;
            registry.forceUpdate = () => {
                allowImmediate = true;
                try {
                    originalTrigger.call($(document.body), 'update_checkout');
                } finally {
                    allowImmediate = false;
                }
            };

            window.sovosDeferUpdateTriggers = registry;
        };

        $.fn.trigger = function () {
            const args = Array.prototype.slice.call(arguments);
            const eventType = args[0];
            const normalizedType = (eventType && eventType.type) ? eventType.type : eventType;

            if (normalizedType === 'update_checkout' && this.is(document.body)) {
                if (maybeQueueUpdate(this, args)) {
                    return this;
                }
            }

            const result = originalTrigger.apply(this, args);

            if (normalizedType === 'update_checkout' && this.is(document.body)) {
                queuedUpdate = null;
            }

            return result;
        };

        originalTrigger.__sovosDeferredApplied = true;

        $(function () {
            registerManualTriggers();
            watchAddressChanges();
            flushPendingUpdate();
        });
    })(jQuery);
    JS;

    wp_add_inline_script($checkout_handle, $inline_script, 'after');
}
add_action('wp_enqueue_scripts', 'sovos_tax_plugin_enqueue_checkout_script', 20);
