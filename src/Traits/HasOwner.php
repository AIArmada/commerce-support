<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

/**
 * Trait for models that support multi-tenancy through owner scoping.
 *
 * This trait provides a standardized way to add owner-based multi-tenancy
 * to Eloquent models. Models using this trait should have `owner_type`
 * and `owner_id` columns in their database table.
 *
 * @method static Builder forOwner(?Model $owner, bool $includeGlobal = false)
 *
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 */
trait HasOwner // @phpstan-ignore trait.unused
{
    protected static function resolveOwnerScopeConfig(): OwnerScopeConfig
    {
        // @phpstan-ignore-next-line
        if (method_exists(static::class, 'ownerScopeConfig')) {
            // @phpstan-ignore-next-line
            $config = static::ownerScopeConfig();

            // @phpstan-ignore-next-line
            if ($config instanceof OwnerScopeConfig) {
                return $config;
            }
        }

        return new OwnerScopeConfig(
            enabled: true,
            includeGlobal: false,
            owner: null,
            ownerTypeColumn: 'owner_type',
            ownerIdColumn: 'owner_id',
        );
    }

    protected static function bootHasOwner(): void
    {
        $config = static::resolveOwnerScopeConfig();

        if (! $config->enabled) {
            return;
        }

        static::addGlobalScope(new OwnerScope($config));

        static::creating(function (Model $model) use ($config): void {
            static::assignOwnerOnCreate($model, $config);
        });

        static::saving(function (Model $model) use ($config): void {
            static::guardOwnedOwnerWrite($model, $config, 'save');
            static::guardGlobalOwnerWrite($model, $config, 'save');
        });

        static::deleting(function (Model $model) use ($config): void {
            static::guardOwnedOwnerWrite($model, $config, 'delete');
            static::guardGlobalOwnerWrite($model, $config, 'delete');
        });
    }

    protected static function assignOwnerOnCreate(Model $model, OwnerScopeConfig $config): void
    {
        $ownerType = $model->getAttribute($config->ownerTypeColumn);
        $ownerId = $model->getAttribute($config->ownerIdColumn);

        if (($ownerType === null) !== ($ownerId === null)) {
            throw new InvalidArgumentException('Owner type and owner id must both be present or both be null.');
        }

        if ($ownerType !== null || $ownerId !== null) {
            static::assertOwnerMatchesCurrentContext($model, $config, 'create');

            return;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context before creating records. Use OwnerContext::withOwner(null, ...) for explicit global records.', $model::class),
            );

            return;
        }

        if (! $config->autoAssignOnCreate) {
            return;
        }

