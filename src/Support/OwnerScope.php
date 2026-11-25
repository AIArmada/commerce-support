<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class OwnerScope implements Scope
{
    /**
     * @param  OwnerScopeConfig|null  $config  Explicit config for non-`HasOwner` scopes that
     *                                         follow a related model's owner tuple. `HasOwner`
     *                                         registers this scope without config so the model's
     *                                         live `ownerScopeConfig()` governs every query.
     */
    public function __construct(private readonly ?OwnerScopeConfig $config = null) {}

    public function apply(Builder $builder, Model $model): void
    {
        $config = $this->resolveConfig($model);

        if (! $config->enabled) {
            return;
        }

        $owner = $config->owner ?? OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            sprintf('%s requires an owner context or explicit global context.', $model::class),
        );

        /** @var Builder<Model> $builder */
        OwnerQuery::applyToEloquentBuilder(
            $builder,
            $owner,
            OwnerScopeOverride::suppressIncludeGlobal() ? false : $config->includeGlobal,
            $config->ownerTypeColumn,
            $config->ownerIdColumn,
        );
    }

    private function resolveConfig(Model $model): OwnerScopeConfig
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $modelClass = $model::class;

        if (method_exists($modelClass, 'ownerScopeConfig')) {
            /** @var OwnerScopeConfig $config */
            $config = $modelClass::ownerScopeConfig();

            return $config;
        }

        return new OwnerScopeConfig(enabled: true);
    }
}
