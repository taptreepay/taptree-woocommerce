<?php

/**
 * Plugin Name:       TapTree Payments for WooCommerce
 * Plugin URI:        https://github.com/taptreepay/taptree-woocommerce
 * Description:       Accept payments and protect the climate in WooCommerce with the official TapTree WooCommerce plugin
 * Version:           2.0.8
 * Author:            TapTree
 * Author URI:        https://taptree.org/
 * Requires at least: 6.0
 * Text Domain:       taptree-payments-for-woocommerce
 * License:           GPLv3 or later
 * WC requires at least: 7.0
 * Requires PHP:      7.3
 */

declare(strict_types=1);

namespace TapTree\WooCommerce;

use Inpsyde\Modularity\Package;
use Inpsyde\Modularity\Properties\PluginProperties;
use TapTree\WooCommerce\Gateway\GatewayModule;
use TapTree\WooCommerce\Log\LogModule;
use TapTree\WooCommerce\Notice\NoticeModule;
use TapTree\WooCommerce\Settings\SettingsModule;
use TapTree\WooCommerce\Shared\SharedModule;
use TapTree\WooCommerce\Api\ApiModule;
use TapTree\WooCommerce\SDK\SDKModule;
use Throwable;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

//$is_wc_version_greater_than_6_6 = defined( WC_VERSION ) && version_compare( WC_VERSION, '6.6', '>=' );
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

/**
 * Display an error message in the WP admin.
 *
 * @param string $message The message content
 *
 * @return void
 */
function errorNotice(string $message)
{
    add_action(
        'all_admin_notices',
        static function () use ($message) {
            $class = 'notice notice-error';
            printf(
                '<div class="%1$s"><p>%2$s</p></div>',
                esc_attr($class),
                wp_kses_post($message)
            );
        }
    );
}

/**
 * Handle any exception that might occur during plugin setup.
 *
 * @param Throwable $throwable The Exception
 *
 * @return void
 */
function handleException(Throwable $throwable)
{
    errorNotice(
        sprintf(
            '<strong>Error:</strong> %s <br><pre>%s</pre>',
            $throwable->getMessage(),
            $throwable->getTraceAsString()
        )
    );
}


function initialize()
{
    try {
        require_once __DIR__ . '/include/functions.php';
        require_once __DIR__ . '/vendor/autoload.php';

        $woo_plugin_path = WC_PLUGIN_FILE;
        // Test to see if WooCommerce is active (including network activated).
        if (
            !(in_array($woo_plugin_path, wp_get_active_and_valid_plugins())
                || in_array($woo_plugin_path, wp_get_active_network_plugins()))
        ) {
            return;
        }

        $properties = PluginProperties::new(__FILE__);
        $bootstrap = Package::new($properties);

        $modules = [
            new NoticeModule(),
            new SharedModule(),
            new SDKModule(),
            new ApiModule(),
            new SettingsModule(),
            new LogModule('taptree-payments-for-woocommerce-'),
            new GatewayModule()
        ];

        $modules = apply_filters('taptree_wc_plugin_modules', $modules);
        $bootstrap->boot(...$modules);
    } catch (\Throwable $throwable) {
        handleException($throwable);
    } catch (Throwable $throwable) {
        handleException($throwable);
    }
}

function register_assets()
{
    wp_register_style('taptree-common', plugins_url('/assets/css/common.css', __FILE__), false, '1.0.0', 'all');
}

add_action('plugins_loaded', __NAMESPACE__ . '\initialize');
add_action('init', __NAMESPACE__ . '\register_assets');
