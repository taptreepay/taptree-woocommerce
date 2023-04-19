<?php

namespace TapTree\WooCommerce\Api;

use Exception;
use TapTree\WooCommerce\Gateway\TapTreePaymentGateway;
use WP_Error;

defined('ABSPATH') || exit;

class TapTreeApi
{
  /**
   * @var TapTreePaymentGateway
   */
  protected TapTreePaymentGateway $gateway;

  public function __construct(TapTreePaymentGateway $gateway)
  {
    $this->gateway = $gateway;
    $this->webhook_url = WC()->api_request_url($this->gateway->webhookSlug);
    $this->api_key = $this->gateway->api_key;
  }

  /**
   * @param WC_Order
   * @return object
   */
  public function create_payment_intent($order)
  {
    $intent_params = $this->intent_params_builder($order);
    $response_raw = wp_safe_remote_post(
      'https://api.taptree.org/v1/payments', //'https://eu-test.taptree.io/v1/payments',
      $intent_params
    );

    return $this->parse_response_safe($response_raw);
  }

  /**
   * @param WC_Order
   * @return array
   */
  private function intent_params_builder($order)
  {
    $params = array(
      'method' => 'POST',
      'data_format' => 'body',
      'headers' => array(
        'Content-Type' => 'application/json; charset=utf-8',
        'Authorization' => 'Bearer ' . $this->api_key,
      ),
      'body' => json_encode(array(
        "capture_method" => "manual",
        "amount" => array(
          "value" => wc_float_to_string($order->get_total()), // number_format( $order->get_total(), 2, '.', '' ),
          "currency" => mb_strtolower(get_woocommerce_currency()),
        ),
        "description" => "Bestellung #" . $order->get_id(),
        "redirect_url" => $this->gateway->getReturnUrl(
          $order,
          $this->gateway->get_return_url($order)
        ),
        "billing_address" => array(
          "receiver" => $this->get_length_limited_string($order->get_billing_first_name() . " " . $order->get_billing_last_name()),
          "street_and_number" => $this->get_length_limited_string($order->get_billing_address_1()),
          "additional_address_information" => $this->get_length_limited_string($order->get_billing_address_2()),
          "postal_code" => $this->get_length_limited_string(wc_format_postcode($order->get_billing_postcode(), $order->get_billing_country())),
          "city" => $this->get_length_limited_string($order->get_billing_city()),
          "country" => $this->get_length_limited_string($order->get_billing_country()),
        ),
        "shipping_address" => array(
          "receiver" => $this->get_length_limited_string($order->get_shipping_first_name() . " " . $order->get_shipping_last_name()),
          "street_and_number" => $this->get_length_limited_string($order->get_shipping_address_1()),
          "additional_address_information" => $this->get_length_limited_string($order->get_shipping_address_2()),
          "postal_code" => $this->get_length_limited_string(wc_format_postcode($order->get_shipping_postcode(), $order->get_shipping_country())),
          "city" => $this->get_length_limited_string($order->get_shipping_city()),
          "country" => $this->get_length_limited_string($order->get_shipping_country()),
        ),
        "customer" => array(
          "id" => $this->get_length_limited_string(strval($order->get_user_id())), // $this->get_length_limited_string( strval($order->get_customer_id()) ),
          "email" => $this->get_length_limited_string($order->get_billing_email()),
        ),
        "locale" => $this->get_length_limited_string(get_locale(), 2),
        "sequence_type" => "one_off",
        "metadata" => array(
          "source" => "woocommerce",
          "order_number" => $order->get_order_number(),
          "order_id" => $order->get_id(),
          "order_key" => $order->get_order_key(),
        ),
        "webhook_url" => $this->gateway->getWebhookUrl($order, $this->gateway->id),
      ))
    );
    return $params;
  }

