<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use AIArmada\CommerceSupport\Models\Timezone;
use RuntimeException;

class SeedTimezonesAction
{
    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function execute(): array
    {
        $timezones = require __DIR__ . '/../../resources/data/timezones.php';

        if (! is_array($timezones)) {
            throw new RuntimeException('Timezone data file must return an array.');
        }

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($timezones as $row) {
            if (! is_array($row) || ! isset($row['name'])) {
                $result['skipped']++;

                continue;
            }

            $timezone = Timezone::query()->firstOrNew(['name' => $row['name']]);
            $wasExisting = $timezone->exists;
            $timezone->fill($row);

            if (! $timezone->isDirty()) {
                $result['skipped']++;

                continue;
            }

            $timezone->save();
            $result[$wasExisting ? 'updated' : 'created']++;
        }

        return $result;
    }
}
