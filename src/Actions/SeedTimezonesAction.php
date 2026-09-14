<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use AIArmada\CommerceSupport\Models\Timezone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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

        $table = (new Timezone)->getTable();
        $now = CarbonImmutable::now()->toIso8601ZuluString();
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        /** @var array<string, array<string, mixed>> $valid */
        $valid = [];

        foreach ($timezones as $row) {
            if (! is_array($row) || ! isset($row['name'])) {
                $result['skipped']++;

                continue;
            }

            $valid[(string) $row['name']] = ['name' => $row['name']];
        }

        if ($valid === []) {
            return $result;
        }

        return DB::transaction(function () use ($table, $now, $valid, $result): array {
            $existingNames = DB::table($table)
                ->whereIn('name', array_keys($valid))
                ->pluck('name')
                ->all();

            $inserts = [];

            foreach ($valid as $name => $row) {
                if (in_array($name, $existingNames, true)) {
                    $result['skipped']++;

                    continue;
                }

                $inserts[] = array_merge($row, [
                    'id' => (string) str()->uuid(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (array_chunk($inserts, 500) as $chunk) {
                DB::table($table)->insert($chunk);
            }

            $result['created'] = count($inserts);

            return $result;
        });
    }
}
