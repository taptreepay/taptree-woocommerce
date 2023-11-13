<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace TapTree\WooCommerce\Api;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use TapTree\WooCommerce\Api\TapTreeApi;
use Psr\Container\ContainerInterface;
use TapTree\WooCommerce\Settings\SettingsHelper;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class ApiModule implements ExecutableModule, ServiceModule
{
    use ModuleClassNameIdTrait;

    public function services(): array
    {
        return [
            'Api.taptree_api' => static function (ContainerInterface $container): TapTreeApi {
                $settingsHelper = $container->get('settings.settings_helper');
                assert($settingsHelper instanceof SettingsHelper);

                return new TapTreeApi($settingsHelper);
            },
        ];
    }

    public function run(ContainerInterface $container): bool
    {
        return true;
    }
}
