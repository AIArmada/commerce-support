<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Data\RecordReference;

final class OwnerScopeConfigFactory
{
    public static function fromConfig(
        string $configKey,
        bool $enabledDefault = false,
        bool $includeGlobalDefault = false,
        ?RecordReference $owner = null,
        string $ownerTypeColumn = 'owner_type',
        string $ownerIdColumn = 'owner_id',
        bool $autoAssignOnCreateDefault = true,
    ): OwnerScopeConfig {
        return new OwnerScopeConfig(
            enabled: (bool) config($configKey . '.enabled', $enabledDefault),
            includeGlobal: (bool) config($configKey . '.include_global', $includeGlobalDefault),
            owner: $owner,
            ownerTypeColumn: $ownerTypeColumn,
            ownerIdColumn: $ownerIdColumn,
            autoAssignOnCreate: (bool) config($configKey . '.auto_assign_on_create', $autoAssignOnCreateDefault),
        );
    }
}
