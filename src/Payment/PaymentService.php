<?php

namespace TapTree\WooCommerce\Payment;

use Exception;
use TapTree\WooCommerce\Api\TapTreeApi;
use TapTree\WooCommerce\Gateway\TapTreePaymentGateway;
use TapTree\WooCommerce\SDK\HttpResponse;
use Psr\Log\LoggerInterface as Logger;
use WC_Order;
use WP_Error;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class PaymentService
{
    protected $gateway;
    /**
     * @var HttpResponse
     */
    private $httpResponse;
    /**
     * @var Logger
     */
    protected $logger;

    protected $pluginId;

    /**
     * @var TapTreeApi
     */
    protected $taptreeApi;

    public function __construct(
        HttpResponse $httpResponse,
        Logger $logger,
        string $pluginId
    ) {
        $this->httpResponse = $httpResponse;
        $this->logger = $logger;
        $this->pluginId = $pluginId;

        add_action('taptree_reduce_stock', 'wc_maybe_reduce_stock_levels');
        add_action('taptree_order_WOO_STATUS_CANCELLED', 'wc_maybe_increase_stock_levels');
    }

    public function setGateway($gateway, $api)
    {
        $this->gateway = $gateway;
        $this->taptreeApi = $api;
    }

    public function onPaymentGatewayWebhookCalled()
    {
        // with the hosted checkout there is only one gateway currently, yet this might change
        // and thus we would need to use setGateway based on the order (wc_get_payment_gateway_by_order)
        $this->logger->debug(__METHOD__ . ":  Gateway " . $this->gateway->id);
        try {
            // simply decode the json data from the request
            $json_data = json_decode(file_get_contents('php://input'), true);
            $get_data = array();
            foreach ($_GET as $key => $value) {
                $get_data[$key] = sanitize_text_field($value);
            }

            $this->logger->debug(__METHOD__ . ":  " . json_encode($json_data));
            $this->logger->debug(__METHOD__ . ":  " . json_encode($get_data));

            // Webhook test by TapTree
            if (isset($json_data['testedByTapTree'])) {
                $this->httpResponse->setHttpResponseCode(200);
                $this->logger->debug(__METHOD__ . ' | Webhook tested by TapTree.', [true]);
                return;
            }

            if (empty($get_data['order_id']) || empty($get_data['key'])) {
                $this->httpResponse->setHttpResponseCode(400);
                $this->logger->debug(__METHOD__ . ":  No order ID or order key provided.");
                return;
            }

            // Check whether a payment id has been provided in the call
            if (empty($json_data['data']['id'])) {
                $this->httpResponse->setHttpResponseCode(400);
                $this->logger->debug(__METHOD__ . ' | No payment object ID provided.', [true]);
                return;
            }

            $order_id = $get_data['order_id'];
            $key = $get_data['key'];
            $order = wc_get_order($order_id);

            if (!$order) {
                $this->httpResponse->setHttpResponseCode(404);
                $this->logger->debug(__METHOD__ . ":  Could not find order $order_id.");
                return;
            }

            if (!$order->key_is_valid($key)) {
                $this->httpResponse->setHttpResponseCode(401);
                $this->logger->debug(__METHOD__ . ":  Invalid key $key for order $order_id.");
                return;
            }

            // play safe and check the gateway
            $gateway = wc_get_payment_gateway_by_order($order);
            if (!$gateway instanceof TapTreePaymentGateway) {
                return;
            }

            // we set the gateway (this is needed as soon as we handle more than one gateway)
            $this->gateway = $gateway;

            // we take the id
            $paymentId = isset($json_data['data']['id']) ? sanitize_text_field($json_data['data']['id']) : null;

            if (empty($paymentId)) {
                $this->httpResponse->setHttpResponseCode(400);
                $this->logger->debug(__METHOD__ . ' | No payment ID provided in the data object.', [true]);
                return;
            }

            $payment = $this->taptreeApi->get_payment($paymentId);
            $this->logger->debug(__METHOD__ . ":  " . json_encode($payment));

            // Payment not found
            if (!$payment) {
                // obfuscate the error message, i.e. do not tell the user that the payment was not found
                $this->httpResponse->setHttpResponseCode(200);
                $this->logger->debug(__METHOD__ . " | No payment $paymentId found.", [true]);
                return;
            }

            if ($order_id != $payment->metadata->order_id) {
                $this->httpResponse->setHttpResponseCode(400);
                $this->logger->debug(__METHOD__ . " | Order ID $order_id does not match order ID in payment metadata {$payment->metadata->order_id}.", [true]);
                return;
            }

            $this->gateway->saveTapTreeInfo($order, $payment);

            // Log a message that webhook was called with all details provided thus far
            $this->logger->debug($this->gateway->id . ": Process TapTree {$payment->mode} {$payment->object} object {$payment->id} with status {$payment->status} of order {$order->get_id()}.", [true]);

            // Check whether the order has already been paid and don't process the payment again
            if ($this->gateway->isOrderPaid($order)) {
                $this->httpResponse->setHttpResponseCode(200);
                $this->logger->debug(__METHOD__ . " | Order $order_id is already paid and does not need an additional payment by TapTree", [true]);

                // TODO: Process refunds only by webhook to make sure that they have been processed by TapTree
                return;
            }

            if (method_exists($this, 'handle_payment_status_' . $payment->status)) {
                call_user_func(array($this, 'handle_payment_status_' . $payment->status), $order, $payment);
            } else {
                $this->logger->debug($this->gateway->id . ": Couldn't find update method handle_payment_status_" . $payment->status);
                $order->add_order_note(sprintf(
                    /*  param 1: payment method title,
                        param 2: payment status,
                        param 3: payment ID
                    */
                    __('%1$s payment %2$s (%3$s), not processed in WooCommerce.', 'taptree-payments-for-woocommerce'),
                    $this->gateway->method_title,
                    $payment->status,
                    $payment->id
                ));
            }
        } catch (\Exception $e) {
            $this->logger->debug(__METHOD__ . ': exception: ' . $e->getMessage());
            $this->httpResponse->setHttpResponseCode(500);
            return;
        } catch (Exception $e) {
            $this->logger->debug(__METHOD__ . ': exception: ' . $e->getMessage());
            $this->httpResponse->setHttpResponseCode(500);
            return;
        }
    }

    public function paymentBrandName($brandKey)
    {
        $brandNames = array(
            'visa' => 'Visa',
            'master' => 'Mastercard',
            'amex' => 'American Express',
            'diners' => 'Diners Club',
            'discover' => 'Discover',
            'jcb' => 'JCB',
            'unionpay' => 'UnionPay',
            'maestro' => 'Maestro',
            'paypal' => 'PayPal',
            'sofort' => 'Sofort',
            'giropay' => 'Giropay',
            'applepay' => 'Apple Pay',
            'googlepay' => 'Google Pay'
        );
        return $brandNames[$brandKey];
    }

    private function walletName($walletKey)
    {
        if ($walletKey === 'applepay' || $walletKey === 'googlepay') {
            return $this->paymentBrandName($walletKey);
        }

        return;
    }

    public function getPaymentMethodName($payment)
    {
        $payment_method = $payment->payment_method;

        if ($payment_method === 'card') {
            return $this->paymentBrandName($payment->card->brand);
        }
        return $this->paymentBrandName($payment_method);
    }

    public function getPaymentMethodDetails($payment)
    {
        $payment_method = $payment->payment_method;

        if ($payment_method === 'card') {
            if (isset($payment->card->wallet))
                return $this->paymentBrandName($payment->card->brand) . ' in ' . $this->walletName($payment->card->wallet) . ' Wallet ' . ' (' . '**** **** **** ' . $payment->card->last4 . ')';
            return $this->paymentBrandName($payment->card->brand) . ' (' . '**** **** **** ' . $payment->card->last4 . ')';
        }
        return $this->paymentBrandName($payment_method);
    }


    /**
     * @param WC_Order
     * @param object
     */
    private function handle_payment_status_authorized(WC_Order $order, $payment)
    {
        $this->logger->debug($this->gateway->id . ": Starting process for payment status 'authorized'. ");

        $order_id = $payment->metadata->order_id;
        $paymentMethodDetails = $this->getPaymentMethodDetails($payment);

        if ($order->has_status(array(TapTreePaymentGateway::WOO_STATUS_PROCESSING, TapTreePaymentGateway::WOO_STATUS_COMPLETED))) {
            $this->logger->debug($this->gateway->id . ": Order is already processing or completed. ");
            return;
        }

        $order->add_order_note(__('Customer authorized payment using ' . $paymentMethodDetails . '.', 'woocommerce'));

        // Stock might have been already reduced! For instance, if the capture failed before.
        if (!$order->get_data_store()->get_stock_reduced($order_id)) {
            $this->logger->debug($this->gateway->id . ": Starting to reduce stock. ");
            do_action('taptree_reduce_stock', $order_id);
            $order->add_order_note(__('Stock reduced. Waiting for capture.', 'woocommerce'));
            $this->logger->debug($this->gateway->id . ": Stock reduced. ");
        }

        // And should be reduced by now
        if (!$order->get_data_store()->get_stock_reduced($order_id)) {
            // Display error for customer that product ran out of stock during checkout. Payment method will not be charged
            $this->logger->debug($this->gateway->id . ": Stock not reduced. ");
            $order->update_status('failed', __('At least one of the products in the order ran out of stock during checkout. The payment method of the customer has NOT been debited yet.', 'woocommerce'));
            return;
        }

        // payment is ready to be captured
        try {
            $capture = $this->taptreeApi->capture_payment($payment->id);
            $this->logger->debug(__METHOD__ . ":  " . json_encode($capture));
            // tell merchant which amount has been captured/paid.
            $order->add_order_note(__($capture->amount_captured->value . " " . $capture->amount_captured->currency . " captured from customer.", 'woocommerce'));
            $this->logger->debug($this->gateway->id . ": Payment captured. ");

            // we automatically capture the full amount, so this should always be true
            // yet play safe
            if ($capture->total_amount_captured->value === $capture->amount_captured->value) {
                $this->logger->debug($this->gateway->id . ": Captured full amount. ");
                $this->handle_payment_WOO_STATUS_COMPLETED($order, $payment);
                return;
            }

            $order->update_status(TapTreePaymentGateway::WOO_STATUS_PENDING, __('Payment partially captured.', 'woocommerce'));
        } catch (\Exception $e) {
            $this->logger->debug($this->gateway->id . ": Capture failed with error: " . $e->getMessage());
            $order->add_order_note(__('Capture failed. Next attempt might be scheduled.', 'woocommerce'));
            $order->update_status(TapTreePaymentGateway::WOO_STATUS_PENDING, __('Capture failed. Next attempt might be scheduled.', 'woocommerce'));
            $this->httpResponse->setHttpResponseCode(500);
            return new WP_Error('taptree_capture_failed', $e->get_message());
        } catch (Exception $e) {
            $this->logger->debug($this->gateway->id . ": Capture failed with error: " . $e->getMessage());
            $order->add_order_note(__('Capture failed. Next attempt might be scheduled.', 'woocommerce'));
            $order->update_status(TapTreePaymentGateway::WOO_STATUS_PENDING, __('Capture failed. Next attempt might be scheduled.', 'woocommerce'));
            $this->httpResponse->setHttpResponseCode(500);
            return new WP_Error('taptree_capture_failed', $e->get_message());
        }
    }

    private function handle_payment_status_paid(WC_Order $order, $payment)
    {
        $this->logger->debug($this->gateway->id . ": Starting process for status 'paid'. ");

        $order_id = $payment->metadata->order_id;

        if ($order->has_status(TapTreePaymentGateway::WOO_STATUS_PROCESSING)) {
            $this->logger->debug($this->gateway->id . ": Order is already processing.");
            return;
        }

        if ($order->has_status(TapTreePaymentGateway::WOO_STATUS_COMPLETED)) {
            $this->logger->debug($this->gateway->id . ": Order is already completed.");
            return;
        }

        // With standard settings we should never reach this point as we capture the payment in Woo manually.
        $order->add_order_note(__('Payment paid, reducing stock', 'woocommerce'));
        do_action('taptree_reduce_stock', $order_id);
        $this->logger->debug($this->gateway->id . ": Stock reduced. ");

        $is_stock_reduced = $order->get_data_store()->get_stock_reduced($order_id);

        if (!$is_stock_reduced) {
            // Display error for customer that product ran out of stock during checkout, but payment method will not be charged
            $order->add_order_note(__('Out of stock. Yet debited the customer already.', 'woocommerce'));
            $order->update_status('failed', __('At least one of the products in the order ran out of stock during checkout. The payment method of the customer has been debited.', 'woocommerce'));
            return;
        }

        $order->add_order_note(__($payment->amount->value . " " . $payment->amount->currency . " paid by customer.", 'woocommerce'));
        $this->handle_payment_WOO_STATUS_COMPLETED($order, $payment);
    }

    private function handle_payment_status_canceled(WC_Order $order, $payment)
    {
        $this->logger->debug($this->gateway->id . ": Payment " . $payment->id . " canceled.");
        $order->add_order_note(__('Customer canceled ' . $this->gateway->title . ' ' . $payment->mode . ' mode payment (' . $payment->id . ').', 'taptree-payments-for-woocommerce'));

        $order_id = $order->get_id();

        // status is already set to completed, refunded or cancelled
        if ($this->isFinalOrderStatus($order)) {
            $this->logger->debug("Order with id " . $order_id . " is already completed, refunded or cancelled.");
            return;
        }

        $this->gateway->unsetActiveTapTreePayment($order, $payment);
        $this->gateway->setCancelledTapTreePaymentId($order_id, $payment->id);

        $gateway = wc_get_payment_gateway_by_order($order);

        apply_filters($this->pluginId . '_order_status_cancelled', TapTreePaymentGateway::WOO_STATUS_PENDING);

        // Overwrite gateway-wide
        apply_filters($this->pluginId . '_order_status_cancelled_' . $gateway->id, TapTreePaymentGateway::WOO_STATUS_PENDING);
    }

    private function handle_payment_status_failed(WC_Order $order, $payment)
    {
        $this->logger->debug($this->gateway->id . ": Payment " . $payment->id . " failed.");
        $orderId = $order->get_id();

        // Add messages to log
        $this->logger->debug(__METHOD__ . ' called for order ' . $orderId);

        // Get current gateway
        $gateway = wc_get_payment_gateway_by_order($order);

        // New order status
        $newOrderStatus = TapTreePaymentGateway::WOO_STATUS_FAILED;

        // Overwrite plugin-wide
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_failed', $newOrderStatus);

        // Overwrite gateway-wide
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_failed_' . $gateway->id, $newOrderStatus);
    }

    private function handle_payment_status_expired(WC_Order $order, $payment)
    {
        $order_id = $payment->metadata->order_id;
        $taptreePaymentFromMeta = $order->get_meta('_taptree_payment', true);
        $taptreePaymentIdFromMeta = $taptreePaymentFromMeta->id;

        $this->logger->debug($this->gateway->id . ": Starting process for status 'expired' for order " . $order_id);

        // Already paid?
        if (!$order->needs_payment()) {
            $this->logger->debug($this->gateway->id . ": Expiry not processed because the order is already paid." . $order_id);
            return;
        }

        // Check the most recent payment id stored in post meta
        if ($taptreePaymentIdFromMeta !== $payment->id) {
            $this->logger->debug(__METHOD__ . ' called for order ' . $order_id . ' and payment ' . $payment->id . ', not processed because of a newer pending payment ' . $taptreePaymentIdFromMeta);
            $order->add_order_note(__('Payment expired (' . $payment->id . ').', 'taptree-payments-for-woocommerce'));
            return;
        }

        // we don't need to cancel the order, because we let the customer retry the payment and otherwise woocommerce expire the order
        $this->logger->debug(__METHOD__ . ' called for order ' . $order_id . ' and payment ' . $payment->id . '.');
        $order->add_order_note(__('Payment (' . $payment->id . ') expired. No active TapTree payments left.', 'taptree-payments-for-woocommerce'));
    }

    private function handle_payment_status_partially_captured(WC_Order $order, $payment)
    {
        // should not happen because we only capture the full amount currently
        $this->logger->debug($this->gateway->id . ": Payment " . $payment->id . " partially captured.");
    }

    /**
     * @param WC_Order
     * @param object
     * @return array
     */
    private function handle_payment_WOO_STATUS_COMPLETED(WC_Order $order, $payment)
    {
        // Remove cart
        if (isset(WC()->cart)) {
            WC()->cart->empty_cart();
        }

        $order->add_order_note(__('Payment completed', 'woocommerce')); // Make consistent with api
        $order->payment_complete($payment->id);
        $order->update_status(TapTreePaymentGateway::WOO_STATUS_PROCESSING, __('Payment processing captured.', 'woocommerce'));
    }

    /**
     * @param WC_Order $order
     *
     * @return bool
     */
    protected function isFinalOrderStatus(WC_Order $order)
    {
        $order_status = $order->get_status();

        $this->logger->debug(
            __METHOD__ . " called for order {$order->get_id()} with status {$order_status}"
        );

        return in_array(
            $order_status,
            ['completed', 'refunded', 'canceled'],
            true
        );
    }
}
