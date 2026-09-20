<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\ExchangeRateProvider;

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

    public function rate(string $from, string $to): ?float
    {
        $from = mb_strtoupper($from);
        $to = mb_strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        /** @var array<string, mixed> $rates */
        $rates = config('commerce-support.currency.exchange_rates.rates', []);

        $fromRate = $this->unitsPerBase($rates, $from);
        $toRate = $this->unitsPerBase($rates, $to);

        if ($fromRate === null || $toRate === null) {
            return null;
        }

        return $toRate / $fromRate;
    }

    /**
     * @param  array<string, mixed>  $rates
     */
    private function unitsPerBase(array $rates, string $code): ?float
    {
        foreach ($rates as $key => $value) {
            if (mb_strtoupper((string) $key) === $code && is_numeric($value) && (float) $value > 0) {
                return (float) $value;
            }
        }

        return $code === $this->baseCurrency() ? 1.0 : null;
    }
}
