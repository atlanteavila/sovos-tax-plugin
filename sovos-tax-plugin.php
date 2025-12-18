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
