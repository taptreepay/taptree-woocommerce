<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\Notice;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Interface NoticeInterface
 *
 * @package TapTree\WC\Notice
 */
interface NoticeInterface
{

    /**
     * @param string $level class to apply: ex. 'notice-error'
     * @param string $message translated message
     *
     * @return mixed
     */
    public function addNotice($level, $message);
}
