=== TapTree Payments for WooCommerce ===
Contributors: taptree
Tags: payments, checkout, woocommerce, credit card, payment gateway
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 2.1.4
Requires PHP: 8.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Accept cards, Apple Pay, PayPal and local payment methods in WooCommerce with the official TapTree plugin.

== Description ==

Connect your WooCommerce store to TapTree and accept cards, Apple Pay, PayPal, local payment methods and Buy Now Pay Later through one integration. Each payment method appears as its own option in your checkout once it is activated for your TapTree account.

By default, payment opens in a secure TapTree popup window while your store stays open in the background. If the browser blocks the popup, the customer is redirected to TapTree's hosted checkout page instead. For each payment method you can also switch to a full redirect. Refunds can be issued directly from the WooCommerce order.

Each payment method has the setting "Projektbeitrag je Zahlung anzeigen" (off by default). When it is on, the thank-you page shows the project contribution for a payment, but only in units TapTree has cleared for display to shoppers; the payment-method chooser never shows a figure. No unit is cleared at the moment, so the setting currently shows nothing. CO₂ figures are never shown to shoppers.

A fixed share of what TapTree earns on payments goes into forest protection and reforestation. [Learn more](https://taptree.org/impact)

== Available payment methods ==
Credit and debit cards:

* VISA
* MasterCard
* American Express
* Carte Bancaire
* DanKort
* PostePay
* Nexi

International payment methods:

* Apple Pay
* PayPal
* Paysafecard

Local payment methods

* Trustly (EU)
* Payconiq (Luxembourg, Belgium)
* iDEAL (Netherlands)
* EPS (Austria)
* WeChat Pay (China)
* SEPA Direct Debit (EU)
* Blik (Poland)
* Przelewy24 (Poland)

Buy Now Pay Later (BNPL)

* iDEAL IN3
* Riverty

== Changelog ==

= 2.1.4 =
* Tested with WordPress 7.1 and WooCommerce 11.2.
* Per-payment figures are now a configurable opt-in with an allow-list of units; none is enabled; the previous CO₂ display is retired.
* The setting is called "Projektbeitrag je Zahlung anzeigen" (formerly "Impact anzeigen") and is off for every payment method after the update, also where an earlier version had saved it as on; turn it on per payment method once a unit is enabled.
* The per-method figure in the checkout and on the order-pay page is gone for good.
* Stored payment method descriptions that still contain the {{impact}} placeholder or a former default text are reset to the new default; {{impact}} is retired and inserts nothing.
* Updated the plugin description.

= 2.1.3 =
* Support for tt_-prefixed API keys (v2 format) with backward-compat for existing tokens. Adds Sandbox environment detection.

== Upgrade Notice ==

= 2.1.4 =
CO₂ figures in the checkout and on the thank-you page are retired. Per-payment figures are now an opt-in per payment method with an allow-list of units; none is enabled, so nothing is shown. The setting is off after the update.
