<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

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
        $now = now()->toIso8601ZuluString();
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($languages as $row) {
            if (! isset($row['code'], $row['name'])) {
                $skipped++;

                continue;
            }

            $existing = DB::table($table)->where('code', $row['code'])->first();

            if ($existing === null) {
                DB::table($table)->insert([
                    'id' => (string) str()->uuid(),
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'native' => $row['native'] ?? null,
                    'dir' => $row['dir'] ?? 'ltr',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $created++;
            } elseif (
                $existing->name !== $row['name']
                || $existing->native !== ($row['native'] ?? null)
                || $existing->dir !== ($row['dir'] ?? 'ltr')
            ) {
                DB::table($table)->where('code', $row['code'])->update([
                    'name' => $row['name'],
                    'native' => $row['native'] ?? null,
                    'dir' => $row['dir'] ?? 'ltr',
                    'updated_at' => $now,
                ]);
                $updated++;
            } else {
                $skipped++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }
}
