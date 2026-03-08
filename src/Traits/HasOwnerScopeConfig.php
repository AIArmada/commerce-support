<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use ReflectionClass;

trait HasOwnerScopeConfig // @phpstan-ignore trait.unused
{
    private static function readOptionalStaticProperty(string $property, mixed $default): mixed
    {
        if (! property_exists(static::class, $property)) {
            return $default;
        }

        $reflection = new ReflectionClass(static::class);

        if (! $reflection->hasProperty($property)) {
            return $default;
        }

        $reflectedProperty = $reflection->getProperty($property);

        if (! $reflectedProperty->isStatic()) {
            return $default;
        }

        return $reflectedProperty->getValue();
    }

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        $key = (string) self::readOptionalStaticProperty('ownerScopeConfigKey', '');
        $enabledByDefault = (bool) self::readOptionalStaticProperty('ownerScopeEnabledByDefault', false);
        $includeGlobalByDefault = (bool) self::readOptionalStaticProperty('ownerScopeIncludeGlobalByDefault', false);
        $ownerTypeColumn = (string) self::readOptionalStaticProperty('ownerScopeOwnerTypeColumn', 'owner_type');
        $ownerIdColumn = (string) self::readOptionalStaticProperty('ownerScopeOwnerIdColumn', 'owner_id');

        if ($key === '') {
            return new OwnerScopeConfig(
                enabled: false,
                includeGlobal: false,
                owner: null,
                ownerTypeColumn: $ownerTypeColumn,
                ownerIdColumn: $ownerIdColumn,
            );
        }

        return OwnerScopeConfig::fromConfig(
            $key,
            $enabledByDefault,
            $includeGlobalByDefault,
            null,
            $ownerTypeColumn,
            $ownerIdColumn,
        );
    }
}
