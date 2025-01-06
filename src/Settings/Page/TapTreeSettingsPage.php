<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace TapTree\WooCommerce\Settings\Page;

use TapTree\WooCommerce\Api\TapTreeApi;
use TapTree\WooCommerce\Settings\SettingsHelper;
use WC_Admin_Settings;
use WC_Settings_Page;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class TapTreeSettingsPage extends WC_Settings_Page
{
    public const FILTER_COMPONENTS_SETTINGS = 'taptree_settings';

    public $id;

    public $label;

    protected $api;

    protected $settingsHelper;

    protected $paymentGateways;

    protected $paymentMethods;

    private $needToRefresh;

    public function __construct(TapTreeApi $api, SettingsHelper $settingsHelper, array $paymentGateways, array $paymentMethods)
    {
        $this->id = 'taptree_settings';
        $this->label = __('TapTree-Einstellungen', 'woocommerce');
        $this->api = $api;
        $this->settingsHelper = $settingsHelper;
        $this->paymentGateways = $paymentGateways;
        $this->paymentMethods = $paymentMethods;
        $this->needToRefresh = false;



        add_action(
            'woocommerce_sections_' . $this->id,
            [$this, 'output_sections']
        );
        add_action('woocommerce_admin_field_api_key', [$this, 'generate_api_key_html']);
        add_action('woocommerce_settings_saved', [$this, 'toggle_need_to_refresh']);

        if ($_POST['refresh_payment_methods'] === 'yes') {
            $this->refresh_payment_methods();
        };

        parent::__construct();
    }

    public function output()
    {
        global $current_section;
        $settings = $this->get_settings($current_section);

        WC_Admin_Settings::output_fields($settings);
    }

    public function get_settings($current_section = '')
    {
        $globalTapTreeSettings = $this->getGlobalSettingsFields();

        $globalTapTreeSettings = apply_filters(
            self::FILTER_COMPONENTS_SETTINGS,
            $globalTapTreeSettings
        );

        return apply_filters(
            'woocommerce_get_settings_' . $this->id,
            $globalTapTreeSettings,
            $current_section
        );
    }

    public function refresh_payment_methods()
    {
        $values = [
            $this->settingsHelper->getSettingId('api_key_test') => get_option($this->settingsHelper->getSettingId('api_key_test')),
            $this->settingsHelper->getSettingId('api_key_live') => get_option($this->settingsHelper->getSettingId('api_key_live')),
            $this->settingsHelper->getSettingId('live_mode') => get_option($this->settingsHelper->getSettingId('live_mode')),
        ];

        $this->api->validateApiKeys($values);
    }


    public function getGlobalSettingsFields()
    {
        $availableMethodsIds =  $this->settingsHelper->getAvailablePaymentMethodsIds();

        $paymentMethodsSection = '';

        if (count($availableMethodsIds)) {
            $paymentMethodsSection =
                '<div style="background: #fff; padding: 15px;">' . '<div style="margin: 0 0 10px 0; font-weight: 600;">'
                . '</div>';

            $paymentMethodsImagesHTML = '';


            $paymentMethodsSection .=
                '<div style=" margin: 10px 0 10px 0; font-weight: bold;';

            $paymentMethodsSection .= (
                $this->needToRefresh ?
                'color: #d63638;">' . __('Deine Einstellungen haben sich geändert.<br>Bitte aktualisiere hier, um die Änderung zu sehen:', 'woocommerce') :
                '">' . __('Die Zahlungseinstellungen in deinem TapTree-Dashboard haben sich geändert?<br>Bitte aktualisiere hier, um die Änderung und weitere Zahlmethoden zu sehen:')
            ) . '&emsp;';


            $paymentMethodsSection .= '<button id="hidden_save" name="save" type="submit" value="Änderungen speichern" hidden style="display: none"></button>'
                . '<button name="refresh_payment_methods" '
                . 'value="yes" '
                . 'type="submit" '
                . 'style="font-weight: 600;'
                . 'cursor: pointer;'
                . 'padding: 2px 6px 2px 6px;'
                . 'color: #2271b1;'
                . 'background: #2271b122;'
                . 'text-decoration: none;'
                . 'border: solid 1px #2271b1;'
                . 'border-radius: 4px;">'
                . __('aktualisieren', 'woocommerce')
                . '</button>'
                . '</div><p>&nbsp;</p>';

            if (!$this->needToRefresh) {
                $count = 0;
                foreach ($this->paymentMethods as $id => $paymentMethod) {
                    $gridItemContent =
                        '<div class="taptree-flexbox" style="column-gap: 10px;">'
                        . str_replace(
                            'margin: -2px 0 0 0;',
                            'margin: 0;',
                            $paymentMethod->getLogoHTML()
                        )
                        . $paymentMethod->getProp('default_title')
                        . '</div>';
                    if (array_key_exists($id, $this->paymentGateways)) {
                        $gridItemContent .=
                            '<a href="' . $this->settingsHelper->getTapTreeGatewaySettingsUrl($this->paymentGateways[$id]->id) . '" '
                            . 'style="font-weight: 600;'
                            . 'padding: 2px 15px 2px 15px;'
                            . 'color: #00a32a;'
                            . 'background: #00a32a22;'
                            . 'text-decoration: none;'
                            . 'border: solid 1px #00a32a;'
                            . 'border-radius: 4px;">'
                            . __('verwalten', 'woocommerce')
                            . '</a>';
                    } else {
                        $gridItemContent .=
                            '<a href="https://my.taptree.org" '
                            . 'style="padding: 2px 4px 2px 4px;'
                            . 'color: #aaa;'
                            . 'text-decoration: none;'
                            . 'border: solid 1px #aaa;'
                            . 'border-radius: 4px;">'
                            . __('aktivieren', 'woocommerce')
                            . '</a>';
                    }

                    $gridItemHTML =
                        '<div class="taptree-responsive-grid-item">'
                        . $gridItemContent
                        . '</div>';

                    $paymentMethodsImagesHTML .= $gridItemHTML;

                    $count++;
                }

                $paymentMethodsImagesHTML .= '</div>';
            }

            if ($paymentMethodsImagesHTML) {
                $paymentMethodsSection .=  '<div class="taptree-responsive-grid-container">' . $paymentMethodsImagesHTML . '</div><br><br></div>';
            } else {
                $paymentMethodsSection .= '</div><br><br>';
            }
        }

        $introText = __(
            '<p>Abwickeln von Zahlungen – immer sicher mit der Hosted-Checkout-Lösung von TapTree.<br>',
            'woocommerce'
        );

        $introText .= '' . __(
            'Falls du noch kein TapTree-Konto hast, melde dich jetzt ',
            'woocommerce'
        ) . '<a href="https://taptree.org/mitmachen">' . __(
            'hier',
            'woocommerce'
        ) . '</a>' . __(
            ' an und beginne noch heute, Zahlungen über dein TapTree-Konto zu empfangen.',
            'woocommerce'
        ) . '</p>';

        $globalSettingsFields = [
            [
                'id' => $this->settingsHelper->getSettingId('title'),
                'title' => __('TapTree-Einstellungen', 'woocommerce'),
                'type' => 'title',
                'desc' => '<div style="padding:0 0 0 10px"><p>' . $introText . '</p>' . $paymentMethodsSection
                    . '<p>&nbsp;</p><h3>' . __('Zugangsdaten', 'woocommerce') . '</h3>'
                    . '<p>' . __('Diese Einstellungen gelten für alle in deinem TapTree-Konto aktivierten Zahlungsmethoden.', 'woocommerce') . '</p>',
            ],
            [
                'id'       => $this->settingsHelper->getSettingId('live_mode'),
                'title'    => __('Live-Modus', 'woocommerce'),
                'type'     => 'checkbox',
                'label'    => __('Live-Modus', 'woocommerce'),
                'desc'     => wp_kses_post(
                    '<br><b style="color: #00a32a;">Mit Häckchen</b>: Kostenpflichtige echte Zahlungen mit Auszahlungen auf dein Bankkonto.<br>'
                        . '<b style="color: #d63638;">Ohne Häckchen</b>: Kostenlose Testzahlungen ohne Auszahlungen auf dein Bankkonto.<br>'
                        . '<b>Achtung</b>: Live-Zahlungen werden nur verarbeitet, wenn du einen gültigen TapTree-Live-API-Schlüssel eingetragen hast.<br>'
                ),
                'default'  => 'yes'
            ],
            [
                'id'       => $this->settingsHelper->getSettingId('api_key_live'),
                'title'    => __('Live-API-Schlüssel', 'woocommerce'),
                'type'     => 'api_key',
                'input_type' => 'password',
                'desc'     => wp_kses_post(
                    'Füge hier deinen <a href="https://my.taptree.org" target="_blank">TapTree-Live-API-Schlüssel</a> ein.<br>Er beginnt mit "live_".'

                ),
            ],
            [
                'id' => $this->settingsHelper->getSettingId('api_key_test'),
                'title' => __('Test-API-Schlüssel', 'woocommerce'),
                'type' => 'api_key',
                'input_type' => 'password',
                'desc' => __('Füge hier deinen <a href="https://my.taptree.org" target="_blank">TapTree-Test-API-Schlüssel</a> ein.<br>Er beginnt mit "test_".', 'woocommerce'),
            ],


            [
                'id'       => $this->settingsHelper->getSettingId('webhook_secret'),
                'title'    => __('Webhook Secret', 'woocommerce'),
                'type'     => 'password',
                'desc'     => wp_kses_post(
                    'Wenn du kein Webhook Secret hinterlegst, muss das Plugin einen zusätzlichen API-Aufruf durchführen, '
                        . 'was den Checkout-Vorgang geringfügig verzögern kann.'
                        . '<br>Du findest dein Webhook Secret direkt unter den API-Keys in deinem TapTree-Dashboard.'
                ),
            ],
            [
                'id' => $this->settingsHelper->getSettingId('debug'),
                'title' => __('Debug-Logs', 'woocommerce'),
                'type' => 'checkbox',
                'desc' => __('Speichere Log-Events des Plugins zur Fehleranalyse.', 'woocommerce'),
            ],
            [
                'id' => '',
                'type' => 'sectionend'
            ],
        ];

        return $globalSettingsFields;
    }

    protected function getPaymentGatewaySettingsUrl($paymentGatewayId)
    {
        return admin_url(
            'admin.php?page=wc-settings&tab=checkout&section=' . sanitize_title(strtolower($paymentGatewayId))
        );
    }

    public function toggle_need_to_refresh()
    {
        $this->needToRefresh = !$this->needToRefresh;
    }

    public function generate_api_key_html($props)
    {
        $key = $props['id'];

?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php
                echo '<label for="' . $key . '">' . esc_html($props['title']) . '</label>';
                ?>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo esc_html($props['title']); ?></span></legend>
                    <div style="display:flex">
                        <?php
                        $apiKey = $props['value'];

                        echo '<input class="input-text regular-input " type="' . esc_attr($props['input_type']) . '" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" style="" value="' . esc_attr($apiKey) . '" placeholder="">';

                        $verified_indicator = '<svg  style="margin-right: 5px;" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true" viewBox="0 -8 9 14" width="11px" height="17px"><path fill="white" d="m0 0 3 3 6-6-1-1-5 5-2-2-1 1" /></svg>';
                        $invalid_indicator = '<svg  style="margin-right: 5px;" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true" viewBox="0 -10 7 14" width="8.5" height="17px"><path fill="white" d="m0 0 1 1 6-6-1-1-6 6m0-5 6 6 1-1-6-6-1 1" /></svg>';


                        $is_key_valid = null;
                        if ($key === $this->settingsHelper->getSettingId('api_key_test')) {
                            $is_key_valid = boolval(get_option($this->settingsHelper->getSettingId('is_test_key_valid')));
                        } elseif ($key === $this->settingsHelper->getSettingId('api_key_live')) {
                            $is_key_valid = boolval(get_option($this->settingsHelper->getSettingId('is_live_key_valid')));
                        }

                        $color = '#ddd';
                        $validity_indicator = '';
                        $validity_label = '';
                        if ($is_key_valid === true) {
                            $color = '#00a32a';
                            $validity_indicator = $verified_indicator;
                            $validity_label = "verifiziert";
                        } elseif ($apiKey && strlen($apiKey) !== 0 && $is_key_valid === false) {
                            $color = '#d63638';
                            $validity_indicator = $invalid_indicator;
                            $validity_label = "nicht valide";
                        }

                        if ($validity_label) {
                            echo '<div style="
                                font-weight: 500;
                                font-size: small;
                                background-color: ' . esc_attr($color) . ';
                                border: none;
                                color: white;
                                padding: 3px 10px;
                                text-align: center;
                                text-decoration: none;
                                display: flex;
                                margin: auto 0 auto 10px;
                                border-radius: 16px;">' . wp_kses_post($validity_indicator) . '<span>' . esc_html($validity_label) . '</span></div>';
                        }
                        ?>
                    </div>
                    <p class="description"><?php echo wp_kses_post($props['desc']); ?></p>
                </fieldset>
            </td>
        </tr>
<?php
    }

    public function save()
    {
        global $current_section;

        $settings = $this->get_settings($current_section);
        $values = $_POST;

        $validatedValues = $this->api->validateApiKeys($values);

        WC_Admin_Settings::save_fields($settings, $validatedValues);
    }

    public function get_sections()
    {
        $sections = [
            '' => __('General', 'woocommerce'),
        ];

        return apply_filters(
            'woocommerce_get_sections_' . $this->id,
            $sections
        );
    }
}
