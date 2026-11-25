<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Data\OwnerJobContext;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class OwnerJobContextMapper
{
    public static function fromModel(Model $owner): OwnerJobContext
    {
        $reference = EloquentRecordReference::fromModel($owner);

        if ($reference === null) {
            throw new InvalidArgumentException('A persisted owner is required.');
        }

        return new OwnerJobContext($reference->type, $reference->id);
    }

    public static function resolve(?OwnerJobContext $context): ?Model
    {
        if ($context === null || $context->ownerType === null || $context->ownerId === null) {
            return null;
        }

        return OwnerContext::fromTypeAndIdOrFail($context->ownerType, $context->ownerId);
    }
}
