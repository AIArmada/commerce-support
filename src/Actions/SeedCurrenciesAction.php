<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use AIArmada\CommerceSupport\Models\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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

        $table = (new Currency)->getTable();
        $now = CarbonImmutable::now()->toIso8601ZuluString();
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        /** @var array<string, array<string, mixed>> $valid */
        $valid = [];

        foreach ($currencies as $row) {
            if (! is_array($row) || ! isset($row['code'], $row['name'])) {
                $result['skipped']++;

                continue;
            }

            $valid[(string) $row['code']] = [
                'code' => $row['code'],
                'name' => $row['name'],
                'symbol' => $row['symbol'] ?? null,
                'symbol_native' => $row['symbol_native'] ?? null,
                'precision' => $row['precision'] ?? 2,
                'symbol_first' => $row['symbol_first'] ?? null,
                'decimal_mark' => $row['decimal_mark'] ?? null,
                'thousands_separator' => $row['thousands_separator'] ?? null,
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

                if (self::differs($current, $row)) {
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
                DB::table($table)->upsert($updates, ['code'], [
                    'name', 'symbol', 'symbol_native', 'precision',
                    'symbol_first', 'decimal_mark', 'thousands_separator',
                    'updated_at',
                ]);
            }

            $result['created'] = count($inserts);
            $result['updated'] = count($updates);

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function differs(object $current, array $row): bool
    {
        foreach ($row as $column => $value) {
            if (self::normalize($current->{$column} ?? null) !== self::normalize($value)) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_bool($value)) {
            return (int) $value;
        }

        return $value;
    }
}
