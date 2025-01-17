<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\Log;

use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\NullLogger;
use TapTree\WooCommerce\Settings\SettingsHelper;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class LogModule implements ServiceModule
{
    use ModuleClassNameIdTrait;

    private $loggerSource;

    /**
     * LogModule constructor.
     */
    public function __construct($loggerSource)
    {
        $this->loggerSource = $loggerSource;
    }

    public function services(): array
    {
        $source = $this->loggerSource;
        return [
            Logger::class => static function (ContainerInterface $container) use ($source): AbstractLogger {
                // Todo: provide settings module to maintain the admin interface where we can also enable debugging
                $settingsHelper = $container->get('settings.settings_helper');
                assert($settingsHelper instanceof SettingsHelper);

                $debugEnabled = $settingsHelper->isDebugEnabled();;

                if ($debugEnabled) {
                    return new WcPsrLoggerAdapter(\wc_get_logger(), $source);
                }
                return new NullLogger();
            },
        ];
    }
}
