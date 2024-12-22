<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\Gateway;

use WC_Payment_Gateway;
use TapTree\WooCommerce\Api\TapTreeApi;
use TapTree\WooCommerce\Notice\NoticeInterface;
use TapTree\WooCommerce\SDK\HttpResponse;
use TapTree\WooCommerce\Payment\PaymentService;
use Psr\Log\LoggerInterface as Logger;
use WC_Order;
use WP_Error;
use Exception;
use TapTree\WooCommerce\PaymentMethods\Interface\PaymentMethodInterface;
use TapTree\WooCommerce\Settings\SettingsHelper;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class TapTreePaymentGateway extends WC_Payment_Gateway
{

    /**
     * WooCommerce default order statuses
     */
    public const WOO_STATUS_PENDING = 'pending';
    public const WOO_STATUS_PROCESSING = 'processing';
    public const WOO_STATUS_ON_HOLD = 'on-hold';
    public const WOO_STATUS_COMPLETED = 'completed';
    public const WOO_STATUS_CANCELLED = 'cancelled';
    public const WOO_STATUS_FAILED = 'failed';
    public const WOO_STATUS_REFUNDED = 'refunded';

    public const TAP_STATUS_AUTHORIZED = 'authorized';
    public const TAP_STATUS_PAID = 'paid';
    public const TAP_STATUS_CANCELED = 'canceled';
    public const TAP_STATUS_FAILED = 'failed';
    public const TAP_STATUS_PENDING = 'pending';
    public const TAP_STATUS_EXPIRED = 'expired';
    public const TAP_STATUS_PARTIALLY_CAPTURED = 'partially_captured';

    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var NoticeInterface
     */
    protected $notice;

    /**
     * @var HttpResponse
     */
    protected $httpResponse;

    /**
     * @var PaymentService
     */
    protected $paymentService;

    protected $standardDescription;

    public $webhookSlug;

    protected $impact;

    /**
     * @var bool
     */
    public static $alreadyDisplayedInstructions = false;

    public function __construct(
        PaymentMethodInterface $paymentMethod,
        SettingsHelper $settingsHelper,
        TapTreeApi $api,
        Logger $logger,
        NoticeInterface $notice,
        HttpResponse $httpResponse,
        PaymentService $paymentService,
        string $pluginId
    ) {
        //$this->icon                = ''; //'https://taptree.org/doc/TapTree_Logo_Square.png';
        $this->id = $settingsHelper->getGatewayId($paymentMethod->getId());
        $this->webhookSlug = $this->id . '_notification';
        $this->paymentMethod = $paymentMethod;
        $this->settingsHelper = $settingsHelper;
        $this->api = $api;
        $this->logger = $logger;
        $this->notice = $notice;
        $this->httpResponse = $httpResponse;
        $this->pluginId = $pluginId;
        $this->paymentService = $paymentService;
        $this->supports            = $this->paymentMethod->getProp('supports');
        // No plugin id, gateway id is unique enough
        $this->plugin_id = '';
        $this->has_fields          = $this->paymentMethod->getProp('has_fields');
        // Todo move this if we have more methods than just the hosted checkout
        $this->method_title        = 'TapTree | ' . $this->paymentMethod->getProp('default_title');
        $this->method_description  = $this->paymentMethod->getProp('settings_description');
        $this->standardDescription = __('Mit dieser Zahlungsart kostenlos und ohne Anmeldung Klimaschutzprojekte unterstützen. Für weitere Infos und zur Bezahlung, erfolgt eine Weiterleitung zum TapTree Payments Formular.');

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->paymentMethod->getProp('enabled');
        $this->as_redirect = $this->paymentMethod->getProp('as_redirect');
        $this->title = $this->paymentMethod->getProp('title');
        $this->show_impact = $this->paymentMethod->getProp('show_impact');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        if ($this->isTapTreeAvailable() && $this->enabled === 'yes') {

            //$this->logger->debug(__METHOD__ . " | " . $this->title . " | " . $this->id . " | " . $this->pluginId);
            $this->paymentService->setGateway($this, $this->api);

            if ($this->as_redirect === 'no') {
                $this->standardDescription = __('Mit dieser Zahlungsart kostenlos und ohne Anmeldung Klimaschutzprojekte unterstützen. Für weitere Infos und zur Bezahlung, wird das sichere TapTree Payments Browserfenster geöffnet.');
                add_action('wp_ajax_taptree_custom_pay_for_order', [$this, 'processPaymentFromPayForOrder']); // For logged-in users
                add_action('wp_ajax_nopriv_taptree_custom_pay_for_order', [$this, 'processPaymentFromPayForOrder']); // For guests
                add_action('wp_enqueue_scripts', [$this, 'initModal']); // Load modal script and enqueue data
            }

            if ($this->paymentMethod->getProp('show_method_description') === 'yes') {
                $this->description = $this->set_payment_description();
            }

            // Hook for each taptree gateway on the order-pay page
            add_action('before_woocommerce_pay_form', [$this, 'handleOrderPayPage']);


            if (!has_action('woocommerce_thankyou_' . $this->id)) {
                add_action(
                    'woocommerce_thankyou_' . $this->id,
                    [$this, 'thankyou_page']
                );
            }

            if ($this->show_impact === 'yes') {
                add_action('woocommerce_after_calculate_totals', array($this, 'action_cart_calculate_totals'));
            }

            add_action('woocommerce_api_' . $this->webhookSlug, array($this->paymentService, 'onPaymentGatewayWebhookCalled'));
        } else {
            $this->enabled = 'no';
        }
    }


    public function getReturnUrl($order, $returnUrl)
    {
        $returnUrl = untrailingslashit($returnUrl);
        $returnUrl = $this->asciiDomainName($returnUrl);
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();

        $returnUrl = $this->appendOrderArgumentsToUrl(
            $orderId,
            $orderKey,
            $returnUrl,
            'onTapTreeReturn'
        );
        $returnUrl = untrailingslashit($returnUrl);
        $this->logger->debug(" Order {$orderId} returnUrl: {$returnUrl}", [true]);

        return apply_filters($this->pluginId . '_return_url', $returnUrl, $order);
    }

    public function getWebhookUrl($order)
    {
        $webhookUrl = WC()->api_request_url($this->webhookSlug);
        $webhookUrl = $this->asciiDomainName($webhookUrl);
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $webhookUrl = $this->appendOrderArgumentsToUrl(
            $orderId,
            $orderKey,
            $webhookUrl
        );
        $webhookUrl = untrailingslashit($webhookUrl);

        $this->logger->debug(" Order {$orderId} webhookUrl: {$webhookUrl}", [true]);

        return apply_filters($this->pluginId . '_webhook_url', $webhookUrl, $order);
    }

    /**
     * @param $order_id
     * @param $order_key
     * @param $webhook_url
     * @param string $filterFlag
     *
     * @return string
     */
    protected function appendOrderArgumentsToUrl($order_id, $order_key, $webhook_url, $filterFlag = '')
    {
        $webhook_url = add_query_arg(
            [
                'order_id' => $order_id,
                'key' => $order_key,
                'filter_flag' => $filterFlag,
            ],
            $webhook_url
        );
        return esc_url_raw($webhook_url);
    }

    /**
     * @param $url
     *
     * @return string
     */
    protected function asciiDomainName($url): string
    {
        $parsed = parse_url($url);
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : '';
        $domain = isset($parsed['host']) ? $parsed['host'] : false;
        $query = isset($parsed['query']) ? $parsed['query'] : '';
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        if (!$domain) {
            return $url;
        }

        if (function_exists('idn_to_ascii')) {
            $domain = $this->idnEncodeDomain($domain);
            $url = $scheme . "://" . $domain . $path . '?' . $query;
        }

        return $url;
    }

    /**
     * @param $domain
     * @return false|mixed|string
     */
    protected function idnEncodeDomain($domain)
    {
        if (
            defined('IDNA_NONTRANSITIONAL_TO_ASCII')
            && defined(
                'INTL_IDNA_VARIANT_UTS46'
            )
        ) {
            $domain = idn_to_ascii(
                $domain,
                IDNA_NONTRANSITIONAL_TO_ASCII,
                INTL_IDNA_VARIANT_UTS46
            ) ? idn_to_ascii(
                $domain,
                IDNA_NONTRANSITIONAL_TO_ASCII,
                INTL_IDNA_VARIANT_UTS46
            ) : $domain;
        } else {
            $domain = idn_to_ascii($domain) ? idn_to_ascii($domain) : $domain;
        }
        return $domain;
    }

    public function setPaymentMethodIcon($impactTitle = null)
    {
        $defaultIcon = PaymentMethodImageBuilder::set_payment_image($this, $impactTitle);

        $this->icon = apply_filters(
            $this->id . '_icon_url',
            $defaultIcon
        );
    }

    public function get_icon()
    {
        $output = $this->icon ?: '';
        return apply_filters('woocommerce_gateway_icon', $output, $this->id);
    }

    public function initModal()
    {
        // Only enqueue the modal script once
        if (wp_script_is('taptree-checkout-modal', 'enqueued')) return;

        wp_enqueue_script(
            'taptree-checkout-modal',
            plugins_url('../assets/js/modal.bundle.js', __DIR__),
            ['jquery'],
            null,
            true
        );

        // Enqueue the modal CSS
        wp_enqueue_style(
            'taptree-checkout-modal-styles',
            plugins_url('../assets/css/modal.css', __DIR__),
            [],
            null
        );

        // Build a map of gateway IDs to their redirect behavior
        $gateway_map = [];
        foreach (WC()->payment_gateways()->get_available_payment_gateways() as $gateway) {
            if ($gateway instanceof self) { // Only include TapTree gateways
                $gateway_map[$gateway->id] = $gateway->as_redirect === 'yes';
            }
        }

        // Only localize placeholder for the checkout page (no order_id and key)
        if (is_checkout() && !is_wc_endpoint_url('order-pay')) {
            wp_localize_script('taptree-checkout-modal', 'taptree_modal_params', [
                'checkout_url'        => esc_url(\WC_AJAX::get_endpoint('checkout')), // Default WooCommerce checkout
                'custom_checkout_url' => '', // No custom endpoint on checkout page
                'order_id'            => '',
                'key'                 => '',
                'i18n_checkout_error' => __('An error occurred during checkout.', 'woocommerce'),
                'security'            => wp_create_nonce('taptree_pay_for_order'),
                'gateways'            => $gateway_map,
            ]);
        } else if (is_wc_endpoint_url('order-pay')) {
            global $wp;
            $order_id = absint($wp->query_vars['order-pay'] ?? 0);
            $order = wc_get_order($order_id);

            if ($order) {
                wp_localize_script('taptree-checkout-modal', 'taptree_modal_params', [
                    'checkout_url'        => esc_url(\WC_AJAX::get_endpoint('checkout')), // Default WooCommerce checkout
                    'custom_checkout_url' => esc_url(\WC_AJAX::get_endpoint('taptree_custom_pay_for_order')), // Custom URL for order-pay
                    'order_id'            => $order_id,
                    'key'                 => $order->get_order_key(),
                    'i18n_checkout_error' => __('An error occurred during checkout.', 'woocommerce'),
                    'security'            => wp_create_nonce('taptree_pay_for_order'),
                    'gateways'            => $gateway_map,
                ]);
            }
        }
    }



    public function isTapTreeAvailable()
    {
        // TODO: we support more currencies than EUR!
        $current_currency = get_woocommerce_currency();
        $available_currencies = array('EUR', 'CHF');

        return in_array($current_currency, $available_currencies, true);
    }

    protected function orderDoesNotExistFailure($order_id): array
    {
        $this->logger->debug(__METHOD__ . ":  Payment processing failed. Order $order_id not found.");

        $this->notice->addNotice(
            'error',
            sprintf(
                __(
                    'Unavailable order %s',
                    'taptree-payments-for-woocommerce'
                ),
                $order_id
            )
        );
        return array('result' => 'failure');
    }

    /**
     * @param $order_id
     * @param $paymentIntent
     */
    public function saveTapTreeInfo($order, $paymentIntent): void
    {
        $order->update_meta_data('_taptree_payment', $paymentIntent);
        // Set the new payment method explicitly
        $order->set_payment_method($this->id); // $this->id is the gateway's ID, e.g., 'taptree_wc_gateway_card'
        $this->logger->debug(__METHOD__ . " | Payment method set to: " . $this->id);
        $order->save();
    }

    /**
     * Delete active TapTree payment id for order
     *
     * @param int $order_id
     *
     * @return $this
     */
    public function unsetActiveTapTreePayment($order, $payment)
    {
        // only unset if payment id matches (i.e., no new payment intent was created)
        $taptreePayment = $this->getActiveTapTreePaymentId($order->get_id());

        if ($taptreePayment->id !== $payment->id) {
            return $this;
        }

        $order->delete_meta_data('_taptree_payment');
        $order->save();

        return $this;
    }

    /**
     * @param int    $order_id
     * @param string $payment_id
     *
     * @return $this
     */
    public function setCancelledTapTreePaymentId($order_id, $payment_id)
    {
        $order = wc_get_order($order_id);
        $order->update_meta_data('_taptree_cancelled_payment_id', $payment_id);
        $order->save();

        return $this;
    }


    /**
     * @param $paymentIntent
     * @param $order_id
     * @param $order
     */
    protected function reportPaymentIntentCreateSucceeded($paymentIntent, $order_id, $order): void
    {
        $this->logger->debug(
            'TapTree payment intent ' . $paymentIntent->id . ' (' . $paymentIntent->mode . ') created for order ' . $order_id
        );
        $order->add_order_note(__('Customer started ' . $this->title . ' ' . $paymentIntent->mode . ' mode payment (' . $paymentIntent->id . ').', 'taptree-payments-for-woocommerce'));

        $this->logger->debug(
            __METHOD__ . " | Redirect user for order " . $order_id  . " to TapTree Checkout URL: " . $paymentIntent->links->checkout->href
        );
    }

    /**
     * @param $order_id
     * @param $e
     */
    protected function reportPaymentIntentCreateFailed($order_id, $e): void
    {
        $this->logger->debug(
            $this->id . ': Failed to create TapTree payment object for order ' . $order_id . ': ' . $e->getMessage()
        );

        /* translators: Placeholder 1: Payment method title */
        $message = sprintf(__('Could not create %s payment.', 'taptree-payments-for-woocommerce'), $this->title);

        $this->notice->addNotice('error', $message);
    }

    public function init_form_fields()
    {
        $this->form_fields = $this->paymentMethod->getFormFields();
    }


    public function processPaymentFromPayForOrder()
    {
        // On the order-pay page, we don't have AJAX as in the checkout page
        // So we need to construct the AJAX response manually

        try {
            // Verify nonce for security
            // TODO: why does it fail?
            //check_ajax_referer('taptree_pay_for_order', 'security');
            $order_id = absint($_POST['order_id'] ?? 0);
            $order_key = sanitize_text_field($_POST['key'] ?? '');
            $order = wc_get_order($order_id);
            if (!$order) {
                $this->logger->error(__METHOD__ . " | Order ID {$order_id} not found.");
                wp_send_json_error([
                    'result' => 'failure',
                    'message' => __('Order not found.', 'woocommerce'),
                    'order_id' => $order_id,
                ]);
            }

            if ($order->get_order_key() !== $order_key) {
                $this->logger->error(__METHOD__ . " | Order key mismatch for Order ID {$order_id}.");
                wp_send_json_error([
                    'result' => 'failure',
                    'message' => __('Invalid order key.', 'woocommerce'),
                    'order_id' => $order_id,
                ]);
            }
            // Generate a new payment intent
            $this->logger->debug(__METHOD__ . " | Generating new payment intent for order {$order_id}.");
            $result = $this->process_payment($order_id);

            if (!empty($result['redirect'])) {
                $this->logger->debug(__METHOD__ . " | Sending redirect URL on AJAX call for order {$order_id}.");
                wp_send_json([
                    'result' => 'success',
                    'redirect' => esc_url_raw($result['redirect']),
                    'order_pay_url' => esc_url_raw($order->get_checkout_payment_url(false)),
                    'thank_you_url' => esc_url_raw($order->get_checkout_order_received_url()),
                    'order_id' => $order_id, // Included for consistency with the checkout page
                ]);
            } else {
                $this->logger->error(__METHOD__ . " | Failed to generate redirect URL for order {$order_id}.");
                throw new Exception(__('Failed to generate payment URL.', 'woocommerce'));
            }
        } catch (\Exception $e) {
            $this->logger->error(__METHOD__ . ' | Error in processPaymentFromPayForOrder: ' . $e->getMessage());
            wp_send_json_error([
                'result' => 'failure',
                'message' => $e->getMessage(),
                'order_id' => $order_id,
            ]);
        }

        wp_die();
    }

    public function process_payment($order_id)
    {
        $this->logger->debug(__METHOD__ . " |  Processing payment for order " . $order_id . " with gateway " . $this->id);

        // get the order
        $order = wc_get_order($order_id);

        // play safe and check if the order exists
        if (!$order) {
            return $this->orderDoesNotExistFailure($order_id);
        }

        // play safe and check if the order is already completed and thus doesn't require payment
        if ($order->get_status() === 'completed') {
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_order_received_url(),
            );
        }

        $order->update_status(TapTreePaymentGateway::WOO_STATUS_PENDING, __('Awaiting authorization', 'woocommerce'));
        $this->logger->debug(__METHOD__ . " |  Set the order pending.");


        try {
            $paymentIntent = $this->api->create_payment_intent($this, $order);
            $this->logger->debug(__METHOD__ . ":  " . json_encode($paymentIntent));
            $checkoutUrl = $paymentIntent->links->checkout->href;

            $this->saveTapTreeInfo($order, $paymentIntent);
            $this->reportPaymentIntentCreateSucceeded($paymentIntent, $order_id, $order);



            return array(
                'result' => 'success',
                'redirect' => $checkoutUrl, // . sprintf("&sr=%s", $order->get_data_store()->get_stock_reduced($order_id)),
                'order_pay_url' => $order->get_checkout_payment_url(false),
                'thank_you_url' => $order->get_checkout_order_received_url(),
                'payment_method' => $this->id,
            );
        } catch (\Exception $e) {
            $this->reportPaymentIntentCreateFailed($order_id, $e);
        } catch (Exception $e) {
            $this->reportPaymentIntentCreateFailed($order_id, $e);
        }
        return array('result' => 'failure');
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $this->logger->debug(__METHOD__ . " |  Processing refund for order " . $order_id);

        // Get the WooCommerce order
        $order = wc_get_order($order_id);
        try {
            // WooCommerce order not found
            if (!$order) {
                $error_message = "Could not find WooCommerce order $order_id.";

                $this->logger->debug(
                    __METHOD__ . ' - ' . $error_message
                );

                return new WP_Error('1', $error_message);
            }

            $payment = $order->get_meta('_taptree_payment', true);

            // Try getting a TapTree Payment object
            if (!$payment) {
                $error_message
                    = "Can\'t process refund. Could not find TapTree Payment object id for order $order_id.";

                $this->logger->debug(
                    __METHOD__ . ' - ' . $error_message
                );

                return new WP_Error('1', $error_message);
            }

            $this->logger->debug(__METHOD__ . ' found payment object: ' . json_encode($payment));

            $this->logger->debug(__METHOD__ . ' - ' . $order_id . ' - Try to process refunds for individual order line(s).');

            if (!($payment->status === 'paid' || $payment->status === 'partially_captured')) {
                $errorMessage = "Can not refund payment $payment->id for WooCommerce order $order_id as it is not paid.";

                $this->logger->debug(__METHOD__ . ' - ' . $errorMessage);

                return new WP_Error('1', $errorMessage);
            }

            $this->logger->debug(__METHOD__ . ' - Create refund - payment object: ' . $payment->id . ', WooCommerce order: ' . $order_id . ', amount: ' . ', reason: ' . $reason . '.');

            do_action($this->pluginId . '_create_refund', $payment, $order);
            $refund = $this->api->create_refund($payment, $order, $amount, $reason);

            $this->logger->debug(__METHOD__ . ' - Refund created: ' . json_encode($refund));

            do_action('taptree-payments-for-woocommerce_refund_payment_created', $refund, $order);

            $order->add_order_note(sprintf(
                /* translators: Placeholder 1: currency, placeholder 2: refunded amount, placeholder 3: optional refund reason, placeholder 4: payment ID, placeholder 5: refund ID */
                __('Refunded %1$s %2$s %3$s - Payment: %4$s, Refund: %5$s', 'taptree-payments-for-woocommerce'),
                $amount,
                'EUR',
                (!empty($reason) ? ' (reason: ' . $reason . ')' : ''),
                $payment->id,
                $refund->id
            ));

            return true;
        } catch (\Exception $e) {
            return new WP_Error(1, $e->getMessage());
        } catch (Exception $e) {
            return new WP_Error(1, $e->getMessage());
        }
    }

    public function thankyou_page($order_id)
    {
        $order = wc_get_order($order_id);

        // Order not found
        if (!$order) {
            return;
        }

        // Empty cart
        if (WC()->cart) {
            WC()->cart->empty_cart();
        }

        $this->logger->debug(__METHOD__ . ":  " . json_encode($order));

        // Same as email instructions, just run that
        $this->displayInstructions(
            $order,
            $admin_instructions = false,
            $plain_text = false
        );
    }

    /**
     * @param WC_Order $order
     *
     * @return string
     */
    public function getOrderRedirectUrl(WC_Order $order): string
    {
        $order_id = $order->get_id();
        $hookReturnPaymentStatus = 'success';
        $returnRedirect = $this->get_return_url($order);
        $failedRedirect = $order->get_checkout_payment_url(false);

        if (!$this->isOrderPaid($order)) {
            return $failedRedirect;
        }
        do_action(
            $this->pluginId . '_customer_return_payment_'
                . $hookReturnPaymentStatus,
            $order
        );

        return $returnRedirect;
    }

    public function setOrderPaidWithTapTree(WC_Order $order)
    {
        $order->update_meta_data('_taptree_paid', '1');
        $order->save();
    }

    public function isPaidByOtherGateway($order_id)
    {
        $order = wc_get_order($order_id);
        return $order->get_payment_method() && (strpos($order->get_payment_method(), 'taptree') === false);
    }

    public function isOrderPaidWithTapTree(WC_Order $order)
    {
        return $order->get_meta('_taptree_paid') === '1';
    }

    public function isOrderPaid(WC_Order $order)
    {
        $order_id = $order->get_id();

        // Check whether the order is processed and paid via another gateway
        if ($this->isPaidByOtherGateway($order_id)) {
            $this->logger->debug($this->id . ": Order $order_id is paid by another gateway. Method used was " . $order->get_payment_method());
            return true;
        }

        // Check whether the order is already processed and paid via TapTree
        if ($this->isOrderPaidWithTapTree($order)) {
            $this->logger->debug($this->id . ": Order $order_id is already paid with TapTree. ");
            return true;
        }

        // Check whether the order itself needs payment
        if (!$order->needs_payment()) {
            $this->logger->debug($this->id . ": Order $order_id does not need payment. ");
            return true;
        }

        return false;
    }

    /**
     * Get active taptree payment id for order
     *
     * @param int $order_id
     *
     * @return string
     */
    public function getActiveTapTreePaymentId($order_id)
    {
        $order = wc_get_order($order_id);
        return $order->get_meta('_taptree_payment', true);
    }


    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order
     * @param bool     $admin_instructions (default: false)
     * @param bool     $plain_text         (default: false)
     *
     * @return void
     */
    public function displayInstructions(
        WC_Order $order,
        bool $admin_instructions = false,
        bool $plain_text = false
    ) {

        $this->logger->debug(__METHOD__);
        if (!$this::$alreadyDisplayedInstructions) {
            $this->logger->debug(__METHOD__ . ": did not already display instructions.");
            $order_payment_method = $order->get_payment_method();

            // Invalid gateway
            if ($this->id !== $order_payment_method) {
                return;
            }

            $payment = $this->getActiveTapTreePaymentId(
                $order->get_id()
            );

            // TapTree payment not found
            if (
                !$payment
            ) {
                // handle payment object content
                $this->logger->debug(__METHOD__ . ": TapTree payment not found.");
                return;
            }

            // handle payment object content
            $this->logger->debug(__METHOD__ . ":  " . json_encode($payment));

            $instructions = $this->getInstructions(
                $this,
                $payment,
                $order,
                $admin_instructions
            );

            if (!empty($instructions)) {
                $instructions = wptexturize($instructions);

                if ($plain_text) {
                    echo wp_kses_post($instructions . PHP_EOL);
                } else {
                    echo '<section class="woocommerce-order-details" >';
                    echo wp_kses_post(wpautop($instructions) . PHP_EOL);
                    echo '</section>';
                }
            }
        }
        $this->logger->debug(__METHOD__ . ": set already displayed instructions.");
        $this::$alreadyDisplayedInstructions = true;
    }

    public function getInstructions(
        $gateway,
        $payment,
        $order = null,
        $admin_instructions = false
    ) {

        if ($payment->status === "open" || $payment->status === "pending") {
            if ($admin_instructions) {
                // Message to admin
                return __(
                    'Wir haben noch keinen endgültigen Zahlungsstatus erhalten.',
                    'taptree-payments-for-woocommerce'
                );
            } else {
                // Message to customer
                return __(
                    'Wir haben noch keinen endgültigen Zahlungsstatus erhalten. Sie werden eine E-Mail erhalten, sobald wir eine Bestätigung der Bank erhalten.',
                    'taptree-payments-for-woocommerce'
                );
            }
        } elseif ($payment->status === "authorized" || $payment->status === "paid") {
            if ($this->show_impact === 'yes' && isset($payment->impact->value) && isset($payment->impact->unit)) {
                return sprintf(
                    /* translators: Placeholder 1: payment method */
                    __(
                        'Payment erfolgreich mit %s.<br/>Mit deiner Zahlung haben wir ' . str_replace(".", ",", $payment->impact->value) . ' ' . $payment->impact->unit . ' gebunden.',
                        'taptree-payments-for-woocommerce'
                    ),
                    $this->paymentService->getPaymentMethodDetails($payment)
                );
            }

            return sprintf(
                /* translators: Placeholder 1: payment method */
                __(
                    'Payment erfolgreich mit %s.',
                    'taptree-payments-for-woocommerce'
                ),
                $this->paymentService->getPaymentMethodDetails($payment)
            );
        }

        return null;
    }

    function action_cart_calculate_totals($cart)
    {
        $this->updateImpactIfTotalsChanged($cart);

        if ($this->paymentMethod->getProp('show_method_description') === 'yes') {
            $this->description = $this->set_payment_description();
        }


        $this->setImpactTitle();
    }

    // hook to build up the payment method icon on the order pay page
    public function handleOrderPayPage()
    {
        $this->setImpactTitle();
    }

    private function updateImpactIfTotalsChanged($cart)
    {
        if (is_admin() && !wp_doing_ajax())
            return;

        if ($cart->is_empty())
            return;

        $cart->get_cart();
        $total = $this->getCartTotal($cart);
        $this->impact = WC()->session->get('taptree_impact');
        if ($this->impact && (strcmp($this->impact->amount->value, $total) === 0)) {
            return;
        }
        // retrieve the impact from the API
        try {
            $this->impact = $this->api->get_impact_info($total, false);
        } catch (\Exception $e) {
        } catch (Exception $e) {
        }

        // store the impact in the session
        WC()->session->set(
            'taptree_impact',
            $this->impact
        );
        $this->logger->debug(__METHOD__ . " | new impact:  " . json_encode($this->impact));
        // force a refresh of the title and description
        //$this->description = $this->set_payment_description();
        //WC()->session->set('reload_checkout ', 'true');
    }

    private function getCartTotal($cart)
    {
        if (!$cart)
            return;

        $cart->get_cart();

        try {
            $total = $cart->total;
            if ($total != 0)
                return $total;

            // some hacky way to get the total amount
            // we need this as the Germanized plugin set cart->total to 0
            $total_str = preg_replace('#[^\d.,]#', '', $cart->get_cart_total());

            if (preg_match('/^\d+[.]\d+$/', $total_str)) {
                return $total_str;
            }

            $split = preg_split('/[.,]/', $total_str);
            $total = implode(array_slice($split, 0, -1, true)) . '.' . end($split);
            return $total;
        } catch (\Exception $e) {
            return;
        } catch (Exception $e) {
            return;
        }
    }



    private function setImpactTitle()
    {
        $impactTitle = $this->getImpactTitle();
        $this->setPaymentMethodIcon($impactTitle);
    }

    private function getImpact()
    {
        if (!WC() || !WC()->session) {
            return null;
        }
        $this->impact = WC()->session->get('taptree_impact');

        return $this->impact;
    }

    public function getImpactTitle()
    {
        $currentImpact = $this->getImpact();
        if ($currentImpact) {
            return __(number_format(floatval($currentImpact->available_payment_methods->{$this->paymentMethod->getId()}->impact->value), 0, ',', '.') . ' ' . $currentImpact->available_payment_methods->{$this->paymentMethod->getId()}->impact->unit, 'taptree-payments-for-woocommerce');
        }

        return __(null, 'taptree-payments-for-woocommerce');
    }

    public function set_payment_description($description = "")
    {
        // If we have a description, use it
        if ($description != "") return $description;
        if ($this->show_impact === 'no') return $this->standardDescription;
        $currentImpact = $this->getImpact();
        if (!$currentImpact) return $this->standardDescription;

        if ($this->as_redirect === 'no') {
            return 'Mit dieser Zahlungsart kostenlos und ohne Anmeldung zusätzlich bis zu <b>' . number_format(floatval($currentImpact->available_payment_methods->{$this->paymentMethod->getId()}->impact->value), 0, ',', '.')  . ' ' . $currentImpact->available_payment_methods->{$this->paymentMethod->getId()}->impact->unit . ' </b> aus der Atmosphäre entfernen. Für weitere Infos und zur Bezahlung, wird das sichere TapTree Payments Browserfenster geöffnet.';
        }

        return 'Mit dieser Zahlungsart kostenlos und ohne Anmeldung zusätzlich bis zu <b>' . number_format(floatval($currentImpact->available_payment_methods->{$this->paymentMethod->getId()}->impact->value), 0, ',', '.')  . ' ' . $currentImpact->available_payment_methods->{$this->paymentMethod->getId()}->impact->unit . ' </b> aus der Atmosphäre entfernen. Für weitere Infos und zur Bezahlung, erfolgt eine Weiterleitung zum TapTree Payments Formular.';
    }
}
