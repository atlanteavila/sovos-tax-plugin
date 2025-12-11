# Sovos Tax Plugin

A lightweight WordPress plugin that boots the `Sovos\SovosIntegration` class for use inside WooCommerce. Provide your Sovos credentials via environment variables and activate the plugin to make the integration available to your site code.

## Installation
1. Copy this repository into your WordPress `wp-content/plugins/` directory.
2. Ensure WooCommerce is active.
3. Set the following environment variables so the plugin can configure credentials without storing them in code:
   - `SOVOS_COMPANY`
   - `SOVOS_USERNAME`
   - `SOVOS_PASSWORD`
   - `SOVOS_HMAC_KEY`
4. Activate **Sovos Tax Plugin** from the WordPress admin Plugins screen.

On successful load, the plugin exposes a global `$sovos_integration` instance you can use to calculate taxes or interact with Sovos.
