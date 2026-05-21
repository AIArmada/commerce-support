<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Support\MoneyFormatter;

/**
 * Provides money formatting capabilities for models using Akaunting Money.
 *
 * All monetary values are stored as integer minor units (cents for USD, sen for MYR, etc).
 * Akaunting Money handles currency-specific precision and locale formatting automatically:
 * - USD/MYR: 10000 minor units = $100.00 / RM100.00 (precision=2)
 * - EUR: 10000 minor units = €100,00 (European decimal format)
 * - JPY: 10000 minor units = ¥10,000 (precision=0, no subunits)
 *
 * Expects the model to have a `currency` property.
 */
trait FormatsMoney
{
    /**
     * Format an amount in minor units as a currency string using Akaunting Money.
     *
     * @param  int  $amountInMinorUnits  The amount in minor units (cents, sen, etc.)
     * @param  string|null  $currency  Optional currency override (uses model's currency property if null)
     * @return string Formatted money string (e.g., "RM100.00", "$5.00", "¥10,000")
     */
    protected function formatMoney(int $amountInMinorUnits, ?string $currency = null): string
    {
        return MoneyFormatter::formatMinor($amountInMinorUnits, $currency ?? $this->currency ?? $this->getDefaultCurrency());
    }

    /**
     * Get the default currency code.
     */
    protected function getDefaultCurrency(): string
    {
        return (string) config('commerce-support.currency.default', 'MYR');
    }
}
