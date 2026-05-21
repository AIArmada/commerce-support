<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class OwnerScope implements Scope
{
    public function __construct(private readonly OwnerScopeConfig $config) {}

    public function apply(Builder $builder, Model $model): void
    {
        if (! $this->config->enabled) {
            return;
        }

        $owner = $this->config->owner ?? OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            sprintf('%s requires an owner context or explicit global context.', $model::class),
        );

        /** @var Builder<Model> $builder */
        OwnerQuery::applyToEloquentBuilder(
            $builder,
            $owner,
            $this->config->includeGlobal,
            $this->config->ownerTypeColumn,
            $this->config->ownerIdColumn,
        );
    }
}
