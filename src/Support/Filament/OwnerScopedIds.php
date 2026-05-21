<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support\Filament;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class OwnerScopedIds
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, mixed>  $ids
     * @return array<int, string>
     */
    public static function allowedIds(string $modelClass, array $ids, ?bool $includeGlobal = null, ?string $configKey = null): array
    {
        $ids = self::normalizeIds($ids);

        if ($ids === []) {
            return [];
        }

        $config = self::resolveConfig($modelClass, $configKey);

        /** @var Builder<Model> $query */
        $query = $modelClass::query();

        if ($config->enabled) {
            $query = OwnerQuery::applyToEloquentBuilder(
                $query->withoutGlobalScope(OwnerScope::class),
                OwnerContext::resolve(),
                $includeGlobal ?? $config->includeGlobal,
                $config->ownerTypeColumn,
                $config->ownerIdColumn,
            );
        }

        /** @var array<int, mixed> $allowed */
        $allowed = $query->whereKey($ids)->pluck($query->getModel()->getKeyName())->all();

        return array_values(array_map(static fn (mixed $id): string => (string) $id, $allowed));
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, mixed>|null  $ids
     * @return array<int, string>
     */
    public static function ensureAllowed(string $field, string $modelClass, ?array $ids, ?bool $includeGlobal = null, ?string $configKey = null): array
    {
        $normalizedIds = self::normalizeIds($ids ?? []);

        if ($normalizedIds === []) {
            return [];
        }

        $allowed = self::allowedIds($modelClass, $normalizedIds, $includeGlobal, $configKey);

        if (count($allowed) !== count($normalizedIds)) {
            throw ValidationException::withMessages([
                $field => ['One or more selected records are invalid for the current owner scope.'],
            ]);
        }

        return $allowed;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function resolveConfig(string $modelClass, ?string $configKey = null): OwnerScopeConfig
    {
        if (! is_a($modelClass, Model::class, true)) {
            throw new InvalidArgumentException(sprintf('%s must be an Eloquent model class.', $modelClass));
        }

        if (method_exists($modelClass, 'ownerScopeConfig')) {
            /** @var OwnerScopeConfig $config */
            $config = $modelClass::ownerScopeConfig();

            return $config;
        }

        if ($configKey !== null && $configKey !== '') {
            return OwnerScopeConfig::fromConfig($configKey);
        }

        if (method_exists($modelClass, 'scopeForOwner') || method_exists($modelClass, 'scopeGlobalOnly')) {
            return new OwnerScopeConfig(enabled: true);
        }

        return new OwnerScopeConfig(enabled: false);
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, string>
     */
    private static function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_map(
            static fn (mixed $id): string => (string) $id,
            array_filter($ids, static fn (mixed $id): bool => is_scalar($id) && (string) $id !== ''),
        )));
    }
}
