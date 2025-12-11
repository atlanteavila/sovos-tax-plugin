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

## Reducing unnecessary Sovos calls
To keep transaction volume low, the integration skips Sovos API requests when it detects orders that should not generate tax:

- Customers with wholesale roles (default: `wholesale_customer`, `wholesaler`, `b2b`).
- Orders that have an active reseller certificate on file (tax exempt).
- Orders where every product is marked tax-exempt in WooCommerce (`tax_status` of `none`).

You can override the detection logic via filters:

- `sovos_integration_is_wholesale_customer` — change how wholesale customers are identified.
- `sovos_integration_is_tax_exempt_product` — mark additional products as tax exempt.
