<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Settings;

use Spatie\LaravelSettings\Settings;

class ExchangeRateSettings extends Settings
{
    /**
     * Reporting base currency (ISO 4217 code).
     */
    public string $base;

    /**
     * Units per one base unit, e.g. ['MYR' => 4.7] with base USD.
     */
    public array $rates;

    /**
     * Dated snapshots overlaying current rates for historical conversion.
     */
    public array $history;

    public static function group(): string
    {
        return 'commerce-exchange-rates';
    }
}
