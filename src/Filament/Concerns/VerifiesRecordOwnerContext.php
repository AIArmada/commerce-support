<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Filament\Concerns;

use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Locked;
use Stringable;

/**
 * Re-verifies a Livewire `record` prop against the current owner scope on every request.
 *
 * Livewire restores public model props via `newQueryForRestoration()`, which bypasses
 * Eloquent global scopes — including `OwnerScope`. Without re-verification, a record
 * widget keeps rendering its mount-time record after a mid-session owner change.
 *
 * This trait stamps the mount-time effective owner context (a configured fixed
 * owner wins over ambient resolution, mirroring `OwnerScope`) plus record
 * identity into a locked snapshot prop, then fails closed on every subsequent
 * request (clears the record,
 * so the widget renders its empty state and write actions no-op) when:
 *
 * - the owner context changed since mount,
 * - the record identity or owner tuple changed since mount,
 * - the record is no longer visible in the current scope,
 * - no stamp exists (e.g. a snapshot predating this guard).
 *
 * Hook wiring is automatic: Livewire invokes `mount{Trait}` / `hydrate{Trait}` for
 * every used trait, so consuming widgets only `use` this trait. Mount-time
 * verification also covers lazy widgets, whose mount runs on a later request.
 *
 * The guard skips silently (nothing to protect) when the record is null,
 * not owner-scoped, or owner scoping is disabled for the model. Unsaved
 * records are kept only when no stamp exists; a stamped record that no longer
 * exists was deleted after mount and fails closed.
 *
 * Consuming components must declare a nullable model prop (named `record` by
 * default, overridable via `ownerGuardedPropName()`) and treat it as
 * mount-time input: replacing it mid-lifecycle invalidates the stamp and
 * fails closed on the next request.
 *
 * Restored models arrive as lazy proxies; if the row was deleted, first
 * access throws `ModelNotFoundException`, which the guard converts to the
 * same fail-closed empty state.
 *
 * @property Model|null $record Filament record-widget convention (`public ?Model $record`).
 */
trait VerifiesRecordOwnerContext
{
    #[Locked]
    public ?string $recordOwnerStamp = null;

    public function mountVerifiesRecordOwnerContext(): void
    {
        try {
            $this->verifyGuardedRecordAtMount();
        } catch (ModelNotFoundException) {
            $this->failOwnerGuard();
        }
    }

    public function hydrateVerifiesRecordOwnerContext(): void
    {
        try {
            $this->verifyGuardedRecordAtHydrate();
        } catch (ModelNotFoundException) {
            $this->failOwnerGuard();
        }
    }

    /**
     * Name of the guarded model prop. Defaults to Filament's `record`
     * convention; components holding their record under another name
     * override this.
     */
    protected function ownerGuardedPropName(): string
    {
        return 'record';
    }

    /**
     * Failure mode when the guarded record no longer verifies. Defaults to
     * clearing the record (the widget renders its empty state); consumers
     * that cannot render empty — e.g. relation managers with a non-nullable
     * owner record — override this to abort instead.
     */
    protected function failOwnerGuard(): void
    {
        $this->clearGuardedRecord();
    }

    private function verifyGuardedRecordAtMount(): void
    {
        $record = $this->recordUnderOwnerGuard();

        if (! $record instanceof Model || ! $record->exists) {
            $this->recordOwnerStamp = null;

            return;
        }

        $config = self::ownerScopeConfigForRecord($record);

        if ($config === null || ! $config->enabled) {
            $this->recordOwnerStamp = null;

            return;
        }

        if (! self::recordIsVisibleInCurrentScope($record, $config)) {
            $this->failOwnerGuard();

            return;
        }

        $this->recordOwnerStamp = self::buildRecordOwnerStamp($record, $config);
    }

    private function verifyGuardedRecordAtHydrate(): void
    {
        $record = $this->recordUnderOwnerGuard();

        if (! $record instanceof Model) {
            $this->recordOwnerStamp = null;

            return;
        }

        if (! $record->exists) {
            // A stamped record that no longer exists was deleted after mount:
            // fail closed. Without a stamp the record was never persisted, so
            // keep the in-memory state instead of destroying user input.
            if ($this->recordOwnerStamp !== null) {
                $this->failOwnerGuard();
            } else {
                $this->recordOwnerStamp = null;
            }

            return;
        }

        $config = self::ownerScopeConfigForRecord($record);

        if ($config === null || ! $config->enabled) {
            $this->recordOwnerStamp = null;

            return;
        }

        if ($this->recordOwnerStamp === null || $this->recordOwnerStamp !== self::buildRecordOwnerStamp($record, $config)) {
            $this->failOwnerGuard();

            return;
        }

        if (! self::recordIsVisibleInCurrentScope($record, $config)) {
            $this->failOwnerGuard();
        }
    }

