<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

use AIArmada\CommerceSupport\Data\OwnerJobContext;

interface OwnerScopedJob
{
    public function ownerContext(): OwnerJobContext;
}
