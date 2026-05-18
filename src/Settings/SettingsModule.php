<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace TapTree\WooCommerce\Settings;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use TapTree\WooCommerce\Api\TapTreeApi;
use TapTree\WooCommerce\Settings\SettingsHelper;
use TapTree\WooCommerce\Settings\Page\TapTreeSettingsPage;
use TapTree\WooCommerce\Notice\AdminNotice;
use Psr\Container\ContainerInterface;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class SettingsModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    /**
     * @var mixed
     */
    protected $settingsProvider;
    /**
     * @var mixed
     */
    protected $pluginId;

    public function services(): array
    {
        return [
            'settings.settings_helper' => static function (ContainerInterface $container): SettingsHelper {
                $pluginId = $container->get('shared.plugin_id');
                $pluginUrl = $container->get('shared.plugin_url');

                return new SettingsHelper(
                    $pluginId,
                    $pluginUrl,
                );
            },
        ];
    }

    public function run(ContainerInterface $container): bool
    {
        $pluginBasename = $container->get('shared.plugin_file');
        $this->settingsHelper = $container->get('settings.settings_helper');
        assert($this->settingsHelper instanceof SettingsHelper);
        $this->api = $container->get('Api.taptree_api');
        assert($this->api instanceof TapTreeApi);
        $pluginVersion = $container->get('shared.plugin_version');
        wp_enqueue_style('taptree-common');

        add_filter('plugin_action_links_' . $pluginBasename, [$this, 'addSettingsLinkToPluginPage']);

        $this->isTestModeNoticePrinted = false;
        add_action('woocommerce_settings_saved', function () {
            $isNoticePrinted = $this->needToShowTestModeNotice();
            if ($isNoticePrinted) {
                $this->isTestModeNoticePrinted = true;
            }
        });
        add_action('admin_init', function () use ($pluginVersion) {
            $this->maybeShowV2UpgradeNotice($pluginVersion);
            if ($this->isTestModeNoticePrinted) {
                return;
            }
            $this->needToShowTestModeNotice();
        });

        $paymentGateways = $container->get('gateway.instances');
        $paymentMethods = $container->get('gateway.payment_methods');
        $this->initGlobalTapTreeSettingsPage($paymentGateways, $paymentMethods);

        return true;
    }

    public function addSettingsLinkToPluginPage($links)
    {
        $link = $this->settingsHelper->getGlobalTapTreeSettingsUrl();

        $link_html = '<a href="' . esc_url($link) . '">' . __('Settings', 'woocommerce') . '</a>';

        array_unshift($links, $link_html);

        return $links;
    }

    /**
     * One-shot non-blocking admin notice prompting merchants on 2.1.3 with at
     * least one legacy (non-tt_) key in storage to rotate to a v2 key. The
     * notice is shown once per plugin version — we stamp the version into a
     * dedicated option so re-running the hook is idempotent.
     */
    public function maybeShowV2UpgradeNotice(string $pluginVersion)
    {
        if (!is_admin()) {
            return;
        }
        $stampOpt = $this->settingsHelper->getSettingId('v2_upgrade_notice_version');
        $stamped = get_option($stampOpt);
        if ($stamped === $pluginVersion) {
            return;
        }

        $live = (string) get_option($this->settingsHelper->getSettingId('api_key_live'));
        $test = (string) get_option($this->settingsHelper->getSettingId('api_key_test'));

        $hasLegacy = false;
        foreach ([$live, $test] as $key) {
            if ($key === '') {
                continue;
            }
            $parsed = $this->settingsHelper->classifyKey($key);
            if ($parsed['format'] === 'legacy') {
                $hasLegacy = true;
                break;
            }
        }

        if (!$hasLegacy) {
            update_option($stampOpt, $pluginVersion);
            return;
        }

        $notice = new AdminNotice();
        $message = sprintf(
            esc_html__(
                '%1$sTapTree Payments for WooCommerce%2$s — Neuer API-Key-Format (v2) verfügbar. Wir empfehlen, deinen Schlüssel im %3$sTapTree-Dashboard%4$s zu erneuern. Bestehende Schlüssel funktionieren weiterhin.',
                'taptree-payments-for-woocommerce'
            ),
            '<strong>',
            '</strong>',
            '<a href="https://my.taptree.org/" target="_blank" rel="noopener noreferrer">',
            '</a>'
        );
        $notice->addNotice('notice-info', $message);

        update_option($stampOpt, $pluginVersion);
    }

    public function needToShowTestModeNotice()
    {
        $liveMode = get_option($this->settingsHelper->getSettingId('live_mode'));
        $showNotice = !$liveMode || $liveMode === 'no';
        if (!$showNotice) {
            return false;
        }
        $testModeNotice = new AdminNotice();
        $noticeHtml = sprintf(
            esc_html__(
                '%1$sTapTree Payments for WooCommerce%2$s Test mode is active, %3$s enable live mode%4$s before going into production.',
                'taptree-payments-for-woocommerce'
            ),
            '<strong>',
            '</strong>',
            '<a href="' . esc_url(
                admin_url('admin.php?page=wc-settings&tab=taptree_settings')
            ) . '">',
            '</a>'
        );
        $testModeNotice->addNotice('notice-error', $noticeHtml);
        return true;
    }

    protected function initGlobalTapTreeSettingsPage($paymentGateways, $paymentMethods)
    {
        add_filter(
            'woocommerce_get_settings_pages',
            function ($settings) use ($paymentGateways, $paymentMethods) {
                $settings[] = new TapTreeSettingsPage(
                    $this->api,
                    $this->settingsHelper,
                    $paymentGateways,
                    $paymentMethods
                );

                return $settings;
            }
        );
    }
}
