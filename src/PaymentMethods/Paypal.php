<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\PaymentMethods;

use TapTree\WooCommerce\PaymentMethods\Abstract\PaymentMethodAbstract;
use TapTree\WooCommerce\PaymentMethods\Interface\PaymentMethodInterface;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Paypal extends PaymentMethodAbstract implements PaymentMethodInterface
{
    protected function getProps(): array
    {
        return
            [
                'id' => 'paypal',
                'default_title' => __('PayPal', 'woocommerce'),
                'settings_description' => __('Accept payments via PayPal', 'woocommerce'),
                'default_description' => '',
                'has_fields' => false,
                'instructions' => true,
                'supports' => [
                    'products',
                    'refunds',
                    'subscriptions',
                ],
                'filtersOnBuild' => false,
                'confirmationDelayed' => false,
                'SEPA' => false,
                'Subscription' => true,
            ];
    }

    protected function selectFormFields(array $sharedFormFields): array
    {
        return $sharedFormFields;
    }

    protected function getLogoBase64(): string
    {
        return $this->settingsHelper->getPluginUrl() . 'public/logos/payment-methods/' . $this->getId() . '.svg';
    }
};