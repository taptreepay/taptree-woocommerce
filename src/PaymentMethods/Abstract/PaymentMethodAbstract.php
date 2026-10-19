<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\PaymentMethods\Abstract;

use TapTree\WooCommerce\PaymentMethods\Interface\PaymentMethodInterface;
use TapTree\WooCommerce\SDK\TemplateRenderer;
use TapTree\WooCommerce\Settings\ConsumerImpact;
use TapTree\WooCommerce\Settings\SettingsHelper;

abstract class PaymentMethodAbstract implements PaymentMethodInterface
{
    private const IMPACT_PLACEHOLDER_PATTERN = '/{{\s*[#^\/]?\s*impact\s*}}/';

    /** Former built-in checkout texts that must not survive in stored settings. */
    private const LEGACY_DESCRIPTIONS = [
        'Mit dieser Zahlungsart kostenlos und ohne Anmeldung Klimaschutzprojekte unterstützen. Für weitere Infos und zur Bezahlung, erfolgt eine Weiterleitung zum TapTree Payments Formular.',
        'Mit dieser Zahlungsart kostenlos und ohne Anmeldung Klimaschutzprojekte unterstützen. Für weitere Infos und zur Bezahlung, wird das sichere TapTree Payments Browserfenster geöffnet.',
        'Mit diesen Zahlungsarten kostenlos und ohne Anmeldung Klimaschutzprojekte unterstützen. Für weitere Infos und zur Bezahlung, erfolgt eine Weiterleitung zum TapTree Payments Formular.',
        'Mit diesen Zahlungsarten kostenlos und ohne Anmeldung Klimaschutzprojekte unterstützen. Für weitere Infos und zur Bezahlung, wird das sichere TapTree Payments Browserfenster geöffnet.',
    ];

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
        return '<img class="taptree-payment-method-logo" '
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

    protected function replacePlaceholders($text)
    {
        // {{impact}} is retired: the chooser never shows a figure, so a stored placeholder prints nothing.
        return TemplateRenderer::render($text, ['impact' => '']);
    }

    /**
     * Resets the stored checkout description to the current default when it
     * still contains the retired {{impact}} placeholder or equals a former
     * built-in text. Idempotent; every other stored setting is left untouched.
     */
    public function resetLegacyDescription(): void
    {
        $optionName = $this->settingsHelper->getGatewayId($this->getId()) . '_settings';
        $settings = get_option($optionName, false);
        if (!is_array($settings) || !isset($settings['description'])) {
            return;
        }

        $stored = trim((string) $settings['description']);
        $isLegacy = preg_match(self::IMPACT_PLACEHOLDER_PATTERN, $stored) === 1
            || in_array($stored, self::LEGACY_DESCRIPTIONS, true);
        if (!$isLegacy) {
            return;
        }

        $settings['description'] = $this->getProps()['default_description'];
        update_option($optionName, $settings, true);
    }

    /**
     * Sets the stored 'show_impact' setting to 'no'. A 'yes' saved by an earlier
     * version was the default of the retired CO₂ display, persisted on first load
     * without any merchant action, and is indistinguishable from a deliberate tick;
     * neither is consent to the project contribution, so both are reset. Idempotent;
     * every other stored setting is left untouched.
     */
    public function resetLegacyImpactOptIn(): void
    {
        $optionName = $this->settingsHelper->getGatewayId($this->getId()) . '_settings';
        $settings = get_option($optionName, false);
        if (!is_array($settings) || ($settings['show_impact'] ?? null) === 'no') {
            return;
        }

        $settings['show_impact'] = 'no';
        update_option($optionName, $settings, true);
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
                'description' => __('Gib den Titel der Zahlungsmethode ein, der im Checkout angezeigt wird.', 'woocommerce'),
                'default' => $defaultTitle,
                'desc_tip' => true,
            ),
            'show_impact' => array(
                'title' => __('Projektbeitrag', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Projektbeitrag je Zahlung anzeigen', 'woocommerce'),
                'description' => $this->getConsumerImpactFieldDescription(),
                'default' => 'no',
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
                'description' => __('Du kannst HTML-Tags zur Formatierung verwenden.', 'woocommerce'),
                'default' => $defaultDescription,
                'desc_tip' => true,
            ),
            'as_redirect' => array(
                'title' => __('Weiterleitung', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Weiterleitung zum Checkout', 'woocommerce'),
                'description' => __('Aktiviere diese Option, wenn der Checkout zum TapTree-Checkout-Formular weiterleiten soll, statt ein Popup zu öffnen.', 'woocommerce'),
                'default' => 'no',
                'desc_tip' => true,
            ),
        );
    }

    /**
     * Help text of the 'show_impact' field. It names the units that may be shown so
     * the admin screen stays truthful when the allow-list changes.
     */
    private function getConsumerImpactFieldDescription(): string
    {
        $description = __('Zeigt auf der Bestätigungsseite den Projektbeitrag je Zahlung an, sobald TapTree dafür eine Einheit liefert, die im Shop angezeigt werden darf.', 'woocommerce');

        if (!ConsumerImpact::hasAllowedUnits()) {
            return $description . ' ' . __('Derzeit gibt es keine solche Einheit; es wird nichts angezeigt.', 'woocommerce');
        }

        return $description . ' ' . sprintf(
            /* translators: Placeholder: comma-separated list of units */
            __('Zulässige Einheiten: %s.', 'woocommerce'),
            implode(', ', ConsumerImpact::allowedUnits())
        );
    }
}
