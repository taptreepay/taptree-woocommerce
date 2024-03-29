<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class PaymentMethodImageBuilder
{
    public static function set_payment_image(TapTreePaymentGateway $gateway, $impactTitle = null)
    {
        $impactElement = '';
        if ($impactTitle && gettype($impactTitle) === 'string' && !str_starts_with($impactTitle, '0 ')) {
            $impactElement = '<div style="display:inline; font-weight: 500; font-size: smaller; background-color: #ddd;
                border: none;
                color: black;
                height: 30px;
                padding: 4px 10px;
                text-align: center;
                text-decoration: none;
                display: inline-block;
                margin: -2px 40px 0 0 ;
                float: right;
                cursor: pointer;
                border-radius: 16px;">-' . $impactTitle . '</div>';
        }

        $logo = $gateway->paymentMethod->getLogoHTML();

        return $logo . $impactElement;
    }
}
