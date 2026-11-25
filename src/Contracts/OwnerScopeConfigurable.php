<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;

interface OwnerScopeConfigurable
{
    public static function ownerScopeConfig(): OwnerScopeConfig;
}
