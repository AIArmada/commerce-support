<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use DateTimeInterface;

/**
 * Pure exchange-rate math shared by every ExchangeRateProvider.
 *
 * Rates are units per one base unit (for example, with base USD,
 * `['MYR' => 4.7]` prices one USD at 4.70 MYR). The base implies 1.0 when
 * unlisted. Unknown, zero, and negative rates resolve to null so callers
 * fail soft instead of converting with nonsense.
 */
final class ExchangeRateResolver
{
    /**
     * Units of $to per one unit of $from, or null when no rate is known.
     *
     * @param  array<string, mixed>  $rates
     * @param  array<string, mixed>  $history
     */
    public static function rate(
        string $base,
        array $rates,
        array $history,
        string $from,
        string $to,
        ?DateTimeInterface $asOf = null,
    ): ?float {
        $base = mb_strtoupper($base);
        $from = mb_strtoupper($from);
        $to = mb_strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        $effective = self::ratesEffectiveAt($rates, $history, $asOf);

        $fromRate = self::unitsPerBase($effective, $base, $from);
        $toRate = self::unitsPerBase($effective, $base, $to);

        if ($fromRate === null || $toRate === null) {
            return null;
        }

        return $toRate / $fromRate;
    }

    /**
     * Current rates overlaid with every history snapshot on or before $asOf.
     *
     * @param  array<string, mixed>  $rates
     * @param  array<string, mixed>  $history
     * @return array<string, mixed>
     */
    private static function ratesEffectiveAt(array $rates, array $history, ?DateTimeInterface $asOf): array
    {
        if ($asOf === null) {
            return $rates;
        }

        $asOfDate = $asOf->format('Y-m-d');
        $snapshots = [];

        foreach ($history as $date => $snapshot) {
            if (is_array($snapshot) && (string) $date <= $asOfDate) {
                $snapshots[(string) $date] = $snapshot;
            }
        }

        ksort($snapshots);

        foreach ($snapshots as $snapshot) {
            foreach ($snapshot as $code => $value) {
                $rates[(string) $code] = $value;
            }
        }

        return $rates;
    }

    /**
     * @param  array<string, mixed>  $rates
     */
    private static function unitsPerBase(array $rates, string $base, string $code): ?float
    {
        foreach ($rates as $key => $value) {
            if (mb_strtoupper((string) $key) === $code && is_numeric($value) && (float) $value > 0) {
                return (float) $value;
            }
        }

        return $code === $base ? 1.0 : null;
    }
}
