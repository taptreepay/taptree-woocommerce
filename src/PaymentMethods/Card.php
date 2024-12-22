<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\PaymentMethods;

use TapTree\WooCommerce\PaymentMethods\Abstract\PaymentMethodAbstract;
use TapTree\WooCommerce\PaymentMethods\Interface\PaymentMethodInterface;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Card extends PaymentMethodAbstract implements PaymentMethodInterface
{
    protected function getProps(): array
    {
        return [
            'id' => 'card',
            'default_title' => __('Kreditkarte und Apple Pay', 'woocommerce'),
            'settings_description' => __('Zahlungen per Kreditkarte und Apple Pay akzeptieren.', 'woocommerce'),
            'default_description' => __('Mit Kreditkarte oder Apple Pay bezahlen und zusätzlich <b>{{impact}}</b> entfernen – sicher, kostenlos und ohne zusätzlichen Aufwand.', 'woocommerce'),
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

    protected function getLogoUrl(): string
    {
        return $this->settingsHelper->getPluginUrl() . 'public/logos/payment-methods/' . $this->getId() . '.svg';
    }
}
