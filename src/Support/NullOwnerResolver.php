<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Null implementation of OwnerResolverInterface.
 *
 * This resolver always returns null, effectively disabling multi-tenancy.
 * Use this as the default resolver when multi-tenancy is not needed.
 */
final class NullOwnerResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        return null;
    }
}
