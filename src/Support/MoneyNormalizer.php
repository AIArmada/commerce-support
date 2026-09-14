<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use NumberFormatter;
use RuntimeException;

/**
 * Centralized money normalization for all commerce packages.
 *
 * All monetary values are stored as integer minor units to avoid floating-point
 * precision issues. This class keeps already-normalized minor-unit values typed
 * as integers.
 */
final class MoneyNormalizer
{
    /**
     * Assert that a value is already represented as integer minor units.
     */
    public static function toCents(int $price): int
    {
        return $price;
    }

    /**
     * Convert cents to a decimal dollar amount.
     *
     * Display-only helper. Never use the result for persistence or
     * calculation — keep monetary math in integer minor units.
     *
     * @param  int  $cents  The amount in cents
     * @return float The amount in dollars (e.g., 1999 → 19.99)
     */
    public static function toDollars(int $cents): float
    {
        return $cents / 100;
    }

    /**
     * Format cents as a currency string.
     *
     * The decimal value is computed with exact integer math; only the final
     * handoff to the locale formatter (which takes a float) crosses into
     * floating point.
     *
     * @param  int  $cents  The amount in cents
     * @param  string  $currencyCode  ISO 4217 currency code
     * @param  string  $locale  Locale for formatting
     * @return string Formatted currency string
     */
    public static function format(int $cents, string $currencyCode = 'MYR', string $locale = 'en_US'): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        $formatted = $formatter->formatCurrency((float) self::decimalString($cents, 2), $currencyCode);

        if ($formatted === false) {
            throw new RuntimeException(sprintf(
                'Currency formatting failed for %s in locale %s: %s',
                $currencyCode,
                $locale,
                $formatter->getErrorMessage(),
            ));
        }

        return $formatted;
    }

    private static function decimalString(int $minor, int $precision): string
    {
        $negative = $minor < 0;
        $abs = abs($minor);
        $scale = 10 ** $precision;
        $whole = intdiv($abs, $scale);
        $fraction = mb_str_pad((string) ($abs % $scale), $precision, '0', STR_PAD_LEFT);

        $decimal = $precision > 0 ? $whole . '.' . $fraction : (string) $whole;

        return $negative ? '-' . $decimal : $decimal;
    }
}
