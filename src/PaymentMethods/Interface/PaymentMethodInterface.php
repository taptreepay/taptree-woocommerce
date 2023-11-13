<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\PaymentMethods\Interface;


interface PaymentMethodInterface
{

    public function getLogoHTML(): string;
    public function getProp(string $propName);
}
