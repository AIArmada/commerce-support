<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

/**
 * Exchange rates for reporting-only currency conversion.
 *
 * Implementations may read static config, a database table, or a live
 * provider. Converted totals are approximations for display and aggregation;
 * never feed them into money movement such as balances or payouts.
 */
interface ExchangeRateProvider
{
    /**
     * Reporting base currency (ISO 4217 code).
     */
    public function baseCurrency(): string;

    /**
     * Units of $to per one unit of $from, or null when no rate is known.
     */
    public function rate(string $from, string $to): ?float;
}
