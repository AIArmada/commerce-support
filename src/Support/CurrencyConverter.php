<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\ExchangeRateProvider;

/**
 * Reporting-only currency conversion in integer minor units.
 *
 * Converted totals are approximations for display and aggregation; they must
 * never drive balances, payouts, or any other money movement. Totals refuse
 * partial conversion: when any leg lacks a rate the whole total is null so
 * callers show a per-currency breakdown instead of a misleading number.
 */
final class CurrencyConverter
{
    public function __construct(private readonly ExchangeRateProvider $rates) {}

    public function baseCurrency(): string
    {
        return $this->rates->baseCurrency();
    }

    public function convertMinor(int $amountMinor, string $from, string $to): ?int
    {
        $rate = $this->rates->rate($from, $to);

        if ($rate === null) {
            return null;
        }

        return (int) round($amountMinor * $rate);
    }

    /**
     * @param  array<string, int>  $amountsByCurrency
     */
    public function totalMinor(array $amountsByCurrency, ?string $to = null): ?int
    {
        $to ??= $this->baseCurrency();
        $total = 0;

        foreach ($amountsByCurrency as $currency => $amountMinor) {
            $converted = $this->convertMinor((int) $amountMinor, (string) $currency, $to);

            if ($converted === null) {
                return null;
            }

            $total += $converted;
        }

        return $total;
    }
}
