<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class OwnerScopeKey
{
    public const string GLOBAL = 'global';

    public static function forOwner(Model | OwnerScopeIdentifiable | null $owner): string
    {
        if ($owner === null) {
            return self::GLOBAL;
        }

        return self::forTypeAndId($owner->getMorphClass(), $owner->getKey());
    }

    public static function forTypeAndId(?string $ownerType, int | string | null $ownerId): string
    {
        if ($ownerType === null && $ownerId === null) {
            return self::GLOBAL;
        }

        if ($ownerType === null || $ownerId === null || $ownerType === '' || (is_string($ownerId) && $ownerId === '')) {
            throw new InvalidArgumentException('Owner type and owner id must both be present or both be null.');
        }

        return hash('sha256', $ownerType . '|' . (string) $ownerId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function forAttributes(
        array $attributes,
        string $ownerTypeColumn = 'owner_type',
        string $ownerIdColumn = 'owner_id',
    ): string {
        $ownerType = $attributes[$ownerTypeColumn] ?? null;
        $ownerId = $attributes[$ownerIdColumn] ?? null;

        return self::forTypeAndId(
            is_string($ownerType) ? $ownerType : null,
            is_int($ownerId) || is_string($ownerId) ? $ownerId : null,
        );
    }
}