        $model->setAttribute($config->ownerTypeColumn, $owner->getMorphClass());
        $model->setAttribute($config->ownerIdColumn, $owner->getKey());
    }

    protected static function guardOwnedOwnerWrite(Model $model, OwnerScopeConfig $config, string $operation): void
    {
        $ownerType = $model->getAttribute($config->ownerTypeColumn);
        $ownerId = $model->getAttribute($config->ownerIdColumn);

        if (($ownerType === null) !== ($ownerId === null)) {
            throw new InvalidArgumentException('Owner type and owner id must both be present or both be null.');
        }

        if ($model->exists) {
            $originalOwnerType = $model->getOriginal($config->ownerTypeColumn);
            $originalOwnerId = $model->getOriginal($config->ownerIdColumn);

            // Block promotion: persisted global record being assigned to any owner
            if ($originalOwnerType === null && $originalOwnerId === null && ($ownerType !== null || $ownerId !== null)) {
                throw new InvalidArgumentException(sprintf(
                    'Owner cannot be assigned to a persisted global %s record. Use a privileged TransferOwnerAction instead.',
                    $model::class,
                ));
            }

            // Block demotion: persisted owned record being set to global
            if (($originalOwnerType !== null || $originalOwnerId !== null) && $ownerType === null && $ownerId === null) {
                throw new InvalidArgumentException(sprintf(
                    'Owner cannot be removed from a persisted %s record. Use a privileged TransferOwnerAction instead.',
                    $model::class,
                ));
            }

            // Block reassignment: owned record being switched to a different owner
            if ($ownerType !== null && ($originalOwnerType !== $ownerType || (string) $originalOwnerId !== (string) $ownerId)) {
                throw new InvalidArgumentException('Owner columns cannot be reassigned after creation.');
            }
        }

        if ($ownerType === null && $ownerId === null) {
            return;
        }

        static::assertOwnerMatchesCurrentContext($model, $config, $operation);
    }

    protected static function assertOwnerMatchesCurrentContext(Model $model, OwnerScopeConfig $config, string $operation): void
    {
        $owner = OwnerContext::resolve();

        if ($owner === null) {
            throw new AuthorizationException(sprintf('A matching owner context is required to %s owned %s records.', $operation, $model::class));
        }

        $ownerType = (string) $model->getAttribute($config->ownerTypeColumn);
        $ownerId = (string) $model->getAttribute($config->ownerIdColumn);

        if ($ownerType === $owner->getMorphClass() && $ownerId === (string) $owner->getKey()) {
            return;
        }

        throw new AuthorizationException(sprintf('Cross-owner %s blocked for %s.', $operation, $model::class));
    }

    protected static function guardGlobalOwnerWrite(Model $model, OwnerScopeConfig $config, string $operation): void
    {
        if (! $model->exists) {
            return;
        }

        if ($model->getAttribute($config->ownerTypeColumn) !== null || $model->getAttribute($config->ownerIdColumn) !== null) {
            return;
        }

        if (OwnerContext::isExplicitGlobal()) {
            return;
        }

        throw new AuthorizationException(sprintf('Explicit global owner context is required to %s global %s records.', $operation, $model::class));
    }

    /**
     * Get the owner model (polymorphic relationship).
     */
    public function owner(): MorphTo
    {
        $config = static::resolveOwnerScopeConfig();

        return $this->morphTo(__FUNCTION__, $config->ownerTypeColumn, $config->ownerIdColumn);
    }

    /**
     * Scope query to the specified owner.
     *
     * @param  Builder<static>  $query
     * @param  Model|string|null  $owner  The owner to scope to; pass null for global-only; omit argument to resolve current owner
     * @param  bool  $includeGlobal  Whether to include global (ownerless) records
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, Model | string | null $owner = OwnerContext::CURRENT, bool $includeGlobal = false): Builder
    {
        /** @var OwnerScopeConfig $config */
        $config = static::resolveOwnerScopeConfig();

        if (! $config->enabled) {
            return $query;
        }

        $ownerTypeColumn = $config->ownerTypeColumn;
        $ownerIdColumn = $config->ownerIdColumn;

        if ($owner === OwnerContext::CURRENT) {
            $owner = OwnerContext::resolve();

            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', static::class),
            );
        }

        if (is_string($owner)) {
            throw new InvalidArgumentException('Owner must be an Eloquent model, null, or omitted.');
        }

        return OwnerQuery::applyToEloquentBuilder(
            $query->withoutOwnerScope(),
            $owner,
            $includeGlobal,
            $ownerTypeColumn,
            $ownerIdColumn
        );
    }

    /**
     * Scope query to only global (ownerless) records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeGlobalOnly(Builder $query): Builder
    {
        /** @var OwnerScopeConfig $config */
        $config = static::resolveOwnerScopeConfig();
        $ownerTypeColumn = $config->ownerTypeColumn;
        $ownerIdColumn = $config->ownerIdColumn;

        return $query->withoutOwnerScope()
            ->whereNull($ownerTypeColumn)
            ->whereNull($ownerIdColumn);
    }

    /**
     * Remove the owner scope from the query.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutOwnerScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(OwnerScope::class);
    }

    /**
     * Check if this model has an owner assigned.
     */
    public function hasOwner(): bool
    {
        $config = static::resolveOwnerScopeConfig();

        return $this->getAttribute($config->ownerTypeColumn) !== null
            && $this->getAttribute($config->ownerIdColumn) !== null;
    }

    /**
     * Check if this model is global (no owner).
     */
    public function isGlobal(): bool
    {
        return ! $this->hasOwner();
    }

    /**
     * Check if this model belongs to the given owner.
     */
    public function belongsToOwner(Model $owner): bool
    {
        $config = static::resolveOwnerScopeConfig();

        return $this->getAttribute($config->ownerTypeColumn) === $owner->getMorphClass()
            && (string) $this->getAttribute($config->ownerIdColumn) === (string) $owner->getKey();
    }

    /**
     * Assign an owner to this model.
     */
    public function assignOwner(Model $owner): static
    {
        $config = static::resolveOwnerScopeConfig();

        $this->setAttribute($config->ownerTypeColumn, $owner->getMorphClass());
        $this->setAttribute($config->ownerIdColumn, $owner->getKey());

        return $this;
    }

    /**
     * Remove the owner from this model (make it global).
     *
     * Only valid on unsaved (new) model instances. Persisted owned records
     * are strictly immutable — use a privileged TransferOwnerAction instead.
     */
    public function removeOwner(): static
    {
        $config = static::resolveOwnerScopeConfig();

        if ($this->exists && $this->hasOwner()) {
            throw new InvalidArgumentException(sprintf(
                'Owner cannot be removed from a persisted %s record. Use a privileged TransferOwnerAction instead.',
                static::class,
            ));
        }

        $this->setAttribute($config->ownerTypeColumn, null);
        $this->setAttribute($config->ownerIdColumn, null);

        return $this;
    }

    /**
     * Get the human-readable display name for the owner.
     */
    public function getOwnerDisplayNameAttribute(): ?string
    {
        $owner = $this->owner;

        if (! $owner) {
            return null;
        }

        /** @var string|null $name */
        $name = $owner->getAttribute('name');
        /** @var string|null $displayName */
        $displayName = $owner->getAttribute('display_name');
        /** @var string|null $email */
        $email = $owner->getAttribute('email');

        /** @var int|string $key */
        $key = $owner->getKey();

        return $name ?? $displayName ?? $email ?? class_basename($owner) . ':' . (string) $key;
    }
}
