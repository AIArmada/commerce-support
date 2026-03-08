<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class OwnerQuery
{
    /**
     * Qualify an Eloquent column (table.column) when not already qualified.
     */
    private static function qualifyEloquentColumn(EloquentBuilder $query, string $column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $query->getModel()->qualifyColumn($column);
    }

    /**
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>  $query
     * @return EloquentBuilder<TModel>
     */
    public static function applyToEloquentBuilder(
        EloquentBuilder $query,
        ?Model $owner,
        bool $includeGlobal = false,
        string $ownerTypeColumn = 'owner_type',
        string $ownerIdColumn = 'owner_id',
    ): EloquentBuilder {
        $ownerTypeColumn = self::qualifyEloquentColumn($query, $ownerTypeColumn);
        $ownerIdColumn = self::qualifyEloquentColumn($query, $ownerIdColumn);

        if ($owner === null) {
            return $query->whereNull($ownerTypeColumn)->whereNull($ownerIdColumn);
        }

        return $query->where(function (EloquentBuilder $builder) use ($owner, $includeGlobal, $ownerTypeColumn, $ownerIdColumn): void {
            $builder->where($ownerTypeColumn, $owner->getMorphClass())
                ->where($ownerIdColumn, $owner->getKey());

            if ($includeGlobal) {
                $builder->orWhere(function (EloquentBuilder $inner) use ($ownerTypeColumn, $ownerIdColumn): void {
                    $inner->whereNull($ownerTypeColumn)->whereNull($ownerIdColumn);
                });
            }
        });
    }

    public static function applyToQueryBuilder(
        QueryBuilder $query,
        ?Model $owner,
        bool $includeGlobal = false,
        string $ownerTypeColumn = 'owner_type',
        string $ownerIdColumn = 'owner_id',
    ): QueryBuilder {
        if ($owner === null) {
            return $query->whereNull($ownerTypeColumn)->whereNull($ownerIdColumn);
        }

        return $query->where(function (QueryBuilder $builder) use ($owner, $includeGlobal, $ownerTypeColumn, $ownerIdColumn): void {
            $builder->where($ownerTypeColumn, $owner->getMorphClass())
                ->where($ownerIdColumn, $owner->getKey());

            if ($includeGlobal) {
                $builder->orWhere(function (QueryBuilder $inner) use ($ownerTypeColumn, $ownerIdColumn): void {
                    $inner->whereNull($ownerTypeColumn)->whereNull($ownerIdColumn);
                });
            }
        });
    }
}
