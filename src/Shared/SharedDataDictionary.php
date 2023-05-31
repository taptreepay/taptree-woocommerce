<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\Shared;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class SharedDataDictionary
{
    public const GATEWAY_CLASSNAMES = [
        'TapTree_WC_Gateway_HostedCheckout',
    ];
}
