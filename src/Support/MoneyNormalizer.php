<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use InvalidArgumentException;
use NumberFormatter;

/**
 * Centralized money normalization for all commerce packages.
 *
 * All monetary values are stored as integer cents to avoid floating-point
 * precision issues. This class provides consistent conversion from various
 * input formats to cents.
 *
 * Input formats supported:
 * - int: treated as cents (returned as-is)
 * - float: treated as decimal dollars, converted to cents (e.g., 19.99 → 1999)
 * - string: sanitized and converted (e.g., "$19.99" → 1999, "1500" → 1500)
 * - null: returns 0
 */
final class MoneyNormalizer
{
    /**
     * Currency symbols to strip from string input.
     *
     * @var array<string>
     */
    private const CURRENCY_SYMBOLS = ['$', '€', '£', '¥', '₹', 'RM', '₱', '₩', '฿', '₫', '₪', '₨', 'R$', 'kr', 'zł'];

    /**
     * Normalize any price input to integer cents.
     *
     * @param  int|float|string|null  $price  The price input
     * @return int The price in cents
     *
     * @throws InvalidArgumentException If the price format is invalid
     */
    public static function toCents(int | float | string | null $price): int
    {
        if ($price === null) {
            return 0;
        }

        if (is_int($price)) {
            return $price;
        }

        if (is_float($price)) {
            return self::floatToCents($price);
        }

        return self::stringToCents($price);
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
    public static function format(int $cents, string $currencyCode = 'USD', string $locale = 'en_US'): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency(self::toDollars($cents), $currencyCode);
    }

    /**
     * Convert float (dollars) to cents with proper rounding.
     */
    private static function floatToCents(float $price): int
    {
        if (! is_finite($price)) {
            throw new InvalidArgumentException('Price must be a finite number');
        }

        return (int) round($price * 100);
    }

    /**
     * Convert string price to cents.
     *
     * Handles formats like:
     * - "19.99" → 1999 (dollars with decimal)
     * - "1999" → 1999 (cents without decimal)
     * - "$19.99" → 1999 (with currency symbol)
     * - "1,999.99" → 199999 (with thousands separator)
     */
    private static function stringToCents(string $price): int
    {
        $sanitized = self::sanitizeString($price);

        if ($sanitized === '') {
            return 0;
        }

        if (! is_numeric($sanitized)) {
            throw new InvalidArgumentException("Invalid price format: {$price}");
        }

        $floatValue = (float) $sanitized;

        if (! is_finite($floatValue)) {
            throw new InvalidArgumentException("Invalid price value: {$price}");
        }

        // If the value contains a decimal point, treat it as dollars
        if (str_contains($sanitized, '.')) {
            return (int) round($floatValue * 100);
        }

        // Otherwise, treat as cents
        return (int) $sanitized;
    }

    /**
     * Sanitize string input by removing currency symbols and formatting.
     */
    private static function sanitizeString(string $price): string
    {
        // Remove whitespace
        $sanitized = mb_trim($price);

        // Remove currency symbols
        $sanitized = str_replace(self::CURRENCY_SYMBOLS, '', $sanitized);

        // Remove thousands separators (commas)
        $sanitized = str_replace(',', '', $sanitized);

        // Remove spaces
        $sanitized = str_replace(' ', '', $sanitized);

        return $sanitized;
    }
}
