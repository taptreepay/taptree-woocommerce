<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\Payment;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Payment
{
  protected $pluginId;

  public function __construct($logger, $pluginId)
  {
    $this->logger = $logger;
    $this->pluginId = $pluginId;
  }

  public function getPaymentObject($paymentId)
  {
  }
}
