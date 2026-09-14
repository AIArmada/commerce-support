<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SeedLanguagesAction
{
    public function execute(): array
    {
        $languages = require __DIR__ . '/../../resources/data/languages.php';

        if (! is_array($languages)) {
            throw new RuntimeException('Language data file must return an array.');
        }

        $table = config('commerce-support.database.tables.languages', 'languages');
        $now = CarbonImmutable::now()->toIso8601ZuluString();
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        /** @var array<string, array<string, mixed>> $valid */
        $valid = [];

        foreach ($languages as $row) {
            if (! isset($row['code'], $row['name'])) {
                $result['skipped']++;

                continue;
            }

            $valid[(string) $row['code']] = [
                'code' => $row['code'],
                'name' => $row['name'],
                'native' => $row['native'] ?? null,
                'dir' => $row['dir'] ?? 'ltr',
            ];
        }

        if ($valid === []) {
            return $result;
        }

        return DB::transaction(function () use ($table, $now, $valid, $result): array {
            $existing = DB::table($table)->whereIn('code', array_keys($valid))->get()->keyBy('code');

            $inserts = [];
            $updates = [];

            foreach ($valid as $code => $row) {
                $current = $existing->get($code);

                if ($current === null) {
                    $inserts[] = array_merge($row, [
                        'id' => (string) str()->uuid(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    continue;
                }

                if (
                    ($current->name ?? null) !== $row['name']
                    || ($current->native ?? null) !== $row['native']
                    || ($current->dir ?? null) !== $row['dir']
                ) {
                    // id is required: NOT NULL is checked before ON CONFLICT resolution.
                    $updates[] = array_merge(['id' => $current->id], $row, ['updated_at' => $now]);
                } else {
                    $result['skipped']++;
                }
            }

            foreach (array_chunk($inserts, 500) as $chunk) {
                DB::table($table)->insert($chunk);
            }

            if ($updates !== []) {
                DB::table($table)->upsert($updates, ['code'], ['name', 'native', 'dir', 'updated_at']);
            }

            $result['created'] = count($inserts);
            $result['updated'] = count($updates);

            return $result;
        });
    }
}
