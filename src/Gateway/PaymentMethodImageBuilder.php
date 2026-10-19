<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class PaymentMethodImageBuilder
{
    /**
     * Builds the payment method logo markup shown next to the gateway title; the
     * chooser never carries a contribution figure.
     *
     * @param TapTreePaymentGateway $gateway
     * @return string
     */
    public static function set_payment_image(TapTreePaymentGateway $gateway): string
    {
        $logo = $gateway->paymentMethod->getLogoHTML();

        return '<div class="taptree-impact-and-logo-wrapper"><div class="taptree-method-logo">' . $logo . '</div></div>';
    }
}
