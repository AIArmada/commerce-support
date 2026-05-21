<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Events\ForgettingCurrentOwnerEvent;
use AIArmada\CommerceSupport\Events\ForgotCurrentOwnerEvent;
use AIArmada\CommerceSupport\Events\MadeOwnerCurrentEvent;
use AIArmada\CommerceSupport\Events\MakingOwnerCurrentEvent;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use Illuminate\Database\Eloquent\Model;
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

        return $resolver->resolve();
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

        if ($owner !== null && ! self::sameOwner($previousOwner, $owner)) {
            event(new MakingOwnerCurrentEvent($owner));
        }

        if ($owner === null && $previousOwner !== null) {
            event(new ForgettingCurrentOwnerEvent($previousOwner));
        }

        $request->attributes->set(self::REQUEST_KEY, ['hasOverride' => true, 'override' => $owner]);

        if ($owner !== null && ! self::sameOwner($previousOwner, $owner)) {
            event(new MadeOwnerCurrentEvent($owner));
        }

        if ($owner === null && $previousOwner !== null) {
            event(new ForgotCurrentOwnerEvent($previousOwner));
        }
    }

    public static function hasOverride(): bool
    {
        return self::readState()['hasOverride'];
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
        $previous = self::readState();

        if ($owner !== null) {
            event(new MakingOwnerCurrentEvent($owner));
        }

        self::writeState(['hasOverride' => true, 'override' => $owner]);

        if ($owner !== null) {
            event(new MadeOwnerCurrentEvent($owner));
        }

        try {
            return $callback();
        } finally {
            if ($owner !== null) {
                event(new ForgettingCurrentOwnerEvent($owner));
            }

            self::writeState($previous);

            if ($owner !== null) {
                event(new ForgotCurrentOwnerEvent($owner));
            }
        }
    }

    public static function fromTypeAndId(?string $ownerType, string | int | null $ownerId): ?Model
    {
        if ($ownerType === '' || (is_string($ownerId) && $ownerId === '')) {
            throw new InvalidArgumentException('Owner type and owner id must not be empty strings.');
        }

        if ($ownerType === null || $ownerId === null) {
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
