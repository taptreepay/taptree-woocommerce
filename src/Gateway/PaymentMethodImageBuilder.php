<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class PaymentMethodImageBuilder
{
    /**
     * Generates the payment method image with tooltip.
     *
     * @param TapTreePaymentGateway $gateway
     * @param string|null $impactTitle
     * @return string
     */
    public static function set_payment_image(TapTreePaymentGateway $gateway, $impactTitle = null): string
    {
        $impactElement = '';

        if ($impactTitle && is_string($impactTitle) && !str_starts_with($impactTitle, '0 ')) {
            $formattedImpactTitle = self::formatImpactTitle($impactTitle);

            // Translatable strings
            $tooltipTopText = __('Deine Zahlung bindet die angegebene Menge CO₂ durch den Schutz von Urwald in der Eifel', 'taptree');
            $tooltipBottomText = __('mit Wohllebens Waldakademie und TapTree<br/>50.32571, 6.79904', 'taptree');
            $altText = __('TapTree Urwald in 50.32571, 6.79904', 'taptree');


            $impactElement = '
                <div class="taptree-impact-element">
                    <div class="taptree-impact-title">-' . esc_html($formattedImpactTitle) . '
                        <svg xmlns="http://www.w3.org/2000/svg" class="taptree-impact-svg" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 18c-4.418 0-8-3.582-8-8s3.582-8 8-8 8 3.582 8 8-3.582 8-8 8z"/>
                            <path d="M11 10h2v6h-2zM11 7h2v2h-2z"/>
                        </svg>
                    </div>
                    <span class="taptree-tooltip-image" role="tooltip">
                            <div class="taptree-tooltip-overlay taptree-top-overlay">
                                ' . esc_html($tooltipTopText) . '
                            </div>
                            <img src="' . esc_url(self::get_plugin_public_url() . 'map/WohllebenTTForest.webp') . '" class="taptree-tooltip-img" alt="' . esc_attr($altText) . '">
                            <div class="taptree-tooltip-overlay taptree-bottom-overlay">
                                ' . $tooltipBottomText . '
                            </div>
                    </span>
                </div>
            ';
        }

        // Get the payment method logo
        $logo = $gateway->paymentMethod->getLogoHTML();

        // Return the combined element
        return '<div class="taptree-impact-and-logo-wrapper">' . $impactElement . '<div class="taptree-method-logo">' . $logo . '</div></div>';
    }

    /**
     * Formats the impact title by replacing "CO2" with "CO₂".
     *
     * @param string $impactTitle
     * @return string
     */
    private static function formatImpactTitle(string $impactTitle): string
    {
        // Replace "CO2" with properly formatted CO₂
        return preg_replace('/CO2/', 'CO₂', $impactTitle);
    }

    /**
     * Constructs the public URL for plugin assets.
     *
     * @return string
     */
    private static function get_plugin_public_url(): string
    {
        // Dynamically construct the correct URL
        return plugins_url('/public/', dirname(__FILE__, 2));
    }
}
