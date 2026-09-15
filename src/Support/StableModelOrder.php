<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Deterministic ordering for batch model processing.
 *
 * Records sort by `created_at` ascending with the primary key as the tiebreak,
 * so batch sync passes assign stable outcomes (the oldest record keeps the
 * cleanest slug, the lowest sequence number, and so on) regardless of the
 * order the records were loaded in.
 */
final class StableModelOrder
{
    /**
     * Run a callback over each record in stable order.
     *
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $records
     * @param  callable(TModel): bool  $sync  Return whether the record changed.
     */
    public static function sync(Collection $records, callable $sync): bool
    {
        $didChange = false;

        foreach (self::sort($records) as $record) {
            $didChange = $sync($record) || $didChange;
        }

        return $didChange;
    }

    /**
     * Find the 1-based position of a record within the stable order.
     *
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $records
     */
    public static function sequence(Collection $records, int | string $modelKey): ?int
    {
        $existingIndex = self::sort($records)->search(
            static fn (Model $record): bool => (string) $record->getKey() === (string) $modelKey,
        );

        if (! is_int($existingIndex)) {
            return null;
        }

        return $existingIndex + 1;
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $records
     * @return Collection<int, TModel>
     */
    public static function sort(Collection $records): Collection
    {
        return $records
            ->sort(static function (Model $left, Model $right): int {
                $leftCreatedAt = self::createdAtTimestamp($left);
                $rightCreatedAt = self::createdAtTimestamp($right);

                if ($leftCreatedAt !== $rightCreatedAt) {
                    return $leftCreatedAt <=> $rightCreatedAt;
                }

                return strcmp((string) $left->getKey(), (string) $right->getKey());
            })
            ->values();
    }

    private static function createdAtTimestamp(Model $model): int
    {
        $createdAt = $model->getAttribute('created_at');

        return $createdAt instanceof DateTimeInterface
            ? $createdAt->getTimestamp()
            : 0;
    }
}
