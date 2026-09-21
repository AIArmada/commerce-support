<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\ExchangeRateProvider;
use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;
use DateTimeInterface;
use Throwable;

/**
 * Runtime-managed exchange rates with static config fallback.
 *
 * Reads the `commerce-exchange-rates` settings group (managed through the
 * Filament adapter) and falls back to `ConfigExchangeRateProvider` when
 * settings are unmigrated or unavailable, so standalone installs and
 * config-driven deployments keep working unchanged. Settings resolve
 * per call so long-lived workers always see current values.
 */
final class SettingsExchangeRateProvider implements ExchangeRateProvider
{
    public function baseCurrency(): string
    {
        try {
            return mb_strtoupper(app(ExchangeRateSettings::class)->base);
        } catch (Throwable) {
            return (new ConfigExchangeRateProvider)->baseCurrency();
        }
    }

    public function rate(string $from, string $to, ?DateTimeInterface $asOf = null): ?float
    {
        try {
            $settings = app(ExchangeRateSettings::class);

            return ExchangeRateResolver::rate(
                $settings->base,
                $settings->rates,
                $settings->history,
                $from,
                $to,
                $asOf,
            );
        } catch (Throwable) {
            return (new ConfigExchangeRateProvider)->rate($from, $to, $asOf);
        }
    }
}
