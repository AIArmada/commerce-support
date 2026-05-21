<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Akaunting\Money\Currency;
use OutOfBoundsException;

/**
 * Shared money formatting helpers for all commerce packages.
 *
 * Monetary values should still be stored and aggregated in minor units,
 * but every user-facing money string should flow through this formatter.
 */
final class MoneyFormatter
{
    /**
     * @var array<string, string>
     */
    private const CURRENCY_ALIASES = [
        'RM' => 'MYR',
        '$' => 'USD',
        '€' => 'EUR',
        '£' => 'GBP',
        '¥' => 'JPY',
        'S$' => 'SGD',
        'A$' => 'AUD',
        'C$' => 'CAD',
    ];

    /**
     * @var array<string, string>
     */
    private const SYMBOL_OVERRIDES = [
        'SGD' => 'S$',
        'AUD' => 'A$',
        'CAD' => 'C$',
    ];

    public static function formatMinor(int | float | string $amountInMinorUnits, ?string $currency = null, ?int $precision = null): string
    {
        $currency = self::normalizeCurrency($currency);
        $minor = self::normalizeMinor($amountInMinorUnits);
        $currencyPrecision = self::precisionFor($currency);

        if ($precision !== null && $precision !== $currencyPrecision) {
            return self::symbol($currency) . self::decimalFromMinor($minor, $currency, $precision);
        }

        if (isset(self::SYMBOL_OVERRIDES[$currency])) {
            return self::symbol($currency) . self::decimalFromMinor($minor, $currency, $precision);
        }

        try {
            return money($minor, $currency, false)->format();
        } catch (OutOfBoundsException) {
            return mb_strtoupper($currency) . ' ' . self::decimalFromMinor($minor, $currency, $precision);
        }
    }

    public static function formatMinorWithCode(int | float | string $amountInMinorUnits, ?string $currency = null, ?int $precision = null): string
    {
        $currency = self::normalizeCurrency($currency);

        return self::decimalFromMinor($amountInMinorUnits, $currency, $precision) . ' ' . mb_strtoupper($currency);
    }

    public static function formatMajor(int | float | string $amountInMajorUnits, ?string $currency = null, ?int $precision = null): string
    {
        $currency = self::normalizeCurrency($currency);
        $currencyPrecision = self::precisionFor($currency);

        if ($precision !== null && $precision !== $currencyPrecision) {
            return self::symbol($currency) . self::decimalFromMajor($amountInMajorUnits, $currency, $precision);
        }

        return self::formatMinor(self::majorToMinor($amountInMajorUnits, $currencyPrecision), $currency, $precision);
    }

    public static function formatMajorWithCode(int | float | string $amountInMajorUnits, ?string $currency = null, ?int $precision = null): string
    {
        $currency = self::normalizeCurrency($currency);

        return self::decimalFromMajor($amountInMajorUnits, $currency, $precision) . ' ' . mb_strtoupper($currency);
    }

    public static function decimalFromMinor(int | float | string $amountInMinorUnits, ?string $currency = null, ?int $precision = null): string
    {
        $currency = self::normalizeCurrency($currency);
        $minor = self::normalizeMinor($amountInMinorUnits);
        $currencyPrecision = self::precisionFor($currency);
        $precision ??= $currencyPrecision;

        return number_format($minor / self::minorScale($currencyPrecision), $precision, '.', ',');
    }

    public static function decimalFromMajor(int | float | string $amountInMajorUnits, ?string $currency = null, ?int $precision = null): string
    {
        $currency = self::normalizeCurrency($currency);
        $precision ??= self::precisionFor($currency);

        return number_format(self::normalizeMajor($amountInMajorUnits), $precision, '.', ',');
    }

    public static function symbol(?string $currency = null): string
    {
        $currency = self::normalizeCurrency($currency);

        if (isset(self::SYMBOL_OVERRIDES[$currency])) {
            return self::SYMBOL_OVERRIDES[$currency];
        }

        $currencies = Currency::getCurrencies();
        $symbol = $currencies[$currency]['symbol'] ?? null;

        if (is_string($symbol) && $symbol !== '') {
            return $symbol;
        }

        $fallback = function_exists('currency_symbol')
            ? currency_symbol($currency)
            : mb_strtoupper($currency);

        if ($fallback === mb_strtoupper($currency)) {
            return $fallback . ' ';
        }

        return $fallback;
    }

    public static function precisionFor(?string $currency = null): int
    {
        $currency = self::normalizeCurrency($currency);
        $currencies = Currency::getCurrencies();
        $precision = $currencies[$currency]['precision'] ?? null;

        return is_int($precision) ? $precision : 2;
    }

    private static function normalizeCurrency(?string $currency): string
    {
        $normalized = mb_strtoupper(mb_trim($currency ?? ''));

        if ($normalized === '') {
            $normalized = (string) config('commerce-support.currency.default', 'MYR');
            $normalized = mb_strtoupper(mb_trim($normalized));
        }

        return self::CURRENCY_ALIASES[$normalized] ?? $normalized;
    }

    private static function normalizeMinor(int | float | string $amountInMinorUnits): int
    {
        if (is_int($amountInMinorUnits)) {
            return $amountInMinorUnits;
        }

        if (is_float($amountInMinorUnits)) {
            return (int) round($amountInMinorUnits);
        }

        $normalized = self::normalizeNumericString($amountInMinorUnits);

        return $normalized === '' ? 0 : (int) round((float) $normalized);
    }

    private static function normalizeMajor(int | float | string $amountInMajorUnits): float
    {
        if (is_int($amountInMajorUnits) || is_float($amountInMajorUnits)) {
            return (float) $amountInMajorUnits;
        }

        $normalized = self::normalizeNumericString($amountInMajorUnits);

        return $normalized === '' ? 0.0 : (float) $normalized;
    }

    private static function normalizeNumericString(string $value): string
    {
        return str_replace([',', ' '], '', mb_trim($value));
    }

    private static function majorToMinor(int | float | string $amountInMajorUnits, int $precision): int
    {
        return (int) round(self::normalizeMajor($amountInMajorUnits) * self::minorScale($precision));
    }

    private static function minorScale(int $precision): int
    {
        return (int) (10 ** $precision);
    }
}
