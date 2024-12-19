<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace TapTree\WooCommerce\Gateway;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use TapTree\WooCommerce\SDK\HttpResponse;
use TapTree\WooCommerce\Notice\AdminNotice;
use TapTree\WooCommerce\Payment\PaymentService;
use TapTree\WooCommerce\Shared\SharedDataDictionary;
use TapTree\WooCommerce\Api\TapTreeApi;
use TapTree\WooCommerce\Settings\SettingsHelper;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;
use WC_Order;
use RuntimeException;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class GatewayModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    /**
     * @var mixed
     */
    protected $gatewayClassnames;
    /**
     * @var mixed
     */
    protected $pluginId;

    protected $logger;

    public function services(): array
    {
        return [
            'gateway.classnames' => static function (): array {
                return SharedDataDictionary::GATEWAY_CLASSNAMES;
            },
            'gateway.instances' => function (ContainerInterface $container): array {
                return $this->instantiatePaymentMethodGateways($container);
            },
            'gateway.payment_methods' => static function (ContainerInterface $container): array {
                return (new self())->instantiatePaymentMethods($container);
            },
            PaymentService::class => static function (ContainerInterface $container): PaymentService {
                $HttpResponseService = $container->get('SDK.HttpResponse');
                assert($HttpResponseService instanceof HttpResponse);
                $settingsHelper = $container->get('settings.settings_helper');
                assert($settingsHelper instanceof SettingsHelper);
                $logger = $container->get(Logger::class);
                assert($logger instanceof Logger);
                $pluginId = $container->get('shared.plugin_id');
                return new PaymentService($HttpResponseService, $settingsHelper, $logger, $pluginId);
            },
        ];
    }



    public function run(ContainerInterface $container): bool
    {
        $this->pluginId = $container->get('shared.plugin_id');
        $this->gatewayClassnames = $container->get('gateway.classnames');
        $this->logger = $container->get(Logger::class);
        assert($this->logger instanceof Logger);

        add_filter($this->pluginId . '_retrieve_payment_gateways', function () {
            return $this->gatewayClassnames;
        });

        add_filter('woocommerce_payment_gateways', function ($gateways) use ($container) {
            $taptreeGateways = $container->get('gateway.instances');
            return array_merge($gateways, $taptreeGateways);
        });

        // Listen to return URL call
        add_action('template_redirect', array($this, 'tapTreeReturnRedirect'));

        // Inject script on thank-you or order-pay pages
        add_action('template_redirect', function () {
            if (is_order_received_page() || is_checkout_pay_page()) {
                $this->injectModalScript();
            }
        });


        return true;
    }


    protected function instantiatePaymentMethods($container): array
    {
        $settingsHelper = $container->get('settings.settings_helper');
        assert($settingsHelper instanceof SettingsHelper);

        $paymentMethods = [];
        $allPaymentMethodsIds = ['card', 'paypal', 'eps', 'wechat', 'ideal', 'payconiq', 'paysafecard', 'przelewy', 'riverty', 'sepa_direct_debit', 'trustly', 'in3', 'blik'];
        foreach ($allPaymentMethodsIds as $paymentMethodId) {
            $paymentMethods[$paymentMethodId] = $this->buildPaymentMethod(
                $paymentMethodId,
                $settingsHelper
            );
        }

        return $paymentMethods;
    }


    public function buildPaymentMethod(
        string $id,
        SettingsHelper $settingsHelper,
    ) {
        $paymentMethodClassName = 'TapTree\\WooCommerce\\PaymentMethods\\' . ucfirst($id);
        if (class_exists($paymentMethodClassName)) {
            $paymentMethod = new $paymentMethodClassName($settingsHelper);
            return $paymentMethod;
        }
    }


    public function instantiatePaymentMethodGateways(ContainerInterface $container): array
    {
        $settingsHelper = $container->get('settings.settings_helper');
        assert($settingsHelper instanceof SettingsHelper);
        $logger = $container->get(Logger::class);
        assert($logger instanceof Logger);
        $notice = $container->get(AdminNotice::class);
        assert($notice instanceof AdminNotice);
        $HttpResponseService = $container->get('SDK.HttpResponse');
        assert($HttpResponseService instanceof HttpResponse);
        $api = $container->get('Api.taptree_api');
        assert($api instanceof TapTreeApi);
        $paymentMethods = $container->get('gateway.payment_methods');
        $pluginId = $container->get('shared.plugin_id');
        $paymentService = $container->get(PaymentService::class);
        assert($paymentService instanceof PaymentService);
        //$logger->debug(__METHOD__);
        wp_enqueue_style('taptree-style-overrides');

        $availablePaymentMethodsIds = $settingsHelper->getAvailablePaymentMethodsIds();
        $gateways = [];
        foreach ($paymentMethods as $id => $paymentMethod) {
            if (in_array($id, $availablePaymentMethodsIds) && !is_null($paymentMethod)) {
                $gateways[$id] = new TapTreePaymentGateway(
                    $paymentMethod,
                    $settingsHelper,
                    $api,
                    $logger,
                    $notice,
                    $HttpResponseService,
                    $paymentService,
                    $pluginId
                );
            }
        }

        return $gateways;
    }

    protected function injectModalScript(): void
    {
        $from_modal = isset($_GET['taptree_from_modal']) ? sanitize_text_field($_GET['taptree_from_modal']) : '';
        $redirect_url = isset($_GET['taptree_redirect_url']) ? esc_url_raw(urldecode($_GET['taptree_redirect_url'])) : '';

        if ($from_modal === '1') {
            add_action('wp_footer', function () use ($redirect_url) {
                echo "
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    try {
                        // Notify the parent about the redirection
                        if (window.opener) {
                            const eventDetail = {
                                type: 'redirected_to_origin',
                                redirectUrl: '{$redirect_url}'
                            };

                            const taptreeEvent = new CustomEvent('taptree_event', { detail: eventDetail });
                            window.opener.dispatchEvent(taptreeEvent);

                            console.log('Notified parent window about redirection:', eventDetail);
                        }

                        // Ensures the window.close() happens asynchronously after notifying the parent window. This avoids issues with synchronous operations conflicting with the popup's lifecycle.
                        setTimeout(() => window.close(), 0);
                    } catch (err) {
                        // no logging as we allow cross-origin windows
                    }
                });
            </script>
            ";
            });
        }
    }

    public function tapTreeReturnRedirect()
    {
        if (!isset($_GET['filter_flag']))
            return;

        $filterFlag = sanitize_text_field(wp_unslash($_GET['filter_flag']));
        if ($filterFlag !== 'onTapTreeReturn') return;

        $this->logger->debug("");
        $this->logger->debug("");
        $this->logger->debug(__METHOD__ . ": need to handle return from TapTree.");

        try {
            // strange php that this is in scope outside of the try block
            $order = self::orderByRequest();
        } catch (\RuntimeException $exc) {
            $this->httpResponse->setHttpResponseCode($exc->getCode());
            $this->logger->debug(__METHOD__ . ":  {$exc->getMessage()}");
            return;
        } catch (RuntimeException $exc) {
            $this->httpResponse->setHttpResponseCode($exc->getCode());
            $this->logger->debug(__METHOD__ . ":  {$exc->getMessage()}");
            return;
        }

        $gateway = wc_get_payment_gateway_by_order($order);
        $orderId = $order->get_id();

        if (!$gateway) {
            $gatewayName = $order->get_payment_method();

            $this->httpResponse->setHttpResponseCode(404);
            $this->logger->debug(
                __METHOD__ . ":  Could not find gateway {$gatewayName} for order {$orderId}."
            );
            return;
        }

        if (!($gateway instanceof TapTreePaymentGateway)) {
            $this->httpResponse->setHttpResponseCode(400);
            $this->logger->debug(__METHOD__ . ": Invalid gateway {get_class($gateway)} for this plugin. Order {$orderId}.");
            return;
        }

        $return_url = $gateway->getOrderRedirectUrl($order);

        // Add utm_nooverride and taptree_from_modal query strings
        $return_url = add_query_arg([
            'utm_nooverride' => 1,
            'taptree_from_modal' => 1,
            'taptree_redirect_url' => urlencode($return_url),
        ], $return_url);

        $this->logger->debug(__METHOD__ . ": Redirect url on return order {$gateway->id}, order {$orderId}: {$return_url}");

        wp_safe_redirect(esc_url_raw($return_url));
        die;
    }


    /**
     * Returns the order from the Request first by Id, if not by Key
     *
     * @return bool|WC_Order
     */
    public function orderByRequest()
    {
        $orderId = filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
        $key = sanitize_text_field(wp_unslash($_GET['key'])) ?: null;
        $order = wc_get_order($orderId);

        if (!$order) {
            $order = wc_get_order(wc_get_order_id_by_order_key($key));
        }

        if (!$order) {
            throw new RuntimeException(
                "Could not find order by order Id {$orderId}",
                404
            );
        }

        if (!$order->key_is_valid($key)) {
            throw new RuntimeException(
                "Invalid key given. Key {$key} does not match the order id: {$orderId}",
                401
            );
        }

        return $order;
    }
}
