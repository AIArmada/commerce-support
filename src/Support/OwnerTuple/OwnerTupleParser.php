<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support\OwnerTuple;

use InvalidArgumentException;

final class OwnerTupleParser
{
    public static function fromTypeAndId(
        ?string $owner_type,
        string | int | null $owner_id,
        bool $allowMalformed = false,
    ): ParsedOwnerTuple {
        if ($owner_type === null && $owner_id === null) {
            return ParsedOwnerTuple::explicitGlobal();
        }

        if ($owner_type !== null && $owner_id !== null) {
            return ParsedOwnerTuple::owner($owner_type, $owner_id);
        }

        return self::malformed(
            owner_type: $owner_type,
            owner_id: $owner_id,
            allowMalformed: $allowMalformed,
            message: 'Owner type and owner id must both be present or both be null.',
        );
    }

    /**
     * @param  array<string, mixed>|object  $row
     */
    public static function fromRow(
        array | object $row,
        OwnerTupleColumns $columns,
        bool $allowMalformed = false,
    ): ParsedOwnerTuple {
        [$hasOwnerType, $rawOwnerType] = self::readValue($row, $columns->ownerTypeColumn);
        [$hasOwnerId, $rawOwnerId] = self::readValue($row, $columns->ownerIdColumn);

        if (! $hasOwnerType || ! $hasOwnerId) {
            return self::malformed(
                owner_type: null,
                owner_id: null,
                allowMalformed: $allowMalformed,
                message: sprintf(
                    'Owner tuple columns are missing from row data (required: %s, %s).',
                    $columns->ownerTypeColumn,
                    $columns->ownerIdColumn,
                ),
            );
        }

        $owner_type = self::normalizeOwnerType($rawOwnerType);
        $owner_id = self::normalizeOwnerId($rawOwnerId);

        $isMalformedType = $rawOwnerType !== null && $owner_type === null;
        $isMalformedId = $rawOwnerId !== null && $owner_id === null;

        if ($isMalformedType || $isMalformedId) {
            return self::malformed(
                owner_type: $owner_type,
                owner_id: $owner_id,
                allowMalformed: $allowMalformed,
                message: 'Owner tuple values must be a non-empty string owner_type and a non-empty string|int owner_id, or both null.',
            );
        }

        return self::fromTypeAndId(
            owner_type: $owner_type,
            owner_id: $owner_id,
            allowMalformed: $allowMalformed,
        );
    }

    /**
     * @param  array<string, mixed>|object  $row
     * @return array{bool, mixed}
     */
    private static function readValue(array | object $row, string $key): array
    {
        if (is_array($row)) {
            if (! array_key_exists($key, $row)) {
                return [false, null];
            }

            return [true, $row[$key]];
        }

        if (method_exists($row, 'getAttributes') && method_exists($row, 'getAttribute')) {
            /** @var array<string, mixed> $attributes */
            $attributes = $row->getAttributes();

            if (! array_key_exists($key, $attributes)) {
                return [false, null];
            }

            return [true, $row->getAttribute($key)];
        }

        if (isset($row->{$key}) || property_exists($row, $key)) {
            return [true, $row->{$key}];
        }

        return [false, null];
    }

    private static function normalizeOwnerType(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private static function normalizeOwnerId(mixed $value): string | int | null
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private static function malformed(
        ?string $owner_type,
        string | int | null $owner_id,
        bool $allowMalformed,
        string $message,
    ): ParsedOwnerTuple {
        if (! $allowMalformed) {
            throw new InvalidArgumentException($message);
        }

        return ParsedOwnerTuple::unresolved($owner_type, $owner_id);
    }
}
