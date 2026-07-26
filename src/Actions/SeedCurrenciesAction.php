<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use AIArmada\CommerceSupport\Models\Currency;
use RuntimeException;

class SeedCurrenciesAction
{
    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function execute(): array
    {
        $currencies = require __DIR__ . '/../../resources/data/currencies.php';

        if (! is_array($currencies)) {
            throw new RuntimeException('Currency data file must return an array.');
        }

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($currencies as $row) {
            if (! is_array($row) || ! isset($row['code'], $row['name'])) {
                $result['skipped']++;

                continue;
            }

            $currency = Currency::query()->firstOrNew(['code' => $row['code']]);
            $wasExisting = $currency->exists;
            $currency->fill($row);

            if (! $currency->isDirty()) {
                $result['skipped']++;

                continue;
            }

            $currency->save();
            $result[$wasExisting ? 'updated' : 'created']++;
        }

        return $result;
    }
}
