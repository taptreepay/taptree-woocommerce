<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!function_exists('has_block')) {
    /**
     * Check if the current page has block
     *
     * @since WooCommerce 5.0
     * @return bool
     */
    function has_block($blockName)
    {
        return false;
    }
}

if (!function_exists('untrailingslashit')) {
    /**
     * @since WooCommerce 2.2.0
     * @param string $string
     * @return string
     */
    function untrailingslashit($string)
    {
        return rtrim($string, '/');
    }
}

function taptreeWooSession()
{
    return WC()->session;
}
