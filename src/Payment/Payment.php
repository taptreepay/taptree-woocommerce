<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\Payment;

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
