<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
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
    protected static function resolveOwnerScopeConfig(): \AIArmada\CommerceSupport\Support\OwnerScopeConfig
    {
        // @phpstan-ignore-next-line
        if (method_exists(static::class, 'ownerScopeConfig')) {
            // @phpstan-ignore-next-line
            $config = static::ownerScopeConfig();

            // @phpstan-ignore-next-line
            if ($config instanceof \AIArmada\CommerceSupport\Support\OwnerScopeConfig) {
                return $config;
            }
        }

        return new \AIArmada\CommerceSupport\Support\OwnerScopeConfig(
            enabled: true,
            includeGlobal: true,
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
    }

    /**
     * Get the owner model (polymorphic relationship).
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
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
        /** @var \AIArmada\CommerceSupport\Support\OwnerScopeConfig $config */
        $config = static::resolveOwnerScopeConfig();

        if (! $config->enabled) {
            return $query;
        }

        $includeGlobal = $includeGlobal && $config->includeGlobal;
        $ownerTypeColumn = $config->ownerTypeColumn;
        $ownerIdColumn = $config->ownerIdColumn;

        if ($owner === OwnerContext::CURRENT) {
            $owner = OwnerContext::resolve();
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
        /** @var \AIArmada\CommerceSupport\Support\OwnerScopeConfig $config */
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
        return $this->owner_type !== null && $this->owner_id !== null;
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
        return $this->owner_type === $owner->getMorphClass()
            && $this->owner_id === $owner->getKey();
    }

    /**
     * Assign an owner to this model.
     */
    public function assignOwner(Model $owner): static
    {
        $this->owner_type = $owner->getMorphClass();
        $this->owner_id = $owner->getKey();

        return $this;
    }

    /**
     * Remove the owner from this model (make it global).
     */
    public function removeOwner(): static
    {
        $this->owner_type = null;
        $this->owner_id = null;

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
