<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Akaunting\Money\Currency;

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

    public static function formatMinor(int $amountInMinorUnits, ?string $currency = null, ?int $precision = null): string
    {
        $currency = self::normalizeCurrency($currency);

        return self::formatMinorWithScale(
            $amountInMinorUnits,
            self::precisionFor($currency),
            $currency,
            $precision,
        );
    }

    public static function formatMinorWithScale(
        int $amountInMinorUnits,
        int $minorUnitPrecision,
        ?string $currency = null,
        ?int $precision = null,
    ): string {
        $currency = self::normalizeCurrency($currency);
        $precision ??= $minorUnitPrecision;
        $decimal = number_format($amountInMinorUnits / self::minorScale($minorUnitPrecision), $precision, '.', ',');

        return self::prefixSymbol(self::symbol($currency), $decimal);
    }

    public static function formatMinorWithCode(int $amountInMinorUnits, ?string $currency = null, ?int $precision = null): string
    {
        $currency = self::normalizeCurrency($currency);

        return self::decimalFromMinor($amountInMinorUnits, $currency, $precision) . ' ' . mb_strtoupper($currency);
    }

    public static function formatMajor(int $amountInMajorUnits, ?string $currency = null, ?int $precision = null): string
    {
        $currency = self::normalizeCurrency($currency);
        $currencyPrecision = self::precisionFor($currency);

        if ($precision !== null && $precision !== $currencyPrecision) {
            return self::symbol($currency) . self::decimalFromMajor($amountInMajorUnits, $currency, $precision);
        }

        return self::formatMinor($amountInMajorUnits * self::minorScale($currencyPrecision), $currency, $precision);
    }

    public static function majorToMinor(int $amountInMajorUnits, ?string $currency = null): int
    {
        $currency = self::normalizeCurrency($currency);

        return $amountInMajorUnits * self::minorScale(self::precisionFor($currency));
    }

    public static function formatMajorWithCode(int $amountInMajorUnits, ?string $currency = null, ?int $precision = null): string
    {
        $currency = self::normalizeCurrency($currency);

        return self::decimalFromMajor($amountInMajorUnits, $currency, $precision) . ' ' . mb_strtoupper($currency);
    }

    public static function decimalFromMinor(int $amountInMinorUnits, ?string $currency = null, ?int $precision = null): string
    {
        $currency = self::normalizeCurrency($currency);
        $currencyPrecision = self::precisionFor($currency);
        $precision ??= $currencyPrecision;

        return number_format($amountInMinorUnits / self::minorScale($currencyPrecision), $precision, '.', ',');
    }

    public static function decimalFromMajor(int $amountInMajorUnits, ?string $currency = null, ?int $precision = null): string
    {
        $currency = self::normalizeCurrency($currency);
        $precision ??= self::precisionFor($currency);

        return number_format($amountInMajorUnits, $precision, '.', ',');
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

    private static function prefixSymbol(string $symbol, string $decimal): string
    {
        if (str_starts_with($decimal, '-')) {
            return '-' . $symbol . mb_substr($decimal, 1);
        }

        return $symbol . $decimal;
    }

    private static function minorScale(int $precision): int
    {
        return (int) (10 ** $precision);
    }
}
