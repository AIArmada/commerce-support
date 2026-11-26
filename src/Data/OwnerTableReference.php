<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Data;

final readonly class OwnerTableReference
{
    public function __construct(
        public string $table,
        public string $keyColumn,
        public int $connectionIdentity,
    ) {}
}
