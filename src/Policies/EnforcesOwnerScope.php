<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Policies;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

trait EnforcesOwnerScope
{
    protected function ownerScopeConfigFor(Model $record): OwnerScopeConfig
    {
        if (! method_exists($record, 'ownerScopeConfig')) {
            throw new InvalidArgumentException(sprintf('%s does not expose owner scoping configuration.', $record::class));
        }

        $config = call_user_func([$record::class, 'ownerScopeConfig']);

        if (! $config instanceof OwnerScopeConfig) {
            throw new InvalidArgumentException(sprintf('%s did not return an owner scope configuration.', $record::class));
        }

        return $config;
    }

    protected function ownerScopeConfigForModel(string $modelClass): OwnerScopeConfig
    {
        if (! is_a($modelClass, Model::class, true) || ! method_exists($modelClass, 'ownerScopeConfig')) {
            throw new InvalidArgumentException(sprintf('%s does not expose owner scoping configuration.', $modelClass));
        }

        $config = call_user_func([$modelClass, 'ownerScopeConfig']);

        if (! $config instanceof OwnerScopeConfig) {
            throw new InvalidArgumentException(sprintf('%s did not return an owner scope configuration.', $modelClass));
        }

        return $config;
    }

    protected function ownerContextAllowsAccess(string $modelClass): bool
    {
        $config = $this->ownerScopeConfigForModel($modelClass);

        if (! $config->enabled) {
            return true;
        }

        return OwnerContext::resolveConfigured($config) !== null || OwnerContext::isExplicitGlobal();
    }

    protected function isViewableInOwnerScope(Model $record): bool
    {
        $config = $this->ownerScopeConfigFor($record);

        if (! $config->enabled) {
            return true;
        }

        [$ownerType, $ownerId] = $this->recordOwnerTuple($record, $config);

        if (($ownerType === null) !== ($ownerId === null)) {
            return false;
        }

        $owner = OwnerContext::resolveConfigured($config);

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $ownerType === null;
        }

        if ($ownerType !== null && $this->matchesOwner($owner, $ownerType, $ownerId)) {
            return true;
        }

        return $ownerType === null
            && $config->includeGlobal
            && ! OwnerScopeOverride::suppressIncludeGlobal();
    }

    protected function isMutableInOwnerScope(Model $record): bool
    {
        $config = $this->ownerScopeConfigFor($record);

        if (! $config->enabled) {
            return true;
        }

        [$ownerType, $ownerId] = $this->recordOwnerTuple($record, $config);

        if (($ownerType === null) !== ($ownerId === null)) {
            return false;
        }

        $owner = OwnerContext::resolveConfigured($config);

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $ownerType === null;
        }

        return $ownerType !== null && $this->matchesOwner($owner, $ownerType, $ownerId);
    }

    /**
     * @return array{0: string|null, 1: string|int|null}
     */
    private function recordOwnerTuple(Model $record, OwnerScopeConfig $config): array
    {
        $ownerType = $record->getAttribute($config->ownerTypeColumn);
        $ownerId = $record->getAttribute($config->ownerIdColumn);

        return [
            is_scalar($ownerType) && (string) $ownerType !== '' ? (string) $ownerType : null,
            is_scalar($ownerId) && (string) $ownerId !== '' ? $ownerId : null,
        ];
    }

    private function matchesOwner(Model $owner, string $ownerType, string | int $ownerId): bool
    {
        return $owner->getMorphClass() === $ownerType
            && (string) $owner->getKey() === (string) $ownerId;
    }
}
