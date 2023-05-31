<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace TapTree\WooCommerce\SDK;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use TapTree\WooCommerce\SDK\HttpResponse;
use Psr\Container\ContainerInterface;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class SDKModule implements ExecutableModule, ServiceModule
{
    use ModuleClassNameIdTrait;

    public function services(): array
    {
        return [
            'SDK.HttpResponse' => static function (): HttpResponse {
                return new HttpResponse();
            },
        ];
    }

    public function run(ContainerInterface $container): bool
    {
        return true;
    }
}
