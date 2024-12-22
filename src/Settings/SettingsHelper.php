<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace TapTree\WooCommerce\Settings;

use stdClass;
use WC_Admin_Settings;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class SettingsHelper
{
    protected $pluginId;
    protected $pluginUrl;
    protected $globalTapTreeSettingsUrl;

    public function __construct(
        $pluginId,
        $pluginUrl,
    ) {
        $this->pluginId = $pluginId;
        $this->pluginUrl = $pluginUrl;
        $this->globalTapTreeSettingsUrl = admin_url(
            'admin.php?page=wc-settings&tab=taptree_settings'
        );
    }

    public function getPluginUrl(): string
    {
        return $this->pluginUrl;
    }

    public function getGlobalTapTreeSettingsUrl(): string
    {
        return $this->globalTapTreeSettingsUrl;
    }

    public function getTapTreeGatewaySettingsUrl(string $gatewayId): string
    {
        return admin_url(
            'admin.php?page=wc-settings&tab=checkout&section=' . sanitize_title($gatewayId)
        );
    }

    public function getSettingId(string $setting)
    {
        $setting_id = $this->pluginId . '_' . trim($setting);
        $setting_id_length = strlen($setting_id);

        $max_option_name_length = 191;

        if ($setting_id_length > $max_option_name_length) {
            trigger_error(sprintf('Setting id %s (%s) to long for database column wp_options.option_name which is varchar(%s).', esc_html($setting_id), esc_html($setting_id_length), esc_html($max_option_name_length)), E_USER_WARNING);
        }

        return $setting_id;
    }

    public function getGatewayId(string $paymentMethodId)
    {
        $payment_gateway_id = 'taptree_wc_gateway' . '_' . trim($paymentMethodId);
        $payment_gateway_id_length = strlen($payment_gateway_id);

        $max_option_name_length = 191;

        if ($payment_gateway_id_length > $max_option_name_length) {
            trigger_error(sprintf('Payment gateway id %s (%s) to long for database column wp_options.option_name which is varchar(%s).', esc_html($payment_gateway_id), esc_html($payment_gateway_id_length), esc_html($max_option_name_length)), E_USER_WARNING);
        }

        return $payment_gateway_id;
    }

    public function addUserNotification(string $message, string $type = 'message')
    {
        if ($message === '') {
            return;
        }

        if ($type === 'message') {
            WC_Admin_Settings::add_message(__($message, 'woocommerce'));
        } elseif ($type === 'error') {
            WC_Admin_Settings::add_error(__($message, 'woocommerce'));
        }
    }

    public function isApiKeyValid($acceptorData, $keyType = 'test')
    {
        if (!$acceptorData->id) {
            return false;
        }

        if ($keyType === 'test' && $acceptorData->mode === 'test') {
            return true;
        }

        if ($keyType === 'live' && $acceptorData->mode === 'live') {
            return true;
        }


        return false;
    }

    public function getAvailablePaymentMethodsIds(): array
    {
        return get_option($this->getSettingId('available_payment_methods')) ?: [];
    }

    public function getApiKey()
    {
        $live_mode = get_option($this->getSettingId('live_mode'));
        $is_test_key_valid = get_option($this->getSettingId('is_test_key_valid'));
        $is_live_key_valid = get_option($this->getSettingId('is_live_key_valid'));

        if ($live_mode === 'yes' && $is_live_key_valid) {
            return get_option($this->getSettingId('api_key_live'));
        }

        if ($is_test_key_valid) {
            return get_option($this->getSettingId('api_key_test'));
        }

        return '';
    }

    public function isDebugEnabled()
    {
        return get_option($this->getSettingId('debug'), 'yes') === 'yes';
    }

    public function sanitizeRecursively(mixed $data): mixed
    {
        switch (gettype($data)) {
            case 'array':
                $sanitizedData = array();
                foreach ($data as $key => $value) {
                    $sanitizedData[$key] = $this->sanitizeRecursively($value);
                }
                return $sanitizedData;
            case 'object':
                $sanitizedData = new stdClass();
                foreach ($data as $key => $value) {
                    $sanitizedData->$key = $this->sanitizeRecursively($value);
                }
                return $sanitizedData;
            case 'string':
                return sanitize_text_field($data);
            case 'boolean':
                return $data;
            case 'integer':
                return $data;
            case 'double':
                return $data;
            case 'resource':
                return null;
            case 'unknown type':
                return null;
            default:
                return null;
        }
    }
}
