<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use NumberFormatter;

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
     * @param  int  $cents  The amount in cents
     * @param  string  $currencyCode  ISO 4217 currency code
     * @param  string  $locale  Locale for formatting
     * @return string Formatted currency string
     */
    public static function format(int $cents, string $currencyCode = 'MYR', string $locale = 'en_US'): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency(self::toDollars($cents), $currencyCode);
    }
}
