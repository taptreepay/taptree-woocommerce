<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class PaymentMethodImageBuilder
{
    public static function set_payment_image(TapTreePaymentGateway $gateway, $impactTitle = null)
    {
        $impactElement = '';

        if ($impactTitle && is_string($impactTitle) && !str_starts_with($impactTitle, '0 ')) {
            $formattedImpactTitle = self::formatImpactTitle($impactTitle);

            $impactElement = '<div style="
                display: inline-block;
                font-weight: 500;
                font-size: smaller;
                background-color: #ddd;
                border: none;
                color: black;
                padding: 2px 10px;
                text-align: center;
                text-decoration: none;
                margin-left: 16px;
                cursor: pointer;
                border-radius: 12px;
                position: relative;
            " onmouseover="this.querySelector(\'.tooltip-text\').style.visibility=\'visible\';this.querySelector(\'.tooltip-text\').style.opacity=\'1\';"
               onmouseout="this.querySelector(\'.tooltip-text\').style.visibility=\'hidden\';this.querySelector(\'.tooltip-text\').style.opacity=\'0\';">
                <span>-' . $formattedImpactTitle . '</span>
                <svg xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px; vertical-align: middle; fill: #24b47e; margin-left: 5px;" viewBox="0 0 24 24">
                    <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 18c-4.418 0-8-3.582-8-8s3.582-8 8-8 8 3.582 8 8-3.582 8-8 8z"/>
                    <path d="M11 10h2v6h-2zM11 7h2v2h-2z"/>
                </svg>
                <span class="tooltip-text" style="
                    visibility: hidden;
                    opacity: 0;
                    background-color: #555;
                    color: #fff;
                    text-align: left;
                    border-radius: 6px;
                    padding: 10px;
                    position: absolute;
                    z-index: 9999;
                    top: 120%; /* Position the tooltip below */
                    left: 50%;
                    transform: translateX(-50%);
                    min-width: 320px;
                    max-width: 380px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    font-size: 11px;
                    white-space: normal;
                    transition: opacity 0.3s, transform 0.3s;
                    overflow-wrap: break-word;
                ">Deine Zahlung bindet die angegebene Menge CO₂ durch den Schutz alter Wälder in der Eifel – umgesetzt von der Wohllebens Waldakademie und finanziert durch unsere Payment-Partnerin TapTree.</span>
            </div>';
        }

        // Get the payment method logo
        $logo = $gateway->paymentMethod->getLogoHTML();

        // Return the combined element
        return $logo . $impactElement;
    }

    private static function formatImpactTitle(string $impactTitle): string
    {
        // Replace "CO2" with properly formatted CO₂
        return preg_replace('/CO2/', 'CO₂', $impactTitle);
    }
}