  /**
   * @param string
   * @return object
   */
  public function get_payment($transaction_id)
  {
    $response_raw = wp_safe_remote_get(
      'https://api.taptree.org/v1/payments/' . $transaction_id,
      array(
        'method' => 'GET',
        'headers' => array(
          'Content-Type' => 'application/json; charset=utf-8',
          'Authorization' => 'Bearer ' . $this->api_key,
        )
      )
    );

    return $this->parse_response_safe($response_raw);
  }

  /**
   * @param string
   * @return object
   */
  public function get_acceptor_data($token)
  {
    $response_raw = wp_safe_remote_get(
      'https://api.taptree.org/v1/acceptor',
      array(
        'method' => 'GET',
        'headers' => array(
          'Content-Type' => 'application/json; charset=utf-8',
          'Authorization' => 'Bearer ' . $token,
        )
      )
    );

    return $this->parse_response_safe($response_raw);
  }

  /**
   * Triggers the capture of the payment with the given transaction_id, which was authorized previously
   * 
   * @param string
   * @return object
   */
  public function capture_payment($transaction_id)
  {
    $response_raw = wp_safe_remote_post(
      'https://api.taptree.org/v1/payments/' . $transaction_id . '/capture',
      array(
        'method' => 'POST',
        'data_format' => 'body',
        'headers' => array(
          'Content-Type' => 'application/json; charset=utf-8',
          'Authorization' => 'Bearer ' . $this->api_key,
        ),
        'body' => '{}'
      )
    );

    return $this->parse_response_safe($response_raw);
  }

  /**
   * Triggers the capture of the payment with the given transaction_id, which was authorized previously
   * 
   * @param string
   * @return object
   */
  public function create_refund($payment, $order, $amount, $reason)
  {
    $response_raw = wp_safe_remote_post(
      'https://api.taptree.org/v1/payments/' . $payment->id . '/refund',
      array(
        'method' => 'POST',
        'data_format' => 'body',
        'headers' => array(
          'Content-Type' => 'application/json; charset=utf-8',
          'Authorization' => 'Bearer ' . $this->api_key,
        ),
        'body' => json_encode(array(
          "amount" => array(
            "value" => wc_float_to_string($amount), // number_format( $order->get_total(), 2, '.', '' ),
            "currency" => mb_strtolower(get_woocommerce_currency()),
          ),
          "description" => $reason,
          "metadata" => array(
            "source" => "woocommerce",
            "order_number" => $order->get_order_number(),
            "order_id" => $order->get_id(),
            "order_key" => $order->get_order_key(),
          ),
        ))
      )
    );

    return $this->parse_response_safe($response_raw);
  }

  /**
   * Get impact info for the checkout
   * 
   * @param string
   * @return object
   */
  public function get_impact_info($total_amount, $omit_payment_methods)
  {
    $response_raw = wp_safe_remote_post(
      'https://api.taptree.org/v1/impact',
      array(
        'timeout' => 3, // don't block too long if there is an issue. should rarely happen because of HA
        'method' => 'POST',
        'data_format' => 'body',
        'headers' => array(
          'Content-Type' => 'application/json; charset=utf-8',
          'Authorization' => 'Bearer ' . $this->api_key,
        ),
        'body' => json_encode(array(
          "omit_payment_methods" => $omit_payment_methods,
          "amount" => array(
            "value" => wc_float_to_string($total_amount), // number_format( $order->get_total(), 2, '.', '' ),
            "currency" => mb_strtolower(get_woocommerce_currency()),
          ),
        ))
      )
    );

    return $this->parse_response_safe($response_raw);
  }

  /**
   * @param string
   * @param integer
   * @return string
   */
  private function get_length_limited_string($string, $max_length = 127)
  {
    return substr($string, 0, $max_length);
  }

  private function parse_response_safe($response_raw)
  {
    if (is_wp_error($response_raw)) {
      return $response_raw;
    } else if (empty($response_raw['body'])) {
      throw new Exception('Received empty response');
    } elseif ($response_raw['response']['code'] == null || $response_raw['response']['code'] >= 400) {
      throw new Exception('Received error response');
    } else {
      return json_decode($response_raw['body']);
    }
  }
}
