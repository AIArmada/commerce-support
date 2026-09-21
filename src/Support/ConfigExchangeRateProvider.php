<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\ExchangeRateProvider;
use DateTimeInterface;

/**
 * Static exchange rates from `commerce-support.currency.exchange_rates`.
 *
 * Rates are units per one base unit (for example, with base USD,
 * `['MYR' => 4.7]` prices one USD at 4.70 MYR). The base implies 1.0 when
 * unlisted. Unknown, zero, and negative rates resolve to null so callers
 * fail soft instead of converting with nonsense.
 */
final class ConfigExchangeRateProvider implements ExchangeRateProvider
{
    public function baseCurrency(): string
    {
        return mb_strtoupper((string) config('commerce-support.currency.exchange_rates.base', 'USD'));
    }

    public function rate(string $from, string $to, ?DateTimeInterface $asOf = null): ?float
    {
        /** @var array<string, mixed> $rates */
        $rates = config('commerce-support.currency.exchange_rates.rates', []);

        /** @var array<string, mixed> $history */
        $history = config('commerce-support.currency.exchange_rates.history', []);

        return ExchangeRateResolver::rate($this->baseCurrency(), $rates, $history, $from, $to, $asOf);
    }
}
