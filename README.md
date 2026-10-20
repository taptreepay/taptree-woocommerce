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
- Optional line about TapTree's forest projects on the thank-you page (not active yet, see below)
- Support for refunds from WooCommerce
- Test mode with sandbox API keys
- Debugging log

## The “Projektbeitrag je Zahlung anzeigen” setting

TapTree puts a fixed share of what it earns on payments into forest protection and reforestation. Each payment method in the plugin has a setting called “Projektbeitrag je Zahlung anzeigen” (show project contribution per payment), meant to add one line about these forest projects to your thank-you page. Since the rules for environmental claims changed in September 2026, TapTree is still working out how that line may be worded. Until then the setting does nothing and stays off, also after updating from an earlier version, and your customers never see CO₂ figures or any other number from the plugin.

For developers: the wordings cleared for customers live in `src/Settings/ConsumerImpact.php` (`CONSUMER_UNITS`); the list is empty.

## About TapTree

A fixed share of what TapTree earns on payments goes into forest protection and reforestation. [Learn more](https://taptree.org/impact)

# License

[GPLv3 (GNU General Public License, version 3) License](http://www.gnu.org/licenses/gpl-3.0.html). Copyright 2024, TapTree GmbH.
