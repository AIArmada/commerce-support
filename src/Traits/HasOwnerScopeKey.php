<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use Illuminate\Database\Eloquent\Model;

trait HasOwnerScopeKey
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

        // `saving` runs before `HasOwner::creating` assigns the owner tuple, so
        // a new record relies on the ambient owner here. Existing records and
        // models that never auto-assign must keep their real (often global)
        // tuple instead of being stamped with ambient state.
        if ($ownerType === null && $ownerId === null && ! $model->exists && ! OwnerContext::isExplicitGlobal()) {
            $config = static::ownerScopeConfigSafely();

            if ($config !== null && $config->enabled && $config->autoAssignOnCreate) {
                $owner = OwnerContext::resolveConfigured($config);

                if ($owner !== null) {
                    return OwnerScopeKey::forOwner($owner);
                }
            }
        }

        return OwnerScopeKey::forTypeAndId(
            is_string($ownerType) ? $ownerType : null,
            $ownerId,
        );
    }

    protected static function ownerScopeConfigSafely(): ?OwnerScopeConfig
    {
        if (! method_exists(static::class, 'resolveOwnerScopeConfig')) {
            return null;
        }

        /** @var OwnerScopeConfig $config */
        $config = static::resolveOwnerScopeConfig();

        return $config;
    }
}
