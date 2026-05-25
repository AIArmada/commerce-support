<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use Illuminate\Database\Eloquent\Model;

trait HasOwnerScopeKey // @phpstan-ignore trait.unused
{
    protected static string $ownerScopeKeyColumn = 'owner_scope';

    protected static function bootHasOwnerScopeKey(): void
    {
        static::saving(function (Model $model): void {
            $model->setAttribute(
                static::ownerScopeKeyColumn(),
                static::resolveOwnerScopeKey($model),
            );
        });
    }

    protected static function ownerScopeKeyColumn(): string
    {
        return static::$ownerScopeKeyColumn;
    }

    protected static function ownerTypeColumnName(): string
    {
        return static::resolveOwnerScopeConfig()->ownerTypeColumn;
    }

    protected static function ownerIdColumnName(): string
    {
        return static::resolveOwnerScopeConfig()->ownerIdColumn;
    }

    protected static function readOwnerScopeKeyAttribute(Model $model, string $column): int | string | null
    {
        $value = $model->getAttribute($column);

        if (is_int($value) || is_string($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }

    protected static function resolveOwnerScopeKey(Model $model): string
    {
        $ownerType = static::readOwnerScopeKeyAttribute($model, static::ownerTypeColumnName());
        $ownerId = static::readOwnerScopeKeyAttribute($model, static::ownerIdColumnName());

        if ($ownerType === null && $ownerId === null && ! OwnerContext::isExplicitGlobal()) {
            $owner = OwnerContext::resolve();

            if ($owner !== null) {
                return OwnerScopeKey::forOwner($owner);
            }
        }

        return OwnerScopeKey::forTypeAndId(
            is_string($ownerType) ? $ownerType : null,
            $ownerId,
        );
    }
}
