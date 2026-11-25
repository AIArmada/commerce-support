<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
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
        $modelContext = null;

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($job);

            switch ($property->getName()) {
                case 'ownerType':
                    if (is_string($value) && $value !== '') {
                        $ownerType = $value;
                    }

                    continue 2;

                case 'ownerId':
                    if ((is_string($value) || is_int($value)) && $value !== '') {
                        $ownerId = $value;
                    }

                    continue 2;

                case 'ownerIsGlobal':
                    if ($value === true) {
                        $ownerIsGlobal = true;
                    }

                    continue 2;
            }

            // Explicit payload properties win regardless of declaration order,
            // so a later model property can never mask them.
            if ($modelContext instanceof OwnerJobContext) {
                continue;
            }

            if ($value instanceof Model) {
                $modelContext = $this->ownerContextFromModel($value);

                continue;
            }

            if ($value instanceof OwnerScopeIdentifiable) {
                $modelContext = $this->ownerContextFromScopeIdentifiable($value);
            }
        }

        if ($ownerType !== null || $ownerId !== null || $ownerIsGlobal) {
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

        return $modelContext ?? new OwnerJobContext(ownerType: null, ownerId: null);
    }

    private function ownerContextFromModel(Model $model): ?OwnerJobContext
    {
        if (method_exists($model, 'getOwner')) {
            $owner = $model->getOwner();

            if ($owner instanceof Model) {
                return OwnerJobContext::fromOwnerModel($owner);
            }
        }

        $columns = OwnerTupleColumns::forModelClass($model::class);
        $modelOwnerType = $model->getAttribute($columns->ownerTypeColumn);
        $modelOwnerId = $model->getAttribute($columns->ownerIdColumn);

        if (is_string($modelOwnerType) && $modelOwnerType !== '' && (is_string($modelOwnerId) || is_int($modelOwnerId))) {
            return new OwnerJobContext(
                ownerType: $modelOwnerType,
                ownerId: $modelOwnerId,
                ownerIsGlobal: false,
            );
        }

        if ($model instanceof OwnerScopeIdentifiable) {
            return $this->ownerContextFromScopeIdentifiable($model);
        }

        return null;
    }

    private function ownerContextFromScopeIdentifiable(OwnerScopeIdentifiable $owner): ?OwnerJobContext
    {
        $key = $owner->getKey();

        if (! is_string($key) && ! is_int($key)) {
            return null;
        }

        if (is_string($key) && $key === '') {
            return null;
        }

        return new OwnerJobContext(
            ownerType: $owner->getMorphClass(),
            ownerId: $key,
            ownerIsGlobal: false,
        );
    }
}
