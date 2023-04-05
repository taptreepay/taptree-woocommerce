<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\Gateway;

use WC_Payment_Gateway;
use TapTree\WooCommerce\Api\TapTreeApi;
use TapTree\WooCommerce\Api\WebhookHandler;
use TapTree\WooCommerce\Notice\NoticeInterface;
use TapTree\WooCommerce\SDK\HttpResponse;
use TapTree\WooCommerce\Shared\SharedDataDictionary;
use TapTree\WooCommerce\Payment\PaymentService;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\LogLevel;
use WC;
use WC_Admin_Settings;
use WC_Order;
use WP_Error;
use Exception;
use InvalidArgumentException;
use UnexpectedValueException;

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
        string $gatewayId,
        Logger $logger,
        NoticeInterface $notice,
        HttpResponse $httpResponse,
        PaymentService $paymentService,
        string $pluginId
    ) {
        //$this->icon                = ''; //'https://taptree.org/doc/TapTree_Logo_Square.png';
        $this->id = $gatewayId;
        $this->webhookSlug = $this->id . '_notification';
        $this->logger = $logger;
        $this->notice = $notice;
        $this->httpResponse = $httpResponse;
        $this->pluginId = $pluginId;
        $this->paymentService = $paymentService;
        $this->supports             = array('products', 'refunds');
        // No plugin id, gateway id is unique enough
        $this->plugin_id = '';
        $this->has_fields          = false;
        // Todo move this if we have more methods than just the hosted checkout
        $this->method_title        = __('TapTree Checkout', 'woocommerce');
        $this->method_description  = __('Process payments climate-friendly and secure with TapTree\'s Hosted Checkout solution.', 'woocommerce');
        $this->standardDescription = __('Mit diesen Zahlungsarten kostenlos und ohne Anmeldung Klimaschutzprojekte unterstützen. Für weitere Infos und zur Bezahlung, erfolgt eine Weiterleitung zum ClimatePay Formular.');

        $this->init_form_fields();
        $this->init_settings();

        // todo test if api_key is set
        $this->api_key = $this->get_option('api_key');
        $this->enabled = $this->get_option('enabled');
        $this->as_redirect = $this->get_option('as_redirect');
        $this->alt_title = $this->get_option('alt_title');
        $this->show_impact = $this->get_option('show_impact');



        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        if ($this->isTapTreeAvailable() && $this->enabled === 'yes') {

            //$this->logger->debug(__METHOD__ . " | " . $this->title . " | " . $this->id . " | " . $this->pluginId);
            $this->taptreeApi = new TapTreeApi($this);
            $this->paymentService->setGateway($this, $this->taptreeApi);

            if (!$this->api_key || strlen($this->api_key) === 0) {
                WC_Admin_Settings::add_error('A valid TapTree API key is required to enable TapTree Climate Pay Checkout.');
                if ($this->update_option('enabled', 'no')) {
                    do_action('woocommerce_update_option', array('enabled' => 'no'));
                    $this->enabled = get_option('enabled');
                }
            }

            if ($this->as_redirect === 'no') {
                $this->standardDescription = __('Mit diesen Zahlungsarten kostenlos und ohne Anmeldung Klimaschutzprojekte unterstützen. Für weitere Infos und zur Bezahlung, wird das sichere ClimatePay Browserfenster geöffnet.');

                $this->initModal();
            }

            $this->title = $this->getPaymentTitle();
            $this->description = $this->set_payment_description();

            $this->initIcon();

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

    public function validate_enabled_field($key, $value)
    {
        $this->logger->debug(__METHOD__ . " | " . $key . " | " . $value);
    }

    public function validate_api_key_field($key, $value)
    {
        if (!$value || strlen($value) === 0) {
            if ($this->update_option('enabled', 'no')) {
                do_action('woocommerce_update_option', array('enabled' => 'no'));
                $this->enabled = get_option('enabled');

                WC_Admin_Settings::add_error('A valid API Key is required. TapTree Checkout has been disabled.');
                return '';
            }

            WC_Admin_Settings::add_error('A valid API Key is required to enable TapTree Checkout.');
            return '';
        }

        return $value;
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
        return $webhook_url;
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

    public function initIcon()
    {
        $defaultIcon = PaymentMethodImageBuilder::set_payment_images($this);

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
        wp_enqueue_script(
            'taptree-checkout-as-modal',
            untrailingslashit('/wp-content/plugins/taptree-woocommerce/src/Gateway/js/modal.js'),
            array('jquery'),
            false,
            true
        );
    }

    public function isTapTreeAvailable()
    {
        // TODO: we support more currencies than EUR!
        $current_currency = get_woocommerce_currency();
        $available_currencies = array('EUR');

        return in_array($current_currency, $available_currencies, true);
    }

    public function getPaymentTitle()
    {
        if ($this->alt_title === 'yes') {
            return 'ClimatePay';
        }

        return 'Kreditkarte und mehr';
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
            "Redirect user for order " . $order_id  . " to TapTree Checkout URL: " . $paymentIntent->links->checkout->href
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
        $this->form_fields = include __DIR__ . '/gateway-properties.php';
    }

    public function process_payment($order_id)
    {
        $this->logger->debug(__METHOD__ . " |  Processing payment for order " . $order_id);

        // get the order
        $order = wc_get_order($order_id);

        if (!$order) {
            return $this->orderDoesNotExistFailure($order_id);
        }

        $order->update_status(TapTreePaymentGateway::WOO_STATUS_PENDING, __('Awaiting authorization', 'woocommerce'));
        $this->logger->debug(__METHOD__ . " |  Set the order pending.");


        try {
            $paymentIntent = $this->taptreeApi->create_payment_intent($order);
            $this->logger->debug(__METHOD__ . ":  " . json_encode($paymentIntent));
            $checkoutUrl = $paymentIntent->links->checkout->href;

            $this->saveTapTreeInfo($order, $paymentIntent);
            $this->reportPaymentIntentCreateSucceeded($paymentIntent, $order_id, $order);

            return array(
                'result' => 'success',
                'redirect' => $checkoutUrl, // . sprintf("&sr=%s", $order->get_data_store()->get_stock_reduced($order_id)),
            );
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
            $refund = $this->taptreeApi->create_refund($payment, $order, $amount, $reason);

            $this->logger->debug(__METHOD__ . ' - Refund created: ' . json_encode($refund));

            do_action('taptree-payments-for-woocommerce_refund_payment_created', $refund, $order);

            $order->add_order_note(sprintf(
                /* translators: Placeholder 1: currency, placeholder 2: refunded amount, placeholder 3: optional refund reason, placeholder 4: payment ID, placeholder 5: refund ID */
                __('Refunded %1$s %2$s %3$s - Payment: %4$s, Refund: %5$s', 'TapTree-payments-for-woocommerce'),
                $amount,
                'EUR',
                (!empty($reason) ? ' (reason: ' . $reason . ')' : ''),
                $payment->id,
                $refund->id
            ));

            return true;
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
        $debugLine = __METHOD__
            . " {$order_id}: Determine what the redirect URL in WooCommerce should be.";
        $this->logger->debug($debugLine);
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
                    echo $instructions . PHP_EOL;
                } else {
                    echo '<section class="woocommerce-order-details" >';
                    echo wpautop($instructions) . PHP_EOL;
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
            if ($this->show_impact === 'yes') {
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
        if (is_admin() && !wp_doing_ajax())
            return;

        if ($cart->is_empty())
            return;

        $cart->get_cart();
        $this->logger->debug("");
        $this->logger->debug("############# START CALCULATE IMPACT #############");
        $total = $this->getCartTotal($cart);
        $this->logger->debug(__METHOD__ . " | I received a total of  " . $total . " € to calculate the impact.");
        $this->impact = WC()->session->get('taptree_impact');
        if ($this->impact && (strcmp($this->impact->amount->value, $total) === 0)) {
            $this->logger->debug(__METHOD__ . " | I have the same total of  " . $this->impact->amount->value . " € in the impact session-just skipping.");
            return;
        }
        $this->logger->debug(__METHOD__ . " | I have a different total of  " . $this->impact->amount->value . " € in the impact session, so I need to calculate the impact again.");
        // retrieve the impact from the API
        $this->impact = $this->taptreeApi->get_impact_info($total, true);
        $this->logger->debug(__METHOD__ . ":  " . json_encode($this->impact));
        // store the impact in the session
        WC()->session->set(
            'taptree_impact',
            $this->impact
        );
        // force a refresh of the title and description
        //$this->title               = $this->getPaymentTitle();
        //$this->description = $this->set_payment_description();
        //WC()->session->set('reload_checkout ', 'true');

        $this->logger->debug("############# END CALCULATE IMPACT #############");
        $this->logger->debug("");
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
        } catch (Exception $e) {
            return;
        }
    }

    private function getImpact()
    {
        if (!WC() || !WC()->session) return null;

        $this->impact = WC()->session->get('taptree_impact');
        return $this->impact;
    }

    public function getImpactTitle()
    {
        $currentImpact = $this->getImpact();
        if ($currentImpact) {
            return __($currentImpact->highest_possible_impact->value . ' ' . $currentImpact->highest_possible_impact->unit, 'taptree-payments-for-woocommerce');
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
            return 'Mit diesen Zahlungsarten kostenlos und ohne Anmeldung zusätzlich bis zu <b>' . str_replace(".", ",", $currentImpact->highest_possible_impact->value) . ' kg CO2</b> aus der Atmosphäre entfernen. Für weitere Infos und zur Bezahlung, wird das sichere ClimatePay Browserfenster geöffnet.';
        }

        return 'Mit diesen Zahlungsarten kostenlos und ohne Anmeldung zusätzlich bis zu <b>' . str_replace(".", ",", $currentImpact->highest_possible_impact->value) . ' kg CO2</b> aus der Atmosphäre entfernen. Für weitere Infos und zur Bezahlung, erfolgt eine Weiterleitung zum ClimatePay Formular.';
    }
}
