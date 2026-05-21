<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use ReflectionObject;
use ReflectionProperty;
use RuntimeException;

final class ResolveOwnerJobContextAction
{
    use AsAction;

    public function handle(object $job): OwnerJobContext
    {
        if ($job instanceof OwnerScopedJob) {
            return $job->ownerContext();
        }

        $reflection = new ReflectionObject($job);
        $ownerType = null;
        $ownerId = null;
        $ownerIsGlobal = false;

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($job);
            $propertyName = $property->getName();

            if (($propertyName === 'ownerType' || $propertyName === 'owner_type') && is_string($value) && $value !== '') {
                $ownerType = $value;
            }

            if (($propertyName === 'ownerId' || $propertyName === 'owner_id') && (is_string($value) || is_int($value)) && $value !== '') {
                $ownerId = $value;
            }

            if (($propertyName === 'ownerIsGlobal' || $propertyName === 'owner_is_global') && $value === true) {
                $ownerIsGlobal = true;
            }

            if ($value instanceof Model) {
                if (method_exists($value, 'getOwner')) {
                    $owner = $value->getOwner();

                    if ($owner instanceof Model) {
                        return OwnerJobContext::fromOwnerModel($owner);
                    }
                }

                if (method_exists($value, 'getAttribute')) {
                    $modelOwnerType = $value->getAttribute('owner_type');
                    $modelOwnerId = $value->getAttribute('owner_id');

                    if (is_string($modelOwnerType) && $modelOwnerType !== '' && (is_string($modelOwnerId) || is_int($modelOwnerId))) {
                        return new OwnerJobContext(
                            ownerType: $modelOwnerType,
                            ownerId: $modelOwnerId,
                            ownerIsGlobal: false,
                        );
                    }
                }

                if (method_exists($value, 'getMorphClass')) {
                    return OwnerJobContext::fromOwnerModel($value);
                }
            }
        }

        try {
            return new OwnerJobContext(
                ownerType: $ownerType,
                ownerId: $ownerId,
                ownerIsGlobal: $ownerIsGlobal,
            );
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException(
                sprintf('%s received invalid owner job context payload: %s', $job::class, $exception->getMessage()),
                previous: $exception,
            );
        }
    }
}
