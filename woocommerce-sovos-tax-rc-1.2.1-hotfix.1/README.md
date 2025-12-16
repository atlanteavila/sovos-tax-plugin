![Plugin Logo](woocommerce-sovos-tax-handler-plugin-logo.png)
<p align="center" style="font-size:42px !important;">WooCommerce Sovos Tax</p>

## About
A custom plugin for retrieving and handling Tax calculations from Sovos Tax Compliance Software

## Exemption meta keys

The plugin uses several meta values to short-circuit Sovos calls when a shopper or product is known to be exempt:

* **`_sovos_always_exempt`** (product meta) – boolean flag surfaced on the product edit screen that marks the item as always exempt from Sovos.
* **`_sovos_exempt_emails`** (user meta) – newline-separated list of emails or domains that qualify the customer for exemption.
* **`_sovos_exempt_alternate_emails`** (user meta) – additional allowlisted emails/domains checked alongside the primary list.
* **`_sovos_is_exempt`**, **`_sovos_exempt_reason`**, **`_sovos_exempt_email`** (order meta) – persisted indicators so later hooks and status transitions reuse the exemption decision instead of re-calling Sovos.
