<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\PaymentMethods\Abstract;

use TapTree\WooCommerce\PaymentMethods\Interface\PaymentMethodInterface;
use TapTree\WooCommerce\Settings\SettingsHelper;

abstract class PaymentMethodAbstract implements PaymentMethodInterface
{
    protected $id;

    protected $settingsHelper;

    public function __construct(
        SettingsHelper $settingsHelper
    ) {

        $this->id = $this->getId();
        $this->settingsHelper = $settingsHelper;
    }

    public function getId(): string
    {
        return $this->getProps()['id'];
    }

    public function getLogoHTML(): string
    {
        return '<img style="'
            . 'height:30px;'
            . 'max-height: unset;'
            . 'margin: -2px 0 0 0;'
            . 'padding: 0;'
            . 'vertical-algin: unset;'
            . 'float: right;" '
            . 'src="' . $this->getLogoUrl() . '" '
            . 'alt="' . $this->getProp('default_title') . '" />';
    }

    public function getPropsAndSettings(): array
    {
        return array_merge($this->getProps(), $this->getSettings());
    }

    public function getProp(string $propName)
    {
        return $this->getPropsAndSettings()[$propName];
    }

    public function getFormFields(): array
    {
        return $this->selectFormFields($this->getSharedFormFields($this->getProps()['default_title']));
    }

    protected function getSettings(): array
    {
        $settings = get_option($this->settingsHelper->getGatewayId($this->getId()) . '_settings', false);
        if (!$settings) {
            $settings = $this->getDefaultSettings();
            update_option($this->settingsHelper->getGatewayId($this->getId()) . '_settings', $settings, true);
        }

        return $settings;
    }

    protected function getDefaultSettings(): array
    {

        $formFields = $this->getFormFields();
        $formFields = array_filter($formFields, static function ($key) {
            return !is_numeric($key);
        }, ARRAY_FILTER_USE_KEY);

        // unset($formFields['title']);

        return array_combine(array_keys($formFields), array_column($formFields, 'default')) ?: [];
    }

    protected function getSharedFormFields($defaultTitle): array
    {
        return array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable TapTree | ' . $defaultTitle, 'woocommerce'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'label' => __('Set title of payment method to "TapTree Payments"', 'woocommerce'),
                'description' => __('Check this option if you want to set the title of the payment method to "TapTree Payments" instead of "Kreditkarte und mehr"', 'woocommerce'),
                'default' => $defaultTitle,
                'desc_tip'      => true,
            ),
            'as_redirect' => array(
                'title' => __('Popup', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Checkout as redirect', 'woocommerce'),
                'description' => __('Check this option if you do not want to open the TapTree Checkout Popup but have your clients redirected instead.', 'woocommerce'),
                'default' => 'no',
                'desc_tip'      => true,
            ),
            'show_impact' => array(
                'title' => __('Show Impact', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Display the Impact of payments in checkout and on thank you page', 'woocommerce'),
                'description' => __('Check this option if you want to display the Impact generated by your payments in the checkout and on the thank you page.', 'woocommerce'),
                'default' => 'yes',
                'desc_tip'      => true,
            ),
            'show_method_description' => array(
                'title' => __('Show payment method description', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Show the standard description of this payment method if it is selected at checkout.', 'woocommerce'),
                'default' => 'no',
            )
        );
    }
}
