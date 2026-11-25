<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Data;

final readonly class OwnerScopeConfig
{
    public function __construct(
        public bool $enabled,
        public bool $includeGlobal = false,
        public ?RecordReference $owner = null,
        public string $ownerTypeColumn = 'owner_type',
        public string $ownerIdColumn = 'owner_id',
        public bool $autoAssignOnCreate = true,
    ) {}

}
