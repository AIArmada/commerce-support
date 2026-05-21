<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class ResolveOwnedModelOrFailAction
{
    use AsAction;

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    public function handle(
        string $modelClass,
        int | string $id,
        Model | string | null $owner = OwnerContext::CURRENT,
        bool $includeGlobal = false,
        ?string $message = null,
    ): Model {
        /** @var Builder<TModel> $query */
        $query = $modelClass::query();

        if ($owner === OwnerContext::CURRENT) {
            $owner = OwnerContext::resolve();

            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', $modelClass),
            );
        }

        if (is_string($owner)) {
            throw new InvalidArgumentException('Owner must be an Eloquent model, null, or omitted.');
        }

        if (method_exists($modelClass, 'ownerScopeConfig')) {
            /** @var OwnerScopeConfig $config */
            $config = $modelClass::ownerScopeConfig();

            if (! $config->enabled) {
                throw new InvalidArgumentException(sprintf(
                    '%s has owner scoping disabled. Do not use OwnerWriteGuard on models that opt out of owner scoping.',
                    $modelClass,
                ));
            }

            $query = OwnerQuery::applyToEloquentBuilder(
                $query->withoutGlobalScope(OwnerScope::class),
                $owner,
                $includeGlobal,
                $config->ownerTypeColumn,
                $config->ownerIdColumn,
            );
        } elseif (method_exists($modelClass, 'scopeForOwner')) {
            $query = OwnerQuery::applyToEloquentBuilder(
                $query->withoutGlobalScope(OwnerScope::class),
                $owner,
                $includeGlobal,
            );
        } else {
            throw new InvalidArgumentException(sprintf(
                '%s does not implement owner scoping. Use the HasOwner trait before using OwnerWriteGuard.',
                $modelClass,
            ));
        }

        $model = $query->whereKey($id)->first();

        if ($model !== null) {
            return $model;
        }

        throw new AuthorizationException($message ?? 'Referenced record is not accessible in the current owner scope.');
    }
}
