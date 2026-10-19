<?php

declare(strict_types=1);

namespace TapTree\WooCommerce\Settings;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Decides whether a per-payment project contribution may be shown to shoppers.
 * The thank-you page is the only consumer surface: it calls renderable() and
 * shows exactly what it returns. The payment-method chooser (checkout and
 * order-pay page) and the method description never show a figure (decision
 * register umweltaussagen-uwg-2026.md, rows "Was zeigt der Checkout?" and
 * "Flächen").
 *
 * Three gates, all required:
 *  1. Platform: the TapTree API delivers a figure at all. While the capability is
 *     off platform-wide, payment.impact is null.
 *  2. Merchant: the gateway setting 'show_impact' is 'yes' (default 'no').
 *  3. Unit: the figure's unit is on CONSUMER_UNITS, the allow-list TapTree ships
 *     with the plugin. There is deliberately no filter, option or constant that
 *     extends it: a unit enters the list only together with a vetted text.
 *
 * Why the list is empty: since 27 September 2026, UWG Anhang Nr. 4c bans telling
 * consumers that a product or payment has a neutral, reduced or positive climate
 * effect on the basis of offsets, and Nr. 4a bans generic environmental claims.
 * A CO₂ figure attached to a payment is exactly that, so a greenhouse-gas unit
 * such as 'g CO2' (the only unit the API has delivered so far) is rejected by the
 * guard whatever the list says. A contribution unit such as a forest area or a
 * number of trees may be added once its text says what is financed, by whom,
 * where and for how long, once TapTree has bought contingents in that unit, and
 * as long as the sum of all allocations stays within them.
 */
final class ConsumerImpact
{
    /**
     * Allow-list: unit exactly as the API delivers it => German specification text.
     * The text is an sprintf format with a single %s, the formatted figure; nothing
     * else is interpolated, and it must not present the figure as a compensation of
     * the purchase. The text must put „etwa" directly before %s: the figure is the
     * value rounded down, and the code never adds „etwa" itself. When adding a unit,
     * add its text to languages/*.pot as well.
     *
     * @var array<string, string>
     */
    public const CONSUMER_UNITS = [];

    // Mirrors GREENHOUSE_GAS_UNIT in taptree-payment/services/checkout/src/lib/consumer-impact.ts; change both or neither.
    private const GREENHOUSE_GAS_UNIT = '/CO\s?[2₂]|kohlendioxid|carbon|treibhausgas|greenhouse/iu';

    private const TEXT_DOMAIN = 'taptree-payments-for-woocommerce';

    /**
     * Units on CONSUMER_UNITS that are not greenhouse-gas units: the only units
     * callers may show or name.
     *
     * @return string[]
     */
    public static function allowedUnits(): array
    {
        return array_values(array_filter(array_keys(self::CONSUMER_UNITS), [self::class, 'isAllowedUnit']));
    }

    /** True when at least one unit may be shown. */
    public static function hasAllowedUnits(): bool
    {
        return self::allowedUnits() !== [];
    }

    public static function isAllowedUnit(string $unit): bool
    {
        // preg_match returns false on a regex error (e.g. invalid UTF-8); only 0 passes, so an error rejects too.
        return array_key_exists($unit, self::CONSUMER_UNITS)
            && preg_match(self::GREENHOUSE_GAS_UNIT, $unit) === 0;
    }

    /**
     * Returns what may be shown to a shopper for the given API figure, or null.
     *
     * @param object|null $impact        Figure as delivered by the API: {value: string|number, unit: string}.
     *                                   Anything else is treated as "no figure".
     * @param bool        $merchantOptIn The gateway's 'show_impact' setting.
     * @return array{value: float, unit: string, figure: string, text: string}|null
     *         null unless the merchant opted in, the unit is allow-listed and the value
     *         is at least 1. 'figure' is the value rounded down to a whole number in
     *         German number format (never up: the figure must not overstate the
     *         contribution), 'text' the specification text with the figure filled in.
     */
    public static function renderable($impact, bool $merchantOptIn): ?array
    {
        if (!$merchantOptIn || !is_object($impact)) {
            return null;
        }

        $unit = isset($impact->unit) && is_string($impact->unit) ? trim($impact->unit) : '';
        if ($unit === '' || !self::isAllowedUnit($unit)) {
            return null;
        }

        if (!isset($impact->value) || !is_numeric($impact->value)) {
            return null;
        }

        $value = (float) $impact->value;
        if (!is_finite($value) || $value < 1) {
            return null;
        }

        $figure = number_format(floor($value), 0, ',', '.');

        return [
            'value' => $value,
            'unit' => $unit,
            'figure' => $figure,
            'text' => sprintf(__(self::CONSUMER_UNITS[$unit], self::TEXT_DOMAIN), $figure),
        ];
    }
}
