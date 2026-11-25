<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Events\ForgettingCurrentOwnerEvent;
use AIArmada\CommerceSupport\Events\ForgotCurrentOwnerEvent;
use AIArmada\CommerceSupport\Events\MadeOwnerCurrentEvent;
use AIArmada\CommerceSupport\Events\MakingOwnerCurrentEvent;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class OwnerContext
{
    public const string CURRENT = '__commerce_support_current_owner__';

    /** Request attribute key used to store owner context state for the current HTTP request. */
    public const string REQUEST_KEY = '__commerce_owner_ctx__';

    /**
     * Non-HTTP fallback storage (console commands, queue jobs).
     * Only modified by withOwner() — always restored in finally — so it never leaks.
     *
     * @var array{hasOverride: bool, override: ?Model}
     */
    private static array $fallback = ['hasOverride' => false, 'override' => null];

    public static function resolveConfigured(OwnerScopeConfig $config): ?Model
    {
        return $config->owner === null
            ? self::resolve()
            : app(OwnerModelLookup::class)->resolve($config->owner);
    }

    public static function resolve(): ?Model
    {
        $state = self::readState();

        if ($state['hasOverride']) {
            return $state['override'];
        }

        if (! app()->bound(OwnerResolverInterface::class)) {
            return null;
        }

        /** @var OwnerResolverInterface $resolver */
        $resolver = app(OwnerResolverInterface::class);

        $reference = $resolver->resolve();

        return $reference === null ? null : app(OwnerModelLookup::class)->resolve($reference);
    }

    /**
     * Set the owner context for the lifetime of the current HTTP request.
     *
     * In HTTP contexts, state is stored on the request attributes bag, which Octane
     * discards automatically between worker cycles — no manual cleanup required.
     *
     * This is intended ONLY for middleware and framework-level integrations
     * (e.g., Spatie PermissionsTeamResolver). For scoped operations, prefer withOwner().
     *
     * @internal Framework middleware integration only.
     */
    public static function setForRequest(?Model $owner): void
    {
        $request = self::httpRequest();

        if (! $request instanceof Request) {
            throw new RuntimeException('OwnerContext::setForRequest() may only be used during an active HTTP request. Use OwnerContext::withOwner() for scoped non-HTTP operations.');
        }

        $previousState = self::readState();
        $previousOwner = $previousState['hasOverride'] ? $previousState['override'] : null;

        $switchingToOwner = $owner !== null && ! self::sameOwner($previousOwner, $owner);
        $forgettingPreviousOwner = $previousOwner !== null && ($owner === null || ! self::sameOwner($previousOwner, $owner));

        if ($switchingToOwner) {
            event(new MakingOwnerCurrentEvent($owner));
        }

        if ($forgettingPreviousOwner) {
            event(new ForgettingCurrentOwnerEvent($previousOwner));
        }

        $request->attributes->set(self::REQUEST_KEY, ['hasOverride' => true, 'override' => $owner]);

        try {
            if ($switchingToOwner) {
                event(new MadeOwnerCurrentEvent($owner));
            }

            if ($forgettingPreviousOwner) {
                event(new ForgotCurrentOwnerEvent($previousOwner));
            }
        } catch (Throwable $e) {
            $request->attributes->set(self::REQUEST_KEY, $previousState);

            throw $e;
        }
    }

    /**
     * Whether an active HTTP request can hold request-scoped owner state.
     *
     * @internal Framework integration support (e.g. team resolver).
     */
    public static function hasActiveRequest(): bool
    {
        return self::httpRequest() instanceof Request;
    }

    /**
     * Update the active owner override in-place without starting a scope.
     *
     * Non-HTTP contexts only. A no-op when no scoped override is active:
     * persisting process-wide owner state would leak across queued jobs.
     *
     * @internal Framework integration support (e.g. team resolver).
     */
    public static function updateActiveOverride(?Model $owner): void
    {
        $state = self::readState();

        if (! $state['hasOverride']) {
            return;
        }

        self::writeState(['hasOverride' => true, 'override' => $owner]);
    }

    public static function hasOverride(): bool
    {
        return self::readState()['hasOverride'];
    }

    /**
     * Flush owner state at an application lifecycle boundary.
     *
     * @internal Octane lifecycle integration only.
     */
    public static function flushState(): void
    {
        if (app()->resolved(OwnerModelLookup::class)) {
            app(OwnerModelLookup::class)->clear();
        }

        self::$fallback = ['hasOverride' => false, 'override' => null];

        $request = self::httpRequest();

        if ($request !== null) {
            $request->attributes->remove(self::REQUEST_KEY);
        }
    }

    public static function isExplicitGlobal(): bool
    {
        $state = self::readState();

        return $state['hasOverride'] && $state['override'] === null;
    }

    public static function assertResolvedOrExplicitGlobal(?Model $owner, ?string $message = null): void
    {
        if ($owner !== null || self::isExplicitGlobal()) {
            return;
        }

        throw new NoCurrentOwnerException($message ?? 'Owner context is required for this owner-protected operation. Use OwnerContext::withOwner(null, ...) for explicit global access.');
    }

    public static function withOwner(?Model $owner, callable $callback): mixed
    {
        $capturedRequest = self::httpRequest();
        $previous = self::readState();
        $previousOwner = $previous['hasOverride'] ? $previous['override'] : null;

        $displacingOwner = $previousOwner !== null && ! self::sameOwner($previousOwner, $owner);
        $restoringPreviousOwner = $previousOwner !== null && ($owner === null || ! self::sameOwner($previousOwner, $owner));

        if ($owner !== null) {
            event(new MakingOwnerCurrentEvent($owner));
        }

        if ($displacingOwner) {
            event(new ForgettingCurrentOwnerEvent($previousOwner));
        }

        self::writeState(['hasOverride' => true, 'override' => $owner]);

        try {
            if ($owner !== null) {
                event(new MadeOwnerCurrentEvent($owner));
            }

            if ($displacingOwner) {
                event(new ForgotCurrentOwnerEvent($previousOwner));
            }
        } catch (Throwable $e) {
            self::restoreState($capturedRequest, $previous);

            throw $e;
        }

        try {
            return $callback();
        } finally {
            if ($owner !== null) {
                try {
                    event(new ForgettingCurrentOwnerEvent($owner));
                } finally {
                    self::restoreState($capturedRequest, $previous);
                }

                event(new ForgotCurrentOwnerEvent($owner));
            } else {
                self::restoreState($capturedRequest, $previous);
            }

            if ($restoringPreviousOwner) {
                event(new MakingOwnerCurrentEvent($previousOwner));
                event(new MadeOwnerCurrentEvent($previousOwner));
            }
        }
    }

    public static function fromTypeAndId(?string $ownerType, string | int | null $ownerId): ?Model
    {
        if ($ownerType === '' || (is_string($ownerId) && $ownerId === '')) {
            throw new InvalidArgumentException('Owner type and owner id must not be empty strings.');
        }

        if (($ownerType === null) !== ($ownerId === null)) {
            throw new InvalidArgumentException('Owner type and owner id must both be present or both be null.');
        }

        if ($ownerType === null && $ownerId === null) {
            return null;
        }

        $resolved = Relation::getMorphedModel($ownerType) ?? $ownerType;

        if (! class_exists($resolved)) {
            throw new InvalidArgumentException(sprintf('Owner type "%s" could not be resolved to a model class.', $ownerType));
        }

        if (! is_a($resolved, Model::class, true)) {
            throw new InvalidArgumentException(sprintf('Owner type "%s" must resolve to an Eloquent model.', $ownerType));
        }

        /** @var Model $owner */
        $owner = new $resolved;
        $owner->setAttribute($owner->getKeyName(), $ownerId);

        return $owner;
    }

    /**
     * Resolve an owner tuple to a persisted model, failing on orphaned tuples.
     *
     * Unlike fromTypeAndId() — which instantiates without querying — this
     * verifies the owner row exists and returns the fresh model. Use it on
     * trust boundaries (queued jobs, batch runners, inbound payloads) where
     * an orphaned or forged tuple must never yield a working owner scope.
     *
     * @throws InvalidArgumentException When the tuple is malformed or unresolvable.
     * @throws ModelNotFoundException When the owner row does not exist.
     */
    public static function fromTypeAndIdOrFail(?string $ownerType, string | int | null $ownerId): ?Model
    {
        if (($ownerType === null) !== ($ownerId === null)) {
            throw new InvalidArgumentException('Owner type and owner id must both be present or both be null.');
        }

        $owner = self::fromTypeAndId($ownerType, $ownerId);

        if ($owner === null) {
            return null;
        }

        $fresh = $owner->newQuery()->whereKey($owner->getKey())->first();

        if (! $fresh instanceof Model) {
            throw (new ModelNotFoundException)->setModel($owner::class, $owner->getKey());
        }

        return $fresh;
    }

    /**
     * @return array{hasOverride: bool, override: ?Model}
     */
    private static function readState(): array
    {
        $request = self::httpRequest();

        if ($request !== null) {
            /** @var array{hasOverride: bool, override: ?Model}|null $stored */
            $stored = $request->attributes->get(self::REQUEST_KEY);

            return $stored ?? ['hasOverride' => false, 'override' => null];
        }

        return self::$fallback;
    }

    /**
     * @param  array{hasOverride: bool, override: ?Model}  $state
     */
    private static function writeState(array $state): void
    {
        $request = self::httpRequest();

        if ($request !== null) {
            $request->attributes->set(self::REQUEST_KEY, $state);

            return;
        }

        self::$fallback = $state;
    }

    /**
     * Restore state onto the request captured at scope entry, so a request
     * swapped in during the callback cannot receive the restored state.
     *
     * @param  array{hasOverride: bool, override: ?Model}  $state
     */
    private static function restoreState(?Request $request, array $state): void
    {
        if ($request !== null) {
            $request->attributes->set(self::REQUEST_KEY, $state);

            return;
        }

        self::$fallback = $state;
    }

    private static function httpRequest(): ?Request
    {
        try {
            $request = app('request');

            return $request instanceof Request ? $request : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function sameOwner(?Model $left, ?Model $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        return $left->getMorphClass() === $right->getMorphClass()
            && (string) $left->getKey() === (string) $right->getKey();
    }
}
