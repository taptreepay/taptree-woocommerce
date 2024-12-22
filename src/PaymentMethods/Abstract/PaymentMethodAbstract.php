<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\PaymentMethods\Abstract;

use TapTree\WooCommerce\PaymentMethods\Interface\PaymentMethodInterface;
use TapTree\WooCommerce\SDK\TemplateRenderer;
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
        return $this->selectFormFields($this->getSharedFormFields($this->getProps()['default_title'], $this->getProps()['default_description']));
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

    public function getFormattedDescription(): string
    {
        // Fetch the saved description, falling back to default if not set
        $description = $this->getProp('description') ?: $this->getProp('default_description');

        // Replace placeholders in the description
        return $this->replacePlaceholders($description);
    }

    public function getImpact()
    {
        // Ensure WooCommerce and session exist
        if (!WC() || !WC()->session) {
            return null;
        }

        // Fetch impact data from the session
        $impactData = WC()->session->get('taptree_impact');

        // Ensure impact data is properly structured
        if (
            !isset($impactData->available_payment_methods) ||
            !is_object($impactData->available_payment_methods) ||
            !isset($impactData->available_payment_methods->{$this->getId()}) ||
            !isset($impactData->available_payment_methods->{$this->getId()}->impact)
        ) {
            return null;
        }

        // Return the specific impact object for the current payment method
        return $impactData->available_payment_methods->{$this->getId()}->impact;
    }

    protected function replacePlaceholders($text)
    {
        $impact = $this->getImpact();

        // Format the impact value and unit into a single string
        $impactFormatted = $impact
            ? sprintf('%s %s', number_format(floatval($impact->value ?? 0), 0, ',', '.'), $impact->unit ?? '')
            : '';

        $data = [
            'impact' => preg_replace('/CO2/', 'CO₂', $impactFormatted), // Pass as a single formatted string
        ];

        return TemplateRenderer::render($text, $data);
    }



    protected function getSharedFormFields($defaultTitle, $defaultDescription): array
    {
        return array(
            'enabled' => array(
                'title' => __('Aktivieren/Deaktivieren', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Aktiviere TapTree | ' . $defaultTitle, 'woocommerce'),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Titel', 'woocommerce'),
                'type' => 'text',
                'label' => __('Titel der Zahlungsmethode festlegen', 'woocommerce'),
                'description' => __('Gib den Titel der Zahlungsmethode ein, der den Kunden angezeigt wird.', 'woocommerce'),
                'default' => $defaultTitle,
                'desc_tip' => true,
            ),
            'show_impact' => array(
                'title' => __('Impact anzeigen', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Zeige den Impact der Zahlung im Checkout und auf der Bestätigungsseite', 'woocommerce'),
                'description' => __('Aktiviere diese Option, um den durch Zahlungen generierten Impact im Checkout und auf der Bestätigungsseite anzuzeigen.', 'woocommerce'),
                'default' => 'yes',
                'desc_tip' => true,
            ),
            'show_method_description' => array(
                'title' => __('Beschreibung anzeigen', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Zeige die Beschreibung dieser Zahlungsmethode beim Checkout an.', 'woocommerce'),
                'default' => 'no',
            ),
            'description' => array(
                'title' => __('Beschreibung', 'woocommerce'),
                'type' => 'textarea',
                'label' => __('Definiere die Beschreibung dieser Zahlungsmethode', 'woocommerce'),
                'description' => __(
                    'Verwende {{impact}} als Platzhalter, um den Umweltimpact dynamisch anzuzeigen und HTML-Tags zur Formatierung.',
                    'woocommerce'
                ),
                'default' => $defaultDescription,
                'desc_tip' => true,
            ),
            'as_redirect' => array(
                'title' => __('Weiterleitung', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Weiterleitung zum Checkout', 'woocommerce'),
                'description' => __('Aktiviere diese Option, wenn deine Kunden zum TapTree Checkout Formular weitergeleitet werden sollen, anstatt ein Popup zu öffnen.', 'woocommerce'),
                'default' => 'no',
                'desc_tip' => true,
            ),
        );
    }
}