    /**
     * The guarded record prop, or null when the component holds no model.
     */
    private function recordUnderOwnerGuard(): ?Model
    {
        $prop = $this->ownerGuardedPropName();

        if (! property_exists($this, $prop)) {
            return null;
        }

        $record = $this->{$prop};

        return $record instanceof Model ? $record : null;
    }

    /**
     * Fail closed: drop the stale record and its stamp.
     */
    private function clearGuardedRecord(): void
    {
        $prop = $this->ownerGuardedPropName();

        if (property_exists($this, $prop)) {
            $this->{$prop} = null;
        }

        $this->recordOwnerStamp = null;
    }

    /**
     * Resolve the model's owner scope config, or null when the model is not
     * owner-scoped. Models without `ownerScopeConfig()` fall back to the same
     * enabled-by-default contract `HasOwner` boots with.
     */
    private static function ownerScopeConfigForRecord(Model $record): ?OwnerScopeConfig
    {
        if (! in_array(HasOwner::class, class_uses_recursive($record), true)) {
            return null;
        }

        return self::ownerScopeConfigForModelClass($record::class);
    }

    /**
     * @param  class-string  $modelClass
     */
    private static function ownerScopeConfigForModelClass(string $modelClass): OwnerScopeConfig
    {
        if (! method_exists($modelClass, 'ownerScopeConfig')) {
            return new OwnerScopeConfig(enabled: true);
        }

        /** @var OwnerScopeConfig $config */
        $config = $modelClass::ownerScopeConfig();

        return $config;
    }

    /**
     * Effective owner context for a record: a configured fixed owner wins
     * over ambient resolution (mirroring `OwnerScope`), making the guard
     * immune to context changes for pinned models.
     *
     * @return array{owner: ?Model, explicitGlobal: bool}
     */
    private static function effectiveOwnerContext(OwnerScopeConfig $config): array
    {
        if ($config->owner !== null) {
            return ['owner' => $config->owner, 'explicitGlobal' => false];
        }

        return ['owner' => OwnerContext::resolve(), 'explicitGlobal' => OwnerContext::isExplicitGlobal()];
    }

    /**
     * Deterministic stamp of the current owner context plus record identity.
     * All scalars are normalized so int/string key forms compare equal.
     */
    private static function buildRecordOwnerStamp(Model $record, OwnerScopeConfig $config): string
    {
        ['owner' => $owner, 'explicitGlobal' => $explicitGlobal] = self::effectiveOwnerContext($config);

        $stamp = [
            'v' => 1,
            'ctx' => [
                't' => $owner?->getMorphClass(),
                'i' => $owner === null ? null : (string) $owner->getKey(),
                'g' => $explicitGlobal,
            ],
            'rec' => [
                'c' => $record::class,
                'k' => (string) $record->getKey(),
                't' => self::stampScalar($record->getAttribute($config->ownerTypeColumn)),
                'i' => self::stampScalar($record->getAttribute($config->ownerIdColumn)),
            ],
        ];

        return json_encode($stamp, JSON_THROW_ON_ERROR);
    }

    private static function stampScalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Fresh scoped visibility check for the record key in the current context.
     * Only the owner scope is replaced (with current config); any other model
     * scopes still apply. Missing owner context fails closed; unexpected
     * errors propagate loudly.
     */
    private static function recordIsVisibleInCurrentScope(Model $record, OwnerScopeConfig $config): bool
    {
        try {
            ['owner' => $owner, 'explicitGlobal' => $explicitGlobal] = self::effectiveOwnerContext($config);

            if ($owner === null && ! $explicitGlobal) {
                return false;
            }

            return OwnerQuery::applyToEloquentBuilder(
                $record->newQuery()->withoutGlobalScope(OwnerScope::class),
                $owner,
                OwnerScopeOverride::suppressIncludeGlobal() ? false : $config->includeGlobal,
                $config->ownerTypeColumn,
                $config->ownerIdColumn,
            )->whereKey($record->getKey())->exists();
        } catch (NoCurrentOwnerException) {
            return false;
        }
    }
}
