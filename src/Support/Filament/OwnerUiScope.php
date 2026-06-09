<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support\Filament;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class OwnerUiScope
{
    public static function resolveOwner(string | Model | Builder $subject, ?string $configKey = null, ?string $message = null): ?Model
    {
        $config = self::resolveConfig($subject, $configKey);

        if (! $config->enabled) {
            return null;
        }

        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            $message ?? sprintf('%s requires an owner context or explicit global context.', self::subjectLabel($subject)),
        );

        return $owner;
    }

    public static function canCreate(string | Model | Builder $subject, ?string $configKey = null): bool
    {
        $config = self::resolveConfig($subject, $configKey);

        if (! $config->enabled) {
            return true;
        }

        return OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal();
    }

    public static function apply(Builder $query, ?string $configKey = null, ?bool $includeGlobal = null, ?string $message = null): Builder
    {
        try {
            $config = self::resolveConfig($query, $configKey);
        } catch (InvalidArgumentException) {
            return self::failClosed($query);
        }

        if (! $config->enabled) {
            return $query;
        }

        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            $message ?? sprintf('%s requires an owner context or explicit global context.', self::subjectLabel($query)),
        );

        $scopedQuery = $query->withoutGlobalScope(OwnerScope::class);

        return OwnerQuery::applyToEloquentBuilder(
            $scopedQuery,
            $owner,
            $includeGlobal ?? $config->includeGlobal,
            $config->ownerTypeColumn,
            $config->ownerIdColumn,
        );
    }

    public static function canAccessRecord(Model $record, ?bool $includeGlobal = null, ?string $configKey = null): bool
    {
        return self::canInteractWithRecord($record, $configKey, $includeGlobal, false);
    }

    public static function canMutateRecord(Model $record, ?string $configKey = null): bool
    {
        return self::canInteractWithRecord($record, $configKey, false, true);
    }

    public static function applyForRecordOwner(Builder $query, Model $record, ?string $recordConfigKey = null, ?string $queryConfigKey = null): Builder
    {
        if (! self::canAccessRecord($record, null, $recordConfigKey)) {
            return self::failClosed($query);
        }

        $recordOwner = self::extractRecordOwner($record, $recordConfigKey);

        if ($recordOwner === false) {
            return self::failClosed($query);
        }

        try {
            $config = self::resolveConfig($query, $queryConfigKey);
        } catch (InvalidArgumentException) {
            return self::failClosed($query);
        }

        if (! $config->enabled) {
            return $query;
        }

        $scopedQuery = $query->withoutGlobalScope(OwnerScope::class);

        return OwnerQuery::applyToEloquentBuilder(
            $scopedQuery,
            $recordOwner,
            false,
            $config->ownerTypeColumn,
            $config->ownerIdColumn,
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel|null
     */
    public static function findForRecordOwner(string $modelClass, Model $record, mixed $id, ?string $recordConfigKey = null, ?string $queryConfigKey = null): ?Model
    {
        if (! is_scalar($id) || (string) $id === '') {
            return null;
        }

        /** @var Builder<TModel> $query */
        $query = $modelClass::query();

        $resolvedModel = self::applyForRecordOwner($query, $record, $recordConfigKey, $queryConfigKey)
            ->whereKey((string) $id)
            ->first();

        return $resolvedModel instanceof $modelClass ? $resolvedModel : null;
    }

    private static function canInteractWithRecord(Model $record, ?string $configKey, ?bool $includeGlobal, bool $forMutation): bool
    {
        try {
            $config = self::resolveConfig($record, $configKey);
        } catch (InvalidArgumentException) {
            return false;
        }

        if (! $config->enabled) {
            return true;
        }

        $recordOwner = self::extractRecordOwner($record, $configKey);

        if ($recordOwner === false) {
            return false;
        }

        $owner = OwnerContext::resolve();

        if ($owner !== null) {
            if ($recordOwner instanceof Model && self::sameOwner($owner, $recordOwner)) {
                return true;
            }

            return ! $forMutation
                && ($includeGlobal ?? $config->includeGlobal)
                && $recordOwner === null;
        }

        return OwnerContext::isExplicitGlobal() && $recordOwner === null;
    }

    private static function extractRecordOwner(Model $record, ?string $configKey = null): Model | false | null
    {
        $config = self::resolveConfig($record, $configKey);
        $ownerType = $record->getAttribute($config->ownerTypeColumn);
        $ownerId = $record->getAttribute($config->ownerIdColumn);

        if ($ownerType === null && $ownerId === null) {
            return null;
        }

        if (($ownerType === null) !== ($ownerId === null)) {
            return false;
        }

        if (! is_scalar($ownerType) || (string) $ownerType === '') {
            return false;
        }

        if (! is_scalar($ownerId) || (string) $ownerId === '') {
            return false;
        }

        try {
            return OwnerContext::fromTypeAndId((string) $ownerType, (string) $ownerId);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private static function resolveConfig(string | Model | Builder $subject, ?string $configKey = null): OwnerScopeConfig
    {
        $modelClass = self::modelClass($subject);

        if ($modelClass !== null && method_exists($modelClass, 'ownerScopeConfig')) {
            /** @var OwnerScopeConfig $config */
            $config = $modelClass::ownerScopeConfig();

            return $config;
        }

        if ($configKey !== null && $configKey !== '') {
            return OwnerScopeConfig::fromConfig($configKey);
        }

        if ($modelClass !== null && (method_exists($modelClass, 'scopeForOwner') || method_exists($modelClass, 'scopeGlobalOnly'))) {
            return new OwnerScopeConfig(enabled: true);
        }

        throw new InvalidArgumentException(sprintf(
            'Unable to resolve owner scope configuration for %s. Provide an explicit config key for raw Eloquent models.',
            self::subjectLabel($subject),
        ));
    }

    private static function failClosed(Builder $query): Builder
    {
        return $query->whereRaw('1 = 0');
    }

    private static function modelClass(string | Model | Builder $subject): ?string
    {
        if ($subject instanceof Builder) {
            return $subject->getModel()::class;
        }

        if ($subject instanceof Model) {
            return $subject::class;
        }

        if (class_exists($subject) && is_a($subject, Model::class, true)) {
            return $subject;
        }

        return null;
    }

    private static function subjectLabel(string | Model | Builder $subject): string
    {
        return self::modelClass($subject)
            ?? ($subject instanceof Builder ? $subject->getModel()::class : (is_string($subject) ? $subject : $subject::class));
    }

    private static function sameOwner(Model $left, Model $right): bool
    {
        return $left->getMorphClass() === $right->getMorphClass()
            && (string) $left->getKey() === (string) $right->getKey();
    }
}
