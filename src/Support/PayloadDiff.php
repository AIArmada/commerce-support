<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use UnitEnum;

/**
 * Compare array payloads the way forms and audits do.
 *
 * Values normalize before comparison (enums to their value or name, dates to
 * ATOM strings, `Arrayable` to arrays, associative arrays key-sorted, numeric
 * `*_id` strings to integers), so Livewire state, audit snapshots, and
 * activity properties compare by meaning rather than by PHP type.
 */
final class PayloadDiff
{
    /**
     * Return the entries of `$state` whose value differs from `$original`.
     *
     * Only keys present in `$original` are considered; keys missing from the
     * original are ignored rather than reported as changes.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $original
     * @return array<string, mixed>
     */
    public static function changed(array $state, array $original): array
    {
        $changes = [];

        foreach ($state as $key => $value) {
            if (! array_key_exists($key, $original)) {
                continue;
            }

            if (self::equal($value, $original[$key], $key)) {
                continue;
            }

            $changes[$key] = $value;
        }

        return $changes;
    }

    public static function equal(mixed $left, mixed $right, string | int | null $key = null): bool
    {
        return self::normalize($left, $key) === self::normalize($right, $key);
    }

    private static function normalize(mixed $value, string | int | null $key = null): mixed
    {
        if ($value instanceof Arrayable) {
            return self::normalizeArray($value->toArray());
        }

        if (is_array($value)) {
            return self::normalizeArray($value);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_string($key) && str_ends_with($key, '_id') && is_string($value) && preg_match('/^[1-9]\d*$/', $value) === 1) {
            return (int) $value;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || is_string($value) || $value === null) {
            return $value;
        }

        if (is_object($value)) {
            return method_exists($value, '__toString')
                ? (string) $value
                : (self::jsonEncode($value) ?? '[object]');
        }

        return (string) $value;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function normalizeArray(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                static fn (mixed $item): mixed => self::normalize($item),
                $value,
            );
        }

        ksort($value);

        $normalized = [];

        foreach ($value as $arrayKey => $item) {
            $normalized[$arrayKey] = self::normalize($item, $arrayKey);
        }

        return $normalized;
    }

    private static function jsonEncode(mixed $value): ?string
    {
        $encoded = json_encode($value);

        return is_string($encoded) ? $encoded : null;
    }
}
