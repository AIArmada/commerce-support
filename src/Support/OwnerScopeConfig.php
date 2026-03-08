<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Database\Eloquent\Model;

final class OwnerScopeConfig
{
    public function __construct(
        public readonly bool $enabled,
        public readonly bool $includeGlobal = false,
        public readonly ?Model $owner = null,
        public readonly string $ownerTypeColumn = 'owner_type',
        public readonly string $ownerIdColumn = 'owner_id',
    ) {}

    public static function fromConfig(
        string $configKey,
        bool $enabledDefault = false,
        bool $includeGlobalDefault = false,
        ?Model $owner = null,
        string $ownerTypeColumn = 'owner_type',
        string $ownerIdColumn = 'owner_id',
    ): self {
        return new self(
            enabled: (bool) config($configKey . '.enabled', $enabledDefault),
            includeGlobal: (bool) config($configKey . '.include_global', $includeGlobalDefault),
            owner: $owner,
            ownerTypeColumn: $ownerTypeColumn,
            ownerIdColumn: $ownerIdColumn,
        );
    }
}
