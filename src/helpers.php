<?php

declare(strict_types=1);
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Schema;

if (! function_exists('commerce_json_column_type')) {
    /**
     * Resolve the preferred JSON column type for a package.
     *
     * @param  string|null  $packageKey  e.g. 'vouchers', 'chip', 'docs' (used to read {PKG}_JSON_COLUMN_TYPE)
     * @param  string  $default  Fallback when no package configuration is available
     */
    function commerce_json_column_type(?string $packageKey = null, string $default = 'jsonb'): string
    {
        $global = getenv('COMMERCE_JSON_COLUMN_TYPE');

        if ($packageKey !== null) {
            $normalizedPackageKey = str_replace('-', '_', $packageKey);
            $envKey = mb_strtoupper($normalizedPackageKey) . '_JSON_COLUMN_TYPE';
            $packageSpecific = getenv($envKey);

            if (is_string($packageSpecific) && $packageSpecific !== '') {
                return $packageSpecific;
            }
        }

        if (is_string($global) && $global !== '') {
            return $global;
        }

        if ($packageKey !== null) {
            $configured = config($packageKey . '.database.json_column_type');

            if (is_string($configured) && $configured !== '') {
                return $configured;
            }
        }

        return $default;
    }
}

if (! function_exists('commerce_schema_create_if_missing')) {
    /**
     * Create a table only when it does not already exist.
     *
     * @param  Closure(Blueprint): void  $callback
     */
    function commerce_schema_create_if_missing(string $table, Closure $callback): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, $callback);
    }
}

if (! function_exists('commerce_csrf_middleware')) {
    /**
     * Resolve the framework CSRF middleware across the supported framework variants.
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
