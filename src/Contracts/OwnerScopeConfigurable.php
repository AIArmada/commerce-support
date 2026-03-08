<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

use AIArmada\CommerceSupport\Support\OwnerScopeConfig;

interface OwnerScopeConfigurable
{
    public static function ownerScopeConfig(): OwnerScopeConfig;
}
