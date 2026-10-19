# TapTree Payments for WooCommerce

Connect your WooCommerce store to TapTree and accept cards, Apple Pay, PayPal, local payment methods and Buy Now Pay Later through one integration. Each payment method appears as its own option in your checkout once it is activated for your TapTree account.

By default, payment opens in a secure TapTree popup window while your store stays open in the background. If the browser blocks the popup, the customer is redirected to TapTree's hosted checkout page instead. For each payment method you can also switch to a full redirect. Refunds can be issued directly from the WooCommerce order.

## Available payment methods

Credit and debit cards:

- VISA
- MasterCard
- American Express
- Carte Bancaire
- DanKort
- PostePay
- Nexi

International payment methods:

- Apple Pay
- PayPal
- Paysafecard

Local payment methods

- Trustly (EU)
- Payconiq (Luxembourg, Belgium)
- iDEAL (Netherlands)
- EPS (Austria)
- WeChat Pay (China)
- SEPA Direct Debit (EU)
- Blik (Poland)
- Przelewy24 (Poland)

Buy Now Pay Later (BNPL)

- iDEAL IN3
- Riverty

## Features

- Popup or redirect checkout, configurable per payment method
- Per-payment project contribution on the thank-you page as an opt-in per payment method, limited to units TapTree has cleared for shoppers (none at the moment, see below)
- Support for refunds from WooCommerce
- Test mode with sandbox API keys
- Debugging log

## Per-payment project contribution

Each payment method has the setting "Projektbeitrag je Zahlung anzeigen" (off by default, also after updating from an earlier version). When it is on, the thank-you page shows the project contribution for a payment, but only in units that TapTree has cleared for display to shoppers; the payment-method chooser never shows a figure. That allow-list lives in `src/Settings/ConsumerImpact.php` (`CONSUMER_UNITS`) and is empty, so nothing is shown at the moment; CO₂ figures are never shown to shoppers.

## About TapTree

A fixed share of what TapTree earns on payments goes into forest protection and reforestation. [Learn more](https://taptree.org/impact)

# License

[GPLv3 (GNU General Public License, version 3) License](http://www.gnu.org/licenses/gpl-3.0.html). Copyright 2024, TapTree GmbH.
