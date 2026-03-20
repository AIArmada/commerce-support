<?php

declare(strict_types=1);
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

if (! function_exists('commerce_json_column_type')) {
    /**
     * Resolve the preferred JSON column type for a package.
     *
     * @param  string|null  $packageKey  e.g. 'vouchers', 'chip', 'docs' (used to read {PKG}_JSON_COLUMN_TYPE)
     * @param  string  $default  Fallback when no env is set
     */
    function commerce_json_column_type(?string $packageKey = null, string $default = 'json'): string
    {
        $global = env('COMMERCE_JSON_COLUMN_TYPE');

        if ($packageKey !== null) {
            $envKey = mb_strtoupper($packageKey) . '_JSON_COLUMN_TYPE';
            $packageSpecific = env($envKey);

            if (is_string($packageSpecific) && $packageSpecific !== '') {
                return $packageSpecific;
            }
        }

        if (is_string($global) && $global !== '') {
            return $global;
        }

        return $default;
    }
}

if (! function_exists('commerce_csrf_middleware')) {
    /**
     * Resolve the framework CSRF middleware across Laravel 12 and 13.
     *
     * @return class-string
     */
    function commerce_csrf_middleware(): string
    {
        $preventRequestForgery = 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery';

        if (class_exists($preventRequestForgery)) {
            return $preventRequestForgery;
        }

        return VerifyCsrfToken::class;
    }
}

if (! function_exists('currency_symbol')) {
    /**
     * Get currency symbol for display in forms/tables.
     *
     * @param  string|null  $code  Currency code, defaults to config
     * @return string Currency symbol (RM, $, €, etc.)
     */
    function currency_symbol(?string $code = null): string
    {
        $code ??= config('commerce-support.currency.default', 'MYR');

        return match ($code) {
            'MYR' => 'RM',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'SGD' => 'S$',
            'AUD' => 'A$',
            default => mb_strtoupper($code),
        };
    }
}
