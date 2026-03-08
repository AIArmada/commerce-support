<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class OwnerWriteGuard
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    public static function findOrFailForOwner(
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
        }

        if (is_string($owner)) {
            throw new InvalidArgumentException('Owner must be an Eloquent model, null, or omitted.');
        }

        if (method_exists($modelClass, 'ownerScopeConfig')) {
            /** @var OwnerScopeConfig $config */
            $config = $modelClass::ownerScopeConfig();

            if ($config->enabled) {
                $query = OwnerQuery::applyToEloquentBuilder(
                    $query->withoutGlobalScope(OwnerScope::class),
                    $owner,
                    $includeGlobal && $config->includeGlobal,
                    $config->ownerTypeColumn,
                    $config->ownerIdColumn,
                );
            }
        } elseif (method_exists($modelClass, 'scopeForOwner')) {
            $query = OwnerQuery::applyToEloquentBuilder(
                $query->withoutGlobalScope(OwnerScope::class),
                $owner,
                $includeGlobal,
            );
        }

        $model = $query->whereKey($id)->first();

        if ($model !== null) {
            return $model;
        }

        throw new AuthorizationException($message ?? 'Referenced record is not accessible in the current owner scope.');
    }
}
