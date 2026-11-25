<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\ReferenceData\Actions;

use AIArmada\CommerceSupport\Features\ReferenceData\Models\Timezone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class SeedTimezonesAction
{
    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function execute(): array
    {
        $timezones = require dirname(__DIR__, 4) . '/resources/data/timezones.php';

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
            $existingNames = array_fill_keys(DB::table($table)
                ->whereIn('name', array_keys($valid))
                ->pluck('name')
                ->all(), true);

            $inserts = [];

            foreach ($valid as $name => $row) {
                if (isset($existingNames[$name])) {
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
                $result['created'] += DB::table($table)->insertOrIgnore($chunk);
            }

            return $result;
        });
    }
}
