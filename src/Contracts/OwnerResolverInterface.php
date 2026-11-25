<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

use AIArmada\CommerceSupport\Data\RecordReference;

/**
 * Resolve the trusted current tenant identity using the host tenancy strategy.
 * Null means no owner has been resolved; it does not grant global access.
 */
interface OwnerResolverInterface
{
    public function resolve(): ?RecordReference;
}
